# Módulo Orientación - Plan anual

## Alcance

El módulo administra un único Plan de Orientación por año calendario. La restricción existe tanto en la validación HTTP como en el índice único `orientation_plans.year`, de modo que dos solicitudes concurrentes no pueden crear planes duplicados.

La matriz anual toma del documento institucional los siguientes componentes por acción:

- acción y objetivo específico;
- niveles o cursos;
- responsables institucionales y usuarios del sistema;
- medios de verificación planificados;
- recursos materiales;
- fechas de inicio y término, estado y porcentaje de avance.

Cada acción puede vincularse a varios planes complementarios del mismo año (por ejemplo, el Plan de Afectividad, Sexualidad y Género), contener actividades programadas o realizadas y reunir evidencias generales o asociadas a una actividad concreta.

La vista anual presenta una fila por acción en una tabla adaptable. Desde cada fila se puede ver el seguimiento, editar la definición, eliminar la acción con confirmación o abrir directamente el registro de una nueva actividad. La eliminación requiere `orientation.manage_plan`, mantiene los planes transversales indexados y elimina en cascada las actividades, vínculos, evidencias y archivos privados propios de la acción.

### Avance aportado por actividades

Cada actividad registra dos porcentajes: el aporte máximo que representa dentro de la acción y su nivel de cumplimiento. La suma de aportes asignados a las actividades de una acción no puede superar 100% y se valida dentro de una transacción con bloqueo de la acción para evitar sobreasignaciones concurrentes.

El avance se calcula como `aporte × cumplimiento / 100` y se suma entre todas las actividades. Una actividad realizada fuerza 100% de cumplimiento; una programada o cancelada aporta 0%; y una actividad en ejecución permite registrar cumplimiento parcial. El estado de la acción cambia automáticamente a `in_progress` al existir avance y a `completed` al alcanzar 100%, salvo que la acción esté explícitamente postergada o cancelada.

Mientras exista al menos una actividad con aporte mayor que cero, el porcentaje manual de la acción queda protegido y el backend conserva el cálculo automático. Las acciones que nunca han tenido aportes positivos conservan su avance manual; si se retira el último aporte de una acción que ya estaba bajo cálculo automático, su avance calculado vuelve a 0%. La interfaz muestra el aporte distribuido, el avance obtenido y el porcentaje todavía disponible.

## Calendario centralizado

`GET /api/orientation/plans/{plan}/calendar` recibe un rango `start`/`end` y devuelve exclusivamente las acciones que se superponen con el rango y las actividades cuya ejecución cae dentro de él. Los índices de fechas evitan cargar el plan completo para cada cambio de vista.

La interfaz ofrece vistas mensual, semanal, diaria y agenda. Las acciones se muestran como periodos de día completo y las actividades conservan hora y lugar. Al seleccionar cualquier evento se abre la ficha de su acción de origen.

## Calendarización curricular por capas

La ruta `/orientation/calendarizacion` complementa el calendario operativo con una lectura curricular anual. Organiza las semanas en cuatro capas independientes que pueden combinarse o verse en conjunto:

- 1° a 6° básico;
- 7° básico a II° medio;
- III° medio;
- IV° medio y su plan vocacional.

La referencia 2026 reúne 164 actividades extraídas de los cuatro documentos institucionales: 42 semanas para cada uno de los tres primeros tramos y 38 para IV° medio. La API expone primero una vista previa sin escritura; el usuario con `orientation.manage_plan` debe confirmar explícitamente que desea guardar esa calendarización en el año. La importación usa claves de origen únicas e inserción idempotente, por lo que una repetición no duplica semanas ni reemplaza cambios ya realizados.

Guardar la calendarización no asigna automáticamente sus actividades a una acción ni modifica avances. Después de guardarlas, cada actividad se puede crear, editar o retirar, clasificar por foco, marcar con estado y vincular opcionalmente a una acción del plan anual del mismo año. Ese vínculo es organizativo: para tributar al avance se debe registrar la ejecución desde la acción mediante su flujo `+ Actividad`, indicando el porcentaje de aporte correspondiente.

La interfaz permite alternar entre un calendario mensual y el cronograma semanal, combinar capas, filtrar por mes o foco y buscar contenidos sin usar una tabla ni desplazamiento horizontal. Al abrir un evento persistido desde el calendario se muestra su edición y el selector de acción asociada.

Los nombres de archivo y la correspondencia real de las semanas determinan el año 2026. Los encabezados internos que indican 2025 son inconsistentes con las fechas y se presentan como una observación de origen, no como instrucciones ni como año operativo del sistema.

## Evidencias y privacidad

Los archivos se almacenan en el disco privado `local`, bajo `orientation/{año}/actions/{acción}`. No se publican URLs directas: la descarga siempre pasa por una ruta autenticada que exige `orientation.view`. Se admiten archivos de hasta 30 MB o un enlace HTTPS verificable.

No existen rutas de borrado físico en esta primera etapa. Planes, acciones, actividades y referencias se conservan mediante estados para mantener trazabilidad.

## Informes PDF y estadísticas

El plan dispone de tres salidas descargables, generadas en el navegador con los mismos datos protegidos por `orientation.view`:

- informe general anual en A4 horizontal, con objetivo, descripción, indicadores, planes indexados, matriz completa de acciones y trazabilidad;
- informe individual de una acción en A4 vertical, con planificación, responsables, actividades realizadas y evidencias asociadas;
- informe estadístico en A4 horizontal, con indicadores ejecutivos, distribución de estados, cobertura documental, carga mensual y desempeño por acción.

La vista `/orientation/estadisticas` consulta `GET /api/orientation/plans/{plan}/statistics`. El servicio usa agregaciones y conteos en base de datos para evitar cargar colecciones completas. Resume avance, ejecución, alertas, trazabilidad, tipos de evidencia, distribución mensual y métricas por acción. La respuesta es privada y se entrega con `Cache-Control: no-store`.

## Rol y permisos

La migración aditiva registra el rol `orientacion` con nombre visible `Orientador/a`, el módulo padre y sus accesos de navegación. Además, asigna explícitamente al rol `super_admin` todos los permisos y las entradas del módulo:

- `orientation.view`;
- `orientation.manage_plan`;
- `orientation.manage_execution`;
- `orientation.manage_evidence`.

Ambos roles reciben los cuatro permisos y los módulos `orientation`, `orientation_annual_plan`, `orientation_calendarization`, `orientation_calendar` y `orientation_statistics`. El grupo `orientacion` también forma parte de `PermissionGroupSeeder` para que la conciliación RBAC lo reconozca.

## Precarga del plan institucional 2026

El comando `orientation:preload-annual-plan` incorpora idempotentemente el contenido del documento institucional: 26 acciones de la matriz principal y 7 acciones adicionales presentes únicamente en la Carta Gantt. No sobrescribe acciones existentes con el mismo título ni modifica los datos generales de un plan ya creado.

La vista previa no escribe registros:

```bash
php artisan orientation:preload-annual-plan --year=2026
```

La aplicación explícita es:

```bash
php artisan orientation:preload-annual-plan --year=2026 --apply --confirm=PRECARGAR-PLAN-ORIENTACION
```

También crea el índice `Plan de Afectividad, Sexualidad y Género` y lo vincula con tres acciones relacionadas. Las acciones cuya Carta Gantt no entrega una fecha inequívoca quedan sin calendarizar y con una observación para completar manualmente. En producción el comando exige además `--backup-verified=RESPALDO-VERIFICADO`.

## Despliegue seguro

Antes de desplegar a producción se debe crear y verificar el respaldo obligatorio. La migración solo agrega tablas, índices, rol, permisos, módulos y relaciones nuevas; no reescribe ni elimina registros operativos existentes. Su método `down()` es deliberadamente no destructivo.

No se deben ejecutar `migrate:fresh`, `migrate:refresh`, `migrate:reset` ni `db:wipe`. Revisar primero las migraciones pendientes y aplicar el artefacto limpio con el flujo normal de respaldo y `migrate --force --no-interaction`.

## Verificación enfocada

- Backend: `php artisan test tests/Feature/Orientation/OrientationAnnualPlanTest.php`
- Precarga: `php artisan test tests/Feature/Orientation/PreloadOrientationAnnualPlanCommandTest.php`
- Frontend: `npm run test:unit -- tests/frontend/orientation-annual-plan.test.js tests/frontend/orientation-report-pdf.test.js`
- Compilación: `npm run prod`

Las pruebas cubren RBAC y navegación, plan único por año, relaciones entre planes y acciones, actividades, evidencia privada, calendarización de cuatro capas e importación idempotente, calendario por rango, fechas fuera del año, aislamiento entre actividades de distintas acciones, agregaciones estadísticas y estructura de los tres PDF.
