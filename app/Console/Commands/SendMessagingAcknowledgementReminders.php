<?php

namespace App\Console\Commands;

use App\Services\Messaging\AcknowledgementReminderService;
use Illuminate\Console\Command;

class SendMessagingAcknowledgementReminders extends Command
{
    protected $signature = 'messaging:send-acknowledgement-reminders';

    protected $description = 'Envía recordatorios automáticos de acuses pendientes sin duplicarlos';

    public function handle(AcknowledgementReminderService $reminders): int
    {
        if (! config('messaging.enabled')) {
            $this->info('Mensajería deshabilitada; no se encolaron recordatorios.');

            return self::SUCCESS;
        }

        $queued = $reminders->queueAutomatic();
        $this->info("Recordatorios encolados: {$queued}");

        return self::SUCCESS;
    }
}
