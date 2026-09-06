// @vitest-environment jsdom
import { nextTick } from "vue";
import { flushPromises, mount, shallowMount } from "@vue/test-utils";
import axios from "axios";
import Swal from "sweetalert2";
import { afterEach, describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";
import path from "node:path";
import ConvivenciaIndex from "../../resources/js/views/convivencia/index.vue";
import ConvivenciaIdpsWorkspace from "../../resources/js/components/convivencia/idps/convivencia-idps-workspace.vue";
import ConvivenciaOperationalSection from "../../resources/js/components/convivencia/operations/convivencia-operational-section.vue";
import ProtocolManager from "../../resources/js/components/convivencia/protocols/protocol-manager.vue";
import ProtocolPartLibrary from "../../resources/js/components/convivencia/protocols/protocol-part-library.vue";
import ConvivenciaDataTable from "../../resources/js/components/convivencia/ui/convivencia-data-table.vue";
import ConvivenciaFormModal from "../../resources/js/components/convivencia/ui/convivencia-form-modal.vue";
import ConvivenciaRemoteSelect from "../../resources/js/components/convivencia/ui/convivencia-remote-select.vue";
import ConvivenciaRecordForm from "../../resources/js/components/convivencia/forms/convivencia-record-form.vue";
import ConvivenciaCaseManagementWorkspace from "../../resources/js/components/convivencia/cases/convivencia-case-management-workspace.vue";
import Multiselect from "@vueform/multiselect";
import {
  confirmConvivenciaAction,
  showConvivenciaError,
  showConvivenciaSuccess,
} from "../../resources/js/components/convivencia/module-utils";

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { name: "LayoutStub", template: "<main><slot /></main>" },
}));

afterEach(() => {
  document.body.classList.remove("convivencia-modal-open");
  document.body.innerHTML = "";
});

describe("rediseño transversal de Convivencia Escolar", () => {
  it("bloquea el fondo, contiene el foco, cierra con Escape y devuelve el foco", async () => {
    const trigger = document.createElement("button");
    trigger.textContent = "Abrir gestión";
    document.body.appendChild(trigger);
    trigger.focus();

    const wrapper = mount(ConvivenciaFormModal, {
      attachTo: document.body,
      props: { modelValue: false, title: "Editar caso" },
      slots: {
        default: "<input data-testid='first-field' autofocus><button type='button' data-testid='last-field'>Última acción</button>",
      },
    });

    await wrapper.setProps({ modelValue: true });
    await nextTick();
    const dialog = document.body.querySelector("[role='dialog']");
    const focusable = Array.from(dialog.querySelectorAll("button:not([disabled]), input:not([disabled])"));
    focusable.forEach((element) => Object.defineProperty(element, "offsetParent", { configurable: true, get: () => document.body }));

    expect(document.body.classList.contains("convivencia-modal-open")).toBe(true);
    expect(dialog.getAttribute("aria-modal")).toBe("true");
    expect(dialog.contains(document.activeElement)).toBe(true);

    focusable.at(-1).focus();
    dialog.dispatchEvent(new KeyboardEvent("keydown", { key: "Tab", bubbles: true, cancelable: true }));
    expect(document.activeElement).toBe(focusable[0]);

    focusable[0].focus();
    dialog.dispatchEvent(new KeyboardEvent("keydown", { key: "Tab", shiftKey: true, bubbles: true, cancelable: true }));
    expect(document.activeElement).toBe(focusable.at(-1));

    dialog.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true, cancelable: true }));
    expect(wrapper.emitted("update:modelValue")?.at(-1)).toEqual([false]);
    expect(wrapper.emitted("close")).toHaveLength(1);

    await wrapper.setProps({ modelValue: false });
    wrapper.vm.afterLeave();
    await nextTick();
    expect(document.body.classList.contains("convivencia-modal-open")).toBe(false);
    expect(document.activeElement).toBe(trigger);

    wrapper.unmount();
    trigger.remove();
  });

  it("mantiene el bloqueo mientras exista otro modal abierto", async () => {
    const first = mount(ConvivenciaFormModal, { props: { modelValue: true, title: "Primer modal" } });
    const second = mount(ConvivenciaFormModal, { props: { modelValue: true, title: "Segundo modal" } });
    await nextTick();

    expect(document.body.classList.contains("convivencia-modal-open")).toBe(true);
    first.unmount();
    expect(document.body.classList.contains("convivencia-modal-open")).toBe(true);
    second.unmount();
    expect(document.body.classList.contains("convivencia-modal-open")).toBe(false);
  });

  it("permite cerrar primero un selector remoto con Escape sin cerrar el formulario", async () => {
    const wrapper = mount(ConvivenciaFormModal, {
      attachTo: document.body,
      props: { modelValue: true, title: "Abrir caso" },
      slots: { default: "<div class='multiselect is-active'><input data-testid='remote-search'></div>" },
    });
    await nextTick();

    const search = document.body.querySelector("[data-testid='remote-search']");
    search.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true, cancelable: true }));
    await nextTick();
    expect(wrapper.emitted("close")).toBeUndefined();

    search.closest(".multiselect").classList.remove("is-active");
    search.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true, cancelable: true }));
    await nextTick();
    expect(wrapper.emitted("close")).toHaveLength(1);
    wrapper.unmount();
  });

  it("presenta la tabla general con semántica móvil y estados de carga", async () => {
    const wrapper = mount(ConvivenciaDataTable, {
      props: { title: "Casos de convivencia", subtitle: "Expedientes visibles", count: 2 },
      slots: {
        default: "<table><thead><tr><th>Folio</th></tr></thead><tbody><tr><td data-label='Folio'>CE-001</td></tr></tbody></table>",
      },
    });

    expect(wrapper.get(".convivencia-data-table").attributes("aria-busy")).toBe("false");
    expect(wrapper.text()).toContain("2 registros");
    expect(wrapper.get("table").exists()).toBe(true);
    expect(wrapper.get("td").attributes("data-label")).toBe("Folio");

    await wrapper.setProps({ loading: true });
    expect(wrapper.get("[role='status']").text()).toContain("Cargando información");
    expect(wrapper.find("table").exists()).toBe(false);
  });

  it("anula ancho y mínimo de la tabla al convertir filas en tarjetas móviles", () => {
    const source = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/ui/convivencia-data-table.vue"), "utf8");
    const mobileStyles = source.slice(source.indexOf("@media(max-width:767.98px)"));

    expect(mobileStyles).toContain(".convivencia-data-table__scroll :deep(table){display:block;width:100%!important;min-width:0!important;max-width:100%}");
  });

  it("renderiza en español las unidades reglamentarias de las partes", () => {
    const wrapper = mount(ProtocolPartLibrary, {
      props: {
        parts: [
          { id: 1, code: "PL-01", title: "Plazo hábil", category: "action", deadline_value: 5, deadline_unit: "business_days", active: true },
          { id: 2, code: "PL-02", title: "Plazo externo", category: "external_report", deadline_value: 1, deadline_unit: "external_defined", active: true },
        ],
        pagination: { current_page: 1, last_page: 1, total: 2 },
      },
    });

    expect(wrapper.text()).toContain("5 días hábiles");
    expect(wrapper.text()).toContain("1 definido por organismo");
    expect(wrapper.text()).not.toContain("Business Days");
    expect(wrapper.text()).not.toContain("External Defined");
  });

  it("emite páginas anterior y siguiente desde una tabla operacional", async () => {
    const wrapper = mount(ConvivenciaOperationalSection, {
      props: {
        section: "derivaciones",
        state: {
          loading: false,
          filters: {},
          pagination: { current_page: 2, last_page: 3, total: 25 },
          items: [{
            id: 8,
            scope: "internal",
            derived_at: "2026-09-04T10:30:00-04:00",
            destination_label: "Orientación",
            priority_level: "media",
            status: "ingresada",
          }],
        },
        canCreate: true,
        actionProvider: () => [{ key: "edit", label: "Editar", icon: "bx-edit-alt" }],
      },
      global: {
        stubs: {
          BBadge: { template: "<span><slot /></span>" },
        },
      },
    });

    const pagerButtons = wrapper.findAll(".convivencia-operational-section__pagination button");
    expect(pagerButtons).toHaveLength(2);
    expect(wrapper.text()).toContain("Página 2 de 3");
    expect(wrapper.text()).toContain("Editar");
    expect(wrapper.get("td[data-label='Acciones']").exists()).toBe(true);

    await pagerButtons[0].trigger("click");
    await pagerButtons[1].trigger("click");
    expect(wrapper.emitted("page")).toEqual([[1], [3]]);
  });

  it("reúne casos, denuncias y derivaciones en un único espacio operativo", async () => {
    const state = (total) => ({ loading: false, filters: {}, items: [], pagination: { current_page: 1, last_page: 1, total } });
    const wrapper = shallowMount(ConvivenciaCaseManagementWorkspace, {
      props: {
        selectedType: "casos",
        states: { cases: state(8), complaints: state(3), derivations: state(2) },
        capabilities: {
          can_view_cases: true,
          can_manage_complaints: true,
          can_manage_internal_derivations: true,
        },
        canCreateProvider: () => true,
        actionProvider: () => [],
      },
    });

    expect(wrapper.findAll("[role='tab']")).toHaveLength(3);
    expect(wrapper.text()).toContain("Expedientes de convivencia");
    expect(wrapper.text()).toContain("un único formulario guiado");
    await wrapper.findAll("[role='tab']")[1].trigger("click");
    expect(wrapper.emitted("update:selectedType")?.at(-1)).toEqual(["denuncias"]);
  });

  it("normaliza la edición IDPS, filtra instrumentos y envía el formulario por evento", async () => {
    const state = {
      loading: false,
      saving: false,
      modal: null,
      overview: { results: { current_page: 1, last_page: 1, total: 1 } },
      periodForm: {},
      dimensionForm: {},
      instrumentForm: {},
      resultForm: {},
    };
    const dimensions = [
      { id: 1, code: "CLIMA", name: "Clima escolar", instruments: [{ id: 11, dimension_id: 1, name: "Encuesta clima" }] },
      { id: 2, code: "PART", name: "Participación", instruments: [{ id: 21, dimension_id: 2, name: "Encuesta participación" }] },
    ];
    const wrapper = shallowMount(ConvivenciaIdpsWorkspace, {
      props: {
        state,
        dimensions,
        periods: [{ id: 4, name: "Diagnóstico" }],
        results: [],
        catalogs: { active_academic_year_id: 90, academic_years: [{ id: 90, name: "2026" }] },
        canManage: true,
      },
      global: {
        stubs: {
          BFormSelect: true,
          BFormInput: true,
          BFormTextarea: true,
          BFormCheckbox: true,
        },
      },
    });

    wrapper.vm.open("result", {
      id: 77,
      period: { id: 4 },
      dimension: { id: 2 },
      instrument: { id: 21 },
      academicYear: { id: 90 },
      course_section: { id: 31 },
      educationLevel: { id: 6 },
      related_plan: { id: 12 },
      result_scope: "curso",
      reference_label: "8° A",
    });
    await nextTick();

    expect(state.modal).toBe("result");
    expect(state.resultForm).toMatchObject({
      id: 77,
      period_id: 4,
      dimension_id: 2,
      instrument_id: 21,
      academic_year_id: 90,
      course_section_id: 31,
      education_level_id: 6,
      related_plan_id: 12,
    });
    expect(wrapper.vm.instrumentOptions).toEqual([{ value: 21, text: "Encuesta participación" }]);

    wrapper.vm.state.resultForm.dimension_id = 1;
    wrapper.vm.onResultDimensionChanged();
    await nextTick();
    expect(state.resultForm.instrument_id).toBeNull();
    expect(wrapper.vm.instrumentOptions).toEqual([{ value: 11, text: "Encuesta clima" }]);

    wrapper.findComponent(ConvivenciaFormModal).vm.$emit("submit");
    await nextTick();
    expect(wrapper.emitted("submit")?.at(-1)).toEqual(["result"]);
  });

  it("actualiza por PUT el registro IDPS editado y conserva la página al recargar", async () => {
    const put = vi.spyOn(axios, "put").mockResolvedValue({ data: {} });
    vi.spyOn(Swal, "fire").mockResolvedValue({ isConfirmed: true });
    const context = {
      idps: {
        saving: false,
        modal: "result",
        resultForm: { id: 77, period_id: 4, dimension_id: 2, instrument_id: 21 },
        overview: { results: { current_page: 3 } },
      },
      loadIdps: vi.fn().mockResolvedValue(),
    };

    await ConvivenciaIndex.methods.saveIdpsRecord.call(context, "result");

    expect(put).toHaveBeenCalledWith("/api/convivencia/idps/results/77", expect.objectContaining({ id: 77, instrument_id: 21 }));
    expect(context.idps.modal).toBeNull();
    expect(context.loadIdps).toHaveBeenCalledWith(3);
    expect(context.idps.saving).toBe(false);
  });

  it("solo ofrece convertir denuncias y derivaciones cuando también puede crear casos", () => {
    const methods = ConvivenciaIndex.methods;
    const contextFor = (capabilities) => {
      const context = { catalogs: { capabilities } };
      context.canCreateSection = (section) => methods.canCreateSection.call(context, section);
      context.canEditSection = (section) => methods.canEditSection.call(context, section);
      context.canEditItem = (section, item) => methods.canEditItem.call(context, section, item);
      return context;
    };
    const complaintOnly = contextFor({ can_manage_complaints: true, can_create_cases: false });
    const authorized = contextFor({ can_manage_complaints: true, can_create_cases: true });
    const derivationAuthorized = contextFor({ can_manage_external_derivations: true, can_create_cases: true });

    expect(methods.operationalActions.call(complaintOnly, "denuncias", { id: 9 }).map((action) => action.key)).not.toContain("convert");
    expect(methods.operationalActions.call(authorized, "denuncias", { id: 9 }).map((action) => action.key)).toContain("convert");
    expect(methods.operationalActions.call(derivationAuthorized, "derivaciones", { id: 12, scope: "external" }).map((action) => action.key)).toContain("convert");
    expect(methods.operationalActions.call(derivationAuthorized, "derivaciones", { id: 12, scope: "external", case_id: 55 }).map((action) => action.key)).not.toContain("convert");

    const linkedVisible = contextFor({ can_manage_external_derivations: true, can_create_cases: true, can_view_cases: true });
    expect(methods.operationalActions.call(linkedVisible, "derivaciones", { id: 12, scope: "external", case_id: 55 })).toEqual(expect.arrayContaining([
      expect.objectContaining({ key: "view-linked-case", label: "Ver caso" }),
    ]));
  });

  it("convierte denuncia, derivación y bitácora sin listar casos si el rol solo puede crearlos", async () => {
    vi.spyOn(Swal, "fire").mockResolvedValue({ isConfirmed: true });
    const post = vi.spyOn(axios, "post").mockResolvedValue({ status: 200, data: {} });
    const get = vi.spyOn(axios, "get").mockResolvedValue({ data: { data: [] } });
    const loadCases = () => axios.get("/api/convivencia/cases");
    const loadComplaints = vi.fn();
    const loadDerivations = vi.fn();
    const loadDailyLogs = vi.fn();
    const base = {
      catalogs: {
        users: [{ id: 25, staff_id: null }],
        capabilities: {
          can_manage_complaints: true,
          can_create_cases: true,
          can_view_cases: false,
        },
      },
      classificationOptions: [{ value: 1 }, { value: 3 }],
      criticalityOptions: [{ value: 2 }, { value: 4 }],
      loadCases,
      loadComplaints,
      loadDerivations,
    };

    await ConvivenciaIndex.methods.submitCaseConversion.call({
      ...base,
      caseConversion: { show: true, saving: false, sourceType: "denuncias", source: { id: 15 }, form: { classification_item_id: 1, criticality_item_id: 2, responsible_user_id: 25 } },
    });
    await ConvivenciaIndex.methods.submitCaseConversion.call({
      ...base,
      caseConversion: { show: true, saving: false, sourceType: "derivaciones", source: { id: 19 }, form: { classification_item_id: 1, criticality_item_id: 2, responsible_user_id: 25 } },
    });
    await ConvivenciaIndex.methods.convertDailyLogToCase.call({
      ...base,
      loadDailyLogs,
      inspectorUserId: () => 25,
    }, { id: 16 });

    expect(post).toHaveBeenCalledWith("/api/convivencia/complaints/15/convert-to-case", expect.any(Object));
    expect(post).toHaveBeenCalledWith("/api/convivencia/derivations/19/convert-to-case", expect.any(Object));
    expect(post).toHaveBeenCalledWith("/api/convivencia/daily-logs/16/convert-to-case", expect.any(Object));
    expect(loadComplaints).toHaveBeenCalledOnce();
    expect(loadDerivations).toHaveBeenCalledOnce();
    expect(loadDailyLogs).toHaveBeenCalledOnce();
    expect(get).not.toHaveBeenCalledWith("/api/convivencia/cases");
  });

  it("crea un caso, cierra el formulario y omite GET cases si el rol no puede listarlos", async () => {
    const post = vi.spyOn(axios, "post").mockResolvedValue({ status: 201, data: { data: { id: 91 } } });
    const get = vi.spyOn(axios, "get").mockResolvedValue({ data: { data: [] } });
    vi.spyOn(Swal, "fire").mockResolvedValue({ isConfirmed: true });
    const resetForm = vi.fn();
    const context = {
      cases: { saving: false, form: { id: null, initial_report: "Caso creado por un rol de apertura." } },
      catalogs: { capabilities: { can_create_cases: true, can_view_cases: false } },
      resetForm,
    };
    const reload = () => axios.get("/api/convivencia/cases");
    context.loadCases = reload;
    context.saveResource = (...args) => ConvivenciaIndex.methods.saveResource.call(context, ...args);

    await ConvivenciaIndex.methods.saveCases.call(context);

    expect(post).toHaveBeenCalledWith("/api/convivencia/cases", expect.objectContaining({ initial_report: expect.any(String) }));
    expect(resetForm).toHaveBeenCalledWith("casos");
    expect(get).not.toHaveBeenCalledWith("/api/convivencia/cases");
    expect(context.cases.saving).toBe(false);
  });

  it("mantiene el seguimiento de caso en solo lectura sin permiso de edición", async () => {
    const post = vi.spyOn(axios, "post").mockResolvedValue({ status: 201, data: {} });
    const context = {
      catalogs: { capabilities: { can_view_cases: true, can_edit_cases: false } },
      caseDetail: { id: 44 },
      caseFollowUpSaving: false,
      caseFollowUpForm: { follow_up_at: "2026-09-05T10:00", title: "Revisión", notes: "Notas", next_follow_up_at: "" },
    };

    await ConvivenciaIndex.methods.saveCaseFollowUp.call(context);

    expect(post).not.toHaveBeenCalled();
    expect(context.caseFollowUpSaving).toBe(false);
    const viewData = ConvivenciaIndex.data();
    viewData.catalogs.capabilities = { can_view_cases: true, can_edit_cases: false };
    viewData.caseDetail = { id: 44, folio: "CAS-044", follow_ups: [], status_logs: [] };
    viewData.caseDetailModal = true;
    const ViewOnlyIndex = { ...ConvivenciaIndex, data: () => viewData, mounted() {} };
    const wrapper = shallowMount(ViewOnlyIndex, {
      global: {
        mocks: {
          $route: { path: "/convivencia/casos" },
          $router: { push: vi.fn(), replace: vi.fn() },
        },
        stubs: {
          Layout: { template: "<main><slot /></main>" },
          BModal: { template: "<section><slot /></section>" },
          BCard: { template: "<article><slot /></article>" },
          BFormInput: true,
          BFormTextarea: true,
          BButton: true,
        },
      },
    });
    expect(wrapper.text()).not.toContain("Registrar seguimiento");

    wrapper.vm.catalogs.capabilities.can_edit_cases = true;
    await nextTick();
    expect(wrapper.text()).toContain("Registrar seguimiento");

    const source = readFileSync(path.resolve(process.cwd(), "resources/js/views/convivencia/index.vue"), "utf8");
    expect(source).toContain('<BCard v-if="catalogs.capabilities?.can_edit_cases === true" class="case-detail__section case-detail__section--accent');
    expect(source).toContain('if (this.catalogs.capabilities?.can_edit_cases !== true || !this.caseDetail?.id) return;');
  });

  it("carga el show completo antes de editar colecciones que se sincronizan", async () => {
    const methods = ConvivenciaIndex.methods;
    const context = ConvivenciaIndex.data();
    context.catalogs.capabilities = {
      can_manage_plans: true,
      can_edit_cases: true,
      can_manage_interviews: true,
      can_manage_sociograms: true,
    };
    context.canCreateSection = (section) => methods.canCreateSection.call(context, section);
    context.canEditSection = (section) => methods.canEditSection.call(context, section);
    context.canEditItem = (section, item) => methods.canEditItem.call(context, section, item);

    const details = {
      "/api/convivencia/plans/1": { id: 1, name: "Plan completo", resources_required: "Equipo mediador", actions: [{ title: "Taller", required_resources: "Sala", evidence_summary: "Acta" }] },
      "/api/convivencia/cases/2": { id: 2, initial_report: "Relato", people: [{ full_name: "Persona vinculada", user_id: 18, notes: "Antecedente reservado" }] },
      "/api/convivencia/interviews/3": { id: 3, motive: "Seguimiento", participants: [{ full_name: "Participante", staff_id: 21, notes: "Acuerdo" }] },
      "/api/convivencia/sociograms/4": { id: 4, title: "Sociograma", questions: [{ id: 40, prompt: "¿Con quién trabajas?", active: true }], answers: [{ question_id: 40, selected_student_id: 51, notes: "Elección" }] },
    };
    const get = vi.spyOn(axios, "get").mockImplementation((url) => Promise.resolve({ data: { data: details[url] } }));

    for (const [section, id] of [["planes", 1], ["casos", 2], ["entrevistas", 3], ["sociogramas", 4]]) {
      await methods.editItem.call(context, section, { id, name: "Resumen sin relaciones" });
    }

    expect(get.mock.calls.map(([url]) => url)).toEqual(Object.keys(details));
    expect(context.plans.form).toMatchObject({ resources_required: "Equipo mediador", actions: [{ title: "Taller", required_resources: "Sala", evidence_summary: "Acta" }] });
    expect(context.cases.form.people).toEqual([expect.objectContaining({ full_name: "Persona vinculada", user_id: 18, notes: "Antecedente reservado" })]);
    expect(context.interviews.form.participants).toEqual([expect.objectContaining({ full_name: "Participante", staff_id: 21, notes: "Acuerdo" })]);
    expect(context.sociograms.form.questions).toEqual([expect.objectContaining({ prompt: "¿Con quién trabajas?", active: true })]);
    expect(context.sociograms.form.answers).toEqual([expect.objectContaining({ question_order: 1, selected_student_id: 51, notes: "Elección" })]);
    expect(context.recordLoadingId).toBeNull();
  });

  it("mantiene paginación de servidor para protocolos, partes y activaciones", async () => {
    const context = ProtocolManager.data();
    const get = vi.spyOn(axios, "get").mockImplementation((url, config) => Promise.resolve({
      data: {
        data: [{ id: config.params.page, name: url }],
        current_page: config.params.page,
        last_page: 4,
        total: 61,
      },
    }));

    await ProtocolManager.methods.loadProtocols.call(context, 2);
    await ProtocolManager.methods.loadParts.call(context, { page: 3, search: "protección", category: "protective_measure" });
    await ProtocolManager.methods.loadActivations.call(context, 4);

    expect(get).toHaveBeenNthCalledWith(1, "/api/convivencia/protocols", { params: expect.objectContaining({ per_page: 15, page: 2 }) });
    expect(get).toHaveBeenNthCalledWith(2, "/api/convivencia/protocol-parts", { params: expect.objectContaining({ per_page: 20, page: 3, search: "protección", category: "protective_measure" }) });
    expect(get).toHaveBeenNthCalledWith(3, "/api/convivencia/protocol-activations", { params: expect.objectContaining({ per_page: 15, page: 4 }) });
    expect(context.protocolPagination).toEqual({ current_page: 2, last_page: 4, total: 61 });
    expect(context.partPagination).toEqual({ current_page: 3, last_page: 4, total: 61 });
    expect(context.activationPagination).toEqual({ current_page: 4, last_page: 4, total: 61 });

    const library = shallowMount(ProtocolPartLibrary, {
      props: { parts: [], pagination: { current_page: 2, last_page: 4, total: 61 } },
    });
    library.vm.search = "  medida  ";
    library.vm.categoryFilter = "protective_measure";
    library.vm.applyQuery(3);
    expect(library.emitted("query")?.at(-1)).toEqual([{ page: 3, search: "medida", category: "protective_measure" }]);
  });

  it("hidrata, nombra y cancela búsquedas obsoletas en selectores remotos", async () => {
    const wrapper = shallowMount(ConvivenciaRemoteSelect, {
      props: { modelValue: 77, type: "cases", ariaLabel: "Caso asociado" },
    });
    const multiselect = wrapper.findComponent(Multiselect);
    expect(multiselect.props("modelValue")).toBe(77);
    expect(multiselect.props("aria")).toEqual({ "aria-label": "Caso asociado" });

    await multiselect.vm.$emit("select", 77, { value: 77, label: "CE-2026-077 · Caso visible" });
    expect(wrapper.emitted("select")?.at(-1)).toEqual([{ value: 77, label: "CE-2026-077 · Caso visible" }]);

    const requests = [];
    const get = vi.spyOn(axios, "get").mockImplementation((url, config) => new Promise((resolve) => {
      requests.push({ url, config, resolve });
    }));
    const first = wrapper.vm.fetchOptions("primer término");
    const firstSignal = requests[0].config.signal;
    const second = wrapper.vm.fetchOptions("segundo término");

    expect(firstSignal.aborted).toBe(true);
    expect(requests[1].url).toBe("/api/convivencia/references/cases");
    expect(requests[1].config.params).toMatchObject({ search: "segundo término", page: 1, per_page: 20, selected_id: 77 });

    requests[1].resolve({ data: { data: [{ id: 77, label: "CE-2026-077 · Caso visible", status: "abierto" }], last_page: 1 } });
    await second;
    requests[0].resolve({ data: { data: [{ id: 2, label: "Resultado obsoleto" }], last_page: 1 } });
    await first;
    expect(wrapper.vm.cachedOptions).toEqual([expect.objectContaining({ value: 77, label: "CE-2026-077 · Caso visible" })]);
    get.mockRestore();
  });

  it("presenta la apertura de casos completa y filtra subclasificaciones por clasificación", async () => {
    const form = {
      id: null,
      academic_year_id: 2026,
      case_type_item_id: 1,
      classification_item_id: 10,
      subclassification_item_id: 102,
      criticality_item_id: null,
      responsible_user_id: 5,
      responsible_staff_id: null,
      student_profile_id: null,
      course_section_id: null,
      opened_at: "2026-09-05T09:30",
      happened_at: "",
      follow_up_due_at: "",
      origin: "observacion",
      status: "abierto",
      place: "",
      initial_report: "",
      background: "",
      immediate_measures: "",
      safeguarding_measures: "",
      internal_notes: "",
      is_sensitive: false,
      people: [],
    };
    const options = {
      academicYears: [{ value: 2026, text: "2026" }],
      caseTypes: [{ value: 1, text: "Caso de convivencia escolar", code: "caso_convivencia" }],
      classifications: [
        { value: 10, text: "Maltrato", color: "#e8590c", metadata: { rice_protocol_codes: ["RICE-P05"] } },
        { value: 20, text: "Asistencia", color: "#0b7285", metadata: { rice_protocol_codes: ["RICE-P12"] } },
      ],
      subclassifications: [
        { value: 101, text: "Agresión verbal", parentId: 10 },
        { value: 102, text: "Agresión física", parentId: 10 },
        { value: 201, text: "Inasistencia reiterada", parentId: 20 },
      ],
      criticalities: [{ value: 30, text: "Alta", description: "Requiere resguardo inmediato", metadata: { requires_immediate_safeguard: true } }],
      caseResponsibleUsers: [{ value: 5, text: "Responsable", staff_id: 15, staff_name: "Ficha Responsable" }],
      users: [{ value: 5, text: "Responsable" }],
      usersOptional: [{ value: null, text: "Seleccione" }],
      staffOptional: [{ value: null, text: "Seleccione" }],
      supportProfessionals: [{
        value: "profile:8",
        profile_id: 8,
        user_id: 51,
        staff_id: 71,
        full_name: "María Psicóloga",
        area_name: "Psicología",
        professional_role_name: "Psicóloga educacional",
        text: "María Psicóloga — Psicología — Psicóloga educacional",
      }],
      coursesOptional: [{ value: null, text: "Seleccione" }],
      caseOrigins: [{ value: "observacion", text: "Observación" }],
      caseStatuses: [{ value: "abierto", text: "Abierto" }],
      personTypes: [{ value: "estudiante", text: "Estudiante" }],
      personRoles: [{ value: "afectado", text: "Afectado" }],
    };
    const wrapper = shallowMount(ConvivenciaRecordForm, {
      props: { section: "casos", form, options },
      global: {
        stubs: {
          BFormSelect: true,
          BFormInput: true,
          BFormTextarea: true,
          BFormCheckbox: true,
          ConvivenciaRemoteSelect: true,
        },
      },
    });

    expect(wrapper.text()).toContain("Apertura guiada y trazable");
    expect(wrapper.text()).toContain("Tipo de caso");
    expect(wrapper.text()).toContain("Año académico");
    expect(wrapper.text()).toContain("Responsable de la gestión");
    expect(wrapper.text()).not.toContain("Ficha de funcionario responsable");
    expect(wrapper.text()).toContain("Ficha institucional vinculada automáticamente");
    expect(wrapper.text()).toContain("Agregar al texto");
    expect(wrapper.text()).toContain("Profesionales de apoyo");
    expect(wrapper.text()).toContain("Incorporación rápida de alumnas");
    expect(wrapper.vm.subclassificationChoices.map((item) => item.value)).toEqual([null, 101, 102]);

    wrapper.vm.form.classification_item_id = 20;
    await nextTick();
    expect(wrapper.vm.form.subclassification_item_id).toBeNull();
    expect(wrapper.vm.subclassificationChoices.map((item) => item.value)).toEqual([null, 201]);
    wrapper.vm.selectCaseResponsible(5);
    expect(form.responsible_staff_id).toBe(15);

    const backgroundSuggestion = wrapper.vm.caseTextSuggestions.background[0];
    wrapper.vm.appendCaseSuggestion("background", backgroundSuggestion);
    wrapper.vm.appendCaseSuggestion("background", backgroundSuggestion);
    expect(form.background).toBe("• No se identifican antecedentes previos a la fecha.");
    expect(wrapper.text()).not.toContain("Resolución");

    wrapper.vm.form.id = 77;
    await nextTick();
    expect(wrapper.text()).toContain("Resolución");
  });

  it("agrega alumnas y profesionales de apoyo rápidamente sin duplicarlos", () => {
    const form = { people: [], is_sensitive: true };
    const options = {
      personRoles: [
        { value: "afectado", text: "Afectada" },
        { value: "testigo", text: "Testigo" },
        { value: "profesional_apoyo", text: "Profesional de apoyo" },
      ],
      supportProfessionals: [{
        value: "profile:8",
        user_id: 51,
        staff_id: 71,
        full_name: "María Psicóloga",
        area_name: "Psicología",
        professional_role_name: "Psicóloga educacional",
      }],
    };
    const wrapper = shallowMount(ConvivenciaRecordForm, {
      props: { section: "casos", form, options },
      global: {
        stubs: {
          BFormSelect: true,
          BFormInput: true,
          BFormTextarea: true,
          BFormCheckbox: true,
          ConvivenciaRemoteSelect: true,
        },
      },
    });

    wrapper.vm.quickStudentId = 91;
    wrapper.vm.quickStudentOption = {
      id: 91,
      full_name: "Alumna Uno",
      identifier: "28111222-3",
      course_section_id: 17,
    };
    wrapper.vm.quickStudentRole = "testigo";
    wrapper.vm.addQuickStudent();
    expect(form.people[0]).toMatchObject({
      student_profile_id: 91,
      person_type: "estudiante",
      role_type: "testigo",
      full_name: "Alumna Uno",
      course_section_id: 17,
      is_sensitive: true,
    });

    wrapper.vm.quickStudentId = 91;
    wrapper.vm.quickStudentOption = { id: 91, full_name: "Alumna Uno" };
    wrapper.vm.addQuickStudent();
    expect(form.people).toHaveLength(1);
    expect(wrapper.vm.quickStudentFeedback.text).toContain("ya está vinculada");

    wrapper.vm.quickProfessionalKey = "profile:8";
    wrapper.vm.addSupportProfessional();
    expect(form.people[1]).toMatchObject({
      user_id: 51,
      staff_id: 71,
      person_type: "funcionario",
      role_type: "profesional_apoyo",
      full_name: "María Psicóloga",
      relationship_label: "Psicología · Psicóloga educacional",
    });
    expect(wrapper.vm.supportTeamEntries).toHaveLength(1);
    expect(wrapper.vm.involvedPeopleEntries).toHaveLength(1);

    wrapper.vm.quickProfessionalKey = "profile:8";
    wrapper.vm.addSupportProfessional();
    expect(form.people).toHaveLength(2);
    expect(wrapper.vm.quickProfessionalFeedback.text).toContain("ya forma parte");
  });

  it("mantiene completos los campos específicos de denuncia y derivación dentro del formulario único", async () => {
    const commonOptions = {
      capabilities: { can_view_cases: true },
      academicYears: [{ value: 2026, text: "2026" }],
      coursesOptional: [{ value: null, text: "Sin curso" }],
      usersOptional: [{ value: null, text: "Sin responsable" }],
      staffOptional: [{ value: null, text: "Sin funcionario" }],
      departmentsOptional: [{ value: null, text: "Sin departamento" }],
      institutionsOptional: [{ value: null, text: "Sin institución" }],
      complaintTypes: [{ value: "anonimo", text: "Anónimo" }],
      complaintStatuses: [{ value: "recibida", text: "Recibida" }],
      situationTypes: [],
      personTypes: [{ value: "estudiante", text: "Estudiante" }],
      personRoles: [{ value: "afectado", text: "Afectado" }],
      derivationScopes: [{ value: "internal", text: "Interna" }, { value: "external", text: "Externa" }],
      derivationStatuses: [{ value: "ingresada", text: "Ingresada" }],
      derivationPriorities: [{ value: "media", text: "Media" }],
      confidentialityLevels: [{ value: "reservada", text: "Reservada" }],
    };
    const stubs = {
      BFormSelect: true,
      BFormInput: true,
      BFormTextarea: true,
      BFormCheckbox: true,
      ConvivenciaRemoteSelect: true,
    };
    const complaint = {
      academic_year_id: 2026,
      case_id: null,
      affected_student_id: null,
      course_section_id: null,
      situation_type_item_id: null,
      complainant_type: "anonimo",
      complainant_name: "Nombre que se debe limpiar",
      contact_email: "persona@example.test",
      contact_phone: "+56911112222",
      place: "",
      received_at: "2026-09-05T08:00",
      happened_at: "",
      report_text: "",
      involved_snapshot: [],
      status: "recibida",
      truth_declaration_accepted: true,
      is_anonymous: false,
      is_sensitive: true,
      admissibility_result: "",
    };
    const complaintWrapper = shallowMount(ConvivenciaRecordForm, {
      props: { section: "denuncias", form: complaint, options: commonOptions },
      global: { stubs },
    });

    expect(complaintWrapper.text()).toContain("Recepción de denuncia");
    expect(complaintWrapper.text()).toContain("Resultado de admisibilidad");
    expect(complaintWrapper.findAllComponents(ConvivenciaRemoteSelect)).toHaveLength(2);
    complaintWrapper.vm.handleAnonymousComplaint(true);
    expect(complaint).toMatchObject({
      complainant_type: "anonimo",
      complainant_name: "",
      contact_email: "",
      contact_phone: "",
    });

    const derivation = {
      scope: "internal",
      case_id: null,
      student_profile_id: null,
      course_section_id: null,
      academic_year_id: 2026,
      responsible_user_id: null,
      status: "ingresada",
      priority_level: "media",
      confidentiality_level: "reservada",
      destination_department_id: 4,
      destination_user_id: 8,
      destination_staff_id: 12,
      external_institution_id: null,
      external_contact_name: "",
      external_contact_email: "",
      external_contact_phone: "",
      destination_label: "",
      derived_at: "2026-09-05T08:00",
      sent_at: "",
      response_due_at: "",
      responded_at: "",
      closed_at: "",
      motive: "Seguimiento especializado",
      narrative: "",
      response_text: "",
      suggested_actions: "",
      follow_up_notes: "",
      is_sensitive: true,
    };
    const derivationWrapper = shallowMount(ConvivenciaRecordForm, {
      props: { section: "derivaciones", form: derivation, options: commonOptions },
      global: { stubs },
    });

    expect(derivationWrapper.text()).toContain("Departamento destino");
    derivationWrapper.vm.form.scope = "external";
    await nextTick();
    expect(derivationWrapper.text()).toContain("Institución externa");
    expect(derivationWrapper.text()).not.toContain("Departamento destino");
    expect(derivation).toMatchObject({
      destination_department_id: null,
      destination_user_id: null,
      destination_staff_id: null,
    });
  });

  it("busca estudiantes con payload mínimo y conserva metadatos para completar el curso", async () => {
    const get = vi.spyOn(axios, "get").mockResolvedValue({
      data: {
        data: [{
          id: 44,
          label: "Estudiante de prueba",
          secondary: "7° A · 12.345.678-9",
          course: "7° A",
          course_section_id: 18,
          academic_year_id: 2026,
          full_name: "Estudiante de prueba",
          identifier: "12345678-9",
        }],
      },
    });
    const wrapper = shallowMount(ConvivenciaRemoteSelect, {
      props: { modelValue: 44, type: "students" },
    });

    const options = await wrapper.vm.fetchOptions("Estudiante");

    expect(get).toHaveBeenCalledWith("/api/convivencia/students", expect.objectContaining({
      params: expect.objectContaining({ search: "Estudiante", selected_id: 44 }),
    }));
    expect(options).toEqual([expect.objectContaining({
      value: 44,
      course: "7° A",
      course_section_id: 18,
      academic_year_id: 2026,
    })]);
    expect(wrapper.vm.studentInitials(options[0])).toBe("EP");
    expect(wrapper.vm.formatRut(options[0].identifier)).toBe("12.345.678-9");
    get.mockRestore();
  });

  it("presenta el selector de estudiantes con identidad, curso y RUT legibles", async () => {
    vi.spyOn(axios, "get").mockResolvedValue({
      data: {
        data: [{
          id: 44,
          label: "Isidora Ignacia Aburto Espinoza",
          full_name: "Isidora Ignacia Aburto Espinoza",
          course: "NT1 A",
          identifier: "27558017-1",
          course_section_id: 18,
        }],
      },
    });
    const wrapper = mount(ConvivenciaRemoteSelect, {
      attachTo: document.body,
      props: {
        modelValue: 44,
        type: "students",
        placeholder: "Escribe nombre o RUT para buscar",
      },
    });
    await flushPromises();
    await nextTick();

    expect(wrapper.find(".convivencia-student-selected").text()).toContain("Isidora Ignacia Aburto Espinoza");
    expect(wrapper.find(".convivencia-student-selected").text()).toContain("NT1 A");

    const options = await wrapper.vm.fetchOptions("Isidora");
    await nextTick();
    const multiselect = wrapper.findComponent(Multiselect);
    multiselect.vm.$emit("select", options[0].value, options[0]);
    await nextTick();

    expect(wrapper.classes()).toContain("is-student-select");
    expect(wrapper.vm.studentName(options[0])).toBe("Isidora Ignacia Aburto Espinoza");
    expect(wrapper.vm.studentCourse(options[0])).toBe("NT1 A");
    expect(wrapper.vm.formatRut(wrapper.vm.studentIdentifier(options[0]))).toBe("27.558.017-1");
    expect(wrapper.emitted("select")?.at(-1)).toEqual([expect.objectContaining({ id: 44, course: "NT1 A" })]);

    wrapper.unmount();
  });

  it("conserva respuestas al renumerar preguntas del sociograma", () => {
    const context = {
      currentOperationalState: {
        form: {
          questions: [{ text: "Primera" }, { text: "Segunda" }],
          answers: [
            { question_order: 1, answer_value: "Se elimina" },
            { question_order: 2, answer_value: "Se conserva" },
          ],
        },
      },
      removeArrayItem(array, index) { array.splice(index, 1); },
    };

    ConvivenciaIndex.methods.removeRecordFormItem.call(context, "questions", 0);

    expect(context.currentOperationalState.form.questions).toEqual([{ text: "Segunda" }]);
    expect(context.currentOperationalState.form.answers).toEqual([{ question_order: 1, answer_value: "Se conserva" }]);
  });

  it("centraliza confirmaciones y avisos en SweetAlert con una capa superior al modal", async () => {
    const fire = vi.spyOn(Swal, "fire").mockResolvedValue({ isConfirmed: true });

    await confirmConvivenciaAction({ title: "Eliminar registro", text: "Esta acción quedará auditada.", confirmButtonText: "Eliminar" });
    await showConvivenciaSuccess("Registro actualizado.");
    await showConvivenciaError("No fue posible guardar.");

    expect(fire).toHaveBeenNthCalledWith(1, expect.objectContaining({
      showCancelButton: true,
      focusCancel: true,
      cancelButtonText: "Cancelar",
      confirmButtonText: "Eliminar",
      customClass: expect.objectContaining({ popup: "convivencia-swal" }),
    }));
    expect(fire).toHaveBeenNthCalledWith(2, expect.objectContaining({ icon: "success", showConfirmButton: false }));
    expect(fire).toHaveBeenNthCalledWith(3, expect.objectContaining({ icon: "error", confirmButtonText: "Entendido" }));

    const modalSource = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/ui/convivencia-form-modal.vue"), "utf8");
    expect(modalSource).toMatch(/\.convivencia-form-modal\{[^}]*z-index:10900/);
    expect(modalSource).toContain(".swal2-container{z-index:20000!important}");
  });

  it("aplica tablas y modales comunes a los flujos operativos y al espacio unificado", () => {
    const indexSource = readFileSync(path.resolve(process.cwd(), "resources/js/views/convivencia/index.vue"), "utf8");
    const caseWorkspaceSource = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/cases/convivencia-case-management-workspace.vue"), "utf8");
    const protocolSource = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/protocols/protocol-manager.vue"), "utf8");
    const partLibrarySource = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/protocols/protocol-part-library.vue"), "utf8");

    expect(indexSource.match(/<ConvivenciaOperationalSection\b/g)).toHaveLength(4);
    expect(indexSource).toContain("<ConvivenciaCaseManagementWorkspace");
    expect(indexSource).toContain("<ConvivenciaPlanWorkspace");
    expect(caseWorkspaceSource).toContain("<ConvivenciaOperationalSection");
    expect(indexSource).toContain("<ConvivenciaFormModal");
    expect(indexSource).toContain("confirmConvivenciaAction");
    expect(protocolSource.match(/<ConvivenciaDataTable\b/g)?.length).toBeGreaterThanOrEqual(2);
    expect(protocolSource.match(/<ConvivenciaFormModal\b/g)?.length).toBeGreaterThanOrEqual(2);
    expect(partLibrarySource).toContain("<ConvivenciaDataTable");
    expect(partLibrarySource).toContain("<ConvivenciaFormModal");
    expect(partLibrarySource).toContain("formless");
    expect(protocolSource).not.toContain("window.confirm");
    expect(indexSource).not.toContain("window.confirm");
  });
});
