<?php

namespace App\Services\Informatica;

use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentMaintenanceReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItEquipmentMaintenanceService
{
    public function __construct(
        private readonly ItEquipmentService $equipmentService,
    ) {
    }

    public function create(array $payload, User $actor, ?UploadedFile $attachment = null): ItEquipmentMaintenanceReport
    {
        return DB::transaction(function () use ($payload, $actor, $attachment) {
            $equipment = ItEquipment::query()->findOrFail($payload['it_equipment_id']);
            $isActiveMaintenance = ($payload['status'] ?? 'borrador') !== 'borrador';

            if ($isActiveMaintenance) {
                $this->assertMaintenanceCanStart($equipment);
            }

            $report = ItEquipmentMaintenanceReport::query()->create([
                'maintenance_code' => $payload['maintenance_code'] ?? $this->generateMaintenanceCode(),
                'it_equipment_id' => $equipment->id,
                'maintenance_date' => Carbon::parse($payload['maintenance_date']),
                'maintenance_type' => $payload['maintenance_type'],
                'technician_user_id' => $payload['technician_user_id'] ?? null,
                'technician_name_snapshot' => $this->resolveTechnicianName($payload),
                'reason' => $payload['reason'],
                'diagnosis' => $payload['diagnosis'] ?? null,
                'actions_performed' => $payload['actions_performed'] ?? null,
                'spare_parts' => $payload['spare_parts'] ?? null,
                'cost_amount' => $payload['cost_amount'] ?? null,
                'initial_equipment_status' => $payload['initial_equipment_status'] ?? $equipment->status,
                'next_maintenance_at' => $payload['next_maintenance_at'] ?? null,
                'observations' => $payload['observations'] ?? null,
                'status' => $payload['status'],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($isActiveMaintenance && $equipment->status !== 'en_mantencion') {
                $this->equipmentService->changeStatus(
                    $equipment,
                    'en_mantencion',
                    $actor,
                    'Equipo derivado a mantención.',
                    'mantencion',
                    $report->id,
                    false
                );
            }

            if ($attachment) {
                $this->equipmentService->storeAttachment($report, $equipment, $attachment, $actor, 'informe_pdf', 'Adjunto de mantención.');
            }

            return $this->freshReport($report);
        });
    }

    public function update(ItEquipmentMaintenanceReport $report, array $payload, User $actor, ?UploadedFile $attachment = null): ItEquipmentMaintenanceReport
    {
        return DB::transaction(function () use ($report, $payload, $actor, $attachment) {
            if ($report->status === 'cerrado') {
                throw ValidationException::withMessages([
                    'report' => 'No se puede editar una mantención ya cerrada.',
                ]);
            }

            $equipment = ItEquipment::query()->findOrFail($payload['it_equipment_id']);
            $isActiveMaintenance = ($payload['status'] ?? $report->status) !== 'borrador';

            if ($isActiveMaintenance) {
                $this->assertMaintenanceCanStart($equipment, $report->id);
            }

            $report->forceFill([
                'it_equipment_id' => $equipment->id,
                'maintenance_code' => $payload['maintenance_code'] ?? $report->maintenance_code,
                'maintenance_date' => Carbon::parse($payload['maintenance_date']),
                'maintenance_type' => $payload['maintenance_type'],
                'technician_user_id' => $payload['technician_user_id'] ?? null,
                'technician_name_snapshot' => $this->resolveTechnicianName($payload),
                'reason' => $payload['reason'],
                'diagnosis' => $payload['diagnosis'] ?? null,
                'actions_performed' => $payload['actions_performed'] ?? null,
                'spare_parts' => $payload['spare_parts'] ?? null,
                'cost_amount' => $payload['cost_amount'] ?? null,
                'initial_equipment_status' => $payload['initial_equipment_status'] ?? $report->initial_equipment_status,
                'next_maintenance_at' => $payload['next_maintenance_at'] ?? null,
                'observations' => $payload['observations'] ?? null,
                'status' => $payload['status'],
                'updated_by' => $actor->id,
            ])->save();

            if ($isActiveMaintenance && $equipment->status !== 'en_mantencion') {
                $this->equipmentService->changeStatus(
                    $equipment,
                    'en_mantencion',
                    $actor,
                    'Equipo mantenido en proceso de mantención.',
                    'mantencion',
                    $report->id,
                    false
                );
            }

            if ($attachment) {
                $this->equipmentService->storeAttachment($report, $equipment, $attachment, $actor, 'respaldo', 'Adjunto adicional de mantención.');
            }

            return $this->freshReport($report);
        });
    }

    public function close(ItEquipmentMaintenanceReport $report, array $payload, User $actor): ItEquipmentMaintenanceReport
    {
        return DB::transaction(function () use ($report, $payload, $actor) {
            if ($report->status === 'cerrado') {
                throw ValidationException::withMessages([
                    'report' => 'La mantención ya se encuentra cerrada.',
                ]);
            }

            $report->forceFill([
                'status' => 'cerrado',
                'final_equipment_status' => $payload['final_equipment_status'],
                'closed_at' => Carbon::parse($payload['closed_at'] ?? now()),
                'closed_by_user_id' => $actor->id,
                'observations' => $this->appendNote($report->observations, $payload['observations'] ?? ''),
                'updated_by' => $actor->id,
            ])->save();

            $this->equipmentService->changeStatus(
                $report->equipment()->firstOrFail(),
                $payload['final_equipment_status'],
                $actor,
                'Cierre de mantención con actualización de estado final.',
                'cierre_mantencion',
                $report->id,
                false
            );

            return $this->freshReport($report);
        });
    }

    private function assertMaintenanceCanStart(ItEquipment $equipment, ?int $ignoreReportId = null): void
    {
        if ($equipment->status === 'dado_de_baja') {
            throw ValidationException::withMessages([
                'it_equipment_id' => 'No se puede iniciar una mantención activa sobre un equipo dado de baja.',
            ]);
        }

        if ($equipment->loans()->active()->exists()) {
            throw ValidationException::withMessages([
                'it_equipment_id' => 'No se puede enviar a mantención un equipo con préstamo activo o atrasado.',
            ]);
        }

        $hasOtherOpenReport = $equipment->maintenanceReports()
            ->whereIn('status', ['finalizado', 'pendiente_revision'])
            ->when($ignoreReportId, fn ($query) => $query->where('id', '!=', $ignoreReportId))
            ->exists();

        if ($hasOtherOpenReport) {
            throw ValidationException::withMessages([
                'it_equipment_id' => 'El equipo ya tiene una mantención activa pendiente de cierre.',
            ]);
        }
    }

    private function resolveTechnicianName(array $payload): ?string
    {
        if (!empty($payload['technician_user_id'])) {
            return User::query()->whereKey($payload['technician_user_id'])->value('name');
        }

        $name = trim((string) ($payload['technician_name'] ?? ''));

        return $name !== '' ? $name : null;
    }

    private function appendNote(?string $existing, string $new): ?string
    {
        $existing = trim((string) $existing);
        $new = trim($new);

        if ($new === '') {
            return $existing !== '' ? $existing : null;
        }

        return trim($existing === '' ? $new : $existing . PHP_EOL . $new);
    }

    private function freshReport(ItEquipmentMaintenanceReport $report): ItEquipmentMaintenanceReport
    {
        return $report->fresh([
            'equipment:id,internal_code,equipment_type,brand,model,status',
            'technician:id,name,email',
            'closedBy:id,name',
            'attachments.uploadedBy:id,name',
        ]);
    }

    private function generateMaintenanceCode(): string
    {
        return 'INF-MAN-' . now()->format('Ymd-His') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
