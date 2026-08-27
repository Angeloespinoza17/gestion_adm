<?php

namespace App\Services\PedagogicalManagement;

use App\Enums\PedagogicalManagement\PrintRequestStatus;
use App\Models\PedagogicalManagement\PedagogicalInstrumentPrintRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedagogicalPrintRequestService
{
    public function register(PedagogicalInstrumentPrintRequest $printRequest, string $action, User $actor): PedagogicalInstrumentPrintRequest
    {
        DB::transaction(function () use ($printRequest, $action, $actor): void {
            $locked = PedagogicalInstrumentPrintRequest::query()->whereKey($printRequest->id)->lockForUpdate()->firstOrFail();
            $attributes = match ($action) {
                'downloaded' => [
                    'download_count' => ((int) $locked->download_count) + 1,
                    'last_downloaded_by' => $actor->id,
                    'last_downloaded_at' => now(),
                    'status' => $locked->status === PrintRequestStatus::Pending ? PrintRequestStatus::InProcess : $locked->status,
                ],
                'printed' => [
                    'print_count' => ((int) $locked->print_count) + 1,
                    'last_printed_by' => $actor->id,
                    'last_printed_at' => now(),
                    'status' => PrintRequestStatus::Printed,
                ],
                'completed' => [
                    'status' => PrintRequestStatus::Completed,
                    'completed_by' => $actor->id,
                    'completed_at' => now(),
                ],
                'in_process' => ['status' => PrintRequestStatus::InProcess],
                default => throw ValidationException::withMessages(['action' => 'La acción de impresión no es válida.']),
            };
            $locked->forceFill($attributes)->save();
        }, 3);

        return $printRequest->fresh($this->relations());
    }

    /** @return list<string> */
    public function relations(): array
    {
        return [
            'school:id,name,rbd',
            'instrument.owner:id,name',
            'instrument.subject:id,name,code',
            'instrument.courses:id,display_name',
            'instrumentFile',
            'review.reviewer:id,name',
            'completedBy:id,name',
        ];
    }
}
