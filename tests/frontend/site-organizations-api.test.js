// @vitest-environment jsdom

import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  archiveSiteOrganization,
  createSiteOrganization,
  createSiteOrganizationRole,
  getSiteOrganizationCatalogs,
  listSiteOrganizations,
  searchOrganizationStaff,
  searchOrganizationStudents,
  updateSiteOrganization,
} from "../../resources/js/services/site-organizations-api";

vi.mock("axios", () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

describe("API de organizaciones del sitio web", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    axios.get.mockResolvedValue({ data: {} });
    axios.post.mockResolvedValue({ data: {} });
    axios.put.mockResolvedValue({ data: {} });
    axios.delete.mockResolvedValue({ data: {} });
  });

  it("lista por tipo sin enviar filtros vacíos", async () => {
    await listSiteOrganizations("cde", { page: 2, year: 2026, status: "", search: null });

    expect(axios.get).toHaveBeenCalledWith("/api/admin/site-organizations", {
      params: { type: "cde", page: 2, year: 2026 },
    });
  });

  it("crea en la colección base e incluye el tipo en el payload", async () => {
    await createSiteOrganization("cgpa", {
      name: "CGPA 2026",
      year: 2026,
      status: "draft",
      members: [],
    });

    expect(axios.post).toHaveBeenCalledWith("/api/admin/site-organizations", expect.objectContaining({
      type: "cgpa",
      name: "CGPA 2026",
      year: 2026,
    }));
  });

  it("usa rutas tipadas para edición y archivo", async () => {
    await updateSiteOrganization("joint_committee", 9, { status: "published" });
    await archiveSiteOrganization("joint_committee", 9);

    expect(axios.put).toHaveBeenCalledWith(
      "/api/admin/site-organizations/joint_committee/9",
      { status: "published" },
    );
    expect(axios.delete).toHaveBeenCalledWith("/api/admin/site-organizations/joint_committee/9");
  });

  it("consulta catálogos y búsquedas institucionales sin RUT ni contactos", async () => {
    await getSiteOrganizationCatalogs("cde", { year: 2026 });
    await searchOrganizationStudents({ year: 2026, search: "maría", page: 1, rut: "ignorar" });
    await searchOrganizationStaff({ type: "joint_committee", search: "ana", page: 2, email: "ignorar" });

    expect(axios.get).toHaveBeenNthCalledWith(1, "/api/admin/site-organizations/catalogs", {
      params: { type: "cde", year: 2026 },
    });
    expect(axios.get).toHaveBeenNthCalledWith(2, "/api/admin/site-organizations/students", {
      params: { year: 2026, search: "maría", page: 1 },
    });
    expect(axios.get).toHaveBeenNthCalledWith(3, "/api/admin/site-organizations/staff", {
      params: { type: "joint_committee", search: "ana", page: 2 },
    });
  });

  it("crea cargos administrables en el catálogo de la organización", async () => {
    await createSiteOrganizationRole({
      organization_type: "cde",
      name: "Delegada de cultura",
      section: "leadership",
      sort_order: 4,
      active: true,
    });

    expect(axios.post).toHaveBeenCalledWith("/api/admin/site-organizations/roles", expect.objectContaining({
      organization_type: "cde",
      name: "Delegada de cultura",
    }));
  });
});
