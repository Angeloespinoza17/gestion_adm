# Superadmin: revisión central de bitácoras

## Objetivo

La herramienta `Superadmin > Revisión de bitácoras` entrega una consulta institucional única, paginada y de sólo lectura sobre las bitácoras operativas existentes. No replica ni modifica registros: cada dato permanece en la tabla y flujo de su módulo de origen.

## Fuentes integradas

- Inspectoría: `inspectoria_daily_logs`.
- Portería: `porter_daily_log_entries`.
- Enfermería: `infirmary_daily_logs`.
- Convivencia Escolar: `convivencia_daily_logs`, excluyendo registros archivados mediante soft delete.

La API normaliza fecha, título, detalle, categoría, prioridad, estado, responsable, estudiante y curso. La ficha de detalle incorpora además los campos particulares disponibles en cada fuente, como seguimiento, acción realizada, turno, atrasos, lugar, caso o derivación.

## Acceso y privacidad

- Ruta frontend: `/superadmin/bitacoras`.
- API: `GET /api/superadmin/logbooks`.
- Ambas superficies son exclusivas de usuarios activos con rol `super_admin`.
- La API está protegida por los middleware de autenticación y `superadmin`.
- La interfaz no expone acciones de creación, edición ni eliminación.
- Los registros de Enfermería se identifican siempre como información sensible; Convivencia conserva su indicador propio `is_sensitive`.

## Consulta y rendimiento

El servicio `SuperAdminLogbookReviewService` aplica los filtros dentro de cada consulta de origen antes de ejecutar el `UNION ALL`. Esto permite utilizar los índices existentes de fecha, prioridad y estado, evita cargar colecciones completas en PHP y realiza la paginación global en la base de datos.

Filtros disponibles:

- búsqueda por contenido, estudiante, RUT, curso o responsable;
- área de origen;
- fecha desde/hasta;
- prioridad;
- estado.

El resumen y los contadores por fuente se calculan en una sola consulta agregada. Sólo los registros de la página actual se hidratan con relaciones específicas de cada módulo.

## Instalación

La migración `2026_08_28_120000_register_superadmin_logbook_review_module.php` es aditiva e idempotente. Sólo agrega el padre `superadmin`, su primera herramienta y las asignaciones RBAC correspondientes mediante `insertOrIgnore`; no altera datos operativos y su `down()` no elimina registros.

Para un despliegue a producción se debe crear y verificar el respaldo exigido antes de ejecutar migraciones. No se debe ejecutar ningún comando destructivo de base de datos.
