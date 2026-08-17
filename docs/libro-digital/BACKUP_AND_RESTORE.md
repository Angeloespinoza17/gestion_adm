# Backup y restauración

## Estado

La capacidad de restauración del LCD no está evidenciada todavía. Antes de activar producción debe existir una restauración completa de ensayo, con resultados, tiempos, hashes y responsables registrados.

## Objetivos

- conservar BD, archivos privados, manifests, fuentes normativas y configuración como un conjunto coherente;
- cifrar backups y separar claves/accesos;
- permitir recuperación point-in-time cuando la plataforma lo soporte;
- demostrar recuperación periódicamente, no solo creación de copias;
- no convertir backups en un canal paralelo de acceso masivo a datos de NNA.

RPO/RTO deben ser aprobados por continuidad institucional. Como propuesta para evaluación, no compromiso vigente: RPO ≤ 15 minutos para BD durante operación y RTO ≤ 4 horas para servicio crítico. La capacidad real debe medirse y documentarse.

## Alcance mínimo

| Activo | Incluir |
|---|---|
| Base de datos | Todas las tablas ERP relacionadas y las 64 `lcd_*`; migrations, RBAC, años, usuarios/staff, estudiantes/matrículas, cursos/horarios, portería y módulos enlazados. |
| Storage privado | Adjuntos, fuentes normativas, reportes, staging EDE cifrado, validaciones y paquetes. |
| Configuración | Config no secreta, feature flags, profiles, mappings y versiones. |
| Secretos/claves | Backup/escrow separado según KMS; no copiar secretos en el mismo archivo sin controles. |
| Aplicación | release/commit, lockfiles, imagen/digest y manifiesto de despliegue. |
| Evidencia | hashes, inventario, logs sanitizados, cadena de auditoría y runbooks. |

No basta respaldar solo tablas `lcd_*`: sus FK y snapshots dependen de entidades ERP.

## Diseño de backup

- snapshots consistentes o backup físico/lógico soportado por el motor;
- PITR/binlog/WAL si está disponible y protegido;
- cifrado fuerte en tránsito/reposo con claves fuera del backup;
- copia inmutable/offline contra ransomware y separación de cuenta;
- redundancia en dominio de falla distinto y residencia aprobada;
- principio 3-2-1 como guía, ajustado por política institucional;
- retención versionada y legal hold;
- checksums por objeto/archivo y manifest del conjunto;
- acceso mínimo, MFA y auditoría de lecturas/restores;
- monitor de éxito, antigüedad, tamaño anómalo y espacio.

## Procedimiento de backup

1. Registrar `backup_id`, entorno, release, motor, tiempo UTC y operador automatizado.
2. Capturar posición de consistencia/PITR.
3. Respaldar BD sin bloquear más de la ventana aprobada.
4. Capturar storage privado de forma consistente con el manifest de BD; evitar archivos que aún estén `processing` o marcarlos como tales.
5. Incluir inventario de rutas lógicas, tamaños y SHA-256.
6. Cifrar antes de transferir fuera del host.
7. Verificar autenticidad/integridad y capacidad de descifrado en un proceso controlado.
8. Replicar a destino secundario/inmutable.
9. Registrar resultado, duración, bytes y alertas sin nombres/PII.
10. No borrar el backup anterior hasta verificar el nuevo y cumplir la política.

## Restauración de ensayo

Frecuencia recomendada para aprobación: mensual durante piloto y al menos trimestral en régimen, además de antes/después de cambios mayores.

### Preparar

- entorno aislado, sin email/webhooks/SIGE/verificador/Internet saliente;
- credenciales y claves de prueba con acceso temporal;
- capacidad suficiente y misma versión compatible de motor;
- fecha/PITR objetivo y activos esperados;
- equipo de privacidad informado por el uso de datos; preferir datos sintéticos o restauración fuertemente restringida.

### Restaurar

1. Verificar manifest/checksums del backup antes de abrirlo.
2. Restaurar BD y aplicar logs hasta el punto definido.
3. Restaurar storage privado manteniendo permisos y cifrado.
4. Desplegar el release compatible; mantener flags LCD y externos apagados.
5. Configurar claves mediante canal seguro; no imprimirlas.
6. Ejecutar migraciones pendientes solo si el objetivo del ensayo lo requiere y son compatibles.
7. Bloquear accesos humanos no autorizados.

### Validar

- conteos por todas las tablas `lcd_*` y entidades ERP dependientes;
- constraints/FK, estados, revisiones y `lock_version`;
- recalcular muestras y cadena completa de `lcd_audit_events`;
- comparar SHA-256 de adjuntos, reportes, exportaciones y manifests;
- abrir/renderizar una muestra de PDF y descifrar un archivo de prueba autorizado;
- validar relación sesión→snapshot→asistencia→firma;
- confirmar que OTP y secretos no existen en tablas/logs;
- verificar reportes/descargas solo con permisos;
- confirmar que flags, queues e integraciones externas siguen apagados;
- medir RPO/RTO observado.

### Destruir entorno de ensayo

Después de la aceptación y plazo de evidencia:

- revocar credenciales/claves temporales;
- destruir de forma aprobada el entorno/restauración, no el backup fuente;
- conservar solo reporte sanitizado, hashes, tiempos, hallazgos y aprobaciones;
- registrar cualquier fallo como blocker.

## Restauración de producción

Solo por incidente autorizado:

1. declarar incidente y detener escrituras/flags/jobs;
2. preservar evidencia del estado corrupto;
3. seleccionar punto objetivo y cuantificar pérdida posterior al RPO;
4. restaurar y validar primero en aislamiento;
5. planificar reconciliación de operaciones posteriores (firmas, asistencia, enmiendas, paquetes);
6. aprobar cambio por responsables técnico, negocio, privacidad y dirección;
7. restaurar/reemplazar con ventana controlada;
8. verificar integridad, accesos, flags e integraciones;
9. reabrir progresivamente;
10. notificar afectados/autoridades cuando corresponda.

No mezclar filas manualmente sin mapping/revisión/auditoría. Una firma cuyo payload no coincide tras restore queda bloqueada para investigación, nunca “recalculada” silenciosamente.

## Gestión de claves

- claves de aplicación, backup y storage separadas cuando la infraestructura lo permita;
- KMS/HSM o gestor institucional, rotación y escrow documentados;
- acceso de restauración bajo cuatro ojos;
- probar restore después de rotación;
- conservar capacidad de descifrar datos históricos mientras deban retenerse;
- si una clave se compromete, activar [INCIDENT_RESPONSE.md](INCIDENT_RESPONSE.md), rotar y evaluar re-cifrado.

## Métricas y alertas

- edad del último backup exitoso;
- cobertura BD/storage y objetos omitidos;
- duración/bytes/tasa de cambio;
- checksum/descifrado fallido;
- replicación inmutable atrasada;
- última restauración exitosa, RPO/RTO observado;
- accesos/restores fuera de ventana.

## Evidencia de aceptación

El reporte de restore debe incluir backup/PITR, versiones, responsables, hashes, conteos, cadena de auditoría, archivos muestreados, RPO/RTO, fallos, datos excluidos y decisión. El gate se cierra solo con restauración exitosa; una captura de “backup completed” no basta.
