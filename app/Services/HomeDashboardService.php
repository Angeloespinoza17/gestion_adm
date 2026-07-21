<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\DependencyReservation;
use App\Models\InternalCommunications\InternalAnnouncement;
use App\Models\NewsPost;
use App\Models\SiteEvent;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\RelevantCalendar\CalendarEventAccessService;
use App\Services\RelevantCalendar\CalendarRecurrenceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class HomeDashboardService
{
    public function __construct(
        private readonly CalendarEventAccessService $calendarAccess,
        private readonly CalendarRecurrenceService $recurrenceService,
    ) {
    }

    public function build(User $user): array
    {
        $timezone = config('app.timezone');
        $now = now($timezone);
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $rangeStart = $now->copy()->startOfMonth()->subDays(7)->startOfDay();
        $rangeEnd = $now->copy()->endOfMonth()->addDays(45)->endOfDay();

        $calendar = $this->relevantCalendar($user, $rangeStart, $rangeEnd, $todayStart);
        $reservations = $this->reservations($user, $rangeStart, $rangeEnd, $todayStart);
        $publicEvents = $this->publicEvents($rangeStart, $rangeEnd, $todayStart);
        $news = $this->news();
        $modules = $this->accessibleModules($user);
        $internalAnnouncements = $this->internalAnnouncements($user);

        $calendarItems = collect($calendar['calendar'])
            ->merge($reservations['calendar'])
            ->merge($publicEvents['calendar'])
            ->sortBy('start')
            ->values();

        $todayItems = $calendarItems
            ->filter(fn (array $item) => $this->eventOverlapsWindow($item, $todayStart, $todayEnd))
            ->take(10)
            ->values();

        $upcomingItems = $calendarItems
            ->filter(fn (array $item) => Carbon::parse((string) $item['start'], $timezone)->greaterThan($todayEnd))
            ->take(10)
            ->values();

        return [
            'generated_at' => $now->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_name' => $user->staff?->full_name,
                'is_super_admin' => $user->isSuperAdmin(),
            ],
            'capabilities' => [
                'can_view_relevant_calendar' => $user->can('viewAny', CalendarEvent::class),
                'can_create_relevant_calendar' => $user->can('create', CalendarEvent::class),
                'can_view_reservations' => $this->hasAny($user, ['ver_reservas']),
                'can_create_reservations' => $this->hasAny($user, ['crear_reservas']),
                'can_manage_reservations' => $this->hasAny($user, ['editar_reservas', 'cancelar_reservas', 'administrar_calendario']),
                'can_manage_public_events' => $this->hasAny($user, ['gestionar_eventos']),
                'can_view_internal_communications' => $this->hasAny($user, ['ver_comunicaciones_internas']),
                'can_manage_internal_communications' => $this->hasAny($user, ['gestionar_comunicaciones_internas']),
            ],
            'metrics' => $this->metrics($todayItems, $calendar, $reservations, $publicEvents, $modules),
            'calendar' => [
                'range' => [
                    'from' => $rangeStart->toDateString(),
                    'to' => $rangeEnd->toDateString(),
                ],
                'events' => $calendarItems->all(),
            ],
            'agenda' => [
                'today' => $todayItems->all(),
                'upcoming' => $upcomingItems->all(),
            ],
            'relevant_calendar' => [
                'upcoming' => $calendar['upcoming'],
                'overdue_count' => $calendar['overdue_count'],
                'current_month_count' => $calendar['current_month_count'],
            ],
            'reservations' => [
                'upcoming' => $reservations['upcoming'],
                'pending' => $reservations['pending'],
                'upcoming_count' => $reservations['upcoming_count'],
                'pending_count' => $reservations['pending_count'],
            ],
            'public_events' => [
                'upcoming' => $publicEvents['upcoming'],
                'upcoming_count' => $publicEvents['upcoming_count'],
            ],
            'news' => $news,
            'internal_announcements' => $internalAnnouncements,
            'quick_links' => $this->quickLinks($user),
        ];
    }

    private function relevantCalendar(User $user, Carbon $rangeStart, Carbon $rangeEnd, Carbon $todayStart): array
    {
        $empty = [
            'calendar' => [],
            'upcoming' => [],
            'overdue_count' => 0,
            'current_month_count' => 0,
        ];

        if (! Schema::hasTable('calendar_events') || ! $user->can('viewAny', CalendarEvent::class)) {
            return $empty;
        }

        $this->calendarAccess
            ->visibleEventsQuery($user)
            ->where('event_kind', CalendarEvent::KIND_SERIES_MASTER)
            ->get()
            ->each(fn (CalendarEvent $master) => $this->recurrenceService->ensureOccurrencesForEvent(
                $master,
                $rangeStart->copy()->startOfDay(),
                $rangeEnd->copy()->startOfDay(),
            ));

        $baseQuery = $this->operationalCalendarQuery($user);

        $calendarEvents = (clone $baseQuery)
            ->with($this->calendarRelations())
            ->where(function (Builder $builder) use ($rangeStart, $rangeEnd) {
                $from = $rangeStart->toDateString();
                $to = $rangeEnd->toDateString();

                $builder
                    ->whereBetween('start_date', [$from, $to])
                    ->orWhereBetween('end_date', [$from, $to])
                    ->orWhere(function (Builder $range) use ($from, $to) {
                        $range
                            ->where('start_date', '<=', $from)
                            ->where('end_date', '>=', $to);
                    });
            })
            ->orderByRaw('COALESCE(end_date, start_date) asc')
            ->orderByRaw($this->prioritySortExpression())
            ->limit(120)
            ->get()
            ->map(fn (CalendarEvent $event) => $this->formatCalendarEvent($event))
            ->values()
            ->all();

        $upcomingLimit = $todayStart->copy()->addDays(30)->toDateString();

        $upcoming = (clone $baseQuery)
            ->with($this->calendarRelations())
            ->whereNotIn('status', CalendarEvent::TERMINAL_STATUSES)
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$todayStart->toDateString()])
            ->whereRaw('COALESCE(end_date, start_date) <= ?', [$upcomingLimit])
            ->orderByRaw('COALESCE(end_date, start_date) asc')
            ->orderByRaw($this->prioritySortExpression())
            ->limit(8)
            ->get()
            ->map(fn (CalendarEvent $event) => $this->formatCalendarListItem($event))
            ->values()
            ->all();

        $overdueCount = (clone $baseQuery)
            ->whereNotIn('status', CalendarEvent::TERMINAL_STATUSES)
            ->whereRaw('COALESCE(end_date, start_date) < ?', [$todayStart->toDateString()])
            ->count();

        $currentMonthCount = (clone $baseQuery)
            ->where(function (Builder $builder) use ($todayStart) {
                $monthStart = $todayStart->copy()->startOfMonth()->toDateString();
                $monthEnd = $todayStart->copy()->endOfMonth()->toDateString();

                $builder
                    ->whereBetween('start_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                    ->orWhere(function (Builder $range) use ($monthStart, $monthEnd) {
                        $range
                            ->where('start_date', '<=', $monthStart)
                            ->where('end_date', '>=', $monthEnd);
                    });
            })
            ->count();

        return [
            'calendar' => $calendarEvents,
            'upcoming' => $upcoming,
            'overdue_count' => $overdueCount,
            'current_month_count' => $currentMonthCount,
        ];
    }

    private function reservations(User $user, Carbon $rangeStart, Carbon $rangeEnd, Carbon $todayStart): array
    {
        $empty = [
            'calendar' => [],
            'upcoming' => [],
            'pending' => [],
            'upcoming_count' => 0,
            'pending_count' => 0,
        ];

        if (! Schema::hasTable('dependency_reservations') || ! $this->hasAny($user, ['ver_reservas'])) {
            return $empty;
        }

        $calendarStatuses = [DependencyReservation::STATUS_APPROVED];

        if ($this->hasAny($user, ['editar_reservas', 'cancelar_reservas', 'administrar_calendario'])) {
            $calendarStatuses[] = DependencyReservation::STATUS_PENDING;
        }

        $calendar = DependencyReservation::query()
            ->with($this->reservationRelations())
            ->whereIn('status', $calendarStatuses)
            ->where('ends_at', '>=', $rangeStart)
            ->where('starts_at', '<=', $rangeEnd)
            ->orderBy('starts_at')
            ->limit(160)
            ->get()
            ->map(fn (DependencyReservation $reservation) => $this->formatReservationEvent($reservation))
            ->values()
            ->all();

        $upcomingQuery = DependencyReservation::query()
            ->with($this->reservationRelations())
            ->whereIn('status', [DependencyReservation::STATUS_APPROVED, DependencyReservation::STATUS_PENDING])
            ->where('ends_at', '>=', $todayStart)
            ->orderBy('starts_at');

        $upcomingCount = (clone $upcomingQuery)->count();

        $upcoming = $upcomingQuery
            ->limit(8)
            ->get()
            ->map(fn (DependencyReservation $reservation) => $this->formatReservationListItem($reservation))
            ->values()
            ->all();

        $pendingQuery = DependencyReservation::query()
            ->with($this->reservationRelations())
            ->where('status', DependencyReservation::STATUS_PENDING)
            ->where('ends_at', '>=', $todayStart)
            ->orderBy('starts_at');

        $pendingCount = (clone $pendingQuery)->count();

        $pending = $pendingQuery
            ->limit(8)
            ->get()
            ->map(fn (DependencyReservation $reservation) => $this->formatReservationListItem($reservation))
            ->values()
            ->all();

        return [
            'calendar' => $calendar,
            'upcoming' => $upcoming,
            'pending' => $pending,
            'upcoming_count' => $upcomingCount,
            'pending_count' => $pendingCount,
        ];
    }

    private function publicEvents(Carbon $rangeStart, Carbon $rangeEnd, Carbon $todayStart): array
    {
        if (! Schema::hasTable('site_events')) {
            return [
                'calendar' => [],
                'upcoming' => [],
                'upcoming_count' => 0,
            ];
        }

        $query = SiteEvent::query()
            ->published()
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $rangeEnd)
            ->where(function (Builder $builder) use ($rangeStart) {
                $builder
                    ->where('ends_at', '>=', $rangeStart)
                    ->orWhere(function (Builder $withoutEnd) use ($rangeStart) {
                        $withoutEnd
                            ->whereNull('ends_at')
                            ->where('starts_at', '>=', $rangeStart);
                    });
            })
            ->orderBy('starts_at');

        $events = $query
            ->limit(60)
            ->get();

        $calendar = $events
            ->map(fn (SiteEvent $event) => $this->formatPublicEvent($event))
            ->values()
            ->all();

        $upcoming = $events
            ->filter(fn (SiteEvent $event) => ($event->ends_at ?: $event->starts_at)?->greaterThanOrEqualTo($todayStart))
            ->take(6)
            ->map(fn (SiteEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'category' => $event->category,
                'location' => $event->location,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'route' => $event->public_url,
            ])
            ->values()
            ->all();

        return [
            'calendar' => $calendar,
            'upcoming' => $upcoming,
            'upcoming_count' => count($upcoming),
        ];
    }

    private function news(): array
    {
        if (! Schema::hasTable('news_posts')) {
            return [];
        }

        return NewsPost::query()
            ->published()
            ->orderedForPublic()
            ->limit(4)
            ->get()
            ->map(fn (NewsPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'category' => $post->category,
                'published_at' => $post->published_at?->toIso8601String(),
                'image_url' => $post->image_url,
                'route' => $post->public_url,
            ])
            ->values()
            ->all();
    }

    private function internalAnnouncements(User $user): array
    {
        if (! Schema::hasTable('internal_announcements')) {
            return [
                'items' => [],
                'unread_count' => 0,
                'pending_ack_count' => 0,
            ];
        }

        $items = InternalAnnouncement::query()
            ->visibleToUser($user)
            ->with([
                'roles:id,name,slug',
                'createdBy:id,name,email',
                'reads' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->orderByDesc('pinned')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'important' THEN 1 ELSE 2 END")
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (InternalAnnouncement $announcement) use ($user) {
                $read = $announcement->reads->first();
                $audiences = $announcement->audience_all
                    ? ['Todos']
                    : $announcement->roles->pluck('name')->values()->all();

                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'category' => $announcement->category,
                    'priority' => $announcement->priority,
                    'priority_label' => $announcement->priorityLabel(),
                    'pinned' => $announcement->pinned,
                    'requires_ack' => $announcement->requires_ack,
                    'published_at' => $announcement->published_at?->toIso8601String(),
                    'expires_at' => $announcement->expires_at?->toIso8601String(),
                    'created_by' => $announcement->createdBy?->name,
                    'audiences' => $audiences,
                    'read_at' => $read?->read_at?->toIso8601String(),
                    'acknowledged_at' => $read?->acknowledged_at?->toIso8601String(),
                    'can_open_admin' => $this->hasAny($user, ['ver_comunicaciones_internas']),
                    'route' => '/comunicaciones',
                ];
            })
            ->values();

        return [
            'items' => $items->all(),
            'unread_count' => $items->whereNull('read_at')->count(),
            'pending_ack_count' => $items
                ->filter(fn (array $item) => $item['requires_ack'] && empty($item['acknowledged_at']))
                ->count(),
        ];
    }

    private function metrics(Collection $todayItems, array $calendar, array $reservations, array $publicEvents, Collection $modules): array
    {
        return [
            [
                'key' => 'today',
                'label' => 'Agenda de hoy',
                'value' => $todayItems->count(),
                'detail' => 'eventos y reservas visibles',
                'icon' => 'bx-calendar-check',
                'tone' => 'primary',
            ],
            [
                'key' => 'calendar',
                'label' => 'Fechas relevantes',
                'value' => count($calendar['upcoming']),
                'detail' => $calendar['overdue_count'].' vencidas',
                'icon' => 'bx-calendar-star',
                'tone' => 'warning',
            ],
            [
                'key' => 'reservations',
                'label' => 'Reservas próximas',
                'value' => $reservations['upcoming_count'],
                'detail' => $reservations['pending_count'].' pendientes',
                'icon' => 'bx-buildings',
                'tone' => 'success',
            ],
            [
                'key' => 'modules',
                'label' => 'Módulos disponibles',
                'value' => $modules->whereNull('parent_id')->count(),
                'detail' => $publicEvents['upcoming_count'].' eventos publicados',
                'icon' => 'bx-grid-alt',
                'tone' => 'info',
            ],
        ];
    }

    private function quickLinks(User $user): array
    {
        $links = [
            [
                'title' => 'Mi perfil',
                'description' => 'Datos personales y seguridad',
                'route' => '/account/profile',
                'icon' => 'bx-user-circle',
                'tone' => 'secondary',
                'permissions' => [],
            ],
            [
                'title' => 'Calendario institucional',
                'description' => 'Fechas relevantes y vencimientos',
                'route' => '/relevant-calendar',
                'icon' => 'bx-calendar-star',
                'tone' => 'primary',
                'permissions' => ['ver_calendario_fechas_relevantes'],
            ],
            [
                'title' => 'Reservar espacio',
                'description' => 'Solicitudes de dependencias',
                'route' => '/spaces/reservations',
                'icon' => 'bx-calendar-plus',
                'tone' => 'success',
                'permissions' => ['crear_reservas'],
            ],
            [
                'title' => 'Calendario de reservas',
                'description' => 'Uso de espacios aprobados',
                'route' => '/spaces/calendar',
                'icon' => 'bx-calendar-event',
                'tone' => 'info',
                'permissions' => ['ver_reservas'],
            ],
            [
                'title' => 'Mis permisos',
                'description' => 'Solicitudes y seguimiento',
                'route' => '/staff/permissions',
                'icon' => 'bx-calendar-minus',
                'tone' => 'warning',
                'permissions' => ['ver_permisos_personal'],
            ],
            [
                'title' => 'Mi backlog',
                'description' => 'Tareas asignadas y seguimiento',
                'route' => '/tasks/backlog',
                'icon' => 'bx-list-check',
                'tone' => 'dark',
                'permissions' => ['ver_tareas'],
            ],
            [
                'title' => 'Comunicaciones internas',
                'description' => 'Avisos segmentados por rol',
                'route' => '/comunicaciones',
                'icon' => 'bx-message-square-detail',
                'tone' => 'info',
                'permissions' => ['ver_comunicaciones_internas'],
            ],
            [
                'title' => 'Noticias',
                'description' => 'Publicaciones del sitio',
                'route' => '/noticias',
                'icon' => 'bx-news',
                'tone' => 'secondary',
                'permissions' => [],
            ],
            [
                'title' => 'Eventos públicos',
                'description' => 'Actividades publicadas',
                'route' => '/eventos',
                'icon' => 'bx-calendar',
                'tone' => 'secondary',
                'permissions' => [],
            ],
        ];

        return collect($links)
            ->filter(fn (array $link) => empty($link['permissions']) || $this->hasAny($user, $link['permissions']))
            ->values()
            ->all();
    }

    private function accessibleModules(User $user): Collection
    {
        if (! Schema::hasTable('system_modules')) {
            return collect();
        }

        if ($user->isSuperAdmin()) {
            return SystemModule::query()
                ->where('active', true)
                ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route']);
        }

        $directModules = SystemModule::query()
            ->where('active', true)
            ->whereHas('roles', function (Builder $query) use ($user) {
                $query->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id));
            })
            ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route']);

        $moduleIds = $directModules->pluck('id')->all();
        $parentIds = $directModules->pluck('parent_id')->filter()->unique()->values()->all();

        while ($parentIds !== []) {
            $parents = SystemModule::query()
                ->where('active', true)
                ->whereIn('id', $parentIds)
                ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route']);

            foreach ($parents as $parent) {
                if (! in_array($parent->id, $moduleIds, true)) {
                    $moduleIds[] = $parent->id;
                }
            }

            $parentIds = $parents->pluck('parent_id')->filter()->unique()->values()->all();
        }

        return SystemModule::query()
            ->whereIn('id', $moduleIds)
            ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route']);
    }

    private function operationalCalendarQuery(User $user): Builder
    {
        return $this->calendarAccess
            ->visibleEventsQuery($user)
            ->where('event_kind', '!=', CalendarEvent::KIND_SERIES_MASTER)
            ->where(function (Builder $builder) {
                $builder
                    ->where('event_kind', '!=', CalendarEvent::KIND_PROCESS)
                    ->orWhereDoesntHave('childEvents');
            });
    }

    private function formatCalendarEvent(CalendarEvent $event): array
    {
        $allDay = ! $event->start_time && ! $event->end_time;
        $start = $allDay
            ? $event->start_date?->format('Y-m-d')
            : $this->combineDateAndTime($event->start_date?->format('Y-m-d'), $event->start_time);

        if ($allDay) {
            $end = ($event->end_date ?: $event->start_date)?->copy()?->addDay()?->format('Y-m-d');
        } else {
            $end = $this->combineDateAndTime(
                ($event->end_date ?: $event->start_date)?->format('Y-m-d'),
                $event->end_time ?: $event->start_time
            );
        }

        return [
            'id' => 'calendar-'.$event->id,
            'model_id' => $event->id,
            'source' => 'relevant_calendar',
            'source_label' => 'Fecha relevante',
            'title' => $event->title,
            'start' => $start,
            'end' => $end,
            'allDay' => $allDay,
            'backgroundColor' => $event->calendar_color,
            'borderColor' => $event->effective_status === 'vencido' ? '#b02a37' : $event->calendar_color,
            'textColor' => '#ffffff',
            'route' => '/relevant-calendar/events/'.$event->id,
            'status' => $event->effective_status,
            'priority' => $event->priority,
            'detail' => $event->institution?->name ?: $event->department?->name,
            'extendedProps' => [
                'route' => '/relevant-calendar/events/'.$event->id,
                'source' => 'relevant_calendar',
                'source_label' => 'Fecha relevante',
                'status' => $event->effective_status,
                'priority' => $event->priority,
                'department' => $event->department?->name,
                'institution' => $event->institution?->name,
                'responsible' => $event->responsibleUser?->name,
            ],
        ];
    }

    private function formatCalendarListItem(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'date' => $event->due_date,
            'status' => $event->effective_status,
            'priority' => $event->priority,
            'detail' => $event->institution?->name ?: $event->department?->name,
            'responsible' => $event->responsibleUser?->name,
            'route' => '/relevant-calendar/events/'.$event->id,
        ];
    }

    private function formatReservationEvent(DependencyReservation $reservation): array
    {
        return [
            'id' => 'reservation-'.$reservation->id,
            'model_id' => $reservation->id,
            'source' => 'reservation',
            'source_label' => 'Reserva',
            'title' => $reservation->status === DependencyReservation::STATUS_PENDING
                ? 'Pendiente: '.$reservation->title
                : $reservation->title,
            'start' => $reservation->starts_at?->format('Y-m-d\TH:i:s'),
            'end' => $reservation->ends_at?->format('Y-m-d\TH:i:s'),
            'allDay' => false,
            'backgroundColor' => $reservation->event_color,
            'borderColor' => $reservation->event_color,
            'textColor' => '#ffffff',
            'route' => '/spaces/calendar',
            'status' => $reservation->status,
            'detail' => $reservation->dependency?->name,
            'extendedProps' => [
                'route' => '/spaces/calendar',
                'source' => 'reservation',
                'source_label' => 'Reserva',
                'status' => $reservation->status,
                'dependency_name' => $reservation->dependency?->name,
                'staff_name' => $reservation->staff?->full_name,
                'department_name' => $reservation->department?->name,
                'activity' => $reservation->activity,
            ],
        ];
    }

    private function formatReservationListItem(DependencyReservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'title' => $reservation->title,
            'activity' => $reservation->activity,
            'status' => $reservation->status,
            'starts_at' => $reservation->starts_at?->toIso8601String(),
            'ends_at' => $reservation->ends_at?->toIso8601String(),
            'dependency' => $reservation->dependency?->name,
            'staff' => $reservation->staff?->full_name,
            'department' => $reservation->department?->name,
            'route' => '/spaces/reservations',
        ];
    }

    private function formatPublicEvent(SiteEvent $event): array
    {
        return [
            'id' => 'public-event-'.$event->id,
            'model_id' => $event->id,
            'source' => 'public_event',
            'source_label' => 'Evento público',
            'title' => $event->title,
            'start' => $event->starts_at?->format('Y-m-d\TH:i:s'),
            'end' => $event->ends_at?->format('Y-m-d\TH:i:s'),
            'allDay' => false,
            'backgroundColor' => '#556ee6',
            'borderColor' => '#556ee6',
            'textColor' => '#ffffff',
            'route' => $event->public_url,
            'status' => 'publicado',
            'detail' => $event->location,
            'extendedProps' => [
                'route' => $event->public_url,
                'source' => 'public_event',
                'source_label' => 'Evento público',
                'category' => $event->category,
                'location' => $event->location,
            ],
        ];
    }

    private function eventOverlapsWindow(array $item, Carbon $from, Carbon $to): bool
    {
        $timezone = config('app.timezone');
        $start = Carbon::parse((string) $item['start'], $timezone);
        $end = Carbon::parse((string) ($item['end'] ?: $item['start']), $timezone);

        if (($item['allDay'] ?? false) && ! empty($item['end'])) {
            $end->subSecond();
        }

        return $start->lessThanOrEqualTo($to) && $end->greaterThanOrEqualTo($from);
    }

    private function combineDateAndTime(?string $date, ?string $time): ?string
    {
        if (! $date) {
            return null;
        }

        $resolvedTime = $time ? substr((string) $time, 0, 5) : '00:00';

        return Carbon::parse($date.' '.$resolvedTime, config('app.timezone'))->format('Y-m-d\TH:i:s');
    }

    private function hasAny(User $user, array $permissions): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    private function calendarRelations(): array
    {
        return [
            'processType:id,name,color',
            'institution:id,name,color',
            'department:id,name,color',
            'responsibleUser:id,name,email',
        ];
    }

    private function reservationRelations(): array
    {
        return [
            'dependency:id,dependency_type_id,name,calendar_color',
            'dependency.type:id,name',
            'staff:id,full_name',
            'department:id,name,color',
        ];
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
