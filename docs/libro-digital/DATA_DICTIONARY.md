# Diccionario de datos

## Alcance y estado

Las once migraciones `2026_08_13_000001` a `000011` definen **69 tablas** con prefijo `lcd_`. El conteo fue contrastado contra `Schema::create('lcd_*')`: `000009`/`000010` amplían tablas existentes y `000011` crea las dos tablas de trazabilidad por documento/objetivo. Son aditivas: usan guardas de esquema, claves foráneas `RESTRICT`/`SET NULL` y `down()` sin operaciones destructivas.

La ampliación `2026_08_22_150000_create_lcd_subject_catalog_management_tables` agrega **2 tablas** de administración de asignaturas, para un total de 71 tablas `lcd_*`. Es forward-only, no inserta aliases ni modifica registros de `schedule_subjects`.

Al **2026-08-14**, `000001`–`000011` están `Ran` en la base local verificada, incluidas track, identidad scoped y las dos tablas fuente–objetivo. Las FK curriculares instaladas usan `RESTRICT` o `SET NULL`, sin `CASCADE`. Este corte local no sustituye migración/restore sobre staging, copia con datos ni motor productivo.

Este documento describe el esquema implementado. No todas las tablas tienen todavía un caso de uso, modelo o endpoint; una tabla vacía no demuestra cumplimiento operativo.

## Convenciones transversales

| Convención | Regla |
|---|---|
| ID interno | `id` entero para relaciones internas. |
| ID público | `public_id` ULID único cuando el recurso puede exponerse. No exponer IDs secuenciales en enlaces públicos. |
| Tenencia | `school_id` obligatorio en entidades institucionales. Consultas sin scope de escuela están prohibidas salvo consolidación expresamente autorizada. |
| Año | `academic_year_id` reutiliza `academic_years`; se conservan `year_snapshot` y `rbd_snapshot` cuando son relevantes. |
| Tiempo | Timestamps en UTC; visualización en la zona de `lcd_schools.timezone`, por defecto `America/Santiago`. Integraciones usan RFC 3339 con offset. |
| Vigencia | Pares `effective_from/effective_to` o `valid_from/valid_to`; `null` final significa vigencia abierta, no dato desconocido. |
| Revisión | `revision` conserva número funcional; `lock_version` controla concurrencia optimista. No son intercambiables. |
| Snapshot | Copia inmutable del contexto de una operación histórica. El origen ERP puede cambiar sin alterar la copia. |
| Hash | SHA-256 hexadecimal de 64 caracteres. Un hash prueba integridad comparativa, no identidad ni certificación. |
| Datos cifrados | El sufijo `*_encrypted` exige cifrado de aplicación/columna. El nombre por sí solo no cifra; debe verificarse el cast/servicio antes de producción. |
| JSON | Solo estructura flexible versionada; no usar para ocultar relaciones que requieran integridad/índices. |
| Borrado | No hay cascade delete. `deleted_at` en adjuntos representa disposición controlada; no autoriza hard-delete automático. |

## Dependencias reutilizadas del ERP

El LCD enlaza, sin duplicarlas, las entidades existentes `users`, `staff`, `student_profiles`, `student_enrollments`, `academic_years`, `course_sections`, `schedule_subjects`, `schedule_events`, `school_day_blocks`, `school_days`, `convivencia_cases`, `convivencia_daily_logs`, `apoyo_atenciones`, `apoyo_planes`, `attendance_interventions` y `porter_student_withdrawals`.

Una eliminación o cambio en el maestro no debe borrar historia LCD. Por eso se combinan FK `RESTRICT` o `SET NULL` con snapshots de nombre, curso, asignatura, docente e identificador.

## 1. Tenencia, normativa y configuración

| Tabla | Propósito | Campos/controles principales | Clasificación |
|---|---|---|---|
| `lcd_schools` | Contexto institucional del LCD. | `public_id`, RBD único, nombre, dependencia, región/comuna, dirección, zona, modalidades, activo. | Interno; dirección y contexto institucional. |
| `lcd_regulatory_profiles` | Versión aplicable de reglas por fecha/nivel/modalidad. | Código+versión únicos, autoridad, resolución, vigencia, `retention_years`, `rules_snapshot`, `source_hash`, activo. | Cumplimiento. No activar sin fuentes verificadas. |
| `lcd_regulatory_rules` | Reglas declarativas vinculadas a un perfil. | Código único por perfil, categoría, definición JSON, vigencia y estado. | Cumplimiento; cambios requieren nueva versión. |
| `lcd_school_academic_years` | Une escuela, año y perfil aplicable. | RBD/año/zona en snapshot, fechas de apertura/cierre, activo; único escuela+año. | Interno. |
| `lcd_school_users` | Scope funcional de usuarios por establecimiento. | `role_snapshot`, `permission_scope`, vigencia, activo, asignador. | Confidencial de autorización. No reemplaza RBAC global. |
| `lcd_feature_flags` | Activa capacidades globales o escolares. | `scope_key`+`code` únicos, `enabled=false`, configuración, actor. | Configuración crítica y auditable. |
| `lcd_settings` | Configuración por scope/escuela/año. | `key`, valor JSON, indicador `is_encrypted`, actor; único scope+key. | Según contenido; secretos no deben residir aquí sin cifrado/secret manager. |
| `lcd_normative_sources` | Registro de procedencia normativa. | Título, autoridad/número, publicación, URL, consulta, SHA-256, ruta privada, estado, metadata. | Cumplimiento. Hash solo de bytes reales. |
| `lcd_reference_catalogs` | Catálogo oficial versionado. | Fuente, código, nombre, versión, activo; código+versión únicos. | Referencia oficial. |
| `lcd_reference_values` | Valores de un catálogo. | Código único por catálogo, etiqueta, metadata, vigencia, activo. | Referencia oficial; no hardcodear códigos fuera del catálogo. |

## 2. Libros, nómina y currículo

| Tabla | Propósito | Campos/controles principales | Clasificación |
|---|---|---|---|
| `lcd_books` | Libro anual de curso/contexto. | Escuela/año/perfil, curso, RBD/año/nivel/grado/curso/modalidad/jornada en snapshot, estado, fuente, transferencia previa, apertura/cierre, retención, revisión, lock. Único escuela+año+código. | Oficial restringido. |
| `lcd_book_periods` | Periodos del libro. | Código único por libro, tipo, rango, estado, revisión/cierre. | Oficial. |
| `lcd_teaching_groups` | Grupo curso-asignatura u otro agrupamiento pedagógico. | Escuela/año/libro, curso, asignatura, vigencia, fuente de nómina, status y snapshots. | Oficial. |
| `lcd_teacher_assignments` | Asignación vigente de docente/profesional. | Grupo, staff, user, asignatura, rol, nombre snapshot, vigencia, principal/activo. | Confidencial de autorización. |
| `lcd_enrollment_links` | Relación histórica entre matrícula ERP y grupo. | Estudiante/matrícula/curso, número de lista, vigencia, status y snapshots protegidos. Único grupo+matrícula. | Personal; identificador cifrado. |
| `lcd_roster_snapshots` | Nómina sellada aplicable en fecha/revisión. | Grupo, fecha, motivo, `sealed`, cantidad, `snapshot_hash`; único grupo+fecha+revisión. | Oficial inmutable. |
| `lcd_roster_snapshot_items` | Estudiante aplicable dentro del snapshot. | Enlace/matrícula/estudiante, lista, vigencia, aplicabilidad, snapshots, `record_hash`; único snapshot+estudiante. | Personal restringido; identificador cifrado. |
| `lcd_curriculum_catalogs` | Corpus curricular versionado y portable. | Fuente normativa de transporte, código, versión, autoridad, URL, hash canónico de catálogo, vigencia, activo. | Cumplimiento/currículo. El hash excluye tenant/año/vínculos/paths y no sustituye los hashes por documento. Actualmente no hay corpus oficial real importado. |
| `lcd_learning_objectives` | OA/OAT/objetivo de un catálogo. | Catálogo, asignatura, nivel/grado/eje/unidad, tipo, código, descripción, indicadores, activo; `000008` agrega `source_page`/`source_row_hash`, `000009` track y `000010` `objective_key` scoped. | Curricular. No sembrar sin fuente oficial; `000010` reemplaza unicidad catálogo+código por catálogo+`objective_key` y está pendiente localmente. |
| `lcd_subject_curriculum_links` | Asocia asignatura institucional con catálogo oficial. | Escuela/año/asignatura/catálogo, nivel/grado, `scope_key`, track, vigencia, activo; `000008` incluye alcance y `000009` track. | Curricular. El validador genera `LEVEL:GRADE:TRACK|ALL`; `000009` está pendiente localmente. |
| `lcd_curriculum_import_batches` | Expediente técnico inmutable de un archivo curricular candidato. | Escuela/año, idempotencia y lock, catálogo/versión/formato, storage privado, hash XLSX/declarado, manifiesto/hash con claves de evidencia, payload validado cifrado, errores/conteos y actores/fechas. | Curricular restringido. `declared_source_hash` del catálogo no sustituye hashes por documento. |
| `lcd_curriculum_import_evidences` | Evidencia asociada a un lote. | Lote/catálogo, `source_key` en metadata, tipo/estado, URL o archivo privado cifrado, MIME/tamaño, SHA-256, manifiesto, confidencialidad, captura y verificación. | Cumplimiento restringido; FK `RESTRICT`. Un adjunto solo es habilitante si su hash real coincide con la declaración. |
| `lcd_curriculum_catalog_activations` | Decisión separada que habilita una versión para escuela/año. | Catálogo/lote, versión de activación, idempotencia, estado, vigencia, snapshot de alcance, manifiesto/`decision_hash`, solicitante/aprobador/activador y supersesión/revocación. | Gobierno curricular. Declarado en `000008`; no existe activación válida observada. |
| `lcd_curriculum_sources` | Documento oficial verificado que participa en un catálogo consolidado. | Catálogo/fuente normativa, `source_key` única por catálogo, `source_scope`, acto/URL, hash declarado y verificado, vigencia, track/asignatura/tipo opcionales, estado/metadata. | Cumplimiento curricular. Declarado en `000011`, pendiente localmente; al activar, cada fila corresponde a bytes archivados y verificados, no a un scope agregado. |

### Programas curriculares y documentos PDF

| Grupo de tablas | Propósito y controles | Clasificación |
|---|---|---|
| `lcd_curriculum_versions`, `lcd_curriculum_programs` | Versión normativa y programa por asignatura/nivel; identidad hashada, revisión, publicación y vigencia. | Curricular oficial. |
| `lcd_curriculum_documents`, `lcd_curriculum_document_pages`, `lcd_curriculum_document_sections`, `lcd_curriculum_document_program` | PDF cifrado, SHA-256, extracción/paginación física e impresa, secciones y relación de fuente. | Curricular restringido hasta publicación; descarga autenticada. |
| `lcd_curriculum_axes`, `lcd_curriculum_units`, `lcd_curriculum_skills`, `lcd_curriculum_skill_formulations`, `lcd_curriculum_attitudes`, `lcd_curriculum_keywords`, `lcd_curriculum_elements` | Estructura normalizada y reutilizable con identidad, orden y metadatos curriculares. | Curricular. |
| `lcd_curriculum_program_axes`, `lcd_curriculum_program_objectives`, `lcd_curriculum_unit_objectives`, `lcd_curriculum_axis_objectives`, `lcd_curriculum_unit_skills`, `lcd_curriculum_unit_attitudes`, `lcd_curriculum_unit_keywords`, `lcd_curriculum_element_relations` | Relaciones n:m con orden, documento, página y texto original. Todas usan FK `RESTRICT`; no se publican OA implícitos. | Curricular/auditable. |
| `lcd_curriculum_import_files`, `lcd_curriculum_import_candidates`, `lcd_curriculum_import_conflicts`, `lcd_curriculum_import_logs` | Staging por archivo, progreso, confianza, decisiones, conflictos y bitácora. Único escuela+SHA evita duplicados. | Interno restringido/auditable. |

`lcd_class_sessions` puede referenciar programa, unidad y eje; `lcd_assessments` puede referenciar programa y unidad. Las columnas son nulas y aditivas para conservar registros históricos. El servicio de dominio exige programa publicado, misma asignatura/nivel y OA pertenecientes a la unidad.
| `lcd_learning_objective_sources` | Relación N:M entre objetivo y documentos fuente. | Objetivo/fuente, rol, localizador, `relationship_hash` y snapshot de fuente; unicidad por objetivo+fuente+rol+localizador. | Trazabilidad oficial. Declarado en `000011`, pendiente localmente; cada objetivo requiere exactamente un `canonical_text`, y otros roles no lo sustituyen. |
| `lcd_subject_catalog_profiles` | Presentación escalable de una asignatura sin alterar su identidad técnica compartida. | Asignatura única, nombre visible, tipo, descripción, tipos de enseñanza JSON y actores. | Configuración curricular interna; FK de asignatura `RESTRICT`. |
| `lcd_subject_external_aliases` | Match confirmado entre un nombre proveniente de otro libro digital y una asignatura institucional. | Escuela, asignatura, sistema de origen, alcance, tipo de enseñanza, nombre externo normalizado, clave SHA-256, vigencia, confirmador y fecha. | Configuración escolar auditable; unicidad escuela+origen+clave e índices para resolución de importación. |

Los 149 nombres de **Libro digital anterior** viven en configuración versionada y no se siembran en estas tablas. Solo una confirmación explícita crea o actualiza un alias escolar. La importación prioriza el alias compatible con el tipo de enseñanza; ante cero o más de un destino mantiene la asignatura pendiente.

## 3. Sesiones, leccionario, asistencia y firma

| Tabla | Propósito | Campos/controles principales | Clasificación |
|---|---|---|---|
| `lcd_class_sessions` | Ocurrencia de una clase. | Libro/grupo/año/perfil/nómina/asignatura/docentes, fecha/horas, estado, tipo, snapshots, resúmenes, `occurrence_key`, hash, revisión y lock. | Oficial. |
| `lcd_session_topics` | Temas ordenados de la clase. | Sesión, título, descripción, orden, revisión. | Pedagógico. |
| `lcd_session_objectives` | OA trabajados con snapshot. | Objetivo opcional, código/descripción snapshot, nivel de tratamiento, avance, notas, orden. | Pedagógico. |
| `lcd_session_activities` | Actividades realizadas. | Tipo, descripción y orden. | Pedagógico. |
| `lcd_session_resources` | Recursos utilizados. | Tipo, nombre, descripción, URL externa y orden. | Interno; validar URLs. |
| `lcd_session_observations` | Observaciones con visibilidad. | Visibilidad, texto, autor. | Puede ser confidencial; requiere RBAC por visibilidad. |
| `lcd_session_cancellations` | Anulación sin eliminar sesión. | Motivo/código, autorización, evidencia, actor/fecha; única por sesión. | Oficial/auditable. |
| `lcd_attendance_justifications` | Justificación separada de la marca. | Estudiante/matrícula, tipo, vigencia, estado, observación, metadata documental, validador. | Personal/salud posible; restringido. |
| `lcd_session_attendance` | Estado del estudiante por sesión. | Snapshot item, estudiante/matrícula, justificación, estado, llegada/salida, fuente, actor, revisión y hash. Único sesión+estudiante. | Oficial personal. |
| `lcd_daily_attendance_closures` | Consolidación diaria versionada. | Grupo/nómina/día, totales, anomalías, snapshot/hash, revisión, actor/fecha. | Oficial inmutable. |
| `lcd_monthly_attendance_closures` | Consolidación mensual versionada. | Grupo/año/mes, días, totales, porcentaje, snapshot/hash, revisión y cierre. | Oficial inmutable. |
| `lcd_attendance_reconciliations` | Diferencias con fuente externa. | Libro/año/mes, fuente, estado, discrepancias, resolución, actor/fecha. | Oficial/restringido. No equivale a conciliación oficial sin evidencia. |
| `lcd_teacher_signatures` | Verificación del firmante sobre una revisión. | Asignación/staff, entidad/revisión, proveedor, correlación, status/fecha, hashes, código de respuesta, identificador/IP cifrados. | Crítico; nunca almacenar OTP. |
| `lcd_signature_attempts` | Evidencia técnica segura de intentos. | Entidad, staff, proveedor/correlación, resultado/código, detalle sanitizado, IP cifrada, user-agent hash, fecha. | Seguridad restringida; sin OTP/RUN completo. |

### Invariantes de sesión y asistencia

- `lcd_class_sessions.occurrence_key` es única dentro del grupo.
- Cada sesión referencia un snapshot sellado; no se reconstruye retrospectivamente desde la matrícula vigente.
- Solo puede existir una marca por sesión+estudiante.
- `late` exige `arrival_at`; `left_early` exige `departure_at`; una justificación no se infiere de `absent`.
- Cantidad registrada debe coincidir con ítems aplicables antes de firma/cierre.
- Una sesión `signed` o `closed` no se sobrescribe; se enmienda.
- El OTP vive únicamente durante la solicitud al verificador y no se incluye en ninguna tabla.

## 4. Gobierno, revisiones y evaluación

| Tabla | Propósito | Campos/controles principales | Clasificación |
|---|---|---|---|
| `lcd_amendment_requests` | Solicitud formal de corrección. | Registro/revisión/campo, before/propuesta, motivo/evidencia, estado, firmas requeridas, solicitante/revisor y revisión aplicada. | Oficial/auditable. |
| `lcd_amendment_approvals` | Secuencia de decisiones. | Orden, rol requerido, decisión, actor/fecha, comentario y hash. Único solicitud+orden. | Oficial. |
| `lcd_record_revisions` | Payload histórico append-only. | Registro+revisión únicos, revisión anterior, solicitud, payload/hash, motivo/actor/fecha. | Oficial inmutable. |
| `lcd_closure_reopenings` | Reapertura excepcional. | Cierre/revisión/scope, estado, motivo, hash original, solicitud/aprobación/reapertura/recierre y revisión reemplazo. | Oficial crítico. |
| `lcd_grading_schemes` | Escala institucional versionada. | Perfil, código+versión, tipo, mínimo/máximo/aprobación, decimales/redondeo, equivalencias/reglas, vigencia. | Curricular oficial. |
| `lcd_assessment_periods` | Periodo evaluativo. | Libro/año/periodo, código, tipo, fechas, peso, estado/revisión/cierre. | Oficial. |
| `lcd_assessments` | Evaluación/instrumento. | Grupo/periodo/esquema/asignatura/docente, fecha, tipo, peso/puntaje, metadata, estado, revisión y lock. | Pedagógico oficial. |
| `lcd_assessment_objectives` | OA evaluados con snapshot. | Evaluación, OA opcional, código/descripción snapshot y peso. | Curricular. |
| `lcd_student_results` | Resultado individual. | Evaluación/estudiante/matrícula/enlace, estado, puntajes/valor cualitativo/porcentaje, ausencia/exención, revisión/hash/actor. | Personal oficial. |
| `lcd_grade_closures` | Cierre de notas por scope. | Grupo/periodo/asignatura/estudiante opcional, snapshot/hash, revisión y actor/fecha. | Oficial inmutable. |

El campo `lcd_student_results.exempt` no debe exponerse como autorización de eximir asignaturas/módulos. Su semántica queda bloqueada hasta alinearla con Decreto 67, D83 y D170.

## 5. Convivencia, PIE, ausencias, retiros y parvularia

| Tabla | Propósito | Campos/controles principales | Clasificación |
|---|---|---|---|
| `lcd_coexistence_entries` | Anotación/registro de convivencia enlazable al módulo existente. | Estudiante/curso/caso, tipo/categoría, fecha/lugar snapshots, descripción/acción cifradas, reserva, apoderado informado, estado/evidencia/revisión. | Altamente sensible. |
| `lcd_pie_support_records` | Registro autorizado de apoyo/intervención PIE. | Estudiante, atención/plan origen, profesional, tipo/fecha/duración, nombres snapshot, objetivo/detalle/acuerdos cifrados, reserva/evidencia/revisión. | Dato sensible de educación/salud. |
| `lcd_absence_cases` | Expediente de ausencia prolongada. | Estudiante/matrícula, caso, fechas/conteos, riesgo/estado/plazo, fundamento, snapshots, cierre/revisión. | Personal restringido. |
| `lcd_absence_case_actions` | Contacto, gestión o evidencia del caso. | Actor, tipo, fecha programada/real, medio/resultado, notas cifradas, evidencia, fundamento/plazo/estado. | Personal restringido. |
| `lcd_parvularia_plans` | Planificación técnico-pedagógica. | Grupo/responsable, tipo/horizonte/título, ámbitos, experiencia, estrategias, recursos, ambiente, evaluación, diversificación, responsables, vigencia/estado/revisión. | Pedagógico. |
| `lcd_parvularia_plan_objectives` | OA/OAT de una planificación con snapshot. | Plan, objetivo opcional, código/descripción snapshot y tipo. | Curricular. |
| `lcd_parvularia_evaluations` | Observación/evaluación individual o grupal. | Plan/estudiante/OA/evaluador, tipo/scope/fecha, indicador/logro, observación/análisis/feedback/decisión cifrados, evidencia/reserva/estado/revisión. | Altamente sensible de NNA. |
| `lcd_early_withdrawals` | Registro LCD de salida anticipada y eventual retorno, enlazado al flujo de portería. | Escuela/libro/año/grupo, retiro de portería, estudiante/matrícula, estado y horas; snapshots de salida/retorno cifrados y hashados, revisión y actores. El vínculo a portería es único. | Personal/oficial restringido; no elimina matrícula ni asistencia. |
| `lcd_late_arrival_periods` | Periodo institucional para agrupar atrasos de parvularia sin fijar una regla regulatoria por código. | Libro/grupo, nombre, rango, `policy_snapshot` y hash, estado/revisión/actor; único libro+rango. | Cumplimiento interno; no activar una clasificación sin fuente aprobada. |
| `lcd_late_arrivals` | Hecho de llegada tardía de un párvulo. | Libro/grupo/periodo/sesión/asistencia, estudiante/matrícula, `arrival_at`, minutos opcionales, fuente, snapshots, justificación cifrada, evidencia, hash y revisión. | Personal/oficial restringido. |

La API de retiro conserva autorización/evidencia de portería y crea una revisión separada al registrar retorno; nunca da de baja la matrícula. La API de atrasos registra el hecho y avisa expresamente que la clasificación horaria regulatoria está deshabilitada hasta importar una regla oficial verificada. Las tablas permiten estos registros base, pero no implementan por sí mismas el procedimiento íntegro REX 432 ni el Libro Técnico Pedagógico completo de REX 700. Esos cierres regulatorios continúan bloqueados.

## 6. EDE, archivos, fiscalización, auditoría y reportes

| Tabla | Propósito | Campos/controles principales | Clasificación |
|---|---|---|---|
| `lcd_ede_versions` | Artefacto EDE importado. | Código+versión, autoridad/URL, hash fuente obligatorio, hash esquema, vigencia, status, metadata/importador/fecha. | Cumplimiento. El endpoint de carga archiva artefactos locales cifrados y deja `imported`; no existe una versión oficial aplicable importada/activada para operación real. |
| `lcd_ede_mappings` | Regla declarativa local→EDE. | Versión/perfil, código, entidad/campo origen y destino, tipo, required, transformación/validación/default, hash/vigencia/activo. | Cumplimiento. No usar sin validador. |
| `lcd_ede_exports` | Ejecución reproducible de exportación. | Scope, versión/perfil, status, dedupe, hashes fuente/manifiesto, conteos, validador, actor y tiempos. | Oficial crítico. |
| `lcd_ede_export_files` | Archivo de una exportación. | Tipo/nombre/ruta privada/MIME/tamaño/SHA-256/cifrado/metadata. | Altamente sensible. |
| `lcd_ede_validation_runs` | Corrida del validador. | Nombre/versión/digest, status, tiempos/exit code, stdout/stderr sanitizados, reporte/hash y actor. | Cumplimiento/seguridad. |
| `lcd_ede_validation_results` | Hallazgo individual. | Severidad/código, tipo/referencia/campo, mensaje/metadata. | Puede referenciar datos personales; minimizar. |
| `lcd_fiscalization_packages` | Paquete aprobado para requerimiento. | Scope/manifiesto/hash/ruta/tamaño/hash, generación/liberación/revocación y motivo. | Altamente sensible; descarga de cuatro ojos. |
| `lcd_attachments` | Archivo/evidencia privada polimórfica. | Owner/categoría/nombres, MIME servidor, tamaño/hash, disco/ruta/cifrado/reserva, malware scan, retención y actores. | Según owner; por defecto restringido. |
| `lcd_audit_events` | Cadena append-only por establecimiento. | Secuencia/actores/roles/impersonación/break-glass, correlación, evento/entidad/revisión, hashes/diff cifrado, hash previo/evento, IP cifrada, fecha. | Seguridad/confidencial. |
| `lcd_report_exports` | Job/archivo de reporte operacional. | Scope, tipo/formato/título/filtros, status/progreso/watermark, ruta/MIME/tamaño/hashes, tiempos/error. | Según reporte; normalmente restringido. |

### Invariantes EDE y fiscalización

- `lcd_ede_versions.source_hash` nunca se rellena con una cadena de ejemplo.
- Una exportación fija versión, perfil, scope y snapshot; una modificación posterior la marca `stale`.
- `validator_status=not_run` mientras no exista corrida real.
- `validated` exige exit code exitoso, reporte hashado, digest del validador y ausencia de errores críticos.
- Un archivo se descarga solo por endpoint autorizado; `private_path` nunca se devuelve al cliente.
- Un paquete liberado no se modifica; puede revocarse conservando motivo y evidencia.
- `POST /ede/import-standard` no activa ni valida: prohíbe `activate`/`approval_reference`, calcula hashes sobre uploads reales, archiva fuente/esquema/mappings/checksums cifrados y solo deja la versión en `imported` para revisión separada.

## Clasificación de datos

| Nivel | Ejemplos | Reglas mínimas |
|---|---|---|
| Público | nombre del establecimiento, normativa publicada | Integridad y procedencia. |
| Interno | catálogos institucionales, horarios, configuración no secreta | Usuario autenticado y scope. |
| Confidencial | nombres, matrícula, asistencia, notas, contactos | Necesidad funcional, cifrado en tránsito/reposo, logs minimizados. |
| Restringido | RUN/IPE, firmas, IP, expedientes, descargas masivas | Permiso específico, masking, auditoría de lectura/descarga, cifrado de campo. |
| Altamente sensible | PIE, diagnósticos, convivencia, restricciones, observaciones parvularia | Compartimentación, DPIA, vistas mínimas, sin cache compartida, break-glass excepcional. |

## Controles de base de datos pendientes

Antes de producción se deben agregar/verificar:

1. permisos de BD que impidan `UPDATE`/`DELETE` de `lcd_audit_events` y revisiones al usuario de aplicación;
2. constraints/checks compatibles con el motor para estados, porcentaje, rango de mes y llegada en atrasos;
3. índices/planes reales en filtros masivos y aislamiento por `school_id`;
4. casts o servicios de cifrado para **cada** campo `*_encrypted` y rotación de claves;
5. trigger o equivalente solo si es portable y probado; no sustituir las reglas de dominio;
6. modelo de legal hold antes de cualquier eventual disposición, sin habilitar borrado automático;
7. toda futura ampliación/normalización de estados mediante migración aditiva compatible, manteniendo alineados enum, workflow, API y UI según [STATE_MACHINES.md](STATE_MACHINES.md).
