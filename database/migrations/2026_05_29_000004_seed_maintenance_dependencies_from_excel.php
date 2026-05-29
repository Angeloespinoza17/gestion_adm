<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DATA = <<<'DATA'
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
DATA;

    public function up(): void
    {
        $now = now();
        $rows = collect($this->rows())
            ->map(fn (array $row) => array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]))
            ->all();

        DB::table('maintenance_dependencies')->upsert(
            $rows,
            ['code'],
            [
                'name',
                'distribution',
                'sector',
                'zone',
                'usage',
                'distribution_code',
                'floor_code',
                'dependency_code',
                'numbering',
                'active',
                'updated_at',
            ]
        );
    }

    public function down(): void
    {
        $codes = collect($this->rows())->pluck('code')->all();

        foreach (array_chunk($codes, 100) as $chunk) {
            DB::table('maintenance_dependencies')->whereIn('code', $chunk)->delete();
        }
    }

    private function rows(): array
    {
        $payload = preg_replace('/\s+/', '', self::DATA);

        return json_decode(gzdecode(base64_decode($payload)), true, flags: JSON_THROW_ON_ERROR);
    }
};
