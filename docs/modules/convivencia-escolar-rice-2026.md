# Gestión de Convivencia Escolar — RICE 2026

## 1. Objetivo

Este módulo centraliza la apertura y gestión de casos de convivencia escolar, manteniendo la lógica de expediente utilizada por Psicología y Trabajo Social, pero incorporando las variables, etapas, medidas y plazos propios del Reglamento Interno de Convivencia Escolar (RICE 2026).

La solución debe permitir:

- abrir un caso desde una denuncia, bitácora u observación directa;
- activar uno o más protocolos sobre un caso o denuncia;
- saber en todo momento en qué paso se encuentra cada activación (`Paso N de M`), su responsable, plazo, requisitos y porcentaje de avance;
- registrar intervenciones, entrevistas, medidas protectoras, formativas, reparatorias, disciplinarias, derivaciones y evidencias;
- construir, editar, relacionar, archivar y reutilizar protocolos y sus partes;
- conservar una instantánea histórica de la definición utilizada en cada activación;
- exportar protocolos, activaciones y expedientes en PDF;
- analizar la operación mediante estadísticas agregadas y alertas accionables.

## 2. Principios de diseño

1. **Trazabilidad:** cada activación conserva su definición, etapas, componentes, plazos, responsables y cambios de estado.
2. **Debido proceso:** el sistema muestra requisitos, comunicaciones, evidencias y oportunidades de resolución o apelación sin decidir automáticamente el fondo de un caso.
3. **Protección inmediata:** las medidas de resguardo pueden registrarse desde el primer paso y no se presentan como una determinación de responsabilidad.
4. **Privacidad:** la visibilidad nominal se rige por permisos y por acceso al caso; el panel general utiliza información agregada.
5. **Edición segura:** cambiar un protocolo no altera activaciones históricas; eliminar equivale a archivar cuando existe uso previo.
6. **Fuente explícita:** cada protocolo indica versión, alcance, vigencia, referencia normativa y fuente reglamentaria.
7. **Rendimiento:** listados paginados, relaciones cargadas de forma dirigida, agregaciones en base de datos e índices para estados, plazos y relaciones principales.

## 3. Flujo operativo

```text
Ingreso o denuncia
        ↓
Evaluación inicial y resguardo inmediato
        ↓
Selección y activación del protocolo
        ↓
Paso actual + plazo + responsable + requisitos
        ↓
Partes del paso: entrevistas, comunicaciones, evidencias y medidas
        ↓
Completar requisitos y avanzar al paso siguiente
        ↓
Resolución, comunicación, apelación si corresponde
        ↓
Seguimiento y cierre trazable
```

Un caso puede tener varias activaciones. El estado del caso no debe volver a seguimiento mientras exista otra activación abierta.

## 4. Estados de ejecución

### Activación

- `activo`
- `en_seguimiento`
- `vencido`
- `cerrado`

### Paso

- `pendiente`
- `en_curso`
- `completado`
- `omitido`, siempre con motivo
- `bloqueado`

### Parte ejecutada

- `pendiente`
- `en_curso`
- `completado`
- `no_aplica`, con justificación cuando sea obligatoria

Al completar un paso, el servicio bloquea la fila de activación dentro de una transacción, valida sus partes obligatorias, cierra el paso actual, inicia el siguiente y recalcula el porcentaje. El último paso cierra la activación.

## 5. Constructor de protocolos

Cada protocolo tiene una definición general y una secuencia ordenada de etapas. Cada etapa puede relacionar partes reutilizables de una biblioteca institucional.

### Datos generales editables

- código institucional;
- nombre y descripción;
- revisión y etiqueta de versión;
- fuente, referencia y alcance reglamentario;
- población o nivel aplicable;
- criticidad y sensibilidad;
- fecha de vigencia;
- documentos, acciones y resguardos generales;
- estado: borrador, activo o inactivo.

### Datos editables por etapa

- código, orden, nombre, objetivo e instrucciones;
- tipo de etapa y responsable;
- plazo, unidad y punto de inicio;
- posibilidad y límite de prórroga;
- regla de finalización;
- acciones mínimas, documentos y medidas de resguardo;
- partes relacionadas, orden, obligatoriedad y condición de aplicación.

La edición obtiene primero el detalle completo del protocolo. No utiliza el objeto resumido del listado y no elimina/recrea ciegamente las etapas.

## 6. Biblioteca de partes relacionables

Las partes se administran independientemente y se pueden relacionar con más de un protocolo o etapa.

| Categoría | Uso principal |
| --- | --- |
| Acción | Actuación operativa o diligencia mínima |
| Entrevista | Entrevista individual, familiar o grupal |
| Comunicación o notificación | Avisos formales y constancia de recepción |
| Documento o evidencia | Actas, respaldos digitales, informes y firmas |
| Medida protectora | Resguardo inmediato o preventivo |
| Medida formativa | Diálogo, servicio o actividad formativa |
| Medida reparadora | Disculpa, restitución o plan de reparación |
| Sanción | Medida disciplinaria aplicable según nivel y gravedad |
| Derivación | Coordinación interna o con redes externas |
| Denuncia externa | Comunicación a autoridad competente |
| Apelación | Presentación, revisión y resolución del recurso |
| Seguimiento | Verificación posterior y prevención de reiteración |
| Cierre | Informe, resolución, comunicación y archivo |
| Regla especial | Condición normativa o excepción del protocolo |

Cada parte incluye título, descripción, instrucciones, responsable, población, referencia reglamentaria, plazo propio, exigencia de evidencia, estado, sensibilidad y configuración adicional. Una parte archivada deja de ofrecerse para nuevas definiciones, pero permanece en los expedientes históricos.

## 7. Plazos

El módulo distingue:

- `hours`: horas corridas, por ejemplo 24 o 48 horas;
- `calendar_days`: días calendario;
- `business_days`: días hábiles, excluyendo fines de semana;
- `school_days`: días escolares registrados en `school_days` para el año académico;
- `external`: plazo gobernado por una autoridad o condición externa.

El vencimiento calculado se persiste como parte de la instantánea. Los cambios posteriores en el calendario o en la definición no reescriben el historial. Si no existe calendario escolar suficiente, el sistema debe informar el uso de un cálculo alternativo y no presentar una fecha inventada como confirmada.

## 8. Matriz inicial RICE 2026

La carga inicial se organiza como definiciones editables y no como lógica rígida en código. Incluye, como mínimo:

1. vulneración de derechos y/o delitos contra estudiantes;
2. violencia intrafamiliar contra estudiantes;
3. abuso sexual infantil y hechos de connotación sexual;
4. gestión colaborativa de conflictos;
5. maltrato escolar entre estudiantes;
6. acoso escolar, bullying y cyberbullying;
7. maltrato de una persona adulta contra un estudiante;
8. maltrato de un estudiante contra una persona adulta;
9. maltrato entre apoderados y/o funcionarios;
10. situaciones relacionadas con drogas y alcohol;
11. prohibición y gestión de dispositivos móviles;
12. asistencia y rescate ante inasistencias;
13. maltrato entre niñas y niños del nivel parvulario (`RICE-P14`);
14. vulneración de derechos y/o delitos contra estudiantes de Educación Parvularia (`RICE-P15`);
15. maltrato infantil con connotación sexual en Educación Parvularia (`RICE-P16`);
16. maltrato de una persona adulta contra un estudiante de Educación Parvularia (`RICE-P17`);
17. maltrato entre funcionario y apoderado de Educación Parvularia (`RICE-P18`).

El documento fuente no contiene un protocolo numerado como 13. La carga conserva esa numeración —`RICE-P01` a `RICE-P12` y `RICE-P14` a `RICE-P18`— y registra la ausencia como advertencia de revisión, sin inventar ni renumerar contenido normativo.

Reglas transversales que deben quedar visibles y editables:

- recepción y registro cronológico;
- evaluación de riesgo y medidas protectoras inmediatas;
- comunicación a las partes y registro de entrevistas;
- investigación ordinaria o extraordinaria;
- recopilación de evidencia y elaboración de informe técnico;
- resolución fundada, notificación y apelación;
- intervención interna, familiar, externa o judicial;
- seguimiento y cierre.

Plazos destacados del documento fuente:

- activación y protección inmediata, con referencias de hasta 24 horas en los protocolos que lo exigen;
- formalización de investigación con referencias de hasta 48 horas;
- investigación de maltrato escolar: 10 días hábiles escolares, prorrogables por 5 con fundamento escrito;
- informe técnico: 3 días;
- resolución y notificación: 3 días;
- apelación general: 5 días hábiles;
- cancelación o expulsión: 15 días sin suspensión cautelar y 5 días con ella;
- procedimiento Aula Segura: 10 días hábiles para investigación y resolución;
- comunicaciones judiciales urgentes: referencias de 24 horas;
- materias asociadas a Ley Karin: plazos externos diferenciados.

El RICE contiene referencias cruzadas que deben ser validadas por la autoridad institucional antes de publicar la versión definitiva —por ejemplo, una mención a gestión colaborativa que apunta al Protocolo 9 aunque la tabla la identifica como Protocolo 4—. El sistema conserva esta advertencia y no corrige por inferencia una referencia normativa.

## 9. Expediente y PDFs

### PDF de definición

- portada, código, versión, vigencia y fuente;
- alcance y reglas generales;
- diagrama de etapas;
- tabla de responsables y plazos;
- partes agrupadas por categoría;
- referencias y advertencias normativas.

### PDF de activación

- folio del caso o denuncia;
- protocolo y revisión congelada;
- `Paso N de M`, avance y semáforo de plazo;
- etapas completadas, actual y pendientes;
- partes, acciones, medidas, evidencias y responsables;
- resolución, seguimiento y cierre.

Los documentos utilizan formato A4, cabecera institucional, secciones numeradas, marca condicional `CONFIDENCIAL`, fecha de emisión y pie `Página X de Y`. Los nombres de archivo se normalizan y los datos sensibles solo se incluyen cuando el endpoint autorizado los entrega.

## 10. Análisis e informes en una sola vista

`/convivencia` reúne el antiguo resumen institucional y los reportes por curso en una única experiencia. El mismo filtro de año, curso, semestre y rango de fechas alimenta indicadores, gráficos, alertas, comparación por curso, detalle documental y exportaciones. La ruta histórica `/convivencia/reportes` redirige a esta vista para no mantener dos lecturas divergentes.

### Indicadores

- casos activos, cerrados y críticos;
- protocolos activos;
- etapas vencidas y próximas a vencer;
- porcentaje de activaciones dentro de plazo;
- tiempo hasta la primera medida protectora;
- tiempo de cierre;
- medidas pendientes e incumplidas;
- casos sin responsable o sin próxima acción.

### Gráficos

- tendencia mensual;
- casos por clasificación, criticidad, nivel y curso;
- activaciones por protocolo;
- distribución por etapa actual;
- cumplimiento de plazos por protocolo;
- partes aplicadas por categoría;
- medidas protectoras, formativas, reparadoras y sanciones separadas;
- cuellos de botella por etapa.

### Informes y exportación

- tabla comparativa por curso con casos, denuncias, bitácora, derivaciones, entrevistas y medidas;
- tasas de cierre de casos y cumplimiento de medidas por curso;
- vista documental por tipo de registro, con aviso cuando la pantalla muestra solo una vista previa;
- Excel con resumen ejecutivo, estadísticas por curso, distribuciones y todos los registros autorizados;
- PDF apaisado con portada institucional, gráficos vectoriales, estadísticas por curso, control de alertas y anexos paginados;
- descarga paginada y acotada, sin cargar el universo de registros al abrir la pantalla.

Las estadísticas y exportaciones respetan la misma visibilidad de casos que los listados. El panel agregado no expone relatos, diagnósticos, identificadores ni notas internas y no presenta el volumen de actividad como una calificación del curso.

## 11. Permisos y privacidad

Se reutilizan los permisos del módulo para ver casos, gestionar protocolos, activar protocolos, ver contenido sensible y exportar reportes. Los controladores verifican además el acceso al caso o denuncia concreto; poseer un permiso funcional no habilita por sí solo a leer todos los expedientes.

Las operaciones de definición y ejecución registran autor y fecha. Las activaciones sensibles solo se listan cuando el usuario puede acceder a su caso o denuncia de origen.

## 12. Migración y despliegue seguro

- migraciones solamente aditivas y reversibles;
- sin `migrate:fresh`, `migrate:refresh`, `migrate:reset` ni `db:wipe`;
- sin carga, modificación ni eliminación automática de registros productivos;
- la matriz RICE se ofrece mediante seeder de desarrollo/prueba protegido contra producción;
- las activaciones creadas antes de esta ampliación se muestran primero en modo histórico de solo lectura y solo materializan una ruta granular mediante una acción explícita, autorizada y auditada;
- respaldo obligatorio antes de cualquier despliegue a producción;
- revisión previa de migraciones, seeders, permisos y asignación de roles;
- despliegue desde un artefacto limpio y verificación del `manifest.json` servido.

### Carga explícita de la matriz RICE 2026 en desarrollo

La carga normativa se realiza únicamente con `ConvivenciaRice2026Seeder`. Este seeder no está registrado en una carga automática, no crea datos demostrativos y no llama a `ConvivenciaSeeder`. Solo admite `APP_ENV=local` —sobre la base exacta `gestion_adm`— o `APP_ENV=testing` dentro de pruebas automatizadas; cualquier otro entorno falla antes de escribir.

```bash
php artisan db:seed --class=Database\\Seeders\\ConvivenciaRice2026Seeder --no-interaction
```

La operación es transaccional, actualiza por códigos naturales sin eliminar casos, activaciones, adjuntos ni definiciones ajenas, conserva los identificadores de pasos y vínculos, y rechaza conflictos archivados o registros inesperados en vez de borrarlos. El resultado esperado y verificado es de 17 protocolos, 28 partes reutilizables, 101 pasos y 209 vínculos (207 asociados a pasos y 2 globales), sin duplicados. Una segunda ejecución debe informar cero cambios y conservar IDs, revisiones, fechas y auditoría.

Este comando no está autorizado para producción. Un eventual proceso productivo requerirá una decisión separada, respaldo previo y revisión normativa de los protocolos marcados con `review_required`.

## 13. Plan anual de Gestión de la Convivencia Escolar

La pestaña **Plan de gestión** funciona como un espacio anual único, siguiendo el patrón operativo del plan de Orientación: una definición por año, acciones planificadas separadas de su ejecución, calendario integrado, evidencias privadas e historial documental.

El Plan 2026 incorpora de forma estructurada el documento institucional recibido: protocolo de bienvenida, diez acciones de marzo a diciembre, cuatro indicadores de evaluación y la cláusula de vinculación con el RICE. Esta última se conserva íntegra con una alerta de revisión; el sistema no la aplica automáticamente como sanción ni modifica el RICE.

Funciones principales:

- objetivos generales y específicos editables;
- componentes editables del protocolo institucional de acogida;
- indicadores con meta, unidad, frecuencia y fuente de verificación;
- un plan por año calendario, enlazado opcionalmente con el plan anterior y con versiones internas restaurables;
- creación del año siguiente desde una definición vigente, sin duplicar actividades ejecutadas ni evidencias;
- acciones con mes o rango exacto multianual, público, responsable, recursos, indicador, medios de verificación y ponderación dentro del plan;
- actividades tipificadas desde catálogo —charla, intervención, taller, reunión, capacitación, jornada, campaña, mediación, acompañamiento, seguimiento, encuesta, evaluación, difusión u otra—;
- actividades ejecutables con fecha/hora, lugar, asistentes, resultados, porcentaje de ejecución y aporte porcentual al avance individual;
- progresión en dos niveles: las actividades construyen el avance de cada acción y la ponderación de las acciones construye el avance total del plan;
- calendario mensual, semanal y de agenda;
- evidencias y documentos fuente almacenados fuera del disco público;
- versiones inmutables de la definición, restauradas siempre como una versión nueva;
- bloqueo de cambios obsoletos por revisión optimista;
- informe PDF institucional con definición, ejecución, evidencias y versiones.

La precarga local es explícita, valida la huella SHA-256 del archivo Word y se detiene ante cualquier plan 2026 distinto:

```bash
php artisan convivencia:preload-plan-2026 --source="/ruta/al/PLAN DE GESTIÓN DE LA CONVIVENCIA ESCOLAR 2026 opción.docx"
```

Solo está permitida en `local` —base `gestion_adm`— y `testing`. No está registrada en seeders automáticos, no elimina planes ni acciones y una segunda ejecución es idempotente.

Los tipos de actividad y la ponderación inicial de las diez acciones documentales se pueden incorporar en desarrollo con el seeder protegido e idempotente:

```bash
php artisan db:seed --class=Database\\Seeders\\ConvivenciaPlanProgressionSeeder --no-interaction
```

El seeder no elimina ni sobrescribe catálogos existentes. Solo pondera el plan 2026 cuando conserva exactamente las diez acciones precargadas, todas siguen sin ponderación y la huella del documento fuente coincide; el cambio queda registrado como una nueva versión.

## 14. Sociogramas y análisis relacional

La pestaña **Sociogramas** permite registrar preguntas sociométricas positivas, neutras o de situaciones que requieren revisión. Cada pregunta define su máximo de elecciones y las nominaciones se capturan con selectores de estudiantes limitados al curso y año académico escogidos.

El detalle integra tres lecturas complementarias:

- red dirigida del curso, con flechas para cada nominación y realce de vínculos positivos recíprocos;
- indicadores ApexCharts de participación, balance de vínculos, reciprocidad, densidad y cobertura positiva;
- matriz de calor origen-destino para reconocer agrupaciones y distribución de elecciones.

La ficha individual muestra recepciones positivas, elecciones que requieren revisión, vínculos recíprocos y participación. Los resultados son descriptivos: no generan diagnósticos ni etiquetas disciplinarias automáticas y deben complementarse con observación, entrevistas y antecedentes del curso.

La lista usa un payload reducido. La nómina y el análisis completo se cargan únicamente al abrir el detalle, y el PDF confidencial reproduce la red, indicadores, tabla individual, preguntas e interpretación profesional.

Para revisar el resultado con datos ficticios en la base local `gestion_adm` existe un seeder protegido, aditivo e idempotente:

```bash
php artisan db:seed --class=Database\\Seeders\\ConvivenciaSociogramDemoSeeder --no-interaction
```

El seeder solo se ejecuta en `local` o `testing`, no elimina ni modifica sociogramas existentes y nunca se incorpora a la cadena automática de producción.

## 15. Criterios de aceptación

1. Crear, editar, consultar y archivar protocolos y partes.
2. Relacionar una parte con una o más etapas y protocolos.
3. Activar un protocolo y obtener una instantánea completa.
4. Mostrar correctamente `Paso N de M`, porcentaje, plazo, siguiente paso y requisitos.
5. Impedir avanzar con requisitos obligatorios pendientes.
6. Completar el último paso y cerrar la activación sin cerrar otras activaciones del caso.
7. Conservar sin cambios las activaciones antiguas después de editar la definición.
8. Aplicar privacidad y permisos en listados, detalle, estadísticas y exportación.
9. Generar PDFs legibles, paginados y coherentes con la pantalla.
10. Superar pruebas backend, frontend, build de producción, navegación autenticada, consola y revisión responsive.
11. Gestionar un único Plan de Convivencia por año con acciones, actividades, calendario, evidencias y versiones restaurables.
12. Mantener el documento 2026 descargable en almacenamiento privado y su contenido operativo editable.
13. Permitir acciones con vigencia de más de un año y mostrarlas como multianuales en matriz, calendario y PDF.
14. Exigir un tipo configurable por actividad y conservar su etiqueta histórica.
15. Mostrar el avance individual de cada acción, la asignación de actividades y el avance total ponderado del plan.
16. Validar que las nominaciones correspondan al curso, coincidan con el tipo de pregunta, respeten el máximo de elecciones y no permitan autoelecciones ni duplicados.
17. Visualizar red, indicadores y matriz sociométrica, y exportar el mismo análisis en un PDF confidencial.
