<?php

namespace Tests\Unit\Convivencia;

use PHPUnit\Framework\TestCase;

class ConvivenciaRice2026ConfigTest extends TestCase
{
    public function test_rice_2026_configuration_integrity(): void
    {
        $config = require dirname(__DIR__, 3).'/config/convivencia_rice_2026.php';
        $parts = $config['parts'];
        $protocols = $config['protocols'];

        $this->assertCount(28, $parts);
        $this->assertCount(17, $protocols);
        $this->assertNotEmpty($config['global_warnings']);

        $partCodes = array_column($parts, 'code');
        $protocolCodes = array_column($protocols, 'code');

        $this->assertSame(array_keys($parts), $partCodes);
        $this->assertSame(array_keys($protocols), $protocolCodes);
        $this->assertCount(count($partCodes), array_unique($partCodes));
        $this->assertCount(count($protocolCodes), array_unique($protocolCodes));
        $this->assertNotContains('RICE-P13', $protocolCodes);

        $stepCount = 0;
        $missingPartReferences = [];

        foreach ($protocols as $protocolCode => $protocol) {
            $this->assertArrayHasKey('warnings', $protocol, "{$protocolCode} debe conservar sus advertencias normativas.");
            $this->assertArrayHasKey('review_required', $protocol, "{$protocolCode} debe declarar review_required.");
            $this->assertIsArray($protocol['warnings']);
            $this->assertNotEmpty($protocol['warnings']);
            $this->assertTrue($protocol['review_required']);

            $stepCodes = array_column($protocol['steps'], 'code');
            $this->assertCount(count($stepCodes), array_unique($stepCodes), "{$protocolCode} contiene codigos de paso repetidos.");
            $stepCount += count($protocol['steps']);

            $links = $protocol['parts'];
            foreach ($protocol['steps'] as $step) {
                $links = array_merge($links, $step['parts']);
            }

            foreach ($links as $link) {
                $partCode = is_string($link) ? $link : ($link['code'] ?? null);
                if (! $partCode || ! isset($parts[$partCode])) {
                    $missingPartReferences[] = "{$protocolCode}:".($partCode ?? '<sin codigo>');
                }
            }
        }

        $this->assertSame(101, $stepCount);
        $this->assertSame([], array_values(array_unique($missingPartReferences)));
        $this->assertStringContainsString('10 dias habiles escolares', implode(' ', $protocols['RICE-P05']['warnings']));
        $this->assertStringContainsString('invierte las ramas de resultado', implode(' ', $protocols['RICE-P10']['warnings']));
        $this->assertStringContainsString('contradiccion', implode(' ', $protocols['RICE-P15']['warnings']));
    }
}
