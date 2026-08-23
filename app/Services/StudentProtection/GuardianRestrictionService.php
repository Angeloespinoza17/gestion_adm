<?php

namespace App\Services\StudentProtection;

use App\Models\Inspectoria\InspectoriaPickupRestriction;
use App\Models\StudentProfile;
use App\Support\Rut;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GuardianRestrictionService
{
    /**
     * @return Collection<int, InspectoriaPickupRestriction>
     */
    public function activeFor(StudentProfile $student): Collection
    {
        if (! $student->relationLoaded('pickupRestrictions')) {
            return $student->pickupRestrictions()
                ->activeOn()
                ->latest('starts_on')
                ->latest('id')
                ->get();
        }

        $today = today();

        return $student->pickupRestrictions
            ->filter(fn (InspectoriaPickupRestriction $restriction) => $restriction->active
                && $restriction->starts_on?->lte($today)
                && (! $restriction->ends_on || $restriction->ends_on->gte($today)))
            ->sortByDesc(fn (InspectoriaPickupRestriction $restriction) => sprintf(
                '%s-%012d',
                $restriction->starts_on?->format('Y-m-d') ?: '0000-00-00',
                $restriction->id,
            ))
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activePayload(StudentProfile $student, bool $includeLegalReference = true): array
    {
        return $this->activeFor($student)
            ->map(function (InspectoriaPickupRestriction $restriction) use ($includeLegalReference): array {
                $payload = [
                    'id' => $restriction->id,
                    'restriction_code' => $restriction->restriction_code,
                    'restricted_person_name' => $restriction->restricted_person_name,
                    'restricted_person_rut' => $restriction->restricted_person_rut,
                    'restricted_person_relationship' => $restriction->restricted_person_relationship,
                    'restriction_type' => $restriction->restriction_type,
                    'restriction_type_label' => $restriction->restriction_type_label,
                    'reason' => $restriction->reason,
                    'starts_on' => $restriction->starts_on?->format('Y-m-d'),
                    'ends_on' => $restriction->ends_on?->format('Y-m-d'),
                    'source_module' => 'social_work',
                    'source_label' => 'Trabajo Social',
                ];

                if ($includeLegalReference) {
                    $payload['legal_reference'] = $restriction->legal_reference;
                }

                return $payload;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @param  array<int, array<string, mixed>>  $restrictions
     * @return array<int, array<string, mixed>>
     */
    public function decorateContacts(array $contacts, array $restrictions): array
    {
        return collect($contacts)
            ->map(function (array $contact) use ($restrictions): array {
                $matches = collect($restrictions)
                    ->filter(fn (array $restriction) => $this->matches($contact, $restriction))
                    ->values()
                    ->all();

                return [
                    ...$contact,
                    'has_active_restriction' => $matches !== [],
                    'restrictions' => $matches,
                    'contact_guidance' => $matches !== []
                        ? 'No contactar sin validar el protocolo y la medida vigente con Trabajo Social.'
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $contact
     * @param  array<string, mixed>  $restriction
     */
    private function matches(array $contact, array $restriction): bool
    {
        $contactRut = Rut::normalize((string) ($contact['rut'] ?? ''));
        $restrictionRut = Rut::normalize((string) ($restriction['restricted_person_rut'] ?? ''));

        if ($contactRut && $restrictionRut) {
            return $contactRut === $restrictionRut;
        }

        $contactName = $this->normalizeName((string) ($contact['name'] ?? ''));
        $restrictionName = $this->normalizeName((string) ($restriction['restricted_person_name'] ?? ''));

        return $contactName !== '' && $restrictionName !== '' && $contactName === $restrictionName;
    }

    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($name))) ?: '');
    }
}
