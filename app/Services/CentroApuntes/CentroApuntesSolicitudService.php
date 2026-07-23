<?php

namespace App\Services\CentroApuntes;

use App\Models\CentroApuntes\CentroApuntesAdjunto;
use App\Models\CentroApuntes\CentroApuntesAsignatura;
use App\Models\CentroApuntes\CentroApuntesHistorialEstado;
use App\Models\CentroApuntes\CentroApuntesMaquina;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CentroApuntesSolicitudService
{
    public function create(array $payload, User $actor, ?UploadedFile $attachment = null): CentroApuntesSolicitud
    {
        return DB::transaction(function () use ($payload, $actor, $attachment) {
            $data = $this->preparePayload($payload, $actor);
            $solicitud = CentroApuntesSolicitud::query()->create($data);

            $this->logHistory(
                $solicitud,
                'creada',
                null,
                $solicitud->status,
                $actor,
                'Solicitud registrada correctamente.'
            );

            if ($attachment instanceof UploadedFile) {
                $this->addAttachment($solicitud, $attachment, $actor);
            }

            return $solicitud->fresh($this->detailRelations());
        });
    }

    public function update(CentroApuntesSolicitud $solicitud, array $payload, User $actor, ?UploadedFile $attachment = null): CentroApuntesSolicitud
    {
        return DB::transaction(function () use ($solicitud, $payload, $actor, $attachment) {
            $previousStatus = $solicitud->status;
            $data = $this->preparePayload($payload, $actor, $solicitud);

            $solicitud->fill($data)->save();

            $this->logHistory(
                $solicitud,
                'actualizada',
                $previousStatus,
                $solicitud->status,
                $actor,
                'Solicitud actualizada.'
            );

            if ($attachment instanceof UploadedFile) {
                $this->addAttachment($solicitud, $attachment, $actor);
            }

            return $solicitud->fresh($this->detailRelations());
        });
    }

    public function changeStatus(
        CentroApuntesSolicitud $solicitud,
        User $actor,
        string $status,
        ?string $notes = null,
    ): CentroApuntesSolicitud {
        return DB::transaction(function () use ($solicitud, $actor, $status, $notes) {
            $previousStatus = $solicitud->status;
            $solicitud->forceFill([
                'status' => $status,
                'status_changed_at' => Carbon::now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->logHistory($solicitud, 'cambio_estado', $previousStatus, $status, $actor, $notes);

            return $solicitud->fresh($this->detailRelations());
        });
    }

    public function registerDelivery(
        CentroApuntesSolicitud $solicitud,
        User $actor,
        User $receivedBy,
        ?string $notes = null,
    ): CentroApuntesSolicitud {
        return DB::transaction(function () use ($solicitud, $actor, $receivedBy, $notes) {
            $previousStatus = $solicitud->status;
            $solicitud->forceFill([
                'status' => 'entregada',
                'received_by_user_id' => $receivedBy->id,
                'received_by_name_snapshot' => $receivedBy->name,
                'delivered_at' => Carbon::now(),
                'status_changed_at' => Carbon::now(),
                'updated_by' => $actor->id,
                'internal_observations' => $notes
                    ? trim((string) $solicitud->internal_observations . PHP_EOL . '[' . Carbon::now()->format('d-m-Y H:i') . '] ' . $notes)
                    : $solicitud->internal_observations,
            ])->save();

            $this->logHistory($solicitud, 'entrega_registrada', $previousStatus, 'entregada', $actor, $notes, [
                'received_by_user_id' => $receivedBy->id,
                'received_by_name' => $receivedBy->name,
            ]);

            return $solicitud->fresh($this->detailRelations());
        });
    }

    public function delete(CentroApuntesSolicitud $solicitud): void
    {
        DB::transaction(function () use ($solicitud) {
            $attachments = $solicitud->attachments()->get();

            foreach ($attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $solicitud->delete();
        });
    }

    public function addAttachment(CentroApuntesSolicitud $solicitud, UploadedFile $file, User $actor): CentroApuntesAdjunto
    {
        return DB::transaction(function () use ($solicitud, $file, $actor) {
            $path = $file->storePubliclyAs(
                sprintf('centro-apuntes/solicitudes/%d', $solicitud->id),
                now()->format('Ymd_His') . '_' . uniqid('', true) . '.' . $file->getClientOriginalExtension(),
                ['disk' => 'public']
            );

            $attachment = $solicitud->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $actor->id,
            ]);

            $solicitud->forceFill([
                'attachment_count' => $solicitud->attachments()->count(),
                'updated_by' => $actor->id,
            ])->save();

            $this->logHistory($solicitud, 'adjunto_registrado', $solicitud->status, $solicitud->status, $actor, 'Se adjuntó un archivo.', [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
            ]);

            return $attachment;
        });
    }

    /**
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'requester:id,name,email,user_type,staff_id',
            'subject:id,name,code,area,education_level,status',
            'machine:id,name,internal_code,type,status',
            'receivedBy:id,name,email',
            'attachments.uploadedBy:id,name',
            'history.performedBy:id,name',
        ];
    }

    private function preparePayload(array $payload, User $actor, ?CentroApuntesSolicitud $current = null): array
    {
        $machine = CentroApuntesMaquina::query()->findOrFail($payload['machine_id']);
        $requester = User::query()->findOrFail($payload['requested_by_user_id']);
        $subject = CentroApuntesAsignatura::query()->findOrFail($payload['subject_id']);

        $priority = $payload['priority'];
        $isImmediate = $priority === 'entrega_inmediata' || ($payload['is_immediate'] ?? false);
        $isUrgent = $isImmediate || $priority === 'urgente' || ($payload['is_urgent'] ?? false);

        $sheetCount = (int) $payload['sheet_count'];
        $copiesCount = (int) $payload['copies_count'];
        $totalImpressions = $sheetCount * $copiesCount;
        $metadata = $current?->metadata ?? [];
        unset($metadata['machine_cost_reference']);

        return [
            'request_code' => $current?->request_code ?? $this->nextRequestCode(),
            'requested_by_user_id' => $requester->id,
            'requested_by_name_snapshot' => $requester->name,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'machine_id' => $machine->id,
            'machine_name_snapshot' => $machine->name,
            'task_type' => $payload['task_type'],
            'task_type_other' => $payload['task_type_other'] ?? null,
            'requested_at' => $payload['requested_at'] ?? $current?->requested_at ?? Carbon::now(),
            'delivery_date' => $payload['delivery_date'],
            'sheet_count' => $sheetCount,
            'copies_count' => $copiesCount,
            'paper_size' => $payload['paper_size'],
            'priority' => $isImmediate ? 'entrega_inmediata' : ($isUrgent ? 'urgente' : 'normal'),
            'is_urgent' => $isUrgent,
            'is_immediate' => $isImmediate,
            'instructions' => $payload['instructions'] ?? null,
            'observations' => $payload['observations'] ?? null,
            'internal_observations' => $payload['internal_observations'] ?? $current?->internal_observations,
            'status' => $current?->status ?? 'pendiente',
            'estimated_total_impressions' => $totalImpressions,
            'estimated_cost_per_sheet' => 0,
            'estimated_cost_per_copy' => 0,
            'estimated_cost_total' => 0,
            'status_changed_at' => $current?->status_changed_at ?? Carbon::now(),
            'metadata' => $metadata,
            'created_by' => $current?->created_by ?? $actor->id,
            'updated_by' => $actor->id,
        ];
    }

    private function logHistory(
        CentroApuntesSolicitud $solicitud,
        string $actionType,
        ?string $previousStatus,
        ?string $newStatus,
        User $actor,
        ?string $notes = null,
        array $metadata = [],
    ): CentroApuntesHistorialEstado {
        return $solicitud->history()->create([
            'action_type' => $actionType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'performed_by' => $actor->id,
            'metadata' => $metadata,
        ]);
    }

    private function nextRequestCode(): string
    {
        $lastId = (int) CentroApuntesSolicitud::query()->max('id') + 1;

        return sprintf('CAP-%05d', $lastId);
    }
}
