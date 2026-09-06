<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\Messaging\AcknowledgementReminderNotification;
use App\Notifications\Messaging\NewMessageNotification;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class InternalNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,unread,read'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $limit = min(max($request->integer('limit', 12), 1), 50);
        $user = $request->user();
        $status = $filters['status'] ?? 'all';
        $search = trim($filters['search'] ?? '');
        $query = $this->visibleNotifications($user)->latest();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('data', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%');
            });
        }

        $notifications = $query->paginate($limit);
        $counts = $this->visibleNotifications($user)
            // La relación ordena por created_at; MySQL en modo estricto no
            // permite ese ORDER BY sobre una consulta agregada sin GROUP BY.
            ->reorder()
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END), 0) AS unread_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS read_count')
            ->first();

        return response()->json([
            'unread_count' => (int) ($counts?->unread_count ?? 0),
            'total_count' => (int) ($counts?->total_count ?? 0),
            'read_count' => (int) ($counts?->read_count ?? 0),
            'data' => $notifications->getCollection()
                ->map(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'type' => class_basename($notification->type),
                    'title' => $notification->data['title'] ?? 'Notificación',
                    'message' => $notification->data['message'] ?? '',
                    'icon' => $notification->data['icon'] ?? 'bx bx-bell',
                    'priority' => $notification->data['priority'] ?? 'media',
                    'action_url' => $notification->data['action_url'] ?? null,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ])
                ->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $item = $this->visibleNotifications($request->user())->findOrFail($notification);
        $item->markAsRead();

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'data' => ['id' => $item->id, 'read_at' => $item->fresh()->read_at],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->visibleNotifications($request->user())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notificaciones marcadas como leídas.',
            'unread_count' => 0,
        ]);
    }

    private function visibleNotifications(User $user): MorphMany
    {
        $query = $user->notifications();

        if (! $user->canUseMessaging()) {
            $query->whereNotIn('type', [
                NewMessageNotification::class,
                AcknowledgementReminderNotification::class,
            ]);
        }

        return $query;
    }
}
