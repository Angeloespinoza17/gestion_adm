<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaDerivationService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
        private readonly ConvivenciaCaseService $caseService,
    ) {}

    public function store(array $payload, User $user): ConvivenciaDerivation
    {
        return DB::transaction(function () use ($payload, $user) {
            $derivation = new ConvivenciaDerivation;
            $this->fillDerivation($derivation, $payload, $user, true);
            $derivation->save();

            $this->supportService->logStatus($derivation, null, $derivation->status, $user, 'Derivación creada.', 'created');
            $this->syncCaseStatus($derivation, $user);

            return $this->loadDerivation($derivation, $user);
        });
    }

    public function update(ConvivenciaDerivation $derivation, array $payload, User $user): ConvivenciaDerivation
    {
        return DB::transaction(function () use ($derivation, $payload, $user) {
            $previousStatus = $derivation->status;

            $this->fillDerivation($derivation, $payload, $user, false);
            $derivation->save();

            if ($previousStatus !== $derivation->status) {
                $this->supportService->logStatus($derivation, $previousStatus, $derivation->status, $user);
            }

            $this->syncCaseStatus($derivation, $user);

            return $this->loadDerivation($derivation, $user);
        });
    }

    public function convertToCase(ConvivenciaDerivation $derivation, array $payload, User $user): ConvivenciaCase
    {
        return $this->caseService->createFromDerivation($derivation, $payload, $user);
    }

    private function fillDerivation(ConvivenciaDerivation $derivation, array $payload, User $user, bool $creating): void
    {
        $attributes = array_intersect_key($payload, array_flip([
            'case_id',
            'academic_year_id',
            'course_section_id',
            'student_profile_id',
            'destination_department_id',
            'destination_staff_id',
            'destination_user_id',
            'external_institution_id',
            'responsible_user_id',
            'scope',
            'status',
            'priority_level',
            'confidentiality_level',
            'destination_label',
            'external_contact_name',
            'external_contact_email',
            'external_contact_phone',
            'derived_at',
            'sent_at',
            'response_due_at',
            'responded_at',
            'closed_at',
            'motive',
            'narrative',
            'response_text',
            'suggested_actions',
            'follow_up_notes',
            'is_sensitive',
        ]));

        if (array_key_exists('is_sensitive', $attributes)) {
            $attributes['is_sensitive'] = (bool) $attributes['is_sensitive'];
        }

        $effectiveScope = $attributes['scope'] ?? $derivation->scope;
        $scopeChanged = $creating || (array_key_exists('scope', $attributes) && $effectiveScope !== $derivation->scope);
        $irrelevantFields = $effectiveScope === 'internal'
            ? ['external_institution_id', 'external_contact_name', 'external_contact_email', 'external_contact_phone']
            : ['destination_department_id', 'destination_staff_id', 'destination_user_id'];

        foreach ($irrelevantFields as $field) {
            if ($scopeChanged || array_key_exists($field, $payload)) {
                $attributes[$field] = null;
            }
        }

        $isClosing = array_key_exists('status', $payload)
            && in_array($payload['status'], ['cerrada', 'rechazada'], true);

        if ($isClosing && ! array_key_exists('closed_at', $payload) && $derivation->closed_at === null) {
            $attributes['closed_at'] = now();
        }

        if ($creating) {
            $attributes += [
                'responsible_user_id' => $user->id,
                'is_sensitive' => true,
            ];
        }

        $attributes['updated_by'] = $user->id;
        $derivation->fill($attributes);

        if ($creating) {
            $derivation->created_by = $user->id;
        }
    }

    private function syncCaseStatus(ConvivenciaDerivation $derivation, User $user): void
    {
        $case = $derivation->case;

        if (! $case || in_array($case->status, ['cerrado', 'archivado'], true)) {
            return;
        }

        $previousStatus = $case->status;
        $case->forceFill([
            'status' => 'derivado',
            'updated_by' => $user->id,
        ])->save();

        if ($previousStatus !== $case->status) {
            $this->supportService->logStatus($case, $previousStatus, $case->status, $user, 'Caso marcado como derivado.');
        }
    }

    private function loadDerivation(ConvivenciaDerivation $derivation, User $user): ConvivenciaDerivation
    {
        return $derivation->fresh([
            'case:id,folio,status,classification_label,criticality_label',
            'academicYear:id,name,year',
            'courseSection:id,display_name',
            'student:id,first_name,last_name,registered_name,rut',
            'destinationDepartment:id,name',
            'destinationStaff:id,full_name',
            'destinationUser:id,name',
            'externalInstitution:id,name,category',
            'responsibleUser:id,name',
            'attachments' => fn ($query) => app(ConvivenciaAccessService::class)
                ->applyAttachmentVisibility($query, $user)
                ->with('uploadedBy:id,name'),
            'statusLogs.changedBy:id,name',
        ]);
    }
}
