<?php

namespace App\Notifications\RiskPrevention;

use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Support\Notifications\NotificationEnvelope;
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
        return NotificationEnvelope::make(
            eventKey: 'risk.matrix.notice:'.$this->version->id.':'.hash('sha256', $this->title),
            eventType: 'risk.matrix.notice',
            module: 'risk_prevention',
            title: $this->title,
            message: $this->message,
            resource: [
                'type' => 'risk_matrix_version',
                'id' => $this->version->id,
                'code' => $this->version->version_number,
            ],
            actionUrl: '/risk-prevention/matrices/'.$this->version->risk_matrix_id.'/versions/'.$this->version->id,
            icon: 'bx bx-shield-quarter',
            priority: $this->priority,
            occurredAt: $this->version->updated_at,
            context: ['risk_matrix_id' => $this->version->risk_matrix_id],
        ) + [
            'risk_matrix_id' => $this->version->risk_matrix_id,
            'risk_matrix_version_id' => $this->version->id,
        ];
    }
}
