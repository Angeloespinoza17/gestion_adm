# Módulo de Trabajo Social

## Objetivo y alcance

Este módulo centraliza la gestión social escolar sin duplicar estudiantes, matrículas, cursos, asistencia, atrasos, anotaciones, PIE, convivencia ni salud. Los antecedentes narrativos se consideran restringidos y los documentos se guardan en el disco privado `local`.

## Arquitectura

- Laravel 12: controladores HTTP delgados, Form Requests, Resource, Policy y servicios transaccionales en `App\Services\SocialWork`.
- Vue 3 Composition API: espacio de trabajo responsive en `/social-work`, componentes de riesgo y medidas de protección, y composable HTTP común.
- Autenticación: Laravel Sanctum.
- Autorización: permisos RBAC existentes más visibilidad por asignación y confidencialidad. Conocer una URL no concede acceso.
- Persistencia: tablas relacionales para datos consultables; JSON solo para esquemas versionados, participantes, instantáneas y secciones flexibles.
- Auditoría: `social_work_audit_events`, con redacción de rutas privadas, contenido altamente confidencial, tokens y contenido completo de informes.

## Modelo de datos

Las tres migraciones crean los agregados siguientes:

1. Casos: casos, estudiantes/personas relacionadas, asignaciones, historial de estados, reaperturas, intervenciones, compromisos, protocolos/versiones/activaciones, Protocolo Cero, derivaciones, informes pedagógicos y antecedentes requeridos.
2. Apoyo estudiantil: programas, medidas de protección, beneficios y entregas JUNAEB, pases, servicios médicos, ayudas técnicas y certificados.
3. Gobernanza: reglas/evaluaciones de riesgo, alertas, formularios/versiones/respuestas, informes/versiones, documentos privados y auditoría.

Índices compuestos cubren estudiante/estado, responsable/estado, caso/fecha, año/programa, alertas/estado y fechas límite. Todas las migraciones nuevas tienen `down()`; no eliminan ni transforman entidades maestras existentes.

## Flujos principales

### Caso

El código correlativo se genera bajo bloqueo transaccional (`TS-AAAA-NNNNN`). La creación vincula al estudiante maestro, registra responsable e historial. Las transiciones se validan mediante `CaseService::TRANSITIONS`. Un caso cerrado queda bloqueado para acciones ordinarias.

### Cierre y reapertura

El cierre exige conclusión, resultado, motivo y riesgo final, y solo procede desde `pendiente_cierre`. La reapertura exige motivo, riesgo, prioridad, responsable y próxima acción; conserva la conclusión previa y crea un registro independiente.

### Intervenciones

Entrevistas, acciones, visitas domiciliarias, llamados, reuniones, acciones familiares y seguimientos comparten una estructura consultable, más `structured_data` para campos propios de la versión de plantilla. Las notas altamente restringidas se separan y ocultan si falta permiso.

### Protocolos

Cada activación referencia una versión y guarda su instantánea. Avanzar etapas nunca modifica la versión histórica. Protocolo Cero registra la recepción inicial sin determinar responsabilidades ni publicar el relato.

### Riesgo y alertas

`RiskDataAdapter` lee asistencia, atrasos y anotaciones originales, además de casos y compromisos. `RiskAssessmentService` aplica reglas configurables, guarda la instantánea y permite una modificación manual solo con justificación. Las alertas usan una clave única de deduplicación y no incluyen relatos ni diagnósticos.

La tarea `social-work:evaluate-risks` se agenda diariamente a las 06:50 y procesa casos por lotes. Puede probarse sin cola con `php artisan social-work:evaluate-risks --sync`.

## Integraciones reutilizadas

- `student_profiles`, `student_enrollments`, `academic_years`, `course_sections`.
- `attendance_records` para asistencia.
- `lcd_late_arrivals` para atrasos.
- `lcd_coexistence_entries` para cantidad de anotaciones, sin copiar textos.
- Indicador PIE maestro (`is_pie_participant`) sin exponer diagnóstico en listados.
- Casos de Convivencia permanecen separados; una coordinación puede modelarse como acción/derivación autorizada.
- Calendario social entrega eventos con título genérico para información restringida. La API deja los enlaces listos para sincronización con `calendar_events` si la institución decide crear eventos compartidos.

## Permisos

Las migraciones registran 31 permisos `social_work.*`, incluyendo lectura, creación, edición, asignación, cierre/reapertura, confidencial/altamente confidencial, entrevistas, acciones, protocolos, alertas, envío y gestión de derivaciones, actualización de situación social, informes pedagógicos, JUNAEB, salud, restricciones de retiro, documentos médicos, informes, plantillas y auditoría. El seeder crea o actualiza el rol canónico `trabajador_social` con 25 permisos operativos y concede al rol `super_admin` los 31 permisos junto con el árbol completo de navegación. Inspectoría y Coordinación de Inspectoría reciben únicamente `social_work.referrals.submit` y los nodos de navegación necesarios para enviar y consultar sus propias derivaciones. Acceso altamente confidencial, corrección de cierres, reasignación, aprobación, configuración y auditoría quedan fuera del rol base de Trabajo Social y deben concederse expresamente.

Un rol con el permiso de envío de derivaciones puede crear y consultar solo sus derivaciones. Responder una solicitud pedagógica no concede acceso al caso. El administrador técnico tampoco obtiene acceso al contenido por administrar configuración.

## Archivos privados

Documentos y certificados se validan por MIME/extensión y 10 MB máximo, se almacenan con `Storage::disk('local')`, registran nombre, tamaño, MIME y SHA-256, y solo se descargan mediante controladores autenticados y autorizados. La descarga usa `Cache-Control: private, no-store` y queda auditada.

## API

Las rutas están en `routes/social_work.php` bajo `/api/social-work`. Incluyen dashboard, estudiantes/perfil/cronología, CRUD y flujos de casos, intervenciones, protocolos, Protocolo Cero, riesgo, antecedentes, alertas, derivaciones, informes pedagógicos, JUNAEB, pases, salud, certificados, documentos, informes, calendario, plantillas y catálogos. Todas usan Sanctum y middleware de permiso.

## Plantillas e informes

`SocialWorkSeeder` crea seis versiones identificadas explícitamente como `BORRADOR BASE – REQUIERE VALIDACIÓN INSTITUCIONAL`: entrevista, visita domiciliaria, llamado, derivación, acta de entrega e informe social. Una plantilla usada conserva su versión. El informe maestro se genera como borrador editable con snapshot y fuentes; no inventa diagnósticos ni hechos y requiere aprobación humana.

La ficha maestra PDF se genera con `pdfmake`, ya incluido, con marca de borrador/confidencialidad, páginas numeradas, versión, secciones autorizadas y aviso de revisión humana. Los listados visibles se exportan a CSV compatible con Excel, excluyendo narrativas. Hasta validar encabezados, logo y firmas, estos documentos no se presentan como formatos oficiales.

## Instalación y despliegue

```bash
php artisan migrate
php artisan db:seed --class=SocialWorkSeeder
php artisan optimize:clear
npm run prod
php artisan queue:restart
```

El scheduler del servidor debe ejecutar `php artisan schedule:run` cada minuto y el worker de colas debe estar activo.

## Pruebas

```bash
php artisan test tests/Unit/SocialWork tests/Feature/SocialWork
npx vitest run tests/frontend/social-work-risk-badge.test.js
npx vite build
```

Las pruebas cubren transiciones, creación y trazabilidad, cierre obligatorio, reapertura, bloqueo del caso cerrado, aislamiento del docente y deduplicación de alertas. El componente de riesgo prueba que icono y texto acompañan al color.

## Pendientes institucionales y riesgos conocidos

- Validar los formatos oficiales, logo, pie, firmas, catálogo de motivos, tipos de casos, umbrales de riesgo y niveles elegibles JUNAEB por año.
- Definir si los eventos confidenciales deben copiarse físicamente al calendario institucional o consumirse desde el feed social.
- Definir reglas de intercambio de campos con Convivencia, PIE y Enfermería; el módulo evita compartirlos por defecto.
- Configurar retención, respaldo cifrado y antivirus institucional para adjuntos.
- El motor usa conteos y umbrales como alertas de apoyo; nunca reemplaza el juicio profesional.
