<?php

namespace App\Services\Informatica;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentAttachment;
use App\Models\It\ItEquipmentLoan;
use App\Models\It\ItEquipmentMaintenanceReport;
use App\Models\User;
use App\Services\Inventory\InventoryCodeService;
use App\Services\Inventory\QrValueService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItEquipmentService
{
    public function __construct(
        private readonly InventoryCodeService $inventoryCodeService,
        private readonly QrValueService $qrValueService,
    ) {
    }

    public function create(array $payload, User $actor, ?UploadedFile $photo = null): ItEquipment
    {
        return DB::transaction(function () use ($payload, $actor, $photo) {
            $createInventoryItem = (bool) ($payload['create_inventory_item'] ?? false);
            unset($payload['create_inventory_item']);

            if (!empty($payload['inventory_item_id'])) {
                $payload = $this->mergeInventoryAssetData($payload, InventoryItem::query()->findOrFail($payload['inventory_item_id']));
            } elseif ($createInventoryItem) {
                $inventoryItem = $this->createInventoryItem($payload, $actor);
                $payload['inventory_item_id'] = $inventoryItem->id;
                $payload['internal_code'] = $inventoryItem->code;
            }

            $data = $this->normalizeResponsible($payload);
            $data['created_by'] = $actor->id;
            $data['updated_by'] = $actor->id;
            $data['active'] = $data['active'] ?? ($data['status'] ?? 'disponible') !== 'dado_de_baja';

            $equipment = ItEquipment::query()->create($data);

            if ($photo) {
                $this->storePhoto($equipment, $photo);
            }

            $this->logStatusChange(
                $equipment->fresh(),
                null,
                $equipment->status,
                $actor,
                'equipo_creado',
                $equipment->id,
                'Alta de equipo informático.'
            );

            return $this->freshEquipment($equipment);
        });
    }

    public function update(ItEquipment $equipment, array $payload, User $actor, ?UploadedFile $photo = null): ItEquipment
    {
        return DB::transaction(function () use ($equipment, $payload, $actor, $photo) {
            $previousStatus = $equipment->status;
            unset($payload['create_inventory_item']);
            if (!empty($payload['inventory_item_id'])) {
                $payload = $this->mergeInventoryAssetData($payload, InventoryItem::query()->findOrFail($payload['inventory_item_id']));
            }
            $data = $this->normalizeResponsible($payload);
            $data['updated_by'] = $actor->id;
            $data['active'] = $data['active'] ?? (($data['status'] ?? $equipment->status) !== 'dado_de_baja');

            $equipment->fill($data)->save();

            if ($equipment->inventoryItem) {
                $this->syncInventoryItem($equipment, $actor);
            }

            if ($photo) {
                $this->storePhoto($equipment, $photo);
            }

            if ($previousStatus !== $equipment->status) {
                $this->logStatusChange(
                    $equipment->fresh(),
                    $previousStatus,
                    $equipment->status,
                    $actor,
                    'equipo_actualizado',
                    $equipment->id,
                    'Actualización manual del estado del equipo.'
                );
            }

            return $this->freshEquipment($equipment);
        });
    }

    public function changeStatus(
        ItEquipment $equipment,
        string $newStatus,
        User $actor,
        ?string $notes = null,
        string $sourceType = 'manual',
        ?int $sourceId = null,
        bool $strict = true,
        array $extra = [],
    ): ItEquipment {
        return DB::transaction(function () use ($equipment, $newStatus, $actor, $notes, $sourceType, $sourceId, $strict, $extra) {
            if ($strict) {
                $this->assertStatusChangeAllowed($equipment, $newStatus);
            }

            $previousStatus = $equipment->status;
            $active = array_key_exists('active', $extra) && $extra['active'] !== null
                ? (bool) $extra['active']
                : $newStatus !== 'dado_de_baja';

            $equipment->forceFill([
                'status' => $newStatus,
                'active' => $active,
                'updated_by' => $actor->id,
            ])->save();

            if ($equipment->inventoryItem) {
                $this->syncInventoryItem($equipment->fresh('inventoryItem'), $actor);
            }

            if ($previousStatus !== $newStatus || $notes) {
                $this->logStatusChange(
                    $equipment->fresh(),
                    $previousStatus,
                    $newStatus,
                    $actor,
                    $sourceType,
                    $sourceId,
                    $notes,
                    $extra['metadata'] ?? []
                );
            }

            return $this->freshEquipment($equipment);
        });
    }

    public function delete(ItEquipment $equipment): void
    {
        if (
            $equipment->loans()->withTrashed()->exists()
            || $equipment->maintenanceReports()->withTrashed()->exists()
            || $equipment->statusLogs()->exists()
            || $equipment->attachments()->withTrashed()->exists()
        ) {
            throw ValidationException::withMessages([
                'equipment' => 'No se puede eliminar un equipo con historial asociado. Usa cambio de estado o baja lógica.',
            ]);
        }

        $equipment->delete();
    }

    public function storeAttachment(
        Model $attachable,
        ItEquipment $equipment,
        UploadedFile $file,
        User $actor,
        ?string $category = null,
        ?string $notes = null,
    ): ItEquipmentAttachment {
        $segment = match (true) {
            $attachable instanceof ItEquipmentLoan => 'loans',
            $attachable instanceof ItEquipmentMaintenanceReport => 'maintenance',
            default => 'equipment',
        };

        $path = $file->storePubliclyAs(
            sprintf('informatica/equipment/%d/%s', $equipment->id, $segment),
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            ['disk' => 'public']
        );

        return ItEquipmentAttachment::query()->create([
            'it_equipment_id' => $equipment->id,
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->getKey(),
            'category' => $category ?: 'documento',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'notes' => $notes,
            'uploaded_by' => $actor->id,
        ]);
    }

    public function removeAttachment(ItEquipmentAttachment $attachment): void
    {
        $path = $attachment->file_path;
        $attachment->delete();

        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function logStatusChange(
        ItEquipment $equipment,
        ?string $previousStatus,
        string $newStatus,
        User $actor,
        string $sourceType,
        ?int $sourceId = null,
        ?string $notes = null,
        array $metadata = [],
    ): void {
        $equipment->statusLogs()->create([
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'changed_at' => now(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'notes' => $notes,
            'changed_by_user_id' => $actor->id,
            'metadata' => $metadata,
        ]);
    }

    private function normalizeResponsible(array $payload): array
    {
        if (!empty($payload['responsible_user_id'])) {
            $user = User::query()->findOrFail($payload['responsible_user_id']);
            $payload['responsible_name'] = $user->name;
        } else {
            $payload['responsible_name'] = trim((string) ($payload['responsible_name'] ?? '')) ?: null;
        }

        return $payload;
    }

    private function mergeInventoryAssetData(array $payload, InventoryItem $item): array
    {
        $payload['inventory_item_id'] = $item->id;
        $payload['internal_code'] = $item->code;
        $payload['brand'] = $item->brand ?: ($payload['brand'] ?? null);
        $payload['model'] = $item->model ?: ($payload['model'] ?? null);
        $payload['serial_number'] = $item->serial_number ?: ($payload['serial_number'] ?? null);
        $payload['acquisition_date'] = $item->purchase_date?->toDateString() ?: ($payload['acquisition_date'] ?? null);
        $payload['reference_value'] = $item->purchase_value ?? ($payload['reference_value'] ?? null);
        $payload['responsible_user_id'] = $item->responsible_user_id ?: ($payload['responsible_user_id'] ?? null);
        $payload['location_name'] = $item->dependency?->name ?: ($payload['location_name'] ?? null);

        return $payload;
    }

    private function createInventoryItem(array $payload, User $actor): InventoryItem
    {
        $category = InventoryCategory::query()->firstWhere('slug', 'tecnologia');

        if (!$category) {
            throw ValidationException::withMessages([
                'inventory_item_id' => 'No existe la categoría Tecnología en Inventario. Ejecuta los catálogos de inventario antes de crear el activo.',
            ]);
        }

        $code = $this->inventoryCodeService->nextCode($category);
        $typeLabel = str((string) ($payload['equipment_type'] ?? 'equipo'))->replace('_', ' ')->title();

        return InventoryItem::query()->create([
            'code' => $code,
            'qr_code' => $this->qrValueService->forCode($code),
            'name' => trim("{$typeLabel} " . ($payload['brand'] ?? '') . ' ' . ($payload['model'] ?? '')),
            'description' => $payload['observations'] ?? null,
            'category_id' => $category->id,
            'brand' => $payload['brand'] ?? null,
            'model' => $payload['model'] ?? null,
            'serial_number' => $payload['serial_number'] ?? null,
            'purchase_date' => $payload['acquisition_date'] ?? null,
            'purchase_value' => $payload['reference_value'] ?? null,
            'status' => $this->inventoryStatus($payload['status'] ?? 'disponible'),
            'condition' => ($payload['status'] ?? null) === 'danado' ? 'Malo' : 'Bueno',
            'responsible_user_id' => $payload['responsible_user_id'] ?? null,
            'active' => (bool) ($payload['active'] ?? true),
            'item_type' => 'asset',
            'stock_quantity' => 1,
            'unit_of_measure' => 'un',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function syncInventoryItem(ItEquipment $equipment, User $actor): void
    {
        $equipment->inventoryItem->forceFill([
            'brand' => $equipment->brand,
            'model' => $equipment->model,
            'serial_number' => $equipment->serial_number,
            'purchase_date' => $equipment->acquisition_date,
            'purchase_value' => $equipment->reference_value,
            'responsible_user_id' => $equipment->responsible_user_id,
            'status' => $this->inventoryStatus($equipment->status),
            'condition' => $equipment->status === 'danado' ? 'Malo' : $equipment->inventoryItem->condition,
            'active' => $equipment->active,
            'updated_by' => $actor->id,
        ])->save();
    }

    private function inventoryStatus(string $status): string
    {
        return match ($status) {
            'prestado' => 'Prestado',
            'en_mantencion' => 'En reparación',
            'dado_de_baja' => 'Dado de baja',
            default => 'Activo',
        };
    }

    private function assertStatusChangeAllowed(ItEquipment $equipment, string $newStatus): void
    {
        $hasOpenLoan = $equipment->loans()->active()->exists();

        if ($newStatus === 'prestado' && !$hasOpenLoan) {
            throw ValidationException::withMessages([
                'status' => 'No puedes marcar manualmente un equipo como prestado sin un préstamo activo.',
            ]);
        }

        if ($hasOpenLoan && $newStatus !== 'prestado') {
            throw ValidationException::withMessages([
                'status' => 'No se puede cambiar el estado mientras exista un préstamo activo o atrasado.',
            ]);
        }
    }

    private function storePhoto(ItEquipment $equipment, UploadedFile $photo): void
    {
        if ($equipment->photo_path) {
            Storage::disk('public')->delete($equipment->photo_path);
        }

        $path = $photo->storePubliclyAs(
            sprintf('informatica/equipment/%d/photo', $equipment->id),
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $photo->getClientOriginalName(),
            ['disk' => 'public']
        );

        $equipment->forceFill([
            'photo_path' => $path,
            'photo_original_name' => $photo->getClientOriginalName(),
            'photo_mime_type' => $photo->getClientMimeType(),
        ])->save();
    }

    private function freshEquipment(ItEquipment $equipment): ItEquipment
    {
        return $equipment->fresh([
            'inventoryItem.category:id,name,slug',
            'inventoryItem.subcategory:id,category_id,name,slug',
            'inventoryItem.dependency:id,code,name',
            'responsibleUser:id,name,email',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);
    }
}
