# Planificación automática de visitas de mantención

## Alcance

El módulo de Planificación de visitas incorpora un asistente exclusivo para usuarios con rol `super_admin`. Permite construir una propuesta masiva por mes, año o rango personalizado, revisarla y confirmarla sin reemplazar visitas existentes.

## Flujo operativo

1. El superadministrador abre **Planificar automáticamente**.
2. Configura período, funcionario base, dependencias, frecuencia, intervalo, días, capacidad, horario y tipo de visita.
3. `POST /api/maintenance/visits/planning/preview` genera una propuesta sin escrituras en base de datos.
4. La propuesta puede editarse por fila: fecha, hora, dependencia, funcionario, tipo o inclusión.
5. `POST /api/maintenance/visits/planning/confirm` vuelve a validar conflictos y crea el lote completo en una transacción.

## Reglas y seguridad

- Los dos endpoints verifican explícitamente `User::isSuperAdmin()`; un permiso operativo de visitas no habilita esta función.
- Sólo se consideran dependencias activas de mantención y funcionarios activos habilitados para recibir órdenes.
- Se pueden excluir fines de semana y días confirmados como no lectivos en `school_days`.
- La propuesta evita una segunda visita para la misma dependencia y fecha, y choques de funcionario, fecha y hora.
- Antes de confirmar se repite la detección de conflictos. Si aparece uno nuevo, no se crea ninguna visita del lote.
- La clave de idempotencia impide duplicar visitas al reintentar una confirmación.
- Las visitas quedan en estado `Programada`, ligadas a un lote auditable y conservan el nombre histórico del responsable junto con `responsible_staff_id`.

## Consulta móvil y exportación PDF

- La vista de escritorio conserva tabla y calendario mensual. Bajo 768 px cambia a una agenda cronológica con tarjetas táctiles, acciones de checklist/edición y filtros plegables, sin desplazamiento horizontal.
- **Exportar agenda** abre un flujo independiente donde se seleccionan mes, persona y formato: calendario visual o listado detallado.
- El filtro usa `responsible_staff_id` para mantener una identidad estable. La consulta también incluye registros históricos cuyo vínculo sea nulo pero cuyo nombre coincida con el funcionario seleccionado.
- El calendario PDF es A4 horizontal, incorpora responsable, período, indicadores por estado, encabezado repetible y un máximo de tres visitas visibles por día; el excedente queda indicado para conservar el mes completo en una página.
- El endpoint devuelve `status_totals` calculado sobre todo el resultado filtrado, de modo que los indicadores no dependan de la página visible.

## Persistencia y despliegue

La migración `2026_08_25_180000_create_maintenance_visit_planning_batches.php` es aditiva: crea la tabla de lotes y agrega dos claves foráneas nulas a `maintenance_visits`. No actualiza ni elimina registros existentes.

Antes de un despliegue a producción se debe generar y verificar el respaldo obligatorio, revisar nuevamente las migraciones pendientes y ejecutar sólo `php artisan migrate --force`; nunca usar comandos destructivos de migración o reinicio de base de datos.
