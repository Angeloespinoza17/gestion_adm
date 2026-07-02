<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionTrainingRequest;
use App\Models\RiskPrevention\RiskPreventionTraining;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiskPreventionTrainingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionTraining::class);

        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('training_type'));
        $status = trim((string) $request->query('compliance_status'));

        $trainings = RiskPreventionTraining::query()
            ->with('participants')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('modality', 'like', "%{$search}%")
                        ->orWhere('observations', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('training_type', $type))
            ->when($status !== '', fn ($query) => $query->whereHas('participants', fn ($query) => $query->where('compliance_status', $status)))
            ->orderByDesc('training_date')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json($trainings);
    }

    public function store(SaveRiskPreventionTrainingRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionTraining::class);

        $training = DB::transaction(function () use ($request) {
            $training = RiskPreventionTraining::query()->create(array_merge(
                $request->safe()->except('participants', 'evidence'),
                ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
            ));

            if ($request->file('evidence') instanceof UploadedFile) {
                $path = $this->storeFile($request->file('evidence'), "risk-prevention/trainings/{$training->id}");
                $training->update([
                    'evidence_path' => $path,
                    'evidence_name' => $request->file('evidence')->getClientOriginalName(),
                ]);
            }

            $this->syncParticipants($training, $request->validated('participants', []));

            return $training;
        });

        return response()->json([
            'message' => 'Capacitación registrada correctamente.',
            'data' => $training->fresh()->load('participants'),
        ], 201);
    }

    public function update(SaveRiskPreventionTrainingRequest $request, RiskPreventionTraining $training): JsonResponse
    {
        $this->authorize('update', $training);

        DB::transaction(function () use ($request, $training) {
            $payload = array_merge(
                $request->safe()->except('participants', 'evidence'),
                ['updated_by' => $request->user()->id],
            );

            if ($request->file('evidence') instanceof UploadedFile) {
                if ($training->evidence_path) {
                    Storage::disk('local')->delete($training->evidence_path);
                }

                $payload['evidence_path'] = $this->storeFile($request->file('evidence'), "risk-prevention/trainings/{$training->id}");
                $payload['evidence_name'] = $request->file('evidence')->getClientOriginalName();
            }

            $training->update($payload);
            $this->syncParticipants($training, $request->validated('participants', []));
        });

        return response()->json([
            'message' => 'Capacitación actualizada correctamente.',
            'data' => $training->fresh()->load('participants'),
        ]);
    }

    public function destroy(RiskPreventionTraining $training): JsonResponse
    {
        $this->authorize('delete', $training);

        if ($training->evidence_path) {
            Storage::disk('local')->delete($training->evidence_path);
        }

        $training->delete();

        return response()->json([
            'message' => 'Capacitación eliminada correctamente.',
        ]);
    }

    public function downloadEvidence(RiskPreventionTraining $training): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $training);

        if (!$training->evidence_path || !Storage::disk('local')->exists($training->evidence_path)) {
            return response()->json(['message' => 'La evidencia no está disponible.'], 404);
        }

        return Storage::disk('local')->download($training->evidence_path, $training->evidence_name ?: basename($training->evidence_path));
    }

    private function syncParticipants(RiskPreventionTraining $training, array $participants): void
    {
        $training->participants()->delete();

        foreach ($participants as $participant) {
            if (!filled($participant['employee_name'] ?? null)) {
                continue;
            }

            $training->participants()->create([
                'employee_name' => $participant['employee_name'],
                'compliance_status' => $participant['compliance_status'] ?? 'pendiente',
                'notes' => $participant['notes'] ?? null,
            ]);
        }
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
