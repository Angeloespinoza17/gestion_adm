# Expansión operativa de Prevención de Riesgos

## Comité Paritario

- Ruta: `/risk-prevention/joint-committee`.
- Reutiliza los períodos e integrantes administrados en Gestión del personal.
- Conserva en almacenamiento privado el acta de constitución y una única acta mensual por comité y período.
- Permite revisar actas históricas por año, integrantes activos y capacitaciones vinculadas.
- Las capacitaciones se registran en `/risk-prevention/trainings`; al seleccionar un Comité Paritario aparecen automáticamente en su ficha, con participantes y cumplimiento individual.

Permisos delegables:

- `ver_comite_paritario`: consulta de actas, integrantes y capacitaciones.
- `cargar_actas_comite_paritario`: consulta operativa y carga de actas, sin entregar administración general de Prevención.

El grupo de permisos **Comité Paritario** aparece en la administración RBAC. Puede asignarse a los roles de Secretaría y Presidencia, o entregarse sólo el permiso de carga cuando corresponda.

## Accidentes y enfermedades profesionales

- Los casos de funcionarios pueden vincularse al registro real de Personal para conservar nombre y RUT exactos.
- Cada caso distingue accidente laboral o enfermedad profesional.
- Registra parte del cuerpo lesionada y días de licencia, tratados como días perdidos.
- El acumulado anual se calcula por funcionario al consultar; no se guarda un total duplicado.
- Los registros históricos sin vínculo a Personal se incorporan al acumulado cuando conservan el mismo RUT.

## Entrega diaria de E.P.P. en Bodega

- Ruta: `/inventory/epp-deliveries`.
- Un EPP puede vincularse a un insumo consumible activo de Inventario/Bodega.
- La disponibilidad, stock mínimo y unidad provienen de Bodega cuando existe ese vínculo.
- Cada acta diaria FO-PREV-03 descuenta el stock dentro de una transacción bloqueada y crea un movimiento de salida auditable con folio y destinatario.
- Los EPP históricos sin vínculo continúan usando su stock anterior para mantener compatibilidad.

Permisos delegables:

- `ver_entregas_epp`: consulta de catálogo, existencias y actas de entrega.
- `registrar_entregas_epp`: consulta operativa y registro diario con descuento de stock.

## Despliegue

La migración `2026_08_25_190000_expand_risk_prevention_committee_accidents_epp.php` es aditiva y su reversa no elimina tablas, columnas, permisos, archivos ni registros. Antes de ejecutarla en producción se debe crear y verificar un respaldo de la base de datos. No se requieren seeders destructivos ni modificación masiva de registros existentes.
