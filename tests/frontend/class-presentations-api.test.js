import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { classPresentationsApi } from "../../resources/js/services/class-presentations-api";

vi.mock("axios", () => ({
    default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

describe("API frontend del Generador de clases con Canva", () => {
    beforeEach(() => {
        axios.get.mockReset();
        axios.post.mockReset();
        axios.delete.mockReset();
        axios.get.mockResolvedValue({ data: { data: {} } });
        axios.post.mockResolvedValue({ data: { data: {} } });
        axios.delete.mockResolvedValue({ data: { data: {} } });
    });

    it("consulta la conexión e inicia OAuth siempre dentro del establecimiento", async () => {
        await classPresentationsApi.canvaConnection({ school_id: 7 });
        await classPresentationsApi.beginCanvaAuthorization({ school_id: 7, redirect_to: "/gestion-pedagogica/generador-clases" });

        expect(axios.get).toHaveBeenCalledWith("/api/gestion-pedagogica/canva/conexion", expect.objectContaining({ params: { school_id: 7 } }));
        expect(axios.post).toHaveBeenCalledWith("/api/gestion-pedagogica/canva/autorizacion", {
            school_id: 7,
            redirect_to: "/gestion-pedagogica/generador-clases",
        });
    });

    it("pagina plantillas y valida el contrato Autofill seleccionado", async () => {
        await classPresentationsApi.canvaTemplates({ school_id: 7, query: "infantil", continuation: "next", limit: 24 });
        await classPresentationsApi.validateCanvaTemplate("template/id", { school_id: 7, slide_count: 12 });

        expect(axios.get).toHaveBeenCalledWith("/api/gestion-pedagogica/canva/plantillas", expect.objectContaining({
            params: { school_id: 7, query: "infantil", continuation: "next", limit: 24 },
        }));
        expect(axios.post).toHaveBeenCalledWith("/api/gestion-pedagogica/canva/plantillas/template%2Fid/validar", { school_id: 7, slide_count: 12 });
    });

    it("obtiene un enlace de edición fresco y permite reintentar el diseño Canva", async () => {
        await classPresentationsApi.canvaEditLink("presentation-1");
        await classPresentationsApi.syncCanvaDesign("presentation-1");
        await classPresentationsApi.disconnectCanva({ school_id: 7 });

        expect(axios.post).toHaveBeenNthCalledWith(1, "/api/gestion-pedagogica/presentaciones/presentation-1/canva/enlace-edicion");
        expect(axios.post).toHaveBeenNthCalledWith(2, "/api/gestion-pedagogica/presentaciones/presentation-1/canva/sincronizar");
        expect(axios.delete).toHaveBeenCalledWith("/api/gestion-pedagogica/canva/conexion", { data: { school_id: 7 } });
    });
});
