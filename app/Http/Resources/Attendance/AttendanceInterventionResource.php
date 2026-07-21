<?php

namespace App\Http\Resources\Attendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceInterventionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'folio' => $this->folio, 'academic_year_id' => $this->academic_year_id,
            'course_section_id' => $this->course_section_id, 'student_profile_id' => $this->student_profile_id,
            'attendance_alert_id' => $this->attendance_alert_id, 'convivencia_case_id' => $this->convivencia_case_id,
            'risk_level_id' => $this->risk_level_id, 'responsible_user_id' => $this->responsible_user_id,
            'status' => $this->status, 'probable_cause' => $this->probable_cause, 'description' => $this->description,
            'opened_at' => $this->opened_at?->toIso8601String(), 'first_contact_at' => $this->first_contact_at?->toIso8601String(),
            'first_action_at' => $this->first_action_at?->toIso8601String(), 'due_on' => $this->due_on?->format('Y-m-d'),
            'result' => $this->result, 'closed_at' => $this->closed_at?->toIso8601String(), 'closure_reason' => $this->closure_reason,
            'student' => $this->whenLoaded('studentProfile', fn () => ['id' => $this->studentProfile?->id, 'name' => $this->studentProfile?->registered_name_resolved, 'rut' => $this->studentProfile?->rut]),
            'course' => $this->whenLoaded('courseSection', fn () => $this->courseSection?->only(['id', 'display_name'])),
            'responsible' => $this->whenLoaded('responsible', fn () => $this->responsible?->only(['id', 'name'])),
            'risk_level' => $this->whenLoaded('riskLevel'),
            'actions' => $this->whenLoaded('actions'),
        ];
    }
}
