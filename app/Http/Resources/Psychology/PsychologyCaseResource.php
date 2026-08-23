<?php

namespace App\Http\Resources\Psychology;

use App\Services\Psychology\PsychologyAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PsychologyCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $access = app(PsychologyAccessService::class);
        $private = $access->canViewPrivateNotes($request->user(), $this->resource);
        $risk = $access->canViewRisk($request->user(), $this->resource);

        return [
            'id' => $this->id, 'code' => $this->code, 'status' => $this->status, 'priority' => $this->priority,
            'confidentiality' => $this->confidentiality, 'origin' => $this->origin_referral_id ? 'referral' : 'direct', 'origin_referral_id' => $this->origin_referral_id, 'student' => $this->whenLoaded('student', function () {
                $enrollment = $this->student->relationLoaded('enrollments') ? $this->student->preferredEnrollment() : null;

                return [
                    'id' => $this->student->id,
                    'name' => $this->student->registered_name_resolved,
                    'rut' => $this->student->rut,
                    'course' => $enrollment?->snapshot_course_display_name,
                    'guardian_name' => $this->student->guardian_name,
                    'guardian_rut' => $this->student->guardian_rut,
                    'guardian_relationship' => $this->student->guardian_relationship,
                    'guardian_phone' => $this->student->guardian_phone,
                    'guardian_email' => $this->student->guardian_email,
                ];
            }),
            'responsible_user' => $this->whenLoaded('responsibleUser', fn () => ['id' => $this->responsibleUser->id, 'name' => $this->responsibleUser->name]),
            'general_reason' => $this->general_reason, 'categories' => $this->categories, 'objectives' => $this->objectives,
            'next_action' => $this->next_action, 'next_review_on' => $this->next_review_on?->toDateString(), 'opened_at' => $this->opened_at,
            'last_activity_at' => $this->last_activity_at, 'guardian_information_status' => $this->guardian_information_status,
            'closed_at' => $this->closed_at, 'closure_reason' => $this->closure_reason,
            'assignments' => $this->whenLoaded('assignments'), 'collaborators' => $this->whenLoaded('collaborators'),
            'referrals' => PsychologyReferralResource::collection($this->whenLoaded('referrals')),
            'plans' => $this->whenLoaded('plans'),
            'activities' => $this->whenLoaded('activities', function () use ($private, $access, $request) {
                $activities = $access->isScopedPsychologist($request->user())
                    ? $this->activities->where('responsible_user_id', $request->user()->id)
                    : $this->activities;

                return $activities->values()->map(function ($activity) use ($private) {
                    $hidden = ['private_note', 'general_background', 'interviewee_rut', 'acknowledged_rut'];

                    return array_merge(
                        $activity->makeHidden($private ? [] : $hidden)->toArray(),
                        collect($hidden)->mapWithKeys(fn ($field) => [$field => $private ? $activity->{$field} : null])->all(),
                    );
                });
            }),
            'coordination_requests' => $this->whenLoaded('coordinationRequests', function () use ($access, $request) {
                $items = $this->coordinationRequests;
                if ($access->isScopedPsychologist($request->user())) {
                    $items = $items->filter(fn ($item) => (int) $item->requester_user_id === (int) $request->user()->id
                        || (int) $item->recipient_user_id === (int) $request->user()->id);
                }

                return $items->values()->map(fn ($item) => [
                    ...$item->toArray(),
                    'can_respond' => $item->status === 'pending' && (int) $item->recipient_user_id === (int) $request->user()->id,
                ]);
            }),
            'risk_assessments' => $this->when($risk, $this->whenLoaded('riskAssessments')),
            'tasks' => $this->whenLoaded('tasks'), 'documents' => $this->whenLoaded('documents'),
            'consents' => $this->whenLoaded('consents'), 'external_referrals' => $this->whenLoaded('externalReferrals'),
            'shared_feedback' => $this->whenLoaded('sharedFeedback'),
            'closures' => $this->whenLoaded('closures'), 'reopenings' => $this->whenLoaded('reopenings'),
        ];
    }
}
