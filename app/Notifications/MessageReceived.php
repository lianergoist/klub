<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Kun mail – selve beskeden vises under Indbakke → Beskeder. Indholdet sendes ikke med i mailen. */
class MessageReceived extends Notification
{
    public function __construct(public string $from, public int $conversationId) {}

    public function via(object $n): array
    {
        return $n->notify_email && $n->email ? ['mail'] : [];
    }

    public function toMail(object $n): MailMessage
    {
        return (new MailMessage)->subject('Ny besked fra '.$this->from)->greeting('Hej '.$n->name)
            ->line($this->from.' har skrevet til dig i klubappen.')
            ->action('Læs beskeden', url('/beskeder/'.$this->conversationId));
    }
}
