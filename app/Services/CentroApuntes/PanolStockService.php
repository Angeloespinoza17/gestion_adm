<?php

namespace App\Services\CentroApuntes;

use App\Models\CentroApuntes\PanolInsumo;
use App\Models\CentroApuntes\PanolMovimiento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PanolStockService
{
    public function registerMovement(PanolInsumo $insumo, array $payload, User $actor): PanolMovimiento
    {
        return DB::transaction(function () use ($insumo, $payload, $actor) {
            $quantity = round((float) $payload['quantity'], 2);
            $delta = $this->resolveDelta($payload['movement_type'], $quantity, $payload['adjustment_mode'] ?? null);
            $stockBefore = (float) $insumo->current_stock;
            $stockAfter = round($stockBefore + $delta, 2);

            if ($stockAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'La salida no puede ser mayor al stock disponible.',
                ]);
            }

            $insumo->forceFill([
                'current_stock' => $stockAfter,
                'updated_by' => $actor->id,
            ]);
            $this->refreshSupplyStatus($insumo, $payload['status_override'] ?? null);
            $insumo->save();

            return PanolMovimiento::query()->create([
                'insumo_id' => $insumo->id,
                'movement_type' => $payload['movement_type'],
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'moved_at' => $payload['moved_at'] ?? Carbon::now(),
                'responsible_user_id' => $payload['responsible_user_id'] ?? $actor->id,
                'requested_by_user_id' => $payload['requested_by_user_id'] ?? null,
                'department_id' => $payload['department_id'] ?? null,
                'reason' => $payload['reason'] ?? null,
                'document_reference' => $payload['document_reference'] ?? null,
                'observations' => $payload['observations'] ?? null,
                'reference_type' => $payload['reference_type'] ?? null,
                'reference_id' => $payload['reference_id'] ?? null,
                'metadata' => array_merge($payload['metadata'] ?? [], [
                    'delta' => $delta,
                    'adjustment_mode' => $payload['adjustment_mode'] ?? null,
                ]),
            ]);
        });
    }

    public function refreshSupplyStatus(PanolInsumo $insumo, ?string $statusOverride = null): void
    {
        if ($statusOverride === 'dado_de_baja' || !$insumo->active) {
            $insumo->status = 'dado_de_baja';
            $insumo->active = false;

            return;
        }

        if ($insumo->expires_at && Carbon::parse($insumo->expires_at)->isPast()) {
            $insumo->status = 'vencido';

            return;
        }

        if ((float) $insumo->current_stock <= 0) {
            $insumo->status = 'agotado';

            return;
        }

        if ((float) $insumo->current_stock <= (float) $insumo->minimum_stock) {
            $insumo->status = 'stock_bajo';

            return;
        }

        $insumo->status = 'disponible';
    }

    private function resolveDelta(string $movementType, float $quantity, ?string $adjustmentMode): float
    {
        return match ($movementType) {
            'ingreso', 'devolucion' => $quantity,
            'salida', 'perdida', 'vencimiento', 'baja' => -$quantity,
            'ajuste' => ($adjustmentMode === 'restar' ? -1 : 1) * $quantity,
            default => throw ValidationException::withMessages([
                'movement_type' => 'Tipo de movimiento no soportado.',
            ]),
        };
    }
}
