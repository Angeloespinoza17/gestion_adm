<?php

namespace App\Services\Inventory;

use App\Models\InventoryCategory;
use Illuminate\Support\Facades\DB;

class InventoryCodeService
{
    /**
     * Genera un código único por categoría y año:
     * INV-{PREFIX}-{YEAR}-{0001}
     */
    public function nextCode(InventoryCategory $category, ?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');

        $next = DB::transaction(function () use ($category, $year) {
            $sequence = DB::table('inventory_sequences')
                ->where('category_id', $category->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                DB::table('inventory_sequences')->insert([
                    'category_id' => $category->id,
                    'year' => $year,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $newNumber = (int) $sequence->last_number + 1;

            DB::table('inventory_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_number' => $newNumber,
                    'updated_at' => now(),
                ]);

            return $newNumber;
        });

        $prefix = strtoupper(trim((string) $category->code_prefix));
        $number = str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        return sprintf('INV-%s-%d-%s', $prefix, $year, $number);
    }
}

