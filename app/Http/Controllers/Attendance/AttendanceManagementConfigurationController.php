<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SaveAttendanceCauseCatalogRequest;
use App\Http\Requests\Attendance\SaveAttendanceInterventionTypeRequest;
use App\Http\Requests\Attendance\UpdateAttendanceManagementSettingsRequest;
use App\Models\Attendance\AttendanceAbsenceReason;
use App\Models\Attendance\AttendanceInterventionType;
use App\Models\Attendance\AttendanceManagementSetting;
use App\Models\User;
use App\Services\Attendance\AttendanceManagementAccessService;
use App\Services\Attendance\AttendanceManagementSettingsService;
use App\Services\Attendance\AttendanceStatisticsAuditService;
use App\Services\Attendance\AttendanceStatisticsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceManagementConfigurationController extends Controller
{
    public function __construct(
        private readonly AttendanceManagementAccessService $access,
        private readonly AttendanceManagementSettingsService $settings,
        private readonly AttendanceStatisticsAuditService $audit,
        private readonly AttendanceStatisticsCache $cache,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);
        $yearId = (int) $request->validate(['academic_year_id' => ['required', 'integer', 'exists:academic_years,id']])['academic_year_id'];

        return response()->json([
            'settings' => $this->settings->forYear($yearId),
            'absence_reasons' => AttendanceAbsenceReason::query()->orderBy('sort_order')->get(),
            'intervention_types' => AttendanceInterventionType::query()->orderBy('sort_order')->get(),
            'assignable_users' => ($this->access->canManageCases($request->user()) || $this->access->canManageInterventions($request->user()) || $this->access->canManagePlans($request->user()))
                ? User::query()->where('active', true)->whereNotNull('staff_id')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'case_statuses' => [
                ['value' => 'detected', 'label' => 'Detectado'], ['value' => 'observation', 'label' => 'En observación'],
                ['value' => 'family_contact', 'label' => 'Contacto familiar'], ['value' => 'cause_assessment', 'label' => 'Evaluación de causas'],
                ['value' => 'active_plan', 'label' => 'Plan activo'], ['value' => 'follow_up', 'label' => 'Seguimiento'],
                ['value' => 'improvement', 'label' => 'Mejora'], ['value' => 'closed', 'label' => 'Cerrado'], ['value' => 'reopened', 'label' => 'Reabierto'],
            ],
        ]);
    }

    public function updateSettings(UpdateAttendanceManagementSettingsRequest $request): JsonResponse
    {
        $data = $request->safe()->except('reason');
        $setting = AttendanceManagementSetting::query()->updateOrCreate(
            ['academic_year_id' => $data['academic_year_id']],
            [...collect($data)->except('academic_year_id')->all(), 'updated_by' => $request->user()->id],
        );
        $this->audit->log('attendance_management_settings_updated', $setting, $request->user(), newValues: $setting->getAttributes(), reason: $request->string('reason')->toString(), request: $request);
        $this->cache->invalidate();

        return response()->json(['settings' => $this->settings->forYear((int) $data['academic_year_id'])]);
    }

    public function storeCause(SaveAttendanceCauseCatalogRequest $request): JsonResponse
    {
        $data = $request->safe()->except('reason');
        $cause = AttendanceAbsenceReason::query()->create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->audit->log('attendance_cause_created', $cause, $request->user(), newValues: $cause->getAttributes(), reason: $request->string('reason')->toString(), request: $request);

        return response()->json($cause, 201);
    }

    public function updateCause(SaveAttendanceCauseCatalogRequest $request, AttendanceAbsenceReason $attendanceAbsenceReason): JsonResponse
    {
        $before = $attendanceAbsenceReason->getAttributes();
        $attendanceAbsenceReason->update([...$request->safe()->except('reason'), 'updated_by' => $request->user()->id]);
        $this->audit->log('attendance_cause_updated', $attendanceAbsenceReason, $request->user(), $before, $attendanceAbsenceReason->fresh()->getAttributes(), $request->string('reason')->toString(), $request);

        return response()->json($attendanceAbsenceReason->fresh());
    }

    public function storeInterventionType(SaveAttendanceInterventionTypeRequest $request): JsonResponse
    {
        $data = $request->safe()->except('reason');
        $type = AttendanceInterventionType::query()->create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->audit->log('attendance_intervention_type_created', $type, $request->user(), newValues: $type->getAttributes(), reason: $request->string('reason')->toString(), request: $request);

        return response()->json($type, 201);
    }

    public function updateInterventionType(SaveAttendanceInterventionTypeRequest $request, AttendanceInterventionType $attendanceInterventionType): JsonResponse
    {
        $before = $attendanceInterventionType->getAttributes();
        $attendanceInterventionType->update([...$request->safe()->except('reason'), 'updated_by' => $request->user()->id]);
        $this->audit->log('attendance_intervention_type_updated', $attendanceInterventionType, $request->user(), $before, $attendanceInterventionType->fresh()->getAttributes(), $request->string('reason')->toString(), $request);

        return response()->json($attendanceInterventionType->fresh());
    }
}
