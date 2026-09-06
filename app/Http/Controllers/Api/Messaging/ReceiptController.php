<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\AcknowledgeMessageRequest;
use App\Jobs\Messaging\MarkMessageNotificationsRead;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Services\Messaging\AcknowledgementReminderService;
use App\Services\Messaging\AcknowledgementService;
use App\Services\Messaging\AuditService;
use App\Services\Messaging\MessagingBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    public function __construct(
        private AcknowledgementService $service,
        private AcknowledgementReminderService $reminders,
        private AuditService $audit,
        private MessagingBroadcaster $broadcaster,
    ) {}

    public function delivered(Request $request): JsonResponse
    {
        $data = $request->validate(['message_ids' => ['required', 'array', 'max:100'], 'message_ids.*' => ['string', 'size:26', 'distinct']]);
        $ids = Message::query()->whereIn('public_id', $data['message_ids'])->pluck('id');
        $count = MessageRecipient::query()->where('user_id', $request->user()->id)->whereIn('message_id', $ids)->whereNull('delivered_at')->update(['delivered_at' => now()]);

        return response()->json(['data' => ['updated' => $count]]);
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $data = $request->validate(['through_message_id' => ['required', 'string', 'size:26']]);
        $through = $conversation->messages()->where('public_id', $data['through_message_id'])->firstOrFail();
        $user = $request->user();
        $result = DB::transaction(function () use ($conversation, $through, $user): array {
            $participant = $conversation->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->firstOrFail();
            $previousMessageId = (int) ($participant->last_read_message_id ?? 0);

            if ($previousMessageId >= $through->id) {
                return ['advanced' => false, 'count' => 0];
            }

            $readAt = now();
            $messageRange = $conversation->messages()
                ->where('id', '>', $previousMessageId)
                ->where('id', '<=', $through->id);
            $count = MessageRecipient::query()
                ->where('user_id', $user->id)
                ->whereIn('message_id', (clone $messageRange)->select('id'))
                ->whereNull('read_at')
                ->update(['delivered_at' => $readAt, 'read_at' => $readAt]);

            $participant->update([
                'last_read_message_id' => $through->id,
                'last_read_at' => $readAt,
            ]);

            $this->audit->record('message_read', $user->id, $conversation->id, $through->id, $user->id, [
                'through' => $through->public_id,
                'count' => $count,
            ]);

            return [
                'advanced' => true,
                'count' => $count,
                'after_message_id' => $previousMessageId,
                'through_message_id' => $through->id,
                'read_at' => $readAt->toIso8601String(),
            ];
        });

        if ($result['advanced']) {
            MarkMessageNotificationsRead::dispatch(
                (int) $user->id,
                (int) $conversation->id,
                (int) $result['after_message_id'],
                (int) $result['through_message_id'],
                $result['read_at'],
            )->onQueue('notifications')->afterCommit();
            $this->broadcaster->messageRead($conversation, $user->id, $through->public_id, $result['count'], $through->sender_id);
        }

        return response()->json(['data' => ['updated' => $result['count'], 'through_message_id' => $through->public_id]]);
    }

    public function acknowledge(AcknowledgeMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);
        $receipt = $this->service->acknowledge($message, $request->user(), $request->validated('comment'));

        return response()->json(['data' => ['status' => 'acknowledged', 'acknowledged_at' => $receipt->acknowledged_at, 'acknowledged_message_version' => $receipt->acknowledged_message_version, 'acknowledged_content_hash' => $receipt->acknowledged_content_hash, 'comment' => $receipt->acknowledgement_comment]]);
    }

    public function index(Request $request, Message $message): JsonResponse
    {
        $this->authorize('receipts', $message);
        $limit = min(max($request->integer('limit', 100), 1), 100);
        $page = $message->recipients()
            ->orderBy('id')
            ->cursorPaginate($limit);
        $receipts = collect($page->items());
        $dueAt = $message->acknowledgement_due_at;
        $summary = $message->recipients()
            ->selectRaw(
                'COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS delivered,
                COALESCE(SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS read_count,
                COALESCE(SUM(CASE WHEN acknowledged_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS acknowledged,
                COALESCE(SUM(CASE WHEN acknowledgement_required = 1 AND acknowledged_at IS NULL AND waived_at IS NULL THEN 1 ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN waived_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS waived'
            )
            ->first();
        $outstanding = (int) ($summary->outstanding ?? 0);
        $overdue = $dueAt?->isPast() ? $outstanding : 0;

        return response()->json([
            'data' => $receipts->map(fn ($receipt) => ['user_id' => $receipt->user_id, 'name' => $receipt->recipient_display_name_snapshot, 'reference' => $receipt->recipient_reference_snapshot, 'delivered_at' => $receipt->delivered_at, 'read_at' => $receipt->read_at, 'acknowledged_at' => $receipt->acknowledged_at, 'comment' => $receipt->acknowledgement_comment, 'last_reminded_at' => $receipt->last_reminded_at, 'reminder_count' => $receipt->reminder_count, 'status' => $this->receiptStatus($message, $receipt)]),
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'delivered' => (int) ($summary->delivered ?? 0),
                'read' => (int) ($summary->read_count ?? 0),
                'acknowledged' => (int) ($summary->acknowledged ?? 0),
                'pending' => $outstanding - $overdue,
                'overdue' => $overdue,
                'waived' => (int) ($summary->waived ?? 0),
            ],
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function remind(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();
        $managesConversation = $message->conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
        abort_unless(
            $user->isSuperAdmin()
                || $user->hasPermission('messaging.send_reminder')
                || $message->sender_id === $user->id
                || $managesConversation,
            403
        );
        $data = $request->validate([
            'user_ids' => ['sometimes', 'array', 'max:5000'],
            'user_ids.*' => ['integer', 'distinct'],
        ]);
        $queued = $this->reminders->queueManual(
            $message,
            $user,
            $data['user_ids'] ?? []
        );

        return response()->json(['data' => ['queued' => $queued]], 202);
    }

    public function waive(Request $request, Message $message): JsonResponse
    {
        $this->authorize('receipts', $message);
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasPermission('messaging.waive_acknowledgement'), 403);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000']]);
        $receipt = $message->recipients()->forMessagingStaff()->where('user_id', $data['user_id'])->where('acknowledgement_required', true)->firstOrFail();
        $receipt->update(['waived_at' => now(), 'waived_by' => $request->user()->id, 'waiver_reason' => $data['reason']]);
        $this->audit->record('acknowledgement_waived', $request->user()->id, $message->conversation_id, $message->id, $receipt->user_id, ['reason' => $data['reason']]);

        return response()->json(['message' => 'Acuse eximido.']);
    }

    public function export(Request $request, Message $message): StreamedResponse
    {
        $this->authorize('receipts', $message);

        return response()->streamDownload(function () use ($message) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Comunicación', $message->public_id]);
            fputcsv($out, ['Hash', $message->content_hash]);
            fputcsv($out, ['Destinatario', 'Referencia', 'Entregado', 'Leído', 'Acuse', 'Fecha acuse', 'Comentario']);
            $message->recipients()
                ->orderBy('id')
                ->lazyById(500)
                ->each(function (MessageRecipient $receipt) use ($out, $message): void {
                    fputcsv($out, [$receipt->recipient_display_name_snapshot, $receipt->recipient_reference_snapshot, $receipt->delivered_at, $receipt->read_at, $this->receiptStatus($message, $receipt), $receipt->acknowledged_at, $receipt->acknowledgement_comment]);
                });
            fclose($out);
        }, 'acuse-'.$message->public_id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function receiptStatus(Message $message, MessageRecipient $receipt): string
    {
        if (! $receipt->acknowledgement_required) {
            return 'not_requested';
        }
        if ($receipt->waived_at) {
            return 'waived';
        }
        if ($receipt->acknowledged_at) {
            return 'acknowledged';
        }

        return $message->acknowledgement_due_at?->isPast() ? 'overdue' : 'pending';
    }
}
