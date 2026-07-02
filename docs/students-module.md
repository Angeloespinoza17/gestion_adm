# Modulo de estudiantes

## Modelo funcional

- `academic_years`: define anos como 2026, 2027 y 2028. Solo uno puede quedar activo.
- `education_levels`: cataloga los niveles oficiales desde `NT1` hasta `4° medio`.
- `course_sections`: crea cursos por ano, nivel y paralelo. Ejemplo: `7° básico A` en `2026`.
- `student_profiles`: guarda la ficha general y extensible de la estudiante.
- `student_enrollments`: guarda la matricula anual. Esta es la fuente de verdad del curso por ano.
- `student_enrollment_movements`: registra cambios de curso, retiros y reingresos dentro del mismo ano academico.
- `student_promotions`: registra auditoria de promociones, repitencias, cambios de paralelo, retiros y egresos.

## Regla historica clave

El curso de una estudiante no vive en `users` ni en `student_profiles`.
Siempre se resuelve desde `student_enrollments`.

Cada matricula anual guarda snapshots:

- `snapshot_year_name`
- `snapshot_level_name`
- `snapshot_section_name`
- `snapshot_course_display_name`

Con esto, si en 2027 una estudiante cambia de curso o avanza de nivel, la ficha de 2026 sigue mostrando exactamente el curso historico que tenia ese ano.

## Cambios, retiros y reingresos

- la matricula anual sigue siendo unica por `estudiante + ano academico`
- los cambios de curso dentro del mismo ano actualizan la matricula vigente y dejan una bitacora en `student_enrollment_movements`
- un retiro deja la matricula con estado `retirada` y permite listar retiradas por ano
- si vuelve el mismo ano, se registra un `reingreso` en la misma matricula sin borrar los movimientos previos
- si vuelve anos despues, se crea una nueva matricula anual y no se toca el ano anterior

## Promocion

La vista de promocion anual:

1. Toma un ano y curso origen.
2. Lista las estudiantes matriculadas ahi.
3. Crea una nueva matricula en el ano destino para cada seleccionada.
4. Nunca modifica la matricula del ano origen.

Estados soportados en la promocion:

- `promovida`
- `repitente`
- `cambio_paralelo`
- `retirada`
- `egresada`

Solo los tres primeros crean una nueva matricula en el ano destino.
Los otros dos dejan trazabilidad en `student_promotions` sin sobreescribir anos anteriores.

## Seeders

`AcademicCatalogSeeder` carga:

- niveles oficiales
- anos 2026, 2027 y 2028
- cursos A y B por nivel y por ano
- estudiantes de ejemplo con historial anual

`StudentTestingSeeder` carga:

- anos academicos de prueba alrededor del ano actual
- cursos A, B y C por nivel y por ano
- 90 estudiantes de prueba con rol `estudiante`
- movimientos aleatorios de cambio de curso, retiro y reingreso para validar la trazabilidad

## Validaciones importantes

- un ano academico por valor de `year`
- un curso unico por `academic_year_id + education_level_id + section_name`
- una sola matricula por estudiante y ano academico
- el curso destino debe pertenecer al ano destino
- no se permiten cambios en matriculas o cursos de anos cerrados, salvo `super_admin`
- no se pierde trazabilidad del ano aunque una estudiante cambie de paralelo o sea retirada y reingresada
