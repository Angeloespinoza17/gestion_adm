# Plan de pruebas del Libro Digital

## Objetivo y estado

Este plan define la evidencia mínima para activar el Libro Digital de Clases (LCD). Al **2026-08-14** la instalación local tiene el núcleo habilitado y preflight técnico verde, pero no se ha ejecutado una certificación integral, piloto productivo, restauración completa ni el validador oficial EDE. La existencia de pruebas unitarias no convierte el módulo en apto para uso oficial.

La liberación se evalúa por capacidad. Firma, EDE, SIGE, parvularia y fiscalización permanecen bloqueadas aunque las pruebas generales resulten satisfactorias.

## Inventario automatizado existente

| Prueba | Cobertura observada | Lo que no demuestra |
|---|---|---|
| `ForwardOnlyMigrationTest` | Descubre por glob las once migraciones `2026_08_13_*` y rechaza rollback destructivo/cascade. | La suite efímera no prueba actualización sobre respaldo productivo, staging ni todos los motores. |
| `CanonicalJsonTest` | Canonización determinista y hashing. | No prueba todos los payloads regulatorios ni compatibilidad EDE. |
| `WorkflowStateMachineTest` | Transiciones declaradas de libro, sesión, enmienda y exportación; EDE debe pasar por `validation_queued`. | No prueba que cada endpoint/servicio fuerce la máquina ni la persistencia concurrente. |
| `ChileanRunTest` | Formato y dígito verificador de RUN chileno. | No verifica identidad ni autorización del firmante. |
| `FakeIdentityVerifierTest` | Doble de pruebas para escenarios de identidad. | Nunca es evidencia válida de verificación ministerial. El driver `fake` debe estar prohibido fuera de testing. |
| `MineducTransactionalIdentityVerifierTest` | Contrato HTTP transaccional documentado: `rut`, `otp`, `DateWithTimeZone`, respuesta booleana y errores básicos. | No prueba credenciales, enrolamiento, TLS, rate limit, ventana anti-replay ni disponibilidad productiva. |
| `AttendancePdfBuilderTest` | Construcción básica de un PDF de asistencia. | No prueba el catálogo completo, fidelidad visual, accesibilidad, firmas ni aceptación fiscalizadora. |
| `XlsxReportBuilderTest` | Estructura ZIP/XML básica, hojas y mitigación de fórmulas del XLSX. | No prueba apertura en todas las suites, catálogo completo, autorización ni aceptación institucional. |
| `LibroDigitalApiTest` | Crear libro/nómina sin alterar matrícula, rechazo de lock obsoleto, aislamiento escolar, formatos de reporte, fail-closed de flags sensibles y rechazo de campos no persistibles del catálogo global. | No cubre matriz completa de roles, todos los flags/endpoints, firma, descargas ni todos los datos sensibles. |
| `LibroDigitalReportSnapshotTest` | La solicitud guarda snapshot cifrado, su hash coincide, el job genera desde ese contenido y conserva `draft_watermark=true`. | No demuestra aislamiento transaccional en el motor productivo, autorización de descarga, expiración, catálogo completo ni QA visual. |
| `LibroDigitalSupplementaryApiTest` | Evaluaciones/resultados/cierre inmutable, PIE/convivencia cifrados y revisionados, ausencias, parvularia fail-closed, retiro/retorno enlazado a portería con identificadores protegidos y atraso factual sin clasificación inventada. | No cubre todos los roles/campos, catálogo OA/OAT oficial, todas las restricciones de portería, adjuntos/antivirus, reglas regulatorias finas, carga ni UAT. |
| `LibroDigitalClosureAmendmentApiTest` | Cierre diario/mensual, conciliación manual no oficial, referencias públicas selladas y enmienda con solicitante/revisor/aplicador distintos que reabre y versiona cierres derivados. | No cubre todos los agregados/campos permitidos, verificador real para nueva firma, todas las carreras ni aprobación institucional de reglas de cierre. |
| `LibroDigitalEdeAuditApiTest` | Fail-closed EDE, importación local idempotente con archivos cifrados y estado `imported`, revalidación antes de encolar, no exposición de staging, descarga privada liberada/hashada, aislamiento escolar, redacción/verificación de auditoría y vista por entidad allowlisted. | No autentica una fuente oficial, no activa una versión, no ejecuta el contenedor oficial, no prueba un paquete real EDE, release/fiscalización ni la matriz completa de permisos/carreras. |
| `CurriculumXlsxReaderTest` + Request evidence | La plantilla real de cinco hojas es legible; se rechazan fórmulas, claves inseguras, MIME/extensión divergentes y exceso de tamaño. | No autentica el contenido curricular ni reemplaza antivirus/allowlist/UAT. |
| `CurriculumBootstrapCommandTest` | Repite el dry-run sin mutaciones, comprueba 129 asignaturas/2 activas esperadas y que `--apply` requiere `--validate-only` más confirmación explícita sin aprobar ni activar. | No ejecuta un apply real ni sustituye revisión de operador, storage o motor productivo. |
| `CurriculumImportApiTest` | Flujo validar→aprobar→activar, SoD parcial, idempotencia, colisiones scoped, evidencia faltante/hash divergente fail-closed, corpus portable entre dos escuelas, evidencia separada y backfill de `objective_key`. | Usa fixtures sintéticos; no demuestra que el corpus oficial NT1–4M sea completo, vigente o reconciliado. |
| `CurriculumTraceabilityGuardTest` | Preflight rehashea bytes cifrados, detecta corrupción y exige exactamente una fuente canónica verificada por objetivo. | No valida por sí solo autoridad, acto, contenido ni vigencia de una fuente real. |

No hay todavía una suite automatizada equivalente para los 15 comandos, los cuatro schedules LCD o el comportamiento real de los cinco jobs `ShouldBeUnique` sobre Redis/cola/storage de destino. Deben probarse dry-run/`--execute`, aprobación, scopes, locks, reintentos, backoff, fallos, alertas y persistencia de resultados sin PII.

Este inventario debe confirmarse en CI contra el commit candidato. No se debe copiar a un acta de liberación un resultado de una revisión anterior.

### Ejecución observada en esta revisión

El 2026-08-12 se ejecutó inicialmente:

```bash
php artisan test tests/Feature/LibroDigital tests/Unit/LibroDigital
```

La corrida focalizada final congelada fue:

```bash
php artisan test tests/Feature/LibroDigital tests/Unit/LibroDigital tests/Unit/Http/Requests/LibroDigital tests/Unit/Http/Resources/LibroDigital
```

Resultado final reproducido al 2026-08-14: **70 pruebas pasaron, 553 assertions, 5,69 s, exit code `0`**. PHPUnit solo emitió la advertencia conocida del schema XML obsoleto. Esta corrida cubre LCD, bootstrap dry-run/guardas, flujo curricular multisource, metadata HTTP segura, aislamiento exacto de grado y la aceptación controlada de `curriculum_track=GENERAL` para 3M/4M dentro del `source_scope` técnico histórico `HC_3M_4M`; no prueba migraciones sobre copia productiva/motor destino, autenticidad/completitud normativa, Docker EDE, integración ministerial real, render visual completo, carga, pentest, restore o UAT.

También se ejecutó directamente una suite global de **310 tests**. Los casos LCD quedaron verdes, pero el repositorio terminó con **40 errores y 3 fallos** clasificados en esta revisión como preexistentes/ajenos al alcance LCD: fixtures/orden en SQLite, uso de `DATE_FORMAT` y columnas esperadas por módulos concurrentes. Por ello **no se declara la suite global verde**; esas incidencias requieren su propio triage/evidencia antes de cualquier release general.

Como evidencia distinta, el build frontend final terminó con exit code `0`; `migrate:status` local muestra `000001`–`000011` `Ran`, y RBAC/navegación están reconciliados en 35 permisos/11 módulos. El lote curricular local quedó `activated` con 6.337 objetivos, 6 fuentes, 12.674 relaciones y 4 vínculos; preflight devuelve `ready/core_ready/module_enabled=true` y la auditoría es íntegra sobre 6 eventos. La misma cuenta aprobó y activó, permitido por el servicio pero inferior al SoD recomendado. Esta evidencia local no significa que staging/producción estén migrados ni que exista UAT/certificación normativa. PHP web/FPM 20/64 MiB aún debe verificarse.

## Ambientes y datos

- **Unitario:** SQLite o base efímera, sin red ni datos reales.
- **Integración:** mismo motor y versión de BD, cache, cola y storage que producción.
- **Preproducción:** topología equivalente, workers aislados y configuración deny-by-default.
- **EDE controlado:** host dedicado para Docker, sin red en el contenedor, imagen fijada por digest y comandos archivados.
- **Restore:** ambiente segregado sin salida de correo, webhooks ni integraciones.

Usar datos sintéticos que incluyan RUN válidos ficticios reservados para pruebas, nombres no reales y casos límite. Nunca reutilizar OTP, antecedentes PIE, convivencia o salud de estudiantes reales en desarrollo.

## Orden de ejecución

1. análisis estático, formato y pruebas unitarias;
2. migración desde una copia anonimizada de cada versión soportada;
3. pruebas de integración de servicios, base de datos, storage y colas;
4. pruebas API/RBAC/tenencia y concurrencia;
5. pruebas de privacidad y seguridad;
6. generación y revisión visual de reportes;
7. restore completo y reconciliación;
8. EDE con versión/digest fijados y contenedor oficial;
9. UAT institucional y acta de aceptación.

Un fallo crítico detiene las fases siguientes; no se compensa con una excepción manual no registrada.

## Migraciones y conservación

### Casos obligatorios

- instalar desde una BD vacía y ejecutar las once migraciones LCD dos veces sin efectos laterales; confirmar 69 tablas `lcd_*` y documentar los cortes previos 7/64 y 8/67;
- migrar una copia con datos previos y comprobar conteos, hashes, FK, índices y `public_id`;
- confirmar que todos los `down()` LCD son no-op y que las FK históricas son `RESTRICT` o `SET NULL`;
- desplegar versión N, crear registros, desplegar N+1 y volver el código a N sin ejecutar `down()`;
- fallar deliberadamente una migración aditiva y ensayar su corrección forward-only;
- comprobar que flags apagados no alteran ni eliminan registros;
- ejecutar el reporte de retención y confirmar que no modifica datos.

### Prohibiciones en producción

No ejecutar `migrate:fresh`, `db:wipe`, `migrate:reset`, `migrate:refresh` ni rollback amplio. Ninguna prueba de rollback puede borrar tablas o filas oficiales.

### Evidencia

Commit, motor/versión, migraciones aplicadas, consultas de conteo antes/después, checksums de muestra, logs sin PII, duración y aprobación del DBA.

## API, RBAC y aislamiento

La API registra 94 rutas y todavía no cuenta con cobertura feature exhaustiva. Antes de considerar estable cualquier ruta se exige probar:

- 401 sin autenticación y 403 sin permiso;
- aislamiento entre escuelas aun manipulando ID numérico, ULID, RBD, body, query y header;
- alcance por año, libro, curso, asignación y vigencia docente;
- usuarios de una escuela sin capacidad de inferir existencia en otra;
- `If-Match`/`lock_version`: éxito, ausencia, versión obsoleta y carrera concurrente;
- `Idempotency-Key`: reintento idéntico, reutilización con payload distinto, expiración y carrera;
- validación server-side independiente de la UI;
- autorización de descargas privadas y revocación/expiración;
- middleware de flags tanto para lecturas como escrituras; documentar expresamente cualquier lectura permitida con el módulo apagado;
- cobertura positiva y negativa de cada permiso listado en [RBAC_MATRIX.md](RBAC_MATRIX.md).

Casos críticos de segregación:

- un superadministrador no puede fabricar la firma de otro docente;
- solo el docente asignado y vigente puede firmar su propia sesión;
- quien solicita una enmienda no la aprueba cuando se exige cuatro ojos;
- soporte no obtiene acceso silencioso; break-glass requiere ticket, motivo, expiración y auditoría;
- un auditor o fiscalizador no muta datos.

## Dominio y máquinas de estado

### Libro

- apertura bloqueada sin perfil, RBD, año, fuente y traspaso previo cuando corresponda;
- cierre bloqueado con sesiones pendientes o integridad fallida;
- reapertura motivada y auditada, sin reescritura de historia;
- toda transición fuera de [STATE_MACHINES.md](STATE_MACHINES.md) retorna conflicto.

### Sesión, nómina y asistencia

- snapshot sellado no cambia por matrícula/retiro posterior;
- no se aceptan duplicados ni estudiantes fuera del snapshot;
- completitud exacta contra ítems aplicables;
- atraso requiere llegada y retiro anticipado requiere salida;
- `not_applicable` se justifica y no disfraza un ausente;
- firma y cierre vuelven inmutable la revisión;
- editar mientras otra solicitud firma o cierra produce conflicto, no pérdida;
- probar el adaptador del controlador entre `arrival_time/departure_time` de API y `arrival_at/departure_at` del servicio, incluido timezone/DST, lock y serialización de vuelta;
- separar y probar justificación/observación: el controlador actual las compacta en `notes` y marca justificación `pending`, por lo que aún no sustituye el workflow formal de `lcd_attendance_justifications`.

### Enmiendas y cierres

- solicitud conserva valor anterior/nuevo, motivo, actor y evidencia;
- aprobación/rechazo y aplicación son eventos separados; probar que solicitante, revisor y aplicador sean tres personas distintas;
- la aplicación crea revisión, recalcula hash, marca exportaciones afectadas como `stale` y reabre/versiona cierres derivados sin sobrescribir la revisión anterior;
- un recierre exige que el registro corregido vuelva al estado/firma requeridos; no se permite “recerrar” solo por tener una enmienda aprobada;
- no se permite update/delete directo sobre una revisión firmada/cerrada.

### Evaluación, PIE, convivencia, ausencias y parvularia

- reglas de escala, redondeo y cierre coinciden con perfil aprobado;
- datos sensibles solo se muestran a funciones necesarias;
- adjuntos se validan, escanean y descargan en privado;
- no existe baja automática de matrícula por ausencia;
- parvularia usa perfil y catálogo OA/OAT propios, nunca reglas escolares genéricas por accidente.

### Currículo, salidas anticipadas y atrasos parvularios

- objetivos y cobertura solo consideran catálogos activos con `source_hash`, vínculo escolar/anual/asignatura activo y vigencia aplicable;
- sin ese catálogo, la API retorna `COMPLIANCE_BLOCKER_CURRICULUM_NOT_IMPORTED`, lista vacía/porcentaje nulo y no presenta cobertura como certificada;
- probar aislamiento escolar, año, libro, asignatura, filtros, vigencia, objetivos desactivados y snapshots de objetivos ya usados;
- el retiro anticipado reutiliza las restricciones/autorizaciones de portería, sella snapshot cifrado/hashado y no modifica ni elimina matrícula/asistencia;
- el retorno exige lock/revisión y una hora posterior a la salida, crea snapshot/hash de retorno y conserva el retiro original;
- el atraso parvulario exige flag/perfil/libro abierto/nómina, persiste el hecho y la hora, cifra justificación y no inventa una clasificación regulatoria;
- probar DST/zona escolar, duplicados, estudiante fuera de nómina, acceso entre escuelas y redacción de identificadores/evidencia.

### Importación y activación curricular

El importador/API multisource de extremo a extremo ya existe y verifica adjuntos contra hashes declarados; todavía no hay un corpus oficial real conciliado/UAT ni corte final de la suite. Antes de cerrar el blocker se deben automatizar y ejecutar, como mínimo, los casos detallados en [CURRICULUM_IMPORT_RUNBOOK.md](CURRICULUM_IMPORT_RUNBOOK.md):

- aplicar `000008`–`000011` sobre base vacía y copia con datos; comprobar tablas, backfill de `objective_key`, track, fuentes/relaciones N:M, FK/índices, idempotencia y `down()` no-op;
- rechazar extensión/MIME/firma ZIP incoherentes, macros, fórmulas, DDE, enlaces externos, hojas ocultas, encabezados alterados, PII y archivos sin antivirus limpio;
- verificar las cinco hojas requeridas y `Referencias` opcional, rechazar hojas desconocidas/ocultas, comprobar esquema `lcd-curriculum-import/v1`, dominios/URLs oficiales, cada `source_key`/scope/rol/localizador, tamaño y SHA-256 contra bytes archivados, fechas/vigencia, RBD/año y hashes canónicos;
- demostrar que una URL no oficial, hash inventado, fuente referenciada sin `evidence_files[SOURCE_KEY]` o bytes divergentes **no** pueden activarse ni cerrar el blocker; cubrir varios documentos/actos bajo un mismo scope y exactamente un `canonical_text` por objetivo;
- probar portabilidad: mismo corpus con otro tenant/año/vínculo conserva `catalog_payload_hash`, pero cada lote/activación conserva evidencia propia; cambiar fuente, objetivo o relación cambia el hash y nunca reutiliza una ruta privada ajena;
- aceptar solo `NT1`, `NT2`, `1B`–`8B`, `1M`–`4M`; exigir que NT1/NT2 mapeen a `NT` sin divergencia y que 3M/4M declaren `GENERAL` (Formación General), `HC`, `TP` o `ARTISTICA`, con especialidad/mención TP cuando corresponda;
- reconciliar código, texto y localizador del 100 % de OA/OAT/OAH/OAA/OAG; mantener `OAC`/ARTISTICA bloqueados sin fuente/semántica aprobada; rechazar renumeración, paráfrasis, truncamiento, duplicados scoped, indicadores convertidos en OA y activación parcial;
- comprobar que los vínculos usan asignaturas ERP existentes, nivel/modalidad compatibles, vigencias no solapadas y decisión humana; ningún fuzzy match se autoactiva;
- cubrir todos los estados de lote y activación, transiciones ilegales, segregación solicitante/aprobador/activador, lock obsoleto, carrera, reintento idempotente, mismo código con bytes distintos y fallo atómico;
- probar expresamente que aprobador y activador sean actores distintos o registrar la decisión institucional que cambie ese gate; hoy el servicio solo los separa del solicitante;
- verificar cifrado/storage privado, autorización de evidencia, auditoría/hashes, supersesión/revocación no destructivas y conservación de snapshots históricos;
- demostrar con consultas, cobertura y preflight que solo la versión aprobada/activada del alcance correcto es utilizable; hasta entonces el blocker y porcentaje `null` permanecen.

### Auditoría por entidad

- `entity_type` pertenece a la allowlist servidor y el recurso pertenece al establecimiento del actor;
- la respuesta excluye diff cifrado, IP, user-agent y payloads before/after sensibles;
- la vista contextual no reemplaza la verificación de la cadena completa del establecimiento; reporta ambos alcances sin afirmar integridad si la cadena global falla.

### UI y contrato final visible

- probar carga, vacío, error, offline/reintento y conflicto `409/412` en cada superficie nueva;
- confirmar que capability/feature flag solo oculta/deshabilita UX y que el servidor rechaza igualmente la acción;
- cobertura OA sin catálogo oficial muestra blocker, no porcentaje oficial ni objetivos aproximados;
- cierre/conciliación muestran el resultado recién creado sin simular un historial que la API todavía no expone;
- no mostrar una acción de reapertura directa mientras no exista endpoint/panel autorizado; la reapertura derivada por enmienda se representa como historial, no overwrite;
- import multipart EDE no permite activación y su éxito se presenta como `imported`, no listo/validado;
- atraso parvulario no recibe etiqueta regulatoria y conciliación SIGE conserva el rótulo manual/no oficial.

## Firma docente e identidad

### Pruebas de servicio

- preparar firma solo con asistencia y leccionario completos;
- el payload canónico incluye revisión, nómina, leccionario y zona/offset;
- el firmante es staff activo, RUN válido y responsable/asignado al grupo/asignatura en la fecha;
- OTP solo vive en memoria de la solicitud y no se persiste en intentos, auditoría, cache, excepciones o logs;
- rechazo, timeout, circuito abierto y respuesta inválida regresan a `ready_to_sign` sin firma válida;
- concurrencia permite una sola firma verificada;
- la firma guarda identificador del verificador, fecha, evidencia sanitizada y sello HMAC de integridad;
- modificar cualquier elemento firmado rompe la verificación o exige enmienda.

### Pruebas de integración bloqueadas

Requieren credenciales y contrato institucional aprobado: enrolamiento, timestamp permitido, anti-replay, límites, TLS, errores, mantenimiento, sandbox/productivo y respuesta real. Hasta completarlas el driver sigue `disabled` y no se prueba con datos personales reales.

El HMAC de aplicación no se rotula como firma electrónica avanzada ni certificación MINEDUC.

## Reportes

El backend actualmente cubre un subconjunto operacional y genera PDF/XLSX/CSV/JSON mediante job. Se debe probar:

- permiso, alcance escolar y snapshot cifrado/hashado; la prueba existente confirma que el job usa el contenido fijado, pero aún debe probarse aislamiento consistente durante su captura en el motor productivo y bajo escritura concurrente;
- watermark `BORRADOR / NO OFICIAL` mientras perfil, cierre y aprobación de release no permitan informe oficial; estar `closed` por sí solo no basta;
- PDF parseable y revisión visual página por página: encabezados, RBD, curso, periodo, paginación, tablas largas, caracteres y zona horaria;
- CSV UTF-8, separador acordado, escape de fórmulas (`=`, `+`, `-`, `@`), saltos de línea e inyección;
- XLSX válido en lectores soportados, XML escapado, nombres de hoja únicos, filas/columnas límite, tipos y mitigación de fórmulas;
- tratar warnings/deprecations del builder como fallo en CI y mantener cubierto el normalizador de nombres de hoja;
- JSON con schema/version, no payload interno accidental;
- nombres seguros, MIME servidor, SHA-256, storage privado, descarga autenticada y expiración;
- reintento idempotente, job fallido y cleanup no destructivo;
- catálogo UI versus tipos realmente soportados; una opción sin builder debe quedar deshabilitada;
- cada formato solo se declara disponible después de pruebas de descarga, apertura, integridad y aceptación; la prueba unitaria de XLSX no cubre esas capas.

La prueba unitaria del PDF no reemplaza la verificación visual ni la aceptación institucional.

## EDE

### Importación local — no es activación oficial

- el endpoint exige permiso `ede.manage`, escuela accesible, idempotencia y tres uploads permitidos; `mappings` debe ser JSON declarativo;
- calcula hashes sobre bytes reales y archiva fuente, esquema, mappings normalizados y checksums cifrados en storage privado;
- una repetición idéntica es idempotente; una colisión/versionado inconsistente debe fallar sin sobrescribir el archivo anterior;
- `activate` y `approval_reference` están prohibidos en HTTP; la respuesta queda `imported`, nunca `active`, `validated` o `released`;
- no exponer `archive_path`, contenido plano o secretos en respuesta/auditoría/logs; probar cleanup ante fallo parcial;
- un upload exitoso no demuestra origen oficial, vigencia, compatibilidad del esquema, aprobación ni corrida del validador.

### Proyección y validación local

- importación de versión/mapeo con URL, hashes reales y vigencia;
- transformaciones declarativas permitidas; rechazo de funciones/código arbitrario;
- requeridos, cardinalidades, catálogos, tipos, fechas, RUN y referencias;
- orden determinista y mismos hashes para el mismo snapshot;
- lectura bajo snapshot consistente de BD; una mutación simultánea no produce mezcla temporal;
- staging cifrado, storage privado, cleanup de texto plano y manifest verificable;
- deduplicación por libro/revisión/versión/alcance;
- flags, fuentes, versión, digest y blockers fallan cerrados;
- la petición de validar solo puede pasar a `validation_queued`, incrementa el lock y encola después del commit; nunca ejecuta el contenedor dentro del request;
- dos solicitudes concurrentes/obsoletas producen un único job o un conflicto predecible (`409/412` según el contrato versionado), sin perder estado;
- el worker vuelve a comprobar tenencia, estado, staging íntegro y blockers antes de entrar en `validating`;
- runner usa argv, digest, `--network=none`, filesystem read-only, `cap-drop=ALL`, límites y timeout;
- sanitización de stdout/stderr/reportes y limpieza aun ante excepción.

### Validación oficial — no ejecutada

Solo después de cerrar los bloqueadores:

1. fijar release, hashes y digest reales;
2. archivar el contrato exacto de `parse`, `insert` y `check`;
3. ejecutar cada operación en entorno aislado;
4. conservar exit code, stdout/stderr sanitizados, reporte, hashes y digest;
5. reconciliar conteos y una muestra contra el libro fuente;
6. marcar `validated` exclusivamente si `check` oficial finaliza exitosamente bajo el criterio aprobado.

Al 2026-08-12 `validator_status` debe permanecer `not_run`. El runner implementado no equivale a una corrida ni valida que el JSON de staging sea el formato que el contenedor espera.

## Privacidad, seguridad y resiliencia

- SAST, análisis de dependencias, secretos y configuración;
- CSRF, XSS, SQL injection, mass assignment, IDOR, SSRF, path traversal y command injection;
- redacción de RUN, OTP, tokens, PIE, convivencia y salud en logs/telemetría;
- cifrado en tránsito, en reposo y rotación/restore de claves;
- carga maliciosa, MIME falso, polyglot, zip bomb y antivirus;
- rate limit y circuit breaker del verificador;
- cadena de auditoría íntegra y detección de update/delete directo en BD;
- indisponibilidad de cache, cola, storage, verificador y BD;
- RPO/RTO aprobados y restore de BD + objetos + claves al mismo punto lógico;
- revisión de amenazas STRIDE de [THREAT_MODEL.md](THREAT_MODEL.md).

Antes del 2026-12-01 deben probarse las medidas resultantes de la DPIA y los procedimientos asociados a la Ley 21.719.

## Rendimiento y capacidad

Probar con el máximo institucional esperado y margen acordado:

- apertura de jornada y lista de curso;
- guardado concurrente de asistencia;
- cierre diario/mensual;
- reporte anual y auditoría extensa;
- proyección y validación EDE en cola aislada;
- backup/restore y verificación de hashes.

Registrar p50/p95/p99, errores, consumo de BD/memoria/storage, tamaño de cola y tiempo de recuperación. Los límites se fijan con el sostenedor; no se inventan SLO normativos.

## UAT y gates de liberación

Participan dirección, UTP, docentes, inspectoría, secretaría, PIE/convivencia según alcance, TI, privacidad, seguridad y asesoría jurídica.

Una capacidad solo puede activarse si:

- no tiene ítems críticos o bloqueadores abiertos en [OPEN_COMPLIANCE_ITEMS.md](OPEN_COMPLIANCE_ITEMS.md);
- API, permiso, tenencia, estado, auditoría y pruebas negativas están cubiertos;
- migración y restore se ensayaron sin pérdida;
- fuentes y perfiles aplicables están aprobados;
- no hay PII en logs ni storage público;
- monitoreo, alertas, soporte e incidente están operativos;
- existe acta con commit, configuración/flags, evidencia y responsables.

Para EDE se agrega corrida oficial exitosa; para firma, integración real aprobada; para parvularia, fuente íntegra y OA/OAT; para fiscalización, cuatro ojos y descarga privada auditada.

## Registro de resultados

Cada ejecución debe producir:

| Campo | Requerido |
|---|---|
| Commit/release y fecha | Sí |
| Ambiente, motor/versión y configuración sin secretos | Sí |
| Alcance y casos ejecutados | Sí |
| Resultado, duración y evidencia | Sí |
| Defectos vinculados y severidad | Sí |
| Riesgo residual y decisión | Sí |
| Aprobadores | Sí |

No incluir RUN, OTP, nombres reales, documentos PIE/convivencia ni payloads íntegros en adjuntos del acta.
