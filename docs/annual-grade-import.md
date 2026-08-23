# Importación anual de calificaciones

La importación se encuentra en **Estudiantes → Reportes → Asistencia**, bajo el permiso `importar_calificaciones`. El proceso incorpora las notas al dominio oficial de Libro Digital (`lcd_assessments` y `lcd_student_results`) y conserva el Excel, sus filas, columnas, celdas y resultados de conciliación como evidencia trazable.

## Interpretación del archivo

- Cada hoja de curso puede tener una cantidad distinta de asignaturas y columnas de evaluación.
- Los encabezados repetidos dentro de una asignatura se distinguen por su ocurrencia (`N2`, `N2 (2)`, etc.).
- Las notas entre `1,0` y `7,0` se registran como resultados numéricos.
- `P`, `PENDIENTE`, `PEND` y una celda vacía permanecen pendientes y no generan una nota ficticia.
- `-`, `—`, `–`, `N/A` y `NA` se registran como no aplicable/eximida.
- Un valor fuera de contrato queda observado como inválido sin detener el resto de la carga.
- La fecha de la evaluación importada corresponde al corte del reporte; la fecha real del instrumento y su ponderación original no vienen informadas en el Excel y quedan señaladas como desconocidas en los metadatos.

## Idempotencia y versiones

El checksum SHA-256 identifica una recarga exacta por establecimiento y año. Si el mismo archivo se carga nuevamente, no se crea otra versión ni se duplican evaluaciones/resultados: solo se reintentan matches y destinos pendientes. Un archivo realmente distinto crea una nueva versión de importación y reutiliza las evaluaciones mediante una identidad estable por año, curso, asignatura, encabezado y ocurrencia.

Una nota corregida explícitamente desde Libro Digital pierde su vínculo de importación y pasa a ser autoritativa. Las cargas siguientes la conservan y registran el estado `preserved_manual`.

Los matches manuales verificados también se reutilizan en versiones posteriores cuando la identidad de origen y el curso continúan siendo compatibles; una coincidencia ambigua vuelve a la cola manual en vez de asumirse.

## Conciliación operativa

- El RUN único y compatible con el curso se vincula automáticamente.
- Las filas no encontradas, RUN duplicados o conflictos de curso pasan a la cola de match manual; esto no bloquea las demás alumnas.
- La búsqueda manual se limita a matrículas vigentes del año y, cuando está resuelto, del curso compatible.
- El nombre de asignatura se resuelve primero mediante un alias confirmado para el establecimiento, sistema de origen y tipo de enseñanza. Solo si no existe ese vínculo se aplica la coincidencia canónica anterior.
- El catálogo de **Libro digital anterior** contiene 149 nombres transcritos de la fuente operacional y se administra en **Libro Digital → Asignaturas → Revisar nombres externos**. Una sugerencia no se usa para importar hasta que una persona la confirma.
- Los matches ambiguos o no confirmados permanecen pendientes. No se aplica similitud difusa ni se cambia el nombre técnico de `schedule_subjects` para forzar una coincidencia.
- Los cursos, asignaturas, libros abiertos o asignaciones docentes que falten quedan como destinos pendientes. La acción **Reintentar** vuelve a conciliarlos sin duplicar datos una vez preparada la estructura de Libro Digital.
- Las evaluaciones cerradas no se modifican; requieren el flujo formal de enmienda del Libro Digital.

## Despliegue seguro

Antes de producción se debe crear y verificar un respaldo de base de datos. Las migraciones `2026_08_21_120000_create_annual_grade_excel_imports` y `2026_08_22_150000_create_lcd_subject_catalog_management_tables` son aditivas, usan claves foráneas restrictivas o anulables y tienen un `down()` deliberadamente no destructivo. La segunda crea perfiles y aliases vacíos: no renombra asignaturas existentes ni instala matches en la base de datos. No se deben ejecutar `migrate:fresh`, `migrate:refresh`, `migrate:reset` ni `db:wipe`.

Variables opcionales:

```dotenv
GRADE_IMPORTS_DISK=local
GRADE_IMPORTS_PATH=grades/imports
GRADE_MAX_UPLOAD_KB=25600
```

Después de migrar se debe ejecutar el seeder RBAC institucional correspondiente o verificar que `importar_calificaciones` esté asignado solo a los roles autorizados. La migración lo instala de forma idempotente para `super_admin` y `administrador` cuando esos roles existen.
