<?php

namespace App\Http\Resources\LibroDigital;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof BackedEnum ? $this->status->value : $this->status;
        $attendanceCount = (int) ($this->attendance_count ?? 0);
        $expectedCount = $this->relationLoaded('rosterSnapshot') && $this->rosterSnapshot?->relationLoaded('items')
            ? $this->rosterSnapshot->items->where('applicability_status', 'applicable')->count()
            : null;

        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'school_id' => $this->school_id,
            'book_id' => $this->book_id,
            'teaching_group_id' => $this->teaching_group_id,
            'schedule_subject_id' => $this->schedule_subject_id,
            'roster_snapshot_id' => $this->roster_snapshot_id,
            'scheduled_date' => $this->session_date?->format('Y-m-d'),
            'date' => $this->session_date?->format('Y-m-d'),
            'start_time' => $this->scheduled_start_at?->format('H:i'),
            'end_time' => $this->scheduled_end_at?->format('H:i'),
            'actual_start_at' => $this->actual_start_at?->toIso8601String(),
            'actual_end_at' => $this->actual_end_at?->toIso8601String(),
            'school_day_block_id' => $this->school_day_block_id,
            'block' => $this->whenLoaded('schoolDayBlock', fn () => [
                'id' => $this->schoolDayBlock?->id,
                'label' => $this->schoolDayBlock?->label,
                'start_time' => $this->schoolDayBlock?->start_time,
                'end_time' => $this->schoolDayBlock?->end_time,
            ]),
            'block_label' => $this->schoolDayBlock?->label,
            'room_name' => $this->scheduleEvent?->room_name,
            'modality' => $this->class_type,
            'notes' => $this->observation,
            'teacher_staff_id' => $this->actual_teacher_id,
            'teacher_name' => $this->teacher_name_snapshot,
            'teacher' => $this->whenLoaded('actualTeacher', fn () => [
                'id' => $this->actualTeacher?->id,
                'name' => $this->actualTeacher?->full_name,
                'full_name' => $this->actualTeacher?->full_name,
            ]),
            'course_name' => $this->course_snapshot,
            'subject_name' => $this->subject_snapshot,
            'status' => $status,
            'attendance_status' => in_array($status, ['ready_to_sign', 'signing', 'signed', 'closed'], true)
                ? 'completed'
                : (($expectedCount !== null && $attendanceCount >= $expectedCount) ? 'completed' : ($attendanceCount > 0 ? 'in_progress' : 'pending')),
            'lesson_record_status' => filled($this->content_summary) && filled($this->activity_summary) ? 'completed' : 'pending',
            'revision' => (int) $this->revision,
            'lock_version' => (int) $this->lock_version,
            'canonical_hash' => $this->canonical_hash,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
