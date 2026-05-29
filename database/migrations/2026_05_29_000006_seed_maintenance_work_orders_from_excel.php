<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DATA = <<<'DATA'
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
a44/X0j+1/8Hg1PJ+q70AQA=
DATA;

    public function up(): void
    {
        $now = now();
        $dependencies = DB::table('maintenance_dependencies')->pluck('id', 'code');

        $rows = collect($this->rows())
            ->map(function (array $row) use ($dependencies, $now) {
                $row = $this->normalizeRow($row);
                $row['maintenance_dependency_id'] = $row['location_code']
                    ? ($dependencies[$row['location_code']] ?? null)
                    : null;

                return array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            })
            ->all();

        DB::table('maintenance_work_orders')->upsert(
            $rows,
            ['source_key'],
            [
                'maintenance_dependency_id',
                'location_code',
                'location_distribution',
                'location_sector',
                'location_name',
                'location_usage',
                'reported_at',
                'requested_by',
                'assigned_to',
                'priority',
                'status',
                'due_date',
                'description',
                'resolution_notes',
                'photo_reference',
                'updated_at',
            ]
        );
    }

    public function down(): void
    {
        $keys = collect($this->rows())->pluck('source_key')->all();

        foreach (array_chunk($keys, 100) as $chunk) {
            DB::table('maintenance_work_orders')->whereIn('source_key', $chunk)->delete();
        }
    }

    private function rows(): array
    {
        $payload = preg_replace('/\s+/', '', self::DATA);

        return json_decode(gzdecode(base64_decode($payload)), true, flags: JSON_THROW_ON_ERROR);
    }

    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && trim($value) === '?') {
                $row[$key] = null;
            }
        }

        $row['reported_at'] = $this->normalizeDate($row['reported_at']);
        $row['due_date'] = $this->normalizeDate($row['due_date']);

        return $row;
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $value, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }

        return null;
    }
};
