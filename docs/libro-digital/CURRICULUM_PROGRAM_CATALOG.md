# Catálogo de programas curriculares ministeriales

## Alcance

Este módulo incorpora libros y documentos curriculares PDF como fuentes trazables del Libro Digital. No sustituye el catálogo maestro de objetivos ni publica información por inferencia automática. Su flujo separa archivo, extracción, conciliación, revisión, validación y publicación.

La fixture de referencia es el **Programa de Estudio de Ciencias Naturales, Primer Año Básico**, Decreto N.º 2960/2012, segunda edición 2018. La validación automatizada confirma 184 páginas físicas, 38 semanas, 114 horas pedagógicas, cuatro unidades (30/30/30/24 horas), doce OA, cuatro OAH y tres ejes. La prueba conserva tanto la página física como la numeración impresa.

## Flujo de gobierno

1. `POST /api/libro-digital/v1/curriculum/program-catalog/imports` recibe uno o varios PDF. Valida firma `%PDF-`, MIME real, tamaño y SHA-256 antes de cifrar el archivo en storage privado.
2. Cada archivo genera un expediente independiente y un job en la cola `curriculum-imports`. Un fallo no cancela el resto del lote.
3. El extractor conserva texto bruto, normalizado, layout, método, confianza, alertas, número físico e impreso por página. Las páginas sin capa suficiente solicitan OCR; si el runtime no está habilitado, quedan advertidas y nunca se inventa texto.
4. El clasificador determina tipo documental, asignatura, nivel, decreto, edición y año. Los parsers especializados cubren programa de estudio, bases curriculares, plan de estudio y priorización; el parser genérico conserva secciones y advertencias.
5. Los hallazgos se guardan en staging como candidatos. Los OA/OAH se concilian por asignatura, grado, tipo y código con `lcd_learning_objectives`.
6. La revisión humana acepta, omite o corrige candidatos y deja actor/fecha/notas. Los conflictos críticos bloquean validación y publicación.
7. La publicación reutiliza OA/OAH existentes, crea el programa, versión, unidades, ejes, habilidades, actitudes, palabras clave y relaciones con la evidencia de página. Los reintentos son idempotentes.

### Creación manual rápida

La pestaña **Crear manualmente** evita esperar la extracción del documento. El usuario define asignatura, nivel, versión, ejes y unidades; selecciona únicamente OA/OAH activos del catálogo maestro y distribuye los OA entre las unidades. También puede registrar conocimientos, habilidades, actitudes y palabras clave como listas editables.

`POST /api/libro-digital/v1/curriculum/program-catalog/programs/manual` guarda un borrador o publica inmediatamente. Un borrador puede omitirse del PDF; la publicación exige un PDF válido, lo cifra en storage privado, conserva su SHA-256 y registra que la extracción automática fue omitida. Si el mismo archivo tenía una importación lenta o pendiente, el expediente se completa de forma trazable y queda vinculado al programa manual en vez de duplicarse.

La operación exige `libro_digital.curriculum_programs.import`; publicar además exige `libro_digital.curriculum_programs.publish`. La API rechaza OA de otra asignatura o nivel, unidades sin OA y una identidad asignatura–nivel–versión duplicada.

Estados principales: `uploaded → validating → extracting_text → running_ocr → detecting_structure → extracting_entities → normalizing → reconciling → pending_review → validated → published|published_with_warnings`. `failed` permite reproceso; `archived` conserva el expediente.

## Catálogo, búsqueda e integración docente

- La matriz asignatura × nivel se genera desde `education_levels` y asignaturas activas; no codifica una lista fija de cursos.
- El detalle presenta semanas, horas, ejes, OA/OAH, unidades, propósitos, habilidades, actitudes, conocimientos, palabras clave, gráficos y fuente.
- La búsqueda transversal consulta OA, unidades, elementos, ejes, habilidades, actitudes y palabras clave con programa, asignatura, grado y página de origen.
- Leccionario y evaluaciones pueden guardar `curriculum_program_id`, `curriculum_unit_id` y, en sesiones, `curriculum_axis_id`. El servidor comprueba que correspondan al nivel y asignatura del libro y que los OA pertenezcan a la unidad.
- El PDF institucional se solicita como job privado. Su snapshot hashado incluye resumen, dashboard, mapa anual, ejes, OA, unidades y fuentes.

## Permisos

| Permiso | Uso |
|---|---|
| `libro_digital.curriculum_programs.view` | Matriz, catálogo y búsqueda. |
| `libro_digital.curriculum_programs.documents.view` | PDF y texto por página. |
| `libro_digital.curriculum_programs.import` | Un PDF por solicitud. |
| `libro_digital.curriculum_programs.import_batch` | Más de un PDF, máximo 30. |
| `libro_digital.curriculum_programs.review` | Revisión y validación. |
| `libro_digital.curriculum_programs.resolve_conflicts` | Resolución documentada. |
| `libro_digital.curriculum_programs.publish` | Publicación y lotes validados. |
| `libro_digital.curriculum_programs.reprocess` | Reintento de expedientes no publicados. |
| `libro_digital.curriculum_programs.archive` | Archivo seguro; no permite retirar programas usados. |
| `libro_digital.curriculum_programs.export_pdf` | Exportación curricular privada. |

## Configuración y operación

```dotenv
LCD_CURRICULUM_PDF_MAX_KB=40960
LCD_CURRICULUM_OCR_MIN_TEXT_CHARS=40
LCD_CURRICULUM_OCR_DRIVER=disabled
LCD_CURRICULUM_IMPORT_QUEUE=curriculum-imports
LCD_CURRICULUM_IMPORT_MEMORY_LIMIT=512M
```

Requisitos base: PHP con `fileinfo`/`mbstring`, `smalot/pdfparser` y un worker Laravel dedicado que atienda `curriculum-imports`. El job valida y aplica únicamente en ese worker un límite entre 256 y 2048 MB; 512 MB fue verificado con el fixture ministerial de 184 páginas. OCR es opcional y debe registrarse como driver explícito; mientras esté deshabilitado, las páginas insuficientes quedan en revisión. Los extractos secundarios marcados `review` se omiten al validar si un revisor no los aceptó explícitamente. Nunca se deben ejecutar comandos del sistema construidos desde nombres de archivo o metadatos del PDF.

El límite de Laravel no aumenta por sí solo la capacidad del servidor web. Para el valor predeterminado de 40 MB por PDF, el runtime web/FPM debe usar `upload_max_filesize >= 40M`, `post_max_size >= 128M`, `max_file_uploads >= 30` y el proxy debe aceptar un cuerpo de al menos 128 MB. Después de cambiar esos valores se deben reiniciar PHP-FPM, Apache o el proceso local. En desarrollo, iniciar el servidor con `composer serve:curriculum`; este comando ejecuta directamente el servidor HTTP de PHP con esos límites, evitando que `artisan serve` los pierda al crear su proceso hijo y sin modificar la configuración global.

## Despliegue seguro

La migración `2026_08_23_010000_create_lcd_curriculum_program_catalog_tables.php` es aditiva y forward-only: crea tablas, índices y claves foráneas, y agrega referencias curriculares nulas a sesiones/evaluaciones. `down()` no elimina tablas ni columnas. En producción:

1. crear y verificar respaldo de base de datos y storage privado;
2. revisar `php artisan migrate --pretend` en el release exacto;
3. ejecutar migración y seeder de permisos sin `migrate:fresh`, `refresh`, `reset`, `db:wipe` ni seeders destructivos;
4. iniciar worker de la cola y comprobar permisos del storage;
5. hacer smoke test con un PDF controlado, validar que quede `pending_review` y no publicar hasta UAT;
6. verificar descarga autenticada, auditoría, reintento idempotente y bloqueo de conflictos críticos.

La activación de asignaturas/libros oficiales sigue siendo una decisión separada del catálogo documental. Usar `lcd:curriculum:sync-official-books` primero en dry-run; en producción el modo `--execute` exige aprobación y referencia de respaldo.
