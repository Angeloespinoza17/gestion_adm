<?php

namespace Tests\Unit\Infirmary;

use App\Services\Infirmary\InfirmaryDashboardService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfirmaryDashboardServiceTest extends TestCase
{
    private InfirmaryDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InfirmaryDashboardService::class);
    }

    #[Test]
    public function it_resolves_the_current_month_and_an_equal_previous_period(): void
    {
        $range = $this->service->resolveDateRange(
            ['period' => 'mensual'],
            Carbon::parse('2026-07-16 11:30:00', 'America/Santiago'),
        );

        $this->assertSame('2026-07-01', $range['from']->toDateString());
        $this->assertSame('2026-07-16', $range['to']->toDateString());
        $this->assertSame(16, $range['days']);
        $this->assertSame('daily', $range['granularity']);
    }

    #[Test]
    public function it_starts_the_second_semester_on_july_first(): void
    {
        $range = $this->service->resolveDateRange(
            ['period' => 'semestral'],
            Carbon::parse('2026-09-10 09:00:00', 'America/Santiago'),
        );

        $this->assertSame('2026-07-01', $range['from']->toDateString());
        $this->assertSame('2026-09-10', $range['to']->toDateString());
        $this->assertSame('weekly', $range['granularity']);
    }

    #[Test]
    public function it_uses_monthly_buckets_for_a_long_custom_range(): void
    {
        $range = $this->service->resolveDateRange([
            'period' => 'personalizado',
            'from' => '2025-01-01',
            'to' => '2026-07-16',
        ]);

        $this->assertSame('2025-01-01', $range['from']->toDateString());
        $this->assertSame('2026-07-16', $range['to']->toDateString());
        $this->assertSame('monthly', $range['granularity']);
    }
}
