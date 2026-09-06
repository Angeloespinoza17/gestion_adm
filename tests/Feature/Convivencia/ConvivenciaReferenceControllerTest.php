<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;
use Database\Seeders\ConvivenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaReferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_type_and_query_are_validated_and_each_type_keeps_its_own_permission(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $user = $this->userWithPermissions([
            ConvivenciaAccessService::MANAGE_COMPLAINTS_PERMISSION,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/convivencia/references/complaints')->assertOk();
        $this->getJson('/api/convivencia/references/cases')->assertForbidden();
        $this->getJson('/api/convivencia/references/plans')->assertForbidden();
        $this->getJson('/api/convivencia/references/protocols')->assertForbidden();
        $this->getJson('/api/convivencia/references/parts')->assertForbidden();

        $this->getJson('/api/convivencia/references/unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
        $this->getJson('/api/convivencia/references/complaints?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
        $this->getJson('/api/convivencia/references/complaints?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }

    public function test_reference_payload_is_minimal_private_and_does_not_leak_narratives_or_contact_data(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $user = $this->superAdmin();
        Sanctum::actingAs($user);

        $case = ConvivenciaCase::query()->firstOrFail();
        $complaint = ConvivenciaComplaint::query()->firstOrFail();
        $plan = ConvivenciaPlan::query()->firstOrFail();
        $protocol = ConvivenciaProtocol::query()->firstOrFail();
        $part = ConvivenciaProtocolPart::query()->firstOrFail();

        $case->forceFill([
            'initial_report' => 'SECRET_CASE_NARRATIVE_2099',
            'internal_notes' => 'SECRET_CASE_INTERNAL_2099',
        ])->save();
        if ($case->student) {
            $case->student->forceFill([
                'rut' => '99.999.999-9',
                'email' => 'secret-student@example.test',
                'phone' => '+56999999999',
            ])->save();
        }
        $complaint->forceFill([
            'report_text' => 'SECRET_COMPLAINT_NARRATIVE_2099',
            'contact_email' => 'secret-complainant@example.test',
            'contact_phone' => '+56888888888',
            'involved_snapshot' => [['private_snapshot' => 'SECRET_SNAPSHOT_2099']],
        ])->save();
        $plan->forceFill(['general_objective' => 'SECRET_PLAN_OBJECTIVE_2099'])->save();
        $protocol->forceFill(['description' => 'SECRET_PROTOCOL_DESCRIPTION_2099'])->save();
        $part->forceFill(['instructions' => 'SECRET_PART_INSTRUCTIONS_2099'])->save();

        $targets = [
            'cases' => $case->id,
            'complaints' => $complaint->id,
            'plans' => $plan->id,
            'protocols' => $protocol->id,
            'parts' => $part->id,
        ];
        $forbiddenKeys = [
            'initial_report',
            'internal_notes',
            'report_text',
            'contact_email',
            'contact_phone',
            'involved_snapshot',
            'general_objective',
            'description',
            'instructions',
            'rut',
            'email',
            'phone',
            'student',
            'affected_student',
            'is_sensitive',
            'created_by',
            'updated_by',
        ];

        foreach ($targets as $type => $selectedId) {
            $response = $this->getJson("/api/convivencia/references/{$type}?per_page=3&selected_id={$selectedId}");

            $response
                ->assertOk()
                ->assertHeader('Pragma', 'no-cache')
                ->assertJsonStructure([
                    'current_page',
                    'data' => [['id', 'label', 'status', 'secondary']],
                    'last_page',
                    'per_page',
                    'total',
                ]);
            $cacheControl = (string) $response->headers->get('Cache-Control');
            foreach (['no-store', 'no-cache', 'must-revalidate', 'private'] as $directive) {
                $this->assertStringContainsString($directive, $cacheControl);
            }

            foreach ($response->json('data') as $reference) {
                $this->assertEqualsCanonicalizing(
                    ['id', 'label', 'status', 'secondary'],
                    array_keys($reference),
                );
                $this->assertSame([], array_values(array_intersect($forbiddenKeys, array_keys($reference))));
            }

            $serialized = $response->getContent();
            foreach ([
                'SECRET_CASE_NARRATIVE_2099',
                'SECRET_CASE_INTERNAL_2099',
                '99.999.999-9',
                'secret-student@example.test',
                '+56999999999',
                'SECRET_COMPLAINT_NARRATIVE_2099',
                'secret-complainant@example.test',
                '+56888888888',
                'SECRET_SNAPSHOT_2099',
                'SECRET_PLAN_OBJECTIVE_2099',
                'SECRET_PROTOCOL_DESCRIPTION_2099',
                'SECRET_PART_INSTRUCTIONS_2099',
            ] as $secret) {
                $this->assertStringNotContainsString($secret, $serialized);
            }
        }
    }

    public function test_search_is_paginated_and_selected_record_is_injected_without_duplicates(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        Sanctum::actingAs($this->superAdmin());

        $first = ConvivenciaPlan::query()->firstOrFail();
        $second = $first->replicate();
        $selected = $first->replicate();
        $first->forceFill(['name' => 'Aguja Alfa'])->save();
        $second->forceFill(['name' => 'Aguja Beta'])->save();
        $selected->forceFill(['name' => 'Plan Seleccionado'])->save();

        $response = $this->getJson("/api/convivencia/references/plans?search=Aguja&per_page=1&page=1&selected_id={$selected->id}");

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('last_page', 2);
        $this->assertCount(2, $response->json('data'));
        $this->assertContains($first->id, array_column($response->json('data'), 'id'));
        $this->assertContains($selected->id, array_column($response->json('data'), 'id'));

        $withoutDuplicate = $this->getJson("/api/convivencia/references/plans?search=Aguja&per_page=1&page=1&selected_id={$first->id}")
            ->assertOk();
        $this->assertCount(1, $withoutDuplicate->json('data'));
        $this->assertSame([$first->id], array_column($withoutDuplicate->json('data'), 'id'));

        $part = ConvivenciaProtocolPart::query()->firstOrFail();
        $part->forceFill(['code' => 'PARTE-BUSQUEDA-UNICA', 'title' => 'Resguardo trazable'])->save();
        $this->getJson('/api/convivencia/references/parts?search=BUSQUEDA-UNICA')
            ->assertOk()
            ->assertJsonPath('data.0.id', $part->id)
            ->assertJsonPath('data.0.secondary', $part->category);
    }

    public function test_selected_id_cannot_bypass_sensitive_visibility_scope(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $user = $this->userWithPermissions([
            ConvivenciaAccessService::VIEW_CASES_PERMISSION,
        ]);
        $otherUserId = User::query()->where('id', '!=', $user->id)->value('id');

        $cases = ConvivenciaCase::query()->orderBy('id')->limit(2)->get();
        $visible = $cases->first();
        $hidden = $cases->last();

        ConvivenciaCase::query()->update([
            'is_sensitive' => true,
            'responsible_user_id' => $otherUserId,
            'created_by' => $otherUserId,
            'updated_by' => $otherUserId,
        ]);
        $this->markOwnedSensitiveCase($visible, $user->id, 'REF-VISIBLE-2099');
        $this->markOwnedSensitiveCase($hidden, (int) $otherUserId, 'REF-HIDDEN-2099');

        Sanctum::actingAs($user);

        $this->getJson("/api/convivencia/references/cases?search=REF-VISIBLE-2099&selected_id={$hidden->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
        $this->getJson("/api/convivencia/references/cases?search=REF-HIDDEN-2099&selected_id={$hidden->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('total', 0);
    }

    public function test_inactive_and_sensitive_protocol_parts_keep_management_boundaries(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $manager = $this->superAdmin();
        $part = ConvivenciaProtocolPart::query()->firstOrFail();
        $protocol = ConvivenciaProtocol::query()->firstOrFail();
        $part->forceFill(['active' => false, 'is_sensitive' => true, 'code' => 'PARTE-INACTIVA-2099'])->save();
        $protocol->forceFill(['status' => 'inactivo', 'code' => 'PROTOCOLO-INACTIVO-2099'])->save();

        Sanctum::actingAs($manager);

        $this->getJson('/api/convivencia/references/parts?search=PARTE-INACTIVA-2099')
            ->assertOk()
            ->assertJsonPath('total', 0);
        $this->getJson("/api/convivencia/references/parts?search=PARTE-INACTIVA-2099&selected_id={$part->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $part->id);
        $this->getJson('/api/convivencia/references/parts?search=PARTE-INACTIVA-2099&include_inactive=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $part->id);

        $this->getJson('/api/convivencia/references/protocols?search=PROTOCOLO-INACTIVO-2099')
            ->assertOk()
            ->assertJsonPath('total', 0);
        $this->getJson("/api/convivencia/references/protocols?search=PROTOCOLO-INACTIVO-2099&selected_id={$protocol->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $protocol->id);

        $activator = $this->userWithPermissions([
            ConvivenciaAccessService::ACTIVATE_PROTOCOLS_PERMISSION,
        ]);
        Sanctum::actingAs($activator);

        $this->getJson('/api/convivencia/references/parts?include_inactive=1')->assertForbidden();
        $this->getJson('/api/convivencia/references/protocols?include_inactive=1')->assertForbidden();
        $this->getJson("/api/convivencia/references/parts?selected_id={$part->id}")
            ->assertOk()
            ->assertJsonMissing(['id' => $part->id]);
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Referencias convivencia test',
            'slug' => 'referencias_convivencia_test_'.strtolower((string) $user->id),
            'active' => true,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id'),
        );
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function superAdmin(): User
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->firstOrFail();
    }

    private function markOwnedSensitiveCase(ConvivenciaCase $case, int $ownerId, string $folio): void
    {
        $case->forceFill([
            'folio' => $folio,
            'is_sensitive' => true,
            'responsible_user_id' => $ownerId,
            'created_by' => $ownerId,
            'updated_by' => $ownerId,
        ])->save();
    }
}
