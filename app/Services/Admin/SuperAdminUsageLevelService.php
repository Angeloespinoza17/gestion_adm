<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SuperAdminUsageLevelService
{
    private const GROUP_SQL = "CASE WHEN users.student_id IS NOT NULL OR LOWER(COALESCE(users.user_type, '')) = 'student' THEN 'student' ELSE 'staff' END";

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function index(array $filters): array
    {
        $period = $this->resolvePeriod($filters);
        $periodUsage = $this->periodUsageQuery($period);
        $users = $this->usersQuery($filters, clone $periodUsage);
        $summary = $this->summary(clone $users);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 10), 50);
        $sort = (string) ($filters['sort'] ?? 'last_activity');
        $direction = (string) ($filters['direction'] ?? ($sort === 'name' ? 'asc' : 'desc'));

        $this->applyOrdering($users, $sort, $direction);
        $paginator = $users->paginate($perPage);
        $paginator->setCollection($paginator->getCollection()->map(fn (object $row): array => $this->normalizeUser($row)));

        return [
            ...$paginator->toArray(),
            'summary' => $summary,
            'groups' => $this->groupOverview(clone $periodUsage),
            'period' => $this->periodPayload($period),
            'tracking' => [
                'started_at' => DB::table('user_usage_daily')->min('usage_date'),
                'activity_window_minutes' => UserUsageRecorder::ACTIVITY_WINDOW_MINUTES,
                'historical_note' => 'Las métricas se registran desde la instalación de esta función; no reconstruyen accesos históricos eliminados.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function detail(User $user, array $filters): array
    {
        $period = $this->resolvePeriod($filters);
        $user->loadMissing(['cargo:id,name', 'staff:id,full_name', 'student:id,first_name,last_name,registered_name', 'roles:id,name,slug']);

        $query = DB::table('user_usage_daily')
            ->where('user_id', $user->id);
        $this->applyPeriod($query, $period);

        $daily = (clone $query)
            ->orderByDesc('usage_date')
            ->limit(60)
            ->get(['usage_date', 'login_count', 'usage_count', 'first_activity_at', 'last_activity_at', 'last_login_at'])
            ->reverse()
            ->values()
            ->map(fn (object $row): array => [
                'date' => (string) $row->usage_date,
                'logins' => (int) $row->login_count,
                'usage' => (int) $row->usage_count,
                'first_activity_at' => $row->first_activity_at,
                'last_activity_at' => $row->last_activity_at,
                'last_login_at' => $row->last_login_at,
            ]);

        $totals = (clone $query)
            ->selectRaw('COALESCE(SUM(login_count), 0) as login_count')
            ->selectRaw('COALESCE(SUM(usage_count), 0) as usage_count')
            ->selectRaw('COUNT(*) as active_days')
            ->selectRaw('MIN(first_activity_at) as first_activity_at')
            ->selectRaw('MAX(last_activity_at) as last_activity_at')
            ->selectRaw('MAX(last_login_at) as last_login_at')
            ->first();

        $isStudent = $user->student_id !== null || strtolower((string) $user->user_type) === 'student';
        $studentName = trim(implode(' ', array_filter([
            $user->student?->first_name,
            $user->student?->last_name,
        ])));

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->staff?->full_name
                    ?: $user->student?->registered_name
                    ?: ($studentName !== '' ? $studentName : $user->name),
                'email' => $user->email,
                'group' => $isStudent ? 'student' : 'staff',
                'group_label' => $isStudent ? 'Estudiante' : 'Funcionario/a',
                'profile_label' => $isStudent ? 'Estudiante' : ($user->cargo?->name ?: 'Funcionario/a'),
                'active' => (bool) $user->active,
                'roles' => $user->roles->map(fn ($role): array => [
                    'slug' => $role->slug,
                    'name' => $role->name,
                ])->values()->all(),
                'created_at' => $user->created_at?->toDateTimeString(),
            ],
            'totals' => [
                'login_count' => (int) ($totals->login_count ?? 0),
                'usage_count' => (int) ($totals->usage_count ?? 0),
                'active_days' => (int) ($totals->active_days ?? 0),
                'first_activity_at' => $totals->first_activity_at ?? null,
                'last_activity_at' => $totals->last_activity_at ?? null,
                'last_login_at' => $totals->last_login_at ?? null,
            ],
            'timeline' => $daily->all(),
            'period' => $this->periodPayload($period),
        ];
    }

    /** @param array{from: ?Carbon, to: Carbon, key: string} $period */
    private function periodUsageQuery(array $period): Builder
    {
        $query = DB::table('user_usage_daily')
            ->select('user_id')
            ->selectRaw('SUM(login_count) as login_count')
            ->selectRaw('SUM(usage_count) as usage_count')
            ->selectRaw('COUNT(*) as active_days')
            ->selectRaw('MIN(first_activity_at) as first_activity_at')
            ->selectRaw('MAX(last_activity_at) as last_activity_at')
            ->selectRaw('MAX(last_login_at) as last_login_at');

        $this->applyPeriod($query, $period);

        return $query->groupBy('user_id');
    }

    /** @param array<string, mixed> $filters */
    private function usersQuery(array $filters, Builder $periodUsage): Builder
    {
        $query = DB::table('users')
            ->leftJoinSub($periodUsage, 'period_usage', 'period_usage.user_id', '=', 'users.id')
            ->leftJoin('cargos', 'cargos.id', '=', 'users.cargo_id')
            ->leftJoin('staff', 'staff.id', '=', 'users.staff_id')
            ->leftJoin('student_profiles', 'student_profiles.id', '=', 'users.student_id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.active',
                'users.user_type',
                'users.created_at',
                'cargos.name as cargo_name',
            ])
            ->selectRaw(self::GROUP_SQL.' as user_group')
            ->selectRaw('COALESCE(period_usage.login_count, 0) as login_count')
            ->selectRaw('COALESCE(period_usage.usage_count, 0) as usage_count')
            ->selectRaw('COALESCE(period_usage.active_days, 0) as active_days')
            ->addSelect([
                'period_usage.first_activity_at',
                'period_usage.last_activity_at',
                'period_usage.last_login_at',
            ]);

        $group = (string) ($filters['group'] ?? 'staff');
        if ($group === 'student') {
            $query->where(function (Builder $builder): void {
                $builder->whereNotNull('users.student_id')
                    ->orWhereRaw("LOWER(COALESCE(users.user_type, '')) = 'student'");
            });
        } else {
            $query->whereNull('users.student_id')
                ->whereRaw("LOWER(COALESCE(users.user_type, '')) <> 'student'");
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('users.name', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('staff.full_name', 'like', $like)
                    ->orWhere('staff.rut', 'like', $like)
                    ->orWhere('student_profiles.first_name', 'like', $like)
                    ->orWhere('student_profiles.last_name', 'like', $like)
                    ->orWhere('student_profiles.registered_name', 'like', $like)
                    ->orWhere('student_profiles.rut', 'like', $like);
            });
        }

        $accountStatus = (string) ($filters['account_status'] ?? '');
        if ($accountStatus !== '') {
            $query->where('users.active', $accountStatus === 'active');
        }

        $usageStatus = (string) ($filters['usage_status'] ?? '');
        if ($usageStatus === 'with_usage') {
            $query->whereNotNull('period_usage.last_activity_at');
        } elseif ($usageStatus === 'without_usage') {
            $query->whereNull('period_usage.last_activity_at');
        }

        return $query;
    }

    private function summary(Builder $users): array
    {
        $row = DB::query()
            ->fromSub($users, 'usage_users')
            ->selectRaw('COUNT(*) as total_users')
            ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_accounts')
            ->selectRaw('SUM(CASE WHEN last_activity_at IS NOT NULL THEN 1 ELSE 0 END) as users_with_usage')
            ->selectRaw('COALESCE(SUM(login_count), 0) as login_count')
            ->selectRaw('COALESCE(SUM(usage_count), 0) as usage_count')
            ->selectRaw('COALESCE(SUM(active_days), 0) as active_days')
            ->selectRaw('MAX(last_activity_at) as last_activity_at')
            ->first();

        $total = (int) ($row->total_users ?? 0);
        $withUsage = (int) ($row->users_with_usage ?? 0);

        return [
            'total_users' => $total,
            'active_accounts' => (int) ($row->active_accounts ?? 0),
            'users_with_usage' => $withUsage,
            'users_without_usage' => max($total - $withUsage, 0),
            'adoption_rate' => $total > 0 ? round(($withUsage / $total) * 100, 1) : 0,
            'login_count' => (int) ($row->login_count ?? 0),
            'usage_count' => (int) ($row->usage_count ?? 0),
            'active_days' => (int) ($row->active_days ?? 0),
            'last_activity_at' => $row->last_activity_at ?? null,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function groupOverview(Builder $periodUsage): array
    {
        $source = DB::table('users')
            ->leftJoinSub($periodUsage, 'period_usage', 'period_usage.user_id', '=', 'users.id')
            ->selectRaw(self::GROUP_SQL.' as user_group')
            ->addSelect('users.active')
            ->selectRaw('COALESCE(period_usage.login_count, 0) as login_count')
            ->selectRaw('COALESCE(period_usage.usage_count, 0) as usage_count')
            ->addSelect('period_usage.last_activity_at');

        $rows = DB::query()
            ->fromSub($source, 'group_users')
            ->select('user_group')
            ->selectRaw('COUNT(*) as total_users')
            ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_accounts')
            ->selectRaw('SUM(CASE WHEN last_activity_at IS NOT NULL THEN 1 ELSE 0 END) as users_with_usage')
            ->selectRaw('COALESCE(SUM(login_count), 0) as login_count')
            ->selectRaw('COALESCE(SUM(usage_count), 0) as usage_count')
            ->groupBy('user_group')
            ->get()
            ->keyBy('user_group');

        return collect(['staff' => 'Funcionarios', 'student' => 'Estudiantes'])
            ->mapWithKeys(function (string $label, string $key) use ($rows): array {
                $row = $rows->get($key);
                $total = (int) ($row->total_users ?? 0);
                $withUsage = (int) ($row->users_with_usage ?? 0);

                return [$key => [
                    'key' => $key,
                    'label' => $label,
                    'total_users' => $total,
                    'active_accounts' => (int) ($row->active_accounts ?? 0),
                    'users_with_usage' => $withUsage,
                    'adoption_rate' => $total > 0 ? round(($withUsage / $total) * 100, 1) : 0,
                    'login_count' => (int) ($row->login_count ?? 0),
                    'usage_count' => (int) ($row->usage_count ?? 0),
                ]];
            })
            ->all();
    }

    private function normalizeUser(object $row): array
    {
        $group = (string) $row->user_group;

        return [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'email' => (string) $row->email,
            'group' => $group,
            'group_label' => $group === 'student' ? 'Estudiante' : 'Funcionario/a',
            'profile_label' => $group === 'student' ? 'Estudiante' : ($row->cargo_name ?: 'Funcionario/a'),
            'active' => (bool) $row->active,
            'login_count' => (int) $row->login_count,
            'usage_count' => (int) $row->usage_count,
            'active_days' => (int) $row->active_days,
            'first_activity_at' => $row->first_activity_at,
            'last_activity_at' => $row->last_activity_at,
            'last_login_at' => $row->last_login_at,
            'created_at' => $row->created_at,
        ];
    }

    private function applyOrdering(Builder $query, string $sort, string $direction): void
    {
        $columns = [
            'name' => 'users.name',
            'logins' => 'period_usage.login_count',
            'usage' => 'period_usage.usage_count',
            'active_days' => 'period_usage.active_days',
            'last_activity' => 'period_usage.last_activity_at',
        ];
        $column = $columns[$sort] ?? $columns['last_activity'];
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        if ($sort !== 'name') {
            $query->orderByRaw($column.' IS NULL');
        }

        $query->orderBy($column, $direction)->orderBy('users.name')->orderBy('users.id');
    }

    /** @param array<string, mixed> $filters
     * @return array{from: ?Carbon, to: Carbon, key: string}
     */
    private function resolvePeriod(array $filters): array
    {
        $to = ! empty($filters['date_to'])
            ? Carbon::createFromFormat('Y-m-d', (string) $filters['date_to'])->startOfDay()
            : today();

        if (! empty($filters['date_from'])) {
            return [
                'from' => Carbon::createFromFormat('Y-m-d', (string) $filters['date_from'])->startOfDay(),
                'to' => $to,
                'key' => 'custom',
            ];
        }

        $key = (string) ($filters['period'] ?? '30');
        if ($key === 'all') {
            return ['from' => null, 'to' => $to, 'key' => 'all'];
        }

        $days = in_array($key, ['30', '90', '365'], true) ? (int) $key : 30;

        return [
            'from' => $to->copy()->subDays($days - 1),
            'to' => $to,
            'key' => (string) $days,
        ];
    }

    /** @param array{from: ?Carbon, to: Carbon, key: string} $period */
    private function applyPeriod(Builder $query, array $period): void
    {
        $query->whereDate('usage_date', '<=', $period['to']->toDateString());
        if ($period['from']) {
            $query->whereDate('usage_date', '>=', $period['from']->toDateString());
        }
    }

    /** @param array{from: ?Carbon, to: Carbon, key: string} $period
     * @return array<string, mixed>
     */
    private function periodPayload(array $period): array
    {
        $labels = [
            '30' => 'Últimos 30 días',
            '90' => 'Últimos 90 días',
            '365' => 'Últimos 12 meses',
            'all' => 'Todo el historial registrado',
            'custom' => 'Rango personalizado',
        ];

        return [
            'key' => $period['key'],
            'label' => $labels[$period['key']] ?? 'Período consultado',
            'from' => $period['from']?->toDateString(),
            'to' => $period['to']->toDateString(),
        ];
    }
}
