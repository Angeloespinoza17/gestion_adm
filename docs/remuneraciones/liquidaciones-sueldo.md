# Liquidaciones de sueldo importadas desde PDF

## Alcance

Este submódulo agrega detalle individual y por subvención al módulo de Remuneraciones. El Libro de Remuneraciones existente conserva su rol de fuente mensual consolidada y no es sobrescrito ni reemplazado. Las liquidaciones PDF se guardan en un modelo separado, versionado y conciliable por establecimiento, RUT exacto, año y mes.

Rutas principales:

- `/remuneraciones/liquidaciones-sueldo`: histórico y totales.
- `/remuneraciones/matriz-subvenciones`: matrices dinámicas.
- `/remuneraciones/conciliacion-liquidaciones`: Libro versus liquidaciones.
- `/remuneraciones/importaciones-liquidaciones`: asistente, progreso e incidencias.

## Arquitectura

El flujo se divide en componentes reemplazables:

- `PayslipPdfTextExtractor`: extracción nativa con coordenadas relativas y respaldo OCR opcional.
- `PayslipProviderDetector`: selecciona un adaptador configurado.
- `PayslipParserInterface`: contrato de adaptadores.
- `NumerusPayslipParser`: encabezados, secciones, líneas envueltas y columnas dinámicas Numerus.
- `PayslipNormalizer`: RUT, etiquetas, códigos y montos.
- `PayslipDistributionService`: cálculo en pesos enteros y controles.
- `PayslipImportService`: staging privado, hash, match exacto, persistencia, incidencias, confirmación y versiones.
- `PayslipReportingService`: histórico, matrices y conciliación sin modificar el Libro.
- `PayslipExportService`: Excel y CSV filtrados.
- `PayslipPaymentProposalService`: borrador y confirmación revisable, sin contabilización automática.
- `AnalyzePayslipBatch` y `ConfirmPayslipBatch`: trabajos idempotentes en la cola `remunerations`.

Los PDF se almacenan en el disco `local`, bajo `private/remuneration/payslips`. La API nunca devuelve `private_path`; la visualización usa una ruta firmada de cinco minutos, autorización RBAC y `Cache-Control: no-store`.

## Persistencia y trazabilidad

La migración `2026_08_24_120000_create_remuneration_payslip_import_tables.php` es aditiva. Reutiliza `lcd_schools`, `staff`, `remuneration_periods` y `accounting_funding_sources`, y agrega:

- aliases y reglas: `remuneration_funding_source_aliases`, `remuneration_payslip_concept_rules`;
- carga: `remuneration_payslip_batches`, `remuneration_payslip_files`, `remuneration_payslip_pages`;
- detalle: `remuneration_payslips`, `remuneration_payslip_earnings`, `remuneration_payslip_discounts`, `remuneration_payslip_discount_allocations`, `remuneration_payslip_employer_contributions`;
- resultado y calidad: `remuneration_payslip_funding_summaries`, `remuneration_payslip_controls`, `remuneration_payslip_issues`;
- propuesta: `remuneration_payment_proposals`, `remuneration_payment_proposal_items`, `remuneration_payment_proposal_allocations`.

Se conserva SHA-256, archivo original privado, proveedor, parser, página, estructura normalizada, confianza, usuario, versión y relación con la versión anterior. Los RUT y nombres extraídos se cifran; las respuestas de consulta enmascaran el RUT. Anular cambia estados y conserva el historial. El `down()` es deliberadamente no destructivo.

## Fórmulas

Para cada subvención `s`:

- `H_s`: haberes imponibles y no imponibles.
- `I_s`: haberes imponibles.
- Para cada descuento legal `j`: `DL_j × I_s / suma(I_s)`.
- Para cada otro descuento `j`: `DO_j × H_s / suma(H_s)`.
- `líquido_s = H_s - descuentos_legales_s - otros_descuentos_s`.

Cada descuento se distribuye por separado. Los montos se redondean a CLP entero y la última fuente activa absorbe solo el residuo de redondeo de esa línea. Se persisten base, proporción, valor calculado, ajuste y valor asignado. Una base cero o inconsistente genera una incidencia; una diferencia sustantiva nunca se corrige en silencio.

Los aportes del empleador se toman directamente del PDF por subvención; no se estiman aplicando porcentajes.

## Controles

Se registran controles por liquidación, subvención y lote:

- detalle y composición de haberes;
- totales imponibles y no imponibles impresos por subvención;
- detalle de descuentos y ecuación del líquido;
- descuentos distribuidos y líquidos distribuidos;
- aportes totales e impresos por subvención;
- páginas versus liquidaciones y agregados del lote;
- conciliación de presencia y montos con el Libro de Remuneraciones.

Los estados son `correcto`, `advertencia` o `error`. La confirmación y la propuesta de pago se bloquean ante errores abiertos.

## Permisos

- `remuneraciones.liquidaciones_pdf.ver`
- `remuneraciones.liquidaciones_pdf.importar`
- `remuneraciones.liquidaciones_pdf.incidencias`
- `remuneraciones.liquidaciones_pdf.reprocesar`
- `remuneraciones.liquidaciones_pdf.exportar`
- `remuneraciones.liquidaciones_pdf.propuesta_pago`
- `remuneraciones.liquidaciones_pdf.anular`
- `remuneraciones.liquidaciones_pdf.auditoria`

Todos requieren además el acceso confidencial base de Remuneraciones; `super_admin` mantiene el bypass RBAC existente.

## Importar un nuevo mes

1. Verificar que los trabajadores tengan RUT válido en su ficha y que el establecimiento tenga el RBD correcto.
2. Iniciar un worker: `php artisan queue:work database --queue=remunerations,default --tries=3`.
3. Abrir **Remuneraciones > Importaciones e incidencias**.
4. Seleccionar establecimiento, uno o más PDF y la política de duplicado.
5. Revisar formato, periodos, match exacto de trabajadores, subvenciones, controles y advertencias.
6. Resolver aliases desconocidos. Si cambia un catálogo, reprocesar para crear una versión nueva.
7. Corregir mes/año cuando corresponda y confirmar. La confirmación se procesa en cola.
8. Revisar la conciliación con el Libro y las matrices antes de generar la propuesta de pago.
9. Generar y confirmar la propuesta. Esto no crea movimientos presupuestarios, pagos ni asientos.

`reject` impide una huella o clave de negocio repetida. `replace` y `new_version` mantienen la versión anterior como histórica y enlazan la nueva.

## Agregar una subvención

1. Crear o activar la fuente en el catálogo contable `accounting_funding_sources`; no se agrega una columna a ninguna tabla.
2. Cargar un PDF de prueba. La etiqueta nueva aparecerá como incidencia `unknown_funding_source`.
3. Resolver la incidencia seleccionando la fuente. Se guarda un alias normalizado por proveedor.
4. Reprocesar el archivo. La matriz, los reportes y el Excel incorporarán la nueva fuente automáticamente.

## Agregar otro formato

1. Crear un adaptador que implemente `PayslipParserInterface` y devuelva el mismo contrato normalizado de Numerus.
2. Mantener detección, parsing y confianza independientes; no agregar condicionales del proveedor a `PayslipImportService`.
3. Registrar la clase en `config/remuneration_payslips.php`, clave `parsers`.
4. Agregar fixtures sintéticos para encabezados, columnas variables, varias páginas, líneas envueltas y controles.
5. Versionar el parser cuando cambie una regla que pueda alterar resultados y reprocesar como nueva versión.

## Despliegue seguro

No se realizó despliegue a producción. Antes de desplegar:

1. Crear y verificar un respaldo de base de datos.
2. Revisar nuevamente todas las migraciones y confirmar que no contienen borrados ni seeders de datos reales.
3. Ejecutar `php artisan migrate:status` y luego `php artisan migrate --force` con el PHP configurado para producción.
4. Publicar el frontend con el flujo normal del proyecto.
5. Reiniciar workers incluyendo `remunerations,default`.
6. Verificar permisos, almacenamiento privado, enlaces firmados, carga de un fixture anonimizado y ausencia de datos sensibles en logs.

Nunca usar `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` ni seeders de demostración en producción.

## Regresión de referencia

La prueba privada opcional se ejecuta con `NUMERUS_REFERENCE_PDF=/ruta/al/archivo.pdf php artisan test tests/Unit/Remuneration/PayslipParserTest.php --filter=reference_pdf_regression`. El archivo no se copia al repositorio. La regresión valida las 123 páginas, totales globales, distribución GENERAL/SEP/PIE y controles en cero.
