# Modulo de horarios docentes

## Decisiones de integracion

- El modulo reutiliza `AcademicYear`, `EducationLevel`, `CourseSection` y `Staff`.
- No se crea una entidad `Teacher`: un docente es un `Staff` activo con cargo/departamento docente.
- La jornada se modela como `SchoolDayTemplate`; los tramos diarios son `SchoolDayBlock`.
- `EducationLevel.default_school_day_template_id` define la jornada heredada por defecto.
- `CourseSection.school_day_template_id` permite sobrescribir la jornada del nivel.
- Las reglas criticas viven en servicios bajo `App\Services\Schedule`, no en Vue ni en controladores.
- Las capas ocultas en UI no filtran la validacion: todos los eventos del docente cuentan para conflictos.
- La validacion estricta bloquea errores criticos; las advertencias pueden guardarse y quedan registradas como incidencias activas.

## Servicios principales

- `ScheduleTimeCalculator`: conversion de minutos/horas pedagogicas, distribucion lectiva/no lectiva y solapamientos.
- `JornadaService`: creacion, actualizacion, duplicado y asignacion de jornadas.
- `StudyPlanService`: planes de estudio y progreso por curso.
- `TeacherContractService`: calculo de distribucion contractual.
- `ScheduleValidationService`: motor central `validateScheduleEvent`.
- `ScheduleEventService`: persistencia de eventos con validacion y registro de incidencias.
- `ScheduleSummaryService`: resumen semanal por docente, curso y conflictos.

## Alcance implementado

- Configuracion horaria por ano academico.
- Jornadas multiples con bloques asignables y no asignables.
- Asignaturas y planes de estudio por nivel o curso.
- Contratos docentes con calculo 65/35 editable.
- Capas de horario por docente.
- Eventos de horario lectivos y no lectivos.
- Vista de horario docente y vista de horario por curso.
- Panel de conflictos.
- Seeds demo y pruebas de logica critica.

## Extensiones previstas

- Exportacion PDF/Excel desde `ScheduleSummaryService`.
- Importacion CSV/Excel hacia `ScheduleSubject` y `StudyPlanSubject`.
- Drag and drop sobre la grilla Vue usando la base de `moveScheduleEvent`.
- Optimizacion visual de bloques con rowspan para tramos dobles o personalizados.
