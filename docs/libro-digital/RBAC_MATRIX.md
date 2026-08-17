# Matriz RBAC

## Estado de implementación

Al **2026-08-14** la base local y `LibroDigitalSeeder` están reconciliados en 35 permisos y 11 módulos LCD. La API v1 registra 94 rutas, incluidas seis de importación curricular, con autenticación, permiso de acceso, flags y middleware de correlación/idempotencia; hay policies, Form Requests y pruebas feature parciales. Esto no es autorización completa: faltan pruebas exhaustivas por ruta/rol de permisos, tenencia, IDOR, sensibilidad, concurrencia y segregación. El flujo curricular local fue solicitado por sistema, pero una misma cuenta aprobó y activó; esa excepción no cumple el SoD recomendado de tres actores.

El módulo debe permanecer apagado. Ocultar controles en Vue nunca constituye autorización. Cada operación de servidor debe evaluar conjuntamente:

1. usuario autenticado/activo y permiso exacto;
2. flag global y flag específico de escuela;
3. membresía vigente en `lcd_school_users`;
4. `school_id` y `academic_year_id` del recurso;
5. asignación vigente al grupo, asignatura, estudiante o función;
6. estado permitido, revisión y lock;
7. bloqueo normativo/legal/fiscalizador aplicable;
8. idempotencia y concurrencia en mutaciones;
9. auditoría de la operación crítica.

El seed usa `syncWithoutDetaching`: no retira permisos antiguos o incompatibles. Su ejecución exige revisar un diff de RBAC y, en especial, el backfill `single_school_migration`, que puede vincular todos los usuarios activos a la escuela configurada. No debe ejecutarse en producción sin una política de membresía explícita.

## Permisos realmente sembrados

| Área | Permisos backend | Alcance/control adicional obligatorio |
|---|---|---|
| Entrada | `libro_digital.access` | Escuela/año; no concede datos por sí solo. |
| Libros | `books.view`, `books.manage` | Escuela/año/curso; cerrar o desactivar, nunca borrar historia. |
| Catálogo curricular | `subject_catalog.manage`, `curriculum.import`, `curriculum.approve`, `curriculum.activate` | `ScheduleSubject` global, escuela/año, fuente/manifest, lock y SoD. El solicitante no aprueba/activa; falta impedir aprobador=activador y verificar bytes oficiales. |
| Sesiones | `sessions.view`, `sessions.manage` | Grupo/asignación y vigencia docente. |
| Asistencia/leccionario/firma | `attendance.manage`, `lesson.manage`, `sign` | Sesión asignada; `sign` es solo firma propia y requiere identidad real. |
| Evaluaciones | `assessments.manage` | Asignatura/grupo o función UTP. |
| Convivencia | `coexistence.view`, `coexistence.manage` | Función, caso y confidencialidad. |
| PIE | `pie.view`, `pie.manage` | Equipo/estudiantes asignados y minimización. |
| Retiros/ausencias/parvularia | `withdrawals.manage`, `absence.manage`, `parvularia.manage` | Procedimiento aplicable; parvularia además requiere flag/perfil. |
| Enmiendas | `amendments.request`, `amendments.review`, `amendments.apply` | El flujo probado separa solicitante, revisor y aplicador; registro/revisión exactos. |
| Cierres | `closures.manage`, `closures.reopen` | Periodo/escuela; reapertura motivada y autorizada. |
| Estadísticas | `statistics.view` | Agregación dentro del scope; evitar reidentificación. |
| Reportes | `reports.view`, `reports.export` | Exportar no amplía el universo visible; descarga auditada. |
| EDE/fiscalización | `ede.manage`, `ede.export`, `ede.validate`, `ede.download` | Flags, preflight, fuentes/digest, segregación y aprobación; hoy bloqueados. |
| Auditoría | `audit.view`, `audit.verify` | Escuela; verificación es lectura, no modificación. |
| Configuración | `configuration.manage` | Allowlist, auditoría y doble control para flags regulados. |

Todos llevan prefijo `libro_digital.`. Los slugs anteriores son los implementados; no deben sustituirse en UI o documentación por aliases inventados.

## Capabilities expuestas por la base API

El controlador base deriva actualmente:

| Capability | Permiso usado |
|---|---|
| `can_view_overview` | `access` |
| `can_view_books`, `can_view_subjects` | `books.view` |
| `can_manage_books` | `books.manage` |
| `can_manage_subject_catalog` | `subject_catalog.manage` |
| `can_view_curriculum_imports` | Cualquiera de `curriculum.import`, `curriculum.approve`, `curriculum.activate` o `audit.view` |
| `can_manage_curriculum_imports`, `can_approve_curriculum_imports`, `can_activate_curriculum_imports` | `curriculum.import`, `curriculum.approve`, `curriculum.activate`, respectivamente |
| `can_view_sessions` | `sessions.view` |
| `can_manage_sessions` | `sessions.manage` |
| `can_manage_attendance` | `attendance.manage` |
| `can_manage_closures`, `can_reconcile_attendance` | `closures.manage` |
| `can_manage_lesson` | `lesson.manage` |
| `can_sign` | `sign` |
| `can_manage_assessments` | `assessments.manage` |
| `can_view_pie`, `can_manage_pie` | `pie.view` o `pie.manage`; gestión exige `pie.manage` |
| `can_view_coexistence`, `can_manage_coexistence` | `coexistence.view` o `coexistence.manage`; gestión exige `coexistence.manage` |
| `can_manage_absence` | `absence.manage` |
| `can_view_withdrawals`, `can_manage_withdrawals` | `withdrawals.manage` o permisos compatibles de portería según lectura/registro |
| `can_manage_parvularia` | `parvularia.manage` |
| `can_request_amendments`, `can_review_amendments`, `can_apply_amendments` | Permiso de enmienda correspondiente |
| `can_view_statistics` | `statistics.view` |
| `can_view_reports` | `reports.view` |
| `can_export_reports` | `reports.export` |
| `can_export_ede` | `ede.export` |
| `can_manage_ede`, `can_validate_ede`, `can_download_ede` | `ede.manage`, `ede.validate`, `ede.download`, respectivamente |
| `can_view_audit`, `can_verify_audit` | `audit.view`, `audit.verify`, respectivamente |
| `can_manage_configuration` | `configuration.manage` |

`can_view_compliance` es true si el usuario tiene algún permiso EDE operativo (`export`, `validate`, `download`), `audit.view` o `configuration.manage`. Esta capability solo controla presentación; cada endpoint debe exigir su permiso específico. `is_super_admin` tampoco autoriza a saltar scope, asignación o reglas de firma.

## Roles y permisos del seed

Leyenda: `V` lectura; `G` gestión; `S` firma propia; `A` revisión/aprobación/cierre; `E` reportes/export; `C` EDE/fiscalización; `U` auditoría; `—` no sembrado. El detalle exacto siempre es la lista de permisos anterior y el código del seeder.

| Rol seed | Libro/sesión | Registro docente/asistencia | Especializados | Gobierno | Reportes | EDE | Auditoría |
|---|---|---|---|---|---|---|---|
| `super_admin` | V/G | G/S | Todos | Todos | E | C | U |
| `sostenedor` | V | — | — | — | E | export/download | view |
| `direccion` | V/G | — | — | currículo approve/activate; enmienda review/apply; cierre/reapertura | E | export/validate/download | view |
| `subdirector` | V/G | — | evaluaciones | currículo approve; review/cierre | E | — | view |
| `coordinador_academico` | V/G | — | evaluaciones | currículo import; review/cierre | E | — | — |
| `jefe_utp` | V/G | — | evaluaciones | currículo import; review/cierre | E | — | — |
| `docente` | V/G asignado | asistencia/leccionario/S | evaluaciones | solicita enmienda | view | — | — |
| `profesor_jefe` | V | asistencia | convivencia view | solicita enmienda | view | — | — |
| `educador_parvulos` | V/G asignado | asistencia/leccionario/S | parvularia | solicita enmienda | view | — | — |
| `inspectoria` | V | asistencia | retiros/ausencias | solicita enmienda | view | — | — |
| `coordinador_inspectoria` | V | asistencia | retiros/ausencias | review/cierre | E | — | — |
| `encargado_convivencia` | libro V | — | convivencia V/G | — | E | — | — |
| `coordinador_pie` | libro V | — | PIE V/G | — | E | — | — |
| `profesional_pie` | libro V | — | PIE V/G | — | view | — | — |
| `secretaria` | V | — | — | — | E | — | — |
| `administrador_matricula` | libro V/G | — | — | — | E | — | — |
| `auditor_interno` | V | — | — | — | E | download | view/verify |
| `fiscalizador_consulta` | V | — | — | — | view | download | view |
| `soporte_tecnico_restringido` | — | — | — | — | — | — | view |

Esta tabla refleja lo sembrado, no una decisión final aprobada. Los permisos no expresan por sí solos asignación, confidencialidad ni cuatro ojos.

## Riesgos de la asignación inicial

1. `super_admin` recibe los 35 permisos mediante wildcard del seeder, incluido `sign`. El servicio de firma exige además staff, responsabilidad y asignación vigentes, pero se requiere una denegación explícita de firma por impersonación/administración y pruebas negativas.
2. `sostenedor` y `direccion` reciben generación/descarga EDE; la liberación de un paquete debe ser un caso de uso separado con cuatro ojos, no una consecuencia del permiso aislado.
3. `direccion` reúne revisión y aplicación de enmiendas. Debe impedir aprobar/aplicar la propia solicitud y registrar aprobadores distintos cuando la política lo exija.
4. `syncWithoutDetaching` no revoca grants previos. Un seeder correctivo debe reconciliar de forma auditada los permisos LCD sin afectar módulos ajenos.
5. Los roles creados automáticamente necesitan responsables, vigencia y revisión periódica; existir en BD no prueba que el usuario deba tenerlo.
6. `reports.export`, `ede.download` y vista de auditoría pueden exponer datos masivos; deben conservar el mismo scope y masking de la consulta fuente.
7. Currículo impide que el solicitante apruebe o active, pero `direccion` puede aprobar y activar el mismo lote. El gate recomendado exige un activador distinto del aprobador; hasta implementarlo/probarlo, no usar el flujo en producción.

## Reglas no delegables

### Firma

- nadie —incluidos superadministrador, dirección y soporte— firma en nombre de otro docente;
- el backend compara usuario, `staff_id`, RUN, responsabilidad/asignación vigente, sesión y revisión;
- la impersonación bloquea firma aunque exista `libro_digital.sign`;
- OTP no se comparte, registra, cachea ni reintenta automáticamente;
- break-glass nunca concede firma.

### Historia y aprobaciones

- nadie edita directamente una revisión firmada o cerrada;
- la corrección crea solicitud, decisión y nueva revisión;
- reapertura extraordinaria requiere motivo, autoridad y auditoría;
- un solicitante no decide/aplica su propia enmienda cuando rige segregación;
- una exportación afectada pasa a `stale`; un paquete liberado se revoca, nunca se reescribe.

### Datos sensibles

- PIE, convivencia, parvularia y ausentismo se filtran por función, caso y confidencialidad;
- lectura de reportes no autoriza exportar;
- exportar no amplía el scope ni elimina masking;
- fiscalizador accede solo al paquete expresamente liberado;
- soporte ve metadatos técnicos mínimos; contenido requiere break-glass aprobado.

## Break-glass

El acceso excepcional requiere incidente/ticket, aprobador institucional, motivo, scope, expiración corta, identidad real e impersonada, bloqueo técnico de firma/enmienda/liberación y revisión posterior. El seeder no crea un permiso break-glass LCD específico; antes de ofrecer este flujo se debe integrar al mecanismo institucional existente y probarlo.

## Pruebas obligatorias

- usuario sin `access`, sin permiso específico, inactivo o con flag off;
- escuela A contra recurso/ID/ULID de escuela B;
- membresía vencida, año equivocado y asignación docente ajena/vencida;
- docente con `sign` sobre sesión propia/ajena, bajo impersonación y con RUN no concordante;
- secretaría sin acceso a PIE/convivencia; equipos especializados limitados a sus casos;
- solicitante versus reviewer/applier de la misma enmienda;
- generación, validación y descarga EDE separadas, todas bloqueadas por flags/compliance;
- auditor/fiscalizador solo lectura y paquete de alcance limitado;
- descarga expirada/revocada y reporte que no amplía scope;
- ejecución repetida del seeder con diff de permisos, roles, módulos y membresías;
- 403/404 sin mutación ni filtración de existencia, con auditoría segura cuando corresponda.

El módulo no puede activarse hasta que estas reglas estén materializadas en rutas, servicios/policies/middleware y pruebas feature, no solo en el seed.
