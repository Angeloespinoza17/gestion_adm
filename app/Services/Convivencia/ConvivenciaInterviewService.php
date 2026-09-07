<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\User;
use App\Services\Records\InterviewRecordRevisionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvivenciaInterviewService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
        private readonly InterviewRecordRevisionService $revisions,
    ) {}

    public function store(array $payload, User $user): ConvivenciaInterview
    {
        return DB::transaction(function () use ($payload, $user) {
            $interview = new ConvivenciaInterview;
            $this->fillInterview($interview, $payload, $user, true);
            $interview->save();

            $this->supportService->syncInterviewParticipants($interview, $payload['participants'] ?? []);
            $this->supportService->logStatus($interview, null, $interview->follow_up_status, $user, 'Entrevista registrada.', 'created');

            return $this->loadInterview($interview, $user);
        });
    }

    public function update(ConvivenciaInterview $interview, array $payload, User $user): ConvivenciaInterview
    {
        return DB::transaction(function () use ($interview, $payload, $user) {
            $locked = ConvivenciaInterview::query()->with('participants')->lockForUpdate()->findOrFail($interview->id);
            $expectedVersion = Carbon::parse($payload['record_updated_at']);
            if (! $locked->updated_at?->equalTo($expectedVersion)) {
                throw ValidationException::withMessages([
                    'record' => 'El acta fue modificada por otra persona. Recárgala antes de guardar tus cambios.',
                ]);
            }

            $reason = (string) $payload['change_reason'];
            unset($payload['change_reason'], $payload['record_updated_at']);
            $previousStatus = $locked->follow_up_status;
            $before = $this->revisionPayload($locked);

            $this->fillInterview($locked, $payload, $user, false);
            $locked->save();

            if (array_key_exists('participants', $payload)) {
                $this->supportService->syncInterviewParticipants($locked, (array) ($payload['participants'] ?? []));
            }

            $locked->load('participants');
            $after = $this->revisionPayload($locked);
            $this->revisions->record('convivencia', $locked, $user, $reason, $before, $after, $locked->case_id);

            if ($previousStatus !== $locked->follow_up_status) {
                $this->supportService->logStatus($locked, $previousStatus, $locked->follow_up_status, $user, $reason);
            } else {
                $this->supportService->logStatus($locked, $previousStatus, $locked->follow_up_status, $user, $reason, 'record_corrected');
            }

            return $this->loadInterview($locked, $user);
        });
    }

    private function revisionPayload(ConvivenciaInterview $interview): array
    {
        return array_merge($interview->only([
            'case_id', 'student_profile_id', 'course_section_id', 'interview_type_item_id',
            'responsible_user_id', 'responsible_staff_id', 'interview_type_label', 'interview_at',
            'motive', 'topics', 'agreements', 'commitments', 'follow_up_date', 'follow_up_status',
            'internal_notes', 'is_sensitive',
        ]), [
            'participants' => $interview->participants->map(fn ($participant) => $participant->only([
                'student_profile_id', 'user_id', 'staff_id', 'participant_type', 'participant_role',
                'full_name', 'contact_reference', 'notes',
            ]))->values()->all(),
        ]);
    }

    private function fillInterview(ConvivenciaInterview $interview, array $payload, User $user, bool $creating): void
    {
        $attributes = array_intersect_key($payload, array_flip([
            'case_id',
            'student_profile_id',
            'course_section_id',
            'responsible_user_id',
            'responsible_staff_id',
            'interview_type_label',
            'interview_at',
            'motive',
            'topics',
            'agreements',
            'commitments',
            'follow_up_date',
            'follow_up_status',
            'internal_notes',
            'is_sensitive',
        ]));

        if (array_key_exists('interview_type_item_id', $payload)) {
            $type = ! empty($payload['interview_type_item_id'])
                ? ConvivenciaCatalogItem::query()->find($payload['interview_type_item_id'])
                : null;
            $attributes['interview_type_item_id'] = $type?->id;

            if (! array_key_exists('interview_type_label', $payload)) {
                $attributes['interview_type_label'] = $type?->name;
            }
        }

        if (array_key_exists('is_sensitive', $attributes)) {
            $attributes['is_sensitive'] = (bool) $attributes['is_sensitive'];
        }

        if ($creating) {
            $attributes += [
                'responsible_user_id' => $user->id,
                'responsible_staff_id' => $user->staff_id,
                'is_sensitive' => true,
            ];
        }

        $attributes['updated_by'] = $user->id;
        $interview->fill($attributes);

        if ($creating) {
            $interview->created_by = $user->id;
        }
    }

    private function loadInterview(ConvivenciaInterview $interview, User $user): ConvivenciaInterview
    {
        return $interview->fresh([
            'case:id,folio,status',
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'type:id,name',
            'responsibleUser:id,name',
            'responsibleStaff:id,full_name',
            'participants.student:id,first_name,last_name,registered_name,rut',
            'participants.user:id,name',
            'participants.staff:id,full_name',
            'attachments' => fn ($query) => app(ConvivenciaAccessService::class)
                ->applyAttachmentVisibility($query, $user)
                ->with('uploadedBy:id,name'),
            'statusLogs.changedBy:id,name',
        ]);
    }
}
