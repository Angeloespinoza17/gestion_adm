<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts()
    {
        $applicationHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $configuredHosts = config('app.trusted_hosts', []);
        $hosts = array_merge(
            [$applicationHost],
            is_array($configuredHosts) ? $configuredHosts : []
        );

        $patterns = [];

        foreach ($hosts as $host) {
            $host = strtolower(rtrim(trim((string) $host), '.'));

            if ($host === '') {
                continue;
            }

            $patterns[$host] = '^'.preg_quote($host).'\\z';
        }

        return array_values($patterns);
    }
}
