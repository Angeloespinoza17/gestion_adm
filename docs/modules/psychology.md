# Módulo de Psicología Escolar

## Propósito y límites

Este módulo gestiona derivaciones e intervenciones psicoeducativas escolares. No emite diagnósticos automáticos, no recomienda tratamientos clínicos y no sustituye la evaluación profesional. La información se separa en resumen institucional, nota profesional privada, acuerdos y retroalimentación al área derivante.

## Arquitectura implementada

- Laravel 12, Sanctum y RBAC propio (`roles`, `permissions`, `permission_role`).
- Controladores delgados en `app/Http/Controllers/Psychology`.
- reglas de negocio transaccionales en `PsychologyWorkflowService`;
- aislamiento y visibilidad en `PsychologyAccessService` y Policies;
- serialización con API Resources para omitir notas privadas;
- auditoría con redacción de contenido sensible en `psychology_audit_events`;
- archivos en disco privado, nombres UUID, hash SHA-256 y descarga controlada;
- notificaciones `database` con mensajes sin motivo ni identidad de la estudiante;
- exportaciones grandes en cola y recordatorios diarios mediante Jobs;
- Vue 3, Axios, Bootstrap 5, ApexCharts, FullCalendar y pdfmake ya presentes en el proyecto;
- paginación de servidor, búsquedas con debounce y actualización al entrar o después de una acción, sin polling agresivo.

No se agregó ninguna dependencia.

## Entidades y relaciones

```text
users ─┬─< psychology_referrals >─ student_profiles ─< student_enrollments >─ course_sections
       │              │
       │              ├─< psychology_referral_status_history
       │              ├─< psychology_case_assignments
       │              └── psychology_cases
       │                         ├─< psychology_case_assignments
       │                         ├─< psychology_case_participants >─ users
       │                         ├─< psychology_intervention_plans ─< psychology_intervention_plan_versions
       │                         ├─< psychology_activities ─< psychology_activity_addenda
       │                         ├─< psychology_risk_assessments ─< psychology_protective_actions
       │                         ├─< psychology_consents
       │                         ├─< psychology_external_referrals
       │                         ├─< psychology_tasks
       │                         ├─< psychology_documents
       │                         ├─< psychology_shared_feedback
       │                         ├─< psychology_case_closures
       │                         └─< psychology_case_reopenings
       └─< psychology_audit_events / psychology_exports

psychology_catalog_items y psychology_settings configuran el comportamiento del módulo.
```

Se reutilizan `User`, `Staff`, `StudentProfile`, `StudentEnrollment`, `CourseSection`, roles, permisos y notificaciones. No se crean copias de estudiantes, apoderados, inspectoras ni profesionales.

## Flujo de derivación

Estados permitidos:

```text
draft → submitted → under_review → information_requested → submitted
                          ├→ accepted → linked_to_existing_case → completed
                          ├→ redirected → completed
                          ├→ rejected
                          └→ duplicated
draft/information_requested → cancelled
```

Las transiciones viven exclusivamente en `PsychologyWorkflowService::TRANSITIONS`. Enviar genera `PSI-D-AAAA-000000`, fecha, historia, auditoría y notificación. Una derivación enviada no admite actualización común; la respuesta a antecedentes o la decisión crean historia auditable. Inspectoría puede seguir usando Atención rápida: su derivación social existente se conserva y, cuando estas tablas están instaladas, también se crea la derivación especializada de Psicología con referencia de origen única.

## Casos, actividades y riesgo

Un caso se abre desde una derivación aceptada y recibe `PSI-AAAA-000000`. La reasignación finaliza la asignación vigente y agrega una nueva fila; nunca reemplaza el historial. Cerrar exige tipo, motivo y resumen de resultado. Reabrir agrega `psychology_case_reopenings` y conserva todos los cierres.

Las actividades admiten borrador y finalización. Después de finalizar no existe endpoint de edición; toda corrección se registra como adenda. `private_note`, los fundamentos de riesgo y notas internas de derivación usan cast `encrypted` de Laravel.

Una evaluación `critical` exige acción inmediata, responsable, hora y protocolo. La acción se guarda en la cronología de resguardo, eleva la prioridad del caso y genera una alerta segura. La interfaz recuerda expresamente que la clasificación profesional no constituye diagnóstico.

## Visibilidad y seguridad

Niveles: `private_psychology`, `psychology_team`, `interdisciplinary_team`, `referral_feedback` y uso exclusivamente agregado para gestión.

- Inspectoría ve solo derivaciones propias, estado, solicitud de antecedentes y retroalimentación compartida.
- Psicología ve casos asignados o participaciones explícitas.
- Coordinación puede ver, asignar y reportar todo el dominio.
- Dirección recibe agregados; sus listados nominales quedan vacíos y el endpoint nominal devuelve 403.
- El superadministrador no accede al dominio sensible por su privilegio global. `PsychologyAccessService` exige que su rol tenga explícitamente `psychology.sensitive.override`. Este permiso no se asigna automáticamente.
- `psychology.sessions.view_private` también se comprueba como permiso explícitamente unido al rol, evitando la concesión implícita del superadministrador.
- Las respuestas no revelan archivos físicos ni notas privadas no autorizadas.
- Los documentos se validan por tamaño/MIME, bloquean extensiones ejecutables, usan UUID y disco privado. Las descargas y el archivado quedan auditados.
- No se incluyen motivos, relatos ni nombres en notificaciones.
- Las exportaciones nominales requieren permiso, quedan auditadas y se descargan desde almacenamiento privado.

## API

Prefijo autenticado: `/api/psychology`.

- `GET dashboard`, `GET catalogs`, `GET students`
- `GET|POST referrals`, `GET|PUT referrals/{referral}`
- `POST referrals/{referral}/transition|assign|open-case`
- `GET cases`, `GET cases/{case}`
- `POST cases/{case}/assign|close|reopen`
- `POST cases/{case}/activities|plans|risk-assessments|tasks|consents|external-referrals|feedback`
- `POST activities/{activity}/finalize|addenda`
- `POST plans/{plan}/versions`, `PATCH tasks/{task}`, `POST risk-assessments/{risk}/acknowledge`
- `POST documents`, `GET|DELETE documents/{document}`
- `GET calendar`, `GET reports`, `GET reports/export.csv`
- `POST exports`, `GET exports/{export}`, `GET exports/{export}/download`
- `POST configuration/catalogs`, `PUT configuration/settings`, `GET audit`

Las rutas exactas y middleware se encuentran en `routes/psychology.php`.

## Interfaz

La ruta `/psychology` contiene dashboard, bandeja de derivaciones, formulario responsable, bandeja/lista de casos, ficha con doce pestañas, actividades, riesgos, tareas, agenda, alertas, reportes, configuración y auditoría. El dashboard usa ApexCharts; la agenda usa FullCalendar; PDF usa pdfmake. Excel se entrega como libro HTML `.xls` compatible y CSV nominal se transmite por lotes. Los listados muestran skeleton, estado vacío, errores, filtros, debounce y paginación.

El menú dinámico registra un contenedor `psychology` y nueve vistas hijas en `system_modules`. Cada vista se asocia en `role_system_module` solo a los roles que pueden utilizarla: Inspectoría recibe Resumen/Derivaciones; Dirección recibe Resumen/Reportes; Psicología y Convivencia reciben sus vistas operativas; Coordinación recibe todas. Configuración y Auditoría quedan limitadas a Coordinación y administración técnica. El menú interno repite esta decisión usando las capacidades devueltas por la API.

## Permisos por rol

- `inspectoria`: `psychology.access`, `psychology.referrals.create`, `psychology.referrals.view_own`, `psychology.documents.upload`.
- `psicologo`: acceso, gestión de derivación asignada, caso asignado, sesiones/notas privadas, riesgo, documentos, cierre y reporte agregado.
- `coordinador_psicologia`: todos los permisos funcionales salvo `psychology.sensitive.override`.
- `convivencia_escolar`: acceso agregado y casos donde sea participante/autorizado.
- `direccion`: `psychology.access` y `psychology.reports.aggregate` únicamente.
- `super_admin`: administración técnica por RBAC global, pero sin contenido sensible hasta asignar explícitamente `psychology.sensitive.override` a su rol.

El listado canónico de 23 permisos está en la migración del módulo.

## Instalación y operación

Variables opcionales:

```dotenv
PSYCHOLOGY_TIMEZONE=America/Santiago
PSYCHOLOGY_DISK=local
PSYCHOLOGY_MAX_FILE_KB=10240
```

Comandos:

```bash
php artisan migrate
php artisan db:seed --class=PsychologyDemoSeeder
php artisan test tests/Feature/Psychology/PsychologyModuleTest.php
npm run test:unit -- tests/frontend/psychology-ui.test.js
npm run prod
php artisan queue:work
php artisan schedule:work
```

El seeder de demostración se bloquea en producción y requiere una estudiante activa y usuarios existentes con roles `inspectoria` y `psicologo`.

## Inventario exacto de implementación

Archivos creados:

```text
app/Events/Psychology/PsychologyRecordChanged.php
app/Http/Controllers/Psychology/{PsychologyCaseController,PsychologyCatalogController,PsychologyConfigurationController,PsychologyDashboardController,PsychologyDocumentController,PsychologyReferralController,PsychologyReportController,PsychologyWorkflowController}.php
app/Http/Requests/Psychology/{AssignPsychologyRequest,OpenPsychologyCaseRequest,SavePsychologyActivityRequest,SavePsychologyReferralRequest,TransitionPsychologyReferralRequest,UploadPsychologyDocumentRequest}.php
app/Http/Resources/Psychology/{PsychologyCaseResource,PsychologyReferralResource}.php
app/Jobs/Psychology/{GeneratePsychologyExport,NotifyPsychologyDeadlines}.php
app/Models/Psychology/{PsychologyActivity,PsychologyActivityAddendum,PsychologyCase,PsychologyCaseAssignment,PsychologyCaseClosure,PsychologyCaseReopening,PsychologyCatalogItem,PsychologyConsent,PsychologyDocument,PsychologyExport,PsychologyExternalReferral,PsychologyInterventionPlan,PsychologyInterventionPlanVersion,PsychologyProtectiveAction,PsychologyReferral,PsychologyReferralStatusHistory,PsychologyRiskAssessment,PsychologySharedFeedback,PsychologyTask}.php
app/Notifications/Psychology/PsychologySafeNotification.php
app/Policies/{PsychologyCasePolicy,PsychologyDocumentPolicy,PsychologyReferralPolicy}.php
app/Services/Psychology/{PsychologyAccessService,PsychologyAuditService,PsychologyNotificationService,PsychologyWorkflowService}.php
config/psychology.php
database/factories/Psychology/{PsychologyCaseFactory,PsychologyReferralFactory}.php
database/migrations/2026_08_17_200000_create_psychology_module.php
database/migrations/2026_08_17_210000_register_psychology_views.php
database/seeders/PsychologyDemoSeeder.php
resources/js/components/psychology/{PsychologyBadge,PsychologyCases,PsychologyDashboard,PsychologyOperations,PsychologyReferrals}.vue
resources/js/composables/usePsychology.js
resources/js/views/psychology/index.vue
routes/psychology.php
tests/Feature/Psychology/PsychologyModuleTest.php
tests/frontend/psychology-ui.test.js
docs/modules/psychology.md
```

Archivos de integración modificados:

```text
app/Console/Kernel.php
app/Providers/AuthServiceProvider.php
app/Services/Inspectoria/InspectoriaPsychosocialReferralService.php
resources/js/components/horizontal-menu.js
resources/js/components/menu.js
resources/js/router/index.js
routes/api.php
tests/Feature/Inspectoria/InspectoriaModuleTest.php
```

`public/build` contiene artefactos generados por Vite y no forma parte del código fuente del inventario.

## Pruebas

La suite backend cubre creación/envío, aislamiento de derivaciones, inmutabilidad tras envío, asignación, apertura de caso, ocultamiento de nota privada, validación crítica, documento privado/auditoría, reporte de Dirección, cierre y reapertura. Vitest cubre etiquetas/estados, capa API paginada y errores uniformes.

## Rendimiento

Los índices cubren estudiante/estado, colas por prioridad, asignado/estado, carga profesional, inactividad, fechas de actividad y vencimientos. Los listados se paginan; la exportación síncrona usa `chunkById` y la alternativa grande usa `GeneratePsychologyExport`. El dashboard usa consultas agregadas y carga relaciones selectivas.

## Limitaciones reales

- El proyecto actual no posee `school_id`/`organization_id`; el módulo sigue el alcance institucional único existente. Si se agrega multiestablecimiento, las tablas y scopes deberán incorporar esa clave antes de habilitar múltiples colegios.
- No se agregó una librería PHP de XLSX. La interfaz exporta `.xls` compatible, CSV y PDF agregado; exportaciones nominales grandes se encolan como CSV privado.
- El PDF se genera en el cliente con los datos ya autorizados. Para PDF masivo en servidor se requerirá adoptar una librería PDF PHP institucional.
- Aunque Reverb/Echo está instalado, no se emiten eventos sensibles por broadcasting hasta contar con canales privados de Psicología y revisión formal de autorización. Las vistas refrescan al entrar y tras acciones, sin polling.
- Profesor jefe se obtiene hoy mediante la convención disponible en el contexto escolar; el proyecto no expone una relación canónica curso–profesor jefe para todos los cursos.
