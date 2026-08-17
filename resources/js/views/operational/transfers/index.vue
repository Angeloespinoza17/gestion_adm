<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../../layouts/main.vue";
import OperationalWorkspaceHeader from "../../../components/operational/operational-workspace-header.vue";
import OperationalCalendar from "../../../components/operational/operational-calendar.vue";

const emptyForm = () => ({
  requester_staff_id: null,
  activity_type: "salida_pedagogica",
  activity_name: "",
  course_subject: "",
  purpose: "",
  transport_date: "",
  departure_time: "",
  return_time: "",
  origin: "Colegio Nuestra Señora del Carmen",
  destination: "",
  transport_mode: "ida_vuelta",
  student_count: 0,
  adult_count: 1,
  reduced_mobility: false,
  mobility_requirements: "",
  visible_observations: "",
  urgent: false,
});

const emptyOperation = () => ({
  final_cost: null,
  confirmation_reference: "",
  confirmation_notes: "",
  dte_status: "pendiente",
  dte_number: "",
  dte_received_on: "",
  payment_status: "no_iniciado",
  payment_reference: "",
  payment_requested_on: "",
  payment_scheduled_on: "",
  paid_on: "",
  administrative_notes: "",
});

export default {
  components: { Layout, OperationalWorkspaceHeader, OperationalCalendar },
  data() {
    return {
      loading: false,
      saving: false,
      error: "",
      catalogs: { staff: [], capabilities: {} },
      providers: [],
      items: [],
      viewMode: "list",
      calendarItems: [],
      calendarLoading: false,
      calendarRange: null,
      calendarLoadedRange: "",
      pagination: { current_page: 1, last_page: 1, total: 0 },
      filters: { search: "", approval_status: "", service_status: "", date_from: "", date_to: "" },
      showForm: false,
      showDetail: false,
      editingId: null,
      form: emptyForm(),
      selected: null,
      documentFile: null,
      documentType: "solicitud_pedagogica",
      documentComments: "",
      quote: { provider_id: "", amount: null, valid_until: "", reference: "", notes: "" },
      operation: emptyOperation(),
      providerForm: { name: "", rut: "", contact_name: "", email: "", phone: "" },
      importFile: null,
      importPreview: null,
      report: null,
    };
  },
  computed: {
    mode() {
      if (this.$route.path.endsWith("/review")) return "review";
      if (this.$route.path.endsWith("/management")) return "management";
      if (this.$route.path.endsWith("/reports")) return "reports";
      return "requests";
    },
    pageTitle() {
      return { requests: "Mis solicitudes de traslado", review: "Bandeja de visación", management: "Gestión de traslados", reports: "Reportes de traslados" }[this.mode];
    },
    pageDescription() {
      return {
        requests: "Crea la solicitud, adjunta el respaldo institucional y sigue su avance.",
        review: "Solicitudes asignadas para visación de Subdirección.",
        management: "Cotización, aprobación, confirmación, DTE y pago del servicio.",
        reports: "Indicadores operativos, costos y exportación de resultados.",
      }[this.mode];
    },
    canEditSelected() {
      return this.canEditTransfer(this.selected);
    },
    canSubmitSelected() {
      return this.selected && this.mode === "requests" && ["borrador", "observado"].includes(this.selected.approval_status);
    },
    selectedQuote() {
      return (this.selected?.quotes || []).find((item) => item.selected) || null;
    },
    importableRows() {
      return (this.importPreview?.rows || []).filter((row) => row.can_import);
    },
    calendarEvents() {
      return this.calendarItems.map((item) => {
        const hasTime = Boolean(item.departure_time);
        const start = hasTime ? `${item.transport_date}T${String(item.departure_time).slice(0, 8)}` : item.transport_date;
        const end = item.return_time ? `${item.transport_date}T${String(item.return_time).slice(0, 8)}` : undefined;
        const color = this.calendarEventColor(item);
        return {
          id: `transfer-${item.id}`,
          title: `${item.activity_name} · ${item.passenger_count || 0} pasajeros`,
          start,
          end,
          allDay: !hasTime,
          backgroundColor: color,
          borderColor: color,
          textColor: "#fff",
          extendedProps: {
            record: item,
            tooltip: `${item.folio} · ${item.destination} · ${this.label("approval_statuses", item.approval_status)}`,
          },
        };
      });
    },
  },
  watch: {
    "$route.path"() { this.resetPage(); },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.resetPage();
  },
  methods: {
    async resetPage() {
      this.showDetail = false;
      this.error = "";
      if (this.mode === "reports") await this.loadReport();
      else await this.loadItems();
      if (this.mode === "management") await this.loadProviders();
    },
    async loadCatalogs() {
      try {
        const { data } = await axios.get("/api/operational/transfers/catalogs");
        this.catalogs = data;
      } catch (error) { this.error = this.formatError(error); }
    },
    async loadItems(page = 1) {
      this.loading = true;
      this.error = "";
      try {
        const queue = { requests: "mine", review: "review", management: "management" }[this.mode];
        const { data } = await axios.get("/api/operational/transfers", {
          params: { page, queue, per_page: 20, ...this.clean(this.filters) },
        });
        this.items = data.data || [];
        this.pagination = { current_page: data.current_page || 1, last_page: data.last_page || 1, total: data.total || 0 };
      } catch (error) { this.error = this.formatError(error); }
      finally { this.loading = false; }
    },
    async loadCalendar(range = null) {
      if (range) {
        const rangeKey = `${range.from}:${range.to}`;
        if (rangeKey === this.calendarLoadedRange) return;
        if (rangeKey === `${this.calendarRange?.from}:${this.calendarRange?.to}` && this.calendarLoading) return;
        this.calendarRange = range;
      }
      if (!this.calendarRange) return;
      this.calendarLoading = true;
      this.error = "";
      try {
        const queue = { requests: "mine", review: "review", management: "management" }[this.mode];
        const { date_from, date_to, ...calendarFilters } = this.filters;
        const { data } = await axios.get("/api/operational/transfers/calendar", {
          params: this.clean({
            queue,
            ...calendarFilters,
            date_from: this.calendarRange.from,
            date_to: this.calendarRange.to,
          }),
        });
        this.calendarItems = data.data || [];
        this.calendarLoadedRange = `${this.calendarRange.from}:${this.calendarRange.to}`;
      } catch (error) { this.error = this.formatError(error); }
      finally { this.calendarLoading = false; }
    },
    applyViewFilters() {
      if (this.mode === "reports") return this.loadReport();
      return this.viewMode === "calendar" ? this.loadCalendar() : this.loadItems();
    },
    switchView(view) {
      this.viewMode = view;
      if (view === "calendar" && this.calendarRange) this.loadCalendar();
    },
    async loadProviders() {
      try {
        const { data } = await axios.get("/api/operational/transfers/providers", { params: { include_inactive: true } });
        this.providers = data.data || [];
      } catch (error) { this.error = this.formatError(error); }
    },
    openCreate() {
      this.editingId = null;
      this.form = emptyForm();
      this.form.requester_staff_id = this.catalogs.current_staff_id || this.catalogs.staff?.[0]?.id || null;
      this.showForm = true;
    },
    canEditTransfer(item) {
      if (!item || this.mode === "reports" || this.mode === "review") return false;
      if (this.catalogs.capabilities?.manage) return ["requests", "management"].includes(this.mode);
      return this.mode === "requests" && ["borrador", "observado"].includes(item.approval_status);
    },
    prepareEdit(item) {
      this.selected = item;
      this.editingId = item.id;
      const defaults = emptyForm();
      this.form = Object.fromEntries(Object.keys(defaults).map((key) => [key, item[key] ?? defaults[key]]));
      this.showDetail = false;
      this.showForm = true;
    },
    async editItem(item) {
      if (!this.canEditTransfer(item)) return;
      this.saving = true;
      this.error = "";
      try {
        const { data } = await axios.get(`/api/operational/transfers/${item.id}`);
        this.prepareEdit(data.data);
      } catch (error) { this.error = this.formatError(error); }
      finally { this.saving = false; }
    },
    editSelected() {
      if (this.canEditSelected) this.prepareEdit(this.selected);
    },
    async saveDraft() {
      this.saving = true;
      try {
        const endpoint = this.editingId ? `/api/operational/transfers/${this.editingId}` : "/api/operational/transfers";
        const method = this.editingId ? "put" : "post";
        const { data } = await axios[method](endpoint, this.form);
        this.showForm = false;
        if (this.viewMode === "calendar" && this.mode !== "reports") await this.loadCalendar();
        else if (this.mode !== "reports") await this.loadItems(this.pagination.current_page);
        await this.openDetail(data.data);
        this.toast(data.message);
      } catch (error) { this.error = this.formatError(error); }
      finally { this.saving = false; }
    },
    async openDetail(item) {
      this.saving = true;
      try {
        const { data } = await axios.get(`/api/operational/transfers/${item.id}`);
        this.selected = data.data;
        this.syncOperation();
        this.showDetail = true;
      } catch (error) { this.error = this.formatError(error); }
      finally { this.saving = false; }
    },
    syncOperation() {
      const source = this.selected?.operation || {};
      this.operation = { ...emptyOperation(), ...Object.fromEntries(Object.keys(emptyOperation()).map((key) => [key, source[key] ?? this.selected?.[key] ?? emptyOperation()[key]])) };
    },
    async refreshSelected() {
      if (this.selected) await this.openDetail(this.selected);
      await this.loadItems(this.pagination.current_page);
      if (this.viewMode === "calendar") await this.loadCalendar();
    },
    async uploadDocument() {
      if (!this.documentFile || !this.selected) return;
      this.saving = true;
      try {
        const body = new FormData();
        body.append("document", this.documentFile);
        body.append("document_type", this.documentType);
        if (this.documentComments) body.append("comments", this.documentComments);
        await axios.post(`/api/operational/transfers/${this.selected.id}/documents`, body);
        this.documentFile = null;
        this.documentComments = "";
        if (this.$refs.documentInput) this.$refs.documentInput.value = "";
        await this.refreshSelected();
        this.toast("Documento adjuntado.");
      } catch (error) { this.error = this.formatError(error); }
      finally { this.saving = false; }
    },
    async submitRequest() {
      await this.postAction(`/api/operational/transfers/${this.selected.id}/submit`, {}, "Solicitud enviada a Subdirección.");
    },
    async cancelRequest() {
      const comment = await this.askComment("Cancelar solicitud", true);
      if (!comment) return;
      await this.postAction(`/api/operational/transfers/${this.selected.id}/cancel`, { comment }, "Solicitud cancelada.");
    },
    async decision(action) {
      const required = action !== "approve";
      const comment = await this.askComment(action === "approve" ? "Confirmar decisión" : "Fundamento de la decisión", required);
      if (comment === null) return;
      const stage = this.mode === "review" ? "visor" : "administration";
      await this.postAction(`/api/operational/transfers/${this.selected.id}/${stage}/${action}`, { comment }, "Decisión registrada.");
    },
    async addProvider() {
      if (!this.providerForm.name) return;
      try {
        await axios.post("/api/operational/transfers/providers", this.clean(this.providerForm));
        this.providerForm = { name: "", rut: "", contact_name: "", email: "", phone: "" };
        await this.loadProviders();
        this.toast("Proveedor creado.");
      } catch (error) { this.error = this.formatError(error); }
    },
    async addQuote() {
      if (!this.quote.provider_id || this.quote.amount === null) return;
      await this.postAction(`/api/operational/transfers/${this.selected.id}/quotes`, this.clean(this.quote), "Cotización registrada.");
      this.quote = { provider_id: "", amount: null, valid_until: "", reference: "", notes: "" };
    },
    async selectQuote(quote) {
      await this.postAction(`/api/operational/transfers/${this.selected.id}/quotes/${quote.id}/select`, {}, "Cotización seleccionada.");
    },
    async saveOperation() {
      this.saving = true;
      try {
        await axios.put(`/api/operational/transfers/${this.selected.id}/operation`, this.clean(this.operation));
        await this.refreshSelected();
        this.toast("Gestión administrativa actualizada.");
      } catch (error) { this.error = this.formatError(error); }
      finally { this.saving = false; }
    },
    async confirmTransfer() {
      await this.postAction(`/api/operational/transfers/${this.selected.id}/confirm`, this.clean(this.operation), "Servicio confirmado.");
    },
    async executeTransfer() {
      await this.postAction(`/api/operational/transfers/${this.selected.id}/execute`, {}, "Traslado marcado como ejecutado.");
    },
    async postAction(url, payload, fallback) {
      this.saving = true;
      try {
        const { data } = await axios.post(url, payload);
        if (data.data) this.selected = data.data;
        await this.refreshSelected();
        this.toast(data.message || fallback);
      } catch (error) { this.error = this.formatError(error); }
      finally { this.saving = false; }
    },
    async exportPdf(item = this.selected) {
      try {
        const response = await axios.get(`/api/operational/transfers/${item.id}/pdf`, { responseType: "blob" });
        this.saveBlob(response.data, `Solicitud_Traslado_${item.folio}.pdf`, "application/pdf");
      } catch (error) { this.error = this.formatError(error); }
    },
    async downloadDocument(document) {
      try {
        const response = await axios.get(`/api/operational/transfers/documents/${document.id}/download`, { responseType: "blob" });
        this.saveBlob(response.data, document.file_name, document.file_type);
      } catch (error) { this.error = this.formatError(error); }
    },
    async previewImport() {
      if (!this.importFile) return;
      this.saving = true;
      try {
        const body = new FormData();
        body.append("file", this.importFile);
        const { data } = await axios.post("/api/operational/transfers/imports/preview", body);
        this.importPreview = data.data;
      } catch (error) { this.error = this.formatError(error); }
      finally { this.saving = false; }
    },
    async commitImport() {
      if (!this.importPreview) return;
      this.saving = true;
      try {
        const { data } = await axios.post("/api/operational/transfers/imports/commit", {
          token: this.importPreview.token,
          rows: this.importableRows.map((row) => row.row),
        });
        this.importPreview = null;
        this.importFile = null;
        await this.loadItems();
        this.toast(`${data.data.created} registros importados; ${data.data.skipped} omitidos.`);
      } catch (error) { this.error = this.formatError(error); }
      finally { this.saving = false; }
    },
    async loadReport() {
      this.loading = true;
      try {
        const { data } = await axios.get("/api/operational/transfers/reports", { params: this.clean(this.filters) });
        this.report = data;
      } catch (error) { this.error = this.formatError(error); }
      finally { this.loading = false; }
    },
    exportCsv() {
      const headers = ["Folio", "Fecha", "Solicitante", "Actividad", "Destino", "Pasajeros", "Aprobación", "Servicio", "Proveedor", "Costo", "DTE", "Pago"];
      const rows = (this.report?.rows || []).map((item) => [item.folio, item.transport_date, item.requester_name_snapshot, item.activity_name, item.destination, item.passenger_count, this.label("approval_statuses", item.approval_status), this.label("service_statuses", item.service_status), item.operation?.provider?.name || "", item.operation?.final_cost || "", this.label("dte_statuses", item.dte_status), this.label("payment_statuses", item.payment_status)]);
      const csv = [headers, ...rows].map((row) => row.map((value) => `"${String(value ?? "").replaceAll('"', '""')}"`).join(";")).join("\n");
      this.saveBlob("\ufeff" + csv, "reporte_traslados.csv", "text/csv;charset=utf-8");
    },
    async askComment(title, required) {
      const result = await Swal.fire({ title, input: "textarea", inputLabel: required ? "Comentario obligatorio" : "Comentario opcional", showCancelButton: true, confirmButtonText: "Continuar", cancelButtonText: "Cancelar", inputValidator: (value) => required && !value ? "Ingresa un fundamento." : undefined });
      if (!result.isConfirmed) return null;
      return result.value || "";
    },
    label(collection, value) {
      return (this.catalogs[collection] || []).find((item) => item.value === value)?.label || value || "—";
    },
    statusClass(status) {
      if (["aprobado", "importado_historico"].includes(status)) return "bg-success-subtle text-success";
      if (["rechazado", "cancelado"].includes(status)) return "bg-danger-subtle text-danger";
      if (["observado", "pendiente_visacion", "pendiente_administracion"].includes(status)) return "bg-warning-subtle text-warning-emphasis";
      return "bg-secondary-subtle text-secondary";
    },
    calendarEventColor(item) {
      if (item.urgent) return "#dc3154";
      if (["rechazado", "cancelado"].includes(item.approval_status)) return "#94a3b8";
      if (["aprobado", "importado_historico"].includes(item.approval_status)) return "#0f9f7a";
      if (["observado", "pendiente_visacion", "pendiente_administracion"].includes(item.approval_status)) return "#d88916";
      return "#5965cf";
    },
    formatDate(value) { return value ? String(value).slice(0, 10).split("-").reverse().join("-") : "—"; },
    money(value) { return value === null || value === undefined || value === "" ? "—" : new Intl.NumberFormat("es-CL", { style: "currency", currency: "CLP", maximumFractionDigits: 0 }).format(value); },
    clean(object) { return Object.fromEntries(Object.entries(object).filter(([, value]) => value !== "" && value !== null && value !== undefined)); },
    formatError(error) {
      const errors = error.response?.data?.errors;
      if (errors) return Object.values(errors).flat().join(" ");
      return error.response?.data?.message || "No fue posible completar la operación.";
    },
    toast(title) { Swal.fire({ toast: true, position: "top-end", icon: "success", title, showConfirmButton: false, timer: 2600 }); },
    saveBlob(content, name, type) {
      const blob = content instanceof Blob ? content : new Blob([content], { type });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a"); link.href = url; link.download = name; link.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    },
  },
};
</script>

<template>
  <Layout>
    <div class="container-fluid py-3 operational-transfers operational-workspace">
      <OperationalWorkspaceHeader :title="pageTitle" :subtitle="pageDescription" icon="bx bx-bus">
        <template #actions>
          <button v-if="mode === 'requests' && catalogs.capabilities?.create" class="btn btn-light" @click="openCreate"><i class="bx bx-plus me-1"></i>Nueva solicitud</button>
          <button v-if="mode === 'reports'" class="btn btn-light" @click="exportCsv"><i class="bx bx-spreadsheet me-1"></i>Exportar CSV</button>
        </template>
      </OperationalWorkspaceHeader>

      <ul class="nav op-section-tabs transfer-section-tabs mb-4">
        <li class="nav-item"><router-link class="nav-link" to="/operational/transfers"><i class="bx bx-file"></i>Mis solicitudes</router-link></li>
        <li v-if="catalogs.capabilities?.review" class="nav-item"><router-link class="nav-link" to="/operational/transfers/review"><i class="bx bx-check-shield"></i>Visación</router-link></li>
        <li v-if="catalogs.capabilities?.manage" class="nav-item"><router-link class="nav-link" to="/operational/transfers/management"><i class="bx bx-cog"></i>Gestión administrativa</router-link></li>
        <li v-if="catalogs.capabilities?.export" class="nav-item"><router-link class="nav-link" to="/operational/transfers/reports"><i class="bx bx-bar-chart-alt-2"></i>Reportes</router-link></li>
      </ul>

      <div v-if="mode !== 'reports'" class="transfer-view-toolbar mb-3">
        <div>
          <strong>Visualización</strong>
          <span>Consulta las solicitudes como bandeja o distribuidas por fecha.</span>
        </div>
        <div class="transfer-view-switch" role="group" aria-label="Cambiar visualización de traslados">
          <button type="button" :class="{ active: viewMode === 'list' }" @click="switchView('list')"><i class="bx bx-list-ul"></i>Vista lista</button>
          <button type="button" :class="{ active: viewMode === 'calendar' }" @click="switchView('calendar')"><i class="bx bx-calendar-event"></i>Calendario</button>
        </div>
      </div>

      <div v-if="error" class="alert alert-danger d-flex justify-content-between"><span>{{ error }}</span><button class="btn-close" @click="error = ''"></button></div>

      <div v-if="mode === 'reports' && report" class="row g-3 mb-4">
        <div v-for="card in [{l:'Solicitudes',v:report.summary.requests,i:'bx-file',t:'indigo'}, {l:'Pasajeros',v:report.summary.passengers,i:'bx-group',t:'sky'}, {l:'Confirmados',v:report.summary.confirmed,i:'bx-check-circle',t:'emerald'}, {l:'Costo total',v:money(report.summary.total_cost),i:'bx-dollar-circle',t:'amber'}]" :key="card.l" class="col-6 col-md-3">
          <div class="op-metric-card" :class="`op-tone-${card.t}`"><div class="op-metric-top"><span class="op-metric-label">{{ card.l }}</span><span class="op-metric-icon"><i :class="`bx ${card.i}`"></i></span></div><div class="op-metric-value">{{ card.v }}</div><div class="op-metric-note">Resumen del período</div></div>
        </div>
      </div>

      <div class="op-filter-panel mb-4">
          <div class="row g-2 align-items-end">
            <div v-if="mode !== 'reports'" class="col-lg-4"><label class="op-filter-label">Buscar</label><input v-model="filters.search" class="form-control" placeholder="Folio, actividad, destino o solicitante" @keyup.enter="applyViewFilters"></div>
            <div v-if="viewMode !== 'calendar' || mode === 'reports'" class="col-md-3 col-lg-2"><label class="op-filter-label">Desde</label><input v-model="filters.date_from" type="date" class="form-control"></div>
            <div v-if="viewMode !== 'calendar' || mode === 'reports'" class="col-md-3 col-lg-2"><label class="op-filter-label">Hasta</label><input v-model="filters.date_to" type="date" class="form-control"></div>
            <div class="col-md-3 col-lg-2"><label class="op-filter-label">Aprobación</label><select v-model="filters.approval_status" class="form-select"><option value="">Todas</option><option v-for="item in catalogs.approval_statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></div>
            <div class="col-md-3 col-lg-2"><button class="btn btn-primary w-100" @click="applyViewFilters"><i class="bx bx-filter-alt me-1"></i>Aplicar</button></div>
          </div>
      </div>

      <div v-if="mode === 'management' && viewMode === 'list' && catalogs.capabilities?.import" class="card op-surface mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><div><h5 class="mb-1">Importar planilla histórica</h5><small class="text-muted">Compatible con la estructura de Gestión traslados.xlsx</small></div><i class="bx bx-import fs-3 text-primary"></i></div>
        <div class="card-body">
          <div class="input-group"><input type="file" accept=".xlsx" class="form-control" @change="importFile = $event.target.files[0]"><button class="btn btn-outline-primary" :disabled="!importFile || saving" @click="previewImport">Vista previa</button></div>
          <div v-if="importPreview" class="mt-3">
            <div class="alert alert-info">{{ importPreview.importable_rows }} de {{ importPreview.total_rows }} filas son importables. Las filas con advertencias críticas se omitirán.</div>
            <div class="table-responsive import-preview"><table class="table table-sm align-middle op-data-table"><thead><tr><th>Fila</th><th>Fecha</th><th>Destino</th><th>Pasajeros</th><th>Proveedor</th><th>Costo</th><th>Revisión</th></tr></thead><tbody><tr v-for="row in importPreview.rows" :key="row.row"><td>{{ row.row }}</td><td>{{ formatDate(row.transport_date) }}</td><td>{{ row.destination || '—' }}</td><td>{{ row.passenger_count }}</td><td>{{ row.provider || '—' }}</td><td>{{ money(row.final_cost) }}</td><td><span v-if="row.can_import && !row.warnings.length" class="text-success">Lista</span><span v-else class="text-warning">{{ row.warnings.join(' ') }}</span></td></tr></tbody></table></div>
            <button class="btn btn-primary mt-2" :disabled="!importableRows.length || saving" @click="commitImport">Importar {{ importableRows.length }} filas válidas</button>
          </div>
        </div>
      </div>

      <OperationalCalendar
        v-if="mode !== 'reports' && viewMode === 'calendar'"
        title="Calendario de traslados"
        subtitle="Cada evento representa una solicitud en la fecha y horario de salida. Selecciónalo para abrir su gestión."
        :events="calendarEvents"
        :loading="calendarLoading"
        @range-change="loadCalendar"
        @event-click="openDetail"
      >
        <template #legend>
          <div class="calendar-legend"><span><i class="legend-dot legend-dot-indigo"></i>Borrador</span><span><i class="legend-dot legend-dot-amber"></i>En revisión</span><span><i class="legend-dot legend-dot-green"></i>Aprobado</span><span><i class="legend-dot legend-dot-red"></i>Urgente</span></div>
        </template>
      </OperationalCalendar>

      <div v-if="mode === 'reports' || viewMode === 'list'" class="card op-surface">
        <div class="table-responsive">
          <table class="table align-middle mb-0 op-data-table">
            <thead><tr><th>Folio / fecha</th><th>Actividad</th><th>Solicitante</th><th>Destino</th><th>Pasajeros</th><th>Aprobación</th><th>Servicio</th><th v-if="mode === 'reports'">Costo</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
              <tr v-if="loading"><td colspan="9" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
              <tr v-else-if="!(mode === 'reports' ? report?.rows : items)?.length"><td colspan="9" class="op-empty-state"><i class="bx bx-bus"></i>No hay traslados para mostrar.</td></tr>
              <tr v-for="item in (mode === 'reports' ? report?.rows : items)" :key="item.id">
                <td><strong>{{ item.folio }}</strong><small class="d-block text-muted">{{ formatDate(item.transport_date) }} · {{ String(item.departure_time || '').slice(0,5) }}</small></td>
                <td><span class="fw-semibold">{{ item.activity_name }}</span><small v-if="item.urgent" class="d-block text-danger">Urgente</small></td>
                <td>{{ item.requester_name_snapshot || item.requested_by?.name || '—' }}</td><td>{{ item.destination }}</td><td>{{ item.passenger_count }}</td>
                <td><span class="badge rounded-pill" :class="statusClass(item.approval_status)">{{ label('approval_statuses', item.approval_status) }}</span></td>
                <td>{{ label('service_statuses', item.service_status) }}</td><td v-if="mode === 'reports'">{{ money(item.operation?.final_cost) }}</td>
                <td class="text-end"><div class="transfer-actions"><button class="transfer-action transfer-action-view" title="Abrir detalle del traslado" @click="openDetail(item)"><i class="bx bx-show"></i><span>Ver</span></button><button v-if="canEditTransfer(item)" class="transfer-action transfer-action-edit" title="Editar antecedentes del traslado" @click="editItem(item)"><i class="bx bx-edit-alt"></i><span>Editar</span></button><button class="transfer-action transfer-action-pdf" title="Descargar solicitud en PDF" @click="exportPdf(item)"><i class="bx bxs-file-pdf"></i><span>PDF</span></button></div></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="mode !== 'reports' && pagination.last_page > 1" class="card-footer bg-white d-flex justify-content-between"><button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page <= 1" @click="loadItems(pagination.current_page - 1)">Anterior</button><span class="small text-muted">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span><button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page >= pagination.last_page" @click="loadItems(pagination.current_page + 1)">Siguiente</button></div>
      </div>
    </div>

    <Teleport to="body">
    <div v-if="showForm" class="modal operational-modal d-block" tabindex="-1" role="dialog" aria-modal="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" @submit.prevent="saveDraft"><div class="modal-header op-modal-header"><div><h5 class="modal-title">{{ editingId ? 'Editar traslado' : 'Nueva solicitud de traslado' }}</h5><small class="text-white-50">{{ editingId ? 'Actualiza los antecedentes sin alterar el estado actual del traslado.' : 'Se guardará primero como borrador.' }}</small></div><button type="button" class="btn-close" @click="showForm = false"></button></div><div class="modal-body">
      <div class="row g-3">
        <div v-if="catalogs.staff?.length > 1" class="col-md-6"><label class="form-label">Solicitante *</label><select v-model="form.requester_staff_id" class="form-select" required><option v-for="staff in catalogs.staff" :key="staff.id" :value="staff.id">{{ staff.name }} · {{ staff.role }}</option></select></div>
        <div class="col-md-6"><label class="form-label">Tipo de actividad *</label><select v-model="form.activity_type" class="form-select" required><option v-for="item in catalogs.activity_types" :key="item.value" :value="item.value">{{ item.label }}</option></select></div>
        <div class="col-md-8"><label class="form-label">Nombre de la actividad *</label><input v-model="form.activity_name" class="form-control" required maxlength="255"></div><div class="col-md-4"><label class="form-label">Curso / asignatura</label><input v-model="form.course_subject" class="form-control"></div>
        <div class="col-12"><label class="form-label">Propósito pedagógico o institucional</label><textarea v-model="form.purpose" class="form-control" rows="2"></textarea></div>
        <div class="col-md-3"><label class="form-label">Fecha *</label><input v-model="form.transport_date" type="date" class="form-control" required></div><div class="col-md-3"><label class="form-label">Salida *</label><input v-model="form.departure_time" type="time" class="form-control" required></div><div class="col-md-3"><label class="form-label">Retorno</label><input v-model="form.return_time" type="time" class="form-control"></div><div class="col-md-3"><label class="form-label">Modalidad *</label><select v-model="form.transport_mode" class="form-select"><option v-for="item in catalogs.transport_modes" :key="item.value" :value="item.value">{{ item.label }}</option></select></div>
        <div class="col-md-6"><label class="form-label">Origen *</label><input v-model="form.origin" class="form-control" required></div><div class="col-md-6"><label class="form-label">Destino *</label><input v-model="form.destination" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Estudiantes *</label><input v-model.number="form.student_count" type="number" min="0" class="form-control" required></div><div class="col-md-3"><label class="form-label">Adultos *</label><input v-model.number="form.adult_count" type="number" min="0" class="form-control" required></div><div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="mobility" v-model="form.reduced_mobility" class="form-check-input" type="checkbox"><label for="mobility" class="form-check-label">Movilidad reducida</label></div></div><div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="urgent" v-model="form.urgent" class="form-check-input" type="checkbox"><label for="urgent" class="form-check-label">Solicitud urgente</label></div></div>
        <div v-if="form.reduced_mobility" class="col-12"><label class="form-label">Apoyos o adaptaciones requeridas *</label><textarea v-model="form.mobility_requirements" class="form-control" rows="2" required></textarea></div><div class="col-12"><label class="form-label">Observaciones</label><textarea v-model="form.visible_observations" class="form-control" rows="2"></textarea></div>
      </div></div><div class="modal-footer"><button type="button" class="btn btn-light" @click="showForm = false">Cerrar</button><button class="btn btn-primary" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>{{ editingId ? 'Guardar cambios' : 'Guardar borrador' }}</button></div></form></div><div class="modal-backdrop show"></div></div>
    </Teleport>

    <Teleport to="body">
    <div v-if="showDetail && selected" class="modal operational-modal d-block" tabindex="-1" role="dialog" aria-modal="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header op-modal-header"><div><span class="badge rounded-pill mb-2" :class="statusClass(selected.approval_status)">{{ label('approval_statuses', selected.approval_status) }}</span><h5 class="modal-title">{{ selected.folio }} · {{ selected.activity_name }}</h5></div><button class="btn-close" @click="showDetail = false"></button></div><div class="modal-body">
      <div class="row g-4">
        <div class="col-lg-7"><div class="detail-card"><h6>Solicitud</h6><dl class="row mb-0"><dt class="col-sm-4">Solicitante</dt><dd class="col-sm-8">{{ selected.requester_name_snapshot }}</dd><dt class="col-sm-4">Visador/a</dt><dd class="col-sm-8">{{ selected.visor_name_snapshot || 'No configurado en organigrama' }}</dd><dt class="col-sm-4">Fecha y horario</dt><dd class="col-sm-8">{{ formatDate(selected.transport_date) }} · {{ String(selected.departure_time).slice(0,5) }} a {{ selected.return_time ? String(selected.return_time).slice(0,5) : '—' }}</dd><dt class="col-sm-4">Ruta</dt><dd class="col-sm-8">{{ selected.origin }} → {{ selected.destination }}</dd><dt class="col-sm-4">Pasajeros</dt><dd class="col-sm-8">{{ selected.passenger_count }} ({{ selected.student_count }} estudiantes, {{ selected.adult_count }} adultos)</dd><dt class="col-sm-4">Propósito</dt><dd class="col-sm-8">{{ selected.purpose || '—' }}</dd><dt v-if="selected.visible_observations" class="col-sm-4 text-warning">Observación</dt><dd v-if="selected.visible_observations" class="col-sm-8 text-warning">{{ selected.visible_observations }}</dd></dl></div></div>
        <div class="col-lg-5"><div class="detail-card"><h6>Estado operativo</h6><div class="d-flex justify-content-between border-bottom py-2"><span>Servicio</span><strong>{{ label('service_statuses', selected.service_status) }}</strong></div><div class="d-flex justify-content-between border-bottom py-2"><span>DTE</span><strong>{{ label('dte_statuses', selected.dte_status) }}</strong></div><div class="d-flex justify-content-between py-2"><span>Pago</span><strong>{{ label('payment_statuses', selected.payment_status) }}</strong></div></div></div>

        <div class="col-12"><div class="detail-card"><div class="d-flex justify-content-between"><h6>Documentos</h6><span class="text-muted small">{{ selected.documents?.length || 0 }} archivo(s)</span></div><div v-if="selected.documents?.length" class="list-group list-group-flush mb-3"><button v-for="document in selected.documents" :key="document.id" class="list-group-item list-group-item-action px-0 d-flex justify-content-between" @click="downloadDocument(document)"><span><i class="bx bx-paperclip me-2"></i>{{ document.file_name }}<small class="d-block text-muted ms-4">{{ document.document_type }}<span v-if="document.official_snapshot"> · Copia oficial</span></small></span><i class="bx bx-download"></i></button></div>
          <div v-if="canEditSelected || mode === 'management'" class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label">Tipo</label><select v-model="documentType" class="form-select"><option v-for="item in catalogs.document_types" :key="item.value" :value="item.value">{{ item.label }}</option></select></div><div class="col-md-5"><label class="form-label">Archivo</label><input ref="documentInput" type="file" class="form-control" @change="documentFile = $event.target.files[0]"></div><div class="col-md-3"><button class="btn btn-outline-primary w-100" :disabled="!documentFile || saving" @click="uploadDocument">Adjuntar</button></div></div>
        </div></div>

        <div v-if="selected.approvals?.length" class="col-12"><div class="detail-card"><h6>Historial de decisiones</h6><div v-for="approval in selected.approvals" :key="approval.id" class="border-start border-3 border-primary ps-3 py-2 mb-2"><strong>{{ approval.step }} · {{ approval.decision }}</strong><span class="text-muted ms-2">{{ approval.actor_user?.name }} · {{ approval.acted_at }}</span><div v-if="approval.comments" class="small mt-1">{{ approval.comments }}</div></div></div></div>

        <template v-if="mode === 'management'">
          <div class="col-12"><div class="detail-card"><h6>Proveedores y cotizaciones</h6><div class="row g-2 mb-3"><div class="col-md-4"><input v-model="providerForm.name" class="form-control" placeholder="Nuevo proveedor"></div><div class="col-md-3"><input v-model="providerForm.rut" class="form-control" placeholder="RUT"></div><div class="col-md-3"><input v-model="providerForm.phone" class="form-control" placeholder="Teléfono"></div><div class="col-md-2"><button class="btn btn-outline-secondary w-100" @click="addProvider">Crear</button></div></div>
            <div v-if="['pendiente_administracion','aprobado'].includes(selected.approval_status)" class="row g-2 align-items-end mb-3"><div class="col-md-4"><label class="form-label">Proveedor</label><select v-model="quote.provider_id" class="form-select"><option value="">Selecciona</option><option v-for="provider in providers.filter(p => p.active)" :key="provider.id" :value="provider.id">{{ provider.name }}</option></select></div><div class="col-md-3"><label class="form-label">Monto</label><input v-model.number="quote.amount" type="number" min="0" class="form-control"></div><div class="col-md-3"><label class="form-label">Referencia</label><input v-model="quote.reference" class="form-control"></div><div class="col-md-2"><button class="btn btn-primary w-100" @click="addQuote">Agregar</button></div></div>
            <div v-if="selected.quotes?.length" class="table-responsive"><table class="table table-sm"><thead><tr><th>Proveedor</th><th>Monto</th><th>Referencia</th><th>Estado</th><th></th></tr></thead><tbody><tr v-for="item in selected.quotes" :key="item.id"><td>{{ item.provider?.name }}</td><td>{{ money(item.amount) }}</td><td>{{ item.reference || '—' }}</td><td><span v-if="item.selected" class="badge bg-success">Seleccionada</span></td><td class="text-end"><button v-if="!item.selected" class="btn btn-sm btn-outline-primary" @click="selectQuote(item)">Seleccionar</button></td></tr></tbody></table></div><div v-else class="text-muted">Aún no existen cotizaciones.</div>
          </div></div>
          <div class="col-12"><div class="detail-card"><h6>Gestión administrativa</h6><div class="row g-3"><div class="col-md-4"><label class="form-label">Costo final</label><input v-model.number="operation.final_cost" type="number" class="form-control"></div><div class="col-md-4"><label class="form-label">Referencia de confirmación *</label><input v-model="operation.confirmation_reference" class="form-control"></div><div class="col-md-4"><label class="form-label">Número DTE</label><input v-model="operation.dte_number" class="form-control"></div><div class="col-md-4"><label class="form-label">Estado DTE</label><select v-model="operation.dte_status" class="form-select"><option v-for="item in catalogs.dte_statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></div><div class="col-md-4"><label class="form-label">Estado pago</label><select v-model="operation.payment_status" class="form-select"><option v-for="item in catalogs.payment_statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></div><div class="col-md-4"><label class="form-label">Referencia pago</label><input v-model="operation.payment_reference" class="form-control"></div><div class="col-12"><label class="form-label">Notas administrativas</label><textarea v-model="operation.administrative_notes" class="form-control" rows="2"></textarea></div></div><button class="btn btn-outline-primary mt-3" @click="saveOperation">Guardar gestión</button></div></div>
        </template>
      </div>
    </div><div class="modal-footer flex-wrap">
      <button class="btn btn-light" @click="showDetail = false">Cerrar</button><button class="btn btn-outline-secondary" @click="exportPdf(selected)"><i class="bx bxs-file-pdf me-1"></i>Exportar PDF</button>
      <button v-if="canEditSelected" class="btn btn-outline-primary" @click="editSelected"><i class="bx bx-edit-alt me-1"></i>Editar</button><button v-if="canSubmitSelected" class="btn btn-primary" @click="submitRequest">Enviar a visación</button><button v-if="mode === 'requests' && !['cancelado','rechazado'].includes(selected.approval_status)" class="btn btn-outline-danger" @click="cancelRequest">Cancelar</button>
      <template v-if="mode === 'review' && selected.approval_status === 'pendiente_visacion'"><button class="btn btn-outline-warning" @click="decision('observe')">Observar</button><button class="btn btn-outline-danger" @click="decision('reject')">Rechazar</button><button class="btn btn-success" @click="decision('approve')">Visar</button></template>
      <template v-if="mode === 'management' && selected.approval_status === 'pendiente_administracion'"><button class="btn btn-outline-warning" @click="decision('observe')">Observar</button><button class="btn btn-outline-danger" @click="decision('reject')">Rechazar</button><button class="btn btn-success" :disabled="!selectedQuote" @click="decision('approve')">Aprobar</button></template>
      <button v-if="mode === 'management' && selected.approval_status === 'aprobado' && selected.service_status !== 'confirmado' && selected.service_status !== 'ejecutado'" class="btn btn-primary" :disabled="!operation.confirmation_reference" @click="confirmTransfer">Confirmar servicio</button><button v-if="mode === 'management' && selected.service_status === 'confirmado'" class="btn btn-success" @click="executeTransfer">Marcar ejecutado</button>
    </div></div></div><div class="modal-backdrop show"></div></div>
    </Teleport>
  </Layout>
</template>

<style scoped>
.operational-transfers { max-width: 1680px; }
.transfer-section-tabs { width: fit-content; max-width: 100%; }
.transfer-section-tabs .nav-link.router-link-exact-active { color: #3f4ab1; background: #fff; box-shadow: 0 3px 12px rgba(30, 41, 100, .1); }
.transfer-view-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; border: 1px solid #e3e8f1; border-radius: .9rem; background: #fff; box-shadow: 0 5px 18px rgba(30,41,80,.04); }
.transfer-view-toolbar > div:first-child strong, .transfer-view-toolbar > div:first-child span { display: block; }
.transfer-view-toolbar > div:first-child strong { color: #263451; font-size: .82rem; }
.transfer-view-toolbar > div:first-child span { margin-top: .12rem; color: #8290a4; font-size: .72rem; }
.transfer-view-switch { display: inline-flex; gap: .25rem; padding: .28rem; border: 1px solid #e0e5ee; border-radius: .72rem; background: #f5f7fb; }
.transfer-view-switch button { display: inline-flex; align-items: center; gap: .4rem; padding: .48rem .72rem; border: 0; border-radius: .55rem; color: #6b778b; background: transparent; font-size: .76rem; font-weight: 700; transition: all .18s ease; }
.transfer-view-switch button:hover { color: #4652bd; }
.transfer-view-switch button.active { color: #4652bd; background: #fff; box-shadow: 0 3px 10px rgba(43,54,120,.12); }
.transfer-actions { display: inline-flex; align-items: center; justify-content: flex-end; gap: .4rem; }
.transfer-action { display: inline-flex; align-items: center; justify-content: center; gap: .32rem; min-height: 32px; padding: .38rem .58rem; border: 1px solid transparent; border-radius: .6rem; font-size: .7rem; font-weight: 750; transition: transform .16s ease, box-shadow .16s ease, background .16s ease; }
.transfer-action:hover { transform: translateY(-1px); box-shadow: 0 5px 12px rgba(31,43,91,.12); }
.transfer-action i { font-size: .95rem; }
.transfer-action-view { border-color: #cad0ff; color: #4855c5; background: #f0f2ff; }
.transfer-action-view:hover { border-color: #5a66d1; color: #fff; background: #5a66d1; }
.transfer-action-edit { border-color: #bde9dc; color: #087a60; background: #eefbf7; }
.transfer-action-edit:hover { border-color: #0f9878; color: #fff; background: #0f9878; }
.transfer-action-pdf { border-color: #ffd1d8; color: #d53d59; background: #fff1f3; }
.transfer-action-pdf:hover { border-color: #d94560; color: #fff; background: #d94560; }
.calendar-legend { display: flex; flex-wrap: wrap; gap: .6rem; color: #66758a; font-size: .68rem; }
.calendar-legend span { display: inline-flex; align-items: center; gap: .3rem; }
.legend-dot { display: inline-block; width: .52rem; height: .52rem; border-radius: 50%; }
.legend-dot-indigo { background: #5965cf; }
.legend-dot-amber { background: #d88916; }
.legend-dot-green { background: #0f9f7a; }
.legend-dot-red { background: #dc3154; }
.operational-modal { position: fixed !important; inset: 0; width: 100vw; height: 100vh; padding: 0; z-index: 1060; overflow-x: hidden; overflow-y: auto; }
.operational-modal .modal-dialog { margin-top: 1rem; margin-bottom: 1rem; }
.operational-modal .modal-content { overflow: hidden; border: 0; border-radius: 1.15rem; box-shadow: 0 22px 70px rgba(15,23,42,.28); }
.modal-backdrop { z-index: -1; }
.detail-card { border: 1px solid #e6ebf1; border-radius: .9rem; padding: 1.1rem; height: 100%; background: linear-gradient(155deg, #fff, #fbfcff); box-shadow: 0 5px 18px rgba(30,41,80,.04); }
.detail-card h6 { color: #183b65; text-transform: uppercase; font-size: .78rem; letter-spacing: .06em; border-bottom: 2px solid #f0b429; padding-bottom: .6rem; margin-bottom: 1rem; }
.import-preview { max-height: 300px; }
.form-label { color: #475569; font-size: .78rem; font-weight: 650; }
@supports (height: 100dvh) { .operational-modal { height: 100dvh; } }
@media (max-width: 768px) { .operational-modal .modal-dialog { margin: .5rem; } .transfer-view-toolbar { align-items: stretch; flex-direction: column; } .transfer-view-switch { width: 100%; } .transfer-view-switch button { flex: 1; } }
</style>
