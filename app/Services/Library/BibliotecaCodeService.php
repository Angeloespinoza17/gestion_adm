<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class BibliotecaCodeService
{
    public function next(string $sequenceKey, ?int $year = null, int $padding = 4): string
    {
        $year ??= (int) now()->format('Y');
        $sequenceKey = strtoupper(trim($sequenceKey));

        $next = DB::transaction(function () use ($sequenceKey, $year) {
            DB::table('biblioteca_sequences')->insertOrIgnore([
                'sequence_key' => $sequenceKey,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('biblioteca_sequences')
                ->where('sequence_key', $sequenceKey)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $nextNumber = (int) $sequence->last_number + 1;

            DB::table('biblioteca_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            return $nextNumber;
        });

        return sprintf(
            'BIB-%s-%d-%s',
            $sequenceKey,
            $year,
            str_pad((string) $next, $padding, '0', STR_PAD_LEFT),
        );
    }
}
