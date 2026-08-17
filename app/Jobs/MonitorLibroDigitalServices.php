<?php

namespace App\Jobs;

use App\Models\LibroDigital\Setting;
use App\Services\LibroDigital\LibroDigitalHealthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonitorLibroDigitalServices implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function handle(LibroDigitalHealthService $health): void
    {
        $result = $health->check();
        Setting::query()->updateOrCreate(
            ['scope_key' => 'global', 'key' => 'last_health_check'],
            [
                'school_id' => null,
                'academic_year_id' => null,
                'value' => [
                    'checked_at' => now('UTC')->toIso8601String(),
                    'healthy' => $result['healthy'],
                    'checks' => collect($result['checks'])->map(fn (array $check) => [
                        'code' => $check['code'],
                        'passed' => $check['passed'],
                        'required' => $check['required'],
                        'duration_ms' => $check['duration_ms'] ?? null,
                    ])->all(),
                ],
                'is_encrypted' => false,
            ],
        );
    }

    public function uniqueId(): string
    {
        return 'lcd-service-health';
    }
}
