# Matriz normativa y de controles

## Leyenda

| Estado | Significado |
|---|---|
| `IMPLEMENTADO` | Existe control ejecutable en backend y estructura necesaria; aún puede requerir integración/prueba de aceptación. |
| `PARCIAL` | Existe esquema, modelo, servicio o UI, pero falta parte del flujo servidor, autorización o evidencia. |
| `DISEÑADO` | El control está documentado o representado en esquema/UI, pero no es ejecutable de extremo a extremo. |
| `BLOQUEADO` | No puede activarse por falta de fuente, contrato, versión, credencial o evidencia oficial. |
| `NO IMPLEMENTADO` | No existe todavía el control de aplicación. |

La columna “Fuente” enlaza a evidencia oficial. Esta matriz no sustituye la revisión jurídica del establecimiento.

## Registro formal y continuidad histórica

| Requisito verificable | Fuente | Control esperado | Estado al 2026-08-13 | Evidencia/criterio de cierre |
|---|---|---|---|---|
| El LCD es opcional; si se adopta se usa papel **o** digital para fiscalización, no ambos. | [FAQ EDE](https://www.ede.mineduc.cl/faq) | `source_format`, decisión institucional fechada y preflight antes de abrir. | `PARCIAL` | `lcd_books.source_format` existe; falta caso de uso de apertura y aprobación institucional. |
| Cambio de papel a digital durante el año exige traspaso previo de información. | [FAQ EDE](https://www.ede.mineduc.cl/faq) | Estado `previous_records_transfer_status`, conciliación y acta/hash de transferencia. | `PARCIAL` | Campo existe; falta importador, workflow, reporte y prueba de traspaso. |
| Contenido mínimo: antecedentes, asignaturas, asistencia, evaluaciones, convivencia y aula/PIE. | [FAQ EDE](https://www.ede.mineduc.cl/faq) | Módulos operacionales vinculados al libro y año. | `PARCIAL` | Esquema/UI y API base existen para estos dominios; faltan matriz RBAC/UAT y reglas especializadas completas. |
| Registrar acciones de ingreso, modificación y eliminación. | [FAQ EDE](https://www.ede.mineduc.cl/faq) | Auditoría append-only con actor, contexto, revisión, hashes y correlación. | `PARCIAL` | Writer/verificador, comando, API general y vista allowlisted/redactada por entidad existen; falta cobertura total, permisos DB append-only, alerta y prueba destino. |
| Exportar la información conforme al estándar aplicable. | [FAQ y desarrolladores EDE](https://www.ede.mineduc.cl/desarrolladores/libro-de-clases-digital) | Proyección versionada separada, archivos y manifiesto. | `BLOQUEADO` | Importador local cifrado, proyector/job/staging existen; no hay versión oficial importada/activada, digest/argv, snapshot consistente, formato contenedor probado ni validador ejecutado. |
| Conservar respaldo LCD por al menos cinco años. | [FAQ EDE](https://www.ede.mineduc.cl/faq) | Perfil de retención, `retention_until`, backup cifrado, legal hold y no borrado automático. | `PARCIAL` | Schema/resolver existen; falta perfil aprobado y restauración evidenciada. |
| El proveedor no puede afirmar certificación MINEDUC. | [FAQ EDE](https://www.ede.mineduc.cl/faq) | Textos de UI/documentos restringidos; “validado” solo con reporte oficial. | `DISEÑADO` | Revisar copies y paquetes; hoy debe mostrar “no validado/no certificado”. |

## Matrícula, nómina y asistencia

| Requisito verificable | Fuente | Control esperado | Estado | Evidencia/criterio de cierre |
|---|---|---|---|---|
| La nómina usada en una sesión no cambia retroactivamente por altas, cambios o retiros posteriores. | Circular de registros y finalidad del LCD; [índice oficial](https://www.ede.mineduc.cl/informaci%C3%B3n-normativa) | Enlaces con vigencia, snapshot sellado por sesión y hash por ítem. | `PARCIAL` | Creación de libro sella nómina y el retiro/retorno conserva snapshots sin mutar matrícula; hay pruebas parciales. Falta cubrir todos los movimientos retroactivos y el motor objetivo. |
| La asistencia no usa `null` como estado implícito. | Control de calidad del diseño; estados requeridos por el registro | Valores explícitos y completitud contra snapshot. | `IMPLEMENTADO` en servicio de sesión | `SessionAttendanceService` admite `present`, `absent`, `late`, `left_early`, `not_applicable`; controlador adapta el contrato. Faltan pruebas feature y reglas diaria/subvención. |
| Un atraso registra hora de llegada. | Registro operativo | Validación `late => arrival_at`. | `IMPLEMENTADO` en base API | Hay pruebas de sesión y atraso parvulario factual; la clasificación regulatoria parvularia permanece bloqueada sin regla oficial. |
| Una justificación es separada y no convierte automáticamente una ausencia. | Circular/registros y trazabilidad | Entidad de justificación con vigencia, documento, estado y validador. | `PARCIAL` | Tabla/modelo existen; falta workflow especializado. |
| Asistencia por bloque, diaria oficial, subvención y SIGE se mantienen separadas. | [DFL 2/1998, art. 13](https://www.bcn.cl/leychile/navegar?idNorma=127911) y Circular 30 | Resolvers y cierres separados; no inferir regla de atraso. | `PARCIAL/BLOQUEADO` | Existen servicio de sesión, cierres API, resolver diario fail-closed, resolver de subvención bloqueado y conciliación manual probada como no oficial. Faltan UAT/reglas finas. |
| La subvención se basa en promedio de asistencia registrada por curso. | [DFL 2/1998, art. 13](https://www.bcn.cl/leychile/navegar?idNorma=127911) | Cálculo solo bajo política oficial vigente y reconciliada. | `BLOQUEADO` | El DFL no define la hora operativa; no activar derivación hasta verificar instrucciones completas. |
| Un cierre diario/mensual conserva snapshot/hash y no se sobrescribe. | Requisitos de integridad del registro | Nuevas revisiones y reapertura autorizada. | `PARCIAL` | Endpoints crean cierre hashado, bloquean duplicado y una enmienda con tres actores reabre/versiona/recierra derivados en prueba. Faltan regla oficial, matriz completa y UAT. |

## Firma docente

| Requisito verificable | Fuente | Control esperado | Estado | Evidencia/criterio de cierre |
|---|---|---|---|---|
| Docente/verificador se registra y usa autenticador. | [Usuarios EDE](https://www.ede.mineduc.cl/usuarios) | Asignación docente vigente, identidad correspondiente y adaptador oficial. | `BLOQUEADO` | Sin credenciales/verificador productivo ni flujo API autorizado. |
| Contrato transaccional usa endpoint y parámetros publicados. | [Validación transaccional](https://www.ede.mineduc.cl/desarrolladores/validaci%C3%B3n-transaccional) | Adaptador versionado; RFC 3339 con offset; respuesta booleana. | `PARCIAL` | Adaptador usa `rut`, `otp` y `DateWithTimeZone`, sin retry, y tiene prueba HTTP. Faltan URL/credenciales reales, enrolamiento y contrato productivo. |
| Contrato masivo conserva campos `RUT`, `OTP`, `TIMESTAMP`. | [Validación masiva](https://www.ede.mineduc.cl/desarrolladores/validaci%C3%B3n-masiva) | Adaptador separado y versionado. | `PARCIAL` | Existe `MineducBulkIdentityVerifier`, pero no tiene prueba contractual/integración productiva ni credenciales. |
| OTP no se persiste ni aparece en logs/errores/telemetría. | Recomendación de seguridad derivada del contrato sensible | Uso efímero, redacción de URL, rate limit y sin retry automático. | `PARCIAL` | Servicio/adaptadores no persisten OTP; hay rate limit, correlación anti-replay y circuit breaker. Falta probar redacción en HTTP/APM/logs y acordar la ventana temporal. |
| No existe fallback local de firma. | Política de integridad del módulo | Si el verificador falla, queda pendiente y no cierra. | `IMPLEMENTADO` en servicio/política | `DisabledIdentityVerifier` retorna indisponible, la política prohíbe fallback y `TeacherSignatureService` libera el claim sin crear firma. Falta API/prueba end-to-end productiva. |
| Una revisión firmada es inmutable; la corrección crea revisión y, si procede, nueva firma. | Requisitos de registro/auditoría | Máquina de estados, hash canónico, enmienda/aprobación. | `PARCIAL` | API/servicio tienen allowlist, segregación en tres actores, revisión append-only y reapertura de cierres probadas para sesión. Falta nueva firma productiva y cobertura de todos los agregados/carreras. |

## Evaluación, PIE y parvularia

| Requisito verificable | Fuente | Control esperado | Estado | Evidencia/criterio de cierre |
|---|---|---|---|---|
| Evaluación institucional objetiva y transparente, formativa/sumativa. | [Decreto 67/2018](https://www.bcn.cl/leychile/Navegar?idNorma=1127255) | Esquemas versionados, periodos, instrumentos, resultados y cierres. | `PARCIAL` | API base de evaluaciones/resultados/cierre inmutable y prueba existen; faltan reglamento versionado, cálculos aprobados y UAT. |
| En básica/media del ámbito de D67, nota final anual 1,0–7,0, un decimal, aprobación 4,0. | [Decreto 67/2018](https://www.bcn.cl/leychile/Navegar?idNorma=1127255) | Perfil de calificación válido; escalas internas no sustituyen acta final. | `DISEÑADO` | `lcd_grading_schemes` es configurable; no hay seed/regla de cierre D67 validada. |
| No eximir de asignaturas/módulos; diversificar/adecuar según D83/D170. | [D67](https://www.bcn.cl/leychile/Navegar?idNorma=1127255), [D83](https://www.bcn.cl/leychile/Navegar?idNorma=1074511), [D170](https://www.bcn.cl/leychile/navegar?idNorma=1012570) | Resultados/adecuaciones trazables; evitar que `exempt` habilite una exención prohibida. | `BLOQUEADO` | El campo `exempt` existe y requiere semántica/regla jurídica antes de exponerlo. |
| PIE registra apoyos autorizados y protege antecedentes sensibles. | [Registro PIE MINEDUC](https://especial.mineduc.cl/implementacion-dcto-supr-no170/registro-planificacion-pie/) | Acceso por asignación/función, campos cifrados y vista mínima. | `PARCIAL` | API base cifra/revisiona y tiene prueba de nómina; faltan RBAC fino, workflow integral y pruebas exhaustivas de no divulgación. |
| Currículo/OA proviene de fuente oficial versionada y aplicable al ciclo/modalidad. | [Currículum Nacional](https://www.curriculumnacional.cl/curriculum/cursos-y-niveles), [actos y artefactos por ciclo](NORMATIVE_SOURCES.md#currículo-oficial-por-ciclo) | Contrato `NT1`, `NT2`, `1B`–`8B`, `1M`–`4M`; documento/hash real por `source_key`; exactamente un `canonical_text` por objetivo; fundamento/modificaciones N:M; lote reproducible; revisión y activación separadas; no códigos/textos inventados. | `PARCIAL/BLOQUEADO` | Plantilla de cinco hojas, lector/validador multisource, adjuntos oficiales cifrados/hashados, servicio y seis rutas existen. La activación debe fallar ante evidencia referenciada faltante o hash divergente. Falta importar/reconciliar/aprobar el corpus oficial NT1–4M, completar ARTISTICA/TP/NT, SoD/UAT y el corte final de pruebas; una activación sintética no basta. Aplican todos los criterios del [runbook](CURRICULUM_IMPORT_RUNBOOK.md). |
| Parvularia usa perfil específico vigente desde 2026-03-01. | [REX 700/2025](https://www.diariooficial.interior.gob.cl/publicaciones/2025/11/29/44312/01/2733475.pdf) | Perfil separado, flags, planificación/evaluación y reglas propias. | `PARCIAL/BLOQUEADO` | API base de libro/plan/evaluación/atraso falla cerrada y prueba hecho sin clasificación; acto íntegro, OA, asistencia y retención específica no están verificados. |
| Planificación parvularia se vincula a Bases Curriculares. | [Decreto 481/2018](https://www.bcn.cl/leychile/Navegar?idNorma=1114961) y [BCEP UCE](https://www.curriculumnacional.cl/recursos/educacion-parvularia-vigentes-2019) | OA/OAT versionados y snapshots en planificación/evaluación; `NT1`/`NT2` mapean al tramo oficial `NT` sin crear objetivos divergentes. | `BLOQUEADO` | Catálogo oficial OA/OAT pendiente; el contrato está en [CURRICULUM_CATALOG_CONTRACT.md](CURRICULUM_CATALOG_CONTRACT.md). |

## Ausencias prolongadas y baja de matrícula

| Requisito verificable | Fuente | Control esperado | Estado | Evidencia/criterio de cierre |
|---|---|---|---|---|
| Existe causal excepcional por ausencia continua e imposibilidad de ubicar tutores. | [REX 432/2023](https://www.diariooficial.interior.gob.cl/publicaciones/2023/10/28/43687/01/2396727.pdf) | Caso, contactos/evidencias, responsable, fundamento y resolución administrativa. | `PARCIAL` | Tablas/modelos/UI base; falta procedimiento completo y aprobación. |
| El procedimiento debe estar regulado en Reglamento Interno. | [REX 432/2023](https://www.bcn.cl/leychile/navegar?i=1197327) | Configuración institucional versionada; no baja automática. | `BLOQUEADO` | Debe adjuntarse reglamento vigente y acto íntegro; no hay motor de plazos aprobado. |
| La baja no borra el historial ni reescribe asistencia. | Integridad del registro | Estado/resolución humana y snapshots históricos. | `DISEÑADO` | FK restrict, snapshots y no-delete; falta caso de uso y prueba end-to-end. |
| Plazos exactos del proceso. | REX 432 íntegra, aún no archivada | Reglas versionadas por fecha, sin hardcode provisional. | `BLOQUEADO` | No se aceptan como verificados los plazos 20+10+10/40 obtenidos de fuentes secundarias. |

## EDE, fiscalización y SIGE

| Requisito verificable | Fuente | Control esperado | Estado | Evidencia/criterio de cierre |
|---|---|---|---|---|
| EDE se versiona y el mapeo no se implementa con números mágicos dispersos. | [Desarrolladores EDE](https://www.ede.mineduc.cl/desarrolladores) y [mapeo oficial](https://docs.google.com/spreadsheets/d/1W5JNVZmO2_kYjvSU8zRQF-lMBCxDdvxguVobTQ0Jd-w/edit) | Tablas de versión/mapping, importador y transformaciones declarativas. | `PARCIAL/BLOQUEADO` | Endpoint/comando importan local, cifran/hashean y HTTP solo deja `imported`; proyector declarativo existe. Falta importación/activación oficial, snapshot consistente y resolver contradicción 43/55. |
| El contenedor ejecuta operaciones oficiales publicadas. | [Contenedor EDE](https://www.ede.mineduc.cl/desarrolladores/contenedor) y [repo](https://github.com/Admin-EDE/DockerEdeCode) | `parse`, `insert`, `check` por argv allowlist, sin privilegios, límites y digest. | `BLOQUEADO` | Existe runner endurecido, pero digest/argv/contrato no están fijados y nunca se ha ejecutado. |
| Solo una corrida oficial exitosa permite estado validado. | [Contenedor EDE](https://www.ede.mineduc.cl/desarrolladores/contenedor) | Exit code, reporte, hash, versión/digest y cero errores críticos. | `BLOQUEADO` | Validador no ejecutado; `validator_status` debe permanecer `not_run`. |
| Paquete de fiscalización conserva alcance, manifiesto, archivos y hashes. | Requisitos de exportación EDE y fiscalización | Snapshot consistente, cuatro ojos, descarga auditada, revocación/stale. | `DISEÑADO` | Schema/UI existe; generador/approval/download backend ausentes. |
| No scraping ni API SIGE simulada. | Ausencia de contrato oficial verificado | Driver deshabilitado; solo archivo/manual auditado. | `PARCIAL` | Existen `DisabledSigeGateway` y endpoint de conciliación manual probado como hashado/no oficial. Falta aprobación/UAT; mantener `disabled` hasta contrato o flujo manual aprobado. |

## Privacidad, seguridad y conservación

| Requisito verificable | Fuente | Control esperado | Estado | Evidencia/criterio de cierre |
|---|---|---|---|---|
| Tratar datos personales/sensibles con finalidad, confidencialidad y seguridad. | [Ley 19.628](https://www.bcn.cl/leychile/navegar?idNorma=141599) | Clasificación, acceso mínimo, cifrado, logs redactados, contratos y retención. | `PARCIAL` | Nombres de campos y cifrado de auditoría existen; faltan policies y verificación de cifrado de todos los campos. |
| Proteger especialmente los datos de NNA. | [Ley 21.430, art. 33](https://www.bcn.cl/leychile/Navegar/imprimir?idNorma=1173643&idParte=10317439) | Minimización, vistas funcionales, control de descargas y acceso por necesidad. | `DISEÑADO` | Falta DPIA/RBAC y pruebas de aislamiento. |
| Ley 21.719 entra en vigor el 2026-12-01. | [Ley 21.719](https://www.bcn.cl/leychile/navegar?i=1209272) | DPIA previa a alto riesgo, gobierno de derechos, brechas, encargados y responsabilidad demostrada. | `BLOQUEADO` para producción | DPIA, inventario, bases jurídicas, contratos y procedimiento de derechos pendientes. |
| Archivos privados se validan, cifran, escanean y descargan con autorización. | Principios de seguridad y privacidad | MIME servidor, tamaño, hash, malware scan, storage privado y URL temporal. | `PARCIAL` | `PrivateAttachmentService` valida MIME/tamaño, hashea y cifra; falta antivirus efectivo, endpoints/descarga autorizada, expiración y pruebas. |
| Migraciones/rollback no eliminan registros oficiales. | Requisito del proyecto y deber de conservación | `down()` no-op, FK restrict/null y rollback de código/flags. | `IMPLEMENTADO` en las once migraciones LCD declaradas | `000009`–`000011` aún están pendientes en la base local; la suite efímera pasa, pero se deben verificar backfill/índices/FK en staging/motor objetivo y prohibir `migrate:rollback` amplio en producción. |
| Vencimiento de retención no produce borrado automático. | Decisión de seguridad/conservación del proyecto | Reporte, archivo, legal hold y proceso jurídico separado. | `PARCIAL` | Existe `lcd:retention:report`, solo lectura; no existe job de borrado y debe mantenerse así. Falta gobernanza de legal hold/disposición. |

## Gates de conformidad

No se permite marcar el módulo como listo para uso oficial hasta que:

- cada fila `BLOQUEADO` aplicable al alcance activado esté cerrada con evidencia;
- las filas `PARCIAL` críticas tengan API, autorización, auditoría y pruebas de aceptación;
- la fuente normativa importada registre URL, fecha de consulta y hash real cuando haya archivo;
- el resultado de preflight se archive con `ready=true` y sin excepciones manuales ocultas;
- la dirección, cumplimiento, seguridad y privacidad firmen la aceptación del perfil aplicable.
