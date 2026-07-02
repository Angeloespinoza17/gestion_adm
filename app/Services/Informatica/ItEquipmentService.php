<?php

namespace App\Services\Informatica;

use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentAttachment;
use App\Models\It\ItEquipmentLoan;
use App\Models\It\ItEquipmentMaintenanceReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItEquipmentService
{
    public function create(array $payload, User $actor, ?UploadedFile $photo = null): ItEquipment
    {
        return DB::transaction(function () use ($payload, $actor, $photo) {
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
            $data = $this->normalizeResponsible($payload);
            $data['updated_by'] = $actor->id;
            $data['active'] = $data['active'] ?? (($data['status'] ?? $equipment->status) !== 'dado_de_baja');

            $equipment->fill($data)->save();

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
            'responsibleUser:id,name,email',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);
    }
}
