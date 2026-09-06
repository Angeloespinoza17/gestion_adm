<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ConvivenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaCaseExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_export_requires_case_visibility_and_export_permission(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $case = ConvivenciaCase::query()->where('is_sensitive', false)->firstOrFail();

        Sanctum::actingAs($this->userWithPermissions(['ver_casos_convivencia'], 'casos_sin_exportar'));
        $this->getJson("/api/convivencia/cases/{$case->id}/export-data")->assertForbidden();

        Sanctum::actingAs($this->userWithPermissions(['exportar_reportes_convivencia'], 'exportar_sin_casos'));
        $this->getJson("/api/convivencia/cases/{$case->id}/export-data")->assertForbidden();

        Sanctum::actingAs($this->userWithPermissions([
            'ver_casos_convivencia',
            'exportar_reportes_convivencia',
        ], 'casos_con_exportacion'));
        $this->getJson("/api/convivencia/cases/{$case->id}/export-data")
            ->assertOk()
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonStructure([
                'generated_at',
                'data' => [
                    'folio',
                    'academic_year',
                    'course_section',
                    'student',
                    'case_type',
                    'classification',
                    'subclassification',
                    'criticality',
                    'responsible_user',
                    'responsible_staff',
                    'people',
                    'follow_ups',
                    'complaints',
                    'daily_logs',
                    'derivations',
                    'measures',
                    'interviews',
                    'protocol_activations',
                    'attachments',
                    'status_logs',
                ],
            ]);
    }

    public function test_case_export_contains_complete_visible_relations_and_filters_confidential_children(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $owner = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        $case = ConvivenciaCase::query()
            ->where('is_sensitive', false)
            ->whereHas('derivations')
            ->whereHas('measures')
            ->whereHas('interviews')
            ->firstOrFail();

        $case->followUps()->create([
            'responsible_user_id' => $owner->id,
            'follow_up_at' => now(),
            'entry_type' => 'seguimiento',
            'status' => 'registrado',
            'title' => 'Seguimiento visible en PDF',
            'notes' => 'Se registran acuerdos y próximo control.',
        ]);

        $privateMarker = 'MARCADOR_RELACION_CONFIDENCIAL_PDF';
        $case->people()->create([
            'person_type' => 'externo',
            'role_type' => 'informante',
            'full_name' => $privateMarker,
            'is_sensitive' => true,
        ]);
        ConvivenciaDerivation::query()->create([
            'case_id' => $case->id,
            'responsible_user_id' => $owner->id,
            'scope' => 'external',
            'status' => 'ingresada',
            'priority_level' => 'urgente',
            'confidentiality_level' => 'confidencial',
            'destination_label' => $privateMarker,
            'derived_at' => now(),
            'motive' => 'Antecedente protegido',
            'is_sensitive' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $case->attachments()->create([
            'category' => 'informe',
            'confidentiality_level' => 'confidencial',
            'is_sensitive' => true,
            'file_path' => 'convivencia-private/test/confidencial.pdf',
            'original_name' => $privateMarker.'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'uploaded_by' => $owner->id,
        ]);

        $viewer = $this->userWithPermissions([
            'ver_casos_convivencia',
            'exportar_reportes_convivencia',
        ], 'exportador_sin_confidenciales');
        Sanctum::actingAs($viewer);
        $response = $this->getJson("/api/convivencia/cases/{$case->id}/export-data")
            ->assertOk()
            ->assertJsonCount(1, 'data.follow_ups')
            ->assertJsonMissing(['full_name' => $privateMarker])
            ->assertDontSee($privateMarker, false);

        $this->assertNotEmpty($response->json('data.derivations'));
        $this->assertNotEmpty($response->json('data.measures'));
        $this->assertNotEmpty($response->json('data.interviews'));
        $this->assertNotEmpty($response->json('data.people'));

        $sensitiveViewer = $this->userWithPermissions([
            'ver_casos_convivencia',
            'exportar_reportes_convivencia',
            'ver_casos_sensibles_convivencia',
        ], 'exportador_con_confidenciales');
        Sanctum::actingAs($sensitiveViewer);
        $this->getJson("/api/convivencia/cases/{$case->id}/export-data")
            ->assertOk()
            ->assertSee($privateMarker, false);
    }

    private function userWithPermissions(array $slugs, string $roleSlug): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => str_replace('_', ' ', ucfirst($roleSlug)),
            'slug' => $roleSlug,
            'active' => true,
        ]);
        $permissions = Permission::query()->whereIn('slug', $slugs)->pluck('id');
        $this->assertCount(count($slugs), $permissions);
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);

        return $user->fresh();
    }
}
