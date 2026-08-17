import {
    computed,
    nextTick,
    onBeforeUnmount,
    ref,
    shallowRef,
    unref,
    watch,
} from "vue";
import { useRoute, useRouter } from "vue-router";
import { libroDigitalApi } from "../services/libro-digital/api";
import {
    CURRICULUM_HIERARCHY_PRESETS,
    CURRICULUM_VIEW_KEYS,
    DEFAULT_HIERARCHY,
    canonicalParams,
    normalizeVisualizationPayload,
    paramsSignature,
} from "../utils/curriculum-visualization";

const STORAGE_KEY = "libro-digital:curriculum-visualization:v1";
const cache = new Map();
const MAX_CACHE_ENTRIES = 36;
const viewSet = new Set(CURRICULUM_VIEW_KEYS);
const hierarchySet = new Set(
    CURRICULUM_HIERARCHY_PRESETS.map((preset) => preset.value)
);

const routeValue = (value, fallback = "") => {
    const scalar = Array.isArray(value) ? value[0] : value;
    return scalar === null || scalar === undefined ? fallback : String(scalar);
};

const storedPreferences = () => {
    if (typeof window === "undefined") return {};
    try {
        return JSON.parse(window.localStorage.getItem(STORAGE_KEY) || "{}");
    } catch {
        return {};
    }
};

const cacheSet = (key, value) => {
    if (cache.has(key)) cache.delete(key);
    cache.set(key, value);
    while (cache.size > MAX_CACHE_ENTRIES) {
        cache.delete(cache.keys().next().value);
    }
};

const maximumDepthForView = (view, hierarchy, drilling = false) => {
    const hierarchyDepth = String(hierarchy || "")
        .split(",")
        .filter(Boolean).length;
    if (view === "sankey") return Math.min(4, hierarchyDepth || 4);
    if (!drilling && ["radial_tree", "network", "mind_map"].includes(view)) {
        return Math.min(3, hierarchyDepth || 3);
    }
    return Math.min(5, Math.max(1, hierarchyDepth || 4));
};

export function useCurriculumVisualization({ requestParams, expectedTotal }) {
    const route = useRoute();
    const router = useRouter();
    const stored = storedPreferences();

    const requestedView = routeValue(route.query.view, stored.view || "table");
    const requestedHierarchy = routeValue(
        route.query.hierarchy,
        stored.hierarchy || DEFAULT_HIERARCHY
    );
    const requestedScope = routeValue(
        route.query.scope,
        stored.scope || "filtered"
    );
    const requestedLabels = routeValue(
        route.query.labels,
        stored.labels || "automatic"
    );

    const activeView = ref(
        viewSet.has(requestedView) ? requestedView : "table"
    );
    const hierarchyPreset = ref(
        hierarchySet.has(requestedHierarchy)
            ? requestedHierarchy
            : DEFAULT_HIERARCHY
    );
    const scope = ref(
        ["filtered", "catalog"].includes(requestedScope)
            ? requestedScope
            : "filtered"
    );
    const labelMode = ref(
        ["automatic", "show", "hide"].includes(requestedLabels)
            ? requestedLabels
            : "automatic"
    );
    const rootNode = ref(routeValue(route.query.root_node) || null);
    const selectedNode = shallowRef(null);
    const data = shallowRef(null);
    const loading = ref(false);
    const error = shallowRef(null);
    const refreshing = ref(false);
    let controller = null;
    let requestTimer = null;
    let urlTimer = null;
    let inFlightKey = null;
    let inFlightPromise = null;
    let ignoreRouteSync = false;

    const baseParams = computed(() => {
        const resolved = { ...(unref(requestParams) || {}) };
        delete resolved.page;
        delete resolved.per_page;
        delete resolved.__cache_token;
        return canonicalParams(resolved);
    });
    const cacheToken = computed(
        () => unref(requestParams)?.__cache_token ?? null
    );
    const baseSignature = computed(() =>
        paramsSignature({
            ...baseParams.value,
            __cache_token: cacheToken.value,
        })
    );
    const requestPayload = computed(() =>
        canonicalParams({
            ...baseParams.value,
            view: activeView.value,
            hierarchy: hierarchyPreset.value,
            scope: scope.value,
            root_node: rootNode.value,
            max_depth: maximumDepthForView(
                activeView.value,
                hierarchyPreset.value,
                Boolean(rootNode.value)
            ),
            include_leaves:
                rootNode.value || Number(unref(expectedTotal) || 0) <= 500
                    ? 1
                    : 0,
        })
    );
    const requestKey = computed(() =>
        paramsSignature({
            ...requestPayload.value,
            __cache_token: cacheToken.value,
        })
    );
    const isGraphView = computed(() => activeView.value !== "table");
    const breadcrumbs = computed(() => {
        const items = [
            {
                id: null,
                type: "catalog",
                name:
                    scope.value === "catalog"
                        ? "Catálogo completo"
                        : "Resultados filtrados",
                code: null,
            },
        ];
        const known = new Set([null]);
        for (const item of data.value?.root?.path || []) {
            if (known.has(item.id)) continue;
            known.add(item.id);
            items.push(item);
        }
        const root = data.value?.root;
        if (root?.id && root.type !== "catalog" && !known.has(root.id)) {
            items.push({
                id: root.id,
                type: root.type,
                name: root.name,
                code: root.code,
            });
        }
        return items;
    });
    const countMismatch = computed(() => {
        if (!data.value || scope.value !== "filtered") return false;
        return (
            Number(data.value.meta.filteredTotalObjectives) !==
            Number(unref(expectedTotal) || 0)
        );
    });

    const persist = () => {
        if (typeof window === "undefined") return;
        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                view: activeView.value,
                hierarchy: hierarchyPreset.value,
                scope: scope.value,
                labels: labelMode.value,
            })
        );
    };

    const updateUrl = () => {
        window.clearTimeout(urlTimer);
        urlTimer = window.setTimeout(async () => {
            const query = { ...route.query };
            ["view", "hierarchy", "scope", "root_node", "labels"].forEach(
                (key) => delete query[key]
            );
            Object.assign(query, {
                view: activeView.value,
                hierarchy: hierarchyPreset.value,
                scope: scope.value,
                ...(rootNode.value ? { root_node: rootNode.value } : {}),
                ...(labelMode.value !== "automatic"
                    ? { labels: labelMode.value }
                    : {}),
            });
            ignoreRouteSync = true;
            try {
                await router.replace({ query });
            } catch {
                // A filter update may supersede this replace operation.
            } finally {
                await nextTick();
                ignoreRouteSync = false;
            }
        }, 20);
    };

    const performLoad = async ({ force = false } = {}) => {
        if (!isGraphView.value) {
            controller?.abort();
            controller = null;
            loading.value = false;
            refreshing.value = false;
            return null;
        }

        const key = requestKey.value;
        if (!force && cache.has(key)) {
            data.value = cache.get(key);
            error.value = null;
            loading.value = false;
            refreshing.value = false;
            return data.value;
        }
        if (!force && inFlightKey === key && inFlightPromise) {
            return inFlightPromise;
        }

        controller?.abort();
        const activeController = new AbortController();
        controller = activeController;
        data.value ? (refreshing.value = true) : (loading.value = true);
        error.value = null;
        inFlightKey = key;

        inFlightPromise = libroDigitalApi
            .curriculumVisualization(
                requestPayload.value,
                activeController.signal
            )
            .then((payload) => {
                if (
                    controller !== activeController ||
                    activeController.signal.aborted
                ) {
                    return null;
                }
                const normalized = normalizeVisualizationPayload(payload);
                cacheSet(key, normalized);
                data.value = normalized;
                return normalized;
            })
            .catch((requestError) => {
                if (
                    controller === activeController &&
                    requestError?.code !== "ERR_CANCELED"
                ) {
                    error.value = requestError;
                }
                return null;
            })
            .finally(() => {
                if (controller === activeController) {
                    controller = null;
                    loading.value = false;
                    refreshing.value = false;
                }
                if (inFlightKey === key) {
                    inFlightKey = null;
                    inFlightPromise = null;
                }
            });

        return inFlightPromise;
    };

    const scheduleLoad = (force = false) => {
        window.clearTimeout(requestTimer);
        if (!isGraphView.value) {
            void performLoad();
            return;
        }
        const delay = baseParams.value.query ? 260 : 40;
        requestTimer = window.setTimeout(
            () => void performLoad({ force }),
            delay
        );
    };

    const setView = (view) => {
        activeView.value = viewSet.has(view) ? view : "table";
        selectedNode.value = null;
    };
    const setHierarchy = (hierarchy) => {
        if (!hierarchySet.has(hierarchy)) return;
        hierarchyPreset.value = hierarchy;
        rootNode.value = null;
        selectedNode.value = null;
        data.value = null;
    };
    const setScope = (nextScope) => {
        scope.value = nextScope === "catalog" ? "catalog" : "filtered";
        rootNode.value = null;
        selectedNode.value = null;
        data.value = null;
    };
    const drillDown = (node) => {
        selectedNode.value = node || null;
        if (!node?.id || node.type === "objective") return;
        rootNode.value = node.id;
    };
    const goToBreadcrumb = (item) => {
        rootNode.value = item?.id || null;
        selectedNode.value = null;
    };
    const resetView = () => {
        rootNode.value = null;
        selectedNode.value = null;
        data.value = null;
        scheduleLoad(true);
    };
    const refresh = () => scheduleLoad(true);
    const exportView = async (handler) => handler?.();

    watch(baseSignature, (next, previous) => {
        if (previous !== undefined && next !== previous) {
            rootNode.value = null;
            selectedNode.value = null;
            data.value = null;
        }
        scheduleLoad();
    });

    watch(
        [activeView, hierarchyPreset, scope, rootNode],
        () => {
            persist();
            updateUrl();
            scheduleLoad();
        },
        { immediate: true }
    );

    watch(labelMode, () => {
        persist();
        updateUrl();
    });

    watch(
        () =>
            [
                route.query.view,
                route.query.hierarchy,
                route.query.scope,
                route.query.root_node,
                route.query.labels,
            ]
                .map((value) => routeValue(value))
                .join("|"),
        () => {
            if (ignoreRouteSync) return;
            const nextView = routeValue(route.query.view, "table");
            const nextHierarchy = routeValue(
                route.query.hierarchy,
                DEFAULT_HIERARCHY
            );
            const nextScope = routeValue(route.query.scope, "filtered");
            const nextLabels = routeValue(route.query.labels, "automatic");
            if (viewSet.has(nextView)) activeView.value = nextView;
            if (hierarchySet.has(nextHierarchy)) {
                hierarchyPreset.value = nextHierarchy;
            }
            scope.value = nextScope === "catalog" ? "catalog" : "filtered";
            rootNode.value = routeValue(route.query.root_node) || null;
            labelMode.value = ["automatic", "show", "hide"].includes(nextLabels)
                ? nextLabels
                : "automatic";
        }
    );

    onBeforeUnmount(() => {
        controller?.abort();
        window.clearTimeout(requestTimer);
        window.clearTimeout(urlTimer);
    });

    return {
        activeView,
        hierarchyPreset,
        scope,
        labelMode,
        rootNode,
        selectedNode,
        data,
        loading,
        refreshing,
        error,
        breadcrumbs,
        countMismatch,
        requestPayload,
        setView,
        setHierarchy,
        setScope,
        drillDown,
        goToBreadcrumb,
        resetView,
        refresh,
        loadVisualization: performLoad,
        loadNodeChildren: drillDown,
        exportView,
    };
}
