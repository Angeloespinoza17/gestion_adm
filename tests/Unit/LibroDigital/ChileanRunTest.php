<?php

namespace Tests\Unit\LibroDigital;

use App\ValueObjects\LibroDigital\ChileanRun;
use PHPUnit\Framework\TestCase;

class ChileanRunTest extends TestCase
{
    public function test_it_normalizes_and_validates_a_synthetic_run(): void
    {
        $run = new ChileanRun('12.345.678-5');

        $this->assertSame('123456785', (string) $run);
        $this->assertSame('12.345.678-5', $run->formatted());
        $this->assertSame('***.***.78-5', $run->masked());
    }

    public function test_it_rejects_an_invalid_check_digit(): void
    {
        $this->assertFalse(ChileanRun::isValid('12.345.678-9'));
    }
}
