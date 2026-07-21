<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceAbsenceReason;
use App\Models\Attendance\AttendanceAlertRule;
use App\Models\Attendance\AttendanceDataQualityIssue;
use App\Models\Attendance\AttendanceFinancialParameter;
use App\Models\Attendance\AttendanceGoal;
use App\Models\Attendance\AttendanceIntervention;
use App\Models\Attendance\AttendanceProjectionSetting;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\AttendanceRiskLevel;
use App\Models\Attendance\SchoolDay;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Attendance\AttendanceAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AttendanceStatisticsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('AttendanceStatisticsSeeder no se ejecuta en producción.');
        }

        $this->call(AttendancePermissionSeeder::class);
        $user = User::query()->firstOrCreate(
            ['email' => 'demo.asistencia@cnsc.test'],
            ['name' => 'Demo Asistencia', 'password' => Hash::make('demo-asistencia'), 'active' => true, 'user_type' => 'staff'],
        );
        $this->seedConfiguration($user->id);
        foreach ([2097, 2098] as $yearNumber) {
            $this->seedYear($yearNumber, $user);
        }
    }

    private function seedConfiguration(int $userId): void
    {
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
            AttendanceAbsenceReason::query()->updateOrCreate(['code' => $code], ['name' => $name, 'category' => $category, 'active' => true, 'sort_order' => $index + 1, 'created_by' => $userId, 'updated_by' => $userId]);
        }
        $levels = [
            ['high', 'Riesgo alto', 0, 84.99, '#dc3545', 'bx-error', 4, 2],
            ['moderate', 'Riesgo moderado', 85, 89.99, '#d97706', 'bx-error-circle', 3, 4],
            ['low', 'Riesgo leve', 90, 94.99, '#2563eb', 'bx-info-circle', 2, 7],
            ['none', 'Sin riesgo', 95, 100, '#198754', 'bx-check-shield', 1, null],
        ];
        foreach ($levels as [$slug, $name, $min, $max, $color, $icon, $priority, $due]) {
            AttendanceRiskLevel::query()->updateOrCreate(
                ['academic_year_id' => null, 'slug' => $slug],
                ['name' => $name, 'minimum_rate' => $min, 'maximum_rate' => $max, 'color' => $color, 'icon' => $icon, 'priority' => $priority, 'intervention_due_days' => $due, 'active' => true, 'created_by' => $userId, 'updated_by' => $userId],
            );
        }
        $rules = [
            ['two_consecutive_absences', 'Dos ausencias consecutivas', 'consecutive_absences', 'gte', 2, 'critical'],
            ['monthly_absences', 'Tres ausencias en un mes', 'monthly_absences', 'gte', 3, 'warning'],
            ['attendance_below_95', 'Asistencia bajo 95%', 'attendance_rate', 'lt', 95, 'warning'],
            ['attendance_below_85', 'Asistencia bajo 85%', 'attendance_rate', 'lt', 85, 'critical'],
            ['monthly_drop', 'Descenso superior a 5 puntos', 'period_drop', 'gt', 5, 'warning'],
            ['frequent_lateness', 'Más de cinco atrasos', 'late_count', 'gt', 5, 'warning'],
            ['missing_records', 'Estudiante sin registros', 'record_count', 'eq', 0, 'critical'],
        ];
        foreach ($rules as [$code, $name, $metric, $operator, $threshold, $severity]) {
            AttendanceAlertRule::query()->updateOrCreate(
                ['academic_year_id' => null, 'code' => $code],
                ['name' => $name, 'metric' => $metric, 'operator' => $operator, 'threshold' => $threshold, 'severity' => $severity, 'evaluation_period' => 'academic_year', 'cooldown_days' => 7, 'response_due_days' => 5, 'active' => true, 'created_by' => $userId, 'updated_by' => $userId],
            );
        }
    }

    private function seedYear(int $yearNumber, User $user): void
    {
        $year = AcademicYear::query()->updateOrCreate(
            ['year' => $yearNumber],
            ['name' => "Demo asistencia {$yearNumber}", 'starts_at' => "{$yearNumber}-03-01", 'ends_at' => "{$yearNumber}-12-20", 'is_active' => false, 'is_closed' => $yearNumber === 2097, 'created_by' => $user->id, 'updated_by' => $user->id],
        );
        $levels = collect([
            ['name' => 'Demo 1° Básico', 'order' => 901, 'type' => 'basica'],
            ['name' => 'Demo 7° Básico', 'order' => 907, 'type' => 'basica'],
            ['name' => 'Demo 1° Medio', 'order' => 911, 'type' => 'media'],
        ])->map(fn ($data) => EducationLevel::query()->firstOrCreate(['name' => $data['name']], $data));
        $dates = collect();
        for ($date = CarbonImmutable::create($yearNumber, 3, 1); $date->lte(CarbonImmutable::create($yearNumber, 5, 15)); $date = $date->addDay()) {
            if ($date->isWeekday()) {
                $dates->push($date);
                SchoolDay::query()->firstOrCreate(
                    ['academic_year_id' => $year->id, 'date' => $date->toDateString()],
                    ['is_school_day' => true, 'status' => $date->day === 14 ? 'pending_confirmation' : 'confirmed', 'source' => 'demo', 'created_by' => $user->id],
                );
            }
        }
        foreach ($levels as $levelIndex => $level) {
            foreach (['A', 'B'] as $sectionIndex => $section) {
                $course = CourseSection::query()->firstOrCreate(
                    ['academic_year_id' => $year->id, 'education_level_id' => $level->id, 'section_name' => $section],
                    ['display_name' => $level->name.' '.$section, 'capacity' => 30, 'active' => true, 'created_by' => $user->id, 'updated_by' => $user->id],
                );
                for ($studentIndex = 1; $studentIndex <= 18; $studentIndex++) {
                    $student = StudentProfile::query()->firstOrCreate(
                        ['rut' => $yearNumber.$levelIndex.$sectionIndex.str_pad((string) $studentIndex, 3, '0', STR_PAD_LEFT).'-D'],
                        ['first_name' => 'Estudiante '.$studentIndex, 'last_name' => 'Demo '.$level->name.' '.$section, 'registered_name' => null, 'birthdate' => CarbonImmutable::create($yearNumber - 7 - $levelIndex * 4, 6, min(28, $studentIndex)), 'gender' => $studentIndex % 2 ? 'Femenino' : 'Masculino', 'commune' => $studentIndex % 3 ? 'Santiago' : 'Puente Alto', 'general_status' => 'activo', 'is_pie_participant' => $studentIndex % 5 === 0, 'created_by' => $user->id, 'updated_by' => $user->id],
                    );
                    $enrollment = StudentEnrollment::query()->firstOrCreate(
                        ['student_profile_id' => $student->id, 'academic_year_id' => $year->id],
                        ['course_section_id' => $course->id, 'enrollment_status' => 'regular', 'enrolled_at' => "{$yearNumber}-03-01", 'snapshot_year_name' => $year->name, 'snapshot_level_name' => $level->name, 'snapshot_section_name' => $section, 'snapshot_course_display_name' => $course->display_name, 'created_by' => $user->id, 'updated_by' => $user->id],
                    );
                    foreach ($dates as $dateIndex => $date) {
                        $absenceModulo = match (true) {
                            $studentIndex <= 5 => 35, $studentIndex <= 10 => 16, $studentIndex <= 14 => 9, default => 5
                        };
                        $descendingPenalty = $studentIndex >= 15 && $dateIndex > 25 ? 2 : 0;
                        $absent = (($dateIndex + $studentIndex + $descendingPenalty) % $absenceModulo) === 0;
                        if ($studentIndex === 18 && in_array($dateIndex, [20, 21, 22, 23], true)) {
                            $absent = true;
                        }
                        $day = SchoolDay::query()->where('academic_year_id', $year->id)->where('date', $date->toDateString())->first();
                        AttendanceRecord::query()->updateOrCreate(
                            ['course_section_id' => $course->id, 'student_profile_id' => $student->id, 'attendance_date' => $date->toDateString()],
                            ['school_day_id' => $day->id, 'academic_year_id' => $year->id, 'student_enrollment_id' => $enrollment->id, 'status' => $absent ? 'absent' : 'present', 'is_justified' => $absent && $studentIndex % 3 === 0, 'absence_reason_id' => $absent ? AttendanceAbsenceReason::query()->where('code', $studentIndex % 3 === 0 ? 'illness' : 'unknown')->value('id') : null, 'minutes_late' => ! $absent && $studentIndex % 6 === 0 && $dateIndex % 7 === 0 ? 12 : 0, 'early_departure' => ! $absent && $studentIndex % 8 === 0 && $dateIndex % 11 === 0, 'origin' => 'demo', 'created_by' => $user->id, 'updated_by' => $user->id],
                        );
                    }
                }
            }
        }
        AttendanceGoal::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'name' => 'Meta institucional demo'],
            ['scope_type' => 'institution', 'starts_on' => $year->starts_at, 'ends_on' => $year->ends_at, 'target_rate' => 92, 'status' => 'active', 'justification' => 'Meta de demostración del módulo.', 'responsible_user_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id],
        );
        AttendanceProjectionSetting::query()->updateOrCreate(['academic_year_id' => $year->id], ['monthly_unit_value' => 1800, 'attendance_factor' => 1, 'target_attendance_rate' => 92, 'annual_school_days' => 190, 'currency' => 'CLP', 'updated_by' => $user->id]);
        AttendanceFinancialParameter::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'name' => 'Parámetro general demo'],
            ['subsidy_type' => 'general', 'unit_value' => 1800, 'attendance_factor' => 1, 'currency' => 'CLP', 'valid_from' => $year->starts_at, 'source_reference' => 'Dato ficticio para pruebas', 'assumptions' => 'No corresponde a un valor oficial.', 'active' => true, 'created_by' => $user->id, 'updated_by' => $user->id],
        );
        app(AttendanceAlertService::class)->rebuild($year->id);
        $alert = DB::table('attendance_alerts')->where('academic_year_id', $year->id)->whereNotNull('student_profile_id')->first();
        if ($alert) {
            AttendanceIntervention::query()->firstOrCreate(
                ['attendance_alert_id' => $alert->id],
                ['folio' => 'ASI-'.$yearNumber.'-000001', 'academic_year_id' => $year->id, 'course_section_id' => $alert->course_section_id, 'student_profile_id' => $alert->student_profile_id, 'responsible_user_id' => $user->id, 'status' => 'family_contact', 'probable_cause' => 'Sin información', 'description' => 'Caso de demostración para seguimiento de asistencia.', 'opened_at' => now(), 'due_on' => now()->addDays(5), 'created_by' => $user->id, 'updated_by' => $user->id],
            );
        }
        AttendanceDataQualityIssue::query()->updateOrCreate(
            ['fingerprint' => hash('sha256', 'demo-quality-'.$year->id)],
            ['academic_year_id' => $year->id, 'type' => 'demo_pending_review', 'severity' => 'warning', 'status' => 'open', 'title' => 'Incidencia de demostración', 'description' => 'Ejemplo de una incidencia de calidad pendiente.', 'suggested_action' => 'Revisar el origen antes de corregir.', 'detected_at' => now()],
        );
        DB::table('attendance_scheduled_reports')->updateOrInsert(
            ['owner_user_id' => $user->id, 'name' => 'Resumen semanal demo '.$yearNumber],
            ['academic_year_id' => $year->id, 'report_type' => 'executive', 'format' => 'pdf', 'frequency' => 'weekly', 'run_at' => '07:00', 'filters' => json_encode(['academic_year_id' => $year->id]), 'recipients' => json_encode([$user->email]), 'active' => true, 'next_run_at' => now()->addWeek(), 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
