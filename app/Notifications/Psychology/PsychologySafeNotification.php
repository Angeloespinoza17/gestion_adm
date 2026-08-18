<?php

namespace App\Notifications\Psychology;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PsychologySafeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $title, private readonly string $message, private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->url, 'module' => 'psychology'];
    }
}
