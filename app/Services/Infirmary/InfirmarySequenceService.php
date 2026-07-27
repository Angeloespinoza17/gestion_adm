<?php

namespace App\Services\Infirmary;

use App\Models\Infirmary\InfirmaryAttention;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InfirmarySequenceService
{
    public const SCHOOL_INSURANCE_KEY = 'school_insurance';

    /**
     * @return array{last_number:int,next_number:int,max_assigned:int}
     */
    public function schoolInsuranceStatus(): array
    {
        $maxAssigned = $this->schoolInsuranceMaxAssigned();
        $storedLast = (int) (DB::table('infirmary_attention_sequences')
            ->where('subject_type', self::SCHOOL_INSURANCE_KEY)
            ->value('last_number') ?? 0);
        $lastNumber = max($storedLast, $maxAssigned);

        return [
            'last_number' => $lastNumber,
            'next_number' => $lastNumber + 1,
            'max_assigned' => $maxAssigned,
        ];
    }

    public function nextSchoolInsuranceNumber(): int
    {
        return DB::transaction(function (): int {
            $maxAssigned = $this->schoolInsuranceMaxAssigned();
            $sequence = $this->lockedSequence($maxAssigned);
            $nextNumber = max((int) $sequence->last_number, $maxAssigned) + 1;

            DB::table('infirmary_attention_sequences')
                ->where('subject_type', self::SCHOOL_INSURANCE_KEY)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            return $nextNumber;
        });
    }

    /**
     * @return array{last_number:int,next_number:int,max_assigned:int}
     */
    public function setNextSchoolInsuranceNumber(int $nextNumber, string $reason, User $user): array
    {
        return DB::transaction(function () use ($nextNumber, $reason, $user): array {
            $maxAssigned = $this->schoolInsuranceMaxAssigned();
            $sequence = $this->lockedSequence($maxAssigned);
            $currentLast = max((int) $sequence->last_number, $maxAssigned);
            $minimumNext = $currentLast + 1;

            if ($nextNumber < $minimumNext) {
                throw ValidationException::withMessages([
                    'next_number' => "El próximo correlativo debe ser {$minimumNext} o superior. No se permiten retrocesos ni reutilización de números.",
                ]);
            }

            $newLast = $nextNumber - 1;

            if ($newLast !== (int) $sequence->last_number) {
                DB::table('infirmary_attention_sequences')
                    ->where('subject_type', self::SCHOOL_INSURANCE_KEY)
                    ->update([
                        'last_number' => $newLast,
                        'updated_at' => now(),
                    ]);

                DB::table('infirmary_sequence_adjustments')->insert([
                    'sequence_key' => self::SCHOOL_INSURANCE_KEY,
                    'previous_last_number' => (int) $sequence->last_number,
                    'new_last_number' => $newLast,
                    'next_number' => $nextNumber,
                    'reason' => $reason,
                    'changed_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'last_number' => $newLast,
                'next_number' => $nextNumber,
                'max_assigned' => $maxAssigned,
            ];
        });
    }

    private function schoolInsuranceMaxAssigned(): int
    {
        return (int) InfirmaryAttention::query()->max('school_insurance_number');
    }

    private function lockedSequence(int $initialLastNumber): object
    {
        DB::table('infirmary_attention_sequences')->insertOrIgnore([
            'subject_type' => self::SCHOOL_INSURANCE_KEY,
            'last_number' => $initialLastNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('infirmary_attention_sequences')
            ->where('subject_type', self::SCHOOL_INSURANCE_KEY)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
