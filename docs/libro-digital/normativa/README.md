# Manifiesto de fuentes normativas

Este directorio conserva **metadatos**, no copias de documentos oficiales. El archivo [sources.json](sources.json) registra, para cada fuente, la autoridad, título, acto y fechas disponibles, URL oficial, estado de verificación y observaciones. El campo superior `consulted_on` (`2026-08-12`, `America/Santiago`) identifica la investigación base; las fuentes curriculares verificadas posteriormente declaran `consulted_on: 2026-08-13`. `last_updated_on` indica el último cambio del manifiesto, no la fecha jurídica de los actos.

## Política de bytes y huellas

- `sha256` y `bytes` solo tienen valor cuando durante la investigación se obtuvieron bytes reales del artefacto indicado.
- `null` significa que no se descargó ni archivó un artefacto verificable; no significa hash vacío, validación implícita ni ausencia de cambios.
- No se versionan en este directorio los PDF, XLSX, claves, imágenes, diccionarios ni otros binarios enlazados.
- Un bloqueo WAF, una carpeta visible o el nombre observado de un archivo no autoriza a inferir su contenido, versión, vigencia o huella.
- Una huella prueba identidad de bytes, no vigencia jurídica, aprobación institucional, compatibilidad EDE ni certificación del sistema.

Al corte actualizado el 2026-08-13 siguen existiendo exactamente tres artefactos con bytes y SHA-256 comprobados:

| ID | Bytes | SHA-256 |
|---|---:|---|
| `ede-lcd-mapping` | 312328 | `638eadb82f64a559311c2676ef7004fc70281396c73800923317f7a6fa2e9fd0` |
| `mineduc-rex-3335-2020` | 578886 | `c8e8b791e5a3c6119fcdaaddfee35c993847878bdda156c6937ec9cf3f158aae` |
| `mineduc-rex-917-2021` | 723296 | `0439d3439b64343c223bb91a4bad13fc1614f94d23b197d7c88e3eb5a446c6dd` |

Los bytes de esos tres artefactos tampoco se incluyen en el repositorio. Cualquier importación futura debe obtener el artefacto desde el canal oficial aprobado, calcular tamaño y SHA-256 sobre los bytes realmente recibidos y guardar una nueva evidencia inmutable; no debe sobrescribir la procedencia de una versión ya usada.

La plantilla institucional de importación curricular tiene una huella local reproducible documentada en [CURRICULUM_IMPORT_RUNBOOK.md](../CURRICULUM_IMPORT_RUNBOOK.md), pero **no es una fuente normativa** y no se cuenta entre estos tres artefactos oficiales verificados.

## Uso operativo

Antes de activar una capacidad regulada:

1. revalidar URL, vigencia, modificaciones y autoridad;
2. archivar el artefacto permitido en el repositorio documental privado aprobado, no aquí;
3. registrar tamaño, SHA-256, fecha/hora, responsable y método de obtención;
4. someter la interpretación y el perfil ejecutable a revisión jurídica/institucional;
5. mantener apagada la capacidad si el estado sigue `blocked_*` o si falta el artefacto íntegro.

Este manifiesto no constituye asesoría jurídica, certificación MINEDUC/Superintendencia ni una release EDE fijada.
