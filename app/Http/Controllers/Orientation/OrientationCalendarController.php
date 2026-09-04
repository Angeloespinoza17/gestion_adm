<?php

namespace App\Http\Controllers\Orientation;

use App\Http\Controllers\Controller;
use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationActivity;
use App\Models\Orientation\OrientationPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OrientationCalendarController extends Controller
{
    public function __invoke(Request $request, OrientationPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);
        $start = Carbon::parse($validated['start'])->startOfDay();
        $endExclusive = Carbon::parse($validated['end'])->startOfDay();
        abort_if($start->diffInDays($endExclusive) > 400, 422, 'El rango de calendario no puede superar 400 días.');

        $actions = OrientationAction::query()
            ->where('orientation_plan_id', $plan->id)
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<', $endExclusive->toDateString())
            ->where(function ($query) use ($start): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $start->toDateString());
            })
            ->orderBy('start_date')
            ->get(['id', 'title', 'start_date', 'end_date', 'status', 'progress']);

        $activities = OrientationActivity::query()
            ->whereHas('action', fn ($query) => $query->where('orientation_plan_id', $plan->id))
            ->where('starts_at', '<', $endExclusive)
            ->where(function ($query) use ($start): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $start);
            })
            ->with('action:id,title')
            ->orderBy('starts_at')
            ->get(['id', 'orientation_action_id', 'title', 'starts_at', 'ends_at', 'status', 'location']);

        $events = $actions->map(fn (OrientationAction $action) => [
            'id' => "action-{$action->id}",
            'title' => $action->title,
            'start' => optional($action->start_date)->toDateString(),
            'end' => optional($action->end_date ?: $action->start_date)?->copy()->addDay()->toDateString(),
            'allDay' => true,
            'backgroundColor' => $this->colorFor($action->status),
            'borderColor' => $this->colorFor($action->status),
            'extendedProps' => [
                'type' => 'action',
                'action_id' => $action->id,
                'status' => $action->status,
                'progress' => $action->progress,
            ],
        ])->concat($activities->map(fn (OrientationActivity $activity) => [
            'id' => "activity-{$activity->id}",
            'title' => $activity->title,
            'start' => optional($activity->starts_at)->toIso8601String(),
            'end' => optional($activity->ends_at)->toIso8601String(),
            'allDay' => false,
            'backgroundColor' => $this->colorFor($activity->status),
            'borderColor' => $this->colorFor($activity->status),
            'extendedProps' => [
                'type' => 'activity',
                'activity_id' => $activity->id,
                'action_id' => $activity->orientation_action_id,
                'action_title' => $activity->action?->title,
                'status' => $activity->status,
                'location' => $activity->location,
            ],
        ]))->values();

        return response()->json(['data' => $events]);
    }

    private function colorFor(string $status): string
    {
        return match ($status) {
            'completed' => '#16866f',
            'in_progress' => '#3265d4',
            'postponed' => '#c47716',
            'cancelled' => '#9ba6b2',
            default => '#6d55c8',
        };
    }
}
