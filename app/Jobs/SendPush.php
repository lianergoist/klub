<?php

namespace App\Jobs;

use App\Support\Push\Fcm;
use Illuminate\Foundation\Bus\Dispatchable;

/** Sendes efter svaret til brugeren, så en tilmelding ikke venter på Firebase. */
class SendPush
{
    use Dispatchable;

    public function __construct(public int $userId, public string $title, public string $body, public ?string $link = null) {}

    public function handle(): void
    {
        try {
            Fcm::sendToUser($this->userId, $this->title, $this->body, $this->link);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
