<?php

namespace App\Jobs\Messaging;

use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Models\User;
use App\Notifications\Messaging\NewMessageNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StoreNewMessageNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [5, 30, 120, 300];

    public function __construct(
        public readonly int $messageId,
        public readonly int $firstUserId,
        public readonly int $lastUserId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        if (! config('messaging.enabled')) {
            return;
        }

        $message = Message::query()
            ->with('conversation:id,public_id')
            ->find($this->messageId);

        if (! $message) {
            return;
        }

        $notificationData = json_encode(
            NewMessageNotification::databasePayload($message),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $notifiableType = (new User)->getMorphClass();

        DB::transaction(function () use ($message, $notificationData, $notifiableType): void {
            $receipts = DB::table('message_recipients as mr')
                ->leftJoin('users as u', 'u.id', '=', 'mr.user_id')
                ->leftJoin('conversation_participants as cp', function ($join) use ($message): void {
                    $join->on('cp.user_id', '=', 'mr.user_id')
                        ->where('cp.conversation_id', '=', $message->conversation_id)
                        ->whereNull('cp.left_at');
                })
                ->where('mr.message_id', $message->id)
                ->whereBetween('mr.user_id', [$this->firstUserId, $this->lastUserId])
                ->whereNull('mr.notification_sent_at')
                ->orderBy('mr.user_id')
                ->lockForUpdate()
                ->get([
                    'mr.id',
                    'mr.user_id',
                    'mr.read_at',
                    'u.active as user_active',
                    'cp.id as active_participant_id',
                ]);

            if ($receipts->isEmpty()) {
                return;
            }

            $timestamp = now();
            $eligibleUserIds = User::query()
                ->messagingStaff()
                ->whereIn('id', $receipts->pluck('user_id'))
                ->pluck('id')
                ->mapWithKeys(fn ($id) => [(int) $id => true]);
            $eligibleReceipts = $receipts->filter(fn ($receipt) => $eligibleUserIds->has((int) $receipt->user_id)
                && $receipt->active_participant_id !== null);
            $notifications = $eligibleReceipts->map(fn ($receipt) => [
                'id' => NewMessageNotification::deterministicId($message->public_id, (int) $receipt->user_id),
                'type' => NewMessageNotification::class,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $receipt->user_id,
                'data' => $notificationData,
                'read_at' => $receipt->read_at,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all();

            if ($notifications !== []) {
                DB::table('notifications')->upsert($notifications, ['id'], ['id']);
            }
            MessageRecipient::query()
                ->whereKey($receipts->pluck('id'))
                ->whereNull('notification_sent_at')
                ->update(['notification_sent_at' => $timestamp]);
        });
    }
}
