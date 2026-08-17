# Fuentes normativas y técnicas

## Criterio de registro

Investigación base realizada el **2026-08-12** y verificación curricular complementaria realizada el **2026-08-13**, en zona `America/Santiago`. Se priorizaron fuentes primarias u oficiales de MINEDUC/EDE, Superintendencia de Educación, Biblioteca del Congreso Nacional (LeyChile), Diario Oficial y Currículum Nacional.

El manifiesto consolidado [normativa/sources.json](normativa/sources.json) conserva los metadatos de las fuentes. Su campo superior `consulted_on` registra el corte base; una entrada posterior puede declarar su propia `consulted_on`. [normativa/README.md](normativa/README.md) define la política metadata-only: no se versionan binarios y solo se informan tamaño/SHA-256 para bytes realmente obtenidos.

Un SHA-256 se informa únicamente cuando se descargaron y verificaron bytes reales durante la investigación. `Sin hash` significa que se consultó la página o índice, que el servidor impidió descargar el acto completo, o que el artefacto aún no fue importado. No se infiere ni se inventa una huella.

Los enlaces y fechas deben revalidarse antes de cada activación anual, cambio de perfil normativo o importación EDE.

## Estándar de Datos para la Educación y Libro de Clases Digital

| Fuente oficial | Fecha/versión observada | Uso verificable | Hash local |
|---|---|---|---|
| [Información normativa EDE](https://www.ede.mineduc.cl/informaci%C3%B3n-normativa) | Consultada 2026-08-12 | Índice oficial de REX 3335, REX 917, Circular 30 modificada, Circular 700 y ORD 1355. | Sin hash; página web. |
| [Preguntas frecuentes EDE](https://www.ede.mineduc.cl/faq) | Consultada 2026-08-12 | LCD opcional; uso de papel **o** digital; traspaso previo al cambiar durante el año; contenido mínimo; libre elección de software; trazabilidad; exportación; respaldo mínimo de cinco años; MINEDUC no certifica software. | Sin hash; página web. |
| [Usuarios EDE](https://www.ede.mineduc.cl/usuarios) | Consultada 2026-08-12 | Registro del docente/verificador y uso de autenticador; enlaza a [Trámites MINEDUC](https://tramites.mineduc.cl/). | Sin hash. |
| [Verificación transaccional](https://www.ede.mineduc.cl/desarrolladores/validaci%C3%B3n-transaccional) | Contrato público consultado 2026-08-12 | GET `https://apiede.mineduc.cl/otp/verify-otp`; parámetros publicados `rut`, `otp`, `DateWithTimeZone`; respuesta booleana. | Sin hash. |
| [Verificación masiva](https://www.ede.mineduc.cl/desarrolladores/validaci%C3%B3n-masiva) | Contrato público consultado 2026-08-12 | POST al endpoint publicado; campos exactos `RUT`, `OTP`, `TIMESTAMP`; respuesta con RUT y booleano. | Sin hash. |
| [Portal para desarrolladores](https://www.ede.mineduc.cl/desarrolladores) | Consultado 2026-08-12 | Índice del modelo ER, diccionario, mapeo, clave pública e instrucciones de validación. | Sin hash. |
| [Libro de Clases Digital para desarrolladores](https://www.ede.mineduc.cl/desarrolladores/libro-de-clases-digital) | Consultado 2026-08-12 | Flujo de mapeo al estándar, ejecución local y comprobación de datos. | Sin hash. |
| [Contenedor EDE](https://www.ede.mineduc.cl/desarrolladores/contenedor) | Consultado 2026-08-12 | Publica `docker pull edemineduc/etl` y las operaciones de transformación/validación. No publica tag ni digest inmutable. | Sin hash. |
| [Repositorio DockerEdeCode](https://github.com/Admin-EDE/DockerEdeCode) | Rama `master`; sin release visible al consultar | README y código enlazados desde EDE para operaciones `parse`, `insert` y `check`. | Sin hash; repositorio no fijado a commit en esta revisión. |
| [Diccionario de datos EDE](https://drive.google.com/drive/folders/1QCSQIM1T3INcqNZefMi6xknz2Ag2xwg2) | Se observaron `CEDS-V7_1-with-Extend.xlsx`, `NDS-Reference-v7_1.xlsx` y funciones de verificación | Evidencia una base CEDS/NDS 7.1 publicada. No constituye por sí sola un manifiesto de release EDE inmutable. | Sin hash; archivos no descargados. |
| [Modelo entidad-relación EDE](https://drive.google.com/drive/folders/1RoRFrg8kEv7ZtETv651MwCpX3QuEzFll) | Se observó `LibroDeClasesDigital_v02.svg` | Modelo ER de referencia publicado. | Sin hash; archivo no descargado. |
| [Mapeo oficial LCD–EDE/CEDS](https://docs.google.com/spreadsheets/d/1W5JNVZmO2_kYjvSU8zRQF-lMBCxDdvxguVobTQ0Jd-w/edit) | Exportado 2026-08-12; 12 hojas | Mapeo de matrícula, antecedentes, retiros, asignaturas, asistencia, actividades, evaluaciones, reuniones, convivencia y aula/PIE. Contiene una contradicción matrícula 55/43 que debe resolverse. | `638eadb82f64a559311c2676ef7004fc70281396c73800923317f7a6fa2e9fd0` sobre 312328 bytes exportados por [XLSX](https://docs.google.com/spreadsheets/d/1W5JNVZmO2_kYjvSU8zRQF-lMBCxDdvxguVobTQ0Jd-w/export?format=xlsx). |
| [Clave pública Superintendencia](https://static.superintendencia-educacion.cl/KP/clave.pub.txt) | Enlace oficial consultado 2026-08-12 | Material público referenciado para el proceso de cifrado EDE. Debe versionarse al importarlo. | Sin hash; no descargada en esta revisión. |

## Resoluciones y circulares sobre EDE y registros

| Acto oficial | Fecha | Contenido relevante | Verificación/hash |
|---|---|---|---|
| Resolución Exenta MINEDUC N.º 3335, “Establece Estándar de Datos para la Educación e instruye labores al comité…” | 2020-08-03 | Establece el Estándar de Datos para la Educación y su gobernanza. [PDF oficial enlazado por EDE](https://drive.google.com/uc?export=download&id=10d1DM4_2s2uP1L3zjOKU1j485CZDVEgF). | PDF real de 578886 bytes; SHA-256 `c8e8b791e5a3c6119fcdaaddfee35c993847878bdda156c6937ec9cf3f158aae`. |
| Resolución Exenta MINEDUC N.º 917, “Modifica la Resolución Exenta N.º 3.335… y fija su texto refundido” | 2021-01-29; timbre documental 2021-03-09 | Modifica y refunde la política del estándar. [PDF oficial enlazado por EDE](https://drive.google.com/uc?export=download&id=1CRnZNWgJHYxObWRIkudMvxNRi_sxTX61). | PDF real de 723296 bytes; SHA-256 `0439d3439b64343c223bb91a4bad13fc1614f94d23b197d7c88e3eb5a446c6dd`. |
| Resolución Exenta Supereduc N.º 30, Circular sobre registros de información | Dictada 2021-01-14; publicada 2021-04-05 | Registros que deben mantener establecimientos con reconocimiento oficial. Modificada por REX 432/2023. [Registro LeyChile](https://www.bcn.cl/leychile/navegar?i=1157769) y [PDF Supereduc modificado](https://www.supereduc.cl/wp-content/uploads/2021/01/Circular-Registros-Modificada-2023.pdf). | Sin hash: el PDF Supereduc fue bloqueado por WAF; no se archivaron bytes válidos. |
| Resolución Exenta Supereduc N.º 432 | Dictada 2023-09-28; publicada 2023-10-28 | Agrega a 5.4 de la Circular 30 una causal excepcional de baja por ausencia continua e imposibilidad de ubicar tutores, mediante procedimiento administrativo del Reglamento Interno. [LeyChile](https://www.bcn.cl/leychile/navegar?i=1197327) y [extracto Diario Oficial](https://www.diariooficial.interior.gob.cl/publicaciones/2023/10/28/43687/01/2396727.pdf). | Sin hash del acto íntegro. El extracto no valida plazos operativos detallados. |
| Resolución Exenta Supereduc N.º 700, Circular sobre registros de educación parvularia | Dictada 2025-11-12; publicada 2025-11-29; vigente desde 2026-03-01 | Aplica a establecimientos que imparten educación parvularia con reconocimiento/autorización y contempla mecanismos digitales. [PDF Supereduc](https://www.supereduc.cl/wp-content/uploads/2025/11/REX-No-0700-APRUEBA-CIRCULAR-SOBRE-REGISTROS-DE-INFORMACION-QUE-DEBEN-MANTENER-LOS-EST.-DE-ED.-PARVULARIA.pdf) y [Diario Oficial](https://www.diariooficial.interior.gob.cl/publicaciones/2025/11/29/44312/01/2733475.pdf). | Sin hash del acto íntegro: descarga Supereduc bloqueada por WAF. No se validó la supuesta regla “dos horas/tercera hora”. |
| ORD Supereduc N.º 1355, contenido y especificaciones de registros formales digitales/electrónicos | Publicado en índice EDE; fecha interna no verificada | Especificaciones relacionadas con registros formales en formato digital/electrónico. [PDF oficial](https://www.supereduc.cl/wp-content/uploads/2023/11/ORD-No-1355-CONTENIDO-Y-ESPECIFICACIONES-DE-REGISTROS-FORMALES-DE-INFORMACION-DIGITAL-O-ELECTRONICO-A-LOS_SOSTENEDORES.pdf). | Sin hash; descarga bloqueada por WAF. No citar reglas finas sin archivar el texto. |

## Evaluación, currículo, PIE y asistencia

| Fuente oficial | Fecha/vigencia | Requisito utilizado |
|---|---|---|
| [Decreto 67/2018, evaluación, calificación y promoción](https://www.bcn.cl/leychile/Navegar?idNorma=1127255) | Publicado 2018-12-31 | Reglamento institucional objetivo/transparente; evaluación formativa/sumativa; no exención de asignaturas/módulos; escala anual 1,0–7,0 con un decimal y aprobación 4,0 para su ámbito; promoción normalmente con 85% de asistencia; contenido de actas. |
| [Decreto 170/2009](https://www.bcn.cl/leychile/navegar?idNorma=1012570) | Vigente según LeyChile al consultar | Evaluación diagnóstica integral/interdisciplinaria para subvención de educación especial; información a la familia; tratamiento de antecedentes sensibles. |
| [Decreto Exento 83/2015](https://www.bcn.cl/leychile/Navegar?idNorma=1074511) | Promulgado 2015-01-30; publicado 2015-02-05 | Criterios y orientaciones de adecuación curricular para parvularia/básica; evaluación coherente con adecuaciones y PACI. |
| [Orientaciones PIE MINEDUC](https://especial.mineduc.cl/implementacion-dcto-supr-no170/orientaciones/) | Consultadas 2026-08-12 | Orientaciones oficiales complementarias de implementación. |
| [Registro de planificación PIE MINEDUC](https://especial.mineduc.cl/implementacion-dcto-supr-no170/registro-planificacion-pie/) | Consultado 2026-08-12 | Planificación de adaptaciones, apoyos y registro de responsables. |
| [Currículum Nacional](https://www.curriculumnacional.cl/) | Consultado 2026-08-12 | Fuente oficial para bases, programas y Objetivos de Aprendizaje. El catálogo OA aún no fue descargado/importado/hashado. |
| [Decreto 481/2018, Bases Curriculares de Educación Parvularia](https://www.bcn.cl/leychile/Navegar?idNorma=1114961) | Publicado 2018-02-10 | Base curricular para planificación y objetivos en educación parvularia. |
| [DFL 2/1998 sobre subvenciones](https://www.bcn.cl/leychile/navegar?idNorma=127911) | Vigente según LeyChile al consultar | Artículo 13 vincula la subvención mensual al promedio de asistencia registrada por curso; no define por sí solo la regla operacional de hora/atraso. |

## Currículo oficial por ciclo

Verificación complementaria al **2026-08-13**. Los enlaces siguientes permiten localizar Bases Curriculares por ciclo; no se descargaron ni archivaron sus bytes en esta revisión y, por ello, todos conservan `sha256=null`/`bytes=null` en el manifiesto.

El [artículo 31 del DFL N.º 2/2009 (Ley General de Educación)](https://www.bcn.cl/leychile/navegar?idNorma=1014974) establece el marco de Bases Curriculares y su aprobación. Para el dato importable se debe usar además el acto y la publicación UCE aplicables a cada ciclo; la LGE por sí sola no contiene los OA/OAT.

| Alcance | Navegación y artefacto UCE/MINEDUC | Acto oficial BCN | Estado de obtención |
|---|---|---|---|
| Parvularia, tramo `NT` aplicable al alcance institucional `NT1`/`NT2` | [Educación Parvularia](https://www.curriculumnacional.cl/curriculum/educacion-parvularia), [ficha BCEP](https://www.curriculumnacional.cl/recursos/educacion-parvularia-vigentes-2019), [PDF oficial observado](https://www.curriculumnacional.cl/614/articles-69957_bases.pdf) | [DS 481/2018](https://www.bcn.cl/leychile/navegar?idNorma=1114961) | HTML/PDF localizados; bytes no archivados. La Base agrupa objetivos en `Nivel Transición`; no autoriza fabricar listas normativas distintas para NT1 y NT2. |
| `1B`–`6B` | [Cursos y asignaturas](https://www.curriculumnacional.cl/curriculum/1o-6o-basico), [ficha](https://www.curriculumnacional.cl/recursos/bases-curriculares-1-6-basico), [PDF oficial observado](https://www.curriculumnacional.cl/614/articles-22394_bases.pdf) | [DS 439/2012](https://www.bcn.cl/leychile/navegar?idNorma=1036799), [DS 433/2012](https://www.bcn.cl/leychile/navegar?idNorma=1047359) | HTML/PDF localizados; bytes no archivados. |
| `7B`, `8B`, `1M`, `2M` | [Cursos y asignaturas](https://www.curriculumnacional.cl/curriculum/7o-basico-2o-medio), [ficha](https://www.curriculumnacional.cl/node/3670), [PDF oficial observado](https://www.curriculumnacional.cl/614/articles-37136_bases.pdf) | [DS 614/2013](https://www.bcn.cl/leychile/navegar?idNorma=1059966), [DS 369/2015](https://www.bcn.cl/leychile/navegar?idNorma=1084868) | HTML/PDF localizados; bytes no archivados. |
| `3M`, `4M`, formación general/HC | [Cursos y asignaturas](https://www.curriculumnacional.cl/curriculum/3o-4o-medio), [ficha](https://www.curriculumnacional.cl/recursos/bases-curriculares-3-4-medio), [PDF oficial observado](https://www.curriculumnacional.cl/614/articles-91414_bases.pdf) | [DS 193/2019](https://www.bcn.cl/leychile/navegar?idNorma=1136078) | HTML/PDF localizados; bytes no archivados. El import debe separar FG y HC. |
| `3M`, `4M`, formación TP | [Navegación TP por especialidad](https://www.curriculumnacional.cl/curriculum/3o-4o-medio-tecnico-profesional) | [DS 452/2013](https://www.bcn.cl/leychile/navegar?idNorma=1056485) y modificaciones vigentes | Documentos se localizan por sector/especialidad; no se obtuvo un paquete integral. El import debe conservar sector, especialidad y mención cuando aplique. |

La [cartilla UCE de Bases Curriculares vigentes 2026](https://www.curriculumnacional.cl/sites/default/files/adjuntos/recursos/2026-04/Cartilla_N1.pdf) informa que la priorización curricular 2023 terminó en diciembre de 2025 y que en 2026 se gestionan las Bases Curriculares. Por ello, una lista histórica de OA priorizados no satisface el universo curricular 2026.

### Formato estructurado: conclusión verificable

- No se identificó al 2026-08-13 una descarga oficial integral CSV, XLS o XLSX de OA/OAT por todos los niveles/asignaturas.
- El sitio oficial ofrece navegación HTML y PDF por ciclo/asignatura. El XLSX descrito en [CURRICULUM_IMPORT_RUNBOOK.md](CURRICULUM_IMPORT_RUNBOOK.md) es un transporte institucional, no un formato MINEDUC.
- El dominio oficial expone un [índice JSON:API](https://www.curriculumnacional.cl/jsonapi) y un [recurso técnico de objetivos](https://www.curriculumnacional.cl/jsonapi/cn_learning_objective/cn_learning_objective). Se observó respuesta JSON:API pública, pero no documentación UCE/MINEDUC que garantice estabilidad, completitud, versionado o uso como contrato oficial. Algunas relaciones estaban restringidas. No se debe aprobar ni activar un catálogo solo con ese endpoint.
- Si se utiliza el JSON:API para ayudar a extraer, se deben archivar respuesta/headers/paginación, reconciliar el 100 % de códigos y textos contra HTML/PDF y obtener aprobación curricular. El endpoint no cierra por sí solo `CURRICULUM_OA_NOT_IMPORTED`.

## Protección de datos y niñez

| Fuente oficial | Fecha/vigencia | Consecuencia para el diseño |
|---|---|---|
| [Ley 19.628 sobre protección de la vida privada](https://www.bcn.cl/leychile/navegar?idNorma=141599) | Versión consultada vigente hasta 2026-11-30 | Marco aplicable al tratamiento durante la fecha de consulta; finalidad, confidencialidad y protección de datos personales/sensibles. |
| [Ley 21.719](https://www.bcn.cl/leychile/navegar?i=1209272) | Promulgada 2024-11-25; publicada 2024-12-13; **entra en vigor 2026-12-01** | Refuerza principios, derechos, seguridad, deberes, protección NNA, notificación de brechas y evaluación de impacto previa para alto riesgo. El módulo debe llegar a esa fecha con DPIA y gobierno de datos aprobados. |
| [Ley 21.430, artículo 33](https://www.bcn.cl/leychile/Navegar/imprimir?idNorma=1173643&idParte=10317439) | Vigente según LeyChile al consultar | Derecho de niños, niñas y adolescentes a vida privada y protección de datos. |

## Hechos técnicos que no deben sobreinterpretarse

- `CEDS-V7_1-with-Extend.xlsx` y `LibroDeClasesDigital_v02.svg` son nombres observados en carpetas oficiales; no equivalen a una release EDE fijada para este despliegue.
- `docker pull edemineduc/etl` no fija una versión ni un digest. Hasta resolverlo, la validación es no reproducible.
- La página pública del verificador documenta una respuesta booleana. No documenta de forma suficiente un ID transaccional, una ventana de frescura, política de replay ni consumo de OTP; esas garantías no se deben inventar.
- El hash del XLSX corresponde a la exportación consultada y puede variar en otra exportación del mismo Google Sheet.
- El FAQ oficial fija respaldo de al menos cinco años para LCD, pero no autoriza un borrado automático al vencer el plazo ni resuelve cada categoría sensible. El proyecto mantiene no-borrado automático.
- MINEDUC declara en su FAQ que no lleva un registro ni emite opinión/certificación sobre software de Libro de Clases Digital. El sistema solo podrá decir “validado técnicamente contra EDE versión X” cuando exista una ejecución oficial exitosa y evidenciada; hoy no existe.

## Fuentes pendientes de archivo íntegro

Las siguientes evidencias deben descargarse desde canal oficial, conservarse en storage privado, registrar bytes y SHA-256 y ser revisadas por cumplimiento antes de parametrizar reglas:

1. Circular REX 30 en su texto modificado íntegro.
2. Resolución Exenta 432 íntegra, incluidos sus plazos/procedimiento detallado.
3. Circular REX 700 íntegra y sus reglas específicas de asistencia/retención.
4. ORD 1355 íntegro.
5. Release formal o snapshot completo del estándar EDE con manifiesto.
6. Clave pública utilizada y digest de la imagen oficial.
7. Artefacto oficial de OA/currículo correspondiente a cada nivel y año.

Su ausencia es un `COMPLIANCE_BLOCKER`; no debe cubrirse copiando valores de blogs, proveedores, manuales secundarios o ejemplos no versionados.
