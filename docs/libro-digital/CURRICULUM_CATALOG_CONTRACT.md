# Contrato de catálogos curriculares

## 1. Alcance y estado

Este documento fija el contrato normativo mínimo para representar currículo oficial en el Libro Digital. Su alcance institucional inicial comprende `NT1`, `NT2`, `1B`–`8B` y `1M`–`4M`.

> Estado técnico local al **2026-08-14**: existe un catálogo activado con 6.337 objetivos, 6 fuentes, 12.674 relaciones N:M y 4 vínculos; el blocker técnico curricular está cerrado y `lcd_enabled` está activo localmente. Esto no certifica autenticidad, vigencia o completitud normativa ni constituye UAT/productivo. La solicitud fue de sistema y una misma cuenta aprobó y activó, permitido por la implementación pero inferior al SoD recomendado.

Este contrato no transcribe ni inventa Objetivos de Aprendizaje. Define identificadores, procedencia, validaciones y aprobaciones para que los bytes obtenidos de una fuente primaria puedan importarse de manera reproducible.

## 2. Principios obligatorios

1. La fuente normativa es la Base Curricular vigente aplicable, no una planificación, un recurso de un proveedor ni una selección histórica de OA priorizados.
2. Se conserva el texto y código publicados; una transformación técnica nunca puede cambiar su significado.
3. Cada documento fuente tiene una `source_key` estable, autoridad, URL, acto, versión, vigencia, bytes archivados y SHA-256 real; un hash global del catálogo no sustituye este expediente.
4. Nivel, modalidad, asignatura y tipo de objetivo son dimensiones distintas. Un código de curso por sí solo no determina el catálogo en `3M` o `4M`.
5. La relación entre una asignatura ERP y un catálogo oficial es explícita, temporal, escolar y aprobada. Se prohíbe activarla mediante similitud de nombres.
6. Importar, aprobar y activar son operaciones separadas. Ningún archivo se autoaprueba.
7. Una nueva versión no sobrescribe ni elimina la anterior. Las sesiones conservan los snapshots de los objetivos usados.
8. Ante fuente incompleta, ambigua, no vigente o no reconciliada, la importación falla cerrada.

## 3. Códigos de nivel institucionales permitidos

`grade_code` solo admite, para este alcance, los siguientes valores exactos:

| Código | Etiqueta | Familia oficial mínima | Observación normativa |
|---|---|---|---|
| `NT1` | Primer Nivel de Transición | BCEP 2018, tramo `NT` | La Base Curricular agrupa los OA por **Nivel Transición**. `NT1` es un alcance institucional; no autoriza crear una lista OA distinta. |
| `NT2` | Segundo Nivel de Transición | BCEP 2018, tramo `NT` | Misma lista normativa del tramo `NT`, salvo que un instrumento oficial aplicable distinga expresamente NT1/NT2. |
| `1B`–`6B` | 1.º a 6.º básico | Bases 1.º–6.º, DS 433/2012 y DS 439/2012 | El catálogo debe distinguir asignatura y curso. |
| `7B`, `8B`, `1M`, `2M` | 7.º básico a 2.º medio | Bases 7.º–2.º, DS 614/2013 y DS 369/2015 | El catálogo debe incluir las asignaturas cubiertas por cada acto. |
| `3M`, `4M` | 3.º y 4.º medio | DS 193/2019 para FG/HC; DS 452/2013 y modificaciones para TP | Es obligatorio declarar `curriculum_track`; el nivel por sí solo es insuficiente. |

No se aceptan alias como `1°B`, `PRIMERO`, `PK`, `KINDER`, `I MEDIO` o números SIGE en la columna canónica. Pueden resolverse en una tabla de correspondencias institucional revisada, pero el dato persistido debe ser uno de los códigos anteriores.

### 3.1 Nivel oficial versus nivel institucional

El manifiesto debe conservar ambos conceptos:

- `grade_code`: código institucional controlado de esta sección;
- `official_level_code`: identificador del tramo o curso tal como se interpretó desde la fuente;
- `source_locator`: página, sección o URL que permite verificar esa interpretación.

Para Parvularia, el mapeo mínimo permitido es:

| `grade_code` | `official_level_code` | Regla |
|---|---|---|
| `NT1` | `NT` | Puede usar los OA/OAT oficiales del tramo `NT`; no se renumeran. |
| `NT2` | `NT` | Puede usar los OA/OAT oficiales del tramo `NT`; no se renumeran. |

Un Programa Pedagógico puede orientar progresión o implementación, pero no reemplaza silenciosamente la Base Curricular ni convierte una sugerencia en OA obligatorio.

## 4. Familias de catálogo válidas

Cada lote declara una familia. Los códigos siguientes son identificadores internos controlados; no pretenden ser números de acto ni códigos emitidos por MINEDUC.

| `catalog_family` | Niveles | Fuente primaria exigida | Segmentación mínima |
|---|---|---|---|
| `BCEP_2018_NT` | `NT1`, `NT2` | Bases Curriculares de Educación Parvularia, DS 481/2018 | ámbito, núcleo, tramo `NT`, tipo OA/OAT |
| `BBCC_1B_6B_2012` | `1B`–`6B` | DS 433/2012 + DS 439/2012 y publicación UCE vigente | asignatura, curso, eje/habilidad/actitud cuando aplique |
| `BBCC_7B_2M_2013_2015` | `7B`, `8B`, `1M`, `2M` | DS 614/2013 + DS 369/2015 y publicación UCE vigente | asignatura, curso, eje/habilidad/actitud cuando aplique |
| `BBCC_3M_4M_FG_HC_2019` | `3M`, `4M` | DS 193/2019 y publicación UCE vigente | track técnico `GENERAL` o `HC`, asignatura/área, curso o ciclo según fuente |
| `BBCC_3M_4M_TP_2013` | `3M`, `4M` | DS 452/2013 y modificaciones vigentes | `TP`, sector, especialidad, mención, OA/OAG |

Una familia no equivale a un solo documento ni necesariamente a una sola fila de `lcd_curriculum_catalogs`: 1.º–6.º y 7.º–2.º, por ejemplo, dependen de más de un acto. Si la fuente publica códigos locales repetidos como `OA 1`, se conserva ese código exacto y la identidad técnica se determina con un `objective_key` hash de código, tipo, asignatura, nivel, grado, track y eje. No se fabrica un prefijo global para resolver la colisión. La migración `000010` reemplaza la unicidad `catalog+code` por `catalog+objective_key`.

### 4.1 Formación de 3.º y 4.º medio

El contrato técnico actual de `curriculum_track` admite:

- `PARVULARIA`: solo para el nivel Parvularia;
- `GENERAL`: formación general o alcance general, cuya interpretación exacta debe constar en el catálogo;
- `HC`: formación diferenciada humanístico-científica;
- `TP`: formación diferenciada técnico-profesional.
- `ARTISTICA`: valor técnico reservado; no puede activarse hasta incorporar y aprobar la fuente oficial aplicable en este registro.

La importación se bloquea si `grade_code` es `3M` o `4M` y falta esta dimensión: el validador exige `GENERAL`, `HC`, `TP` o `ARTISTICA` y conserva el track en objetivos y vínculos. Para `TP` también se requieren normativamente sector, especialidad y, si corresponde, mención, dimensiones que aún no están completas en el contrato ejecutable. No se deben mezclar objetivos GENERAL, HC, TP o artísticos bajo una misma relación de asignatura.

## 5. Tipos de objetivo

`objective_type` es un catálogo cerrado:

| Valor | Uso permitido |
|---|---|
| `OA` | Objetivo de Aprendizaje identificado como tal por la fuente. |
| `OAT` | Objetivo de Aprendizaje Transversal. En Parvularia se usa para los núcleos transversales definidos por BCEP. |
| `OAH` | Objetivo de Aprendizaje de Habilidad cuando la publicación oficial lo rotula así. |
| `OAA` | Objetivo de Aprendizaje de Actitud cuando la publicación oficial lo rotula así. |
| `OAG` | Objetivo de Aprendizaje Genérico de formación TP, solo con fuente DS 452 aplicable. |

No se infiere el tipo por palabras de la descripción. Si la fuente utiliza otra categoría, se abre un cambio de contrato y una revisión curricular antes de importarla. Los indicadores de evaluación sugeridos se conservan separadamente en `indicators`; no se transforman en OA.

`CurriculumImportValidator` admite además `OAC`, pero este contrato no le asigna significado normativo porque no se incorporó una fuente oficial que defina su uso y alcance. Una fila `OAC` debe fallar cerrada en la revisión curricular hasta ampliar este contrato; no se recodifica como otro tipo. Que el validador acepte sintácticamente `OAG` u `OAC` no demuestra que el lote pertenezca a una fuente TP/artística aplicable.

## 6. Identidad y fidelidad del objetivo

Cada objetivo debe conservar como mínimo:

- familia, catálogo y versión;
- `grade_code` y `official_level_code`;
- modalidad/trayectoria cuando aplique;
- código y nombre de asignatura, ámbito o especialidad oficial;
- `objective_type`;
- `objective_code` exactamente trazable a la fuente;
- `objective_key` técnico reproducible, sin alterar el código oficial visible;
- descripción íntegra sin resumen editorial;
- eje, núcleo, ámbito, habilidad o unidad únicamente si la fuente los declara;
- localizador verificable dentro del artefacto fuente;
- estado y vigencia.

Se permite normalizar finales de línea, espacios Unicode equivalentes y codificación para comparar, pero se debe conservar el valor publicado. Se prohíbe:

- renumerar OA;
- traducir o parafrasear descripciones;
- completar fragmentos desde memoria o fuentes secundarias;
- reutilizar un OA de otro curso por similitud textual;
- clasificar como obligatorio un recurso, indicador o priorización voluntaria;
- crear una división NT1/NT2 no publicada.

## 7. Fuentes oficiales por ciclo

| Alcance | Navegación UCE/MINEDUC | Artefacto UCE observado | Acto en BCN |
|---|---|---|---|
| Parvularia `NT` | [Bases de Educación Parvularia](https://www.curriculumnacional.cl/curriculum/educacion-parvularia) | [Ficha BCEP vigentes desde 2019](https://www.curriculumnacional.cl/recursos/educacion-parvularia-vigentes-2019); [PDF](https://www.curriculumnacional.cl/614/articles-69957_bases.pdf) | [DS 481/2018](https://www.bcn.cl/leychile/navegar?idNorma=1114961) |
| `1B`–`6B` | [Bases 1.º–6.º](https://www.curriculumnacional.cl/curriculum/1o-6o-basico) | [Ficha](https://www.curriculumnacional.cl/recursos/bases-curriculares-1-6-basico); [PDF](https://www.curriculumnacional.cl/614/articles-22394_bases.pdf) | [DS 439/2012](https://www.bcn.cl/leychile/navegar?idNorma=1036799), [DS 433/2012](https://www.bcn.cl/leychile/navegar?idNorma=1047359) |
| `7B`–`2M` | [Bases 7.º–2.º](https://www.curriculumnacional.cl/curriculum/7o-basico-2o-medio) | [Ficha](https://www.curriculumnacional.cl/node/3670); [PDF](https://www.curriculumnacional.cl/614/articles-37136_bases.pdf) | [DS 614/2013](https://www.bcn.cl/leychile/navegar?idNorma=1059966), [DS 369/2015](https://www.bcn.cl/leychile/navegar?idNorma=1084868) |
| `3M`–`4M` FG/HC | [Bases 3.º–4.º](https://www.curriculumnacional.cl/curriculum/3o-4o-medio) | [Ficha](https://www.curriculumnacional.cl/recursos/bases-curriculares-3-4-medio); [PDF](https://www.curriculumnacional.cl/614/articles-91414_bases.pdf) | [DS 193/2019](https://www.bcn.cl/leychile/navegar?idNorma=1136078) |
| `3M`–`4M` TP | [Bases TP](https://www.curriculumnacional.cl/curriculum/3o-4o-medio-tecnico-profesional) | Documentos por especialidad enlazados desde UCE | [DS 452/2013](https://www.bcn.cl/leychile/navegar?idNorma=1056485) y modificaciones vigentes |

La [cartilla oficial de Bases Curriculares vigentes 2026](https://www.curriculumnacional.cl/sites/default/files/adjuntos/recursos/2026-04/Cartilla_N1.pdf) señala que la priorización 2023 terminó en diciembre de 2025 y que desde 2026 los establecimientos gestionan las Bases Curriculares sin priorización. Un documento de “OA priorizados” no puede poblar el universo esperado de 2026.

### 7.1 Alcances de fuente controlados

La importación usa exactamente estos `source_scope` técnicos. Son agrupadores de cobertura, no identificadores de documentos ni prueba de vigencia:

| `source_scope` | Grados | Track esperado | Evidencia mínima |
|---|---|---|---|
| `PARVULARIA_NT1_NT2` | `NT1`, `NT2` | `PARVULARIA` | BCEP/artefacto de texto y DS 481/2018, vinculados según rol. |
| `GENERAL_1B_6B` | `1B`–`6B` | `GENERAL` | Cada publicación/acto aplicable; DS 439 y DS 433 no se fusionan bajo una fuente ficticia. |
| `GENERAL_7B_2M` | `7B`, `8B`, `1M`, `2M` | `GENERAL` | Cada publicación/acto aplicable; DS 614 y DS 369 conservan `source_key` distintas. |
| `HC_3M_4M` | `3M`, `4M` | `GENERAL` o `HC` | Artefacto vigente y DS 193/2019; el nombre del scope es histórico y no reclasifica Formación General como HC. |
| `TP_3M_4M` | `3M`, `4M` | `TP` | Documento por sector/especialidad/mención y DS 452/2013/modificaciones aplicables. |
| `ARTISTICA_3M_4M` | `3M`, `4M` | `ARTISTICA` | Fuente oficial específica aún no incorporada en este registro; permanece fail-closed. |

Cada documento recibe una `source_key` única dentro del catálogo. El `source_scope` puede repetirse entre varios documentos. `curriculum_track`, asignatura y `objective_type` pueden restringir más su cobertura; nunca la amplían fuera del scope.

`HC_3M_4M` se conserva sin renombrar porque forma parte del contrato técnico histórico (enum, plantilla y migraciones). Conforme a las Bases de Formación General y Formación Diferenciada aprobadas por el DS 193/2019, su cobertura técnica incluye tanto `curriculum_track=GENERAL` para Matemática y las demás asignaturas de Formación General de 3M/4M como `curriculum_track=HC` para profundización Humanístico-Científica. El validador distingue ambos valores; compartir `source_scope` no permite mezclar tracks, omitir el track real ni convertir objetivos FG en HC.

### 7.2 Relación N:M objetivo–fuente

Cada objetivo debe tener **exactamente una** relación `canonical_text` con `source_key` y localizador verificable. Además puede relacionarse con fuentes de estos roles:

| `source_role` | Semántica |
|---|---|
| `canonical_text` | Artefacto oficial cuyos bytes contienen el texto/código conciliado. Es obligatorio y único por objetivo. |
| `legal_basis` | Acto que aprueba o da fundamento jurídico al contenido. No sustituye el artefacto de texto si no lo contiene. |
| `amendment` | Modificación aplicable que debe considerarse para vigencia/contenido. |
| `supersedes` | Documento que reemplaza una versión anterior; exige versionado forward-only. |
| `complementary` | Contexto oficial adicional. No cuenta como texto canónico ni cierra evidencia faltante. |

Las fuentes complementarias no deben quedar como una lista global sin alcance: se vinculan al objetivo al que aplican o se conservan solo como metadata no habilitante. Una relación es inválida si la fuente no existe, está fuera de `source_scope`, contradice track/asignatura/tipo, carece de bytes verificados o usa un localizador ambiguo.

### 7.3 Formatos oficiales comprobados

- UCE publica navegación HTML por base, asignatura/ámbito y curso, además de PDF descargable.
- No se identificó al 2026-08-13 una descarga oficial integral CSV, XLS o XLSX de OA/OAT.
- El dominio expone un [índice JSON:API](https://www.curriculumnacional.cl/jsonapi) y un [recurso técnico de objetivos](https://www.curriculumnacional.cl/jsonapi/cn_learning_objective/cn_learning_objective), pero no se encontró documentación MINEDUC que lo declare un contrato público estable. Algunas relaciones presentan acceso restringido. No basta por sí solo para aprobar un catálogo.
- El XLSX descrito en el runbook es un **formato institucional de transporte**, no un formato oficial MINEDUC.

## 8. Relación con asignaturas del ERP

Una fila de `lcd_subject_curriculum_links` solo puede activarse si:

1. la escuela y el año académico existen y coinciden con el alcance aprobado;
2. la asignatura ERP existe, está vigente y su dueño funcional confirmó su significado;
3. el catálogo está activo, hashado y cubre el nivel/modalidad de la asignatura;
4. el nivel del curso pertenece al catálogo;
5. la vigencia del vínculo cae dentro de la vigencia del catálogo;
6. no hay otro vínculo activo incompatible para el mismo contexto;
7. la decisión consta en el manifiesto de aprobación.

Una coincidencia de texto, abreviatura o código local puede proponerse como borrador, nunca activarse automáticamente.

La migración `000008` añade `scope_key` y `000009` añade `curriculum_track` al objetivo/vínculo. El validador deriva el alcance como `<LEVEL_CODE>:<GRADE_CODE>:<CURRICULUM_TRACK|ALL>` (por ejemplo, `MEDIA:3M:HC`); `ALL` es solo un fallback técnico y no autoriza omitir modalidad en 3M/4M. Sector, especialidad y mención TP aún no están representados.

## 9. Versionado e inmutabilidad

- `catalog_code + catalog_version` identifica una versión importada.
- `lcd_curriculum_import_batches.source_hash` identifica los bytes reales del archivo de transporte recibido (por ahora XLSX); `declared_source_hash` de `Catalogo` es solo una declaración resumen y no representa por sí sola el corpus NT1–4M.
- `lcd_curriculum_catalogs.source_hash` identifica el payload canónico portable del corpus: código/nombre/versión/autoridad/vigencia del catálogo, fuentes, objetivos y relaciones objetivo–fuente ordenadas. Excluye los campos legacy `Catalogo.source_url/source_sha256`, RBD, escuela/año, IDs internos, filas, rutas privadas, cobertura y `Vinculos`; el mismo corpus puede reutilizarse entre establecimientos sin mezclar sus expedientes.
- Cada fila `Fuentes` declara su propio SHA-256, que debe coincidir con `evidence_files[SOURCE_KEY]`. La evidencia se archiva cifrada y la activación debe usar el hash calculado sobre esos bytes, no uno inferido de URL o metadatos.
- `lcd_curriculum_sources` conserva la fuente verificada por documento y `lcd_learning_objective_sources` materializa la relación N:M con rol, localizador, snapshot y hash de relación.
- `source_page` y `source_row_hash` permiten trazar una fila a su localizador y contenido normalizado; la activación calcula el hash de fila, pero falta verificarlo contra una extracción aprobada de la fuente oficial.
- `manifest_hash` identifica reglas, conteos, corpus y alcance del lote; `catalog_payload_hash` fija por separado el corpus portable.
- `decision_hash` identifica exactamente el `decision_manifest` que se aprobó para activar.
- Una versión reemplazante crea una nueva activación y marca la anterior `superseded`; no borra objetivos ni vínculos históricos.
- `revoked` impide uso futuro, pero conserva toda evidencia y no modifica sesiones existentes.
- Un cambio de bytes, acto, vigencia, texto, código, mapping o alcance produce nueva versión; no se corrige en sitio.

## 10. Condición de uso oficial

La presencia de filas en las tablas curriculares no prueba conformidad. Un catálogo es utilizable solo cuando el lote, sus evidencias y la activación completaron el flujo de [CURRICULUM_IMPORT_RUNBOOK.md](CURRICULUM_IMPORT_RUNBOOK.md), el alcance curricular está completo y el preflight vuelve a ejecutarse sin el blocker correspondiente.
