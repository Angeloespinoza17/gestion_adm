import { nextTick, onBeforeUnmount, onMounted, ref } from "vue";

const MINIMUM_CHART_SIZE = 2;
const MAXIMUM_RENDER_ATTEMPTS = 24;

const nextFrame = () =>
    new Promise((resolve) => {
        if (typeof requestAnimationFrame === "function") {
            requestAnimationFrame(() => resolve());
            return;
        }
        setTimeout(resolve, 16);
    });

const requestFrame = (callback) =>
    typeof requestAnimationFrame === "function"
        ? requestAnimationFrame(callback)
        : setTimeout(callback, 16);

const cancelFrame = (frame) => {
    if (!frame) return;
    if (typeof cancelAnimationFrame === "function") {
        cancelAnimationFrame(frame);
        return;
    }
    clearTimeout(frame);
};

const chartInstance = (component) => {
    const exposed = component?.chart;
    return exposed?.value || exposed || null;
};

export const chartRenderState = (element, component) => {
    const bounds = element?.getBoundingClientRect?.() || {};
    const instance = chartInstance(component);
    const width = Math.max(
        Number(bounds.width || 0),
        Number(element?.clientWidth || 0),
        Number(instance?.getWidth?.() || 0)
    );
    const height = Math.max(
        Number(bounds.height || 0),
        Number(element?.clientHeight || 0),
        Number(instance?.getHeight?.() || 0)
    );
    const canvases = [...(element?.querySelectorAll?.("canvas") || [])];
    const canvasesReady =
        !canvases.length ||
        canvases.every(
            (canvas) =>
                Number(canvas.width || 0) >= MINIMUM_CHART_SIZE &&
                Number(canvas.height || 0) >= MINIMUM_CHART_SIZE
        );

    return {
        width,
        height,
        ready:
            width >= MINIMUM_CHART_SIZE &&
            height >= MINIMUM_CHART_SIZE &&
            canvasesReady,
    };
};

export const useRenderableEChart = () => {
    const chart = ref(null);
    const chartHost = ref(null);
    let resizeObserver = null;
    let resizeFrame = null;

    const resize = async () => {
        await nextTick();
        const state = chartRenderState(chartHost.value, chart.value);
        if (
            state.width < MINIMUM_CHART_SIZE ||
            state.height < MINIMUM_CHART_SIZE
        ) {
            return false;
        }
        chart.value?.resize?.({
            width: Math.round(state.width),
            height: Math.round(state.height),
        });
        return true;
    };

    const scheduleResize = () => {
        cancelFrame(resizeFrame);
        resizeFrame = requestFrame(async () => {
            resizeFrame = null;
            await resize();
        });
    };

    const waitUntilRenderable = async () => {
        for (let attempt = 0; attempt < MAXIMUM_RENDER_ATTEMPTS; attempt += 1) {
            await resize();
            await nextFrame();
            const state = chartRenderState(chartHost.value, chart.value);
            if (state.ready) return state;
        }
        throw new Error(
            "El gráfico todavía no tiene un tamaño visible. Usa Ajustar o abre la vista nuevamente antes de exportar."
        );
    };

    const exportImage = async (options = {}) => {
        await waitUntilRenderable();
        await nextFrame();
        const source = chart.value?.getDataURL?.(options);
        if (!source) {
            throw new Error(
                "El gráfico aún no terminó de dibujarse. Espera un instante y vuelve a exportar."
            );
        }
        return source;
    };

    onMounted(async () => {
        if (typeof ResizeObserver !== "undefined" && chartHost.value) {
            resizeObserver = new ResizeObserver((entries) => {
                const box = entries[0]?.contentRect;
                if (
                    Number(box?.width || 0) >= MINIMUM_CHART_SIZE &&
                    Number(box?.height || 0) >= MINIMUM_CHART_SIZE
                ) {
                    scheduleResize();
                }
            });
            resizeObserver.observe(chartHost.value);
        }
        await nextTick();
        scheduleResize();
        await nextFrame();
        scheduleResize();
    });

    onBeforeUnmount(() => {
        resizeObserver?.disconnect?.();
        cancelFrame(resizeFrame);
    });

    return {
        chart,
        chartHost,
        chartInstance: () => chartInstance(chart.value),
        resize,
        scheduleResize,
        waitUntilRenderable,
        exportImage,
    };
};
