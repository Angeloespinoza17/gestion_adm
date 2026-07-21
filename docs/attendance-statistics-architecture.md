# Estadísticas Avanzadas de Asistencia

## Diagnóstico y reutilización

El módulo usa `attendance_records` como única fuente de verdad diaria, `school_days` como calendario lectivo, `student_enrollments` para vigencia de matrícula y `course_sections`/`education_levels` para la jerarquía académica. También reutiliza `attendance_alerts`, `attendance_followups`, `attendance_imports`, `attendance_projection_settings`, el RBAC existente y los casos de convivencia mediante un vínculo opcional.

No se crean copias de estudiantes, cursos, matrículas, jornadas ni registros de asistencia. Los indicadores se calculan con consultas agregadas y solo se persisten configuraciones, procesos, auditoría y resultados de trabajos asíncronos.

## Componentes nuevos

- Configuración: tramos de riesgo, reglas de alerta, motivos de ausencia y parámetros financieros versionados.
- Gestión: metas, intervenciones vinculables a convivencia, acciones y reportes programados.
- Operación: filtros guardados, preferencias del dashboard, exportaciones en segundo plano y ejecuciones de proyección.
- Gobierno: incidencias de calidad de datos y bitácora de auditoría.
- Servicios: periodo, cálculo, agregación, tendencia, riesgo, impacto financiero, calidad, caché y exportación.
- API y Vue: dashboard, explorador paginado, detalle de estudiante, alertas/intervenciones, metas, simulador, calidad y exportaciones.

## Supuestos

- Una fila de `attendance_records` representa la situación de una estudiante en un día y curso; su restricción única evita presente/ausente simultáneo.
- `present` y `absent` siguen siendo los estados canónicos. Justificación, atraso y retiro anticipado son atributos del registro para mantener retrocompatibilidad con los PDF Lirmi.
- La sede no existe como dimensión académica en el proyecto. Se informa como brecha y no se inventa una entidad.
- La jornada proviene de `school_day_templates` cuando el curso la tiene asociada.
- Los cruces sensibles con enfermería no se exponen desde este módulo hasta contar con permiso explícito y una regla de minimización aprobada.
- Las cifras financieras son estimaciones y solo se calculan con parámetros vigentes almacenados en base de datos.

## Inconsistencias detectadas

- Los registros importados actuales solo contienen presente/ausente; las métricas de justificación, atraso y retiro comienzan en cero hasta que esos datos se registren.
- `school_days` es institucional, por lo que cierres especiales por curso se detectan por cobertura de registros, no por un calendario separado.
- El proyecto no incluye Laravel Excel ni un generador PDF PHP. El módulo incorpora un generador PDF vectorial paginado y produce Excel SpreadsheetML de varias hojas, CSV y PDF desde jobs, sin capturas de pantalla.
- La cola configurada localmente usa `sync`. En producción se recomienda Redis/database para exportaciones y evaluaciones masivas.

## Instalación y verificación

```bash
php artisan migrate
php artisan db:seed --class=AttendancePermissionSeeder
php artisan test tests/Unit/Attendance tests/Feature/Attendance
npm run prod
```

Los datos demostrativos son opcionales: `php artisan db:seed --class=AttendanceStatisticsSeeder`. Este seeder es independiente y se niega a ejecutarse en producción. Para revertir únicamente la última tanda, usar `php artisan migrate:rollback --step=1`. Antes del despliegue se recomienda respaldo y ejecutar los jobs con `php artisan queue:work` cuando `QUEUE_CONNECTION` no sea `sync`.

El planificador debe invocar `php artisan schedule:run` cada minuto. El módulo recalcula alertas diariamente y revisa reportes programados cada cinco minutos. La migración usa nombres explícitos cortos para respetar el límite de 64 caracteres de MySQL.
