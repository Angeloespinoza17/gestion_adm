# Calendario y Fechas Relevantes

## Qué incluye

- Vista principal en `/relevant-calendar` con:
  - calendario mensual, semanal, diario y listado
  - bandeja filtrable
  - próximos vencimientos
  - vencidos
  - procesos del mes
  - historial
  - alertas internas calculadas según recordatorios activos
- Detalle del evento en `/relevant-calendar/events/{id}`
- Catálogos administrables:
  - `/relevant-calendar/process-types`
  - `/relevant-calendar/institutions`

## Permisos del módulo

- `ver_calendario_fechas_relevantes`
- `ver_todo_calendario_fechas_relevantes`
- `gestionar_calendario_fechas_relevantes_departamento`
- `administrar_calendario_fechas_relevantes`
- `administrar_tipos_calendario_fechas_relevantes`
- `administrar_instituciones_calendario_fechas_relevantes`
- `exportar_calendario_fechas_relevantes`

## Recurrencia

- Soporta eventos únicos, diarios, semanales, mensuales, anuales y personalizados.
- La regla se guarda en `calendar_events.recurrence_rule`.
- Las series generan ocurrencias persistidas en `calendar_events` con `event_kind = occurrence`.
- Edición soportada:
  - solo esta ocurrencia
  - esta y las futuras
  - toda la serie

## Procesos con etapas

- Un proceso padre usa `event_kind = process`.
- Cada etapa relacionada usa `event_kind = stage` y `parent_event_id`.
- El rango del proceso padre se recalcula desde las fechas de sus etapas.

## Recordatorios

- Cada evento puede tener varios recordatorios en `calendar_event_reminders`.
- La vista principal calcula las alertas internas del día a partir de esas reglas.
- La estructura queda lista para futuro despacho por correo reutilizando la misma tabla de recordatorios.

## Adjuntos y auditoría

- Adjuntos almacenados en `storage/app/calendar-events/...`
- Auditoría en `calendar_event_logs`
- Se registra creación, edición, eliminación, cambios de serie y gestión de adjuntos

## Seeders

- `Database\\Seeders\\CalendarProcessTypeSeeder`
- `Database\\Seeders\\CalendarInstitutionSeeder`
- `Database\\Seeders\\Modules\\RelevantCalendarModuleSeeder`

## Nota técnica

- La opción “último día hábil del mes” considera lunes a viernes. No descuenta feriados chilenos.
