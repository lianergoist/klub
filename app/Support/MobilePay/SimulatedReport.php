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

    public static function settlements(): array
    {
        $open = Payment::whereNull('payout_id')->whereIn('method', Settlements::METHODS)->orderBy('created_at')->get();
        $out = [];
        foreach ($open->groupBy(fn ($p) => self::payoutDate($p->created_at)->toDateString()) as $date => $items) {
            if ($date > now()->toDateString()) continue; // ikke udbetalt endnu
            $out[] = [
                'payout_id' => 'MP-PO-'.str_replace('-', '', $date),
                'date' => $date,
                'items' => $items->map(fn ($p) => ['reference' => $p->reference, 'amount' => $p->amount, 'fee' => self::fee($p->amount)])->values()->all(),
            ];
        }

        return $out;
    }
}
