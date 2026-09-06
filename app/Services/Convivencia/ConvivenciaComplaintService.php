<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaComplaintService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
        private readonly ConvivenciaCaseService $caseService,
    ) {}

    public function store(array $payload, ?User $user = null): ConvivenciaComplaint
    {
        return DB::transaction(function () use ($payload, $user) {
            $complaint = new ConvivenciaComplaint;
            $this->fillComplaint($complaint, $payload, $user, true);
            $complaint->save();

            $this->supportService->logStatus($complaint, null, $complaint->status, $user, 'Denuncia ingresada.', 'created');

            return $this->loadComplaint($complaint, $user);
        });
    }

    public function update(ConvivenciaComplaint $complaint, array $payload, User $user): ConvivenciaComplaint
    {
        return DB::transaction(function () use ($complaint, $payload, $user) {
            $previousStatus = $complaint->status;

            $this->fillComplaint($complaint, $payload, $user, false);
            $complaint->save();

            if ($previousStatus !== $complaint->status) {
                $this->supportService->logStatus($complaint, $previousStatus, $complaint->status, $user);
            }

            return $this->loadComplaint($complaint, $user);
        });
    }

    public function convertToCase(ConvivenciaComplaint $complaint, array $payload, User $user): ConvivenciaCase
    {
        return $this->caseService->createFromComplaint($complaint, $payload, $user);
    }

    private function fillComplaint(ConvivenciaComplaint $complaint, array $payload, ?User $user, bool $creating): void
    {
        $attributes = array_intersect_key($payload, array_flip([
            'academic_year_id',
            'course_section_id',
            'affected_student_id',
            'responsible_user_id',
            'case_id',
            'complainant_name',
            'complainant_type',
            'contact_email',
            'contact_phone',
            'situation_type_label',
            'place',
            'received_at',
            'happened_at',
            'report_text',
            'involved_snapshot',
            'truth_declaration_accepted',
            'is_anonymous',
            'is_sensitive',
            'status',
            'admissibility_result',
        ]));

        if (array_key_exists('involved_snapshot', $attributes) && $attributes['involved_snapshot'] !== null) {
            $attributes['involved_snapshot'] = array_values((array) $attributes['involved_snapshot']);
        }

        foreach (['truth_declaration_accepted', 'is_anonymous', 'is_sensitive'] as $booleanField) {
            if (array_key_exists($booleanField, $attributes)) {
                $attributes[$booleanField] = (bool) $attributes[$booleanField];
            }
        }

        if (array_key_exists('situation_type_item_id', $payload)) {
            $situationType = ! empty($payload['situation_type_item_id'])
                ? ConvivenciaCatalogItem::query()->find($payload['situation_type_item_id'])
                : null;
            $attributes['situation_type_item_id'] = $situationType?->id;

            if (! array_key_exists('situation_type_label', $payload)) {
                $attributes['situation_type_label'] = $situationType?->name;
            }
        }

        if (($payload['complainant_type'] ?? null) === 'anonimo') {
            $attributes['is_anonymous'] = true;
        }

        if ($creating) {
            $attributes += [
                'responsible_user_id' => $user?->id,
                'received_at' => now(),
                'involved_snapshot' => [],
                'truth_declaration_accepted' => false,
                'is_anonymous' => false,
                'is_sensitive' => true,
            ];
        }

        $isAnonymous = (bool) ($attributes['is_anonymous'] ?? $complaint->is_anonymous ?? false);
        if ($isAnonymous) {
            $attributes['complainant_name'] = null;
            $attributes['contact_email'] = null;
            $attributes['contact_phone'] = null;
        }

        $attributes['updated_by'] = $user?->id;
        $complaint->fill($attributes);

        if ($creating) {
            $complaint->folio = $this->supportService->nextFolio('DEN', ConvivenciaComplaint::query());
            $complaint->created_by = $user?->id;
        }
    }

    private function loadComplaint(ConvivenciaComplaint $complaint, ?User $user): ConvivenciaComplaint
    {
        return $complaint->fresh([
            'academicYear:id,name,year',
            'courseSection:id,display_name',
            'affectedStudent:id,first_name,last_name,registered_name,rut',
            'situationType:id,name',
            'responsibleUser:id,name',
            'case:id,folio,status',
            'protocolActivations.protocol:id,name',
            'attachments' => fn ($query) => app(ConvivenciaAccessService::class)
                ->applyAttachmentVisibility($query, $user)
                ->with('uploadedBy:id,name'),
            'statusLogs.changedBy:id,name',
        ]);
    }
}
