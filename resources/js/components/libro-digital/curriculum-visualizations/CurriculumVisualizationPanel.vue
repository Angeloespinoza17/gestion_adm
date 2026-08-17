<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { useCurriculumVisualization } from "../../../composables/useCurriculumVisualization";
import {
    CURRICULUM_HIERARCHY_PRESETS,
    accessibleVisualizationSummary,
    formatObjectiveCount,
    isObjectiveNode,
    nodeTypeLabel,
    stableNodeColor,
} from "../../../utils/curriculum-visualization";
import {
    exportCurriculumVisualizationPdf,
    safeFilenamePart,
} from "../../../utils/curriculum-visualization-export";
import CurriculumVisualizationBreadcrumbs from "./CurriculumVisualizationBreadcrumbs.vue";
import CurriculumVisualizationHelp from "./CurriculumVisualizationHelp.vue";
import CurriculumVisualizationToolbar from "./CurriculumVisualizationToolbar.vue";
import { visualizationRegistry } from "./visualizationRegistry";

const props = defineProps({
    requestParams: { type: Object, required: true },
    filtros: { type: Object, required: true },
    context: { type: Object, required: true },
    total: { type: Number, default: 0 },
});

const emit = defineEmits([
    "open-objective",
    "apply-node-filter",
    "view-node-in-table",
    "clear-filters",
]);

const requestParamsRef = computed(() => props.requestParams);
const totalRef = computed(() => props.total);
const {
    activeView,
    hierarchyPreset,
    scope,
    labelMode,
    selectedNode,
    data,
    loading,
    refreshing,
    error,
    breadcrumbs,
    countMismatch,
    setView,
    setHierarchy,
    setScope,
    drillDown,
    goToBreadcrumb,
    resetView,
    refresh,
} = useCurriculumVisualization({
    requestParams: requestParamsRef,
    expectedTotal: totalRef,
});

const visualization = ref(null);
const visualizationViewport = ref(null);
const workspace = ref(null);
const workspaceMain = ref(null);
const fullscreenExit = ref(null);
const nodePanel = ref(null);
const nodePanelClose = ref(null);
const fullscreen = ref(false);
const mobileNodePanel = ref(false);
const helpVisible = ref(false);
const exporting = ref(false);
const exportError = ref("");
const exportSuccess = ref("");
const reducedMotion = ref(false);
const visualScale = ref(1);
const panMode = ref(false);
const visualScalePercent = computed(() => Math.round(visualScale.value * 100));
const visualStageStyle = computed(() => ({
    transform: `scale(${visualScale.value})`,
    marginLeft:
        visualScale.value < 1
            ? `${Math.round((1 - visualScale.value) * 50)}%`
            : "0",
}));
let motionQuery = null;
let mobileQuery = null;
let fullscreenReturnFocus = null;
let previousBodyOverflow = "";
let nodePanelReturnFocus = null;
let nodePanelPreviousBodyOverflow = "";
let panPointerId = null;
let panStartX = 0;
let panStartY = 0;
let panStartScrollLeft = 0;
let panStartScrollTop = 0;
let workspaceResizeObserver = null;
let visualizationReadyFrame = null;
const preloadRecoveryKey = "lcd:curriculum-visualization-preload-recovery";

const activeDefinition = computed(
    () => visualizationRegistry[activeView.value] || visualizationRegistry.table
);
const activeComponent = computed(() => activeDefinition.value.component);
const graphTotal = computed(
    () =>
        data.value?.meta?.totalObjectives ??
        data.value?.root?.objectiveCount ??
        0
);
const hasVisualizationData = computed(() =>
    Boolean(data.value?.root?.id && graphTotal.value > 0)
);
const hierarchyLabel = computed(
    () =>
        CURRICULUM_HIERARCHY_PRESETS.find(
            (preset) => preset.value === hierarchyPreset.value
        )?.label || hierarchyPreset.value
);
const textualSummary = computed(() =>
    accessibleVisualizationSummary(data.value)
);
const filterLabels = Object.freeze({
    level_code: "Nivel",
    grade_code: "Grado",
    curriculum_track: "Formación",
    subject_code: "Asignatura",
    objective_type: "Tipo",
    axis_code: "Agrupador curricular",
    source: "Fuente",
    status: "Estado",
    query: "Búsqueda",
});
const activeFilters = computed(() =>
    Object.entries(props.filtros || {})
        .filter(
            ([key, value]) =>
                filterLabels[key] &&
                value !== null &&
                value !== undefined &&
                value !== "" &&
                !(key === "status" && value === "all")
        )
        .map(([key, value]) => `${filterLabels[key]}: ${value}`)
);
const exportFilters = computed(() =>
    scope.value === "catalog"
        ? ["Alcance: catálogo completo del contexto seleccionado"]
        : activeFilters.value
);
const legendItems = computed(() =>
    [...(data.value?.root?.children || [])]
        .sort(
            (left, right) =>
                Number(right.objectiveCount || 0) -
                Number(left.objectiveCount || 0)
        )
        .map((node) => ({
            id: node.id,
            label: node.shortName || node.code || node.name,
            count: Number(node.objectiveCount || 0).toLocaleString("es-CL"),
            color: stableNodeColor(node),
        }))
);
const accessibleNodes = computed(() => {
    const hierarchyNodes = data.value?.root?.children || [];
    const nodes = hierarchyNodes.length
        ? hierarchyNodes
        : (data.value?.graph?.nodes || []).map((node) => ({
              ...node,
              objectiveCount: node.value,
          }));
    return [...nodes]
        .sort(
            (left, right) =>
                Number(right.objectiveCount || right.value || 0) -
                Number(left.objectiveCount || left.value || 0)
        )
        .slice(0, 100);
});
const accessibleListTruncated = computed(() => {
    const count = data.value?.root?.children?.length
        ? data.value.root.children.length
        : data.value?.graph?.nodes?.length || 0;
    return count > accessibleNodes.value.length;
});
const nodeFilters = computed(
    () => selectedNode.value?.metadata?.filters || null
);
const currentTitle = computed(
    () => `${activeDefinition.value.label} · mapa curricular`
);

const handleNode = (node) => {
    selectedNode.value = node || null;
};
const exploreNode = (node) => {
    if (!node || isObjectiveNode(node)) return;
    drillDown(node);
};
const activateAccessibleNode = (node) => {
    handleNode(node);
    if (isObjectiveNode(node)) emit("open-objective", node);
    else exploreNode(node);
};
const applySelectedFilter = () => {
    if (!nodeFilters.value) return;
    emit("apply-node-filter", {
        filters: nodeFilters.value,
        node: selectedNode.value,
    });
};
const showSelectedInTable = () => {
    const filters = nodeFilters.value || {};
    const node = selectedNode.value;
    setView("table");
    emit("view-node-in-table", { filters, node });
};
const centerView = async () => {
    await nextTick();
    visualization.value?.center?.();
};
const handleVisualizationReady = async () => {
    await nextTick();
    if (visualizationReadyFrame) cancelAnimationFrame(visualizationReadyFrame);
    visualizationReadyFrame = requestAnimationFrame(() => {
        visualization.value?.center?.();
        visualizationReadyFrame = null;
    });
};
const observeWorkspaceSize = async () => {
    workspaceResizeObserver?.disconnect?.();
    workspaceResizeObserver = null;
    await nextTick();
    if (!workspaceMain.value || typeof ResizeObserver === "undefined") return;
    workspaceResizeObserver = new ResizeObserver(() => {
        void handleVisualizationReady();
    });
    workspaceResizeObserver.observe(workspaceMain.value);
};
const setVisualScale = async (nextScale) => {
    const viewport = visualizationViewport.value;
    const previousScale = visualScale.value;
    const normalized = Math.min(2, Math.max(0.55, Number(nextScale) || 1));
    if (Math.abs(normalized - previousScale) < 0.001) return;

    const centerX = viewport
        ? (viewport.scrollLeft + viewport.clientWidth / 2) / previousScale
        : 0;
    const centerY = viewport
        ? (viewport.scrollTop + viewport.clientHeight / 2) / previousScale
        : 0;
    visualScale.value = normalized;
    await nextTick();
    if (viewport) {
        viewport.scrollLeft = Math.max(
            0,
            centerX * normalized - viewport.clientWidth / 2
        );
        viewport.scrollTop = Math.max(
            0,
            centerY * normalized - viewport.clientHeight / 2
        );
    }
};
const zoomIn = () => setVisualScale(visualScale.value + 0.15);
const zoomOut = () => setVisualScale(visualScale.value - 0.15);
const fitVisualization = async () => {
    panMode.value = false;
    visualScale.value = 1;
    await nextTick();
    const viewport = visualizationViewport.value;
    if (viewport) {
        viewport.scrollLeft = 0;
        viewport.scrollTop = 0;
    }
    visualization.value?.center?.();
};
const resetWorkspace = () => {
    visualScale.value = 1;
    panMode.value = false;
    visualization.value?.reset?.();
    resetView();
};
const togglePanMode = () => {
    panMode.value = !panMode.value;
};
const handleViewportPointerDown = (event) => {
    if (!panMode.value || event.button !== 0) return;
    if (event.target?.closest?.("button, input, select, a, [role='button']"))
        return;
    const viewport = visualizationViewport.value;
    if (!viewport) return;
    panPointerId = event.pointerId;
    panStartX = event.clientX;
    panStartY = event.clientY;
    panStartScrollLeft = viewport.scrollLeft;
    panStartScrollTop = viewport.scrollTop;
    viewport.setPointerCapture?.(event.pointerId);
};
const handleViewportPointerMove = (event) => {
    if (panPointerId !== event.pointerId) return;
    const viewport = visualizationViewport.value;
    if (!viewport) return;
    viewport.scrollLeft = panStartScrollLeft - (event.clientX - panStartX);
    viewport.scrollTop = panStartScrollTop - (event.clientY - panStartY);
};
const finishViewportPan = (event) => {
    if (panPointerId !== event.pointerId) return;
    visualizationViewport.value?.releasePointerCapture?.(event.pointerId);
    panPointerId = null;
};
const handleViewportWheel = (event) => {
    if (!event.ctrlKey && !event.metaKey) return;
    event.preventDefault();
    void setVisualScale(visualScale.value + (event.deltaY < 0 ? 0.1 : -0.1));
};
const handlePreloadError = (event) => {
    event.preventDefault?.();
    const now = Date.now();
    let lastRecovery = 0;
    try {
        lastRecovery = Number(
            window.sessionStorage?.getItem(preloadRecoveryKey) || 0
        );
    } catch {
        lastRecovery = 0;
    }
    if (now - lastRecovery > 15000) {
        try {
            window.sessionStorage?.setItem(preloadRecoveryKey, String(now));
        } catch {
            // La recarga sigue siendo segura aunque el almacenamiento esté bloqueado.
        }
        window.location.reload();
        return;
    }
    exportError.value =
        "No se pudo cargar el gráfico actualizado. Recarga la página para sincronizar los recursos.";
};
const focusableWithin = (container) => [
    ...(container?.querySelectorAll(
        "button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex='-1'])"
    ) || []),
];
const trapFocus = (event, container) => {
    if (event.key !== "Tab") return;
    const focusable = focusableWithin(container);
    if (!focusable.length) {
        event.preventDefault();
        container?.focus?.();
        return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};
const handleWorkspaceKeydown = (event) => {
    if (fullscreen.value) trapFocus(event, workspace.value);
};
const closeNodePanel = () => {
    selectedNode.value = null;
};
const handleNodePanelKeydown = (event) => {
    if (!mobileNodePanel.value) return;
    if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        closeNodePanel();
        return;
    }
    if (event.key === "Tab") {
        event.stopPropagation();
        trapFocus(event, nodePanel.value);
    }
};
const setFullscreen = async (next) => {
    if (next === fullscreen.value) return;
    if (next) {
        fullscreenReturnFocus = document.activeElement;
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
    } else {
        if (mobileNodePanel.value && selectedNode.value) {
            nodePanelPreviousBodyOverflow = previousBodyOverflow;
            document.body.style.overflow = "hidden";
        } else {
            document.body.style.overflow = previousBodyOverflow;
        }
    }
    fullscreen.value = next;
    await centerView();
    if (next) {
        fullscreenExit.value?.focus?.();
    } else if (mobileNodePanel.value && selectedNode.value) {
        nodePanelClose.value?.focus?.();
    } else {
        fullscreenReturnFocus?.focus?.();
    }
};
const toggleFullscreen = () => setFullscreen(!fullscreen.value);
const closeFullscreenOnEscape = (event) => {
    if (event.key !== "Escape" || !fullscreen.value) return;
    void setFullscreen(false);
};
const exportPdf = async () => {
    if (!visualization.value?.exportImage || exporting.value) return;
    exporting.value = true;
    exportError.value = "";
    exportSuccess.value = "";
    try {
        const subject =
            scope.value === "catalog"
                ? "catalogo-completo"
                : props.filtros?.subject_code || "catalogo";
        const grade =
            scope.value === "catalog"
                ? "contexto"
                : props.filtros?.grade_code || "todos-los-grados";
        const year =
            props.context?.academic_year_id || new Date().getFullYear();
        await exportCurriculumVisualizationPdf({
            capture: () => visualization.value?.exportImage?.(),
            title: currentTitle.value,
            total: graphTotal.value,
            filters: exportFilters.value,
            hierarchy: hierarchyLabel.value,
            legend: legendItems.value,
            filename: `mapa-curricular-${safeFilenamePart(
                subject
            )}-${safeFilenamePart(grade)}-${safeFilenamePart(year)}.pdf`,
        });
        exportSuccess.value =
            "La visualización se exportó correctamente en formato PDF.";
    } catch (captureError) {
        exportError.value =
            captureError?.message || "No fue posible exportar esta vista.";
    } finally {
        exporting.value = false;
    }
};
const handleMotionChange = (event) => {
    reducedMotion.value = event.matches;
};
const handleMobileChange = async (event) => {
    const wasMobile = mobileNodePanel.value;
    mobileNodePanel.value = event.matches;
    if (!wasMobile && event.matches && selectedNode.value) {
        nodePanelReturnFocus = document.activeElement;
        nodePanelPreviousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        await nextTick();
        nodePanelClose.value?.focus?.();
    } else if (wasMobile && !event.matches && selectedNode.value) {
        document.body.style.overflow = fullscreen.value
            ? "hidden"
            : nodePanelPreviousBodyOverflow;
        nodePanelReturnFocus?.focus?.();
        nodePanelReturnFocus = null;
    }
};

onMounted(() => {
    document.addEventListener("keydown", closeFullscreenOnEscape);
    window.addEventListener("vite:preloadError", handlePreloadError);
    motionQuery =
        window.matchMedia?.("(prefers-reduced-motion: reduce)") || null;
    reducedMotion.value = Boolean(motionQuery?.matches);
    motionQuery?.addEventListener?.("change", handleMotionChange);
    mobileQuery = window.matchMedia?.("(max-width: 575px)") || null;
    mobileNodePanel.value = Boolean(mobileQuery?.matches);
    mobileQuery?.addEventListener?.("change", handleMobileChange);
    void observeWorkspaceSize();
    void handleVisualizationReady();
});
onBeforeUnmount(() => {
    document.removeEventListener("keydown", closeFullscreenOnEscape);
    window.removeEventListener("vite:preloadError", handlePreloadError);
    motionQuery?.removeEventListener?.("change", handleMotionChange);
    mobileQuery?.removeEventListener?.("change", handleMobileChange);
    workspaceResizeObserver?.disconnect?.();
    if (visualizationReadyFrame) cancelAnimationFrame(visualizationReadyFrame);
    if (fullscreen.value) {
        document.body.style.overflow = previousBodyOverflow;
    } else if (mobileNodePanel.value && selectedNode.value) {
        document.body.style.overflow = nodePanelPreviousBodyOverflow;
    }
});

watch(selectedNode, async (next, previous) => {
    if (next && !previous && mobileNodePanel.value) {
        nodePanelReturnFocus = document.activeElement;
        nodePanelPreviousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        await nextTick();
        nodePanelClose.value?.focus?.();
    } else if (!next && previous && nodePanelReturnFocus) {
        document.body.style.overflow = fullscreen.value
            ? "hidden"
            : nodePanelPreviousBodyOverflow;
        await nextTick();
        nodePanelReturnFocus.focus?.();
        nodePanelReturnFocus = null;
    }
});

watch(activeView, () => {
    exportError.value = "";
    exportSuccess.value = "";
    selectedNode.value = null;
    visualScale.value = 1;
    panMode.value = false;
    void observeWorkspaceSize();
    void handleVisualizationReady();
});

watch(
    () => data.value?.meta?.rootNode,
    () => {
        visualScale.value = 1;
        panMode.value = false;
        void handleVisualizationReady();
    }
);
</script>

<template>
    <div class="cv-panel">
        <CurriculumVisualizationToolbar
            :active-view="activeView"
            :hierarchy="hierarchyPreset"
            :scope="scope"
            :label-mode="labelMode"
            :loading="loading || refreshing"
            :exporting="exporting"
            :fullscreen="fullscreen"
            @update:view="setView"
            @update:hierarchy="setHierarchy"
            @update:scope="setScope"
            @update:label-mode="labelMode = $event"
            @reset="resetWorkspace"
            @center="fitVisualization"
            @fullscreen="toggleFullscreen"
            @export="exportPdf"
            @help="helpVisible = true"
        />

        <slot v-if="activeView === 'table'" name="table"></slot>

        <section
            v-else
            ref="workspace"
            class="cv-workspace"
            :class="{ 'cv-workspace--fullscreen': fullscreen }"
            :role="fullscreen ? 'dialog' : 'region'"
            :aria-modal="fullscreen ? 'true' : undefined"
            aria-labelledby="cv-workspace-title"
            tabindex="-1"
            @keydown="handleWorkspaceKeydown"
        >
            <header class="cv-workspace__header">
                <div class="cv-workspace__title">
                    <span class="cv-workspace__icon" aria-hidden="true">
                        <i :class="['bx', activeDefinition.icon]"></i>
                    </span>
                    <div>
                        <span>{{ activeDefinition.label }}</span>
                        <h3 id="cv-workspace-title">
                            {{ formatObjectiveCount(graphTotal) }}
                        </h3>
                    </div>
                </div>
                <CurriculumVisualizationBreadcrumbs
                    :items="breadcrumbs"
                    :busy="loading"
                    @select="goToBreadcrumb"
                />
                <span
                    v-if="refreshing"
                    class="cv-workspace__refresh"
                    role="status"
                >
                    <span class="spinner-border" aria-hidden="true"></span>
                    Actualizando
                </span>
                <button
                    v-if="fullscreen"
                    ref="fullscreenExit"
                    type="button"
                    class="cv-workspace__exit-fullscreen"
                    @click="setFullscreen(false)"
                >
                    <i class="bx bx-exit-fullscreen" aria-hidden="true"></i>
                    Salir de pantalla completa
                </button>
            </header>

            <div
                v-if="data?.meta?.aggregated || !data?.meta?.leavesIncluded"
                class="cv-notice cv-notice--info"
                role="status"
            >
                <i class="bx bx-layer" aria-hidden="true"></i>
                <span>
                    La visualización se encuentra agregada para mantener una
                    navegación fluida. Selecciona una categoría para ver mayor
                    detalle.
                </span>
            </div>
            <div
                v-if="data?.meta?.graphTruncated || data?.graph?.truncated"
                class="cv-notice cv-notice--warning"
                role="status"
            >
                <i class="bx bx-filter-alt" aria-hidden="true"></i>
                <span>
                    Esta vista alcanzó su límite de nodos. No se ocultaron
                    resultados: se muestran categorías agregadas para continuar
                    explorando.
                </span>
            </div>
            <div
                v-if="countMismatch"
                class="cv-notice cv-notice--warning"
                role="alert"
            >
                <i class="bx bx-error-circle" aria-hidden="true"></i>
                <span>
                    El conteo gráfico no coincide con la última lectura de la
                    tabla. Actualiza la vista antes de interpretar los
                    resultados.
                </span>
                <button type="button" @click="refresh">Actualizar</button>
            </div>
            <div
                v-if="exportError"
                class="cv-notice cv-notice--warning"
                role="alert"
            >
                <i class="bx bx-error-circle" aria-hidden="true"></i>
                <span>{{ exportError }}</span>
            </div>
            <div
                v-if="exportSuccess"
                class="cv-notice cv-notice--success"
                role="status"
                aria-live="polite"
            >
                <i class="bx bx-check-circle" aria-hidden="true"></i>
                <span>{{ exportSuccess }}</span>
            </div>

            <div
                v-if="legendItems.length"
                class="cv-legend"
                aria-label="Leyenda de categorías"
            >
                <strong>Leyenda</strong>
                <ul>
                    <li v-for="item in legendItems.slice(0, 10)" :key="item.id">
                        <span
                            :style="{ backgroundColor: item.color }"
                            aria-hidden="true"
                        ></span>
                        <span :title="item.label">{{ item.label }}</span>
                        <small>{{ item.count }} OA</small>
                    </li>
                </ul>
                <span v-if="legendItems.length > 10" class="cv-legend__more">
                    +{{ legendItems.length - 10 }} categorías
                </span>
            </div>

            <div class="cv-workspace__body">
                <div ref="workspaceMain" class="cv-workspace__main">
                    <div
                        v-if="loading && !data"
                        class="cv-state cv-state--loading"
                        role="status"
                        aria-live="polite"
                    >
                        <div class="cv-skeleton" aria-hidden="true">
                            <span v-for="index in 12" :key="index"></span>
                        </div>
                        <strong>Construyendo mapa curricular</strong>
                        <p>
                            Estamos agrupando los objetivos del alcance
                            seleccionado.
                        </p>
                    </div>

                    <div
                        v-else-if="error && !data"
                        class="cv-state"
                        role="alert"
                    >
                        <span class="cv-state__icon cv-state__icon--error">
                            <i
                                class="bx bx-error-circle"
                                aria-hidden="true"
                            ></i>
                        </span>
                        <strong>No fue posible cargar la visualización.</strong>
                        <p>{{ error?.message || "Inténtalo nuevamente." }}</p>
                        <div class="cv-state__actions">
                            <button type="button" @click="refresh">
                                Reintentar
                            </button>
                            <button
                                type="button"
                                class="is-secondary"
                                @click="setView('table')"
                            >
                                Ver resultados en tabla
                            </button>
                        </div>
                    </div>

                    <div
                        v-else-if="!hasVisualizationData"
                        class="cv-state"
                        role="status"
                    >
                        <span class="cv-state__icon">
                            <i class="bx bx-search-alt" aria-hidden="true"></i>
                        </span>
                        <strong>
                            No existen Objetivos de Aprendizaje que coincidan
                            con los filtros actuales.
                        </strong>
                        <p>
                            Amplía el alcance o vuelve a la tabla para revisar
                            la búsqueda.
                        </p>
                        <div class="cv-state__actions">
                            <button
                                type="button"
                                @click="emit('clear-filters')"
                            >
                                Limpiar filtros
                            </button>
                            <button
                                type="button"
                                class="is-secondary"
                                @click="setView('table')"
                            >
                                Volver a la tabla
                            </button>
                        </div>
                    </div>

                    <div
                        v-else
                        ref="visualizationViewport"
                        class="cv-visualization-viewport"
                        :class="{
                            'cv-visualization-viewport--panning': panMode,
                        }"
                        aria-label="Área navegable de la visualización"
                        @pointerdown="handleViewportPointerDown"
                        @pointermove="handleViewportPointerMove"
                        @pointerup="finishViewportPan"
                        @pointercancel="finishViewportPan"
                        @wheel="handleViewportWheel"
                    >
                        <div
                            class="cv-viewport-tools"
                            aria-label="Controles de navegación y exportación"
                        >
                            <button
                                type="button"
                                :disabled="visualScale <= 0.55"
                                aria-label="Alejar visualización"
                                title="Alejar"
                                @click="zoomOut"
                            >
                                <i
                                    class="bx bx-zoom-out"
                                    aria-hidden="true"
                                ></i>
                            </button>
                            <output
                                :aria-label="`Escala ${visualScalePercent}%`"
                            >
                                {{ visualScalePercent }}%
                            </output>
                            <button
                                type="button"
                                :disabled="visualScale >= 2"
                                aria-label="Acercar visualización"
                                title="Acercar"
                                @click="zoomIn"
                            >
                                <i class="bx bx-zoom-in" aria-hidden="true"></i>
                            </button>
                            <button
                                type="button"
                                aria-label="Ajustar visualización al contenedor"
                                title="Ajustar y centrar"
                                @click="fitVisualization"
                            >
                                <i
                                    class="bx bx-crosshair"
                                    aria-hidden="true"
                                ></i>
                                <span>Ajustar</span>
                            </button>
                            <button
                                type="button"
                                :aria-pressed="panMode"
                                title="Mover el lienzo arrastrando"
                                @click="togglePanMode"
                            >
                                <i class="bx bx-move" aria-hidden="true"></i>
                                <span>Mover</span>
                            </button>
                            <button
                                type="button"
                                class="cv-viewport-tools__export"
                                :disabled="exporting"
                                title="Exportar visualización en PDF con su contexto"
                                @click="exportPdf"
                            >
                                <i
                                    class="bx bx-download"
                                    aria-hidden="true"
                                ></i>
                                <span>{{
                                    exporting ? "Exportando" : "Exportar PDF"
                                }}</span>
                            </button>
                        </div>

                        <div
                            class="cv-visualization-stage"
                            :style="visualStageStyle"
                        >
                            <Suspense>
                                <component
                                    :is="activeComponent"
                                    :key="`${activeView}:${hierarchyPreset}:${
                                        data?.meta?.rootNode || 'root'
                                    }`"
                                    ref="visualization"
                                    :root="data.root"
                                    :graph="data.graph"
                                    :label-mode="labelMode"
                                    :reduced-motion="reducedMotion"
                                    :selected-node="selectedNode"
                                    @vue:mounted="handleVisualizationReady"
                                    @select-node="handleNode"
                                    @drilldown="exploreNode"
                                    @open-objective="
                                        emit('open-objective', $event)
                                    "
                                />
                                <template #fallback>
                                    <div
                                        class="cv-state cv-state--loading"
                                        role="status"
                                    >
                                        <span
                                            class="spinner-border"
                                            aria-hidden="true"
                                        ></span>
                                        <strong
                                            >Preparando visualización</strong
                                        >
                                    </div>
                                </template>
                            </Suspense>
                        </div>
                    </div>
                </div>

                <aside
                    v-if="selectedNode"
                    ref="nodePanel"
                    class="cv-node-panel"
                    :role="mobileNodePanel ? 'dialog' : 'complementary'"
                    :aria-modal="mobileNodePanel ? 'true' : undefined"
                    aria-labelledby="cv-node-panel-title"
                    tabindex="-1"
                    @keydown="handleNodePanelKeydown"
                >
                    <header>
                        <span>{{ nodeTypeLabel(selectedNode) }}</span>
                        <button
                            ref="nodePanelClose"
                            type="button"
                            aria-label="Cerrar información del nodo"
                            @click="closeNodePanel"
                        >
                            <i class="bx bx-x" aria-hidden="true"></i>
                        </button>
                    </header>
                    <h4 id="cv-node-panel-title">
                        <span v-if="selectedNode.code"
                            >{{ selectedNode.code }} ·
                        </span>
                        {{ selectedNode.name }}
                    </h4>
                    <p v-if="selectedNode.description">
                        {{ selectedNode.description }}
                    </p>
                    <dl>
                        <div>
                            <dt>Objetivos</dt>
                            <dd>
                                {{
                                    Number(
                                        selectedNode.objectiveCount ||
                                            selectedNode.value ||
                                            0
                                    ).toLocaleString("es-CL")
                                }}
                            </dd>
                        </div>
                        <div v-if="!isObjectiveNode(selectedNode)">
                            <dt>Disponibles</dt>
                            <dd>
                                {{
                                    Number(
                                        selectedNode.availableCount || 0
                                    ).toLocaleString("es-CL")
                                }}
                            </dd>
                        </div>
                        <div v-if="!isObjectiveNode(selectedNode)">
                            <dt>No disponibles</dt>
                            <dd>
                                {{
                                    Number(
                                        selectedNode.unavailableCount || 0
                                    ).toLocaleString("es-CL")
                                }}
                            </dd>
                        </div>
                        <div
                            v-if="
                                selectedNode.metadata?.child_count !== undefined
                            "
                        >
                            <dt>Subcategorías</dt>
                            <dd>
                                {{
                                    Number(
                                        selectedNode.metadata.child_count
                                    ).toLocaleString("es-CL")
                                }}
                            </dd>
                        </div>
                    </dl>
                    <div class="cv-node-panel__actions">
                        <button
                            v-if="isObjectiveNode(selectedNode)"
                            type="button"
                            @click="emit('open-objective', selectedNode)"
                        >
                            Abrir ficha oficial
                        </button>
                        <button
                            v-else
                            type="button"
                            @click="exploreNode(selectedNode)"
                        >
                            Explorar esta categoría
                        </button>
                        <button
                            v-if="!isObjectiveNode(selectedNode)"
                            type="button"
                            class="is-secondary"
                            :disabled="!nodeFilters"
                            @click="applySelectedFilter"
                        >
                            Aplicar como filtro
                        </button>
                        <button
                            type="button"
                            class="is-secondary"
                            @click="showSelectedInTable"
                        >
                            Ver objetivos en tabla
                        </button>
                    </div>
                </aside>
            </div>

            <details v-if="data" class="cv-accessible-summary">
                <summary>Resumen y navegación accesible</summary>
                <p>{{ textualSummary }}</p>
                <ul v-if="accessibleNodes.length">
                    <li v-for="node in accessibleNodes" :key="node.id">
                        <button
                            type="button"
                            @click="activateAccessibleNode(node)"
                        >
                            <span>
                                <strong>{{ node.code || node.name }}</strong>
                                <small>{{ nodeTypeLabel(node) }}</small>
                            </span>
                            <span>{{
                                formatObjectiveCount(
                                    node.objectiveCount || node.value
                                )
                            }}</span>
                        </button>
                    </li>
                </ul>
                <p
                    v-if="accessibleListTruncated"
                    class="cv-accessible-summary__note"
                >
                    Se muestran las primeras 100 categorías ordenadas por
                    cantidad. La tabla permanece disponible como alternativa
                    completa.
                </p>
            </details>

            <footer class="cv-workspace__footer">
                <span>
                    <i class="bx bx-info-circle" aria-hidden="true"></i>
                    El tamaño representa cantidad de objetivos, no importancia
                    curricular.
                </span>
                <button type="button" @click="setView('table')">
                    <i class="bx bx-table" aria-hidden="true"></i>
                    Usar tabla accesible
                </button>
            </footer>
        </section>

        <CurriculumVisualizationHelp
            :model-value="helpVisible"
            @update:model-value="helpVisible = $event"
        />
    </div>
</template>

<style scoped>
.cv-panel {
    display: grid;
    gap: 18px;
}

.cv-workspace {
    position: relative;
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: var(--lcd-radius-lg, 16px);
    background: #fff;
    box-shadow: var(--lcd-shadow-sm, 0 2px 8px rgba(20, 39, 65, 0.055));
}

.cv-workspace--fullscreen {
    position: fixed;
    z-index: 1085;
    border-radius: 0;
    box-shadow: none;
    overflow: auto;
    inset: 0;
}

.cv-workspace--fullscreen .cv-workspace__header {
    position: sticky;
    z-index: 8;
    top: 0;
}

.cv-workspace__header {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 18px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--lcd-border, #dbe3eb);
    background: var(--lcd-surface-muted, #f7f9fb);
}

.cv-workspace__title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cv-workspace__icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex: 0 0 auto;
    border-radius: 11px;
    background: var(--lcd-brand-700, #245486);
    color: #fff;
    font-size: 1.2rem;
    place-items: center;
}

.cv-workspace__title > div > span {
    color: var(--lcd-brand-700, #245486);
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.cv-workspace__title h3 {
    margin: 1px 0 0;
    color: var(--lcd-ink, #17263d);
    font-size: 0.98rem;
    white-space: nowrap;
}

.cv-workspace__refresh {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--lcd-muted, #627187);
    font-size: 0.75rem;
}

.cv-workspace__refresh .spinner-border {
    width: 0.85rem;
    height: 0.85rem;
    border-width: 2px;
}

.cv-workspace__exit-fullscreen {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border: 1px solid var(--lcd-brand-700, #245486);
    border-radius: 8px;
    background: var(--lcd-brand-700, #245486);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 750;
}

.cv-notice {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 12px 14px 0;
    padding: 10px 12px;
    border: 1px solid transparent;
    border-radius: 9px;
    font-size: 0.78rem;
}

.cv-notice > i {
    flex: 0 0 auto;
    font-size: 1.05rem;
}

.cv-notice--info {
    border-color: #c4dbe9;
    background: #f0f8fc;
    color: #245a76;
}

.cv-notice--warning {
    border-color: #ead5b4;
    background: #fff9ee;
    color: #75501b;
}

.cv-notice--success {
    border-color: #b9dfcf;
    background: #f0faf6;
    color: #176149;
}

.cv-notice button {
    margin-left: auto;
    border: 0;
    background: transparent;
    color: inherit;
    font-weight: 750;
    text-decoration: underline;
}

.cv-legend {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 11px 14px 0;
    padding: 9px 11px;
    overflow-x: auto;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 9px;
    background: #fbfcfd;
    scrollbar-width: thin;
}

.cv-legend > strong {
    color: var(--lcd-ink, #17263d);
    font-size: 0.7rem;
    text-transform: uppercase;
}

.cv-legend ul {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.cv-legend li {
    display: inline-flex;
    max-width: 230px;
    align-items: center;
    gap: 5px;
    color: var(--lcd-ink-soft, #33445b);
    font-size: 0.69rem;
    white-space: nowrap;
}

.cv-legend li > span:first-child {
    width: 11px;
    height: 11px;
    flex: 0 0 auto;
    border: 1px solid rgba(23, 38, 61, 0.24);
    border-radius: 3px;
}

.cv-legend li > span:nth-child(2) {
    overflow: hidden;
    text-overflow: ellipsis;
}

.cv-legend li small,
.cv-legend__more {
    color: var(--lcd-muted, #627187);
    font-size: 0.65rem;
}

.cv-legend__more {
    flex: 0 0 auto;
}

.cv-workspace__body {
    position: relative;
    display: flex;
    min-height: 560px;
}

.cv-workspace__main {
    position: relative;
    min-width: 0;
    flex: 1 1 auto;
}

.cv-visualization-viewport {
    position: relative;
    width: 100%;
    min-height: 560px;
    max-height: clamp(560px, 68vh, 760px);
    overflow: auto;
    overscroll-behavior: contain;
    background: linear-gradient(rgba(219, 227, 235, 0.28) 1px, transparent 1px),
        linear-gradient(90deg, rgba(219, 227, 235, 0.28) 1px, transparent 1px),
        #fff;
    background-size: 24px 24px;
    scrollbar-gutter: stable;
    touch-action: pan-x pan-y;
}

.cv-visualization-viewport--panning {
    cursor: grab;
    touch-action: none;
    user-select: none;
}

.cv-visualization-viewport--panning:active {
    cursor: grabbing;
}

.cv-visualization-stage {
    width: 100%;
    min-height: 560px;
    transform-origin: top left;
    transition: transform 180ms ease, margin-left 180ms ease;
}

.cv-viewport-tools {
    position: sticky;
    z-index: 7;
    top: 10px;
    display: flex;
    width: max-content;
    max-width: calc(100% - 20px);
    min-height: 42px;
    align-items: center;
    gap: 3px;
    margin: 10px 10px -52px auto;
    padding: 4px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 11px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 6px 20px rgba(23, 38, 61, 0.12);
    backdrop-filter: blur(8px);
}

.cv-viewport-tools button {
    display: inline-flex;
    min-width: 34px;
    min-height: 34px;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 5px 8px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: var(--lcd-brand-700, #245486);
    font-size: 0.7rem;
    font-weight: 750;
}

.cv-viewport-tools button:hover,
.cv-viewport-tools button[aria-pressed="true"] {
    background: var(--lcd-brand-50, #f1f7fb);
}

.cv-viewport-tools button[aria-pressed="true"] {
    box-shadow: inset 0 0 0 2px #ffb000, 0 0 0 1px #17263d;
    color: #17263d;
}

.cv-viewport-tools button:disabled {
    cursor: not-allowed;
    opacity: 0.42;
}

.cv-viewport-tools output {
    min-width: 42px;
    color: var(--lcd-ink-soft, #33445b);
    font-size: 0.68rem;
    font-weight: 800;
    text-align: center;
}

.cv-viewport-tools .cv-viewport-tools__export {
    padding-inline: 11px;
    background: var(--lcd-brand-700, #245486);
    color: #fff;
}

.cv-viewport-tools .cv-viewport-tools__export:hover {
    background: var(--lcd-brand-800, #183f69);
}

.cv-node-panel {
    width: min(330px, 34vw);
    flex: 0 0 auto;
    padding: 18px;
    overflow: auto;
    border-left: 4px solid #ffb000;
    background: linear-gradient(180deg, #fff9e9 0, #fbfcfd 96px);
}

.cv-node-panel header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.cv-node-panel header > span {
    color: var(--lcd-brand-700, #245486);
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.cv-node-panel header button {
    display: grid;
    width: 32px;
    height: 32px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 8px;
    background: #fff;
    color: var(--lcd-muted, #627187);
    font-size: 1.15rem;
    place-items: center;
}

.cv-node-panel h4 {
    margin: 15px 0 8px;
    color: var(--lcd-ink, #17263d);
    font-size: 1rem;
    line-height: 1.42;
}

.cv-node-panel > p {
    display: -webkit-box;
    overflow: hidden;
    color: var(--lcd-muted, #627187);
    font-size: 0.78rem;
    line-height: 1.55;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 7;
}

.cv-node-panel dl {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin: 16px 0;
}

.cv-node-panel dl > div {
    padding: 9px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 9px;
    background: #fff;
}

.cv-node-panel dt {
    color: var(--lcd-muted, #627187);
    font-size: 0.64rem;
    font-weight: 650;
}

.cv-node-panel dd {
    margin: 2px 0 0;
    color: var(--lcd-ink, #17263d);
    font-size: 0.9rem;
    font-weight: 800;
}

.cv-node-panel__actions {
    display: grid;
    gap: 7px;
}

.cv-node-panel__actions button,
.cv-state__actions button {
    min-height: 39px;
    padding: 8px 12px;
    border: 1px solid var(--lcd-brand-700, #245486);
    border-radius: 9px;
    background: var(--lcd-brand-700, #245486);
    color: #fff;
    font-size: 0.76rem;
    font-weight: 750;
}

.cv-node-panel__actions button.is-secondary,
.cv-state__actions button.is-secondary {
    background: #fff;
    color: var(--lcd-brand-700, #245486);
}

.cv-node-panel__actions button:disabled {
    cursor: not-allowed;
    opacity: 0.48;
}

.cv-state {
    display: flex;
    min-height: 560px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 34px;
    color: var(--lcd-muted, #627187);
    text-align: center;
}

.cv-state > strong {
    margin-top: 14px;
    color: var(--lcd-ink, #17263d);
    font-size: 1rem;
}

.cv-state p {
    max-width: 540px;
    margin: 7px auto 0;
    font-size: 0.82rem;
}

.cv-state__icon {
    display: grid;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: var(--lcd-brand-50, #f1f7fb);
    color: var(--lcd-brand-700, #245486);
    font-size: 1.65rem;
    place-items: center;
}

.cv-state__icon--error {
    background: #fff1f2;
    color: var(--lcd-danger, #b43e4d);
}

.cv-state__actions {
    display: flex;
    gap: 8px;
    margin-top: 17px;
}

.cv-skeleton {
    display: grid;
    width: min(760px, 92%);
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.cv-skeleton span {
    min-height: 58px;
    border-radius: 8px;
    background: linear-gradient(90deg, #edf2f6 20%, #f8fafb 50%, #edf2f6 80%);
    background-size: 220% 100%;
    animation: cv-shimmer 1.35s linear infinite;
}

.cv-skeleton span:nth-child(3n + 1) {
    min-height: 92px;
}

@keyframes cv-shimmer {
    to {
        background-position: -220% 0;
    }
}

.cv-accessible-summary {
    border-top: 1px solid var(--lcd-border, #dbe3eb);
    background: var(--lcd-surface-muted, #f7f9fb);
}

.cv-accessible-summary summary {
    padding: 13px 18px;
    color: var(--lcd-brand-700, #245486);
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 750;
}

.cv-accessible-summary > p {
    margin: 0;
    padding: 2px 18px 13px;
    color: var(--lcd-muted, #627187);
    font-size: 0.78rem;
    line-height: 1.55;
}

.cv-accessible-summary ul {
    display: grid;
    max-height: 330px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
    margin: 0;
    padding: 0 18px 16px;
    overflow: auto;
    list-style: none;
}

.cv-accessible-summary li button {
    display: flex;
    width: 100%;
    min-height: 50px;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 10px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 8px;
    background: #fff;
    color: var(--lcd-ink-soft, #33445b);
    text-align: left;
}

.cv-accessible-summary li button:hover {
    border-color: var(--lcd-brand-500, #3e7daf);
}

.cv-accessible-summary li button > span:first-child {
    min-width: 0;
}

.cv-accessible-summary li strong,
.cv-accessible-summary li small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cv-accessible-summary li small {
    margin-top: 2px;
    color: var(--lcd-muted, #627187);
    font-size: 0.67rem;
}

.cv-accessible-summary li button > span:last-child {
    flex: 0 0 auto;
    color: var(--lcd-brand-700, #245486);
    font-size: 0.72rem;
    font-weight: 750;
}

.cv-accessible-summary__note {
    color: var(--lcd-warning, #a56819) !important;
}

.cv-workspace__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 12px 18px;
    border-top: 1px solid var(--lcd-border, #dbe3eb);
    color: var(--lcd-muted, #627187);
    font-size: 0.74rem;
}

.cv-workspace__footer > span,
.cv-workspace__footer button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.cv-workspace__footer button {
    min-height: 35px;
    padding: 6px 10px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 8px;
    background: #fff;
    color: var(--lcd-brand-700, #245486);
    font-size: 0.73rem;
    font-weight: 750;
}

.cv-panel button:focus-visible,
.cv-panel summary:focus-visible {
    outline: 3px solid var(--lcd-focus, rgba(46, 105, 156, 0.32));
    outline-offset: 2px;
}

@media (max-width: 991px) {
    .cv-workspace__header {
        grid-template-columns: 1fr auto;
    }

    .cv-workspace__header nav {
        grid-column: 1 / -1;
        grid-row: 2;
    }

    .cv-workspace__body {
        display: block;
    }

    .cv-node-panel {
        width: auto;
        max-height: none;
        border-top: 1px solid var(--lcd-border, #dbe3eb);
        border-left: 0;
    }
}

@media (max-width: 767px) {
    .cv-workspace__header {
        padding: 12px;
    }

    .cv-workspace__title h3 {
        white-space: normal;
    }

    .cv-workspace__body,
    .cv-state {
        min-height: 470px;
    }

    .cv-visualization-viewport,
    .cv-visualization-stage {
        min-height: 470px;
    }

    .cv-visualization-viewport {
        max-height: min(66vh, 560px);
    }

    .cv-viewport-tools {
        position: sticky;
        top: 6px;
        max-width: calc(100% - 12px);
        margin: 6px 6px -48px auto;
        overflow-x: auto;
    }

    .cv-viewport-tools button span {
        display: none;
    }

    .cv-viewport-tools .cv-viewport-tools__export span {
        display: inline;
    }

    .cv-accessible-summary ul {
        grid-template-columns: 1fr;
    }

    .cv-workspace__footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .cv-node-panel dl {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575px) {
    .cv-visualization-viewport {
        max-height: 500px;
    }

    .cv-viewport-tools .cv-viewport-tools__export span {
        max-width: 72px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cv-node-panel {
        position: fixed;
        z-index: 1100;
        width: auto;
        max-height: none;
        padding: 18px;
        border: 0;
        background: #fff;
        inset: 0;
    }

    .cv-node-panel header {
        position: sticky;
        z-index: 1;
        top: -18px;
        padding-block: 14px;
        background: #fff;
    }

    .cv-workspace__exit-fullscreen {
        grid-column: 1 / -1;
        justify-self: stretch;
        justify-content: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cv-skeleton span {
        animation: none;
    }

    .cv-visualization-stage {
        transition: none;
    }
}
</style>
