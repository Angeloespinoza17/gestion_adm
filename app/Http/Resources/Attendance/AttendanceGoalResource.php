<?php

namespace App\Http\Resources\Attendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'academic_year_id' => $this->academic_year_id, 'name' => $this->name,
            'scope_type' => $this->scope_type, 'scope_id' => $this->scope_id,
            'student_profile_id' => $this->student_profile_id, 'starts_on' => $this->starts_on?->format('Y-m-d'),
            'ends_on' => $this->ends_on?->format('Y-m-d'), 'target_rate' => (float) $this->target_rate,
            'status' => $this->status, 'justification' => $this->justification,
            'responsible' => $this->whenLoaded('responsible', fn () => $this->responsible?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
