<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaDailyLogRequest;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Services\Convivencia\ConvivenciaDailyLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConvivenciaDailyLogController extends Controller
{
    public function __construct(
        private readonly ConvivenciaDailyLogService $dailyLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaDailyLog::class);

        $query = app(\App\Services\Convivencia\ConvivenciaAccessService::class)
            ->applyDailyLogVisibility(
                ConvivenciaDailyLog::query()->with([
                    'case:id,folio,status',
                    'generatedDerivation:id,scope,status,destination_label',
                    'courseSection:id,display_name',
                    'student:id,first_name,last_name,registered_name,rut',
                    'type:id,name',
                    'inspectorUser:id,name',
                    'inspectorStaff:id,full_name',
                ]),
                $request->user(),
            );

        $search = trim((string) $request->query('search'));
        $query
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('daily_log_type_label', 'like', "%{$search}%")
                        ->orWhere('place', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('inspector_user_id'), fn ($builder, $value) => $builder->where('inspector_user_id', $value))
            ->when($request->query('student_profile_id'), fn ($builder, $value) => $builder->where('student_profile_id', $value))
            ->when($request->query('course_section_id'), fn ($builder, $value) => $builder->where('course_section_id', $value))
            ->when($request->query('daily_log_type_item_id'), fn ($builder, $value) => $builder->where('daily_log_type_item_id', $value))
            ->when($request->query('from'), fn ($builder, $value) => $builder->whereDate('happened_at', '>=', $value))
            ->when($request->query('to'), fn ($builder, $value) => $builder->whereDate('happened_at', '<=', $value));

        return response()->json($query->latest('happened_at')->paginate((int) $request->query('per_page', 12)));
    }

    public function store(SaveConvivenciaDailyLogRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaDailyLog::class);

        $dailyLog = $this->dailyLogService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Hecho diario registrado correctamente.',
            'data' => $dailyLog,
        ], 201);
    }

    public function show(ConvivenciaDailyLog $dailyLog): JsonResponse
    {
        $this->authorize('view', $dailyLog);

        return response()->json([
            'data' => $dailyLog->load([
                'case:id,folio,status',
                'generatedDerivation:id,scope,status,destination_label',
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'student:id,first_name,last_name,registered_name,rut',
                'type:id,name',
                'inspectorUser:id,name',
                'inspectorStaff:id,full_name',
                'attachments.uploadedBy:id,name',
                'statusLogs.changedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveConvivenciaDailyLogRequest $request, ConvivenciaDailyLog $dailyLog): JsonResponse
    {
        $this->authorize('update', $dailyLog);

        $updated = $this->dailyLogService->update($dailyLog, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Hecho diario actualizado correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(ConvivenciaDailyLog $dailyLog): JsonResponse
    {
        $this->authorize('delete', $dailyLog);

        $dailyLog->delete();

        return response()->json([
            'message' => 'Hecho diario archivado correctamente.',
        ]);
    }

    public function convertToCase(Request $request, ConvivenciaDailyLog $dailyLog): JsonResponse
    {
        $this->authorize('update', $dailyLog);

        $payload = $request->validate([
            'case_type_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'classification_item_id' => ['required', 'integer', 'exists:convivencia_catalog_items,id'],
            'subclassification_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'criticality_item_id' => ['required', 'integer', 'exists:convivencia_catalog_items,id'],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'follow_up_due_at' => ['nullable', 'date'],
            'is_sensitive' => ['sometimes', 'boolean'],
        ]);

        $case = $this->dailyLogService->convertToCase($dailyLog, $payload, $request->user());

        return response()->json([
            'message' => 'El hecho diario fue convertido correctamente en un caso.',
            'data' => $case,
        ]);
    }

    public function convertToDerivation(Request $request, ConvivenciaDailyLog $dailyLog): JsonResponse
    {
        $this->authorize('update', $dailyLog);

        $validator = Validator::make($request->all(), [
            'case_id' => ['nullable', 'integer', 'exists:convivencia_cases,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'destination_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'destination_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'destination_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'external_institution_id' => ['nullable', 'integer', 'exists:convivencia_external_institutions,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scope' => ['required', Rule::in(array_column(\App\Models\Convivencia\ConvivenciaDerivation::SCOPE_OPTIONS, 'value'))],
            'status' => ['required', Rule::in(array_column(\App\Models\Convivencia\ConvivenciaDerivation::STATUS_OPTIONS, 'value'))],
            'priority_level' => ['required', Rule::in(array_column(\App\Models\Convivencia\ConvivenciaDerivation::PRIORITY_OPTIONS, 'value'))],
            'confidentiality_level' => ['required', 'string', 'max:50'],
            'destination_label' => ['nullable', 'string', 'max:191'],
            'external_contact_name' => ['nullable', 'string', 'max:160'],
            'external_contact_email' => ['nullable', 'email', 'max:191'],
            'external_contact_phone' => ['nullable', 'string', 'max:80'],
            'derived_at' => ['nullable', 'date'],
            'sent_at' => ['nullable', 'date'],
            'response_due_at' => ['nullable', 'date'],
            'responded_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'motive' => ['required', 'string'],
            'narrative' => ['nullable', 'string'],
            'response_text' => ['nullable', 'string'],
            'suggested_actions' => ['nullable', 'string'],
            'follow_up_notes' => ['nullable', 'string'],
            'is_sensitive' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $scope = $request->input('scope');

            if ($scope === 'internal' && !$request->filled('destination_department_id') && !$request->filled('destination_staff_id') && !$request->filled('destination_user_id') && !$request->filled('destination_label')) {
                $validator->errors()->add('destination_label', 'Debes indicar un destinatario interno para la derivación.');
            }

            if ($scope === 'external' && !$request->filled('external_institution_id') && !$request->filled('destination_label')) {
                $validator->errors()->add('external_institution_id', 'Debes indicar una institución externa para la derivación.');
            }
        });

        $payload = $validator->validate();
        $derivation = $this->dailyLogService->convertToDerivation($dailyLog, $payload, $request->user());

        return response()->json([
            'message' => 'El hecho diario fue convertido correctamente en una derivación.',
            'data' => $derivation,
        ]);
    }
}
