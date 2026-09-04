<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

class PresentationStyleContract
{
    /** @param array<string,mixed> $course @return array<string,mixed> */
    public function build(string $style, string $palette, array $course = []): array
    {
        $profiles = (array) config('class_presentations.style_profiles', []);
        $style = array_key_exists($style, $profiles) ? $style : 'institutional';
        $profile = (array) ($profiles[$style] ?? []);
        $palette = $palette === 'automatic' ? $this->automaticPalette($style, $course) : $palette;
        $paletteCollection = $style === 'children'
            ? (array) config('class_presentations.children_palette_tokens', [])
            : (array) config('class_presentations.palette_tokens', []);
        if (! array_key_exists($palette, $paletteCollection)) {
            $palette = 'institutional';
        }

        return [
            'version' => 'v2.0',
            'style_key' => $style,
            'style_label' => (string) config("class_presentations.options.visual_style.{$style}", $style),
            'palette_key' => $palette,
            'palette_label' => (string) config("class_presentations.options.palette.{$palette}", $palette),
            'description' => (string) ($profile['description'] ?? ''),
            'heading_font' => (string) ($profile['heading_font'] ?? 'Aptos Display'),
            'body_font' => (string) ($profile['body_font'] ?? 'Aptos'),
            'geometry' => (string) ($profile['geometry'] ?? 'structured'),
            'traits' => array_values((array) ($profile['traits'] ?? [])),
            'colors' => (array) ($paletteCollection[$palette] ?? []),
            'composition_rules' => array_values((array) ($profile['composition_rules'] ?? [])),
            'avoid' => array_values((array) ($profile['avoid'] ?? [])),
            'course_context' => [
                'name' => $course['name'] ?? null,
                'level' => $course['level'] ?? null,
            ],
        ];
    }

    /** @param array<string,mixed> $configuration @return array<string,mixed> */
    public function ensure(array $configuration, array $course = []): array
    {
        foreach (array_keys((array) config('class_presentations.multiple_options', [])) as $key) {
            $configuration[$key] = $this->normalizeSelections($configuration[$key] ?? []);
        }
        $configuration['style_contract'] = $this->build(
            (string) ($configuration['visual_style'] ?? 'institutional'),
            (string) ($configuration['palette'] ?? 'automatic'),
            $course,
        );

        return $configuration;
    }

    /** @return list<string> */
    public function normalizeSelections(mixed $value): array
    {
        if (is_string($value) || is_numeric($value)) {
            $value = [$value];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), is_array($value) ? $value : []),
            static fn (string $item): bool => $item !== '',
        )));
    }

    /** @param array<string,mixed> $course */
    private function automaticPalette(string $style, array $course): string
    {
        if ($style === 'children') {
            return 'warm';
        }
        $context = mb_strtolower(implode(' ', array_filter([
            (string) ($course['name'] ?? ''),
            (string) ($course['level'] ?? ''),
        ])));

        return str_contains($context, 'ciencia') || str_contains($context, 'naturaleza') ? 'green' : 'institutional';
    }
}
