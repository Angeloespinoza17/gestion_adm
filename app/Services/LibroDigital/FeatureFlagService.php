<?php

namespace App\Services\LibroDigital;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeatureFlagService
{
    public function enabled(string $key = 'lcd_enabled', ?int $schoolId = null): bool
    {
        if (! Schema::hasTable('lcd_feature_flags')) {
            return $key === 'lcd_enabled' && (bool) config('libro_digital.enabled', false);
        }

        $codeColumn = Schema::hasColumn('lcd_feature_flags', 'code') ? 'code' : 'key';
        $query = DB::table('lcd_feature_flags')->where($codeColumn, $key);

        if ($schoolId !== null && Schema::hasColumn('lcd_feature_flags', 'school_id')) {
            $scoped = (clone $query)->where('school_id', $schoolId)->value('enabled');
            if ($scoped !== null) {
                return (bool) $scoped;
            }
        }

        $global = $query
            ->when(Schema::hasColumn('lcd_feature_flags', 'school_id'), fn ($builder) => $builder->whereNull('school_id'))
            ->value('enabled');

        if ($global !== null) {
            return (bool) $global;
        }

        return $key === 'lcd_enabled' && (bool) config('libro_digital.enabled', false);
    }

    /** @return array<string, bool> */
    public function all(?int $schoolId = null): array
    {
        $keys = [
            'lcd_enabled',
            'lcd_parvularia_enabled',
            'lcd_identity_verifier_enabled',
            'lcd_ede_export_enabled',
            'lcd_sige_reconciliation_enabled',
            'lcd_fiscalization_download_enabled',
        ];

        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $this->enabled($key, $schoolId)])->all();
    }
}
