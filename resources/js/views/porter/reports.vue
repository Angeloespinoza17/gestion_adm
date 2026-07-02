<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";
import { getPdfMake } from "../../utils/pdfmake";

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      canExport: false,
      filters: {
        date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
        date_to: new Date().toISOString().slice(0, 10),
      },
      report: {
        summary: {},
        withdrawals_by_course: [],
        withdrawals_by_reason: [],
        top_people: [],
        pending_items: [],
        pending_goods: [],
        movements_by_user: [],
      },
    };
  },
  mounted() {
    this.loadCatalogs().then(() => this.loadReport());
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/porter/catalogs");
      this.canExport = Boolean(response.data.capabilities?.can_export);
    },
    async loadReport() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/reports", {
          params: this.filters,
        });
        this.report = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    exportExcel() {
      const rows = [
        ["Resumen", ""],
        ["Retiros totales", this.report.summary.withdrawals_total || 0],
        ["Retiros autorizados", this.report.summary.withdrawals_authorized || 0],
        ["Retiros observados", this.report.summary.withdrawals_observed || 0],
        ["Retiros rechazados", this.report.summary.withdrawals_rejected || 0],
        ["Objetos pendientes", this.report.summary.items_pending || 0],
        ["Mercadería pendiente", this.report.summary.goods_pending || 0],
        [""],
        ["Retiros por curso", ""],
        ...this.report.withdrawals_by_course.map((item) => [item.course_name_snapshot, item.total]),
        [""],
        ["Retiros por motivo", ""],
        ...this.report.withdrawals_by_reason.map((item) => [item.reason, item.total]),
      ];

      const html = `<table>${rows.map((row) => `<tr>${row.map((cell) => `<td>${cell ?? ""}</td>`).join("")}</tr>`).join("")}</table>`;
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
    },
    exportPdf() {
      const pdfMake = getPdfMake();
      const doc = {
        content: [
          { text: "Reportes de Portería", style: "title" },
          { text: `Desde ${this.filters.date_from} hasta ${this.filters.date_to}`, style: "muted", margin: [0, 0, 0, 10] },
          {
            table: {
              widths: ["*", 80],
              body: [
                [{ text: "Indicador", style: "tableHeader" }, { text: "Total", style: "tableHeader" }],
                ["Retiros totales", this.report.summary.withdrawals_total || 0],
                ["Retiros autorizados", this.report.summary.withdrawals_authorized || 0],
                ["Retiros observados", this.report.summary.withdrawals_observed || 0],
                ["Retiros rechazados", this.report.summary.withdrawals_rejected || 0],
                ["Objetos pendientes", this.report.summary.items_pending || 0],
                ["Mercadería pendiente", this.report.summary.goods_pending || 0],
              ],
            },
            layout: "lightHorizontalLines",
          },
        ],
        styles: {
          title: { fontSize: 16, bold: true, color: "#2a3042" },
          muted: { fontSize: 9, color: "#74788d" },
          tableHeader: { bold: true, fillColor: "#eff2f7" },
        },
      };

      pdfMake.createPdf(doc).download(`reportes_porteria_${this.filters.date_from}_${this.filters.date_to}.pdf`);
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudieron cargar los reportes.";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0">Reportes de portería</h4>
        <div class="text-muted">Indicadores operativos y exportables del módulo.</div>
      </div>
      <div v-if="canExport" class="d-flex gap-2">
        <BButton variant="outline-success" @click="exportExcel">Excel</BButton>
        <BButton variant="outline-danger" @click="exportPdf">PDF</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.date_from" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.date_to" type="date" />
        </div>
        <div class="col-md-3">
          <BButton variant="primary" @click="loadReport">Actualizar</BButton>
        </div>
      </div>
    </BCard>

    <BCard v-if="loading">
      <LoadingState message="Cargando reportes..." compact />
    </BCard>

    <div v-else class="row g-3">
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Retiros totales</div>
          <div class="display-6 fw-semibold">{{ report.summary.withdrawals_total || 0 }}</div>
        </BCard>
      </div>
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Autorizados</div>
          <div class="display-6 fw-semibold">{{ report.summary.withdrawals_authorized || 0 }}</div>
        </BCard>
      </div>
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Objetos pendientes</div>
          <div class="display-6 fw-semibold">{{ report.summary.items_pending || 0 }}</div>
        </BCard>
      </div>
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Mercadería pendiente</div>
          <div class="display-6 fw-semibold">{{ report.summary.goods_pending || 0 }}</div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Retiros por curso">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Curso</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in report.withdrawals_by_course" :key="item.course_name_snapshot">
                  <td>{{ item.course_name_snapshot }}</td>
                  <td class="text-end">{{ item.total }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Retiros por motivo">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Motivo</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in report.withdrawals_by_reason" :key="item.reason">
                  <td>{{ item.reason }}</td>
                  <td class="text-end">{{ item.total }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Personas que retiran con mayor frecuencia">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Persona</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in report.top_people" :key="`${item.person_name}-${item.person_rut}`">
                  <td>{{ item.person_name }}<span v-if="item.person_rut" class="small text-muted"> · {{ item.person_rut }}</span></td>
                  <td class="text-end">{{ item.total }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Movimientos por usuario">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th class="text-end">Movimientos</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in report.movements_by_user" :key="item.id">
                  <td>{{ item.name }}</td>
                  <td class="text-end">{{ item.total }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Objetos pendientes">
          <div v-if="!report.pending_items.length" class="text-muted">Sin pendientes.</div>
          <ul v-else class="list-unstyled mb-0">
            <li v-for="item in report.pending_items" :key="item.id" class="mb-2">
              <div class="d-flex justify-content-between gap-2">
                <div>{{ item.description }}</div>
                <PorterStatusBadge :value="item.status" :label="item.status" />
              </div>
              <div class="small text-muted">{{ formatDateTime(item.received_at) }}</div>
            </li>
          </ul>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Mercadería pendiente">
          <div v-if="!report.pending_goods.length" class="text-muted">Sin pendientes.</div>
          <ul v-else class="list-unstyled mb-0">
            <li v-for="item in report.pending_goods" :key="item.id" class="mb-2">
              <div class="d-flex justify-content-between gap-2">
                <div>{{ item.goods_detail }}</div>
                <PorterStatusBadge :value="item.status" :label="item.status" />
              </div>
              <div class="small text-muted">{{ formatDateTime(item.moved_at) }}</div>
            </li>
          </ul>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
