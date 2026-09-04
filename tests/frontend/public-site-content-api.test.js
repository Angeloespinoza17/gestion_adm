// @vitest-environment jsdom

import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  createStudentLife,
  createTestimonial,
  listStudentLife,
  listTestimonials,
  updateStudentLife,
} from "../../resources/js/services/public-site-content-api";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

describe("Cliente API de contenidos del sitio web", () => {
  beforeEach(() => vi.clearAllMocks());

  it("consulta ambos listados paginados sin parámetros vacíos", async () => {
    axios.get.mockResolvedValue({ data: {} });
    await listTestimonials({ page: 2, search: "familia", status: "", featured: null });
    await listStudentLife({ page: 3, category: "Pastoral", active: "1", search: "" });

    expect(axios.get).toHaveBeenNthCalledWith(1, "/api/admin/testimonials", {
      params: { page: 2, search: "familia" },
    });
    expect(axios.get).toHaveBeenNthCalledWith(2, "/api/admin/student-life", {
      params: { page: 3, category: "Pastoral", active: "1" },
    });
  });

  it("serializa testimonios con imagen, estado, prioridad y booleanos", async () => {
    axios.post.mockResolvedValue({ data: {} });
    const image = new File(["portrait"], "persona.webp", { type: "image/webp" });
    await createTestimonial({
      quote: "Una comunidad que acompaña.",
      author_name: "Ana Pérez",
      author_role: "Apoderada",
      status: "published",
      active: true,
      featured: true,
      authorization_confirmed: true,
      sort_order: 2,
      image,
    });

    const [url, payload] = axios.post.mock.calls[0];
    expect(url).toBe("/api/admin/testimonials");
    expect(payload).toBeInstanceOf(FormData);
    expect(payload.get("quote")).toBe("Una comunidad que acompaña.");
    expect(payload.get("featured")).toBe("1");
    expect(payload.get("authorization_confirmed")).toBe("1");
    expect(payload.get("sort_order")).toBe("2");
    expect(payload.get("image")).toBe(image);
  });

  it("crea vida estudiantil con portada y galería accesible", async () => {
    axios.post.mockResolvedValue({ data: {} });
    const cover = new File(["cover"], "portada.jpg", { type: "image/jpeg" });
    const gallery = new File(["gallery"], "actividad.png", { type: "image/png" });
    await createStudentLife({
      title: "Encuentro pastoral",
      slug: "encuentro-pastoral",
      category: "Pastoral",
      summary: "Una jornada de encuentro.",
      body: "<p>Contenido</p>",
      cover_image: cover,
      gallery: [gallery],
      gallery_alts: ["Estudiantes en una actividad pastoral"],
      status: "draft",
      active: true,
    });

    const [url, payload] = axios.post.mock.calls[0];
    expect(url).toBe("/api/admin/student-life");
    expect(payload.get("cover_image")).toBe(cover);
    expect(payload.getAll("gallery[]")).toEqual([gallery]);
    expect(payload.getAll("gallery_alts[]")).toEqual(["Estudiantes en una actividad pastoral"]);
  });

  it("actualiza contenido multipart mediante method spoofing", async () => {
    axios.post.mockResolvedValue({ data: {} });
    await updateStudentLife(8, {
      title: "Experiencia actualizada",
      slug: "experiencia-actualizada",
      category: "Deportes",
      summary: "Resumen",
      body: "<p>Contenido</p>",
      status: "published",
      active: true,
      featured: false,
      remove_gallery_image_ids: [21, 22],
    });

    const [url, payload] = axios.post.mock.calls[0];
    expect(url).toBe("/api/admin/student-life/8");
    expect(payload.get("_method")).toBe("PUT");
    expect(payload.getAll("remove_gallery_image_ids[]")).toEqual(["21", "22"]);
  });
});
