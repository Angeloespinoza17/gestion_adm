<?php

return [
    /*
     * These are the official source pages confirmed by the institution on
     * 22-08-2026. The operational matrix is resolved from the active,
     * evidence-backed LCD curriculum catalog; these scopes prevent TP or
     * artistic specialities from being enabled by a source that does not
     * declare them.
     */
    'sources' => [
        'parvularia' => [
            'label' => 'Bases Curriculares de Educación Parvularia',
            'url' => 'https://www.curriculumnacional.cl/recursos/educacion-parvularia-vigentes-2019',
            'grade_codes' => ['NT1', 'NT2'],
            'tracks' => ['PARVULARIA'],
        ],
        'basica_1_6' => [
            'label' => 'Bases Curriculares de 1° a 6° Básico',
            'url' => 'https://www.curriculumnacional.cl/recursos/bases-curriculares-1-6-basico',
            'grade_codes' => ['1B', '2B', '3B', '4B', '5B', '6B'],
            'tracks' => ['GENERAL'],
        ],
        'basica_7_media_2' => [
            'label' => 'Bases Curriculares de 7° Básico a 2° Medio',
            'url' => 'https://www.curriculumnacional.cl/recursos/bases-curriculares-7o-basico-2o-medio',
            'grade_codes' => ['7B', '8B', '1M', '2M'],
            'tracks' => ['GENERAL'],
        ],
        'media_3_4' => [
            'label' => 'Bases Curriculares de 3° y 4° Medio',
            'url' => 'https://www.curriculumnacional.cl/recursos/bases-curriculares-3-4-medio',
            'grade_codes' => ['3M', '4M'],
            'tracks' => ['GENERAL', 'HC'],
        ],
    ],
];
