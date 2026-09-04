<?php

return [
    'storage' => [
        'disk' => env('PEDAGOGICAL_INSTRUMENTS_DISK', 'local'),
        'root' => trim((string) env('PEDAGOGICAL_INSTRUMENTS_ROOT', 'private/pedagogical-management/instruments'), '/'),
        'max_file_kb' => (int) env(
            'PEDAGOGICAL_INSTRUMENTS_MAX_FILE_KB',
            env('PEDAGOGICAL_INSTRUMENTS_MAX_PDF_KB', 30720),
        ),
    ],

    'analysis' => [
        'queue' => env('PEDAGOGICAL_INSTRUMENTS_QUEUE', 'pedagogical-instruments'),
        'minimum_text_characters' => (int) env('PEDAGOGICAL_INSTRUMENTS_MIN_TEXT_CHARS', 40),
        'rules_version' => 'deterministic-v1.0.0',
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'model' => env('OPENAI_PEDAGOGICAL_MODEL', 'gpt-5.4-mini'),
        'timeout_seconds' => (int) env('OPENAI_PEDAGOGICAL_TIMEOUT', 180),
        'prompt_version' => 'document-review-v1.3.0',
        'max_output_tokens' => (int) env('OPENAI_PEDAGOGICAL_MAX_OUTPUT_TOKENS', 10000),
    ],

    'statistics' => [
        'rubric_version' => 'institutional-review-v1.0.0',
        'cache_seconds' => (int) env('PEDAGOGICAL_STATISTICS_CACHE_SECONDS', 300),
        'minimum_reports' => 5,
        'minimum_comparable_pairs' => 3,
    ],

    'review_criteria' => [
        [
            'code' => '2.1',
            'dimension' => 'Alineación curricular',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'El instrumento evalúa uno o más OA del programa de estudio correspondiente al nivel.',
        ],
        [
            'code' => '2.2',
            'dimension' => 'Alineación curricular',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Los indicadores de evaluación están seleccionados de acuerdo con los OA del programa de estudio correspondiente al nivel.',
        ],
        [
            'code' => '2.3',
            'dimension' => 'Alineación curricular',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Existe coherencia entre las habilidades o destrezas, los contenidos y las actitudes evaluadas con el instrumento.',
        ],
        [
            'code' => '2.4',
            'dimension' => 'Alineación curricular',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Existe coherencia entre los OA, las actividades y las preguntas o actividades del instrumento.',
        ],
        [
            'code' => '3.1',
            'dimension' => 'Calidad del instrumento',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Las instrucciones son claras y precisas.',
        ],
        [
            'code' => '3.2',
            'dimension' => 'Calidad del instrumento',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Las preguntas o actividades son adecuadas a lo establecido en los planes y programas del nivel.',
        ],
        [
            'code' => '3.3',
            'dimension' => 'Calidad del instrumento',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'El instrumento de evaluación es coherente con el tipo de evaluación, el instrumento y la técnica empleada.',
        ],
        [
            'code' => '3.4',
            'dimension' => 'Calidad del instrumento',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Cumple con los criterios de las indicaciones institucionales para la elaboración de instrumentos.',
        ],
        [
            'code' => '4.1',
            'dimension' => 'Aspectos formales e institucionales',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Incluye logo institucional, fecha calendarizada, departamento, asignatura, docente, unidad de aprendizaje y espacios para nombre, curso y fecha del estudiante.',
        ],
        [
            'code' => '4.2',
            'dimension' => 'Aspectos formales e institucionales',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Declara OA, indicadores, capacidades, destrezas y contenido según el modelo sociocognitivo, y registra las destrezas con su puntaje total y espacio para el puntaje obtenido.',
        ],
        [
            'code' => '4.3',
            'dimension' => 'Acceso universal y presentación',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Incorpora la declaración institucional de acceso universal y presenta tipografía legible, tamaño pertinente, interlineado mínimo 1,15, alineación funcional y recursos para destacar información relevante.',
        ],
        [
            'code' => '4.4',
            'dimension' => 'Acceso universal y presentación',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Las imágenes son nítidas y de tamaño suficiente, y el texto resguarda ortografía, construcción gramatical comprensible, ausencia de redundancias y adecuación al rango etario.',
        ],
        [
            'code' => '4.5',
            'dimension' => 'Acceso universal y presentación',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Organiza el instrumento y sus ítems en complejidad creciente e incorpora, cuando sea viable, apoyos, palabras clave o preguntas de inducción que activen el razonamiento sin entregar la respuesta.',
        ],
        [
            'code' => '4.6',
            'dimension' => 'Distribución y desempeño',
            'applicability' => 'Instrumentos con puntaje',
            'criterion' => 'La distribución del puntaje mantiene equilibrio entre los aspectos técnico-teóricos del OA y los elementos formales declarados en criterios o descriptores.',
        ],
        [
            'code' => '5.1',
            'dimension' => 'Rúbricas, pautas y productos',
            'applicability' => 'Rúbricas, pautas o evaluaciones de producto',
            'criterion' => 'Explicita objetivo, instrucciones generales, producto esperado, metodología de trabajo, tiempo o etapas y forma de entrega o ponderación cuando corresponda.',
        ],
        [
            'code' => '5.2',
            'dimension' => 'Rúbricas, pautas y productos',
            'applicability' => 'Rúbricas, pautas o evaluaciones de producto',
            'criterion' => 'Comunica sugerencias, condiciones relevantes, materiales o fuentes permitidas y no permitidas, proporciona un ejemplo o modelo de referencia y añade traducción cuando corresponda.',
        ],
        [
            'code' => '6.1',
            'dimension' => 'Evaluaciones tipo prueba',
            'applicability' => 'Evaluaciones escritas tipo prueba',
            'criterion' => 'Los ítems de desarrollo incluyen un modelo de respuesta y, cuando es necesario, la explicación del razonamiento esperado para favorecer la autonomía.',
        ],
        [
            'code' => '6.2',
            'dimension' => 'Evaluaciones tipo prueba',
            'applicability' => 'Evaluaciones escritas tipo prueba',
            'criterion' => 'Incluye al menos tres tipos de ítems y uno de ellos corresponde a comprensión lectora, desarrollo o argumentación escrita; además diversifica la forma de evidenciar las destrezas cuando es pertinente.',
        ],
        [
            'code' => '7.1',
            'dimension' => 'Metacognición y autorregulación',
            'applicability' => 'Todos los instrumentos',
            'criterion' => 'Incorpora un ítem adicional de autoevaluación y metacognición centrado en razonamiento, planificación, organización, estrategias, autorregulación o transferencia, y no sólo en la percepción o agrado de la actividad.',
        ],
    ],

    'pagination' => [
        'instruments' => 15,
        'validation_results' => 40,
        'max' => 100,
    ],
];
