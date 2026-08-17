<?php

namespace App\Services\HumanResources;

use App\Models\HumanResources\HrAbsenceBalance;
use App\Models\HumanResources\HrAbsenceRecord;
use App\Models\HumanResources\HrMedicalLeave;
use App\Models\PermissionRequest;
use Carbon\CarbonImmutable;

class HrAbsenceLedgerService
{
    public function synchronizePlatformRecords(?int $userId = null): void
    {
        PermissionRequest::query()
            ->with('permissionType:id,name')
            ->whereNotIn('status', ['rechazado', 'cancelado', 'anulado'])
            ->whereDoesntHave('absenceLedgerRecord')
            ->chunkById(100, function ($requests) use ($userId): void {
                foreach ($requests as $request) {
                    $typeName = mb_strtolower((string) $request->permissionType?->name);
                    $type = str_contains($typeName, 'administr')
                        ? 'dia_administrativo'
                        : (str_contains($typeName, 'compensa') ? 'dia_compensatorio' : 'permiso_autorizado');
                    $quantity = (float) ($request->duration_days ?: 0);
                    if ($quantity <= 0) {
                        $quantity = $request->is_half_day ? 0.5 : max(1, CarbonImmutable::parse($request->start_date)->diffInDays(CarbonImmutable::parse($request->end_date)) + 1);
                    }
                    $record = HrAbsenceRecord::create([
                        'staff_id' => $request->staff_id,
                        'permission_request_id' => $request->id,
                        'absence_type' => $type,
                        'starts_on' => $request->start_date,
                        'ends_on' => $request->end_date,
                        'starts_at' => $request->start_time,
                        'ends_at' => $request->end_time,
                        'quantity' => $quantity,
                        'unit' => $request->duration_hours > 0 && $request->duration_days <= 0 ? 'horas' : 'dias',
                        'status' => $this->permissionStatus($request->status),
                        'source' => 'plataforma',
                        'affects_attendance' => (bool) $request->affects_attendance,
                        'affects_payroll' => (bool) $request->affects_salary,
                        'external_key' => hash('sha256', 'permission-request:'.$request->id),
                        'notes' => $request->reason ?: $request->description,
                        'metadata' => ['permission_status' => $request->status],
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                    $this->syncBalanceMovement($record, $userId);
                }
            });

        HrMedicalLeave::query()
            ->whereDoesntHave('absenceLedgerRecord')
            ->chunkById(100, function ($leaves) use ($userId): void {
                foreach ($leaves as $leave) {
                    HrAbsenceRecord::create([
                        'staff_id' => $leave->staff_id,
                        'medical_leave_id' => $leave->id,
                        'absence_type' => 'licencia_medica',
                        'starts_on' => $leave->starts_at,
                        'ends_on' => $leave->ends_at,
                        'quantity' => $leave->days,
                        'unit' => 'dias',
                        'status' => $leave->status === 'tramitada' ? 'tramitada' : 'registrada',
                        'source' => 'plataforma',
                        'affects_attendance' => true,
                        'affects_payroll' => (bool) $leave->affects_payroll,
                        'external_key' => hash('sha256', 'medical-leave:'.$leave->id),
                        'notes' => $leave->notes,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            });
    }

    public function syncBalanceMovement(HrAbsenceRecord $record, ?int $userId = null): void
    {
        $record->balanceMovements()->delete();
        if (! in_array($record->absence_type, ['dia_administrativo', 'dia_compensatorio'], true)
            || in_array($record->status, ['anulada', 'cancelada'], true)) {
            return;
        }

        $bucket = $record->absence_type === 'dia_administrativo' ? 'administrativo' : 'compensatorio';
        $balance = HrAbsenceBalance::query()->firstOrCreate([
            'staff_id' => $record->staff_id,
            'year' => (int) $record->starts_on->format('Y'),
        ], [
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        $balance->movements()->create([
            'absence_record_id' => $record->id,
            'bucket' => $bucket,
            'movement_type' => 'debito',
            'quantity' => -abs((float) $record->quantity),
            'effective_on' => $record->starts_on,
            'description' => $bucket === 'administrativo' ? 'Día administrativo utilizado' : 'Día compensatorio utilizado',
            'external_key' => hash('sha256', 'absence-movement:'.$record->id),
            'created_by' => $userId,
        ]);
    }

    private function permissionStatus(string $status): string
    {
        return match ($status) {
            'aprobado', 'ejecutado' => 'justificada',
            'observado' => 'pendiente_regularizacion',
            default => 'pendiente',
        };
    }
}
