<?php

namespace App\Jobs\Messaging;

use App\Services\Messaging\AcknowledgementReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAcknowledgementReminderBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public array $backoff = [5, 30, 120, 300];

    public function __construct(
        public readonly array $receiptIds,
        public readonly bool $manual = false,
        public readonly ?int $actorId = null,
    ) {
        $this->onQueue('notifications')->afterCommit();
    }

    public function handle(AcknowledgementReminderService $reminders): void
    {
        $reminders->processBatch($this->receiptIds, $this->manual, $this->actorId);
    }
}
