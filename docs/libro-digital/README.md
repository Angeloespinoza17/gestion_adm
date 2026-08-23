# Libro Digital de Clases

Documentación técnica, operativa y de cumplimiento del módulo Libro Digital de Clases (LCD) integrado al ERP.

> Estado al 14 de agosto de 2026: la instalación local tiene el núcleo LCD habilitado y preflight técnico verde, pero el desarrollo **no está habilitado para producción**. EDE, identidad, SIGE, parvularia y fiscalización permanecen apagados/no requeridos; el validador oficial no se ha ejecutado. Esta implementación **no está certificada ni aprobada por MINEDUC o Superintendencia de Educación**.

## Propósito

El módulo busca conservar y operar, con trazabilidad, los registros de matrícula, nómina histórica, clases, asistencia, evaluaciones, firmas docentes, cierres/enmiendas, convivencia, apoyos PIE, ausencias prolongadas, salidas anticipadas/retornos y educación parvularia —incluido el registro factual de atrasos—. La interoperabilidad EDE se diseña como una proyección versionada separada del modelo operacional.

Los objetivos que condicionan todo el diseño son:

- no perder ni sobrescribir silenciosamente registros históricos;
- no eliminar información mediante migraciones o rollback de aplicación;
- usar snapshots y revisiones para preservar el contexto vigente al registrar una clase;
- exigir autorización, estado válido, asignación vigente y contexto de establecimiento en cada operación;
- mantener separados el registro operacional, la proyección EDE y el paquete de fiscalización;
- impedir que una función bloqueada se presente como validada, certificada o lista para fiscalización.

## Estado real de la implementación

| Área | Estado | Evidencia actual |
|---|---|---|
| Esquema de datos | Parcial implementado | El repositorio declara once migraciones aditivas y **69 tablas** `lcd_*`; todos sus `down()` son no-op y las FK usan `RESTRICT` o `SET NULL`. `000008` agrega gobierno de importación, `000009`/`000010` track e identidad de objetivo y `000011` dos tablas de trazabilidad fuente–objetivo. En la base local observada `000001`–`000011` figuran `Ran`; todavía falta validar migración/restore en staging y motor productivo. |
| Modelo de dominio | Parcial implementado | Modelos Eloquent para entidades principales, ULID público, relaciones, casts, snapshots e índices. No todas las 69 tablas declaradas tienen todavía cobertura completa de servicio/API/UAT. |
| Servicios transversales | Parcial implementado | JSON canónico, auditoría/integridad, lock/idempotencia, preflight por capacidad, perfiles/reglas, asistencia de sesión, cierres diarios-mensuales, enmiendas, currículo fail-closed y healthcheck. Reglas diaria/subvención y clasificación regulatoria de atrasos fallan cerradas sin perfil/fuente verificados. |
| Firma docente | Servicio implementado; activación bloqueada | `TeacherSignatureService` valida completitud, asignación, RUN, estado/concurrencia, usa OTP efímero, registra evidencia sanitizada y sello HMAC. Existen drivers `disabled`, `fake` de pruebas, transaccional y masivo. Faltan credenciales, contrato productivo, replay/rate limit probado y flujo API completo. No es una certificación ni una afirmación de firma electrónica avanzada. |
| Interfaz Vue | Parcial implementado; build verde | Shell para jornada/libros/asignaturas/workspace/estadísticas/reportes/cumplimiento. El workspace cubre sesiones, leccionario/cobertura OA fail-closed, asistencia/retiro anticipado, cierres/conciliación manual no oficial, evaluaciones, PIE, convivencia, ausencias, retiros/retornos, enmiendas con tres actores, parvularia y atrasos factuales. Incluye conflictos `If-Match`, idempotencia y gates por capability/flag; EDE incorpora import multipart sin activación. El build final terminó con exit code `0`. No hay listado histórico de cierres/conciliaciones ni panel/endpoint directo de reapertura; clasificación de atrasos y envío SIGE oficial están deliberadamente bloqueados. La UI no es autoridad de acceso y requiere UAT. |
| API servidor | Amplia, aún no liberable | Hay **94 rutas** versionadas `/api/libro-digital/v1`, incluidas seis para plantilla/listado/validación/aprobación/activación curricular, además de núcleo, operaciones suplementarias, EDE y auditoría. Usan autenticación, permiso base, flag, Requests, alcance escolar y, según operación, permisos dedicados/idempotencia. El conteo no demuestra una matriz exhaustiva de autorización, workflow, concurrencia ni adecuación regulatoria; fiscalización continúa bloqueada. |
| RBAC | Seed/policies parciales | La base local y `LibroDigitalSeeder` están reconciliados con 35 permisos y 11 módulos LCD, incluidos import/approve/activate curricular. Policies cubren libro, sesión y asignatura, más middleware/permisos de ruta. Falta demostrar enforcement productivo de cada endpoint/caso y cerrar el gap por el cual una misma cuenta puede aprobar y activar. |
| EDE | Implementación técnica parcial; bloqueada | Existen esquema, comando y endpoint de importación explícita, proyector declarativo, servicio/job único de exportación a staging cifrado, API y runner/job asíncrono endurecido de `parse`/`insert`/`check`. `POST /ede/import-standard` valida uploads locales, calcula hashes, archiva fuente/esquema/mappings/checksums cifrados y **solo deja estado `imported`**; prohíbe activar o adjuntar aprobación en esa petición. Esto no significa que exista una versión oficial importada. Faltan versión/digest/argv oficiales, snapshot consistente probado, corrida real del validador y release aprobada; `validator_status` permanece `not_run`. |
| SIGE | Bloqueado | `LCD_SIGE_DRIVER=disabled`; no se encontró un contrato de API oficial documentado. No se permite scraping ni API simulada. |
| Reportes y PDF | Backend parcial | El servicio materializa al solicitar una instantánea cifrada y hashada; el job verifica y usa esa instantánea para PDF/XLSX/CSV/JSON, almacena en privado y mantiene la marca `BORRADOR · NO VALIDADO PARA FISCALIZACIÓN`. Existe descarga autenticada y expirable. Faltan probar el aislamiento del snapshot en el motor objetivo, alinear el catálogo con datasets realmente especializados, ampliar RBAC y completar QA visual/funcional. |
| Fiscalización | Bloqueado | Hay esquema de paquetes, pero no generador; no se ha ejecutado el validador EDE oficial. |
| Currículo y operaciones suplementarias | Instalación local técnica activada; UAT/cumplimiento productivo abiertos | Objetivos/cobertura solo usan catálogos activados y vínculos de escuela/año/asignatura. Localmente existe un lote activado con 6.337 objetivos (5.394 activos/943 inactivos), 6 fuentes y 12.674 relaciones. La oferta confirmada el 22-08-2026 dejó 58 asignaturas/núcleos oficiales activos, 34 vínculos para NT1/NT2/1B/2B y 34 libros con nómina sellada en `draft`; no se abrieron porque no existe asignación docente inequívoca. Esto no certifica autenticidad/completitud normativa ni reemplaza UAT. |
| Programas curriculares PDF | Implementado; publicación gobernada | Carga individual/masiva, storage cifrado, extracción por página, clasificación/parser, staging, conciliación OA, conflictos, revisión, publicación idempotente, matriz, búsqueda, gráficos, PDF e integración con leccionario/evaluaciones. OCR queda deshabilitado por defecto y producción requiere backup, worker, UAT y fuente oficial aprobada. |
| Backup/restauración | Procedimiento documentado | Falta una restauración de ensayo evidenciada para este módulo y sus archivos privados. |
| Operación en segundo plano | Codificada; operación no demostrada | Existen 16 comandos LCD y cinco jobs `ShouldBeUnique`. `lcd:curriculum:sync-official-books` es dry-run por defecto, exige aprobación para aplicar y respaldo declarado en producción. El scheduler agenda health cada cinco minutos, integridad diaria y detecciones de sesiones/firmas faltantes en días hábiles. Falta evidencia de workers, `schedule:run`, alertas y resultados en un host productivo. `lcd:retention:run --execute` falla cerrado y no borra/anonimiza. |
| Despliegue local observado | Instalación técnica lista; no equivale a release productivo | `000001`–`000011` están `Ran`; hay 35 permisos/11 módulos LCD, catálogo curricular activado y preflight `ready/core_ready/module_enabled=true`. Auditoría íntegra sobre 6 eventos. MySQL cumple 64 MiB, pero PHP CLI está en 2/8 MiB y no acredita el runtime web/FPM mínimo 20/64 MiB. Falta staging, restore, carga, pentest, UAT y segregación de tres actores. |
| Pruebas automatizadas | Suite focalizada verde; suite global no declarada verde | La corrida literal `Feature/LibroDigital + Unit/LibroDigital + Unit/Http Requests/Resources` pasó **70 pruebas/553 assertions** en **5,69 s**, exit code `0`; solo apareció el warning XML conocido. Incluye bootstrap dry-run/guardas, trazabilidad multisource, evidencia/hash fail-closed, portabilidad, preflight, aislamiento cross-grade y migraciones forward-only. No cubre autenticidad normativa, motor productivo, Docker/validador oficial, restore, carga, pentest ni UAT. |
| Cumplimiento | Abierto | Los bloqueos vigentes se mantienen en [OPEN_COMPLIANCE_ITEMS.md](OPEN_COMPLIANCE_ITEMS.md). |

## Feature flags y configuración segura

La configuración base está en `config/libro_digital.php` y es denegación por defecto:

| Flag/configuración | Valor seguro | Condición mínima para activar |
|---|---:|---|
| `LCD_ENABLED` / `lcd_enabled` | `false` | API, RBAC, preflight, pruebas funcionales, privacidad y piloto aprobados. |
| `lcd_parvularia_enabled` | `false` | Perfil REX 700 vigente cargado y reglas finas verificadas. |
| `lcd_identity_verifier_enabled` | `false` | Credenciales, contrato, seguridad OTP y pruebas de integración aprobadas. |
| `LCD_EDE_ENABLED` / `lcd_ede_export_enabled` | `false` | Fuente EDE importada, versión/digest fijados, proyección probada y validador oficial ejecutado. |
| `lcd_sige_reconciliation_enabled` | `false` | Solo gateway de archivo/manual auditado o API oficial formalmente documentada. |
| `lcd_fiscalization_download_enabled` | `false` | Paquete íntegro, validado, aprobado y descarga privada auditada. |

Un flag no reemplaza autorización. La aplicación debe comprobar, en este orden, autenticación, permiso, establecimiento, año, asignación funcional, estado, vigencia y bloqueo normativo.

## Bloqueos de activación obligatorios

No se puede activar el módulo para operación oficial mientras exista cualquiera de estos puntos:

1. La versión/release EDE y el digest de `edemineduc/etl` no están fijados.
2. El validador oficial `check` no se ha ejecutado y no existe reporte íntegro archivado.
3. El verificador de identidad no tiene credenciales ni contrato productivo probado.
4. El catálogo local está importado, versionado y hashado, pero falta conciliación normativa/UAT y aceptación del expediente para el alcance productivo.
5. No hay API SIGE oficial documentada; el driver debe continuar deshabilitado.
6. La API/RBAC está implementada y cubierta por la suite focalizada, pero aún requiere UAT de extremo a extremo por establecimiento, año y asignación, además de revisión institucional de membresías y segregación.
7. La proyección EDE no toma todavía un snapshot transaccional consistente y el staging no ha sido contrastado contra el formato real del contenedor.
8. No existe evidencia de restauración de respaldo ni DPIA aprobada para el tratamiento de alto riesgo.

La lista completa y sus criterios de cierre están en [OPEN_COMPLIANCE_ITEMS.md](OPEN_COMPLIANCE_ITEMS.md).

## Documentos

| Documento | Contenido |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Contexto, componentes, dominio, límites de confianza y decisiones. |
| [NORMATIVE_SOURCES.md](NORMATIVE_SOURCES.md) | Registro de fuentes oficiales, fechas de consulta y hashes reales. |
| [normativa/sources.json](normativa/sources.json) | Manifiesto metadata-only de fuentes; tamaños/hashes solo para bytes realmente obtenidos. |
| [normativa/README.md](normativa/README.md) | Política de procedencia, bytes, huellas y actualización del manifiesto. |
| [NORMATIVE_MATRIX.md](NORMATIVE_MATRIX.md) | Obligación, fuente, control, estado y evidencia requerida. |
| [CURRICULUM_CATALOG_CONTRACT.md](CURRICULUM_CATALOG_CONTRACT.md) | Niveles NT1/NT2/1B–8B/1M–4M, familias, tipos OA/OAT y reglas de procedencia. |
| [CURRICULUM_IMPORT_RUNBOOK.md](CURRICULUM_IMPORT_RUNBOOK.md) | Contrato XLSX institucional, validaciones, segregación y criterios para cerrar el blocker curricular. |
| [CURRICULUM_PROGRAM_CATALOG.md](CURRICULUM_PROGRAM_CATALOG.md) | Importación PDF, revisión, publicación, matriz, integración docente, permisos y despliegue. |
| [DATA_DICTIONARY.md](DATA_DICTIONARY.md) | Diccionario de las 69 tablas declaradas, invariantes y clasificación de datos. |
| [RBAC_MATRIX.md](RBAC_MATRIX.md) | Roles, permisos, alcances y segregación de funciones. |
| [STATE_MACHINES.md](STATE_MACHINES.md) | Estados, transiciones y discrepancias que deben resolverse. |
| [EDE_MAPPING.md](EDE_MAPPING.md) | Artefactos oficiales, mapeo versionado y controles de sanidad. |
| [EDE_EXPORT_RUNBOOK.md](EDE_EXPORT_RUNBOOK.md) | Procedimiento de exportación y validación EDE. |
| [FISCALIZATION_RUNBOOK.md](FISCALIZATION_RUNBOOK.md) | Preparación, aprobación y entrega de paquetes. |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Despliegue apagado, preflight y activación progresiva. |
| [ROLLBACK.md](ROLLBACK.md) | Reversión forward-only sin borrar registros. |
| [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md) | Alcance, cifrado, restauración y evidencia. |
| [PRIVACY_AND_SECURITY.md](PRIVACY_AND_SECURITY.md) | Privacidad, Ley 19.628/21.719 y controles de seguridad. |
| [THREAT_MODEL.md](THREAT_MODEL.md) | Modelo STRIDE y tratamiento de amenazas. |
| [INCIDENT_RESPONSE.md](INCIDENT_RESPONSE.md) | Clasificación, contención, recuperación y notificación. |
| [TEST_PLAN.md](TEST_PLAN.md) | Estrategia de pruebas y gates de liberación. |
| [OPEN_COMPLIANCE_ITEMS.md](OPEN_COMPLIANCE_ITEMS.md) | Registro de bloqueos y evidencias faltantes. |

## Convenciones

- Fechas técnicas persistidas en UTC; presentación en `America/Santiago`; intercambio externo RFC 3339 con offset.
- Identificadores internos numéricos y `public_id` ULID en recursos expuestos.
- Archivos y exportaciones en almacenamiento privado; nunca se expone una ruta física.
- SHA-256 identifica snapshots, archivos, manifiestos y cadena de auditoría; no equivale por sí solo a firma electrónica ni certificación.
- Un registro firmado o cerrado se corrige creando una solicitud y una nueva revisión; nunca por sobrescritura directa.
- `validated` se reserva exclusivamente para una ejecución exitosa y evidenciada del validador oficial con versión/digest registrados.
- La investigación normativa base fue consultada el **2026-08-12** y la verificación complementaria de Currículum Nacional el **2026-08-13**. Debe revisarse antes de cada activación anual o cambio regulatorio.

## Advertencia de uso

Estos documentos son controles de ingeniería y cumplimiento, no un dictamen jurídico. La dirección del establecimiento, el sostenedor, su asesoría jurídica y el responsable de seguridad/privacidad deben aprobar los perfiles normativos, plazos de conservación y procedimientos institucionales antes del uso oficial.
