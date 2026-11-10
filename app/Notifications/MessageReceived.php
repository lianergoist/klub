<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Kun mail – selve beskeden vises under Indbakke → Beskeder. Indholdet sendes ikke med i mailen. */
class MessageReceived extends Notification
{
    public function __construct(public string $from, public int $conversationId, public ?string $group = null) {}

    public function via(object $n): array
    {
        $via = $n->notify_email && $n->email ? ['mail'] : [];
        if (PushChannel::wants($n)) $via[] = PushChannel::class;

        return $via;
    }

    /** Push viser ikke beskedens indhold på låseskærmen – kun hvem den er fra. */
    public function toPush(object $n): array
    {
        return ['title' => $this->group ? 'Ny besked i '.$this->group : 'Ny besked fra '.$this->from,
            'body' => $this->group ? $this->from.' har skrevet i gruppen' : 'Tryk for at læse', 'link' => '/beskeder/'.$this->conversationId];
    }

    public function toMail(object $n): MailMessage
    {
        return (new MailMessage)->subject($this->group ? 'Ny besked i '.$this->group : 'Ny besked fra '.$this->from)->greeting('Hej '.$n->name)
            ->line($this->from.' har skrevet '.($this->group ? 'i gruppen "'.$this->group.'"' : 'til dig').' i klubappen.')
            ->action('Læs beskeden', url('/beskeder/'.$this->conversationId));
    }
}
