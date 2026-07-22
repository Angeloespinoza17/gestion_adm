<?php

namespace App\Services\Students;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentDeletionService
{
    /**
     * Relations that are deleted by their foreign key when the profile is deleted.
     *
     * @var array<int, array{table: string, column: string, label: string}>
     */
    private const CASCADE_RELATIONS = [
        ['table' => 'apoyo_atenciones', 'column' => 'student_profile_id', 'label' => 'Atenciones de apoyo profesional'],
        ['table' => 'apoyo_derivaciones', 'column' => 'student_profile_id', 'label' => 'Derivaciones de apoyo profesional'],
        ['table' => 'apoyo_planes', 'column' => 'student_profile_id', 'label' => 'Planes de apoyo profesional'],
        ['table' => 'apoyo_seguimientos', 'column' => 'student_profile_id', 'label' => 'Seguimientos de apoyo profesional'],
        ['table' => 'attendance_goals', 'column' => 'student_profile_id', 'label' => 'Metas de asistencia'],
        ['table' => 'infirmary_attentions', 'column' => 'student_profile_id', 'label' => 'Atenciones de enfermería'],
        ['table' => 'infirmary_attention_calls', 'column' => 'student_profile_id', 'label' => 'Llamados de enfermería'],
        ['table' => 'infirmary_medication_administrations', 'column' => 'student_profile_id', 'label' => 'Administraciones de medicamentos'],
        ['table' => 'infirmary_medication_authorizations', 'column' => 'student_profile_id', 'label' => 'Autorizaciones de medicamentos'],
        ['table' => 'pme_estudiantes_sep', 'column' => 'student_profile_id', 'label' => 'Clasificaciones PME/SEP'],
    ];

    /**
     * Relations that must be removed explicitly before the student profile.
     * The order prevents restrictions between enrollments and movements.
     *
     * @var array<int, array{table: string, column: string, label: string}>
     */
    private const RESTRICT_RELATIONS = [
        ['table' => 'attendance_interventions', 'column' => 'student_profile_id', 'label' => 'Intervenciones de asistencia'],
        ['table' => 'attendance_records', 'column' => 'student_profile_id', 'label' => 'Registros de asistencia'],
        ['table' => 'porter_student_withdrawals', 'column' => 'student_profile_id', 'label' => 'Retiros de portería'],
        ['table' => 'student_enrollment_movements', 'column' => 'student_profile_id', 'label' => 'Movimientos de matrícula'],
        ['table' => 'student_promotions', 'column' => 'student_profile_id', 'label' => 'Promociones académicas'],
        ['table' => 'student_enrollments', 'column' => 'student_profile_id', 'label' => 'Matrículas'],
    ];

    /**
     * Historical relations that remain in the system with a null student reference.
     *
     * @var array<int, array{table: string, column: string, label: string}>
     */
    private const PRESERVED_RELATIONS = [
        ['table' => 'apoyo_adjuntos', 'column' => 'student_profile_id', 'label' => 'Adjuntos de apoyo profesional'],
        ['table' => 'apoyo_entrevistas', 'column' => 'student_profile_id', 'label' => 'Entrevistas de apoyo profesional'],
        ['table' => 'attendance_alerts', 'column' => 'student_profile_id', 'label' => 'Alertas de asistencia'],
        ['table' => 'attendance_data_quality_issues', 'column' => 'student_profile_id', 'label' => 'Incidencias de calidad de asistencia'],
        ['table' => 'biblioteca_prestamos', 'column' => 'student_profile_id', 'label' => 'Préstamos de biblioteca'],
        ['table' => 'biblioteca_reservas', 'column' => 'student_profile_id', 'label' => 'Reservas de biblioteca'],
        ['table' => 'convivencia_attachments', 'column' => 'student_profile_id', 'label' => 'Adjuntos de convivencia'],
        ['table' => 'convivencia_cases', 'column' => 'student_profile_id', 'label' => 'Casos de convivencia'],
        ['table' => 'convivencia_case_people', 'column' => 'student_profile_id', 'label' => 'Participantes de casos de convivencia'],
        ['table' => 'convivencia_complaints', 'column' => 'affected_student_id', 'label' => 'Denuncias de convivencia'],
        ['table' => 'convivencia_daily_logs', 'column' => 'student_profile_id', 'label' => 'Bitácoras de convivencia'],
        ['table' => 'convivencia_derivations', 'column' => 'student_profile_id', 'label' => 'Derivaciones de convivencia'],
        ['table' => 'convivencia_interviews', 'column' => 'student_profile_id', 'label' => 'Entrevistas de convivencia'],
        ['table' => 'convivencia_interview_participants', 'column' => 'student_profile_id', 'label' => 'Participantes de entrevistas'],
        ['table' => 'convivencia_measures', 'column' => 'student_profile_id', 'label' => 'Medidas de convivencia'],
        ['table' => 'convivencia_sociogram_answers', 'column' => 'respondent_student_id', 'label' => 'Respuestas de sociograma'],
        ['table' => 'convivencia_sociogram_answers', 'column' => 'selected_student_id', 'label' => 'Selecciones de sociograma'],
        ['table' => 'infirmary_accidents', 'column' => 'student_profile_id', 'label' => 'Accidentes de enfermería'],
        ['table' => 'infirmary_documents', 'column' => 'student_profile_id', 'label' => 'Documentos de enfermería'],
        ['table' => 'infirmary_medications', 'column' => 'student_profile_id', 'label' => 'Medicamentos de enfermería'],
        ['table' => 'it_equipment_loans', 'column' => 'requester_student_profile_id', 'label' => 'Préstamos de informática'],
        ['table' => 'porter_received_items', 'column' => 'student_profile_id', 'label' => 'Objetos recibidos en portería'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function impact(StudentProfile $student): array
    {
        $account = $student->user()->first();
        $willDelete = $this->relationCounts($student, [
            ...self::CASCADE_RELATIONS,
            ...self::RESTRICT_RELATIONS,
        ]);
        $willPreserve = $this->relationCounts($student, self::PRESERVED_RELATIONS);

        return [
            'student' => [
                'id' => (int) $student->getKey(),
                'name' => $student->full_name,
                'rut' => $student->rut,
            ],
            'account' => [
                'exists' => (bool) $account,
                'email' => $account?->email,
                'active' => $account ? (bool) $account->active : null,
            ],
            'will_delete' => $willDelete,
            'will_preserve' => $willPreserve,
            'delete_total' => collect($willDelete)->sum('count'),
            'preserve_total' => collect($willPreserve)->sum('count'),
        ];
    }

    /**
     * @return array{account_deleted: bool}
     */
    public function deleteStudent(StudentProfile $student): array
    {
        $studentId = (int) $student->getKey();

        return DB::transaction(function () use ($studentId): array {
            $lockedStudent = StudentProfile::query()->lockForUpdate()->findOrFail($studentId);
            $linkedUser = User::query()
                ->where('student_id', $studentId)
                ->lockForUpdate()
                ->first();

            $linkedUser?->delete();
            $this->deleteStudentRecord($lockedStudent);

            return ['account_deleted' => (bool) $linkedUser];
        });
    }

    public function deleteStudentRecord(StudentProfile $student): void
    {
        foreach (self::RESTRICT_RELATIONS as $relation) {
            if (! $this->relationExists($relation['table'], $relation['column'])) {
                continue;
            }

            DB::table($relation['table'])
                ->where($relation['column'], $student->getKey())
                ->delete();
        }

        $student->delete();
    }

    /**
     * @param  array<int, array{table: string, column: string, label: string}>  $relations
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function relationCounts(StudentProfile $student, array $relations): array
    {
        return collect($relations)
            ->map(function (array $relation) use ($student): array {
                $count = $this->relationExists($relation['table'], $relation['column'])
                    ? DB::table($relation['table'])->where($relation['column'], $student->getKey())->count()
                    : 0;

                return [
                    'key' => $relation['table'].'.'.$relation['column'],
                    'label' => $relation['label'],
                    'count' => $count,
                ];
            })
            ->filter(fn (array $relation): bool => $relation['count'] > 0)
            ->values()
            ->all();
    }

    private function relationExists(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }
}
