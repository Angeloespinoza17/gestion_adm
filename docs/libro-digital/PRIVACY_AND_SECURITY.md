# Privacidad y seguridad

## Marco temporal

Al 2026-08-12 rige la [Ley 19.628](https://www.bcn.cl/leychile/navegar?idNorma=141599) en la versión consultada. La [Ley 21.719](https://www.bcn.cl/leychile/navegar?i=1209272), promulgada el 2024-11-25 y publicada el 2024-12-13, **entra en vigor el 2026-12-01**. El proyecto debe completar su adecuación antes de esa fecha, no esperar al primer incidente.

También aplica la protección especial de niños, niñas y adolescentes de la [Ley 21.430, artículo 33](https://www.bcn.cl/leychile/Navegar/imprimir?idNorma=1173643&idParte=10317439), además de reglas educacionales y de datos sensibles PIE/salud.

Este documento es una pauta técnica; la base jurídica y los plazos definitivos requieren aprobación institucional/jurídica.

## Riesgo del tratamiento

El LCD trata datos masivos de NNA, asistencia, rendimiento, firmas, convivencia, PIE, posibles datos de salud y seguimiento de trayectoria. Por escala, sensibilidad, perfilamiento/riesgo y efectos educacionales, debe considerarse tratamiento de alto riesgo para fines de diseño.

Antes del piloto con datos reales se requiere una DPIA que cubra:

- finalidades, categorías, titulares, fuentes y destinatarios;
- base jurídica por operación, no un consentimiento genérico;
- necesidad/proporcionalidad y alternativas menos invasivas;
- retención, legal holds y backups;
- riesgos para derechos/interés superior/autonomía progresiva;
- decisiones automatizadas, estadísticas, alertas y revisión humana;
- proveedores/encargados, transferencias, storage e integraciones;
- controles, riesgo residual, responsables y aprobación.

## Inventario y bases

| Finalidad | Datos mínimos | Acceso típico | Nota de base/gobierno |
|---|---|---|---|
| Registro formal escolar | identidad, matrícula, curso, clase, asistencia, evaluación | docente, UTP, inspectoría, dirección | Documentar obligación educacional aplicable; no depender de consentimiento revocable para un registro obligatorio. |
| Firma docente | RUN, asignación, payload hash, resultado técnico | firmante y auditor autorizado | OTP es efímero y no se conserva. |
| PIE/apoyos | estudiante, profesional, objetivo/apoyo, evidencia mínima | equipo PIE asignado | Altamente sensible; separar diagnóstico/expediente clínico del LCD general. |
| Convivencia | hecho, medidas, participantes y reserva | convivencia/dirección autorizados | Minimizar texto libre y acceso; evitar exposición en reportes generales. |
| Ausencia prolongada | fechas, contactos, gestiones, evidencia | inspectoría/dirección | Revisión humana; nunca baja automática. |
| Parvularia | planificación, observación/evaluación, asistencia | educador/equipo asignado | Protección reforzada y perfil normativo específico. |
| Reportes/fiscalización | subconjunto solicitado y manifest | roles autorizados/receptor | Minimización, cuatro ojos y descarga auditada. |
| Seguridad/auditoría | actor, IP cifrada, user-agent hash, acción | seguridad/auditor | Retención y acceso separados del contenido pedagógico. |

## Principios de diseño

- **Licitud y lealtad:** registrar autoridad/finalidad y no reutilizar para fines incompatibles.
- **Finalidad:** cada campo, reporte e integración tiene propósito documentado.
- **Proporcionalidad/minimización:** no exportar diagnósticos/textos completos si basta estado o evidencia mínima.
- **Calidad:** corrección por revisión, procedencia, vigencia y derecho a impugnar.
- **Responsabilidad:** DPIA, inventario, contratos, logs, pruebas y aprobaciones.
- **Seguridad/confidencialidad:** least privilege, cifrado, segregación y respuesta a incidentes.
- **Transparencia:** avisos comprensibles para comunidad educativa sin revelar defensas sensibles.
- **Interés superior de NNA:** revisión humana y prevención de estigmatización.
- **Retención limitada con deber de conservación:** no borrar antes del plazo/hold ni conservar sin razón; el proyecto no automatiza hard-delete.

## Controles de acceso

- backend autoritativo mediante permisos, escuela/año/asignación/función/estado;
- deny-by-default y feature flags apagados;
- aislamiento entre establecimientos probado contra IDOR;
- permisos separados para ver, editar, firmar, aprobar, exportar y descargar;
- PIE/convivencia/parvularia compartimentados;
- reautenticación/MFA para exportación, fiscalización, configuración y break-glass;
- sesión corta y revocación para acciones críticas;
- auditoría de lecturas sensibles y descargas masivas;
- soporte sin acceso cotidiano al contenido; break-glass con ticket, motivo, tiempo y revisión.

Ver [RBAC_MATRIX.md](RBAC_MATRIX.md).

## Cifrado y claves

### En tránsito

- TLS moderno extremo a extremo, redirección HTTPS y cookies `Secure`, `HttpOnly`, `SameSite` apropiado;
- no poner datos sensibles en URLs salvo contrato externo inevitable; en ese caso redacción completa de query;
- verificación de certificados y egress allowlist.

### En reposo

- cifrado de disco/BD y storage privado;
- cifrado de campo para identificadores, IP, PIE, convivencia y textos de alta sensibilidad;
- archivos/reportes/exportaciones cifrados;
- backups cifrados con clave separada;
- hashes no sustituyen cifrado.

`*_encrypted` es una obligación, no evidencia automática. Antes de producción se debe comprobar el cast/servicio de cifrado de cada campo y que consultas, errores y serialización nunca entreguen ciphertext/ruta.

### Claves

- gestor de secretos/KMS, no repositorio/BD/plano;
- separación por ambiente/propósito, rotación y acceso de cuatro ojos;
- versionar referencia de clave para re-cifrado y restore;
- plan de compromiso/rotación y pruebas después de rotar.

## Firma y OTP

- no guardar OTP en texto, cifrado, sesión, cache, job, auditoría ni APM;
- no repetir automáticamente una petición que pudiera consumir el código;
- timestamp RFC 3339 con offset, correlación e idempotencia;
- rate limiting por usuario/IP/entidad sin registrar el código;
- protección de replay y circuit breaker;
- comparar firmante con staff/RUN/asignación/revisión;
- nunca permitir firma durante impersonación o por soporte;
- no usar password local como fallback;
- registrar solo resultado/código sanitizado y hashes necesarios.

El contrato transaccional público usa `rut`, `otp` y `DateWithTimeZone`; el adaptador actual usa esos nombres y tiene una prueba HTTP unitaria. Aún faltan credenciales, enrolamiento, redacción end-to-end y contrato productivo. La ventana de frescura no está documentada públicamente: es blocker hasta aprobar el contrato.

## Archivos, reportes y exportaciones

- detección MIME en servidor, extensión allowlist y límite de tamaño;
- nombre seguro generado; nunca confiar en filename/ruta del cliente;
- escaneo antimalware antes de disponibilidad; `pending` no se descarga;
- storage privado, sin ejecución ni indexación;
- URLs firmadas cortas y autorizadas al momento de descargar;
- PDF con metadata/ID/hash/watermark; validar contenido y diseño;
- prevenir fórmula CSV/XLSX (`=`, `+`, `-`, `@`); los builders implementados anteponen apóstrofo y requieren pruebas de apertura/aceptación;
- desactivar contenido activo/macros y sanitizar documentos;
- manifests sin PII/secretos;
- exportación/descarga auditada y revocable.

## Logging y observabilidad

Nunca registrar:

- OTP, contraseña, token, cookie o cabecera de autorización;
- RUN/IPE completos, teléfonos/correos completos;
- payloads de sesión/asistencia con nombres;
- diagnósticos, PIE, convivencia, restricciones o observaciones parvularia;
- query string del verificador;
- rutas privadas, claves/frases del contenedor;
- archivos o respuestas ministeriales crudas.

Usar correlation ID, códigos de error, IDs públicos no PII, conteos, latencia y hashes truncados cuando sea seguro. Restringir acceso/retención de logs y revisar redacción con tests automatizados.

## Seguridad de aplicación

- validación/Form Requests, mass-assignment restringido y respuestas Resources;
- CSRF para sesión web, CORS allowlist, CSP y escaping contra XSS;
- consultas parametrizadas y scope escolar obligatorio;
- `If-Match`/lock y idempotency key;
- rate limit en login, OTP, reportes, exports y búsquedas;
- ULID público y 404/403 consistentes contra enumeración;
- dependencias/SBOM/secret scan/SAST/SCA y parches;
- contenedor EDE por digest, no root, limits, mounts mínimos y argv allowlist;
- entornos separados, datos sintéticos fuera de producción y egress controlado.

## Derechos de titulares y transparencia

Preparar antes del 2026-12-01:

1. canal autenticado para acceso, rectificación, supresión/oposición, bloqueo/portabilidad cuando procedan;
2. triage de identidad/representación de NNA y autonomía progresiva;
3. búsqueda por sistemas, backups, proveedores y paquetes;
4. respuesta explicable y registro de excepciones legales de conservación;
5. rectificación como revisión, sin falsificar/borrar historia oficial;
6. restricción de tratamiento mientras se resuelve disputa cuando corresponda;
7. SLA y responsables alineados a la ley/procedimiento institucional;
8. aviso de privacidad por audiencia y finalidad.

“Supresión” no autoriza borrar un registro cuya conservación es obligatoria; la respuesta debe explicar base/excepción y aplicar restricción/minimización cuando corresponda.

## Retención

- FAQ EDE: respaldo LCD al menos cinco años;
- perfiles por tipo y fecha de cierre;
- parvularia/ausencias/PIE requieren reglas específicas verificadas;
- legal hold prevalece sobre disposición;
- reportes de elegibilidad, aprobación y evidencia;
- **sin hard-delete automático en producción**: `retention_until` activa revisión/archivo, no borrado;
- backups siguen su calendario y hold; no prometer supresión inmediata imposible.

## Proveedores y transferencias

Antes de usar hosting, email, observabilidad, antivirus, firma o almacenamiento externo:

- due diligence y contrato de encargado/subencargados;
- finalidad/instrucciones, confidencialidad, seguridad, ubicación y transferencias;
- brechas, cooperación en derechos, retorno/eliminación y auditoría;
- acceso técnico mínimo y segregación de ambientes;
- evidencia de backup/restore y terminación.

## DPIA y gate de Ley 21.719

Owner sugerido: responsable institucional de datos/privacidad con dirección, jurídica, seguridad, UTP, PIE y convivencia. Evidencia mínima:

- inventario/ROPA y diagrama de flujo;
- bases jurídicas y avisos;
- matriz de minimización/retención;
- threat model y pruebas;
- contratos/transferencias;
- workflow de derechos y brechas;
- riesgo residual aceptado y fecha de revisión.

Sin DPIA aprobada y acciones críticas cerradas, no activar con datos reales. Revisarla al cambiar EDE, verificador, SIGE, analítica, proveedor, población, datos o finalidad.
