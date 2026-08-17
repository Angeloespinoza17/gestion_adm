# Visualizaciones del catálogo curricular

## Propósito y alcance

Este módulo permite explorar el catálogo de Objetivos de Aprendizaje (OA) con nueve vistas intercambiables: Tabla, Treemap, Sunburst, Círculos, Sankey, Icicle, Árbol radial, Red curricular y Mapa mental. La tabla paginada sigue siendo la vista predeterminada y la fuente funcional de sus filtros; las vistas gráficas consultan una agregación de todos los resultados filtrados, no solo la página visible.

Las visualizaciones describen la estructura del catálogo. No calculan avance, cobertura, logro, rendimiento, planificación ni prioridad pedagógica. En todas las vistas, el área, diámetro, grosor o tamaño de un elemento representa **cantidad de OA únicos**, nunca importancia curricular.

## Arquitectura

```text
CurriculumObjectivesSection.vue
  ├─ filtros y tabla existentes
  └─ CurriculumVisualizationPanel.vue
       ├─ useCurriculumVisualization.js
       │    ├─ URL y preferencias locales
       │    ├─ debounce, cancelación y caché de sesión
       │    └─ libroDigitalApi.curriculumVisualization()
       └─ visualizationRegistry.js
            ├─ ECharts: Treemap, Sunburst, Sankey, Árbol radial y Red
            ├─ d3-hierarchy + SVG: Círculos e Icicle
            └─ Vue Flow: Mapa mental de solo lectura

GET /api/libro-digital/v1/curriculum/objectives/visualization
  ├─ CurriculumObjectiveVisualizationRequest
  ├─ CurriculumExplorerService::query()       (consulta compartida con la tabla)
  ├─ CurriculumObjectiveVisualizationService  (agregación SQL y caché)
  ├─ CurriculumHierarchyBuilder               (árbol normalizado)
  └─ CurriculumGraphBuilder                   (grafo jerárquico acotado)
```

El frontend usa Vue 3 con Composition API y `<script setup>`, Vue Router, Axios y estado local. Este módulo no introduce un store global. El registro usa `defineAsyncComponent`, por lo que D3, Vue Flow y los módulos de ECharts no se cargan al permanecer en la tabla.

### Dependencias de visualización

-   `echarts` y `vue-echarts`: Treemap, Sunburst, Sankey, árbol radial, red, tooltips, ARIA y captura gráfica para PDF.
-   `d3-hierarchy`: `hierarchy`, `pack` y `partition` para Círculos e Icicle.
-   `@vue-flow/core`, `@vue-flow/background`, `@vue-flow/minimap` y `@vue-flow/controls`: mapa mental de solo lectura.
-   `vitest`, `@vue/test-utils` y `jsdom`: pruebas unitarias del frontend.

ECharts se registra por módulos en `curriculum-visualizations/echarts.js`; no se importa su distribución completa. El proyecto conserva sus otras librerías de gráficos.

## Endpoint agregado

```http
GET /api/libro-digital/v1/curriculum/objectives/visualization
```

La ruta está declarada antes de `/curriculum/objectives/{objective}` para que `visualization` no sea interpretado como un identificador de OA.

### Contexto y filtros

`school_id` y `academic_year_id` son obligatorios. El endpoint acepta el mismo vocabulario que el catálogo:

| Parámetro                                        | Uso                                                            |
| ------------------------------------------------ | -------------------------------------------------------------- |
| `book_id`                                        | Limita al libro y conserva sus reglas de selección curricular. |
| `course_section_id`, `course_label`              | Contexto de curso.                                             |
| `catalog_id`                                     | Catálogo curricular.                                           |
| `level_code`                                     | Nivel educativo.                                               |
| `grade_code`                                     | Grado.                                                         |
| `curriculum_track`                               | Formación o trayectoria.                                       |
| `schedule_subject_id`, `subject_code`, `subject` | Asignatura.                                                    |
| `objective_type`                                 | Tipo de objetivo.                                              |
| `axis_code`                                      | Eje, núcleo o agrupador curricular.                            |
| `source`                                         | Fuente canónica.                                               |
| `status`                                         | `all`, `active` o `inactive`.                                  |
| `query`                                          | Búsqueda por código o texto.                                   |

`page` y `per_page` pertenecen a la tabla y se eliminan antes de solicitar la visualización. Así se evita construir un gráfico a partir de una sola página.

Los parámetros propios de las vistas son:

| Parámetro        | Valores y reglas                                                                                   |
| ---------------- | -------------------------------------------------------------------------------------------------- |
| `view`           | `treemap`, `sunburst`, `circle_packing`, `sankey`, `icicle`, `radial_tree`, `network`, `mind_map`. |
| `hierarchy`      | Lista separada por comas o arreglo de 1 a 5 dimensiones permitidas.                                |
| `scope`          | `filtered` (predeterminado) o `catalog`.                                                           |
| `max_depth`      | Entero entre 1 y 5, sujeto al límite específico de cada vista.                                     |
| `include_leaves` | Booleano; solicita OA hoja cuando la rama cabe bajo el umbral.                                     |
| `root_node`      | Identificador opaco de un nodo devuelto previamente para drilldown.                                |

`scope=filtered` conserva todos los filtros visibles. `scope=catalog` retira filtros de presentación, pero nunca el tenant, año, catálogo activo ni las restricciones de un libro/curso autorizado.

Ejemplo:

```http
GET /api/libro-digital/v1/curriculum/objectives/visualization?school_id=1&academic_year_id=1&subject_code=FIL&status=all&view=treemap&hierarchy=subject,curricular_group,grade,objective&scope=filtered&max_depth=4&include_leaves=true
```

### Dimensiones permitidas

Laravel valida las dimensiones con una lista cerrada antes de construir cualquier agregación:

```text
catalog
education_level
grade
formation
subject
curricular_group
objective_type
source
status
objective
```

`objective` solo puede ocupar la última posición. Los nombres enviados por el cliente nunca se usan como columnas SQL arbitrarias. `ambit`, `nucleus` y `axis` son tipos semánticos admitidos por el contrato del frontend, pero no dimensiones consultables mientras no existan como clasificaciones persistidas e inequívocas en el modelo actual.

### Contrato de respuesta

La respuesta contiene un único contrato para todas las vistas:

```json
{
    "meta": {
        "scope": "filtered",
        "view": "treemap",
        "total_objectives": 64,
        "filtered_total_objectives": 64,
        "total_subjects": 1,
        "total_curricular_groups": 6,
        "available_objectives": 64,
        "unavailable_objectives": 0,
        "hierarchy": ["subject", "curricular_group", "grade", "objective"],
        "max_depth": 4,
        "leaves_included": true,
        "aggregated": false,
        "leaf_threshold": 500,
        "root_node": "catalog:<hash>",
        "graph_node_limit": 300,
        "graph_total_nodes": 72,
        "graph_truncated": false
    },
    "root": {},
    "graph": { "nodes": [], "links": [] }
}
```

Los nombres del backend usan `snake_case`; `normalizeVisualizationPayload()` entrega al frontend una forma estable en `camelCase`.

Un nodo jerárquico normalizado contiene `id`, `entityId`, `parentId`, `type`, `name`, `shortName`, `code`, `description`, `value`, `objectiveCount`, `availableCount`, `unavailableCount`, `percentageOfParent`, `depth`, `hasChildren`, `children`, `path` y `metadata`. Los identificadores son opacos y estables para la combinación de tipo y ruta; no deben descomponerse en el navegador.

Un nodo de grafo contiene `id`, `entityId`, `name`, `code`, `type`, `value`, `depth`, `category`, `hasChildren` y `metadata`. Un enlace contiene `source`, `target`, `value`, `relationType` e `isOfficial`.

`filtered_total_objectives` siempre representa el total del filtro base, incluso después de profundizar. `total_objectives` representa el nodo raíz actual. Esta distinción permite verificar que el total de la tabla coincide con el universo gráfico sin confundirlo con el subtotal del drilldown.

## Jerarquías

El preset gráfico predeterminado es:

```text
Asignatura → Eje/Núcleo → Grado → Objetivo
subject,curricular_group,grade,objective
```

Los otros presets compartidos son:

-   Nivel educativo: `education_level,grade,subject,curricular_group,objective`.
-   Formación: `formation,subject,grade,curricular_group,objective`.
-   Tipo de objetivo: `objective_type,subject,curricular_group,objective`.
-   Fuente: `source,subject,grade,objective`.

Los presets viven en `CURRICULUM_HIERARCHY_PRESETS`; las vistas no duplican su lógica. La URL conserva `view`, `hierarchy`, `scope`, `root_node` y el modo de etiquetas. La preferencia de visualización se guarda bajo una clave propia de `localStorage`, sin alterar preferencias globales.

Los OA se cuentan con `COUNT(DISTINCT objectives.id)`. La fuente canónica se reduce a una sola relación por OA y la clasificación principal se construye una vez por ruta. Los valores `NULL`, cadena vacía y `SIN_CODIGO` se consolidan en `Sin clasificación principal`. En Parvularia, si la asignatura ya representa el núcleo y el agrupador repite la misma etiqueta, se omite el nivel duplicado.

## Semántica de cada vista

| Vista          | Lectura e interacción                                                                                                                              |
| -------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Tabla          | Resultado accesible y paginado existente; no solicita el endpoint gráfico.                                                                         |
| Treemap        | Área proporcional a OA; clic en categoría profundiza y clic en OA abre el detalle existente.                                                       |
| Sunburst       | Anillos por nivel jerárquico; el segmento seleccionado pasa a ser la nueva raíz.                                                                   |
| Círculos       | Contención jerárquica determinista mediante `pack()`; el área representa OA.                                                                       |
| Sankey         | Flujos agregados entre un máximo de cuatro columnas; el grosor es cantidad de OA únicos. El tooltip del enlace muestra origen, destino y cantidad. |
| Icicle         | Partición rectangular mediante `partition()`; usa la misma paleta estable y admite navegación por nodo.                                            |
| Árbol radial   | Raíz central; vista inicial limitada a tres niveles y drilldown progresivo hasta hojas.                                                            |
| Red curricular | Grafo de fuerza de las relaciones entregadas por el backend; máximo 300 nodos.                                                                     |
| Mapa mental    | Jerarquía navegable de solo lectura en Vue Flow; máximo 250 nodos. El arrastre es solo visual y no persiste relaciones.                            |

Las vistas de red y mapa mental no permiten crear, editar ni guardar conexiones. Actualmente el grafo contiene únicamente enlaces jerárquicos oficiales generados desde clasificaciones registradas.

Todas las vistas gráficas se renderizan dentro de un lienzo acotado y desplazable. La barra flotante del lienzo permite acercar, alejar, volver al 100 %, activar el modo **Mover** para arrastrar el contenido y exportar sin regresar al encabezado de la página. `Ctrl/Cmd + rueda` también cambia la escala; las barras de desplazamiento permanecen disponibles como alternativa.

## Carga progresiva y límites

-   El backend incluye hojas OA solo si la rama consultada contiene hasta 500 objetivos y `include_leaves=true`.
-   Sobre ese umbral devuelve categorías agregadas. El frontend muestra que se debe seleccionar una categoría para profundizar.
-   El Árbol radial pide como máximo tres niveles en la raíz. Al enviar un `root_node` válido puede pedir la profundidad restante para alcanzar OA.
-   Sankey usa como máximo cuatro dimensiones efectivas, aunque el preset original contenga cinco.
-   Sankey permite mover nodos y, en modo de etiquetas automático, muestra como máximo las doce categorías de mayor volumen por columna. El resto continúa disponible mediante tooltip y drilldown, sin eliminar nodos ni alterar conteos.
-   Red curricular usa hasta 300 nodos.
-   Mapa mental usa hasta 250 nodos.
-   Las etiquetas se ocultan de forma automática cuando no hay espacio legible; el tooltip conserva la información completa.

Los metadatos `aggregated`, `leaves_included`, `graph_total_nodes` y `graph_truncated` permiten anunciar explícitamente cualquier agregación o límite. No se deben aplicar cortes adicionales silenciosos en los componentes.

El composable cancela la solicitud anterior con `AbortController`, aplica debounce a `query`, evita solicitudes idénticas en vuelo y mantiene una caché LRU de sesión de hasta 36 respuestas. Cambiar los filtros restablece el nodo raíz, pero no muta los filtros recibidos.

## Caché del servidor

Las agregaciones usan el store configurado por Laravel, incluido `file`; Redis no es requisito. Los valores predeterminados están en `config/libro_digital.php`:

```text
LCD_CURRICULUM_VISUALIZATION_CACHE_TTL=600
LCD_CURRICULUM_VISUALIZATION_LEAF_THRESHOLD=500
LCD_CURRICULUM_VISUALIZATION_GRAPH_NODE_LIMIT=300
```

Los valores se acotan a máximos seguros. La clave incorpora escuela, año, versiones y hashes de catálogos, activaciones, libro y `lock_version`, además de filtros, scope, vista, jerarquía, raíz, profundidad e inclusión de hojas. La activación o una nueva versión curricular produce otra clave. El TTL limita la vigencia de cambios extraordinarios que no alteren esos identificadores.

## Seguridad y permisos

La ruta comparte el grupo protegido del Libro Digital:

-   autenticación Sanctum;
-   correlación/auditoría de solicitudes;
-   permiso base `libro_digital.access`;
-   módulo habilitado;
-   autorización del request: `libro_digital.lesson.manage` o `libro_digital.books.view`.

El servidor resuelve escuela, año, catálogo, curso y libro; el cliente no puede ampliar el tenant ni el contexto autorizado. Un `root_node` debe cumplir el formato `tipo:<sha256>` y pertenecer al árbol filtrado actual. Los filtros y dimensiones se validan mediante listas permitidas.

Los tooltips usan texto plano/rich text y los SVG exportados escapan contenido. No se utiliza HTML curricular sin sanitizar.

## Accesibilidad

-   Los controles tienen etiquetas visibles o nombres accesibles, estados `aria-pressed` y navegación con teclado.
-   Cada gráfico ofrece una región con nombre y un resumen textual generado por `accessibleVisualizationSummary()`.
-   El resumen comunica total, número de categorías, categoría mayor, estado agregado y la advertencia sobre el significado del tamaño.
-   ECharts tiene habilitado su componente ARIA.
-   Los nodos SVG son enfocables y activables con teclado.
-   Los estados de carga, vacío, error y truncamiento se anuncian sin depender solo del color.
-   El texto de cada nodo elige automáticamente entre tinta oscura y blanco según su relación de contraste; además, color, forma y etiquetas se combinan para que el color no sea el único canal informativo.
-   Las animaciones se desactivan cuando el usuario solicita movimiento reducido.

## Exportación PDF

Cada componente expone `exportImage()`. ECharts usa `getDataURL`; Círculos e Icicle serializan su SVG; el mapa mental genera un SVG seguro de su disposición visible. `exportCurriculumVisualizationPdf()` compone la captura completa y la inserta en un PDF A3 horizontal con:

-   título y total de OA;
-   filtros activos;
-   jerarquía seleccionada;
-   leyenda de las categorías principales y aviso si existen más;
-   fecha y hora;
-   visualización capturada;
-   nota: “El tamaño representa cantidad de objetivos, no importancia curricular”.

La exportación representa el estado visible y sus límites. No modifica datos ni sustituye el exportador tabular XLSX del catálogo.

El botón **Exportar PDF** se encuentra tanto en la barra superior como en la barra flotante del lienzo, de modo que permanece accesible mientras se navega por un gráfico alto o ampliado. Al finalizar, la interfaz anuncia el resultado mediante una región `aria-live`.

## Paleta y tokens visuales

La paleta estable se define en `resources/js/utils/curriculum-visualization.js`. `stableNodeColor()` aplica un hash determinista; los nodos de una misma asignatura conservan color entre vistas y renders, mientras los niveles interiores mezclan variaciones claras.

Para modificar colores:

1. Cambiar únicamente los valores hexadecimales de `PALETTE`.
2. Mantener contraste suficiente con `#17263d` y fondo blanco.
3. No cambiar el orden sin aceptar que la asignación histórica de colores variará.
4. Ejecutar las pruebas de estabilidad y verificar Treemap, Sunburst, Círculos e Icicle en tema claro.

Los componentes reutilizan los tokens `--lcd-ink`, `--lcd-muted`, `--lcd-border` y `--lcd-primary` con fallbacks; no introducen Tailwind.

## Ciclo de vida y redimensionamiento

Los componentes ECharts usan `autoresize` con throttle y exponen `center()` para forzar `resize()` al entrar en pantalla completa o cambiar de contenedor. Las vistas SVG crean un `ResizeObserver` sobre su contenedor y usan `window.resize` como fallback. Ambos se desconectan en `onBeforeUnmount`; el composable también aborta solicitudes y limpia sus temporizadores.

Si un gráfico aparece con tamaño cero o recortado:

1. Confirmar que el contenedor visible tenga ancho y alto (`min-height` está definido por la vista).
2. Comprobar que la vista no se montó dentro de un ancestro aún oculto.
3. Llamar `center()` después de mostrar un modal, panel o pantalla completa.
4. Revisar que `ResizeObserver` no haya sido reemplazado por un polyfill incompatible.
5. Verificar en consola que no existan advertencias de ECharts sobre ancho/alto.

## Cómo agregar una nueva vista

1. Crear un componente dentro de `resources/js/components/libro-digital/curriculum-visualizations/` con las props normalizadas (`root`, `graph`, `labelMode`, `reducedMotion`, según corresponda).
2. Emitir `select-node`, `drilldown` y `open-objective` con nodos del contrato común.
3. Exponer `center()`, `reset()` y `exportImage()`.
4. Añadir la clave a `CURRICULUM_VIEW_KEYS` y al allowlist `VIEWS` de Laravel.
5. Registrar el componente mediante `defineAsyncComponent` en `visualizationRegistry.js`.
6. Si requiere otro límite, aplicarlo en el request/servicio y comunicarlo en `meta`; nunca cortar silenciosamente en el componente.
7. Si agrega una dimensión, definir explícitamente su descriptor y SQL en el backend y sumarla al allowlist. Nunca interpolar nombres de columnas enviados por el navegador.
8. Añadir pruebas de normalización, payload, interacción, accesibilidad, límite y build lazy.
9. Actualizar este documento.

No es necesario crear otro endpoint: todas las vistas deben consumir el contrato normalizado común.

## Pruebas y verificación

Frontend:

```bash
npm run test:unit
npm run prod
```

Backend focalizado:

```bash
php artisan test tests/Feature/LibroDigital/CurriculumVisualizationApiTest.php
php artisan test tests/Feature/LibroDigital/CurriculumExplorerApiTest.php
```

Formato de archivos frontend y documentación:

```bash
npx prettier --check vitest.config.mjs "tests/frontend/**/*.js" docs/curriculum-visualizations.md
```

La verificación manual mínima es:

1. Abrir el catálogo y filtrar una asignatura conocida, por ejemplo Filosofía.
2. Anotar el total de la tabla y recorrer las ocho vistas gráficas; el total filtrado debe ser idéntico.
3. Profundizar en una categoría, subir con breadcrumbs y abrir un OA; debe reutilizarse el detalle existente.
4. Volver a Tabla; los filtros deben conservarse.
5. Recargar una URL con `view`, `hierarchy`, `scope` y `root_node` válidos; debe restaurarse el estado.
6. Cambiar rápidamente la búsqueda; las solicitudes anteriores deben aparecer canceladas, no como errores de UI.
7. Probar anchos de 1440 px, 1024 px y 390 px, pantalla completa, teclado, movimiento reducido y exportación PDF.
8. Confirmar que una respuesta agregada o truncada muestre el aviso de profundización.

## Limitaciones de los datos actuales

-   No existe una relación persistida OA↔OA de progresión, interdisciplinariedad o afinidad. Por ello la Red curricular no inventa enlaces: muestra solamente la jerarquía registrada y marcada como oficial.
-   No existe una entidad persistida e inequívoca de **Ámbito**. En Educación Parvularia las asignaturas del horario representan núcleos y el eje puede repetir ese núcleo; el servicio evita esa duplicación. Hasta contar con una fuente oficial estructurada, la clasificación desconocida se presenta como “Agrupador curricular”, no como un ámbito inferido.
-   La fuente canónica se elige de las relaciones registradas con rol `canonical_text`; los conflictos se informan en metadatos y no multiplican OA.
-   La cantidad de OA indica volumen del catálogo. No expresa importancia, horas, prioridad, cobertura, aprendizaje ni desempeño.
