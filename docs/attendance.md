# Módulo de asistencia

La asistencia está integrada en `Estudiantes > Reportes > Asistencia`. El backend entrega un payload consolidado para todos los cursos y evita una solicitud por curso.

## Primera importación

1. Abre la pestaña `Asistencia` y presiona `Importar PDF`.
2. Selecciona obligatoriamente un curso existente del año académico.
3. Selecciona el PDF mensual Lirmi y presiona `Analizar archivo`.
4. Revisa período, totales, advertencias y coincidencias de estudiantes.
5. Resuelve manualmente cada fila ambigua o sin coincidencia. El sistema no crea estudiantes.
6. Selecciona la estrategia de conflictos y confirma.

La confirmación se ejecuta dentro de una transacción. El archivo se identifica con SHA-256 y no genera registros duplicados al reimportarlo para el mismo curso.

## Reimportaciones y conflictos

- `Rechazar`: cancela la consolidación cuando un registro existente es distinto.
- `Sobrescribir`: reemplaza únicamente después de la confirmación explícita.
- `Conservar`: mantiene registros existentes y agrega los faltantes.

Las importaciones, conteos, conflictos, usuario y fecha quedan en el historial de auditoría.

## Proyección e ingresos

El botón de configuración en `Escenarios e ingresos` permite registrar:

- valor monetario de la unidad;
- factor vigente;
- ajuste adicional;
- meta institucional;
- reducción del escenario conservador;
- tasa del escenario personalizado;
- días lectivos anuales y ventana de cálculo.

Mientras el valor o el factor no estén configurados, la vista muestra `Configuración financiera requerida` y no presenta $0 como una estimación válida.

La proyección acumula los registros reales y agrega los días esperados restantes:

`proyección = (presentes reales + esperados restantes × tasa del escenario) / registros esperados proyectados`

El ingreso estimado usa:

`asistencia media proyectada × valor unidad × factor + ajustes`

## Jornadas y correcciones

Selecciona una fecha del calendario para revisar presentes, ausentes, curso, fuente y archivo. Los usuarios con `editar_asistencia` pueden corregir un registro. Una jornada con 0 % permanece como `pending_confirmation` hasta que un usuario autorizado la confirme.

Toda corrección registra el usuario, cambia el origen a `manual` y recalcula las alertas del curso.

## Alertas y seguimiento

Las alertas se recalculan después de importar, después de corregir y diariamente a las 06:30 mediante:

```bash
php artisan attendance:rebuild-alerts
```

Para recalcular un año específico:

```bash
php artisan attendance:rebuild-alerts --year=1
```

Desde la vista se puede registrar contacto con apoderado, entrevista, derivación, compromiso u otra acción, junto con notas y próxima fecha.

## Permisos

- `ver_asistencia`
- `importar_asistencia`
- `editar_asistencia`
- `gestionar_alertas_asistencia`
- `proyectar_ingresos_asistencia`

Los permisos se crean con `AttendancePermissionSeeder` y se asignan inicialmente a administración y superadministración.
