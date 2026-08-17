<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\AcknowledgeMessageRequest;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Notifications\Messaging\AcknowledgementReminderNotification;
use App\Notifications\Messaging\NewMessageNotification;
use App\Services\Messaging\AcknowledgementService;
use App\Services\Messaging\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    public function __construct(private AcknowledgementService $service, private AuditService $audit) {}

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
        $count = MessageRecipient::query()->where('user_id', $request->user()->id)->whereIn('message_id', $conversation->messages()->where('id', '<=', $through->id)->select('id'))->whereNull('read_at')->update(['delivered_at' => now(), 'read_at' => now()]);
        $conversation->participants()->where('user_id', $request->user()->id)->update(['last_read_message_id' => $through->id, 'last_read_at' => now()]);
        $readMessageIds = $conversation->messages()->where('id', '<=', $through->id)->pluck('public_id')->flip();
        $request->user()->unreadNotifications()
            ->where('type', NewMessageNotification::class)
            ->where('data->conversation_id', $conversation->public_id)
            ->get()
            ->filter(fn ($notification) => $readMessageIds->has($notification->data['message_id'] ?? null))
            ->each->markAsRead();
        $this->audit->record('message_read', $request->user()->id, $conversation->id, $through->id, $request->user()->id, ['through' => $through->public_id, 'count' => $count]);

        return response()->json(['data' => ['updated' => $count, 'through_message_id' => $through->public_id]]);
    }

    public function acknowledge(AcknowledgeMessageRequest $request, Message $message): JsonResponse
    {
        $receipt = $this->service->acknowledge($message, $request->user(), $request->validated('comment'));

        return response()->json(['data' => ['status' => 'acknowledged', 'acknowledged_at' => $receipt->acknowledged_at, 'acknowledged_message_version' => $receipt->acknowledged_message_version, 'acknowledged_content_hash' => $receipt->acknowledged_content_hash, 'comment' => $receipt->acknowledgement_comment]]);
    }

    public function index(Request $request, Message $message): JsonResponse
    {
        $this->authorize('receipts', $message);
        $receipts = $message->recipients()->with('user:id,name,email')->get();

        return response()->json(['data' => $receipts->map(fn ($r) => ['user_id' => $r->user_id, 'name' => $r->recipient_display_name_snapshot, 'reference' => $r->recipient_reference_snapshot, 'delivered_at' => $r->delivered_at, 'read_at' => $r->read_at, 'acknowledged_at' => $r->acknowledged_at, 'comment' => $r->acknowledgement_comment, 'last_reminded_at' => $r->last_reminded_at, 'reminder_count' => $r->reminder_count, 'status' => $r->acknowledgementStatus()]), 'summary' => ['total' => $receipts->count(), 'delivered' => $receipts->whereNotNull('delivered_at')->count(), 'read' => $receipts->whereNotNull('read_at')->count(), 'acknowledged' => $receipts->whereNotNull('acknowledged_at')->count(), 'pending' => $receipts->filter(fn ($r) => $r->acknowledgementStatus() === 'pending')->count(), 'overdue' => $receipts->filter(fn ($r) => $r->acknowledgementStatus() === 'overdue')->count(), 'waived' => $receipts->whereNotNull('waived_at')->count()]]);
    }

    public function remind(Request $request, Message $message): JsonResponse
    {
        $this->authorize('receipts', $message);
        $data = $request->validate(['user_ids' => ['sometimes', 'array'], 'user_ids.*' => ['integer', 'distinct']]);
        $cooldown = now()->subMinutes(config('messaging.acknowledgements.manual_reminder_cooldown_minutes'));
        $query = $message->recipients()->where('acknowledgement_required', true)->whereNull('acknowledged_at')->whereNull('waived_at')->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', $cooldown));
        if (! empty($data['user_ids'])) {
            $query->whereIn('user_id', $data['user_ids']);
        }
        $receipts = $query->get();
        foreach ($receipts as $receipt) {
            $receipt->loadMissing('user');
            $receipt->user?->notify(new AcknowledgementReminderNotification($message->loadMissing('conversation')));
            $receipt->update(['last_reminded_at' => now(), 'reminder_count' => $receipt->reminder_count + 1]);
            $this->audit->record('reminder_sent', $request->user()->id, $message->conversation_id, $message->id, $receipt->user_id, ['manual' => true]);
        }

        return response()->json(['data' => ['sent' => $receipts->count()]]);
    }

    public function waive(Request $request, Message $message): JsonResponse
    {
        $this->authorize('receipts', $message);
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasPermission('messaging.waive_acknowledgement'), 403);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000']]);
        $receipt = $message->recipients()->where('user_id', $data['user_id'])->where('acknowledgement_required', true)->firstOrFail();
        $receipt->update(['waived_at' => now(), 'waived_by' => $request->user()->id, 'waiver_reason' => $data['reason']]);
        $this->audit->record('acknowledgement_waived', $request->user()->id, $message->conversation_id, $message->id, $receipt->user_id, ['reason' => $data['reason']]);

        return response()->json(['message' => 'Acuse eximido.']);
    }

    public function export(Request $request, Message $message): StreamedResponse
    {
        $this->authorize('receipts', $message);
        $rows = $message->recipients()->get();

        return response()->streamDownload(function () use ($message, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Comunicación', $message->public_id]);
            fputcsv($out, ['Hash', $message->content_hash]);
            fputcsv($out, ['Destinatario', 'Referencia', 'Entregado', 'Leído', 'Acuse', 'Fecha acuse', 'Comentario']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->recipient_display_name_snapshot, $r->recipient_reference_snapshot, $r->delivered_at, $r->read_at, $r->acknowledgementStatus(), $r->acknowledged_at, $r->acknowledgement_comment]);
            } fclose($out);
        }, 'acuse-'.$message->public_id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
