# Runbook de exportación EDE

## Estado operativo

> **NO EJECUTABLE EN PRODUCCIÓN.** Al 2026-08-12 existen importador de artefactos locales, proyector declarativo, servicio/job de solicitud y staging cifrado, API y runner/job asíncrono de `parse`/`insert`/`check`. Permanecen bloqueados: no se han importado los artefactos oficiales requeridos; `LCD_EDE_ENABLED=false`; versión, mappings confiables, digest y argv no están fijados; el formato de entrada no se ha contrastado y el validador oficial no ha sido ejecutado. La presencia de estas clases no autoriza una exportación manual ni un estado `validated`.

Una exportación operacional JSON/CSV/PDF no es un paquete EDE. Ningún archivo puede etiquetarse `validated`, liberarse a fiscalización o presentarse como certificado sin completar este runbook.

## Responsabilidades

| Rol | Responsabilidad |
|---|---|
| Operador EDE | Ejecuta importación/exportación/validación sin alterar datos fuente. |
| Responsable funcional | Confirma escuela, año, alcance, cierres y excepciones. |
| Cumplimiento | Aprueba fuentes, versión, mapeos y cierre de blockers. |
| Seguridad/infraestructura | Fija digest, sandbox, storage, secretos y logs. |
| Auditor | Verifica manifiesto, hashes, cadena y segregación. |
| Director/autorizado | Decide liberación; distinto del único generador cuando sea posible. |

## Gates previos

Todos deben estar en verde:

- [ ] `lcd_enabled` habilitado solo para el scope piloto aprobado.
- [ ] `lcd_ede_export_enabled` aún apagado durante preparación.
- [ ] API, RBAC y auditoría probados; operador tiene permiso específico.
- [ ] Escuela/RBD, año y perfil vigentes y aprobados.
- [ ] Fuente EDE oficial almacenada en privado con URL, bytes, SHA-256 y fecha.
- [ ] `lcd_ede_versions` tiene `source_hash` y `schema_hash` reales.
- [ ] Diccionario, referencias y mapeos de la misma versión importados.
- [ ] Contradicción matrícula 43/55 resuelta con fuente oficial.
- [ ] Fuente OA oficial importada o se ha demostrado que no aplica al alcance.
- [ ] Imagen `edemineduc/etl` fijada por digest inmutable y escaneada.
- [ ] README del mismo commit/digest archivado; interfaz `parse/insert/check` revisada.
- [ ] Clave pública oficial descargada, hashada y verificada.
- [ ] Worker aislado, storage temporal cifrado y capacidad/timeout configurados.
- [ ] Backup/restauración reciente verificado.
- [ ] No hay enmiendas, cierres, jobs o fiscalización concurrentes incompatibles.
- [ ] Preflight normativo y de datos termina sin `COMPLIANCE_BLOCKER`.

Si falla un gate, registrar el blocker, no crear un paquete liberable y mantener el flag apagado.

## Operaciones oficiales observadas

La documentación oficial consultada publica la imagen `edemineduc/etl` y las operaciones:

- `parse json <archivo-json-zip>`: transforma el JSON según el contrato documentado;
- `insert`: inserta/procesa hacia la base cifrada según el README fijado;
- `check <frase-secreta> <archivo-db>`: genera/verifica el reporte.

No se documenta aquí una línea completa `docker run`, porque el tag/digest y la invocación exacta deben tomarse del README fijado y todavía no lo están. No concatenar strings provenientes del usuario. El `EdeValidatorRunner` implementado acepta una operación allowlist y argumentos configurados como arrays; su uso sigue bloqueado hasta archivar/probar ese contrato.

## Flujo controlado y cobertura actual

### 1. Crear solicitud

**Cobertura:** `EdeExportService::request` implementa gates parciales, deduplicación, estado `requested`, auditoría y dispatch. Existe endpoint con permiso dedicado/idempotencia, pero faltan cobertura exhaustiva de RBAC, carreras y metadatos operacionales completos.

Registrar en `lcd_ede_exports`:

- escuela, año, libro opcional, perfil y versión EDE;
- alcance exacto y filtros en `scope_snapshot`;
- solicitante, timestamp UTC y `deduplication_key`;
- estado inicial canónico `requested`;
- correlation ID, versión de aplicación y commit.

La solicitud no contiene secretos ni PII innecesaria. Un reintento con la misma deduplication key retorna la ejecución existente.

### 2. Preflight

**Cobertura:** `assertReady` exige flags, digest presente, contrato de comandos, versión activa con hashes, mappings activos y blockers configurados. No ejecuta todavía todas las comprobaciones funcionales siguientes ni guarda un resultado detallado por control.

Cambiar de `requested` a `preflight_running` y comprobar:

1. perfil normativo vigente y fuentes verificadas;
2. RBD/año/curso y decisión de medio digital;
3. transferencia papel→digital completada cuando corresponda;
4. nóminas/snapshots íntegros;
5. sesiones, asistencia, firmas, cierres y evaluaciones completas dentro del alcance;
6. enmiendas/reaperturas pendientes;
7. referencias EDE, OA y códigos desconocidos;
8. digest/clave/storage/worker/validador;
9. cadena de auditoría válida;
10. legal hold o fiscalización activa.

Guardar cada resultado, no solo un booleano. Un error produce `preflight_failed`; no se omite por flag ni aprobación verbal.

### 3. Tomar snapshot consistente

**Cobertura:** pendiente crítica. El proyector actual ejecuta múltiples consultas y luego calcula `records_hash`; ese hash detecta el candidato producido, pero no garantiza un único punto lógico si hubo escrituras concurrentes.

- Abrir transacción/snapshot de lectura consistente apropiado para el motor.
- Seleccionar revisiones exactas firmadas/cerradas.
- Registrar IDs/revisiones y `source_snapshot_hash` canónico.
- Cerrar la transacción antes de operaciones Docker largas.
- Si cambia un registro incluido antes de liberar, marcar la exportación `stale`.

No mantener locks de tablas durante la ejecución del contenedor.

### 4. Proyectar

**Cobertura:** parcial. `EdeProjectionService` aplica mappings activos, required/default y una allowlist de transformaciones. No ejecuta aún un schema/reference validator completo, y su mapping oficial no ha sido importado/aprobado.

`EdeProjectionService` debe terminar de:

- aplicar únicamente mappings activos de la versión fijada;
- validar tipo, longitud, required, cardinalidad y referencias;
- producir conteos por entidad y un índice local de errores sanitizado;
- impedir defaults no autorizados y códigos aproximados;
- conservar la trazabilidad local en un sidecar privado, no filtrarla sin necesidad;
- emitir JSON canónico reproducible en un directorio temporal exclusivo.

Errores críticos vuelven a `preflight_failed`; warnings quedan en manifiesto.

### 5. Empaquetar y ejecutar contenedor

**Cobertura:** el job genera `projection.json.enc`, manifiesto/hashes y estado `generated/not_run`. `EdeValidatorRunner` descifra a un directorio efímero, usa `image@digest`, `--network=none`, filesystem read-only, `cap-drop=ALL`, límites, timeout y cleanup. No se ha ejecutado ni probado contra el contrato oficial; el secreto que pudiera exigir `check` no tiene diseño final aprobado.

El runner debe:

- referenciar `image@sha256:<digest>`; nunca `latest` ni imagen sin digest;
- usar usuario no root, filesystem read-only salvo mounts temporales y sin red salvo necesidad formal;
- aplicar CPU, memoria, PIDs, tamaño y timeout;
- montar solo input/output de esa ejecución;
- permitir solo `parse`, `insert`, `check` y argumentos generados por el sistema;
- pasar secretos por mecanismo efímero que no aparezca en argv/log cuando la herramienta lo permita;
- capturar exit code y stdout/stderr con redacción;
- calcular SHA-256 de cada output antes de moverlo a storage privado;
- limpiar temporales de forma segura al terminar.

Nunca mostrar la frase secreta, RUN, OTP, rutas privadas o payloads en logs.

### 6. Validar

**Cobertura:** parcial no ejecutada. API/comando cambian atómicamente a `validation_queued` y encolan `ValidateLibroDigitalEdeExport`; no ejecutan Docker dentro de la petición. El worker vuelve a comprobar estado/blockers y el runner crea `lcd_ede_validation_runs`, cifra el reporte y actualiza estado según exit code/operación. Todavía debe demostrarse que la normalización en `lcd_ede_validation_results` y un exit code exitoso equivalen a cero errores críticos para el release fijado.

Crear `lcd_ede_validation_runs` con:

- nombre/versión/digest del validador;
- inicio/fin, exit code y operador/job;
- salidas sanitizadas;
- ruta privada/hash del reporte;
- resultados normalizados en `lcd_ede_validation_results`.

La ejecución secuencial exacta de `parse`, `insert` y `check` debe seguir el README archivado de la versión. Un timeout, exit code no satisfactorio, reporte ilegible, hash distinto o error crítico produce `validation_failed`.

### 7. Construir manifiesto

**Cobertura:** el manifest actual contiene versión/hashes EDE, libro/revisión/lock, hashes de mapping, conteos, fecha, `records_hash` y `manifest_hash`. El manifiesto fiscal completo descrito a continuación aún no está implementado.

Campos mínimos:

```text
package_id, school_id, RBD, academic_year, regulatory_profile,
ede_version, export_scope, generated_at, generated_by,
source_snapshot_hash, entity/record counts,
files{name,size,sha256}, validator{name,version,image,digest,executed_at,result},
application_version, git_commit, environment_id, warnings, compliance_blockers
```

No incluir secretos, rutas físicas ni datos personales. Canonicalizar y calcular `manifest_hash` después de finalizarlo.

### 8. Decidir resultado

| Resultado | Estado |
|---|---|
| Preflight o proyección fallida | `preflight_failed` |
| Archivos creados, validador aún no ejecutado | `generated`, `validator_status=not_run` |
| Solicitud aceptada y job pendiente | `validation_queued`, `validator_status=not_run` |
| Corrida en progreso | `validating` |
| Error/timeout/hallazgo crítico | `validation_failed` |
| Corrida oficial exitosa, hashes y evidencia completos | `validated` |
| Datos fuente cambiaron | `stale` |
| Invalidación administrativa | `revoked` |

`released` requiere un caso de uso separado de cuatro ojos descrito en [FISCALIZATION_RUNBOOK.md](FISCALIZATION_RUNBOOK.md).

## Verificación posterior

- [ ] Recalcular SHA-256 desde storage y comparar archivos/manifiesto/reporte.
- [ ] Confirmar digest efectivo del contenedor, no solo el configurado.
- [ ] Verificar cadena de auditoría del scope.
- [ ] Comparar conteos EDE con el snapshot fuente.
- [ ] Revisar warnings uno por uno y documentar aceptación.
- [ ] Confirmar que no haya OTP/RUN completo/secreto en logs o artefactos auxiliares.
- [ ] Probar que una nueva enmienda marca el paquete `stale`.
- [ ] Ejecutar descarga como autorizado y rechazar no autorizado/URL expirada.

## Fallas y reintentos

| Falla | Acción segura |
|---|---|
| Worker/host cae | Retomar desde un checkpoint idempotente; no reutilizar outputs sin verificar hashes. |
| Contenedor timeout | Terminar proceso, preservar log sanitizado, limpiar temporales y marcar fallo. |
| Digest cambió | Detener; crear/importar otra versión y repetir todas las pruebas. |
| Datos cambiaron | Marcar `stale`; generar solicitud nueva desde snapshot nuevo. |
| Error de referencia | Corregir fuente/mapping mediante versión; no editar output manual. |
| `check` falla | Conservar reporte, bloquear release y volver a proyectar tras corregir. |
| Storage insuficiente | Detener antes de generar parcialmente; no liberar artefactos incompletos. |

No hay retry automático de operaciones no demostradas como idempotentes. Nunca editar la SQLite/CSV resultante para “hacerla pasar”.

## Evidencia de ejecución

Conservar en privado:

- solicitud, preflight y aprobaciones;
- fuentes/mappings/clave con hashes;
- snapshot/manifest y todos los SHA-256;
- digest/commit/README del validador;
- exit codes y logs sanitizados;
- reporte oficial y resultados;
- eventos de auditoría y downloads;
- incidentes, warnings y aceptación.

Retener según el perfil aplicable y legal hold; no eliminar por rollback ni por expiración de enlace.
