# Generador de clases

Módulo privado de Gestión pedagógica para crear presentaciones de clase editables a partir de la cadena curricular institucional. No ofrece un prompt libre: establecimiento, año, curso, asignatura, unidad, objetivos, decisiones pedagógicas y formato se eligen mediante un asistente guiado.

## Alcance implementado

- Ruta Vue: `/gestion-pedagogica/generador-clases`.
- API privada: `/api/gestion-pedagogica`.
- Reutiliza `lcd_schools`, `academic_years`, `course_sections`, `schedule_subjects`, `lcd_curriculum_programs`, `lcd_curriculum_units` y `lcd_learning_objectives`.
- Valida en servidor toda la cadena curso → asignatura → programa publicado → unidad → objetivos.
- Genera mediante OpenAI Responses API el contenido pedagógico y un `teacher_guide` estructurado; la clave nunca llega al navegador.
- Usa Structured Outputs con JSON Schema estricto y valida nuevamente diapositivas, estilo, tiempos, objetivos y guion docente antes de crear artefactos.
- Construye siempre una guía docente A4 separada, con propósito, preparación, cronograma, discurso y acciones por diapositiva, preguntas, dificultades previsibles, evaluación, inclusión y fuentes.
- Conecta cada docente con Canva mediante OAuth 2.0 Authorization Code + PKCE. Los access/refresh tokens rotativos se cifran en la base de datos y nunca llegan a Vue.
- Consulta Brand Templates reales, valida su dataset Autofill y sólo permite generar cuando la plantilla contiene el contrato completo para la cantidad de diapositivas elegida.
- Canva crea el diseño editable mediante un job Autofill asíncrono. El sistema conserva además PPTX/PDF técnicos de respaldo; estos archivos locales no se presentan como exportaciones del diseño Canva.
- Procesa en la cola `class-presentations`, mantiene progreso, fallos, reintentos, auditoría, historial y versiones inmutables.
- Almacena PPTX, PDF, JSON y previsualizaciones en disco privado. Cada descarga vuelve a pasar por policy.
- Los materiales de referencia admitidos son PDF, DOCX, PPTX, TXT y Markdown; se limitan por cantidad, tamaño y texto extraído, y se tratan como datos no confiables frente a prompt injection.
- No envía al modelo nombres, RUT, diagnósticos, calificaciones ni datos personales de estudiantes.

## Permisos

| Permiso | Alcance |
| --- | --- |
| `class-presentations.view` | Entrar al módulo y consultar el historial propio. |
| `class-presentations.view-all` | Consultar presentaciones autorizadas del establecimiento. |
| `class-presentations.create` | Solicitar una nueva generación. |
| `class-presentations.download` | Descargar artefactos autorizados. |
| `class-presentations.regenerate` | Crear una nueva versión o reintentar una fallida. |
| `class-presentations.archive` | Archivar sin eliminar archivos ni versiones. |

La migración RBAC asigna permisos completos a superadministración, administración, dirección, coordinación académica y UTP. El rol docente recibe vista propia, creación, descarga y regeneración, pero no vista global ni archivado.

## Variables de entorno

```dotenv
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_PRESENTATION_MODEL=gpt-5.6
OPENAI_PRESENTATION_TIMEOUT=300
OPENAI_PRESENTATION_MAX_OUTPUT_TOKENS=30000
OPENAI_PRESENTATION_REASONING_EFFORT=medium

CLASS_PRESENTATIONS_DISK=local
CLASS_PRESENTATIONS_ROOT=private/class-presentations
CLASS_PRESENTATIONS_QUEUE=class-presentations
CLASS_PRESENTATIONS_DAILY_USER_QUOTA=20
CLASS_PRESENTATIONS_MAX_REFERENCE_FILE_KB=15360
CLASS_PRESENTATIONS_MAX_REFERENCE_FILES=5
CLASS_PRESENTATIONS_MAX_EXTRACTED_CHARACTERS_PER_FILE=30000
CLASS_PRESENTATIONS_MAX_EXTRACTED_CHARACTERS_TOTAL=70000
CLASS_PRESENTATIONS_NODE_BINARY=node
CLASS_PRESENTATIONS_LIBREOFFICE_BINARY=soffice
CLASS_PRESENTATIONS_PDFTOPPM_BINARY=pdftoppm
CLASS_PRESENTATIONS_PROCESS_TIMEOUT=180
CLASS_PRESENTATIONS_EXTERNAL_PROCESS_TIMEOUT=180
CLASS_PRESENTATIONS_TEACHER_GUIDE_PROCESS_TIMEOUT=180

CANVA_ENABLED=true
CANVA_CLIENT_ID=
CANVA_CLIENT_SECRET=
CANVA_REDIRECT_URI=https://dominio.example/api/integraciones/canva/callback
CANVA_SCOPES="design:content:write design:meta:read brandtemplate:meta:read brandtemplate:content:read profile:read"
CANVA_CONNECT_TIMEOUT=8
CANVA_TIMEOUT=30
CANVA_OAUTH_STATE_TTL_MINUTES=10
CANVA_TOKEN_REFRESH_LEEWAY_SECONDS=300
CANVA_TEMPLATE_CACHE_SECONDS=300
CANVA_AUTOFILL_POLL_SECONDS=5
CANVA_AUTOFILL_DEADLINE_MINUTES=15
```

`OPENAI_API_KEY` es obligatoria para generar contenido. `CANVA_CLIENT_SECRET` debe existir sólo en `.env` o en el gestor de secretos del servidor; nunca debe copiarse al frontend, documentación, logs o repositorio. Si un secreto aparece en una captura o mensaje, debe revocarse y regenerarse antes de configurar el sistema.

La URI declarada en Canva debe coincidir exactamente con `CANVA_REDIRECT_URI` y usar HTTPS en producción. Cada usuario autoriza su propia cuenta; el Client ID y el Client Secret de la integración no reemplazan este OAuth.

Autofill requiere la capability `autofill` (normalmente Canva Enterprise; las cuentas pagadas pueden disponer de una prueba limitada durante desarrollo) y `brand_template`. La plantilla debe publicar estos campos de texto mediante Canva Data Autofill:

- Globales: `TITLE`, `SUBTITLE`, `COURSE`, `SUBJECT`, `UNIT`, `OBJECTIVES`.
- Por cada página `NN`: `SNN_TITLE`, `SNN_TEXT`, `SNN_BULLETS`; por ejemplo `S01_TITLE`, `S01_TEXT`, `S01_BULLETS`.

El backend vuelve a consultar y validar el dataset al confirmar la solicitud y antes del envío remoto. Canva omite silenciosamente nombres inexistentes, por eso una plantilla incompleta se bloquea antes de gastar una generación de contenido.

LibreOffice es obligatorio cuando `generate_pdf` está activado. Poppler (`pdftoppm`) es recomendable para producir las previsualizaciones reales; sin él, el sistema conserva una vista SVG simplificada sólo cuando el PDF no es obligatorio.

## Dependencias y proceso de cola

```bash
npm ci
composer install --no-dev --optimize-autoloader
composer run worker:class-presentations
```

El servidor debe disponer de Node.js, LibreOffice (`soffice`) y Poppler (`pdftoppm`). El worker productivo debe ser persistente (Supervisor, systemd o equivalente), reiniciarse después del release y monitorearse junto a `jobs` y `failed_jobs`.

Comando equivalente del worker:

```bash
php artisan queue:work database --queue=class-presentations --tries=3 --timeout=900 --sleep=2
```

## Migraciones y despliegue seguro

Las migraciones son aditivas, idempotentes y forward-only. El registro RBAC usa inserciones tolerantes a duplicados: no actualiza permisos, módulos ni grupos preexistentes.

- `2026_08_29_010000_create_class_presentations.php`
- `2026_08_29_011000_register_class_presentation_generator.php`
- `2026_08_30_230000_add_canva_integration_to_class_presentations.php`

Antes de cualquier despliegue a producción se debe crear y verificar un respaldo de base de datos. No ejecutar `migrate:fresh`, `migrate:refresh`, `migrate:reset` ni `db:wipe`. Revisar primero el SQL y luego aplicar únicamente estas migraciones:

```bash
php artisan migrate --pretend --path=database/migrations/2026_08_29_010000_create_class_presentations.php
php artisan migrate --pretend --path=database/migrations/2026_08_29_011000_register_class_presentation_generator.php
php artisan migrate --pretend --path=database/migrations/2026_08_30_230000_add_canva_integration_to_class_presentations.php

php artisan migrate --force --path=database/migrations/2026_08_29_010000_create_class_presentations.php
php artisan migrate --force --path=database/migrations/2026_08_29_011000_register_class_presentation_generator.php
php artisan migrate --force --path=database/migrations/2026_08_30_230000_add_canva_integration_to_class_presentations.php
```

Después: limpiar caché de configuración, compilar con `npm run prod`, reiniciar el worker, confirmar que la cola consume `class-presentations`, revisar `failed_jobs` y validar una generación autenticada completa.

## Verificación enfocada

```bash
php artisan test tests/Feature/PedagogicalManagement/ClassPresentationGeneratorTest.php
php artisan test tests/Feature/PedagogicalManagement/ClassPresentationCanvaIntegrationTest.php
npm run test:unit -- tests/frontend/class-presentations.test.js tests/frontend/class-presentations-canva-components.test.js tests/frontend/class-presentations-api.test.js
npm run prod
```

La presentación de aceptación usa Orientación, 7° Básico B, `OR07 OA 06`, 45 minutos, 12 diapositivas, actividad grupal, ticket de salida, notas del presentador, PPTX y PDF. El JSON reproducible está en `tests/Fixtures/ClassPresentations/orientation-7b-conflict-resolution.json`.

## Operación y errores

- `OPENAI_NOT_CONFIGURED`: falta clave o modelo.
- `OPENAI_TIMEOUT` / `OPENAI_CONNECTION_FAILED`: conectividad o tiempo de espera.
- `OPENAI_RATE_LIMITED`: límite temporal; el job reintenta con backoff.
- `OPENAI_REFUSAL`, `OPENAI_RESPONSE_INCOMPLETE`, `OPENAI_OUTPUT_INVALID`: la respuesta no es utilizable.
- `DECK_*`: el contenido no cumple cantidad, tiempos, objetivos o límites editoriales.
- `PPTX_*`, `PDF_*`, `PREVIEW_*`: falló la construcción o el control de calidad del artefacto.
- `TEACHER_GUIDE_*`: el guion no cubre exactamente diapositivas, objetivos o duración, o falló su PDF A4.
- `CANVA_CONNECTION_REQUIRED` / `CANVA_REAUTHORIZATION_REQUIRED`: falta OAuth o debe renovarse.
- `CANVA_AUTOFILL_CAPABILITY_REQUIRED`: la cuenta no posee el plan/capability necesario.
- `CANVA_TEMPLATE_CONTRACT_INVALID`: faltan campos Autofill o tienen un tipo distinto de texto.
- `CANVA_SUBMISSION_UNCONFIRMED`: una interrupción impide saber si Canva alcanzó a crear el diseño; se bloquea el reenvío automático para evitar duplicados.
- `CANVA_REMOTE_JOB_*`: Canva recibió el trabajo, pero no pudo crear el diseño.

Los mensajes técnicos sensibles no se exponen en producción. El historial muestra un mensaje accionable, conserva la versión fallida y permite reintentar cuando corresponde.
