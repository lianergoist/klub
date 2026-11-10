<?php

namespace App\Support\MobilePay;

use App\Http\Controllers\Api\FinanceController;
use App\Models\Entry;
use App\Models\Payment;
use App\Models\Payout;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Indlæser Vipps MobilePays afregningsrapport og afstemmer udbetalinger.
 *
 * Rapporten (fra SimulatedReport i proof of concept, fra Vipps MobilePays rapport-API i drift) er en liste af udbetalinger:
 *   ['payout_id' => 'MP-PO-…', 'date' => 'YYYY-MM-DD',
 *    'items' => [['reference' => '<vores betalingsreference>', 'amount' => øre, 'fee' => øre], …]]
 *
 * For hver ny udbetaling:
 *  - kobles hver betaling til udbetalingen via referencen (og gebyret gemmes på betalingen)
 *  - bogføres de samlede gebyrer som én udgift på kontoen Gebyrer
 * Indtægterne er allerede bogført brutto, da betalingen blev gennemført, så bogført brutto − gebyrer = det, banken modtager.
 */
class Settlements
{
    public const METHODS = ['mobilepay', 'card']; // betalinger der afregnes via udbyderen

    /** @return array{created:int, payments:int, fees:int, unknown:array} */
    public static function import(array $report, ?int $userId = null): array
    {
        $created = 0; $payments = 0; $fees = 0; $unknown = [];

        foreach ($report as $s) {
            if (Payout::where('provider_payout_id', $s['payout_id'])->exists()) continue; // allerede indlæst

            DB::transaction(function () use ($s, $userId, &$created, &$payments, &$fees, &$unknown) {
                $gross = array_sum(array_column($s['items'], 'amount'));
                $fee = array_sum(array_column($s['items'], 'fee'));
                $payout = Payout::create([
                    'provider_payout_id' => $s['payout_id'], 'payout_date' => $s['date'],
                    'gross' => $gross, 'fees' => $fee, 'net' => $gross - $fee, 'payment_count' => count($s['items']),
                ]);
                foreach ($s['items'] as $it) {
                    $p = Payment::where('reference', $it['reference'])->whereIn('method', self::METHODS)->first();
                    if (! $p) { $unknown[] = $it['reference']; continue; }
                    $p->update(['payout_id' => $payout->id, 'fee' => $it['fee']]);
                    $payments++;
                }
                if ($fee) {
                    $entry = Entry::create([
                        'entry_date' => $s['date'], 'account_id' => FinanceController::accountId('2600'), 'amount' => -$fee,
                        'text' => 'MobilePay-gebyrer, udbetaling '.$s['payout_id'].' ('.count($s['items']).(count($s['items']) === 1 ? ' betaling)' : ' betalinger)'),
                        'created_by' => $userId,
                    ]);
                    $payout->update(['fee_entry_id' => $entry->id]);
                }
                $created++; $fees += $fee;
            });
        }

        if ($created) Audit::log('payouts.imported', null, ['payouts' => $created, 'payments' => $payments, 'fees' => Money::fmt($fees)]);

        return ['created' => $created, 'payments' => $payments, 'fees' => $fees, 'unknown' => $unknown];
    }

    /** Hent rapport og indlæs. I drift: kald Vipps MobilePays rapport-API i stedet for simulatoren. */
    public static function fetchAndImport(?int $userId = null): array
    {
        return self::import(SimulatedReport::settlements(), $userId);
    }
}
