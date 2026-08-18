<?php

namespace App\Http\Resources\Psychology;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PsychologyReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $internal = $request->user()?->hasPermission('psychology.referrals.view_all') || (int) $this->assigned_user_id === (int) $request->user()?->id;

        return [
            'id' => $this->id, 'code' => $this->code, 'status' => $this->status, 'suggested_urgency' => $this->suggested_urgency,
            'professional_priority' => $this->professional_priority, 'origin_area' => $this->origin_area,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'name' => $this->student->registered_name_resolved, 'rut' => $this->student->rut]),
            'course' => $this->whenLoaded('course', fn () => ['id' => $this->course?->id, 'name' => $this->course?->display_name]),
            'referred_by' => $this->whenLoaded('referredBy', fn () => ['id' => $this->referredBy?->id, 'name' => $this->referredBy?->name]),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => ['id' => $this->assignedUser?->id, 'name' => $this->assignedUser?->name]),
            'case_id' => $this->case_id, 'primary_reason' => $this->primary_reason, 'secondary_reasons' => $this->secondary_reasons,
            'observed_facts' => $this->observed_facts, 'approximate_started_on' => $this->approximate_started_on,
            'people_involved' => $this->people_involved, 'measures_taken' => $this->measures_taken,
            'known_previous_interventions' => $this->known_previous_interventions, 'observed_risk_indicators' => $this->observed_risk_indicators,
            'immediate_response_needed' => $this->immediate_response_needed, 'guardian_informed' => $this->guardian_informed,
            'guardian_contact_status' => $this->guardian_contact_status, 'observations' => $this->observations,
            'information_request' => $this->information_request, 'information_response' => $this->information_response,
            'shared_decision_note' => $this->shared_decision_note, 'internal_decision_note' => $this->when($internal, $this->internal_decision_note),
            'referred_at' => $this->referred_at, 'first_reviewed_at' => $this->first_reviewed_at, 'updated_at' => $this->updated_at,
            'histories' => $this->whenLoaded('histories', fn () => $this->histories->map(fn ($history) => ['from' => $history->from_status, 'to' => $history->to_status, 'reason' => $history->reason, 'shared_note' => $history->shared_note, 'internal_note' => $internal ? $history->internal_note : null, 'changed_at' => $history->changed_at, 'changed_by' => $history->changedBy?->name])),
            'documents' => $this->whenLoaded('documents'),
        ];
    }
}
