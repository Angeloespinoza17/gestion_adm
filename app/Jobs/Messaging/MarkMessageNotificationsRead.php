<?php

namespace App\Jobs\Messaging;

use App\Models\Messaging\Message;
use App\Models\User;
use App\Notifications\Messaging\NewMessageNotification;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MarkMessageNotificationsRead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [5, 30, 120, 300];

    public function __construct(
        public readonly int $userId,
        public readonly int $conversationId,
        public readonly int $afterMessageId,
        public readonly int $throughMessageId,
        public readonly string $readAt,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        if (! User::query()->messagingStaff()->whereKey($this->userId)->exists()) {
            return;
        }

        $timestamp = CarbonImmutable::parse($this->readAt);

        Message::query()
            ->withTrashed()
            ->where('conversation_id', $this->conversationId)
            ->where('id', '>', $this->afterMessageId)
            ->where('id', '<=', $this->throughMessageId)
            ->select(['id', 'public_id'])
            ->orderBy('id')
            ->chunkById(500, function ($messages) use ($timestamp): void {
                $publicIds = $messages->pluck('public_id')->all();
                $notificationIds = array_map(
                    fn (string $publicId) => NewMessageNotification::deterministicId($publicId, $this->userId),
                    $publicIds
                );

                DB::table('notifications')
                    ->whereIn('id', $notificationIds)
                    ->whereNull('read_at')
                    ->update(['read_at' => $timestamp, 'updated_at' => $timestamp]);

                // Compatibilidad con notificaciones previas a los IDs determinísticos.
                DB::table('notifications')
                    ->where('notifiable_id', $this->userId)
                    ->where('type', NewMessageNotification::class)
                    ->whereNull('read_at')
                    ->whereIn('data->message_id', $publicIds)
                    ->update(['read_at' => $timestamp, 'updated_at' => $timestamp]);
            });
    }
}
