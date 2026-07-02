<?php

namespace App\Http\Controllers\Staff\Permissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Permissions\SyncPermissionRequestReplacementsRequest;
use App\Models\PermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PermissionRequestReplacementController extends Controller
{
    public function sync(SyncPermissionRequestReplacementsRequest $request, PermissionRequest $permissionRequest): JsonResponse
    {
        $this->authorize('manageReplacements', PermissionRequest::class);
        $this->authorize('view', $permissionRequest);

        DB::transaction(function () use ($request, $permissionRequest) {
            $permissionRequest->replacements()->delete();

            foreach ($request->validated()['items'] as $item) {
                $permissionRequest->replacements()->create($item);
            }

            $permissionRequest->logs()->create([
                'user_id' => $request->user()->id,
                'action' => 'reemplazos_actualizados',
                'old_status' => $permissionRequest->status,
                'new_status' => $permissionRequest->status,
                'details' => ['count' => count($request->validated()['items'])],
            ]);
        });

        return response()->json([
            'message' => 'Reemplazos actualizados correctamente.',
            'data' => $permissionRequest->fresh()->load([
                'replacements.replacedStaff:id,full_name',
                'replacements.replacementStaff:id,full_name',
            ]),
        ]);
    }
}
