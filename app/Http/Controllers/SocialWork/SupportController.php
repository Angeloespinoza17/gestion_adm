<?php

namespace App\Http\Controllers\SocialWork;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Pme\PmeStudentSepClassification;
use App\Models\SocialWork\JunaebBenefit;
use App\Models\SocialWork\JunaebDelivery;
use App\Models\SocialWork\MedicalService;
use App\Models\SocialWork\ProgramType;
use App\Models\SocialWork\ProtectionMeasure;
use App\Models\SocialWork\StudentProgram;
use App\Models\SocialWork\SupportDevice;
use App\Models\SocialWork\TransportPass;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Services\Attendance\StudentMonthlyAttendanceContextService;
use App\Services\SocialWork\AuditService;
use App\Services\SocialWork\JunaebService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    public function studentOptions(): JsonResponse
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        if (! $activeYear) {
            return response()->json(['data' => [], 'academic_year' => null]);
        }

        $students = StudentProfile::query()
            ->select(['id', 'first_name', 'last_name', 'registered_name', 'rut'])
            ->where('general_status', 'activo')
            ->whereHas('enrollments', fn (Builder $query) => $query
                ->where('academic_year_id', $activeYear->id)
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES))
            ->with(['enrollments' => fn ($query) => $query
                ->where('academic_year_id', $activeYear->id)
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
                ->with([
                    'academicYear:id,name,year',
                    'courseSection:id,display_name,education_level_id',
                    'courseSection.educationLevel:id,name,order,type',
                ])
                ->latest('id')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (StudentProfile $student) use ($activeYear): array {
                $enrollment = $student->preferredEnrollment($activeYear);

                return [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'registered_name' => $student->registered_name,
                    'registered_name_resolved' => $student->registered_name_resolved,
                    'rut' => $student->rut,
                    'current_enrollment' => $enrollment,
                    'transport_pass_eligible' => $this->isTransportPassEligible($enrollment),
                ];
            });

        return response()->json([
            'data' => $students,
            'academic_year' => $activeYear->only(['id', 'name', 'year']),
        ]);
    }

    public function supportMatrix(Request $request, StudentMonthlyAttendanceContextService $attendanceContext): JsonResponse
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $schoolYear = $request->integer('school_year') ?: (int) ($activeYear?->year ?: now()->year);
        $activeYearId = $activeYear?->id;
        $search = trim((string) $request->query('search'));

        $query = StudentProfile::query()
            ->select([
                'id', 'first_name', 'last_name', 'registered_name', 'rut', 'general_status',
                'health_insurance', 'height_cm', 'weight_kg', 'blood_type', 'food_allergies',
                'has_chronic_illness', 'chronic_illness_details', 'has_medication_allergies',
                'medication_allergies_details', 'contraindicated_medications',
                'fit_for_physical_education', 'has_private_school_insurance', 'healthcare_provider',
                'health_observations', 'has_physical_restrictions', 'physical_restrictions_details',
                'is_pie_participant', 'pie_permanence_type', 'pie_diagnosis',
            ])
            ->with([
                'enrollments' => fn ($query) => $query
                    ->when($activeYearId, fn ($inner) => $inner->where('academic_year_id', $activeYearId))
                    ->with(['academicYear:id,name,year', 'courseSection:id,display_name,education_level_id', 'courseSection.educationLevel:id,name'])
                    ->latest('id'),
                'socialPrograms' => fn ($query) => $query
                    ->where('school_year', $schoolYear)
                    ->where('status', 'vigente')
                    ->with('programType:id,code,name,category'),
                'junaebBenefits' => fn ($query) => $query
                    ->where('school_year', $schoolYear)
                    ->whereNotIn('status', ['anulado', 'rechazado'])
                    ->with(['benefitType:id,code,name,category', 'deliveries.items']),
                'transportPasses' => fn ($query) => $query
                    ->where('school_year', $schoolYear)
                    ->latest('id'),
                'medicalServices' => fn ($query) => $query->latest('id'),
                'supportDevices' => fn ($query) => $query->where('active', true)->latest('id'),
                'protectionMeasures' => fn ($query) => $query->where('status', 'vigente')->latest('starts_on'),
                'socialAlerts' => fn ($query) => $query
                    ->whereNotIn('status', ['resuelta', 'descartada'])
                    ->where(fn (Builder $inner) => $inner
                        ->where('type', 'like', '%medic%')
                        ->orWhere('type', 'like', '%salud%'))
                    ->latest('alerted_at'),
                'sepClassifications' => fn ($query) => $query
                    ->when($activeYearId, fn ($inner) => $inner->where('academic_year_id', $activeYearId))
                    ->latest('id'),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('registered_name', 'like', "%{$search}%")
                ->orWhere('rut', 'like', "%{$search}%")))
            ->when($request->filled('course_section_id'), fn (Builder $query) => $query
                ->whereHas('enrollments', fn (Builder $inner) => $inner
                    ->where('course_section_id', $request->integer('course_section_id'))
                    ->when($activeYearId, fn (Builder $yearQuery) => $yearQuery->where('academic_year_id', $activeYearId))))
            ->when($request->query('program') === 'junaeb', fn (Builder $query) => $query
                ->where(fn (Builder $inner) => $inner
                    ->whereHas('socialPrograms', fn (Builder $program) => $program
                        ->where('school_year', $schoolYear)
                        ->where('status', 'vigente')
                        ->whereHas('programType', fn (Builder $type) => $type->where('code', 'junaeb')))
                    ->orWhereHas('junaebBenefits', fn (Builder $benefit) => $benefit
                        ->where('school_year', $schoolYear)
                        ->whereNotIn('status', ['anulado', 'rechazado']))))
            ->when($request->query('program') === 'pro_retencion', fn (Builder $query) => $query
                ->whereHas('socialPrograms', fn (Builder $program) => $program
                    ->where('school_year', $schoolYear)
                    ->where('status', 'vigente')
                    ->whereHas('programType', fn (Builder $type) => $type->where('code', 'pro_retencion'))))
            ->when($request->boolean('medical_alert'), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('has_chronic_illness', true)
                ->orWhere('has_medication_allergies', true)
                ->orWhere('has_physical_restrictions', true)
                ->orWhereNotNull('food_allergies')
                ->orWhereHas('socialAlerts', fn (Builder $alert) => $alert
                    ->whereNotIn('status', ['resuelta', 'descartada'])
                    ->where(fn (Builder $alertType) => $alertType
                        ->where('type', 'like', '%medic%')
                        ->orWhere('type', 'like', '%salud%')))))
            ->orderBy('last_name')
            ->orderBy('first_name');

        $page = $query->paginate($this->perPage($request, 20, 100));
        $attendanceProfiles = $attendanceContext->forStudents($page->getCollection()->pluck('id'), $activeYearId);
        $page->getCollection()->transform(function (StudentProfile $student) use ($attendanceProfiles) {
            $programs = $student->socialPrograms;
            $programCodes = $programs->pluck('programType.code')->filter();
            $externalPrograms = $programs
                ->filter(fn (StudentProgram $program) => $program->programType?->category === 'programa_externo'
                    && $program->programType?->code !== 'pro_retencion')
                ->map(fn (StudentProgram $program) => filled($program->notes) ? $program->notes : $program->programType?->name)
                ->filter()
                ->values();
            $hasJunaeb = $programCodes->contains('junaeb') || $student->junaebBenefits->isNotEmpty();
            $hasMedicalAlert = $student->has_chronic_illness
                || $student->has_medication_allergies
                || $student->has_physical_restrictions
                || filled($student->food_allergies)
                || $student->socialAlerts->isNotEmpty();

            $student->setAttribute('current_enrollment', $student->enrollments->first());
            $student->setAttribute('program_flags', [
                'junaeb' => $hasJunaeb,
                'pro_retencion' => $programCodes->contains('pro_retencion'),
                'external' => $externalPrograms,
            ]);
            $student->setAttribute('sep_classification', $student->sepClassifications->first()?->classification);
            $student->setAttribute('attendance_profile', $attendanceProfiles->get($student->id));
            $student->setAttribute('medical_alert', $hasMedicalAlert);
            $student->setAttribute('protection_summary', [
                'has_measure' => $student->protectionMeasures->isNotEmpty(),
                'measure_types' => $student->protectionMeasures->pluck('measure_type')->values(),
                'pie' => (bool) $student->is_pie_participant,
                'unresolved_medical_alerts' => $student->socialAlerts->count(),
            ]);
            $student->setAttribute('health', [
                'insurance' => $student->health_insurance,
                'provider' => $student->healthcare_provider,
                'blood_type' => $student->blood_type,
                'height_cm' => $student->height_cm,
                'weight_kg' => $student->weight_kg,
                'food_allergies' => $student->food_allergies,
                'has_chronic_illness' => (bool) $student->has_chronic_illness,
                'chronic_illness_details' => $student->chronic_illness_details,
                'has_medication_allergies' => (bool) $student->has_medication_allergies,
                'medication_allergies_details' => $student->medication_allergies_details,
                'contraindicated_medications' => $student->contraindicated_medications,
                'fit_for_physical_education' => (bool) $student->fit_for_physical_education,
                'has_physical_restrictions' => (bool) $student->has_physical_restrictions,
                'physical_restrictions_details' => $student->physical_restrictions_details,
                'observations' => $student->health_observations,
            ]);

            $student->unsetRelation('enrollments');
            $student->unsetRelation('socialPrograms');
            $student->unsetRelation('sepClassifications');
            $student->unsetRelation('socialAlerts');

            return $student;
        });

        return response()->json($page);
    }

    public function updateSupportProfile(Request $request, StudentProfile $student, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'is_pie_participant' => ['required', 'boolean'],
            'pie_permanence_type' => ['nullable', 'string', 'max:100'],
            'pie_diagnosis' => ['nullable', 'string', 'max:2000'],
            'sep_classification' => ['nullable', 'in:none,prioritaria,preferente,pendiente_validacion'],
            'junaeb' => ['required', 'boolean'],
            'pro_retencion' => ['required', 'boolean'],
            'external_program' => ['required', 'boolean'],
            'external_program_name' => ['nullable', 'string', 'max:255'],
        ]);

        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        abort_unless($activeYear, 422, 'No existe un año académico activo para registrar la situación social.');
        $student->loadMissing('enrollments');
        $enrollment = $student->preferredEnrollment($activeYear);
        $before = [
            'is_pie_participant' => (bool) $student->is_pie_participant,
            'pie_permanence_type' => $student->pie_permanence_type,
            'sep_classification' => $student->sepClassifications()->where('academic_year_id', $activeYear->id)->latest('id')->value('classification'),
        ];

        DB::transaction(function () use ($data, $student, $activeYear, $enrollment, $request): void {
            $student->update([
                'is_pie_participant' => $data['is_pie_participant'],
                'pie_permanence_type' => $data['is_pie_participant'] ? ($data['pie_permanence_type'] ?? null) : null,
                'pie_diagnosis' => $data['is_pie_participant'] ? ($data['pie_diagnosis'] ?? null) : null,
                'updated_by' => $request->user()->id,
            ]);

            $sep = PmeStudentSepClassification::withTrashed()
                ->where('student_profile_id', $student->id)
                ->where('academic_year_id', $activeYear->id)
                ->first();
            if (($data['sep_classification'] ?? 'none') === 'none') {
                $sep?->delete();
            } elseif ($sep) {
                $sep->restore();
                $sep->update([
                    'course_section_id' => $enrollment?->course_section_id,
                    'classification' => $data['sep_classification'], 'state' => 'vigente',
                    'source' => 'Trabajo Social', 'loaded_at' => today(), 'updated_by' => $request->user()->id,
                ]);
            } else {
                PmeStudentSepClassification::create([
                    'student_profile_id' => $student->id, 'course_section_id' => $enrollment?->course_section_id,
                    'academic_year_id' => $activeYear->id, 'classification' => $data['sep_classification'],
                    'state' => 'vigente', 'source' => 'Trabajo Social', 'loaded_at' => today(),
                    'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
                ]);
            }

            foreach ([
                'junaeb' => [$data['junaeb'], null],
                'pro_retencion' => [$data['pro_retencion'], null],
                'otro_externo' => [$data['external_program'], $data['external_program_name'] ?? null],
            ] as $code => [$enabled, $notes]) {
                $type = ProgramType::query()->firstWhere('code', $code);
                if (! $type) {
                    continue;
                }
                $program = StudentProgram::withTrashed()
                    ->where('student_profile_id', $student->id)
                    ->where('program_type_id', $type->id)
                    ->where('school_year', $activeYear->year)
                    ->latest('id')->first();
                if ($enabled) {
                    $payload = [
                        'status' => 'vigente', 'starts_on' => $program?->starts_on ?: today(), 'ends_on' => null,
                        'notes' => $notes, 'confidentiality' => 'interno', 'updated_by' => $request->user()->id,
                    ];
                    if ($program) {
                        $program->restore();
                        $program->update($payload);
                    } else {
                        StudentProgram::create([...$payload,
                            'student_profile_id' => $student->id, 'program_type_id' => $type->id,
                            'school_year' => $activeYear->year, 'created_by' => $request->user()->id,
                        ]);
                    }
                } elseif ($program && $program->status === 'vigente') {
                    $program->update(['status' => 'finalizado', 'ends_on' => today(), 'updated_by' => $request->user()->id]);
                }
            }
        });

        $audit->record('student_support.updated', $student, $request->user(), $before, [
            'is_pie_participant' => $data['is_pie_participant'],
            'pie_permanence_type' => $data['pie_permanence_type'] ?? null,
            'sep_classification' => $data['sep_classification'] ?? 'none',
            'junaeb' => $data['junaeb'], 'pro_retencion' => $data['pro_retencion'],
            'external_program' => $data['external_program'],
        ]);

        return response()->json(['message' => 'Situación social actualizada con trazabilidad.']);
    }

    public function programs(Request $request): JsonResponse
    {
        return response()->json(StudentProgram::query()
            ->with(['student:id,first_name,last_name,registered_name,rut', 'programType'])
            ->when($request->query('student_profile_id'), fn (Builder $query, $value) => $query->where('student_profile_id', $value))
            ->paginate($this->perPage($request)));
    }

    public function storeProgram(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'program_type_id' => ['required', 'exists:social_work_program_types,id'],
            'school_year' => ['required', 'integer', 'between:2000,2100'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'confidentiality' => ['required', 'in:interno,restringido,altamente_restringido'],
        ]);

        return $this->created(StudentProgram::create(array_merge($data, [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ])));
    }

    public function protectionMeasures(Request $request): JsonResponse
    {
        return response()->json(ProtectionMeasure::query()
            ->with(['student:id,first_name,last_name,registered_name,rut', 'responsible:id,name'])
            ->when($request->query('student_profile_id'), fn (Builder $query, $value) => $query->where('student_profile_id', $value))
            ->paginate($this->perPage($request)));
    }

    public function storeProtectionMeasure(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'case_id' => ['nullable', 'exists:social_work_cases,id'],
            'measure_type' => ['required', 'string'],
            'authority' => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', 'string'],
            'school_scope' => ['nullable', 'string'],
            'confidentiality' => ['required', 'in:restringido,altamente_restringido'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
        ]);

        return $this->created(ProtectionMeasure::create(array_merge($data, ['created_by' => $request->user()->id])));
    }

    public function benefits(Request $request): JsonResponse
    {
        return response()->json(JunaebBenefit::query()
            ->with([
                'student:id,first_name,last_name,registered_name,rut',
                'benefitType',
                'courseSection:id,display_name',
                'deliveries.items',
            ])
            ->when($request->query('status'), fn (Builder $query, $value) => $query->where('status', $value))
            ->when($request->query('school_year'), fn (Builder $query, $value) => $query->where('school_year', $value))
            ->paginate($this->perPage($request)));
    }

    public function storeBenefit(Request $request, JunaebService $service): JsonResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'benefit_type_id' => ['required', 'exists:junaeb_benefit_types,id'],
            'course_section_id' => ['nullable', 'exists:course_sections,id'],
            'school_year' => ['required', 'integer', 'between:2000,2100'],
            'status' => ['required', 'string'],
            'approved_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return $this->created($service->registerBenefit($data, $request->user()));
    }

    public function deliveries(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        return response()->json(JunaebDelivery::query()
            ->with([
                'benefit.student:id,first_name,last_name,registered_name,rut',
                'benefit.benefitType:id,code,name,category',
                'benefit.courseSection:id,display_name',
                'items',
                'responsible:id,name',
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('folio', 'like', "%{$search}%")
                ->orWhere('receiver_name', 'like', "%{$search}%")
                ->orWhereHas('benefit.student', fn (Builder $student) => $student
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('registered_name', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%"))))
            ->when($request->query('status'), fn (Builder $query, $value) => $query->where('status', $value))
            ->latest('delivered_on')
            ->latest('id')
            ->paginate($this->perPage($request)));
    }

    public function deliver(Request $request, JunaebBenefit $benefit, JunaebService $service): JsonResponse
    {
        $data = $request->validate([
            'received_by_school_on' => ['nullable', 'date'],
            'delivered_on' => ['nullable', 'date'],
            'receiver_name' => ['nullable', 'string'],
            'receiver_relationship' => ['nullable', 'string'],
            'status' => ['required', 'in:preparada,recibida,entregada,anulada'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string'],
        ]);

        return $this->created($service->deliver($benefit, $data, $request->user()));
    }

    public function cloneDelivery(Request $request, JunaebDelivery $delivery, JunaebService $service): JsonResponse
    {
        return $this->created($service->cloneDelivery($delivery, $request->user()));
    }

    public function bulkDeliver(Request $request, JunaebService $service): JsonResponse
    {
        $data = $request->validate([
            'deliveries' => ['required', 'array', 'min:1', 'max:200'],
            'deliveries.*.benefit_id' => ['required', 'exists:student_junaeb_benefits,id'],
            'deliveries.*.delivered_on' => ['required', 'date'],
            'deliveries.*.receiver_name' => ['required', 'string'],
            'deliveries.*.receiver_relationship' => ['nullable', 'string'],
            'deliveries.*.items' => ['nullable', 'array'],
        ]);

        $created = DB::transaction(function () use ($data, $request, $service) {
            return collect($data['deliveries'])->map(function ($row) use ($request, $service) {
                $benefit = JunaebBenefit::findOrFail($row['benefit_id']);
                unset($row['benefit_id']);

                return $service->deliver($benefit, array_merge($row, ['status' => 'entregada']), $request->user());
            });
        });

        return response()->json(['message' => 'Entregas masivas registradas.', 'data' => $created], 201);
    }

    public function transportPasses(Request $request): JsonResponse
    {
        return response()->json(TransportPass::query()
            ->with(['student:id,first_name,last_name,registered_name,rut', 'responsible:id,name'])
            ->when($request->query('status'), fn (Builder $query, $value) => $query->where('status', $value))
            ->when($request->query('school_year'), fn (Builder $query, $value) => $query->where('school_year', $value))
            ->latest('id')
            ->paginate($this->perPage($request)));
    }

    public function storeTransportPass(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'school_year' => ['required', 'integer'],
            'pass_type' => ['required', 'string'],
            'status' => ['required', 'string'],
            'identifier' => ['nullable', 'string'],
            'requested_on' => ['nullable', 'date'],
            'received_on' => ['nullable', 'date'],
            'delivered_on' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        return $this->created(TransportPass::create(array_merge($data, ['created_by' => $request->user()->id])));
    }

    public function updateTransportPass(Request $request, TransportPass $pass): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:posee,pendiente_solicitud,pendiente_reposicion,pendiente_entrega,recibido'],
            'identifier' => ['nullable', 'string'],
            'requested_on' => ['nullable', 'date'],
            'received_on' => ['nullable', 'date'],
            'delivered_on' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);
        $pass->update($data);

        return response()->json(['message' => 'Pase actualizado.', 'data' => $pass->load('student')]);
    }

    public function medicalServices(Request $request): JsonResponse
    {
        return response()->json(MedicalService::query()
            ->with('student:id,first_name,last_name,registered_name,rut')
            ->when($request->query('status'), fn (Builder $query, $value) => $query->where('status', $value))
            ->when($request->filled('is_junaeb'), fn (Builder $query) => $query->where('is_junaeb', $request->boolean('is_junaeb')))
            ->latest('id')
            ->paginate($this->perPage($request)));
    }

    public function storeMedicalService(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'service_type' => ['required', 'string'],
            'origin' => ['nullable', 'string'],
            'is_junaeb' => ['boolean'],
            'referred_on' => ['nullable', 'date'],
            'attended_on' => ['nullable', 'date'],
            'status' => ['required', 'string'],
            'general_result' => ['nullable', 'string'],
            'next_control_on' => ['nullable', 'date'],
            'provider' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'confidentiality' => ['required', 'in:interno,restringido,altamente_restringido'],
        ]);

        return $this->created(MedicalService::create(array_merge($data, ['created_by' => $request->user()->id])));
    }

    public function supportDevices(Request $request): JsonResponse
    {
        return response()->json(SupportDevice::query()
            ->with('student:id,first_name,last_name,registered_name,rut')
            ->when($request->query('student_profile_id'), fn (Builder $query, $value) => $query->where('student_profile_id', $value))
            ->when($request->filled('active'), fn (Builder $query) => $query->where('active', $request->boolean('active')))
            ->latest('id')
            ->paginate($this->perPage($request)));
    }

    public function storeSupportDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'support_type' => ['required', 'string'],
            'origin' => ['nullable', 'string'],
            'starts_on' => ['nullable', 'date'],
            'active' => ['boolean'],
            'renewal_on' => ['nullable', 'date'],
            'brief_note' => ['nullable', 'string'],
        ]);

        return $this->created(SupportDevice::create(array_merge($data, ['registered_by' => $request->user()->id])));
    }

    private function created(Model $model): JsonResponse
    {
        return response()->json(['message' => 'Registro guardado.', 'data' => $model], 201);
    }

    private function perPage(Request $request, int $default = 30, int $max = 100): int
    {
        return min(max($request->integer('per_page', $default), 1), $max);
    }

    private function isTransportPassEligible(?StudentEnrollment $enrollment): bool
    {
        $level = $enrollment?->courseSection?->educationLevel;
        $type = $level?->type;

        if (! in_array($type, ['basica', 'media'], true)) {
            return false;
        }

        $grade = $this->gradeFromLabel((string) ($level?->name ?: $enrollment?->courseSection?->display_name));

        return $type === 'basica'
            ? $grade !== null && $grade >= 5 && $grade <= 8
            : $grade !== null && $grade >= 1 && $grade <= 4;
    }

    private function gradeFromLabel(string $label): ?int
    {
        $normalized = strtolower(Str::ascii($label));
        if (preg_match('/(^|\D)([1-8])(?:\D|$)/', $normalized, $matches)) {
            return (int) $matches[2];
        }

        foreach ([
            'primero' => 1, 'primer' => 1, 'segundo' => 2, 'tercero' => 3, 'cuarto' => 4,
            'quinto' => 5, 'sexto' => 6, 'septimo' => 7, 'octavo' => 8,
        ] as $word => $grade) {
            if (str_contains($normalized, $word)) {
                return $grade;
            }
        }

        return null;
    }
}
