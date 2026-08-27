<?php

namespace Tests\Feature\Rbac;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicCoordinatorRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED_MODULES = [
        'dashboard',
        'students',
        'students_directory',
        'students_promotions',
        'students_movements',
        'students_reports',
        'students_attendance_statistics',
        'students_attendance_management',
        'students_grade_statistics',
        'operational_management',
        'operational_transfers_requests',
        'operational_transfers_review',
        'operational_transfers_management',
        'operational_transfers_reports',
        'hr_absence_management',
        'hr_recruitment_management',
        'inspectoria',
        'inspectoria_bitacora',
    ];

    private const EXCLUDED_MODULES = [
        'students_levels',
        'students_academic_years',
        'students_courses',
    ];

    private const REQUIRED_PERMISSIONS = [
        'ver_dashboard',
        'ver_estudiantes',
        'crear_estudiantes',
        'editar_estudiantes',
        'eliminar_estudiantes',
        'ver_ficha_estudiante',
        'gestionar_matriculas_estudiantes',
        'promover_estudiantes',
        'grade_statistics.view',
        'grade_statistics.view_students',
        'ver_asistencia',
        'importar_asistencia',
        'importar_calificaciones',
        'editar_asistencia',
        'gestionar_alertas_asistencia',
        'proyectar_ingresos_asistencia',
        'attendance_statistics.view',
        'attendance_statistics.view_global',
        'attendance_statistics.view_course',
        'attendance_statistics.view_student',
        'attendance_statistics.view_financial',
        'attendance_statistics.view_sensitive_segments',
        'attendance_statistics.export',
        'attendance_statistics.configure',
        'attendance_statistics.manage_goals',
        'attendance_statistics.manage_alerts',
        'attendance_statistics.manage_interventions',
        'attendance_statistics.manage_reports',
        'attendance_statistics.view_audit',
        'attendance_management.view',
        'attendance_management.view_all',
        'attendance_management.manage_cases',
        'attendance_management.manage_interventions',
        'attendance_management.manage_causes',
        'attendance_management.manage_action_plans',
        'attendance_management.export',
        'attendance_management.view_sensitive',
        'attendance_management.configure',
        'ver_traslados_operativos',
        'solicitar_traslados_operativos',
        'visar_traslados_operativos',
        'gestionar_traslados_operativos',
        'exportar_traslados_operativos',
        'rrhh.ausencias.ver',
        'rrhh.seleccion.ver',
        'ver_bitacora_inspectoria',
    ];

    private const EXCLUDED_PERMISSIONS = [
        'ver_configuracion_base_estudiantes',
        'administrar_anos_academicos',
        'administrar_cursos_academicos',
        'ver_modulo_inspectoria',
        'registrar_bitacora_inspectoria',
        'rrhh.ausencias.gestionar',
        'rrhh.seleccion.gestionar',
        'rrhh.psicolaborales.confidencial',
    ];

    public function test_role_has_the_requested_modules_without_student_base_configuration(): void
    {
        $role = $this->role();
        $moduleSlugs = $role->modules->pluck('slug')->all();

        $this->assertSame('Coordinadora Académica', $role->name);
        $this->assertTrue($role->active);
        $missingModules = array_values(array_diff(self::REQUIRED_MODULES, $moduleSlugs));
        $this->assertEmpty($missingModules, 'Módulos faltantes: '.implode(', ', $missingModules));
        $this->assertEmpty(array_intersect(self::EXCLUDED_MODULES, $moduleSlugs));
    }

    public function test_role_has_full_student_workflow_and_read_only_inspectoria_daily_log(): void
    {
        $permissionSlugs = $this->role()->permissions->pluck('slug')->all();

        $missingPermissions = array_values(array_diff(self::REQUIRED_PERMISSIONS, $permissionSlugs));
        $this->assertEmpty($missingPermissions, 'Permisos faltantes: '.implode(', ', $missingPermissions));
        $this->assertEmpty(array_intersect(self::EXCLUDED_PERMISSIONS, $permissionSlugs));
    }

    public function test_student_base_configuration_is_forbidden_even_through_direct_api_urls(): void
    {
        $this->actingAsCoordinator();

        $this->getJson('/api/students?per_page=10')->assertOk();
        $this->getJson('/api/students/levels')->assertForbidden();
        $this->getJson('/api/students/academic-years')->assertForbidden();
        $this->getJson('/api/students/courses')->assertForbidden();
    }

    public function test_navigation_catalog_only_exposes_the_requested_student_modules(): void
    {
        $this->actingAsCoordinator();

        $moduleSlugs = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))
            ->pluck('slug')
            ->all();

        $missingModules = array_values(array_diff(self::REQUIRED_MODULES, $moduleSlugs));
        $this->assertEmpty($missingModules, 'Módulos de navegación faltantes: '.implode(', ', $missingModules));
        $this->assertEmpty(array_intersect(self::EXCLUDED_MODULES, $moduleSlugs));
    }

    public function test_inspectoria_access_is_limited_to_reading_the_daily_log(): void
    {
        $this->actingAsCoordinator();

        $this->getJson('/api/inspectoria/catalogs')->assertOk();
        $this->getJson('/api/inspectoria/daily-log')->assertOk();
        $this->getJson('/api/inspectoria/attentions')->assertForbidden();
        $this->postJson('/api/inspectoria/daily-log', [])->assertForbidden();
    }

    public function test_migration_is_idempotent(): void
    {
        $role = $this->role();
        $permissionCount = $role->permissions()->count();
        $moduleCount = $role->modules()->count();

        $migration = require database_path('migrations/2026_08_26_090000_create_academic_coordinator_role.php');
        $migration->up();

        $role->refresh();

        $this->assertSame(1, Role::query()->where('slug', 'coordinadora_academica')->count());
        $this->assertSame($permissionCount, $role->permissions()->count());
        $this->assertSame($moduleCount, $role->modules()->count());
    }

    public function test_rbac_reconciliation_preserves_the_profile_boundary(): void
    {
        $this->seed(RbacSeeder::class);

        $role = $this->role();
        $moduleSlugs = $role->modules->pluck('slug')->all();
        $permissionSlugs = $role->permissions->pluck('slug')->all();

        $this->assertEmpty(array_diff(self::REQUIRED_MODULES, $moduleSlugs));
        $this->assertEmpty(array_intersect(self::EXCLUDED_MODULES, $moduleSlugs));
        $this->assertEmpty(array_diff(self::REQUIRED_PERMISSIONS, $permissionSlugs));
        $this->assertEmpty(array_intersect(self::EXCLUDED_PERMISSIONS, $permissionSlugs));
    }

    private function role(): Role
    {
        return Role::query()
            ->with(['permissions:id,slug', 'modules:id,slug'])
            ->where('slug', 'coordinadora_academica')
            ->firstOrFail();
    }

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach($this->role());
        Sanctum::actingAs($user);

        return $user;
    }
}
