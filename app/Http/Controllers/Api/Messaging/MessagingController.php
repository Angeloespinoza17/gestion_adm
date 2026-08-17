<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessagingController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json(['unread_messages' => MessageRecipient::query()->where('user_id', $userId)->whereNull('read_at')->count(), 'unread_conversations' => Conversation::query()->visibleTo($request->user())->whereHas('messages.recipients', fn ($q) => $q->where('user_id', $userId)->whereNull('read_at'))->count(), 'pending_acknowledgements' => MessageRecipient::query()->where('user_id', $userId)->where('acknowledgement_required', true)->whereNull('acknowledged_at')->whereNull('waived_at')->whereHas('message', fn ($q) => $q->where(fn ($d) => $d->whereNull('acknowledgement_due_at')->orWhere('acknowledgement_due_at', '>=', now())))->count(), 'overdue_acknowledgements' => MessageRecipient::query()->where('user_id', $userId)->where('acknowledgement_required', true)->whereNull('acknowledged_at')->whereNull('waived_at')->whereHas('message', fn ($q) => $q->where('acknowledgement_due_at', '<', now()))->count()]);
    }

    public function config(Request $request): JsonResponse
    {
        return response()->json(['enabled' => (bool) config('messaging.enabled'), 'realtime' => ['enabled' => (bool) config('messaging.realtime.enabled'), 'poll_interval_ms' => config('messaging.realtime.poll_interval_ms')], 'messages' => ['max_length' => config('messaging.messages.max_length')], 'attachments' => ['max_files' => config('messaging.attachments.max_files'), 'max_size_mb' => config('messaging.attachments.max_size_mb'), 'extensions' => config('messaging.attachments.extensions')], 'reactions' => config('messaging.reactions'), 'capabilities' => ['send_announcement' => $request->user()->isSuperAdmin() || $request->user()->hasPermission('messaging.send_announcement') || $request->user()->hasPermission('gestionar_comunicaciones_internas')]]);
    }

    public function users(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('query'));
        abort_if(mb_strlen($q) < 2, 422, 'Ingresa al menos 2 caracteres.');
        $users = User::query()->where('active', true)->whereKeyNot($request->user()->id)->where(fn ($x) => $x->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'))->limit(20)->get(['id', 'name', 'email', 'profile_photo_path']);

        return response()->json(['data' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'photo' => $u->profile_photo_url])]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:200'], 'conversation_id' => ['nullable', 'string', 'size:26']]);
        $query = Message::query()->whereNull('deleted_at')->whereHas('conversation.participants', fn ($q) => $q->where('user_id', $request->user()->id))->where(fn ($q) => $q->where('body', 'like', '%'.$data['query'].'%')->orWhere('subject', 'like', '%'.$data['query'].'%')->orWhereHas('attachments', fn ($a) => $a->where('original_name', 'like', '%'.$data['query'].'%')));
        if (! empty($data['conversation_id'])) {
            $query->whereHas('conversation', fn ($q) => $q->where('public_id', $data['conversation_id']));
        } $items = $query->with(['conversation:id,public_id,title', 'sender:id,name'])->latest('id')->limit(50)->get();

        return response()->json(['data' => $items->map(fn ($m) => ['public_id' => $m->public_id, 'conversation_id' => $m->conversation->public_id, 'conversation_title' => $m->conversation->title, 'sender' => $m->sender?->name, 'subject' => $m->subject, 'excerpt' => mb_strimwidth((string) $m->body,0,240,'…'), 'sent_at' => $m->sent_at])]);
    }
}
