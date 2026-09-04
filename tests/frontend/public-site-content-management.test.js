// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import { readFileSync } from "node:fs";
import Swal from "sweetalert2";
import { beforeEach, describe, expect, it, vi } from "vitest";
import WebContentManager from "../../resources/js/components/public-site/web-content-manager.vue";
import {
  createStudentLife,
  createTestimonial,
  getStudentLifeCatalogs,
  getTestimonialCatalogs,
  listStudentLife,
  listTestimonials,
  removeTestimonial,
  updateStudentLife,
  updateTestimonial,
} from "../../resources/js/services/public-site-content-api";

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

vi.mock("../../resources/js/services/public-site-content-api", () => ({
  createStudentLife: vi.fn(),
  createTestimonial: vi.fn(),
  getStudentLifeCatalogs: vi.fn(),
  getTestimonialCatalogs: vi.fn(),
  listStudentLife: vi.fn(),
  listTestimonials: vi.fn(),
  removeStudentLife: vi.fn(),
  removeTestimonial: vi.fn(),
  updateStudentLife: vi.fn(),
  updateTestimonial: vi.fn(),
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { props: ["message"], template: "<div class='loading-state'>{{ message }}</div>" },
}));

const paginated = (data) => ({
  data,
  meta: { current_page: 1, last_page: 1, per_page: 12, total: data.length, from: data.length ? 1 : 0, to: data.length },
  summary: { total: data.length, published: 1, featured: 1, active: data.length },
});

const testimonial = {
  id: 4,
  quote: "El colegio acompaña de manera cercana nuestra formación.",
  author_name: "Camila Pérez",
  author_role: "Estudiante",
  status: "published",
  active: true,
  featured: true,
  sort_order: 1,
  consent_confirmed_at: "2026-08-30T18:00:00-04:00",
  preview_image_url: "/api/admin/testimonials/4/image",
};

const studentLife = {
  id: 7,
  title: "Encuentro de pastoral",
  slug: "encuentro-de-pastoral",
  category: "Pastoral",
  summary: "Una jornada de comunidad, servicio y encuentro.",
  body_html: "<p>Contenido</p>",
  status: "published",
  active: true,
  featured: false,
  sort_order: 3,
  event_date: "2026-08-20",
  preview_cover_image_url: "/api/admin/student-life/7/cover",
  public_url: "/vidaestudiantil/encuentro-de-pastoral",
};

const mountManager = (contentType) => mount(WebContentManager, {
  props: { contentType },
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { props: ["message"], template: "<div class='loading-state'>{{ message }}</div>" },
      RouterLink: { props: ["to"], template: "<a :href='to'><slot /></a>" },
      Teleport: true,
      Ckeditor: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template: "<textarea class='ckeditor-stub' :value='modelValue' @input='$emit(`update:modelValue`, $event.target.value)'></textarea>",
      },
    },
  },
});

describe("Gestión interna de contenidos del sitio web", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("permissions", JSON.stringify([
      "ver_testimonios",
      "gestionar_testimonios",
      "ver_vida_estudiantil",
      "gestionar_vida_estudiantil",
    ]));
    vi.clearAllMocks();
    getTestimonialCatalogs.mockResolvedValue({ data: { statuses: [], stats: { total: 1, published: 1, featured: 1, active: 1 } } });
    getStudentLifeCatalogs.mockResolvedValue({ data: { statuses: [], categories: ["Pastoral", "Deportes"], stats: { total: 1, published: 1, featured: 0, active: 1 } } });
    listTestimonials.mockResolvedValue({ data: paginated([testimonial]) });
    listStudentLife.mockResolvedValue({ data: paginated([studentLife]) });
    createTestimonial.mockResolvedValue({ data: { message: "Testimonio creado." } });
    createStudentLife.mockResolvedValue({ data: { message: "Publicación creada." } });
    updateTestimonial.mockResolvedValue({ data: { message: "Testimonio actualizado." } });
    updateStudentLife.mockResolvedValue({ data: { message: "Publicación actualizada." } });
    removeTestimonial.mockResolvedValue({ data: { message: "Testimonio eliminado." } });
    Swal.fire.mockResolvedValue({ isConfirmed: true });
  });

  it("integra rutas protegidas y enlaces dinámicos bajo Sitio web", () => {
    const router = readFileSync("resources/js/router/index.js", "utf8");
    const sideNav = readFileSync("resources/js/components/side-nav.vue", "utf8");

    expect(router).toContain('path: "/admin/testimonios"');
    expect(router).toContain('permission: "ver_testimonios"');
    expect(router).toContain('path: "/admin/vida-estudiantil"');
    expect(router).toContain('permission: "ver_vida_estudiantil"');
    expect(sideNav).toContain('public_site_testimonials: "bx-message-rounded-dots"');
    expect(sideNav).toContain('public_site_student_life: "bx-images"');
  });

  it("presenta testimonios con estado, prioridad, orden y acciones editoriales", async () => {
    const wrapper = mountManager("testimonials");
    await flushPromises();

    expect(wrapper.text()).toContain("Testimonios");
    expect(wrapper.text()).toContain("Camila Pérez");
    expect(wrapper.text()).toContain("Publicado");
    expect(wrapper.text()).toContain("Destacado");
    expect(wrapper.text()).toContain("#1");
    expect(wrapper.find('button[aria-label="Editar"]').exists()).toBe(true);
    expect(wrapper.find('button[aria-label="Quitar destacado"]').exists()).toBe(true);
  });

  it("exige autorización y autenticidad antes de crear un testimonio", async () => {
    const wrapper = mountManager("testimonials");
    await flushPromises();
    await wrapper.find(".web-content-primary-action").trigger("click");

    Object.assign(wrapper.vm.form, {
      quote: "Una experiencia cercana.",
      author_name: "María González",
      author_role: "Apoderada",
    });
    await wrapper.vm.confirmSave();
    expect(createTestimonial).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain("Confirma la autorización antes de guardar.");
    expect(wrapper.text()).toContain("no fue inventado");

    wrapper.vm.form.authorization_confirmed = true;
    await wrapper.vm.save();
    expect(createTestimonial).toHaveBeenCalledWith(expect.objectContaining({
      quote: "Una experiencia cercana.",
      author_name: "María González",
    }));
  });

  it("muestra Vida estudiantil con portada, fecha, vínculo público y formulario editorial", async () => {
    const wrapper = mountManager("student-life");
    await flushPromises();

    expect(wrapper.text()).toContain("Vida estudiantil");
    expect(wrapper.text()).toContain("Encuentro de pastoral");
    expect(wrapper.find('.student-life-card__media img').attributes("src")).toBe("/api/admin/student-life/7/cover");
    expect(wrapper.find('a[aria-label="Ver publicación"]').attributes("href")).toBe("/vidaestudiantil/encuentro-de-pastoral");

    await wrapper.find(".web-content-primary-action").trigger("click");
    expect(wrapper.text()).toContain("Contenido individual");
    expect(wrapper.text()).toContain("Portada y galería");
    expect(wrapper.find('input[placeholder="experiencia-que-deja-huella"]').exists()).toBe(true);
    wrapper.vm.form.title = "Alegría, Servicio y Comunidad";
    wrapper.vm.syncSlugFromTitle();
    expect(wrapper.vm.form.slug).toBe("alegria-servicio-y-comunidad");
  });

  it("envía filtros paginados propios de Vida estudiantil", async () => {
    const wrapper = mountManager("student-life");
    await flushPromises();
    await wrapper.setData({ filters: { search: "pastoral", status: "published", active: "1", featured: "", category: "Pastoral" } });
    await wrapper.find(".web-content-filters").trigger("submit");
    await flushPromises();

    expect(listStudentLife).toHaveBeenLastCalledWith(expect.objectContaining({
      page: 1,
      search: "pastoral",
      status: "published",
      active: "1",
      category: "Pastoral",
    }));
  });

  it("publica, destaca y reordena mediante actualizaciones seguras", async () => {
    const wrapper = mountManager("testimonials");
    await flushPromises();

    await wrapper.vm.togglePublished({ ...testimonial, status: "draft", published_at: null, consent_confirmed_at: null, authorization_confirmed: false });
    expect(updateTestimonial).not.toHaveBeenCalled();
    expect(wrapper.vm.error).toContain("confirma la autorización");

    await wrapper.vm.togglePublished({ ...testimonial, status: "draft", published_at: null });
    expect(updateTestimonial).toHaveBeenLastCalledWith(4, expect.objectContaining({ status: "published", active: true }));

    await wrapper.vm.toggleFeatured({ ...testimonial, featured: false });
    expect(updateTestimonial).toHaveBeenLastCalledWith(4, expect.objectContaining({ featured: true }));

    await wrapper.vm.move(testimonial, 1);
    expect(updateTestimonial).toHaveBeenLastCalledWith(4, expect.objectContaining({ sort_order: 2 }));
  });

  it("sanea errores del servidor sin exponer SQL ni infraestructura", async () => {
    listTestimonials.mockRejectedValueOnce({
      response: { status: 500, data: { message: "SQLSTATE[42S02] Connection mysql Database gestion_adm" } },
    });
    const wrapper = mountManager("testimonials");
    await flushPromises();

    expect(wrapper.text()).toContain("No fue posible cargar los testimonios.");
    expect(wrapper.text()).not.toContain("SQLSTATE");
    expect(wrapper.text()).not.toContain("gestion_adm");
  });
});
