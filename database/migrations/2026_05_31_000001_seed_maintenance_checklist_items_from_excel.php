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
            ]));

        $existingKeys = DB::table('maintenance_checklist_items')
            ->get(['system', 'subdimension', 'review'])
            ->mapWithKeys(fn ($row) => [
                $this->rowKey((array) $row) => true,
            ]);

        $rows = $rows
            ->reject(fn (array $row) => $existingKeys->has($this->rowKey($row)))
            ->unique(fn (array $row) => $this->rowKey($row))
            ->all();

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('maintenance_checklist_items')->insert($chunk);
        }
    }

    public function down(): void
    {
        // No eliminamos por sistema porque el criterio también alcanzaría ítems
        // creados o modificados posteriormente en producción.
    }

    private function rows(): array
    {
        $payload = preg_replace('/\s+/', '', self::DATA);

        return json_decode(gzdecode(base64_decode($payload)), true, flags: JSON_THROW_ON_ERROR);
    }

    private function rowKey(array $row): string
    {
        return hash('sha256', implode("\0", [
            (string) ($row['system'] ?? ''),
            (string) ($row['subdimension'] ?? ''),
            (string) ($row['review'] ?? ''),
        ]));
    }
};
