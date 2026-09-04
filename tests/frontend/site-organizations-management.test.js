// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import { readFileSync } from "node:fs";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import SiteOrganizationManager from "../../resources/js/components/public-site/site-organization-manager.vue";
import {
  createSiteOrganization,
  createSiteOrganizationRole,
  getSiteOrganizationCatalogs,
  listSiteOrganizations,
  searchOrganizationStaff,
  searchOrganizationStudents,
} from "../../resources/js/services/site-organizations-api";

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

vi.mock("../../resources/js/services/site-organizations-api", () => ({
  archiveSiteOrganization: vi.fn(),
  createSiteOrganization: vi.fn(),
  createSiteOrganizationRole: vi.fn(),
  getSiteOrganization: vi.fn(),
  getSiteOrganizationCatalogs: vi.fn(),
  listSiteOrganizations: vi.fn(),
  searchOrganizationStaff: vi.fn(),
  searchOrganizationStudents: vi.fn(),
  updateSiteOrganization: vi.fn(),
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { props: ["message"], template: "<div class='loading-state'>{{ message }}</div>" },
}));

const roles = [
  { id: 1, name: "Presidenta", section: "leadership" },
  { id: 2, name: "Asesora", section: "advisor" },
  { id: 3, name: "Integrante", section: "member" },
];

const catalogs = {
  statuses: ["draft", "published", "archived"],
  sections: ["leadership", "advisor", "member"],
  roles,
  capabilities: { can_manage: true, can_publish: true },
};

const cde = {
  id: 12,
  name: "Centro de Estudiantes 2026",
  year: 2026,
  summary: "Representación estudiantil.",
  status: "draft",
  active: true,
  members: [
    {
      id: 31,
      member_kind: "student",
      student_id: 7,
      display_name: "Antonia Pérez",
      course: "8° A",
      role_id: 1,
      role_name: "Presidenta",
      section: "leadership",
      sort_order: 1,
      public_name_authorized: true,
    },
  ],
};

const mountedWrappers = [];
const mountManager = (organizationType = "cde") => {
  const wrapper = mount(SiteOrganizationManager, {
    props: { organizationType },
    attachTo: document.body,
    global: {
      stubs: {
        Layout: { template: "<div><slot /></div>" },
        LoadingState: { props: ["message"], template: "<div class='loading-state'>{{ message }}</div>" },
        RouterLink: { props: ["to"], template: "<a :href='to'><slot /></a>" },
      },
    },
  });
  mountedWrappers.push(wrapper);
  return wrapper;
};

describe("Gestión interna de CGPA, CDE y Comité Paritario", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("permissions", JSON.stringify([
      "gestionar_cgpa_sitio",
      "gestionar_cde_sitio",
      "gestionar_comite_paritario_sitio",
    ]));
    vi.clearAllMocks();
    getSiteOrganizationCatalogs.mockResolvedValue({ data: { data: catalogs } });
    listSiteOrganizations.mockResolvedValue({
      data: {
        data: [cde],
        meta: { current_page: 1, last_page: 1, total: 1 },
        summary: { total: 1, published: 0, draft: 1, active: 1 },
      },
    });
    searchOrganizationStudents.mockResolvedValue({
      data: { data: [{ id: 8, name: "Josefa Soto", course: "7° B" }], meta: { current_page: 1, last_page: 1, total: 1 } },
    });
    searchOrganizationStaff.mockResolvedValue({
      data: { data: [{ id: 21, name: "Paula Díaz", position: "Profesora" }], meta: { current_page: 1, last_page: 1, total: 1 } },
    });
    createSiteOrganization.mockResolvedValue({ data: { message: "Versión creada." } });
    createSiteOrganizationRole.mockResolvedValue({ data: { data: { id: 9, name: "Delegada de cultura", section: "leadership" } } });
  });

  afterEach(() => {
    mountedWrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  });

  it("integra tres rutas protegidas y sus iconos dentro de Sitio web", () => {
    const router = readFileSync("resources/js/router/index.js", "utf8");
    const sideNav = readFileSync("resources/js/components/side-nav.vue", "utf8");

    expect(router).toContain('path: "/admin/cgpa"');
    expect(router).toContain('permission: "ver_cgpa_sitio"');
    expect(router).toContain('path: "/admin/cde"');
    expect(router).toContain('permission: "ver_cde_sitio"');
    expect(router).toContain('path: "/admin/comite-paritario"');
    expect(router).toContain('permission: "ver_comite_paritario_sitio"');
    expect(sideNav).toContain('public_site_cgpa: "bx-group"');
    expect(sideNav).toContain('public_site_cde: "bx-user-voice"');
    expect(sideNav).toContain('public_site_joint_committee: "bx-shield-quarter"');
  });

  it("presenta CDE por año con estado, integrantes y vista previa privada", async () => {
    const wrapper = mountManager("cde");
    await flushPromises();

    expect(wrapper.text()).toContain("Centro de Estudiantes");
    expect(wrapper.vm.pickerKindOptions).toEqual(expect.arrayContaining([
      expect.objectContaining({ value: "staff", label: "Docentes asesores" }),
    ]));
    expect(wrapper.text()).toContain("Centro de Estudiantes 2026");
    expect(wrapper.text()).toContain("Borrador");
    expect(wrapper.text()).toContain("1 integrante");

    await wrapper.find('button[aria-label="Vista previa"]').trigger("click");
    expect(wrapper.text()).toContain("Vista pública simulada");
    expect(wrapper.text()).toContain("Antonia Pérez");
    expect(wrapper.text()).toContain("Presidenta");
  });

  it("alterna estudiantes y asesores usando registros reales sin RUT ni contacto", async () => {
    const wrapper = mountManager("cde");
    await flushPromises();
    await wrapper.vm.openCreate();
    await wrapper.vm.openPersonPicker("student");
    await flushPromises();

    expect(searchOrganizationStudents).toHaveBeenCalledWith(expect.objectContaining({ year: 2026 }));
    expect(wrapper.text()).toContain("Josefa Soto");
    expect(wrapper.text()).toContain("7° B");
    expect(wrapper.text()).toContain("No incluye RUT ni datos de contacto");

    await wrapper.vm.changePickerKind("staff");
    await flushPromises();
    expect(searchOrganizationStaff).toHaveBeenCalledWith(expect.objectContaining({ type: "cde" }));
    expect(wrapper.text()).toContain("Paula Díaz");
    expect(wrapper.text()).toContain("Profesora");

    wrapper.vm.selectPerson({ id: 21, name: "Paula Díaz", position: "Profesora" });
    expect(wrapper.vm.form.members[0]).toEqual(expect.objectContaining({
      member_kind: "staff",
      staff_id: 21,
      section: "advisor",
    }));
  });

  it("crea cargos CDE desde la vista y los asigna al integrante", async () => {
    const wrapper = mountManager("cde");
    await flushPromises();
    await wrapper.vm.openCreate();
    wrapper.vm.form.members = [{
      member_kind: "student",
      student_id: 8,
      display_name: "Josefa Soto",
      course: "7° B",
      section: "leadership",
      role_id: 1,
      public_name_authorized: true,
    }];
    wrapper.vm.openRoleCreator(0);
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain("Crear cargo personalizado");
    expect(wrapper.find('button[aria-label="Crear cargo personalizado"]').exists()).toBe(true);
    wrapper.vm.roleCreator.name = "Delegada de cultura";
    await wrapper.vm.saveRole();

    expect(createSiteOrganizationRole).toHaveBeenCalledWith(expect.objectContaining({
      organization_type: "cde",
      name: "Delegada de cultura",
      section: "leadership",
    }));
    expect(wrapper.vm.form.members[0].role_id).toBe(9);
  });

  it("preserva IDs de integrantes CGPA al editar y reordenar la directiva", async () => {
    const wrapper = mountManager("cgpa");
    await flushPromises();
    await wrapper.vm.openCreate();
    Object.assign(wrapper.vm.form, {
      id: 44,
      name: "CGPA 2026",
      year: 2026,
      members: [
        { id: 81, member_kind: "external", display_name: "Primera persona", role_id: 1, section: "leadership", public_name_authorized: true },
        { id: 82, member_kind: "external", display_name: "Segunda persona", role_id: 1, section: "leadership", public_name_authorized: true },
      ],
    });

    wrapper.vm.moveMember(1, -1);
    const payload = wrapper.vm.payload();

    expect(payload.members.map((member) => member.id)).toEqual([82, 81]);
    expect(payload.members.map((member) => member.sort_order)).toEqual([1, 2]);
    expect(payload.members[0]).toEqual(expect.objectContaining({
      display_name: "Segunda persona",
      public_name_authorized: true,
    }));
  });

  it("bloquea publicación sin autorización y guarda IDs reales cuando existe consentimiento", async () => {
    const wrapper = mountManager("cde");
    await flushPromises();
    await wrapper.vm.openCreate();
    Object.assign(wrapper.vm.form, {
      name: "CDE 2027",
      year: 2027,
      status: "published",
      members: [{
        member_kind: "student",
        student_id: 8,
        display_name: "Josefa Soto",
        course: "7° B",
        role_id: 1,
        section: "leadership",
        public_name_authorized: false,
      }],
    });

    await wrapper.vm.save();
    expect(createSiteOrganization).not.toHaveBeenCalled();
    expect(wrapper.vm.errorFor("members")).toContain("autorizar");

    wrapper.vm.form.members[0].public_name_authorized = true;
    await wrapper.vm.save();
    expect(createSiteOrganization).toHaveBeenCalledWith("cde", expect.objectContaining({
      year: 2027,
      status: "published",
      members: [expect.objectContaining({ student_id: 8, public_name_authorized: true })],
    }));
  });

  it("construye Comité Paritario desde funcionarios y campos de la fuente oficial", async () => {
    listSiteOrganizations.mockResolvedValueOnce({ data: { data: [], meta: { total: 0 } } });
    const wrapper = mountManager("joint_committee");
    await flushPromises();
    await wrapper.vm.openCreate();
    await wrapper.vm.openPersonPicker("staff");
    await flushPromises();
    expect(wrapper.vm.form.members).toHaveLength(0);
    expect(wrapper.vm.picker.kind).toBe("staff");
    expect(wrapper.vm.personAlreadySelected({ id: 21 })).toBe(false);
    wrapper.vm.selectPerson({ id: 21, name: "Paula Díaz", position: "Profesora" });
    await wrapper.vm.$nextTick();
    Object.assign(wrapper.vm.form.members[0], {
      representation: "empleador",
      member_role: "titular",
      position_name: "Presidenta",
      public_name_authorized: true,
    });

    const payload = wrapper.vm.payload();
    expect(payload.starts_on).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(payload.members[0]).toEqual(expect.objectContaining({
      member_kind: "staff",
      staff_id: 21,
      representation: "empleador",
      member_role: "titular",
      position_name: "Presidenta",
    }));
    expect(wrapper.text()).toContain("Representación");
    expect(wrapper.text()).toContain("Cargo en directiva");
  });

  it("incluye diseño responsive, accesibilidad y reducción de movimiento", () => {
    const source = readFileSync("resources/js/components/public-site/site-organization-manager.vue", "utf8");

    expect(source).toContain('aria-modal="true"');
    expect(source).toContain('aria-label="Subir integrante"');
    expect(source).toContain("@media(max-width:767.98px)");
    expect(source).toContain("@media(prefers-reduced-motion:reduce)");
    expect(source).toContain("public_name_authorized");
  });

  it("cierra el editor con Escape, libera el scroll y devuelve el foco", async () => {
    const wrapper = mountManager("cgpa");
    await flushPromises();
    const trigger = wrapper.find(".organization-primary-action");
    trigger.element.focus();
    await trigger.trigger("click");
    await flushPromises();

    expect(wrapper.vm.showEditor).toBe(true);
    expect(document.body.style.overflow).toBe("hidden");

    document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.showEditor).toBe(false);
    expect(document.body.style.overflow).toBe("");
    expect(document.activeElement).toBe(trigger.element);
  });
});
