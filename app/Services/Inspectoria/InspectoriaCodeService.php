<?php

namespace App\Services\Inspectoria;

use Illuminate\Support\Facades\DB;

class InspectoriaCodeService
{
    public function next(string $key, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $key = strtoupper(trim($key));

        $number = DB::transaction(function () use ($key, $year) {
            DB::table('inspectoria_sequences')->insertOrIgnore([
                'sequence_key' => $key,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = DB::table('inspectoria_sequences')
                ->where('sequence_key', $key)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();
            $next = (int) $sequence->last_number + 1;
            DB::table('inspectoria_sequences')->where('id', $sequence->id)->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        });

        return sprintf('INS-%s-%d-%04d', $key, $year, $number);
    }
}
