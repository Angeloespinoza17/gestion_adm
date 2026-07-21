<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";
import { getPdfMake } from "../../utils/pdfmake";

const localDateTimeInput = (date) => {
  const pad = (value) => String(value).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const defaultExpectedReturn = () => {
  const date = new Date();
  date.setHours(18, 0, 0, 0);
  if (date.getTime() < Date.now()) date.setDate(date.getDate() + 1);
  return localDateTimeInput(date);
};

const emptyKeyForm = () => ({
  porter_key_group_id: null,
  code: "",
  name: "",
  maintenance_dependency_id: null,
  observations: "",
  active: true,
});

const emptyGroupForm = () => ({
  code: "",
  name: "",
  description: "",
  active: true,
});

const emptyLoanForm = () => ({
  porter_key_id: null,
  staff_id: null,
  maintenance_dependency_id: null,
  requester_name: "",
  requester_rut: "",
  purpose: "",
  expected_return_at: defaultExpectedReturn(),
  observations: "",
});

const emptyFilters = () => ({
  search: "",
  status: null,
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      savingKey: false,
      savingGroup: false,
      savingLoan: false,
      loading: false,
      showKeyModal: false,
      showGroupModal: false,
      error: null,
      catalogs: { dependencies: [], staff: [], key_loan_statuses: [] },
      keyForm: emptyKeyForm(),
      groupForm: emptyGroupForm(),
      loanForm: emptyLoanForm(),
      keyGroups: [],
      keys: [],
      loans: [],
      summary: {},
      filters: emptyFilters(),
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    loanStatusOptions() {
      return [{ value: null, text: "Todos los estados" }].concat((this.catalogs.key_loan_statuses || []).map((item) => ({ value: item.value, text: item.label })));
    },
    staffOptions() {
      return [{ value: null, text: "Sin funcionario asociado" }].concat(
        (this.catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name }))
      );
    },
    dependencyOptions() {
      return [{ value: null, text: "Sin dependencia" }].concat(
        (this.catalogs.dependencies || []).map((item) => ({ value: item.id, text: `${item.code || "S/C"} · ${item.name}` }))
      );
    },
    activeKeyGroups() {
      return (this.keyGroups || []).filter((item) => item.active);
    },
    groupOptions() {
      return [{ value: null, text: "Sin manojo" }].concat(
        this.activeKeyGroups.map((item) => ({ value: item.id, text: this.groupLabel(item) }))
      );
    },
    availableKeys() {
      return (this.keys || []).filter((item) => item.active && !item.active_loans_count);
    },
    availableKeyOptions() {
      return [{ value: null, text: "Seleccionar llave disponible..." }].concat(
        this.availableKeys.map((item) => ({ value: item.id, text: this.keyOptionLabel(item) }))
      );
    },
    selectedKey() {
      return (this.keys || []).find((item) => Number(item.id) === Number(this.loanForm.porter_key_id)) || null;
    },
    activeLoans() {
      return (this.loans || []).filter((item) => item.status === "prestada");
    },
    summaryCards() {
      return [
        { label: "Registradas", value: this.summary.total_keys || 0, detail: "Llaves del catálogo", icon: "bx bx-key", tone: "primary" },
        { label: "Disponibles", value: this.availableKeys.length, detail: "Listas para préstamo", icon: "bx bx-check-circle", tone: "success" },
        { label: "Prestadas", value: this.summary.active_loans || 0, detail: "Fuera de portería", icon: "bx bx-log-out-circle", tone: "warning" },
        { label: "Observadas", value: this.summary.observed_loans || 0, detail: "Devueltas con reparo", icon: "bx bx-error-circle", tone: "danger" },
        { label: "Manojos", value: this.summary.key_groups || this.keyGroups.length || 0, detail: "Grupos de llaves", icon: "bx bx-collection", tone: "info" },
      ];
    },
    loanFields() {
      return [
        { key: "porter_key", label: "Llave", thClass: "keys-th", tdClass: "keys-td key-main-cell" },
        { key: "requester_name", label: "Solicitante", thClass: "keys-th", tdClass: "keys-td" },
        { key: "status", label: "Estado", thClass: "keys-th", tdClass: "keys-td" },
        { key: "checked_out_at", label: "Salida", thClass: "keys-th", tdClass: "keys-td" },
        { key: "actions", label: "Acciones", thClass: "keys-th text-end", tdClass: "keys-td text-end" },
      ];
    },
    keyFields() {
      return [
        { key: "code", label: "Código", thClass: "keys-th", tdClass: "keys-td key-code-cell" },
        { key: "name", label: "Llave / manojo", thClass: "keys-th", tdClass: "keys-td" },
        { key: "active_loans_count", label: "Estado", thClass: "keys-th", tdClass: "keys-td" },
        { key: "actions", label: "Acción", thClass: "keys-th text-end", tdClass: "keys-td text-end" },
      ];
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadData();
  },
  methods: {
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/porter/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async loadData(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/keys", {
          params: {
            page,
            search: this.filters.search || null,
            status: this.filters.status || null,
          },
        });
        this.keyGroups = response.data.groups || [];
        this.keys = response.data.keys || [];
        this.loans = response.data.loans?.data || [];
        this.summary = response.data.summary || {};
        this.pagination = {
          current_page: response.data.loans?.current_page || 1,
          total: response.data.loans?.total || 0,
          per_page: response.data.loans?.per_page || 15,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async submitKey() {
      if (this.savingKey) return;

      const issues = [];
      if (!String(this.keyForm.code || "").trim()) issues.push("Ingresa el código de la llave.");
      if (!String(this.keyForm.name || "").trim()) issues.push("Ingresa el nombre de la llave.");
      if (issues.length) {
        await this.showValidationAlert(issues);
        return;
      }

      this.savingKey = true;
      this.error = null;
      try {
        await axios.post("/api/porter/keys", this.keyForm);
        this.keyForm = emptyKeyForm();
        this.showKeyModal = false;
        await this.loadData();
        await Swal.fire({
          icon: "success",
          title: "Llave registrada",
          text: "La llave quedó disponible en el catálogo.",
          timer: 1600,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar la llave");
      } finally {
        this.savingKey = false;
      }
    },
    async submitGroup() {
      if (this.savingGroup) return;

      const issues = [];
      if (!String(this.groupForm.name || "").trim()) issues.push("Ingresa el nombre del manojo.");
      if (issues.length) {
        await this.showValidationAlert(issues);
        return;
      }

      this.savingGroup = true;
      this.error = null;
      try {
        await axios.post("/api/porter/key-groups", this.groupForm);
        this.groupForm = emptyGroupForm();
        this.showGroupModal = false;
        await this.loadData(this.pagination.current_page || 1);
        await Swal.fire({
          icon: "success",
          title: "Manojo registrado",
          text: "Ahora puedes asignar llaves a este grupo.",
          timer: 1600,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar el manojo");
      } finally {
        this.savingGroup = false;
      }
    },
    async exportGroup(group) {
      try {
        const response = await axios.get("/api/porter/keys");
        const freshGroup = (response.data.groups || []).find((item) => Number(item.id) === Number(group.id)) || group;
        const groupKeys = (response.data.keys || [])
          .filter((item) => Number(item.porter_key_group_id) === Number(group.id))
          .sort((a, b) => String(a.name || "").localeCompare(String(b.name || ""), "es"));

        this.downloadGroupPdf(freshGroup, groupKeys);
      } catch (error) {
        await this.showRequestError(error, "No se pudo exportar el manojo");
      }
    },
    downloadGroupPdf(group, groupKeys) {
      const pdfMake = getPdfMake();
      const title = `Manojo de llaves: ${this.groupLabel(group)}`;
      const generatedAt = this.formatDateTime(localDateTimeInput(new Date()));
      const rows = groupKeys.map((key) => [
        key.code || "-",
        key.name || "-",
        this.keyLocation(key),
        this.keyStatusLabel(key),
        key.observations || "-",
      ]);

      const tableBody = [
        [
          { text: "Código", style: "tableHeader" },
          { text: "Llave", style: "tableHeader" },
          { text: "Dependencia", style: "tableHeader" },
          { text: "Estado", style: "tableHeader" },
          { text: "Observaciones", style: "tableHeader" },
        ],
      ].concat(
        rows.length
          ? rows
          : [[{ text: "Este manojo no tiene llaves asociadas.", colSpan: 5, italics: true, color: "#74788d" }, {}, {}, {}, {}]]
      );

      pdfMake
        .createPdf({
          pageOrientation: "landscape",
          pageMargins: [32, 34, 32, 34],
          content: [
            { text: title, style: "title" },
            { text: `Generado: ${generatedAt}`, style: "subtitle" },
            {
              columns: [
                { text: [{ text: "Código: ", bold: true }, group.code || "-"] },
                { text: [{ text: "Estado: ", bold: true }, group.active ? "Activo" : "Inactivo"] },
                { text: [{ text: "Llaves: ", bold: true }, String(groupKeys.length)] },
              ],
              columnGap: 12,
              margin: [0, 4, 0, 8],
            },
            { text: group.description || "Sin descripción.", style: "description" },
            {
              table: {
                headerRows: 1,
                widths: [70, 150, 150, 80, "*"],
                body: tableBody,
              },
              layout: "lightHorizontalLines",
              margin: [0, 10, 0, 0],
            },
          ],
          styles: {
            title: { fontSize: 18, bold: true, color: "#2a3042" },
            subtitle: { fontSize: 9, color: "#74788d", margin: [0, 3, 0, 8] },
            description: { fontSize: 10, color: "#495057", margin: [0, 0, 0, 8] },
            tableHeader: { bold: true, color: "#2a3042", fillColor: "#eef2ff" },
          },
          defaultStyle: { fontSize: 9 },
        })
        .download(`manojo-${this.slugify(this.groupLabel(group))}.pdf`);
    },
    async submitLoan() {
      if (this.savingLoan) return;

      const issues = [];
      if (!this.loanForm.porter_key_id) issues.push("Selecciona una llave disponible.");
      if (!String(this.loanForm.requester_name || "").trim()) issues.push("Ingresa el nombre de quien retira la llave.");
      if (issues.length) {
        await this.showValidationAlert(issues);
        return;
      }

      const confirmed = await this.confirmLoan();
      if (!confirmed) return;

      this.savingLoan = true;
      this.error = null;
      try {
        await axios.post(`/api/porter/keys/${this.loanForm.porter_key_id}/loans`, this.loanForm);
        this.loanForm = emptyLoanForm();
        await this.loadData();
        await Swal.fire({
          icon: "success",
          title: "Llave prestada",
          text: "El préstamo quedó registrado en portería.",
          timer: 1600,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar el préstamo");
      } finally {
        this.savingLoan = false;
      }
    },
    async returnLoan(item) {
      const { value } = await Swal.fire({
        title: "Registrar devolución",
        html: `
          <div class="swal-key-summary">
            <strong>${this.escapeHtml(this.loanKeyLabel(item))}</strong>
            <span>${this.escapeHtml(this.requesterLine(item))}</span>
          </div>
          <select id="loan-status" class="swal2-select">
            <option value="devuelta">Devuelta</option>
            <option value="observada">Observada</option>
          </select>
          <textarea id="loan-return-observations" class="swal2-textarea" placeholder="Observaciones de devolución"></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: "Guardar devolución",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: () => ({
          status: document.getElementById("loan-status").value,
          return_observations: document.getElementById("loan-return-observations").value.trim(),
        }),
      });

      if (!value) return;

      try {
        await axios.post(`/api/porter/key-loans/${item.id}/return`, value);
        await this.loadData(this.pagination.current_page || 1);
        await Swal.fire({
          icon: "success",
          title: "Devolución registrada",
          text: "La llave quedó cerrada en el historial.",
          timer: 1500,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar la devolución");
      }
    },
    selectKeyForLoan(item) {
      if (!item.active || item.active_loans_count) return;
      this.loanForm.porter_key_id = item.id;
      document.querySelector(".loan-panel")?.scrollIntoView({ behavior: "smooth", block: "start" });
    },
    resetFilters() {
      this.filters = emptyFilters();
      this.loadData(1);
    },
    clearLoan() {
      this.loanForm = emptyLoanForm();
    },
    openKeyModal() {
      this.keyForm = emptyKeyForm();
      this.showKeyModal = true;
    },
    openGroupModal() {
      this.groupForm = emptyGroupForm();
      this.showGroupModal = true;
    },
    async confirmLoan() {
      const result = await Swal.fire({
        icon: "question",
        title: "Prestar llave",
        html: `
          <div class="swal-key-confirm">
            <div><span>Llave</span><strong>${this.escapeHtml(this.selectedKey ? this.keyLabel(this.selectedKey) : "-")}</strong></div>
            <div><span>Solicitante</span><strong>${this.escapeHtml(this.loanForm.requester_name)}</strong></div>
            <div><span>Devolución esperada</span><strong>${this.escapeHtml(this.formatDateTime(this.loanForm.expected_return_at))}</strong></div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Registrar préstamo",
        cancelButtonText: "Revisar",
        reverseButtons: true,
      });

      return result.isConfirmed;
    },
    async showValidationAlert(issues) {
      await Swal.fire({
        icon: "warning",
        title: "Faltan datos",
        html: `<ul class="swal-key-validation">${issues.map((issue) => `<li>${this.escapeHtml(issue)}</li>`).join("")}</ul>`,
        confirmButtonText: "Entendido",
      });
    },
    async showRequestError(error, title) {
      const message = this.formatError(error);
      this.error = message;
      await Swal.fire({
        icon: "error",
        title,
        text: message,
        confirmButtonText: "Entendido",
      });
    },
    keyLabel(item) {
      return [item.code, item.name].filter(Boolean).join(" · ");
    },
    keyOptionLabel(item) {
      const group = item.group ? this.groupLabel(item.group) : null;
      return [this.keyLabel(item), group].filter(Boolean).join(" · ");
    },
    groupLabel(item) {
      return [item.code, item.name].filter(Boolean).join(" · ") || "Manojo sin nombre";
    },
    keyGroupLabel(item) {
      return item.group ? this.groupLabel(item.group) : "Sin manojo";
    },
    keyLocation(item) {
      return item.dependency?.name || "Sin dependencia asignada";
    },
    keyStatusValue(item) {
      if (!item.active) return "inactiva";
      return item.active_loans_count ? "prestada" : "disponible";
    },
    keyStatusLabel(item) {
      if (!item.active) return "Inactiva";
      return item.active_loans_count ? "Prestada" : "Disponible";
    },
    loanKeyLabel(item) {
      const key = [item.porter_key?.code, item.porter_key?.name].filter(Boolean).join(" · ");
      const group = item.porter_key?.group ? this.groupLabel(item.porter_key.group) : null;
      return [key, group].filter(Boolean).join(" · ") || "-";
    },
    requesterLine(item) {
      return [item.requester_name, item.requester_rut].filter(Boolean).join(" · ") || "-";
    },
    loanTargetLine(item) {
      return item.dependency?.name || item.staff?.full_name || item.purpose || "Sin destino informado";
    },
    statusLabel(value) {
      const option = (this.catalogs.key_loan_statuses || []).find((item) => item.value === value);
      return option?.label || this.humanize(value);
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    humanize(value) {
      if (!value) return "-";
      return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    slugify(value) {
      return String(value || "manojo")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "") || "manojo";
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || {};
      const firstKey = Object.keys(errors)[0];
      return errors[firstKey]?.[0] || error?.response?.data?.message || error?.message || "Error desconocido";
    },
    escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },
  },
};
</script>

<template>
  <Layout>
    <section class="keys-page">
      <div class="keys-heading">
        <div>
          <h4 class="mb-0">Control de llaves</h4>
          <p class="mb-0 text-muted">Préstamos, devoluciones y catálogo operativo de portería.</p>
        </div>
        <div class="heading-actions">
          <router-link class="btn btn-outline-primary" to="/porter/dashboard">Panel portería</router-link>
          <BButton variant="outline-primary" @click="openGroupModal">Nuevo manojo</BButton>
          <BButton variant="outline-primary" @click="openKeyModal">Nueva llave</BButton>
          <BButton variant="primary" :disabled="loading" @click="loadData(pagination.current_page || 1)">
            {{ loading ? "Actualizando..." : "Actualizar" }}
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

      <div class="key-stats">
        <div v-for="card in summaryCards" :key="card.label" class="key-stat-card" :class="`tone-${card.tone}`">
          <div class="stat-icon"><i :class="card.icon"></i></div>
          <span>{{ card.label }}</span>
          <strong>{{ card.value }}</strong>
          <small>{{ card.detail }}</small>
        </div>
      </div>

      <div class="top-grid">
        <BCard class="keys-panel loan-panel">
          <div class="panel-title-row">
            <div>
              <h5 class="mb-1">Prestar llave</h5>
              <p class="mb-0 text-muted">Registro rápido de salida.</p>
            </div>
            <BBadge variant="success">{{ availableKeys.length }} disponibles</BBadge>
          </div>

          <div v-if="selectedKey" class="selected-key">
            <span>Llave seleccionada</span>
            <strong>{{ keyLabel(selectedKey) }}</strong>
            <small>{{ keyGroupLabel(selectedKey) }} · {{ keyLocation(selectedKey) }}</small>
          </div>

          <div class="loan-form-grid">
            <div class="span-6">
              <label class="form-label">Llave</label>
              <BFormSelect v-model="loanForm.porter_key_id" :options="availableKeyOptions" />
            </div>
            <div class="span-6">
              <label class="form-label">Solicitante</label>
              <BFormInput v-model="loanForm.requester_name" placeholder="Nombre de quien retira" autocomplete="off" />
            </div>
            <div class="span-4">
              <label class="form-label">RUT o identificación</label>
              <BFormInput v-model="loanForm.requester_rut" placeholder="Opcional" autocomplete="off" />
            </div>
            <div class="span-4">
              <label class="form-label">Funcionario asociado</label>
              <BFormSelect v-model="loanForm.staff_id" :options="staffOptions" />
            </div>
            <div class="span-4">
              <label class="form-label">Destino</label>
              <BFormSelect v-model="loanForm.maintenance_dependency_id" :options="dependencyOptions" />
            </div>
            <div class="span-4">
              <label class="form-label">Devolución esperada</label>
              <BFormInput v-model="loanForm.expected_return_at" type="datetime-local" />
            </div>
            <div class="span-8">
              <label class="form-label">Motivo</label>
              <BFormInput v-model="loanForm.purpose" placeholder="Apertura, mantenimiento, supervisión..." autocomplete="off" />
            </div>
            <div class="span-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="loanForm.observations" rows="2" />
            </div>
          </div>

          <div class="form-actions">
            <BButton variant="outline-secondary" :disabled="savingLoan" @click="clearLoan">Limpiar</BButton>
            <BButton variant="primary" :disabled="savingLoan || !availableKeys.length" @click="submitLoan">
              {{ savingLoan ? "Guardando..." : "Registrar préstamo" }}
            </BButton>
          </div>
        </BCard>

        <BCard class="keys-panel active-panel">
          <div class="panel-title-row">
            <div>
              <h5 class="mb-1">Llaves fuera</h5>
              <p class="mb-0 text-muted">Devolución rápida.</p>
            </div>
            <BBadge variant="warning">{{ summary.active_loans || 0 }}</BBadge>
          </div>

          <div v-if="!activeLoans.length" class="empty-state">No hay préstamos activos en la página actual.</div>
          <div v-else class="active-list">
            <div v-for="item in activeLoans" :key="item.id" class="active-row">
              <div>
                <strong>{{ loanKeyLabel(item) }}</strong>
                <span>{{ requesterLine(item) }}</span>
                <small>{{ loanTargetLine(item) }} · salida {{ formatDateTime(item.checked_out_at) }}</small>
              </div>
              <BButton size="sm" variant="outline-primary" @click="returnLoan(item)">Devolver</BButton>
            </div>
          </div>
        </BCard>
      </div>

      <BCard class="keys-panel">
        <div class="panel-title-row">
          <div>
            <h5 class="mb-1">Manojos de llaves</h5>
            <p class="mb-0 text-muted">Agrupa llaves por dependencia, sector o uso operativo.</p>
          </div>
          <BButton size="sm" variant="outline-primary" @click="openGroupModal">Nuevo manojo</BButton>
        </div>

        <div v-if="!keyGroups.length" class="empty-state">No hay manojos registrados.</div>
        <div v-else class="group-list">
          <div v-for="group in keyGroups" :key="group.id" class="group-row">
            <div class="group-main">
              <strong>{{ groupLabel(group) }}</strong>
              <span>{{ group.description || "Sin descripción" }}</span>
            </div>
            <div class="group-counts">
              <span><strong>{{ group.keys_count || 0 }}</strong> llaves</span>
              <span><strong>{{ group.active_keys_count || 0 }}</strong> activas</span>
            </div>
            <div class="group-actions">
              <PorterStatusBadge :value="group.active ? 'activa' : 'inactiva'" :label="group.active ? 'Activo' : 'Inactivo'" />
              <BButton size="sm" variant="outline-primary" @click="exportGroup(group)">Exportar PDF</BButton>
            </div>
          </div>
        </div>
      </BCard>

      <BCard class="keys-panel">
        <div class="panel-title-row history-heading">
          <div>
            <h5 class="mb-1">Historial de préstamos</h5>
            <p class="mb-0 text-muted">Salida y devolución de llaves.</p>
          </div>
        </div>

        <div class="history-filters">
          <BFormInput class="filter-search" v-model="filters.search" placeholder="Llave, código, solicitante o identificación" @keyup.enter="loadData(1)" />
          <BFormSelect class="filter-status" v-model="filters.status" :options="loanStatusOptions" />
          <div class="filter-actions">
            <BButton variant="primary" :disabled="loading" @click="loadData(1)">Filtrar</BButton>
            <BButton variant="outline-secondary" :disabled="loading" @click="resetFilters">Limpiar</BButton>
          </div>
        </div>

        <BTable
          :items="loans"
          :busy="loading"
          :fields="loanFields"
          responsive
          hover
          show-empty
          table-class="keys-table"
        >
          <template #table-busy><LoadingState message="Cargando préstamos..." compact /></template>
          <template #empty><div class="empty-table">No hay préstamos para los filtros seleccionados.</div></template>
          <template #cell(porter_key)="{ item }">
            <div class="fw-semibold">{{ loanKeyLabel(item) }}</div>
            <div class="small text-muted table-subline">{{ loanTargetLine(item) }}</div>
          </template>
          <template #cell(requester_name)="{ item }">
            <div class="fw-semibold">{{ item.requester_name || "-" }}</div>
            <div class="small text-muted table-subline">{{ item.requester_rut || "Sin identificación" }}</div>
          </template>
          <template #cell(status)="{ item }">
            <PorterStatusBadge :value="item.status" :label="statusLabel(item.status)" />
          </template>
          <template #cell(checked_out_at)="{ item }">
            <div class="fw-semibold">{{ formatDateTime(item.checked_out_at) }}</div>
            <div class="small text-muted">Esperada: {{ formatDateTime(item.expected_return_at) }}</div>
          </template>
          <template #cell(actions)="{ item }">
            <BButton v-if="item.status === 'prestada'" size="sm" variant="outline-primary" @click="returnLoan(item)">Devolver</BButton>
            <span v-else class="text-muted small">{{ formatDateTime(item.returned_at) }}</span>
          </template>
        </BTable>

        <div class="pagination-row">
          <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="loadData" />
        </div>
      </BCard>

      <BCard class="keys-panel">
        <div class="panel-title-row">
          <div>
            <h5 class="mb-1">Catálogo</h5>
            <p class="mb-0 text-muted">Llaves registradas y disponibilidad.</p>
          </div>
          <BButton size="sm" variant="outline-primary" @click="openKeyModal">Nueva llave</BButton>
        </div>

        <BTable
          :items="keys"
          :busy="loading"
          :fields="keyFields"
          responsive
          hover
          show-empty
          table-class="keys-table"
        >
          <template #table-busy><LoadingState message="Cargando llaves..." compact /></template>
          <template #empty><div class="empty-table">No hay llaves registradas.</div></template>
          <template #cell(code)="{ item }">
            <strong>{{ item.code }}</strong>
          </template>
          <template #cell(name)="{ item }">
            <div class="fw-semibold">{{ item.name }}</div>
            <div class="small text-muted table-subline">{{ keyGroupLabel(item) }}</div>
            <div class="small text-muted table-subline">{{ keyLocation(item) }}</div>
          </template>
          <template #cell(active_loans_count)="{ item }">
            <PorterStatusBadge :value="keyStatusValue(item)" :label="keyStatusLabel(item)" />
          </template>
          <template #cell(actions)="{ item }">
            <BButton
              size="sm"
              variant="outline-primary"
              :disabled="!item.active || Boolean(item.active_loans_count)"
              @click="selectKeyForLoan(item)"
            >
              Prestar
            </BButton>
          </template>
        </BTable>
      </BCard>

      <BModal v-model="showKeyModal" title="Registrar llave" size="lg" hide-footer centered>
        <div class="key-modal-form">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Código</label>
              <BFormInput v-model="keyForm.code" autocomplete="off" />
            </div>
            <div class="col-md-8">
              <label class="form-label">Nombre</label>
              <BFormInput v-model="keyForm.name" autocomplete="off" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Manojo</label>
              <BFormSelect v-model="keyForm.porter_key_group_id" :options="groupOptions" />
            </div>
            <div class="col-md-8">
              <label class="form-label">Dependencia</label>
              <BFormSelect v-model="keyForm.maintenance_dependency_id" :options="dependencyOptions" />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="keyForm.observations" rows="3" />
            </div>
            <div class="col-12">
              <BFormCheckbox v-model="keyForm.active" switch>Activa</BFormCheckbox>
            </div>
          </div>
          <div class="modal-actions">
            <BButton variant="outline-secondary" :disabled="savingKey" @click="showKeyModal = false">Cancelar</BButton>
            <BButton variant="primary" :disabled="savingKey" @click="submitKey">{{ savingKey ? "Guardando..." : "Registrar llave" }}</BButton>
          </div>
        </div>
      </BModal>

      <BModal v-model="showGroupModal" title="Registrar manojo de llaves" size="lg" hide-footer centered>
        <div class="key-modal-form">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Código</label>
              <BFormInput v-model="groupForm.code" placeholder="Opcional" autocomplete="off" />
            </div>
            <div class="col-md-8">
              <label class="form-label">Nombre</label>
              <BFormInput v-model="groupForm.name" placeholder="Ej: Manojo gimnasio" autocomplete="off" />
            </div>
            <div class="col-12">
              <label class="form-label">Descripción</label>
              <BFormTextarea v-model="groupForm.description" rows="3" placeholder="Sector, uso o detalle operativo" />
            </div>
            <div class="col-12">
              <BFormCheckbox v-model="groupForm.active" switch>Activo</BFormCheckbox>
            </div>
          </div>
          <div class="modal-actions">
            <BButton variant="outline-secondary" :disabled="savingGroup" @click="showGroupModal = false">Cancelar</BButton>
            <BButton variant="primary" :disabled="savingGroup" @click="submitGroup">
              {{ savingGroup ? "Guardando..." : "Registrar manojo" }}
            </BButton>
          </div>
        </div>
      </BModal>
    </section>
  </Layout>
</template>

<style scoped>
.keys-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.keys-heading,
.panel-title-row,
.form-actions,
.filter-actions,
.pagination-row,
.modal-actions,
.active-row,
.group-row {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
}

.keys-heading {
  flex-wrap: wrap;
}

.keys-heading h4,
.panel-title-row h5 {
  color: #343a46;
  font-weight: 700;
}

.heading-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.keys-panel,
.key-stat-card {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(217, 226, 246, 0.92);
  border-radius: 0.5rem;
  box-shadow: 0 18px 42px rgba(90, 110, 150, 0.08);
}

.keys-panel :deep(.card-body) {
  padding: 1.35rem;
}

.key-stats {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.key-stat-card {
  min-height: 124px;
  padding: 1.1rem 1.2rem;
}

.stat-icon {
  align-items: center;
  border-radius: 0.5rem;
  display: inline-flex;
  height: 2.25rem;
  justify-content: center;
  margin-bottom: 0.65rem;
  width: 2.25rem;
}

.stat-icon i {
  font-size: 1.25rem;
}

.key-stat-card span,
.key-stat-card small,
.selected-key span,
.selected-key small,
.active-row span,
.active-row small {
  color: #747b91;
  display: block;
}

.key-stat-card strong,
.selected-key strong,
.active-row strong {
  color: #343a46;
  display: block;
}

.key-stat-card strong {
  font-size: 2rem;
  line-height: 1;
  margin: 0.35rem 0;
}

.tone-primary .stat-icon {
  background: rgba(85, 110, 230, 0.12);
  color: #556ee6;
}

.tone-success .stat-icon {
  background: rgba(52, 195, 143, 0.13);
  color: #34c38f;
}

.tone-warning .stat-icon {
  background: rgba(241, 180, 76, 0.16);
  color: #d99518;
}

.tone-danger .stat-icon {
  background: rgba(244, 106, 106, 0.14);
  color: #f46a6a;
}

.tone-info .stat-icon {
  background: rgba(80, 165, 241, 0.14);
  color: #50a5f1;
}

.top-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: minmax(0, 1.35fr) minmax(360px, 0.65fr);
}

.panel-title-row,
.history-heading {
  margin-bottom: 1rem;
}

.selected-key {
  background: #f8faff;
  border: 1px solid #e7ecf8;
  border-radius: 0.5rem;
  margin-bottom: 1rem;
  padding: 0.85rem 1rem;
}

.loan-form-grid,
.history-filters {
  display: grid;
  gap: 0.85rem;
  grid-template-columns: repeat(12, minmax(0, 1fr));
}

.loan-form-grid > *,
.history-filters > * {
  min-width: 0;
}

.span-4 {
  grid-column: span 4;
}

.span-6 {
  grid-column: span 6;
}

.span-8 {
  grid-column: span 8;
}

.span-12 {
  grid-column: span 12;
}

.form-label,
.history-filters :deep(.form-control),
.history-filters :deep(.form-select) {
  font-weight: 600;
}

.form-actions,
.modal-actions {
  border-top: 1px solid rgba(217, 226, 246, 0.88);
  justify-content: flex-end;
  margin-top: 1rem;
  padding-top: 1rem;
}

.active-list {
  display: grid;
  gap: 0.85rem;
}

.active-row {
  align-items: flex-start;
  border-bottom: 1px solid #eef2fa;
  padding-bottom: 0.85rem;
}

.active-row:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.active-row div {
  min-width: 0;
}

.active-row strong,
.active-row span,
.active-row small {
  overflow-wrap: anywhere;
}

.group-list {
  display: grid;
  gap: 0.75rem;
}

.group-row {
  background: #f8faff;
  border: 1px solid #e7ecf8;
  border-radius: 0.5rem;
  padding: 0.9rem 1rem;
}

.group-main {
  flex: 1;
  min-width: 0;
}

.group-main strong,
.group-main span {
  display: block;
  overflow-wrap: anywhere;
}

.group-main strong {
  color: #343a46;
}

.group-main span,
.group-counts {
  color: #747b91;
}

.group-counts {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.group-counts span {
  background: #fff;
  border: 1px solid #e7ecf8;
  border-radius: 0.5rem;
  padding: 0.35rem 0.55rem;
  white-space: nowrap;
}

.group-counts strong {
  color: #343a46;
}

.group-actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: flex-end;
}

.history-filters {
  margin-bottom: 1rem;
}

.filter-search {
  grid-column: span 7;
}

.filter-status {
  grid-column: span 2;
}

.filter-actions {
  grid-column: span 3;
  justify-content: flex-end;
}

.filter-actions .btn {
  min-width: 0;
  white-space: nowrap;
}

:deep(.keys-table) {
  table-layout: fixed;
  width: 100%;
}

:deep(.keys-table th) {
  border-bottom-color: #e7ecf8;
  color: #747b91;
  font-size: 0.75rem;
  letter-spacing: 0;
  text-transform: uppercase;
}

:deep(.keys-table td) {
  border-color: #eef2fa;
  color: #343a46;
  overflow-wrap: anywhere;
  vertical-align: middle;
}

:deep(.key-main-cell) {
  width: 28%;
}

:deep(.key-code-cell) {
  width: 14%;
}

.table-subline {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.empty-state,
.empty-table {
  color: #747b91;
  padding: 1rem 0;
  text-align: center;
}

.pagination-row {
  justify-content: flex-end;
  margin-top: 1rem;
}

.key-modal-form {
  display: grid;
  gap: 1rem;
}

:global(.swal-key-summary),
:global(.swal-key-confirm) {
  text-align: left;
}

:global(.swal-key-summary) {
  background: #f5f7fb;
  border: 1px solid #e4e9f6;
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
  padding: 0.85rem;
}

:global(.swal-key-summary strong),
:global(.swal-key-summary span),
:global(.swal-key-confirm span),
:global(.swal-key-confirm strong) {
  display: block;
}

:global(.swal-key-summary span),
:global(.swal-key-confirm span) {
  color: #747b91;
}

:global(.swal-key-confirm) {
  display: grid;
  gap: 0.7rem;
}

:global(.swal-key-validation) {
  margin: 0;
  padding-left: 1.25rem;
  text-align: left;
}

@media (max-width: 1399.98px) {
  .key-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .top-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 991.98px) {
  .span-4,
  .span-6,
  .span-8 {
    grid-column: span 6;
  }

  .filter-search,
  .filter-status,
  .filter-actions {
    grid-column: span 6;
  }
}

@media (max-width: 767.98px) {
  .key-stats,
  .loan-form-grid,
  .history-filters {
    grid-template-columns: 1fr;
  }

  .span-4,
  .span-6,
  .span-8,
  .span-12,
  .filter-search,
  .filter-status,
  .filter-actions {
    grid-column: span 1;
  }

  .keys-heading,
  .panel-title-row,
  .form-actions,
  .filter-actions,
  .modal-actions,
  .active-row,
  .group-row {
    align-items: stretch;
    flex-direction: column;
  }

  .group-actions {
    justify-content: flex-start;
  }

  .heading-actions,
  .heading-actions .btn,
  .form-actions .btn,
  .filter-actions .btn,
  .modal-actions .btn,
  .group-actions .btn {
    width: 100%;
  }
}
</style>
