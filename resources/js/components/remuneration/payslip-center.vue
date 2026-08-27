<script>
import axios from "axios";
import Swal from "sweetalert2";
import { formatRemunerationError, money } from "./module-utils";

const API = "/api/remuneraciones/liquidaciones-sueldo";
const viewRoutes = {
  history: "/remuneraciones/liquidaciones-sueldo",
  matrix: "/remuneraciones/matriz-subvenciones",
  reconciliation: "/remuneraciones/conciliacion-liquidaciones",
  imports: "/remuneraciones/importaciones-liquidaciones",
};

export default {
  name: "PayslipCenter",
  props: {
    initialView: { type: String, default: "history" },
  },
  data() {
    return {
      activeView: this.initialView,
      loading: true,
      actionLoading: false,
      error: null,
      catalogs: { schools: [], periods: [], funding_sources: [], permissions: [], months: [] },
      filters: { school_id: "", year: new Date().getFullYear(), month: "", month_from: "", month_to: "", funding_source_id: "", search: "", concept: "" },
      dashboard: { metrics: {}, funding_sources: [], monthly: [], discount_destinations: [] },
      history: { data: [], current_page: 1, last_page: 1 },
      matrix: { data: [], current_page: 1, last_page: 1 },
      matrixType: "workers",
      reconciliation: null,
      batches: { data: [], current_page: 1, last_page: 1 },
      selectedBatch: null,
      selectedFiles: [],
      duplicateAction: "reject",
      wizardStep: 1,
      pollTimer: null,
      proposal: { school_id: "", period_id: "", notes: "" },
      proposals: { data: [] },
    };
  },
  computed: {
    viewItems() {
      return [
        { key: "history", label: "Liquidaciones de sueldo", icon: "bx-receipt" },
        { key: "matrix", label: "Matriz por subvención", icon: "bx-grid-alt" },
        { key: "reconciliation", label: "Conciliación", icon: "bx-git-compare" },
        { key: "imports", label: "Importaciones e incidencias", icon: "bx-cloud-upload" },
        { key: "proposal", label: "Propuesta de pago", icon: "bx-wallet" },
      ];
    },
    wizardItems() {
      return ["Carga", "Detección", "Vista previa", "Trabajadores", "Mapeos", "Validaciones", "Confirmación", "Procesamiento", "Resultado"];
    },
    fundingCodes() {
      const codes = new Set(this.catalogs.funding_sources.map((source) => source.code));
      (this.history.data || []).forEach((row) => Object.keys(row.funding_sources || {}).forEach((code) => codes.add(code)));
      return [...codes].filter(Boolean);
    },
    selectedPeriod() {
      return this.catalogs.periods.find((period) => String(period.id) === String(this.proposal.period_id));
    },
    canImport() { return this.hasPermission("remuneraciones.liquidaciones_pdf.importar"); },
    canExport() { return this.hasPermission("remuneraciones.liquidaciones_pdf.exportar"); },
    canResolve() { return this.hasPermission("remuneraciones.liquidaciones_pdf.incidencias"); },
    canReprocess() { return this.hasPermission("remuneraciones.liquidaciones_pdf.reprocesar"); },
    canPropose() { return this.hasPermission("remuneraciones.liquidaciones_pdf.propuesta_pago"); },
  },
  watch: {
    initialView(value) {
      this.activeView = value;
      this.loadCurrentView();
    },
    "filters.school_id"(value) {
      if (!this.proposal.school_id) this.proposal.school_id = value;
    },
  },
  async mounted() {
    await this.loadCatalogs();
    if (!this.filters.school_id && this.catalogs.schools.length === 1) {
      this.filters.school_id = this.catalogs.schools[0].id;
      this.proposal.school_id = this.catalogs.schools[0].id;
    }
    await this.loadCurrentView();
  },
  beforeUnmount() {
    this.stopPolling();
  },
  methods: {
    money,
    hasPermission(permission) {
      return this.catalogs.permissions.includes(permission) || this.catalogs.permissions.includes("remuneraciones.admin");
    },
    statusLabel(status) {
      const labels = {
        cargado: "Cargado", procesando: "Procesando", listo_revision: "Listo para revisión",
        en_cola_confirmacion: "En cola", confirmando: "Confirmando", confirmado: "Confirmado",
        fallido: "Fallido", anulado: "Anulado", importada: "Importada", reemplazada: "Reemplazada",
        pendiente: "Pendiente", correcto: "Correcto", diferencia: "Con diferencia", sin_libro: "Sin Libro",
        abierta: "Abierta", resuelta: "Resuelta", borrador: "Borrador", confirmada: "Confirmada",
      };
      return labels[status] || String(status || "-").replaceAll("_", " ");
    },
    statusClass(status) {
      if (["confirmado", "confirmada", "importada", "correcto", "resuelta"].includes(status)) return "success";
      if (["fallido", "anulado", "error", "diferencia", "abierta"].includes(status)) return "danger";
      if (["procesando", "confirmando", "en_cola_confirmacion"].includes(status)) return "info";
      return "warning";
    },
    changeView(view) {
      this.activeView = view;
      if (viewRoutes[view] && this.$route.path !== viewRoutes[view]) this.$router.push(viewRoutes[view]);
      this.loadCurrentView();
    },
    async loadCatalogs() {
      try {
        const { data } = await axios.get(`${API}/catalogs`);
        this.catalogs = data;
      } catch (error) {
        this.error = formatRemunerationError(error);
      }
    },
    params(extra = {}) {
      return Object.fromEntries(Object.entries({ ...this.filters, ...extra }).filter(([, value]) => value !== "" && value !== null && value !== undefined));
    },
    async loadCurrentView(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        if (this.activeView === "history") await Promise.all([this.loadDashboard(), this.loadHistory(page)]);
        if (this.activeView === "matrix") await this.loadMatrix(page);
        if (this.activeView === "reconciliation") await this.loadReconciliation();
        if (this.activeView === "imports") await this.loadBatches(page);
        if (this.activeView === "proposal") await this.loadProposals();
      } catch (error) {
        this.error = formatRemunerationError(error);
      } finally {
        this.loading = false;
      }
    },
    async loadDashboard() {
      const { data } = await axios.get(`${API}/dashboard`, { params: this.params() });
      this.dashboard = data;
    },
    async loadHistory(page = 1) {
      const { data } = await axios.get(`${API}/history`, { params: this.params({ page, per_page: 25 }) });
      this.history = data;
    },
    async loadMatrix(page = 1) {
      const { data } = await axios.get(`${API}/matrices/${this.matrixType}`, { params: this.params({ page, per_page: 30 }) });
      this.matrix = data;
    },
    async loadReconciliation() {
      if (!this.filters.school_id || !this.filters.year || !this.filters.month) {
        this.reconciliation = null;
        return;
      }
      const { data } = await axios.get(`${API}/reconciliation`, { params: { school_id: this.filters.school_id, year: this.filters.year, month: this.filters.month } });
      this.reconciliation = data;
    },
    async loadBatches(page = 1) {
      const { data } = await axios.get(`${API}/batches`, { params: { school_id: this.filters.school_id || undefined, page, per_page: 20 } });
      this.batches = data;
    },
    async loadProposals() {
      const { data } = await axios.get(`${API}/payment-proposals`);
      this.proposals = data;
    },
    selectFiles(event) {
      this.selectedFiles = [...(event.target.files || [])];
    },
    async stageFiles() {
      if (!this.filters.school_id || !this.selectedFiles.length) {
        Swal.fire("Faltan datos", "Seleccione establecimiento y al menos un PDF.", "warning");
        return;
      }
      this.actionLoading = true;
      try {
        const form = new FormData();
        form.append("school_id", this.filters.school_id);
        form.append("duplicate_action", this.duplicateAction);
        this.selectedFiles.forEach((file) => form.append("files[]", file));
        const { data } = await axios.post(`${API}/batches`, form, { headers: { "Content-Type": "multipart/form-data" } });
        this.selectedBatch = { data: data.data, payslips: { data: [] }, progress: {} };
        this.wizardStep = 2;
        this.startPolling(data.data.public_id);
        await this.loadBatches();
      } catch (error) {
        Swal.fire("No se pudo cargar", formatRemunerationError(error), "error");
      } finally {
        this.actionLoading = false;
      }
    },
    async openBatch(batch) {
      this.activeView = "imports";
      this.wizardStep = 2;
      await this.refreshBatch(batch.public_id);
      if (["procesando", "cargado", "en_cola_confirmacion", "confirmando"].includes(this.selectedBatch?.data?.status)) this.startPolling(batch.public_id);
    },
    async refreshBatch(publicId) {
      const { data } = await axios.get(`${API}/batches/${publicId}`, { params: { per_page: 200 } });
      this.selectedBatch = data;
      const status = data.data.status;
      if (status === "listo_revision") this.wizardStep = Math.max(this.wizardStep, 3);
      if (["en_cola_confirmacion", "confirmando"].includes(status)) this.wizardStep = 8;
      if (["confirmado", "fallido", "anulado"].includes(status)) {
        this.wizardStep = 9;
        this.stopPolling();
      }
    },
    startPolling(publicId) {
      this.stopPolling();
      this.refreshBatch(publicId);
      this.pollTimer = window.setInterval(() => this.refreshBatch(publicId).catch(() => this.stopPolling()), 2500);
    },
    stopPolling() {
      if (this.pollTimer) window.clearInterval(this.pollTimer);
      this.pollTimer = null;
    },
    async confirmBatch() {
      if (!this.selectedBatch || this.selectedBatch.data.error_count > 0) return;
      this.actionLoading = true;
      try {
        const corrections = (this.selectedBatch.payslips.data || []).map((row) => ({ payslip_id: row.id, year: row.year, month: row.month, staff_id: row.staff_id }));
        await axios.post(`${API}/batches/${this.selectedBatch.data.public_id}/confirm`, { duplicate_action: this.duplicateAction, corrections });
        this.wizardStep = 8;
        this.startPolling(this.selectedBatch.data.public_id);
      } catch (error) {
        Swal.fire("No se pudo confirmar", formatRemunerationError(error), "error");
      } finally {
        this.actionLoading = false;
      }
    },
    async resolveIssue(issue) {
      if (issue.code === "unmatched_staff") {
        Swal.fire("RUT sin coincidencia", "Cree o corrija primero la ficha del trabajador. El sistema no permite asociaciones difusas.", "info");
        return;
      }
      const payload = {};
      if (issue.code === "unknown_funding_source") {
        const options = Object.fromEntries(this.catalogs.funding_sources.map((source) => [source.id, `${source.code} · ${source.name}`]));
        const result = await Swal.fire({ title: "Mapear subvención", input: "select", inputOptions: options, inputPlaceholder: "Seleccione fuente", showCancelButton: true, confirmButtonText: "Guardar mapeo", inputValidator: (value) => (!value ? "Seleccione una fuente" : undefined) });
        if (!result.isConfirmed) return;
        payload.funding_source_id = result.value;
      }
      if (issue.code === "missing_period") {
        const year = await Swal.fire({ title: "Año", input: "number", inputValue: this.filters.year, showCancelButton: true, inputAttributes: { min: 2020, max: 2100 } });
        if (!year.isConfirmed) return;
        const month = await Swal.fire({ title: "Mes", input: "number", inputValue: this.filters.month || 1, showCancelButton: true, inputAttributes: { min: 1, max: 12 } });
        if (!month.isConfirmed) return;
        payload.year = Number(year.value); payload.month = Number(month.value);
      }
      const reason = await Swal.fire({ title: "Resolución trazable", input: "textarea", inputPlaceholder: "Explique la decisión...", showCancelButton: true, confirmButtonText: "Resolver", inputValidator: (value) => (!value || value.length < 5 ? "Ingrese una justificación" : undefined) });
      if (!reason.isConfirmed) return;
      payload.resolution = reason.value;
      try {
        await axios.post(`${API}/issues/${issue.id}/resolve`, payload);
        await this.refreshBatch(this.selectedBatch.data.public_id);
      } catch (error) {
        Swal.fire("No se pudo resolver", formatRemunerationError(error), "error");
      }
    },
    async reprocessFile(fileId) {
      if (!fileId) return;
      const result = await Swal.fire({ title: "Crear nueva versión", text: "La versión anterior conservará toda su trazabilidad.", icon: "question", showCancelButton: true, confirmButtonText: "Reprocesar" });
      if (!result.isConfirmed) return;
      const { data } = await axios.post(`${API}/files/${fileId}/reprocess`);
      await this.openBatch(data.data);
    },
    async openPrivateFile(file) {
      const { data } = await axios.get(`${API}/files/${file.id}/link`);
      window.open(data.url, "_blank", "noopener,noreferrer");
    },
    exportUrl(kind) {
      const query = new URLSearchParams(this.params()).toString();
      return kind === "excel" ? `${API}/export/excel?${query}` : `${API}/export/csv/${kind}?${query}`;
    },
    async generateProposal() {
      if (!this.proposal.school_id || !this.proposal.period_id) return;
      this.actionLoading = true;
      try {
        await axios.post(`${API}/payment-proposals`, this.proposal);
        await this.loadProposals();
        Swal.fire("Propuesta creada", "Quedó en borrador y no generó movimientos presupuestarios.", "success");
      } catch (error) {
        Swal.fire("No se pudo generar", formatRemunerationError(error), "error");
      } finally { this.actionLoading = false; }
    },
    async confirmProposal(proposal) {
      const result = await Swal.fire({ title: "Confirmar propuesta", text: "Esta acción no contabiliza ni registra pagos; solo cierra la revisión.", icon: "question", showCancelButton: true, confirmButtonText: "Confirmar" });
      if (!result.isConfirmed) return;
      await axios.post(`${API}/payment-proposals/${proposal.public_id}/confirm`);
      await this.loadProposals();
    },
  },
};
</script>

<template>
  <section class="payslip-center">
    <BAlert v-if="error" show variant="danger" dismissible @dismissed="error = null">{{ error }}</BAlert>

    <header class="payslip-hero">
      <div class="payslip-hero__copy">
        <span class="payslip-kicker"><i class="bx bx-shield-quarter"></i> Remuneraciones · detalle privado</span>
        <h2>Liquidaciones con distribución contable exacta</h2>
        <p>El pago bancario conserva el sueldo líquido; cada peso queda explicado por subvención, descuento y página de origen.</p>
      </div>
      <div class="payslip-hero__signal">
        <span><i class="bx bx-lock-alt"></i> PDF privado</span>
        <strong>{{ dashboard.metrics?.payslips || 0 }}</strong>
        <small>liquidaciones vigentes</small>
      </div>
    </header>

    <nav class="payslip-nav" aria-label="Liquidaciones de sueldo">
      <button v-for="view in viewItems" :key="view.key" type="button" :class="{ active: activeView === view.key }" @click="changeView(view.key)">
        <i class="bx" :class="view.icon"></i><span>{{ view.label }}</span>
      </button>
    </nav>

    <BCard v-if="activeView !== 'imports' && activeView !== 'proposal'" class="payslip-filter-card border-0 shadow-sm">
      <div class="payslip-filters">
        <div><label>Establecimiento</label><select v-model="filters.school_id" class="form-select"><option value="">Todos</option><option v-for="school in catalogs.schools" :key="school.id" :value="school.id">{{ school.name }} · RBD {{ school.rbd }}</option></select></div>
        <div><label>Año</label><input v-model.number="filters.year" type="number" min="2020" max="2100" class="form-control" /></div>
        <div><label>Mes</label><select v-model="filters.month" class="form-select"><option value="">Todos</option><option v-for="month in catalogs.months" :key="month.value" :value="month.value">{{ month.label }}</option></select></div>
        <div><label>Subvención</label><select v-model="filters.funding_source_id" class="form-select"><option value="">Todas</option><option v-for="source in catalogs.funding_sources" :key="source.id" :value="source.id">{{ source.code }}</option></select></div>
        <div class="payslip-filter-search"><label>Trabajador o concepto</label><input v-model="filters.search" class="form-control" placeholder="Buscar por nombre..." @keyup.enter="loadCurrentView()" /></div>
        <BButton variant="primary" class="align-self-end" @click="loadCurrentView()"><i class="bx bx-filter-alt me-1"></i> Aplicar</BButton>
      </div>
    </BCard>

    <div v-if="loading" class="payslip-loading"><span class="spinner-border spinner-border-sm"></span> Preparando información...</div>

    <template v-else-if="activeView === 'history'">
      <div class="payslip-metrics">
        <article><span class="metric-icon metric-icon--blue"><i class="bx bx-wallet"></i></span><div><small>Líquido pagable</small><strong>{{ money(dashboard.metrics?.net_total) }}</strong><em>Pago bancario</em></div></article>
        <article><span class="metric-icon metric-icon--violet"><i class="bx bx-layer"></i></span><div><small>Haberes</small><strong>{{ money(dashboard.metrics?.gross_total) }}</strong><em>Base contable</em></div></article>
        <article><span class="metric-icon metric-icon--amber"><i class="bx bx-minus-circle"></i></span><div><small>Descuentos</small><strong>{{ money(dashboard.metrics?.deduction_total) }}</strong><em>Distribuidos línea a línea</em></div></article>
        <article><span class="metric-icon metric-icon--green"><i class="bx bx-building-house"></i></span><div><small>Costo total</small><strong>{{ money(dashboard.metrics?.total_cost) }}</strong><em>Incluye aportes</em></div></article>
      </div>

      <div class="row g-3">
        <div class="col-xl-7">
          <BCard class="payslip-card border-0 shadow-sm h-100">
            <div class="payslip-card__header"><div><span>Distribución dinámica</span><h5>Líquido por subvención</h5></div><a v-if="canExport" :href="exportUrl('excel')" class="btn btn-sm btn-outline-primary"><i class="bx bx-spreadsheet me-1"></i> Excel completo</a></div>
            <div v-if="dashboard.funding_sources?.length" class="funding-list">
              <div v-for="source in dashboard.funding_sources" :key="source.id" class="funding-row">
                <div class="funding-row__identity"><b>{{ source.code }}</b><span>{{ source.name }}</span></div>
                <div class="funding-row__bar"><span :style="{ width: `${Math.max(3, Number(source.net_amount || 0) * 100 / Math.max(1, Number(dashboard.metrics?.net_total || 1)))}%` }"></span></div>
                <strong>{{ money(source.net_amount) }}</strong>
              </div>
            </div>
            <div v-else class="payslip-empty"><i class="bx bx-layer"></i><span>Aún no hay distribución para los filtros seleccionados.</span></div>
          </BCard>
        </div>
        <div class="col-xl-5">
          <BCard class="payslip-card border-0 shadow-sm h-100">
            <div class="payslip-card__header"><div><span>Obligaciones</span><h5>Descuentos por destino</h5></div></div>
            <div class="destination-list"><div v-for="item in dashboard.discount_destinations" :key="item.destination"><span>{{ item.destination }}</span><strong>{{ money(item.amount) }}</strong></div></div>
          </BCard>
        </div>
      </div>

      <BCard class="payslip-card border-0 shadow-sm">
        <div class="payslip-card__header"><div><span>Histórico verificable</span><h5>Liquidaciones individuales</h5></div><div class="d-flex gap-2"><a v-if="canExport" :href="exportUrl('earnings')" class="btn btn-sm btn-outline-secondary">CSV haberes</a><a v-if="canExport" :href="exportUrl('discounts')" class="btn btn-sm btn-outline-secondary">CSV descuentos</a></div></div>
        <div class="table-responsive payslip-table-wrap"><table class="table align-middle payslip-table"><thead><tr><th>Período</th><th>Trabajador</th><th>RUT</th><th>Haberes</th><th>Descuentos</th><th>Líquido</th><th v-for="code in fundingCodes" :key="code">{{ code }} líquido</th><th>Controles</th></tr></thead><tbody><tr v-for="row in history.data" :key="row.id"><td><span class="period-chip">{{ row.period || `${row.month}/${row.year}` }}</span></td><td><b>{{ row.worker }}</b><small>Versión {{ row.version }}</small></td><td>{{ row.rut }}</td><td>{{ money(row.gross_total) }}</td><td>{{ money(row.deductions) }}</td><td class="amount-strong">{{ money(row.net_amount) }}</td><td v-for="code in fundingCodes" :key="`${row.id}-${code}`">{{ money(row.funding_sources?.[code]?.net || 0) }}</td><td><span class="status-pill" :class="`status-pill--${row.controls?.error ? 'danger' : 'success'}`"><i class="bx" :class="row.controls?.error ? 'bx-error' : 'bx-check'"></i>{{ row.controls?.error || 0 }} errores</span></td></tr><tr v-if="!history.data?.length"><td :colspan="7 + fundingCodes.length"><div class="payslip-empty"><i class="bx bx-receipt"></i><span>Sin liquidaciones para estos filtros.</span></div></td></tr></tbody></table></div>
        <div class="payslip-pager"><BButton size="sm" variant="outline-secondary" :disabled="history.current_page <= 1" @click="loadHistory(history.current_page - 1)">Anterior</BButton><span>Página {{ history.current_page }} de {{ history.last_page }}</span><BButton size="sm" variant="outline-secondary" :disabled="history.current_page >= history.last_page" @click="loadHistory(history.current_page + 1)">Siguiente</BButton></div>
      </BCard>
    </template>

    <template v-else-if="activeView === 'matrix'">
      <div class="matrix-tabs"><button v-for="item in [{k:'workers',l:'Funcionarios'},{k:'earnings',l:'Haberes'},{k:'discounts',l:'Descuentos'},{k:'contributions',l:'Aportes empleador'},{k:'controls',l:'Controles'}]" :key="item.k" :class="{active:matrixType===item.k}" @click="matrixType=item.k;loadMatrix()">{{ item.l }}</button></div>
      <BCard class="payslip-card border-0 shadow-sm">
        <div class="payslip-card__header"><div><span>Matriz operacional</span><h5>{{ matrixType === 'workers' ? 'Trabajador y subvención' : matrixType }}</h5></div><a v-if="canExport" :href="exportUrl('excel')" class="btn btn-sm btn-primary"><i class="bx bx-download me-1"></i> Exportar con metodología</a></div>
        <div class="table-responsive payslip-table-wrap payslip-table-wrap--tall"><table class="table align-middle payslip-table"><thead><tr v-if="matrixType==='workers'"><th>Período</th><th>Trabajador</th><th>Total haberes</th><th>Total líquido</th><template v-for="code in fundingCodes" :key="code"><th>{{ code }} haberes</th><th>{{ code }} líquido</th><th>{{ code }} aportes</th><th>{{ code }} costo</th></template></tr><tr v-else><th>Período</th><th>Trabajador</th><th>Código</th><th>Descripción / control</th><th>Clasificación</th><th>Subvención</th><th>Monto</th></tr></thead><tbody><template v-if="matrixType==='workers'"><tr v-for="row in matrix.data" :key="row.id"><td>{{ row.period }}</td><td><b>{{ row.worker }}</b><small>{{ row.rut }}</small></td><td>{{ money(row.gross_total) }}</td><td class="amount-strong">{{ money(row.net_amount) }}</td><template v-for="code in fundingCodes" :key="`${row.id}-${code}`"><td>{{ money(row.funding_sources?.[code]?.gross || 0) }}</td><td>{{ money(row.funding_sources?.[code]?.net || 0) }}</td><td>{{ money(row.funding_sources?.[code]?.employer || 0) }}</td><td>{{ money(row.funding_sources?.[code]?.total_cost || 0) }}</td></template></tr></template><template v-else><tr v-for="(row,index) in matrix.data" :key="`${matrixType}-${index}-${row.code}`"><td>{{ row.period }}</td><td><b>{{ row.worker || row.payslip?.staff?.full_name }}</b><small>{{ row.rut }}</small></td><td>{{ row.code || '-' }}</td><td>{{ row.description || row.label }}</td><td>{{ row.classification || row.status || (row.is_imponible ? 'Imponible' : 'No imponible') }}</td><td>{{ row.funding_source || '-' }}</td><td>{{ money(row.amount ?? row.difference) }}</td></tr></template><tr v-if="!matrix.data?.length"><td :colspan="matrixType==='workers' ? 4 + fundingCodes.length*4 : 7"><div class="payslip-empty"><i class="bx bx-grid-alt"></i><span>Sin filas para esta matriz.</span></div></td></tr></tbody></table></div>
        <div class="payslip-pager"><BButton size="sm" variant="outline-secondary" :disabled="matrix.current_page <= 1" @click="loadMatrix(matrix.current_page - 1)">Anterior</BButton><span>Página {{ matrix.current_page }} de {{ matrix.last_page }}</span><BButton size="sm" variant="outline-secondary" :disabled="matrix.current_page >= matrix.last_page" @click="loadMatrix(matrix.current_page + 1)">Siguiente</BButton></div>
      </BCard>
    </template>

    <template v-else-if="activeView === 'reconciliation'">
      <div v-if="!filters.school_id || !filters.month" class="alert alert-info mb-0" role="status">
        <i class="bx bx-info-circle me-1"></i> Seleccione establecimiento, año y mes para comparar ambas fuentes.
      </div>
      <template v-else-if="reconciliation">
        <div class="reconciliation-banner" :class="`reconciliation-banner--${reconciliation.status}`"><div><i class="bx" :class="reconciliation.status==='correcto'?'bx-check-shield':'bx-git-compare'"></i><span>Estado de conciliación</span><strong>{{ statusLabel(reconciliation.status) }}</strong></div><p>El Libro permanece como fuente consolidada; las liquidaciones aportan el detalle por trabajador y subvención.</p></div>
        <div class="payslip-metrics payslip-metrics--three"><article><div><small>Presentes en ambas</small><strong>{{ reconciliation.summary.both }}</strong></div></article><article><div><small>Solo en Libro</small><strong>{{ reconciliation.summary.only_book }}</strong></div></article><article><div><small>Solo en liquidaciones</small><strong>{{ reconciliation.summary.only_payslips }}</strong></div></article></div>
        <BCard class="payslip-card border-0 shadow-sm"><div class="payslip-card__header"><div><span>Comparación nominal</span><h5>Libro vs. Liquidaciones</h5></div></div><div class="table-responsive payslip-table-wrap"><table class="table align-middle payslip-table"><thead><tr><th>Trabajador</th><th>Presencia</th><th>Haberes Libro</th><th>Haberes PDF</th><th>Diferencia</th><th>Líquido Libro</th><th>Líquido PDF</th><th>Estado</th></tr></thead><tbody><tr v-for="row in reconciliation.rows" :key="row.staff_id"><td><b>{{ row.worker }}</b><small>{{ row.rut }}</small></td><td>{{ statusLabel(row.presence) }}</td><td>{{ money(row.values.gross_total.book) }}</td><td>{{ money(row.values.gross_total.payslip) }}</td><td :class="{'text-danger fw-bold':row.values.gross_total.difference}">{{ money(row.values.gross_total.difference) }}</td><td>{{ money(row.values.net_amount.book) }}</td><td>{{ money(row.values.net_amount.payslip) }}</td><td><span class="status-pill" :class="`status-pill--${row.status==='correcto'?'success':'danger'}`">{{ statusLabel(row.status) }}</span></td></tr></tbody></table></div></BCard>
      </template>
    </template>

    <template v-else-if="activeView === 'imports'">
      <div class="wizard-stepper"><div v-for="(step,index) in wizardItems" :key="step" :class="{active:wizardStep===index+1,done:wizardStep>index+1}"><span>{{ wizardStep>index+1?'✓':index+1 }}</span><small>{{ step }}</small></div></div>
      <BCard v-if="!selectedBatch" class="upload-card border-0 shadow-sm"><div class="upload-card__intro"><span><i class="bx bx-cloud-upload"></i></span><div><small>Etapa 1</small><h4>Cargar PDF de liquidaciones</h4><p>Uno o varios archivos, incluso con meses distintos. El original quedará en almacenamiento privado.</p></div></div><div class="upload-grid"><div><label>Establecimiento</label><select v-model="filters.school_id" class="form-select"><option value="">Seleccione</option><option v-for="school in catalogs.schools" :key="school.id" :value="school.id">{{ school.name }} · {{ school.rbd }}</option></select></div><div><label>Ante duplicados</label><select v-model="duplicateAction" class="form-select"><option value="reject">Detener y advertir</option><option value="replace">Reemplazar conservando versión</option><option value="new_version">Crear nueva versión</option></select></div><label class="drop-zone"><input type="file" accept="application/pdf" multiple @change="selectFiles" /><i class="bx bxs-file-pdf"></i><b>{{ selectedFiles.length ? `${selectedFiles.length} PDF seleccionados` : 'Arrastre o seleccione PDF' }}</b><span>Máximo {{ 50 }} MB por archivo</span></label></div><div class="d-flex justify-content-end"><BButton variant="primary" size="lg" :disabled="!canImport || actionLoading" @click="stageFiles"><span v-if="actionLoading" class="spinner-border spinner-border-sm me-1"></span> Analizar en cola</BButton></div></BCard>

      <template v-else>
        <BCard class="batch-status-card border-0 shadow-sm"><div><span class="batch-icon" :class="`batch-icon--${statusClass(selectedBatch.data.status)}`"><i class="bx bx-loader-circle"></i></span><div><small>Lote {{ selectedBatch.data.public_id }}</small><h4>{{ statusLabel(selectedBatch.data.status) }}</h4><p>{{ selectedBatch.data.payslip_count }} liquidaciones · {{ selectedBatch.data.error_count }} errores · {{ selectedBatch.data.warning_count }} advertencias</p></div></div><div class="batch-progress"><div><span :style="{width:`${selectedBatch.progress.percent || 0}%`}"></span></div><b>{{ selectedBatch.progress.percent || 0 }}%</b></div><BButton variant="outline-secondary" @click="selectedBatch=null;stopPolling();wizardStep=1">Otro lote</BButton></BCard>
        <div class="row g-3">
          <div class="col-xl-8"><BCard class="payslip-card border-0 shadow-sm"><div class="payslip-card__header"><div><span>Vista previa</span><h5>Páginas y controles</h5></div><div class="d-flex gap-2"><BButton v-for="file in selectedBatch.data.files" :key="file.id" size="sm" variant="outline-primary" @click="openPrivateFile(file)"><i class="bx bx-lock-open-alt me-1"></i> PDF</BButton></div></div><div class="table-responsive payslip-table-wrap"><table class="table align-middle payslip-table"><thead><tr><th>Página</th><th>Período</th><th>Trabajador</th><th>Match exacto</th><th>Haberes</th><th>Líquido</th><th>Subvenciones</th><th>Controles</th></tr></thead><tbody><tr v-for="row in selectedBatch.payslips.data" :key="row.id"><td>{{ row.page }}</td><td><div class="period-edit"><input v-model.number="row.month" type="number" min="1" max="12" /><span>/</span><input v-model.number="row.year" type="number" min="2020" max="2100" /></div></td><td><b>{{ row.worker }}</b><small>{{ row.rut }}</small></td><td><span class="status-pill" :class="`status-pill--${row.matched?'success':'danger'}`">{{ row.matched?'Exacto':'Sin coincidencia' }}</span></td><td>{{ money(row.gross_total) }}</td><td class="amount-strong">{{ money(row.net_amount) }}</td><td><span v-for="source in row.funding_sources" :key="source.code" class="source-chip">{{ source.code }}</span></td><td><span class="status-pill" :class="`status-pill--${row.controls.some(c=>c.status==='error')?'danger':'success'}`">{{ row.controls.filter(c=>c.status==='error').length }} errores</span></td></tr></tbody></table></div></BCard></div>
          <div class="col-xl-4"><BCard class="payslip-card border-0 shadow-sm"><div class="payslip-card__header"><div><span>Resolución previa</span><h5>Incidencias</h5></div><span class="issue-count">{{ selectedBatch.data.issues?.filter(i=>i.status==='abierta').length || 0 }}</span></div><div class="issue-list"><article v-for="issue in selectedBatch.data.issues?.filter(i=>i.status==='abierta')" :key="issue.id" :class="`issue--${issue.severity}`"><i class="bx bx-error-circle"></i><div><b>{{ issue.message }}</b><small>{{ issue.code }}</small><button v-if="canResolve && issue.code!=='reprocess_required'" type="button" @click="resolveIssue(issue)">Resolver</button><button v-if="canReprocess && issue.code==='reprocess_required'" type="button" @click="reprocessFile(issue.file_id)">Reprocesar</button></div></article><div v-if="!selectedBatch.data.issues?.filter(i=>i.status==='abierta').length" class="payslip-empty"><i class="bx bx-check-shield"></i><span>Sin incidencias abiertas.</span></div></div></BCard><BCard class="confirmation-card border-0 shadow-sm"><i class="bx bx-check-double"></i><h5>Confirmación</h5><p>La importación solo se confirma cuando todos los controles están correctos.</p><BButton variant="success" class="w-100" :disabled="selectedBatch.data.status!=='listo_revision' || selectedBatch.data.error_count>0 || actionLoading" @click="confirmBatch">Confirmar e importar</BButton></BCard></div>
        </div>
      </template>

      <BCard v-if="!selectedBatch" class="payslip-card border-0 shadow-sm"><div class="payslip-card__header"><div><span>Trazabilidad</span><h5>Historial de lotes</h5></div></div><div class="table-responsive"><table class="table align-middle payslip-table"><thead><tr><th>Fecha</th><th>Establecimiento</th><th>Archivos</th><th>Páginas</th><th>Liquidaciones</th><th>Líquido</th><th>Estado</th><th></th></tr></thead><tbody><tr v-for="batch in batches.data" :key="batch.public_id"><td>{{ batch.created_at }}</td><td>{{ batch.school?.name }}</td><td>{{ batch.file_count }}</td><td>{{ batch.page_count }}</td><td>{{ batch.payslip_count }}</td><td>{{ money(batch.net_total) }}</td><td><span class="status-pill" :class="`status-pill--${statusClass(batch.status)}`">{{ statusLabel(batch.status) }}</span></td><td><BButton size="sm" variant="outline-primary" @click="openBatch(batch)">Revisar</BButton></td></tr></tbody></table></div></BCard>
    </template>

    <template v-else-if="activeView === 'proposal'">
      <div class="proposal-hero"><div><span>Separar pago de contabilización</span><h3>Propuesta de pago de remuneraciones</h3><p>Cada trabajador conserva un pago único por su líquido; las líneas por subvención explican su distribución presupuestaria.</p></div><i class="bx bx-wallet"></i></div>
      <div class="row g-3"><div class="col-lg-5"><BCard class="payslip-card border-0 shadow-sm"><div class="payslip-card__header"><div><span>Nueva propuesta</span><h5>Seleccionar período</h5></div></div><div class="d-flex flex-column gap-3"><div><label class="form-label">Establecimiento</label><select v-model="proposal.school_id" class="form-select"><option value="">Seleccione</option><option v-for="school in catalogs.schools" :key="school.id" :value="school.id">{{ school.name }}</option></select></div><div><label class="form-label">Período</label><select v-model="proposal.period_id" class="form-select"><option value="">Seleccione</option><option v-for="period in catalogs.periods" :key="period.id" :value="period.id">{{ period.name }}</option></select></div><div><label class="form-label">Notas de revisión</label><textarea v-model="proposal.notes" class="form-control" rows="3"></textarea></div><BAlert show variant="info" class="mb-0"><i class="bx bx-info-circle me-1"></i> No se generará ningún movimiento presupuestario.</BAlert><BButton variant="primary" size="lg" :disabled="!canPropose || actionLoading" @click="generateProposal">Generar borrador</BButton></div></BCard></div><div class="col-lg-7"><BCard class="payslip-card border-0 shadow-sm"><div class="payslip-card__header"><div><span>Propuestas revisables</span><h5>Historial</h5></div></div><div class="proposal-list"><article v-for="item in proposals.data" :key="item.public_id"><div><span>{{ item.period?.name }} · {{ item.school?.name }}</span><strong>{{ money(item.net_total) }}</strong><small>{{ item.items_count || '' }} Pago líquido distribuido: {{ money(item.distributed_total) }}</small></div><span class="status-pill" :class="`status-pill--${statusClass(item.status)}`">{{ statusLabel(item.status) }}</span><BButton v-if="item.status==='borrador' && canPropose" size="sm" variant="success" @click="confirmProposal(item)">Confirmar revisión</BButton></article><div v-if="!proposals.data?.length" class="payslip-empty"><i class="bx bx-wallet"></i><span>Aún no hay propuestas.</span></div></div></BCard></div></div>
    </template>
  </section>
</template>

<style scoped>
.payslip-center { --ink:#172033; --muted:#6b7487; --line:#e7eaf0; --brand:#4059d8; display:flex; flex-direction:column; gap:1rem; }
.payslip-hero { align-items:center; background:linear-gradient(125deg,#17233c 0%,#263d70 55%,#4059d8 100%); border-radius:20px; box-shadow:0 18px 42px rgba(29,46,91,.2); color:#fff; display:flex; justify-content:space-between; min-height:180px; overflow:hidden; padding:2rem 2.2rem; position:relative; }
.payslip-hero::after { border:1px solid rgba(255,255,255,.13); border-radius:50%; content:""; height:260px; position:absolute; right:-70px; top:-125px; width:260px; }
.payslip-hero__copy { max-width:760px; position:relative; z-index:1; }.payslip-kicker { align-items:center; color:#b9c8ff; display:flex; font-size:.7rem; font-weight:800; gap:.4rem; letter-spacing:.1em; text-transform:uppercase; }.payslip-hero h2{color:#fff;font-size:1.7rem;margin:.55rem 0}.payslip-hero p{color:#dfe6ff;margin:0;max-width:680px}.payslip-hero__signal{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:16px;display:flex;flex-direction:column;min-width:190px;padding:1rem 1.2rem;position:relative;z-index:1}.payslip-hero__signal span{color:#cbd6ff;font-size:.72rem}.payslip-hero__signal strong{font-size:2rem}.payslip-hero__signal small{color:#dfe6ff}
.payslip-nav{background:#fff;border:1px solid var(--line);border-radius:14px;display:flex;gap:.35rem;overflow-x:auto;padding:.42rem}.payslip-nav button{align-items:center;background:transparent;border:0;border-radius:10px;color:#606a7d;display:flex;font-size:.78rem;font-weight:700;gap:.45rem;padding:.65rem .85rem;white-space:nowrap}.payslip-nav button i{font-size:1.1rem}.payslip-nav button.active{background:#eef1ff;color:var(--brand)}
.payslip-filter-card,.payslip-card{border-radius:16px}.payslip-filters{display:grid;gap:.75rem;grid-template-columns:minmax(190px,1.4fr) 100px 130px 130px minmax(180px,1fr) auto}.payslip-filters label,.upload-grid label{color:#687186;display:block;font-size:.67rem;font-weight:800;letter-spacing:.05em;margin-bottom:.3rem;text-transform:uppercase}.payslip-filters .form-control,.payslip-filters .form-select,.upload-grid .form-select{border-color:#dde2eb;border-radius:9px;font-size:.82rem;min-height:39px}.payslip-loading{align-items:center;color:var(--muted);display:flex;gap:.55rem;justify-content:center;min-height:260px}
.payslip-metrics{display:grid;gap:1rem;grid-template-columns:repeat(4,1fr)}.payslip-metrics article{align-items:center;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 7px 22px rgba(25,34,53,.055);display:flex;gap:.9rem;padding:1rem}.payslip-metrics small{color:var(--muted);display:block;font-size:.72rem}.payslip-metrics strong{color:var(--ink);display:block;font-size:1.12rem;white-space:nowrap}.payslip-metrics em{color:#98a0af;font-size:.66rem;font-style:normal}.metric-icon{align-items:center;border-radius:12px;display:flex;flex:0 0 44px;font-size:1.25rem;height:44px;justify-content:center}.metric-icon--blue{background:#eef1ff;color:#4059d8}.metric-icon--violet{background:#f3edff;color:#7a4bc9}.metric-icon--amber{background:#fff4df;color:#c88616}.metric-icon--green{background:#e9f9f2;color:#1f9d70}
.payslip-card__header{align-items:center;display:flex;gap:1rem;justify-content:space-between;margin-bottom:1rem}.payslip-card__header span{color:#8a92a2;font-size:.65rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.payslip-card__header h5{color:var(--ink);font-size:1rem;margin:.18rem 0 0}.funding-list{display:flex;flex-direction:column;gap:1rem}.funding-row{align-items:center;display:grid;gap:.8rem;grid-template-columns:190px 1fr 115px}.funding-row__identity b{background:#eef1ff;border-radius:7px;color:var(--brand);display:inline-block;font-size:.68rem;margin-right:.5rem;padding:.25rem .45rem}.funding-row__identity span{color:#5e6677;font-size:.76rem}.funding-row__bar{background:#edf0f5;border-radius:999px;height:9px;overflow:hidden}.funding-row__bar span{background:linear-gradient(90deg,#4059d8,#7692ff);border-radius:inherit;display:block;height:100%}.funding-row>strong{color:var(--ink);font-size:.8rem;text-align:right}.destination-list{display:flex;flex-direction:column}.destination-list>div{align-items:center;border-bottom:1px solid #eef1f5;display:flex;justify-content:space-between;padding:.8rem 0}.destination-list span{color:#626b7c;font-size:.8rem}.destination-list strong{color:var(--ink)}
.payslip-table-wrap{max-height:520px;overflow:auto}.payslip-table-wrap--tall{max-height:640px}.payslip-table{font-size:.76rem;margin:0}.payslip-table thead th{background:#f7f8fb;color:#687186;font-size:.64rem;font-weight:800;letter-spacing:.035em;position:sticky;text-transform:uppercase;top:0;white-space:nowrap;z-index:2}.payslip-table td{border-color:#edf0f5;white-space:nowrap}.payslip-table td b{color:#263043;display:block}.payslip-table td small{color:#8a92a2;display:block;font-size:.65rem}.amount-strong{color:#16845f!important;font-weight:800}.period-chip,.source-chip{background:#f1f3f8;border-radius:7px;color:#536077;display:inline-block;font-size:.68rem;font-weight:700;padding:.28rem .45rem}.source-chip{background:#eef1ff;color:#4059d8;margin:.1rem}.status-pill{align-items:center;background:#f1f3f6;border-radius:999px;color:#687186;display:inline-flex;font-size:.65rem;font-weight:800;gap:.25rem;padding:.3rem .52rem}.status-pill--success{background:#e7f8f1;color:#17855f}.status-pill--danger{background:#ffebec;color:#c33b46}.status-pill--warning{background:#fff3dc;color:#a66b09}.status-pill--info{background:#e8f5ff;color:#2873a8}.payslip-pager{align-items:center;border-top:1px solid #edf0f5;color:#7a8291;display:flex;font-size:.72rem;gap:.8rem;justify-content:flex-end;margin-top:1rem;padding-top:.8rem}.payslip-empty{align-items:center;color:#8a92a2;display:flex;flex-direction:column;gap:.45rem;justify-content:center;min-height:160px}.payslip-empty i{color:#c5cad4;font-size:2rem}
.matrix-tabs{display:flex;flex-wrap:wrap;gap:.45rem}.matrix-tabs button{background:#fff;border:1px solid var(--line);border-radius:999px;color:#687186;font-size:.72rem;font-weight:700;padding:.45rem .8rem}.matrix-tabs button.active{background:#4059d8;border-color:#4059d8;color:#fff}.reconciliation-banner{align-items:center;background:#fff8e8;border:1px solid #f1d99d;border-radius:16px;display:flex;justify-content:space-between;padding:1rem 1.2rem}.reconciliation-banner>div{align-items:center;display:grid;gap:.1rem;grid-template-columns:42px auto}.reconciliation-banner i{font-size:1.6rem;grid-row:1/3}.reconciliation-banner span{color:#9a6f17;font-size:.66rem;font-weight:800;text-transform:uppercase}.reconciliation-banner strong{color:#6f5319}.reconciliation-banner p{color:#806c43;margin:0;max-width:620px}.reconciliation-banner--correcto{background:#eaf9f3;border-color:#b8ead7}.payslip-metrics--three{grid-template-columns:repeat(3,1fr)}
.wizard-stepper{background:#fff;border:1px solid var(--line);border-radius:14px;display:grid;grid-template-columns:repeat(9,1fr);overflow-x:auto;padding:.75rem}.wizard-stepper>div{align-items:center;color:#9aa1ae;display:flex;flex-direction:column;gap:.3rem;min-width:80px;position:relative}.wizard-stepper>div::after{background:#e6e9ef;content:"";height:2px;left:65%;position:absolute;top:13px;width:70%}.wizard-stepper>div:last-child::after{display:none}.wizard-stepper span{align-items:center;background:#eef0f4;border-radius:50%;display:flex;font-size:.68rem;font-weight:800;height:28px;justify-content:center;position:relative;width:28px;z-index:1}.wizard-stepper small{font-size:.62rem}.wizard-stepper .active{color:#4059d8}.wizard-stepper .active span{background:#4059d8;color:#fff}.wizard-stepper .done{color:#17855f}.wizard-stepper .done span,.wizard-stepper .done::after{background:#dff5ec;color:#17855f}.upload-card{border-radius:18px}.upload-card__intro{align-items:center;display:flex;gap:1rem;margin-bottom:1.3rem}.upload-card__intro>span{align-items:center;background:#eef1ff;border-radius:14px;color:#4059d8;display:flex;font-size:1.8rem;height:58px;justify-content:center;width:58px}.upload-card__intro small{color:#4059d8;font-size:.65rem;font-weight:800;text-transform:uppercase}.upload-card__intro h4{margin:.1rem 0}.upload-card__intro p{color:#7a8291;margin:0}.upload-grid{display:grid;gap:1rem;grid-template-columns:1fr 1fr;margin-bottom:1rem}.drop-zone{align-items:center;background:#f8f9fc;border:1px dashed #b9c1d1;border-radius:14px;cursor:pointer;display:flex!important;flex-direction:column;grid-column:1/3;justify-content:center;min-height:145px;text-transform:none!important}.drop-zone input{display:none}.drop-zone i{color:#e05260;font-size:2.2rem}.drop-zone b{color:#455064}.drop-zone span{color:#9299a7;font-size:.7rem}.batch-status-card :deep(.card-body){align-items:center;display:flex;gap:1rem;justify-content:space-between}.batch-status-card :deep(.card-body)>div:first-child{align-items:center;display:flex;gap:.8rem}.batch-icon{align-items:center;background:#e8f5ff;border-radius:12px;color:#2873a8;display:flex;font-size:1.35rem;height:46px;justify-content:center;width:46px}.batch-icon--danger{background:#ffebec;color:#c33b46}.batch-icon--success{background:#e7f8f1;color:#17855f}.batch-status-card small{color:#8a92a2}.batch-status-card h4{font-size:1rem;margin:.1rem 0}.batch-status-card p{color:#7a8291;font-size:.72rem;margin:0}.batch-progress{align-items:center;display:flex!important;gap:.6rem!important;min-width:260px}.batch-progress>div{background:#edf0f5;border-radius:999px;height:8px;overflow:hidden;width:100%}.batch-progress>div span{background:#4059d8;display:block;height:100%}.batch-progress b{font-size:.75rem}.period-edit{align-items:center;display:flex;gap:.15rem}.period-edit input{border:1px solid #dfe3eb;border-radius:5px;font-size:.7rem;padding:.2rem;width:56px}.period-edit input:last-child{width:70px}.issue-count{align-items:center;background:#ffebec;border-radius:50%;color:#c33b46;display:flex!important;height:28px;justify-content:center;width:28px}.issue-list{display:flex;flex-direction:column;gap:.55rem;max-height:420px;overflow:auto}.issue-list article{align-items:flex-start;background:#fff8e8;border-left:3px solid #e4a832;border-radius:8px;display:flex;gap:.55rem;padding:.65rem}.issue-list article.issue--error{background:#fff1f2;border-color:#d84f5b}.issue-list article>i{color:#d14b57;font-size:1.1rem}.issue-list b{color:#525b6c;display:block;font-size:.72rem}.issue-list small{color:#9aa1ae;display:block;font-size:.6rem}.issue-list button{background:transparent;border:0;color:#4059d8;font-size:.66rem;font-weight:800;margin-top:.25rem;padding:0}.confirmation-card{border-radius:16px;margin-top:1rem;text-align:center}.confirmation-card i{color:#1f9d70;font-size:2rem}.confirmation-card h5{font-size:.95rem}.confirmation-card p{color:#7a8291;font-size:.72rem}.proposal-hero{align-items:center;background:linear-gradient(120deg,#f0f4ff,#f8f6ff);border:1px solid #dfe6ff;border-radius:17px;display:flex;justify-content:space-between;padding:1.3rem 1.5rem}.proposal-hero span{color:#4059d8;font-size:.65rem;font-weight:800;text-transform:uppercase}.proposal-hero h3{font-size:1.2rem;margin:.2rem 0}.proposal-hero p{color:#70798b;margin:0;max-width:700px}.proposal-hero>i{color:#4059d8;font-size:3rem}.proposal-list{display:flex;flex-direction:column;gap:.65rem}.proposal-list article{align-items:center;border:1px solid #e8ebf1;border-radius:10px;display:grid;gap:.8rem;grid-template-columns:1fr auto auto;padding:.75rem}.proposal-list span{color:#727b8c;font-size:.7rem}.proposal-list strong{display:block}.proposal-list small{color:#9aa1ae;display:block}
@media(max-width:1199.98px){.payslip-filters{grid-template-columns:repeat(3,1fr)}.payslip-metrics{grid-template-columns:repeat(2,1fr)}.wizard-stepper{grid-template-columns:repeat(9,95px)}}
@media(max-width:767.98px){.payslip-hero{align-items:flex-start;flex-direction:column;gap:1rem;padding:1.4rem}.payslip-hero h2{font-size:1.35rem}.payslip-hero__signal{width:100%}.payslip-filters{grid-template-columns:1fr 1fr}.payslip-filter-search{grid-column:1/3}.payslip-metrics,.payslip-metrics--three{grid-template-columns:1fr}.funding-row{grid-template-columns:1fr}.funding-row>strong{text-align:left}.upload-grid{grid-template-columns:1fr}.drop-zone{grid-column:auto}.batch-status-card :deep(.card-body){align-items:stretch;flex-direction:column}.batch-progress{min-width:0;width:100%}.reconciliation-banner{align-items:flex-start;flex-direction:column;gap:.8rem}.proposal-list article{grid-template-columns:1fr}.payslip-nav button span{display:none}}
</style>
