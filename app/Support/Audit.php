<?php

namespace App\Support;

use App\Models\AuditLog;

class Audit
{
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
}
