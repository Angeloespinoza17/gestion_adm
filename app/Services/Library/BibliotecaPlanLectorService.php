<?php

namespace App\Services\Library;

use App\Models\Library\BibliotecaPlanLector;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BibliotecaPlanLectorService
{
    public function __construct(
        private readonly BibliotecaAlertService $alertService,
        private readonly BibliotecaLoanService $loanService,
    ) {
    }

    public function store(array $payload, User $actor): BibliotecaPlanLector
    {
        return DB::transaction(function () use ($payload, $actor) {
            $plan = new BibliotecaPlanLector();
            $this->fill($plan, $payload, $actor, true);
            $plan->save();

            $this->alertService->refreshOperationalAlerts($actor);

            return $plan->fresh(['academicYear', 'courseSection', 'responsibleStaff', 'obra']);
        });
    }

    public function update(BibliotecaPlanLector $plan, array $payload, User $actor): BibliotecaPlanLector
    {
        return DB::transaction(function () use ($plan, $payload, $actor) {
            $this->fill($plan, $payload, $actor, false);
            $plan->save();

            $this->alertService->refreshOperationalAlerts($actor);

            return $plan->fresh(['academicYear', 'courseSection', 'responsibleStaff', 'obra']);
        });
    }

    public function registerMassLoan(BibliotecaPlanLector $plan, array $payload, User $actor): array
    {
        return DB::transaction(function () use ($plan, $payload, $actor) {
            $plan->loadMissing(['obra.ejemplares', 'courseSection']);

            $quantity = (int) ($payload['quantity'] ?? $plan->required_copies);
            $dueAt = Carbon::parse($payload['due_at'] ?? $plan->end_date)->format('Y-m-d');
            $borrowedAt = Carbon::parse($payload['borrowed_at'] ?? now());
            $batchCode = 'PLAN-' . $plan->id . '-' . now()->format('YmdHis');

            $available = $plan->obra->ejemplares
                ->where('is_active', true)
                ->where('availability_status', 'disponible')
                ->take($quantity);

            $created = [];

            foreach ($available as $ejemplar) {
                $created[] = $this->loanService->create([
                    'batch_code' => $batchCode,
                    'borrower_type' => 'course',
                    'course_section_id' => $plan->course_section_id,
                    'academic_year_id' => $plan->academic_year_id,
                    'biblioteca_ejemplar_id' => $ejemplar->id,
                    'borrowed_at' => $borrowedAt,
                    'due_at' => $dueAt,
                    'delivered_by_user_id' => $payload['delivered_by_user_id'] ?? $actor->id,
                    'notes' => sprintf('Préstamo masivo de plan lector #%d.', $plan->id),
                ], $actor);
            }

            $plan->forceFill([
                'available_copies' => $plan->obra->fresh()->available_copies,
                'status' => $plan->status === 'planificado' ? 'en_ejecucion' : $plan->status,
                'fulfillment_percentage' => min(
                    100,
                    $plan->required_copies > 0 ? (int) round((count($created) / $plan->required_copies) * 100) : 0
                ),
                'updated_by' => $actor->id,
            ])->save();

            $this->alertService->refreshOperationalAlerts($actor);

            return [
                'plan' => $plan->fresh(['obra', 'courseSection', 'academicYear']),
                'created_loans' => $created,
                'requested_quantity' => $quantity,
                'created_quantity' => count($created),
            ];
        });
    }

    private function fill(BibliotecaPlanLector $plan, array $payload, User $actor, bool $creating): void
    {
        $obra = \App\Models\Library\BibliotecaObra::query()->findOrFail($payload['biblioteca_obra_id']);

        $plan->fill([
            'academic_year_id' => $payload['academic_year_id'],
            'course_section_id' => $payload['course_section_id'],
            'subject' => $payload['subject'],
            'responsible_staff_id' => $payload['responsible_staff_id'] ?? null,
            'biblioteca_obra_id' => $obra->id,
            'period' => $payload['period'] ?? null,
            'start_date' => Carbon::parse($payload['start_date'])->format('Y-m-d'),
            'end_date' => Carbon::parse($payload['end_date'])->format('Y-m-d'),
            'objective' => $payload['objective'] ?? null,
            'associated_activity' => $payload['associated_activity'] ?? null,
            'evaluation_description' => $payload['evaluation_description'] ?? null,
            'required_copies' => (int) ($payload['required_copies'] ?? 1),
            'available_copies' => $obra->available_copies,
            'fulfillment_percentage' => (int) ($payload['fulfillment_percentage'] ?? $plan->fulfillment_percentage ?? 0),
            'status' => $payload['status'],
            'notes' => $payload['notes'] ?? null,
            'attachments' => array_values($payload['attachments'] ?? []),
            'updated_by' => $actor->id,
        ]);

        if ($creating) {
            $plan->created_by = $actor->id;
        }
    }
}
