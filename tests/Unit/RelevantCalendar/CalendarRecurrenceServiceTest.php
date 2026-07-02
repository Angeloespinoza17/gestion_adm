<?php

namespace Tests\Unit\RelevantCalendar;

use App\Models\CalendarEvent;
use App\Services\RelevantCalendar\CalendarRecurrenceService;
use Carbon\Carbon;
use Tests\TestCase;

class CalendarRecurrenceServiceTest extends TestCase
{
    public function test_it_matches_last_business_day_monthly_rule(): void
    {
        $service = new CalendarRecurrenceService();
        $event = new CalendarEvent([
            'start_date' => '2026-01-30',
            'event_kind' => CalendarEvent::KIND_SERIES_MASTER,
            'is_recurring' => true,
        ]);

        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_mode' => 'last_business_day',
            'starts_on' => '2026-01-30',
        ];

        $this->assertTrue($service->matchesDate($event, Carbon::parse('2026-02-27'), $rule));
        $this->assertFalse($service->matchesDate($event, Carbon::parse('2026-02-28'), $rule));
    }

    public function test_it_respects_weekly_intervals(): void
    {
        $service = new CalendarRecurrenceService();
        $event = new CalendarEvent([
            'start_date' => '2026-07-06',
            'event_kind' => CalendarEvent::KIND_SERIES_MASTER,
            'is_recurring' => true,
        ]);

        $rule = [
            'frequency' => 'weekly',
            'interval' => 2,
            'weekdays' => ['Monday'],
            'starts_on' => '2026-07-06',
        ];

        $this->assertTrue($service->matchesDate($event, Carbon::parse('2026-07-06'), $rule));
        $this->assertFalse($service->matchesDate($event, Carbon::parse('2026-07-13'), $rule));
        $this->assertTrue($service->matchesDate($event, Carbon::parse('2026-07-20'), $rule));
    }
}
