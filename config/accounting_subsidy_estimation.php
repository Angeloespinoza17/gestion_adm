<?php

return [
    'defaults' => [
        'jec' => true,
        'sep_category' => 'autonomo',
        'include_gratuity' => true,
        'concentration_band' => 'auto',
    ],

    'schedules' => [
        [
            'valid_from' => '2025-12-01',
            'valid_to' => '2026-05-31',
            'use_value' => 35789.0316,
            'label' => 'Valores vigentes desde diciembre de 2025',
        ],
        [
            'valid_from' => '2026-06-01',
            'valid_to' => null,
            'use_value' => 36299.2040,
            'label' => 'Valores vigentes desde junio de 2026 - Ley 21.806',
        ],
    ],

    'general_use_factors' => [
        'sin_jec' => [
            'parvularia' => 2.60943,
            'basica_1_6' => 2.33330,
            'basica_7_8' => 2.51282,
            'media_hc' => 2.78884,
        ],
        'jec' => [
            'parvularia' => 3.11037,
            'basica_1_6' => 3.11037,
            'basica_7_8' => 3.11957,
            'media_hc' => 3.68078,
        ],
    ],

    'sep_use_factors' => [
        'autonomo' => [
            'prioritario' => ['inicial' => 2.03280, 'superior' => 1.35480],
            'preferente' => ['inicial' => 1.01640, 'superior' => 0.67740],
        ],
        'emergente' => [
            'prioritario' => ['inicial' => 1.01640, 'superior' => 0.67740],
            'preferente' => ['inicial' => 0.50820, 'superior' => 0.33870],
        ],
    ],

    'concentration_use_factors' => [
        '60_plus' => ['inicial' => 0.30200, 'superior' => 0.20200],
        '45_60' => ['inicial' => 0.26900, 'superior' => 0.17900],
        '30_45' => ['inicial' => 0.20200, 'superior' => 0.13400],
        '15_30' => ['inicial' => 0.11800, 'superior' => 0.07800],
        'none' => ['inicial' => 0.0, 'superior' => 0.0],
    ],

    'gratuity_use_factor' => 0.45000,
    'pension_reform' => [
        'valid_from' => '2026-07-01',
        'use_factors' => [
            'parvularia' => 0.04170,
            'basica_1_6' => 0.03860,
            'basica_7_8' => 0.03860,
            'media_hc' => 0.03510,
        ],
    ],

    'sources' => [
        [
            'label' => 'Valores de Subvención Educacional desde junio de 2026',
            'url' => 'https://www.comunidadescolar.cl/wp-content/uploads/2026/06/valor-subvenciones-Junio2026-Ley-21806-reajuste-2_con_adicional_-del_14.pdf',
            'issuer' => 'Coordinación Nacional de Subvenciones - MINEDUC',
        ],
        [
            'label' => 'Valores de otras subvenciones desde junio de 2026',
            'url' => 'https://www.comunidadescolar.cl/wp-content/uploads/2026/06/otros-valores-subvenciones-Junio-2026-ley21806-Reaj-200-con-adicional-del-140.pdf',
            'issuer' => 'Coordinación Nacional de Subvenciones - MINEDUC',
        ],
        [
            'label' => 'DFL N° 2 de 1998, artículo 13',
            'url' => 'https://www.bcn.cl/leychile/navegar?idNorma=127911&idVersion=2023-02-09',
            'issuer' => 'Biblioteca del Congreso Nacional',
        ],
        [
            'label' => 'Ley N° 20.248, artículos 15 y 16',
            'url' => 'https://www.bcn.cl/leychile/navegar?idNorma=269001',
            'issuer' => 'Biblioteca del Congreso Nacional',
        ],
    ],
];
