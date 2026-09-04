<?php

namespace App\Http\Controllers\Orientation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orientation\SaveOrientationActionRequest;
use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationPlan;
use App\Services\Orientation\OrientationWorkspaceService;
use App\Services\Orientation\OrientationActivityProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrientationActionController extends Controller
{
    public function __construct(
        private readonly OrientationWorkspaceService $workspace,
        private readonly OrientationActivityProgressService $progress,
    ) {}

    public function show(OrientationAction $action): JsonResponse
    {
        return response()->json([
            'data' => $this->workspace->serializeActionDetail($this->workspace->loadAction($action)),
        ]);
    }

    public function store(SaveOrientationActionRequest $request, OrientationPlan $plan): JsonResponse
    {
        $action = DB::transaction(function () use ($request, $plan): OrientationAction {
            $payload = $request->safe()->except(['responsible_user_ids', 'related_plan_ids']);
            $payload['orientation_plan_id'] = $plan->id;
            $payload['sort_order'] = $payload['sort_order'] ?? ((int) $plan->actions()->max('sort_order') + 1);
            $payload['created_by'] = $request->user()?->id;
            $payload['updated_by'] = $request->user()?->id;

            $action = OrientationAction::query()->create($payload);
            $action->responsibleUsers()->sync($request->input('responsible_user_ids', []));
            $action->relatedPlans()->sync($request->input('related_plan_ids', []));

            return $action;
        });

        return response()->json([
            'message' => 'Acción incorporada al plan anual.',
            'data' => $this->workspace->serializeActionDetail($this->workspace->loadAction($action)),
        ], 201);
    }

    public function update(SaveOrientationActionRequest $request, OrientationAction $action): JsonResponse
    {
        DB::transaction(function () use ($request, $action): void {
            $payload = $request->safe()->except(['responsible_user_ids', 'related_plan_ids']);
            $hasAutomaticProgress = $action->activities()
                ->where('contribution_percent', '>', 0)
                ->exists();

            if ($hasAutomaticProgress) {
                unset($payload['progress']);
            }
            $payload['updated_by'] = $request->user()?->id;
            $action->update($payload);
            $action->responsibleUsers()->sync($request->input('responsible_user_ids', []));
            $action->relatedPlans()->sync($request->input('related_plan_ids', []));

            if ($hasAutomaticProgress) {
                $this->progress->recalculateAction($action, $request->user()?->id);
            }
        });

        return response()->json([
            'message' => 'Acción actualizada correctamente.',
            'data' => $this->workspace->serializeActionDetail($this->workspace->loadAction($action)),
        ]);
    }

    public function destroy(OrientationAction $action): JsonResponse
    {
        $title = $action->title;
        $storedFiles = $action->evidences()
            ->whereNotNull('file_path')
            ->get(['storage_disk', 'file_path']);

        DB::transaction(fn (): bool => $action->delete());

        foreach ($storedFiles as $storedFile) {
            try {
                Storage::disk($storedFile->storage_disk ?: 'local')->delete($storedFile->file_path);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'message' => "La acción «{$title}» y sus registros asociados fueron eliminados.",
        ]);
    }
}
