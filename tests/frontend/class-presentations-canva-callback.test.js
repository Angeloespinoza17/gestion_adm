// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { canvaOAuthCallbackNotice, consumeCanvaOAuthCallback } from "../../resources/js/services/canva-oauth-callback";
import ClassPresentationsView from "../../resources/js/views/pedagogical-management/class-presentations.vue";

const apiMocks = vi.hoisted(() => ({
    options: vi.fn(),
    canvaConnection: vi.fn(),
    list: vi.fn(),
}));

vi.mock("../../resources/js/services/class-presentations-api", () => ({
    classPresentationsApi: apiMocks,
    isPresentationPending: () => false,
    statusPresentation: (status) => ({ label: status, tone: "neutral" }),
}));

vi.mock("../../resources/js/services/pedagogical-management-api", () => ({
    errorMessage: (_error, fallback) => fallback,
    saveBlob: vi.fn(),
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
    default: { template: "<main><slot /></main>" },
}));

vi.mock("sweetalert2", () => ({
    default: { fire: vi.fn() },
}));

const baseOptions = {
    schools: [{ id: 7, name: "Colegio" }],
    academic_years: [{ id: 2026, name: "2026" }],
    courses: [],
    authors: [],
    options: {},
    multiple_options: {},
    option_descriptions: {},
    style_profiles: {},
    statuses: [],
    selected_school_id: 7,
    selected_academic_year_id: 2026,
    canva_configured: true,
};

let wrapper;

async function mountCallback(url, connection) {
    window.history.replaceState({ preserved: true }, "", url);
    apiMocks.canvaConnection.mockResolvedValue(connection);
    wrapper = mount(ClassPresentationsView);
    await flushPromises();
    await flushPromises();
    return wrapper;
}

describe("Retorno OAuth de Canva en el Generador de clases", () => {
    beforeEach(() => {
        apiMocks.options.mockReset().mockResolvedValue({ ...baseOptions });
        apiMocks.canvaConnection.mockReset();
        apiMocks.list.mockReset().mockResolvedValue({ data: [] });
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        window.history.replaceState({}, "", "/");
    });

    it("consume connected, conserva otros parámetros y limpia sólo los datos OAuth", () => {
        const replaceState = vi.fn();
        const browser = {
            location: { href: "https://colegio.test/gestion-pedagogica/generador-clases?tab=history&canva=connected&connection=uuid-123&course=8#canva" },
            history: { state: { preserved: true }, replaceState },
        };

        expect(consumeCanvaOAuthCallback(browser)).toEqual({ status: "connected", code: "", connection: "uuid-123" });
        expect(replaceState).toHaveBeenCalledWith(
            { preserved: true },
            "",
            "/gestion-pedagogica/generador-clases?tab=history&course=8#canva",
        );
    });

    it("no elimina parámetros genéricos si la URL no es un callback Canva reconocido", () => {
        const replaceState = vi.fn();
        const browser = {
            location: { href: "https://colegio.test/ruta?canva=otro&code=codigo-compartido&connection=externa" },
            history: { state: null, replaceState },
        };

        expect(consumeCanvaOAuthCallback(browser)).toBeNull();
        expect(replaceState).not.toHaveBeenCalled();
    });

    it("traduce errores OAuth a avisos accionables sin reflejar valores inseguros", () => {
        expect(canvaOAuthCallbackNotice({ status: "error", code: "CANVA_OAUTH_DENIED" }).text).toContain("Pulsa “Conectar Canva”");
        expect(canvaOAuthCallbackNotice({ status: "error", code: "CANVA_OAUTH_STATE_INVALID" }).text).toContain("venció o ya fue utilizado");
        expect(canvaOAuthCallbackNotice({ status: "connected", connection: "uuid" }, { connected: false }).text).toContain("Pulsa “Verificar conexión”");
        expect(canvaOAuthCallbackNotice({ status: "error", code: "<script>" }).text).not.toContain("<script>");
    });

    it("al montar verifica una conexión exitosa, informa y conserva el resto de la URL", async () => {
        const mounted = await mountCallback(
            "/gestion-pedagogica/generador-clases?tab=history&canva=connected&connection=uuid-123#canva",
            { configured: true, connected: true, enterprise_autofill: true, autofill_available: true, mode: "enterprise", capabilities: { autofill: true } },
        );

        expect(apiMocks.canvaConnection).toHaveBeenCalledWith({ school_id: "7" });
        expect(mounted.find(".module-alert").text()).toContain("Canva quedó conectado y verificado");
        expect(mounted.find(".module-alert").classes()).toContain("is-success");
        expect(window.location.search).toBe("?tab=history");
        expect(window.location.hash).toBe("#canva");
    });

    it("al montar informa una cancelación y también vuelve a consultar el estado real", async () => {
        const mounted = await mountCallback(
            "/gestion-pedagogica/generador-clases?view=new&canva=error&code=CANVA_OAUTH_DENIED",
            { configured: true, connected: false, autofill_available: false, mode: "unavailable", capabilities: {} },
        );

        expect(apiMocks.canvaConnection).toHaveBeenCalledWith({ school_id: "7" });
        expect(mounted.find(".module-alert").text()).toContain("La autorización de Canva fue cancelada");
        expect(mounted.find(".module-alert").classes()).toContain("is-warning");
        expect(window.location.search).toBe("?view=new");
    });
});
