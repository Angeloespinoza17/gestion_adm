<?php

namespace Tests\Feature\RiskPrevention;

use App\Models\RiskPrevention\RiskAssessment;
use App\Models\RiskPrevention\RiskEntry;
use App\Models\RiskPrevention\RiskImportBatch;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixTask;
use App\Models\User;
use Database\Seeders\Modules\PrevencionRiesgosModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiskMatrixImportTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PrevencionRiesgosModuleSeeder::class);
        $this->actor = User::query()->where('email', 'superadmin@cnscgestion.cl')->firstOrFail();
        Sanctum::actingAs($this->actor);
    }

    public function test_anonymized_legacy_workbook_is_previewed_normalized_and_committed_transactionally(): void
    {
        $this->assertSame(0, RiskMatrix::query()->count());
        $preview = $this->post('/api/risk-prevention/risk-matrices/imports/preview', [
            'file' => $this->fixtureUpload(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'preview_ready')
            ->assertJsonPath('data.total_rows', 4)
            ->assertJsonPath('data.valid_rows', 4)
            ->assertJsonPath('data.error_rows', 0);

        $this->assertSame(0, RiskMatrix::query()->count(), 'La previsualización no debe escribir matrices.');
        $batchId = $preview->json('data.id');
        $batch = RiskImportBatch::query()->findOrFail($batchId);
        $rows = $batch->preview_payload['rows'];
        $longTask = 'Tareas en superficie o plataformas sobre 1,80 metros ('.str_repeat('limpieza preventiva de superficies, ', 9).'cierre seguro)';
        $rows[0]['task'] = $longTask;
        $summary = $batch->summary;
        $summary['matrix_metadata'] = [
            'company_name' => 'Entidad histórica anonimizada',
            'updated_on' => '2026-06-01',
            'total_workers' => 25,
        ];
        $batch->update(['preview_payload' => ['rows' => $rows], 'summary' => $summary]);

        $this->assertSame(2, $rows[0]['probability']);
        $this->assertSame(4, $rows[0]['consequence']);
        $this->assertSame(8, $rows[0]['score']);
        $this->assertSame('important', $rows[0]['risk_level_code']);
        $this->assertTrue($rows[0]['controlled']);
        $this->assertFalse($rows[1]['controlled']);
        $this->assertFalse($rows[2]['controlled']);
        $this->assertTrue($rows[3]['controlled']);
        $this->assertTrue(collect($rows[0]['normalization_warnings'])->contains('code', 'magnitude_recalculated'));
        $this->assertTrue(collect($rows[0]['normalization_warnings'])->contains('code', 'classification_recalculated'));
        $this->assertFalse((bool) data_get($batch->summary, 'criteria_sheets_differ'));
        $this->assertCount(2, data_get($batch->summary, 'criteria_sheets'));

        $committed = $this->postJson("/api/risk-prevention/risk-matrices/imports/{$batchId}/commit", [
            'type' => 'new_matrix',
            'code' => 'IPER-IMPORT-ANON',
            'name' => 'Importación histórica anonimizada',
        ])->assertOk()->assertJsonPath('data.source_import_batch_id', $batchId);

        $this->assertSame(1, RiskMatrix::query()->count());
        $this->assertSame(4, RiskEntry::query()->count());
        $this->assertSame($longTask, RiskMatrixTask::query()->orderBy('id')->value('task_name'));
        $this->assertSame('Entidad histórica anonimizada', RiskMatrix::query()->value('company_name'));
        $this->assertSame('2026-06-01', substr((string) $committed->json('data.updated_on'), 0, 10));
        $this->assertSame(25, $committed->json('data.total_workers'));
        $this->assertSame([1, 2, 4, 8], RiskAssessment::query()->orderBy('calculated_score')->pluck('calculated_score')->map(fn ($score) => (int) $score)->all());
        $this->assertSame('committed', $batch->fresh()->status);
        $this->assertDatabaseHas('prevent_risk_audit_logs', ['action' => 'import_committed']);
        $this->assertNotNull($committed->json('data.id'));
        $detail = $this->getJson('/api/risk-prevention/risk-matrix-versions/'.$committed->json('data.id'))->assertOk();
        $this->assertNotNull($detail->json('data.processes.0.tasks.0.risks.0.current_assessment.calculated_score'));
        $this->assertSame('important', $detail->json('data.processes.0.tasks.0.risks.0.current_assessment.calculated_level.code'));

        $duplicate = $this->post('/api/risk-prevention/risk-matrices/imports/preview', [
            'file' => $this->fixtureUpload(),
        ])->assertCreated();
        $duplicateId = $duplicate->json('data.id');
        $this->assertNotNull($duplicate->json('data.summary.duplicate_file_warning'));

        $this->postJson("/api/risk-prevention/risk-matrices/imports/{$duplicateId}/commit", [
            'type' => 'new_matrix',
            'code' => 'IPER-IMPORT-DUP',
            'name' => 'Duplicado no confirmado',
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseMissing('prevent_risk_matrices', ['code' => 'IPER-IMPORT-DUP']);
    }

    private function fixtureUpload(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/Fixtures/matriz-iper-anonimizada.xlsx'),
            'matriz-iper-anonimizada.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
