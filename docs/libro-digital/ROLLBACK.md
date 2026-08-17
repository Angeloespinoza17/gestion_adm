# Rollback forward-only

## Regla fundamental

Un rollback de aplicación **nunca elimina registros del Libro Digital**. Las once migraciones LCD declaradas tienen `down()` no-op por diseño. En producción no se ejecutan `migrate:rollback`, `migrate:reset`, `migrate:fresh`, `db:wipe`, `DROP`, `TRUNCATE` ni scripts que borren tablas/historia para revertir un release. La base local observada ha aplicado `000001`–`000008`; `000009`–`000011` deben desplegarse y, si requieren corrección, corregirse con otra migración forward-only.

Rollback significa:

1. apagar capacidades;
2. detener productores incompatibles;
3. desplegar código compatible anterior o hotfix;
4. mantener el esquema expandido y todos los datos;
5. corregir mediante una nueva migración aditiva;
6. reconciliar/auditar cualquier operación en vuelo.

## Preparación obligatoria

- releases anterior/nuevo compatibles con columnas/tablas adicionales;
- feature flags capaces de detener módulo, firma, EDE, SIGE, parvularia y descargas;
- backup y punto de recuperación verificados;
- inventario de jobs/colas y cambios en vuelo;
- migraciones expand/contract: primero añadir, después backfill, mucho después dejar de usar; no drop;
- runbook ensayado en staging.

## Niveles de rollback

### Nivel 1: desactivación funcional

Usar ante error lógico o de integración sin corrupción:

- `lcd_enabled=false` para scope afectado;
- apagar flags específicos;
- bloquear nuevas mutaciones y mantener lectura administrativa solo si es segura;
- pausar workers LCD que generen/escriban;
- conservar jobs/records existentes para reconciliación;
- registrar incidente y timestamp.

Desactivar una función no cambia estados ni elimina datos.

### Nivel 2: rollback de código

1. Confirmar que release anterior tolera el esquema actual.
2. Apagar flags y drenar/pausar jobs del release fallido.
3. Desplegar artefacto anterior por el mecanismo normal.
4. Limpiar cachés de código/configuración de forma controlada.
5. No revertir migraciones.
6. Ejecutar smoke tests de ERP, lectura LCD y auditoría.
7. Reconciliar mutations/jobs ocurridos entre releases.

Si la versión anterior no es compatible, crear un hotfix forward-compatible; no forzar rollback destructivo.

### Nivel 3: corrección de esquema forward-only

Ante columna/índice/constraint defectuoso:

- crear una nueva migración que añada la estructura correcta;
- mantener la estructura anterior hasta que ningún release la use;
- backfill por chunks idempotentes, con checkpoints y auditoría técnica;
- no renombrar/drop en la emergencia si rompe el código anterior;
- validar conteos/hashes antes/después.

### Nivel 4: recuperación desde backup

Solo ante corrupción/pérdida confirmada y bajo [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md). Restaurar una base completa puede sobrescribir operaciones posteriores, por lo que requiere:

- declarar incidente mayor;
- aislar escrituras;
- definir point-in-time y datos posteriores;
- restaurar primero en entorno aislado;
- comparar/reconciliar;
- aprobación ejecutiva/técnica/jurídica antes de reemplazar producción.

No usar restore como forma cotidiana de deshacer una feature.

## Casos específicos

| Falla | Respuesta |
|---|---|
| Migración falla antes de completar | Detener deploy; inspeccionar qué objetos sí se crearon; mantenerlos; corregir con migración idempotente nueva. |
| API nueva falla | Apagar `lcd_enabled`, volver código, mantener tablas/datos. |
| Firma defectuosa | Apagar flag/verifier; dejar sesiones pendientes; revocar/analizar firmas afectadas mediante workflow, no borrarlas. |
| Reporte expone datos | Revocar links, apagar reportes, iniciar incidente; conservar evidencia/archivo restringido. |
| Proyección EDE incorrecta | Apagar EDE, marcar exportación `stale`/`revoked`, conservar outputs y generar nueva versión. |
| Validador/digest incorrecto | Revocar paquetes, no editar resultados; repetir con digest aprobado. |
| Seeder otorga permisos excesivos | Apagar módulo, snapshot de RBAC, retirar permisos mediante seeder/migración correctiva auditada; no borrar usuarios/roles. |
| Auditoría inconsistente | Poner módulo read-only/apagado, preservar BD/logs y activar respuesta a incidente. |

## Jobs en vuelo

Antes de volver código:

- identificar jobs LCD por ID no-PII;
- detener consumo de colas específicas;
- permitir finalizar solo jobs idempotentes y compatibles;
- marcar ejecuciones huérfanas como fallidas/stale mediante un reconciliador, no borrar filas;
- verificar que no existan archivos parciales presentados como completos;
- reanudar tras smoke test.

## Validación posterior

- conteos por tablas críticas antes/después;
- hashes de snapshots/exportaciones sin cambios inesperados;
- cadena de auditoría válida;
- estados sin valores huérfanos;
- flags apagados y permisos correctos;
- storage privado y enlaces revocados;
- ninguna migration/down eliminó tablas/filas;
- incidente y reconciliación documentados.

## Prohibiciones permanentes

- editar manualmente una sesión firmada para “volver atrás”;
- borrar exportaciones, intentos de firma, eventos o paquetes fallidos;
- ejecutar SQL destructivo usando variables/globs no resueltos;
- restaurar producción sin ensayo/conciliación;
- ocultar un incidente corrigiendo timestamps/hashes;
- convertir expiración de retención en borrado automático.

## Cierre

El responsable técnico registra causa, scope, releases, flags, jobs, evidencia de integridad, datos reconciliados y acción preventiva. La reapertura del módulo sigue [DEPLOYMENT.md](DEPLOYMENT.md) desde preflight; no basta con “el sitio volvió a responder”.
