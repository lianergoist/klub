<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Nyt opslag i en kategori, medlemmet følger. Sendes kun som push-besked (ikke mail eller indbakke). */
class PostPosted extends Notification
{
    public function __construct(public string $title, public string $body, public string $link) {}

    public function via(object $notifiable): array
    {
        return PushChannel::wants($notifiable) ? [PushChannel::class] : [];
    }

    public function toPush(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'link' => $this->link];
    }
}
