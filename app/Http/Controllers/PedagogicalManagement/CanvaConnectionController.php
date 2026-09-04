<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\CanvaAuthorizationRequest;
use App\Http\Requests\PedagogicalManagement\CanvaSchoolRequest;
use App\Models\PedagogicalManagement\CanvaConnection;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaConnectionLocator;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaOAuthService;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTokenService;
use App\Services\PedagogicalManagement\ClassPresentations\ClassPresentationAccessService;
use Illuminate\Http\JsonResponse;

class CanvaConnectionController extends Controller
{
    public function show(
        CanvaSchoolRequest $request,
        ClassPresentationAccessService $access,
        CanvaConnectionLocator $connections,
    ): JsonResponse {
        $schoolId = (int) $request->validated('school_id');
        abort_unless($access->canAccessSchool($request->user(), $schoolId), 403);

        return response()->json([
            'data' => $connections->response($connections->current($request->user(), $schoolId), $schoolId),
        ]);
    }

    public function begin(
        CanvaAuthorizationRequest $request,
        ClassPresentationAccessService $access,
        CanvaOAuthService $oauth,
    ): JsonResponse {
        $data = $request->validated();
        $schoolId = (int) $data['school_id'];
        abort_unless($access->canAccessSchool($request->user(), $schoolId), 403);
        $school = $access->schoolsFor($request->user())->whereKey($schoolId)->firstOrFail();

        return response()->json([
            'data' => $oauth->begin($request->user(), $school, $data['redirect_to'] ?? null),
        ], 202);
    }

    public function destroy(
        CanvaSchoolRequest $request,
        ClassPresentationAccessService $access,
        CanvaConnectionLocator $connections,
        CanvaTokenService $tokens,
    ): JsonResponse {
        $schoolId = (int) $request->validated('school_id');
        abort_unless($access->canAccessSchool($request->user(), $schoolId), 403);

        CanvaConnection::query()
            ->where('school_id', $schoolId)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [
                CanvaConnectionStatus::Active->value,
                CanvaConnectionStatus::ReauthorizationRequired->value,
            ])
            ->latest('id')
            ->get()
            ->each(fn (CanvaConnection $connection) => $tokens->disconnect($connection));

        return response()->json(['data' => $connections->response(null, $schoolId)]);
    }
}
