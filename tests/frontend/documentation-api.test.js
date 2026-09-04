// @vitest-environment jsdom

import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  createDocumentation,
  filenameFromDisposition,
  getDocumentationCatalogs,
  listDocumentation,
  updateDocumentation,
} from "../../resources/js/services/documentation-api";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

describe("Cliente API de Documentación", () => {
  beforeEach(() => vi.clearAllMocks());

  it("consulta catálogo y listado sin parámetros vacíos", async () => {
    axios.get.mockResolvedValue({ data: {} });
    await getDocumentationCatalogs();
    await listDocumentation({ page: 2, search: "PEI", category: null, year: "" });

    expect(axios.get).toHaveBeenNthCalledWith(1, "/api/documentation/catalogs");
    expect(axios.get).toHaveBeenNthCalledWith(2, "/api/documentation", { params: { page: 2, search: "PEI" } });
  });

  it("serializa altas multipart con los indicadores público y activo", async () => {
    axios.post.mockResolvedValue({ data: {} });
    const file = new File(["pdf"], "PEI.pdf", { type: "application/pdf" });
    await createDocumentation({
      title: "PEI",
      category: "proyecto-educativo",
      year: 2023,
      version: "Final",
      description: "Documento oficial",
      is_public: true,
      is_active: true,
      file,
    });

    const [url, payload] = axios.post.mock.calls[0];
    expect(url).toBe("/api/documentation");
    expect(payload).toBeInstanceOf(FormData);
    expect(payload.get("category")).toBe("proyecto-educativo");
    expect(payload.get("is_public")).toBe("1");
    expect(payload.get("is_active")).toBe("1");
    expect(payload.get("file")).toBe(file);
  });

  it("actualiza mediante method spoofing compatible con archivos multipart", async () => {
    axios.post.mockResolvedValue({ data: {} });
    await updateDocumentation(7, {
      title: "PEI actualizado",
      category: "proyecto-educativo",
      year: 2024,
      version: "2.0",
      is_public: false,
      is_active: true,
    });

    const [url, payload] = axios.post.mock.calls[0];
    expect(url).toBe("/api/documentation/7");
    expect(payload.get("_method")).toBe("PUT");
    expect(payload.get("is_public")).toBe("0");
  });

  it("recupera nombres UTF-8 de Content-Disposition", () => {
    expect(filenameFromDisposition("attachment; filename*=UTF-8''Proyecto%20Educativo.pdf"))
      .toBe("Proyecto Educativo.pdf");
    expect(filenameFromDisposition("", "respaldo.pdf")).toBe("respaldo.pdf");
  });
});

