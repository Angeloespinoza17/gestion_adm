<?php

namespace App\Notifications\RiskPrevention;

use App\Models\RiskPrevention\RiskMatrixVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RiskMatrixNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly RiskMatrixVersion $version,
        private readonly string $priority = 'alta',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'icon' => 'bx bx-shield-quarter',
            'priority' => $this->priority,
            'action_url' => '/risk-prevention/matrices/'.$this->version->risk_matrix_id.'/versions/'.$this->version->id,
            'risk_matrix_id' => $this->version->risk_matrix_id,
            'risk_matrix_version_id' => $this->version->id,
        ];
    }
}
