<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionAccidentRequest;
use App\Http\Requests\RiskPrevention\StoreRiskPreventionAccidentFollowUpRequest;
use App\Models\RiskPrevention\RiskPreventionAccident;
use App\Models\RiskPrevention\RiskPreventionAccidentFollowUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskPreventionAccidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionAccident::class);

        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('accident_type'));
        $status = trim((string) $request->query('case_status'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        $accidents = RiskPreventionAccident::query()
            ->with('followUps')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('involved_person_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('accident_type', $type))
            ->when($status !== '', fn ($query) => $query->where('case_status', $status))
            ->when($from !== '', fn ($query) => $query->whereDate('occurred_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('occurred_at', '<=', $to))
            ->orderByDesc('occurred_at')
            ->paginate((int) $request->query('per_page', 12));

        return response()->json($accidents);
    }

    public function store(SaveRiskPreventionAccidentRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionAccident::class);

        $payload = $request->validated();
        if (($payload['case_status'] ?? null) === 'cerrado') {
            $payload['closed_at'] = now();
        }

        $accident = RiskPreventionAccident::query()->create(array_merge(
            $payload,
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        ));

        return response()->json([
            'message' => 'Accidente registrado correctamente.',
            'data' => $accident->load('followUps'),
        ], 201);
    }

    public function show(RiskPreventionAccident $accident): JsonResponse
    {
        $this->authorize('view', $accident);

        return response()->json([
            'data' => $accident->load('followUps'),
        ]);
    }

    public function update(SaveRiskPreventionAccidentRequest $request, RiskPreventionAccident $accident): JsonResponse
    {
        $this->authorize('update', $accident);

        $payload = $request->validated();
        $payload['updated_by'] = $request->user()->id;
        $payload['closed_at'] = ($payload['case_status'] ?? null) === 'cerrado'
            ? ($accident->closed_at ?: now())
            : null;

        $accident->update($payload);

        return response()->json([
            'message' => 'Accidente actualizado correctamente.',
            'data' => $accident->fresh()->load('followUps'),
        ]);
    }

    public function destroy(RiskPreventionAccident $accident): JsonResponse
    {
        $this->authorize('delete', $accident);
        $accident->delete();

        return response()->json([
            'message' => 'Registro de accidente eliminado correctamente.',
        ]);
    }

    public function storeFollowUp(StoreRiskPreventionAccidentFollowUpRequest $request, RiskPreventionAccident $accident): JsonResponse
    {
        $this->authorize('update', $accident);

        $followUp = $accident->followUps()->create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id],
        ));

        $accident->update([
            'case_status' => $followUp->status,
            'closed_at' => $followUp->status === 'cerrado' ? now() : null,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Seguimiento registrado correctamente.',
            'data' => $accident->fresh()->load('followUps'),
        ], 201);
    }

    public function destroyFollowUp(RiskPreventionAccidentFollowUp $accidentFollowUp): JsonResponse
    {
        $accident = $accidentFollowUp->accident()->firstOrFail();
        $this->authorize('update', $accident);

        $accidentFollowUp->delete();

        return response()->json([
            'message' => 'Seguimiento eliminado correctamente.',
            'data' => $accident->fresh()->load('followUps'),
        ]);
    }
}
