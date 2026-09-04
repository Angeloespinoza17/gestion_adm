<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

class PresentationSchema
{
    /** @return array<string,mixed> */
    public function build(int $slideCount): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $source = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'publisher' => $nullableString,
                'url' => $nullableString,
            ],
            'required' => ['title', 'publisher', 'url'],
            'additionalProperties' => false,
        ];
        $visual = [
            'type' => ['object', 'null'],
            'properties' => [
                'kind' => ['type' => 'string', 'enum' => ['editable_shapes', 'diagram', 'timeline', 'chart', 'image_suggestion', 'none']],
                'description' => ['type' => 'string'],
                'alt_text' => ['type' => 'string'],
            ],
            'required' => ['kind', 'description', 'alt_text'],
            'additionalProperties' => false,
        ];
        $chart = [
            'type' => ['object', 'null'],
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['bar', 'line', 'pie']],
                'labels' => ['type' => 'array', 'items' => ['type' => 'string']],
                'datasets' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'values' => ['type' => 'array', 'items' => ['type' => 'number']],
                        ],
                        'required' => ['name', 'values'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['type', 'labels', 'datasets'],
            'additionalProperties' => false,
        ];
        $activity = [
            'type' => ['object', 'null'],
            'properties' => [
                'goal' => ['type' => 'string'],
                'modality' => ['type' => 'string'],
                'time_minutes' => ['type' => 'integer', 'minimum' => 0],
                'resources' => ['type' => 'array', 'items' => ['type' => 'string']],
                'instructions' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 6],
                'expected_product' => ['type' => 'string'],
                'sharing' => ['type' => 'string'],
            ],
            'required' => ['goal', 'modality', 'time_minutes', 'resources', 'instructions', 'expected_product', 'sharing'],
            'additionalProperties' => false,
        ];
        $assessment = [
            'type' => ['object', 'null'],
            'properties' => [
                'type' => ['type' => 'string'],
                'instructions' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 6],
                'success_criteria' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 6],
            ],
            'required' => ['type', 'instructions', 'success_criteria'],
            'additionalProperties' => false,
        ];
        $guideQuestion = [
            'type' => 'object',
            'properties' => [
                'prompt' => ['type' => 'string'],
                'expected_ideas' => ['type' => 'array', 'maxItems' => 4, 'items' => ['type' => 'string']],
                'follow_up' => ['type' => 'string'],
            ],
            'required' => ['prompt', 'expected_ideas', 'follow_up'],
            'additionalProperties' => false,
        ];
        $guideMisconception = [
            'type' => 'object',
            'properties' => [
                'signal' => ['type' => 'string'],
                'response' => ['type' => 'string'],
            ],
            'required' => ['signal', 'response'],
            'additionalProperties' => false,
        ];
        $teacherGuide = [
            'type' => 'object',
            'properties' => [
                'schema_version' => ['type' => 'string', 'enum' => ['v1.0']],
                'title' => ['type' => 'string'],
                'at_a_glance' => [
                    'type' => 'object',
                    'properties' => [
                        'purpose' => ['type' => 'string'],
                        'central_message' => ['type' => 'string'],
                        'curricular_alignment' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'maxItems' => 10,
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'evidence' => ['type' => 'string'],
                                ],
                                'required' => ['code', 'description', 'evidence'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'prior_knowledge' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                        'key_vocabulary' => [
                            'type' => 'array',
                            'maxItems' => 10,
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'term' => ['type' => 'string'],
                                    'teacher_definition' => ['type' => 'string'],
                                    'example' => ['type' => 'string'],
                                ],
                                'required' => ['term', 'teacher_definition', 'example'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'preparation' => [
                            'type' => 'object',
                            'properties' => [
                                'materials' => ['type' => 'array', 'maxItems' => 10, 'items' => ['type' => 'string']],
                                'before_class' => ['type' => 'array', 'maxItems' => 8, 'items' => ['type' => 'string']],
                                'room_setup' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                                'safety_and_privacy' => ['type' => 'array', 'maxItems' => 8, 'items' => ['type' => 'string']],
                            ],
                            'required' => ['materials', 'before_class', 'room_setup', 'safety_and_privacy'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'required' => ['purpose', 'central_message', 'curricular_alignment', 'prior_knowledge', 'key_vocabulary', 'preparation'],
                    'additionalProperties' => false,
                ],
                'timeline' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'phase' => ['type' => 'string', 'enum' => ['opening', 'development', 'closure']],
                            'title' => ['type' => 'string'],
                            'slide_numbers' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => $slideCount,
                                'items' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $slideCount],
                            ],
                            'minutes' => ['type' => 'integer', 'minimum' => 0],
                            'focus' => ['type' => 'string'],
                        ],
                        'required' => ['phase', 'title', 'slide_numbers', 'minutes', 'focus'],
                        'additionalProperties' => false,
                    ],
                ],
                'slide_script' => [
                    'type' => 'array',
                    'minItems' => $slideCount,
                    'maxItems' => $slideCount,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'slide_number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $slideCount],
                            'minutes' => ['type' => 'integer', 'minimum' => 0],
                            'purpose' => ['type' => 'string'],
                            'teacher_script' => ['type' => 'string'],
                            'teacher_actions' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'string']],
                            'questions' => ['type' => 'array', 'maxItems' => 3, 'items' => $guideQuestion],
                            'misconceptions' => ['type' => 'array', 'maxItems' => 3, 'items' => $guideMisconception],
                            'evidence_to_observe' => ['type' => 'array', 'maxItems' => 4, 'items' => ['type' => 'string']],
                            'transition' => ['type' => 'string'],
                        ],
                        'required' => ['slide_number', 'minutes', 'purpose', 'teacher_script', 'teacher_actions', 'questions', 'misconceptions', 'evidence_to_observe', 'transition'],
                        'additionalProperties' => false,
                    ],
                ],
                'activity_support' => [
                    'type' => 'array',
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'slide_number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $slideCount],
                            'setup' => ['type' => 'string'],
                            'grouping' => ['type' => 'string'],
                            'time_minutes' => ['type' => 'integer', 'minimum' => 0],
                            'facilitation_steps' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                            'monitoring_prompts' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'string']],
                            'expected_evidence' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'string']],
                            'contingency' => ['type' => 'string'],
                        ],
                        'required' => ['slide_number', 'setup', 'grouping', 'time_minutes', 'facilitation_steps', 'monitoring_prompts', 'expected_evidence', 'contingency'],
                        'additionalProperties' => false,
                    ],
                ],
                'assessment_support' => [
                    'type' => 'array',
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'slide_number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $slideCount],
                            'administration' => ['type' => 'string'],
                            'expected_evidence' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                            'success_criteria' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                            'feedback_prompts' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'string']],
                            'next_steps' => [
                                'type' => 'object',
                                'properties' => [
                                    'needs_support' => ['type' => 'string'],
                                    'ready' => ['type' => 'string'],
                                    'extension' => ['type' => 'string'],
                                ],
                                'required' => ['needs_support', 'ready', 'extension'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'required' => ['slide_number', 'administration', 'expected_evidence', 'success_criteria', 'feedback_prompts', 'next_steps'],
                        'additionalProperties' => false,
                    ],
                ],
                'differentiation' => [
                    'type' => 'object',
                    'properties' => [
                        'access' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                        'participation' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                        'expression' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                        'support' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                        'extension' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                    ],
                    'required' => ['access', 'participation', 'expression', 'support', 'extension'],
                    'additionalProperties' => false,
                ],
                'closure' => [
                    'type' => 'object',
                    'properties' => [
                        'closing_script' => ['type' => 'string'],
                        'formative_summary' => ['type' => 'string'],
                        'follow_up' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'string']],
                    ],
                    'required' => ['closing_script', 'formative_summary', 'follow_up'],
                    'additionalProperties' => false,
                ],
                'sources' => ['type' => 'array', 'items' => $source],
                'verification_warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['schema_version', 'title', 'at_a_glance', 'timeline', 'slide_script', 'activity_support', 'assessment_support', 'differentiation', 'closure', 'sources', 'verification_warnings'],
            'additionalProperties' => false,
        ];
        $selection = fn (string $key, int $max): array => [
            'type' => 'array',
            'minItems' => 1,
            'maxItems' => $max,
            'items' => ['type' => 'string', 'enum' => array_keys((array) config("class_presentations.options.{$key}", []))],
        ];
        $appliedConfiguration = [
            'type' => 'object',
            'properties' => [
                'visual_style' => ['type' => 'string', 'enum' => array_keys((array) config('class_presentations.options.visual_style', []))],
                'palette' => ['type' => 'string', 'enum' => array_keys((array) config('class_presentations.options.palette', []))],
                'methodologies' => $selection('methodology', (int) config('class_presentations.multiple_options.methodology.max', 3)),
                'activities' => $selection('activity', (int) config('class_presentations.multiple_options.activity.max', 3)),
                'assessments' => $selection('assessment', (int) config('class_presentations.multiple_options.assessment.max', 3)),
                'visual_resources' => $selection('visual_resources', (int) config('class_presentations.multiple_options.visual_resources.max', 4)),
                'style_contract_version' => ['type' => 'string', 'enum' => ['v2.0']],
            ],
            'required' => ['visual_style', 'palette', 'methodologies', 'activities', 'assessments', 'visual_resources', 'style_contract_version'],
            'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => [
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'], 'subtitle' => ['type' => 'string'],
                        'course' => ['type' => 'string'], 'subject' => ['type' => 'string'], 'unit' => ['type' => 'string'],
                        'duration_minutes' => ['type' => 'integer'], 'slide_count' => ['type' => 'integer', 'const' => $slideCount],
                        'central_message' => ['type' => 'string'],
                        'applied_configuration' => $appliedConfiguration,
                    ],
                    'required' => ['title', 'subtitle', 'course', 'subject', 'unit', 'duration_minutes', 'slide_count', 'central_message', 'applied_configuration'],
                    'additionalProperties' => false,
                ],
                'slides' => [
                    'type' => 'array', 'minItems' => $slideCount, 'maxItems' => $slideCount,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'number' => ['type' => 'integer'],
                            'type' => ['type' => 'string', 'enum' => ['cover', 'opening', 'objectives', 'introduction', 'explanation', 'comparison', 'process', 'timeline', 'case', 'example', 'activity', 'assessment', 'synthesis', 'conclusion', 'bibliography', 'closure']],
                            'pedagogical_function' => ['type' => 'string'],
                            'title' => ['type' => 'string'], 'main_idea' => ['type' => 'string'],
                            'visible_text' => ['type' => 'string'],
                            'bullets' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                            'highlighted_concepts' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string']],
                            'visual_resource' => $visual, 'chart_data' => $chart, 'alt_text' => $nullableString,
                            'speaker_notes' => $nullableString,
                            'estimated_minutes' => ['type' => 'integer', 'minimum' => 0],
                            'audience_question' => $nullableString,
                            'activity' => $activity, 'assessment' => $assessment,
                            'sources' => ['type' => 'array', 'items' => $source],
                        ],
                        'required' => ['number', 'type', 'pedagogical_function', 'title', 'main_idea', 'visible_text', 'bullets', 'highlighted_concepts', 'visual_resource', 'chart_data', 'alt_text', 'speaker_notes', 'estimated_minutes', 'audience_question', 'activity', 'assessment', 'sources'],
                        'additionalProperties' => false,
                    ],
                ],
                'teacher_guide' => $teacherGuide,
                'bibliography' => ['type' => 'array', 'items' => $source],
                'verification_warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['metadata', 'slides', 'teacher_guide', 'bibliography', 'verification_warnings'],
            'additionalProperties' => false,
        ];
    }
}
