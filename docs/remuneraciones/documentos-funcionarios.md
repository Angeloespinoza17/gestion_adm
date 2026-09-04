# Documentos de funcionarios

La vista `/remuneraciones/documentos` centraliza los documentos y constancias que RR.HH. debe controlar para la nómina activa. Reutiliza los funcionarios existentes y mantiene los archivos en almacenamiento privado.

## Flujo

1. En **Crear documentos** se define una obligación institucional.
2. La obligación puede requerir entrega, firma o ambas acciones.
3. La vigencia puede ser:
   - sin vencimiento;
   - una cantidad de meses desde la fecha del documento;
   - una fecha indicada al registrar cada funcionario.
4. En **Listado de funcionarios** se presenta una tabla compacta con una fila por persona, su avance, alertas y estado general. Los documentos no crean columnas adicionales.
5. **Ver documentos** abre la carpeta del funcionario. Allí se consulta cada requisito, entrega, firma, vencimiento y respaldo, y se accede a su actualización.
6. La pestaña **Liquidaciones** reutiliza el historial individual del submódulo de liquidaciones PDF. Cada registro se identifica por funcionario, año y mes; al importar un nuevo período aparece automáticamente sin duplicar archivos en el control documental.
7. Al actualizar un documento se guardan las fechas de entrega y firma, el vencimiento calculado, observaciones y un respaldo opcional.

## Estados

- **Pendiente de entrega/firma:** falta una acción exigida por el catálogo.
- **Al día:** todas las acciones están completas y el documento no vence o sigue vigente.
- **Por vencer:** la fecha entra en el rango de alerta configurado.
- **Vencido:** la fecha de vencimiento ya pasó.

Los requisitos archivados dejan de exigirse a la nómina actual, pero los registros históricos se conservan.

## Seguridad y permisos

- Se requieren `remuneraciones.acceso_confidencial`, `remuneraciones.ver` y `remuneraciones.rrhh.gestionar`.
- Los montos e historial de liquidaciones solo se consultan cuando el usuario también posee `remuneraciones.liquidaciones_pdf.ver`; la API conserva su autorización independiente.
- No se agregan permisos ni asignaciones de roles nuevas.
- Los adjuntos nuevos se guardan en el disco privado `local` y solo se descargan mediante una ruta autenticada con cabeceras `private, no-store`.
- Las creaciones, cambios y archivados se registran en la auditoría de Remuneraciones.

## Persistencia

La migración `2026_09_01_160000_create_hr_document_requirements.php` es aditiva: crea el catálogo `hr_document_requirements` y amplía `hr_document_controls` para asociar requisito, entrega, firma y archivo privado. No modifica filas productivas existentes ni ejecuta seeders.
