<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionAccidentRequest;
use App\Http\Requests\RiskPrevention\StoreRiskPreventionAccidentFollowUpRequest;
use App\Models\RiskPrevention\RiskPreventionAccident;
use App\Models\RiskPrevention\RiskPreventionAccidentFollowUp;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RiskPreventionAccidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionAccident::class);

        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('accident_type'));
        $status = trim((string) $request->query('case_status'));
        $eventType = trim((string) $request->query('event_type'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        $accidents = RiskPreventionAccident::query()
            ->with(['followUps', 'staff:id,full_name,rut,cargo_id', 'staff.cargo:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('involved_person_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('accident_type', $type))
            ->when($status !== '', fn ($query) => $query->where('case_status', $status))
            ->when($eventType !== '', fn ($query) => $query->where('event_type', $eventType))
            ->when($from !== '', fn ($query) => $query->whereDate('occurred_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('occurred_at', '<=', $to))
            ->orderByDesc('occurred_at')
            ->paginate(min(max((int) $request->query('per_page', 12), 1), 100));

        $this->appendAnnualLostDays($accidents->getCollection());
        $summaryYear = min(max((int) $request->query('summary_year', now()->year), 2000), 2100);

        return response()->json(array_merge($accidents->toArray(), [
            'summary' => $this->annualSummary($summaryYear),
        ]));
    }

    public function store(SaveRiskPreventionAccidentRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionAccident::class);

        $payload = $request->validated();
        $payload = $this->applyStaffSnapshot($payload);
        if (($payload['case_status'] ?? null) === 'cerrado') {
            $payload['closed_at'] = now();
        }

        $accident = RiskPreventionAccident::query()->create(array_merge(
            $payload,
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        ));

        return response()->json([
            'message' => 'Accidente registrado correctamente.',
            'data' => $accident->load('followUps'),
        ], 201);
    }

    public function show(RiskPreventionAccident $accident): JsonResponse
    {
        $this->authorize('view', $accident);

        return response()->json([
            'data' => $accident->load('followUps'),
        ]);
    }

    public function update(SaveRiskPreventionAccidentRequest $request, RiskPreventionAccident $accident): JsonResponse
    {
        $this->authorize('update', $accident);

        $payload = $request->validated();
        $payload = $this->applyStaffSnapshot($payload);
        $payload['updated_by'] = $request->user()->id;
        $payload['closed_at'] = ($payload['case_status'] ?? null) === 'cerrado'
            ? ($accident->closed_at ?: now())
            : null;

        $accident->update($payload);

        return response()->json([
            'message' => 'Accidente actualizado correctamente.',
            'data' => $accident->fresh()->load('followUps'),
        ]);
    }

    public function destroy(RiskPreventionAccident $accident): JsonResponse
    {
        $this->authorize('delete', $accident);
        $accident->delete();

        return response()->json([
            'message' => 'Registro de accidente eliminado correctamente.',
        ]);
    }

    public function storeFollowUp(StoreRiskPreventionAccidentFollowUpRequest $request, RiskPreventionAccident $accident): JsonResponse
    {
        $this->authorize('update', $accident);

        $followUp = $accident->followUps()->create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id],
        ));

        $accident->update([
            'case_status' => $followUp->status,
            'closed_at' => $followUp->status === 'cerrado' ? now() : null,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Seguimiento registrado correctamente.',
            'data' => $accident->fresh()->load('followUps'),
        ], 201);
    }

    public function destroyFollowUp(RiskPreventionAccidentFollowUp $accidentFollowUp): JsonResponse
    {
        $accident = $accidentFollowUp->accident()->firstOrFail();
        $this->authorize('update', $accident);

        $accidentFollowUp->delete();

        return response()->json([
            'message' => 'Seguimiento eliminado correctamente.',
            'data' => $accident->fresh()->load('followUps'),
        ]);
    }

    private function applyStaffSnapshot(array $payload): array
    {
        if (($payload['accident_type'] ?? null) !== 'staff') {
            $payload['staff_id'] = null;
            $payload['event_type'] = 'accidente';
            $payload['lost_days'] = 0;
            $payload['injured_body_part'] = null;

            return $payload;
        }

        if (filled($payload['staff_id'] ?? null)) {
            $staff = Staff::query()->findOrFail($payload['staff_id']);
            $payload['involved_person_name'] = $staff->full_name;
            $payload['involved_person_identifier'] = $staff->rut;
        }

        return $payload;
    }

    private function appendAnnualLostDays(Collection $pageItems): void
    {
        if ($pageItems->isEmpty()) {
            return;
        }

        $staffCases = $pageItems->where('accident_type', 'staff');
        if ($staffCases->isEmpty()) {
            return;
        }

        $years = $staffCases
            ->map(fn (RiskPreventionAccident $accident) => $accident->occurred_at?->year)
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $staffIds = $staffCases->pluck('staff_id')->filter()->unique()->values();
        $identifiers = $staffCases
            ->pluck('involved_person_identifier')
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->values();
        $identifierToStaffId = $staffCases
            ->filter(fn (RiskPreventionAccident $accident) => $accident->staff_id && filled($accident->involved_person_identifier))
            ->mapWithKeys(fn (RiskPreventionAccident $accident) => [
                Str::lower(trim((string) $accident->involved_person_identifier)) => (int) $accident->staff_id,
            ])
            ->all();
        $names = $staffCases
            ->whereNull('staff_id')
            ->filter(fn ($item) => blank($item->involved_person_identifier))
            ->pluck('involved_person_name')
            ->filter()
            ->unique()
            ->values();

        $history = RiskPreventionAccident::query()
            ->where('accident_type', 'staff')
            ->whereBetween('occurred_at', [
                Carbon::create($years->first(), 1, 1)->startOfDay(),
                Carbon::create($years->last(), 12, 31)->endOfDay(),
            ])
            ->where(function ($query) use ($staffIds, $identifiers, $names) {
                if ($staffIds->isNotEmpty()) {
                    $query->whereIn('staff_id', $staffIds);
                }
                if ($identifiers->isNotEmpty()) {
                    $query->orWhere(function ($query) use ($identifiers) {
                        $query->whereNull('staff_id')->whereIn('involved_person_identifier', $identifiers);
                    });
                }
                if ($names->isNotEmpty()) {
                    $query->orWhere(function ($query) use ($names) {
                        $query->whereNull('staff_id')
                            ->whereNull('involved_person_identifier')
                            ->whereIn('involved_person_name', $names);
                    });
                }
            })
            ->get([
                'staff_id',
                'involved_person_identifier',
                'involved_person_name',
                'occurred_at',
                'lost_days',
            ])
            ->groupBy(fn (RiskPreventionAccident $accident) => $this->annualIdentityKey($accident, $identifierToStaffId))
            ->map(fn (Collection $items) => (int) $items->sum('lost_days'));

        $pageItems->each(function (RiskPreventionAccident $accident) use ($history, $identifierToStaffId): void {
            $accident->setAttribute(
                'annual_lost_days',
                $accident->accident_type === 'staff'
                    ? (int) $history->get($this->annualIdentityKey($accident, $identifierToStaffId), 0)
                    : 0,
            );
        });
    }

    private function annualIdentityKey(RiskPreventionAccident $accident, array $identifierToStaffId = []): string
    {
        $year = $accident->occurred_at?->year ?: 0;
        if ($accident->staff_id) {
            return "{$year}|staff:{$accident->staff_id}";
        }

        if (filled($accident->involved_person_identifier)) {
            $identifier = Str::lower(trim((string) $accident->involved_person_identifier));
            if (isset($identifierToStaffId[$identifier])) {
                return "{$year}|staff:{$identifierToStaffId[$identifier]}";
            }

            return "{$year}|identifier:{$identifier}";
        }

        return "{$year}|name:".Str::lower(trim((string) $accident->involved_person_name));
    }

    private function annualSummary(int $year): array
    {
        $summary = RiskPreventionAccident::query()
            ->where('accident_type', 'staff')
            ->whereBetween('occurred_at', [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ])
            ->selectRaw('COUNT(*) as total_cases')
            ->selectRaw('COALESCE(SUM(lost_days), 0) as lost_days')
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'accidente' THEN 1 ELSE 0 END), 0) as accidents")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'enfermedad_profesional' THEN 1 ELSE 0 END), 0) as occupational_diseases")
            ->first();

        return [
            'year' => $year,
            'total_cases' => (int) ($summary?->total_cases ?? 0),
            'lost_days' => (int) ($summary?->lost_days ?? 0),
            'accidents' => (int) ($summary?->accidents ?? 0),
            'occupational_diseases' => (int) ($summary?->occupational_diseases ?? 0),
        ];
    }
}
