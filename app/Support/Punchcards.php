<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Purchase;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\ClubMessage;
use Illuminate\Support\Facades\DB;

/**
 * Klippekort for ikke-medlemmer (gæster).
 * Et kort er et køb af en pakke med klip (packages.uses), betalt og inden for gyldigheden.
 * Tilmelding til en aktivitet, der er åben for klippekort og i en af kortets kategorier, bruger et klip.
 * Afmelding inden afmeldingsfristen giver klippet tilbage; udeblivelse eller afmelding efter fristen gør ikke.
 */
class Punchcards
{
    /** Gæstens kort med klip tilbage, der stadig gælder (dem, der udløber først, bruges først). */
    public static function activeCards(User $u)
    {
        return Purchase::with('package.categories')->activeOn()->whereHas('users', fn ($q) => $q->whereKey($u->id))
            ->whereHas('package', fn ($q) => $q->where('uses', '>', 0))->where('uses_left', '>', 0)
            ->orderByRaw('valid_to IS NULL, valid_to')->get();
    }

    /** Det kort, der skal bruges til aktiviteten (kategori passer, og kortet gælder på aktivitetens dato). */
    public static function cardFor(User $u, Activity $a): ?Purchase
    {
        $cats = $a->categories->pluck('id');

        return self::activeCards($u)->first(fn ($p) => $p->package->categories->pluck('id')->intersect($cats)->isNotEmpty()
            && (! $p->valid_to || $p->valid_to->gte($a->starts_at->copy()->startOfDay())));
    }

    /** Kategorier, som et klippekort (der ikke er arkiveret) dækker. */
    public static function categoryIds(): array
    {
        return \Illuminate\Support\Facades\DB::table('category_package')->join('packages', 'packages.id', '=', 'category_package.package_id')
            ->where('packages.uses', '>', 0)->whereNull('packages.archived_at')->distinct()->pluck('category_id')->all();
    }

    /** Aktiviteter, gæsten kan se: kommende, ikke aflyst, åben for klippekort og i en kategori, et klippekort dækker. */
    public static function activitiesFor(User $u)
    {
        $cats = \App\Models\Package::where('uses', '>', 0)->whereNull('archived_at')->with('categories')->get()
            ->flatMap(fn ($p) => $p->categories->pluck('id'))->unique();

        return Activity::with('categories')->withCount(['registrations as signed_up_count' => fn ($q) => $q->where('status', 'signed_up')])
            ->where('punchcard', true)->where('status', 'active')->whereNull('cancelled_at')->where('ends_at', '>=', now())
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $cats))->orderBy('starts_at')->limit(60)->get();
    }

    public static function punch(User $u, Activity $a): Registration
    {
        abort_unless($a->punchcard, 422, 'Aktiviteten er ikke åben for klippekort.');
        abort_if($a->cancelled_at, 422, 'Aktiviteten er aflyst.');
        abort_if($a->isPending(), 422, 'Aktiviteten er ikke åben for tilmelding endnu.');
        abort_if($a->ends_at->isPast(), 422, 'Aktiviteten er afholdt.');
        abort_if($a->deadlinePassed(), 422, 'Svarfristen er overskredet.');

        return DB::transaction(function () use ($u, $a) {
            Activity::whereKey($a->id)->lockForUpdate()->first();
            $reg = Registration::firstOrNew(['activity_id' => $a->id, 'user_id' => $u->id]);
            abort_if($reg->exists && $reg->status === 'signed_up', 422, 'Du er allerede tilmeldt.');
            abort_if($a->isFull(), 422, 'Der er ikke flere pladser.');
            $card = self::cardFor($u, $a);
            abort_unless($card, 422, 'Du har ikke et gyldigt klippekort med klip tilbage til denne aktivitet.');
            $card = Purchase::whereKey($card->id)->lockForUpdate()->first();
            abort_unless($card->uses_left > 0, 422, 'Klippekortet er brugt op.');
            $card->decrement('uses_left');
            $was = $reg->exists && $reg->status === 'cancelled';
            $reg->fill(['status' => 'signed_up', 'purchase_id' => $card->id])->save();
            $reg->log($was ? 'resignup' : 'signup', $u);
            Audit::log('punchcard.used', $card, ['activity' => $a->id, 'uses_left' => $card->uses_left]);
            $u->notify(new ClubMessage('Tilmeldt: '.$a->title, $a->starts_at->locale('da')->isoFormat('dddd D. MMMM [kl.] HH:mm').'. Der er brugt et klip – du har '.$card->uses_left.' tilbage.', '/klip'));

            return $reg;
        });
    }

    public static function unpunch(User $u, Activity $a): void
    {
        $reg = Registration::where('activity_id', $a->id)->where('user_id', $u->id)->where('status', 'signed_up')->firstOrFail();
        abort_if($a->cancelLocked(), 422, 'Afmelding er lukket '.$a->cancel_hours.' timer før start. Klippet er brugt.');
        DB::transaction(function () use ($reg, $u) {
            $reg->update(['status' => 'cancelled']);
            $reg->log('cancel', $u);
            if ($reg->purchase_id && ($card = Purchase::whereKey($reg->purchase_id)->lockForUpdate()->first())) {
                $card->increment('uses_left');
                Audit::log('punchcard.returned', $card, ['activity' => $reg->activity_id, 'uses_left' => $card->uses_left]);
            }
        });
    }

    /** Demo/eksempel: SUP 10-turskort (foreløbig pris 600 kr.) og de almindelige SUP-aktiviteter åbnet for klippekort. */
    public static function createDemo(int $accountId, ?int $by, string $from): ?\App\Models\Package
    {
        if (\App\Models\Package::where('uses', '>', 0)->exists()) return null;
        $sup = \App\Models\Category::where('name', 'SUP')->first();
        if (! $sup) return null;
        $p = \App\Models\Package::create([
            'kind' => 'product', 'name' => 'SUP 10-turskort', 'description' => '10 SUP-træninger inden for 12 måneder. Tilmeld dig de ture, du vil med til – hver gang bruger ét klip. Det er sociale ture i roligt tempo. Man skal kunne stå op på boardet og padle, og selv kunne komme op når man falder i vandet.',
            'login_text' => 'Køb et klippekort til SUP-ture',
            'period_type' => 'days', 'period_days' => 365, 'total_min' => 1, 'total_max' => 1, 'uses' => 10, 'guest_only' => true,
            'self_service' => true, 'account_id' => $accountId, 'created_by' => $by, 'position' => 60,
        ]);
        foreach (\App\Models\PersonCategory::all() as $c) $p->slots()->create(['person_category_id' => $c->id, 'min' => 0, 'max' => 1]);
        $p->categories()->sync([$sup->id]);
        Prices::set($p->priceCode(), 100000, $from, $by);
        Activity::whereIn('title', ['Vinter-SUP i tørdragt', 'Vinterbad og SUP-hygge', 'Nytårs-SUP'])->update(['punchcard' => true]);
        Audit::log('package.created', $p, ['name' => $p->name, 'price' => '1.000,00 kr.']);

        return $p;
    }
}
