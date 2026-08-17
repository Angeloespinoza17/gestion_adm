import { defineAsyncComponent, defineComponent, h } from "vue";

const VisualizationLoading = defineComponent({
    name: "CurriculumVisualizationLoading",
    setup: () => () =>
        h(
            "div",
            {
                class: "d-flex min-vh-50 align-items-center justify-content-center gap-2 text-muted",
                role: "status",
            },
            [
                h("span", {
                    class: "spinner-border spinner-border-sm",
                    "aria-hidden": "true",
                }),
                h("span", "Preparando visualización"),
            ]
        ),
});

const VisualizationLoadError = defineComponent({
    name: "CurriculumVisualizationLoadError",
    setup: () => () =>
        h(
            "div",
            {
                class: "alert alert-warning m-3 d-flex flex-wrap align-items-center justify-content-between gap-3",
                role: "alert",
            },
            [
                h("span", [
                    h("strong", "No se pudo cargar este gráfico. "),
                    "La aplicación puede tener una versión anterior en memoria.",
                ]),
                h(
                    "button",
                    {
                        type: "button",
                        class: "btn btn-primary",
                        onClick: () => window.location.reload(),
                    },
                    "Recargar módulo"
                ),
            ]
        ),
});

const asyncView = (loader) =>
    defineAsyncComponent({
        loader,
        delay: 120,
        timeout: 30000,
        loadingComponent: VisualizationLoading,
        errorComponent: VisualizationLoadError,
        suspensible: false,
        onError(error, retry, fail, attempts) {
            if (attempts <= 2) {
                window.setTimeout(retry, attempts * 250);
                return;
            }
            fail(error);
        },
    });

export const visualizationRegistry = Object.freeze({
    table: {
        label: "Tabla",
        shortLabel: "Tabla",
        icon: "bx-table",
        component: null,
        description: "Resultados paginados y accesibles.",
    },
    treemap: {
        label: "Treemap",
        shortLabel: "Treemap",
        icon: "bx-grid-alt",
        component: asyncView(() => import("./CurriculumTreemap.vue")),
        description: "Área proporcional a la cantidad de objetivos.",
    },
    sunburst: {
        label: "Sunburst",
        shortLabel: "Sunburst",
        icon: "bx-doughnut-chart",
        component: asyncView(() => import("./CurriculumSunburst.vue")),
        description: "Jerarquía curricular organizada en anillos.",
    },
    circle_packing: {
        label: "Círculos",
        shortLabel: "Círculos",
        icon: "bx-circle",
        component: asyncView(() => import("./CurriculumCirclePacking.vue")),
        description: "Categorías contenidas en círculos proporcionales.",
    },
    sankey: {
        label: "Sankey",
        shortLabel: "Sankey",
        icon: "bx-git-branch",
        component: asyncView(() => import("./CurriculumSankey.vue")),
        description: "Flujos agregados entre niveles curriculares.",
    },
    icicle: {
        label: "Icicle",
        shortLabel: "Icicle",
        icon: "bx-columns",
        component: asyncView(() => import("./CurriculumIcicle.vue")),
        description: "Jerarquía rectangular navegable por niveles.",
    },
    radial_tree: {
        label: "Árbol radial",
        shortLabel: "Árbol",
        icon: "bx-share-alt",
        component: asyncView(() => import("./CurriculumRadialTree.vue")),
        description: "Ramas curriculares alrededor de una raíz central.",
    },
    network: {
        label: "Red curricular",
        shortLabel: "Red",
        icon: "bx-git-merge",
        component: asyncView(() => import("./CurriculumNetwork.vue")),
        description: "Solo relaciones explícitas registradas.",
    },
    mind_map: {
        label: "Mapa mental",
        shortLabel: "Mapa mental",
        icon: "bx-sitemap",
        component: asyncView(() => import("./CurriculumMindMap.vue")),
        description: "Exploración jerárquica de solo lectura.",
    },
});

export const visualizationOptions = Object.entries(visualizationRegistry).map(
    ([value, definition]) => ({
        value,
        text: definition.label,
        ...definition,
    })
);
