<?php

namespace App\Services\PublicSite;

use App\Models\WebAnalyticsPageView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class WebAnalyticsTrackingService
{
    private const BOT_PATTERN = '/bot|crawler|spider|crawling|headless|preview|facebookexternalhit|whatsapp|telegrambot|slurp|bingpreview|lighthouse|pagespeed|uptimerobot|monitoring/i';

    public function recordPageView(Request $request, array $payload): ?WebAnalyticsPageView
    {
        if (! $this->canRecord($request)) {
            return null;
        }

        try {
            $now = now();
            $userAgent = (string) $request->userAgent();
            $referrerHost = $this->normalizeHost($payload['referrer_host'] ?? null);
            [$source, $channel] = $this->trafficSource(
                $payload['campaign_source'] ?? null,
                $payload['campaign_medium'] ?? null,
                $referrerHost,
            );

            return WebAnalyticsPageView::query()->create([
                'public_id' => (string) Str::uuid(),
                'visitor_hash' => $this->anonymousHash((string) $payload['visitor_id']),
                'session_hash' => $this->anonymousHash((string) $payload['session_id']),
                'viewed_on' => $now->toDateString(),
                'page_type' => $payload['page_type'],
                'content_type' => $payload['content_type'] ?: null,
                'content_identifier' => $payload['content_identifier'] ?: null,
                'content_slug' => $payload['content_slug'] ?: null,
                'path' => $payload['path'],
                'title' => $payload['title'] ?: null,
                'referrer_host' => $referrerHost,
                'traffic_source' => $source,
                'traffic_channel' => $channel,
                'campaign' => $payload['campaign_name'] ?: null,
                'device_type' => $this->deviceType($payload['viewport_width'] ?? null, $userAgent),
                'browser_family' => $this->browserFamily($userAgent),
                'viewport_width' => $payload['viewport_width'] ?? null,
                'started_at' => $now,
                'last_seen_at' => $now,
                'engaged_seconds' => 0,
                'max_scroll_depth' => 0,
                'interaction_count' => 0,
                'is_engaged' => false,
            ]);
        } catch (Throwable $exception) {
            if (app()->environment('testing')) {
                throw $exception;
            }

            report($exception);

            return null;
        }
    }

    public function recordEngagement(Request $request, array $payload): bool
    {
        if (! $this->canRecord($request)) {
            return false;
        }

        try {
            $view = WebAnalyticsPageView::query()
                ->where('public_id', $payload['visit_id'])
                ->where('visitor_hash', $this->anonymousHash((string) $payload['visitor_id']))
                ->where('session_hash', $this->anonymousHash((string) $payload['session_id']))
                ->first();

            if (! $view) {
                return false;
            }

            $seconds = max($view->engaged_seconds, (int) $payload['engaged_seconds']);
            $scroll = max($view->max_scroll_depth, (int) $payload['max_scroll_depth']);
            $interactions = max($view->interaction_count, (int) $payload['interaction_count']);

            $view->forceFill([
                'last_seen_at' => now(),
                'engaged_seconds' => $seconds,
                'max_scroll_depth' => $scroll,
                'interaction_count' => $interactions,
                'is_engaged' => $seconds >= 10 || $scroll >= 50 || $interactions > 0,
            ])->save();

            return true;
        } catch (Throwable $exception) {
            if (app()->environment('testing')) {
                throw $exception;
            }

            report($exception);

            return false;
        }
    }

    private function canRecord(Request $request): bool
    {
        if (preg_match(self::BOT_PATTERN, (string) $request->userAgent()) === 1) {
            return false;
        }

        if (strtolower((string) $request->header('Sec-Fetch-Site')) === 'cross-site') {
            return false;
        }

        $origin = $request->header('Origin');

        if (! $origin) {
            return true;
        }

        return strtolower((string) parse_url($origin, PHP_URL_HOST)) === strtolower($request->getHost());
    }

    private function anonymousHash(string $identifier): string
    {
        return hash_hmac('sha256', $identifier, (string) config('app.key'));
    }

    private function normalizeHost(?string $host): ?string
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        return $host !== '' ? Str::limit($host, 191, '') : null;
    }

    /** @return array{0:string,1:string} */
    private function trafficSource(?string $campaignSource, ?string $campaignMedium, ?string $referrerHost): array
    {
        $campaignSource = Str::squish((string) $campaignSource);
        $campaignMedium = strtolower(Str::squish((string) $campaignMedium));

        if ($campaignSource !== '') {
            $channel = match (true) {
                str_contains($campaignMedium, 'email') => 'email',
                str_contains($campaignMedium, 'social') => 'social',
                str_contains($campaignMedium, 'cpc'), str_contains($campaignMedium, 'paid') => 'paid',
                default => 'campaign',
            };

            return [Str::limit($campaignSource, 120, ''), $channel];
        }

        if (! $referrerHost) {
            return ['Directo', 'direct'];
        }

        if (preg_match('/(^|\.)(google|bing|yahoo|duckduckgo|ecosia)\./i', $referrerHost) === 1) {
            return [$referrerHost, 'search'];
        }

        if (preg_match('/(^|\.)(facebook|instagram|linkedin|tiktok|youtube|x|twitter)\./i', $referrerHost) === 1) {
            return [$referrerHost, 'social'];
        }

        return [$referrerHost, 'referral'];
    }

    private function deviceType(?int $viewportWidth, string $userAgent): string
    {
        if ($viewportWidth !== null) {
            return match (true) {
                $viewportWidth < 768 => 'mobile',
                $viewportWidth < 1200 => 'tablet',
                default => 'desktop',
            };
        }

        return preg_match('/mobile|iphone|android/i', $userAgent) === 1 ? 'mobile' : 'desktop';
    }

    private function browserFamily(string $userAgent): string
    {
        return match (true) {
            preg_match('/Edg\//i', $userAgent) === 1 => 'Edge',
            preg_match('/OPR\//i', $userAgent) === 1 => 'Opera',
            preg_match('/Firefox\//i', $userAgent) === 1 => 'Firefox',
            preg_match('/Chrome\//i', $userAgent) === 1 => 'Chrome',
            preg_match('/Safari\//i', $userAgent) === 1 => 'Safari',
            default => 'Otro',
        };
    }
}
