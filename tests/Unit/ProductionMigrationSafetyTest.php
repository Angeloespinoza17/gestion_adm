<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductionMigrationSafetyTest extends TestCase
{
    public static function pendingProductionMigrations(): array
    {
        $patterns = [
            __DIR__.'/../../database/migrations/2026_08_19_*.php',
            __DIR__.'/../../database/migrations/2026_08_20_*.php',
            __DIR__.'/../../database/migrations/2026_08_21_*.php',
            __DIR__.'/../../database/migrations/2026_08_22_*.php',
            __DIR__.'/../../database/migrations/2026_08_23_*.php',
        ];

        $paths = [];
        foreach ($patterns as $pattern) {
            $paths = array_merge($paths, glob($pattern) ?: []);
        }

        sort($paths);

        return array_map(fn (string $path): array => [$path], $paths);
    }

    #[DataProvider('pendingProductionMigrations')]
    public function test_pending_migrations_do_not_delete_production_records(string $path): void
    {
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertDoesNotMatchRegularExpression('/Schema::drop(?:IfExists|Table)?\s*\(/i', $contents, $path);
        $this->assertDoesNotMatchRegularExpression('/->dropColumn\s*\(/i', $contents, $path);
        $this->assertDoesNotMatchRegularExpression('/(?:->|::)delete\s*\(/i', $contents, $path);
        $this->assertDoesNotMatchRegularExpression('/(?:->|::)truncate\s*\(/i', $contents, $path);
    }
}
