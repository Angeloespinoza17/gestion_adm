<?php

namespace App\Jobs;

use App\Models\LibroDigital\Setting;
use App\Services\LibroDigital\LibroDigitalIntegrityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunLibroDigitalIntegrityCheck implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly ?int $schoolId = null) {}

    public function handle(LibroDigitalIntegrityService $integrity): void
    {
        $result = $integrity->check($this->schoolId);
        Setting::query()->updateOrCreate(
            [
                'scope_key' => $this->schoolId ? 'school:'.$this->schoolId : 'global',
                'key' => 'last_integrity_check',
            ],
            [
                'school_id' => $this->schoolId,
                'academic_year_id' => null,
                'value' => [
                    'checked_at' => now('UTC')->toIso8601String(),
                    'valid' => $result['valid'],
                    'checked' => $result['checked'],
                    'issue_count' => count($result['issues']),
                    'issue_codes' => collect($result['issues'])->pluck('code')->filter()->unique()->values()->all(),
                    'repair_performed' => false,
                ],
                'is_encrypted' => false,
            ],
        );
    }

    public function uniqueId(): string
    {
        return 'lcd-integrity-'.($this->schoolId ?? 'global');
    }
}
