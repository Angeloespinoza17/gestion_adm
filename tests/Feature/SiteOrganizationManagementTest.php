<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Cargo;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Permission;
use App\Models\RiskPrevention\RiskPreventionJointCommittee;
use App\Models\Role;
use App\Models\SiteOrganization;
use App\Models\SiteOrganizationMember;
use App\Models\SiteOrganizationRole;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\PublicSiteOrganizationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteOrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_modules_are_registered_and_each_area_is_protected(): void
    {
        Sanctum::actingAs(User::factory()->create(['active' => true, 'user_type' => 'staff']));

        $this->getJson('/api/admin/site-organizations?type=cgpa')->assertForbidden();
        $this->postJson('/api/admin/site-organizations', [
            'type' => 'cde',
        ])->assertForbidden();

        foreach ([
            'ver_cgpa_sitio',
            'gestionar_cgpa_sitio',
            'ver_cde_sitio',
            'gestionar_cde_sitio',
            'ver_comite_paritario_sitio',
            'gestionar_comite_paritario_sitio',
        ] as $permission) {
            $this->assertDatabaseHas('permissions', ['slug' => $permission, 'active' => true]);
        }

        foreach ([
            'public_site_cgpa' => '/admin/cgpa',
            'public_site_cde' => '/admin/cde',
            'public_site_joint_committee' => '/admin/comite-paritario',
        ] as $slug => $route) {
            $this->assertDatabaseHas('system_modules', [
                'slug' => $slug,
                'frontend_route' => $route,
                'active' => true,
            ]);
        }
    }

    public function test_cgpa_can_be_published_with_explicit_name_authorization_and_keeps_audit(): void
    {
        $user = $this->userWithPermissions(['ver_cgpa_sitio', 'gestionar_cgpa_sitio']);
        Sanctum::actingAs($user);

        $roleId = $this->postJson('/api/admin/site-organizations/roles', [
            'organization_type' => 'cgpa',
            'name' => 'Presidenta',
            'section' => 'leadership',
            'sort_order' => 1,
            'active' => true,
        ])->assertCreated()->json('data.id');

        $response = $this->postJson('/api/admin/site-organizations', [
            'type' => 'cgpa',
            'name' => 'Directiva CGPA 2026',
            'year' => 2026,
            'summary' => 'Familias al servicio de la comunidad educativa.',
            'status' => 'published',
            'active' => true,
            'members' => [
                [
                    'member_kind' => 'external',
                    'display_name' => 'Representante autorizada',
                    'role_id' => $roleId,
                    'section' => 'leadership',
                    'sort_order' => 1,
                    'public_name_authorized' => true,
                ],
                [
                    'member_kind' => 'external',
                    'display_name' => 'Representante reservada',
                    'role_id' => $roleId,
                    'section' => 'leadership',
                    'sort_order' => 2,
                    'public_name_authorized' => false,
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonCount(2, 'data.members');

        $organizationId = $response->json('data.id');
        $this->getJson('/api/admin/site-organizations?type=cgpa&per_page=1')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.published', 1)
            ->assertJsonPath('summary.draft', 0)
            ->assertJsonPath('summary.active', 1);
        $member = SiteOrganizationMember::query()
            ->where('site_organization_id', $organizationId)
            ->where('display_name_snapshot', 'Representante autorizada')
            ->firstOrFail();
        $authorizedAt = $member->public_name_authorized_at?->toISOString();
        $authorizedBy = $member->public_name_authorized_by;

        $public = app(PublicSiteOrganizationResolver::class)->latest('cgpa');
        $this->assertSame('Directiva CGPA 2026', $public['name']);
        $this->assertCount(1, $public['members']);
        $this->assertSame('Representante autorizada', $public['members'][0]['display_name']);
        $this->assertArrayNotHasKey('student_id', $public['members'][0]);
        $this->assertArrayNotHasKey('email', $public['members'][0]);

        $payload = $response->json('data');
        $payload['members'] = collect($payload['members'])->map(fn (array $item): array => [
            'id' => $item['id'],
            'member_kind' => $item['member_kind'],
            'display_name' => $item['display_name'],
            'role_id' => $item['role_id'],
            'section' => $item['section'],
            'sort_order' => $item['sort_order'] + 1,
            'public_name_authorized' => $item['public_name_authorized'],
        ])->all();

        $this->putJson("/api/admin/site-organizations/cgpa/{$organizationId}", $payload)
            ->assertOk();

        $member->refresh();
        $this->assertSame($authorizedAt, $member->public_name_authorized_at?->toISOString());
        $this->assertSame($authorizedBy, $member->public_name_authorized_by);
    }

    public function test_cde_uses_enrolled_students_and_staff_without_exposing_sensitive_fields(): void
    {
        $user = $this->userWithPermissions(['ver_cde_sitio', 'gestionar_cde_sitio']);
        Sanctum::actingAs($user);
        [$student, $enrollment] = $this->createEnrolledStudent(2026, '8° Básico A');
        [$otherStudent] = $this->createEnrolledStudent(2025, '7° Básico A');
        $staff = $this->createStaff('Profesora asesora');
        $otherLegitimateAdvisor = $this->createStaffWithCargo(
            'Coordinadora de convivencia',
            'Orientadora',
            'orientadora',
        );
        $inactiveStaff = $this->createStaffWithCargo(
            'Funcionario inactivo',
            'Docente',
            'docente-inactivo',
            false,
        );

        $leadershipRole = $this->createRoleThroughApi('cde', 'Presidenta', 'leadership');
        $advisorRole = $this->createRoleThroughApi('cde', 'Profesora asesora', 'advisor');

        $this->getJson('/api/admin/site-organizations/students?year=2026&search=Estudiante')
            ->assertOk()
            ->assertJsonPath('data.0.id', $student->id)
            ->assertJsonPath('data.0.course', '8° Básico A')
            ->assertJsonMissingPath('data.0.rut')
            ->assertJsonMissingPath('data.0.email');

        $this->getJson('/api/admin/site-organizations/staff?search=Profesora')
            ->assertOk()
            ->assertJsonPath('data.0.id', $staff->id)
            ->assertJsonMissingPath('data.0.rut')
            ->assertJsonMissingPath('data.0.email');
        $this->getJson('/api/admin/site-organizations/staff?type=cde&search=Orientadora')
            ->assertOk()
            ->assertJsonPath('data.0.id', $otherLegitimateAdvisor->id)
            ->assertJsonPath('data.0.position', 'Orientadora')
            ->assertJsonMissingPath('data.0.rut')
            ->assertJsonMissingPath('data.0.email');
        $this->getJson('/api/admin/site-organizations/staff?type=cde&search=Funcionario%20inactivo')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $basePayload = [
            'type' => 'cde',
            'name' => 'Centro de Estudiantes 2026',
            'year' => 2026,
            'summary' => 'Liderazgo y participación de las estudiantes.',
            'status' => 'published',
            'active' => true,
            'members' => [
                [
                    'member_kind' => 'student',
                    'student_id' => $student->id,
                    'role_id' => $leadershipRole,
                    'section' => 'leadership',
                    'sort_order' => 1,
                    'public_name_authorized' => true,
                ],
                [
                    'member_kind' => 'staff',
                    'staff_id' => $otherLegitimateAdvisor->id,
                    'role_id' => $advisorRole,
                    'section' => 'advisor',
                    'sort_order' => 2,
                    'public_name_authorized' => false,
                ],
            ],
        ];

        $this->postJson('/api/admin/site-organizations', array_replace_recursive($basePayload, [
            'members' => [[
                'member_kind' => 'student',
                'student_id' => $otherStudent->id,
                'role_id' => $leadershipRole,
                'section' => 'leadership',
                'sort_order' => 1,
                'public_name_authorized' => true,
            ]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('members.0.student_id');

        $response = $this->postJson('/api/admin/site-organizations', $basePayload)
            ->assertCreated()
            ->assertJsonPath('data.members.0.course_label', '8° Básico A')
            ->assertJsonPath('data.members.1.detail_snapshot', 'Orientadora')
            ->assertJsonMissingPath('data.members.0.rut');

        $this->assertDatabaseHas('site_organization_members', [
            'site_organization_id' => $response->json('data.id'),
            'student_profile_id' => $student->id,
            'display_name_snapshot' => $student->registered_name_resolved,
            'detail_snapshot' => '8° Básico A',
        ]);

        $public = app(PublicSiteOrganizationResolver::class)->latest('cde');
        $this->assertCount(1, $public['members']);
        $this->assertSame($student->registered_name_resolved, $public['members'][0]['display_name']);
        $this->assertSame('8° Básico A', $public['members'][0]['course_label']);
    }

    public function test_joint_committee_adapter_updates_official_source_and_archives_only_web_publication(): void
    {
        $user = $this->userWithPermissions([
            'ver_comite_paritario_sitio',
            'gestionar_comite_paritario_sitio',
        ]);
        Sanctum::actingAs($user);
        $staff = $this->createStaff('Integrante Comité');

        $response = $this->postJson('/api/admin/site-organizations', [
            'type' => 'joint_committee',
            'name' => 'Comité Paritario 2026–2028',
            'starts_on' => '2026-03-01',
            'ends_on' => '2028-02-29',
            'summary' => 'Prevención y cuidado de la comunidad trabajadora.',
            'status' => 'published',
            'active' => true,
            'members' => [[
                'member_kind' => 'staff',
                'staff_id' => $staff->id,
                'representation' => 'trabajadores',
                'member_role' => 'titular',
                'position_name' => 'Presidenta',
                'section' => 'leadership',
                'sort_order' => 1,
                'joined_on' => '2026-03-01',
                'ended_on' => null,
                'active' => true,
                'public_name_authorized' => true,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'joint_committee')
            ->assertJsonMissingPath('data.members.0.rut');

        $committeeId = $response->json('data.id');
        $this->assertDatabaseHas('prevent_joint_committees', [
            'id' => $committeeId,
            'name' => 'Comité Paritario 2026–2028',
            'web_status' => 'published',
            'active' => true,
        ]);
        $this->assertDatabaseHas('prevent_joint_committee_staff', [
            'committee_id' => $committeeId,
            'staff_id' => $staff->id,
            'position_name' => 'Presidenta',
            'public_name_authorized' => true,
        ]);

        $public = app(PublicSiteOrganizationResolver::class)->latest('joint-committee');
        $this->assertSame('Comité Paritario 2026–2028', $public['name']);
        $this->assertSame('Integrante Comité', $public['members'][0]['display_name']);

        $this->deleteJson("/api/admin/site-organizations/joint_committee/{$committeeId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.active', true);
        $this->assertNull(app(PublicSiteOrganizationResolver::class)->latest('joint-committee'));
        $this->assertTrue(RiskPreventionJointCommittee::query()->findOrFail($committeeId)->active);
    }

    public function test_cross_type_payload_cannot_bypass_authorization_or_change_role_ownership(): void
    {
        $cgpa = SiteOrganization::query()->create([
            'type' => 'cgpa',
            'year' => 2026,
            'name' => 'CGPA 2026',
            'status' => 'draft',
            'active' => true,
        ]);
        $cgpaRole = SiteOrganizationRole::query()->create([
            'organization_type' => 'cgpa',
            'name' => 'Presidenta',
            'section' => 'leadership',
            'active' => true,
        ]);
        $user = $this->userWithPermissions(['ver_cde_sitio', 'gestionar_cde_sitio']);
        Sanctum::actingAs($user);

        $this->putJson("/api/admin/site-organizations/cgpa/{$cgpa->id}", [
            'type' => 'cde',
            'name' => 'Intento cruzado',
            'year' => 2026,
            'status' => 'draft',
            'active' => true,
            'members' => [],
        ])->assertForbidden();

        $this->putJson("/api/admin/site-organizations/roles/{$cgpaRole->id}", [
            'organization_type' => 'cde',
            'name' => 'Cargo alterado',
            'section' => 'leadership',
            'sort_order' => 1,
            'active' => true,
        ])->assertForbidden();

        $this->assertSame('CGPA 2026', $cgpa->fresh()->name);
        $this->assertSame('cgpa', $cgpaRole->fresh()->organization_type);
    }

    public function test_public_scopes_exclude_inactive_committee_and_latest_uses_a_limited_query(): void
    {
        SiteOrganization::query()->create([
            'type' => 'cgpa',
            'year' => 2025,
            'name' => 'CGPA 2025',
            'status' => 'published',
            'active' => true,
            'published_at' => now()->subDay(),
        ]);
        SiteOrganization::query()->create([
            'type' => 'cgpa',
            'year' => 2026,
            'name' => 'CGPA 2026',
            'status' => 'published',
            'active' => true,
            'published_at' => now()->subDay(),
        ]);
        RiskPreventionJointCommittee::query()->create([
            'name' => 'Comité inactivo',
            'starts_on' => '2026-01-01',
            'active' => false,
            'web_status' => 'published',
            'web_published_at' => now()->subDay(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $latest = app(PublicSiteOrganizationResolver::class)->latest('cgpa');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame('CGPA 2026', $latest['name']);
        $organizationQuery = collect($queries)->first(
            fn (array $query): bool => str_contains($query['query'], 'from "site_organizations"')
                && str_contains(strtolower($query['query']), 'select'),
        );
        $this->assertNotNull($organizationQuery);
        $this->assertStringContainsString('limit 1', strtolower($organizationQuery['query']));
        $this->assertNull(app(PublicSiteOrganizationResolver::class)->latest('joint-committee'));
    }

    private function createRoleThroughApi(string $type, string $name, string $section): int
    {
        return (int) $this->postJson('/api/admin/site-organizations/roles', [
            'organization_type' => $type,
            'name' => $name,
            'section' => $section,
            'sort_order' => 1,
            'active' => true,
        ])->assertCreated()->json('data.id');
    }

    /** @return array{StudentProfile, StudentEnrollment} */
    private function createEnrolledStudent(int $year, string $courseName): array
    {
        $academicYear = AcademicYear::query()->firstOrCreate(
            ['year' => $year],
            ['name' => (string) $year, 'is_active' => $year === 2026, 'is_closed' => false],
        );
        $level = EducationLevel::query()->firstOrCreate(
            ['name' => "Nivel {$year}"],
            ['order' => $year, 'type' => 'basica'],
        );
        $course = CourseSection::query()->create([
            'academic_year_id' => $academicYear->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => $courseName,
            'active' => true,
        ]);
        $student = StudentProfile::query()->create([
            'first_name' => 'Estudiante',
            'last_name' => (string) $year,
            'registered_name' => "Estudiante {$year}",
            'general_status' => 'activo',
        ]);
        $enrollment = StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'matriculada',
            'snapshot_year_name' => (string) $year,
            'snapshot_level_name' => $level->name,
            'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => $courseName,
        ]);

        return [$student, $enrollment];
    }

    private function createStaff(string $name): Staff
    {
        return $this->createStaffWithCargo($name, 'Docente asesora', 'docente-asesora');
    }

    private function createStaffWithCargo(
        string $name,
        string $cargoName,
        string $cargoSlug,
        bool $active = true,
    ): Staff {
        $cargo = Cargo::query()->firstOrCreate(
            ['slug' => $cargoSlug],
            ['name' => $cargoName, 'active' => true],
        );

        return Staff::query()->create([
            'full_name' => $name,
            'rut' => (string) random_int(10000000, 99999999).'-'.random_int(0, 9),
            'cargo_id' => $cargo->id,
            'status' => $active ? 'activo' : 'inactivo',
            'active' => $active,
        ]);
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol organizaciones '.Str::random(8),
            'slug' => 'rol_organizaciones_'.Str::lower(Str::random(10)),
            'active' => true,
        ]);
        $permissions = collect($permissionSlugs)->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline($slug), 'active' => true],
        ));
        $role->permissions()->sync($permissions->pluck('id'));
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $user->roles()->attach($role);

        return $user;
    }
}
