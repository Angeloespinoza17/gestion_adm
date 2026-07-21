<?php

namespace App\Services\Attendance;

use Closure;
use Illuminate\Support\Facades\Cache;

class AttendanceStatisticsCache
{
    private const VERSION_KEY = 'attendance-statistics:version';

    public function remember(string $segment, array $filters, Closure $resolver, int $seconds = 300): mixed
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $normalized = $this->normalize($filters);
        $key = 'attendance-statistics:'.$version.':'.$segment.':'.hash('sha256', json_encode($normalized));

        return Cache::remember($key, now()->addSeconds($seconds), $resolver);
    }

    public function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
    }

    private function normalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalize($item);
            }
        }

        return $value;
    }
}
