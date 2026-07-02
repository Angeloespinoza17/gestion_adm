<?php

namespace App\Http\Controllers\RelevantCalendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\RelevantCalendar\SaveCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\CalendarInstitution;
use App\Models\CalendarProcessType;
use App\Models\Department;
use App\Models\User;
use App\Services\RelevantCalendar\CalendarEventAccessService;
use App\Services\RelevantCalendar\CalendarEventService;
use App\Services\RelevantCalendar\CalendarRecurrenceService;
use App\Services\RelevantCalendar\CalendarReminderService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarEventController extends Controller
{
    public function __construct(
        private readonly CalendarEventAccessService $accessService,
        private readonly CalendarEventService $eventService,
        private readonly CalendarRecurrenceService $recurrenceService,
        private readonly CalendarReminderService $reminderService,
    ) {
    }

    public function catalogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $user = $request->user();

        return response()->json([
            'process_types' => CalendarProcessType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'color']),
            'institutions' => CalendarInstitution::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'color', 'website_url']),
            'departments' => Department::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'color', 'responsible_staff_id']),
            'users' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email', 'staff_id']),
            'responsible_users' => User::query()
                ->where('active', true)
                ->where('user_type', 'staff')
                ->whereNotNull('staff_id')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id']),
            'priorities' => CalendarEvent::PRIORITY_OPTIONS,
            'statuses' => CalendarEvent::STATUS_OPTIONS,
            'reminder_types' => CalendarEvent::REMINDER_TYPE_OPTIONS,
            'recurrence_types' => CalendarEvent::RECURRENCE_FREQUENCY_OPTIONS,
            'weekdays' => CalendarEvent::WEEKDAY_OPTIONS,
            'participant_roles' => CalendarEvent::PARTICIPANT_ROLE_OPTIONS,
            'managed_department_ids' => $this->accessService->managedDepartmentIds($user),
            'capabilities' => [
                'can_create' => $user->can('create', CalendarEvent::class),
                'can_export' => $user->can('export', CalendarEvent::class),
                'can_manage_types' => $user->can('manageTypes', CalendarEvent::class),
                'can_manage_institutions' => $user->can('manageInstitutions', CalendarEvent::class),
                'can_manage_all' => $this->accessService->canManageAll($user),
                'can_manage_departments' => $this->accessService->canManageDepartments($user),
                'can_view_all' => $this->accessService->canViewAll($user),
            ],
            'stage_templates' => [
                ['key' => 'preparation_start', 'title' => 'Inicio de preparación'],
                ['key' => 'internal_review_deadline', 'title' => 'Fecha límite de revisión interna'],
                ['key' => 'approval_deadline', 'title' => 'Fecha límite de aprobación'],
                ['key' => 'submission_deadline', 'title' => 'Fecha de envío o declaración'],
                ['key' => 'payment_deadline', 'title' => 'Fecha de pago'],
                ['key' => 'closing_date', 'title' => 'Fecha de cierre o archivo'],
            ],
            'current_user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->staff_id,
            ],
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $baseQuery = $this->operationalQuery($request->user());
        $baseQuery = $this->applyFilters($baseQuery, $request);

        $today = now(config('app.timezone'))->toDateString();
        $currentMonthStart = now(config('app.timezone'))->startOfMonth()->toDateString();
        $currentMonthEnd = now(config('app.timezone'))->endOfMonth()->toDateString();
        $upcomingLimitDate = now(config('app.timezone'))->copy()->addDays(30)->toDateString();

        $upcomingQuery = (clone $baseQuery)
            ->whereNotIn('status', CalendarEvent::TERMINAL_STATUSES)
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$today])
            ->whereRaw('COALESCE(end_date, start_date) <= ?', [$upcomingLimitDate])
            ->orderByRaw('COALESCE(end_date, start_date) asc')
            ->orderByRaw($this->prioritySortExpression());

        $overdueQuery = (clone $baseQuery)
            ->whereNotIn('status', CalendarEvent::TERMINAL_STATUSES)
            ->whereRaw('COALESCE(end_date, start_date) < ?', [$today])
            ->orderByRaw('COALESCE(end_date, start_date) asc');

        $monthQuery = (clone $baseQuery)
            ->where(function (Builder $builder) use ($currentMonthStart, $currentMonthEnd) {
                $builder
                    ->whereBetween('start_date', [$currentMonthStart, $currentMonthEnd])
                    ->orWhereBetween('end_date', [$currentMonthStart, $currentMonthEnd])
                    ->orWhere(function (Builder $range) use ($currentMonthStart, $currentMonthEnd) {
                        $range
                            ->where('start_date', '<=', $currentMonthStart)
                            ->where('end_date', '>=', $currentMonthEnd);
                    });
            })
            ->orderByRaw('COALESCE(end_date, start_date) asc');

        $historyQuery = (clone $baseQuery)
            ->where(function (Builder $builder) {
                $builder
                    ->whereNotNull('completed_at')
                    ->orWhereIn('status', CalendarEvent::TERMINAL_STATUSES);
            })
            ->orderByDesc('completed_at')
            ->orderByDesc('updated_at');

        $reminderEvents = (clone $baseQuery)
            ->with(['reminders', 'institution:id,name', 'responsibleUser:id,name'])
            ->limit(100)
            ->get();

        return response()->json([
            'stats' => [
                'active' => (clone $baseQuery)->count(),
                'upcoming' => (clone $upcomingQuery)->count(),
                'overdue' => (clone $overdueQuery)->count(),
                'current_month' => (clone $monthQuery)->count(),
                'recurring' => $this->visibleSeriesQuery($request->user())->count(),
            ],
            'upcoming' => $upcomingQuery
                ->with($this->listRelations())
                ->limit(12)
                ->get(),
            'overdue' => $overdueQuery
                ->with($this->listRelations())
                ->limit(12)
                ->get(),
            'current_month' => $monthQuery
                ->with($this->listRelations())
                ->limit(20)
                ->get(),
            'history' => $historyQuery
                ->with(array_merge($this->listRelations(), ['completedBy:id,name,email']))
                ->limit(20)
                ->get(),
            'alerts' => $this->reminderService
                ->dueAlerts($reminderEvents),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $query = $this->operationalQuery($request->user())
            ->with($this->listRelations());

        $query = $this->applyFilters($query, $request);
        $query
            ->orderByRaw('COALESCE(end_date, start_date) asc')
            ->orderByRaw($this->prioritySortExpression())
            ->orderByDesc('id');

        return response()->json(
            $query->paginate((int) $request->query('per_page', 15))
        );
    }

    public function calendarFeed(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $from = Carbon::parse((string) $request->query('date_from', now(config('app.timezone'))->startOfMonth()->toDateString()), config('app.timezone'))->startOfDay();
        $to = Carbon::parse((string) $request->query('date_to', now(config('app.timezone'))->endOfMonth()->toDateString()), config('app.timezone'))->startOfDay();

        $this->visibleSeriesQuery($request->user())->get()->each(function (CalendarEvent $master) use ($from, $to) {
            $this->recurrenceService->ensureOccurrencesForEvent($master, $from, $to);
        });

        $query = $this->calendarQuery($request->user())
            ->with($this->listRelations());
        $query = $this->applyFilters($query, $request);

        $query->where(function (Builder $builder) use ($from, $to) {
            $builder
                ->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
                ->orWhereBetween('end_date', [$from->toDateString(), $to->toDateString()])
                ->orWhere(function (Builder $range) use ($from, $to) {
                    $range
                        ->where('start_date', '<=', $from->toDateString())
                        ->where('end_date', '>=', $to->toDateString());
                });
        });

        return response()->json([
            'data' => $query->get()->map(fn (CalendarEvent $event) => $this->toCalendarEntry($event))->values(),
        ]);
    }

    public function show(CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('view', $calendarEvent);

        return response()->json([
            'data' => $calendarEvent->load([
                'processType:id,name,color',
                'institution:id,name,website_url,color',
                'department:id,name,color,responsible_staff_id',
                'responsibleUser:id,name,email,staff_id',
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
                'completedBy:id,name,email',
                'eventUsers.user:id,name,email,staff_id',
                'reminders',
                'attachments.uploadedBy:id,name,email',
                'logs.user:id,name,email',
                'parentEvent.processType:id,name,color',
                'parentEvent.institution:id,name,color',
                'childEvents.processType:id,name,color',
                'childEvents.institution:id,name,color',
                'childEvents.responsibleUser:id,name,email',
                'childEvents.eventUsers.user:id,name,email',
                'childEvents.reminders',
            ]),
        ]);
    }

    public function store(SaveCalendarEventRequest $request): JsonResponse
    {
        $this->authorize('create', CalendarEvent::class);

        $event = $this->eventService->createEvent($request->validated(), $request->user());

        return response()->json([
            'message' => 'Evento creado correctamente.',
            'data' => $event,
        ], 201);
    }

    public function update(SaveCalendarEventRequest $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('update', $calendarEvent);

        $event = $this->eventService->updateEvent($calendarEvent, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Evento actualizado correctamente.',
            'data' => $event,
        ]);
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('delete', $calendarEvent);

        $this->eventService->deleteEvent($calendarEvent, request()->user());

        return response()->json([
            'message' => 'Evento eliminado correctamente.',
        ]);
    }

    private function operationalQuery(User $user): Builder
    {
        return $this->accessService->visibleEventsQuery($user)
            ->where('event_kind', '!=', CalendarEvent::KIND_SERIES_MASTER)
            ->where(function (Builder $builder) {
                $builder
                    ->where('event_kind', '!=', CalendarEvent::KIND_PROCESS)
                    ->orWhereDoesntHave('childEvents');
            });
    }

    private function calendarQuery(User $user): Builder
    {
        return $this->accessService->visibleEventsQuery($user)
            ->where('event_kind', '!=', CalendarEvent::KIND_SERIES_MASTER)
            ->where('event_kind', '!=', CalendarEvent::KIND_PROCESS);
    }

    private function visibleSeriesQuery(User $user): Builder
    {
        return $this->accessService->visibleEventsQuery($user)
            ->where('event_kind', CalendarEvent::KIND_SERIES_MASTER);
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        $search = trim((string) $request->query('search'));
        $month = $request->query('month');
        $year = $request->query('year');
        $processTypeId = $request->query('process_type_id');
        $institutionId = $request->query('institution_id');
        $status = trim((string) $request->query('status'));
        $priority = trim((string) $request->query('priority'));
        $responsibleUserId = $request->query('responsible_user_id');
        $departmentId = $request->query('department_id');
        $dateFrom = trim((string) $request->query('date_from'));
        $dateTo = trim((string) $request->query('date_to'));
        $overdueOnly = filter_var($request->query('overdue_only'), FILTER_VALIDATE_BOOLEAN);
        $upcomingOnly = filter_var($request->query('upcoming_only'), FILTER_VALIDATE_BOOLEAN);
        $recurringOnly = filter_var($request->query('recurring_only'), FILTER_VALIDATE_BOOLEAN);
        $manualOnly = filter_var($request->query('manual_only'), FILTER_VALIDATE_BOOLEAN);

        $query
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('institution', fn (Builder $institution) => $institution->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('responsibleUser', fn (Builder $responsible) => $responsible->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($processTypeId, fn (Builder $builder) => $builder->where('process_type_id', $processTypeId))
            ->when($institutionId, fn (Builder $builder) => $builder->where('institution_id', $institutionId))
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($priority !== '', fn (Builder $builder) => $builder->where('priority', $priority))
            ->when($responsibleUserId, fn (Builder $builder) => $builder->where('responsible_user_id', $responsibleUserId))
            ->when($departmentId, fn (Builder $builder) => $builder->where('department_id', $departmentId))
            ->when($dateFrom !== '', fn (Builder $builder) => $builder->whereRaw('COALESCE(end_date, start_date) >= ?', [$dateFrom]))
            ->when($dateTo !== '', fn (Builder $builder) => $builder->where('start_date', '<=', $dateTo))
            ->when($month && $year, fn (Builder $builder) => $builder
                ->whereMonth(DB::raw('COALESCE(end_date, start_date)'), (int) $month)
                ->whereYear(DB::raw('COALESCE(end_date, start_date)'), (int) $year))
            ->when(!$month && $year, fn (Builder $builder) => $builder
                ->whereYear(DB::raw('COALESCE(end_date, start_date)'), (int) $year))
            ->when($overdueOnly, fn (Builder $builder) => $builder
                ->whereNotIn('status', CalendarEvent::TERMINAL_STATUSES)
                ->whereRaw('COALESCE(end_date, start_date) < ?', [now(config('app.timezone'))->toDateString()]))
            ->when($upcomingOnly, fn (Builder $builder) => $builder
                ->whereNotIn('status', CalendarEvent::TERMINAL_STATUSES)
                ->whereRaw('COALESCE(end_date, start_date) >= ?', [now(config('app.timezone'))->toDateString()]))
            ->when($recurringOnly, fn (Builder $builder) => $builder
                ->where('is_recurring', true))
            ->when($manualOnly, fn (Builder $builder) => $builder
                ->where('is_recurring', false));

        return $query;
    }

    /**
     * @return array<int, string>
     */
    private function listRelations(): array
    {
        return [
            'processType:id,name,color',
            'institution:id,name,color',
            'department:id,name,color',
            'responsibleUser:id,name,email',
            'parentEvent:id,title,event_kind,start_date,end_date,status',
        ];
    }

    private function toCalendarEntry(CalendarEvent $event): array
    {
        $allDay = !$event->start_time && !$event->end_time;
        $start = $allDay
            ? $event->start_date?->format('Y-m-d')
            : $this->combineDateAndTime($event->start_date?->format('Y-m-d'), $event->start_time);

        if ($allDay) {
            $endDate = ($event->end_date ?: $event->start_date)?->copy()?->addDay();
            $end = $endDate?->format('Y-m-d');
        } else {
            $end = $this->combineDateAndTime(
                ($event->end_date ?: $event->start_date)?->format('Y-m-d'),
                $event->end_time ?: $event->start_time
            );

            if ($end && $start && $end <= $start) {
                $end = Carbon::parse($start, config('app.timezone'))->addHour()->format('Y-m-d\TH:i:s');
            }
        }

        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $start,
            'end' => $end,
            'allDay' => $allDay,
            'backgroundColor' => $event->calendar_color,
            'borderColor' => $event->effective_status === 'vencido' ? '#b02a37' : $event->calendar_color,
            'textColor' => '#ffffff',
            'classNames' => array_values(array_filter([
                $event->effective_status === 'vencido' ? 'calendar-event-overdue' : null,
                $event->priority === 'critica' ? 'calendar-event-critical' : null,
                $event->event_kind === CalendarEvent::KIND_STAGE ? 'calendar-event-stage' : null,
            ])),
            'extendedProps' => [
                'priority' => $event->priority,
                'status' => $event->effective_status,
                'department' => $event->department?->name,
                'institution' => $event->institution?->name,
                'process_type' => $event->processType?->name,
                'responsible' => $event->responsibleUser?->name,
                'event_kind' => $event->event_kind,
                'parent_event_id' => $event->parent_event_id,
                'due_date' => $event->due_date,
            ],
        ];
    }

    private function combineDateAndTime(?string $date, ?string $time): ?string
    {
        if (!$date) {
            return null;
        }

        $resolvedTime = $time ? substr((string) $time, 0, 5) : '00:00';

        return Carbon::parse("{$date} {$resolvedTime}", config('app.timezone'))->format('Y-m-d\TH:i:s');
    }

    private function prioritySortExpression(): string
    {
        return "CASE priority
            WHEN 'critica' THEN 4
            WHEN 'alta' THEN 3
            WHEN 'media' THEN 2
            WHEN 'baja' THEN 1
            ELSE 0
        END DESC";
    }
}
