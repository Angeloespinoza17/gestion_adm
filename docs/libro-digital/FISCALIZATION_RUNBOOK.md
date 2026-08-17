# Runbook de fiscalización

## Estado operativo

> La descarga de fiscalización está deshabilitada (`lcd_fiscalization_download_enabled=false`). Existe esquema para paquetes y una UI prevista, pero no hay un generador/liberador completo ni una exportación EDE validada. No se debe entregar un “paquete oficial” usando archivos parciales o reportes operacionales.

Un reporte PDF/XLSX/CSV/JSON del módulo puede apoyar la revisión interna, pero no sustituye el paquete EDE ni demuestra validación oficial.

## Principios

- alcance mínimo necesario y expresamente solicitado;
- integridad reproducible mediante snapshots, manifiesto y SHA-256;
- cuatro ojos para generar/liberar cuando el riesgo lo justifique;
- acceso temporal, privado y auditado;
- no modificar un paquete liberado; solo revocar o emitir nueva versión;
- no entregar diagnósticos, PIE, convivencia u otros datos sensibles fuera del alcance;
- nunca afirmar certificación del software.

## Roles

| Actor | Acción |
|---|---|
| Coordinador de respuesta | Registra requerimiento, plazo, autoridad y alcance. |
| Dueño de datos/dirección | Confirma legitimidad, alcance y responsable institucional. |
| Operador | Genera desde snapshots; no libera unilateralmente. |
| Cumplimiento/privacidad | Revisa base, minimización, bloqueos y datos sensibles. |
| Auditor/aprobador | Verifica hashes, reporte EDE, cadena y manifiesto. |
| Receptor autorizado | Descarga el paquete aprobado dentro de la ventana. |

## 1. Registrar requerimiento

Conservar en un expediente privado:

- organismo/funcionario requirente y canal verificado;
- número de oficio/expediente, fecha/hora y plazo;
- establecimiento/RBD, año, cursos, periodos y tipos de registro solicitados;
- fundamento y restricciones de entrega;
- contacto institucional responsable;
- nivel de confidencialidad y destinatarios;
- legal hold asociado, si corresponde.

Verificar el requerimiento por un canal institucional independiente ante señales de phishing o cambio de destinatario.

## 2. Congelar alcance, no la operación completa

- Identificar revisiones firmadas/cerradas incluidas.
- Crear snapshot consistente y hash del alcance.
- Bloquear o advertir enmiendas concurrentes sobre ese alcance mediante workflow; no congelar innecesariamente otros cursos.
- Registrar toda enmienda posterior y marcar paquetes afectados `stale`.
- Activar legal hold para las evidencias relacionadas cuando exista modelo/proceso aprobado. Hoy el módulo no tiene tabla de legal hold; administrar el hold externamente y documentar el blocker.

## 3. Ejecutar preflight

- [ ] requerimiento autenticado y alcance aprobado;
- [ ] usuario operador con escuela/año correctos;
- [ ] perfiles normativos y fuentes registrados;
- [ ] libros/sesiones/nóminas/cierres íntegros;
- [ ] cadena de auditoría válida;
- [ ] enmiendas y reaperturas explicadas;
- [ ] archivos escaneados y hashes vigentes;
- [ ] EDE validado si el requerimiento lo exige;
- [ ] no hay blockers ocultos o aceptaciones verbales;
- [ ] backup recuperable y espacio privado disponible.

Si el requerimiento exige EDE, seguir [EDE_EXPORT_RUNBOOK.md](EDE_EXPORT_RUNBOOK.md). Hoy ese gate no puede completarse.

## 4. Construir paquete

Crear `lcd_fiscalization_packages` con estado `queued` y `scope_snapshot`. El generador futuro debe incluir solo componentes autorizados:

- manifiesto canónico;
- exportación EDE y reporte de validador, cuando existan;
- reportes operacionales solicitados con marca de borrador si no están cerrados;
- historial de revisiones/enmiendas aplicable;
- verificación de cadena de auditoría;
- índice de archivos y hashes;
- nota de blockers/warnings y alcance excluido.

No incluir:

- OTP, tokens, cookies, contraseñas o claves;
- rutas físicas, stack traces o logs crudos;
- RUN completos en el manifiesto;
- datos de otros establecimientos/cursos;
- antecedentes sensibles no solicitados;
- archivos temporales o fallidos.

## 5. Manifiesto

| Campo | Requisito |
|---|---|
| Identidad | `package_id`, request/expedient ID, RBD, escuela, año y alcance. |
| Procedencia | perfil normativo, fuentes/fechas/hashes, versión de aplicación/commit. |
| Snapshot | ID/hash fuente y revisiones incluidas. |
| EDE | versión, mapping, digest, ejecución/resultado del validador. Si no se ejecutó, decir `not_run`. |
| Archivos | nombre lógico, MIME, tamaño y SHA-256 por archivo. |
| Aprobación | generador, revisores, timestamps y decisiones. |
| Advertencias | borrador, stale, exclusiones y blockers. |
| Integridad | hash del manifiesto y del paquete final. |

## 6. Revisión de cuatro ojos

El aprobador, distinto del operador cuando sea posible:

1. recalcula todos los hashes desde los archivos almacenados;
2. compara scope con requerimiento;
3. abre/renderiza muestras de PDF y valida XLSX/CSV/JSON;
4. revisa errores/warnings EDE y estado `validated` real;
5. verifica cadena de auditoría;
6. busca PII/secretos no requeridos;
7. confirma marca de borrador y revisiones;
8. aprueba o rechaza con motivo firmado/auditado.

No se permite aprobar la propia generación como único control, ni liberar con `validator_status=not_run` cuando EDE sea requerido.

## 7. Liberar y entregar

- Cambiar a `released` solo por caso de uso autorizado.
- Generar URL firmada de corta duración, de un solo propósito y ligada al receptor cuando la infraestructura lo permita.
- Exigir autenticación/segundo factor para la descarga de alto riesgo.
- Entregar hash del paquete por canal separado.
- Registrar actor, receptor, IP protegida, user-agent hash, timestamp, resultado y correlation ID.
- Configurar límite de intentos y revocación.
- Nunca enviar adjuntos sensibles por correo sin canal/controles aprobados.

## 8. Verificar recepción y cerrar

- Confirmar descarga/recepción por canal verificado.
- Revocar enlace temporal.
- Registrar archivos exactos y hash aceptado por el receptor.
- Liberar bloqueo operacional manteniendo legal hold cuando aplique.
- Conservar el expediente, paquete y auditoría conforme al perfil; no borrar al expirar el link.
- Registrar lecciones/incident response si hubo error.

## Revocación o paquete stale

Revocar cuando:

- se detecta error o exposición excesiva;
- cambia una revisión incluida;
- se compromete el destinatario o enlace;
- el validador/digest era incorrecto;
- el requerimiento fue cancelado o corregido.

Acciones:

1. marcar `revoked_at` y motivo, sin eliminar el paquete;
2. invalidar URLs y notificar al receptor;
3. evaluar incidente/brecha;
4. generar otro paquete desde nuevo snapshot si corresponde;
5. conservar trazabilidad de ambos.

## Entrega urgente con EDE bloqueado

La urgencia no cierra un blocker. Si la autoridad solicita información antes de disponer de EDE validado:

- dirección/cumplimiento determinan el canal y formato admisible;
- se entrega solo un reporte operacional claramente rotulado como tal;
- el manifiesto declara “validación EDE oficial no ejecutada”;
- no se usa la palabra “validado”, “certificado” o “paquete EDE”;
- se documenta alcance, decisión y riesgo aceptado.

## Evidencia mínima de cierre

- requerimiento y autenticación del canal;
- scope/snapshot y hashes;
- preflight;
- paquete/manifiesto/reporte del validador si aplica;
- aprobaciones y segregación;
- logs de descarga sanitizados;
- confirmación del receptor;
- revocaciones/incidentes;
- verificación posterior de integridad.
