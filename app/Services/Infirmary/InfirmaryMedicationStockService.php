<?php

namespace App\Services\Infirmary;

use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\Infirmary\InfirmaryMedicationMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class InfirmaryMedicationStockService
{
    public function refreshDynamicStatuses(): void
    {
        InfirmaryMedication::query()->get()->each(function (InfirmaryMedication $medication) {
            $status = $this->determineMedicationStatus($medication);

            if ($medication->status !== $status) {
                $medication->forceFill(['status' => $status])->save();
            }
        });

        InfirmaryMedicationAuthorization::query()->get()->each(function (InfirmaryMedicationAuthorization $authorization) {
            $status = $this->determineAuthorizationStatus($authorization);

            if ($authorization->status !== $status) {
                $authorization->forceFill(['status' => $status])->save();
            }
        });
    }

    public function determineMedicationStatus(InfirmaryMedication $medication): string
    {
        $today = now()->startOfDay();

        if ($medication->expires_at && $medication->expires_at->copy()->startOfDay()->lt($today)) {
            return InfirmaryMedication::STATUS_VENCIDO;
        }

        if ($medication->expires_at && $medication->expires_at->copy()->startOfDay()->lte(now()->addDays(30)->startOfDay())) {
            return InfirmaryMedication::STATUS_PROXIMO_VENCER;
        }

        if ((float) $medication->current_stock <= 0) {
            return InfirmaryMedication::STATUS_AGOTADO;
        }

        if ((float) $medication->current_stock <= (float) $medication->minimum_stock) {
            return InfirmaryMedication::STATUS_STOCK_BAJO;
        }

        return InfirmaryMedication::STATUS_DISPONIBLE;
    }

    public function determineAuthorizationStatus(InfirmaryMedicationAuthorization $authorization): string
    {
        $today = now()->startOfDay();
        $warningDate = now()->addDays(30)->startOfDay();

        if ($authorization->end_date && $authorization->end_date->copy()->startOfDay()->lt($today)) {
            return InfirmaryMedicationAuthorization::STATUS_TERMINADA;
        }

        $isExpired = collect([
            $authorization->medical_authorization_expires_at,
            $authorization->guardian_authorization_expires_at,
        ])->filter()->contains(fn ($date) => $date->copy()->startOfDay()->lt($today));

        if ($isExpired) {
            return InfirmaryMedicationAuthorization::STATUS_VENCIDA;
        }

        $isWarning = collect([
            $authorization->end_date,
            $authorization->medical_authorization_expires_at,
            $authorization->guardian_authorization_expires_at,
        ])->filter()->contains(fn ($date) => $date->copy()->startOfDay()->lte($warningDate));

        if ($isWarning) {
            return InfirmaryMedicationAuthorization::STATUS_PROXIMA_A_VENCER;
        }

        return InfirmaryMedicationAuthorization::STATUS_VIGENTE;
    }

    public function increaseStock(
        InfirmaryMedication $medication,
        string $movementType,
        float $quantity,
        ?User $user = null,
        ?string $reason = null,
        ?string $notes = null,
        ?Model $reference = null,
        ?Carbon $movedAt = null,
    ): InfirmaryMedicationMovement {
        return $this->storeMovement($medication, $movementType, abs($quantity), $user, $reason, $notes, $reference, $movedAt);
    }

    public function decreaseStock(
        InfirmaryMedication $medication,
        string $movementType,
        float $quantity,
        ?User $user = null,
        ?string $reason = null,
        ?string $notes = null,
        ?Model $reference = null,
        ?Carbon $movedAt = null,
    ): InfirmaryMedicationMovement {
        return $this->storeMovement($medication, $movementType, abs($quantity) * -1, $user, $reason, $notes, $reference, $movedAt);
    }

    public function applyAdjustment(
        InfirmaryMedication $medication,
        float $delta,
        ?User $user = null,
        ?string $reason = null,
        ?string $notes = null,
        ?Model $reference = null,
        ?Carbon $movedAt = null,
    ): InfirmaryMedicationMovement {
        return $this->storeMovement(
            $medication,
            InfirmaryMedicationMovement::TYPE_AJUSTE,
            $delta,
            $user,
            $reason,
            $notes,
            $reference,
            $movedAt,
        );
    }

    public function reverseAdministration(
        InfirmaryMedicationAdministration $administration,
        ?User $user = null,
        string $reason = 'Reversa por actualización de atención',
    ): void {
        $administration->loadMissing('medication');

        if (!$administration->medication) {
            return;
        }

        $this->increaseStock(
            $administration->medication,
            InfirmaryMedicationMovement::TYPE_REVERSA,
            (float) $administration->quantity_administered,
            $user,
            $reason,
            null,
            $administration,
            $administration->administered_at ? Carbon::parse($administration->administered_at) : now(),
        );
    }

    private function storeMovement(
        InfirmaryMedication $medication,
        string $movementType,
        float $delta,
        ?User $user = null,
        ?string $reason = null,
        ?string $notes = null,
        ?Model $reference = null,
        ?Carbon $movedAt = null,
    ): InfirmaryMedicationMovement {
        $before = (float) $medication->current_stock;
        $after = max(0, $before + $delta);

        $medication->forceFill([
            'current_stock' => $after,
            'status' => $this->determineMedicationStatus($medication->forceFill(['current_stock' => $after])),
        ])->save();

        return InfirmaryMedicationMovement::query()->create([
            'medication_id' => $medication->id,
            'movement_type' => $movementType,
            'quantity' => abs($delta),
            'stock_before' => $before,
            'stock_after' => $after,
            'reason' => $reason,
            'notes' => $notes,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'moved_at' => ($movedAt ?: now())->format('Y-m-d H:i:s'),
            'performed_by' => $user?->id,
        ]);
    }
}
