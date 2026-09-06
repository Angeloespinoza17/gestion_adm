<?php

namespace App\Services\Messaging;

use App\Jobs\Messaging\SendAcknowledgementReminderBatch;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Models\User;
use App\Notifications\Messaging\AcknowledgementReminderNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class AcknowledgementReminderService
{
    private const AUTOMATIC_COOLDOWN_HOURS = 12;

    private const BATCH_SIZE = 200;

    public function queueAutomatic(): int
    {
        return $this->queueEligible(false);
    }

    public function queueManual(Message $message, User $actor, array $userIds = []): int
    {
        abort_unless($actor->canUseMessaging(), 403, 'La mensajería institucional está disponible exclusivamente para funcionarios.');

        return $this->queueEligible(true, $message->id, (int) $actor->id, $userIds);
    }

    public function processBatch(array $receiptIds, bool $manual, ?int $actorId = null): int
    {
        if (! config('messaging.enabled')) {
            return 0;
        }

        $receiptIds = collect($receiptIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->take(self::BATCH_SIZE)
            ->values()
            ->all();

        if ($receiptIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($receiptIds, $manual, $actorId): int {
            $receipts = $this->eligibleQuery($manual, now())
                ->whereKey($receiptIds)
                ->with([
                    'message:id,public_id,conversation_id,subject,body,priority',
                    'message.conversation:id,public_id',
                ])
                ->orderBy('message_recipients.id')
                ->lockForUpdate()
                ->get();

            if ($receipts->isEmpty()) {
                return 0;
            }

            $timestamp = now();
            $notifiableType = (new User)->getMorphClass();
            $effectiveActorId = $manual && $actorId && User::query()->messagingStaff()->whereKey($actorId)->exists()
                ? $actorId
                : null;
            $notifications = [];
            $audits = [];

            foreach ($receipts as $receipt) {
                $message = $receipt->message;
                if (! $message?->conversation) {
                    continue;
                }

                $nextReminder = ((int) $receipt->reminder_count) + 1;
                $notification = new AcknowledgementReminderNotification($message);
                $notifications[] = [
                    'id' => Uuid::uuid5(
                        Uuid::NAMESPACE_URL,
                        'skote:messaging:acknowledgement-reminder:'.$receipt->id.':'.$nextReminder
                    )->toString(),
                    'type' => AcknowledgementReminderNotification::class,
                    'notifiable_type' => $notifiableType,
                    'notifiable_id' => $receipt->user_id,
                    'data' => json_encode(
                        $notification->toArray(new User),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ),
                    'read_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                $audits[] = [
                    'public_id' => (string) Str::ulid(),
                    'conversation_id' => $message->conversation_id,
                    'message_id' => $message->id,
                    'actor_id' => $effectiveActorId,
                    'target_user_id' => $receipt->user_id,
                    'event_type' => 'reminder_sent',
                    'metadata' => json_encode(
                        ['manual' => $manual],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ),
                    'occurred_at' => $timestamp,
                ];
            }

            if ($notifications === []) {
                return 0;
            }

            DB::table('notifications')->upsert($notifications, ['id'], ['id']);
            MessageRecipient::query()
                ->whereKey($receipts->pluck('id'))
                ->update([
                    'last_reminded_at' => $timestamp,
                    'reminder_count' => DB::raw('reminder_count + 1'),
                    'updated_at' => $timestamp,
                ]);
            DB::table('messaging_audit_events')->insert($audits);

            return count($notifications);
        }, 3);
    }

    private function queueEligible(
        bool $manual,
        ?int $messageId = null,
        ?int $actorId = null,
        array $userIds = []
    ): int {
        if (! config('messaging.enabled')) {
            return 0;
        }

        $queued = 0;
        $query = $this->eligibleQuery($manual, now(), $messageId, $userIds)
            ->select('message_recipients.id');

        $query->chunkById(self::BATCH_SIZE, function ($receipts) use (&$queued, $manual, $actorId): void {
            $ids = $receipts->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids === []) {
                return;
            }

            SendAcknowledgementReminderBatch::dispatch($ids, $manual, $actorId);
            $queued += count($ids);
        }, 'message_recipients.id', 'id');

        return $queued;
    }

    private function eligibleQuery(
        bool $manual,
        Carbon $referenceTime,
        ?int $messageId = null,
        array $userIds = []
    ): Builder {
        $cooldown = $manual
            ? $referenceTime->copy()->subMinutes((int) config('messaging.acknowledgements.manual_reminder_cooldown_minutes', 30))
            : $referenceTime->copy()->subHours(self::AUTOMATIC_COOLDOWN_HOURS);

        $query = MessageRecipient::query()
            ->select('message_recipients.*')
            ->join('messages as reminder_messages', 'reminder_messages.id', '=', 'message_recipients.message_id')
            ->join('conversations as reminder_conversations', 'reminder_conversations.id', '=', 'reminder_messages.conversation_id')
            ->join('conversation_participants as reminder_memberships', function (JoinClause $join): void {
                $join->on('reminder_memberships.conversation_id', '=', 'reminder_messages.conversation_id')
                    ->on('reminder_memberships.user_id', '=', 'message_recipients.user_id');
            })
            ->join('users as reminder_users', 'reminder_users.id', '=', 'message_recipients.user_id')
            ->where('message_recipients.acknowledgement_required', true)
            ->whereNull('message_recipients.acknowledged_at')
            ->whereNull('message_recipients.waived_at')
            ->whereNull('reminder_messages.deleted_at')
            ->whereNull('reminder_conversations.deleted_at')
            ->whereNull('reminder_memberships.left_at')
            ->whereIn('reminder_users.id', User::query()->messagingStaff()->select('id'))
            ->where(function (Builder $builder) use ($cooldown): void {
                $builder->whereNull('message_recipients.last_reminded_at')
                    ->orWhere('message_recipients.last_reminded_at', '<=', $cooldown);
            });

        if (! $manual) {
            $query->whereNotNull('reminder_messages.acknowledgement_due_at')
                ->where('reminder_messages.acknowledgement_due_at', '<=', $referenceTime->copy()->addDay());
        }

        if ($messageId) {
            $query->where('message_recipients.message_id', $messageId);
        }

        $userIds = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($userIds !== []) {
            $query->whereIn('message_recipients.user_id', $userIds);
        }

        return $query;
    }
}
