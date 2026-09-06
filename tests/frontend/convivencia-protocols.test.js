// @vitest-environment jsdom
import axios from "axios";
import Swal from "sweetalert2";
import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { readFileSync } from "node:fs";
import path from "node:path";
import ProtocolEditor from "../../resources/js/components/convivencia/protocols/protocol-editor.vue";
import ProtocolActivationWorkspace from "../../resources/js/components/convivencia/protocols/protocol-activation-workspace.vue";
import ProtocolManager from "../../resources/js/components/convivencia/protocols/protocol-manager.vue";
import ProtocolProgressStepper from "../../resources/js/components/convivencia/protocols/protocol-progress-stepper.vue";
import SideNav from "../../resources/js/components/side-nav.vue";
import {
  firstConvivenciaFallbackRoute,
  visibleConvivenciaTabs,
} from "../../resources/js/views/convivencia/tab-access";
import ConvivenciaIndex from "../../resources/js/views/convivencia/index.vue";
import { buildConvivenciaProtocolPdfDefinition, protocolParts as protocolPdfParts } from "../../resources/js/components/convivencia/pdf/convivencia-protocol-pdf";
import { buildConvivenciaActivationPdfDefinition } from "../../resources/js/components/convivencia/pdf/convivencia-activation-pdf";
import { buildConvivenciaPartPdfDefinition } from "../../resources/js/components/convivencia/pdf/convivencia-part-pdf";
import { buildConvivenciaDashboardPdfDefinition, resolveDashboardPeriod } from "../../resources/js/components/convivencia/pdf/convivencia-dashboard-pdf";
import { CONVIVENCIA_DASHBOARD_METRIC_LABELS } from "../../resources/js/components/convivencia/dashboard/dashboard-metrics";
import {
  CONVIVENCIA_COMPLETION_CRITERIA_LABELS,
  CONVIVENCIA_PROTOCOL_CONDITION_LABELS,
  convivenciaProtocolPartCategoryLabel,
  convivenciaProtocolRuntimeLabel,
} from "../../resources/js/components/convivencia/protocol-runtime-labels";

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { name: "LayoutStub", template: "<main><slot /></main>" },
}));

const protocol = {
  id: 4,
  revision: 7,
  code: "RICE-04",
  version_label: "2026.1",
  name: "Protocolo de prueba",
  status: "activo",
  is_sensitive: true,
  metadata: { review_required: true, warnings: ["El plazo indicado debe ser revisado por el equipo directivo."] },
  steps: [{
    id: 10,
    step_order: 1,
    code: "resguardo",
    stage_name: "Resguardo inmediato",
    deadline_value: 2,
    deadline_unit: "hours",
    deadline_anchor: "activation_started",
    can_extend: false,
    deadline_resolution: { fallback_used: true, fallback_reason: "Calendario escolar 2026 no disponible." },
    completion_rule: { summary: "Registrar medidas", all: [{ field: "evidence", operator: "present" }], custom_condition: "rice-source" },
  }],
  part_links: [
    { id: 22, protocol_step_id: 10, step_code: "resguardo", protocol_part_id: 9, is_required: true, condition: { role: "affected", field: "suspected_crime_or_rights_violation", operator: "equals", value: true }, configuration: { evidence: true }, part: { id: 9, category: "protective_measure", code: "MP-01", title: "Separación preventiva" } },
    { id: 23, protocol_part_id: 8, is_required: true, condition: { education_scope: "parvularia" }, configuration: { allow_not_applicable: true }, part: { id: 8, category: "document", code: "BASE", title: "Registro general" } },
  ],
};

const parts = [
  { id: 9, category: "protective_measure", code: "MP-01", title: "Separación preventiva", active: true },
  { id: 8, category: "document", code: "BASE", title: "Registro general", active: true },
];

describe("gestión de protocolos de Convivencia Escolar", () => {
  it("mantiene todos los destinos de Convivencia en un único nivel del sidenav", () => {
    const section = SideNav.methods.convivenciaFallbackSection.call({});

    expect(section.label).toBe("Convivencia Escolar");
    expect(section.subItems.map((item) => item.link)).toEqual([
      "/convivencia",
      "/convivencia/casos",
      "/convivencia/protocolos",
      "/convivencia/entrevistas",
      "/convivencia/medidas",
      "/convivencia/bitacora",
      "/convivencia/planes",
      "/convivencia/sociogramas",
      "/convivencia/idps",
    ]);
    expect(section.subItems.every((item) => item.parentId === section.id)).toBe(true);
    expect(section.subItems.every((item) => !item.subItems?.length)).toBe(true);

    const context = {
      convivenciaFallbackSection: SideNav.methods.convivenciaFallbackSection,
      collectMenuRoutes: SideNav.methods.collectMenuRoutes,
    };
    const normalized = SideNav.methods.normalizeConvivenciaSection.call(context, [{
      id: 91,
      name: "Convivencia Escolar",
      slug: "convivencia",
      subItems: [{
        id: 92,
        label: "Agrupador antiguo",
        subItems: [{ id: 93, label: "Protocolos", link: "/convivencia/protocolos" }],
      }],
    }]);

    expect(normalized[0].subItems).toHaveLength(9);
    expect(normalized[0].subItems.every((item) => item.link && !item.subItems)).toBe(true);
  });

  it("preserva reglas RICE no representadas y aplana vínculos al guardar", () => {
    const wrapper = mount(ProtocolEditor, {
      props: { protocol, parts },
      global: { stubs: { ConvivenciaRemoteSelect: true } },
    });
    const payload = wrapper.vm.buildPayload();
    const stepLink = payload.part_links.find((link) => link.step_code === "resguardo");
    const globalLink = payload.part_links.find((link) => link.protocol_part_id === 8);

    expect(payload.expected_revision).toBe(7);
    expect(payload.steps[0].completion_rule.all).toEqual([{ field: "evidence", operator: "present" }]);
    expect(payload.steps[0].completion_rule.custom_condition).toBe("rice-source");
    expect(payload.steps[0].completion_rule.summary).toBe("Registrar medidas");
    expect(payload.steps[0].completion_rule).not.toHaveProperty("requires_all_parts");
    expect(payload.steps[0].completion_rule).not.toHaveProperty("requires_notes");
    expect(payload.steps[0].completion_rule).not.toHaveProperty("requires_completion_note");
    expect(stepLink).toMatchObject({ step_code: "resguardo", protocol_part_id: 9, is_required: true, condition: { role: "affected", field: "suspected_crime_or_rights_violation", operator: "equals", value: true }, configuration: { evidence: true } });
    expect(globalLink).toMatchObject({ protocol_part_id: 8, is_required: true, condition: { education_scope: "parvularia" }, configuration: { allow_not_applicable: true } });
    expect(globalLink.step_code).toBeUndefined();
    expect(wrapper.text()).toContain("Partes generales del protocolo");
    expect(wrapper.text()).toContain("Registro general");
    expect(wrapper.text()).toContain("Revisión normativa requerida");
    expect(wrapper.text()).toContain("El plazo indicado debe ser revisado");

    wrapper.vm.form.general_part_links[0].condition_json = "{incompleto";
    expect(wrapper.vm.validationErrors).toContain("El vínculo general 1: la condición debe contener un objeto JSON válido.");

    wrapper.vm.linkRemotePart({ value: 125, label: "RICE-MP-125 · Resguardo remoto", secondary: "protective_measure" }, "general");
    expect(wrapper.vm.form.general_part_links).toEqual(expect.arrayContaining([
      expect.objectContaining({ protocol_part_id: 125, protocol_step_id: null }),
    ]));
    expect(wrapper.vm.activeParts).toEqual(expect.arrayContaining([
      expect.objectContaining({ id: 125, code: "RICE-MP-125", title: "Resguardo remoto" }),
    ]));
  });

  it("mantiene completion_rule idéntica en una edición inocua y solo cambia all/any de forma explícita", async () => {
    const originalRule = { all: [{ field: "evidence", operator: "present", options: { source: "rice" } }] };
    const simpleProtocol = {
      ...protocol,
      id: 5,
      metadata: {},
      steps: [{ ...protocol.steps[0], id: 11, completion_rule: originalRule }],
      part_links: [],
    };
    const wrapper = mount(ProtocolEditor, {
      props: { protocol: simpleProtocol, parts },
      global: { stubs: { ConvivenciaRemoteSelect: true } },
    });

    expect(wrapper.vm.buildPayload().steps[0].completion_rule).toEqual(originalRule);
    expect(wrapper.vm.buildPayload().steps[0].completion_rule).not.toHaveProperty("requires_all_parts");
    expect(wrapper.vm.buildPayload().steps[0].completion_rule).not.toHaveProperty("requires_notes");

    wrapper.vm.form.steps[0].completion_any_json = '["acta_firmada", {"field":"notification","operator":"present"}]';
    const editedRule = wrapper.vm.buildPayload().steps[0].completion_rule;
    expect(editedRule.all).toEqual(originalRule.all);
    expect(editedRule.any).toEqual(["acta_firmada", { field: "notification", operator: "present" }]);
    expect(editedRule).not.toHaveProperty("requires_all_parts");
    expect(editedRule).not.toHaveProperty("requires_notes");
    wrapper.vm.section = "steps";
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain("Todas deben cumplirse");
    expect(wrapper.text()).toContain("Al menos una debe cumplirse");
  });

  it("muestra explícitamente Paso N de M y el control de plazo", () => {
    const wrapper = mount(ProtocolProgressStepper, {
      props: {
        steps: [
          { id: 1, step_order: 1, stage_name: "Recepción", status: "completed" },
          { id: 2, step_order: 2, stage_name: "Investigación", status: "in_progress", due_at: "2026-09-05T13:00:00-04:00" },
          { id: 3, step_order: 3, stage_name: "Resolución", status: "pending" },
        ],
        progress: { current_order: 2, total_steps: 3, completed_steps: 1, percentage: 33, label: "Paso 2 de 3", deadline_status: "due_soon", due_at: "2026-09-05T13:00:00-04:00" },
      },
    });

    expect(wrapper.text()).toContain("Paso 2 de 3");
    expect(wrapper.text()).toContain("Investigación");
    expect(wrapper.text()).toContain("Próximo a vencer");
    expect(wrapper.find(".protocol-stepper__percentage").attributes("aria-label")).toContain("33%");
  });

  it("impide No aplica en partes obligatorias incondicionales y conserva excepciones explícitas", () => {
    const methods = ProtocolActivationWorkspace.methods;
    const context = {
      partCondition(part) { return methods.partCondition.call(context, part); },
      partConfiguration(part) { return methods.partConfiguration.call(context, part); },
    };
    const canSkip = (part) => methods.canMarkNotApplicable.call(context, part);

    expect(canSkip({ is_required: true, snapshot: { condition: {}, configuration: {} } })).toBe(false);
    expect(canSkip({ is_required: true, snapshot: { condition: { role: "affected" }, configuration: {} } })).toBe(true);
    expect(canSkip({ is_required: true, snapshot: { configuration: { allow_not_applicable: true } } })).toBe(true);
    expect(canSkip({ is_required: true, snapshot: { configuration: { blocked_for_preschool_child: true } } })).toBe(true);
    expect(canSkip({ is_required: false, snapshot: {} })).toBe(true);
  });

  it("registra explícitamente que una condición no se cumple al marcar No aplica", async () => {
    const part = { id: 201, is_required: true, requires_evidence: false, snapshot: { condition: { role: "affected" }, configuration: {} } };
    const form = {
      revision: 7,
      status: "not_applicable",
      notes: "La persona involucrada no tiene el rol exigido por esta parte.",
      evidence_summary: "",
      outcome: "",
      due_at: "",
      initial_due_at: "",
      data: { condition_confirmed: true },
    };
    const methods = ProtocolActivationWorkspace.methods;
    const put = vi.spyOn(axios, "put").mockResolvedValue({ data: {} });
    const success = vi.spyOn(Swal, "fire").mockResolvedValue({ isConfirmed: true });
    const context = {
      partSavingId: null,
      selectedStepId: 101,
      partForm: () => form,
      canEditPart: () => true,
      partCondition: (target) => methods.partCondition.call(context, target),
      partConfiguration: (target) => methods.partConfiguration.call(context, target),
      canMarkNotApplicable: (target) => methods.canMarkNotApplicable.call(context, target),
      partBlockedForPreschool: (target) => methods.partBlockedForPreschool.call(context, target),
      isRevisionConflict: methods.isRevisionConflict,
      load: vi.fn().mockResolvedValue(),
    };

    methods.onPartStatusChanged.call(context, part);
    expect(form.data).toMatchObject({ condition_confirmed: false, condition_not_met_confirmed: false });
    form.data.condition_not_met_confirmed = true;
    await methods.savePart.call(context, part);

    expect(put).toHaveBeenCalledWith("/api/convivencia/protocol-activation-parts/201", expect.objectContaining({
      status: "not_applicable",
      data: expect.objectContaining({ condition_confirmed: false, condition_not_met_confirmed: true }),
    }));
    put.mockRestore();
    success.mockRestore();
  });

  it("autoriza cada ruta y entrada de menú solo con sus capacidades correspondientes", () => {
    const routerSource = readFileSync(path.resolve(process.cwd(), "resources/js/router/index.js"), "utf8");
    const permissionsDeclaration = routerSource.match(/export const CONVIVENCIA_MODULE_PERMISSIONS = Object\.freeze\(\[([\s\S]*?)\]\);/);
    const granularPermissions = [...(permissionsDeclaration?.[1] || "").matchAll(/"([^"]+)"/g)].map((match) => match[1]);

    expect(granularPermissions).toHaveLength(21);
    expect(granularPermissions).toContain("gestionar_protocolos_convivencia");
    expect(granularPermissions).toContain("ver_dashboard_convivencia");
    expect(granularPermissions[0]).toBe("ver_convivencia");
    expect(routerSource).toContain("const convivenciaRoute = (path, title, permissionsAny)");
    expect(routerSource).toContain("permissionsAny: convivenciaPermissions(permissionsAny)");

    const routePermissions = {
      "/convivencia": ["ver_convivencia", "ver_dashboard_convivencia", "ver_casos_convivencia"],
      "/convivencia/protocolos": ["ver_convivencia", "ver_casos_convivencia", "gestionar_protocolos_convivencia", "activar_protocolos_convivencia"],
      "/convivencia/casos": ["ver_convivencia", "ver_casos_convivencia", "crear_casos_convivencia", "gestionar_denuncias_convivencia", "gestionar_derivaciones_internas_convivencia", "gestionar_derivaciones_externas_convivencia"],
    };
    const context = {
      $router: {
        resolve: (link) => ({ meta: { permissionsAny: routePermissions[link] || [] }, matched: [] }),
      },
    };
    context.filterMenuByPermissions = SideNav.methods.filterMenuByPermissions.bind(context);
    const menu = [
      { id: "dashboard", label: "Panel", link: "/convivencia" },
      { id: "protocols", label: "Protocolos", link: "/convivencia/protocolos" },
      { id: "records", label: "Expedientes", link: "/convivencia/casos" },
    ];

    expect(context.filterMenuByPermissions(menu, ["gestionar_protocolos_convivencia"]).map((item) => item.id)).toEqual(["protocols"]);
    expect(context.filterMenuByPermissions(menu, ["gestionar_derivaciones_externas_convivencia"]).map((item) => item.id)).toEqual(["records"]);
    expect(context.filterMenuByPermissions(menu, ["ver_convivencia"])).toHaveLength(3);
    expect(context.filterMenuByPermissions(menu, [])).toHaveLength(0);
    expect(context.filterMenuByPermissions(menu, ["__superadmin__"])).toHaveLength(3);

    const tabs = [
      { key: "dashboard", route: "/convivencia" },
      { key: "casos", route: "/convivencia/casos" },
      { key: "reportes", route: "/convivencia/reportes" },
    ];
    const visibleTabs = visibleConvivenciaTabs(tabs, { can_manage_external_derivations: true });
    expect(visibleTabs.map((tab) => tab.key)).toEqual(["casos"]);

    expect(firstConvivenciaFallbackRoute("/convivencia/reportes", visibleTabs)).toBe("/convivencia/casos");
    expect(firstConvivenciaFallbackRoute("/convivencia/casos", visibleTabs)).toBeNull();
  });

  it("reinicia cada formulario en su estado real y carga el dashboard al iniciar catálogos", async () => {
    const context = ConvivenciaIndex.data();
    Object.entries(ConvivenciaIndex.methods).forEach(([name, method]) => {
      context[name] = method;
    });
    context.activeTab = "dashboard";
    context.visibleTabs = [{ key: "dashboard", route: "/convivencia" }];
    context.$route = { path: "/convivencia" };
    context.$router = { replace: vi.fn(() => Promise.resolve()) };
    context.inspectorUserId = () => 77;

    const get = vi.spyOn(axios, "get").mockImplementation((url) => {
      if (url === "/api/convivencia/catalogs") {
        return Promise.resolve({ data: {
          active_academic_year_id: 2026,
          users: [],
          capabilities: { can_view_dashboard: true },
        } });
      }
      if (url === "/api/convivencia/dashboard") {
        return Promise.resolve({ data: { metrics: { open_cases: 4 } } });
      }
      return Promise.reject(new Error(`Solicitud inesperada: ${url}`));
    });

    await ConvivenciaIndex.methods.loadCatalogs.call(context);

    expect(get).toHaveBeenCalledWith("/api/convivencia/catalogs");
    expect(get).toHaveBeenCalledWith("/api/convivencia/dashboard", { params: context.dashboard.filters });
    expect(context.dashboard.data).toEqual({ metrics: { open_cases: 4 } });
    expect(context.catalogsLoading).toBe(false);

    const mappings = {
      planes: ["plans", "borrador"],
      casos: ["cases", "abierto"],
      denuncias: ["complaints", "recibida"],
      derivaciones: ["derivations", "ingresada"],
      protocolos: ["protocols", "activo"],
      medidas: ["measures", "asignada"],
      entrevistas: ["interviews", "pendiente", "follow_up_status"],
      bitacora: ["dailyLogs", "registrado"],
      sociogramas: ["sociograms", "borrador"],
    };
    Object.entries(mappings).forEach(([section, [stateKey, expected, field = "status"]]) => {
      context[stateKey].form = { id: 999, [field]: "modificado" };
      context[stateKey].showForm = true;
      expect(ConvivenciaIndex.methods.resetForm.call(context, section)).toBe(true);
      expect(context[stateKey].form[field]).toBe(expected);
      expect(context[stateKey].form.id).toBeNull();
      expect(context[stateKey].showForm).toBe(false);
    });
    expect(context.dailyLogs.form.inspector_user_id).toBe(77);
    get.mockRestore();
  });

  it("envía la revisión al preparar una activación histórica", async () => {
    const post = vi.spyOn(axios, "post").mockResolvedValue({ data: {} });
    const success = vi.spyOn(Swal, "fire").mockResolvedValue({ isConfirmed: true });
    const load = vi.fn().mockResolvedValue();
    const context = {
      canManage: true,
      legacyMode: true,
      materializing: false,
      activationId: 41,
      activation: { id: 41, revision: 7, runtime_materialization_endpoint: "/api/convivencia/protocol-activations/41/materialize-runtime" },
      load,
    };

    await ProtocolActivationWorkspace.methods.materializeRuntime.call(context);

    expect(post).toHaveBeenCalledWith("/api/convivencia/protocol-activations/41/materialize-runtime", { revision: 7 });
    expect(load).toHaveBeenCalled();
    expect(context.materializing).toBe(false);
    post.mockRestore();
    success.mockRestore();
  });

  it("respeta saldo, unidad y autorización al solicitar una prórroga", async () => {
    const computed = ProtocolActivationWorkspace.computed;
    const exhausted = { selectedStep: { extension_value: 5, data: { extension_used_value: 5 } } };
    exhausted.selectedExtensionConfiguredMaximum = computed.selectedExtensionConfiguredMaximum.call(exhausted);
    exhausted.selectedExtensionUsedValue = computed.selectedExtensionUsedValue.call(exhausted);
    expect(computed.selectedExtensionMaximum.call(exhausted)).toBe(0);

    const put = vi.spyOn(axios, "put").mockResolvedValue({ data: {} });
    const success = vi.spyOn(Swal, "fire").mockResolvedValue({ isConfirmed: true });
    const load = vi.fn().mockResolvedValue();
    const context = {
      selectedStep: { id: 101 },
      selectedStepId: 101,
      canEditSelectedStep: true,
      deadlineChanged: false,
      selectedDeadlineCanBeEdited: false,
      selectedExtensionMaximum: 3,
      selectedExtensionUnit: "school_days",
      extensionRequiresApproval: true,
      saving: false,
      stepForm: {
        revision: 7,
        status: "in_progress",
        notes: "",
        outcome: "",
        evidence_summary: "",
        due_at: "2026-09-10T12:00",
        extension_value: 3,
        extension_unit: "school_days",
        extension_approved: true,
        log_notes: "Autorizada por dirección.",
        completion_criteria: { evidence_recorded: true },
        data: {},
      },
      deadlineUnitLabel: ProtocolActivationWorkspace.methods.deadlineUnitLabel,
      isRevisionConflict: ProtocolActivationWorkspace.methods.isRevisionConflict,
      load,
    };

    await ProtocolActivationWorkspace.methods.saveStep.call(context);

    expect(put).toHaveBeenCalledWith("/api/convivencia/protocol-activation-steps/101", expect.objectContaining({
      revision: 7,
      extension_value: 3,
      extension_unit: "school_days",
      extension_approved: true,
      completion_criteria: { evidence_recorded: true },
      log_notes: "Autorizada por dirección.",
    }));
    expect(load).toHaveBeenCalledWith(101);
    put.mockRestore();
    success.mockRestore();
  });

  it("genera definiciones A4 completas para protocolo, activación, parte y panel", () => {
    const protocolPdfFixture = JSON.parse(JSON.stringify(protocol));
    delete protocolPdfFixture.part_links[0].step_code;
    protocolPdfFixture.part_links[0].protocol_step_id = 10;
    protocolPdfFixture.steps[0].part_links = [{
      ...protocolPdfFixture.part_links[0],
      part: protocolPdfFixture.part_links[0].part,
    }];
    const protocolPdf = buildConvivenciaProtocolPdfDefinition({ data: protocolPdfFixture }, "2026-09-04T12:00:00-04:00");
    const activationPdf = buildConvivenciaActivationPdfDefinition({ data: {
      id: 30,
      status: "activo",
      case: { folio: "CONV-CAS-2026-000001-RESERVADO" },
      protocol_snapshot: protocol,
      current_activation_step_id: 101,
      progress: { current_order: 1, total_steps: 1, completed_steps: 0, percentage: 0, label: "Paso 1 de 1", deadline_status: "on_time" },
      runtime_steps: [{
        id: 101,
        step_order: 1,
        stage_name: "Resguardo inmediato",
        status: "in_progress",
        snapshot: { completion_rule: { all: ["report_recorded", "responsible_assigned", "guardians_informed", "evidence_recorded"], any: ["agreement_act_signed", "notification_recorded"] }, deadline_resolution: { fallback_used: true, fallback_reason: "No se encontró calendario lectivo aplicable." } },
        data: { completion_criteria: { evidence_recorded: true, agreement_act_signed: false } },
        parts: [{ id: 201, category: "protective_measure", code: "MP-01", title: "Separación preventiva", status: "pending", is_required: true, snapshot: { condition: { role: "affected" }, configuration: { requires_guardian: true } }, data: { condition_confirmed: true } }],
      }],
      runtime_parts: [{ id: 202, activation_step_id: null, category: "document", code: "BASE", title: "Registro general", status: "completed", is_required: true, snapshot: { condition: { education_scope: "parvularia" }, configuration: { allow_not_applicable: true } }, data: { condition_confirmed: true } }],
    } });
    const partPdf = buildConvivenciaPartPdfDefinition({ data: { id: 9, category: "protective_measure", code: "APELACION_ADULTO", title: "Separación preventiva", population_scope: "Estudiantes afectados", requires_evidence: true, metadata: { deadline_resolution: { fallback_used: true, fallback_reason: "Calendario de días escolares pendiente." } }, links: [{ protocol: { name: "Protocolo de prueba" }, step: { stage_name: "Resguardo inmediato" }, is_required: true, condition: { role: "affected" }, configuration: { requires_guardian: true } }] } });
    const dashboardPayload = { filters: { academic_year_id: 1 }, catalogs: { academic_years: [{ id: 1, name: "Año académico 2026", year: 2026 }] }, data: { metrics: { protocol_compliance_percentage: 82, overdue_protocol_steps: 2 }, charts: { activations_by_protocol: [{ label: "Protocolo protective_measure", total: 4 }], parts_by_category: [{ label: "protective_measure", total: 8 }, { category: "other", total: 2 }], bottlenecks: [{ label: "Investigación", total: 3 }] }, alerts: [{ title: "Dos etapas vencidas", severity: "high" }], recent: { cases: [{ folio: "CONV-CAS-2026-000001", status: "open" }] } } };
    const dashboardPdf = buildConvivenciaDashboardPdfDefinition(dashboardPayload);

    for (const definition of [protocolPdf, activationPdf, partPdf, dashboardPdf]) expect(definition.pageSize).toBe("A4");
    expect(protocolPdf.watermark.text).toBe("CONFIDENCIAL");
    const protectivePart = protocolPdfParts(protocolPdfFixture, protocolPdfFixture.steps).find((part) => part.id === 9);
    expect(protectivePart.relation_audit).toHaveLength(1);
    expect(protectivePart.relation_audit[0]).toMatchObject({ step: "Resguardo inmediato", required: true });
    expect(protectivePart.relation_audit.some((relation) => relation.step === "General")).toBe(false);
    expect(JSON.stringify(protocolPdf.content)).toContain("El plazo indicado debe ser revisado");
    expect(JSON.stringify(protocolPdf.content)).toContain("Obligatoriedad en vínculos");
    expect(JSON.stringify(protocolPdf.content)).toContain("Condiciones por vínculo");
    expect(JSON.stringify(protocolPdf.content)).toContain("Configuración por vínculo");
    expect(JSON.stringify(protocolPdf.content)).toContain("Rol: Persona afectada");
    expect(JSON.stringify(protocolPdf.content)).toContain("Campo: Sospecha de delito o vulneración de derechos");
    expect(JSON.stringify(protocolPdf.content)).toContain("Operador: Es igual a");
    expect(JSON.stringify(protocolPdf.content)).not.toContain("Suspected Crime Or Rights Violation");
    expect(JSON.stringify(protocolPdf.content)).toContain("fecha alternativa no constituye un vencimiento confirmado");
    expect(JSON.stringify(protocolPdf.content)).toContain("Calendario escolar 2026 no disponible");
    expect(JSON.stringify(activationPdf.content)).toContain("Paso 1 de 1");
    expect(JSON.stringify(activationPdf.content)).toContain("REQUISITOS GENERALES DEL PROTOCOLO");
    expect(JSON.stringify(activationPdf.content)).toContain("Registro general");
    expect(JSON.stringify(activationPdf.content)).toContain("Regla de término");
    expect(JSON.stringify(activationPdf.content)).toContain("Criterios de término registrados");
    expect(JSON.stringify(activationPdf.content)).toContain("Evidencia registrada: Sí");
    expect(JSON.stringify(activationPdf.content)).toContain("Informe registrado");
    expect(JSON.stringify(activationPdf.content)).toContain("Responsable asignado");
    expect(JSON.stringify(activationPdf.content)).toContain("Apoderados informados");
    expect(JSON.stringify(activationPdf.content)).not.toContain("responsible_assigned");
    expect(JSON.stringify(activationPdf.content)).toContain("Configuración operativa");
    expect(JSON.stringify(activationPdf.content)).toContain("Condición confirmada");
    expect(JSON.stringify(activationPdf.content)).toContain("Plazo no confirmado");
    expect(JSON.stringify(activationPdf.content)).toContain("No se encontró calendario lectivo aplicable");
    expect(JSON.stringify(partPdf.content)).toContain("Estudiantes afectados");
    expect(JSON.stringify(partPdf.content)).toContain("Protocolo de prueba");
    expect(JSON.stringify(partPdf.content)).toContain("Condición de aplicación");
    expect(JSON.stringify(partPdf.content)).toContain("Configuración operativa");
    expect(JSON.stringify(partPdf.content)).toContain("Requiere apoderado: Sí");
    expect(JSON.stringify(partPdf.content)).toContain("Calendario de días escolares pendiente");
    expect(JSON.stringify(dashboardPdf.content)).toContain("ACTIVACIONES POR PROTOCOLO");
    expect(JSON.stringify(dashboardPdf.content)).toContain("CUELLOS DE BOTELLA");
    expect(JSON.stringify(dashboardPdf.content)).toContain("Medida protectora");
    expect(JSON.stringify(dashboardPdf.content)).toContain("Otra parte");
    expect(JSON.stringify(dashboardPdf.content)).toContain("Protocolo protective_measure");
    expect(JSON.stringify(dashboardPdf.content)).toContain("Abierto");
    expect(JSON.stringify(dashboardPdf.content)).toContain("Alta");
    expect(JSON.stringify(dashboardPdf.content)).not.toContain('"text":"Open"');
    expect(resolveDashboardPeriod(dashboardPayload)).toBe("2026");
    expect(dashboardPdf.content[0].table.body[0][1].stack[0].text).toBe("PERIODO-2026");
    expect(dashboardPdf.content.some((item) => item?.unbreakable === false)).toBe(false);
    expect(dashboardPdf.content.some((item) => item?.style === "notice")).toBe(false);

    for (const definition of [activationPdf, partPdf]) {
      const cover = definition.content[0].table;
      expect(cover.widths[1]).toBeGreaterThanOrEqual(116);
      expect(cover.body[0][1].stack[0]).toMatchObject({ fontSize: 8, noWrap: true });
    }
    expect(activationPdf.content[0].table.widths[1]).toBeGreaterThan(116);
    expect(activationPdf.footer(1, 5).columns[0].text).toContain("Documento reservado");
  });

  it("traduce categorías y criterios RICE sin alterar sus claves de contrato", () => {
    expect(convivenciaProtocolPartCategoryLabel("protective_measure")).toBe("Medida protectora");
    expect(convivenciaProtocolPartCategoryLabel("formative_measure")).toBe("Medida formativa");
    expect(convivenciaProtocolPartCategoryLabel("other")).toBe("Otra parte");
    expect(convivenciaProtocolPartCategoryLabel("Categoría institucional")).toBe("Categoría institucional");

    const expected = {
      responsible_assigned: "Responsable asignado",
      guardians_informed: "Apoderados informados",
      report_recorded: "Informe registrado",
      initial_risk_assessed: "Riesgo inicial evaluado",
      minimum_diligences_completed: "Diligencias mínimas completadas",
    };
    Object.entries(expected).forEach(([key, label]) => {
      expect(CONVIVENCIA_COMPLETION_CRITERIA_LABELS[key]).toBe(label);
      expect(convivenciaProtocolRuntimeLabel(key)).toBe(label);
      expect(ProtocolActivationWorkspace.methods.humanizeToken(key)).toBe(label);
    });

    expect(CONVIVENCIA_PROTOCOL_CONDITION_LABELS.suspected_crime_or_rights_violation).toBe("Sospecha de delito o vulneración de derechos");
    const workspaceMethods = ProtocolActivationWorkspace.methods;
    const workspaceContext = {
      humanizeToken: workspaceMethods.humanizeToken,
      describeStructuredValue: workspaceMethods.describeStructuredValue,
    };
    expect(workspaceMethods.describeStructuredValue.call(workspaceContext, {
      field: "suspected_crime_or_rights_violation",
      operator: "equals",
      value: true,
    })).toBe("Campo: Sospecha de delito o vulneración de derechos · Operador: Es igual a · Valor: Sí");

    const dashboardSource = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/dashboard/convivencia-dashboard.vue"), "utf8");
    expect(dashboardSource).toContain("donutOptions(partsByCategory, true)");
  });

  it("presenta en español todas las métricas entregadas por el dashboard", () => {
    const backendMetricKeys = [
      "open_cases", "closed_cases", "internal_derivations_pending", "external_derivations_pending",
      "pending_measures", "interviews_done", "complaints_received", "active_protocols", "daily_events",
      "overdue_followups", "overdue_protocol_steps", "due_soon_protocol_steps", "overdue_protocol_parts",
      "due_soon_protocol_parts", "protocol_compliance_percentage", "on_time_protocol_steps",
      "comparable_protocol_steps", "average_protocol_closure_hours", "median_protocol_closure_hours",
      "protocol_closure_sample_size",
    ];

    backendMetricKeys.forEach((key) => {
      expect(CONVIVENCIA_DASHBOARD_METRIC_LABELS[key], key).toEqual(expect.any(String));
      expect(CONVIVENCIA_DASHBOARD_METRIC_LABELS[key].trim().length, key).toBeGreaterThan(0);
    });
    expect(CONVIVENCIA_DASHBOARD_METRIC_LABELS.overdue_protocol_parts).toBe("Requisitos de protocolo vencidos");
    expect(CONVIVENCIA_DASHBOARD_METRIC_LABELS.average_protocol_closure_hours).toBe("Promedio de cierre de protocolos (horas)");
  });

  it("mantiene endpoints, edición desde show y RBAC explícito de exportación", () => {
    const manager = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/protocols/protocol-manager.vue"), "utf8");
    const workspace = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/protocols/protocol-activation-workspace.vue"), "utf8");
    const library = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/protocols/protocol-part-library.vue"), "utf8");

    expect(manager).toContain("/api/convivencia/protocols/${id}");
    expect(manager).toContain("/api/convivencia/protocol-parts");
    expect(manager).toContain("this.capabilities.can_export_reports === true");
    expect(ProtocolManager.computed.canActivate.call({ capabilities: { can_manage_protocols: true } })).toBe(false);
    expect(ProtocolManager.computed.canActivate.call({ capabilities: { can_activate_protocols: true } })).toBe(true);
    expect(library).toContain("this.capabilities.can_export_reports === true");
    expect(library).toContain("{{ usageCount(part) }} vinculación(es)");
    expect(library).toContain("Esta parte tiene ${count} vinculación(es) con protocolos");
    expect(library).not.toContain("{{ usageCount(part) }} protocolo(s)");
    expect(workspace).toContain("/api/convivencia/protocol-activation-steps/${this.selectedStep.id}/complete");
    expect(workspace).toContain("/api/convivencia/protocol-activation-parts/${part.id}");
    expect(workspace).toContain("revision: this.activation.revision || null");
    expect(workspace).toContain("runtime_parts");
    expect(workspace).toContain("Partes de la etapa y requisitos generales");
    expect(workspace).toContain("condition_confirmed");
    expect(workspace).toContain("blocked_for_preschool_child");
    expect(workspace).toContain('v-if="canMarkNotApplicable(part)"');
    expect(workspace).toContain("Una parte obligatoria sin condición ni excepción configurada no puede marcarse como no aplicable");
    expect(workspace).toContain("Deben cumplirse todas");
    expect(workspace).toContain("Debe cumplirse al menos una");
    expect(workspace).toContain("completion_criteria");
    expect(workspace).toContain("Preparar ruta histórica");
    expect(workspace).toContain("runtime_materialization_endpoint");
    expect(workspace).toContain('v-if="canEditPart(part)"');
    expect(workspace).toContain("Justificación del cambio de plazo o prórroga");
    expect(workspace).toContain("Plazo estimado por falta de calendario escolar");
    expect(workspace).toContain("extension_approved");
    expect(workspace).toContain("Saldo disponible: máximo");
    expect(workspace).toContain("selectedExtensionMaximum === 0");
    expect(workspace).toContain("Fundamenta el cambio de plazo en las notas del requisito");
    expect(workspace).toContain("if (this.isRevisionConflict(error)) await this.load");
  });
});
