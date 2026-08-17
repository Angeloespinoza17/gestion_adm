<?php

namespace App\Console\Commands;

use App\Models\LibroDigital\EdeVersion;
use App\Services\LibroDigital\CompliancePreflightService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LibroDigitalEdePreflight extends Command
{
    protected $signature = 'lcd:ede:preflight {--school= : ID interno del establecimiento} {--year= : Año calendario o ID académico} {--json}';

    protected $description = 'Ejecuta controles EDE y de alcance sin generar archivos ni modificar registros.';

    public function handle(CompliancePreflightService $preflight): int
    {
        $schoolId = (int) $this->option('school');
        $year = trim((string) $this->option('year'));
        if ($schoolId < 1 || $year === '') {
            $this->error('Debes indicar --school y --year.');

            return self::INVALID;
        }
        $academicYear = DB::table('academic_years')
            ->where(fn ($query) => $query->where('id', (int) $year)->orWhere('year', (int) $year))
            ->first();
        if (! $academicYear || ! DB::table('lcd_school_academic_years')->where('school_id', $schoolId)->where('academic_year_id', $academicYear->id)->where('active', true)->exists()) {
            $this->error('El establecimiento y año académico no forman un contexto activo.');

            return self::FAILURE;
        }

        $result = $preflight->run($schoolId);
        $checks = collect($result['checks'])->whereIn('capability', ['core', 'ede'])->values()->all();
        $activeVersion = EdeVersion::query()->where('status', 'active')->whereHas('mappings', fn ($query) => $query->where('active', true))->latest('effective_from')->first();
        $payload = [
            'ready' => (bool) ($result['core_ready'] ?? false) && (bool) data_get($result, 'capabilities.ede.ready', false),
            'school_id' => $schoolId,
            'academic_year_id' => $academicYear->id,
            'active_version' => $activeVersion?->only(['public_id', 'code', 'version', 'source_hash', 'schema_hash']),
            'book_count' => DB::table('lcd_books')->where('school_id', $schoolId)->where('academic_year_id', $academicYear->id)->count(),
            'checks' => $checks,
        ];
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Control', 'Estado'], collect($checks)->map(fn ($check) => [$check['label'], $check['passed'] ? 'OK' : 'BLOQUEADO'])->all());
            $this->line($payload['ready'] ? '<info>Preflight EDE conforme.</info>' : '<error>Preflight EDE bloqueado.</error>');
        }

        return $payload['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
