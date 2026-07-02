<?php

namespace App\Http\Controllers\RelevantCalendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\RelevantCalendar\StoreCalendarInstitutionRequest;
use App\Http\Requests\RelevantCalendar\UpdateCalendarInstitutionRequest;
use App\Models\CalendarEvent;
use App\Models\CalendarInstitution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarInstitutionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageInstitutions', CalendarEvent::class);

        $search = trim((string) $request->query('search'));
        $active = $request->query('active');

        $query = CalendarInstitution::query()
            ->withCount('events')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('website_url', 'like', "%{$search}%");
                });
            });

        if ($active !== null && $active !== '') {
            $query->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCalendarInstitutionRequest $request): JsonResponse
    {
        $this->authorize('manageInstitutions', CalendarEvent::class);

        $payload = $request->validated();
        $payload['slug'] = $this->generateSlug($payload['name']);

        $institution = CalendarInstitution::query()->create($payload);

        return response()->json([
            'message' => 'Institución creada correctamente.',
            'data' => $institution,
        ], 201);
    }

    public function update(UpdateCalendarInstitutionRequest $request, CalendarInstitution $calendarInstitution): JsonResponse
    {
        $this->authorize('manageInstitutions', CalendarEvent::class);

        $payload = $request->validated();
        $payload['slug'] = $this->generateSlug($payload['name'], $calendarInstitution->id);
        $calendarInstitution->update($payload);

        return response()->json([
            'message' => 'Institución actualizada correctamente.',
            'data' => $calendarInstitution,
        ]);
    }

    public function destroy(CalendarInstitution $calendarInstitution): JsonResponse
    {
        $this->authorize('manageInstitutions', CalendarEvent::class);

        $calendarInstitution->delete();

        return response()->json([
            'message' => 'Institución eliminada correctamente.',
        ]);
    }

    public function setActive(Request $request, CalendarInstitution $calendarInstitution): JsonResponse
    {
        $this->authorize('manageInstitutions', CalendarEvent::class);

        $payload = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $calendarInstitution->update(['is_active' => $payload['is_active']]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $calendarInstitution,
        ]);
    }

    private function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'institucion';
        $slug = $base;
        $counter = 2;

        while (
            CalendarInstitution::query()
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
