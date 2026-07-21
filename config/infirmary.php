<?php

return [
    'school_insurance_certificate' => [
        'institution_type' => (int) env('INFIRMARY_SCHOOL_INSTITUTION_TYPE', 3),
        'rbd' => env('INFIRMARY_SCHOOL_RBD', '6830'),
        'establishment_name' => env('INFIRMARY_SCHOOL_NAME', 'COLEGIO NUESTRA SEÑORA DEL CARMEN'),
        'commune' => env('INFIRMARY_SCHOOL_COMMUNE', 'Valdivia'),
        'city' => env('INFIRMARY_SCHOOL_CITY', 'Valdivia'),
        'commune_code' => env('INFIRMARY_SCHOOL_COMMUNE_CODE', '14101'),
        'schedule' => env('INFIRMARY_SCHOOL_SCHEDULE', 'Mañana'),
        'logo_url' => env('INFIRMARY_SCHOOL_LOGO_URL', '/brand/logo-cnsc.png'),
    ],
];
