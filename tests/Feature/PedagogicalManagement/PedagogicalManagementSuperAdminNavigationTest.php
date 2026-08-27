<?php

namespace Tests\Feature\PedagogicalManagement;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedagogicalManagementSuperAdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_receives_permissions_and_document_workflow_modules(): void
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Acceso total.', 'active' => true],
        );
        $user = User::factory()->create(['active' => true]);
        $user->roles()->sync([$role->id]);

        $moduleResponse = $this->actingAs($user)->getJson('/api/me/modules')->assertOk();
        $this->assertStringContainsString('no-store', (string) $moduleResponse->headers->get('Cache-Control'));
        $modules = collect($moduleResponse->json('data'));

        $parent = $modules->firstWhere('slug', 'pedagogical_management');
        $child = $modules->firstWhere('slug', 'pedagogical_instrument_analysis');
        $teacherModule = $modules->firstWhere('slug', 'pedagogical_my_instruments');
        $reviewModule = $modules->firstWhere('slug', 'pedagogical_document_review');
        $assignmentModule = $modules->firstWhere('slug', 'pedagogical_coordinator_assignments');
        $printModule = $modules->firstWhere('slug', 'centro_apuntes_pedagogical_queue');
        $this->assertNotNull($parent);
        $this->assertNotNull($child);
        $this->assertSame('Gestión pedagógica', $parent['name']);
        $this->assertSame('Análisis de instrumentos', $child['name']);
        $this->assertSame('/gestion-pedagogica/analisis-instrumentos', $child['frontend_route']);
        $this->assertSame($parent['id'], $child['parent_id']);
        $this->assertSame(1, $child['sort_order']);
        $this->assertSame('/gestion-pedagogica/instrumentos', $teacherModule['frontend_route']);
        $this->assertSame('/gestion-pedagogica/revision-documental', $reviewModule['frontend_route']);
        $this->assertSame('/gestion-pedagogica/asignaciones', $assignmentModule['frontend_route']);
        $this->assertSame('/centro-apuntes/instrumentos-aprobados', $printModule['frontend_route']);

        $permissionSlugs = $this->actingAs($user)->getJson('/api/me/permissions')
            ->assertOk()
            ->json('data');
        $this->assertContains('__superadmin__', $permissionSlugs);
        foreach ([
            'pedagogical-instruments.view',
            'pedagogical-instruments.view-all',
            'pedagogical-instruments.create',
            'pedagogical-instruments.update',
            'pedagogical-instruments.archive',
            'pedagogical-instruments.download',
            'pedagogical-instruments.analyze',
            'pedagogical-instruments.resolve-validations',
            'pedagogical-instruments.approve',
            'pedagogical-instruments.review-assigned',
            'pedagogical-instruments.decide',
            'pedagogical-instruments.ai-report',
            'pedagogical-guidance.manage',
            'pedagogical-coordinators.configure',
            'pedagogical-print-requests.view',
            'pedagogical-print-requests.print',
        ] as $permission) {
            $this->assertContains($permission, $permissionSlugs);
        }
    }
}
