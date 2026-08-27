<?php

namespace App\Services\PedagogicalManagement;

use App\Enums\PedagogicalManagement\InstrumentStatus;
use App\Models\PedagogicalManagement\PedagogicalInstrumentValidationEvent;
use App\Models\PedagogicalManagement\PedagogicalInstrumentValidationResult;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedagogicalInstrumentResolutionService
{
    public function __construct(private readonly AuditEventWriter $audit) {}

    public function resolve(PedagogicalInstrumentValidationResult $result, string $status, string $notes, User $actor, Request $request): PedagogicalInstrumentValidationResult
    {
        $before = $this->snapshot($result);
        DB::transaction(function () use ($result, $status, $notes, $actor, $before): void {
            $locked = PedagogicalInstrumentValidationResult::query()->whereKey($result->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'resolved_at' => now(), 'resolved_by' => $actor->id,
                'resolution_status' => $status, 'resolution_notes' => $notes,
            ])->save();
            PedagogicalInstrumentValidationEvent::query()->create([
                'validation_result_id' => $locked->id, 'action' => 'resolved', 'notes' => $notes,
                'before_snapshot' => $before, 'after_snapshot' => $this->snapshot($locked),
                'performed_by' => $actor->id, 'performed_at' => now(),
            ]);
            $this->refreshInstrumentStatus($locked);
        }, 3);
        $instrument = $result->analysisRun->instrument;
        $this->audit->write(
            'pedagogical.instrument.validation_resolved', 'resolve', $instrument, actor: $actor,
            schoolId: $instrument->school_id, academicYearId: $instrument->academic_year_id,
            before: $before, after: $this->snapshot($result->fresh()), reason: $notes, request: $request,
        );

        return $result->fresh(['events.performer']);
    }

    public function reopen(PedagogicalInstrumentValidationResult $result, string $notes, User $actor, Request $request): PedagogicalInstrumentValidationResult
    {
        $before = $this->snapshot($result);
        DB::transaction(function () use ($result, $notes, $actor, $before): void {
            $locked = PedagogicalInstrumentValidationResult::query()->whereKey($result->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'resolved_at' => null, 'resolved_by' => null, 'resolution_status' => null, 'resolution_notes' => null,
            ])->save();
            PedagogicalInstrumentValidationEvent::query()->create([
                'validation_result_id' => $locked->id, 'action' => 'reopened', 'notes' => $notes,
                'before_snapshot' => $before, 'after_snapshot' => $this->snapshot($locked),
                'performed_by' => $actor->id, 'performed_at' => now(),
            ]);
            $this->refreshInstrumentStatus($locked);
        }, 3);
        $instrument = $result->analysisRun->instrument;
        $this->audit->write(
            'pedagogical.instrument.validation_reopened', 'reopen', $instrument, actor: $actor,
            schoolId: $instrument->school_id, academicYearId: $instrument->academic_year_id,
            before: $before, after: $this->snapshot($result->fresh()), reason: $notes, request: $request,
        );

        return $result->fresh(['events.performer']);
    }

    private function refreshInstrumentStatus(PedagogicalInstrumentValidationResult $result): void
    {
        $run = $result->analysisRun;
        $instrument = $run->instrument;
        $hasUnresolved = $run->validationResults()->where('is_blocking', true)->whereNull('resolved_at')->where('outcome', 'fail')->exists();
        $instrument->forceFill(['status' => $hasUnresolved ? InstrumentStatus::ReviewRequired : InstrumentStatus::ValidatedWithWarnings])->save();
    }

    /** @return array<string,mixed> */
    private function snapshot(PedagogicalInstrumentValidationResult $result): array
    {
        return $result->only(['uuid', 'code', 'outcome', 'is_blocking', 'resolved_at', 'resolved_by', 'resolution_status', 'resolution_notes']);
    }
}
