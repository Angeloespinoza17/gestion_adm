<?php

namespace App\Http\Resources;

use App\Models\RiskPrevention\RiskPreventionJointCommittee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteOrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof RiskPreventionJointCommittee) {
            return $this->committee();
        }

        return [
            'type' => $this->type,
            'id' => $this->id,
            'name' => $this->name,
            'year' => (int) $this->year,
            'starts_on' => null,
            'ends_on' => null,
            'summary' => $this->summary,
            'status' => $this->status,
            'active' => (bool) $this->active,
            'published_at' => $this->published_at?->toISOString(),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($member) => [
                'id' => $member->id,
                'member_kind' => $member->member_kind,
                'student_id' => $member->student_profile_id,
                'student_profile_id' => $member->student_profile_id,
                'staff_id' => $member->staff_id,
                'display_name' => $member->display_name_snapshot,
                'detail' => $member->detail_snapshot,
                'detail_snapshot' => $member->detail_snapshot,
                'course_label' => $member->member_kind === 'student' ? $member->detail_snapshot : null,
                'position_name' => $member->role?->name,
                'role_id' => $member->role_id,
                'role' => $member->role ? [
                    'id' => $member->role->id,
                    'name' => $member->role->name,
                    'section' => $member->role->section,
                ] : null,
                'section' => $member->section,
                'sort_order' => (int) $member->sort_order,
                'public_name_authorized' => (bool) $member->public_name_authorized,
                'public_name_authorized_at' => $member->public_name_authorized_at?->toISOString(),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function committee(): array
    {
        return [
            'type' => 'joint_committee',
            'id' => $this->id,
            'name' => $this->name,
            'year' => $this->starts_on?->year,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'summary' => $this->web_summary,
            'status' => $this->web_status,
            'active' => (bool) $this->active,
            'published_at' => $this->web_published_at?->toISOString(),
            'members' => $this->whenLoaded('staffMembers', fn () => $this->staffMembers
                ->sortBy([
                    fn ($a, $b) => ((int) $a->pivot->sort_order) <=> ((int) $b->pivot->sort_order),
                    fn ($a, $b) => strcmp($a->full_name, $b->full_name),
                ])
                ->map(fn ($staff) => [
                    'id' => $staff->pivot->id,
                    'member_kind' => 'staff',
                    'student_id' => null,
                    'staff_id' => $staff->id,
                    'display_name' => $staff->full_name,
                    'detail' => $staff->cargo?->name,
                    'detail_snapshot' => $staff->cargo?->name,
                    'position_name' => $staff->pivot->position_name,
                    'section' => $staff->pivot->section,
                    'sort_order' => (int) $staff->pivot->sort_order,
                    'representation' => $staff->pivot->representation,
                    'member_role' => $staff->pivot->member_role,
                    'joined_on' => $staff->pivot->joined_on,
                    'ended_on' => $staff->pivot->ended_on,
                    'active' => (bool) $staff->pivot->active,
                    'public_name_authorized' => (bool) $staff->pivot->public_name_authorized,
                    'public_name_authorized_at' => $staff->pivot->public_name_authorized_at,
                ])
                ->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
