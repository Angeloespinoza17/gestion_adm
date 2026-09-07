<?php

namespace App\Services\Records;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterviewRecordRevisionService
{
    public function record(
        string $module,
        Model $record,
        User $user,
        string $reason,
        array $before,
        array $after,
        ?int $caseId = null,
    ): void {
        $before = $this->normalize($before);
        $after = $this->normalize($after);
        $changedFields = collect(array_unique([...array_keys($before), ...array_keys($after)]))
            ->filter(fn (string $field) => $this->encoded($before[$field] ?? null) !== $this->encoded($after[$field] ?? null))
            ->values()
            ->all();

        if ($changedFields === []) {
            throw ValidationException::withMessages([
                'record' => 'No se detectaron cambios para guardar en la ficha.',
            ]);
        }

        DB::table('interview_record_revisions')->insert([
            'module' => $module,
            'record_type' => $record::class,
            'record_id' => $record->getKey(),
            'case_id' => $caseId,
            'edited_by' => $user->id,
            'reason' => $reason,
            'changed_fields' => $this->encoded($changedFields),
            'before_payload' => Crypt::encryptString($this->encoded($before)),
            'after_payload' => Crypt::encryptString($this->encoded($after)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalize(array $payload): array
    {
        return json_decode($this->encoded($payload), true, 512, JSON_THROW_ON_ERROR);
    }

    private function encoded(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
