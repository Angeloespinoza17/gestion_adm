# Despliegue

## Política

El Libro Digital se despliega **apagado**. Instalar código y esquema no autoriza el uso oficial. La activación es progresiva por establecimiento/curso/capacidad y requiere evidencia de preflight, autorización, seguridad, privacidad y cumplimiento.

Valores iniciales obligatorios:

```dotenv
LCD_ENABLED=false
LCD_IDENTITY_VERIFIER=disabled
LCD_IDENTITY_TRANSACTIONAL_URL=
LCD_IDENTITY_BULK_URL=
LCD_IDENTITY_MAX_TIMESTAMP_SKEW=
LCD_EDE_ENABLED=false
LCD_EDE_VERSION=
LCD_EDE_VALIDATOR_DIGEST=
LCD_EDE_COMMAND_CONTRACT_VERIFIED=false
LCD_EDE_PARSE_COMMAND=[]
LCD_EDE_INSERT_COMMAND=[]
LCD_EDE_CHECK_COMMAND=[]
LCD_SIGE_DRIVER=disabled
```

No almacenar secretos reales en `.env` versionado, base de datos, logs o esta documentación. Utilizar el gestor de secretos aprobado.

## Precondiciones

- [ ] release/commit identificado y artefactos de build reproducibles;
- [ ] backup consistente de BD y archivos privados; restauración reciente probada;
- [ ] las once migraciones LCD `000001`–`000011` revisadas como aditivas; 69 tablas `lcd_*` esperadas en el esquema nuevo;
- [ ] plan [ROLLBACK.md](ROLLBACK.md) aprobado y versión anterior disponible;
- [ ] compatibilidad de código anterior/nuevo con el esquema expandido;
- [ ] almacenamiento privado, cola, scheduler y monitoreo configurados; cinco jobs LCD `ShouldBeUnique` deben ejecutarse en workers supervisados;
- [ ] capacidad de carga verificada en el runtime web: MySQL `max_allowed_packet >= 64 MiB`, PHP `upload_max_filesize >= 20 MiB` y `post_max_size >= 64 MiB`; el proxy/web server debe aceptar al menos el mismo cuerpo total;
- [ ] claves/cifrado y rotación definidos;
- [ ] permisos y seeder revisados en staging con datos sintéticos;
- [ ] `OPEN_COMPLIANCE_ITEMS` revisado;
- [ ] ventana, responsables y criterio de abortar definidos.

## Orden de despliegue

### Evidencia local observada al corte

En el entorno local compartido, al **2026-08-14**, se verificó:

- build frontend final con exit code `0`;
- suite focalizada literal LCD de cierre: 70 pruebas, 553 assertions, 5,69 s, exit code `0`; solo warning conocido del schema XML de PHPUnit;
- `000001`–`000011` en estado `Ran`, incluidas identidad scoped y trazabilidad N:M curricular;
- RBAC/navegación local reconciliados con 35 permisos y 11 módulos LCD;
- un lote curricular local `activated`: 6.337 objetivos (5.394 activos / 943 inactivos), 6 fuentes, 12.674 relaciones objetivo–fuente y 4 vínculos; 129 asignaturas compartidas, con `MAT` y `PM` como únicas activas;
- health técnico `healthy`;
- contexto institucional vinculado al RBD 6830; preflight local `ready=true`, `core_ready=true`, `module_enabled=true`, con capacidades identidad/EDE/parvularia/SIGE/fiscalización apagadas y no requeridas;
- cadena de auditoría íntegra sobre 6 eventos. El lote fue solicitado por sistema, pero la misma cuenta `super_admin` aprobó y activó: la implementación lo permite porque solo separa solicitante de aprobador/activador, aunque no cumple la segregación recomendada de tres personas;
- MySQL local `max_allowed_packet=64 MiB`. El PHP CLI observado conserva `upload_max_filesize=2 MiB` y `post_max_size=8 MiB`; no acredita ni satisface los mínimos web/FPM de 20/64 MiB y debe corregirse/verificarse antes de usar uploads HTTP.

Esta es evidencia de una instalación local técnica, no un acta de staging/producción ni UAT curricular. El preflight local verde comprueba el grafo y los controles implementados; no certifica autenticidad, vigencia o completitud normativa, restore, carga, pentest ni capacidad del runtime web. La corrida global histórica tampoco se declara verde. No reescribir la excepción de actores: una operación productiva futura debe usar aprobador y activador distintos o conservar una excepción formal aprobada.

### 1. Preparar aplicación

1. Verificar dependencias, configuración y permisos del directorio privado.
2. Construir frontend y ejecutar suite en CI/staging.
3. Confirmar que las 94 rutas LCD registradas estén protegidas por autenticación, permiso y middleware `lcd.enabled`; revisar además los tres permisos curriculares nuevos y el permiso específico de cada mutación/importación/descarga.
4. Mantener flags globales y escolares apagados.
5. Pausar solo jobs LCD incompatibles; no detener procesos institucionales no relacionados.

### 2. Respaldar

Seguir [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md). Registrar:

- timestamp UTC, motor/versión y posición de recuperación;
- tamaño/hashes/ubicación cifrada;
- cobertura de BD, storage privado y configuración;
- responsable y prueba de lectura.

No continuar si el backup no es recuperable o la última restauración falló.

### 3. Migrar esquema

En la raíz del proyecto:

```bash
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
```

Controles:

- ejecutar una sola instancia de migración;
- capturar salida/exit code sin secretos;
- confirmar las once migraciones `2026_08_13_000001`–`000011` como aplicadas y el total esperado de 69 tablas `lcd_*`;
- comprobar creación/índices/FK de `lcd_*`, incluidas `lcd_early_withdrawals`, `lcd_late_arrival_periods`, `lcd_late_arrivals`, gobierno de importación y `lcd_curriculum_sources`/`lcd_learning_objective_sources`;
- comparar conteos antes/después; `000007` agrega estructuras, `000008` gobierno, `000009` track, `000010` hace backfill de identidad scoped y `000011` agrega trazabilidad N:M, sin eliminar historia;
- verificar que ningún `down()`/deploy borró registros;
- no ejecutar `migrate:fresh`, `db:wipe`, `migrate:reset` ni rollback amplio en producción.

### 4. Ejecutar seeder idempotente

Cuando `LibroDigitalSeeder` esté revisado/aprobado:

```bash
php artisan db:seed --class=LibroDigitalSeeder --force
```

Debe mantener flags apagados, fuentes sin hash como bloqueadas y catálogos sin valores inventados. Ejecutarlo dos veces en staging y comparar conteos/relaciones. **Revisar antes** el backfill de `lcd_school_users`: no debe conceder acceso escolar a todos los usuarios por una presunción no aprobada.

### 5. Validar infraestructura

- importación curricular: comprobar en el MySQL de destino `max_allowed_packet >= 64 MiB`; un valor menor puede cortar la persistencia del payload/manifiesto cifrado aunque PHP haya aceptado la solicitud;
- runtime PHP web/FPM: comprobar `upload_max_filesize >= 20 MiB` y `post_max_size >= 64 MiB`. La configuración de CLI no acredita la de FPM/Apache; verificar ambos SAPI y reiniciar workers/procesos después de modificarla;
- proxy/web server/WAF: permitir como mínimo 64 MiB por solicitud y ajustar timeout/buffering. Si una carga legítima agrupa evidencias cuyo total supera 64 MiB, elevar coordinadamente `post_max_size` y el límite del proxy sin cambiar el máximo aplicativo de 20 MiB por archivo;
- storage privado: escritura/lectura/borrado de objeto de prueba no sensible;
- workers: colas separadas, timeout/backoff, failed jobs y supervisión;
- scheduler: una sola ejecución por tarea e idempotencia; probar `schedule:list`, `schedule:run` y el lock compartido de `onOneServer`;
- logs: redacción de OTP/RUN/PIE/convivencia/tokens;
- métricas: errores, latencia, cola, storage, auditoría;
- correo/URLs: descargas autenticadas y expiración;
- clock/NTP y zona `America/Santiago`.

### 6. Preflight de aplicación

`CompliancePreflightService` y el command no destructivo existen. Ejecutar por ID interno de escuela y archivar JSON:

```bash
php artisan lcd:preflight --school=<ID_INTERNO> --json
php artisan lcd:health --json
php artisan lcd:integrity:check --school=<ID_INTERNO> --json
php artisan lcd:attendance:check-integrity --school=<ID_INTERNO> --json
php artisan lcd:audit:verify --school=<ID_INTERNO> --json
php artisan lcd:retention:report --school=<ID_INTERNO> --json
```

`lcd:retention:report` es solo informativo: no borra, anonimiza ni modifica. Ninguno de estos comandos repara automáticamente. Sustituir `<ID_INTERNO>` por un ID resuelto y revisado; no copiar literalmente el placeholder.

Archivar resultado por escuela. El preflight actual separa `core_ready` y readiness de identidad/EDE según sus flags; EDE/identidad apagados se informan como `skipped`, no bloquean por configuración externa. Los blockers core abiertos sí impiden la apertura. `ready=false` impide activación; no editar la salida para aprobar.

### Inventario operativo implementado

Existen 15 comandos LCD. Su presencia no implica que hayan sido ejecutados ni aprobados en producción:

| Comandos | Semántica segura |
|---|---|
| `lcd:preflight`, `lcd:health`, `lcd:integrity:check`, `lcd:attendance:check-integrity`, `lcd:audit:verify`, `lcd:retention:report` | Solo inspección/reporte; no reparan ni eliminan. |
| `lcd:detect-missing-sessions`, `lcd:detect-missing-signatures` | Detectan omisiones; no crean sesiones, no firman y no cierran. |
| `lcd:open-books` | Previsualiza por defecto; solo abre candidatos que pasan preflight con `--execute`, actor y referencia de aprobación. |
| `lcd:attendance:reconcile` | Compara evidencia por defecto. Con `--execute` registra una conciliación manual aprobada; no modifica asistencia ni declara envío oficial. |
| `lcd:ede:import-standard` | Importa únicamente archivos locales suministrados y calcula sus huellas reales; no descarga, inventa ni activa sin aprobación explícita. No se han importado aún las fuentes oficiales requeridas. |
| `lcd:ede:preflight` | Inspección EDE; no genera archivos. |
| `lcd:ede:export` | Lista candidatos por defecto. `--execute` crea/encola una proyección candidata; no valida ni libera. |
| `lcd:ede:validate` | Previsualiza por defecto. `--execute` **encola** validación asíncrona; no ejecuta Docker en el proceso web/CLI solicitante y no equivale a una corrida exitosa. |
| `lcd:retention:run` | El dry-run solo genera un plan. `--execute` está deliberadamente bloqueado/fail-closed: no borra ni anonimiza registros. |

Los jobs `GenerateLibroDigitalReport`, `GenerateLibroDigitalEdeExport`, `ValidateLibroDigitalEdeExport`, `MonitorLibroDigitalServices` y `RunLibroDigitalIntegrityCheck` implementan `ShouldBeUnique`. Debe probarse el backend de locks compartido y la recuperación de jobs fallidos; la unicidad de clase no sustituye idempotencia de negocio.

El scheduler codificado agenda:

| Tarea | Frecuencia codificada | Estado operativo al corte |
|---|---|---|
| `MonitorLibroDigitalServices` | Cada cinco minutos | Sin evidencia de ejecución/alerta en host productivo. |
| `RunLibroDigitalIntegrityCheck` | Diario 02:40 | Incluye cadena de auditoría e integridad LCD; sin evidencia productiva ni circuito de alertamiento aprobado. |
| `lcd:detect-missing-sessions` | Días hábiles 18:15 | Detección no mutante; sin evidencia productiva. |
| `lcd:detect-missing-signatures` | Días hábiles 19:00 | Detección no mutante; sin evidencia productiva. |

Health e integridad persisten un resumen operacional en `lcd_settings`; verificar en staging que no incluya PII y que el monitoreo lea/alerte por resultados fallidos. No activar EDE porque exista el job: siguen faltando versión/digest/contrato oficial, fuentes importadas y una validación real exitosa.

### Límite del endpoint de importación EDE

`POST /api/libro-digital/v1/ede/import-standard` está implementado para un operador con `libro_digital.ede.manage`, contexto escolar, módulo habilitado e idempotencia. La petición:

- exige uploads explícitos de fuente, esquema y mappings declarativos JSON;
- valida extensión/MIME/tamaño, calcula hashes sobre los bytes recibidos y archiva fuente, esquema, mappings y checksums cifrados en storage privado;
- prohíbe `activate` y `approval_reference` y deja la versión solamente en `imported` para revisión separada;
- no descarga desde la URL declarada, no autentica por sí sola la autoridad/vigencia y no ejecuta el validador.

No invocar este endpoint en producción mientras el módulo permanezca apagado ni cargar fixtures como si fueran estándar oficial. Probarlo únicamente en ambiente efímero con archivos sintéticos; después verificar que no exista versión `active`, que no se exponga `archive_path` y que el storage no contenga texto plano. Una importación oficial futura requiere artefactos obtenidos por el canal aprobado, hashes reconciliados, revisión humana y activación separada; hoy ese gate sigue abierto.

### 7. Smoke tests con módulo apagado

- rutas sin permiso/flag no exponen datos;
- navegación general del ERP no se rompe;
- migraciones previas y tareas existentes funcionan;
- seeder no cambió roles ajenos;
- backups/jobs/logs funcionan;
- API retorna errores estructurados/correlation ID sin PII;
- currículo sin catálogo oficial activo/vinculado/hashado retorna `COMPLIANCE_BLOCKER_CURRICULUM_NOT_IMPORTED` y nunca porcentaje certificado;
- rutas de importación EDE, retiro, cierre, enmienda y auditoría rechazan permiso/scope/idempotencia ausentes incluso con el módulo encendido en test.

## Activación progresiva

### Fase A: equipo técnico/administrativo

- solo después de probar preflight y scope de flags, habilitar `lcd_enabled` para una escuela y grupo de prueba interno;
- usar año/curso de piloto aprobado y datos controlados;
- verificar aislamiento, auditoría, reportes con watermark y concurrencia;
- mantener firma, EDE, SIGE, parvularia y fiscalización apagados.

### Fase B: operación piloto

- habilitar docentes asignados de un curso;
- monitorear sesiones/asistencia/leccionario/enmiendas;
- reconciliar con el medio oficial vigente;
- no abandonar papel si la decisión formal de medio digital y transferencia no está aprobada;
- revisar diariamente errores, integridad y soporte.

### Fase C: capacidades reguladas

Activar por separado:

| Capacidad | Gate adicional |
|---|---|
| Firma | endpoint/parametros corregidos y probados, credenciales, verifier enrolado, replay/rate limit, incident plan. |
| Parvularia | REX 700 íntegra, perfil/retención/asistencia aprobados y OA importados. |
| EDE | versión/mappings/hashes/digest, runner sandbox, corrida oficial y evidencia. |
| SIGE | gateway archivo/manual aprobado o API oficial documentada; nunca scraping. |
| Fiscalización | exportación validada, cuatro ojos, URL privada y runbook ensayado. |

### Fase D: expansión

Expandir escuela/curso solo después de una ventana estable, pruebas de restore, métricas y firma de aceptación. Cada escuela mantiene su scope y perfil; no copiar flags/permisos ciegamente.

## Smoke tests posteriores

- autenticación, 403/404 e IDOR entre escuelas;
- crear libro borrador y confirmar no-delete/desactivación;
- snapshot de nómina y sesión sintética;
- asistencia completa/incompleta/atraso/concurrencia;
- leccionario y firma bloqueada si verifier disabled;
- cierre diario/mensual sella una nueva revisión; duplicado falla, y conciliación manual declara `official=false`/`official_submission_performed=false`;
- enmienda sintética exige solicitante, revisor y aplicador distintos, conserva revisión previa y reabre/reversiona derivados sin sobrescribirlos;
- retiro anticipado y retorno sintéticos conservan snapshots cifrados/hashados, lock/revisión y no cambian/eliminan matrícula;
- atraso parvulario registra hora/hecho, pero no devuelve una clasificación regulatoria inventada y permanece detrás de flag/perfil;
- reporte PDF/XLSX/CSV/JSON privado con hash y watermark;
- auditoría encadenada, verificación y vista por entidad allowlisted/redactada;
- enmienda marca exportación previa stale;
- workers idempotentes y job fallido visible; una solicitud EDE debe quedar `validation_queued` y retornar sin ejecutar el contenedor en la petición;
- logs/telemetría sin PII.

No usar datos personales reales para smoke tests técnicos.

## Monitoreo y abort criteria

Abortar activación, apagar flags y seguir rollback si ocurre:

- acceso entre escuelas o permisos incorrectos;
- pérdida/sobrescritura de historia;
- auditoría rota o ausente;
- OTP/RUN/PIE/convivencia en logs;
- firmas sin verificador válido o payload inconsistente;
- exportación marcada validada sin reporte/digest;
- errores de migración, corrupción, cola descontrolada o storage expuesto;
- restauración no viable.

## Evidencia del despliegue

Conservar release/commit, aprobaciones, backup, salida de migración/seeder/preflight, flags por scope, resultados de tests, métricas, incidentes y decisión de avanzar/revertir. No incluir secretos ni PII en el acta.
