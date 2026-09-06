# Superadmin: revisión central de bitácoras

## Objetivo

La herramienta `Superadmin > Revisión de bitácoras` entrega una consulta institucional única, paginada y de sólo lectura sobre las bitácoras operativas existentes. No replica ni modifica registros: cada dato permanece en la tabla y flujo de su módulo de origen.

## Fuentes integradas

- Inspectoría: `inspectoria_daily_logs`.
- Portería: `porter_daily_log_entries`.
- Enfermería: `infirmary_daily_logs`.
- Convivencia Escolar: `convivencia_daily_logs`, excluyendo registros archivados mediante soft delete.
- Funcionarios: `operational_staff_log_entries`.
- Nocheros: `security_rounds`, vinculando el turno, funcionario, sectores revisados y novedades de seguridad.
- Incidencias nocturnas: `security_incidents`, con prioridad, estado, sector, responsable, compromiso de respuesta y resolución.

La API normaliza fecha, título, detalle, categoría, prioridad, estado, responsable, estudiante y curso. La ficha de detalle incorpora además los campos particulares disponibles en cada fuente, como seguimiento, acción realizada, turno, atrasos, lugar, caso o derivación.

Las rondas nocturnas se muestran como registros independientes y trazables. Su ficha incluye número de acta, nochero responsable, horario y cobertura del turno, sectores, observaciones, evidencia disponible y novedades con su prioridad, estado y responsable. Las novedades críticas conservan el valor `critica` y se incluyen en el indicador de alta prioridad.

Cada incidencia nocturna también se muestra como un registro propio para que no quede oculta dentro de la ronda. La ficha consolidada mantiene el vínculo con el acta y el nochero, e informa sector, responsable actual, fecha compromiso, respuesta operativa, notas de cierre, seguimientos y evidencias. Esta integración es de sólo lectura y enlaza a `/security/incidents` para la gestión autorizada en el módulo de origen.

## Acceso y privacidad

- Ruta frontend: `/superadmin/bitacoras`.
- API: `GET /api/superadmin/logbooks`.
- Ambas superficies son exclusivas de usuarios activos con rol `super_admin`.
- La API está protegida por los middleware de autenticación y `superadmin`.
- La interfaz no expone acciones de creación, edición ni eliminación.
- Los registros de Enfermería se identifican siempre como información sensible; Convivencia conserva su indicador propio `is_sensitive`.
- La integración de Nocheros reutiliza `ver_rondas_seguridad` y las reglas vigentes del módulo; no agrega permisos nuevos ni amplía el acceso de otros roles.

## Consulta y rendimiento

El servicio `SuperAdminLogbookReviewService` aplica los filtros dentro de cada consulta de origen antes de ejecutar el `UNION ALL`. Esto permite utilizar los índices existentes de fecha, prioridad y estado, evita cargar colecciones completas en PHP y realiza la paginación global en la base de datos.

Filtros disponibles:

- búsqueda por contenido, estudiante, RUT, curso o responsable;
- área de origen;
- fecha desde/hasta;
- prioridad;
- estado.

El resumen y los contadores por fuente se calculan en una sola consulta agregada. Sólo los registros de la página actual se hidratan con relaciones específicas de cada módulo.

La fuente de Nocheros agrega las novedades por ronda en una subconsulta SQL antes del `UNION ALL`, evitando duplicar rondas y permitiendo filtrar por prioridad sin cargar toda la bitácora en memoria.

La fuente de Incidencias nocturnas consulta directamente `security_incidents` y aplica búsqueda, fecha, prioridad y estado antes de la unión. La bandeja operativa calcula en una sola agregación los totales abiertos, críticos, vencidos y sin responsable.

## Panel de nocheros

`/security/dashboard` presenta una bitácora nocturna reciente junto con turnos activos, rondas del día, rondas que requieren atención, novedades abiertas y tiempo promedio de respuesta. El promedio se calcula en la base de datos y la bitácora carga únicamente las ocho rondas más recientes visibles para el usuario, con conteos de sectores y novedades.

## Instalación

La migración `2026_08_28_120000_register_superadmin_logbook_review_module.php` es aditiva e idempotente. Sólo agrega el padre `superadmin`, su primera herramienta y las asignaciones RBAC correspondientes mediante `insertOrIgnore`; no altera datos operativos y su `down()` no elimina registros.

Para un despliegue a producción se debe crear y verificar el respaldo exigido antes de ejecutar migraciones. No se debe ejecutar ningún comando destructivo de base de datos.
