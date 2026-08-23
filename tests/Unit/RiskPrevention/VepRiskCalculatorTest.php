<?php

namespace Tests\Unit\RiskPrevention;

use App\Enums\RiskPrevention\RiskMatrixStatus;
use App\Services\RiskPrevention\LegacyRiskValueNormalizer;
use App\Services\RiskPrevention\VepRiskCalculator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VepRiskCalculatorTest extends TestCase
{
    #[DataProvider('vepCases')]
    public function test_it_calculates_the_canonical_vep_classification(
        int $probability,
        int $consequence,
        int $expectedScore,
        string $expectedLevel,
        bool $severeWarning,
    ): void {
        $result = (new VepRiskCalculator)->calculate($probability, $consequence);

        $this->assertSame($expectedScore, $result['vep']);
        $this->assertSame($expectedLevel, $result['risk_level_label']);
        $this->assertSame($severeWarning, $result['severe_consequence_warning']);
    }

    public static function vepCases(): array
    {
        return [
            'one by one' => [1, 1, 1, 'Tolerable', false],
            'one by two' => [1, 2, 2, 'Tolerable', false],
            'two by one' => [2, 1, 2, 'Tolerable', false],
            'two by two' => [2, 2, 4, 'Moderado', false],
            'four by one' => [4, 1, 4, 'Moderado', false],
            'severe consequence with moderate score' => [1, 4, 4, 'Moderado', true],
            'important probability two' => [2, 4, 8, 'Importante', true],
            'important consequence two' => [4, 2, 8, 'Importante', false],
            'intolerable' => [4, 4, 16, 'Intolerable', true],
        ];
    }

    public function test_it_rejects_values_outside_the_versioned_scale(): void
    {
        $this->expectException(ValidationException::class);
        (new VepRiskCalculator)->calculate(3, 4);
    }

    public function test_status_transitions_preserve_approved_immutability(): void
    {
        $this->assertTrue(RiskMatrixStatus::Draft->canTransitionTo(RiskMatrixStatus::InReview));
        $this->assertTrue(RiskMatrixStatus::InReview->canTransitionTo(RiskMatrixStatus::Approved));
        $this->assertFalse(RiskMatrixStatus::Approved->isEditable());
        $this->assertFalse(RiskMatrixStatus::Approved->canTransitionTo(RiskMatrixStatus::Draft));
        $this->assertTrue(RiskMatrixStatus::Approved->canTransitionTo(RiskMatrixStatus::Superseded));
    }

    public function test_it_normalizes_legacy_excel_values_without_trusting_labels(): void
    {
        $normalizer = new LegacyRiskValueNormalizer;

        $legacyFactor = $normalizer->vepFactor('Baja (2)', 'probability');
        $this->assertSame(2, $legacyFactor['value']);
        $this->assertNotNull($legacyFactor['warning']);
        $this->assertTrue($normalizer->boolean('SI'));
        $this->assertTrue($normalizer->boolean('Sí'));
        $this->assertFalse($normalizer->boolean('No'));
        $this->assertFalse($normalizer->boolean('NO'));
        $this->assertSame('non_routine', $normalizer->routineType('No rutinaria'));
        $this->assertSame('engineering', $normalizer->hierarchy('Control de ingeniería'));
        $this->assertSame('quarterly', $normalizer->periodicity('Trimestral'));
    }
}
