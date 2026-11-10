<?php

namespace App\Notifications;

use App\Jobs\SendPush;
use App\Support\Push\Fcm;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/** Notifikationskanal: push-besked til medlemmets telefon(er), hvis de har appen og har push slået til. */
class PushChannel
{
    public static function wants(object $notifiable): bool
    {
        return Fcm::enabled() && ($notifiable->notify_push ?? false)
            && DB::table('device_tokens')->where('user_id', $notifiable->id)->exists();
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $p = $notification->toPush($notifiable);
        SendPush::dispatchAfterResponse($notifiable->id, $p['title'], $p['body'], $p['link'] ?? null);
    }
}
