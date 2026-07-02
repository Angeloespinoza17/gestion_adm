# Plan de implementacion - Modulo de Remuneraciones

## Resumen del modulo

El modulo de remuneraciones se integrara al stack existente Laravel 12 + Vue 3 + Sanctum, usando el RBAC propio (`permissions`, `roles`, `system_modules`) y reutilizando entidades ya disponibles: `staff`, `contracts`, `departments`, `cargos`, `accounting_cost_centers`, `accounting_funding_sources`, `accounting_manual_accounts` y `accounting_journal_entries`.

La revision estructural de los adjuntos confirma:

- Liquidaciones reales en PDF con formato A4, secciones de identificacion laboral, prevision/salud, Carrera Docente, dias/horas, haberes por fuente, descuentos, aportes empleador, totales imponibles/no imponibles/tributables y liquido.
- Planilla RR.HH. con columnas para datos personales, forma de pago, contratos, Carrera Docente, horas por fuente SEP/PIE/Pro Retencion, AFP, salud, APV, AFC y cargas familiares.

No se usaran nombres, RUT, cuentas bancarias ni montos reales de esos archivos en seeders, tests, documentacion o pantallas demo.

## Entidades principales

- Periodos de remuneracion: mes, anio, estado, fechas de cierre y reapertura.
- Parametros legales por vigencia: AFP, salud, AFC, SIS, mutualidad, SANNA, UF, UTM, ingreso minimo, topes imponibles, impuesto unico, asignacion familiar, RBMN, BRP, ATDP y factores docentes.
- Fichas previsionales de trabajador: AFP, salud, APV, AFC, cargas, banco y forma de pago, asociadas a `staff`.
- Contratos remuneracionales: extension remuneracional de `contracts`, con sueldo base, horas, tipo funcionario, Carrera Docente, tramo docente y distribucion de jornada/fuentes.
- Conceptos de haberes y descuentos: imponibles, no imponibles, tributables, formulas seguras y configuracion contable.
- Movimientos por periodo: haberes/descuentos manuales, licencias, permisos, atrasos, reemplazos, ausencias, horas extra y finiquitos.
- Liquidaciones: cabecera, lineas, descuentos, aportes, distribucion por fuente/centro, pagos y snapshot completo.
- Centralizacion contable: asientos generados desde liquidaciones aprobadas y pagadas.
- Auditoria: bitacora critica para calculos, aprobaciones, pagos, exportaciones, cierres, reaperturas, anulaciones y reversas.

## Tablas propuestas

- `remuneration_periods`
- `remuneration_legal_parameters`
- `remuneration_employee_profiles`
- `remuneration_contract_settings`
- `remuneration_concepts`
- `remuneration_employee_concepts`
- `remuneration_movements`
- `remuneration_payrolls`
- `remuneration_payroll_lines`
- `remuneration_payroll_distributions`
- `remuneration_payments`
- `remuneration_accounting_exports`
- `remuneration_audit_logs`

## Relaciones

- `staff` 1:1 `remuneration_employee_profiles`
- `contracts` 1:1 `remuneration_contract_settings`
- `remuneration_periods` 1:N `remuneration_payrolls`
- `staff` 1:N `remuneration_payrolls`
- `contracts` 1:N `remuneration_payrolls`
- `remuneration_payrolls` 1:N `remuneration_payroll_lines`
- `remuneration_payrolls` 1:N `remuneration_payroll_distributions`
- `remuneration_payrolls` 1:N `remuneration_payments`
- `accounting_funding_sources` y `accounting_cost_centers` se referencian desde distribuciones.
- `accounting_journal_entries` se referencia desde centralizaciones.

## Servicios backend

- `RemunerationAccessService`: permisos, instalacion y roles.
- `RemunerationAuditService`: auditoria critica con usuario, IP, entidad, cambios y motivo.
- `RemunerationRoundingService`: redondeos centralizados en CLP entero.
- `SafeFormulaEvaluator`: evaluador sin `eval`, con variables whitelisted y operadores aritmeticos controlados.
- `RemunerationParameterResolver`: selecciona parametros vigentes por periodo y arma snapshot.
- `PayrollCalculationService`: calcula haberes, descuentos, aportes empleador, impuesto, liquido y distribuciones.
- `PayrollPeriodService`: cierre/reapertura y bloqueo de periodos.
- `PayrollAccountingService`: centralizacion contable y reversa.
- `RemunerationReportService`: dashboard, reportes CSV y DJ 1887 preliminar.
- `RemunerationImportService`: importacion validada desde planillas sin guardar datos sensibles en logs.

## Vistas frontend

- `/remuneraciones`: dashboard operativo.
- `/remuneraciones/trabajadores`: ficha remuneracional enlazada a funcionarios.
- `/remuneraciones/contratos`: parametros remuneracionales del contrato.
- `/remuneraciones/parametros`: parametros legales por vigencia.
- `/remuneraciones/conceptos`: haberes, descuentos y formulas.
- `/remuneraciones/movimientos`: movimientos del periodo.
- `/remuneraciones/liquidaciones`: calculo, revision y aprobacion.
- `/remuneraciones/pagos`: pagos, medios y comprobantes.
- `/remuneraciones/centralizacion`: asientos y reversas.
- `/remuneraciones/reportes`: reportes, exportaciones y DJ 1887.
- `/remuneraciones/auditoria`: trazabilidad.

Cada vista debe incluir breadcrumb/header, filtros, buscador, tabla paginada, estados visuales, confirmaciones SweetAlert2, ayuda contextual y errores entendibles.

## Jobs

- Calculo masivo de liquidaciones por periodo.
- Importacion de trabajadores/contratos/conceptos.
- Generacion masiva de PDFs de liquidacion.
- Envio de liquidaciones por email cuando se habilite.
- Centralizacion contable por periodo.
- Generacion de DJ 1887 preliminar.

## Exports

- Liquidaciones CSV/PDF.
- Nomina de pago.
- Libro de remuneraciones electronico preliminar.
- Centralizacion contable CSV.
- Reportes por fuente de financiamiento, centro de costo, trabajador, contrato y periodo.
- DJ 1887 preliminar.

## Imports

- Trabajadores y fichas previsionales desde planilla RR.HH.
- Parametros legales por vigencia.
- Conceptos recurrentes.
- Movimientos de periodo: bonos, descuentos, licencias, atrasos, reemplazos y ajustes.

## Permisos

- `remuneraciones.ver`
- `remuneraciones.dashboard`
- `remuneraciones.trabajadores.gestionar`
- `remuneraciones.contratos.gestionar`
- `remuneraciones.parametros.gestionar`
- `remuneraciones.conceptos.gestionar`
- `remuneraciones.movimientos.gestionar`
- `remuneraciones.liquidaciones.calcular`
- `remuneraciones.liquidaciones.aprobar`
- `remuneraciones.pagos.gestionar`
- `remuneraciones.contabilidad.centralizar`
- `remuneraciones.reportes.ver`
- `remuneraciones.reportes.exportar`
- `remuneraciones.periodos.cerrar`
- `remuneraciones.admin`

## Riesgos

- Cambios legales frecuentes: se mitigara parametrizando todo por vigencia y evitando constantes en codigo.
- Liquidaciones historicas alterables: se mitigara con snapshots completos y bloqueo de periodos cerrados.
- Formulas inseguras: se mitigara con evaluador controlado y variables permitidas.
- Duplicacion de trabajadores/contratos: se mitigara extendiendo `staff` y `contracts`.
- Privacidad: seeders, tests y ejemplos solo usaran datos ficticios.
- Contabilidad incompleta: la centralizacion generara asientos internos trazables y reversibles.

## Fases de implementacion

1. Base de datos, modelos, permisos y modulo de navegacion.
2. Parametros legales, perfiles previsionales, conceptos y movimientos.
3. Motor de calculo con snapshot, redondeos y bloqueo de periodos.
4. Liquidaciones, pagos, aprobaciones y auditoria critica.
5. Centralizacion contable y reportes.
6. Frontend Vue unificado con tabs operativos.
7. Tests de calculo, periodo cerrado, snapshot, auditoria y APIs principales.

## Decisiones tecnicas tomadas

- Montos CLP se guardaran como enteros (`bigInteger`) y porcentajes/factores como `decimal` controlado.
- Las liquidaciones guardaran snapshot JSON de parametros, formulas, contrato, perfil, distribucion y version de calculo.
- Se reutilizaran fuentes y centros de costo contables existentes para evitar catalogos paralelos.
- Los periodos cerrados bloquearan modificaciones; correcciones se modelaran como movimientos complementarios o reliquidaciones posteriores.
- La primera entrega implementara una API modular suficiente para operar el ciclo completo sin intentar codificar cada regla legal chilena en constantes.
