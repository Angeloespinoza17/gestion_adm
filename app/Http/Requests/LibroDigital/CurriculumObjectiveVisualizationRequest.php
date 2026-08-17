<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CurriculumObjectiveVisualizationRequest extends ListCurriculumObjectivesRequest
{
    public const DEFAULT_HIERARCHY = [
        'subject',
        'curricular_group',
        'grade',
        'objective',
    ];

    public const DIMENSIONS = [
        'catalog',
        'education_level',
        'grade',
        'formation',
        'subject',
        'curricular_group',
        'objective_type',
        'source',
        'status',
        'objective',
    ];

    public const VIEWS = [
        'treemap',
        'sunburst',
        'circle_packing',
        'sankey',
        'icicle',
        'radial_tree',
        'network',
        'mind_map',
    ];

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'view' => ['sometimes', 'string', Rule::in(self::VIEWS)],
            'hierarchy' => ['sometimes', 'array', 'min:1', 'max:5'],
            'hierarchy.*' => ['required', 'string', 'distinct', Rule::in(self::DIMENSIONS)],
            'scope' => ['sometimes', 'string', Rule::in(['filtered', 'catalog'])],
            'max_depth' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'include_leaves' => ['sometimes', 'boolean'],
            'root_node' => [
                'sometimes',
                'nullable',
                'string',
                'max:96',
                'regex:/^(?:catalog|education_level|grade|formation|subject|curricular_group|objective_type|source|status|objective):[a-f0-9]{64}$/',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hierarchy = $this->input('hierarchy', self::DEFAULT_HIERARCHY);
            if (! is_array($hierarchy)) {
                return;
            }

            $objectivePosition = array_search('objective', $hierarchy, true);
            if ($objectivePosition !== false && $objectivePosition !== array_key_last($hierarchy)) {
                $validator->errors()->add(
                    'hierarchy',
                    'La dimensión objective solo puede ocupar el último nivel de la jerarquía.',
                );
            }

            $view = (string) $this->input('view', 'treemap');
            $effectiveDepth = min(count($hierarchy), (int) $this->input('max_depth', count($hierarchy)));
            if ($view === 'sankey' && $effectiveDepth > 4) {
                $validator->errors()->add(
                    'hierarchy',
                    'La vista Sankey admite como máximo cuatro columnas jerárquicas.',
                );
            }
            $hasRootNode = trim((string) $this->input('root_node', '')) !== '';
            if ($view === 'radial_tree' && ! $hasRootNode && (int) $this->input('max_depth', 3) > 3) {
                $validator->errors()->add(
                    'max_depth',
                    'El árbol radial admite una profundidad inicial máxima de tres niveles.',
                );
            }
        });
    }

    /** @return list<string> */
    public function hierarchyDimensions(): array
    {
        $hierarchy = $this->validated('hierarchy', self::DEFAULT_HIERARCHY);

        return array_values(array_map('strval', is_array($hierarchy) ? $hierarchy : self::DEFAULT_HIERARCHY));
    }

    protected function prepareForValidation(): void
    {
        $hierarchy = $this->input('hierarchy');
        if (is_string($hierarchy)) {
            $hierarchy = array_values(array_filter(
                array_map(fn (string $item): string => trim($item), explode(',', $hierarchy)),
                fn (string $item): bool => $item !== '',
            ));
        }

        $includeLeaves = $this->input('include_leaves');
        if (is_string($includeLeaves) && in_array(mb_strtolower($includeLeaves), ['true', 'false'], true)) {
            $includeLeaves = mb_strtolower($includeLeaves) === 'true';
        }

        $merge = [];
        if ($hierarchy !== null) {
            $merge['hierarchy'] = $hierarchy;
        }
        if ($includeLeaves !== null) {
            $merge['include_leaves'] = $includeLeaves;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
