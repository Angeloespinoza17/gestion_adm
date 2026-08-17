<?php

namespace App\Http\Controllers\HumanResources;

use App\Http\Controllers\Controller;
use App\Models\HumanResources\HrAbsenceBalance;
use App\Models\HumanResources\HrAbsenceRecord;
use App\Models\HumanResources\HrMedicalLeave;
use App\Models\Staff;
use App\Services\HumanResources\HrAbsenceLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrAbsenceController extends Controller
{
    public function __construct(private readonly HrAbsenceLedgerService $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);
        $this->ledger->synchronizePlatformRecords($request->user()->id);
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'type' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string', 'max:50'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $year = (int) ($filters['year'] ?? now()->year);
        $query = HrAbsenceRecord::query()
            ->with('staff:id,full_name,rut,cargo_id')
            ->whereYear('starts_on', $year)
            ->when($filters['type'] ?? null, fn ($builder, $value) => $builder->where('absence_type', $value))
            ->when($filters['status'] ?? null, fn ($builder, $value) => $builder->where('status', $value))
            ->when($filters['staff_id'] ?? null, fn ($builder, $value) => $builder->where('staff_id', $value))
            ->when($filters['search'] ?? null, function ($builder, $value): void {
                $builder->whereHas('staff', fn ($staff) => $staff->where('full_name', 'like', '%'.$value.'%')->orWhere('rut', 'like', '%'.$value.'%'));
            });
        $summaryQuery = clone $query;
        $typeTotals = (clone $summaryQuery)
            ->select('absence_type', DB::raw('COUNT(*) as records'), DB::raw('SUM(quantity) as quantity'))
            ->groupBy('absence_type')
            ->get();
        $balances = HrAbsenceBalance::query()
            ->with('staff:id,full_name,rut')
            ->withSum(['movements as administrative_movement_total' => fn ($q) => $q->where('bucket', 'administrativo')], 'quantity')
            ->withSum(['movements as compensatory_movement_total' => fn ($q) => $q->where('bucket', 'compensatorio')], 'quantity')
            ->where('year', $year)
            ->orderBy(Staff::select('full_name')->whereColumn('staff.id', 'hr_absence_balances.staff_id'))
            ->get()
            ->map(function (HrAbsenceBalance $balance): array {
                $adminMovement = (float) ($balance->administrative_movement_total ?? 0);
                $compMovement = (float) ($balance->compensatory_movement_total ?? 0);

                return [
                    'id' => $balance->id,
                    'staff_id' => $balance->staff_id,
                    'staff' => $balance->staff,
                    'year' => $balance->year,
                    'administrative_entitlement' => (float) $balance->administrative_entitlement,
                    'administrative_adjustment' => (float) $balance->administrative_adjustment,
                    'administrative_used' => abs(min(0, $adminMovement)),
                    'administrative_available' => (float) $balance->administrative_entitlement + (float) $balance->administrative_adjustment + $adminMovement,
                    'compensatory_entitlement' => (float) $balance->compensatory_entitlement,
                    'compensatory_adjustment' => (float) $balance->compensatory_adjustment,
                    'compensatory_used' => abs(min(0, $compMovement)),
                    'compensatory_available' => (float) $balance->compensatory_entitlement + (float) $balance->compensatory_adjustment + $compMovement,
                    'notes' => $balance->notes,
                ];
            });

        return response()->json(['data' => [
            'summary' => [
                'year' => $year,
                'records' => (clone $summaryQuery)->count(),
                'people' => (clone $summaryQuery)->distinct('staff_id')->count('staff_id'),
                'days' => round((float) (clone $summaryQuery)->where('unit', 'dias')->sum('quantity'), 2),
                'pending' => (clone $summaryQuery)->whereIn('status', ['pendiente', 'pendiente_regularizacion'])->count(),
                'by_type' => $typeTotals,
            ],
            'records' => $query->latest('starts_on')->latest('id')->paginate((int) ($filters['per_page'] ?? 25)),
            'balances' => $balances,
            'staff' => Staff::query()->select('id', 'full_name', 'rut', 'cargo_id')->where('active', true)->orderBy('full_name')->get(),
            'catalogs' => $this->catalogs(),
            'capabilities' => [
                'manage' => $this->can($request, 'rrhh.ausencias.gestionar'),
                'import' => $this->can($request, 'rrhh.ausencias.importar'),
                'export' => $this->can($request, 'rrhh.ausencias.exportar'),
            ],
        ]]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->authorizeView($request);
        $this->ledger->synchronizePlatformRecords($request->user()->id);
        $filters = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string', 'max:50'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $records = HrAbsenceRecord::query()
            ->with('staff:id,full_name,rut,cargo_id')
            ->whereDate('starts_on', '<=', $filters['date_to'])
            ->whereDate('ends_on', '>=', $filters['date_from'])
            ->when($filters['type'] ?? null, fn ($builder, $value) => $builder->where('absence_type', $value))
            ->when($filters['status'] ?? null, fn ($builder, $value) => $builder->where('status', $value))
            ->when($filters['staff_id'] ?? null, fn ($builder, $value) => $builder->where('staff_id', $value))
            ->when($filters['search'] ?? null, function ($builder, $value): void {
                $builder->whereHas('staff', fn ($staff) => $staff->where('full_name', 'like', '%'.$value.'%')->orWhere('rut', 'like', '%'.$value.'%'));
            })
            ->orderBy('starts_on')
            ->orderBy('staff_id')
            ->get();

        return response()->json(['data' => $records]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $this->validatedRecord($request);
        $record = DB::transaction(function () use ($payload, $request): HrAbsenceRecord {
            $medicalLeave = null;
            if ($payload['absence_type'] === 'licencia_medica') {
                $medicalLeave = HrMedicalLeave::create([
                    'staff_id' => $payload['staff_id'],
                    'starts_at' => $payload['starts_on'],
                    'ends_at' => $payload['ends_on'],
                    'days' => $payload['quantity'],
                    'affects_payroll' => $payload['affects_payroll'] ?? true,
                    'status' => $payload['status'] === 'tramitada' ? 'tramitada' : 'ingresada',
                    'notes' => $payload['notes'] ?? null,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
            }
            $record = HrAbsenceRecord::create($payload + [
                'medical_leave_id' => $medicalLeave?->id,
                'source' => 'manual_fuera_plataforma',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->ledger->syncBalanceMovement($record, $request->user()->id);

            return $record;
        });

        return response()->json(['message' => 'Ausencia registrada.', 'data' => $record->load('staff:id,full_name,rut')], 201);
    }

    public function update(Request $request, HrAbsenceRecord $absence): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $this->validatedRecord($request);
        DB::transaction(function () use ($absence, $payload, $request): void {
            $absence->update($payload + ['updated_by' => $request->user()->id]);
            if ($absence->medicalLeave) {
                $absence->medicalLeave->update([
                    'staff_id' => $payload['staff_id'],
                    'starts_at' => $payload['starts_on'],
                    'ends_at' => $payload['ends_on'],
                    'days' => $payload['quantity'],
                    'affects_payroll' => $payload['affects_payroll'] ?? true,
                    'status' => $payload['status'] === 'tramitada' ? 'tramitada' : 'ingresada',
                    'notes' => $payload['notes'] ?? null,
                    'updated_by' => $request->user()->id,
                ]);
            }
            $this->ledger->syncBalanceMovement($absence->fresh(), $request->user()->id);
        });

        return response()->json(['message' => 'Ausencia actualizada.', 'data' => $absence->fresh()->load('staff:id,full_name,rut')]);
    }

    public function destroy(Request $request, HrAbsenceRecord $absence): JsonResponse
    {
        $this->authorizeManage($request);
        DB::transaction(function () use ($absence): void {
            $absence->balanceMovements()->delete();
            $absence->update(['status' => 'anulada']);
            $absence->delete();
        });

        return response()->json(['message' => 'Registro anulado.']);
    }

    public function updateBalance(Request $request, Staff $staff): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'administrative_entitlement' => ['required', 'numeric', 'min:0', 'max:365'],
            'administrative_adjustment' => ['nullable', 'numeric', 'between:-365,365'],
            'compensatory_entitlement' => ['required', 'numeric', 'min:0', 'max:365'],
            'compensatory_adjustment' => ['nullable', 'numeric', 'between:-365,365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $balance = HrAbsenceBalance::query()->updateOrCreate([
            'staff_id' => $staff->id,
            'year' => $payload['year'],
        ], $payload + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Saldo anual actualizado.', 'data' => $balance]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->can($request, 'rrhh.ausencias.exportar'), 403);
        $year = (int) $request->integer('year', now()->year);
        $records = HrAbsenceRecord::query()->with('staff:id,full_name,rut')->whereYear('starts_on', $year)->orderBy('starts_on')->get();

        return response()->streamDownload(function () use ($records): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Funcionario/a', 'RUT', 'Tipo', 'Inicio', 'Hora inicio', 'Término', 'Hora término', 'Cantidad', 'Unidad', 'Estado', 'Origen', 'Observaciones'], ';');
            foreach ($records as $record) {
                fputcsv($out, [$record->staff?->full_name, $record->staff?->rut, $record->absence_type, $record->starts_on?->format('d-m-Y'), $record->starts_at, $record->ends_on?->format('d-m-Y'), $record->ends_at, $record->quantity, $record->unit, $record->status, $record->source, $record->notes], ';');
            }
            fclose($out);
        }, 'ausencias-'.$year.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedRecord(Request $request): array
    {
        $payload = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'absence_type' => ['required', 'in:licencia_medica,dia_administrativo,dia_compensatorio,permiso_autorizado,ausencia_injustificada,otro'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'starts_at' => ['nullable', 'required_if:unit,horas', 'date_format:H:i'],
            'ends_at' => ['nullable', 'required_if:unit,horas', 'date_format:H:i'],
            'quantity' => ['nullable', 'numeric', 'min:0.25', 'max:8760'],
            'unit' => ['required', 'in:dias,horas'],
            'rest_type' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:registrada,pendiente,pendiente_regularizacion,justificada,tramitada,cerrada,anulada'],
            'affects_attendance' => ['boolean'],
            'affects_payroll' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
        if ($payload['unit'] === 'horas') {
            $startsAt = CarbonImmutable::parse($payload['starts_on'].' '.$payload['starts_at']);
            $endsAt = CarbonImmutable::parse($payload['ends_on'].' '.$payload['ends_at']);
            $minutes = $startsAt->diffInMinutes($endsAt, false);

            if ($minutes < 15) {
                throw ValidationException::withMessages([
                    'ends_at' => 'La hora de término debe ser posterior a la hora de inicio, con una duración mínima de 15 minutos.',
                ]);
            }

            $payload['quantity'] = round($minutes / 60, 2);
        } else {
            $payload['starts_at'] = null;
            $payload['ends_at'] = null;
        }

        if ($payload['unit'] === 'dias' && ! isset($payload['quantity'])) {
            $payload['quantity'] = CarbonImmutable::parse($payload['starts_on'])->diffInDays(CarbonImmutable::parse($payload['ends_on'])) + 1;
        }

        return $payload;
    }

    private function catalogs(): array
    {
        return [
            'types' => [
                ['value' => 'licencia_medica', 'label' => 'Licencia médica'],
                ['value' => 'dia_administrativo', 'label' => 'Día administrativo'],
                ['value' => 'dia_compensatorio', 'label' => 'Día compensatorio'],
                ['value' => 'permiso_autorizado', 'label' => 'Permiso autorizado'],
                ['value' => 'ausencia_injustificada', 'label' => 'Ausencia por regularizar'],
                ['value' => 'otro', 'label' => 'Otra ausencia'],
            ],
            'statuses' => [
                ['value' => 'registrada', 'label' => 'Registrada'],
                ['value' => 'pendiente', 'label' => 'Pendiente'],
                ['value' => 'pendiente_regularizacion', 'label' => 'Pendiente de regularización'],
                ['value' => 'justificada', 'label' => 'Justificada'],
                ['value' => 'tramitada', 'label' => 'Tramitada'],
                ['value' => 'cerrada', 'label' => 'Cerrada'],
            ],
        ];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($this->can($request, 'rrhh.ausencias.ver'), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->can($request, 'rrhh.ausencias.gestionar'), 403);
    }

    private function can(Request $request, string $permission): bool
    {
        return $request->user()->isSuperAdmin() || $request->user()->hasPermission($permission);
    }
}
