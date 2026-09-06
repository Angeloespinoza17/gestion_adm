<?php

namespace App\Services\PublicSite;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebAnalyticsDashboardService
{
    public function overview(Carbon $from, Carbon $to, ?string $contentType = null): array
    {
        if (! Schema::hasTable('web_analytics_page_views')) {
            return $this->emptyOverview($from, $to, $contentType);
        }

        $current = $this->summary($from, $to, $contentType);
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1);
        $previous = $this->summary($previousFrom, $previousTo, $contentType);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'label' => $this->periodLabel($from, $to),
            ],
            'summary' => [
                'views' => $this->metric($current['views'], $previous['views']),
                'visitors' => $this->metric($current['visitors'], $previous['visitors']),
                'sessions' => $this->metric($current['sessions'], $previous['sessions']),
                'avg_active_seconds' => $this->metric($current['avg_active_seconds'], $previous['avg_active_seconds']),
                'engagement_rate' => $this->metric($current['engagement_rate'], $previous['engagement_rate']),
                'bounce_rate' => $this->metric($current['bounce_rate'], $previous['bounce_rate'], inverse: true),
                'pages_per_session' => $this->metric($current['pages_per_session'], $previous['pages_per_session']),
            ],
            'daily' => $this->daily($from, $to, $contentType),
            'top_pages' => $this->topPages($from, $to, $contentType),
            'content_performance' => $this->contentPerformance($from, $to, $contentType),
            'sources' => $this->sources($from, $to, $contentType),
            'devices' => $this->devices($from, $to, $contentType),
            'browsers' => $this->browsers($from, $to, $contentType),
            'content_types' => DB::table('web_analytics_page_views')
                ->whereNotNull('content_type')
                ->distinct()
                ->orderBy('content_type')
                ->pluck('content_type')
                ->values(),
            'tracking' => [
                'started_on' => ($startedOn = DB::table('web_analytics_page_views')->min('viewed_on'))
                    ? Carbon::parse($startedOn)->toDateString()
                    : null,
                'privacy' => 'Métricas anónimas: no se almacenan IP, parámetros de URL ni agentes de usuario.',
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function summary(Carbon $from, Carbon $to, ?string $contentType): array
    {
        $row = $this->query($from, $to, $contentType)
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) as visitors')
            ->selectRaw('COUNT(DISTINCT session_hash) as sessions')
            ->selectRaw('AVG(engaged_seconds) as avg_active_seconds')
            ->selectRaw('SUM(CASE WHEN is_engaged = 1 THEN 1 ELSE 0 END) as engaged_views')
            ->first();

        $sessions = $this->query($from, $to, $contentType)
            ->select('session_hash')
            ->selectRaw('COUNT(*) as page_views')
            ->selectRaw('MAX(engaged_seconds) as max_active_seconds')
            ->selectRaw('MAX(max_scroll_depth) as max_scroll_depth')
            ->selectRaw('MAX(interaction_count) as max_interactions')
            ->groupBy('session_hash');

        $bounce = DB::query()
            ->fromSub($sessions, 'session_metrics')
            ->selectRaw('COUNT(*) as total_sessions')
            ->selectRaw('SUM(CASE WHEN page_views = 1 AND max_active_seconds < 10 AND max_scroll_depth < 50 AND max_interactions = 0 THEN 1 ELSE 0 END) as bounced_sessions')
            ->first();

        $views = (int) ($row->views ?? 0);
        $sessionCount = (int) ($row->sessions ?? 0);
        $engagedViews = (int) ($row->engaged_views ?? 0);
        $totalSessions = (int) ($bounce->total_sessions ?? 0);

        return [
            'views' => $views,
            'visitors' => (int) ($row->visitors ?? 0),
            'sessions' => $sessionCount,
            'avg_active_seconds' => round((float) ($row->avg_active_seconds ?? 0), 1),
            'engagement_rate' => $views > 0 ? round(($engagedViews / $views) * 100, 1) : 0,
            'bounce_rate' => $totalSessions > 0
                ? round(((int) ($bounce->bounced_sessions ?? 0) / $totalSessions) * 100, 1)
                : 0,
            'pages_per_session' => $sessionCount > 0 ? round($views / $sessionCount, 2) : 0,
        ];
    }

    private function daily(Carbon $from, Carbon $to, ?string $contentType): array
    {
        $rows = $this->query($from, $to, $contentType)
            ->select('viewed_on')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) as visitors')
            ->selectRaw('COUNT(DISTINCT session_hash) as sessions')
            ->selectRaw('AVG(engaged_seconds) as avg_active_seconds')
            ->selectRaw('SUM(CASE WHEN is_engaged = 1 THEN 1 ELSE 0 END) as engaged_views')
            ->groupBy('viewed_on')
            ->orderBy('viewed_on')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->viewed_on)->toDateString());

        $series = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);
            $views = (int) ($row->views ?? 0);

            $series[] = [
                'date' => $date,
                'views' => $views,
                'visitors' => (int) ($row->visitors ?? 0),
                'sessions' => (int) ($row->sessions ?? 0),
                'avg_active_seconds' => round((float) ($row->avg_active_seconds ?? 0), 1),
                'engagement_rate' => $views > 0
                    ? round(((int) ($row->engaged_views ?? 0) / $views) * 100, 1)
                    : 0,
            ];

            $cursor->addDay();
        }

        return $series;
    }

    private function topPages(Carbon $from, Carbon $to, ?string $contentType): array
    {
        return $this->query($from, $to, $contentType)
            ->select(['path', 'page_type', 'content_type'])
            ->selectRaw('MAX(title) as title')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) as visitors')
            ->selectRaw('AVG(engaged_seconds) as avg_active_seconds')
            ->selectRaw('SUM(CASE WHEN is_engaged = 1 THEN 1 ELSE 0 END) as engaged_views')
            ->groupBy(['path', 'page_type', 'content_type'])
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'path' => $row->path,
                'title' => $row->title ?: $row->path,
                'page_type' => $row->page_type,
                'content_type' => $row->content_type,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
                'avg_active_seconds' => round((float) $row->avg_active_seconds, 1),
                'engagement_rate' => (int) $row->views > 0
                    ? round(((int) $row->engaged_views / (int) $row->views) * 100, 1)
                    : 0,
            ])
            ->all();
    }

    private function contentPerformance(Carbon $from, Carbon $to, ?string $contentType): array
    {
        return $this->query($from, $to, $contentType)
            ->whereNotNull('content_type')
            ->whereNotNull('content_identifier')
            ->select(['content_type', 'content_identifier', 'content_slug'])
            ->selectRaw('MAX(title) as title')
            ->selectRaw('MAX(path) as path')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) as visitors')
            ->selectRaw('COUNT(DISTINCT session_hash) as sessions')
            ->selectRaw('AVG(engaged_seconds) as avg_active_seconds')
            ->selectRaw('SUM(CASE WHEN is_engaged = 1 THEN 1 ELSE 0 END) as engaged_views')
            ->groupBy(['content_type', 'content_identifier', 'content_slug'])
            ->orderByDesc('views')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'content_type' => $row->content_type,
                'content_identifier' => $row->content_identifier,
                'content_slug' => $row->content_slug,
                'title' => $row->title ?: $row->content_slug ?: $row->content_identifier,
                'path' => $row->path,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
                'sessions' => (int) $row->sessions,
                'avg_active_seconds' => round((float) $row->avg_active_seconds, 1),
                'engagement_rate' => (int) $row->views > 0
                    ? round(((int) $row->engaged_views / (int) $row->views) * 100, 1)
                    : 0,
            ])
            ->all();
    }

    private function sources(Carbon $from, Carbon $to, ?string $contentType): array
    {
        return $this->query($from, $to, $contentType)
            ->select(['traffic_source', 'traffic_channel'])
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) as visitors')
            ->groupBy(['traffic_source', 'traffic_channel'])
            ->orderByDesc('views')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'source' => $row->traffic_source,
                'channel' => $row->traffic_channel,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
            ])
            ->all();
    }

    private function devices(Carbon $from, Carbon $to, ?string $contentType): array
    {
        return $this->distribution($from, $to, $contentType, 'device_type');
    }

    private function browsers(Carbon $from, Carbon $to, ?string $contentType): array
    {
        return $this->distribution($from, $to, $contentType, 'browser_family');
    }

    private function distribution(Carbon $from, Carbon $to, ?string $contentType, string $column): array
    {
        $rows = $this->query($from, $to, $contentType)
            ->select($column)
            ->selectRaw('COUNT(*) as views')
            ->groupBy($column)
            ->orderByDesc('views')
            ->get();
        $total = max(1, (int) $rows->sum('views'));

        return $rows
            ->map(fn ($row) => [
                'label' => $row->{$column},
                'views' => (int) $row->views,
                'percentage' => round(((int) $row->views / $total) * 100, 1),
            ])
            ->all();
    }

    private function query(Carbon $from, Carbon $to, ?string $contentType): Builder
    {
        return DB::table('web_analytics_page_views')
            ->whereBetween('viewed_on', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($contentType, fn (Builder $query) => $query->where('content_type', $contentType));
    }

    private function metric(float|int $value, float|int $previous, bool $inverse = false): array
    {
        $change = (float) $previous !== 0.0
            ? round((((float) $value - (float) $previous) / abs((float) $previous)) * 100, 1)
            : null;

        return [
            'value' => $value,
            'change' => $change,
            'improved' => $change === null ? null : ($inverse ? $change <= 0 : $change >= 0),
        ];
    }

    private function periodLabel(Carbon $from, Carbon $to): string
    {
        return $from->copy()->locale('es')->translatedFormat('j M')
            .' – '
            .$to->copy()->locale('es')->translatedFormat('j M Y');
    }

    private function emptyOverview(Carbon $from, Carbon $to, ?string $contentType): array
    {
        $days = $from->diffInDays($to) + 1;
        $zero = ['value' => 0, 'change' => null, 'improved' => null];

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'label' => $this->periodLabel($from, $to),
            ],
            'summary' => [
                'views' => $zero,
                'visitors' => $zero,
                'sessions' => $zero,
                'avg_active_seconds' => $zero,
                'engagement_rate' => $zero,
                'bounce_rate' => $zero,
                'pages_per_session' => $zero,
            ],
            'daily' => [],
            'top_pages' => [],
            'content_performance' => [],
            'sources' => [],
            'devices' => [],
            'browsers' => [],
            'content_types' => $contentType ? [$contentType] : [],
            'tracking' => [
                'started_on' => null,
                'privacy' => 'Métricas anónimas: no se almacenan IP, parámetros de URL ni agentes de usuario.',
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
