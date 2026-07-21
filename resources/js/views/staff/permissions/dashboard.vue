<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";
import EmptyState from "../../../components/staff/permissions/empty-state.vue";
import MetricCard from "../../../components/staff/permissions/metric-card.vue";
import PageHeader from "../../../components/staff/permissions/page-header.vue";
import StatusBadge from "../../../components/staff/permissions/status-badge.vue";
import { getPdfMake } from "../../../utils/pdfmake";

export default {
  components: { Layout, EmptyState, MetricCard, PageHeader, StatusBadge },
  data() {
    return {
      loading: false,
      exportingReport: false,
      exportingYearSheet: false,
      error: null,
      stats: {
        pending: 0,
        approved_month: 0,
        rejected_month: 0,
        without_pay: 0,
        requires_replacement: 0,
        upcoming: 0,
      },
      recentPending: [],
      upcomingPermissions: [],
      topStaff: [],
      topDepartments: [],
    };
  },
  mounted() {
    this.loadDashboard();
  },
  computed: {
    statCards() {
      return [
        { key: "pending", label: "Pendientes", icon: "bx-time-five", variant: "warning", hint: "Requieren acción" },
        { key: "approved_month", label: "Aprobados del mes", icon: "bx-check-circle", variant: "success", hint: "Resueltos favorablemente" },
        { key: "rejected_month", label: "Rechazados del mes", icon: "bx-x-circle", variant: "danger", hint: "No aprobados" },
        { key: "without_pay", label: "Sin goce", icon: "bx-wallet", variant: "neutral", hint: "Impacto remuneracional" },
        { key: "requires_replacement", label: "Con reemplazo", icon: "bx-transfer-alt", variant: "info", hint: "Cobertura pendiente" },
        { key: "upcoming", label: "Próximos", icon: "bx-calendar-event", variant: "primary", hint: "Calendario operativo" },
      ];
    },
  },
  methods: {
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/permissions/dashboard");
        this.stats = response.data.stats || this.stats;
        this.recentPending = response.data.recent_pending || [];
        this.upcomingPermissions = response.data.upcoming_permissions || [];
        this.topStaff = response.data.top_staff || [];
        this.topDepartments = response.data.top_departments || [];
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    exportDashboardPdf() {
      this.exportingReport = true;

      try {
        const pdfMake = getPdfMake();
        const generatedAt = new Date();
        const fileStamp = generatedAt.toISOString().slice(0, 10);
        const docDefinition = {
          pageOrientation: "landscape",
          pageMargins: [32, 34, 32, 34],
          content: [
            {
              columns: [
                [
                  { text: "Reporte de permisos del personal", style: "title" },
                  { text: `Generado el ${this.formatDate(fileStamp)} · Dashboard operativo`, style: "subtitle" },
                ],
                {
                  text: "Módulo de permisos",
                  alignment: "right",
                  color: "#3152c9",
                  bold: true,
                  fontSize: 10,
                },
              ],
              columnGap: 20,
              margin: [0, 0, 0, 16],
            },
            this.buildStatsSection(),
            this.buildTableSection(
              "Solicitudes pendientes de revisión",
              ["Funcionario", "Tipo", "Estado", "Inicio"],
              this.recentPending.map((item) => [
                item.staff?.full_name || "-",
                item.permission_type?.name || "-",
                this.statusLabel(item.status),
                this.formatDate(item.start_date),
              ])
            ),
            this.buildTableSection(
              "Permisos próximos",
              ["Funcionario", "Tipo", "Inicio", "Término"],
              this.upcomingPermissions.map((item) => [
                item.staff?.full_name || "-",
                item.permission_type?.name || "-",
                this.formatDate(item.start_date),
                this.formatDate(item.end_date),
              ])
            ),
            {
              columns: [
                this.buildTableSection(
                  "Funcionarios con más solicitudes",
                  ["Funcionario", "Solicitudes"],
                  this.topStaff.map((item) => [item.full_name || "-", item.total ?? 0]),
                  true
                ),
                this.buildTableSection(
                  "Departamentos con más solicitudes",
                  ["Departamento", "Solicitudes"],
                  this.topDepartments.map((item) => [item.name || "-", item.total ?? 0]),
                  true
                ),
              ],
              columnGap: 16,
            },
          ],
          styles: {
            title: { fontSize: 17, bold: true, color: "#24324b" },
            subtitle: { fontSize: 9, color: "#66738d", margin: [0, 4, 0, 0] },
            sectionTitle: { fontSize: 11, bold: true, color: "#24324b", margin: [0, 12, 0, 6] },
            metricLabel: { fontSize: 8, bold: true, color: "#66738d" },
            metricValue: { fontSize: 17, bold: true, color: "#24324b" },
          },
          defaultStyle: { fontSize: 8, color: "#334155" },
        };

        pdfMake.createPdf(docDefinition).download(`dashboard_permisos_${fileStamp}.pdf`);
      } catch (error) {
        this.error = error?.message || "No se pudo generar el reporte PDF.";
      } finally {
        this.exportingReport = false;
      }
    },
    async exportCurrentYearExcel() {
      if (this.exportingYearSheet) return;

      this.exportingYearSheet = true;
      this.error = null;

      try {
        const year = new Date().getFullYear();
        const response = await axios.get("/api/staff/permissions/reports", {
          params: {
            year,
            all: true,
          },
        });
        const rows = response.data.data?.data || [];
        const summary = response.data.summary || {};
        const html = this.buildCurrentYearExcel(rows, summary, year);

        this.downloadBlob(
          html,
          "application/vnd.ms-excel;charset=utf-8;",
          `permisos_${year}.xls`
        );
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.exportingYearSheet = false;
      }
    },
    buildCurrentYearExcel(rows, summary, year) {
      const headers = [
        "ID",
        "Funcionario",
        "RUT",
        "Cargo",
        "Departamentos",
        "Tipo de permiso",
        "Estado",
        "Inicio",
        "Término",
        "Hora inicio",
        "Hora término",
        "Duración",
        "Días",
        "Horas",
        "Jornada completa",
        "Media jornada",
        "Con goce",
        "Afecta remuneración",
        "Afecta asistencia",
        "Requiere reemplazo",
        "Urgente",
        "Retroactivo",
        "Estado asistencia",
        "Estado remuneraciones",
        "Horas descuento",
        "Días descuento",
        "Motivo",
        "Descripción",
        "Observaciones funcionario",
        "Observaciones visibles",
        "Observaciones internas",
        "Jefatura directa",
        "Enviado",
        "Aprobado",
        "Rechazado",
        "Cancelado",
        "Ejecutado",
      ];
      const summaryRows = [
        ["Total", summary.total ?? rows.length],
        ["Con goce", summary.con_goce ?? 0],
        ["Sin goce", summary.sin_goce ?? 0],
        ["Pendientes", summary.pendientes ?? 0],
        ["Rechazados", summary.rechazados ?? 0],
        ["Requieren reemplazo", summary.requieren_reemplazo ?? 0],
        ["Afectan remuneración", summary.afectan_remuneracion ?? 0],
        ["Fuera de plazo o urgencia", summary.fuera_plazo_o_urgencia ?? 0],
      ];
      const detailRows = rows.map((item) => [
        item.id,
        item.staff?.full_name || "-",
        item.staff?.rut || "-",
        item.staff?.cargo?.name || item.cargo_name || "-",
        (item.departments || []).map((department) => department.name).join(", ") || "-",
        item.permission_type?.name || "-",
        this.statusLabel(item.status),
        this.formatDate(item.start_date),
        this.formatDate(item.end_date),
        item.start_time || "-",
        item.end_time || "-",
        item.duration_label || "-",
        this.formatNumberCell(item.duration_days),
        this.formatNumberCell(item.duration_hours),
        this.booleanLabel(item.is_full_day),
        this.booleanLabel(item.is_half_day),
        item.with_pay === null || item.with_pay === undefined ? "Pendiente" : this.booleanLabel(item.with_pay),
        this.booleanLabel(item.affects_salary),
        this.booleanLabel(item.affects_attendance),
        this.booleanLabel(item.requires_replacement),
        this.booleanLabel(item.urgency),
        this.booleanLabel(item.retroactive),
        this.statusLabel(item.attendance_status),
        this.statusLabel(item.payroll_status),
        this.formatNumberCell(item.salary_discount_hours),
        this.formatNumberCell(item.salary_discount_days),
        item.reason || "-",
        item.description || "-",
        item.employee_observations || "-",
        item.visible_observations || "-",
        item.internal_observations || "-",
        item.direct_manager_name || "-",
        this.formatDateTime(item.submitted_at),
        this.formatDateTime(item.approved_at),
        this.formatDateTime(item.rejected_at),
        this.formatDateTime(item.cancelled_at),
        this.formatDateTime(item.executed_at),
      ]);

      return `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
          <head>
            <meta charset="UTF-8" />
            <style>
              body { font-family: Arial, sans-serif; color: #24324b; }
              h1 { margin: 0 0 4px; font-size: 20px; color: #24324b; }
              .meta { margin: 0 0 16px; color: #66738d; font-size: 12px; }
              table { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
              th { background: #eaf7ef; color: #117a37; font-weight: 700; }
              th, td { border: 1px solid #d8e2ef; padding: 7px 9px; font-size: 12px; vertical-align: top; }
              .summary th { background: #f6fff9; color: #117a37; }
              .text { mso-number-format: "\\@"; }
              .number { mso-number-format: "0.00"; text-align: right; }
            </style>
          </head>
          <body>
            <h1>Planilla anual de permisos ${this.escapeExcelHtml(year)}</h1>
            <div class="meta">Generado el ${this.escapeExcelHtml(this.formatDateTime(new Date().toISOString()))}</div>
            <table class="summary">
              <thead>
                <tr><th>Indicador</th><th>Total</th></tr>
              </thead>
              <tbody>
                ${summaryRows
                  .map((row) => `<tr><td>${this.escapeExcelHtml(row[0])}</td><td class="number">${this.escapeExcelHtml(row[1])}</td></tr>`)
                  .join("")}
              </tbody>
            </table>
            <table>
              <thead>
                <tr>${headers.map((header) => `<th>${this.escapeExcelHtml(header)}</th>`).join("")}</tr>
              </thead>
              <tbody>
                ${(detailRows.length ? detailRows : [["Sin datos para el año seleccionado."]])
                  .map((row) => `<tr>${row.map((cell) => `<td class="text">${this.escapeExcelHtml(cell)}</td>`).join("")}</tr>`)
                  .join("")}
              </tbody>
            </table>
          </body>
        </html>
      `;
    },
    buildStatsSection() {
      return {
        table: {
          widths: ["*", "*", "*", "*", "*", "*"],
          body: [
            this.statCards.map((card) => ({ text: card.label, style: "metricLabel" })),
            this.statCards.map((card) => ({ text: String(this.stats[card.key] ?? 0), style: "metricValue" })),
          ],
        },
        layout: {
          fillColor: (rowIndex) => (rowIndex === 0 ? "#f8fafc" : null),
          hLineColor: () => "#e6ebf4",
          vLineColor: () => "#e6ebf4",
          paddingLeft: () => 8,
          paddingRight: () => 8,
          paddingTop: () => 7,
          paddingBottom: () => 7,
        },
        margin: [0, 0, 0, 6],
      };
    },
    buildTableSection(title, headers, rows, compact = false) {
      const bodyRows = rows.length
        ? rows
        : [[{ text: "Sin datos para mostrar.", colSpan: headers.length, color: "#66738d", italics: true }, ...headers.slice(1).map(() => "")]];

      return {
        stack: [
          { text: title, style: "sectionTitle" },
          {
            table: {
              headerRows: 1,
              widths: compact ? ["*", "auto"] : headers.map(() => "*"),
              body: [
                headers.map((header) => ({ text: header, bold: true, color: "#475569" })),
                ...bodyRows,
              ],
            },
            layout: {
              fillColor: (rowIndex) => (rowIndex === 0 ? "#f1f5f9" : null),
              hLineColor: () => "#e6ebf4",
              vLineColor: () => "#e6ebf4",
              paddingLeft: () => 6,
              paddingRight: () => 6,
              paddingTop: () => 5,
              paddingBottom: () => 5,
            },
          },
        ],
        margin: [0, 0, 0, 4],
      };
    },
    formatDate(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).split(" ")[0].split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    statusLabel(value) {
      if (!value) return "-";
      return String(value)
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    booleanLabel(value) {
      return value ? "Sí" : "No";
    },
    formatNumberCell(value) {
      if (value === null || value === undefined || value === "") return "0";
      return String(value).replace(".", ",");
    },
    formatDateTime(value) {
      if (!value) return "-";
      const [date, time] = String(value).replace("T", " ").split(" ");
      const formattedDate = this.formatDate(date);

      return time ? `${formattedDate} ${time.slice(0, 5)}` : formattedDate;
    },
    escapeExcelHtml(value) {
      return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    },
    downloadBlob(content, type, name) {
      const blob = new Blob([`\uFEFF${content}`], { type });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = name;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudo cargar el dashboard.";
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader
      title="Dashboard de permisos"
      subtitle="Indicadores operativos, solicitudes pendientes y próximos permisos del personal."
      icon="bx-calendar-check"
    >
      <template #actions>
        <BButton
          class="permission-file-button permission-pdf-button"
          variant="link"
          :disabled="loading || exportingReport"
          aria-label="Descargar reporte PDF"
          title="Descargar reporte PDF"
          @click="exportDashboardPdf"
        >
          <i :class="exportingReport ? 'bx bx-loader-alt bx-spin' : 'mdi mdi-file-pdf-box'"></i>
        </BButton>
        <BButton
          class="permission-file-button permission-excel-button"
          variant="link"
          :disabled="loading || exportingYearSheet"
          aria-label="Descargar planilla Excel del año actual"
          title="Descargar planilla Excel del año actual"
          @click="exportCurrentYearExcel"
        >
          <i :class="exportingYearSheet ? 'bx bx-loader-alt bx-spin' : 'mdi mdi-file-excel-box'"></i>
        </BButton>
        <BButton variant="outline-primary" @click="loadDashboard">
          <i class="bx bx-refresh me-1"></i>Actualizar
        </BButton>
      </template>
    </PageHeader>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>
    <BCard v-if="loading" class="permission-card">Cargando dashboard...</BCard>

    <div v-else class="row g-3">
      <div v-for="card in statCards" :key="card.key" class="col-md-4 col-xl-2">
        <MetricCard
          :label="card.label"
          :value="stats[card.key] ?? 0"
          :hint="card.hint"
          :icon="card.icon"
          :variant="card.variant"
        />
      </div>

      <div class="col-lg-6">
        <BCard class="permission-card">
          <template #header>
            <div class="permission-section-title mb-0">
              <i class="bx bx-list-check"></i>
              <span>Solicitudes pendientes de revisión</span>
            </div>
          </template>
          <EmptyState
            v-if="!recentPending.length"
            icon="bx-check-shield"
            title="Sin pendientes"
            text="No hay solicitudes esperando revisión."
          />
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle permission-table">
              <thead>
                <tr>
                  <th>Funcionario</th>
                  <th>Tipo</th>
                  <th>Estado</th>
                  <th>Inicio</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in recentPending" :key="item.id">
                  <td>{{ item.staff?.full_name || "-" }}</td>
                  <td>{{ item.permission_type?.name || "-" }}</td>
                  <td><StatusBadge :status="item.status" /></td>
                  <td>{{ formatDate(item.start_date) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard class="permission-card">
          <template #header>
            <div class="permission-section-title mb-0">
              <i class="bx bx-calendar-event"></i>
              <span>Permisos próximos</span>
            </div>
          </template>
          <EmptyState
            v-if="!upcomingPermissions.length"
            icon="bx-calendar-x"
            title="Sin permisos próximos"
            text="El calendario operativo no tiene permisos programados."
          />
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle permission-table">
              <thead>
                <tr>
                  <th>Funcionario</th>
                  <th>Tipo</th>
                  <th>Inicio</th>
                  <th>Término</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in upcomingPermissions" :key="item.id">
                  <td>{{ item.staff?.full_name || "-" }}</td>
                  <td>{{ item.permission_type?.name || "-" }}</td>
                  <td>{{ formatDate(item.start_date) }}</td>
                  <td>{{ formatDate(item.end_date) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard class="permission-card">
          <template #header>
            <div class="permission-section-title mb-0">
              <i class="bx bx-user-pin"></i>
              <span>Funcionarios con más solicitudes</span>
            </div>
          </template>
          <EmptyState
            v-if="!topStaff.length"
            icon="bx-user"
            title="Sin datos"
            text="No hay actividad suficiente para mostrar este ranking."
          />
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle permission-table">
              <thead>
                <tr>
                  <th>Funcionario</th>
                  <th class="text-end">Solicitudes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in topStaff" :key="item.staff_id">
                  <td>{{ item.full_name }}</td>
                  <td class="text-end">{{ item.total }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard class="permission-card">
          <template #header>
            <div class="permission-section-title mb-0">
              <i class="bx bx-buildings"></i>
              <span>Departamentos con más solicitudes</span>
            </div>
          </template>
          <EmptyState
            v-if="!topDepartments.length"
            icon="bx-building"
            title="Sin datos"
            text="No hay actividad por departamento para mostrar."
          />
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle permission-table">
              <thead>
                <tr>
                  <th>Departamento</th>
                  <th class="text-end">Solicitudes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in topDepartments" :key="item.id">
                  <td>{{ item.name }}</td>
                  <td class="text-end">{{ item.total }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
