<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceChecklistItem;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceEvidencePhoto;
use App\Models\MaintenanceVisit;
use App\Models\MaintenanceVisitChecklistResponse;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceVisitChecklistPhotoTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceVisit $visit;

    private MaintenanceChecklistItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create([
            'name' => 'Revisor de dependencias',
            'slug' => 'revisor-dependencias-test',
            'active' => true,
        ]);

        foreach (['ver_visitas_mantencion', 'gestionar_visitas_mantencion', 'crear_ot'] as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'active' => true]
            );
            $role->permissions()->attach($permission);
        }

        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $dependency = MaintenanceDependency::query()->create([
            'dependency_kind' => MaintenanceDependency::KIND_SPACE,
            'code' => 'DEP-FOTOS-01',
            'name' => 'Sala de prueba fotográfica',
            'active' => true,
            'is_maintenance_location' => true,
        ]);

        $this->visit = MaintenanceVisit::query()->create([
            'maintenance_dependency_id' => $dependency->id,
            'responsible' => 'Responsable de mantención',
            'visit_date' => '2026-08-31',
            'visit_time' => '09:00',
            'visit_type' => 'Inspección',
            'status' => 'En progreso',
        ]);

        $this->item = MaintenanceChecklistItem::query()->create([
            'system' => 'Electricidad',
            'subdimension' => 'Iluminación',
            'review' => 'Revisar luminarias y conexiones visibles.',
            'active' => true,
        ]);
    }

    public function test_multiple_photos_are_preserved_linked_to_the_generated_work_order_and_can_be_deleted(): void
    {
        $upload = $this->withHeader('Accept', 'application/json')->post("/api/maintenance/visits/{$this->visit->id}/checklist-photo", [
            'maintenance_checklist_item_id' => $this->item->id,
            'photos' => [
                UploadedFile::fake()->image('vista-general.jpg', 800, 600),
                UploadedFile::fake()->image('detalle.png', 640, 480),
                UploadedFile::fake()->image('contexto.webp', 640, 480),
            ],
        ]);

        $upload
            ->assertOk()
            ->assertJsonCount(3, 'data.photos')
            ->assertJsonCount(3, 'data.photo_urls');

        $responseId = $upload->json('data.id');
        $photoIds = collect($upload->json('data.photos'))->pluck('id');
        $paths = MaintenanceEvidencePhoto::query()->pluck('path');

        $this->assertCount(3, $paths);
        $paths->each(fn (string $path) => Storage::disk('public')->assertExists($path));

        $this->postJson("/api/maintenance/visits/{$this->visit->id}/checklist", [
            'responses' => [[
                'maintenance_checklist_item_id' => $this->item->id,
                'review_status' => 'No OK',
                'observations' => 'La luminaria presenta desgaste.',
                'finding_description' => 'Reemplazar luminaria y revisar la conexión.',
            ]],
        ])->assertOk();

        $workOrderResponse = $this->postJson(
            "/api/maintenance/visit-checklist-responses/{$responseId}/create-work-order"
        );

        $workOrderResponse
            ->assertCreated()
            ->assertJsonCount(3, 'data.evidence_photos')
            ->assertJsonCount(3, 'data.photo_urls');

        $workOrderId = $workOrderResponse->json('data.id');
        $this->assertDatabaseHas('maintenance_visit_checklist_responses', [
            'id' => $responseId,
            'work_order_id' => $workOrderId,
        ]);
        $this->assertSame(3, MaintenanceEvidencePhoto::query()->where('maintenance_work_order_id', $workOrderId)->count());

        $deletedPhoto = MaintenanceEvidencePhoto::query()->findOrFail($photoIds->first());
        $deletedPath = $deletedPhoto->path;

        $this->deleteJson("/api/maintenance/visits/{$this->visit->id}/checklist-photos/{$deletedPhoto->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonCount(2, 'data.photo_urls');

        Storage::disk('public')->assertMissing($deletedPath);
        $this->assertDatabaseCount('maintenance_evidence_photos', 2);
    }

    public function test_photo_limit_is_enforced_without_deleting_existing_evidence(): void
    {
        $this->withHeader('Accept', 'application/json')->post("/api/maintenance/visits/{$this->visit->id}/checklist-photo", [
            'maintenance_checklist_item_id' => $this->item->id,
            'photos' => collect(range(1, 3))
                ->map(fn (int $number) => UploadedFile::fake()->image("foto-{$number}.jpg", 320, 240))
                ->all(),
        ])->assertOk();

        $this->withHeader('Accept', 'application/json')->post("/api/maintenance/visits/{$this->visit->id}/checklist-photo", [
            'maintenance_checklist_item_id' => $this->item->id,
            'photos' => [UploadedFile::fake()->image('foto-4.jpg', 320, 240)],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photos');

        $this->assertDatabaseCount('maintenance_evidence_photos', 3);
        $this->assertSame(3, MaintenanceVisitChecklistResponse::query()->firstOrFail()->photos()->count());
    }
}
