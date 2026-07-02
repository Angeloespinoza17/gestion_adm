<?php

namespace App\Services\CentroApuntes;

use App\Models\CentroApuntes\PanolEntrega;
use App\Models\CentroApuntes\PanolEntregaDetalle;
use App\Models\CentroApuntes\PanolInsumo;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PanolDeliveryService
{
    public function __construct(
        private readonly PanolStockService $stockService,
    ) {
    }

    public function create(array $payload, User $actor): PanolEntrega
    {
        return DB::transaction(function () use ($payload, $actor) {
            $delivery = PanolEntrega::query()->create($this->basePayload($payload, $actor));
            $this->syncDetails($delivery, $payload['details'] ?? []);

            return $delivery->fresh($this->detailRelations());
        });
    }

    public function update(PanolEntrega $delivery, array $payload, User $actor): PanolEntrega
    {
        if ($delivery->status === 'entregada') {
            throw ValidationException::withMessages([
                'delivery' => 'No se puede editar una entrega que ya fue registrada como entregada.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $payload, $actor) {
            $delivery->fill($this->basePayload($payload, $actor, $delivery))->save();
            $this->syncDetails($delivery, $payload['details'] ?? []);

            return $delivery->fresh($this->detailRelations());
        });
    }

    public function approve(PanolEntrega $delivery, User $actor, ?string $notes = null): PanolEntrega
    {
        return $this->transition($delivery, $actor, 'aprobada', [
            'approved_at' => Carbon::now(),
            'approved_by_user_id' => $actor->id,
            'receipt_notes' => $notes ? trim((string) $delivery->receipt_notes . PHP_EOL . $notes) : $delivery->receipt_notes,
        ]);
    }

    public function reject(PanolEntrega $delivery, User $actor, ?string $notes = null): PanolEntrega
    {
        return $this->transition($delivery, $actor, 'rechazada', [
            'receipt_notes' => $notes ? trim((string) $delivery->receipt_notes . PHP_EOL . $notes) : $delivery->receipt_notes,
        ]);
    }

    public function annul(PanolEntrega $delivery, User $actor, ?string $notes = null): PanolEntrega
    {
        return $this->transition($delivery, $actor, 'anulada', [
            'receipt_notes' => $notes ? trim((string) $delivery->receipt_notes . PHP_EOL . $notes) : $delivery->receipt_notes,
        ]);
    }

    public function deliver(PanolEntrega $delivery, User $actor, ?User $withdrawnBy = null, ?string $notes = null): PanolEntrega
    {
        if ($delivery->status === 'entregada') {
            throw ValidationException::withMessages([
                'delivery' => 'La entrega seleccionada ya fue registrada.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $actor, $withdrawnBy, $notes) {
            $delivery->loadMissing('details.insumo');

            foreach ($delivery->details as $detail) {
                $this->stockService->registerMovement($detail->insumo, [
                    'movement_type' => 'salida',
                    'quantity' => $detail->quantity,
                    'moved_at' => Carbon::now(),
                    'responsible_user_id' => $actor->id,
                    'requested_by_user_id' => $delivery->requested_by_user_id,
                    'department_id' => $delivery->department_id,
                    'reason' => 'Entrega de materiales desde pañol.',
                    'observations' => $notes,
                    'reference_type' => PanolEntrega::class,
                    'reference_id' => $delivery->id,
                    'metadata' => [
                        'delivery_code' => $delivery->delivery_code,
                    ],
                ], $actor);
            }

            $delivery->forceFill([
                'status' => 'entregada',
                'delivered_at' => Carbon::now(),
                'delivered_by_user_id' => $actor->id,
                'withdrawn_by_user_id' => $withdrawnBy?->id ?? $delivery->withdrawn_by_user_id,
                'withdrawn_by_name_snapshot' => $withdrawnBy?->name ?? $delivery->withdrawn_by_name_snapshot,
                'approved_at' => $delivery->approved_at ?? Carbon::now(),
                'approved_by_user_id' => $delivery->approved_by_user_id ?? $actor->id,
                'receipt_notes' => $notes ? trim((string) $delivery->receipt_notes . PHP_EOL . $notes) : $delivery->receipt_notes,
                'updated_by' => $actor->id,
            ])->save();

            return $delivery->fresh($this->detailRelations());
        });
    }

    public function delete(PanolEntrega $delivery): void
    {
        if ($delivery->status === 'entregada') {
            throw ValidationException::withMessages([
                'delivery' => 'No se puede eliminar una entrega que ya impactó stock.',
            ]);
        }

        $delivery->delete();
    }

    /**
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'requester:id,name,email',
            'withdrawnBy:id,name,email',
            'department:id,name',
            'approvedBy:id,name',
            'deliveredBy:id,name',
            'details.insumo:id,name,unit_of_measure,current_stock,status',
        ];
    }

    private function basePayload(array $payload, User $actor, ?PanolEntrega $current = null): array
    {
        $requester = User::query()->findOrFail($payload['requested_by_user_id']);
        $withdrawnBy = !empty($payload['withdrawn_by_user_id'])
            ? User::query()->findOrFail($payload['withdrawn_by_user_id'])
            : null;
        $department = !empty($payload['department_id'])
            ? Department::query()->findOrFail($payload['department_id'])
            : null;

        return [
            'delivery_code' => $current?->delivery_code ?? $this->nextDeliveryCode(),
            'requested_by_user_id' => $requester->id,
            'requested_by_name_snapshot' => $requester->name,
            'withdrawn_by_user_id' => $withdrawnBy?->id,
            'withdrawn_by_name_snapshot' => $withdrawnBy?->name,
            'department_id' => $department?->id,
            'department_name_snapshot' => $department?->name,
            'requested_at' => $payload['requested_at'] ?? $current?->requested_at ?? Carbon::now(),
            'status' => $current?->status ?? 'solicitada',
            'observations' => $payload['observations'] ?? null,
            'receipt_notes' => $payload['receipt_notes'] ?? $current?->receipt_notes,
            'metadata' => array_merge($current?->metadata ?? [], [
                'detail_count' => count($payload['details'] ?? []),
            ]),
            'created_by' => $current?->created_by ?? $actor->id,
            'updated_by' => $actor->id,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    private function syncDetails(PanolEntrega $delivery, array $details): void
    {
        if (empty($details)) {
            throw ValidationException::withMessages([
                'details' => 'Debe registrar al menos un insumo para la entrega.',
            ]);
        }

        $delivery->details()->delete();
        $totalEstimatedCost = 0;

        foreach ($details as $detail) {
            $insumo = PanolInsumo::query()->findOrFail($detail['insumo_id']);
            $quantity = round((float) $detail['quantity'], 2);

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'details' => 'Todas las cantidades de entrega deben ser mayores que cero.',
                ]);
            }

            $lineTotal = round($quantity * (float) $insumo->unit_price_estimated, 2);
            $totalEstimatedCost += $lineTotal;

            PanolEntregaDetalle::query()->create([
                'panol_entrega_id' => $delivery->id,
                'insumo_id' => $insumo->id,
                'insumo_name_snapshot' => $insumo->name,
                'quantity' => $quantity,
                'unit_cost_estimated' => $insumo->unit_price_estimated,
                'line_total_estimated' => $lineTotal,
                'notes' => $detail['notes'] ?? null,
            ]);
        }

        $delivery->forceFill([
            'total_estimated_cost' => $totalEstimatedCost,
        ])->save();
    }

    private function transition(PanolEntrega $delivery, User $actor, string $status, array $changes = []): PanolEntrega
    {
        $delivery->forceFill(array_merge($changes, [
            'status' => $status,
            'updated_by' => $actor->id,
        ]))->save();

        return $delivery->fresh($this->detailRelations());
    }

    private function nextDeliveryCode(): string
    {
        $nextId = (int) PanolEntrega::query()->max('id') + 1;

        return sprintf('PAN-%05d', $nextId);
    }
}
