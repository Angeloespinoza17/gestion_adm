<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { formatRemunerationError } from "../../components/remuneration/module-utils";

const emptyRequirement = () => ({
  code: "",
  name: "",
  description: "",
  requires_delivery: true,
  requires_signature: false,
  validity_mode: "none",
  validity_months: 12,
  alert_days: 30,
  is_required: true,
  active: true,
  sort_order: 100,
});

const emptyCompliance = () => ({
  issued_at: "",
  expires_at: "",
  delivered: false,
  delivered_on: "",
  signed: false,
  signed_on: "",
  remove_file: false,
  notes: "",
  file: null,
});

export default {
  components: { Layout, PageHeader, LoadingState },
  data() {
    return {
      activeTab: "staff",
      staff: [],
      requirements: [],
      summary: { staff: 0, requirements: 0, expected: 0, current: 0, expiring: 0, expired: 0, pending: 0 },
      pagination: { current_page: 1, last_page: 1, total: 0, from: null, to: null },
      filters: { search: "", status: "" },
      isLoading: true,
      isRefreshingStaff: false,
      staffRequestSequence: 0,
      isSaving: false,
      error: null,
      requirementModal: false,
      staffDetailModal: false,
      staffDetailTab: "documents",
      complianceModal: false,
      editingRequirementId: null,
      requirementForm: emptyRequirement(),
      complianceForm: emptyCompliance(),
      selectedStaff: null,
      selectedDocument: null,
      staffPayslips: [],
      staffPayslipTotal: 0,
      isLoadingPayslips: false,
      payslipAccessDenied: false,
      payslipError: null,
    };
  },
  computed: {
    activeRequirements() {
      return this.requirements.filter((item) => item.active);
    },
    selectedStaffDocuments() {
      return this.selectedStaff?.documents || [];
    },
    activeDocumentFiltersCount() {
      return [this.filters.search, this.filters.status].filter(Boolean).length;
    },
    documentStatusTabs() {
      return [
        { value: "", label: "Todos", count: this.summary.staff },
        { value: "pending", label: "Pendientes", count: this.summary.pending },
        { value: "expiring", label: "Por vencer", count: this.summary.expiring },
        { value: "expired", label: "Vencidos", count: this.summary.expired },
        { value: "current", label: "Al día", count: this.summary.current },
      ];
    },
    selectedRequirement() {
      return this.selectedDocument?.requirement || null;
    },
    completionProgress() {
      const complete = Number(this.summary.current || 0) + Number(this.summary.expiring || 0);
      const total = Number(this.summary.expected || 0);
      return total ? Math.round((complete / total) * 100) : 0;
    },
  },
  mounted() {
    this.loadInitial();
  },
  methods: {
    async loadInitial() {
      this.isLoading = true;
      this.error = null;
      try {
        await Promise.all([this.loadRequirements(), this.loadStaff(1, false)]);
      } catch (error) {
        this.error = formatRemunerationError(error);
      } finally {
        this.isLoading = false;
      }
    },
    async loadRequirements() {
      const response = await axios.get("/api/remuneraciones/documents/requirements", {
        params: { include_inactive: 1 },
      });
      this.requirements = response.data.data || [];
    },
    async loadStaff(page = 1, manageLoading = true) {
      const requestSequence = ++this.staffRequestSequence;
      const requestedSearch = this.filters.search || undefined;
      const requestedStatus = this.filters.status || undefined;
      if (manageLoading) this.isRefreshingStaff = true;
      if (requestSequence === this.staffRequestSequence) this.error = null;
      try {
        const response = await axios.get("/api/remuneraciones/documents/staff", {
          params: {
            search: requestedSearch,
            status: requestedStatus,
            page,
            per_page: 12,
          },
        });
        if (requestSequence !== this.staffRequestSequence) return;
        this.staff = response.data.data || [];
        if (!requestedStatus) {
          this.summary = response.data.summary || this.summary;
        }
        this.pagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          total: response.data.total || 0,
          from: response.data.from,
          to: response.data.to,
        };
      } catch (error) {
        if (requestSequence === this.staffRequestSequence) {
          this.error = formatRemunerationError(error);
        }
      } finally {
        if (manageLoading && requestSequence === this.staffRequestSequence) {
          this.isRefreshingStaff = false;
        }
      }
    },
    changeTab(tab) {
      this.activeTab = tab;
      this.error = null;
    },
    setStatusFilter(status) {
      if (this.filters.status === status) return;
      this.filters.status = status;
      this.loadStaff(1);
    },
    resetStaffFilters() {
      this.filters = { search: "", status: "" };
      this.loadStaff(1);
    },
    openStaffDetail(person) {
      this.selectedStaff = person;
      this.staffDetailTab = "documents";
      this.staffPayslips = [];
      this.staffPayslipTotal = 0;
      this.payslipAccessDenied = false;
      this.payslipError = null;
      this.staffDetailModal = true;
      this.loadStaffPayslips(person.id);
    },
    closeStaffDetail() {
      if (this.isSaving) return;
      this.staffDetailModal = false;
    },
    selectStaffDetailTab(tab) {
      this.staffDetailTab = tab;
    },
    async loadStaffPayslips(staffId) {
      this.isLoadingPayslips = true;
      this.payslipAccessDenied = false;
      this.payslipError = null;
      try {
        const response = await axios.get("/api/remuneraciones/liquidaciones-sueldo/history", {
          params: { staff_id: staffId, per_page: 36 },
        });
        this.staffPayslips = response.data.data || [];
        this.staffPayslipTotal = Number(response.data.total || this.staffPayslips.length);
      } catch (error) {
        this.staffPayslips = [];
        this.staffPayslipTotal = 0;
        if (Number(error?.response?.status) === 403) {
          this.payslipAccessDenied = true;
        } else {
          this.payslipError = formatRemunerationError(error);
        }
      } finally {
        this.isLoadingPayslips = false;
      }
    },
    openRequirement(requirement = null) {
      this.editingRequirementId = requirement?.id || null;
      this.requirementForm = requirement
        ? {
            code: requirement.code || "",
            name: requirement.name || "",
            description: requirement.description || "",
            requires_delivery: Boolean(requirement.requires_delivery),
            requires_signature: Boolean(requirement.requires_signature),
            validity_mode: requirement.validity_mode || "none",
            validity_months: requirement.validity_months || 12,
            alert_days: requirement.alert_days ?? 30,
            is_required: Boolean(requirement.is_required),
            active: Boolean(requirement.active),
            sort_order: requirement.sort_order ?? 100,
          }
        : emptyRequirement();
      this.requirementModal = true;
    },
    closeRequirement() {
      if (this.isSaving) return;
      this.requirementModal = false;
    },
    async saveRequirement() {
      if (!this.requirementForm.requires_delivery && !this.requirementForm.requires_signature) {
        await Swal.fire("Falta una acción", "Selecciona entrega, firma o ambas.", "warning");
        return;
      }

      this.isSaving = true;
      try {
        const url = this.editingRequirementId
          ? `/api/remuneraciones/documents/requirements/${this.editingRequirementId}`
          : "/api/remuneraciones/documents/requirements";
        const method = this.editingRequirementId ? "put" : "post";
        await axios[method](url, this.requirementForm);
        this.requirementModal = false;
        await Promise.all([this.loadRequirements(), this.loadStaff(1)]);
        await Swal.fire("Guardado", "La definición documental quedó disponible para la nómina activa.", "success");
      } catch (error) {
        await Swal.fire("No se pudo guardar", formatRemunerationError(error), "error");
      } finally {
        this.isSaving = false;
      }
    },
    async archiveRequirement(requirement) {
      const result = await Swal.fire({
        title: "Archivar documento",
        text: "Dejará de exigirse a la nómina activa, pero su historial se conservará.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Archivar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;

      try {
        const response = await axios.delete(`/api/remuneraciones/documents/requirements/${requirement.id}`);
        await Promise.all([this.loadRequirements(), this.loadStaff(1)]);
        await Swal.fire("Listo", response.data.message, "success");
      } catch (error) {
        await Swal.fire("No se pudo archivar", formatRemunerationError(error), "error");
      }
    },
    openCompliance(person, document) {
      const compliance = document.compliance || {};
      this.selectedStaff = person;
      this.selectedDocument = document;
      this.staffDetailModal = false;
      this.complianceForm = {
        issued_at: compliance.issued_at || "",
        expires_at: compliance.expires_at || "",
        delivered: Boolean(compliance.delivered_at),
        delivered_on: this.datePart(compliance.delivered_at),
        signed: Boolean(compliance.signed_at),
        signed_on: this.datePart(compliance.signed_at),
        remove_file: false,
        notes: compliance.notes || "",
        file: null,
      };
      this.complianceModal = true;
    },
    closeCompliance() {
      if (this.isSaving) return;
      this.complianceModal = false;
    },
    handleFile(event) {
      this.complianceForm.file = event.target.files?.[0] || null;
      if (this.complianceForm.file) this.complianceForm.remove_file = false;
    },
    async saveCompliance() {
      const form = new FormData();
      ["issued_at", "expires_at", "delivered_on", "signed_on", "notes"].forEach((field) => {
        if (this.complianceForm[field]) form.append(field, this.complianceForm[field]);
      });
      form.append("delivered", this.complianceForm.delivered ? "1" : "0");
      form.append("signed", this.complianceForm.signed ? "1" : "0");
      form.append("remove_file", this.complianceForm.remove_file ? "1" : "0");
      if (this.complianceForm.file) form.append("file", this.complianceForm.file);

      this.isSaving = true;
      try {
        await axios.post(
          `/api/remuneraciones/documents/staff/${this.selectedStaff.id}/requirements/${this.selectedRequirement.id}`,
          form,
        );
        this.complianceModal = false;
        await this.loadStaff(this.pagination.current_page);
        await Swal.fire("Actualizado", "La entrega, firma y vigencia quedaron registradas.", "success");
      } catch (error) {
        await Swal.fire("No se pudo actualizar", formatRemunerationError(error), "error");
      } finally {
        this.isSaving = false;
      }
    },
    async downloadFile(compliance) {
      if (!compliance?.download_url) return;
      try {
        const response = await axios.get(compliance.download_url, { responseType: "blob" });
        const url = URL.createObjectURL(response.data);
        const anchor = document.createElement("a");
        anchor.href = url;
        anchor.download = compliance.original_name || "documento";
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
      } catch (error) {
        await Swal.fire("No se pudo descargar", formatRemunerationError(error), "error");
      }
    },
    initials(name) {
      return String(name || "F")
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
    },
    datePart(value) {
      return value ? String(value).slice(0, 10) : "";
    },
    formatDate(value) {
      if (!value) return "Sin fecha";
      return new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "short", year: "numeric" })
        .format(new Date(`${this.datePart(value)}T12:00:00`));
    },
    formatBytes(value) {
      const bytes = Number(value || 0);
      if (!bytes) return "";
      if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
      return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    },
    formatCurrency(value) {
      return new Intl.NumberFormat("es-CL", {
        style: "currency",
        currency: "CLP",
        maximumFractionDigits: 0,
      }).format(Number(value || 0));
    },
    payslipPeriodLabel(payslip) {
      if (payslip.period) return payslip.period;
      if (!payslip.year || !payslip.month) return "Período no informado";
      const date = new Date(Number(payslip.year), Number(payslip.month) - 1, 1);
      const month = new Intl.DateTimeFormat("es-CL", { month: "long" }).format(date);
      return `${month.charAt(0).toUpperCase()}${month.slice(1)} ${payslip.year}`;
    },
    payslipStatusLabel(status) {
      return {
        importada: "Importada",
        borrador: "Borrador",
        anulada: "Anulada",
        pendiente: "Pendiente",
      }[status] || status || "Sin estado";
    },
    staffProgress(person) {
      const total = Number(person?.document_summary?.total || 0);
      return total ? Math.round((Number(person.document_summary.current || 0) / total) * 100) : 0;
    },
    staffOverallStatus(person) {
      const summary = person?.document_summary || {};
      if (Number(summary.expired || 0) > 0) return "expired";
      if (Number(summary.expiring || 0) > 0) return "expiring";
      if (Number(summary.pending || 0) > 0) return "pending";
      if (Number(summary.total || 0) > 0) return "current";
      return "empty";
    },
    staffOverallLabel(person) {
      return {
        expired: "Con documentos vencidos",
        expiring: "Con vencimientos próximos",
        pending: "Con documentos pendientes",
        current: "Documentación al día",
        empty: "Sin requisitos definidos",
      }[this.staffOverallStatus(person)];
    },
    validityLabel(requirement) {
      if (requirement.validity_mode === "months") {
        return `${requirement.validity_months} ${Number(requirement.validity_months) === 1 ? "mes" : "meses"}`;
      }
      if (requirement.validity_mode === "manual") return "Fecha por funcionario";
      return "Sin vencimiento";
    },
    actionLabel(requirement) {
      if (requirement.requires_delivery && requirement.requires_signature) return "Entrega y firma";
      return requirement.requires_signature ? "Requiere firma" : "Requiere entrega";
    },
    isDocumentComplete(document) {
      return ["current", "expiring", "expired"].includes(document?.status);
    },
    expiryLabel(document) {
      if (document?.compliance?.expires_at) return this.formatDate(document.compliance.expires_at);
      if (document?.requirement?.validity_mode === "none") return "Sin vencimiento";
      return "Fecha pendiente";
    },
    statusLabel(status) {
      return {
        current: "Al día",
        expiring: "Por vencer",
        expired: "Vencido",
        pending_delivery: "Pendiente de entrega",
        pending_signature: "Pendiente de firma",
        pending_both: "Pendiente de entrega y firma",
        pending: "Pendiente",
      }[status] || status;
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Documentos" pageTitle="Remuneraciones" />

    <main class="rem-docs-page">
      <section class="rem-docs-hero">
        <div class="rem-docs-hero__content">
          <span class="rem-docs-eyebrow"><i class="bx bx-lock-alt"></i> Gestión documental confidencial</span>
          <h1>Documentos de funcionarios, claros y al día</h1>
          <p>Define lo que debe entregarse o firmarse y controla su vigencia desde un solo lugar.</p>
          <div class="rem-docs-progress" aria-label="Avance documental">
            <div class="rem-docs-progress__copy">
              <span>Cumplimiento vigente</span>
              <strong>{{ completionProgress }}%</strong>
            </div>
            <div class="rem-docs-progress__track"><span :style="{ width: `${completionProgress}%` }"></span></div>
          </div>
        </div>
        <div class="rem-docs-hero__visual" aria-hidden="true">
          <span class="rem-docs-file rem-docs-file--back"><i class="bx bx-check-shield"></i></span>
          <span class="rem-docs-file rem-docs-file--front"><i class="bx bx-file"></i><b></b><b></b><b></b></span>
          <span class="rem-docs-sign"><i class="bx bx-pen"></i></span>
        </div>
      </section>

      <section class="rem-docs-metrics" aria-label="Resumen documental">
        <article>
          <span class="metric-icon metric-icon--blue"><i class="bx bx-group"></i></span>
          <div><strong>{{ summary.staff }}</strong><span>Funcionarios</span></div>
        </article>
        <article>
          <span class="metric-icon metric-icon--green"><i class="bx bx-check-circle"></i></span>
          <div><strong>{{ summary.current }}</strong><span>Documentos al día</span></div>
        </article>
        <article>
          <span class="metric-icon metric-icon--amber"><i class="bx bx-time-five"></i></span>
          <div><strong>{{ summary.pending }}</strong><span>Pendientes</span></div>
        </article>
        <article>
          <span class="metric-icon metric-icon--red"><i class="bx bx-error-circle"></i></span>
          <div><strong>{{ Number(summary.expiring || 0) + Number(summary.expired || 0) }}</strong><span>Alertas de vigencia</span></div>
        </article>
      </section>

      <nav class="rem-docs-tabs" aria-label="Navegación de documentos">
        <button type="button" :class="{ active: activeTab === 'staff' }" @click="changeTab('staff')">
          <span><i class="bx bx-id-card"></i></span>
          <span><strong>Listado de funcionarios</strong><small>Entregas, firmas y vencimientos</small></span>
        </button>
        <button type="button" :class="{ active: activeTab === 'requirements' }" @click="changeTab('requirements')">
          <span><i class="bx bx-file-find"></i></span>
          <span><strong>Crear documentos</strong><small>Requisitos y reglas de vigencia</small></span>
        </button>
      </nav>

      <div v-if="error" class="rem-docs-alert"><i class="bx bx-error-circle"></i><span>{{ error }}</span></div>

      <template v-if="activeTab === 'staff'">
        <section class="staff-list-panel">
          <header class="staff-list-panel__head">
            <div>
              <span class="staff-list-eyebrow">Listado</span>
              <h2>Documentos por funcionario</h2>
            </div>
            <span class="staff-list-filter-count" :class="{ active: activeDocumentFiltersCount > 0 }">
              {{ activeDocumentFiltersCount }} {{ activeDocumentFiltersCount === 1 ? "filtro" : "filtros" }}
            </span>
          </header>

          <div class="staff-list-status-tabs" role="tablist" aria-label="Estados documentales">
            <button
              v-for="tab in documentStatusTabs"
              :key="tab.value || 'all'"
              type="button"
              role="tab"
              :aria-selected="filters.status === tab.value"
              :class="{ active: filters.status === tab.value }"
              @click="setStatusFilter(tab.value)"
            >
              <span>{{ tab.label }}</span><strong>{{ tab.count }}</strong>
            </button>
          </div>

          <form class="staff-list-filters" @submit.prevent="loadStaff(1)">
            <label class="staff-list-filter-field staff-list-filter-field--search">
              <span>Búsqueda</span>
              <input v-model.trim="filters.search" type="search" placeholder="Buscar nombre, RUT o cargo" aria-label="Búsqueda" />
            </label>
            <label class="staff-list-filter-field">
              <span>Estado</span>
              <select v-model="filters.status" aria-label="Estado">
                <option value="">Todos</option>
                <option value="pending">Pendientes</option>
                <option value="expiring">Por vencer</option>
                <option value="expired">Vencidos</option>
                <option value="current">Al día</option>
              </select>
            </label>
            <div class="staff-list-filter-actions">
              <button class="staff-list-primary-button" type="submit"><i class="mdi mdi-filter-outline"></i> Filtrar</button>
              <button class="staff-list-secondary-button" type="button" @click="resetStaffFilters">Limpiar</button>
            </div>
          </form>

          <LoadingState v-if="isLoading && !staff.length" message="Revisando documentos de funcionarios..." />

          <div
            v-else-if="staff.length"
            class="staff-list-table-wrap"
            :class="{ 'is-refreshing': isRefreshingStaff }"
            :aria-busy="isRefreshingStaff"
          >
            <span v-if="isRefreshingStaff" class="visually-hidden" role="status">Actualizando listado de funcionarios</span>
            <table class="staff-list-table">
              <caption class="visually-hidden">Resumen documental por funcionario</caption>
              <colgroup>
                <col class="staff-list-col-person" />
                <col class="staff-list-col-progress" />
                <col class="staff-list-col-alerts" />
                <col class="staff-list-col-status" />
                <col class="staff-list-col-actions" />
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">Funcionario</th>
                  <th scope="col">Cumplimiento</th>
                  <th scope="col">Alertas</th>
                  <th scope="col">Estado general</th>
                  <th scope="col" class="staff-list-actions-heading">Detalle</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="person in staff" :key="person.id">
                  <td class="staff-list-person-cell">
                    <div class="staff-list-person">
                      <span class="staff-list-avatar">{{ initials(person.full_name) }}</span>
                      <div>
                        <strong>{{ person.full_name }}</strong>
                        <span>{{ person.rut || "Sin RUT" }} · {{ person.position || "Cargo no informado" }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="staff-list-progress-cell">
                    <div class="staff-progress-copy">
                      <strong>{{ person.document_summary.current }}/{{ person.document_summary.total }}</strong>
                      <span>{{ staffProgress(person) }}%</span>
                    </div>
                    <div class="staff-progress-track"><span :style="{ width: `${staffProgress(person)}%` }"></span></div>
                    <small>{{ person.document_summary.pending }} pendientes</small>
                  </td>
                  <td class="staff-list-alerts-cell">
                    <span v-if="person.document_summary.expired" class="staff-alert-chip is-expired"><i class="bx bx-error-circle"></i>{{ person.document_summary.expired }} vencidos</span>
                    <span v-if="person.document_summary.expiring" class="staff-alert-chip is-expiring"><i class="bx bx-time-five"></i>{{ person.document_summary.expiring }} por vencer</span>
                    <span v-if="!person.document_summary.expired && !person.document_summary.expiring" class="staff-alert-chip is-clear"><i class="bx bx-check-circle"></i>Sin alertas</span>
                  </td>
                  <td class="staff-list-status-cell">
                    <span class="staff-overall-status" :class="`is-${staffOverallStatus(person)}`">{{ staffOverallLabel(person) }}</span>
                  </td>
                  <td class="staff-list-actions-cell">
                    <button type="button" class="staff-list-detail-button" @click="openStaffDetail(person)">
                      <i class="bx bx-folder-open"></i><span>Ver documentos</span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <section v-else-if="!isLoading" class="rem-docs-empty">
            <span><i class="bx bx-folder-open"></i></span>
            <h2>{{ activeRequirements.length ? "No encontramos funcionarios" : "Primero crea un documento" }}</h2>
            <p>{{ activeRequirements.length ? "Prueba con otra búsqueda o filtro de estado." : "El catálogo define lo que debe entregar o firmar cada funcionario." }}</p>
            <button v-if="!activeRequirements.length" type="button" @click="changeTab('requirements'); openRequirement()">Crear primer documento</button>
          </section>

          <footer v-if="pagination.last_page > 1 && !isLoading" class="rem-docs-pagination">
            <span>Mostrando {{ pagination.from }}–{{ pagination.to }} de {{ pagination.total }}</span>
            <div>
              <button type="button" :disabled="isRefreshingStaff || pagination.current_page <= 1" @click="loadStaff(pagination.current_page - 1)"><i class="bx bx-chevron-left"></i></button>
              <strong>{{ pagination.current_page }} / {{ pagination.last_page }}</strong>
              <button type="button" :disabled="isRefreshingStaff || pagination.current_page >= pagination.last_page" @click="loadStaff(pagination.current_page + 1)"><i class="bx bx-chevron-right"></i></button>
            </div>
          </footer>
        </section>
      </template>

      <template v-else>
        <section class="requirements-heading">
          <div>
            <span class="section-kicker">Catálogo institucional</span>
            <h2>Documentos y firmas requeridas</h2>
            <p>Cada requisito activo aparecerá automáticamente en el listado de todos los funcionarios activos.</p>
          </div>
          <button type="button" class="primary-action" @click="openRequirement()"><i class="bx bx-plus"></i> Crear documento</button>
        </section>

        <LoadingState v-if="isLoading" message="Cargando catálogo documental..." />
        <section v-else-if="requirements.length" class="requirements-grid">
          <article v-for="requirement in requirements" :key="requirement.id" class="requirement-card" :class="{ archived: !requirement.active }">
            <header>
              <span class="requirement-card__icon"><i :class="requirement.requires_signature ? 'bx bx-pen' : 'bx bx-file-blank'"></i></span>
              <span v-if="!requirement.active" class="requirement-card__archived">Archivado</span>
              <span v-else-if="requirement.is_required" class="requirement-card__required">Obligatorio</span>
            </header>
            <h3>{{ requirement.name }}</h3>
            <p>{{ requirement.description || "Sin descripción adicional." }}</p>
            <div class="requirement-card__tags">
              <span><i class="bx bx-check-square"></i> {{ actionLabel(requirement) }}</span>
              <span><i class="bx bx-calendar"></i> {{ validityLabel(requirement) }}</span>
              <span v-if="requirement.validity_mode !== 'none'"><i class="bx bx-bell"></i> Alerta {{ requirement.alert_days }} días antes</span>
            </div>
            <footer>
              <span>{{ requirement.controls_count }} registros</span>
              <div>
                <button type="button" title="Editar documento" @click="openRequirement(requirement)"><i class="bx bx-edit-alt"></i></button>
                <button v-if="requirement.active" type="button" title="Archivar documento" @click="archiveRequirement(requirement)"><i class="bx bx-archive-in"></i></button>
              </div>
            </footer>
          </article>
        </section>
        <section v-else class="rem-docs-empty">
          <span><i class="bx bx-file-find"></i></span>
          <h2>Crea el primer documento</h2>
          <p>Define si debe entregarse, firmarse y cuánto tiempo permanece vigente.</p>
          <button type="button" @click="openRequirement()">Crear documento</button>
        </section>
      </template>
    </main>

    <Teleport to="body">
      <div v-if="staffDetailModal" class="rem-docs-modal" role="dialog" aria-modal="true" aria-labelledby="staff-detail-modal-title" @click.self="closeStaffDetail">
      <section class="rem-docs-dialog rem-docs-dialog--staff-detail">
        <header class="rem-docs-dialog__header">
          <div>
            <span>Carpeta documental</span>
            <h2 id="staff-detail-modal-title">{{ selectedStaff?.full_name }}</h2>
          </div>
          <button type="button" aria-label="Cerrar" @click="closeStaffDetail"><i class="bx bx-x"></i></button>
        </header>

        <div class="staff-detail-summary">
          <span class="staff-detail-avatar">{{ initials(selectedStaff?.full_name) }}</span>
          <div class="staff-detail-identity">
            <strong>{{ selectedStaff?.rut || "Sin RUT" }}</strong>
            <span>{{ selectedStaff?.position || "Cargo no informado" }}</span>
          </div>
          <div class="staff-detail-metrics">
            <span><strong>{{ selectedStaff?.document_summary?.current || 0 }}</strong><small>Al día</small></span>
            <span><strong>{{ selectedStaff?.document_summary?.pending || 0 }}</strong><small>Pendientes</small></span>
            <span><strong>{{ Number(selectedStaff?.document_summary?.expired || 0) + Number(selectedStaff?.document_summary?.expiring || 0) }}</strong><small>Alertas</small></span>
          </div>
        </div>

        <nav class="staff-detail-tabs" aria-label="Detalle del funcionario">
          <button type="button" :class="{ active: staffDetailTab === 'documents' }" @click="selectStaffDetailTab('documents')">
            <i class="bx bx-file"></i> Documentos <span>{{ selectedStaffDocuments.length }}</span>
          </button>
          <button type="button" :class="{ active: staffDetailTab === 'payslips' }" @click="selectStaffDetailTab('payslips')">
            <i class="bx bx-receipt"></i> Liquidaciones <span>{{ isLoadingPayslips ? "…" : staffPayslipTotal }}</span>
          </button>
        </nav>

        <div class="staff-detail-body">
          <template v-if="staffDetailTab === 'documents'">
            <div v-if="selectedStaffDocuments.length" class="staff-detail-documents">
              <article v-for="document in selectedStaffDocuments" :key="document.requirement.id" class="staff-detail-document">
                <span class="staff-detail-document__state" :class="{ complete: isDocumentComplete(document) }">
                  <i :class="isDocumentComplete(document) ? 'bx bx-check' : 'bx bx-minus'"></i>
                </span>
                <div class="staff-detail-document__main">
                  <header>
                    <div>
                      <h3>{{ document.requirement.name }}</h3>
                      <p>{{ actionLabel(document.requirement) }} · {{ validityLabel(document.requirement) }}</p>
                    </div>
                    <span class="staff-document-pill" :class="`is-${document.status}`">{{ statusLabel(document.status) }}</span>
                  </header>
                  <div class="staff-detail-document__facts">
                    <span v-if="document.requirement.requires_delivery"><i class="bx bx-package"></i><small>Entrega</small><strong>{{ document.compliance?.delivered_at ? formatDate(document.compliance.delivered_at) : "Pendiente" }}</strong></span>
                    <span v-if="document.requirement.requires_signature"><i class="bx bx-pen"></i><small>Firma</small><strong>{{ document.compliance?.signed_at ? formatDate(document.compliance.signed_at) : "Pendiente" }}</strong></span>
                    <span><i class="bx bx-calendar"></i><small>Vencimiento</small><strong>{{ expiryLabel(document) }}</strong></span>
                    <span><i class="bx bx-paperclip"></i><small>Respaldo</small><strong>{{ document.compliance?.has_file ? document.compliance.original_name : "Sin archivo" }}</strong></span>
                  </div>
                </div>
                <div class="staff-detail-document__actions">
                  <button type="button" class="staff-detail-edit" @click="openCompliance(selectedStaff, document)"><i class="bx bx-edit-alt"></i> Actualizar</button>
                  <button v-if="document.compliance?.has_file" type="button" class="staff-detail-download" title="Descargar respaldo" @click="downloadFile(document.compliance)"><i class="bx bx-download"></i></button>
                </div>
              </article>
            </div>
            <div v-else class="staff-detail-empty">
              <i class="bx bx-file-find"></i>
              <strong>No hay documentos definidos</strong>
              <span>Crea requisitos documentales para comenzar el seguimiento.</span>
            </div>
          </template>

          <template v-else>
            <div class="staff-payslip-note">
              <i class="bx bx-calendar-check"></i>
              <div><strong>Una liquidación por período mensual</strong><span>Cada nuevo mes importado en Remuneraciones se agrega aquí automáticamente, sin crear columnas ni duplicar documentos.</span></div>
            </div>
            <LoadingState v-if="isLoadingPayslips" message="Buscando liquidaciones del funcionario..." />
            <div v-else-if="payslipAccessDenied" class="staff-detail-message is-warning">
              <i class="bx bx-lock-alt"></i><div><strong>Acceso restringido</strong><span>Las liquidaciones requieren el permiso específico de liquidaciones PDF.</span></div>
            </div>
            <div v-else-if="payslipError" class="staff-detail-message is-error">
              <i class="bx bx-error-circle"></i><div><strong>No pudimos cargar las liquidaciones</strong><span>{{ payslipError }}</span></div>
            </div>
            <div v-else-if="staffPayslips.length" class="staff-payslip-list">
              <article v-for="payslip in staffPayslips" :key="payslip.id" class="staff-payslip-row">
                <span class="staff-payslip-row__icon"><i class="bx bx-receipt"></i></span>
                <div class="staff-payslip-row__period"><strong>{{ payslipPeriodLabel(payslip) }}</strong><span>{{ payslip.school || "Establecimiento no informado" }}</span></div>
                <div class="staff-payslip-row__status"><span :class="`is-${payslip.status}`">{{ payslipStatusLabel(payslip.status) }}</span><small>Versión {{ payslip.version || 1 }}</small></div>
                <div class="staff-payslip-row__amount"><small>Líquido</small><strong>{{ formatCurrency(payslip.net_amount) }}</strong></div>
              </article>
            </div>
            <div v-else class="staff-detail-empty">
              <i class="bx bx-receipt"></i>
              <strong>Sin liquidaciones importadas</strong>
              <span>Cuando se procese el PDF del próximo mes, aparecerá en este historial.</span>
            </div>
          </template>
        </div>

        <footer class="staff-detail-footer">
          <span v-if="staffDetailTab === 'documents'">Selecciona Actualizar para registrar entrega, firma, vigencia o respaldo.</span>
          <span v-else>El historial completo y las importaciones se administran en el módulo especializado.</span>
          <a v-if="staffDetailTab === 'payslips' && !payslipAccessDenied" href="/remuneraciones/liquidaciones-sueldo"><i class="bx bx-link-external"></i> Abrir liquidaciones</a>
          <button type="button" class="secondary-action" @click="closeStaffDetail">Cerrar</button>
        </footer>
        </section>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="requirementModal" class="rem-docs-modal" role="dialog" aria-modal="true" aria-labelledby="requirement-modal-title">
        <form class="rem-docs-dialog" @submit.prevent="saveRequirement">
        <header class="rem-docs-dialog__header">
          <div><span>Configuración documental</span><h2 id="requirement-modal-title">{{ editingRequirementId ? "Editar documento" : "Crear documento" }}</h2></div>
          <button type="button" aria-label="Cerrar" @click="closeRequirement"><i class="bx bx-x"></i></button>
        </header>
        <div class="rem-docs-dialog__body">
          <label class="form-field form-field--wide"><span>Nombre del documento o acción <b>*</b></span><input v-model.trim="requirementForm.name" required maxlength="191" placeholder="Ej. Reglamento interno" /></label>
          <label class="form-field form-field--wide"><span>Descripción</span><textarea v-model.trim="requirementForm.description" rows="3" placeholder="Explica qué debe presentar o aceptar el funcionario."></textarea></label>

          <fieldset class="choice-section form-field--wide">
            <legend>¿Qué debe hacer el funcionario?</legend>
            <div class="action-choices">
              <label :class="{ selected: requirementForm.requires_delivery }"><input v-model="requirementForm.requires_delivery" type="checkbox" /><span><i class="bx bx-package"></i><strong>Entregar</strong><small>Documento físico o archivo</small></span></label>
              <label :class="{ selected: requirementForm.requires_signature }"><input v-model="requirementForm.requires_signature" type="checkbox" /><span><i class="bx bx-pen"></i><strong>Firmar</strong><small>Constancia de aceptación</small></span></label>
            </div>
          </fieldset>

          <fieldset class="choice-section form-field--wide">
            <legend>Vigencia</legend>
            <div class="validity-choices">
              <label :class="{ selected: requirementForm.validity_mode === 'none' }"><input v-model="requirementForm.validity_mode" type="radio" value="none" /><span><i class="bx bx-infinite"></i><strong>Sin vencimiento</strong></span></label>
              <label :class="{ selected: requirementForm.validity_mode === 'months' }"><input v-model="requirementForm.validity_mode" type="radio" value="months" /><span><i class="bx bx-calendar"></i><strong>Por meses</strong></span></label>
              <label :class="{ selected: requirementForm.validity_mode === 'manual' }"><input v-model="requirementForm.validity_mode" type="radio" value="manual" /><span><i class="bx bx-calendar-edit"></i><strong>Fecha manual</strong></span></label>
            </div>
          </fieldset>

          <label v-if="requirementForm.validity_mode === 'months'" class="form-field"><span>Meses de vigencia <b>*</b></span><input v-model.number="requirementForm.validity_months" type="number" min="1" max="1200" required /></label>
          <label v-if="requirementForm.validity_mode !== 'none'" class="form-field"><span>Alertar con anticipación</span><div class="input-suffix"><input v-model.number="requirementForm.alert_days" type="number" min="0" max="365" required /><span>días</span></div></label>
          <label class="switch-field"><input v-model="requirementForm.is_required" type="checkbox" /><span></span><div><strong>Documento obligatorio</strong><small>Se contabiliza como pendiente hasta completarlo.</small></div></label>
          <label v-if="editingRequirementId" class="switch-field"><input v-model="requirementForm.active" type="checkbox" /><span></span><div><strong>Requisito activo</strong><small>Visible en la nómina actual.</small></div></label>
        </div>
        <footer class="rem-docs-dialog__footer"><button type="button" class="secondary-action" @click="closeRequirement">Cancelar</button><button type="submit" class="primary-action" :disabled="isSaving"><i class="bx bx-check"></i> {{ isSaving ? "Guardando..." : "Guardar documento" }}</button></footer>
        </form>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="complianceModal" class="rem-docs-modal" role="dialog" aria-modal="true" aria-labelledby="compliance-modal-title" @click.self="closeCompliance">
        <form class="rem-docs-dialog rem-docs-dialog--compliance" @submit.prevent="saveCompliance">
        <header class="rem-docs-dialog__header">
          <div><span>{{ selectedStaff?.full_name }}</span><h2 id="compliance-modal-title">{{ selectedRequirement?.name }}</h2></div>
          <button type="button" aria-label="Cerrar" @click="closeCompliance"><i class="bx bx-x"></i></button>
        </header>
        <div class="rem-docs-dialog__body">
          <div class="compliance-note form-field--wide"><i class="bx bx-info-circle"></i><span>{{ actionLabel(selectedRequirement || {}) }} · {{ validityLabel(selectedRequirement || {}) }}</span></div>
          <label class="form-field"><span>Fecha del documento</span><input v-model="complianceForm.issued_at" type="date" /></label>
          <label v-if="selectedRequirement?.validity_mode === 'manual'" class="form-field"><span>Fecha de vencimiento <b>*</b></span><input v-model="complianceForm.expires_at" type="date" :required="complianceForm.delivered || complianceForm.signed" /></label>

          <fieldset class="completion-section form-field--wide">
            <legend>Estado de cumplimiento</legend>
            <div class="completion-grid">
              <label v-if="selectedRequirement?.requires_delivery" :class="{ completed: complianceForm.delivered }">
                <input v-model="complianceForm.delivered" type="checkbox" />
                <span class="completion-check"><i class="bx bx-check"></i></span>
                <span><strong>Documento entregado</strong><small>Marca cuando ya fue recibido.</small></span>
                <input v-if="complianceForm.delivered" v-model="complianceForm.delivered_on" type="date" aria-label="Fecha de entrega" @click.stop />
              </label>
              <label v-if="selectedRequirement?.requires_signature" :class="{ completed: complianceForm.signed }">
                <input v-model="complianceForm.signed" type="checkbox" />
                <span class="completion-check"><i class="bx bx-check"></i></span>
                <span><strong>Documento firmado</strong><small>Marca cuando la firma fue verificada.</small></span>
                <input v-if="complianceForm.signed" v-model="complianceForm.signed_on" type="date" aria-label="Fecha de firma" @click.stop />
              </label>
            </div>
          </fieldset>

          <label class="file-drop form-field--wide">
            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="handleFile" />
            <i class="bx bx-cloud-upload"></i>
            <span><strong>{{ complianceForm.file ? complianceForm.file.name : "Adjuntar respaldo" }}</strong><small>PDF, imagen o Word · máximo 20 MB · almacenamiento privado</small></span>
          </label>
          <label v-if="selectedDocument?.compliance?.has_file && !complianceForm.file" class="remove-file form-field--wide"><input v-model="complianceForm.remove_file" type="checkbox" /> Quitar el archivo actual: {{ selectedDocument.compliance.original_name }}</label>
          <label class="form-field form-field--wide"><span>Observaciones</span><textarea v-model.trim="complianceForm.notes" rows="3" placeholder="Antecedentes o aclaraciones del registro."></textarea></label>
        </div>
        <footer class="rem-docs-dialog__footer"><button type="button" class="secondary-action" @click="closeCompliance">Cancelar</button><button type="submit" class="primary-action" :disabled="isSaving"><i class="bx bx-save"></i> {{ isSaving ? "Guardando..." : "Guardar estado" }}</button></footer>
        </form>
      </div>
    </Teleport>
  </Layout>
</template>

<style scoped>
.rem-docs-page { --ink:#18243d; --muted:#65728a; --line:#e5eaf3; --blue:#3157d5; --blue-soft:#edf2ff; --green:#158a68; --amber:#c67b19; --red:#cf4b56; color:var(--ink); padding-bottom:2rem; }
.rem-docs-hero { position:relative; overflow:hidden; min-height:250px; display:flex; align-items:center; justify-content:space-between; padding:2.15rem 2.4rem; border-radius:24px; background:linear-gradient(124deg,#192c5b 0%,#284eaa 55%,#4276dd 100%); box-shadow:0 18px 42px rgba(34,69,145,.2); color:#fff; }
.rem-docs-hero::before { content:""; position:absolute; width:420px; height:420px; right:-120px; top:-210px; border:1px solid rgba(255,255,255,.16); border-radius:50%; box-shadow:0 0 0 62px rgba(255,255,255,.04),0 0 0 130px rgba(255,255,255,.035); }
.rem-docs-hero__content { position:relative; z-index:1; max-width:680px; }
.rem-docs-eyebrow,.section-kicker { display:inline-flex; align-items:center; gap:.45rem; font-size:.74rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
.rem-docs-eyebrow { color:#d9e5ff; }
.rem-docs-hero h1 { max-width:620px; margin:.8rem 0 .65rem; color:#fff; font-size:clamp(1.75rem,3vw,2.65rem); line-height:1.08; letter-spacing:-.035em; }
.rem-docs-hero p { max-width:610px; margin:0; color:rgba(255,255,255,.8); font-size:1rem; line-height:1.6; }
.rem-docs-progress { width:min(470px,100%); margin-top:1.4rem; }
.rem-docs-progress__copy { display:flex; justify-content:space-between; margin-bottom:.45rem; color:#e9efff; font-size:.78rem; }
.rem-docs-progress__track { height:8px; overflow:hidden; border-radius:99px; background:rgba(255,255,255,.18); }
.rem-docs-progress__track span { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#71e0be,#d7ffef); transition:width .4s ease; }
.rem-docs-hero__visual { position:relative; z-index:1; width:260px; height:190px; margin-right:1.5rem; }
.rem-docs-file { position:absolute; display:flex; flex-direction:column; border-radius:18px; box-shadow:0 18px 34px rgba(9,23,58,.25); }
.rem-docs-file--back { width:120px; height:145px; top:14px; left:28px; align-items:center; justify-content:center; transform:rotate(-10deg); color:#89addf; background:#dceaff; font-size:2.8rem; }
.rem-docs-file--front { width:135px; height:165px; right:22px; bottom:3px; padding:24px; transform:rotate(8deg); color:#3157d5; background:#fff; font-size:2.5rem; }
.rem-docs-file--front b { display:block; width:100%; height:6px; margin-top:12px; border-radius:5px; background:#e4eaf6; }
.rem-docs-file--front b:last-child { width:62%; }
.rem-docs-sign { position:absolute; right:0; bottom:0; display:grid; place-items:center; width:62px; height:62px; border:6px solid #3868cb; border-radius:18px; color:#fff; background:#1aa47b; box-shadow:0 12px 25px rgba(10,39,89,.3); font-size:1.6rem; }
.rem-docs-metrics { position:relative; z-index:2; display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; margin:-20px 1.15rem 1.25rem; }
.rem-docs-metrics article { display:flex; align-items:center; gap:.85rem; min-height:90px; padding:1rem 1.1rem; border:1px solid rgba(221,228,241,.9); border-radius:16px; background:rgba(255,255,255,.98); box-shadow:0 9px 24px rgba(34,54,94,.08); }
.metric-icon { display:grid; place-items:center; flex:0 0 44px; width:44px; height:44px; border-radius:13px; font-size:1.35rem; }
.metric-icon--blue { color:#3157d5; background:#edf2ff; }.metric-icon--green { color:#128260; background:#e8f8f2; }.metric-icon--amber { color:#b66d10; background:#fff5df; }.metric-icon--red { color:#c84451; background:#ffedef; }
.rem-docs-metrics strong { display:block; font-size:1.45rem; line-height:1.1; }.rem-docs-metrics article div span { display:block; margin-top:.25rem; color:var(--muted); font-size:.78rem; }
.rem-docs-tabs { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.8rem; padding:.45rem; margin-bottom:1rem; border:1px solid var(--line); border-radius:18px; background:#f5f7fb; }
.rem-docs-tabs button { display:flex; align-items:center; gap:.85rem; padding:.85rem 1rem; border:0; border-radius:14px; color:#647087; background:transparent; text-align:left; transition:.2s ease; }
.rem-docs-tabs button>span:first-child { display:grid; place-items:center; width:42px; height:42px; border-radius:12px; color:#6f7f9e; background:#e9edf5; font-size:1.25rem; }
.rem-docs-tabs button strong,.rem-docs-tabs button small { display:block; }.rem-docs-tabs button strong { color:#354159; font-size:.9rem; }.rem-docs-tabs button small { margin-top:.15rem; color:#808ba0; font-size:.72rem; }
.rem-docs-tabs button.active { background:#fff; box-shadow:0 7px 20px rgba(35,56,96,.1); }.rem-docs-tabs button.active>span:first-child { color:#fff; background:linear-gradient(135deg,#3157d5,#527be1); }.rem-docs-tabs button.active strong { color:var(--ink); }
.rem-docs-alert { display:flex; align-items:center; gap:.65rem; padding:.85rem 1rem; margin-bottom:1rem; border:1px solid #facbd0; border-radius:13px; color:#9e2935; background:#fff1f2; }
.rem-docs-panel { border:1px solid var(--line); border-radius:17px; background:#fff; box-shadow:0 7px 20px rgba(32,52,91,.05); }.rem-docs-panel--toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem; margin-bottom:1rem; }
.rem-docs-search { display:flex; align-items:center; width:min(470px,100%); height:44px; border:1px solid #dce3ef; border-radius:12px; background:#f9fafc; }.rem-docs-search i { margin-left:.85rem; color:#77849b; font-size:1.15rem; }.rem-docs-search input { min-width:0; flex:1; height:100%; padding:0 .7rem; border:0; outline:0; background:transparent; }.rem-docs-search button { height:34px; margin-right:5px; padding:0 .9rem; border:0; border-radius:9px; color:#fff; background:#3157d5; font-size:.78rem; font-weight:700; }
.rem-docs-filters { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.35rem; }.rem-docs-filters button { white-space:nowrap; padding:.55rem .72rem; border:1px solid transparent; border-radius:9px; color:#6a758a; background:transparent; font-size:.74rem; font-weight:700; }.rem-docs-filters button:hover,.rem-docs-filters button.active { border-color:#ccd8f6; color:#3157d5; background:#eef3ff; }
.staff-list-panel { padding:22px; border:1px solid #dfebfb; border-radius:8px; background:rgba(255,255,255,.88); box-shadow:0 18px 44px rgba(63,84,120,.06); }.staff-list-panel__head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; }.staff-list-eyebrow { display:block; color:#6d7690; font-size:12px; font-weight:600; line-height:1.2; text-transform:uppercase; }.staff-list-panel__head h2 { margin:4px 0 0; color:#303848; font-size:1.05rem; font-weight:700; }.staff-list-filter-count { display:inline-flex; align-items:center; min-height:30px; padding:0 12px; border:1px solid #dce5f4; border-radius:999px; color:#647089; background:#f4f7fb; font-size:13px; font-weight:600; }.staff-list-filter-count.active { border-color:#c7d7fe; color:#3152c9; background:#eef4ff; }
.staff-list-status-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid #e2eaf8; }.staff-list-status-tabs button { display:inline-flex; align-items:center; gap:8px; min-height:38px; padding:0 12px; border:1px solid #d5deed; border-radius:999px; color:#647089; background:#fff; font-size:13px; font-weight:600; }.staff-list-status-tabs button strong { display:inline-flex; align-items:center; justify-content:center; min-width:24px; min-height:24px; padding:0 6px; border-radius:999px; color:#475569; background:#f1f5f9; font-size:11px; }.staff-list-status-tabs button.active { border-color:#5b74df; color:#3152c9; background:#eef4ff; }.staff-list-status-tabs button.active strong { color:#fff; background:#5b74df; }
.staff-list-filters { display:grid; grid-template-columns:minmax(260px,1.7fr) minmax(170px,.7fr) auto; align-items:end; gap:12px; margin-bottom:18px; }.staff-list-filter-field { display:flex; flex-direction:column; min-width:0; gap:7px; margin:0; }.staff-list-filter-field>span { color:#4c5568; font-size:13px; font-weight:600; line-height:1.2; }.staff-list-filter-field input,.staff-list-filter-field select { width:100%; min-height:44px; padding:0 14px; border:1px solid #dce5f4; border-radius:8px; outline:0; color:#303848; background:#fff; font-size:14px; }.staff-list-filter-field input:focus,.staff-list-filter-field select:focus { border-color:#9db1f8; box-shadow:0 0 0 3px rgba(91,116,223,.12); }.staff-list-filter-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; }.staff-list-primary-button,.staff-list-secondary-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:42px; padding:0 18px; border:1px solid transparent; border-radius:8px; font-size:14px; font-weight:600; }.staff-list-primary-button { border-color:#5b74df; color:#fff; background:#5b74df; }.staff-list-primary-button:hover { border-color:#4f66ca; color:#fff; background:#4f66ca; }.staff-list-secondary-button { border-color:#b9c3d8; color:#566079; background:#fff; }.staff-list-secondary-button:hover { border-color:#8d99b2; color:#384154; background:#f5f7fb; }
.staff-list-table-wrap { position:relative; overflow-x:auto; border-top:1px solid #e2eaf8; scrollbar-color:#aebddd #eef2f8; scrollbar-width:thin; }
.staff-list-table-wrap.is-refreshing { cursor:progress; }.staff-list-table-wrap.is-refreshing::after { content:""; position:absolute; z-index:3; top:0; left:0; width:100%; height:3px; border-radius:999px; background:linear-gradient(90deg,transparent,#5b74df,#22a47a,transparent); background-repeat:no-repeat; background-position:-48% 0; background-size:32% 100%; animation:staff-list-refresh 1s ease-in-out infinite; pointer-events:none; }
@keyframes staff-list-refresh { 0% { background-position:-48% 0; } 100% { background-position:148% 0; } }
.staff-list-table { width:100%; min-width:980px; table-layout:fixed; border-collapse:separate; border-spacing:0; }
.staff-list-col-person { width:30%; }.staff-list-col-progress { width:20%; }.staff-list-col-alerts { width:14%; }.staff-list-col-status { width:21%; }.staff-list-col-actions { width:15%; }
.staff-list-table th { padding:16px 14px; border-bottom:1px solid #dce7f7; color:#727b92; font-size:12px; font-weight:700; text-align:left; text-transform:uppercase; vertical-align:middle; }
.staff-list-table th:not(:first-child) { text-align:center; }.staff-list-table td { height:86px; padding:14px; border-bottom:1px solid #e5edf9; color:#364154; font-size:14px; vertical-align:middle; }
.staff-list-table tbody tr:last-child td { border-bottom:0; }.staff-list-table tbody tr:hover td { background:#fafcff; }.staff-list-actions-heading { text-align:center!important; }
.staff-list-person { display:flex; align-items:center; gap:11px; min-width:0; }.staff-list-avatar { display:grid!important; place-items:center; flex:0 0 42px; width:42px; height:42px; margin:0!important; border-radius:12px; color:#3157d5!important; background:#edf2ff; font-size:12px!important; font-weight:800; }
.staff-list-person>div { min-width:0; }.staff-list-person-cell strong,.staff-list-person-cell span { display:block; }.staff-list-person-cell strong { overflow:hidden; color:#263042; font-size:14px; font-weight:700; line-height:1.3; text-overflow:ellipsis; white-space:nowrap; }.staff-list-person-cell div span { overflow:hidden; margin-top:4px; color:#68728b; font-size:12px; text-overflow:ellipsis; white-space:nowrap; }
.staff-list-progress-cell { text-align:center; }.staff-progress-copy { display:flex; align-items:center; justify-content:space-between; width:min(180px,100%); margin:0 auto; gap:8px; }.staff-progress-copy strong { color:#303a4c; font-size:13px; }.staff-progress-copy span { color:#758097; font-size:11px; font-weight:700; }.staff-progress-track { width:min(180px,100%); height:6px; overflow:hidden; margin:7px auto 5px; border-radius:999px; background:#edf1f7; }.staff-progress-track span { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#3157d5,#1ba77d); }.staff-list-progress-cell>small { display:block; width:min(180px,100%); margin:0 auto; color:#8792a5; font-size:11px; text-align:left; }
.staff-list-alerts-cell { text-align:center; }.staff-alert-chip { display:inline-flex; align-items:center; justify-content:center; gap:4px; min-height:25px; padding:0 8px; border-radius:999px; font-size:10px; font-weight:700; white-space:nowrap; }.staff-alert-chip+.staff-alert-chip { margin-left:4px; }.staff-alert-chip.is-expired { color:#b4232d; background:#fff0f1; }.staff-alert-chip.is-expiring { color:#a86610; background:#fff6e3; }.staff-alert-chip.is-clear { color:#16755c; background:#eaf8f3; }
.staff-list-status-cell { text-align:center; }.staff-overall-status { display:inline-flex; align-items:center; justify-content:center; min-height:29px; padding:0 12px; border:1px solid transparent; border-radius:999px; font-size:10px; font-weight:700; white-space:nowrap; }.staff-overall-status.is-current { border-color:#a7e2ce; color:#08765a; background:#edf9f5; }.staff-overall-status.is-pending { border-color:#f1d89f; color:#98651d; background:#fff9ea; }.staff-overall-status.is-expiring { border-color:#f5ca71; color:#a45d0b; background:#fff7df; }.staff-overall-status.is-expired { border-color:#f3b8bd; color:#ad2833; background:#fff1f2; }.staff-overall-status.is-empty { border-color:#d9e0eb; color:#69748a; background:#f5f7fa; }
.staff-list-actions-cell { text-align:center; }.staff-list-detail-button { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-width:142px; min-height:36px; padding:0 12px; border:1px solid #cbd7f3; border-radius:8px; color:#3152c9; background:#f5f8ff; font-size:12px; font-weight:700; white-space:nowrap; }.staff-list-detail-button:hover { border-color:#8fa7e9; color:#2644ad; background:#eaf0ff; }
.staff-document-pill { display:inline-flex; align-items:center; min-height:28px; max-width:100%; overflow:hidden; padding:0 10px; border:1px solid #f4d589; border-radius:999px; color:#9c681e; background:#fffbeb; font-size:11px; font-weight:600; text-overflow:ellipsis; white-space:nowrap; }.staff-document-pill.is-current { border-color:#a7f3d0; color:#047857; background:#ecfdf5; }.staff-document-pill.is-expiring { border-color:#fcd34d; color:#b45309; background:#fffbeb; }.staff-document-pill.is-expired { border-color:#fecaca; color:#b91c1c; background:#fef2f2; }
.staff-list-panel>.rem-docs-empty { border:0; border-radius:0; background:transparent; }.staff-list-panel>.rem-docs-pagination { padding-bottom:0; }
.requirements-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; padding:1.2rem 0; }.section-kicker { color:#4468c4; }.requirements-heading h2 { margin:.3rem 0; font-size:1.35rem; }.requirements-heading p { margin:0; color:var(--muted); }.primary-action,.secondary-action { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; min-height:42px; padding:.65rem 1rem; border-radius:11px; font-size:.78rem; font-weight:800; }.primary-action { border:0; color:#fff; background:linear-gradient(135deg,#3157d5,#527be1); box-shadow:0 8px 17px rgba(49,87,213,.18); }.primary-action:disabled { opacity:.6; }.secondary-action { border:1px solid #d8dfeb; color:#536077; background:#fff; }
.requirements-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; }.requirement-card { display:flex; flex-direction:column; min-height:260px; padding:1.15rem; border:1px solid var(--line); border-radius:18px; background:#fff; box-shadow:0 8px 23px rgba(31,51,90,.05); }.requirement-card.archived { opacity:.66; background:#f7f8fa; }.requirement-card header,.requirement-card footer { display:flex; align-items:center; justify-content:space-between; }.requirement-card__icon { display:grid; place-items:center; width:46px; height:46px; border-radius:14px; color:#3157d5; background:#ecf2ff; font-size:1.3rem; }.requirement-card__required,.requirement-card__archived { padding:.3rem .5rem; border-radius:99px; color:#9b650d; background:#fff2d7; font-size:.64rem; font-weight:800; text-transform:uppercase; }.requirement-card__archived { color:#657083; background:#e8ebf0; }.requirement-card h3 { margin:1rem 0 .4rem; font-size:1rem; }.requirement-card>p { flex:1; margin:0; color:var(--muted); font-size:.76rem; line-height:1.5; }.requirement-card__tags { display:flex; flex-wrap:wrap; gap:.35rem; margin:1rem 0; }.requirement-card__tags span { display:inline-flex; align-items:center; gap:.25rem; padding:.35rem .45rem; border-radius:8px; color:#53627c; background:#f2f5fa; font-size:.66rem; }.requirement-card footer { padding-top:.8rem; border-top:1px solid #edf0f5; color:#7c8799; font-size:.7rem; }.requirement-card footer div { display:flex; gap:.35rem; }.requirement-card footer button { display:grid; place-items:center; width:32px; height:32px; border:1px solid #dfe5ef; border-radius:8px; color:#53637d; background:#fff; }
.rem-docs-empty { display:flex; flex-direction:column; align-items:center; padding:3.5rem 1rem; border:1px dashed #cad4e5; border-radius:18px; color:var(--muted); background:#fafbfe; text-align:center; }.rem-docs-empty>span { display:grid; place-items:center; width:64px; height:64px; border-radius:20px; color:#3c61c7; background:#eaf0ff; font-size:1.8rem; }.rem-docs-empty h2 { margin:1rem 0 .35rem; color:var(--ink); font-size:1.1rem; }.rem-docs-empty p { margin:0 0 1rem; }.rem-docs-empty button { padding:.65rem .9rem; border:0; border-radius:10px; color:#fff; background:#3157d5; font-weight:700; }
.rem-docs-pagination { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem .2rem; color:var(--muted); font-size:.75rem; }.rem-docs-pagination div { display:flex; align-items:center; gap:.65rem; }.rem-docs-pagination button { display:grid; place-items:center; width:34px; height:34px; border:1px solid #dbe2ee; border-radius:9px; color:#40506c; background:#fff; }.rem-docs-pagination button:disabled { opacity:.45; }
.rem-docs-modal { position:fixed; z-index:20000; inset:0; display:grid; place-items:center; overflow-y:auto; padding:1.2rem; background:rgba(12,23,47,.62); backdrop-filter:blur(5px); }.rem-docs-dialog { width:min(690px,100%); overflow:hidden; border:1px solid rgba(255,255,255,.4); border-radius:21px; background:#fff; box-shadow:0 28px 70px rgba(7,18,43,.32); }.rem-docs-dialog__header { display:flex; align-items:center; justify-content:space-between; padding:1.15rem 1.35rem; border-bottom:1px solid var(--line); background:linear-gradient(90deg,#f8faff,#fff); }.rem-docs-dialog__header span { color:#5270b9; font-size:.67rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }.rem-docs-dialog__header h2 { margin:.15rem 0 0; font-size:1.15rem; }.rem-docs-dialog__header>button { display:grid; place-items:center; width:36px; height:36px; border:0; border-radius:10px; color:#66748c; background:#eef1f6; font-size:1.3rem; }.rem-docs-dialog__body { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; max-height:calc(100vh - 210px); overflow-y:auto; padding:1.25rem 1.35rem; }.rem-docs-dialog__footer { display:flex; justify-content:flex-end; gap:.6rem; padding:1rem 1.35rem; border-top:1px solid var(--line); background:#fbfcfe; }
.rem-docs-dialog--staff-detail { width:min(980px,100%); }
.staff-detail-summary { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:13px; padding:16px 22px; background:linear-gradient(90deg,#f6f9ff,#fff); }
.staff-detail-avatar { display:grid; place-items:center; width:48px; height:48px; border-radius:14px; color:#fff; background:linear-gradient(135deg,#3157d5,#6382df); box-shadow:0 8px 18px rgba(49,87,213,.2); font-size:13px; font-weight:800; }.staff-detail-identity strong,.staff-detail-identity span { display:block; }.staff-detail-identity strong { color:#30394a; font-size:13px; }.staff-detail-identity span { margin-top:3px; color:#7a8496; font-size:11px; }
.staff-detail-metrics { display:flex; align-items:center; gap:8px; }.staff-detail-metrics>span { min-width:78px; padding:8px 10px; border:1px solid #e1e8f4; border-radius:10px; background:#fff; text-align:center; }.staff-detail-metrics strong,.staff-detail-metrics small { display:block; }.staff-detail-metrics strong { color:#33415a; font-size:15px; }.staff-detail-metrics small { margin-top:2px; color:#8a94a6; font-size:9px; font-weight:700; text-transform:uppercase; }
.staff-detail-tabs { display:flex; gap:6px; padding:10px 22px 0; border-top:1px solid #e8eef8; border-bottom:1px solid #dfe7f4; background:#fff; }.staff-detail-tabs button { display:inline-flex; align-items:center; gap:7px; min-height:42px; margin-bottom:-1px; padding:0 12px; border:0; border-bottom:2px solid transparent; color:#69758b; background:transparent; font-size:12px; font-weight:700; }.staff-detail-tabs button>span { display:grid; place-items:center; min-width:21px; height:21px; padding:0 5px; border-radius:999px; color:#66748d; background:#edf1f7; font-size:10px; }.staff-detail-tabs button.active { border-bottom-color:#3157d5; color:#3152c9; }.staff-detail-tabs button.active>span { color:#fff; background:#5270d8; }
.staff-detail-body { min-height:280px; max-height:calc(100vh - 330px); overflow-y:auto; padding:18px 22px; background:#f8fafd; }.staff-detail-documents { display:flex; flex-direction:column; gap:10px; }
.staff-detail-document { display:grid; grid-template-columns:38px minmax(0,1fr) auto; align-items:start; gap:12px; padding:15px; border:1px solid #e0e8f4; border-radius:13px; background:#fff; box-shadow:0 5px 14px rgba(49,64,95,.04); }.staff-detail-document__state { display:grid; place-items:center; width:34px; height:34px; border:2px solid #d2dae7; border-radius:10px; color:#96a0b1; background:#f7f9fc; font-size:17px; }.staff-detail-document__state.complete { border-color:#7ac8af; color:#fff; background:#18946f; }.staff-detail-document__main { min-width:0; }.staff-detail-document__main header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }.staff-detail-document__main h3 { margin:0; color:#283348; font-size:14px; }.staff-detail-document__main p { margin:4px 0 0; color:#8490a5; font-size:10px; }
.staff-detail-document__facts { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin-top:12px; }.staff-detail-document__facts>span { display:grid; grid-template-columns:auto 1fr; column-gap:6px; min-width:0; padding:8px; border-radius:9px; background:#f7f9fc; }.staff-detail-document__facts i { grid-row:1/3; align-self:center; color:#6680c6; font-size:15px; }.staff-detail-document__facts small,.staff-detail-document__facts strong { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.staff-detail-document__facts small { color:#909aac; font-size:8px; font-weight:700; text-transform:uppercase; }.staff-detail-document__facts strong { margin-top:2px; color:#536077; font-size:10px; font-weight:600; }
.staff-detail-document__actions { display:flex; align-items:center; gap:6px; padding-top:1px; }.staff-detail-document__actions button { min-height:34px; border:1px solid #d5def0; border-radius:8px; color:#405477; background:#fff; font-size:11px; font-weight:700; }.staff-detail-edit { display:inline-flex; align-items:center; gap:5px; padding:0 10px; }.staff-detail-download { display:grid; place-items:center; width:34px; padding:0; font-size:15px!important; }.staff-detail-document__actions button:hover { border-color:#9fb2e4; color:#3152c9; background:#f2f6ff; }
.staff-payslip-note { display:flex; align-items:flex-start; gap:10px; margin-bottom:12px; padding:12px 14px; border:1px solid #cfdcfb; border-radius:11px; color:#3454a9; background:#eef4ff; }.staff-payslip-note>i { margin-top:1px; font-size:20px; }.staff-payslip-note strong,.staff-payslip-note span { display:block; }.staff-payslip-note strong { font-size:12px; }.staff-payslip-note span { margin-top:3px; color:#6175aa; font-size:10px; line-height:1.45; }
.staff-payslip-list { overflow:hidden; border:1px solid #e0e8f4; border-radius:12px; background:#fff; }.staff-payslip-row { display:grid; grid-template-columns:auto minmax(190px,1fr) 130px 150px; align-items:center; gap:12px; padding:12px 14px; border-bottom:1px solid #e8eef7; }.staff-payslip-row:last-child { border-bottom:0; }.staff-payslip-row__icon { display:grid; place-items:center; width:36px; height:36px; border-radius:10px; color:#3157d5; background:#edf2ff; font-size:17px; }.staff-payslip-row__period strong,.staff-payslip-row__period span,.staff-payslip-row__status small,.staff-payslip-row__amount small,.staff-payslip-row__amount strong { display:block; }.staff-payslip-row__period strong { color:#303b50; font-size:12px; }.staff-payslip-row__period span { margin-top:3px; color:#8791a3; font-size:10px; }.staff-payslip-row__status>span { display:inline-flex; min-height:25px; align-items:center; padding:0 8px; border-radius:999px; color:#08765a; background:#eaf8f3; font-size:9px; font-weight:800; text-transform:uppercase; }.staff-payslip-row__status>span.is-anulada { color:#b02b36; background:#fff0f1; }.staff-payslip-row__status small { margin-top:3px; color:#929bad; font-size:9px; }.staff-payslip-row__amount { text-align:right; }.staff-payslip-row__amount small { color:#9099aa; font-size:9px; text-transform:uppercase; }.staff-payslip-row__amount strong { margin-top:2px; color:#26364e; font-size:13px; }
.staff-detail-empty,.staff-detail-message { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:210px; padding:30px; border:1px dashed #cfd8e8; border-radius:13px; color:#7c879a; background:#fff; text-align:center; }.staff-detail-empty i { color:#5874c7; font-size:34px; }.staff-detail-empty strong,.staff-detail-empty span { display:block; }.staff-detail-empty strong { margin-top:10px; color:#3f4b61; font-size:13px; }.staff-detail-empty span { max-width:420px; margin-top:4px; font-size:11px; }.staff-detail-message { flex-direction:row; gap:10px; min-height:120px; text-align:left; }.staff-detail-message>i { font-size:25px; }.staff-detail-message strong,.staff-detail-message span { display:block; }.staff-detail-message strong { color:#4c566a; font-size:12px; }.staff-detail-message span { margin-top:3px; font-size:10px; }.staff-detail-message.is-warning { border-color:#eed59f; color:#98651d; background:#fffaf0; }.staff-detail-message.is-error { border-color:#f1bec3; color:#ac303a; background:#fff3f4; }
.staff-detail-footer { display:flex; align-items:center; gap:10px; padding:12px 22px; border-top:1px solid #e1e8f3; background:#fff; }.staff-detail-footer>span { flex:1; color:#7f899b; font-size:10px; }.staff-detail-footer>a { display:inline-flex; align-items:center; gap:5px; min-height:36px; padding:0 11px; border:1px solid #c9d6f3; border-radius:8px; color:#3152c9; background:#f4f7ff; font-size:11px; font-weight:700; }
.form-field { display:flex; flex-direction:column; gap:.38rem; min-width:0; }.form-field--wide { grid-column:1 / -1; }.form-field>span,.choice-section legend,.completion-section legend { color:#44516a; font-size:.73rem; font-weight:800; }.form-field b { color:#c53d49; }.form-field input:not([type="checkbox"]),.form-field textarea,.input-suffix { width:100%; border:1px solid #d8dfeb; border-radius:10px; color:var(--ink); background:#fff; }.form-field input:not([type="checkbox"]),.form-field textarea { padding:.68rem .75rem; outline:0; }.form-field input:focus,.form-field textarea:focus { border-color:#7390e1; box-shadow:0 0 0 3px rgba(49,87,213,.1); }.choice-section,.completion-section { margin:0; padding:0; border:0; }.choice-section legend,.completion-section legend { margin-bottom:.55rem; }.action-choices,.validity-choices { display:grid; gap:.6rem; }.action-choices { grid-template-columns:repeat(2,minmax(0,1fr)); }.validity-choices { grid-template-columns:repeat(3,minmax(0,1fr)); }.action-choices label,.validity-choices label { cursor:pointer; }.action-choices input,.validity-choices input { position:absolute; opacity:0; pointer-events:none; }.action-choices label>span,.validity-choices label>span { display:flex; align-items:center; gap:.55rem; min-height:62px; padding:.7rem; border:1px solid #dce2ed; border-radius:12px; background:#fff; }.action-choices label.selected>span,.validity-choices label.selected>span { border-color:#8da5e5; color:#294ba6; background:#f0f4ff; box-shadow:0 0 0 2px rgba(49,87,213,.08); }.action-choices i,.validity-choices i { font-size:1.2rem; }.action-choices strong,.action-choices small,.validity-choices strong { display:block; font-size:.73rem; }.action-choices small { margin-top:.13rem; color:#7b879b; font-weight:400; }.input-suffix { display:flex; align-items:center; }.input-suffix input { border:0!important; box-shadow:none!important; }.input-suffix span { padding-right:.7rem; color:#7a8495; font-size:.72rem; }
.switch-field { grid-column:1 / -1; display:grid; grid-template-columns:auto 1fr; column-gap:.65rem; align-items:center; cursor:pointer; }.switch-field input { position:absolute; opacity:0; }.switch-field>span { position:relative; width:42px; height:24px; border-radius:99px; background:#cdd4df; transition:.2s; }.switch-field>span::after { content:""; position:absolute; width:18px; height:18px; left:3px; top:3px; border-radius:50%; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,.18); transition:.2s; }.switch-field input:checked+span { background:#3157d5; }.switch-field input:checked+span::after { transform:translateX(18px); }.switch-field div strong,.switch-field div small { display:block; }.switch-field div strong { font-size:.76rem; }.switch-field div small { margin-top:.1rem; color:#7b8699; font-size:.68rem; }
.compliance-note { display:flex; align-items:center; gap:.55rem; padding:.65rem .75rem; border-radius:10px; color:#3456ad; background:#eef3ff; font-size:.74rem; }.compliance-note i { font-size:1.05rem; }.completion-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.65rem; }.completion-grid>label { position:relative; display:grid; grid-template-columns:auto 1fr; gap:.6rem; align-items:center; min-height:78px; padding:.8rem; border:1px solid #dce2ec; border-radius:13px; cursor:pointer; }.completion-grid>label.completed { border-color:#8fd3bd; background:#effaf6; }.completion-grid>label>input[type="checkbox"] { position:absolute; opacity:0; }.completion-check { display:grid; place-items:center; width:27px; height:27px; border:2px solid #c8d0dd; border-radius:8px; color:transparent; }.completed .completion-check { border-color:#1d9773; color:#fff; background:#1d9773; }.completion-grid strong,.completion-grid small { display:block; }.completion-grid strong { font-size:.76rem; }.completion-grid small { margin-top:.18rem; color:#7b8697; font-size:.67rem; }.completion-grid>label>input[type="date"] { grid-column:1/-1; width:100%; padding:.42rem .5rem; border:1px solid #cdd8d3; border-radius:8px; background:#fff; font-size:.72rem; }
.file-drop { flex-direction:row; align-items:center; padding:.85rem; border:1px dashed #9fb1d9; border-radius:13px; color:#3a5eb7; background:#f6f8ff; cursor:pointer; }.file-drop input { position:absolute; width:1px; height:1px; opacity:0; }.file-drop>i { font-size:1.5rem; }.file-drop strong,.file-drop small { display:block; }.file-drop strong { font-size:.76rem; }.file-drop small { margin-top:.15rem; color:#78869f; font-size:.66rem; }.remove-file { display:block; color:#8c4b52; font-size:.71rem; }
@media (max-width:1100px) { .rem-docs-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }.requirements-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }.staff-list-filters { grid-template-columns:minmax(240px,1fr) minmax(170px,.5fr); }.staff-list-filter-actions { grid-column:1/-1; justify-content:flex-start; }.staff-detail-document__facts { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:760px) { .rem-docs-hero { min-height:auto; padding:1.5rem; }.rem-docs-hero__visual { display:none; }.rem-docs-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); margin:-12px .6rem 1rem; gap:.65rem; }.rem-docs-metrics article { min-height:78px; padding:.75rem; }.metric-icon { flex-basis:38px; width:38px; height:38px; }.rem-docs-tabs { grid-template-columns:1fr; }.staff-list-panel { padding:16px; }.staff-list-filters { grid-template-columns:1fr; }.staff-list-filter-actions { grid-column:1; }.staff-list-status-tabs { flex-wrap:nowrap; overflow-x:auto; padding-bottom:12px; }.staff-list-status-tabs button { flex:0 0 auto; }.requirements-grid { grid-template-columns:1fr; }.requirements-heading { align-items:flex-start; flex-direction:column; }.rem-docs-dialog__body { grid-template-columns:1fr; }.form-field--wide { grid-column:1; }.validity-choices,.completion-grid { grid-template-columns:1fr; }.rem-docs-dialog { align-self:start; }.rem-docs-modal { padding:.65rem; }.rem-docs-dialog__body { max-height:none; }.switch-field { grid-column:1; }.staff-detail-summary { grid-template-columns:auto 1fr; padding:13px 15px; }.staff-detail-metrics { grid-column:1/-1; }.staff-detail-metrics>span { flex:1; min-width:0; }.staff-detail-tabs { overflow-x:auto; padding-left:15px; padding-right:15px; }.staff-detail-tabs button { flex:0 0 auto; }.staff-detail-body { max-height:none; padding:14px; }.staff-detail-document { grid-template-columns:34px minmax(0,1fr); }.staff-detail-document__actions { grid-column:1/-1; justify-content:flex-end; }.staff-payslip-row { grid-template-columns:auto minmax(0,1fr) auto; }.staff-payslip-row__status { grid-column:2; }.staff-payslip-row__amount { grid-column:3; grid-row:1/3; }.staff-detail-footer { flex-wrap:wrap; padding:12px 15px; }.staff-detail-footer>span { flex-basis:100%; } }
@media (max-width:480px) { .rem-docs-hero { border-radius:18px; padding:1.25rem; }.rem-docs-hero h1 { font-size:1.65rem; }.rem-docs-metrics { grid-template-columns:1fr 1fr; }.rem-docs-metrics article { gap:.55rem; }.rem-docs-metrics strong { font-size:1.15rem; }.rem-docs-metrics article div span { font-size:.66rem; }.rem-docs-tabs button small { display:none; }.staff-list-panel { padding:13px; }.staff-list-panel__head h2 { font-size:.95rem; }.staff-list-filter-actions { display:grid; grid-template-columns:1fr 1fr; }.staff-list-table { min-width:850px; }.action-choices { grid-template-columns:1fr; }.rem-docs-dialog__footer { display:grid; grid-template-columns:1fr 1fr; }.rem-docs-pagination>span { display:none; }.rem-docs-pagination { justify-content:center; }.staff-detail-document__main header { align-items:flex-start; flex-direction:column; }.staff-detail-document__facts { grid-template-columns:1fr; }.staff-payslip-row { grid-template-columns:auto minmax(0,1fr); }.staff-payslip-row__amount { grid-column:2; grid-row:auto; text-align:left; }.staff-detail-footer>a,.staff-detail-footer>button { flex:1; justify-content:center; } }
</style>
