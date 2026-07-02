# Módulo de Portería

## Alcance implementado

- Dashboard operativo con alertas, pendientes, retiros del día y últimos movimientos.
- Búsqueda rápida de estudiantes con ficha mínima para portería.
- Registro de retiros con:
  - validación contra matrícula vigente del año activo
  - validación de persona autorizada
  - alerta por restricción de retiro
  - prevención de duplicados recientes
  - autorización especial cuando corresponde
- Registro de recepción de objetos, documentos y encomiendas.
- Registro de mercadería institucional, proveedores y entregas.
- Control de visitas con ingreso y salida.
- Control de proveedores o servicios externos con responsable y dependencia.
- Bitácora diaria operativa de portería.
- Control de llaves con catálogo, préstamos y devoluciones.
- Bitácora transversal en `porter_movement_logs`.
- Solicitudes de autorización especial en `porter_authorization_requests`.

## Estructura de datos

- `student_profiles` ahora guarda:
  - `pickup_restriction`
  - `pickup_restriction_notes`
  - `porter_alert_notes`
  - `authorized_pickup_people` (JSON)
- Nuevas tablas:
  - `porter_student_withdrawals`
  - `porter_received_items`
  - `porter_goods_movements`
  - `porter_visits`
  - `porter_external_service_entries`
  - `porter_daily_log_entries`
  - `porter_keys`
  - `porter_key_loans`
  - `porter_authorization_requests`
  - `porter_movement_logs`

## Permisos agregados

- `ver_porteria`
- `registrar_retiro_porteria`
- `autorizar_retiros_porteria`
- `registrar_objetos_porteria`
- `entregar_objetos_porteria`
- `registrar_mercaderia_porteria`
- `entregar_mercaderia_porteria`
- `registrar_visitas_porteria`
- `registrar_proveedores_porteria`
- `registrar_bitacora_porteria`
- `gestionar_llaves_porteria`
- `ver_historial_porteria`
- `exportar_reportes_porteria`

## Rol agregado

- `porteria`

Otorga acceso solo al módulo Portería y no incluye permisos académicos, financieros ni administrativos fuera del dominio.

## Flujo sugerido de uso

1. Personal autorizado mantiene en ficha de estudiante las restricciones y personas autorizadas de retiro.
2. Portería busca a la estudiante desde `/porter/students`.
3. Si corresponde retiro, registra la salida desde `/porter/withdrawals`.
4. Si la persona no está autorizada o existe restricción, el retiro queda `observado` y genera solicitud de autorización especial.
5. Inspectoría, coordinación, dirección o administración pueden resolver esa autorización desde el historial de retiros.
6. Objetos y mercadería se registran desde sus secciones respectivas y se marcan como derivados o entregados.
7. Visitas y proveedores registran su ingreso y luego su salida para cerrar trazabilidad.
8. La bitácora diaria consolida novedades del turno.
9. Las llaves se mantienen en catálogo y cada préstamo/devolución queda trazado.

## Rutas frontend

- `/porter/dashboard`
- `/porter/students`
- `/porter/withdrawals`
- `/porter/received-items`
- `/porter/goods`
- `/porter/visits`
- `/porter/providers`
- `/porter/daily-log`
- `/porter/keys`
- `/porter/reports`

## Rutas API principales

- `GET /api/porter/catalogs`
- `GET /api/porter/dashboard`
- `GET /api/porter/students`
- `GET /api/porter/students/{studentProfile}`
- `GET|POST /api/porter/withdrawals`
- `POST /api/porter/withdrawals/{porterStudentWithdrawal}/resolve`
- `POST /api/porter/withdrawals/{porterStudentWithdrawal}/annul`
- `GET|POST /api/porter/received-items`
- `PUT /api/porter/received-items/{porterReceivedItem}/status`
- `GET|POST /api/porter/goods-movements`
- `PUT /api/porter/goods-movements/{porterGoodsMovement}/status`
- `GET|POST /api/porter/visits`
- `PUT /api/porter/visits/{porterVisit}/exit`
- `GET|POST /api/porter/external-services`
- `PUT /api/porter/external-services/{porterExternalServiceEntry}/exit`
- `GET|POST /api/porter/daily-log`
- `GET|POST /api/porter/keys`
- `POST /api/porter/keys/{porterKey}/loans`
- `POST /api/porter/key-loans/{porterKeyLoan}/return`
- `GET /api/porter/reports`

## Notas de verificación

- Las pruebas feature del módulo fueron agregadas en `tests/Feature/Porter/PorterStudentWithdrawalTest.php`.
- En este entorno no pudieron ejecutarse completamente porque una migración previa del proyecto (`2026_05_31_000004_create_maintenance_annual_plans_table.php`) falla en MySQL por nombre de índice demasiado largo antes de llegar a las pruebas del módulo.
