<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";
import { getPdfMake } from "../../utils/pdfmake";

const currentMonthRange = () => ({
  date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
  date_to: new Date().toISOString().slice(0, 10),
});

const emptyReport = () => ({
  summary: {},
  withdrawals_by_course: [],
  withdrawals_by_reason: [],
  top_people: [],
  pending_items: [],
  pending_goods: [],
  movements_by_user: [],
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      canExport: false,
      catalogs: {
        withdrawal_reasons: [],
        withdrawal_statuses: [],
        received_item_statuses: [],
        goods_statuses: [],
      },
      filters: currentMonthRange(),
      report: emptyReport(),
    };
  },
  computed: {
    summary() {
      return this.report.summary || {};
    },
    pendingTotal() {
      return Number(this.summary.items_pending || 0) + Number(this.summary.goods_pending || 0);
    },
    authorizedRate() {
      return this.percentage(this.summary.withdrawals_authorized, this.summary.withdrawals_total);
    },
    observedRate() {
      return this.percentage(this.summary.withdrawals_observed, this.summary.withdrawals_total);
    },
    rejectedRate() {
      return this.percentage(this.summary.withdrawals_rejected, this.summary.withdrawals_total);
    },
    periodLabel() {
      return `${this.formatDate(this.filters.date_from)} al ${this.formatDate(this.filters.date_to)}`;
    },
    summaryCards() {
      return [
        {
          label: "Retiros",
          value: this.summary.withdrawals_total || 0,
          detail: `${this.authorizedRate}% autorizados`,
          icon: "bx bx-log-out-circle",
          tone: "primary",
        },
        {
          label: "Autorizados",
          value: this.summary.withdrawals_authorized || 0,
          detail: "Salidas registradas sin reparo",
          icon: "bx bx-check-circle",
          tone: "success",
        },
        {
          label: "Observados",
          value: this.summary.withdrawals_observed || 0,
          detail: "Casos con revisión operativa",
          icon: "bx bx-error-circle",
          tone: "warning",
        },
        {
          label: "Rechazados",
          value: this.summary.withdrawals_rejected || 0,
          detail: "Solicitudes no autorizadas",
          icon: "bx bx-x-circle",
          tone: "danger",
        },
        {
          label: "Pendientes",
          value: this.pendingTotal,
          detail: `${this.summary.items_pending || 0} objetos · ${this.summary.goods_pending || 0} mercadería`,
          icon: "bx bx-package",
          tone: "info",
        },
      ];
    },
    withdrawalStatusRows() {
      return [
        { label: "Autorizados", value: this.summary.withdrawals_authorized || 0, rate: this.authorizedRate, tone: "success" },
        { label: "Observados", value: this.summary.withdrawals_observed || 0, rate: this.observedRate, tone: "warning" },
        { label: "Rechazados", value: this.summary.withdrawals_rejected || 0, rate: this.rejectedRate, tone: "danger" },
      ];
    },
    withdrawalsByCourse() {
      return this.report.withdrawals_by_course || [];
    },
    withdrawalsByReason() {
      return this.report.withdrawals_by_reason || [];
    },
    topPeople() {
      return this.report.top_people || [];
    },
    pendingItems() {
      return this.report.pending_items || [];
    },
    pendingGoods() {
      return this.report.pending_goods || [];
    },
    movementsByUser() {
      return this.report.movements_by_user || [];
    },
    busiestUser() {
      return this.movementsByUser[0] || null;
    },
    courseChartOptions() {
      return this.barChartOptions(
        this.withdrawalsByCourse.map((item) => item.course_name_snapshot || "Sin curso"),
        "#556ee6"
      );
    },
    courseChartSeries() {
      return [{ name: "Retiros", data: this.withdrawalsByCourse.map((item) => Number(item.total || 0)) }];
    },
    reasonChartOptions() {
      return {
        chart: { fontFamily: "inherit" },
        labels: this.withdrawalsByReason.map((item) => this.reasonLabel(item.reason)),
        colors: ["#556ee6", "#34c38f", "#f1b44c", "#50a5f1", "#f46a6a", "#74788d"],
        legend: { position: "bottom" },
        stroke: { colors: ["var(--bs-body-bg)"], width: 3 },
        dataLabels: { enabled: true, formatter: (value) => `${Math.round(value)}%` },
        plotOptions: { pie: { donut: { size: "64%", labels: { show: true, total: { show: true, label: "Retiros" } } } } },
      };
    },
    reasonChartSeries() {
      return this.withdrawalsByReason.map((item) => Number(item.total || 0));
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadReport();
  },
  methods: {
    barChartOptions(categories, color) {
      return {
        chart: { toolbar: { show: false }, fontFamily: "inherit" },
        colors: [color],
        dataLabels: { enabled: false },
        grid: { borderColor: "rgba(148, 163, 184, .2)", strokeDashArray: 4 },
        plotOptions: { bar: { borderRadius: 5, horizontal: true, barHeight: "58%" } },
        xaxis: { categories, min: 0, labels: { formatter: (value) => Math.round(value) } },
        tooltip: { y: { formatter: (value) => `${value} retiro(s)` } },
      };
    },
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/porter/catalogs");
        this.catalogs = response.data;
        this.canExport = Boolean(response.data.capabilities?.can_export);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async loadReport() {
      if (!this.validateFilters()) return;

      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/reports", {
          params: this.filters,
        });
        this.report = {
          ...emptyReport(),
          ...response.data,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = currentMonthRange();
      this.loadReport();
    },
    exportExcel() {
      if (!this.canExport || this.exporting) return;

      this.exporting = true;
      try {
        const sections = this.exportSections();
        const html = sections
          .map((section) => `
            <h3>${this.escapeHtml(section.title)}</h3>
            <table>
              <thead><tr>${section.headers.map((cell) => `<th>${this.escapeHtml(cell)}</th>`).join("")}</tr></thead>
              <tbody>
                ${this.rowsOrEmpty(section.rows, section.headers.length)
                  .map((row) => `<tr>${row.map((cell) => `<td>${this.escapeHtml(cell)}</td>`).join("")}</tr>`)
                  .join("")}
              </tbody>
            </table>
          `)
          .join("<br />");

        const blob = new Blob([`\uFEFF<html><body>${html}</body></html>`], {
          type: "application/vnd.ms-excel;charset=utf-8;",
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `reportes_porteria_${this.filters.date_from}_${this.filters.date_to}.xls`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      } finally {
        this.exporting = false;
      }
    },
    exportPdf() {
      if (!this.canExport || this.exporting) return;

      this.exporting = true;
      try {
        const pdfMake = getPdfMake();
        const content = [
          { text: "Reporte de Portería", style: "title" },
          { text: `Periodo ${this.periodLabel}`, style: "muted", margin: [0, 0, 0, 12] },
        ];

        this.exportSections().forEach((section) => {
          content.push({ text: section.title, style: "section" });
          content.push({
            table: {
              headerRows: 1,
              widths: section.headers.map(() => "*"),
              body: [section.headers.map((header) => ({ text: String(header), style: "tableHeader" }))].concat(
                this.rowsOrEmpty(section.rows, section.headers.length).map((row) => row.map((cell) => String(cell ?? "")))
              ),
            },
            layout: "lightHorizontalLines",
          });
        });

        pdfMake
          .createPdf({
            pageOrientation: "landscape",
            content,
            styles: {
              title: { fontSize: 17, bold: true, color: "#2a3042" },
              section: { fontSize: 12, bold: true, color: "#2a3042", margin: [0, 12, 0, 6] },
              muted: { fontSize: 9, color: "#74788d" },
              tableHeader: { bold: true, fillColor: "#eff2f7" },
            },
            defaultStyle: { fontSize: 9 },
          })
          .download(`reportes_porteria_${this.filters.date_from}_${this.filters.date_to}.pdf`);
      } finally {
        this.exporting = false;
      }
    },
    exportSections() {
      return [
        {
          title: "Resumen",
          headers: ["Indicador", "Total"],
          rows: [
            ["Retiros totales", this.summary.withdrawals_total || 0],
            ["Retiros autorizados", this.summary.withdrawals_authorized || 0],
            ["Retiros observados", this.summary.withdrawals_observed || 0],
            ["Retiros rechazados", this.summary.withdrawals_rejected || 0],
            ["Objetos pendientes", this.summary.items_pending || 0],
            ["Mercadería pendiente", this.summary.goods_pending || 0],
          ],
        },
        {
          title: "Retiros por curso",
          headers: ["Curso", "Total"],
          rows: this.withdrawalsByCourse.map((item) => [item.course_name_snapshot || "Sin curso", item.total || 0]),
        },
        {
          title: "Retiros por motivo",
          headers: ["Motivo", "Total"],
          rows: this.withdrawalsByReason.map((item) => [this.reasonLabel(item.reason), item.total || 0]),
        },
        {
          title: "Personas que retiran",
          headers: ["Persona", "Identificación", "Total"],
          rows: this.topPeople.map((item) => [item.person_name || "-", item.person_rut || "-", item.total || 0]),
        },
        {
          title: "Movimientos por usuario",
          headers: ["Usuario", "Movimientos"],
          rows: this.movementsByUser.map((item) => [item.name || "-", item.total || 0]),
        },
        {
          title: "Objetos pendientes",
          headers: ["Recepción", "Destinatario", "Estado", "Fecha"],
          rows: this.pendingItems.map((item) => [item.description || "-", this.receivedItemRecipient(item), this.receivedStatusLabel(item.status), this.formatDateTime(item.received_at)]),
        },
        {
          title: "Mercadería pendiente",
          headers: ["Mercadería", "Destino", "Estado", "Fecha"],
          rows: this.pendingGoods.map((item) => [item.goods_detail || "-", this.goodsDestination(item), this.goodsStatusLabel(item.status), this.formatDateTime(item.moved_at)]),
        },
      ];
    },
    rowsOrEmpty(rows, length) {
      if (rows.length) return rows;
      return [["Sin datos"].concat(Array.from({ length: Math.max(length - 1, 0) }, () => ""))];
    },
    validateFilters() {
      if (this.filters.date_from && this.filters.date_to && this.filters.date_from > this.filters.date_to) {
        this.error = "La fecha inicial no puede ser posterior a la fecha final.";
        return false;
      }

      return true;
    },
    maxTotal(rows) {
      return Math.max(...(rows || []).map((item) => Number(item.total || 0)), 1);
    },
    barWidth(item, rows) {
      return `${Math.max((Number(item.total || 0) / this.maxTotal(rows)) * 100, item.total ? 8 : 0)}%`;
    },
    percentage(value, total) {
      const denominator = Number(total || 0);
      if (!denominator) return 0;
      return Math.round((Number(value || 0) / denominator) * 100);
    },
    reasonLabel(value) {
      return this.catalogLabel("withdrawal_reasons", value);
    },
    receivedStatusLabel(value) {
      return this.catalogLabel("received_item_statuses", value);
    },
    goodsStatusLabel(value) {
      return this.catalogLabel("goods_statuses", value);
    },
    catalogLabel(collection, value) {
      const option = (this.catalogs[collection] || []).find((item) => item.value === value);
      if (option?.label) return option.label;
      return this.humanize(value);
    },
    receivedItemRecipient(item) {
      const studentName = [item.student_profile?.first_name, item.student_profile?.last_name].filter(Boolean).join(" ");
      return item.recipient_label || studentName || item.department?.name || "Sin destinatario";
    },
    goodsDestination(item) {
      return [item.department?.name, item.responsible_staff?.full_name].filter(Boolean).join(" · ") || item.contact_name || "Sin destino";
    },
    formatDate(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
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
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudieron cargar los reportes.";
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
    <section class="porter-reports">
      <div class="reports-heading">
        <div>
          <h4 class="mb-0">Reportes de portería</h4>
          <p class="mb-0 text-muted">Indicadores de retiros, pendientes y trazabilidad operativa.</p>
        </div>
        <div class="heading-actions">
          <router-link class="btn btn-outline-primary" to="/porter/dashboard">Panel portería</router-link>
          <BButton variant="primary" :disabled="loading" @click="loadReport">{{ loading ? "Actualizando..." : "Actualizar" }}</BButton>
          <BButton v-if="canExport" variant="outline-success" :disabled="loading || exporting" @click="exportExcel">Excel</BButton>
          <BButton v-if="canExport" variant="outline-danger" :disabled="loading || exporting" @click="exportPdf">PDF</BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

      <BCard class="reports-panel filters-panel">
        <div class="filter-grid">
          <div>
            <label class="form-label">Desde</label>
            <BFormInput v-model="filters.date_from" type="date" />
          </div>
          <div>
            <label class="form-label">Hasta</label>
            <BFormInput v-model="filters.date_to" type="date" />
          </div>
          <div class="period-summary">
            <span>Periodo</span>
            <strong>{{ periodLabel }}</strong>
          </div>
          <div class="filter-actions">
            <BButton variant="primary" :disabled="loading" @click="loadReport">Generar</BButton>
            <BButton variant="outline-secondary" :disabled="loading" @click="resetFilters">Mes actual</BButton>
          </div>
        </div>
      </BCard>

      <BCard v-if="loading" class="reports-panel">
        <LoadingState message="Cargando reportes..." compact />
      </BCard>

      <template v-else>
        <div class="summary-grid">
          <div v-for="card in summaryCards" :key="card.label" class="summary-card" :class="`tone-${card.tone}`">
            <div class="summary-icon"><i :class="card.icon"></i></div>
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
            <small>{{ card.detail }}</small>
          </div>
        </div>

        <div class="top-grid">
          <BCard class="reports-panel status-panel">
            <div class="panel-title-row">
              <div>
                <h5 class="mb-1">Estado de retiros</h5>
                <p class="mb-0 text-muted">Distribución del periodo seleccionado.</p>
              </div>
              <strong class="panel-total">{{ summary.withdrawals_total || 0 }}</strong>
            </div>

            <div class="status-stack">
              <div v-for="item in withdrawalStatusRows" :key="item.label" class="status-row">
                <div class="status-row-header">
                  <span>{{ item.label }}</span>
                  <strong>{{ item.value }} · {{ item.rate }}%</strong>
                </div>
                <div class="progress-track">
                  <div class="progress-fill" :class="`fill-${item.tone}`" :style="{ width: `${item.rate}%` }"></div>
                </div>
              </div>
            </div>
          </BCard>

          <BCard class="reports-panel activity-panel">
            <div class="panel-title-row">
              <div>
                <h5 class="mb-1">Actividad de usuarios</h5>
                <p class="mb-0 text-muted">Registros de trazabilidad capturados.</p>
              </div>
            </div>

            <div v-if="busiestUser" class="leader-row">
              <span>Mayor actividad</span>
              <strong>{{ busiestUser.name }}</strong>
              <small>{{ busiestUser.total }} movimientos</small>
            </div>
            <div v-else class="empty-state">Sin actividad registrada en el periodo.</div>

            <div class="compact-list">
              <div v-for="item in movementsByUser.slice(0, 5)" :key="item.id" class="compact-row">
                <span>{{ item.name }}</span>
                <strong>{{ item.total }}</strong>
              </div>
            </div>
          </BCard>
        </div>

        <div class="report-grid">
          <BCard class="reports-panel">
            <div class="panel-title-row">
              <h5 class="mb-0">Retiros por curso</h5>
            </div>
            <div v-if="!withdrawalsByCourse.length" class="empty-state">Sin retiros por curso.</div>
            <apexchart v-else type="bar" :height="Math.max(290, withdrawalsByCourse.length * 38)" :options="courseChartOptions" :series="courseChartSeries" />
          </BCard>

          <BCard class="reports-panel">
            <div class="panel-title-row">
              <h5 class="mb-0">Retiros por motivo</h5>
            </div>
            <div v-if="!withdrawalsByReason.length" class="empty-state">Sin retiros por motivo.</div>
            <apexchart v-else type="donut" height="330" :options="reasonChartOptions" :series="reasonChartSeries" />
          </BCard>

          <BCard class="reports-panel">
            <div class="panel-title-row">
              <h5 class="mb-0">Personas que retiran</h5>
            </div>
            <div v-if="!topPeople.length" class="empty-state">Sin recurrencia de personas.</div>
            <div v-else class="table-responsive">
              <table class="report-table">
                <thead>
                  <tr>
                    <th>Persona</th>
                    <th>Identificación</th>
                    <th class="text-end">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in topPeople" :key="`${item.person_name}-${item.person_rut}`">
                    <td>{{ item.person_name || "-" }}</td>
                    <td>{{ item.person_rut || "-" }}</td>
                    <td class="text-end fw-semibold">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>

          <BCard class="reports-panel">
            <div class="panel-title-row">
              <h5 class="mb-0">Movimientos por usuario</h5>
            </div>
            <div v-if="!movementsByUser.length" class="empty-state">Sin movimientos de usuario.</div>
            <div v-else class="table-responsive">
              <table class="report-table">
                <thead>
                  <tr>
                    <th>Usuario</th>
                    <th class="text-end">Movimientos</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in movementsByUser" :key="item.id">
                    <td>{{ item.name }}</td>
                    <td class="text-end fw-semibold">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <div class="pending-grid">
          <BCard class="reports-panel">
            <div class="panel-title-row">
              <div>
                <h5 class="mb-1">Objetos pendientes</h5>
                <p class="mb-0 text-muted">{{ summary.items_pending || 0 }} registros abiertos.</p>
              </div>
              <router-link class="btn btn-sm btn-outline-primary" to="/porter/received-items">Abrir</router-link>
            </div>
            <div v-if="!pendingItems.length" class="empty-state">Sin objetos pendientes.</div>
            <div v-else class="pending-list">
              <div v-for="item in pendingItems" :key="item.id" class="pending-row">
                <div>
                  <strong>{{ item.description }}</strong>
                  <span>{{ receivedItemRecipient(item) }} · {{ formatDateTime(item.received_at) }}</span>
                </div>
                <PorterStatusBadge :value="item.status" :label="receivedStatusLabel(item.status)" />
              </div>
            </div>
          </BCard>

          <BCard class="reports-panel">
            <div class="panel-title-row">
              <div>
                <h5 class="mb-1">Mercadería pendiente</h5>
                <p class="mb-0 text-muted">{{ summary.goods_pending || 0 }} movimientos abiertos.</p>
              </div>
              <router-link class="btn btn-sm btn-outline-primary" to="/porter/goods">Abrir</router-link>
            </div>
            <div v-if="!pendingGoods.length" class="empty-state">Sin mercadería pendiente.</div>
            <div v-else class="pending-list">
              <div v-for="item in pendingGoods" :key="item.id" class="pending-row">
                <div>
                  <strong>{{ item.goods_detail }}</strong>
                  <span>{{ goodsDestination(item) }} · {{ formatDateTime(item.moved_at) }}</span>
                </div>
                <PorterStatusBadge :value="item.status" :label="goodsStatusLabel(item.status)" />
              </div>
            </div>
          </BCard>
        </div>
      </template>
    </section>
  </Layout>
</template>

<style scoped>
.porter-reports {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.reports-heading,
.panel-title-row,
.filter-actions,
.status-row-header,
.bar-label,
.compact-row,
.pending-row {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
}

.reports-heading {
  flex-wrap: wrap;
}

.reports-heading h4,
.panel-title-row h5 {
  color: #343a46;
  font-weight: 700;
}

.heading-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.reports-panel,
.summary-card {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(217, 226, 246, 0.92);
  border-radius: 0.5rem;
  box-shadow: 0 18px 42px rgba(90, 110, 150, 0.08);
}

.reports-panel :deep(.card-body) {
  padding: 1.35rem;
}

.filter-grid {
  align-items: end;
  display: grid;
  gap: 0.85rem;
  grid-template-columns: repeat(12, minmax(0, 1fr));
}

.filter-grid > div {
  grid-column: span 3;
  min-width: 0;
}

.filter-grid .period-summary {
  align-self: stretch;
  background: #f8faff;
  border: 1px solid #e7ecf8;
  border-radius: 0.5rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 0.65rem 0.85rem;
}

.period-summary span,
.summary-card span,
.summary-card small,
.leader-row span,
.leader-row small,
.pending-row span {
  color: #747b91;
}

.period-summary strong,
.leader-row strong {
  color: #343a46;
}

.filter-actions {
  justify-content: flex-end;
}

.filter-actions .btn {
  min-width: 0;
  white-space: nowrap;
}

.summary-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.summary-card {
  min-height: 138px;
  padding: 1.15rem 1.2rem;
  position: relative;
}

.summary-icon {
  align-items: center;
  border-radius: 0.5rem;
  display: inline-flex;
  height: 2.3rem;
  justify-content: center;
  margin-bottom: 0.75rem;
  width: 2.3rem;
}

.summary-icon i {
  font-size: 1.25rem;
}

.summary-card strong {
  color: #343a46;
  display: block;
  font-size: 2rem;
  line-height: 1;
  margin: 0.35rem 0;
}

.tone-primary .summary-icon {
  background: rgba(85, 110, 230, 0.12);
  color: #556ee6;
}

.tone-success .summary-icon {
  background: rgba(52, 195, 143, 0.13);
  color: #34c38f;
}

.tone-warning .summary-icon {
  background: rgba(241, 180, 76, 0.16);
  color: #d99518;
}

.tone-danger .summary-icon {
  background: rgba(244, 106, 106, 0.14);
  color: #f46a6a;
}

.tone-info .summary-icon {
  background: rgba(80, 165, 241, 0.14);
  color: #50a5f1;
}

.top-grid,
.report-grid,
.pending-grid {
  display: grid;
  gap: 1rem;
}

.top-grid {
  grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.6fr);
}

.report-grid,
.pending-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.panel-title-row {
  margin-bottom: 1rem;
}

.panel-total {
  color: #343a46;
  font-size: 2rem;
  line-height: 1;
}

.status-stack,
.bar-list,
.pending-list,
.compact-list {
  display: grid;
  gap: 0.85rem;
}

.status-row-header span,
.bar-label span,
.compact-row span {
  color: #343a46;
  font-weight: 600;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-row-header strong,
.bar-label strong,
.compact-row strong {
  color: #343a46;
  white-space: nowrap;
}

.progress-track,
.bar-track {
  background: #eef2fa;
  border-radius: 999px;
  height: 0.55rem;
  overflow: hidden;
}

.progress-fill,
.bar-fill {
  background: #556ee6;
  border-radius: inherit;
  height: 100%;
  min-width: 0.25rem;
}

.bar-fill.secondary {
  background: #50a5f1;
}

.fill-success {
  background: #34c38f;
}

.fill-warning {
  background: #f1b44c;
}

.fill-danger {
  background: #f46a6a;
}

.leader-row {
  background: #f8faff;
  border: 1px solid #e7ecf8;
  border-radius: 0.5rem;
  display: grid;
  gap: 0.2rem;
  margin-bottom: 1rem;
  padding: 0.9rem 1rem;
}

.report-table {
  margin: 0;
  table-layout: fixed;
  width: 100%;
}

.report-table th {
  border-bottom: 1px solid #e7ecf8;
  color: #747b91;
  font-size: 0.75rem;
  letter-spacing: 0;
  padding: 0.75rem 0.6rem;
  text-transform: uppercase;
}

.report-table td {
  border-bottom: 1px solid #eef2fa;
  color: #343a46;
  overflow-wrap: anywhere;
  padding: 0.75rem 0.6rem;
  vertical-align: middle;
}

.pending-row {
  align-items: flex-start;
  border-bottom: 1px solid #eef2fa;
  padding-bottom: 0.85rem;
}

.pending-row:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.pending-row div {
  min-width: 0;
}

.pending-row strong,
.pending-row span {
  display: block;
  overflow-wrap: anywhere;
}

.empty-state {
  color: #747b91;
  padding: 1rem 0;
  text-align: center;
}

@media (max-width: 1399.98px) {
  .summary-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .top-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 991.98px) {
  .filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .filter-grid > div {
    grid-column: span 1;
  }

  .summary-grid,
  .report-grid,
  .pending-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 767.98px) {
  .reports-heading,
  .panel-title-row,
  .filter-actions,
  .pending-row {
    align-items: stretch;
    flex-direction: column;
  }

  .heading-actions,
  .heading-actions .btn,
  .filter-actions .btn {
    width: 100%;
  }

  .filter-grid {
    grid-template-columns: 1fr;
  }
}
</style>
