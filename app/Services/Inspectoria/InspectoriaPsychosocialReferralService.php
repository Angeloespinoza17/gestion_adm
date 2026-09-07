<?php

namespace App\Services\Inspectoria;

use App\Models\Inspectoria\InspectoriaAttention;
use App\Models\Psychology\PsychologyReferral;
use App\Models\SocialWork\Referral;
use App\Models\User;
use App\Notifications\OperationalEventNotification;
use App\Services\Notifications\OperationalNotificationService;
use App\Services\Psychology\PsychologyWorkflowService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InspectoriaPsychosocialReferralService
{
    public function __construct(
        private readonly PsychologyWorkflowService $psychologyWorkflow,
        private readonly OperationalNotificationService $notifications,
    ) {}

    public function sync(InspectoriaAttention $attention, User $actor): ?Referral
    {
        if (! in_array('derivacion_psicosocial', $attention->actions_taken ?? [], true)
            || ! $attention->psychosocial_referral_user_id) {
            return null;
        }

        $requestLabels = $this->labels($attention->request_types, InspectoriaAttention::REQUEST_TYPES);
        $actionLabels = $this->labels(
            array_values(array_diff($attention->actions_taken ?? [], ['derivacion_psicosocial'])),
            InspectoriaAttention::ACTIONS,
        );
        $reason = 'Derivación psicosocial';
        if ($requestLabels !== []) {
            $reason .= ' · '.implode(', ', $requestLabels);
        }

        $actor->loadMissing('staff');

        $socialReferral = Referral::query()->updateOrCreate(
            ['inspectoria_attention_id' => $attention->id],
            [
                'student_profile_id' => $attention->student_profile_id,
                'course_section_id' => $attention->course_section_id,
                'referral_date' => ($attention->psychosocial_referred_at ?? $attention->attended_at ?? now())->toDateString(),
                'source_unit' => 'Inspectoría',
                'source_person' => $actor->staff?->full_name ?: $actor->name,
                'reason' => Str::limit($reason, 255, ''),
                'description' => $attention->brief_note,
                'observed_background' => $requestLabels === []
                    ? null
                    : 'Solicitud registrada: '.implode(', ', $requestLabels).'.',
                'previous_actions' => $actionLabels === []
                    ? "Atención rápida de Inspectoría {$attention->attention_code}."
                    : "Atención rápida {$attention->attention_code}: ".implode(', ', $actionLabels).'.',
                'urgency' => $attention->priority === 'urgente' ? 'urgente' : 'normal',
                'immediate_risk' => $attention->priority === 'urgente',
                'status' => 'enviada',
                'assigned_user_id' => $attention->psychosocial_referral_user_id,
                'confidentiality' => 'restringido',
                'created_by' => $attention->created_by ?? $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        if (Schema::hasTable('psychology_referrals') && ! PsychologyReferral::query()->where('source_type', 'inspectoria_attention')->where('source_id', $attention->id)->exists()) {
            $psychologyReferral = $this->psychologyWorkflow->createReferral([
                'student_profile_id' => $attention->student_profile_id,
                'course_section_id' => $attention->course_section_id,
                'suggested_user_id' => $attention->psychosocial_referral_user_id,
                'assigned_user_id' => $attention->psychosocial_referral_user_id,
                'origin_area' => 'inspectoria',
                'source_type' => 'inspectoria_attention',
                'source_id' => $attention->id,
                'suggested_urgency' => $attention->priority === 'urgente' ? 'critical' : 'medium',
                'primary_reason' => Str::limit($reason, 160, ''),
                'observed_facts' => $attention->brief_note ?: "Atención de Inspectoría {$attention->attention_code}; revisar antecedentes autorizados.",
                'measures_taken' => $actionLabels === [] ? null : implode(', ', $actionLabels),
                'immediate_response_needed' => $attention->priority === 'urgente',
                'guardian_informed' => (bool) $attention->guardian_notified,
                'guardian_contact_status' => $attention->guardian_notified ? 'contacted' : 'not_contacted',
                'purpose_declaration_accepted' => true,
                'submit' => true,
            ], $actor);

            if ($attention->psychosocial_referral_user_id) {
                $professional = User::query()->find($attention->psychosocial_referral_user_id);
                if ($professional) {
                    $this->psychologyWorkflow->assignReferral($psychologyReferral, $professional, $actor, [
                        'reason' => 'Profesional seleccionada desde Atención rápida de Inspectoría.',
                        'professional_priority' => $attention->priority === 'urgente' ? 'critical' : null,
                    ]);
                }
            }
        }

        if ($socialReferral->wasRecentlyCreated) {
            $recipient = User::query()
                ->whereKey($attention->psychosocial_referral_user_id)
                ->where('active', true)
                ->first();
            $eventKey = 'social_work.referral.created:'.$socialReferral->id;
            $this->notifications->send(
                $recipient,
                new OperationalEventNotification(
                    eventKey: $eventKey,
                    eventType: 'social_work.referral.created',
                    module: 'social_work',
                    title: 'Nueva derivación psicosocial',
                    message: 'Inspectoría te asignó una derivación psicosocial pendiente de revisión.',
                    resource: [
                        'type' => 'social_work_referral',
                        'id' => $socialReferral->id,
                        'code' => $attention->attention_code,
                    ],
                    actionUrl: $recipient?->hasPermission('social_work.referrals.submit')
                        ? '/social-work/referrals'
                        : '/psychology/referrals',
                    icon: 'bx bx-git-branch',
                    priority: $socialReferral->urgency,
                    occurredAt: $attention->psychosocial_referred_at,
                    actor: $actor,
                    context: [
                        'attention_id' => $attention->id,
                        'assigned_user_id' => $attention->psychosocial_referral_user_id,
                        'status' => $socialReferral->status,
                    ],
                ),
                $eventKey,
            );
        }

        return $socialReferral;
    }

    /**
     * @param  array<int, string>|null  $values
     * @param  array<string, string>  $catalog
     * @return array<int, string>
     */
    private function labels(?array $values, array $catalog): array
    {
        return collect($values ?? [])
            ->map(fn (string $value) => $catalog[$value] ?? null)
            ->filter()
            ->values()
            ->all();
    }
}
