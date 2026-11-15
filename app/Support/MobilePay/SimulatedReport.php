<?php

namespace App\Support\MobilePay;

use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * KUN TIL PROOF OF CONCEPT: laver en afregningsrapport ud fra klubbens egne MobilePay-/kortbetalinger,
 * i samme form som Vipps MobilePays rapport. Erstattes af et kald til deres rapport-API i drift.
 *
 * Antagelser (skal bekræftes i klubbens aftale): udbetaling hver bankdag for betalinger fra dagen før,
 * gebyr 1 % pr. betaling (min. 0), intet gebyr på refusioner.
 */
class SimulatedReport
{
    public const FEE_PERCENT = 1.0;

    public static function fee(int $amount): int
    {
        return $amount > 0 ? (int) round($amount * self::FEE_PERCENT / 100) : 0;
    }

    /** Bankdag efter betalingsdagen (lørdag/søndag springes over). */
    public static function payoutDate(Carbon $paidAt): Carbon
    {
        $d = $paidAt->copy()->startOfDay()->addDay();
        while ($d->isWeekend()) $d->addDay();

        return $d;
    }

    /** @param bool $now Demo: udbetal også det, der først ville blive udbetalt senere – med dags dato. */
    public static function settlements(bool $now = false): array
    {
        $open = Payment::whereNull('payout_id')->whereIn('method', Settlements::METHODS)->orderBy('created_at')->get();
        $today = now()->toDateString();
        $out = [];
        foreach ($open->groupBy(fn ($p) => min(self::payoutDate($p->created_at)->toDateString(), $now ? $today : '9999-12-31')) as $date => $items) {
            if ($date > $today) continue; // ikke udbetalt endnu
            $id = 'MP-PO-'.str_replace('-', '', $date);
            for ($n = 2; \App\Models\Payout::where('provider_payout_id', $id)->exists(); $n++) $id = 'MP-PO-'.str_replace('-', '', $date).'-'.$n; // flere demo-udbetalinger samme dag
            $out[] = [
                'payout_id' => $id,
                'date' => $date,
                'items' => $items->map(fn ($p) => ['reference' => $p->reference, 'amount' => $p->amount, 'fee' => self::fee($p->amount)])->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Demo: når intet venter på udbetaling, oprettes 3 dagsleje-betalinger med MobilePay fra tilfældige medlemmer,
     * så knappen "Hent afregningsrapport nu" altid har noget at vise. Ingen beskeder sendes til medlemmerne.
     */
    public static function createDemoPayments(): int
    {
        $pkg = \App\Models\Package::where('name', 'like', 'Dagsleje%')->whereNull('archived_at')->first();
        $amount = $pkg ? \App\Support\Prices::get($pkg->priceCode()) : 15000;
        $account = \App\Http\Controllers\Api\FinanceController::accountId('1200');
        $users = \App\Models\User::where('status', 'active')->inRandomOrder()->limit(3)->get();
        foreach ($users as $i => $u) {
            $at = now()->subMinutes(20 * ($i + 1));
            \Illuminate\Support\Facades\DB::transaction(function () use ($u, $at, $amount, $account, $pkg) {
                $desc = ($pkg?->name ?? 'Dagsleje').' – '.$u->name;
                $inv = \App\Models\Invoice::create(['payer_id' => $u->id, 'member_id' => $u->id, 'kind' => 'product', 'description' => $desc, 'amount' => $amount,
                    'status' => 'paid', 'due_on' => $at->copy()->addDays(14), 'account_id' => $account, 'paid_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
                $p = $inv->payments()->create(['amount' => $amount, 'method' => 'mobilepay', 'reference' => 'MOBILEPAY-'.$at->format('ymdHis').'-'.$inv->id, 'created_at' => $at, 'updated_at' => $at]);
                \App\Models\Entry::create(['entry_date' => $at->toDateString(), 'text' => $desc, 'account_id' => $account, 'amount' => $amount, 'payment_id' => $p->id]);
                \App\Support\Audit::log('payment.registered', $inv, ['method' => 'mobilepay', 'amount' => $amount, 'demo' => true]);
            });
        }

        return $users->count();
    }
}
