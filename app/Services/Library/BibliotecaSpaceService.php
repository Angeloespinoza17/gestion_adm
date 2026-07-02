<?php

namespace App\Services\Library;

use App\Models\Library\BibliotecaEspacio;
use App\Models\Library\BibliotecaUsoEspacio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BibliotecaSpaceService
{
    public function __construct(
        private readonly BibliotecaAlertService $alertService,
    ) {
    }

    public function storeSpace(array $payload, User $actor): BibliotecaEspacio
    {
        return DB::transaction(function () use ($payload, $actor) {
            $space = !empty($payload['id'])
                ? BibliotecaEspacio::query()->findOrFail($payload['id'])
                : new BibliotecaEspacio();

            $space->fill([
                'name' => $payload['name'],
                'location' => $payload['location'] ?? null,
                'capacity' => $payload['capacity'] ?? null,
                'resources' => array_values($payload['resources'] ?? []),
                'active' => (bool) ($payload['active'] ?? true),
                'notes' => $payload['notes'] ?? null,
                'updated_by' => $actor->id,
            ]);

            if (!$space->exists) {
                $space->created_by = $actor->id;
            }

            $space->save();

            return $space;
        });
    }

    public function storeUsage(array $payload, User $actor): BibliotecaUsoEspacio
    {
        return DB::transaction(function () use ($payload, $actor) {
            $this->assertNoOverlap(
                null,
                (int) $payload['biblioteca_espacio_id'],
                Carbon::parse($payload['start_at']),
                Carbon::parse($payload['end_at'])
            );

            $usage = BibliotecaUsoEspacio::query()->create([
                'biblioteca_espacio_id' => $payload['biblioteca_espacio_id'],
                'activity_type' => $payload['activity_type'],
                'title' => $payload['title'],
                'course_section_id' => $payload['course_section_id'] ?? null,
                'responsible_staff_id' => $payload['responsible_staff_id'] ?? null,
                'requested_by_user_id' => $payload['requested_by_user_id'] ?? $actor->id,
                'attendee_count' => $payload['attendee_count'] ?? null,
                'requested_resources' => array_values($payload['requested_resources'] ?? []),
                'start_at' => Carbon::parse($payload['start_at']),
                'end_at' => Carbon::parse($payload['end_at']),
                'status' => $payload['status'] ?? 'solicitada',
                'observations' => $payload['observations'] ?? null,
                'evidence' => array_values($payload['evidence'] ?? []),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->alertService->refreshOperationalAlerts($actor);

            return $usage->fresh(['espacio', 'courseSection', 'responsibleStaff', 'requestedBy']);
        });
    }

    public function updateUsage(BibliotecaUsoEspacio $usage, array $payload, User $actor): BibliotecaUsoEspacio
    {
        return DB::transaction(function () use ($usage, $payload, $actor) {
            $spaceId = (int) ($payload['biblioteca_espacio_id'] ?? $usage->biblioteca_espacio_id);
            $startAt = Carbon::parse($payload['start_at'] ?? $usage->start_at);
            $endAt = Carbon::parse($payload['end_at'] ?? $usage->end_at);

            $this->assertNoOverlap($usage->id, $spaceId, $startAt, $endAt);

            $usage->fill([
                'biblioteca_espacio_id' => $spaceId,
                'activity_type' => $payload['activity_type'] ?? $usage->activity_type,
                'title' => $payload['title'] ?? $usage->title,
                'course_section_id' => $payload['course_section_id'] ?? $usage->course_section_id,
                'responsible_staff_id' => $payload['responsible_staff_id'] ?? $usage->responsible_staff_id,
                'requested_by_user_id' => $payload['requested_by_user_id'] ?? $usage->requested_by_user_id,
                'attendee_count' => $payload['attendee_count'] ?? $usage->attendee_count,
                'requested_resources' => array_values($payload['requested_resources'] ?? $usage->requested_resources ?? []),
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => $payload['status'] ?? $usage->status,
                'observations' => $payload['observations'] ?? $usage->observations,
                'evidence' => array_values($payload['evidence'] ?? $usage->evidence ?? []),
                'updated_by' => $actor->id,
            ])->save();

            $this->alertService->refreshOperationalAlerts($actor);

            return $usage->fresh(['espacio', 'courseSection', 'responsibleStaff', 'requestedBy']);
        });
    }

    public function transition(BibliotecaUsoEspacio $usage, string $status, User $actor, ?string $notes = null): BibliotecaUsoEspacio
    {
        $usage->forceFill([
            'status' => $status,
            'observations' => trim(($usage->observations ? $usage->observations . PHP_EOL : '') . ($notes ?? '')),
            'updated_by' => $actor->id,
        ])->save();

        $this->alertService->refreshOperationalAlerts($actor);

        return $usage->fresh(['espacio', 'courseSection', 'responsibleStaff', 'requestedBy']);
    }

    private function assertNoOverlap(?int $ignoreId, int $spaceId, Carbon $startAt, Carbon $endAt): void
    {
        $hasOverlap = BibliotecaUsoEspacio::query()
            ->where('biblioteca_espacio_id', $spaceId)
            ->whereIn('status', ['solicitada', 'aprobada', 'realizada'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where(function ($query) use ($startAt, $endAt) {
                $query
                    ->whereBetween('start_at', [$startAt, $endAt])
                    ->orWhereBetween('end_at', [$startAt, $endAt])
                    ->orWhere(function ($inner) use ($startAt, $endAt) {
                        $inner->where('start_at', '<=', $startAt)->where('end_at', '>=', $endAt);
                    });
            })
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'biblioteca_espacio_id' => 'El espacio ya tiene una reserva en ese tramo horario.',
            ]);
        }
    }
}
