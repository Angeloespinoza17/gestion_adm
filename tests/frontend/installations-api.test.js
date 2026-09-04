// @vitest-environment jsdom

import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  createInstallation,
  installationPayload,
  listInstallations,
  reorderInstallations,
  updateInstallation,
} from "../../resources/js/services/public-site-content-api";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

describe("Cliente API de instalaciones del sitio web", () => {
  beforeEach(() => vi.clearAllMocks());

  it("consulta el listado sin enviar filtros vacíos", async () => {
    axios.get.mockResolvedValue({ data: {} });
    await listInstallations({ page: 2, search: "biblioteca", status: "", category: null, featured: "1" });

    expect(axios.get).toHaveBeenCalledWith("/api/admin/installations", {
      params: { page: 2, search: "biblioteca", featured: "1" },
    });
  });

  it("serializa portada, galería accesible, características e icono de catálogo", async () => {
    axios.post.mockResolvedValue({ data: {} });
    const cover = new File(["cover"], "biblioteca.webp", { type: "image/webp" });
    const gallery = new File(["gallery"], "lectura.jpg", { type: "image/jpeg" });
    const installation = {
      title: "Biblioteca escolar",
      slug: "biblioteca-escolar",
      category: "Aprendizaje",
      summary: "Un espacio para leer y aprender.",
      body: "<p>Descripción</p>",
      location_label: "Primer piso",
      capacity: 40,
      accessibility_notes: "Acceso a nivel.",
      icon: "buildings",
      features: ["Colección abierta", "Mesas de estudio"],
      cover_image: cover,
      cover_image_alt: "Vista interior de la biblioteca escolar",
      gallery: [gallery],
      gallery_alts: ["Estudiantes leyendo en la biblioteca"],
      active: true,
      featured: false,
      status: "draft",
    };

    const payload = installationPayload(installation);
    expect(payload.get("icon")).toBe("buildings");
    expect(payload.get("accessibility_notes")).toBe("Acceso a nivel.");
    expect(payload.getAll("features[]")).toEqual(["Colección abierta", "Mesas de estudio"]);
    expect(payload.get("cover_image")).toBe(cover);
    expect(payload.getAll("gallery[]")).toEqual([gallery]);
    expect(payload.getAll("gallery_alts[]")).toEqual(["Estudiantes leyendo en la biblioteca"]);

    await createInstallation(installation);
    expect(axios.post).toHaveBeenCalledWith(
      "/api/admin/installations",
      expect.any(FormData),
      { headers: { "Content-Type": "multipart/form-data" } },
    );
    expect(axios.post.mock.calls[0][1].get("icon")).toBe("buildings");
  });

  it("actualiza en multipart y reordena mediante el endpoint dedicado", async () => {
    axios.post.mockResolvedValue({ data: {} });
    axios.patch.mockResolvedValue({ data: {} });

    await updateInstallation(8, {
      title: "Laboratorio",
      slug: "laboratorio",
      category: "Ciencias",
      summary: "Espacio científico.",
      body: "<p>Equipamiento</p>",
      icon: "science",
      gallery_order: [31, 29],
      remove_gallery_image_ids: [30],
      active: true,
    });
    const [url, payload] = axios.post.mock.calls[0];
    expect(url).toBe("/api/admin/installations/8");
    expect(payload.get("_method")).toBe("PUT");
    expect(payload.getAll("gallery_order[]")).toEqual(["31", "29"]);
    expect(payload.getAll("remove_gallery_image_ids[]")).toEqual(["30"]);

    await reorderInstallations([{ id: 8, sort_order: 2 }]);
    expect(axios.patch).toHaveBeenCalledWith("/api/admin/installations/reorder", {
      items: [{ id: 8, sort_order: 2 }],
    });
  });
});
