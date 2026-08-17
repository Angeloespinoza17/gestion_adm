<?php

namespace App\Services\Passes;

use App\Models\Inspectoria\InspectoriaPass;
use App\Models\Library\BibliotecaPase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StudentPassPriorityService
{
    public function assertLibraryPassAllowed(int $studentId, mixed $from, mixed $until): void
    {
        if (! Schema::hasTable('inspectoria_passes')) {
            return;
        }

        $overlap = InspectoriaPass::query()
            ->where('student_profile_id', $studentId)
            ->where('status', 'emitido')
            ->where('valid_from', '<', Carbon::parse($until))
            ->where('valid_until', '>', Carbon::parse($from))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'valid_from' => 'Existe un pase prioritario de Inspectoría vigente en ese horario.',
            ]);
        }
    }

    public function supersedeLibraryPasses(InspectoriaPass $pass, User $actor): int
    {
        if (! Schema::hasTable('biblioteca_pases')) {
            return 0;
        }

        $passes = BibliotecaPase::query()
            ->where('student_profile_id', $pass->student_profile_id)
            ->where('status', 'emitido')
            ->where('valid_from', '<', $pass->valid_until)
            ->where('valid_until', '>', $pass->valid_from)
            ->get();

        foreach ($passes as $libraryPass) {
            $trace = "Anulado automáticamente: prevalece el pase prioritario {$pass->pass_code}.";
            $libraryPass->forceFill([
                'status' => 'anulado',
                'superseded_by_inspectoria_pass_id' => $pass->id,
                'notes' => trim(implode("\n", array_filter([$libraryPass->notes, $trace]))),
                'updated_by' => $actor->id,
            ])->save();
        }

        return $passes->count();
    }
}
