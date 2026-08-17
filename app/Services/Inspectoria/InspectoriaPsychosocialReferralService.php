<?php

namespace App\Services\Inspectoria;

use App\Models\Inspectoria\InspectoriaAttention;
use App\Models\SocialWork\Referral;
use App\Models\User;
use Illuminate\Support\Str;

class InspectoriaPsychosocialReferralService
{
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

        return Referral::query()->updateOrCreate(
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
