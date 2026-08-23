# Gestión de ausencia y asistencia escolar

## Objetivo y fuente oficial

El módulo transforma la asistencia ya registrada en señales preventivas, expedientes e intervenciones trazables. No crea una tabla paralela de asistencia. Todos los indicadores se derivan de:

- `attendance_records`: observación oficial de presencia, ausencia, atraso, retiro anticipado y justificación.
- `school_days`: sólo jornadas con `status = confirmed` e `is_school_day = true`.
- `student_enrollments`: sólo registros comprendidos entre la fecha de matrícula y la fecha de retiro, ambas inclusivas.

Un día sin registro de asistencia no se interpreta como ausencia. `lcd_session_attendance` permanece como una fuente de granularidad de Libro Digital separada y no se reconcilia automáticamente con `attendance_records`.

## Arquitectura

La migración `2026_08_21_090000_create_attendance_management_tables.php` es aditiva y crea:

- configuración anual: `attendance_management_settings`;
- resultados derivados: `attendance_risk_snapshots` y `attendance_pattern_detections`;
- catálogo: `attendance_intervention_types`;
- expediente: `attendance_cases`, participantes, causas, historial de estados, notas, contactos familiares y acuerdos;
- seguimiento: `attendance_action_plans` y `attendance_action_plan_actions`;
- extensión de `attendance_interventions` para vincular caso, tipo, resultado resumido y próxima acción.

Las relaciones con estudiantes, años, expedientes y catálogos usan restricciones de borrado. Las referencias operativas a usuarios y cursos admiten `NULL` cuando corresponde para conservar la trazabilidad histórica. Los índices cubren los filtros de año, curso, nivel de riesgo, estado, responsable y vencimientos que alimentan el dashboard.

## Cálculo de riesgo explicable

Los valores predeterminados están en `config/attendance_management.php` y pueden personalizarse por año académico desde la interfaz de configuración.

Niveles por asistencia acumulada:

- verde: 95% o más;
- amarillo: desde 90% y bajo 95%;
- naranja: desde 85% y bajo 90%;
- rojo: bajo 85%;
- crítico: cualquier disparador crítico o puntaje ponderado desde 81.

El puntaje, acotado entre 0 y 100, suma contribuciones por asistencia acumulada, últimos 30 y 15 días lectivos, racha de ausencias, porcentaje injustificado, atrasos y existencia de caso previo. Para cada tasa de asistencia se usa:

`min(1, (meta - tasa) / (meta - 60)) * ponderación`, con contribución cero cuando se cumple la meta.

Los disparadores críticos predeterminados son cinco ausencias lectivas consecutivas, caída reciente de ocho puntos porcentuales o cinco ausencias en la ventana reciente. La respuesta API conserva las métricas, razones textuales, banda del puntaje y disparadores; por ello una decisión puede ser explicada y auditada.

## Patrones detectados

Los detectores son componentes independientes que reciben un conjunto normalizado de observaciones válidas. Actualmente se evalúan:

- ausencias recurrentes de lunes y de viernes;
- racha de ausencias consecutivas;
- descenso reciente o recuperación respecto de una ventana lectiva comparable;
- ausencias intermitentes por semana;
- ausencias previas o posteriores a interrupciones del calendario;
- aumento de atrasos;
- retiros anticipados recurrentes;
- concentración de ausencias sin justificación.

Cada detección incluye severidad, ocurrencias, periodo, métricas y una confianza calculada según la cantidad de observaciones. Los parámetros mínimos son configurables; nunca se afirma un patrón con observaciones insuficientes.

## Flujo operacional

1. El proceso diario calcula un snapshot por estudiante y año sobre registros válidos.
2. Los casos prioritarios aparecen en el dashboard y en la bandeja de gestión pendiente.
3. Un usuario autorizado abre el expediente, asigna responsable, adulto referente y participantes explícitos.
4. El equipo registra causas, notas, contactos familiares, intervenciones y acuerdos de reunión.
5. El plan individual define objetivo, indicador, meta, plazo, acciones y responsables.
6. Cada acción cambia entre pendiente, en curso, completada o cancelada; completar exige un resultado verificable.
7. La evaluación compara el indicador actual con la línea inicial y clasifica mejora significativa, mejora parcial, sin cambio, deterioro o datos insuficientes.
8. Los cambios de estado, acciones críticas y exportaciones quedan en auditoría.

El comando es:

```bash
php artisan attendance:analyze --academic-year-id=ID --date=AAAA-MM-DD
```

Acepta también `--course-section-id`. El scheduler lo ejecuta diariamente a las 06:45 en `America/Santiago`, con bloqueo de solapamiento y ejecución única por clúster.

## Acceso y confidencialidad

| Permiso | Alcance |
|---|---|
| `attendance_management.view` | Dashboard y estudiantes de cursos autorizados. |
| `attendance_management.view_all` | Visión institucional completa. |
| `attendance_management.manage_cases` | Apertura, actualización, cierre, notas y acuerdos. |
| `attendance_management.manage_interventions` | Contactos e intervenciones. |
| `attendance_management.manage_causes` | Causas del expediente y su catálogo. |
| `attendance_management.manage_action_plans` | Planes, acciones y evaluaciones. |
| `attendance_management.export` | Reportes autorizados. |
| `attendance_management.view_sensitive` | Causas y contenido confidencial. |
| `attendance_management.configure` | Umbrales, ponderaciones, alertas y catálogos. |

El alcance ordinario se obtiene de jefatura de curso, asignación de inspectoría o asignación docente de Libro Digital. Ser responsable, adulto referente o participante explícito permite ver ese expediente y estudiante, pero no amplía la visibilidad al resto del curso. Un usuario inactivo no obtiene acceso, incluido un superadministrador inactivo.

Sin `view_sensitive`, la API excluye notas y causas sensibles y redacta observaciones familiares, descripciones/resultados de intervención, antecedentes iniciales del plan, causas identificadas y notas de cierre. El listado de expedientes sólo entrega información operacional resumida y no serializa el contenido interno del plan.

## API

La API autenticada se publica bajo `/api/attendance-management`:

- `GET /dashboard`, `GET /students`, `GET /students/{id}`;
- `GET|POST /cases`, `GET|PATCH /cases/{id}`;
- `POST /cases/{id}/causes`, `/family-contacts`, `/interventions`, `/action-plans`, `/agreements`, `/notes`;
- `POST /action-plans/{id}/evaluate` y `PATCH /action-plan-actions/{id}`;
- `GET /configuration`, `PUT /configuration/settings` y mantenimiento de causas/tipos de intervención.

Los listados usan paginación de servidor. El análisis procesa estudiantes por bloques de 200 y actualiza snapshots/patrones idempotentemente. Dashboard, estadísticas y exportaciones aplican los mismos alcances de curso y usan índices y caché con invalidación tras operaciones relevantes.

## Reportes PDF

La interfaz genera, mediante la cola existente de exportaciones, los reportes:

- gestión institucional;
- gestión por curso;
- ficha individual;
- entrevista familiar, sin puntaje técnico ni causa sensible;
- casos críticos;
- efectividad de intervenciones, con advertencia explícita de que la asociación observada no prueba causalidad.

Cada descarga respeta el permiso de exportación y el alcance de estudiantes/cursos del solicitante. La ficha individual y el formato familiar exigen autorización nominal sobre el estudiante.

## Despliegue seguro

No ejecutar `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` ni seeders destructivos. La reversión automática de esta migración es deliberadamente no destructiva para no eliminar expedientes ni trazabilidad.

Procedimiento recomendado en producción:

1. Activar modo de mantenimiento sólo si la ventana operativa lo exige.
2. Crear y verificar un respaldo completo antes de cualquier cambio, por ejemplo con el comando institucional `php artisan backup:database --no-prune`.
3. Registrar conteos previos de `attendance_records`, `school_days`, `student_enrollments`, `attendance_interventions`, usuarios, roles y permisos.
4. Revisar nuevamente que la migración sea sólo aditiva y que ningún seeder modifique asistencia o expedientes.
5. Ejecutar `php artisan migrate --force`.
6. Ejecutar de forma controlada `php artisan db:seed --class=AttendancePermissionSeeder`; este seeder reconcilia permisos, módulo y catálogos, pero no crea ni elimina registros de asistencia.
7. Limpiar cachés de aplicación según el procedimiento institucional y confirmar que el scheduler esté activo.
8. Ejecutar `php artisan attendance:analyze --academic-year-id=ID` para generar datos derivados.
9. Comparar los conteos originales: las tablas fuente deben permanecer iguales; validar rutas, permisos, dashboard y descarga PDF con un usuario de cada rol.
10. Si se requiere retirar la versión, revertir código y rutas manteniendo las tablas hasta contar con un respaldo y un plan explícito de conservación de datos.

## Verificación automatizada

Los escenarios dedicados cubren validez de jornadas y matrículas, ausencia de falsos ausentes, cálculo y explicabilidad del riesgo, detección de patrones, flujo completo de expediente, notas sensibles, acuerdos, intervenciones, plan/acción/evaluación, auditoría, alcance por curso, redacción confidencial y PDF autorizado. La suite previa de estadísticas también comprueba dashboard, explorador, metas, simulación, exportación y reportes programados.
