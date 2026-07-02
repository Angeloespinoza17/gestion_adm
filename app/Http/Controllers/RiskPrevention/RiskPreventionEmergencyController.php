<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionEmergencyPlanRequest;
use App\Http\Requests\RiskPrevention\StoreRiskPreventionEmergencyDrillRequest;
use App\Models\RiskPrevention\RiskPreventionEmergencyDrill;
use App\Models\RiskPrevention\RiskPreventionEmergencyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiskPreventionEmergencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionEmergencyPlan::class);

        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('record_type'));
        $active = $request->query('active');

        $plans = RiskPreventionEmergencyPlan::query()
            ->with('drills')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('emergency_type', 'like', "%{$search}%")
                        ->orWhere('responsible_name', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('record_type', $type))
            ->when($active !== null && $active !== '', fn ($query) => $query->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('record_type')
            ->orderBy('title')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json($plans);
    }

    public function storePlan(SaveRiskPreventionEmergencyPlanRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionEmergencyPlan::class);

        $plan = RiskPreventionEmergencyPlan::query()->create(array_merge(
            $request->safe()->except('document'),
            [
                'active' => $request->boolean('active', true),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        ));

        if ($request->file('document') instanceof UploadedFile) {
            $path = $this->storeFile($request->file('document'), "risk-prevention/emergency-plans/{$plan->id}");
            $plan->update([
                'document_path' => $path,
                'document_name' => $request->file('document')->getClientOriginalName(),
            ]);
        }

        return response()->json([
            'message' => 'Registro de emergencia guardado correctamente.',
            'data' => $plan->fresh()->load('drills'),
        ], 201);
    }

    public function updatePlan(SaveRiskPreventionEmergencyPlanRequest $request, RiskPreventionEmergencyPlan $emergencyPlan): JsonResponse
    {
        $this->authorize('update', $emergencyPlan);

        $payload = array_merge(
            $request->safe()->except('document'),
            [
                'active' => $request->boolean('active', true),
                'updated_by' => $request->user()->id,
            ],
        );

        if ($request->file('document') instanceof UploadedFile) {
            if ($emergencyPlan->document_path) {
                Storage::disk('local')->delete($emergencyPlan->document_path);
            }

            $payload['document_path'] = $this->storeFile($request->file('document'), "risk-prevention/emergency-plans/{$emergencyPlan->id}");
            $payload['document_name'] = $request->file('document')->getClientOriginalName();
        }

        $emergencyPlan->update($payload);

        return response()->json([
            'message' => 'Registro de emergencia actualizado correctamente.',
            'data' => $emergencyPlan->fresh()->load('drills'),
        ]);
    }

    public function destroyPlan(RiskPreventionEmergencyPlan $emergencyPlan): JsonResponse
    {
        $this->authorize('delete', $emergencyPlan);

        if ($emergencyPlan->document_path) {
            Storage::disk('local')->delete($emergencyPlan->document_path);
        }

        foreach ($emergencyPlan->drills as $drill) {
            if ($drill->document_path) {
                Storage::disk('local')->delete($drill->document_path);
            }
        }

        $emergencyPlan->delete();

        return response()->json([
            'message' => 'Registro de emergencia eliminado correctamente.',
        ]);
    }

    public function storeDrill(StoreRiskPreventionEmergencyDrillRequest $request, RiskPreventionEmergencyPlan $emergencyPlan): JsonResponse
    {
        $this->authorize('update', $emergencyPlan);

        $drill = $emergencyPlan->drills()->create(array_merge(
            $request->safe()->except('document'),
            [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        ));

        if ($request->file('document') instanceof UploadedFile) {
            $path = $this->storeFile($request->file('document'), "risk-prevention/emergency-drills/{$drill->id}");
            $drill->update([
                'document_path' => $path,
                'document_name' => $request->file('document')->getClientOriginalName(),
            ]);
        }

        return response()->json([
            'message' => 'Simulacro registrado correctamente.',
            'data' => $emergencyPlan->fresh()->load('drills'),
        ], 201);
    }

    public function destroyDrill(RiskPreventionEmergencyDrill $emergencyDrill): JsonResponse
    {
        $plan = $emergencyDrill->plan()->firstOrFail();
        $this->authorize('update', $plan);

        if ($emergencyDrill->document_path) {
            Storage::disk('local')->delete($emergencyDrill->document_path);
        }

        $emergencyDrill->delete();

        return response()->json([
            'message' => 'Simulacro eliminado correctamente.',
            'data' => $plan->fresh()->load('drills'),
        ]);
    }

    public function downloadPlanDocument(RiskPreventionEmergencyPlan $emergencyPlan): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $emergencyPlan);

        if (!$emergencyPlan->document_path || !Storage::disk('local')->exists($emergencyPlan->document_path)) {
            return response()->json(['message' => 'El documento no está disponible.'], 404);
        }

        return Storage::disk('local')->download($emergencyPlan->document_path, $emergencyPlan->document_name ?: basename($emergencyPlan->document_path));
    }

    public function downloadDrillDocument(RiskPreventionEmergencyDrill $emergencyDrill): StreamedResponse|JsonResponse
    {
        $plan = $emergencyDrill->plan()->firstOrFail();
        $this->authorize('view', $plan);

        if (!$emergencyDrill->document_path || !Storage::disk('local')->exists($emergencyDrill->document_path)) {
            return response()->json(['message' => 'El documento no está disponible.'], 404);
        }

        return Storage::disk('local')->download($emergencyDrill->document_path, $emergencyDrill->document_name ?: basename($emergencyDrill->document_path));
    }

    private function storeFile(UploadedFile $file, string $directory): string
    {
        return $file->storeAs(
            $directory,
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            'local',
        );
    }
}
