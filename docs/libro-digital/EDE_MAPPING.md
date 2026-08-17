# Mapeo EDE/CEDS

## Estado

El modelo de mapeo y la proyección están **implementados parcialmente, pero no importados ni validados con los artefactos oficiales requeridos**. Existen tablas versionadas y `EdeProjectionService`, que agrupa mappings activos y solo permite transformaciones declarativas `direct`, `string`, `integer`, `boolean`, `date` y `enum_map`. `EdeExportService` y su job generan un manifest y staging cifrado. El comando `lcd:ede:import-standard` y `POST /api/libro-digital/v1/ede/import-standard` aceptan archivos locales explícitos y calculan huellas reales; HTTP archiva los cuatro artefactos cifrados, prohíbe activación/aprobación y solo deja `imported`. Ninguno descarga fuentes de forma implícita. Aun así no existe una versión/mappings EDE oficial activa y confiable; la versión/digest/argv del contenedor no están fijados y el validador oficial no se ha ejecutado.

Este documento registra artefactos consultados y diseña el proceso; no declara que el conjunto sea completo ni certificado.

## Artefactos oficiales consultados

| Artefacto | Evidencia | Estado local |
|---|---|---|
| Política EDE | [REX 3335/2020 y REX 917/2021](NORMATIVE_SOURCES.md#resoluciones-y-circulares-sobre-ede-y-registros) | PDFs descargados y hashados. |
| Diccionario | [Carpeta oficial](https://drive.google.com/drive/folders/1QCSQIM1T3INcqNZefMi6xknz2Ag2xwg2) | Se observaron CEDS/NDS 7.1; archivos no importados ni hashados. |
| Modelo ER | [Carpeta oficial](https://drive.google.com/drive/folders/1RoRFrg8kEv7ZtETv651MwCpX3QuEzFll) | Se observó `LibroDeClasesDigital_v02.svg`; no importado. |
| Mapeo LCD | [Google Sheet oficial](https://docs.google.com/spreadsheets/d/1W5JNVZmO2_kYjvSU8zRQF-lMBCxDdvxguVobTQ0Jd-w/edit) | XLSX exportado: SHA-256 `638eadb82f64a559311c2676ef7004fc70281396c73800923317f7a6fa2e9fd0`. |
| Contenedor | [Página oficial](https://www.ede.mineduc.cl/desarrolladores/contenedor), [repositorio](https://github.com/Admin-EDE/DockerEdeCode) | Se documentan `parse`, `insert`, `check`; imagen sin digest. |
| Clave pública | [Superintendencia](https://static.superintendencia-educacion.cl/KP/clave.pub.txt) | No descargada/hashada. |

Los nombres CEDS 7.1 y ER v02 son observaciones, no una versión EDE seleccionada. `LCD_EDE_VERSION` y `LCD_EDE_VALIDATOR_DIGEST` deben permanecer vacíos hasta completar la importación controlada.

## Hojas del mapeo oficial

La exportación consultada contiene 12 hojas:

1. Registro de matrícula.
2. Registro de antecedentes de estudiantes.
3. Registro salidas o retiros.
4. Registro de control de Asignatura.
5. Registro de Asistencia.
6. Resumen Asistencia Mes.
7. Registro de actividades.
8. Registro Evaluaciones y Subsectores.
9. Temario reunión apoderados.
10. Asistencia Reunión Apoderados.
11. Registro de anotaciones de convivencia escolar.
12. Registro Aula y PIE.

## Referencias observadas: no sembrar todavía

| Concepto | Expresión/código observado | Estado de control |
|---|---:|---|
| RUN | `RefPersonIdentificationSystemId == 51` | Pendiente de contrastar con `NDS-Reference-v7_1.xlsx` importado. |
| IPE | `== 52` | Pendiente de contraste/validador. |
| Documento de país de origen | `== 53` | Pendiente de contraste/validador. |
| Número de lista | `== 54` | Pendiente de contraste/validador. |
| Número correlativo de matrícula | `== 55` en una sección | **Contradicción:** otra expresión del mismo XLSX usa `== 43 (School)`. Bloqueado. |
| Establecimiento escolar | `Organization.RefOrganizationTypeId == 10` | Pendiente de catálogo importado. |
| Course | `RefOrganizationTypeId == 21` | Pendiente de catálogo importado. |
| Course Section / subject | `RefOrganizationTypeId == 22` | La semántica exacta debe verificarse por entidad/campo. |
| Rol estudiante | `RoleId == 6` | Pendiente de catálogo importado. |
| Evento DailyAttendance | `RefAttendanceEventTypeId == 1` | Pendiente de catálogo importado. |
| Presente | `RefAttendanceStatusId == 1` | No inferir el resto de estados por secuencia. |
| Jerarquía educacional | 38 modalidad, 39 jornada, 40 nivel, 41 rama, 42 sector, 43 especialidad, 44 tipo curso, 45 código enseñanza, 46 grado | Requiere catálogo/referencia y validación oficial. |

### Bloqueo matrícula 43/55

El XLSX consultado contiene simultáneamente:

- una regla de “número de matrícula” con `RefPersonIdentificationSystemId == 55 (School)`;
- otra regla, en contexto de asistencia, que dice `RefPersonIdentificationSystemId == 43 (School)`.

No se debe elegir uno por intuición. El criterio de cierre es:

1. importar y hashar el diccionario/reference oficial de la misma versión;
2. resolver la expresión con el responsable EDE o una fuente oficial inequívoca;
3. documentar la decisión, fuente y fecha;
4. ejecutar `parse/insert/check` sobre fixtures anonimizados;
5. conservar el reporte y pruebas de regresión.

## Modelo de mapeo local

`lcd_ede_mappings` implementa reglas declarativas por `ede_version_id`:

| Campo local | Uso |
|---|---|
| `code` | Identificador estable de la regla. |
| `source_entity`, `source_field` | Origen operacional explícito. |
| `target_record_type`, `target_field` | Destino EDE. |
| `data_type` | Tipo normalizado esperado. |
| `required` | Requerimiento base; condiciones dinámicas viven versionadas. |
| `transform_definition` | Transformación declarativa con nombre/version; no código arbitrario. |
| `validation_definition` | Reglas de dominio/referencia. |
| `default_value` | Solo si el estándar autoriza un default; jamás datos inventados. |
| `mapping_hash` | SHA-256 de la representación canónica de la regla. |
| `effective_from/to`, `active` | Vigencia sin eliminar versiones anteriores. |

La tabla implementada es más compacta que el modelo detallado originalmente propuesto. El proyector consume hoy `source_entity`, `source_field`, `target_record_type`, `target_field`, `required`, `default_value` y `transform_definition`; no ejecuta `validation_definition` como un motor completo ni resuelve catálogos oficiales. Antes de importar debe decidirse si requiere columnas separadas para catálogo de referencia, condición, clase de transformación y referencia normativa. Esa evolución será aditiva.

## Mapeo conceptual inicial

Esto describe fuentes locales, no campos EDE definitivos:

| Dominio LCD | Origen operacional | Familia EDE sugerida por artefactos | Controles antes de proyectar |
|---|---|---|---|
| Escuela | `lcd_schools`, snapshots de `lcd_books` | Organization | RBD, tipo de organización, vigencia. |
| Persona/identificador | `student_profiles`, `lcd_enrollment_links` | Person / PersonIdentifier | RUN/IPE/documento cifrado; sistema de identificación versionado. |
| Matrícula/rol | `student_enrollments`, `lcd_enrollment_links` | OrganizationPersonRole / K12StudentEnrollment | fecha, estado y curso vigentes; no alterar snapshots. |
| Curso/asignatura | `lcd_teaching_groups`, `schedule_subjects` | Organization / course/section | códigos solo desde catálogos oficiales. |
| Docente/asignación | `lcd_teacher_assignments`, `staff` | Role/assignment relacionados | firma propia y vigencia. |
| Sesión/actividad | `lcd_class_sessions`, topics/objectives/activities | Registro de control/actividades | revisión firmada, fechas RFC 3339, OA snapshot. |
| Asistencia | `lcd_session_attendance`, cierres | AttendanceEvent/Status | distinguir bloque/diaria/subvención/SIGE; no inferir. |
| Evaluación | assessments/results/closures | Evaluaciones y subsectores | escala/reglamento/versiones y situación final. |
| Convivencia | `lcd_coexistence_entries` | Anotaciones de convivencia | minimización y nivel de reserva. |
| PIE/aula | `lcd_pie_support_records` | Registro Aula y PIE | solo información requerida; no exportar diagnósticos innecesarios. |
| Retiros/salidas | entidades ERP existentes y snapshots | Salidas o retiros | preservar historial y autorización. |

## Pipeline requerido

```mermaid
flowchart LR
    acquire["Adquirir artefactos oficiales"] --> hash["Registrar bytes, URL, fecha y SHA-256"]
    hash --> import["Importar diccionario/referencias/mapeo"]
    import --> normalize["Normalizar a lcd_ede_versions/mappings/catalogs"]
    normalize --> review["Revisión humana y contradicciones"]
    review --> fixtures["Fixtures anonimizados y pruebas"]
    fixtures --> project["Proyección desde snapshot consistente"]
    project --> official["parse / insert / check oficial"]
    official --> evidence["Reporte, digest, hashes y aprobación"]
```

Componentes aún no implementados o sin cierre:

- `EdeStandardImporter` y `EdeDictionaryParser`;
- `EdeReferenceCatalogImporter`;
- `EdeMappingImporter`;
- importación de schema/reference/mapping con verificación de cobertura;
- validador local de schema/catálogos y cardinalidades;
- builder del formato de entrada exacto exigido por el contenedor;
- snapshot transaccional consistente para `EdeProjectionService`;
- integración probada del `EdeValidatorRunner` con el release/digest/argv oficiales.

Componentes presentes, aún bloqueados:

- `EdeProjectionService`: genera registros por mapping y manifest/hashes, pero `sourceDataset()` realiza varias consultas sin snapshot de BD fijo;
- `EdeExportService` + `GenerateLibroDigitalEdeExport`: gate, deduplicación y staging JSON cifrado; termina `generated` con `validator_status=not_run`;
- `EdeValidatorRunner`: argv allowlist y contenedor sin red/read-only/capabilities, reporte cifrado y sanitización; no ejecutado, y su contrato/formato de entrada aún no se ha demostrado.

## Controles de importación

1. Fuente debe ser dominio oficial y estar listada en `lcd_normative_sources`.
2. Guardar copia privada inmutable, tamaño, MIME real, SHA-256 y fecha de consulta.
3. No permitir que una nueva importación edite una versión usada; crear otra versión.
4. Validar unicidad de códigos y referencias, tipos, claves y cardinalidades.
5. Rechazar referencias desconocidas; no asignar el “más parecido”.
6. Comparar filas/hojas esperadas y emitir reporte de cobertura.
7. Hacer diff entre versiones y requerir aprobación para cambios semánticos.
8. No importar fórmulas/links/macros como código ejecutable.
9. No incluir datos personales en fixtures.
10. El seeder solo referencia artefactos importados; nunca copia manualmente una porción y la declara completa.

## Controles de proyección

- scope por escuela/año/libro y perfil normativo;
- transacción o snapshot consistente con `source_snapshot_hash`;
- selección explícita de revisión firmada/cerrada;
- normalización de fechas/offset y strings sin pérdida;
- cada registro proyectado conserva referencia local interna no expuesta en el paquete final cuando no corresponda;
- conteos por entidad y reconciliación con universo esperado;
- validaciones de required, tipo, longitud y referencia antes del contenedor;
- warnings y errors estructurados, sin PII en logs;
- reproducibilidad: misma fuente+mapping+versión produce hashes equivalentes, salvo metadata temporal explícita;
- una enmienda invalida/stalea las exportaciones cuyo snapshot incluía la revisión anterior.

## Lenguaje permitido

| Situación | Texto permitido |
|---|---|
| Antes de importar estándar | “Exportación EDE no configurada”. |
| Importado, sin validador | “Proyección preliminar; validación oficial no ejecutada”. |
| Validador con errores | “Validación EDE fallida; no apto para liberar”. |
| Validador exitoso y evidencia completa | “Validado técnicamente contra EDE versión `{version}` con validador `{digest}` el `{timestamp}`”. |
| Siempre | “El software no está certificado por MINEDUC/Superintendencia”. |

Hoy solo aplican los dos primeros mensajes y `validator_status=not_run`.
