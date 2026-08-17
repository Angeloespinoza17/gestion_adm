<?php

namespace Tests\Unit\LibroDigital;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ForwardOnlyMigrationTest extends TestCase
{
    public static function migrations(): array
    {
        return array_map(fn (string $path) => [$path], glob(__DIR__.'/../../../database/migrations/2026_08_13_*.php') ?: []);
    }

    #[DataProvider('migrations')]
    public function test_lcd_migrations_are_additive_and_have_no_destructive_rollback(string $path): void
    {
        $contents = file_get_contents($path);

        $this->assertStringContainsString('Forward-only migration', $contents);
        $this->assertDoesNotMatchRegularExpression('/drop(?:IfExists|Column|Table)?\s*\(/i', $contents);
        $this->assertDoesNotMatchRegularExpression('/truncate\s*\(/i', $contents);
        $this->assertDoesNotMatchRegularExpression('/cascadeOnDelete\s*\(/i', $contents);
        $this->assertDoesNotMatchRegularExpression('/onDelete\s*\(\s*[\'\"]cascade/i', $contents);
    }
}
