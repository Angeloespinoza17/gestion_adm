<?php

namespace App\Console\Commands;

use App\Models\Messaging\MessageRecipient;
use App\Notifications\Messaging\AcknowledgementReminderNotification;
use App\Services\Messaging\AuditService;
use Illuminate\Console\Command;

class SendMessagingAcknowledgementReminders extends Command
{
    protected $signature = 'messaging:send-acknowledgement-reminders';

    protected $description = 'Envía recordatorios automáticos de acuses pendientes sin duplicarlos';

    public function handle(AuditService $audit): int
    {
        $sent = 0;
        MessageRecipient::query()->with(['message.conversation', 'user'])->where('acknowledgement_required', true)->whereNull('acknowledged_at')->whereNull('waived_at')->whereHas('message', fn ($q) => $q->whereNotNull('acknowledgement_due_at')->where('acknowledgement_due_at', '<=', now()->addDay()))->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subHours(12)))->chunkById(200, function ($receipts) use (&$sent, $audit) {
            foreach ($receipts as $receipt) {
                if (! $receipt->user?->active) {
                    continue;
                } $receipt->user->notify(new AcknowledgementReminderNotification($receipt->message));
                $receipt->update(['last_reminded_at' => now(), 'reminder_count' => $receipt->reminder_count + 1]);
                $audit->record('reminder_sent', null, $receipt->message->conversation_id, $receipt->message_id, $receipt->user_id, ['manual' => false]);
                $sent++;
            }
        });
        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }
}
