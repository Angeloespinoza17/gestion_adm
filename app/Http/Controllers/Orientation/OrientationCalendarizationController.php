<?php

namespace App\Http\Controllers\Orientation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orientation\SaveOrientationCalendarizationEntryRequest;
use App\Models\Orientation\OrientationCalendarizationEntry;
use App\Models\Orientation\OrientationPlan;
use App\Services\Orientation\OrientationCalendarizationReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrientationCalendarizationController extends Controller
{
    public function __construct(
        private readonly OrientationCalendarizationReferenceService $reference,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);
        $year = (int) ($validated['year'] ?? today()->year);
        $plan = OrientationPlan::query()
            ->where('year', $year)
            ->first(['id', 'year', 'title', 'status']);

        $entries = collect();
        if ($plan && Schema::hasTable('orientation_calendarization_entries')) {
            $entries = OrientationCalendarizationEntry::query()
                ->where('orientation_plan_id', $plan->id)
                ->with('action:id,title')
                ->orderBy('start_date')
                ->orderBy('level_group')
                ->orderBy('id')
                ->get([
                    'id',
                    'orientation_plan_id',
                    'orientation_action_id',
                    'level_group',
                    'title',
                    'description',
                    'category',
                    'start_date',
                    'end_date',
                    'status',
                    'source_key',
                    'source_label',
                ])
                ->map(fn (OrientationCalendarizationEntry $entry) => $this->serialize($entry));
        }

        $isReferencePreview = $entries->isEmpty() && $year === OrientationCalendarizationReferenceService::REFERENCE_YEAR;
        if ($isReferencePreview) {
            $entries = collect($this->reference->entries($year));
        }

        $availableYears = OrientationPlan::query()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value) => (int) $value)
            ->push($year)
            ->push(OrientationCalendarizationReferenceService::REFERENCE_YEAR)
            ->unique()
            ->sortDesc()
            ->values();
        $user = $request->user();

        return response()->json([
            'selected_year' => $year,
            'available_years' => $availableYears,
            'plan' => $plan,
            'layers' => $this->reference->layers(),
            'categories' => $this->reference->categories(),
            'entries' => $entries->values(),
            'actions' => $plan ? $plan->actions()
                ->get(['id', 'title', 'start_date', 'end_date'])
                ->map(fn ($action) => [
                    'id' => $action->id,
                    'title' => $action->title,
                    'start_date' => optional($action->start_date)->toDateString(),
                    'end_date' => optional($action->end_date)->toDateString(),
                ])->values() : [],
            'is_reference_preview' => $isReferencePreview,
            'reference_available' => $year === OrientationCalendarizationReferenceService::REFERENCE_YEAR,
            'reference_year' => OrientationCalendarizationReferenceService::REFERENCE_YEAR,
            'reference_note' => 'La calendarización toma las fechas 2026 indicadas por los nombres y semanas de los archivos. Los encabezados internos que señalan 2025 no se replican por ser inconsistentes con esas fechas.',
            'summary' => [
                'total' => $entries->count(),
                'by_layer' => $entries->groupBy('level_group')->map->count(),
                'months' => $entries->pluck('start_date')->filter()->map(fn ($date) => substr((string) $date, 0, 7))->unique()->count(),
            ],
            'capabilities' => [
                'can_view' => (bool) $user?->hasPermission('orientation.view'),
                'can_manage_plan' => (bool) $user?->hasPermission('orientation.manage_plan'),
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function importReference(Request $request, OrientationPlan $plan): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('orientation.manage_plan'), 403);
        abort_if(
            (int) $plan->year !== OrientationCalendarizationReferenceService::REFERENCE_YEAR,
            422,
            'Los documentos de referencia corresponden a la calendarización 2026.'
        );

        $referenceEntries = $this->reference->entries((int) $plan->year);
        $before = OrientationCalendarizationEntry::query()
            ->where('orientation_plan_id', $plan->id)
            ->whereNotNull('source_key')
            ->count();
        $now = now();
        $userId = $request->user()?->id;

        DB::transaction(function () use ($referenceEntries, $plan, $now, $userId): void {
            collect($referenceEntries)
                ->map(fn (array $entry) => [
                    'orientation_plan_id' => $plan->id,
                    'orientation_action_id' => null,
                    'level_group' => $entry['level_group'],
                    'title' => $entry['title'],
                    'description' => null,
                    'category' => $entry['category'],
                    'start_date' => $entry['start_date'],
                    'end_date' => $entry['end_date'],
                    'status' => 'planned',
                    'source_key' => $entry['source_key'],
                    'source_label' => $entry['source_label'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->chunk(100)
                ->each(fn ($chunk) => DB::table('orientation_calendarization_entries')->insertOrIgnore($chunk->all()));
        });

        $after = OrientationCalendarizationEntry::query()
            ->where('orientation_plan_id', $plan->id)
            ->whereNotNull('source_key')
            ->count();

        return response()->json([
            'message' => $after > $before
                ? 'Calendarización de referencia guardada para el plan anual.'
                : 'La calendarización de referencia ya estaba guardada; no se duplicaron actividades.',
            'imported' => $after - $before,
            'total_reference_entries' => $after,
        ]);
    }

    public function store(
        SaveOrientationCalendarizationEntryRequest $request,
        OrientationPlan $plan
    ): JsonResponse {
        $entry = OrientationCalendarizationEntry::query()->create([
            ...$request->validated(),
            'orientation_plan_id' => $plan->id,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Actividad incorporada a la calendarización.',
            'data' => $this->serialize($entry->load('action:id,title')),
        ], 201);
    }

    public function update(
        SaveOrientationCalendarizationEntryRequest $request,
        OrientationCalendarizationEntry $calendarizationEntry
    ): JsonResponse {
        $calendarizationEntry->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Actividad calendarizada actualizada.',
            'data' => $this->serialize($calendarizationEntry->fresh()->load('action:id,title')),
        ]);
    }

    public function destroy(OrientationCalendarizationEntry $calendarizationEntry): JsonResponse
    {
        $calendarizationEntry->delete();

        return response()->json([
            'message' => 'Actividad retirada de la calendarización.',
        ]);
    }

    private function serialize(OrientationCalendarizationEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'orientation_action_id' => $entry->orientation_action_id,
            'action' => $entry->action ? ['id' => $entry->action->id, 'title' => $entry->action->title] : null,
            'level_group' => $entry->level_group,
            'title' => $entry->title,
            'description' => $entry->description,
            'category' => $entry->category,
            'start_date' => optional($entry->start_date)->toDateString(),
            'end_date' => optional($entry->end_date)->toDateString(),
            'status' => $entry->status,
            'source_key' => $entry->source_key,
            'source_label' => $entry->source_label,
            'is_reference' => false,
        ];
    }
}
