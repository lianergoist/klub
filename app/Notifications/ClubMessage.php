<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Én generisk besked: vises i appens indbakke og sendes på mail, hvis medlemmet har valgt det.
 * Push-beskeder kobles på samme sted i næste fase.
 */
class ClubMessage extends Notification
{
    public function __construct(public string $title, public string $body, public ?string $link = null) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notify_email && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)->subject($this->title)->greeting('Hej '.$notifiable->name)->line($this->body);
        if ($this->link) {
            $m->action('Åbn i klubappen', url($this->link));
        }

        return $m;
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'link' => $this->link];
    }
}
