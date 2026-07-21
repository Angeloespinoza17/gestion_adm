# Permisos de estadísticas de asistencia

| Permiso | Alcance |
| --- | --- |
| `attendance_statistics.view` | Acceso al módulo y datos agregados autorizados. |
| `attendance_statistics.view_global` | Vista institucional, niveles y todos los cursos. |
| `attendance_statistics.view_course` | Datos de cursos autorizados. |
| `attendance_statistics.view_student` | Ficha analítica nominal. |
| `attendance_statistics.view_financial` | Parámetros e impacto financiero. |
| `attendance_statistics.view_sensitive_segments` | Segmentos personales o sensibles disponibles. |
| `attendance_statistics.export` | Crear y descargar exportaciones. |
| `attendance_statistics.configure` | Tramos, reglas, motivos y parámetros. |
| `attendance_statistics.manage_goals` | Crear y actualizar metas. |
| `attendance_statistics.manage_alerts` | Asignar y cambiar alertas. |
| `attendance_statistics.manage_interventions` | Crear, actualizar y cerrar intervenciones. |
| `attendance_statistics.manage_reports` | Programar reportes. |
| `attendance_statistics.view_audit` | Consultar y exportar auditoría. |

Las rutas aplican middleware de permiso. La interfaz usa las capacidades devueltas por la API solo para presentación; no reemplaza la autorización del backend. Superadministración recibe todos los permisos y administración recibe el conjunto operativo por el seeder.
