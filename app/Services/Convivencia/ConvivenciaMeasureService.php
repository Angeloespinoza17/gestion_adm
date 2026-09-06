<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaMeasureService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
    ) {}

    public function store(array $payload, User $user): ConvivenciaMeasure
    {
        return DB::transaction(function () use ($payload, $user) {
            $measure = new ConvivenciaMeasure;
            $this->fillMeasure($measure, $payload, $user, true);
            $measure->save();

            $this->supportService->logStatus($measure, null, $measure->status, $user, 'Medida formativa creada.', 'created');

            return $this->loadMeasure($measure, $user);
        });
    }

    public function update(ConvivenciaMeasure $measure, array $payload, User $user): ConvivenciaMeasure
    {
        return DB::transaction(function () use ($measure, $payload, $user) {
            $previousStatus = $measure->status;

            $this->fillMeasure($measure, $payload, $user, false);
            $measure->save();

            if ($previousStatus !== $measure->status) {
                $this->supportService->logStatus($measure, $previousStatus, $measure->status, $user);
            }

            return $this->loadMeasure($measure, $user);
        });
    }

    private function fillMeasure(ConvivenciaMeasure $measure, array $payload, User $user, bool $creating): void
    {
        $attributes = array_intersect_key($payload, array_flip([
            'case_id',
            'student_profile_id',
            'course_section_id',
            'responsible_user_id',
            'responsible_staff_id',
            'validated_by',
            'measure_type_label',
            'description',
            'training_objective',
            'assigned_at',
            'due_at',
            'status',
            'evidence_summary',
            'student_reflection',
            'repair_action',
            'responsible_notes',
            'closure_notes',
            'closed_at',
            'is_sensitive',
        ]));

        if (array_key_exists('measure_type_item_id', $payload)) {
            $type = ! empty($payload['measure_type_item_id'])
                ? ConvivenciaCatalogItem::query()->find($payload['measure_type_item_id'])
                : null;
            $attributes['measure_type_item_id'] = $type?->id;

            if (! array_key_exists('measure_type_label', $payload)) {
                $attributes['measure_type_label'] = $type?->name;
            }
        }

        if (array_key_exists('is_sensitive', $attributes)) {
            $attributes['is_sensitive'] = (bool) $attributes['is_sensitive'];
        }

        $isClosing = array_key_exists('status', $payload)
            && in_array($payload['status'], ['cumplida', 'incumplida', 'cerrada'], true);

        if ($isClosing && ! array_key_exists('validated_by', $payload) && $measure->validated_by === null) {
            $attributes['validated_by'] = $user->id;
        }

        if ($isClosing && ! array_key_exists('closed_at', $payload) && $measure->closed_at === null) {
            $attributes['closed_at'] = now();
        }

        if ($creating) {
            $attributes += [
                'responsible_user_id' => $user->id,
                'responsible_staff_id' => $user->staff_id,
                'is_sensitive' => true,
            ];
        }

        $attributes['updated_by'] = $user->id;
        $measure->fill($attributes);

        if ($creating) {
            $measure->created_by = $user->id;
        }
    }

    private function loadMeasure(ConvivenciaMeasure $measure, User $user): ConvivenciaMeasure
    {
        return $measure->fresh([
            'case:id,folio,status,classification_label,criticality_label',
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'type:id,name',
            'responsibleUser:id,name',
            'responsibleStaff:id,full_name',
            'validator:id,name',
            'attachments' => fn ($query) => app(ConvivenciaAccessService::class)
                ->applyAttachmentVisibility($query, $user)
                ->with('uploadedBy:id,name'),
            'statusLogs.changedBy:id,name',
        ]);
    }
}
