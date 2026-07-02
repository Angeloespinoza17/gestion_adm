<?php

namespace App\Services\Library;

use App\Models\Library\BibliotecaAlerta;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaPlanLector;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaReserva;
use App\Models\Library\BibliotecaUsoEspacio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BibliotecaAlertService
{
    public function refreshOperationalAlerts(?User $actor = null): void
    {
        $this->refreshLoanAlerts($actor);
        $this->refreshReservationAlerts($actor);
        $this->refreshAvailabilityAlerts($actor);
        $this->refreshReadingPlanAlerts($actor);
        $this->refreshSpaceAlerts($actor);
    }

    public function markResolved(string $relatedType, int $relatedId): void
    {
        BibliotecaAlerta::query()
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->where('status', 'pendiente')
            ->update([
                'status' => 'resuelta',
                'resolved_at' => now(),
            ]);
    }

    private function refreshLoanAlerts(?User $actor = null): void
    {
        $today = Carbon::today();

        $overdueLoans = BibliotecaPrestamo::query()
            ->with(['obra:id,title', 'student:id,first_name,last_name', 'staff:id,full_name'])
            ->whereIn('status', ['activo', 'renovado', 'vencido'])
            ->whereDate('due_at', '<', $today)
            ->get();

        $soonDueLoans = BibliotecaPrestamo::query()
            ->with('obra:id,title')
            ->whereIn('status', ['activo', 'renovado'])
            ->whereBetween('due_at', [$today->copy(), $today->copy()->addDays(3)])
            ->get();

        $this->replaceAlerts(
            'prestamo_vencido',
            $overdueLoans,
            fn (BibliotecaPrestamo $loan) => [
                'alert_level' => 'danger',
                'title' => 'Préstamo vencido',
                'message' => sprintf(
                    '%s tiene vencido "%s" desde %s.',
                    $loan->borrower_name_snapshot,
                    $loan->obra?->title ?? 'Recurso sin título',
                    Carbon::parse($loan->due_at)->format('d-m-Y')
                ),
                'due_at' => Carbon::parse($loan->due_at)->endOfDay(),
                'recipient_scope' => $loan->borrower_type === 'course' ? 'profesor_jefe' : 'bibliotecaria',
                'metadata' => ['loan_code' => $loan->loan_code],
            ],
            $actor
        );

        $this->replaceAlerts(
            'prestamo_por_vencer',
            $soonDueLoans,
            fn (BibliotecaPrestamo $loan) => [
                'alert_level' => 'warning',
                'title' => 'Préstamo próximo a vencer',
                'message' => sprintf(
                    '"%s" debe devolverse el %s.',
                    $loan->obra?->title ?? 'Recurso sin título',
                    Carbon::parse($loan->due_at)->format('d-m-Y')
                ),
                'due_at' => Carbon::parse($loan->due_at)->endOfDay(),
                'recipient_scope' => 'bibliotecaria',
                'metadata' => ['loan_code' => $loan->loan_code],
            ],
            $actor
        );
    }

    private function refreshReservationAlerts(?User $actor = null): void
    {
        $now = Carbon::now();

        $pendingReservations = BibliotecaReserva::query()
            ->with('obra:id,title')
            ->whereIn('status', ['solicitada', 'aprobada'])
            ->whereNotNull('pickup_at')
            ->where('pickup_at', '<', $now)
            ->get();

        $this->replaceAlerts(
            'reserva_no_retirada',
            $pendingReservations,
            fn (BibliotecaReserva $reservation) => [
                'alert_level' => 'warning',
                'title' => 'Reserva no retirada',
                'message' => sprintf(
                    'La reserva %s de "%s" superó su fecha de retiro.',
                    $reservation->reservation_code,
                    $reservation->obra?->title ?? 'Recurso sin título'
                ),
                'due_at' => $reservation->pickup_at,
                'recipient_scope' => 'bibliotecaria',
                'metadata' => ['reservation_code' => $reservation->reservation_code],
            ],
            $actor
        );
    }

    private function refreshAvailabilityAlerts(?User $actor = null): void
    {
        $lowAvailability = BibliotecaEjemplar::query()
            ->selectRaw('biblioteca_obra_id, count(*) as total')
            ->where('is_active', true)
            ->where('availability_status', 'disponible')
            ->groupBy('biblioteca_obra_id')
            ->having('total', '<=', 1)
            ->with('obra:id,title,internal_code')
            ->get();

        $damagedOrLost = BibliotecaEjemplar::query()
            ->with('obra:id,title')
            ->whereIn('availability_status', ['danado', 'perdido'])
            ->get();

        $this->replaceAlerts(
            'baja_disponibilidad',
            $lowAvailability,
            fn (BibliotecaEjemplar $ejemplar) => [
                'alert_level' => 'warning',
                'title' => 'Disponibilidad crítica',
                'message' => sprintf(
                    'La obra "%s" tiene %d ejemplar(es) disponible(s).',
                    $ejemplar->obra?->title ?? 'Obra sin título',
                    $ejemplar->total
                ),
                'due_at' => null,
                'recipient_scope' => 'bibliotecaria',
                'metadata' => ['obra_id' => $ejemplar->biblioteca_obra_id],
            ],
            $actor,
            'biblioteca_obra_id'
        );

        $this->replaceAlerts(
            'ejemplar_danado_perdido',
            $damagedOrLost,
            fn (BibliotecaEjemplar $ejemplar) => [
                'alert_level' => $ejemplar->availability_status === 'perdido' ? 'danger' : 'warning',
                'title' => $ejemplar->availability_status === 'perdido' ? 'Ejemplar perdido' : 'Ejemplar dañado',
                'message' => sprintf(
                    '%s del ejemplar %s asociado a "%s".',
                    ucfirst(str_replace('_', ' ', $ejemplar->availability_status)),
                    $ejemplar->code,
                    $ejemplar->obra?->title ?? 'Obra sin título'
                ),
                'due_at' => null,
                'recipient_scope' => 'bibliotecaria',
                'metadata' => ['ejemplar_code' => $ejemplar->code],
            ],
            $actor
        );
    }

    private function refreshReadingPlanAlerts(?User $actor = null): void
    {
        $shortagePlans = BibliotecaPlanLector::query()
            ->with(['obra:id,title', 'courseSection:id,display_name'])
            ->where('status', '!=', 'finalizado')
            ->whereColumn('available_copies', '<', 'required_copies')
            ->get();

        $this->replaceAlerts(
            'plan_lector_faltante',
            $shortagePlans,
            fn (BibliotecaPlanLector $plan) => [
                'alert_level' => 'warning',
                'title' => 'Plan lector con faltantes',
                'message' => sprintf(
                    'El curso %s no tiene ejemplares suficientes para "%s".',
                    $plan->courseSection?->display_name ?? 'Curso sin nombre',
                    $plan->obra?->title ?? 'Lectura sin título'
                ),
                'due_at' => $plan->start_date?->copy()->startOfDay(),
                'recipient_scope' => 'bibliotecaria',
                'metadata' => ['required_copies' => $plan->required_copies, 'available_copies' => $plan->available_copies],
            ],
            $actor
        );
    }

    private function refreshSpaceAlerts(?User $actor = null): void
    {
        $today = Carbon::today();

        $spaceReservations = BibliotecaUsoEspacio::query()
            ->with('espacio:id,name')
            ->whereDate('start_at', $today)
            ->whereIn('status', ['solicitada', 'aprobada'])
            ->get();

        $this->replaceAlerts(
            'espacio_hoy',
            $spaceReservations,
            fn (BibliotecaUsoEspacio $usage) => [
                'alert_level' => 'info',
                'title' => 'Espacio reservado hoy',
                'message' => sprintf(
                    '%s tiene uso programado en %s.',
                    $usage->title,
                    $usage->espacio?->name ?? 'espacio sin nombre'
                ),
                'due_at' => $usage->start_at,
                'recipient_scope' => 'bibliotecaria',
                'metadata' => ['status' => $usage->status],
            ],
            $actor
        );
    }

    /**
     * @template TItem of mixed
     * @param  Collection<int, TItem>  $items
     * @param  callable(TItem): array<string, mixed>  $payloadBuilder
     */
    private function replaceAlerts(
        string $alertType,
        Collection $items,
        callable $payloadBuilder,
        ?User $actor = null,
        string $relatedKey = 'id',
    ): void {
        $relatedIds = $items->pluck($relatedKey)->filter()->values()->all();

        BibliotecaAlerta::query()
            ->where('alert_type', $alertType)
            ->when(!empty($relatedIds), fn ($query) => $query->whereNotIn('related_id', $relatedIds))
            ->update([
                'status' => 'resuelta',
                'resolved_at' => now(),
            ]);

        foreach ($items as $item) {
            $payload = $payloadBuilder($item);
            BibliotecaAlerta::query()->updateOrCreate(
                [
                    'alert_type' => $alertType,
                    'related_type' => get_class($item),
                    'related_id' => $item->{$relatedKey},
                ],
                [
                    'alert_level' => $payload['alert_level'],
                    'title' => $payload['title'],
                    'message' => $payload['message'],
                    'status' => 'pendiente',
                    'due_at' => $payload['due_at'],
                    'recipient_scope' => $payload['recipient_scope'] ?? null,
                    'recipient_user_id' => $payload['recipient_user_id'] ?? null,
                    'metadata' => $payload['metadata'] ?? [],
                    'resolved_at' => null,
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]
            );
        }
    }
}
