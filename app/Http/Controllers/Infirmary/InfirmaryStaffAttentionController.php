<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infirmary\SaveInfirmaryStaffAttentionRequest;
use App\Models\Infirmary\InfirmaryAttention;
use App\Services\Infirmary\InfirmaryAttentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InfirmaryStaffAttentionController extends Controller
{
    public function __construct(
        private readonly InfirmaryAttentionService $attentionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InfirmaryAttention::class);

        $search = trim((string) $request->query('search'));
        $staffId = $request->query('staff_id');
        $category = trim((string) $request->query('attention_category'));
        $priority = trim((string) $request->query('priority'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        $query = InfirmaryAttention::query()
            ->where('subject_type', InfirmaryAttention::SUBJECT_STAFF)
            ->with([
                'staff:id,full_name,rut,cargo_id,institutional_email,phone,status,active',
                'staff.cargo:id,name,slug',
                'dependency:id,name',
                'referredBy:id,full_name',
                'treatments:id,attention_id,treatment_categories,treatment_types,derivation_type,derivation_support_teams,blood_pressure,pulse,temperature,oxygen_saturation,medication_id,medication_quantity,notes',
                'treatments.medication:id,name,commercial_name,unit',
                'referrals:id,attention_id,referral_type,referred_at,result',
            ])
            ->withCount(['referrals', 'followUps', 'documents'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('staff_full_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('staff_rut_snapshot', 'like', "%{$search}%")
                        ->orWhere('staff_cargo_snapshot', 'like', "%{$search}%")
                        ->orWhere('consultation_reason', 'like', "%{$search}%")
                        ->orWhere('logbook', 'like', "%{$search}%")
                        ->orWhere('initial_description', 'like', "%{$search}%");
                });
            })
            ->when($staffId, fn ($query) => $query->where('staff_id', $staffId))
            ->when($category !== '', fn ($query) => $query->where('attention_category', $category))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($from !== '', fn ($query) => $query->whereDate('attended_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('attended_at', '<=', $to));

        $attentions = $query
            ->latest('attended_at')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 12))));

        $attentions->getCollection()->each(
            fn (InfirmaryAttention $attention) => $this->hideWorkflowFields($attention)
        );

        return response()->json($attentions);
    }

    public function store(SaveInfirmaryStaffAttentionRequest $request): JsonResponse
    {
        $this->authorize('create', InfirmaryAttention::class);

        return response()->json([
            'message' => 'Atención a funcionario registrada correctamente.',
            'data' => $this->hideWorkflowFields(
                $this->attentionService->store($request->validated(), $request->user())
            ),
        ], 201);
    }

    public function show(InfirmaryAttention $attention): JsonResponse
    {
        $this->ensureStaffAttention($attention);
        $this->authorize('view', $attention);

        return response()->json([
            'data' => $this->hideWorkflowFields($this->loadAttention($attention)),
        ]);
    }

    public function update(
        SaveInfirmaryStaffAttentionRequest $request,
        InfirmaryAttention $attention,
    ): JsonResponse {
        $this->ensureStaffAttention($attention);
        $this->authorize('update', $attention);

        return response()->json([
            'message' => 'Atención a funcionario actualizada correctamente.',
            'data' => $this->hideWorkflowFields(
                $this->attentionService->update($attention, $request->validated(), $request->user())
            ),
        ]);
    }

    public function destroy(InfirmaryAttention $attention): JsonResponse
    {
        $this->ensureStaffAttention($attention);
        $this->authorize('delete', $attention);

        foreach ($attention->documents as $document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->delete();
        }

        $this->attentionService->delete($attention, request()->user());

        return response()->json([
            'message' => 'Atención a funcionario eliminada correctamente.',
        ]);
    }

    private function ensureStaffAttention(InfirmaryAttention $attention): void
    {
        abort_unless($attention->subject_type === InfirmaryAttention::SUBJECT_STAFF, 404);
    }

    private function loadAttention(InfirmaryAttention $attention): InfirmaryAttention
    {
        return $attention->load([
            'staff:id,full_name,rut,birth_date,cargo_id,institutional_email,personal_email,phone,status,active',
            'staff.cargo:id,name,slug',
            'referredBy:id,full_name',
            'dependency:id,code,name,location,floor_sector',
            'attendedBy:id,name',
            'treatments.medication:id,name,commercial_name,unit',
            'treatments.emotionalProfessional:id,full_name',
            'referrals.responsibleUser:id,name',
            'followUps.responsibleUser:id,name',
            'administrations.medication:id,name,commercial_name,unit',
            'documents.uploadedBy:id,name',
        ]);
    }

    private function hideWorkflowFields(InfirmaryAttention $attention): InfirmaryAttention
    {
        return $attention->makeHidden([
            'status',
            'attention_duration_minutes',
            'finalized_at',
        ]);
    }
}
