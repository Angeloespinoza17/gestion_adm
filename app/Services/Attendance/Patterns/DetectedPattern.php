<?php

namespace App\Services\Attendance\Patterns;

class DetectedPattern
{
    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly float $confidence,
        public readonly int $occurrences,
        public readonly string $description,
        public readonly array $metrics,
        public readonly array $period,
    ) {}

    public function confidenceLabel(): string
    {
        return $this->confidence >= 0.8 ? 'strong' : ($this->confidence >= 0.6 ? 'recurrent' : 'possible');
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'confidence' => round($this->confidence, 2),
            'confidence_label' => $this->confidenceLabel(),
            'occurrences' => $this->occurrences,
            'description' => $this->description,
            'metrics' => $this->metrics,
            'period' => $this->period,
        ];
    }
}
