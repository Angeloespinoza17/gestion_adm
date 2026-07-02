<?php

namespace Tests\Unit\Support;

use App\Support\Rut;
use PHPUnit\Framework\TestCase;

class RutTest extends TestCase
{
    public function test_it_normalizes_rut_values(): void
    {
        $this->assertSame('12345678-5', Rut::normalize('12.345.678-5'));
        $this->assertSame('76086428-5', Rut::normalize('760864285'));
    }

    public function test_it_validates_known_rut_values(): void
    {
        $this->assertTrue(Rut::isValid('12.345.678-5'));
        $this->assertTrue(Rut::isValid('76086428-5'));
        $this->assertFalse(Rut::isValid('12.345.678-9'));
        $this->assertFalse(Rut::isValid('abc'));
    }
}
