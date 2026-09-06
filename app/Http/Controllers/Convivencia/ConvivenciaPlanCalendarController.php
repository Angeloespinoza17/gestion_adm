<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaPlanAction;
use App\Models\Convivencia\ConvivenciaPlanActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ConvivenciaPlanCalendarController extends Controller
{
    public function __invoke(Request $request, ConvivenciaPlan $plan): JsonResponse
    {
        $this->authorize('view', $plan);
        $payload = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);
        $start = Carbon::parse($payload['start'])->startOfDay();
        $end = Carbon::parse($payload['end'])->startOfDay();
        abort_if($start->diffInDays($end) > 400, 422, 'El rango del calendario no puede superar 400 días.');

        $actions = $plan->actions()
            ->orderBy('sort_order')
            ->get()
            ->filter(function (ConvivenciaPlanAction $action) use ($start, $end, $plan): bool {
                if ($action->starts_on) {
                    $actionEnd = $action->ends_on?->copy()->addDay() ?: $action->starts_on->copy()->addDay();

                    return $action->starts_on->lt($end) && $actionEnd->gt($start);
                }

                if (! $action->planned_month || ! $plan->calendar_year) {
                    return false;
                }

                $monthStart = Carbon::create((int) $plan->calendar_year, (int) $action->planned_month, 1)->startOfDay();
                $monthEnd = $monthStart->copy()->addMonth();

                return $monthStart->lt($end) && $monthEnd->gt($start);
            });

        $activities = ConvivenciaPlanActivity::query()
            ->whereHas('action', fn ($query) => $query->where('plan_id', $plan->id))
            ->where('starts_at', '<', $end)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $start))
            ->with(['action:id,title', 'activityType:id,code,name,color'])
            ->orderBy('starts_at')
            ->get();

        $events = $actions->map(function (ConvivenciaPlanAction $action) use ($plan): array {
            $monthStart = $action->planned_month
                ? Carbon::create((int) $plan->calendar_year, (int) $action->planned_month, 1)
                : null;
            $eventStart = $action->starts_on?->toDateString() ?: $monthStart?->toDateString();
            $eventEnd = $action->ends_on?->copy()->addDay()->toDateString() ?: $monthStart?->copy()->endOfMonth()->addDay()->toDateString();

            return [
                'id' => "action-{$action->id}",
                'title' => $action->title,
                'start' => $eventStart,
                'end' => $eventEnd,
                'allDay' => true,
                'backgroundColor' => $this->color($action->status),
                'borderColor' => $this->color($action->status),
                'extendedProps' => ['type' => 'action', 'action_id' => $action->id, 'status' => $action->status],
            ];
        })->concat($activities->map(fn (ConvivenciaPlanActivity $activity) => [
            'id' => "activity-{$activity->id}",
            'title' => $activity->title,
            'start' => $activity->starts_at?->toIso8601String(),
            'end' => $activity->ends_at?->toIso8601String(),
            'allDay' => false,
            'backgroundColor' => $this->color($activity->status),
            'borderColor' => $this->color($activity->status),
            'extendedProps' => [
                'type' => 'activity',
                'activity_id' => $activity->id,
                'action_id' => $activity->plan_action_id,
                'action_title' => $activity->action?->title,
                'status' => $activity->status,
                'location' => $activity->location,
                'activity_type' => $activity->activityType?->name ?: $activity->activity_type_label,
            ],
        ]))->values();

        return response()->json(['data' => $events]);
    }

    private function color(string $status): string
    {
        return match ($status) {
            'completada', 'realizada' => '#19866f',
            'en_ejecucion' => '#3265d4',
            'postergada' => '#c47716',
            'cancelada' => '#9ba6b2',
            default => '#5968dc',
        };
    }
}
