<?php

return [
    'case_type' => [
        [
            'code' => 'caso_convivencia',
            'name' => 'Caso de convivencia escolar',
            'description' => 'Expediente de intervención, seguimiento y cierre ante una situación que afecta la convivencia escolar.',
            'color' => '#4f63d9',
        ],
        [
            'code' => 'situacion_reglamentaria',
            'name' => 'Situación reglamentaria',
            'description' => 'Hecho que requiere análisis y medidas conforme al Reglamento Interno.',
            'color' => '#6f42c1',
        ],
        [
            'code' => 'vulneracion_derechos',
            'name' => 'Vulneración o riesgo de vulneración',
            'description' => 'Situación que exige resguardo de derechos y eventual coordinación con la red externa.',
            'color' => '#d63384',
        ],
        [
            'code' => 'intervencion_preventiva',
            'name' => 'Intervención preventiva',
            'description' => 'Acompañamiento temprano para evitar escalamiento o reiteración de una situación.',
            'color' => '#0d6efd',
        ],
        [
            'code' => 'reconocimiento_positivo',
            'name' => 'Reconocimiento positivo',
            'description' => 'Registro de conductas, acciones o acuerdos que fortalecen el buen trato.',
            'color' => '#198754',
        ],
    ],

    'classification' => [
        ['code' => 'conflicto_convivencia', 'name' => 'Conflicto de convivencia', 'description' => 'Desacuerdo puntual o dificultad relacional sin indicadores de acoso sistemático.', 'color' => '#f59f00', 'protocol_codes' => ['RICE-P04']],
        ['code' => 'maltrato_escolar', 'name' => 'Maltrato o violencia escolar', 'description' => 'Agresión física, verbal, psicológica o social entre integrantes de la comunidad educativa.', 'color' => '#e8590c', 'protocol_codes' => ['RICE-P05', 'RICE-P07', 'RICE-P08', 'RICE-P09', 'RICE-P14', 'RICE-P17', 'RICE-P18']],
        ['code' => 'acoso_escolar', 'name' => 'Acoso escolar o ciberacoso', 'description' => 'Hostigamiento reiterado, con asimetría de poder, presencial o mediante medios digitales.', 'color' => '#c92a2a', 'protocol_codes' => ['RICE-P06']],
        ['code' => 'discriminacion', 'name' => 'Discriminación', 'description' => 'Trato desigual, exclusión o menoscabo asociado a una característica personal o social.', 'color' => '#7048e8', 'protocol_codes' => []],
        ['code' => 'vulneracion_derechos', 'name' => 'Vulneración de derechos o presunto delito', 'description' => 'Hechos que pueden afectar derechos fundamentales o revestir carácter de delito.', 'color' => '#d63384', 'protocol_codes' => ['RICE-P01', 'RICE-P15']],
        ['code' => 'violencia_intrafamiliar', 'name' => 'Violencia intrafamiliar', 'description' => 'Antecedentes de violencia en el contexto familiar que afectan a un estudiante.', 'color' => '#9c36b5', 'protocol_codes' => ['RICE-P02']],
        ['code' => 'connotacion_sexual', 'name' => 'Abuso o situación de connotación sexual', 'description' => 'Abuso sexual infantil, acoso sexual o conductas de connotación sexual.', 'color' => '#a61e4d', 'protocol_codes' => ['RICE-P03', 'RICE-P16']],
        ['code' => 'drogas_alcohol', 'name' => 'Drogas, alcohol o medicamentos', 'description' => 'Consumo, porte, suministro o exposición a alcohol, drogas o medicamentos.', 'color' => '#2b8a3e', 'protocol_codes' => ['RICE-P10']],
        ['code' => 'convivencia_digital', 'name' => 'Convivencia y dispositivos digitales', 'description' => 'Uso de dispositivos, grabaciones, mensajería o redes sociales que afecta la convivencia.', 'color' => '#1971c2', 'protocol_codes' => ['RICE-P11']],
        ['code' => 'asistencia_reiterada', 'name' => 'Inasistencia y riesgo de desvinculación', 'description' => 'Inasistencias reiteradas, riesgo de deserción o necesidad de revinculación educativa.', 'color' => '#0b7285', 'protocol_codes' => ['RICE-P12']],
        ['code' => 'observacion_positiva', 'name' => 'Convivencia positiva y reparación', 'description' => 'Acciones de buen trato, reparación o colaboración que corresponde reconocer y seguir.', 'color' => '#198754', 'protocol_codes' => []],
        ['code' => 'otra_situacion', 'name' => 'Otra situación de convivencia', 'description' => 'Situación no comprendida en las clasificaciones anteriores; debe describirse con precisión.', 'color' => '#6c757d', 'protocol_codes' => []],
    ],

    'subclassification' => [
        ['parent' => 'conflicto_convivencia', 'code' => 'desacuerdo_puntual', 'name' => 'Desacuerdo puntual'],
        ['parent' => 'conflicto_convivencia', 'code' => 'conflicto_aula', 'name' => 'Conflicto en aula'],
        ['parent' => 'conflicto_convivencia', 'code' => 'conflicto_recreo', 'name' => 'Conflicto en recreo o espacio común'],
        ['parent' => 'conflicto_convivencia', 'code' => 'gestion_colaborativa', 'name' => 'Mediación o gestión colaborativa'],

        ['parent' => 'maltrato_escolar', 'code' => 'agresion_verbal', 'name' => 'Agresión verbal'],
        ['parent' => 'maltrato_escolar', 'code' => 'agresion_fisica', 'name' => 'Agresión física'],
        ['parent' => 'maltrato_escolar', 'code' => 'maltrato_psicologico', 'name' => 'Maltrato psicológico o social'],
        ['parent' => 'maltrato_escolar', 'code' => 'adulto_a_estudiante', 'name' => 'Maltrato de adulto a estudiante'],
        ['parent' => 'maltrato_escolar', 'code' => 'estudiante_a_adulto', 'name' => 'Maltrato de estudiante a adulto'],
        ['parent' => 'maltrato_escolar', 'code' => 'entre_adultos', 'name' => 'Maltrato entre adultos'],
        ['parent' => 'maltrato_escolar', 'code' => 'entre_parvulos', 'name' => 'Maltrato entre párvulos'],

        ['parent' => 'acoso_escolar', 'code' => 'bullying', 'name' => 'Acoso escolar presencial'],
        ['parent' => 'acoso_escolar', 'code' => 'ciberacoso', 'name' => 'Ciberacoso o ciberbullying'],

        ['parent' => 'discriminacion', 'code' => 'discriminacion_genero_identidad', 'name' => 'Por sexo, género, identidad u orientación'],
        ['parent' => 'discriminacion', 'code' => 'discriminacion_origen', 'name' => 'Por nacionalidad, etnia u origen'],
        ['parent' => 'discriminacion', 'code' => 'discriminacion_discapacidad', 'name' => 'Por discapacidad o necesidad educativa'],
        ['parent' => 'discriminacion', 'code' => 'discriminacion_socioeconomica', 'name' => 'Por condición socioeconómica'],
        ['parent' => 'discriminacion', 'code' => 'discriminacion_otro', 'name' => 'Otra forma de discriminación'],

        ['parent' => 'vulneracion_derechos', 'code' => 'negligencia_cuidados', 'name' => 'Negligencia o falta de cuidados'],
        ['parent' => 'vulneracion_derechos', 'code' => 'maltrato_infantil', 'name' => 'Maltrato infantil'],
        ['parent' => 'vulneracion_derechos', 'code' => 'abandono_desproteccion', 'name' => 'Abandono o desprotección'],
        ['parent' => 'vulneracion_derechos', 'code' => 'presunto_delito', 'name' => 'Hecho eventualmente constitutivo de delito'],

        ['parent' => 'violencia_intrafamiliar', 'code' => 'violencia_directa', 'name' => 'Violencia directa contra estudiante'],
        ['parent' => 'violencia_intrafamiliar', 'code' => 'exposicion_violencia', 'name' => 'Exposición o testigo de violencia familiar'],

        ['parent' => 'connotacion_sexual', 'code' => 'abuso_sexual_infantil', 'name' => 'Abuso sexual infantil'],
        ['parent' => 'connotacion_sexual', 'code' => 'acoso_sexual', 'name' => 'Acoso sexual'],
        ['parent' => 'connotacion_sexual', 'code' => 'conducta_connotacion_sexual', 'name' => 'Conducta de connotación sexual'],

        ['parent' => 'drogas_alcohol', 'code' => 'consumo_alcohol', 'name' => 'Consumo o exposición a alcohol'],
        ['parent' => 'drogas_alcohol', 'code' => 'consumo_drogas', 'name' => 'Consumo o exposición a drogas'],
        ['parent' => 'drogas_alcohol', 'code' => 'porte_suministro', 'name' => 'Porte, suministro o eventual tráfico'],
        ['parent' => 'drogas_alcohol', 'code' => 'uso_medicamentos', 'name' => 'Uso indebido de medicamentos'],

        ['parent' => 'convivencia_digital', 'code' => 'uso_dispositivo', 'name' => 'Uso no autorizado de dispositivo personal'],
        ['parent' => 'convivencia_digital', 'code' => 'grabacion_difusion', 'name' => 'Grabación o difusión no autorizada'],
        ['parent' => 'convivencia_digital', 'code' => 'incidente_redes', 'name' => 'Incidente en mensajería o redes sociales'],

        ['parent' => 'asistencia_reiterada', 'code' => 'inasistencia_reiterada', 'name' => 'Inasistencia reiterada'],
        ['parent' => 'asistencia_reiterada', 'code' => 'riesgo_desercion', 'name' => 'Riesgo de deserción escolar'],
        ['parent' => 'asistencia_reiterada', 'code' => 'revinculacion_educativa', 'name' => 'Proceso de revinculación educativa'],

        ['parent' => 'observacion_positiva', 'code' => 'accion_buen_trato', 'name' => 'Acción destacada de buen trato'],
        ['parent' => 'observacion_positiva', 'code' => 'acuerdo_reparatorio', 'name' => 'Acuerdo o acción reparatoria'],
        ['parent' => 'observacion_positiva', 'code' => 'colaboracion_comunidad', 'name' => 'Colaboración con la comunidad'],

        ['parent' => 'otra_situacion', 'code' => 'falta_reglamento', 'name' => 'Otra falta al Reglamento Interno'],
        ['parent' => 'otra_situacion', 'code' => 'otro_antecedente', 'name' => 'Otro antecedente relevante'],
    ],

    'criticality' => [
        [
            'code' => 'baja',
            'name' => 'Baja',
            'description' => 'Situación puntual, sin riesgo inmediato. Requiere registro, orientación y seguimiento preventivo.',
            'color' => '#2f9e44',
            'metadata' => ['response' => 'Seguimiento preventivo', 'requires_immediate_safeguard' => false],
        ],
        [
            'code' => 'media',
            'name' => 'Media',
            'description' => 'Situación relevante o reiterada. Requiere intervención planificada, responsables y plazo de seguimiento.',
            'color' => '#f59f00',
            'metadata' => ['response' => 'Intervención prioritaria', 'requires_immediate_safeguard' => false],
        ],
        [
            'code' => 'alta',
            'name' => 'Alta',
            'description' => 'Existe riesgo significativo para la integridad o los derechos. Deben registrarse medidas de resguardo inmediatas.',
            'color' => '#e8590c',
            'metadata' => ['response' => 'Resguardo inmediato y evaluación de protocolo', 'requires_immediate_safeguard' => true],
        ],
        [
            'code' => 'critica',
            'name' => 'Crítica',
            'description' => 'Riesgo grave o inminente, posible delito o vulneración severa. Exige actuación urgente y evaluación de denuncia/derivación externa.',
            'color' => '#c92a2a',
            'metadata' => ['response' => 'Actuación urgente y activación de red/protocolo', 'requires_immediate_safeguard' => true],
        ],
    ],
];
