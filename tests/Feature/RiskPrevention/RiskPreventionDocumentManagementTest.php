<?php

namespace Tests\Feature\RiskPrevention;

use App\Models\RiskPrevention\RiskPreventionDocument;
use App\Models\User;
use Database\Seeders\PermissionGroupSeeder;
use Database\Seeders\Modules\PrevencionRiesgosModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiskPreventionDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_upload_a_disseminable_photo_with_file_metadata(): void
    {
        Storage::fake('local');
        $this->seed(PrevencionRiesgosModuleSeeder::class);

        $manager = User::query()->where('email', 'superadmin@cnscgestion.cl')->firstOrFail();
        Sanctum::actingAs($manager);

        $response = $this->post('/api/risk-prevention/documents', [
            'document_type' => 'protocolo',
            'title' => 'Protocolo fotografiado',
            'document_group' => 'Emergencias',
            'version_number' => '1.0',
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'status' => 'vigente',
            'is_disseminable' => '1',
            'responsible_name' => 'Prevención de Riesgos',
            'document' => UploadedFile::fake()->image('protocolo.jpg', 1200, 900),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.is_disseminable', true)
            ->assertJsonPath('data.file_extension', 'jpg')
            ->assertJsonPath('data.has_file', true);

        $document = RiskPreventionDocument::query()->firstOrFail();

        $this->assertSame('image/jpeg', $document->mime_type);
        $this->assertGreaterThan(0, $document->file_size);
        $this->assertNotNull($document->disseminated_at);
        $this->assertSame($manager->id, $document->disseminated_by);
        Storage::disk('local')->assertExists($document->document_path);
    }

    public function test_staff_user_only_sees_current_disseminable_documents(): void
    {
        Storage::fake('local');
        $this->seed(PrevencionRiesgosModuleSeeder::class);

        $staff = User::factory()->create([
            'user_type' => 'staff',
            'active' => true,
        ]);
        Sanctum::actingAs($staff);

        $visible = $this->createDocument('Documento visible', true, 'vigente');
        $this->createDocument('Documento interno', false, 'vigente');
        $this->createDocument('Documento archivado', true, 'archivado');

        $response = $this->getJson('/api/risk-prevention/disseminated-documents');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonMissing(['title' => 'Documento interno'])
            ->assertJsonMissing(['title' => 'Documento archivado']);
    }

    public function test_staff_cannot_download_a_non_disseminable_document_by_direct_url(): void
    {
        Storage::fake('local');
        $this->seed(PrevencionRiesgosModuleSeeder::class);

        $staff = User::factory()->create([
            'user_type' => 'staff',
            'active' => true,
        ]);
        Sanctum::actingAs($staff);

        $document = $this->createDocument('Documento reservado', false, 'vigente');

        $this->getJson("/api/risk-prevention/disseminated-documents/{$document->id}/download")
            ->assertNotFound();
    }

    public function test_non_staff_user_cannot_open_disseminated_documents_endpoint(): void
    {
        $this->seed(PrevencionRiesgosModuleSeeder::class);

        $student = User::factory()->create([
            'user_type' => 'student',
            'staff_id' => null,
            'active' => true,
        ]);
        Sanctum::actingAs($student);

        $this->getJson('/api/risk-prevention/disseminated-documents')
            ->assertForbidden();
    }

    public function test_staff_navigation_only_receives_the_document_management_child_from_risk_prevention(): void
    {
        $this->seed([
            PrevencionRiesgosModuleSeeder::class,
            PermissionGroupSeeder::class,
        ]);

        $staff = User::factory()->create([
            'user_type' => 'staff',
            'active' => true,
        ]);
        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/me/modules');

        $response
            ->assertOk()
            ->assertJsonFragment(['slug' => 'risk_prevention'])
            ->assertJsonFragment(['slug' => 'risk_prevention_staff_documents'])
            ->assertJsonMissing(['slug' => 'risk_prevention_dashboard'])
            ->assertJsonMissing(['slug' => 'risk_prevention_documents']);
    }

    private function createDocument(string $title, bool $disseminable, string $status): RiskPreventionDocument
    {
        $path = "risk-prevention/testing/{$title}.txt";
        Storage::disk('local')->put($path, $title);

        return RiskPreventionDocument::query()->create([
            'document_type' => 'instructivo',
            'title' => $title,
            'version_number' => '1.0',
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'status' => $status,
            'is_disseminable' => $disseminable,
            'document_path' => $path,
            'document_name' => "{$title}.txt",
            'mime_type' => 'text/plain',
            'file_extension' => 'txt',
            'file_size' => strlen($title),
            'disseminated_at' => $disseminable ? now() : null,
        ]);
    }
}
