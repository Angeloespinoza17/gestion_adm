<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\Security\SecurityNotification;
use App\Services\Security\SecurityAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityNotificationController extends Controller
{
    public function __construct(
        private readonly SecurityAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = SecurityNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id');

        return response()->json([
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
            'data' => $query->paginate((int) $request->query('per_page', 20)),
        ]);
    }

    public function markAsRead(Request $request, SecurityNotification $securityNotification): JsonResponse
    {
        abort_unless((int) $securityNotification->user_id === (int) $request->user()->id, 403);

        $securityNotification->update([
            'read_at' => $securityNotification->read_at ?: now(),
        ]);

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'data' => $securityNotification,
        ]);
    }
}
