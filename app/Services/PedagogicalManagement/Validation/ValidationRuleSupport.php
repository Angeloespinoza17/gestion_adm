<?php

namespace App\Services\PedagogicalManagement\Validation;

use App\DTO\PedagogicalManagement\ValidationFinding;

trait ValidationRuleSupport
{
    /** @return array<string,mixed> */
    protected function finding(
        string $code,
        string $category,
        string $severity,
        string $outcome,
        string $reliability,
        string $title,
        string $message,
        ?array $detected = null,
        ?array $expected = null,
        ?string $fieldPath = null,
        ?string $excerpt = null,
        ?int $page = null,
        bool $blocking = false,
    ): array {
        return ValidationFinding::make(
            $code, $category, $severity, $outcome, $reliability, $title, $message,
            $detected, $expected, $fieldPath, $excerpt, $page, $blocking,
        );
    }

    /** @return array<string,mixed> */
    protected function pass(string $code, string $category, string $title, string $message): array
    {
        return $this->finding($code, $category, 'info', 'pass', 'exact', $title, $message);
    }

    /** @return array<string,mixed> */
    protected function notEvaluable(string $code, string $category, string $title, string $message): array
    {
        return $this->finding($code, $category, 'info', 'not_evaluable', 'not_evaluable', $title, $message);
    }
}
