<?php

namespace App\Http\Controllers\RelevantCalendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\RelevantCalendar\StoreCalendarProcessTypeRequest;
use App\Http\Requests\RelevantCalendar\UpdateCalendarProcessTypeRequest;
use App\Models\CalendarEvent;
use App\Models\CalendarProcessType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarProcessTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageTypes', CalendarEvent::class);

        $search = trim((string) $request->query('search'));
        $active = $request->query('active');

        $query = CalendarProcessType::query()
            ->withCount('events')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        if ($active !== null && $active !== '') {
            $query->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCalendarProcessTypeRequest $request): JsonResponse
    {
        $this->authorize('manageTypes', CalendarEvent::class);

        $payload = $request->validated();
        $payload['slug'] = $this->generateSlug($payload['name']);

        $type = CalendarProcessType::query()->create($payload);

        return response()->json([
            'message' => 'Tipo de proceso creado correctamente.',
            'data' => $type,
        ], 201);
    }

    public function update(UpdateCalendarProcessTypeRequest $request, CalendarProcessType $calendarProcessType): JsonResponse
    {
        $this->authorize('manageTypes', CalendarEvent::class);

        $payload = $request->validated();
        $payload['slug'] = $this->generateSlug($payload['name'], $calendarProcessType->id);
        $calendarProcessType->update($payload);

        return response()->json([
            'message' => 'Tipo de proceso actualizado correctamente.',
            'data' => $calendarProcessType,
        ]);
    }

    public function destroy(CalendarProcessType $calendarProcessType): JsonResponse
    {
        $this->authorize('manageTypes', CalendarEvent::class);

        $calendarProcessType->delete();

        return response()->json([
            'message' => 'Tipo de proceso eliminado correctamente.',
        ]);
    }

    public function setActive(Request $request, CalendarProcessType $calendarProcessType): JsonResponse
    {
        $this->authorize('manageTypes', CalendarEvent::class);

        $payload = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $calendarProcessType->update(['is_active' => $payload['is_active']]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $calendarProcessType,
        ]);
    }

    private function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tipo-proceso';
        $slug = $base;
        $counter = 2;

        while (
            CalendarProcessType::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
