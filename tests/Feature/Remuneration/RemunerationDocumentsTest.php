<?php

namespace Tests\Feature\Remuneration;

use App\Models\HumanResources\HrDocumentControl;
use App\Models\HumanResources\HrDocumentRequirement;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RemunerationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemunerationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RemunerationSeeder::class);
        Sanctum::actingAs(User::query()->firstOrFail());
    }

    public function test_requirement_appears_as_pending_for_every_active_staff_without_duplicate_rows(): void
    {
        $activeStaffCount = Staff::query()->where('active', true)->count();

        $response = $this->postJson('/api/remuneraciones/documents/requirements', [
            'name' => 'Reglamento interno',
            'description' => 'Debe ser entregado y firmado por cada funcionario.',
            'requires_delivery' => true,
            'requires_signature' => true,
            'validity_mode' => 'none',
            'alert_days' => 30,
            'is_required' => true,
            'active' => true,
            'sort_order' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'reglamento_interno')
            ->assertJsonPath('data.requires_signature', true);

        $matrix = $this->getJson('/api/remuneraciones/documents/staff?per_page=50');

        $matrix->assertOk()
            ->assertJsonPath('summary.staff', $activeStaffCount)
            ->assertJsonPath('summary.requirements', 1)
            ->assertJsonPath('summary.pending', $activeStaffCount)
            ->assertJsonPath('data.0.documents.0.status', 'pending_both');

        $this->assertSame(
            0,
            HrDocumentControl::query()
                ->where('document_requirement_id', $response->json('data.id'))
                ->count(),
        );
    }

    public function test_delivery_signature_private_file_and_monthly_expiration_are_recorded(): void
    {
        Storage::fake('local');

        $staff = Staff::query()->where('active', true)->firstOrFail();
        $requirement = HrDocumentRequirement::query()->create([
            'code' => 'antecedentes',
            'name' => 'Certificado de antecedentes',
            'requires_delivery' => true,
            'requires_signature' => true,
            'validity_mode' => 'months',
            'validity_months' => 12,
            'alert_days' => 45,
            'is_required' => true,
            'active' => true,
            'sort_order' => 10,
        ]);

        $response = $this->post(
            "/api/remuneraciones/documents/staff/{$staff->id}/requirements/{$requirement->id}",
            [
                'issued_at' => '2026-09-01',
                'delivered' => '1',
                'delivered_on' => '2026-09-02',
                'signed' => '1',
                'signed_on' => '2026-09-03',
                'file' => UploadedFile::fake()->create('antecedentes.pdf', 240, 'application/pdf'),
                'notes' => 'Original revisado por RR.HH.',
            ],
        );

        $response->assertCreated();

        $control = HrDocumentControl::query()
            ->where('document_requirement_id', $requirement->id)
            ->where('staff_id', $staff->id)
            ->firstOrFail();
        $this->assertSame('2027-09-01', $control->expires_at?->format('Y-m-d'));
        $this->assertSame('2026-09-02', $control->delivered_at?->format('Y-m-d'));
        $this->assertSame('2026-09-03', $control->signed_at?->format('Y-m-d'));
        $this->assertSame('local', $control->file_disk);
        $this->assertSame('antecedentes.pdf', $control->original_name);
        Storage::disk('local')->assertExists($control->file_path);

        $download = $this->get("/api/remuneraciones/documents/compliances/{$control->id}/download");
        $download->assertOk();
        $this->assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
    }

    public function test_document_without_expiration_stays_current_after_delivery(): void
    {
        $staff = Staff::query()->where('active', true)->firstOrFail();
        $requirement = HrDocumentRequirement::query()->create([
            'code' => 'entrega_reglamento',
            'name' => 'Entrega de reglamento',
            'requires_delivery' => true,
            'requires_signature' => false,
            'validity_mode' => 'none',
            'alert_days' => 0,
            'is_required' => true,
            'active' => true,
            'sort_order' => 10,
        ]);

        $this->postJson(
            "/api/remuneraciones/documents/staff/{$staff->id}/requirements/{$requirement->id}",
            [
                'issued_at' => '2026-09-01',
                'delivered' => true,
                'delivered_on' => '2026-09-01',
                'signed' => false,
            ],
        )->assertCreated();

        $control = HrDocumentControl::query()
            ->where('document_requirement_id', $requirement->id)
            ->where('staff_id', $staff->id)
            ->firstOrFail();
        $this->assertNull($control->expires_at);

        $this->getJson('/api/remuneraciones/documents/staff?search='.urlencode((string) $staff->rut))
            ->assertOk()
            ->assertJsonPath('data.0.documents.0.status', 'current')
            ->assertJsonPath('data.0.documents.0.compliance.expires_at', null);
    }

    public function test_requirement_and_manual_expiration_validation_are_enforced(): void
    {
        $this->postJson('/api/remuneraciones/documents/requirements', [
            'name' => 'Acción inválida',
            'requires_delivery' => false,
            'requires_signature' => false,
            'validity_mode' => 'none',
            'alert_days' => 30,
            'is_required' => true,
            'active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('requires_delivery');

        $staff = Staff::query()->where('active', true)->firstOrFail();
        $requirement = HrDocumentRequirement::query()->create([
            'code' => 'vigencia_manual',
            'name' => 'Documento con vigencia informada',
            'requires_delivery' => true,
            'requires_signature' => false,
            'validity_mode' => 'manual',
            'alert_days' => 30,
            'is_required' => true,
            'active' => true,
            'sort_order' => 10,
        ]);

        $this->postJson(
            "/api/remuneraciones/documents/staff/{$staff->id}/requirements/{$requirement->id}",
            ['delivered' => true, 'signed' => false],
        )->assertUnprocessable()->assertJsonValidationErrors('expires_at');
    }
}
