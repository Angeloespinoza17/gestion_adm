<?php

namespace App\Http\Resources\LibroDigital;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $group = $this->relationLoaded('teachingGroups') ? $this->teachingGroups->first() : null;
        $assignment = $group?->relationLoaded('teacherAssignments')
            ? ($group->teacherAssignments->firstWhere('is_primary', true) ?: $group->teacherAssignments->first())
            : null;
        $status = $this->status instanceof BackedEnum ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'school_id' => $this->school_id,
            'academic_year_id' => $this->academic_year_id,
            'course_section_id' => $this->course_section_id,
            'schedule_subject_id' => $group?->schedule_subject_id,
            'teacher_staff_id' => $assignment?->staff_id,
            'teaching_group_id' => $group?->id,
            'teaching_group_public_id' => $group?->public_id,
            'name' => $group?->name ?: $this->course_label,
            'display_name' => collect([$this->course_label, $group?->subject_snapshot])->filter()->join(' · '),
            'code' => $this->code,
            'modality' => $this->modality_code,
            'notes' => $group?->metadata['notes'] ?? null,
            'normative_profile_id' => $this->regulatory_profile_id,
            'status' => $status,
            'revision' => (int) $this->revision,
            'lock_version' => (int) $this->lock_version,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear?->id,
                'name' => $this->academicYear?->name,
                'year' => $this->academicYear?->year,
            ]),
            'course' => $this->whenLoaded('courseSection', fn () => [
                'id' => $this->courseSection?->id,
                'display_name' => $this->courseSection?->display_name,
                'section_name' => $this->courseSection?->section_name,
            ]),
            'subject' => $group?->relationLoaded('subject') ? [
                'id' => $group->subject?->id,
                'name' => $group->subject?->name,
                'code' => $group->subject?->code,
                'color' => $group->subject?->color,
            ] : null,
            'teacher' => $assignment?->relationLoaded('staff') ? [
                'id' => $assignment->staff?->id,
                'name' => $assignment->staff?->full_name,
                'full_name' => $assignment->staff?->full_name,
            ] : null,
            'teacher_name' => $assignment?->teacher_name_snapshot,
            'regulatory_profile' => $this->whenLoaded('regulatoryProfile', fn () => [
                'id' => $this->regulatoryProfile?->id,
                'code' => $this->regulatoryProfile?->code,
                'name' => $this->regulatoryProfile?->name,
                'version' => $this->regulatoryProfile?->version,
            ]),
            'sessions_count' => (int) ($this->sessions_count ?? 0),
            'roster_count' => (int) ($group?->getAttribute('roster_count') ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
