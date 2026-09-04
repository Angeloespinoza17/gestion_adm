<?php

namespace App\Services\PedagogicalManagement\ClassPresentations\Canva;

use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use App\Models\PedagogicalManagement\ClassPresentation;

class CanvaTemplateFieldMapper
{
    /** @param array<string,mixed> $dataset @return array{compatible:bool,dataset:array<string,mixed>,expected_fields:list<string>,missing_fields:list<string>,invalid_type_fields:list<string>} */
    public function validate(array $dataset, int $slideCount): array
    {
        $fields = (array) ($dataset['dataset'] ?? $dataset);
        $expected = $this->expectedFields($slideCount);
        $missing = [];
        $invalidTypes = [];
        foreach ($expected as $field) {
            if (! array_key_exists($field, $fields)) {
                $missing[] = $field;
            } elseif ((string) data_get($fields, $field.'.type') !== 'text') {
                $invalidTypes[] = $field;
            }
        }

        return [
            'compatible' => $missing === [] && $invalidTypes === [],
            'dataset' => $fields,
            'expected_fields' => $expected,
            'missing_fields' => $missing,
            'invalid_type_fields' => $invalidTypes,
        ];
    }

    /** @param array<string,mixed> $deck @param array<string,mixed> $dataset @return array<string,array{type:string,text:string}> */
    public function map(ClassPresentation $presentation, array $deck, array $dataset): array
    {
        $slides = array_values((array) ($deck['slides'] ?? []));
        $expectedSlides = (int) data_get($presentation->configuration, 'slide_count', count($slides));
        if ($expectedSlides <= 0 || count($slides) !== $expectedSlides) {
            throw new CanvaIntegrationException('El contenido no está listo para construir la presentación en Canva.', 'CANVA_CONTENT_INVALID', 422);
        }
        $validation = $this->validate($dataset, $expectedSlides);
        if (! $validation['compatible']) {
            throw new CanvaIntegrationException(
                'La plantilla Canva no contiene todos los campos de texto requeridos para esta cantidad de diapositivas.',
                'CANVA_TEMPLATE_CONTRACT_INVALID',
                422,
            );
        }

        $metadata = (array) ($deck['metadata'] ?? []);
        $snapshot = (array) $presentation->curricular_snapshot;
        $objectives = collect(data_get($snapshot, 'objectives', []))->map(
            fn (array $objective): string => trim(((string) ($objective['code'] ?? '')).' · '.((string) ($objective['description'] ?? '')), ' ·'),
        )->filter()->implode("\n");
        $mapped = [
            'TITLE' => $this->text($metadata['title'] ?? $presentation->title),
            'SUBTITLE' => $this->text($metadata['subtitle'] ?? data_get($snapshot, 'unit.title')),
            'COURSE' => $this->text(data_get($snapshot, 'course.name')),
            'SUBJECT' => $this->text(data_get($snapshot, 'subject.name')),
            'UNIT' => $this->text(data_get($snapshot, 'unit.title')),
            'OBJECTIVES' => $this->text($objectives),
        ];
        foreach ($slides as $index => $slide) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $mapped["S{$number}_TITLE"] = $this->text($slide['title'] ?? '');
            $mapped["S{$number}_TEXT"] = $this->text($slide['visible_text'] ?? $slide['main_idea'] ?? '');
            $mapped["S{$number}_BULLETS"] = $this->text(collect((array) ($slide['bullets'] ?? []))->map(fn ($item): string => '• '.trim((string) $item))->implode("\n"));
        }

        return collect($mapped)->map(fn (string $text): array => ['type' => 'text', 'text' => $text])->all();
    }

    /** @return list<string> */
    public function expectedFields(int $slideCount): array
    {
        $fields = ['TITLE', 'SUBTITLE', 'COURSE', 'SUBJECT', 'UNIT', 'OBJECTIVES'];
        foreach (range(1, max(1, $slideCount)) as $number) {
            $prefix = 'S'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            array_push($fields, "{$prefix}_TITLE", "{$prefix}_TEXT", "{$prefix}_BULLETS");
        }

        return $fields;
    }

    private function text(mixed $value): string
    {
        $normalized = preg_replace("/\r\n?|\u{2028}|\u{2029}/u", "\n", trim((string) $value)) ?? '';

        return mb_substr($normalized, 0, max(100, (int) config('canva.max_field_characters', 5000)));
    }
}
