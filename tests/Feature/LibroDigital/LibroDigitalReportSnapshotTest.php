<?php

namespace Tests\Feature\LibroDigital;

use App\Jobs\GenerateLibroDigitalReport;
use App\Models\LibroDigital\ReportExport;
use App\Models\LibroDigital\School;
use App\Models\Role;
use App\Models\User;
use App\Services\LibroDigital\LibroDigitalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibroDigitalReportSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_job_uses_an_encrypted_hash_verified_source_snapshot(): void
    {
        Storage::fake('local');
        Queue::fake();
        config([
            'libro_digital.storage.disk' => 'local',
            'libro_digital.storage.root' => 'private/libro-digital',
        ]);

        $school = School::query()->create([
            'rbd' => '12345-6',
            'name' => 'Escuela de prueba',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $user = User::factory()->create(['active' => true]);

        $service = app(LibroDigitalReportService::class);
        $export = $service->request($school, $user, 'attendance', 'json', []);

        Queue::assertPushed(GenerateLibroDigitalReport::class, fn (GenerateLibroDigitalReport $job): bool => $job->reportExportId === $export->id);
        $this->assertNotNull($export->source_private_path);
        $this->assertNotNull($export->source_snapshot_hash);
        Storage::disk('local')->assertExists($export->source_private_path);

        $ciphertext = Storage::disk('local')->get($export->source_private_path);
        $this->assertStringNotContainsString('Resumen ejecutivo', $ciphertext);
        $snapshotJson = Crypt::decryptString($ciphertext);
        $this->assertSame($export->source_snapshot_hash, hash('sha256', $snapshotJson));

        $service->generate($export->fresh());
        $completed = $export->fresh();
        $this->assertSame('completed', $completed->status->value);
        $generated = json_decode(Storage::disk('local')->get($completed->private_path), true, flags: JSON_THROW_ON_ERROR);
        $snapshot = json_decode($snapshotJson, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($snapshot['metadata'], $generated['metadata']);
        $this->assertSame($snapshot['sections'], $generated['sections']);
        $this->assertTrue($completed->draft_watermark);
    }

    public function test_report_download_rejects_a_private_file_whose_hash_changed(): void
    {
        Storage::fake('local');
        config(['libro_digital.storage.disk' => 'local']);
        $school = School::query()->create([
            'rbd' => '12345-6',
            'name' => 'Escuela de prueba',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $user->roles()->sync([$role->id]);
        $path = 'private/libro-digital/reports/tampered.pdf';
        Storage::disk('local')->put($path, 'tampered-after-generation');
        $export = ReportExport::query()->create([
            'school_id' => $school->id,
            'requested_by' => $user->id,
            'report_type' => 'official_roster',
            'format' => 'pdf',
            'title' => 'Informe de prueba',
            'filters_snapshot' => [],
            'status' => 'completed',
            'progress' => 100,
            'draft_watermark' => true,
            'private_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('tampered-after-generation'),
            'sha256' => hash('sha256', 'original-generated-file'),
            'requested_at' => now('UTC')->subMinute(),
            'completed_at' => now('UTC'),
            'expires_at' => now('UTC')->addDay(),
        ]);

        $this->actingAs($user)->getJson('/api/libro-digital/v1/reports/'.$export->public_id.'/download?school_id='.$school->id)
            ->assertConflict()->assertJsonPath('code', 'LCD_REPORT_HASH_MISMATCH');
    }
}
