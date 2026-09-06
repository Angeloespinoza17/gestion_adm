<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\Message;
use App\Models\User;
use App\Services\Messaging\MessagingBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagingController extends Controller
{
    public function __construct(private MessagingBroadcaster $broadcaster) {}

    public function summary(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $now = now();
        $receipts = function () use ($userId) {
            return DB::table('message_recipients as mr')
                ->join('messages as m', 'm.id', '=', 'mr.message_id')
                ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
                ->join('conversation_participants as cp', function ($join) use ($userId) {
                    $join->on('cp.conversation_id', '=', 'm.conversation_id')
                        ->where('cp.user_id', '=', $userId)
                        ->whereNull('cp.left_at');
                })
                ->where('mr.user_id', $userId)
                ->whereNull('m.deleted_at')
                ->whereNull('c.deleted_at');
        };
        $pending = $receipts()
            ->where('mr.acknowledgement_required', true)
            ->whereNull('mr.acknowledged_at')
            ->whereNull('mr.waived_at');
        $summary = DB::query()
            ->selectSub(
                $receipts()->whereNull('mr.read_at')->selectRaw('COUNT(*)'),
                'unread_messages'
            )
            ->selectSub(
                $receipts()->whereNull('mr.read_at')->selectRaw('COUNT(DISTINCT m.conversation_id)'),
                'unread_conversations'
            )
            ->selectSub(
                (clone $pending)
                    ->where(fn ($query) => $query
                        ->whereNull('m.acknowledgement_due_at')
                        ->orWhere('m.acknowledgement_due_at', '>=', $now))
                    ->selectRaw('COUNT(*)'),
                'pending_acknowledgements'
            )
            ->selectSub(
                (clone $pending)
                    ->where('m.acknowledgement_due_at', '<', $now)
                    ->selectRaw('COUNT(*)'),
                'overdue_acknowledgements'
            )
            ->first();

        return response()->json([
            'unread_messages' => (int) ($summary->unread_messages ?? 0),
            'unread_conversations' => (int) ($summary->unread_conversations ?? 0),
            'pending_acknowledgements' => (int) ($summary->pending_acknowledgements ?? 0),
            'overdue_acknowledgements' => (int) ($summary->overdue_acknowledgements ?? 0),
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => (bool) config('messaging.enabled'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'photo' => $user->profile_photo_url,
                'is_staff' => true,
            ],
            'realtime' => [
                'enabled' => $this->broadcaster->isEnabled(),
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => (int) config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
                'path' => config('broadcasting.connections.reverb.options.path', ''),
                'connection_grace_ms' => (int) config('messaging.realtime.connection_grace_ms'),
            ],
            'polling' => [
                'enabled' => (bool) config('messaging.polling.enabled'),
                'interval_ms' => (int) config('messaging.polling.interval_ms'),
                'active_interval_ms' => (int) config('messaging.polling.active_interval_ms'),
                'max_interval_ms' => (int) config('messaging.polling.max_interval_ms'),
                'reconciliation_interval_ms' => (int) config('messaging.polling.reconciliation_interval_ms'),
                'jitter_ratio' => (float) config('messaging.polling.jitter_ratio'),
                'recovery_limit' => (int) config('messaging.polling.recovery_limit'),
            ],
            'messages' => ['max_length' => config('messaging.messages.max_length')],
            'attachments' => [
                'max_files' => config('messaging.attachments.max_files'),
                'max_size_mb' => config('messaging.attachments.max_size_mb'),
                'extensions' => config('messaging.attachments.extensions'),
            ],
            'reactions' => config('messaging.reactions'),
            'capabilities' => [
                'send_announcement' => $user->isSuperAdmin()
                    || $user->hasPermission('messaging.send_announcement')
                    || $user->hasPermission('gestionar_comunicaciones_internas'),
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('query'));
        abort_if(mb_strlen($q) < 2, 422, 'Ingresa al menos 2 caracteres.');
        $users = User::query()
            ->messagingStaff()
            ->whereKeyNot($request->user()->id)
            ->where(fn ($x) => $x
                ->where('name', 'like', '%'.$q.'%')
                ->orWhere('email', 'like', '%'.$q.'%'))
            ->limit(20)
            ->get(['id', 'name', 'email', 'profile_photo_path']);

        return response()->json(['data' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'photo' => $u->profile_photo_url, 'is_staff' => true])]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:200'], 'conversation_id' => ['nullable', 'string', 'size:26']]);
        $query = Message::query()->whereNull('deleted_at')->whereHas('conversation.participants', fn ($q) => $q->where('user_id', $request->user()->id)->whereNull('left_at'))->where(fn ($q) => $q->where('body', 'like', '%'.$data['query'].'%')->orWhere('subject', 'like', '%'.$data['query'].'%')->orWhereHas('attachments', fn ($a) => $a->where('original_name', 'like', '%'.$data['query'].'%')));
        if (! empty($data['conversation_id'])) {
            $query->whereHas('conversation', fn ($q) => $q->where('public_id', $data['conversation_id']));
        } $items = $query->with(['conversation:id,public_id,title', 'sender:id,name'])->latest('id')->limit(50)->get();

        return response()->json(['data' => $items->map(fn ($m) => ['public_id' => $m->public_id, 'conversation_id' => $m->conversation->public_id, 'conversation_title' => $m->conversation->title, 'sender' => $m->sender?->name, 'subject' => $m->subject, 'excerpt' => mb_strimwidth((string) $m->body, 0, 240, '…'), 'sent_at' => $m->sent_at])]);
    }
}
