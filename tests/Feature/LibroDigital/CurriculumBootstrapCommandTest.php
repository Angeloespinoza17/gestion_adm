<?php

namespace Tests\Feature\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Services\LibroDigital\XlsxReportBuilder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurriculumBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/lcd-curr-bootstrap-'.Str::lower(Str::random(12));
        mkdir($this->temporaryDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.'/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_dry_run_is_repeatable_and_apply_guards_never_create_or_approve_records(): void
    {
        [$school, $year] = $this->context();
        [$xlsx, $evidenceDirectory] = $this->workbook();
        $arguments = [
            '--school' => (string) $school->id,
            '--year' => (string) $year->year,
            '--xlsx' => $xlsx,
            '--evidence-dir' => $evidenceDirectory,
            '--system-request' => true,
        ];

        $this->assertSame(Command::SUCCESS, Artisan::call('lcd:curriculum:bootstrap', $arguments));
        $firstOutput = Artisan::output();
        $this->assertStringContainsString('"declared": 129', $firstOutput);
        $this->assertStringContainsString('"to_create": 129', $firstOutput);
        $this->assertStringContainsString('"inactive": 127', $firstOutput);
        $this->assertStringContainsString('"MAT"', $firstOutput);
        $this->assertStringContainsString('"PM"', $firstOutput);

        $this->assertSame(Command::SUCCESS, Artisan::call('lcd:curriculum:bootstrap', $arguments));
        $this->assertSame($firstOutput, Artisan::output());

        $this->assertSame(Command::INVALID, Artisan::call('lcd:curriculum:bootstrap', [
            ...$arguments,
            '--apply' => true,
            '--confirm' => 'CREAR-Y-VALIDAR',
        ]));
        $this->assertStringContainsString('--validate-only', Artisan::output());

        $this->assertSame(Command::INVALID, Artisan::call('lcd:curriculum:bootstrap', [
            ...$arguments,
            '--apply' => true,
            '--validate-only' => true,
        ]));
        $this->assertStringContainsString('--confirm=CREAR-Y-VALIDAR', Artisan::output());

        $this->assertDatabaseCount('schedule_subjects', 0);
        $this->assertDatabaseCount('lcd_curriculum_import_batches', 0);
        $this->assertDatabaseCount('lcd_curriculum_catalog_activations', 0);
        $this->assertDatabaseCount('lcd_audit_events', 0);
    }

    /** @return array{School, AcademicYear} */
    private function context(): array
    {
        $school = School::query()->create([
            'rbd' => '12345-6',
            'name' => 'Escuela prueba bootstrap',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $year = AcademicYear::factory()->create([
            'year' => 2035,
            'name' => '2035',
            'starts_at' => '2035-03-01',
            'ends_at' => '2035-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-CURRICULUM-BOOTSTRAP',
            'name' => 'Perfil curricular bootstrap',
            'version' => '1',
            'effective_from' => '2035-01-01',
            'retention_years' => 6,
            'rules_snapshot' => [],
            'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id,
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);

        return [$school, $year];
    }

    /** @return array{string, string} */
    private function workbook(): array
    {
        $evidence = 'official curriculum evidence';
        $evidencePath = $this->temporaryDirectory.'/official.pdf';
        file_put_contents($evidencePath, $evidence);
        $evidenceHash = hash('sha256', $evidence);
        $subjects = [
            ['PM', 'Pensamiento Matemático', 'PARVULARIA', 'PARVULARIA', 'NT1,NT2', 2, 2, 'ACTIVA', 'https://example.test/pm', 'vigente'],
            ['MAT', 'Matemática', 'GENERAL', 'BASICA', '1B,2B', 2, 2, 'ACTIVA', 'https://example.test/mat', 'vigente'],
        ];
        for ($index = 1; $index <= 127; $index++) {
            $code = sprintf('S%03d', $index);
            $subjects[] = [$code, 'Asignatura '.$code, 'GENERAL', 'MEDIA', '1M', 1, 1, 'REFERENCIA', 'https://example.test/'.$code, 'vigente'];
        }

        $contents = (new XlsxReportBuilder)->build([], [
            [
                'title' => 'Catalogo',
                'headers' => ['catalog_code', 'catalog_name', 'version', 'authority', 'source_url', 'source_sha256', 'effective_from', 'effective_to'],
                'rows' => [['CAT-BOOTSTRAP', 'Catálogo de prueba', '1', 'MINEDUC', 'https://example.test/catalog', $evidenceHash, '2035-01-01', '2035-12-31']],
            ],
            [
                'title' => 'Fuentes',
                'headers' => ['source_key', 'source_scope', 'source_name', 'authority', 'document_number', 'source_url', 'source_sha256', 'effective_from', 'effective_to', 'curriculum_track', 'subject_code', 'objective_type'],
                'rows' => [['SOURCE-1', 'GENERAL_1B_6B', 'Fuente oficial', 'MINEDUC', 'DS-1', 'https://example.test/source', $evidenceHash, '2035-01-01', '2035-12-31', 'GENERAL', 'MAT', 'OA']],
            ],
            [
                'title' => 'Objetivos',
                'headers' => ['catalog_code', 'catalog_version', 'code', 'objective_type', 'subject_code', 'level_code', 'grade_code', 'curriculum_track', 'axis_code', 'unit_code', 'description', 'indicators_json', 'active', 'source_page'],
                'rows' => [['CAT-BOOTSTRAP', '1', 'OA-1', 'OA', 'MAT', 'BASICA', '1B', 'GENERAL', 'NUMEROS', '', 'Objetivo de prueba', '[]', 'SI', 'p. 1']],
            ],
            [
                'title' => 'ObjetivoFuentes',
                'headers' => ['objective_code', 'objective_type', 'subject_code', 'level_code', 'grade_code', 'curriculum_track', 'axis_code', 'source_key', 'source_role', 'source_locator'],
                'rows' => [['OA-1', 'OA', 'MAT', 'BASICA', '1B', 'GENERAL', 'NUMEROS', 'SOURCE-1', 'canonical_text', 'p. 1']],
            ],
            [
                'title' => 'Vinculos',
                'headers' => ['school_rbd', 'academic_year', 'subject_code', 'catalog_code', 'catalog_version', 'level_code', 'grade_code', 'curriculum_track', 'valid_from', 'valid_to', 'active'],
                'rows' => [
                    ['12345-6', '2035', 'PM', 'CAT-BOOTSTRAP', '1', 'PARVULARIA', 'NT1', 'PARVULARIA', '2035-01-01', '2035-12-31', 'SI'],
                    ['12345-6', '2035', 'MAT', 'CAT-BOOTSTRAP', '1', 'BASICA', '1B', 'GENERAL', '2035-01-01', '2035-12-31', 'SI'],
                ],
            ],
            [
                'title' => 'Asignaturas',
                'headers' => ['codigo_tecnico', 'asignatura_ambito', 'trayectoria', 'niveles', 'grados', 'filas_maestras', 'activables', 'disposiciones', 'pagina_oficial', 'estado'],
                'rows' => $subjects,
            ],
        ]);
        $xlsx = $this->temporaryDirectory.'/curriculum.xlsx';
        file_put_contents($xlsx, $contents);

        return [$xlsx, $this->temporaryDirectory];
    }
}
