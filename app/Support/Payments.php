<?php

namespace App\Support;

use App\Models\Entry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\ClubMessage;
use Illuminate\Support\Facades\DB;

/**
 * Kæden: betaling gennemført -> regning betalt -> postering i regnskabet -> evt. tilmelding bekræftet -> kvittering.
 * I PoC'en kaldes markPaid() fra den simulerede MobilePay-skærm. I drift kaldes den fra udbyderens webhook.
 */
class Payments
{
    public static function markPaid(Invoice $invoice, string $method, ?User $by, ?string $reference = null): Payment
    {
        return DB::transaction(function () use ($invoice, $method, $by, $reference) {
            $invoice->refresh();
            abort_if($invoice->status !== 'open', 422, 'Regningen er ikke åben.');

            $payment = $invoice->payments()->create([
                'amount' => $invoice->amount,
                'method' => $method,
                'reference' => $reference ?? strtoupper($method).'-'.now()->format('ymdHis').'-'.$invoice->id,
                'registered_by' => $by?->id,
            ]);

            $invoice->update(['status' => 'paid', 'paid_at' => now()]);

            Entry::create([
                'entry_date' => now()->toDateString(),
                'text' => $invoice->description.($invoice->member_id && $invoice->member_id !== $invoice->payer_id ? ' (betalt af '.$invoice->payer->name.')' : ''),
                'account_id' => $invoice->account_id,
                'amount' => $invoice->amount,
                'activity_id' => $invoice->activity_id,
                'payment_id' => $payment->id,
                'created_by' => $by?->id,
            ]);

            $reg = Registration::where('invoice_id', $invoice->id)->first();
            if ($reg && $reg->status === 'pending_payment') {
                $reg->update(['status' => 'signed_up']);
                $reg->log('paid', $by);
            }

            Memberships::onPaid($invoice->fresh());

            Audit::log('payment.registered', $invoice, ['method' => $method, 'amount' => $invoice->amount]);

            $invoice->payer->notify(new ClubMessage(
                'Kvittering: '.$invoice->description,
                'Vi har modtaget '.Money::fmt($invoice->amount).' ('.self::methodLabel($method).'). Tak!',
                '/profil/regninger'
            ));

            return $payment;
        });
    }

    public static function refund(Invoice $invoice, int $amount, User $approvedBy, string $reason): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $approvedBy, $reason) {
            abort_if($invoice->status !== 'paid', 422, 'Kun betalte regninger kan refunderes.');
            $already = -$invoice->payments()->where('amount', '<', 0)->sum('amount');
            abort_if($amount <= 0 || $amount + $already > $invoice->amount, 422, 'Ugyldigt beløb.');

            $payment = $invoice->payments()->create([
                'amount' => -$amount,
                'method' => $invoice->payments()->where('amount', '>', 0)->value('method') ?? 'bank',
                'reference' => 'REFUSION-'.$invoice->id.'-'.now()->format('ymdHis').': '.$reason, // unik, så udbyderens rapport kan kobles
                'registered_by' => $approvedBy->id,
                'approved_by' => $approvedBy->id,
            ]);

            Entry::create([
                'entry_date' => now()->toDateString(),
                'text' => 'Refusion: '.$invoice->description.' – '.$reason,
                'account_id' => $invoice->account_id,
                'amount' => -$amount,
                'activity_id' => $invoice->activity_id,
                'payment_id' => $payment->id,
                'created_by' => $approvedBy->id,
            ]);

            if ($amount + $already >= $invoice->amount) {
                $invoice->update(['status' => 'refunded']);
            }

            Audit::log('payment.refunded', $invoice, ['amount' => $amount, 'reason' => $reason]);
            $invoice->payer->notify(new ClubMessage('Refusion: '.$invoice->description, Money::fmt($amount).' bliver sendt tilbage. Årsag: '.$reason, '/profil/regninger'));

            return $payment;
        });
    }

    public static function methodLabel(string $m): string
    {
        return ['mobilepay' => 'MobilePay', 'card' => 'Betalingskort', 'cash' => 'Kontant', 'bank' => 'Bankoverførsel'][$m] ?? $m;
    }
}
