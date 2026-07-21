<?php

namespace Database\Seeders;

use App\Models\Attendance\AttendanceAbsenceReason;
use App\Models\Attendance\AttendanceAlertRule;
use App\Models\Attendance\AttendanceRiskLevel;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AttendancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = self::definitions();

        $permissions = collect($definitions)->map(fn (array $definition) => Permission::query()->updateOrCreate(
            ['slug' => $definition['slug']],
            [...$definition, 'active' => true],
        ));

        $group = PermissionGroup::query()->where('slug', 'estudiantes')->first();
        $group?->permissions()->syncWithoutDetaching($permissions->pluck('id'));

        $studentsModule = SystemModule::query()->where('slug', 'students')->first();
        if ($studentsModule) {
            SystemModule::query()->updateOrCreate(
                ['slug' => 'students_attendance_statistics'],
                [
                    'name' => 'Estadísticas de asistencia',
                    'frontend_route' => '/students/attendance-statistics',
                    'icon' => null,
                    'sort_order' => 8,
                    'active' => true,
                    'parent_id' => $studentsModule->id,
                ],
            );
        }

        Role::query()
            ->whereIn('slug', ['super_admin', 'administrador'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissions->pluck('id')));

        $this->seedStatisticsConfiguration();
    }

    /**
     * @return array<int, array{slug:string,name:string,description:string}>
     */
    public static function definitions(): array
    {
        return [
            ['slug' => 'ver_asistencia', 'name' => 'Ver asistencia', 'description' => 'Permite consultar estadísticas, calendarios y detalle de asistencia.'],
            ['slug' => 'importar_asistencia', 'name' => 'Importar asistencia', 'description' => 'Permite previsualizar y confirmar importaciones mensuales de asistencia.'],
            ['slug' => 'editar_asistencia', 'name' => 'Editar asistencia', 'description' => 'Permite corregir registros de asistencia con trazabilidad.'],
            ['slug' => 'gestionar_alertas_asistencia', 'name' => 'Gestionar alertas de asistencia', 'description' => 'Permite reconocer, asignar, resolver y registrar seguimientos.'],
            ['slug' => 'proyectar_ingresos_asistencia', 'name' => 'Proyectar ingresos por asistencia', 'description' => 'Permite consultar y configurar escenarios financieros de asistencia.'],
            ['slug' => 'attendance_statistics.view', 'name' => 'Ver estadísticas avanzadas de asistencia', 'description' => 'Permite acceder al dashboard y análisis agregados.'],
            ['slug' => 'attendance_statistics.view_global', 'name' => 'Ver asistencia institucional', 'description' => 'Permite analizar todos los niveles y cursos.'],
            ['slug' => 'attendance_statistics.view_course', 'name' => 'Ver asistencia por curso', 'description' => 'Permite consultar dashboards y matrices por curso.'],
            ['slug' => 'attendance_statistics.view_student', 'name' => 'Ver asistencia individual', 'description' => 'Permite consultar fichas nominales de asistencia.'],
            ['slug' => 'attendance_statistics.view_financial', 'name' => 'Ver impacto financiero de asistencia', 'description' => 'Permite consultar estimaciones financieras.'],
            ['slug' => 'attendance_statistics.view_sensitive_segments', 'name' => 'Ver segmentos sensibles de asistencia', 'description' => 'Permite segmentar por atributos personales autorizados.'],
            ['slug' => 'attendance_statistics.export', 'name' => 'Exportar estadísticas de asistencia', 'description' => 'Permite generar PDF, Excel y CSV.'],
            ['slug' => 'attendance_statistics.configure', 'name' => 'Configurar estadísticas de asistencia', 'description' => 'Permite gestionar reglas, riesgos, motivos y parámetros.'],
            ['slug' => 'attendance_statistics.manage_goals', 'name' => 'Gestionar metas de asistencia', 'description' => 'Permite crear y mantener metas institucionales e individuales.'],
            ['slug' => 'attendance_statistics.manage_alerts', 'name' => 'Gestionar alertas avanzadas de asistencia', 'description' => 'Permite asignar y resolver alertas.'],
            ['slug' => 'attendance_statistics.manage_interventions', 'name' => 'Gestionar intervenciones de asistencia', 'description' => 'Permite crear y cerrar intervenciones.'],
            ['slug' => 'attendance_statistics.manage_reports', 'name' => 'Gestionar reportes de asistencia', 'description' => 'Permite programar y administrar reportes.'],
            ['slug' => 'attendance_statistics.view_audit', 'name' => 'Ver auditoría de asistencia', 'description' => 'Permite consultar trazabilidad y accesos sensibles.'],
        ];
    }

    private function seedStatisticsConfiguration(): void
    {
        if (! Schema::hasTable('attendance_absence_reasons')) {
            return;
        }

        $reasons = [
            ['illness', 'Enfermedad', 'salud'], ['medical_care', 'Atención médica', 'salud'],
            ['mental_health', 'Salud mental', 'salud'], ['family_problem', 'Problema familiar', 'familia'],
            ['transport', 'Transporte', 'logística'], ['weather', 'Condición climática', 'entorno'],
            ['economic_difficulty', 'Dificultad económica', 'socioeconómica'], ['motivation', 'Desmotivación', 'educativa'],
            ['school_climate', 'Convivencia escolar', 'convivencia'], ['bullying', 'Bullying', 'convivencia'],
            ['care_responsibilities', 'Responsabilidades de cuidado', 'familia'], ['travel', 'Viaje', 'personal'],
            ['procedure', 'Trámite', 'personal'], ['suspension', 'Suspensión', 'institucional'],
            ['institutional_activity', 'Actividad institucional', 'institucional'], ['registration_error', 'Error de registro', 'datos'],
            ['unknown', 'Sin información', 'sin_información'], ['other', 'Otro', 'otro'],
        ];
        foreach ($reasons as $index => [$code, $name, $category]) {
            AttendanceAbsenceReason::query()->updateOrCreate(['code' => $code], ['name' => $name, 'category' => $category, 'active' => true, 'sort_order' => $index + 1]);
        }

        $levels = [
            ['high', 'Riesgo alto', 0, 84.99, '#dc3545', 'bx-error', 4, 2],
            ['moderate', 'Riesgo moderado', 85, 89.99, '#d97706', 'bx-error-circle', 3, 4],
            ['low', 'Riesgo leve', 90, 94.99, '#2563eb', 'bx-info-circle', 2, 7],
            ['none', 'Sin riesgo', 95, 100, '#198754', 'bx-check-shield', 1, null],
        ];
        foreach ($levels as [$slug, $name, $minimum, $maximum, $color, $icon, $priority, $dueDays]) {
            AttendanceRiskLevel::query()->updateOrCreate(
                ['academic_year_id' => null, 'slug' => $slug],
                ['name' => $name, 'minimum_rate' => $minimum, 'maximum_rate' => $maximum, 'color' => $color, 'icon' => $icon, 'priority' => $priority, 'intervention_due_days' => $dueDays, 'active' => true],
            );
        }

        $rules = [
            ['two_consecutive_absences', 'Dos ausencias consecutivas', 'consecutive_absences', 'gte', 2, 'critical'],
            ['monthly_absences', 'Tres ausencias en un mes', 'monthly_absences', 'gte', 3, 'warning'],
            ['attendance_below_95', 'Asistencia bajo 95%', 'attendance_rate', 'lt', 95, 'warning'],
            ['attendance_below_85', 'Asistencia bajo 85%', 'attendance_rate', 'lt', 85, 'critical'],
            ['monthly_drop', 'Descenso superior a 5 puntos', 'period_drop', 'gt', 5, 'warning'],
            ['frequent_lateness', 'Más de cinco atrasos', 'late_count', 'gt', 5, 'warning'],
        ];
        foreach ($rules as [$code, $name, $metric, $operator, $threshold, $severity]) {
            AttendanceAlertRule::query()->updateOrCreate(
                ['academic_year_id' => null, 'code' => $code],
                ['name' => $name, 'metric' => $metric, 'operator' => $operator, 'threshold' => $threshold, 'severity' => $severity, 'evaluation_period' => 'academic_year', 'cooldown_days' => 7, 'response_due_days' => 5, 'active' => true],
            );
        }
    }
}
