<?php

namespace App\Support;

use App\Models\AuditLog;

class Audit
{
    /** Handlinger, der vedrører regnskabet (vises i Økonomi → Ændringslog). Ender på punktum = alle handlinger med det præfiks. */
    public const FINANCE = [
        'entry.', 'payment.', 'payouts.', 'budget.', 'expense_claim.', 'price.', 'invoice.', 'account.', 'purchase.', 'product.ordered',
        'membership.generated', 'membership.guest_credit', 'membership.legacy_upgrade_cancelled',
        'package.purchased', 'package.purchase_cancelled', 'household.upgrade_requested', 'household.upgrade_cancelled',
    ];

    public static function log(string $action, $subject = null, array $changes = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->id,
            'changes' => $changes ?: null,
            'created_at' => now(),
        ]);
    }

    /** De seneste 300 linjer – kun regnskab eller hele loggen. */
    public static function rows(bool $financeOnly)
    {
        return AuditLog::with('user')
            ->when($financeOnly, fn ($q) => $q->where(function ($q) {
                foreach (self::FINANCE as $a) {
                    str_ends_with($a, '.') ? $q->orWhere('action', 'like', $a.'%') : $q->orWhere('action', $a);
                }
            }))
            ->latest('id')->limit(300)->get()->map(fn ($l) => [
                'id' => $l->id, 'at' => $l->created_at->toIso8601String(), 'by' => $l->user?->name ?? 'System',
                'action' => $l->action, 'subject' => $l->subject_type ? $l->subject_type.' #'.$l->subject_id : null, 'changes' => $l->changes,
            ]);
    }
}
