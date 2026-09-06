<script>
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../ui/loading-state.vue";
import SiteAdminNavigation from "./site-admin-navigation.vue";
import {
  archiveSiteOrganization,
  createSiteOrganization,
  createSiteOrganizationRole,
  getSiteOrganization,
  getSiteOrganizationCatalogs,
  listSiteOrganizations,
  searchOrganizationStaff,
  searchOrganizationStudents,
  updateSiteOrganization,
} from "../../services/site-organizations-api";

const TYPE_CONFIG = {
  cgpa: {
    title: "Centro General de Padres y Apoderados",
    shortTitle: "CGPA",
    eyebrow: "Representación de las familias",
    description: "Administra cada directiva anual y decide, integrante por integrante, qué nombres se publican en el sitio.",
    icon: "bx-group",
    periodMode: "year",
    permission: "gestionar_cgpa_sitio",
    empty: "Aún no hay una directiva CGPA registrada.",
  },
  cde: {
    title: "Centro de Estudiantes",
    shortTitle: "CDE",
    eyebrow: "Participación estudiantil",
    description: "Construye la directiva anual desde estudiantes matriculadas y el equipo asesor, sin exponer datos sensibles.",
    icon: "bx-user-voice",
    periodMode: "year",
    permission: "gestionar_cde_sitio",
    empty: "Aún no hay una directiva CDE registrada.",
  },
  joint_committee: {
    title: "Comité Paritario",
    shortTitle: "Comité Paritario",
    eyebrow: "Gobernanza y prevención",
    description: "Publica versiones del comité oficial, su directiva y representantes, conservando la trazabilidad de cada período.",
    icon: "bx-shield-quarter",
    periodMode: "dates",
    permission: "gestionar_comite_paritario_sitio",
    empty: "Aún no hay una versión del Comité Paritario registrada.",
  },
};

const today = () => new Date().toISOString().slice(0, 10);
const currentYear = () => new Date().getFullYear();
const isTrue = (value) => value === true || value === 1 || value === "1" || value === "true";

const normalizeMember = (member = {}, index = 0) => ({
  id: member.id || member.pivot_id || null,
  member_kind: member.member_kind || (member.student_id || member.student_profile_id ? "student" : (member.staff_id || member.pivot ? "staff" : "external")),
  student_id: member.student_id || member.student_profile_id || null,
  staff_id: member.staff_id || (member.id && member.pivot ? member.id : null),
  display_name: member.display_name || member.display_name_snapshot || member.name || member.full_name || member.student?.name || member.student?.full_name || member.staff?.name || member.staff?.full_name || "",
  course: member.course || member.course_name || member.student?.course || member.student?.course_name || (member.member_kind === "student" ? member.detail_snapshot : "") || "",
  institutional_position: member.institutional_position || member.staff_position || member.staff?.position || member.staff?.position_name || (member.member_kind === "staff" ? member.detail_snapshot : "") || "",
  role_id: member.role_id || null,
  role_name: member.role_name || member.role?.name || "",
  section: member.section || "leadership",
  representation: member.representation || member.pivot?.representation || "trabajadores",
  member_role: member.member_role || member.pivot?.member_role || "titular",
  position_name: member.position_name || member.pivot?.position_name || "",
  joined_on: member.joined_on || member.pivot?.joined_on || "",
  ended_on: member.ended_on || member.pivot?.ended_on || "",
  active: member.active !== false && member.pivot?.active !== false,
  sort_order: Number(member.sort_order ?? index + 1),
  public_name_authorized: isTrue(member.public_name_authorized) || Boolean(member.public_name_authorized_at),
});

export default {
  components: { Layout, LoadingState, SiteAdminNavigation },
  props: {
    organizationType: {
      type: String,
      required: true,
      validator: (value) => Object.prototype.hasOwnProperty.call(TYPE_CONFIG, value),
    },
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: "",
      success: "",
      organizations: [],
      catalogs: { statuses: [], sections: [], roles: [], capabilities: {} },
      meta: { current_page: 1, last_page: 1, total: 0 },
      summary: { total: 0, published: 0, draft: 0, active: 0 },
      filters: { search: "", status: "", year: "" },
      showEditor: false,
      showPreview: false,
      previewItem: null,
      form: null,
      fieldErrors: {},
      picker: {
        open: false,
        kind: "student",
        search: "",
        loading: false,
        results: [],
        page: 1,
        lastPage: 1,
      },
      roleCreator: {
        open: false,
        saving: false,
        name: "",
        section: "leadership",
        targetIndex: null,
      },
      lastFocusedElement: null,
      nestedFocusedElement: null,
      previousBodyOverflow: "",
    };
  },
  computed: {
    config() {
      return TYPE_CONFIG[this.organizationType];
    },
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canManage() {
      return Boolean(
        this.catalogs.capabilities?.can_manage
        || this.permissions.includes(this.config.permission)
        || this.permissions.includes("__superadmin__"),
      );
    },
    canPublish() {
      return this.catalogs.capabilities?.can_publish ?? this.canManage;
    },
    yearOptions() {
      const years = new Set([currentYear(), currentYear() + 1]);
      this.organizations.forEach((item) => {
        if (item.year) years.add(Number(item.year));
      });
      return Array.from(years).sort((a, b) => b - a);
    },
    statusOptions() {
      const statuses = this.catalogs.statuses || [];
      if (statuses.length) {
        return statuses.map((status) => (typeof status === "string"
          ? { value: status, label: this.statusLabel(status) }
          : { value: status.value || status.id, label: status.label || status.name }));
      }
      return [
        { value: "draft", label: "Borrador" },
        { value: "published", label: "Publicado" },
        { value: "archived", label: "Archivado" },
      ];
    },
    metrics() {
      return [
        { label: "Versiones", value: this.summary.total ?? this.meta.total ?? 0, icon: "bx-layer", tone: "blue" },
        { label: "Publicadas", value: this.summary.published || 0, icon: "bx-world", tone: "green" },
        { label: "Borradores", value: this.summary.draft ?? this.summary.drafts ?? 0, icon: "bx-edit-alt", tone: "amber" },
      ];
    },
    authorizedMembers() {
      return (this.previewItem?.members || []).filter((member) => member.public_name_authorized);
    },
    hiddenMemberCount() {
      return Math.max(0, (this.previewItem?.members || []).length - this.authorizedMembers.length);
    },
    pickerKindOptions() {
      if (this.organizationType === "joint_committee") return [{ value: "staff", label: "Funcionarios" }];
      if (this.organizationType === "cde") {
        return [
          { value: "student", label: "Estudiantes" },
          { value: "staff", label: "Docentes asesores" },
        ];
      }
      return [];
    },
  },
  watch: {
    organizationType: {
      immediate: true,
      handler() {
        this.resetState();
        this.loadCatalogs();
        this.load();
      },
    },
  },
  mounted() {
    document.addEventListener("keydown", this.handleEscape);
  },
  beforeUnmount() {
    document.removeEventListener("keydown", this.handleEscape);
    this.releaseBodyScroll(true);
  },
  methods: {
    resetState() {
      this.organizations = [];
      this.catalogs = { statuses: [], sections: [], roles: [], capabilities: {} };
      this.meta = { current_page: 1, last_page: 1, total: 0 };
      this.summary = { total: 0, published: 0, draft: 0, active: 0 };
      this.error = "";
      this.success = "";
      this.showEditor = false;
      this.showPreview = false;
      this.form = this.emptyForm();
      this.filters = { search: "", status: "", year: "" };
    },
    emptyForm() {
      return {
        id: null,
        name: this.organizationType === "joint_committee"
          ? "Comité Paritario de Higiene y Seguridad"
          : this.config?.title || "",
        year: this.config?.periodMode === "year" ? currentYear() : null,
        starts_on: this.config?.periodMode === "dates" ? today() : "",
        ends_on: "",
        summary: "",
        status: "draft",
        active: true,
        members: [],
      };
    },
    unwrap(response) {
      return response?.data?.data ?? response?.data ?? {};
    },
    normalizeCollection(response) {
      const root = response?.data || {};
      const payload = root.data ?? root;
      const rows = Array.isArray(payload) ? payload : (payload.data || []);
      const pagination = root.meta || payload.meta || (Array.isArray(payload) ? {} : payload);
      return {
        rows,
        meta: {
          current_page: Number(pagination.current_page || 1),
          last_page: Number(pagination.last_page || 1),
          total: Number(pagination.total ?? rows.length),
        },
        summary: root.summary || payload.summary || {},
      };
    },
    async loadCatalogs(year = null) {
      try {
        const response = await getSiteOrganizationCatalogs(this.organizationType, {
          year: year || this.form?.year || this.filters.year || undefined,
        });
        const catalogs = this.unwrap(response);
        this.catalogs = {
          statuses: catalogs.statuses || [],
          types: catalogs.types || [],
          sections: catalogs.sections || [],
          roles: catalogs.roles || [],
          capabilities: catalogs.capabilities || response?.data?.capabilities || {},
        };
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible cargar los catálogos de esta sección.");
      }
    },
    async load(page = 1) {
      this.loading = true;
      this.error = "";
      try {
        const response = await listSiteOrganizations(this.organizationType, {
          page,
          search: this.filters.search,
          status: this.filters.status,
          year: this.filters.year,
        });
        const normalized = this.normalizeCollection(response);
        this.organizations = normalized.rows.map((item) => this.normalizeOrganization(item));
        this.meta = normalized.meta;
        const pageSummary = this.organizations.reduce((totals, item) => ({
          ...totals,
          [item.status]: (totals[item.status] || 0) + 1,
          active: totals.active + (item.active ? 1 : 0),
        }), { published: 0, draft: 0, archived: 0, active: 0 });
        this.summary = {
          ...this.summary,
          ...pageSummary,
          ...normalized.summary,
          total: normalized.summary.total ?? normalized.meta.total,
        };
      } catch (error) {
        this.error = this.errorMessage(error, `No fue posible cargar ${this.config.shortTitle}.`);
      } finally {
        this.loading = false;
      }
    },
    normalizeOrganization(item = {}) {
      return {
        ...item,
        status: item.status || "draft",
        active: item.active !== false,
        members: (item.members || item.staff_members || []).map(normalizeMember),
      };
    },
    async openCreate() {
      if (!this.canManage) return;
      this.captureFocus();
      this.fieldErrors = {};
      this.form = this.emptyForm();
      await this.loadCatalogs(this.form.year);
      this.showEditor = true;
      this.lockBodyScroll();
      this.focusDialog("editorDialog");
    },
    async openEdit(item) {
      if (!this.canManage) return;
      this.captureFocus();
      this.error = "";
      this.fieldErrors = {};
      try {
        const response = await getSiteOrganization(this.organizationType, item.id);
        this.form = this.normalizeOrganization(this.unwrap(response));
        await this.loadCatalogs(this.form.year);
        this.showEditor = true;
        this.lockBodyScroll();
        this.focusDialog("editorDialog");
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible abrir esta versión para editarla.");
      }
    },
    closeEditor() {
      if (this.saving) return;
      this.showEditor = false;
      this.picker.open = false;
      this.roleCreator.open = false;
      this.fieldErrors = {};
      this.releaseBodyScroll();
      this.restoreFocus();
    },
    validate() {
      const errors = {};
      if (!String(this.form.name || "").trim()) errors.name = "Escribe un nombre para esta versión.";
      if (this.config.periodMode === "year" && !this.form.year) errors.year = "Selecciona el año.";
      if (this.config.periodMode === "dates" && !this.form.starts_on) errors.starts_on = "Indica la fecha de inicio.";
      if (this.form.ends_on && this.form.starts_on && this.form.ends_on < this.form.starts_on) {
        errors.ends_on = "La fecha de término debe ser posterior al inicio.";
      }
      this.form.members.forEach((member, index) => {
        if (this.organizationType === "cgpa" && !String(member.display_name || "").trim()) {
          errors[`members.${index}.display_name`] = "Indica el nombre de la persona.";
        }
        if (this.organizationType === "cde" && !member.student_id && !member.staff_id) {
          errors[`members.${index}.person`] = "Selecciona una persona del registro institucional.";
        }
        if (this.organizationType === "joint_committee" && !member.staff_id) {
          errors[`members.${index}.person`] = "Selecciona un funcionario.";
        }
        if (this.organizationType === "joint_committee" && !String(member.position_name || "").trim()) {
          errors[`members.${index}.position_name`] = "Indica el cargo dentro del comité.";
        }
        if (member.ended_on && member.joined_on && member.ended_on < member.joined_on) {
          errors[`members.${index}.ended_on`] = "El término debe ser posterior al ingreso.";
        }
        if (this.organizationType !== "joint_committee" && !member.role_id) {
          errors[`members.${index}.role_id`] = "Selecciona un cargo.";
        }
      });
      if (this.form.status === "published") {
        if (!this.form.members.length) errors.members = "Agrega al menos una persona antes de publicar.";
        if (this.form.members.length && !this.form.members.some((member) => member.public_name_authorized)) {
          errors.members = "Para publicar, al menos una persona debe autorizar que su nombre sea visible.";
        }
      }
      this.fieldErrors = errors;
      return !Object.keys(errors).length;
    },
    payload() {
      const payload = {
        name: String(this.form.name || "").trim(),
        summary: String(this.form.summary || "").trim() || null,
        status: this.form.status,
        active: Boolean(this.form.active),
        members: this.form.members.map((member, index) => ({
          id: member.id || undefined,
          member_kind: member.member_kind,
          display_name: this.organizationType === "cgpa" ? String(member.display_name || "").trim() : undefined,
          student_id: member.student_id || undefined,
          staff_id: member.staff_id || undefined,
          role_id: member.role_id || undefined,
          section: member.section,
          representation: this.organizationType === "joint_committee" ? member.representation : undefined,
          member_role: this.organizationType === "joint_committee" ? member.member_role : undefined,
          position_name: this.organizationType === "joint_committee" ? String(member.position_name || "").trim() || null : undefined,
          joined_on: this.organizationType === "joint_committee" ? member.joined_on || null : undefined,
          ended_on: this.organizationType === "joint_committee" ? member.ended_on || null : undefined,
          active: this.organizationType === "joint_committee" ? Boolean(member.active) : undefined,
          sort_order: index + 1,
          public_name_authorized: Boolean(member.public_name_authorized),
        })),
      };

      if (this.config.periodMode === "year") payload.year = Number(this.form.year);
      if (this.config.periodMode === "dates") {
        payload.starts_on = this.form.starts_on;
        payload.ends_on = this.form.ends_on || null;
      }

      return payload;
    },
    async save() {
      if (!this.canManage || !this.validate()) return;
      this.saving = true;
      this.error = "";
      try {
        const response = this.form.id
          ? await updateSiteOrganization(this.organizationType, this.form.id, this.payload())
          : await createSiteOrganization(this.organizationType, this.payload());
        this.success = response?.data?.message || "La versión quedó guardada correctamente.";
        this.showEditor = false;
        this.releaseBodyScroll();
        this.restoreFocus();
        await this.load(this.meta.current_page);
      } catch (error) {
        this.fieldErrors = error?.response?.data?.errors || {};
        this.error = this.errorMessage(error, "No fue posible guardar los cambios.");
      } finally {
        this.saving = false;
      }
    },
    async archiveItem(item) {
      if (!this.canManage) return;
      const result = await Swal.fire({
        title: "Archivar versión",
        text: "Se conservará el historial y dejará de mostrarse como versión vigente.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, archivar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;
      try {
        const response = await archiveSiteOrganization(this.organizationType, item.id);
        this.success = response?.data?.message || "La versión fue archivada.";
        await this.load(this.meta.current_page);
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible archivar esta versión.");
      }
    },
    openPreview(item) {
      this.captureFocus();
      this.previewItem = this.normalizeOrganization(item);
      this.showPreview = true;
      this.lockBodyScroll();
      this.focusDialog("previewDialog");
    },
    closePreview() {
      this.showPreview = false;
      this.releaseBodyScroll();
      this.restoreFocus();
    },
    addExternalMember() {
      this.form.members.push(normalizeMember({
        member_kind: "external",
        section: "leadership",
        role_id: this.firstRoleId("leadership"),
      }, this.form.members.length));
    },
    async openPersonPicker(kind = null) {
      this.nestedFocusedElement = document.activeElement;
      this.picker.kind = kind || (this.organizationType === "joint_committee" ? "staff" : "student");
      this.picker.search = "";
      this.picker.results = [];
      this.picker.open = true;
      this.lockBodyScroll();
      await this.searchPeople(1);
      this.focusDialog("pickerDialog");
    },
    closePersonPicker() {
      this.picker.open = false;
      this.restoreNestedFocus();
    },
    async searchPeople(page = 1) {
      this.picker.loading = true;
      try {
        const response = this.picker.kind === "student"
          ? await searchOrganizationStudents({ year: this.form.year, search: this.picker.search, page })
          : await searchOrganizationStaff({ type: this.organizationType, search: this.picker.search, page });
        const normalized = this.normalizeCollection(response);
        this.picker.results = normalized.rows;
        this.picker.page = normalized.meta.current_page;
        this.picker.lastPage = normalized.meta.last_page;
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible buscar personas en el registro institucional.");
      } finally {
        this.picker.loading = false;
      }
    },
    async changePickerKind(kind) {
      this.picker.kind = kind;
      this.picker.search = "";
      await this.searchPeople(1);
    },
    personId(person) {
      return Number(person.id);
    },
    personAlreadySelected(person) {
      const id = this.personId(person);
      return this.form.members.some((member) => (
        this.picker.kind === "student"
          ? Number(member.student_id) === id
          : Number(member.staff_id) === id
      ));
    },
    selectPerson(person) {
      if (this.personAlreadySelected(person)) return;
      const isCommittee = this.organizationType === "joint_committee";
      const section = isCommittee ? "member" : (this.picker.kind === "staff" ? "advisor" : "leadership");
      this.form.members.push(normalizeMember({
        member_kind: this.picker.kind,
        student_id: this.picker.kind === "student" ? person.id : null,
        staff_id: this.picker.kind === "staff" ? person.id : null,
        display_name: person.name || person.full_name,
        course: person.course || person.course_name || "",
        institutional_position: person.position || person.position_name || "",
        section,
        role_id: isCommittee ? null : this.firstRoleId(section),
        joined_on: isCommittee ? this.form.starts_on : "",
      }, this.form.members.length));
      this.closePersonPicker();
    },
    removeMember(index) {
      this.form.members.splice(index, 1);
      this.reindexMembers();
    },
    moveMember(index, direction) {
      const target = index + direction;
      if (target < 0 || target >= this.form.members.length) return;
      const next = [...this.form.members];
      [next[index], next[target]] = [next[target], next[index]];
      this.form.members = next;
      this.reindexMembers();
    },
    reindexMembers() {
      this.form.members = this.form.members.map((member, index) => ({ ...member, sort_order: index + 1 }));
    },
    firstRoleId(section) {
      return this.rolesForSection(section)[0]?.id || null;
    },
    rolesForSection(section) {
      const roles = this.catalogs.roles || [];
      const matching = roles.filter((role) => !role.section || role.section === section);
      return matching.length ? matching : roles;
    },
    roleName(member) {
      return member.position_name
        || member.role_name
        || (this.catalogs.roles || []).find((role) => Number(role.id) === Number(member.role_id))?.name
        || "Integrante";
    },
    openRoleCreator(index = null) {
      this.nestedFocusedElement = document.activeElement;
      const member = index === null ? null : this.form.members[index];
      const isCdeAdvisor = this.organizationType === "cde" && member?.member_kind === "staff";
      this.roleCreator = {
        open: true,
        saving: false,
        name: "",
        section: isCdeAdvisor ? "advisor" : (member?.section || "leadership"),
        targetIndex: index,
      };
      this.lockBodyScroll();
      this.focusDialog("roleDialog");
    },
    closeRoleCreator() {
      this.roleCreator.open = false;
      this.restoreNestedFocus();
    },
    async saveRole() {
      if (!String(this.roleCreator.name || "").trim()) return;
      this.roleCreator.saving = true;
      try {
        const response = await createSiteOrganizationRole({
          organization_type: this.organizationType,
          name: String(this.roleCreator.name).trim(),
          section: this.roleCreator.section,
          sort_order: (this.catalogs.roles || []).length + 1,
          active: true,
        });
        const role = this.unwrap(response);
        this.catalogs.roles = [...(this.catalogs.roles || []), role];
        if (this.roleCreator.targetIndex !== null && this.form.members[this.roleCreator.targetIndex]) {
          this.form.members[this.roleCreator.targetIndex].role_id = role.id;
        }
        this.closeRoleCreator();
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible crear el cargo.");
      } finally {
        this.roleCreator.saving = false;
      }
    },
    statusLabel(status) {
      return { draft: "Borrador", published: "Publicado", archived: "Archivado" }[status] || status;
    },
    periodLabel(item) {
      if (this.config.periodMode === "year") return String(item.year || "Sin año");
      return `${this.formatDate(item.starts_on)} — ${item.ends_on ? this.formatDate(item.ends_on) : "Vigente"}`;
    },
    formatDate(value) {
      if (!value) return "Sin fecha";
      return new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "short", year: "numeric" })
        .format(new Date(`${String(value).slice(0, 10)}T12:00:00`));
    },
    sectionLabel(section) {
      return { leadership: "Directiva", advisor: "Asesoría", member: "Integrantes" }[section] || section;
    },
    memberSubtitle(member) {
      if (member.member_kind === "student") return member.course || "Estudiante matriculada";
      if (member.member_kind === "staff") {
        return member.institutional_position || (this.organizationType === "cde" ? "Equipo asesor" : "Funcionario/a");
      }
      return this.sectionLabel(member.section);
    },
    captureFocus() {
      this.lastFocusedElement = document.activeElement;
    },
    focusDialog(refName) {
      this.$nextTick(() => this.$refs[refName]?.focus?.());
    },
    restoreFocus() {
      this.$nextTick(() => this.lastFocusedElement?.focus?.());
    },
    restoreNestedFocus() {
      this.$nextTick(() => this.nestedFocusedElement?.focus?.());
    },
    lockBodyScroll() {
      if (!document.body.classList.contains("site-organization-modal-open")) {
        this.previousBodyOverflow = document.body.style.overflow;
      }
      this.$refs.managerRoot?.closest?.(".premium-content-grid")?.classList.add("site-organization-modal-layer");
      document.body.classList.add("site-organization-modal-open");
      document.body.style.overflow = "hidden";
    },
    releaseBodyScroll(force = false) {
      if (!force && (this.showEditor || this.showPreview || this.picker.open || this.roleCreator.open)) return;
      this.$refs.managerRoot?.closest?.(".premium-content-grid")?.classList.remove("site-organization-modal-layer");
      document.body.classList.remove("site-organization-modal-open");
      document.body.style.overflow = this.previousBodyOverflow;
    },
    handleEscape(event) {
      if (event.key !== "Escape") return;
      if (this.roleCreator.open) return this.closeRoleCreator();
      if (this.picker.open) return this.closePersonPicker();
      if (this.showPreview) return this.closePreview();
      if (this.showEditor) this.closeEditor();
    },
    errorFor(path) {
      const value = this.fieldErrors[path];
      return Array.isArray(value) ? value[0] : value;
    },
    errorMessage(error, fallback) {
      if (Number(error?.response?.status) >= 500) return fallback;
      const errors = error?.response?.data?.errors;
      return (errors && Object.values(errors).flat()[0]) || error?.response?.data?.message || fallback;
    },
  },
};
</script>

<template>
  <Layout>
    <main ref="managerRoot" class="organization-manager">
      <SiteAdminNavigation />
      <section class="organization-hero">
        <div class="organization-hero__copy">
          <span class="organization-eyebrow"><i class="bx" :class="config.icon"></i> Sitio web · {{ config.eyebrow }}</span>
          <h1>{{ config.title }}</h1>
          <p>{{ config.description }}</p>
        </div>
        <div class="organization-hero__metrics" aria-label="Resumen de versiones">
          <article v-for="metric in metrics" :key="metric.label" :class="`tone-${metric.tone}`">
            <span><i class="bx" :class="metric.icon"></i></span>
            <div><strong>{{ metric.value }}</strong><small>{{ metric.label }}</small></div>
          </article>
        </div>
      </section>

      <div v-if="error" class="organization-alert is-error" role="alert">
        <i class="bx bx-error-circle"></i><span>{{ error }}</span>
        <button type="button" aria-label="Cerrar mensaje" @click="error = ''"><i class="bx bx-x"></i></button>
      </div>
      <div v-if="success" class="organization-alert is-success" role="status">
        <i class="bx bx-check-circle"></i><span>{{ success }}</span>
        <button type="button" aria-label="Cerrar mensaje" @click="success = ''"><i class="bx bx-x"></i></button>
      </div>

      <section class="organization-toolbar" aria-label="Filtros y acciones">
        <form class="organization-filters" @submit.prevent="load(1)">
          <label class="organization-search">
            <span>Buscar</span>
            <i class="bx bx-search"></i>
            <input v-model.trim="filters.search" type="search" placeholder="Nombre o período" />
          </label>
          <label>
            <span>Estado</span>
            <select v-model="filters.status">
              <option value="">Todos</option>
              <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
            </select>
          </label>
          <label v-if="config.periodMode === 'year'">
            <span>Año</span>
            <select v-model="filters.year">
              <option value="">Todos</option>
              <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
            </select>
          </label>
          <button class="organization-filter-button" type="submit"><i class="bx bx-filter-alt"></i><span>Aplicar</span></button>
        </form>
        <button v-if="canManage" class="organization-primary-action" type="button" @click="openCreate">
          <span><i class="bx bx-plus"></i></span>
          Nueva {{ config.periodMode === "year" ? "directiva" : "versión" }}
        </button>
      </section>

      <LoadingState v-if="loading" :message="`Cargando ${config.shortTitle}...`" />

      <section v-else-if="organizations.length" class="organization-grid" :aria-label="`Versiones de ${config.shortTitle}`">
        <article v-for="item in organizations" :key="item.id" class="organization-card" :class="`is-${item.status}`">
          <header>
            <span class="organization-period">{{ periodLabel(item) }}</span>
            <span class="organization-status" :class="`is-${item.status}`">{{ statusLabel(item.status) }}</span>
          </header>
          <div class="organization-card__body">
            <span class="organization-card__icon"><i class="bx" :class="config.icon"></i></span>
            <h2>{{ item.name }}</h2>
            <p>{{ item.summary || "Sin descripción pública registrada." }}</p>
            <div class="organization-members-preview">
              <span v-for="member in item.members.slice(0, 4)" :key="member.id || `${member.display_name}-${member.sort_order}`" :title="member.display_name">
                {{ String(member.display_name || "?").charAt(0).toUpperCase() }}
              </span>
              <small>{{ item.members.length }} {{ item.members.length === 1 ? "integrante" : "integrantes" }}</small>
            </div>
          </div>
          <footer>
            <button type="button" aria-label="Vista previa" @click="openPreview(item)"><i class="bx bx-show"></i><span>Vista previa</span></button>
            <button v-if="canManage" type="button" aria-label="Editar" @click="openEdit(item)"><i class="bx bx-edit-alt"></i><span>Editar</span></button>
            <button v-if="canManage && item.status !== 'archived'" class="is-danger" type="button" aria-label="Archivar" @click="archiveItem(item)"><i class="bx bx-archive-in"></i></button>
          </footer>
        </article>
      </section>

      <section v-else-if="!loading" class="organization-empty">
        <span><i class="bx" :class="config.icon"></i></span>
        <h2>{{ config.empty }}</h2>
        <p>Crea la primera versión para preparar su publicación en la página del colegio.</p>
        <button v-if="canManage" type="button" @click="openCreate"><i class="bx bx-plus"></i> Crear primera versión</button>
      </section>

      <nav v-if="meta.last_page > 1" class="organization-pagination" aria-label="Paginación">
        <button type="button" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><i class="bx bx-chevron-left"></i> Anterior</button>
        <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button type="button" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Siguiente <i class="bx bx-chevron-right"></i></button>
      </nav>

      <div v-if="showEditor" class="organization-modal" role="dialog" aria-modal="true" :aria-labelledby="`organization-editor-title-${organizationType}`">
        <section ref="editorDialog" class="organization-dialog" tabindex="-1">
          <header class="organization-dialog__header">
            <span><i class="bx" :class="form.id ? 'bx-edit-alt' : 'bx-plus'"></i></span>
            <div>
              <small>{{ config.shortTitle }} · gestión web</small>
              <h2 :id="`organization-editor-title-${organizationType}`">{{ form.id ? "Editar versión" : "Nueva versión" }}</h2>
              <p>La composición se conserva por período y solo se publica con autorización.</p>
            </div>
            <button type="button" aria-label="Cerrar editor" @click="closeEditor"><i class="bx bx-x"></i></button>
          </header>

          <form class="organization-form" @submit.prevent="save">
            <div class="organization-form__body">
              <section class="organization-form__section">
                <header><span>01</span><div><h3>Identidad y vigencia</h3><p>Define la versión que aparecerá en la historia institucional.</p></div></header>
                <div class="organization-fields" :class="{ 'is-dates': config.periodMode === 'dates' }">
                  <label :class="{ 'has-error': errorFor('name') }">
                    <span>Nombre <em>*</em></span>
                    <input v-model.trim="form.name" type="text" maxlength="180" />
                    <small v-if="errorFor('name')">{{ errorFor("name") }}</small>
                  </label>
                  <label v-if="config.periodMode === 'year'" :class="{ 'has-error': errorFor('year') }">
                    <span>Año <em>*</em></span>
                    <input v-model.number="form.year" type="number" min="2000" max="2100" @change="loadCatalogs(form.year)" />
                    <small v-if="errorFor('year')">{{ errorFor("year") }}</small>
                  </label>
                  <template v-else>
                    <label :class="{ 'has-error': errorFor('starts_on') }">
                      <span>Inicio <em>*</em></span><input v-model="form.starts_on" type="date" />
                      <small v-if="errorFor('starts_on')">{{ errorFor("starts_on") }}</small>
                    </label>
                    <label :class="{ 'has-error': errorFor('ends_on') }">
                      <span>Término</span><input v-model="form.ends_on" type="date" />
                      <small v-if="errorFor('ends_on')">{{ errorFor("ends_on") }}</small>
                    </label>
                  </template>
                  <label class="is-full">
                    <span>Presentación pública</span>
                    <textarea v-model.trim="form.summary" rows="3" maxlength="1200" placeholder="Describe brevemente el propósito y trabajo de esta directiva."></textarea>
                  </label>
                </div>
              </section>

              <section class="organization-form__section">
                <header class="member-section-heading">
                  <span>02</span>
                  <div><h3>Integrantes y directiva</h3><p>Ordena los cargos tal como deben mostrarse públicamente.</p></div>
                  <button v-if="organizationType === 'cgpa'" type="button" @click="addExternalMember"><i class="bx bx-plus"></i> Agregar persona</button>
                  <button v-else type="button" @click="openPersonPicker()"><i class="bx bx-search-alt"></i> Seleccionar persona</button>
                </header>

                <div v-if="errorFor('members')" class="member-error" role="alert"><i class="bx bx-error-circle"></i>{{ errorFor("members") }}</div>

                <div v-if="form.members.length" class="member-editor-list">
                  <article v-for="(member, index) in form.members" :key="member.id || `new-${index}`" class="member-editor" :class="{ 'has-consent': member.public_name_authorized }">
                    <div class="member-order" aria-label="Orden del integrante">
                      <strong>{{ index + 1 }}</strong>
                      <button type="button" :disabled="index === 0" aria-label="Subir integrante" @click="moveMember(index, -1)"><i class="bx bx-chevron-up"></i></button>
                      <button type="button" :disabled="index === form.members.length - 1" aria-label="Bajar integrante" @click="moveMember(index, 1)"><i class="bx bx-chevron-down"></i></button>
                    </div>
                    <div class="member-editor__identity">
                      <span class="member-avatar">{{ String(member.display_name || "?").charAt(0).toUpperCase() }}</span>
                      <label v-if="organizationType === 'cgpa'" :class="{ 'has-error': errorFor(`members.${index}.display_name`) }">
                        <span>Nombre completo</span>
                        <input v-model.trim="member.display_name" type="text" maxlength="160" placeholder="Nombre de la persona" />
                        <small v-if="errorFor(`members.${index}.display_name`)">{{ errorFor(`members.${index}.display_name`) }}</small>
                      </label>
                      <div v-else>
                        <strong>{{ member.display_name }}</strong>
                        <small>{{ memberSubtitle(member) }}</small>
                      </div>
                    </div>

                    <template v-if="organizationType !== 'joint_committee'">
                      <label class="member-field" :class="{ 'has-error': errorFor(`members.${index}.role_id`) }">
                        <span>Cargo</span>
                        <select v-model="member.role_id">
                          <option :value="null" disabled>Seleccionar cargo</option>
                          <option v-for="role in rolesForSection(member.section)" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                        <small v-if="errorFor(`members.${index}.role_id`)">{{ errorFor(`members.${index}.role_id`) }}</small>
                      </label>
                      <button class="member-add-role" type="button" @click="openRoleCreator(index)" aria-label="Crear cargo personalizado"><i class="bx bx-plus"></i> Nuevo cargo</button>
                      <label class="member-field">
                        <span>Grupo</span>
                        <select v-model="member.section" :disabled="organizationType === 'cde' && member.member_kind === 'staff'">
                          <template v-if="organizationType === 'cde' && member.member_kind === 'staff'">
                            <option value="advisor">Asesoría</option>
                          </template>
                          <template v-else>
                            <option value="leadership">Directiva</option>
                            <option value="member">Integrantes</option>
                          </template>
                        </select>
                      </label>
                    </template>

                    <template v-else>
                      <label class="member-field"><span>Representación</span><select v-model="member.representation"><option value="trabajadores">Trabajadores</option><option value="empleador">Empleador</option></select></label>
                      <label class="member-field"><span>Calidad</span><select v-model="member.member_role"><option value="titular">Titular</option><option value="suplente">Suplente</option></select></label>
                      <label class="member-field" :class="{ 'has-error': errorFor(`members.${index}.position_name`) }"><span>Cargo en directiva</span><input v-model.trim="member.position_name" type="text" maxlength="100" placeholder="Presidencia, Secretaría..." /><small v-if="errorFor(`members.${index}.position_name`)">{{ errorFor(`members.${index}.position_name`) }}</small></label>
                      <label class="member-field"><span>Desde</span><input v-model="member.joined_on" type="date" /></label>
                      <label class="member-field" :class="{ 'has-error': errorFor(`members.${index}.ended_on`) }"><span>Hasta</span><input v-model="member.ended_on" type="date" /><small v-if="errorFor(`members.${index}.ended_on`)">{{ errorFor(`members.${index}.ended_on`) }}</small></label>
                    </template>

                    <label class="member-consent">
                      <input v-model="member.public_name_authorized" type="checkbox" />
                      <span><i class="bx" :class="member.public_name_authorized ? 'bx-check' : 'bx-lock-alt'"></i></span>
                      <div><strong>Nombre autorizado</strong><small>{{ member.public_name_authorized ? "Se mostrará públicamente" : "Se mantendrá oculto" }}</small></div>
                    </label>
                    <button class="member-remove" type="button" aria-label="Quitar integrante" @click="removeMember(index)"><i class="bx bx-trash"></i></button>
                  </article>
                </div>
                <div v-else class="member-empty">
                  <i class="bx bx-user-plus"></i><strong>Sin integrantes</strong>
                  <span>{{ organizationType === "cgpa" ? "Agrega nombres y cargos de la directiva." : "Selecciona personas desde los registros institucionales." }}</span>
                </div>
              </section>

              <section class="organization-form__section">
                <header><span>03</span><div><h3>Estado editorial</h3><p>Controla la vigencia y publicación de esta versión.</p></div></header>
                <div class="editorial-panel">
                  <label><span>Estado</span><select v-model="form.status" :disabled="!canPublish"><option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option></select></label>
                  <label class="active-check"><input v-model="form.active" type="checkbox" /><span><i class="bx bx-check"></i></span><div><strong>Versión activa</strong><small>Puede considerarse vigente dentro de su período.</small></div></label>
                  <div class="privacy-note"><i class="bx bx-shield-quarter"></i><div><strong>Privacidad por integrante</strong><p>Los nombres sin autorización quedan en el registro interno, pero no se entregan al sitio público.</p></div></div>
                </div>
              </section>
            </div>

            <footer class="organization-form__footer">
              <p><i class="bx bx-history"></i> Cada versión conserva su composición y período.</p>
              <div>
                <button type="button" @click="closeEditor">Cancelar</button>
                <button class="is-primary" type="submit" :disabled="saving"><i class="bx" :class="saving ? 'bx-loader-alt bx-spin' : 'bx-save'"></i>{{ saving ? "Guardando..." : "Guardar versión" }}</button>
              </div>
            </footer>
          </form>
        </section>
      </div>

      <div v-if="picker.open" class="organization-modal is-nested" role="dialog" aria-modal="true" aria-labelledby="person-picker-title">
        <section ref="pickerDialog" class="person-picker-dialog" tabindex="-1">
          <header><div><small>Registro institucional</small><h2 id="person-picker-title">Seleccionar persona</h2></div><button type="button" aria-label="Cerrar selector" @click="closePersonPicker"><i class="bx bx-x"></i></button></header>
          <nav v-if="pickerKindOptions.length > 1" class="person-kind-switcher" aria-label="Tipo de persona">
            <button v-for="kind in pickerKindOptions" :key="kind.value" type="button" :class="{ active: picker.kind === kind.value }" @click="changePickerKind(kind.value)">{{ kind.label }}</button>
          </nav>
          <form class="person-search" @submit.prevent="searchPeople(1)"><i class="bx bx-search"></i><input v-model.trim="picker.search" type="search" :placeholder="picker.kind === 'student' ? 'Buscar estudiante por nombre' : 'Buscar funcionario por nombre'" /><button type="submit">Buscar</button></form>
          <p class="person-privacy"><i class="bx bx-lock-alt"></i> La búsqueda solo muestra nombre y {{ picker.kind === "student" ? "curso vigente" : "cargo institucional" }}. No incluye RUT ni datos de contacto.</p>
          <LoadingState v-if="picker.loading" message="Buscando personas..." compact />
          <div v-else class="person-results">
            <button v-for="person in picker.results" :key="person.id" type="button" :disabled="personAlreadySelected(person)" @click="selectPerson(person)">
              <span>{{ String(person.name || person.full_name || "?").charAt(0).toUpperCase() }}</span>
              <div><strong>{{ person.name || person.full_name }}</strong><small>{{ picker.kind === "student" ? (person.course || person.course_name || "Sin curso vigente") : (person.position || person.position_name || "Sin cargo informado") }}</small></div>
              <i class="bx" :class="personAlreadySelected(person) ? 'bx-check-circle' : 'bx-plus-circle'"></i>
            </button>
            <div v-if="!picker.results.length" class="person-empty"><i class="bx bx-search-alt"></i><span>No encontramos coincidencias.</span></div>
          </div>
          <footer v-if="picker.lastPage > 1"><button type="button" :disabled="picker.page <= 1" @click="searchPeople(picker.page - 1)">Anterior</button><span>{{ picker.page }} / {{ picker.lastPage }}</span><button type="button" :disabled="picker.page >= picker.lastPage" @click="searchPeople(picker.page + 1)">Siguiente</button></footer>
        </section>
      </div>

      <div v-if="roleCreator.open" class="organization-modal is-nested" role="dialog" aria-modal="true" aria-labelledby="role-creator-title">
        <section ref="roleDialog" class="role-dialog" tabindex="-1">
          <header><div><small>Catálogo de cargos · {{ config.shortTitle }}</small><h2 id="role-creator-title">Crear cargo personalizado</h2></div><button type="button" aria-label="Cerrar creador de cargos" @click="closeRoleCreator"><i class="bx bx-x"></i></button></header>
          <div class="role-dialog__body">
            <label><span>Nombre del cargo</span><input v-model.trim="roleCreator.name" type="text" maxlength="100" placeholder="Ej.: Delegada de cultura" @keyup.enter="saveRole" /></label>
            <label>
              <span>Grupo</span>
              <select
                v-model="roleCreator.section"
                :disabled="organizationType === 'cde' && roleCreator.targetIndex !== null && form.members[roleCreator.targetIndex]?.member_kind === 'staff'"
              >
                <template v-if="organizationType === 'cde' && roleCreator.targetIndex !== null && form.members[roleCreator.targetIndex]?.member_kind === 'staff'">
                  <option value="advisor">Asesoría</option>
                </template>
                <template v-else>
                  <option value="leadership">Directiva</option>
                  <option v-if="organizationType === 'cde'" value="advisor">Asesoría</option>
                  <option value="member">Integrantes</option>
                </template>
              </select>
            </label>
          </div>
          <footer><button type="button" @click="closeRoleCreator">Cancelar</button><button class="is-primary" type="button" :disabled="roleCreator.saving || !roleCreator.name" @click="saveRole">{{ roleCreator.saving ? "Creando..." : "Crear cargo" }}</button></footer>
        </section>
      </div>

      <div v-if="showPreview && previewItem" class="organization-modal" role="dialog" aria-modal="true" aria-labelledby="organization-preview-title">
        <section ref="previewDialog" class="preview-dialog" tabindex="-1">
          <button class="preview-close" type="button" aria-label="Cerrar vista previa" @click="closePreview"><i class="bx bx-x"></i></button>
          <div class="preview-cover"><span><i class="bx" :class="config.icon"></i></span><small>{{ config.eyebrow }}</small><h2 id="organization-preview-title">{{ previewItem.name }}</h2><p>{{ previewItem.summary || "Comunidad organizada al servicio del proyecto educativo." }}</p><em>{{ periodLabel(previewItem) }}</em></div>
          <div class="preview-body">
            <header><div><small>Vista pública simulada</small><strong>Directiva e integrantes</strong></div><span>{{ statusLabel(previewItem.status) }}</span></header>
            <div v-if="authorizedMembers.length" class="preview-members">
              <article v-for="member in authorizedMembers" :key="member.id || `${member.display_name}-${member.sort_order}`"><span>{{ String(member.display_name || "?").charAt(0).toUpperCase() }}</span><div><strong>{{ member.display_name }}</strong><small>{{ roleName(member) }}</small></div></article>
            </div>
            <div v-else class="preview-empty"><i class="bx bx-hide"></i><span>No hay nombres autorizados para mostrar.</span></div>
            <p v-if="hiddenMemberCount" class="preview-privacy"><i class="bx bx-lock-alt"></i> {{ hiddenMemberCount }} {{ hiddenMemberCount === 1 ? "integrante permanece oculto" : "integrantes permanecen ocultos" }} por privacidad.</p>
          </div>
        </section>
      </div>
    </main>
  </Layout>
</template>

<style scoped>
.organization-manager {
  --org-navy-950: #062f43;
  --org-navy-900: #07394f;
  --org-blue-700: #0b6477;
  --org-green-500: #789978;
  --org-ink: #203f50;
  --org-muted: #687f8b;
  --org-line: #d9e6e9;
  min-height: 100vh;
  padding: 1rem 1rem 2.5rem;
  background: linear-gradient(180deg, #edf4f5 0, #f8fafb 310px, #f9fbfb 100%);
  color: var(--org-ink);
}

.organization-hero {
  display: grid;
  position: relative;
  overflow: hidden;
  align-items: center;
  gap: 1.5rem;
  padding: clamp(1.35rem, 2.7vw, 2rem);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 28px;
  background:
    radial-gradient(circle at 88% -35%, rgba(128, 162, 123, 0.72), transparent 43%),
    linear-gradient(116deg, #062f43, #0b6073 70%, #456f6a);
  color: #fff;
  box-shadow: 0 24px 52px rgba(7, 52, 70, 0.18);
  grid-template-columns: minmax(0, 1.5fr) minmax(330px, 0.7fr);
}

.organization-hero::after {
  position: absolute;
  right: -65px;
  bottom: -95px;
  width: 270px;
  height: 270px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  content: "";
  pointer-events: none;
}

.organization-hero__copy,
.organization-hero__metrics {
  position: relative;
  z-index: 1;
}

.organization-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: #efc77f;
  font-size: 0.64rem;
  font-weight: 850;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.organization-hero h1 {
  max-width: 760px;
  margin: 0.55rem 0 0.35rem;
  color: #fff;
  font-size: clamp(1.8rem, 3vw, 2.8rem);
  font-weight: 850;
  letter-spacing: -0.04em;
  line-height: 1.03;
}

.organization-hero p {
  max-width: 720px;
  margin: 0;
  color: rgba(255, 255, 255, 0.74);
  font-size: 0.78rem;
  line-height: 1.65;
}

.organization-switcher {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 1.15rem;
}

.organization-switcher a {
  border: 1px solid rgba(255, 255, 255, 0.17);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.07);
  padding: 0.43rem 0.72rem;
  color: rgba(255, 255, 255, 0.76);
  font-size: 0.62rem;
  font-weight: 800;
  transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
}

.organization-switcher a.active {
  border-color: rgba(255, 255, 255, 0.35);
  background: #fff;
  color: #0a5267;
}

.organization-hero__metrics {
  display: grid;
  align-self: center;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 20px;
  background: rgba(3, 39, 54, 0.3);
  backdrop-filter: blur(12px);
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.organization-hero__metrics article {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
  padding: 1rem 0.7rem;
  border-right: 1px solid rgba(255, 255, 255, 0.12);
}

.organization-hero__metrics article:last-child {
  border-right: 0;
}

.organization-hero__metrics article > span {
  display: grid;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  place-items: center;
  border-radius: 11px;
  background: rgba(255, 255, 255, 0.11);
  color: #f0c77e;
}

.organization-hero__metrics article > div {
  display: grid;
  min-width: 0;
}

.organization-hero__metrics strong {
  color: #fff;
  font-size: 1.12rem;
  line-height: 1;
}

.organization-hero__metrics small {
  color: rgba(255, 255, 255, 0.62);
  font-size: 0.52rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.organization-alert {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin: 0.75rem 0 0;
  padding: 0.7rem 0.8rem;
  border: 1px solid;
  border-radius: 13px;
  font-size: 0.7rem;
}

.organization-alert > i {
  font-size: 1.05rem;
}

.organization-alert button {
  margin-left: auto;
  border: 0;
  background: transparent;
  color: inherit;
}

.organization-alert.is-error {
  border-color: #eccad0;
  background: #fff4f5;
  color: #943846;
}

.organization-alert.is-success {
  border-color: #cbe5dd;
  background: #f1faf7;
  color: #176b5a;
}

.organization-toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 1rem;
  margin: 1rem 0;
  padding: 0.8rem;
  border: 1px solid var(--org-line);
  border-radius: 19px;
  background: #fff;
  box-shadow: 0 12px 30px rgba(24, 59, 72, 0.055);
}

.organization-filters {
  display: flex;
  align-items: end;
  gap: 0.55rem;
  min-width: 0;
}

.organization-filters label,
.organization-fields label,
.member-editor label,
.role-dialog label,
.editorial-panel > label:first-child {
  display: grid;
  align-content: start;
  gap: 0.28rem;
  min-width: 0;
}

.organization-filters label > span,
.organization-fields label > span,
.member-field > span,
.role-dialog label > span,
.editorial-panel > label:first-child > span {
  color: #587381;
  font-size: 0.56rem;
  font-weight: 850;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.organization-filters input,
.organization-filters select,
.organization-fields input,
.organization-fields select,
.organization-fields textarea,
.member-editor input,
.member-editor select,
.role-dialog input,
.role-dialog select,
.editorial-panel select {
  width: 100%;
  border: 1px solid #d5e2e6;
  border-radius: 10px;
  outline: 0;
  background: #fafcfc;
  padding: 0.58rem 0.65rem;
  color: #2a4b5c;
  font-size: 0.66rem;
}

.organization-filters input,
.organization-filters select {
  height: 39px;
}

.organization-filters input:focus,
.organization-filters select:focus,
.organization-fields input:focus,
.organization-fields select:focus,
.organization-fields textarea:focus,
.member-editor input:focus,
.member-editor select:focus,
.role-dialog input:focus,
.role-dialog select:focus {
  border-color: #2a8392;
  box-shadow: 0 0 0 3px rgba(17, 111, 128, 0.1);
}

.organization-search {
  position: relative;
  width: min(330px, 30vw);
}

.organization-search i {
  position: absolute;
  bottom: 11px;
  left: 0.7rem;
  color: #77909b;
}

.organization-search input {
  padding-left: 2rem;
}

.organization-filter-button {
  display: inline-flex;
  height: 39px;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 0 0.75rem;
  border: 0;
  border-radius: 10px;
  background: #e7f1f2;
  color: #0a6878;
  font-size: 0.62rem;
  font-weight: 800;
}

.organization-primary-action,
.organization-empty button {
  display: inline-flex;
  min-height: 45px;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.35rem 0.9rem 0.35rem 0.42rem;
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 999px;
  background: linear-gradient(108deg, #07475f, #0b7081 58%, #789978);
  color: #fff;
  font-size: 0.68rem;
  font-weight: 850;
  box-shadow: 0 12px 24px rgba(7, 73, 94, 0.19);
}

.organization-primary-action > span {
  display: grid;
  width: 33px;
  height: 33px;
  place-items: center;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.14);
  font-size: 1rem;
}

.organization-grid {
  display: grid;
  gap: 0.9rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.organization-card {
  display: flex;
  position: relative;
  overflow: hidden;
  min-width: 0;
  flex-direction: column;
  border: 1px solid var(--org-line);
  border-radius: 21px;
  background: #fff;
  box-shadow: 0 14px 32px rgba(21, 59, 74, 0.06);
}

.organization-card::before {
  position: absolute;
  top: 0;
  left: 25px;
  width: 58px;
  height: 3px;
  border-radius: 0 0 5px 5px;
  background: #c4d4d9;
  content: "";
}

.organization-card.is-published::before {
  background: linear-gradient(90deg, #0b7182, #7c9b79);
}

.organization-card > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 1rem 1rem 0.25rem;
}

.organization-period {
  color: #0b6879;
  font-size: 0.6rem;
  font-weight: 850;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.organization-status {
  padding: 0.25rem 0.48rem;
  border-radius: 999px;
  background: #eef2f3;
  color: #647680;
  font-size: 0.52rem;
  font-weight: 800;
}

.organization-status.is-published {
  background: #e4f4ee;
  color: #18785f;
}

.organization-status.is-archived {
  background: #f8eaec;
  color: #9a4350;
}

.organization-card__body {
  display: flex;
  flex: 1;
  flex-direction: column;
  padding: 0.65rem 1rem 1rem;
}

.organization-card__icon {
  display: grid;
  width: 47px;
  height: 47px;
  place-items: center;
  border-radius: 15px;
  background: linear-gradient(145deg, #e4f0f2, #f4f7f1);
  color: #0b6879;
  font-size: 1.25rem;
}

.organization-card h2 {
  margin: 0.7rem 0 0.25rem;
  color: var(--org-ink);
  font-size: 1rem;
  font-weight: 800;
}

.organization-card p {
  display: -webkit-box;
  overflow: hidden;
  min-height: 43px;
  margin: 0;
  color: var(--org-muted);
  font-size: 0.66rem;
  line-height: 1.55;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
}

.organization-members-preview {
  display: flex;
  align-items: center;
  margin-top: auto;
  padding-top: 1rem;
}

.organization-members-preview > span {
  display: grid;
  width: 29px;
  height: 29px;
  margin-left: -6px;
  place-items: center;
  border: 2px solid #fff;
  border-radius: 10px;
  background: #e5eff1;
  color: #0b6677;
  font-size: 0.58rem;
  font-weight: 850;
}

.organization-members-preview > span:first-child {
  margin-left: 0;
}

.organization-members-preview small {
  margin-left: 0.5rem;
  color: #758c97;
  font-size: 0.55rem;
}

.organization-card > footer {
  display: flex;
  gap: 0.35rem;
  padding: 0.65rem 0.75rem;
  border-top: 1px solid #edf2f3;
}

.organization-card > footer button {
  display: inline-flex;
  min-height: 32px;
  align-items: center;
  justify-content: center;
  gap: 0.28rem;
  padding: 0.35rem 0.55rem;
  border: 1px solid #dce7ea;
  border-radius: 10px;
  background: #fff;
  color: #496a78;
  font-size: 0.58rem;
  font-weight: 750;
}

.organization-card > footer button.is-danger {
  margin-left: auto;
  color: #96434f;
}

.organization-empty {
  display: grid;
  min-height: 320px;
  place-items: center;
  padding: 2rem;
  border: 1px dashed #c7d9dd;
  border-radius: 23px;
  background: linear-gradient(145deg, #fbfdfd, #f1f6f7);
  text-align: center;
}

.organization-empty > span {
  display: grid;
  width: 65px;
  height: 65px;
  place-items: center;
  border-radius: 20px;
  background: #e4eff1;
  color: #0b6979;
  font-size: 1.55rem;
}

.organization-empty h2 {
  margin: 0.8rem 0 0.2rem;
  color: var(--org-ink);
  font-size: 1.05rem;
}

.organization-empty p {
  margin: 0 0 1rem;
  color: var(--org-muted);
  font-size: 0.67rem;
}

.organization-empty button {
  padding: 0.55rem 0.85rem;
}

.organization-pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.65rem;
  margin-top: 1rem;
  padding-top: 0.8rem;
  border-top: 1px solid var(--org-line);
  color: #738a95;
  font-size: 0.6rem;
}

.organization-pagination button {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.45rem 0.65rem;
  border: 1px solid var(--org-line);
  border-radius: 10px;
  background: #fff;
  color: #36596a;
  font-size: 0.6rem;
}

.organization-pagination button:disabled {
  opacity: 0.4;
}

.organization-modal {
  --org-navy-950: #062f43;
  --org-navy-900: #07394f;
  --org-blue-700: #0b6477;
  --org-green-500: #789978;
  --org-ink: #203f50;
  --org-muted: #687f8b;
  --org-line: #d9e6e9;
  display: grid;
  position: fixed;
  z-index: 12000;
  inset: 0;
  place-items: center;
  padding: 1rem;
  background: rgba(2, 27, 39, 0.68);
  backdrop-filter: blur(7px);
}

.organization-modal.is-nested {
  z-index: 12010;
  background: rgba(2, 27, 39, 0.78);
}

:global(.premium-content-grid.site-organization-modal-layer) {
  z-index: 11990 !important;
  overflow: visible !important;
}

.organization-dialog,
.person-picker-dialog,
.role-dialog,
.preview-dialog {
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.25);
  background: #f6fafb;
  box-shadow: 0 38px 100px rgba(0, 20, 30, 0.38);
}

.organization-dialog {
  display: flex;
  width: min(1180px, calc(100vw - 2rem));
  max-height: calc(100vh - 2rem);
  flex-direction: column;
  border-radius: 25px;
}

.organization-dialog__header,
.person-picker-dialog > header,
.role-dialog > header {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  flex: 0 0 auto;
  padding: 1.05rem 1.2rem;
  background:
    radial-gradient(circle at 85% -50%, rgba(126, 158, 120, 0.62), transparent 42%),
    linear-gradient(110deg, #062f43, #0b6476);
  color: #fff;
}

.organization-dialog__header > span {
  display: grid;
  width: 46px;
  height: 46px;
  flex: 0 0 46px;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 15px;
  background: rgba(255, 255, 255, 0.11);
  font-size: 1.2rem;
}

.organization-dialog__header > div,
.person-picker-dialog > header > div,
.role-dialog > header > div {
  display: grid;
}

.organization-dialog__header small,
.person-picker-dialog > header small,
.role-dialog > header small {
  color: #efc77f;
  font-size: 0.54rem;
  font-weight: 850;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.organization-dialog__header h2,
.person-picker-dialog > header h2,
.role-dialog > header h2 {
  margin: 0.05rem 0;
  color: #fff;
  font-size: 1.1rem;
  font-weight: 800;
}

.organization-dialog__header p {
  margin: 0;
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.58rem;
}

.organization-dialog__header > button,
.person-picker-dialog > header > button,
.role-dialog > header > button {
  display: grid;
  width: 37px;
  height: 37px;
  margin-left: auto;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 11px;
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  font-size: 1.2rem;
}

.organization-form {
  display: flex;
  min-height: 0;
  flex: 1;
  flex-direction: column;
}

.organization-form__body {
  display: grid;
  gap: 0.8rem;
  overflow-y: auto;
  padding: 0.9rem;
}

.organization-form__section {
  min-width: 0;
  padding: 0.95rem;
  border: 1px solid var(--org-line);
  border-radius: 18px;
  background: #fff;
}

.organization-form__section > header {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin-bottom: 0.8rem;
}

.organization-form__section > header > span {
  display: grid;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  place-items: center;
  border-radius: 11px;
  background: #e7f1f2;
  color: #0b6979;
  font-size: 0.58rem;
  font-weight: 900;
}

.organization-form__section > header > div {
  display: grid;
}

.organization-form__section h3 {
  margin: 0;
  color: #244757;
  font-size: 0.79rem;
  font-weight: 800;
}

.organization-form__section header p {
  margin: 0.08rem 0 0;
  color: #768b95;
  font-size: 0.56rem;
}

.member-section-heading > button {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  margin-left: auto;
  padding: 0.45rem 0.68rem;
  border: 1px solid #cbdcdf;
  border-radius: 999px;
  background: #f3f8f8;
  color: #0a6979;
  font-size: 0.57rem;
  font-weight: 800;
}

.organization-fields {
  display: grid;
  gap: 0.65rem;
  grid-template-columns: minmax(0, 2fr) minmax(150px, 0.65fr);
}

.organization-fields.is-dates {
  grid-template-columns: minmax(0, 1.5fr) repeat(2, minmax(150px, 0.5fr));
}

.organization-fields .is-full {
  grid-column: 1 / -1;
}

.organization-fields textarea {
  resize: vertical;
  line-height: 1.5;
}

.organization-fields label > small,
.member-editor label > small {
  color: #a43b49;
  font-size: 0.53rem;
}

.has-error input,
.has-error select,
.has-error textarea {
  border-color: #d06974 !important;
}

.member-error {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 0.65rem;
  padding: 0.55rem 0.65rem;
  border: 1px solid #eccbd0;
  border-radius: 10px;
  background: #fff4f5;
  color: #963844;
  font-size: 0.58rem;
}

.member-editor-list {
  display: grid;
  gap: 0.55rem;
}

.member-editor {
  display: grid;
  align-items: end;
  gap: 0.5rem;
  padding: 0.65rem;
  border: 1px solid #dce7e9;
  border-radius: 15px;
  background: #fbfcfd;
  grid-template-columns: 37px minmax(180px, 1.25fr) minmax(130px, 0.65fr) auto minmax(120px, 0.55fr) minmax(185px, 0.8fr) 34px;
}

.member-editor.has-consent {
  border-color: #bedbd3;
  box-shadow: inset 3px 0 #5f9683;
}

.member-order {
  display: grid;
  align-self: center;
  gap: 0.15rem;
  grid-template-columns: 1fr 1fr;
}

.member-order strong {
  grid-column: 1 / -1;
  color: #2e5868;
  font-size: 0.63rem;
  text-align: center;
}

.member-order button {
  display: grid;
  width: 17px;
  height: 17px;
  place-items: center;
  border: 0;
  border-radius: 5px;
  background: #eaf1f2;
  color: #527380;
  font-size: 0.65rem;
}

.member-order button:disabled {
  opacity: 0.35;
}

.member-editor__identity {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
}

.member-avatar {
  display: grid;
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  place-items: center;
  border-radius: 12px;
  background: linear-gradient(145deg, #dcebed, #eef4ee);
  color: #0b6879;
  font-size: 0.68rem;
  font-weight: 850;
}

.member-editor__identity > div {
  display: grid;
  min-width: 0;
}

.member-editor__identity strong,
.member-editor__identity small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-editor__identity strong {
  color: #2c4d5c;
  font-size: 0.65rem;
}

.member-editor__identity small {
  color: #748a95;
  font-size: 0.54rem;
}

.member-editor input,
.member-editor select {
  height: 38px;
  padding: 0.5rem;
  font-size: 0.6rem;
}

.member-add-role {
  height: 38px;
  align-self: end;
  border: 1px solid #d4e2e5;
  border-radius: 10px;
  background: #eef5f5;
  color: #0b6879;
  font-size: 0.55rem;
  font-weight: 800;
}

.member-consent {
  display: grid !important;
  min-height: 38px;
  align-items: center !important;
  align-self: end;
  padding: 0.3rem 0.4rem;
  border: 1px solid #dce7e9;
  border-radius: 11px;
  background: #fff;
  cursor: pointer;
  grid-template-columns: 34px 1fr;
}

.member-consent input,
.active-check input {
  position: absolute;
  opacity: 0;
}

.member-consent > span {
  display: grid;
  width: 29px;
  height: 29px;
  place-items: center;
  border-radius: 9px;
  background: #edf2f3;
  color: #6e838e;
}

.member-consent input:checked + span,
.active-check input:checked + span {
  background: linear-gradient(145deg, #0b6e7f, #799a79);
  color: #fff;
}

.member-consent > div,
.active-check > div {
  display: grid;
}

.member-consent strong,
.active-check strong {
  color: #3a5c6b;
  font-size: 0.52rem;
}

.member-consent small {
  color: #748a95 !important;
  font-size: 0.47rem !important;
}

.member-remove {
  display: grid;
  width: 34px;
  height: 38px;
  place-items: center;
  border: 1px solid #ecdadd;
  border-radius: 10px;
  background: #fff8f8;
  color: #99424e;
}

.member-empty {
  display: grid;
  min-height: 150px;
  place-items: center;
  border: 1px dashed #c8dadd;
  border-radius: 15px;
  background: #f7fafb;
  text-align: center;
}

.member-empty > i {
  color: #76929c;
  font-size: 1.7rem;
}

.member-empty strong {
  margin-top: 0.25rem;
  color: #355765;
  font-size: 0.7rem;
}

.member-empty span {
  color: #758b96;
  font-size: 0.55rem;
}

.editorial-panel {
  display: grid;
  gap: 0.65rem;
  grid-template-columns: minmax(160px, 0.5fr) minmax(220px, 0.85fr) minmax(300px, 1.1fr);
}

.editorial-panel > label:first-child select {
  height: 42px;
}

.active-check {
  display: grid !important;
  align-items: center !important;
  padding: 0.5rem;
  border: 1px solid var(--org-line);
  border-radius: 12px;
  background: #fafcfc;
  cursor: pointer;
  grid-template-columns: 38px 1fr;
}

.active-check > span {
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border-radius: 10px;
  background: #e8eff1;
  color: #758a94;
}

.active-check small {
  color: #748a95;
  font-size: 0.5rem;
}

.privacy-note {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.55rem 0.65rem;
  border: 1px solid #d9e5e8;
  border-radius: 12px;
  background: #f4f8f8;
}

.privacy-note > i {
  display: grid;
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
  place-items: center;
  border-radius: 11px;
  background: #e3eff0;
  color: #0b6a7a;
  font-size: 1rem;
}

.privacy-note strong {
  color: #315463;
  font-size: 0.57rem;
}

.privacy-note p {
  margin: 0.1rem 0 0;
  color: #708792;
  font-size: 0.49rem;
  line-height: 1.4;
}

.organization-form__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex: 0 0 auto;
  padding: 0.7rem 0.9rem;
  border-top: 1px solid var(--org-line);
  background: #fff;
}

.organization-form__footer p {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  color: #738995;
  font-size: 0.54rem;
}

.organization-form__footer > div,
.role-dialog > footer {
  display: flex;
  gap: 0.4rem;
}

.organization-form__footer button,
.role-dialog > footer button {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  justify-content: center;
  gap: 0.3rem;
  padding: 0.42rem 0.78rem;
  border: 1px solid #d7e3e6;
  border-radius: 999px;
  background: #fff;
  color: #496a78;
  font-size: 0.58rem;
  font-weight: 800;
}

.organization-form__footer button.is-primary,
.role-dialog > footer button.is-primary {
  border-color: transparent;
  background: linear-gradient(108deg, #07465e, #0a7081 58%, #789978);
  color: #fff;
  box-shadow: 0 9px 20px rgba(7, 76, 96, 0.17);
}

.organization-form__footer button:disabled,
.role-dialog button:disabled {
  opacity: 0.5;
}

.person-picker-dialog,
.role-dialog {
  display: flex;
  width: min(720px, calc(100vw - 2rem));
  max-height: calc(100vh - 2rem);
  flex-direction: column;
  border-radius: 22px;
}

.person-kind-switcher {
  display: flex;
  gap: 0.35rem;
  padding: 0.65rem 0.8rem;
  border-bottom: 1px solid var(--org-line);
  background: #fff;
}

.person-kind-switcher button {
  padding: 0.42rem 0.68rem;
  border: 1px solid #d8e5e7;
  border-radius: 999px;
  background: #f5f8f9;
  color: #56727f;
  font-size: 0.58rem;
  font-weight: 800;
}

.person-kind-switcher button.active {
  border-color: #0b7081;
  background: #0b6174;
  color: #fff;
}

.person-search {
  display: grid;
  position: relative;
  gap: 0.45rem;
  padding: 0.75rem 0.8rem 0.55rem;
  background: #fff;
  grid-template-columns: minmax(0, 1fr) auto;
}

.person-search > i {
  position: absolute;
  top: 1.47rem;
  left: 1.5rem;
  color: #6f8994;
}

.person-search input {
  min-width: 0;
  height: 40px;
  padding: 0 0.7rem 0 2.15rem;
  border: 1px solid #d5e2e6;
  border-radius: 11px;
  background: #fafcfc;
  color: #2a4b5c;
  font-size: 0.65rem;
}

.person-search button {
  padding: 0 0.85rem;
  border: 0;
  border-radius: 11px;
  background: #e4eff1;
  color: #0a6778;
  font-size: 0.6rem;
  font-weight: 800;
}

.person-privacy {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  padding: 0.15rem 0.85rem 0.65rem;
  background: #fff;
  color: #6d8490;
  font-size: 0.52rem;
}

.person-results {
  display: grid;
  gap: 0.45rem;
  overflow-y: auto;
  padding: 0.7rem 0.8rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.person-results > button {
  display: grid;
  min-width: 0;
  align-items: center;
  gap: 0.55rem;
  padding: 0.55rem;
  border: 1px solid #dce7e9;
  border-radius: 13px;
  background: #fff;
  color: #2d5262;
  text-align: left;
  grid-template-columns: 37px minmax(0, 1fr) auto;
}

.person-results > button:disabled {
  opacity: 0.55;
}

.person-results > button > span {
  display: grid;
  width: 37px;
  height: 37px;
  place-items: center;
  border-radius: 12px;
  background: #e6f0f1;
  color: #0b6879;
  font-size: 0.66rem;
  font-weight: 850;
}

.person-results > button > div {
  display: grid;
  min-width: 0;
}

.person-results strong,
.person-results small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.person-results strong {
  font-size: 0.61rem;
}

.person-results small {
  color: #758a95;
  font-size: 0.52rem;
}

.person-results > button > i {
  color: #0b7080;
  font-size: 1.05rem;
}

.person-empty {
  display: grid;
  min-height: 120px;
  place-items: center;
  color: #748b96;
  font-size: 0.58rem;
  grid-column: 1 / -1;
}

.person-empty i {
  font-size: 1.5rem;
}

.person-picker-dialog > footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 0.6rem 0.8rem;
  border-top: 1px solid var(--org-line);
  background: #fff;
  color: #758b95;
  font-size: 0.56rem;
}

.person-picker-dialog > footer button {
  padding: 0.35rem 0.55rem;
  border: 1px solid var(--org-line);
  border-radius: 9px;
  background: #fff;
  color: #426573;
  font-size: 0.54rem;
}

.role-dialog {
  width: min(540px, calc(100vw - 2rem));
}

.role-dialog__body {
  display: grid;
  gap: 0.65rem;
  padding: 1rem;
  grid-template-columns: 1.4fr 0.8fr;
}

.role-dialog input,
.role-dialog select {
  height: 40px;
}

.role-dialog > footer {
  justify-content: flex-end;
  padding: 0.65rem 0.8rem;
  border-top: 1px solid var(--org-line);
  background: #fff;
}

.preview-dialog {
  display: grid;
  position: relative;
  width: min(900px, calc(100vw - 2rem));
  max-height: calc(100vh - 2rem);
  overflow-y: auto;
  border-radius: 25px;
  background: #fff;
  grid-template-columns: minmax(280px, 0.75fr) minmax(360px, 1fr);
}

.preview-close {
  display: grid;
  position: absolute;
  z-index: 2;
  top: 0.75rem;
  right: 0.75rem;
  width: 37px;
  height: 37px;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  background: rgba(5, 45, 59, 0.65);
  color: #fff;
  font-size: 1.1rem;
}

.preview-cover {
  display: flex;
  min-height: 430px;
  align-items: flex-start;
  flex-direction: column;
  justify-content: center;
  padding: 2rem;
  background:
    radial-gradient(circle at 90% 10%, rgba(127, 160, 122, 0.65), transparent 35%),
    linear-gradient(140deg, #062f43, #0b6375);
  color: #fff;
}

.preview-cover > span {
  display: grid;
  width: 64px;
  height: 64px;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.1);
  font-size: 1.7rem;
}

.preview-cover small {
  margin-top: 1.2rem;
  color: #efc77e;
  font-size: 0.57rem;
  font-weight: 850;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.preview-cover h2 {
  margin: 0.35rem 0;
  color: #fff;
  font-size: 1.8rem;
  line-height: 1.1;
}

.preview-cover p {
  margin: 0;
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.69rem;
  line-height: 1.6;
}

.preview-cover em {
  margin-top: 1.2rem;
  padding: 0.38rem 0.62rem;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 999px;
  color: #fff;
  font-size: 0.55rem;
  font-style: normal;
}

.preview-body {
  padding: 2rem 1.4rem 1.4rem;
}

.preview-body > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.7rem;
  margin-bottom: 1rem;
}

.preview-body > header > div {
  display: grid;
}

.preview-body > header small {
  color: #b97c2c;
  font-size: 0.52rem;
  font-weight: 850;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.preview-body > header strong {
  color: #294c5b;
  font-size: 0.86rem;
}

.preview-body > header > span {
  padding: 0.3rem 0.5rem;
  border-radius: 999px;
  background: #e5f4ef;
  color: #1d7963;
  font-size: 0.52rem;
  font-weight: 800;
}

.preview-members {
  display: grid;
  gap: 0.45rem;
}

.preview-members article {
  display: grid;
  align-items: center;
  gap: 0.55rem;
  padding: 0.55rem;
  border: 1px solid #e0e9eb;
  border-radius: 13px;
  grid-template-columns: 40px 1fr;
}

.preview-members article > span {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  border-radius: 12px;
  background: #e5eff1;
  color: #0b6979;
  font-size: 0.66rem;
  font-weight: 850;
}

.preview-members article > div {
  display: grid;
}

.preview-members strong {
  color: #345563;
  font-size: 0.62rem;
}

.preview-members small {
  color: #748a95;
  font-size: 0.51rem;
}

.preview-empty {
  display: grid;
  min-height: 180px;
  place-items: center;
  border: 1px dashed #cbdadd;
  border-radius: 15px;
  background: #f7fafb;
  color: #748a95;
  font-size: 0.58rem;
}

.preview-empty i {
  font-size: 1.5rem;
}

.preview-privacy {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  margin: 0.8rem 0 0;
  color: #6c8490;
  font-size: 0.51rem;
}

.organization-manager :is(button,a,input,select,textarea,[tabindex]):focus-visible{outline:3px solid rgba(57,164,182,.45);outline-offset:2px}.member-consent:focus-within,.active-check:focus-within{border-color:#68a9b3;box-shadow:0 0 0 3px rgba(17,111,128,.12)}
@media(max-width:1280px){.organization-hero{grid-template-columns:1fr}.organization-hero__metrics{max-width:460px}.organization-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.member-editor{grid-template-columns:35px minmax(190px,1fr) repeat(2,minmax(130px,.65fr)) 34px}.member-consent{grid-column:2/-2}.member-remove{grid-column:-2/-1;grid-row:1}.member-add-role{grid-row:2}}
@media(max-width:900px){.organization-toolbar{align-items:stretch;flex-direction:column}.organization-filters{display:grid;grid-template-columns:1fr 1fr}.organization-search{grid-column:1/-1;width:auto}.organization-primary-action{align-self:flex-end}.editorial-panel{grid-template-columns:1fr 1fr}.privacy-note{grid-column:1/-1}.preview-dialog{grid-template-columns:1fr;max-width:650px}.preview-cover{min-height:280px}.organization-fields.is-dates{grid-template-columns:1fr 1fr}.organization-fields.is-dates>label:first-child{grid-column:1/-1}}
@media(max-width:767.98px){.organization-manager{padding:.7rem .6rem 1.8rem}.organization-hero{border-radius:22px;padding:1.2rem}.organization-hero__metrics{grid-template-columns:repeat(3,minmax(0,1fr));width:100%}.organization-hero__metrics article{align-items:flex-start;flex-direction:column}.organization-switcher{flex-wrap:nowrap;overflow:auto}.organization-switcher a{flex:0 0 auto}.organization-filters{grid-template-columns:1fr}.organization-search{grid-column:auto}.organization-filter-button{width:100%}.organization-primary-action{width:100%}.organization-grid{grid-template-columns:1fr}.organization-dialog,.person-picker-dialog,.role-dialog,.preview-dialog{width:100%;max-height:100vh;border-radius:0}.organization-modal{place-items:stretch;padding:0}.organization-dialog__header p{display:none}.organization-form__body{padding:.65rem}.organization-form__section{padding:.75rem}.organization-fields,.organization-fields.is-dates,.editorial-panel,.role-dialog__body{grid-template-columns:1fr}.organization-fields.is-dates>label:first-child{grid-column:auto}.member-section-heading{align-items:flex-start!important;flex-wrap:wrap}.member-section-heading>button{width:100%;margin-left:0;justify-content:center}.member-editor{grid-template-columns:34px minmax(0,1fr) 34px;align-items:start}.member-editor__identity,.member-field,.member-add-role,.member-consent{grid-column:2/3}.member-remove{grid-column:3/4;grid-row:1}.member-add-role{grid-row:auto}.member-consent{min-height:45px}.organization-form__footer{align-items:stretch;flex-direction:column}.organization-form__footer p{display:none}.organization-form__footer>div{display:grid;grid-template-columns:.8fr 1.2fr}.organization-form__footer button{width:100%}.person-results{grid-template-columns:1fr}.person-picker-dialog>header,.role-dialog>header{padding:.85rem}.preview-cover{min-height:250px;padding:1.4rem}.preview-body{padding:1.25rem .9rem}.preview-close{background:rgba(5,45,59,.82)}}
@media(prefers-reduced-motion:reduce){.organization-manager *{scroll-behavior:auto!important;transition:none!important}}
</style>
