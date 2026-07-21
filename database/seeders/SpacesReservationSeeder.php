<?php

namespace Database\Seeders;

use Database\Seeders\Support\PreventsProductionSeeding;

use App\Models\DependencyReservation;
use App\Models\DependencyReservationCollaborator;
use App\Models\Department;
use App\Models\MaintenanceDependency;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SpacesReservationSeeder extends Seeder
{
    use PreventsProductionSeeding;

    private const TITLE_PREFIX = 'Reserva prueba espacios';

    public function run(): void
    {
        $this->preventProductionSeeding();
        $timezone = config('app.timezone');
        $today = Carbon::today($timezone);

        $dependencies = MaintenanceDependency::query()
            ->reservableSpaces()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $staff = Staff::query()
            ->where('active', true)
            ->orderBy('full_name')
            ->get();

        $departments = Department::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->where('active', true)
            ->orderBy('id')
            ->get();

        if ($dependencies->isEmpty() || $staff->isEmpty() || $users->isEmpty()) {
            throw new RuntimeException('Se requieren dependencias reservables, funcionarios activos y usuarios activos para sembrar reservas.');
        }

        $usersByStaff = $users
            ->filter(fn (User $user) => filled($user->staff_id))
            ->keyBy('staff_id');

        $definitions = $this->definitions($today);

        DB::transaction(function () use ($definitions, $dependencies, $staff, $departments, $users, $usersByStaff) {
            DependencyReservation::query()
                ->where('title', 'like', self::TITLE_PREFIX . ' %')
                ->delete();

            foreach ($definitions as $index => $definition) {
                $requester = $staff[$index % $staff->count()];
                $dependency = $dependencies[$index % $dependencies->count()];
                $department = $departments->isNotEmpty() ? $departments[$index % $departments->count()] : null;
                $creator = $usersByStaff->get($requester->id) ?: $users[$index % $users->count()];
                $moderator = $users[($index + 1) % $users->count()];
                $startsAt = $definition['starts_at'];
                $endsAt = $startsAt->copy()->addMinutes($definition['duration']);
                $status = $definition['status'];

                $reservation = DependencyReservation::query()->create([
                    'maintenance_dependency_id' => $dependency->id,
                    'staff_id' => $requester->id,
                    'department_id' => $department?->id,
                    'title' => sprintf('%s %02d - %s', self::TITLE_PREFIX, $index + 1, $definition['title']),
                    'activity' => $definition['activity'],
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'repetition_type' => $definition['repetition_type'] ?? 'none',
                    'repetition_until' => $definition['repetition_until'] ?? null,
                    'series_uuid' => $definition['series_uuid'] ?? null,
                    'status' => $status,
                    'observations' => $definition['observations'] ?? null,
                    'estimated_attendees' => $definition['estimated_attendees'],
                    'special_requirements' => $definition['special_requirements'] ?? null,
                    'created_by' => $creator->id,
                    'approved_by' => in_array($status, [
                        DependencyReservation::STATUS_APPROVED,
                        DependencyReservation::STATUS_FINISHED,
                        DependencyReservation::STATUS_CANCELLED,
                    ], true) ? $moderator->id : null,
                    'cancelled_by' => $status === DependencyReservation::STATUS_CANCELLED ? $creator->id : null,
                    'approved_at' => in_array($status, [
                        DependencyReservation::STATUS_APPROVED,
                        DependencyReservation::STATUS_FINISHED,
                        DependencyReservation::STATUS_CANCELLED,
                    ], true) ? $startsAt->copy()->subDays(2)->setTime(10, 0) : null,
                    'rejected_at' => $status === DependencyReservation::STATUS_REJECTED
                        ? $startsAt->copy()->subDay()->setTime(16, 30)
                        : null,
                    'cancelled_at' => $status === DependencyReservation::STATUS_CANCELLED
                        ? $startsAt->copy()->subHours(8)
                        : null,
                ]);

                $this->seedCollaborators($reservation, $staff, $index);
            }
        });
    }

    private function definitions(Carbon $today): array
    {
        return [
            $this->definition($today, -10, '09:00', 120, DependencyReservation::STATUS_FINISHED, 'Taller de cierre pedagógico', 'Evaluación de actividades finalizadas del ciclo anterior.', 24),
            $this->definition($today, -7, '11:00', 90, DependencyReservation::STATUS_FINISHED, 'Reunión de coordinación finalizada', 'Revisión de acuerdos ejecutados por el equipo académico.', 12),
            $this->definition($today, -3, '15:30', 90, DependencyReservation::STATUS_REJECTED, 'Ensayo institucional rechazado', 'Solicitud rechazada para probar flujo de moderación.', 18, 'Rechazada por conflicto de agenda.'),
            $this->definition($today, -1, '17:00', 60, DependencyReservation::STATUS_CANCELLED, 'Reserva cancelada de convivencia', 'Caso de prueba para reservas canceladas.', 20, 'Cancelada por cambio de planificación.'),
            $this->definition($today, 1, '08:30', 90, DependencyReservation::STATUS_PENDING, 'Solicitud pendiente CRA', 'Reserva pendiente para validar bandeja de aprobación.', 16),
            $this->definition($today, 1, '10:30', 120, DependencyReservation::STATUS_APPROVED, 'Capacitación docente', 'Capacitación interna sobre planificación y evaluación.', 35, null, 'Proyector, audio y disposición tipo aula.'),
            $this->definition($today, 2, '09:00', 60, DependencyReservation::STATUS_PENDING, 'Entrevistas PIE', 'Bloque de entrevistas con familias y equipo de apoyo.', 8),
            $this->definition($today, 2, '12:00', 90, DependencyReservation::STATUS_APPROVED, 'Consejo de ciclo', 'Reunión de seguimiento académico por nivel.', 22),
            $this->definition($today, 3, '08:00', 120, DependencyReservation::STATUS_PENDING, 'Ensayo acto mensual', 'Ensayo general con estudiantes y profesores jefes.', 45, null, 'Micrófono inalámbrico y parlantes.'),
            $this->definition($today, 3, '14:00', 90, DependencyReservation::STATUS_APPROVED, 'Taller convivencia escolar', 'Actividad formativa con equipo de convivencia.', 30),
            $this->definition($today, 4, '09:30', 120, DependencyReservation::STATUS_PENDING, 'Feria científica', 'Preparación de muestras y estaciones de trabajo.', 40, null, 'Mesas perimetrales y alargadores.'),
            $this->definition($today, 4, '15:00', 60, DependencyReservation::STATUS_APPROVED, 'Reunión centro de estudiantes', 'Planificación de iniciativas estudiantiles.', 14),
            $this->definition($today, 5, '10:00', 90, DependencyReservation::STATUS_PENDING, 'Mesa técnica de evaluación', 'Revisión de instrumentos y criterios compartidos.', 18),
            $this->definition($today, 5, '16:00', 120, DependencyReservation::STATUS_APPROVED, 'Jornada pastoral', 'Actividad de preparación y coordinación pastoral.', 32),
            $this->definition($today, 6, '08:30', 60, DependencyReservation::STATUS_CANCELLED, 'Reserva cancelada apoderados', 'Caso de prueba para cancelaciones futuras.', 26, 'Suspendida por actualización de calendario.'),
            $this->definition($today, 6, '11:00', 90, DependencyReservation::STATUS_PENDING, 'Reforzamiento matemático', 'Sesión grupal para estudiantes priorizados.', 20, null, null, 'weekly', $today->copy()->addWeeks(4), Str::uuid()->toString()),
            $this->definition($today, 7, '09:00', 120, DependencyReservation::STATUS_APPROVED, 'Encuentro de departamentos', 'Coordinación entre departamentos y equipos de apoyo.', 38),
            $this->definition($today, 8, '13:00', 90, DependencyReservation::STATUS_PENDING, 'Taller de lectura', 'Actividad de mediación lectora para estudiantes.', 15),
            $this->definition($today, 9, '10:30', 90, DependencyReservation::STATUS_APPROVED, 'Preparación SIMCE', 'Planificación de actividades de preparación académica.', 28),
            $this->definition($today, 10, '15:30', 120, DependencyReservation::STATUS_PENDING, 'Capacitación uso de espacios', 'Prueba funcional completa del módulo de reservas.', 25),
        ];
    }

    private function definition(
        Carbon $today,
        int $dayOffset,
        string $time,
        int $duration,
        string $status,
        string $title,
        string $activity,
        int $attendees,
        ?string $observations = null,
        ?string $specialRequirements = null,
        string $repetitionType = 'none',
        ?Carbon $repetitionUntil = null,
        ?string $seriesUuid = null,
    ): array {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return [
            'starts_at' => $today->copy()->addDays($dayOffset)->setTime($hour, $minute),
            'duration' => $duration,
            'status' => $status,
            'title' => $title,
            'activity' => $activity,
            'estimated_attendees' => $attendees,
            'observations' => $observations,
            'special_requirements' => $specialRequirements,
            'repetition_type' => $repetitionType,
            'repetition_until' => $repetitionUntil,
            'series_uuid' => $seriesUuid,
        ];
    }

    private function seedCollaborators(DependencyReservation $reservation, $staff, int $index): void
    {
        $rows = [];

        if ($index % 3 === 0 && $staff->count() > 1) {
            $collaborator = $staff[($index + 1) % $staff->count()];
            $rows[] = [
                'dependency_reservation_id' => $reservation->id,
                'staff_id' => $collaborator->id,
                'external_email' => null,
            ];
        }

        if ($index % 5 === 0) {
            $rows[] = [
                'dependency_reservation_id' => $reservation->id,
                'staff_id' => null,
                'external_email' => sprintf('colaborador.prueba%02d@cnscgestion.local', $index + 1),
            ];
        }

        foreach ($rows as $row) {
            DependencyReservationCollaborator::query()->create($row);
        }
    }
}
