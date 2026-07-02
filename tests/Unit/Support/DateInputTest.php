<?php

namespace Tests\Unit\Support;

use App\Support\DateInput;
use PHPUnit\Framework\TestCase;

class DateInputTest extends TestCase
{
    public function test_it_normalizes_common_date_formats(): void
    {
        $this->assertSame('1993-07-17', DateInput::normalize('17/07/1993'));
        $this->assertSame('2020-10-01', DateInput::normalize('01-10-2020'));
        $this->assertSame('2020-10-01', DateInput::normalize('2020-10-01'));
    }

    public function test_it_returns_null_for_empty_values(): void
    {
        $this->assertNull(DateInput::normalize(''));
        $this->assertNull(DateInput::normalize(null));
    }
}
