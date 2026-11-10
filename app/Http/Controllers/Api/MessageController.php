<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Notifications\MessageReceived;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Private beskeder mellem medlemmer – to eller flere i samme samtale (testudgave).
 * Indholdet er privat: bestyrelsen ser kun en besked, hvis en deltager anmelder den.
 */
class MessageController extends Controller
{
    private function me(Request $r): User
    {
        $u = $r->user();
        abort_unless($u->canUseMessages(), 403, 'Beskeder er kun for voksne medlemmer med eget login.');

        return $u;
    }

    private function mine(Request $r, Conversation $c): User
    {
        $u = $this->me($r);
        abort_unless($c->involves($u), 404);

        return $u;
    }

    /** Hvorfor kan $u ikke skrive i samtalen? null = må gerne. */
    private function cannotSend(User $u, Conversation $c): ?string
    {
        $others = $c->members->where('id', '!=', $u->id);
        if ($others->isEmpty()) return 'Der er ikke andre i samtalen.';
        if (! $c->is_group) return $u->cannotMessage($others->first());
        // Gruppe: har man selv fravalgt beskeder, kan man kun skrive i grupper startet af trænere/bestyrelse
        if (! $u->allow_messages && ! $u->isStaff() && ! User::find($c->created_by)?->isStaff()) {
            return 'Du har slået beskeder fra, så du kan kun skrive i grupper startet af trænere eller bestyrelse.';
        }

        return null;
    }

    public function index(Request $r)
    {
        $u = $this->me($r);
        $convs = Conversation::for($u)->whereNotNull('last_message_at')->with('members:id,name', 'latestMessage.sender:id,name')
            ->orderByDesc('last_message_at')->limit(100)->get();
        $blocked = $u->blockedIds();

        return $convs->map(function ($c) use ($u, $blocked) {
            $lastRead = $c->members->firstWhere('id', $u->id)?->pivot->last_read_message_id ?? 0;
            $unread = $c->messages()->where('id', '>', $lastRead)->where('system', false)
                ->where(fn ($q) => $q->where('sender_id', '!=', $u->id)->orWhereNull('sender_id'))->count();
            $m = $c->latestMessage;
            $prefix = ! $m || $m->system ? '' : ($m->sender_id === $u->id ? 'Dig: ' : ($c->is_group ? explode(' ', $m->sender?->name ?? '')[0].': ' : ''));
            $other = $c->is_group ? null : $c->members->firstWhere('id', '!=', $u->id);

            return [
                'id' => $c->id,
                'name' => $c->displayName($u),
                'is_group' => $c->is_group,
                'count' => $c->members->count(),
                'last' => $prefix.mb_strimwidth($m?->body ?? '', 0, 90, '…'),
                'at' => $c->last_message_at?->toIso8601String(),
                'unread' => $unread,
                'blocked' => $other && in_array($other->id, $blocked),
            ];
        });
    }

    public function unread(Request $r)
    {
        return ['messages' => $r->user()->canUseMessages() ? $r->user()->unreadMessages() : 0, 'notifications' => $r->user()->unreadNotifications()->count()];
    }

    /** Alle medlemmer, man kan skrive til (kun navn vises). Medlemmer der har fravalgt beskeder er ikke med. */
    public function people(Request $r)
    {
        $u = $this->me($r);
        $u->loadMissing('roleAssignments');
        $trainers = User::whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['trainer', 'board'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))->pluck('id')->flip();

        return $u->messageableQuery()->orderBy('name')->get(['id', 'name', 'allow_messages'])->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'trainer' => isset($trainers[$p->id]),
            'opted_out' => ! $p->allow_messages, // kun synlig for trænere/bestyrelse, som stadig kan skrive
        ]);
    }

    /** Start samtale: én modtager => genbrug 1:1-samtalen. Flere => ny gruppe. */
    public function start(Request $r)
    {
        $u = $this->me($r);
        $d = $r->validate(['user_ids' => 'required|array|min:1|max:30', 'user_ids.*' => 'integer', 'title' => 'nullable|string|max:80']);
        $ids = array_values(array_unique(array_diff($d['user_ids'], [$u->id])));
        abort_if(! $ids, 422, 'Vælg mindst ét medlem.');
        $people = User::whereIn('id', $ids)->get();
        abort_if($people->count() !== count($ids), 422, 'Et af de valgte medlemmer findes ikke.');
        foreach ($people as $p) {
            if ($why = $u->cannotMessage($p)) abort(422, $why);
        }

        if (count($ids) === 1) {
            return ['id' => Conversation::between($u, $people->first())->id];
        }

        $c = DB::transaction(function () use ($u, $ids, $d) {
            $c = Conversation::create(['is_group' => true, 'title' => trim($d['title'] ?? '') ?: null, 'created_by' => $u->id]);
            $c->members()->attach(array_merge([$u->id], $ids));

            return $c;
        });
        Audit::log('conversation.group_created', $c, ['members' => count($ids) + 1]);

        return ['id' => $c->id];
    }

    public function show(Request $r, Conversation $conversation)
    {
        $u = $this->mine($r, $conversation);
        $conversation->load('members.roleAssignments'); // fulde brugere: reglerne kræver login, status, fravalg m.m.
        $members = $conversation->members;

        $q = $conversation->messages()->with('sender:id,name')->orderByDesc('id');
        if ($after = $r->query('after')) $q->where('id', '>', $after);
        if ($before = $r->query('before')) $q->where('id', '<', $before);
        $msgs = $q->limit(50)->get()->reverse()->values();

        $maxId = $conversation->messages()->max('id') ?? 0;
        $conversation->members()->updateExistingPivot($u->id, ['last_read_message_id' => $maxId]);

        $others = $members->where('id', '!=', $u->id);
        $other = $conversation->is_group ? null : $others->first();
        $why = $this->cannotSend($u, $conversation);

        return [
            'id' => $conversation->id,
            'name' => $conversation->displayName($u),
            'title' => $conversation->title,
            'is_group' => $conversation->is_group,
            'members' => $members->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'me' => $m->id === $u->id])->sortBy('name')->values(),
            'with' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
            'can_send' => $why === null,
            'cannot_reason' => $why,
            'i_blocked' => $other && in_array($other->id, $u->blockedIds()),
            'more' => ! $after && $msgs->count() === 50,
            // Hvor langt har de andre læst? Bruges til "Læst" / "Læst af 3"
            'read_upto' => $others->map(fn ($m) => ['name' => explode(' ', $m->name)[0], 'id' => (int) $m->pivot->last_read_message_id])->values(),
            'messages' => $msgs->map(fn ($m) => [
                'id' => $m->id, 'body' => $m->body, 'mine' => $m->sender_id === $u->id, 'system' => $m->system,
                'sender' => $m->sender?->name, 'sender_id' => $m->sender_id, 'at' => $m->created_at->toIso8601String(),
            ]),
        ];
    }

    public function send(Request $r, Conversation $conversation)
    {
        $u = $this->mine($r, $conversation);
        $conversation->load('members.roleAssignments');
        $others = $conversation->members->where('id', '!=', $u->id);
        if ($why = $this->cannotSend($u, $conversation)) abort(422, $why);
        $body = trim($r->validate(['body' => 'required|string|max:4000'])['body']);
        abort_if($body === '', 422, 'Skriv en besked.');

        $m = $conversation->messages()->create(['sender_id' => $u->id, 'body' => $body]);
        $conversation->update(['last_message_at' => now()]);
        $conversation->members()->updateExistingPivot($u->id, ['last_read_message_id' => $m->id]);

        // Mail kun til deltagere, der ikke allerede har ulæste beskeder i samtalen
        foreach ($others as $o) {
            $hadUnread = $conversation->messages()->where('id', '<', $m->id)->where('id', '>', $o->pivot->last_read_message_id)
                ->where('sender_id', '!=', $o->id)->where('system', false)->exists();
            if (! $hadUnread) $o->notify(new MessageReceived($u->name, $conversation->id, $conversation->is_group ? $conversation->displayName($o) : null));
        }

        return ['id' => $m->id, 'at' => $m->created_at->toIso8601String()];
    }

    /** Gruppe: tilføj flere deltagere. */
    public function addMembers(Request $r, Conversation $conversation)
    {
        $u = $this->mine($r, $conversation);
        abort_unless($conversation->is_group, 422, 'Start en ny gruppe for at skrive til flere.');
        $ids = $r->validate(['user_ids' => 'required|array|min:1', 'user_ids.*' => 'integer'])['user_ids'];
        $names = [];
        foreach (User::whereIn('id', $ids)->get() as $p) {
            if ($why = $u->cannotMessage($p)) abort(422, $why);
            $conversation->members()->syncWithoutDetaching([$p->id => ['left_at' => null]]);
            $names[] = $p->name;
        }
        $conversation->messages()->create(['sender_id' => null, 'system' => true, 'body' => explode(' ', $u->name)[0].' tilføjede '.implode(', ', $names)]);

        return ['ok' => true];
    }

    public function rename(Request $r, Conversation $conversation)
    {
        $u = $this->mine($r, $conversation);
        abort_unless($conversation->is_group, 422);
        $title = trim((string) $r->validate(['title' => 'nullable|string|max:80'])['title']) ?: null;
        $conversation->update(['title' => $title]);
        $conversation->messages()->create(['sender_id' => null, 'system' => true, 'body' => explode(' ', $u->name)[0].($title ? ' kaldte gruppen "'.$title.'"' : ' fjernede gruppens navn')]);

        return ['ok' => true];
    }

    /** Forlad en gruppe. Man ser den ikke længere og får ikke flere beskeder. */
    public function leave(Request $r, Conversation $conversation)
    {
        $u = $this->mine($r, $conversation);
        abort_unless($conversation->is_group, 422, 'Du kan blokere i stedet for at forlade en samtale med én person.');
        $conversation->members()->updateExistingPivot($u->id, ['left_at' => now()]);
        $conversation->messages()->create(['sender_id' => null, 'system' => true, 'body' => explode(' ', $u->name)[0].' forlod samtalen']);

        return ['ok' => true];
    }

    public function block(Request $r, User $user)
    {
        $u = $this->me($r);
        abort_if($user->id === $u->id, 422);
        $block = (bool) $r->validate(['block' => 'required|boolean'])['block'];
        $block ? DB::table('user_blocks')->insertOrIgnore(['user_id' => $u->id, 'blocked_id' => $user->id, 'created_at' => now()])
               : DB::table('user_blocks')->where('user_id', $u->id)->where('blocked_id', $user->id)->delete();
        Audit::log($block ? 'user.blocked' : 'user.unblocked', $user);

        return ['ok' => true];
    }

    /** En deltager anmelder en besked. Bestyrelsen får beskedens tekst – ellers ser ingen indholdet. */
    public function report(Request $r, Message $message)
    {
        $u = $this->me($r);
        abort_unless($message->conversation->involves($u) && $message->sender_id && $message->sender_id !== $u->id, 403);
        $reason = $r->validate(['reason' => 'required|string|max:300'])['reason'];
        $message->load('sender');
        DB::table('message_reports')->insert(['message_id' => $message->id, 'user_id' => $u->id, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
        Audit::log('message.reported', $message, ['reason' => $reason]);
        User::whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['board', 'admin'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))
            ->get()->each->notify(new ClubMessage(
                'Besked anmeldt',
                $u->name.' har anmeldt en besked fra '.$message->sender->name.' ('.$reason.'): "'.mb_strimwidth($message->body, 0, 300, '…').'"',
                '/medlemmer/'.$message->sender_id
            ));

        return ['ok' => true];
    }
}
