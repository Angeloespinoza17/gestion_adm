# Gestión pedagógica — flujo documental de instrumentos

## Alcance

El módulo gestiona el envío, revisión, rectificación, aprobación e impresión de instrumentos pedagógicos PDF o Word `.docx`. El rol `docente` registra asignatura, curso y archivo; Coordinación Académica revisa únicamente los cursos o niveles que tiene asignados y Centro de Apuntes recibe automáticamente la versión exacta que fue aprobada.

La coordinadora puede solicitar opcionalmente un informe OpenAI como apoyo profesional. El informe no toma decisiones, no se ejecuta al cargar el PDF y sólo se comparte con el docente si la coordinadora marca explícitamente esa opción. El análisis determinístico local anterior permanece disponible como herramienta independiente y no cambia el estado de aprobación documental.

## Flujo

1. El docente registra establecimiento, asignatura, curso y PDF. El instrumento queda `submitted`.
2. El servidor valida extensión, MIME, firma, integridad, tamaño, duplicidad SHA-256 y existencia de una capa de texto.
3. El archivo se guarda en almacenamiento privado bajo `private/pedagogical-management/instruments/{school}/{instrument}/v{n}`.
4. La bandeja `Revisión documental` aplica el alcance configurado por curso o nivel para la coordinadora.
5. La coordinadora puede ver el PDF y solicitar `GeneratePedagogicalAiReportJob`. El archivo se envía a OpenAI sólo en esta acción explícita y se elimina remotamente de forma best-effort después de procesarlo.
6. La resolución humana puede ser `approved`, `approved_with_observations` o `rectification_requested`.
7. Al solicitar rectificación, el docente recibe las observaciones, los documentos institucionales habilitados y, si fue autorizado, el informe IA. La nueva carga crea otra versión y conserva todo el historial.
8. Una aprobación crea de forma transaccional una solicitud privada en `Instrumentos aprobados` de Centro de Apuntes, desde donde se puede imprimir, descargar y completar la tarea.

## Evolución y estadísticas

La vista `/gestion-pedagogica/estadisticas` requiere `pedagogical-instruments.statistics`. La coordinadora consulta exclusivamente informes de instrumentos incluidos en sus cursos o niveles asignados; los roles con `pedagogical-instruments.view-all` mantienen el alcance global autorizado del establecimiento. El rol docente no recibe esta superficie ni estadísticas nominales de otras personas.

La unidad oficial es un informe OpenAI completado que fue vinculado a una resolución humana. Las regeneraciones no vinculadas no se duplican en los indicadores. Cada resolución proyecta un resumen inmutable hacia `pedagogical_report_snapshots`, sus 19 criterios hacia `pedagogical_report_criterion_snapshots` y los hallazgos complementarios hacia `pedagogical_report_misc_snapshots`.

Los indicadores incluyen ajuste a la pauta, cobertura de evidencia, aprobación inicial, mejora entre versiones, tiempo de rectificación, cierre de rectificaciones, persistencia, resolución y regresión por criterio. Las comparaciones sólo se realizan entre versiones consecutivas del mismo instrumento cuando conservan el mismo `rubric_hash`. Con menos de cinco informes o tres pares comparables la interfaz identifica la información como exploratoria.

El botón `Exportar informe PDF` genera un documento ejecutivo A4 con el mismo alcance autorizado y los filtros efectivamente aplicados al tablero. Incluye lectura ejecutiva, seis indicadores, evolución temporal, resoluciones, dimensiones, prioridades, mapa completo de los 19 criterios, hallazgos misceláneos, trayectorias docentes e instrumentales y metodología. Los valores sin base comparable se presentan como `Sin comparación` o `Sin dato`; nunca se convierten en cero.

Para proyectar informes históricos después de ejecutar la migración aditiva:

```bash
php artisan pedagogical:rebuild-report-statistics
```

El comando sólo crea o actualiza registros derivados en las tablas estadísticas; no modifica instrumentos, archivos, informes ni revisiones de origen.

## Privacidad y autorización

- Los PDF permanecen privados; toda visualización o descarga exige policy y permiso vigentes.
- El docente sólo ve y rectifica sus instrumentos. La coordinación sólo ve instrumentos de los cursos o niveles asignados, salvo roles con alcance global explícito.
- Cada revisión queda vinculada a una versión inmutable. Una rectificación no reemplaza ni borra archivos anteriores.
- El informe OpenAI se vincula a la versión exacta, usa salida JSON estructurada, se solicita por cola y no se comparte por defecto.
- La decisión siempre es humana. Ni OpenAI ni las reglas determinísticas aprueban, rechazan o envían documentos a impresión.
- La cola de Centro de Apuntes conserva contadores y fechas de descarga, impresión y cierre.

## Operación

La cola usa conexión `database`. Debe existir un worker persistente para:

```bash
php artisan queue:work database --queue=pedagogical-instruments --tries=3 --timeout=600 --sleep=1
```

En local también están disponibles `composer run serve:local` y `composer run worker:pedagogical`.

Variables opcionales:

```dotenv
PEDAGOGICAL_INSTRUMENTS_DISK=local
PEDAGOGICAL_INSTRUMENTS_ROOT=private/pedagogical-management/instruments
PEDAGOGICAL_INSTRUMENTS_MAX_FILE_KB=30720
PEDAGOGICAL_INSTRUMENTS_MIN_TEXT_CHARS=40
PEDAGOGICAL_INSTRUMENTS_QUEUE=pedagogical-instruments
OPENAI_API_KEY=
OPENAI_PEDAGOGICAL_MODEL=gpt-5.4-mini
OPENAI_PEDAGOGICAL_TIMEOUT=180
OPENAI_PEDAGOGICAL_MAX_OUTPUT_TOKENS=5000
```

El mismo worker procesa tanto los análisis determinísticos solicitados explícitamente como los informes OpenAI. Sin `OPENAI_API_KEY`, el flujo de revisión humana sigue operativo y el botón informa que la integración no está configurada.

## Estados documentales

- `submitted`: enviado por el docente y pendiente de resolución.
- `rectification_requested`: la coordinadora solicitó cambios.
- `resubmitted`: el docente cargó una nueva versión rectificada.
- `approved`: aprobado y enviado a Centro de Apuntes.
- `approved_with_observations`: aprobado, con mensaje para el docente, y enviado a Centro de Apuntes.
- `archived`: registro conservado fuera del flujo activo.

Los estados técnicos del informe OpenAI son `pending`, `processing`, `completed` y `failed`. Son independientes del estado documental.

## Verificación mínima

- Pruebas de API para PDF-only, privacidad, RBAC, alcance curso/nivel y duplicidad.
- Prueba end-to-end de rectificación, historial, aprobación y cola de Centro de Apuntes.
- Prueba del informe OpenAI con HTTP simulado, salida estructurada, `store=false` y vínculo a versión.
- Prueba de estadísticas con evolución entre versiones, resolución y persistencia por criterio, hallazgos misceláneos y aislamiento por alcance de coordinación.
- Pruebas frontend de estados, acciones explícitas y superficies por rol.
- Compilación de producción y validación visual autenticada en escritorio y 390 px.
- En despliegue se deben comprobar el worker persistente, `jobs`, `failed_jobs`, la generación visible y una impresión de prueba autorizada.
