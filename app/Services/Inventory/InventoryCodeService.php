<?php

namespace App\Services\Inventory;

use App\Models\InventoryCategory;
use Illuminate\Support\Facades\DB;

class InventoryCodeService
{
    /**
     * Genera un código único por categoría y año:
     * INV-{PREFIX}-{YEAR}-{0001}
     *
     * Si el bien no tiene categoría, usa una secuencia general:
     * INV-SIN-{YEAR}-{0001}
     */
    public function nextCode(?InventoryCategory $category = null, ?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');

        if ($category === null) {
            return $this->nextUncategorizedCode($year);
        }

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

    private function nextUncategorizedCode(int $year): string
    {
        $next = DB::transaction(function () use ($year) {
            $sequence = DB::table('inventory_uncategorized_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                DB::table('inventory_uncategorized_sequences')->insert([
                    'year' => $year,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $newNumber = (int) $sequence->last_number + 1;

            DB::table('inventory_uncategorized_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_number' => $newNumber,
                    'updated_at' => now(),
                ]);

            return $newNumber;
        });

        $number = str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        return sprintf('INV-SIN-%d-%s', $year, $number);
    }
}
