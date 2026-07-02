<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      data: {
        totals: {},
        rounds_by_date: [],
        rounds_by_staff: [],
        sectors_with_most_incidents: [],
        recent_notifications: [],
        upcoming_shifts: [],
      },
      logoDataUrl: null,
    };
  },
  mounted() {
    this.loadDashboard();
    this.loadLogo();
  },
  methods: {
    async loadDashboard() {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/security/dashboard");
        this.data = response.data;
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.loading = false;
      }
    },
    async loadLogo() {
      try {
        const response = await fetch("/brand/logo-cnsc.png");
        if (!response.ok) return;
        const blob = await response.blob();
        this.logoDataUrl = await this.blobToDataUrl(blob);
      } catch (error) {
        this.logoDataUrl = null;
      }
    },
    blobToDataUrl(blob) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    },
    async exportPdf() {
      this.exporting = true;
      try {
        const pdfMake = getPdfMake();
        const totals = this.data.totals || {};
        const docDefinition = {
          pageOrientation: "portrait",
          content: [
            this.logoDataUrl
              ? {
                  columns: [
                    { image: this.logoDataUrl, width: 70 },
                    {
                      width: "*",
                      stack: [
                        { text: "Control de Nochero", style: "title" },
                        { text: "Resumen institucional de rondas de seguridad", color: "#6c757d" },
                      ],
                    },
                  ],
                  margin: [0, 0, 0, 12],
                }
              : { text: "Control de Nochero", style: "title" },
            {
              columns: [
                { text: `Rondas realizadas: ${totals.rounds_total || 0}` },
                { text: `Novedades: ${totals.incidents_total || 0}` },
                { text: `Críticas: ${totals.critical_incidents || 0}` },
              ],
              margin: [0, 0, 0, 8],
            },
            {
              columns: [
                { text: `Pendientes: ${totals.pending_incidents || 0}` },
                { text: `Resueltas: ${totals.resolved_incidents || 0}` },
                { text: `Promedio respuesta: ${totals.average_response_minutes ?? "-"} min` },
              ],
              margin: [0, 0, 0, 14],
            },
            { text: "Rondas por fecha", style: "section" },
            {
              table: {
                headerRows: 1,
                widths: ["*", 90],
                body: [
                  ["Fecha", "Rondas"],
                  ...(this.data.rounds_by_date || []).map((item) => [item.label, String(item.total)]),
                ],
              },
              layout: "lightHorizontalLines",
              margin: [0, 0, 0, 12],
            },
            { text: "Sectores con más incidencias", style: "section" },
            {
              table: {
                headerRows: 1,
                widths: ["*", 90],
                body: [
                  ["Sector", "Incidencias"],
                  ...(this.data.sectors_with_most_incidents || []).map((item) => [item.label, String(item.total)]),
                ],
              },
              layout: "lightHorizontalLines",
            },
          ],
          styles: {
            title: { fontSize: 18, bold: true },
            section: { fontSize: 13, bold: true, margin: [0, 8, 0, 6] },
          },
          defaultStyle: {
            fontSize: 10,
          },
        };

        pdfMake.createPdf(docDefinition).download(`control-nochero-resumen-${new Date().toISOString().slice(0, 10)}.pdf`);
        await this.showSuccess("Resumen exportado correctamente.");
      } finally {
        this.exporting = false;
      }
    },
    formatDateTime(value) {
      if (!value) return "-";
      return new Date(value).toLocaleString("es-CL", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudo cargar el panel.";
    },
    showSuccess(message) {
      return Swal.fire({
        icon: "success",
        title: "Operación realizada",
        text: message,
        confirmButtonText: "OK",
      });
    },
    showError(message) {
      return Swal.fire({
        icon: "error",
        title: "No se pudo completar la operación",
        text: message,
        confirmButtonText: "OK",
      });
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Panel de rondas</h4>
        <div class="text-muted">Resumen operativo de rondas, novedades y tiempos de respuesta.</div>
      </div>
      <div class="d-flex gap-2">
        <BButton variant="outline-secondary" @click="loadDashboard">Actualizar</BButton>
        <BButton variant="primary" :disabled="exporting" @click="exportPdf">Exportar PDF</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <LoadingState v-if="loading" message="Cargando panel..." />

    <template v-else>
      <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
          <BCard class="h-100 shadow-sm border-0">
            <div class="text-muted small">Total de rondas</div>
            <div class="display-6 fw-semibold">{{ data.totals.rounds_total || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="h-100 shadow-sm border-0">
            <div class="text-muted small">Novedades pendientes</div>
            <div class="display-6 fw-semibold text-warning">{{ data.totals.pending_incidents || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="h-100 shadow-sm border-0">
            <div class="text-muted small">Novedades críticas</div>
            <div class="display-6 fw-semibold text-danger">{{ data.totals.critical_incidents || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="h-100 shadow-sm border-0">
            <div class="text-muted small">Promedio de respuesta</div>
            <div class="display-6 fw-semibold text-success">{{ data.totals.average_response_minutes ?? "-" }}</div>
            <div class="small text-muted">minutos</div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard title="Rondas por fecha" class="h-100">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th class="text-end">Rondas</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.rounds_by_date" :key="item.label">
                    <td>{{ item.label }}</td>
                    <td class="text-end">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard title="Rondas por funcionario" class="h-100">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Funcionario</th>
                    <th class="text-end">Rondas</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.rounds_by_staff" :key="item.label">
                    <td>{{ item.label }}</td>
                    <td class="text-end">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard title="Sectores con más incidencias" class="h-100">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Sector</th>
                    <th class="text-end">Incidencias</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.sectors_with_most_incidents" :key="item.label">
                    <td>{{ item.label }}</td>
                    <td class="text-end">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard title="Alertas recientes" class="h-100">
            <div v-if="!data.recent_notifications.length" class="text-muted">No hay alertas recientes.</div>
            <div v-else class="list-group list-group-flush">
              <div
                v-for="notification in data.recent_notifications"
                :key="notification.id"
                class="list-group-item px-0"
              >
                <div class="d-flex justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold">{{ notification.title }}</div>
                    <div class="small text-muted">{{ notification.message }}</div>
                  </div>
                  <span
                    class="badge align-self-start"
                    :class="{
                      'bg-danger': notification.priority === 'critica',
                      'bg-warning text-dark': notification.priority === 'alta',
                      'bg-info': notification.priority === 'media',
                      'bg-secondary': notification.priority === 'baja',
                    }"
                  >
                    {{ notification.priority }}
                  </span>
                </div>
                <div class="small text-muted mt-1">{{ formatDateTime(notification.created_at) }}</div>
              </div>
            </div>
          </BCard>
        </div>

        <div class="col-12">
          <BCard title="Próximos turnos">
            <div class="row g-3">
              <div v-for="shift in data.upcoming_shifts" :key="shift.id" class="col-md-6 col-xl-4">
                <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                  <div class="fw-semibold">{{ shift.staff?.full_name || "-" }}</div>
                  <div class="small text-muted">{{ shift.schedule_summary }}</div>
                  <div class="small mt-2">
                    {{ shift.is_weekly_template ? formatDateTime(shift.next_occurrence_at) : formatDateTime(shift.scheduled_start_at) }}
                  </div>
                  <div class="small text-muted">
                    hasta {{ shift.is_weekly_template ? formatDateTime(shift.next_occurrence_end_at) : formatDateTime(shift.scheduled_end_at) }}
                  </div>
                  <span class="badge bg-primary-subtle text-primary mt-2">{{ shift.status }}</span>
                </div>
              </div>
            </div>
          </BCard>
        </div>
      </div>
    </template>
  </Layout>
</template>
