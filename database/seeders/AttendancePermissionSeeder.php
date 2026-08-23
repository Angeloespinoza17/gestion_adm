<?php

namespace Database\Seeders;

use App\Models\Attendance\AttendanceAbsenceReason;
use App\Models\Attendance\AttendanceAlertRule;
use App\Models\Attendance\AttendanceInterventionType;
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
        $managementModule = null;
        if ($studentsModule) {
            $managementModule = SystemModule::query()->updateOrCreate(
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
            SystemModule::query()->updateOrCreate(
                ['slug' => 'students_attendance_management'],
                [
                    'name' => 'Gestión de ausencia',
                    'frontend_route' => '/students/attendance-management',
                    'icon' => null,
                    'sort_order' => 9,
                    'active' => true,
                    'parent_id' => $studentsModule->id,
                ],
            );
        }

        Role::query()
            ->whereIn('slug', ['super_admin', 'administrador'])
            ->get()
            ->each(function (Role $role) use ($permissions, $managementModule): void {
                $role->permissions()->syncWithoutDetaching($permissions->pluck('id'));
                if ($managementModule) {
                    $role->modules()->syncWithoutDetaching([$managementModule->id]);
                }
            });

        if ($managementModule) {
            Role::query()->whereHas('permissions', fn ($query) => $query->whereIn('slug', [
                'attendance_management.view', 'attendance_statistics.view', 'attendance_statistics.view_student', 'attendance_statistics.view_course',
            ]))->get()->each(fn (Role $role) => $role->modules()->syncWithoutDetaching([$managementModule->id]));
        }

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
            ['slug' => 'importar_calificaciones', 'name' => 'Importar calificaciones', 'description' => 'Permite importar calificaciones anuales, conciliar estudiantes y reintentar registros pendientes.'],
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
            ['slug' => 'attendance_management.view', 'name' => 'Ver gestión de ausencia', 'description' => 'Permite acceder al dashboard preventivo y a los estudiantes de cursos autorizados.'],
            ['slug' => 'attendance_management.view_all', 'name' => 'Ver gestión institucional de ausencia', 'description' => 'Permite consultar todos los cursos y expedientes institucionales.'],
            ['slug' => 'attendance_management.manage_cases', 'name' => 'Gestionar expedientes de asistencia', 'description' => 'Permite abrir, actualizar, cerrar y reabrir expedientes con trazabilidad.'],
            ['slug' => 'attendance_management.manage_interventions', 'name' => 'Gestionar intervenciones de ausencia', 'description' => 'Permite registrar contactos e intervenciones de apoyo.'],
            ['slug' => 'attendance_management.manage_causes', 'name' => 'Gestionar causas de ausencia', 'description' => 'Permite identificar causas y mantener su catálogo.'],
            ['slug' => 'attendance_management.manage_action_plans', 'name' => 'Gestionar planes de acción de asistencia', 'description' => 'Permite crear, ejecutar y evaluar planes de acompañamiento.'],
            ['slug' => 'attendance_management.export', 'name' => 'Exportar gestión de ausencia', 'description' => 'Permite generar reportes institucionales, de curso, individuales y familiares.'],
            ['slug' => 'attendance_management.view_sensitive', 'name' => 'Ver información sensible de ausencia', 'description' => 'Permite consultar observaciones confidenciales y causas sensibles autorizadas.'],
            ['slug' => 'attendance_management.configure', 'name' => 'Configurar gestión de ausencia', 'description' => 'Permite administrar umbrales, ponderaciones, alertas y catálogos.'],
        ];
    }

    private function seedStatisticsConfiguration(): void
    {
        if (! Schema::hasTable('attendance_absence_reasons')) {
            return;
        }

        $reasons = [
            ['illness', 'Enfermedad', 'salud', false], ['medical_care', 'Atención médica', 'salud', false],
            ['mental_health', 'Salud mental', 'salud', true], ['family_problem', 'Problema familiar', 'familia', true],
            ['sleep_routine', 'Sueño o dificultades de rutina', 'bienestar', false], ['academic_difficulty', 'Dificultad académica', 'educativa', false],
            ['transport', 'Transporte', 'logística', false], ['weather', 'Condición climática', 'entorno', false],
            ['economic_difficulty', 'Dificultad económica', 'socioeconómica', true], ['motivation', 'Desmotivación', 'educativa', false],
            ['school_climate', 'Convivencia escolar', 'convivencia', true], ['bullying', 'Acoso escolar', 'convivencia', true],
            ['care_responsibilities', 'Responsabilidades de cuidado', 'familia', true], ['family_health', 'Salud de un familiar', 'familia', true],
            ['judicial_situation', 'Situación judicial', 'protección', true], ['address_change', 'Cambio de domicilio', 'familia', false],
            ['travel', 'Viaje', 'personal', false], ['procedure', 'Trámite', 'personal', false],
            ['suspension', 'Suspensión', 'institucional', false], ['institutional_activity', 'Actividad institucional', 'institucional', false],
            ['registration_error', 'Error de registro', 'datos', false], ['no_family_contact', 'Sin contacto familiar', 'gestión', true],
            ['unknown', 'Sin información', 'sin_información', false], ['other', 'Otro', 'otro', false],
        ];
        foreach ($reasons as $index => [$code, $name, $category, $sensitive]) {
            AttendanceAbsenceReason::query()->updateOrCreate(['code' => $code], ['name' => $name, 'category' => $category, 'is_sensitive' => $sensitive, 'active' => true, 'sort_order' => $index + 1]);
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

        if (Schema::hasTable('attendance_intervention_types')) {
            $types = [
                ['phone_contact', 'Llamada telefónica', 'contacto_familiar', true, false],
                ['whatsapp_contact', 'Contacto por WhatsApp', 'contacto_familiar', true, false],
                ['family_interview', 'Entrevista con familia', 'contacto_familiar', true, true],
                ['student_interview', 'Entrevista con estudiante', 'acompañamiento', false, true],
                ['teacher_coordination', 'Coordinación con profesor/a jefe', 'coordinación', false, false],
                ['inspectoria_coordination', 'Coordinación con inspectoría', 'coordinación', false, false],
                ['social_work_referral', 'Derivación a trabajo social', 'derivación', false, true],
                ['psychology_referral', 'Derivación a psicología', 'derivación', false, true],
                ['home_visit', 'Visita domiciliaria', 'acompañamiento', true, true],
                ['monitoring', 'Monitoreo de asistencia', 'seguimiento', false, false],
                ['recognition', 'Reconocimiento de mejora', 'refuerzo_positivo', false, false],
            ];
            foreach ($types as $index => [$code, $name, $category, $familyContact, $sensitive]) {
                AttendanceInterventionType::query()->updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'category' => $category, 'family_contact' => $familyContact, 'sensitive' => $sensitive, 'active' => true, 'sort_order' => $index + 1],
                );
            }
        }
    }
}
