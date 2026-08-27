<?php

namespace App\DTO\PedagogicalManagement;

final class ValidationFinding
{
    /**
     * @param array<string,mixed>|null $detected
     * @param array<string,mixed>|null $expected
     * @return array<string,mixed>
     */
    public static function make(
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
        return [
            'code' => $code,
            'category' => $category,
            'severity' => $severity,
            'outcome' => $outcome,
            'reliability' => $reliability,
            'title' => $title,
            'message' => $message,
            'detected_value' => $detected,
            'expected_value' => $expected,
            'field_path' => $fieldPath,
            'source_excerpt' => $excerpt,
            'page_number' => $page,
            'is_blocking' => $blocking,
        ];
    }
}
