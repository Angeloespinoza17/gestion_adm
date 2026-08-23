<?php

return [
    /*
    | El Libro Digital se despliega apagado. La activacion productiva requiere
    | preflight, perfiles normativos vigentes y permisos institucionales.
    */
    'enabled' => (bool) env('LCD_ENABLED', false),

    'timezone' => env('LCD_TIMEZONE', 'America/Santiago'),

    'default_school' => [
        'rbd' => env('LCD_SCHOOL_RBD', env('INFIRMARY_SCHOOL_RBD', '6830')),
        'name' => env('LCD_SCHOOL_NAME', env('INFIRMARY_SCHOOL_NAME', 'COLEGIO NUESTRA SEÑORA DEL CARMEN')),
        'legal_name' => env('LCD_SCHOOL_LEGAL_NAME'),
    ],

    'storage' => [
        'disk' => env('LCD_STORAGE_DISK', 'local'),
        'root' => 'private/libro-digital',
        'max_file_kb' => (int) env('LCD_MAX_FILE_KB', 20480),
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'text/csv',
            'application/json',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ],

    'identity_verifier' => [
        'driver' => env('LCD_IDENTITY_VERIFIER', 'disabled'),
        'transactional_url' => env('LCD_IDENTITY_TRANSACTIONAL_URL'),
        'bulk_url' => env('LCD_IDENTITY_BULK_URL'),
        'timeout_seconds' => (int) env('LCD_IDENTITY_TIMEOUT', 10),
        'connect_timeout_seconds' => (int) env('LCD_IDENTITY_CONNECT_TIMEOUT', 3),
        // Se deja sin valor hasta que el contrato institucional fije una ventana.
        'max_timestamp_skew_seconds' => env('LCD_IDENTITY_MAX_TIMESTAMP_SKEW'),
        'rate_limit_attempts' => (int) env('LCD_IDENTITY_RATE_LIMIT_ATTEMPTS', 5),
        'rate_limit_decay_seconds' => (int) env('LCD_IDENTITY_RATE_LIMIT_DECAY', 300),
        'circuit_breaker_failures' => (int) env('LCD_IDENTITY_CIRCUIT_FAILURES', 5),
        'circuit_breaker_seconds' => (int) env('LCD_IDENTITY_CIRCUIT_SECONDS', 60),
        'healthcheck_url' => env('LCD_IDENTITY_HEALTHCHECK_URL'),
    ],

    'ede' => [
        'enabled' => (bool) env('LCD_EDE_ENABLED', false),
        'version' => env('LCD_EDE_VERSION'),
        'validator_image' => env('LCD_EDE_VALIDATOR_IMAGE', 'edemineduc/etl'),
        'validator_digest' => env('LCD_EDE_VALIDATOR_DIGEST'),
        'validator_user' => env('LCD_EDE_VALIDATOR_USER'),
        'timeout_seconds' => (int) env('LCD_EDE_TIMEOUT', 900),
        // Debe habilitarse solo luego de archivar y probar el contrato exacto
        // parse/insert/check del artefacto oficial vigente.
        'command_contract_verified' => (bool) env('LCD_EDE_COMMAND_CONTRACT_VERIFIED', false),
        // JSON arrays de argumentos, versionados por operación. Ejemplo solo
        // después de verificar el contrato: ["check","{input}","{output}"]
        'commands' => [
            'parse' => json_decode((string) env('LCD_EDE_PARSE_COMMAND', '[]'), true) ?: [],
            'insert' => json_decode((string) env('LCD_EDE_INSERT_COMMAND', '[]'), true) ?: [],
            'check' => json_decode((string) env('LCD_EDE_CHECK_COMMAND', '[]'), true) ?: [],
        ],
        // El exit code del contenedor no basta para declarar un archivo válido.
        // Estos valores se configuran solo después de archivar el esquema real
        // del reporte generado por la operación check.
        'validation_report' => [
            'success_path' => env('LCD_EDE_CHECK_SUCCESS_PATH'),
            'success_value' => json_decode((string) env('LCD_EDE_CHECK_SUCCESS_VALUE', 'true'), true),
        ],
    ],

    'sige' => [
        'driver' => env('LCD_SIGE_DRIVER', 'disabled'),
    ],

    'reports' => [
        'expires_days' => (int) env('LCD_REPORT_EXPIRES_DAYS', 7),
        // En desarrollo local no siempre existe un worker de cola. La ficha
        // curricular es acotada y se genera en la misma solicitud para evitar
        // exportaciones detenidas en "en cola". Producción conserva la cola.
        'sync_curriculum_program_exports' => env(
            'LCD_CURRICULUM_PROGRAM_PDF_SYNC',
            env('APP_ENV') === 'local',
        ),
        // El XLSX curricular se genera por streaming; este límite sigue siendo
        // deliberado para evitar consultas/exportaciones institucionales sin cota.
        // El servicio lo limita además a un máximo absoluto de 10.000 filas.
        'curriculum_max_rows' => (int) env('LCD_CURRICULUM_EXPORT_MAX_ROWS', 10000),
    ],

    'curriculum_visualizations' => [
        // La clave incorpora versión/hash de catálogo, activación, contexto y
        // filtros. Debe funcionar con el store file predeterminado, sin tags.
        'cache_ttl_seconds' => (int) env('LCD_CURRICULUM_VISUALIZATION_CACHE_TTL', 600),
        'leaf_threshold' => (int) env('LCD_CURRICULUM_VISUALIZATION_LEAF_THRESHOLD', 500),
        'graph_node_limit' => (int) env('LCD_CURRICULUM_VISUALIZATION_GRAPH_NODE_LIMIT', 300),
    ],

    'curriculum_import' => [
        // Los PDF se conservan cifrados en almacenamiento privado. El proceso
        // trabaja sobre una copia temporal y nunca publica candidatos directo.
        'max_pdf_kb' => (int) env('LCD_CURRICULUM_PDF_MAX_KB', 40960),
        'ocr_min_text_chars' => (int) env('LCD_CURRICULUM_OCR_MIN_TEXT_CHARS', 40),
        'ocr_driver' => env('LCD_CURRICULUM_OCR_DRIVER', 'disabled'),
        'queue' => env('LCD_CURRICULUM_IMPORT_QUEUE', 'curriculum-imports'),
        // smalot conserva el árbol PDF completo durante la extracción. Este
        // límite solo lo aplica el job dedicado y se valida entre 256 y 2048 MB.
        'worker_memory_limit' => env('LCD_CURRICULUM_IMPORT_MEMORY_LIMIT', '512M'),
    ],

    'attachments' => [
        'scan_driver' => env('LCD_MALWARE_SCAN_DRIVER', 'disabled'),
        'clamav_socket' => env('LCD_CLAMAV_SOCKET'),
    ],
];
