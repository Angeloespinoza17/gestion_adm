// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import { readFileSync } from "node:fs";
import Swal from "sweetalert2";
import { beforeEach, describe, expect, it, vi } from "vitest";
import WebContentManager from "../../resources/js/components/public-site/web-content-manager.vue";
import {
  createInstallation,
  getInstallationCatalogs,
  listInstallations,
  reorderInstallations,
  updateInstallation,
} from "../../resources/js/services/public-site-content-api";

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

vi.mock("../../resources/js/services/public-site-content-api", () => ({
  createInstallation: vi.fn(),
  createStudentLife: vi.fn(),
  createTestimonial: vi.fn(),
  getInstallationCatalogs: vi.fn(),
  getStudentLifeCatalogs: vi.fn(),
  getTestimonialCatalogs: vi.fn(),
  listInstallations: vi.fn(),
  listStudentLife: vi.fn(),
  listTestimonials: vi.fn(),
  removeInstallation: vi.fn(),
  removeStudentLife: vi.fn(),
  removeTestimonial: vi.fn(),
  reorderInstallations: vi.fn(),
  updateInstallation: vi.fn(),
  updateStudentLife: vi.fn(),
  updateTestimonial: vi.fn(),
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div class='premium-content-grid'><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { props: ["message"], template: "<div class='loading-state'>{{ message }}</div>" },
}));

const installation = {
  id: 12,
  title: "Biblioteca escolar",
  slug: "biblioteca-escolar",
  category: "Aprendizaje",
  summary: "Un espacio abierto a la lectura y la investigación.",
  body_html: "<p>Descripción del espacio.</p>",
  location_label: "Primer piso",
  capacity: 40,
  capacity_label: "Capacidad para 40 personas",
  accessibility_notes: "Acceso a nivel.",
  features: ["Colección abierta", "Mesas de estudio"],
  icon: "book",
  icon_class: "bi-book",
  status: "published",
  active: true,
  featured: true,
  sort_order: 2,
  published_at: "2026-08-30T18:00:00-04:00",
  cover_image_alt: "Vista interior de la biblioteca escolar",
  preview_cover_image_url: "/api/admin/installations/12/cover",
  gallery_images: [
    { id: 31, preview_url: "/api/admin/installations/12/gallery/31", alt: "Mesas de lectura", sort_order: 1 },
  ],
  public_url: "/instalaciones#biblioteca-escolar",
};

const response = {
  data: [installation],
  meta: { current_page: 1, last_page: 1, per_page: 12, total: 1, from: 1, to: 1 },
  summary: { total: 1, published: 1, featured: 1, active: 1 },
};

const mountManager = () => mount(WebContentManager, {
  props: { contentType: "installations" },
  global: {
    stubs: {
      Layout: { template: "<div class='premium-content-grid'><slot /></div>" },
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

describe("Gestión interna de instalaciones", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("permissions", JSON.stringify([
      "ver_instalaciones_sitio",
      "gestionar_instalaciones_sitio",
    ]));
    vi.clearAllMocks();
    getInstallationCatalogs.mockResolvedValue({
      data: {
        statuses: ["draft", "published", "archived"],
        categories: ["Aprendizaje", "Deporte"],
        icons: [{ value: "buildings", label: "Edificio", class_name: "bi-buildings" }],
        stats: { total: 1, published: 1, featured: 1, active: 1 },
        capabilities: { can_manage: true },
      },
    });
    listInstallations.mockResolvedValue({ data: response });
    createInstallation.mockResolvedValue({ data: { message: "Instalación creada." } });
    reorderInstallations.mockResolvedValue({ data: { message: "Orden actualizado." } });
    Swal.fire.mockResolvedValue({ isConfirmed: true });
  });

  it("integra la ruta protegida y el módulo dinámico Sitio web", () => {
    const router = readFileSync("resources/js/router/index.js", "utf8");
    const sideNav = readFileSync("resources/js/components/side-nav.vue", "utf8");
    const manager = readFileSync("resources/js/components/public-site/web-content-manager.vue", "utf8");

    expect(router).toContain('path: "/admin/instalaciones"');
    expect(router).toContain('permission: "ver_instalaciones_sitio"');
    expect(sideNav).toContain('public_site_installations: "bx-buildings"');
    expect(sideNav).toContain('"/admin/instalaciones": "bx-buildings"');
    expect(manager).toContain("body.web-content-modal-open .swal2-container");
    expect(manager).toContain("z-index:1200!important");
  });

  it("presenta cada espacio con portada, datos prácticos y vínculo público", async () => {
    const wrapper = mountManager();
    await flushPromises();

    expect(wrapper.text()).toContain("Instalaciones");
    expect(wrapper.text()).toContain("Biblioteca escolar");
    expect(wrapper.text()).toContain("Primer piso");
    expect(wrapper.text()).toContain("Capacidad 40");
    expect(wrapper.text()).toContain("Colección abierta");
    expect(wrapper.find('.student-life-card__media img').attributes("alt")).toBe("Vista interior de la biblioteca escolar");
    expect(wrapper.find('a[aria-label="Ver en instalaciones"]').attributes("href")).toBe("/instalaciones#biblioteca-escolar");
  });

  it("ofrece un formulario editorial accesible con características, galería y SEO", async () => {
    const wrapper = mountManager();
    await flushPromises();
    await wrapper.find(".web-content-primary-action").trigger("click");

    expect(wrapper.text()).toContain("Identidad del espacio");
    expect(wrapper.text()).toContain("Accesibilidad");
    expect(wrapper.text()).toContain("Características del espacio");
    expect(wrapper.text()).toContain("Hasta 12 imágenes");
    expect(wrapper.text()).toContain("Metadatos para buscadores");
    expect(wrapper.find('.slug-field span').text()).toBe("/instalaciones#");
    expect(wrapper.vm.form.icon).toBe("buildings");
  });

  it("impide publicar sin portada y exige textos alternativos para todas las imágenes", async () => {
    const wrapper = mountManager();
    await flushPromises();
    await wrapper.vm.openCreate();

    Object.assign(wrapper.vm.form, {
      title: "Laboratorio de ciencias",
      slug: "laboratorio-ciencias",
      category: "Ciencias",
      summary: "Un espacio para experimentar.",
      body: "<p>Laboratorio equipado.</p>",
      status: "published",
      capacity: 32,
    });
    expect(wrapper.vm.validate()).toBe(false);
    expect(wrapper.vm.validationErrors.cover_image).toContain("portada");

    const cover = new File(["cover"], "laboratorio.jpg", { type: "image/jpeg" });
    const gallery = new File(["gallery"], "experimento.webp", { type: "image/webp" });
    Object.assign(wrapper.vm.form, { cover_image: cover, gallery: [gallery], gallery_alts: [""] });
    expect(wrapper.vm.validate()).toBe(false);
    expect(wrapper.vm.validationErrors.cover_image_alt).toContain("lectores de pantalla");
    expect(wrapper.vm.validationErrors.gallery_alts).toContain("texto alternativo");

    Object.assign(wrapper.vm.form, {
      cover_image_alt: "Mesones del laboratorio de ciencias",
      gallery_alts: ["Estudiantes realizando un experimento"],
    });
    await wrapper.vm.save();
    expect(createInstallation).toHaveBeenCalledWith(expect.objectContaining({
      icon: "buildings",
      cover_image_alt: "Mesones del laboratorio de ciencias",
      gallery_alts: ["Estudiantes realizando un experimento"],
    }));
  });

  it("reordena con el endpoint específico sin reescribir el registro", async () => {
    const wrapper = mountManager();
    await flushPromises();
    const previous = { ...installation, id: 11, title: "Capilla", slug: "capilla", sort_order: 1 };
    await wrapper.setData({ items: [previous, installation] });
    await wrapper.vm.move(installation, -1);

    expect(reorderInstallations).toHaveBeenCalledWith([
      { id: 12, sort_order: 1 },
      { id: 11, sort_order: 2 },
    ]);
  });

  it("bloquea la publicación rápida cuando alguna imagen carece de descripción", async () => {
    const wrapper = mountManager();
    await flushPromises();
    const draft = {
      ...installation,
      status: "draft",
      cover_image_alt: "",
      gallery_images: [{ id: 31, alt: "" }],
    };

    await wrapper.vm.togglePublished(draft);
    expect(updateInstallation).not.toHaveBeenCalled();
    expect(wrapper.vm.error).toContain("describe todas las imágenes");
  });
});
