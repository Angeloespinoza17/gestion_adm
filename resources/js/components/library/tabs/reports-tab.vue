<script>
import axios from "axios";
import LibraryHelpButton from "../help-button.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  basicApexOptions,
  downloadExcelWorkbook,
  downloadPdfReport,
  extractChartLabels,
  formatLibraryDate,
  formatLibraryError,
  printLibraryHtml,
} from "../module-utils";

const periodOptions = [
  { value: "daily", label: "Hoy", fullLabel: "Diario" },
  { value: "weekly", label: "Semana", fullLabel: "Semanal" },
  { value: "monthly", label: "Mes", fullLabel: "Mensual" },
  { value: "semestral", label: "6 meses", fullLabel: "Semestral" },
  { value: "annual", label: "Año", fullLabel: "Anual" },
];

const labelMap = {
  student: "Estudiantes",
  staff: "Funcionarios",
  teacher: "Docentes",
  guardian: "Apoderados",
  course: "Cursos",
  libro: "Libros",
  diccionario: "Diccionarios",
  enciclopedia: "Enciclopedias",
  tablet: "Tablets",
  notebook: "Notebooks",
  proyector: "Proyectores",
  parlante: "Parlantes",
  juego_educativo: "Juegos educativos",
  material_didactico: "Material didáctico",
  kit_pedagogico: "Kits pedagógicos",
  audiovisual: "Audiovisual",
  disponible: "Disponible",
  prestado: "Prestado",
  reservado: "Reservado",
  en_reparacion: "En reparación",
  danado: "Dañado",
  perdido: "Perdido",
  dado_de_baja: "Dado de baja",
  solicitada: "Solicitada",
  aprobada: "Aprobada",
  rechazada: "Rechazada",
  cancelada: "Cancelada",
  pendiente: "Pendiente",
  planificado: "Planificado",
  en_ejecucion: "En ejecución",
  finalizado: "Finalizado",
  lectura: "Lectura",
  estudio: "Estudio",
  taller: "Taller",
  reunion: "Reunión",
  actividad_cultural: "Actividad cultural",
  clase: "Clase",
};

export default {
  components: {
    LibraryHelpButton,
    LoadingState,
  },
  props: {
    catalogs: { type: Object, required: true },
  },
  data() {
    return {
      loading: false,
      exportingPdf: false,
      error: null,
      report: {
        summary: {},
        sections: {},
        detail: {},
      },
      filters: {
        period: "monthly",
        date_from: "",
        date_to: "",
      },
      periodOptions,
    };
  },
  computed: {
    rangeLabel() {
      const from = this.report.summary?.from;
      const to = this.report.summary?.to;
      if (!from || !to) return "Periodo no disponible";
      return `${this.formatReportDate(from)} — ${this.formatReportDate(to)}`;
    },
    activePeriodLabel() {
      if (this.filters.date_from || this.filters.date_to) return "Rango personalizado";
      return this.periodOptions.find((item) => item.value === this.filters.period)?.fullLabel || "Mensual";
    },
    inventoryRows() {
      return (this.report.summary?.inventory_status || []).map((item) => [
        this.groupLabel(item.label),
        Number(item.total || 0),
      ]);
    },
    inventoryTotal() {
      return this.inventoryRows.reduce((total, row) => total + Number(row[1] || 0), 0);
    },
    inventoryAvailable() {
      const row = (this.report.summary?.inventory_status || []).find((item) => item.label === "disponible");
      return Number(row?.total || 0);
    },
    returnRate() {
      const total = Number(this.report.summary?.total_loans || 0);
      if (!total) return 0;
      return Math.round((Number(this.report.summary?.total_returns || 0) / total) * 100);
    },
    primaryMetrics() {
      const summary = this.report.summary || {};
      return [
        {
          label: "Préstamos del periodo",
          value: summary.total_loans || 0,
          detail: "Entregas registradas",
          icon: "bx-transfer-alt",
          tone: "indigo",
        },
        {
          label: "Tasa de atrasos",
          value: `${summary.overdue_rate || 0}%`,
          detail: `${summary.total_overdue || 0} préstamo(s) vencido(s)`,
          icon: "bx-timer",
          tone: "rose",
        },
        {
          label: "Devoluciones",
          value: summary.total_returns || 0,
          detail: `${this.returnRate}% de los préstamos del periodo`,
          icon: "bx-check-circle",
          tone: "emerald",
        },
        {
          label: "Inventario controlado",
          value: this.inventoryTotal,
          detail: `${this.inventoryAvailable} unidad(es) disponibles`,
          icon: "bx-layer",
          tone: "amber",
        },
      ];
    },
    secondaryMetrics() {
      const summary = this.report.summary || {};
      return [
        { label: "Reservas", value: summary.total_reservations || 0, icon: "bx-bookmark" },
        { label: "Textos pendientes", value: summary.pending_textbook_deliveries || 0, icon: "bx-book-bookmark" },
        { label: "Órdenes con faltantes", value: summary.textbook_orders_with_shortages || 0, icon: "bx-error-circle" },
        { label: "Pases activos", value: summary.active_library_passes || 0, icon: "bx-id-card" },
        { label: "Uso de espacios", value: summary.total_spaces || 0, icon: "bx-building-house" },
        { label: "Planes lectores", value: summary.total_reading_plans || 0, icon: "bx-list-check" },
      ];
    },
    estateRows() {
      return this.objectRows(this.report.sections?.loans_by_estate);
    },
    bookRows() {
      return this.objectRows(this.report.sections?.loans_by_book);
    },
    courseRows() {
      return this.objectRows(this.report.sections?.loans_by_course);
    },
    estateSeries() {
      return this.estateRows.map((row) => row[1]);
    },
    inventorySeries() {
      return this.inventoryRows.map((row) => row[1]);
    },
    topBooksSeries() {
      return [{ name: "Préstamos", data: this.bookRows.slice(0, 8).map((row) => row[1]) }];
    },
    courseSeries() {
      return [{ name: "Préstamos", data: this.courseRows.slice(0, 8).map((row) => row[1]) }];
    },
    estateChartOptions() {
      return this.donutOptions(this.estateRows, ["#526ee0", "#2daf87", "#eeae45", "#9b6ddb", "#4ba5cf"]);
    },
    inventoryChartOptions() {
      return this.donutOptions(this.inventoryRows, ["#2daf87", "#526ee0", "#eeae45", "#db5d71", "#9b6ddb", "#667085"]);
    },
    topBooksChartOptions() {
      return this.barOptions(this.bookRows.slice(0, 8), "#526ee0");
    },
    courseChartOptions() {
      return this.barOptions(this.courseRows.slice(0, 8), "#2daf87");
    },
    sectionRows() {
      const sections = this.report.sections || {};
      return [
        { key: "courses", title: "Préstamos por curso", rows: this.objectRows(sections.loans_by_course), icon: "bx-group", tone: "indigo" },
        { key: "books", title: "Préstamos por libro", rows: this.objectRows(sections.loans_by_book), icon: "bx-book-open", tone: "blue" },
        { key: "categories", title: "Préstamos por categoría", rows: this.objectRows(sections.loans_by_category), icon: "bx-purchase-tag", tone: "purple" },
        { key: "estates", title: "Préstamos por estamento", rows: this.objectRows(sections.loans_by_estate), icon: "bx-user-pin", tone: "green" },
        { key: "overdue", title: "Mora por usuario", rows: this.objectRows(sections.overdue_by_user), icon: "bx-user-x", tone: "rose" },
        { key: "locations", title: "Inventario por ubicación", rows: this.objectRows(sections.inventory_by_location), icon: "bx-map", tone: "amber" },
        { key: "inventory", title: "Estado del inventario", rows: this.inventoryRows, icon: "bx-layer", tone: "green" },
        { key: "reservations", title: "Reservas por tipo", rows: this.objectRows(sections.reservations_by_type), icon: "bx-bookmark", tone: "blue" },
        { key: "reading", title: "Plan lector por estado", rows: this.objectRows(sections.reading_plan_by_status), icon: "bx-list-check", tone: "purple" },
        { key: "spaces", title: "Uso de espacios por actividad", rows: this.objectRows(sections.spaces_by_activity), icon: "bx-building", tone: "indigo" },
      ];
    },
    visibleDetailSections() {
      return this.sectionRows
        .filter((section) => ["categories", "overdue", "locations", "reservations", "reading", "spaces"].includes(section.key))
        .filter((section) => section.rows.length);
    },
    currentAlerts() {
      return this.report.sections?.current_alerts || [];
    },
    criticalAlerts() {
      return this.currentAlerts.filter((item) => ["critical", "danger", "error"].includes(item.alert_level)).length;
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatLibraryDate,
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/reportes", {
          params: this.filters,
        });
        this.report = response.data;
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudieron generar los reportes.");
      } finally {
        this.loading = false;
      }
    },
    selectPeriod(period) {
      this.filters.period = period;
      this.filters.date_from = "";
      this.filters.date_to = "";
      this.load();
    },
    clearCustomRange() {
      this.filters.date_from = "";
      this.filters.date_to = "";
      this.load();
    },
    formatReportDate(value) {
      if (!value) return "-";
      const normalized = /^\d{4}-\d{2}-\d{2}$/.test(String(value))
        ? `${value}T12:00:00`
        : value;
      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      }).format(new Date(normalized));
    },
    groupLabel(value) {
      if (labelMap[value]) return labelMap[value];
      return String(value || "Sin información")
        .replaceAll("_", " ")
        .replace(/(^|[\s-])\p{L}/gu, (letter) => letter.toUpperCase());
    },
    objectRows(source) {
      return Object.entries(source || {}).map(([label, total]) => [
        this.groupLabel(label),
        Number(total || 0),
      ]);
    },
    hasRows(rows) {
      return Array.isArray(rows) && rows.some((row) => Number(row[1]) > 0);
    },
    maxForRows(rows) {
      return Math.max(1, ...(rows || []).map((row) => Number(row[1] || 0)));
    },
    barWidth(total, rows) {
      return `${Math.max(4, Math.round((Number(total || 0) / this.maxForRows(rows)) * 100))}%`;
    },
    donutOptions(rows, colors) {
      return {
        chart: { fontFamily: "inherit", toolbar: { show: false } },
        labels: extractChartLabels(rows.map((row) => ({ label: row[0] }))),
        colors,
        stroke: { width: 3, colors: ["#fff"] },
        legend: {
          position: "bottom",
          fontSize: "11px",
          markers: { width: 7, height: 7, radius: 7 },
        },
        dataLabels: { enabled: false },
        plotOptions: {
          pie: {
            donut: {
              size: "70%",
              labels: {
                show: true,
                total: {
                  show: true,
                  label: "Total",
                  color: "#7b8798",
                  formatter: () => String(rows.reduce((total, row) => total + Number(row[1] || 0), 0)),
                },
              },
            },
          },
        },
        tooltip: { y: { formatter: (value) => `${value} registro(s)` } },
      };
    },
    barOptions(rows, color) {
      return {
        ...basicApexOptions({
          categories: rows.map((row) => row[0]),
          colors: [color],
          horizontal: true,
        }),
        chart: { toolbar: { show: false }, fontFamily: "inherit" },
        xaxis: {
          categories: rows.map((row) => row[0]),
          labels: { style: { colors: "#8792a3", fontSize: "10px" } },
        },
        yaxis: {
          labels: {
            maxWidth: 155,
            style: { colors: "#59677d", fontSize: "10px" },
          },
        },
        plotOptions: {
          bar: {
            horizontal: true,
            borderRadius: 5,
            barHeight: "48%",
          },
        },
        dataLabels: { enabled: false },
        grid: { borderColor: "#edf0f5", strokeDashArray: 3 },
        tooltip: { y: { formatter: (value) => `${value} préstamo(s)` } },
      };
    },
    alertLevelLabel(level) {
      const labels = {
        critical: "Crítica",
        danger: "Crítica",
        error: "Crítica",
        warning: "Advertencia",
        info: "Informativa",
      };
      return labels[level] || this.groupLabel(level);
    },
    alertTone(level) {
      if (["critical", "danger", "error"].includes(level)) return "critical";
      if (level === "warning") return "warning";
      return "info";
    },
    exportSections() {
      return [{
        title: `Resumen · ${this.rangeLabel}`,
        headers: ["Indicador", "Resultado"],
        rows: this.primaryMetrics.concat(this.secondaryMetrics).map((item) => [item.label, item.value]),
      }].concat(this.sectionRows.map((section) => ({
        title: section.title,
        headers: ["Agrupación", "Total"],
        rows: section.rows,
      })));
    },
    exportExcel() {
      downloadExcelWorkbook("biblioteca-reportes", this.exportSections());
    },
    pdfSections() {
      const descriptions = {
        courses: "Cursos y niveles con actividad de préstamo durante el periodo.",
        books: "Títulos ordenados según su nivel de circulación.",
        categories: "Distribución de los préstamos por categoría interna.",
        estates: "Participación de estudiantes, funcionarios, docentes y apoderados.",
        overdue: "Personas que mantienen préstamos vencidos en el periodo.",
        locations: "Distribución física de los ejemplares por ubicación.",
        inventory: "Disponibilidad y condición operativa del inventario.",
        reservations: "Solicitudes de reserva agrupadas por tipo de recurso.",
        reading: "Situación de los planes lectores registrados.",
        spaces: "Actividades realizadas en los espacios de biblioteca.",
      };
      const sections = this.sectionRows
        .filter((section) => section.rows.length)
        .map((section) => ({
          title: section.title,
          description: descriptions[section.key],
          headers: ["Agrupación", "Total"],
          widths: ["*", 72],
          rows: section.rows,
        }));

      if (this.currentAlerts.length) {
        sections.push({
          title: "Alertas operativas vigentes",
          description: "Situaciones pendientes que requieren revisión o seguimiento.",
          headers: ["Alerta", "Tipo", "Nivel", "Registrada"],
          widths: ["*", 105, 72, 72],
          rows: this.currentAlerts.map((item) => [
            item.title || "Sin título",
            this.groupLabel(item.alert_type),
            this.alertLevelLabel(item.alert_level),
            this.formatLibraryDate(item.created_at),
          ]),
        });
      }

      return sections;
    },
    async collectPdfCharts() {
      const definitions = [
        ["estatePdfChart", "Préstamos por estamento", "Participación de la comunidad escolar en la circulación bibliográfica."],
        ["inventoryPdfChart", "Estado del inventario", "Disponibilidad y condición operativa de todos los ejemplares."],
        ["booksPdfChart", "Libros más prestados", "Títulos con mayor circulación durante el periodo seleccionado."],
        ["coursesPdfChart", "Préstamos por curso", "Comparación de la actividad entre cursos y niveles."],
      ];

      await this.$nextTick();
      await new Promise((resolve) => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)));

      const charts = [];
      for (const [ref, title, caption] of definitions) {
        const component = this.$refs[ref];
        const element = component?.$el;
        if (!component || typeof component.dataURI !== "function" || !element?.isConnected) continue;

        try {
          const result = await component.dataURI({ scale: 1.6 });
          if (result?.imgURI) charts.push({ title, caption, image: result.imgURI });
        } catch (_) {
          // Un gráfico aún no renderizado no debe impedir la descarga del informe.
        }
      }

      return charts;
    },
    async exportPdf() {
      if (this.exportingPdf) return;
      this.exportingPdf = true;

      try {
        const charts = await this.collectPdfCharts();
        const summary = this.report.summary || {};
        const from = summary.from ? this.formatReportDate(summary.from) : "inicio no informado";
        const to = summary.to ? this.formatReportDate(summary.to) : "término no informado";
        const fileRange = `${String(summary.from || "inicio").slice(0, 10)}_${String(summary.to || "fin").slice(0, 10)}`;
        const overdueMessage = Number(summary.total_overdue || 0)
          ? `${summary.total_overdue} préstamo(s) vencido(s) requieren seguimiento.`
          : "No se registran préstamos vencidos en el periodo.";

        downloadPdfReport(
          `biblioteca-reportes_${fileRange}`,
          "Radiografía de la biblioteca",
          `${this.activePeriodLabel} | ${from} al ${to}`,
          this.pdfSections(),
          {
            variant: "analytics",
            organization: "BIBLIOTECA ESCOLAR | AVIS",
            reportLabel: "INFORME DE GESTIÓN CRA",
            generatedAt: new Date().toLocaleString("es-CL"),
            summary: `El periodo registra ${summary.total_loans || 0} préstamo(s), ${summary.total_returns || 0} devolución(es) y una tasa de atraso de ${summary.overdue_rate || 0}%. ${overdueMessage} El inventario controlado alcanza ${this.inventoryTotal} ejemplar(es), con ${this.inventoryAvailable} disponible(s).`,
            metrics: this.primaryMetrics,
            supportingMetrics: this.secondaryMetrics,
            charts,
          }
        );
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo generar el PDF del reporte.");
      } finally {
        this.exportingPdf = false;
      }
    },
    printReport() {
      const html = this.exportSections()
        .map(
          (section) => `
            <h3>${section.title}</h3>
            <table>
              <thead><tr>${section.headers.map((header) => `<th>${header}</th>`).join("")}</tr></thead>
              <tbody>${section.rows.map((row) => `<tr><td>${row[0]}</td><td>${row[1]}</td></tr>`).join("")}</tbody>
            </table>
          `
        )
        .join("");
      printLibraryHtml("Reportes Biblioteca Escolar", html);
    },
  },
};
</script>

<template>
  <div class="report-view">
    <section class="report-head">
      <div class="report-head__copy">
        <span class="report-kicker"><i class="bx bx-line-chart"></i> INTELIGENCIA DE GESTIÓN</span>
        <h5>Radiografía de la biblioteca</h5>
        <p>Circulación, mora, inventario y participación escolar reunidos para tomar decisiones con rapidez.</p>
        <div class="report-range"><i class="bx bx-calendar"></i><strong>{{ activePeriodLabel }}</strong><span>{{ rangeLabel }}</span></div>
      </div>
      <div class="report-head__actions">
        <LibraryHelpButton
          title="Ayuda: reportes de biblioteca"
          text="Selecciona un periodo o rango de fechas. El panel se actualiza y mantiene el consolidado completo disponible para Excel, PDF o impresión."
          button-text="Ayuda del reporte"
        />
        <div class="export-group" aria-label="Exportar reporte">
          <button type="button" data-cnsc-action-ignore title="Exportar a Excel" @click="exportExcel"><i class="bx bx-spreadsheet"></i><span>Excel</span></button>
          <button type="button" data-cnsc-action-ignore title="Exportar a PDF" :disabled="exportingPdf" @click="exportPdf">
            <i class="bx" :class="exportingPdf ? 'bx-loader-alt spinning' : 'bx-file'"></i>
            <span>{{ exportingPdf ? "Generando..." : "PDF" }}</span>
          </button>
          <button type="button" data-cnsc-action-ignore title="Imprimir reporte" @click="printReport"><i class="bx bx-printer"></i><span>Imprimir</span></button>
        </div>
      </div>
    </section>

    <BAlert v-if="error" show variant="danger" class="border-0 rounded-4">{{ error }}</BAlert>

    <section class="filter-panel">
      <div class="filter-panel__period">
        <div>
          <small>PERIODO DEL ANÁLISIS</small>
          <strong>Selecciona una vista rápida</strong>
        </div>
        <div class="period-switch" role="group" aria-label="Periodo del reporte">
          <button
            v-for="period in periodOptions"
            :key="period.value"
            type="button"
            data-cnsc-action-ignore
            :class="{ active: filters.period === period.value && !filters.date_from && !filters.date_to }"
            :aria-pressed="filters.period === period.value && !filters.date_from && !filters.date_to"
            @click="selectPeriod(period.value)"
          >{{ period.label }}</button>
        </div>
      </div>
      <div class="custom-range">
        <div>
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.date_from" type="date" />
        </div>
        <span><i class="bx bx-right-arrow-alt"></i></span>
        <div>
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.date_to" type="date" />
        </div>
        <button type="button" class="generate-button" data-cnsc-action-ignore :disabled="loading" @click="load">
          <i class="bx" :class="loading ? 'bx-loader-alt spinning' : 'bx-bar-chart-alt-2'"></i>
          Generar
        </button>
        <button v-if="filters.date_from || filters.date_to" type="button" class="clear-range" data-cnsc-action-ignore title="Limpiar fechas" @click="clearCustomRange">
          <i class="bx bx-x"></i>
        </button>
      </div>
    </section>

    <section v-if="loading" class="report-loading">
      <LoadingState message="Analizando actividad de biblioteca..." compact />
    </section>

    <template v-else>
      <section class="primary-metrics" aria-label="Indicadores principales">
        <article v-for="metric in primaryMetrics" :key="metric.label" :class="`metric-${metric.tone}`">
          <span class="metric-icon"><i class="bx" :class="metric.icon"></i></span>
          <div>
            <small>{{ metric.label }}</small>
            <strong>{{ metric.value }}</strong>
            <p>{{ metric.detail }}</p>
          </div>
        </article>
      </section>

      <section class="secondary-metrics" aria-label="Indicadores complementarios">
        <article v-for="metric in secondaryMetrics" :key="metric.label">
          <i class="bx" :class="metric.icon"></i>
          <div><strong>{{ metric.value }}</strong><span>{{ metric.label }}</span></div>
        </article>
      </section>

      <section class="analytics-grid">
        <article class="analytics-card">
          <header>
            <div><small>COMUNIDAD</small><h6>Préstamos por estamento</h6><p>Quiénes están utilizando la colección.</p></div>
            <span class="card-icon card-icon--indigo"><i class="bx bx-user-pin"></i></span>
          </header>
          <apexchart ref="estatePdfChart" v-if="hasRows(estateRows)" type="donut" height="285" :options="estateChartOptions" :series="estateSeries" />
          <div v-else class="chart-empty"><i class="bx bx-pie-chart-alt-2"></i><strong>Sin préstamos en el periodo</strong><span>La distribución aparecerá al registrar actividad.</span></div>
        </article>

        <article class="analytics-card">
          <header>
            <div><small>DISPONIBILIDAD</small><h6>Estado del inventario</h6><p>Condición operativa de todos los ejemplares.</p></div>
            <span class="card-icon card-icon--green"><i class="bx bx-layer"></i></span>
          </header>
          <apexchart ref="inventoryPdfChart" v-if="hasRows(inventoryRows)" type="donut" height="285" :options="inventoryChartOptions" :series="inventorySeries" />
          <div v-else class="chart-empty"><i class="bx bx-layer"></i><strong>Sin inventario registrado</strong><span>Agrega ejemplares para comenzar el análisis.</span></div>
        </article>

        <article class="analytics-card analytics-card--wide">
          <header>
            <div><small>PREFERENCIAS DE LECTURA</small><h6>Libros más prestados</h6><p>Ranking de títulos con mayor circulación.</p></div>
            <span class="card-icon card-icon--blue"><i class="bx bx-book-open"></i></span>
          </header>
          <apexchart ref="booksPdfChart" v-if="hasRows(bookRows)" type="bar" height="300" :options="topBooksChartOptions" :series="topBooksSeries" />
          <div v-else class="chart-empty"><i class="bx bx-book-open"></i><strong>Sin datos de circulación</strong><span>Los títulos aparecerán después de sus primeros préstamos.</span></div>
        </article>

        <article class="analytics-card analytics-card--wide">
          <header>
            <div><small>PARTICIPACIÓN</small><h6>Préstamos por curso</h6><p>Comparación de uso entre cursos y niveles.</p></div>
            <span class="card-icon card-icon--amber"><i class="bx bx-group"></i></span>
          </header>
          <apexchart ref="coursesPdfChart" v-if="hasRows(courseRows)" type="bar" height="300" :options="courseChartOptions" :series="courseSeries" />
          <div v-else class="chart-empty"><i class="bx bx-group"></i><strong>Sin cursos con actividad</strong><span>El ranking aparecerá cuando existan préstamos asociados.</span></div>
        </article>
      </section>

      <section v-if="visibleDetailSections.length" class="detail-section">
        <header class="section-heading">
          <div><small>ANÁLISIS COMPLEMENTARIO</small><h5>Distribuciones relevantes</h5><p>Los grupos con actividad durante el periodo seleccionado.</p></div>
          <span>{{ visibleDetailSections.length }} análisis con datos</span>
        </header>
        <div class="detail-grid">
          <article v-for="section in visibleDetailSections" :key="section.key" class="detail-card">
            <header>
              <span :class="`detail-icon detail-icon--${section.tone}`"><i class="bx" :class="section.icon"></i></span>
              <div><h6>{{ section.title }}</h6><small>{{ section.rows.reduce((total, row) => total + Number(row[1] || 0), 0) }} registro(s)</small></div>
            </header>
            <ol>
              <li v-for="(row, index) in section.rows.slice(0, 6)" :key="`${section.key}-${row[0]}`">
                <span class="ranking">{{ index + 1 }}</span>
                <div>
                  <span><strong>{{ row[0] }}</strong><b>{{ row[1] }}</b></span>
                  <i><span :style="{ width: barWidth(row[1], section.rows) }"></span></i>
                </div>
              </li>
            </ol>
          </article>
        </div>
      </section>

      <section class="alerts-panel">
        <header class="section-heading">
          <div>
            <small>SEGUIMIENTO OPERATIVO</small>
            <h5>Alertas vigentes</h5>
            <p>Situaciones que requieren revisión o seguimiento de biblioteca.</p>
          </div>
          <div class="alert-summary">
            <span><strong>{{ currentAlerts.length }}</strong> activas</span>
            <span v-if="criticalAlerts" class="critical"><strong>{{ criticalAlerts }}</strong> críticas</span>
          </div>
        </header>

        <div v-if="currentAlerts.length" class="alerts-table-wrap">
          <table class="alerts-table">
            <thead><tr><th>Alerta</th><th>Tipo</th><th>Nivel</th><th>Registrada</th></tr></thead>
            <tbody>
              <tr v-for="(item, index) in currentAlerts" :key="`${item.title}-${index}`">
                <td data-label="Alerta"><div class="alert-title"><span :class="`alert-dot alert-dot--${alertTone(item.alert_level)}`"></span><strong>{{ item.title }}</strong></div></td>
                <td data-label="Tipo">{{ groupLabel(item.alert_type) }}</td>
                <td data-label="Nivel"><span :class="`alert-level alert-level--${alertTone(item.alert_level)}`">{{ alertLevelLabel(item.alert_level) }}</span></td>
                <td data-label="Registrada">{{ formatLibraryDate(item.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="alerts-empty"><i class="bx bx-check-shield"></i><div><strong>Sin alertas pendientes</strong><span>No existen situaciones que requieran seguimiento en este momento.</span></div></div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.report-view {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.report-head {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1.35rem 1.5rem;
  border-radius: 20px;
  color: #fff;
  background:
    radial-gradient(circle at 88% 12%, rgba(139, 213, 207, .2), transparent 28%),
    linear-gradient(135deg, #172a4c, #3d4f9f 62%, #397e93);
  box-shadow: 0 16px 38px rgba(38, 54, 112, .18);
}

.report-head::after {
  content: "";
  position: absolute;
  right: -45px;
  bottom: -70px;
  width: 190px;
  height: 190px;
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 50%;
}

.report-head__copy,
.report-head__actions {
  position: relative;
  z-index: 1;
}

.report-head__copy {
  min-width: 0;
}

.report-kicker {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  color: #b9e3e0;
  font-size: .59rem;
  font-weight: 800;
  letter-spacing: .14em;
}

.report-kicker i {
  font-size: .85rem;
}

.report-head h5 {
  margin: .25rem 0;
  color: #fff;
  font-size: 1.05rem;
}

.report-head p {
  max-width: 660px;
  margin: 0;
  color: rgba(255,255,255,.72);
  font-size: .7rem;
}

.report-range {
  display: inline-flex;
  align-items: center;
  gap: .38rem;
  padding: .35rem .55rem;
  margin-top: .65rem;
  border: 1px solid rgba(255,255,255,.15);
  border-radius: 8px;
  color: rgba(255,255,255,.72);
  background: rgba(255,255,255,.08);
  font-size: .58rem;
}

.report-range strong {
  color: #fff;
}

.report-range span {
  padding-left: .38rem;
  border-left: 1px solid rgba(255,255,255,.18);
}

.report-head__actions {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: .55rem;
}

.export-group {
  display: flex;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,.17);
  border-radius: 11px;
  background: rgba(255,255,255,.08);
}

.export-group button {
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .4rem .65rem;
  border: 0 !important;
  color: rgba(255,255,255,.88);
  background: transparent;
  font-size: .62rem;
  font-weight: 750;
}

.export-group button + button {
  border-left: 1px solid rgba(255,255,255,.14) !important;
}

.export-group button:hover {
  color: #fff;
  background: rgba(255,255,255,.1);
}

.export-group button:disabled {
  cursor: wait;
  opacity: .7;
}

.export-group i {
  font-size: .88rem;
}

.filter-panel {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 1rem;
  padding: .85rem 1rem;
  border: 1px solid #dfe6f0;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(42, 58, 88, .05);
}

.filter-panel__period {
  display: flex;
  align-items: center;
  gap: .85rem;
}

.filter-panel__period > div:first-child {
  display: flex;
  flex-direction: column;
  min-width: 120px;
}

.filter-panel__period small,
.section-heading small,
.analytics-card header small {
  color: #7184bc;
  font-size: .53rem;
  font-weight: 800;
  letter-spacing: .1em;
}

.filter-panel__period strong {
  color: #3f4e65;
  font-size: .68rem;
}

.period-switch {
  display: flex;
  padding: .25rem;
  border-radius: 10px;
  background: #f1f4f8;
}

.period-switch button {
  min-height: 32px;
  padding: .35rem .6rem;
  border: 0 !important;
  border-radius: 8px !important;
  color: #718095;
  background: transparent;
  font-size: .61rem;
  font-weight: 750;
}

.period-switch button.active {
  color: #405bc3;
  background: #fff;
  box-shadow: 0 4px 12px rgba(61, 78, 124, .11);
}

.custom-range {
  display: flex;
  align-items: flex-end;
  gap: .45rem;
}

.custom-range > div {
  width: 132px;
}

.custom-range .form-label {
  margin-bottom: .25rem;
  color: #657286;
  font-size: .58rem;
  font-weight: 750;
}

.custom-range :deep(.form-control) {
  min-height: 36px;
  border-color: #dce3ed;
  border-radius: 9px;
  font-size: .63rem;
  box-shadow: none;
}

.custom-range > span {
  height: 36px;
  display: grid;
  place-items: center;
  color: #9aa4b3;
}

.generate-button,
.clear-range {
  min-height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .35rem;
  border-radius: 9px !important;
  font-size: .62rem;
  font-weight: 750;
}

.generate-button {
  padding: .4rem .75rem;
  border: 0 !important;
  color: #fff;
  background: linear-gradient(135deg, #4a65d2, #5977df);
}

.clear-range {
  width: 36px;
  padding: 0;
  border: 1px solid #e0e5ed !important;
  color: #758197;
  background: #fff;
}

.spinning {
  animation: spin .8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.report-loading {
  padding: 2rem;
  border: 1px solid #e1e6ef;
  border-radius: 16px;
  background: #fff;
}

.primary-metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .7rem;
}

.primary-metrics article {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: .7rem;
  min-height: 108px;
  padding: .85rem;
  border: 1px solid #e0e6ef;
  border-radius: 15px;
  background: #fff;
  box-shadow: 0 7px 20px rgba(43, 58, 87, .045);
}

.primary-metrics article::after {
  content: "";
  position: absolute;
  top: 0;
  right: 0;
  width: 42px;
  height: 3px;
  border-radius: 0 0 0 4px;
  background: var(--metric-color);
}

.metric-icon {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  color: var(--metric-color);
  background: var(--metric-bg);
  font-size: 1.15rem;
}

.primary-metrics article > div {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.primary-metrics small {
  color: #7c889a;
  font-size: .61rem;
  font-weight: 700;
}

.primary-metrics strong {
  margin: .08rem 0;
  color: #2d3d55;
  font-size: 1.28rem;
  line-height: 1;
}

.primary-metrics p {
  overflow: hidden;
  margin: .12rem 0 0;
  color: #98a1af;
  font-size: .55rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.metric-indigo { --metric-color: #526ee0; --metric-bg: #edf1ff; }
.metric-rose { --metric-color: #d85368; --metric-bg: #ffedf0; }
.metric-emerald { --metric-color: #239471; --metric-bg: #e9f8f2; }
.metric-amber { --metric-color: #c98925; --metric-bg: #fff4df; }

.secondary-metrics {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  overflow: hidden;
  border: 1px solid #e0e6ef;
  border-radius: 14px;
  background: #fff;
}

.secondary-metrics article {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  min-height: 67px;
  padding: .6rem;
}

.secondary-metrics article + article {
  border-left: 1px solid #edf0f4;
}

.secondary-metrics > article > i {
  color: #6d7c95;
  font-size: 1.05rem;
}

.secondary-metrics article div {
  display: flex;
  flex-direction: column;
}

.secondary-metrics strong {
  color: #33445b;
  font-size: .82rem;
}

.secondary-metrics span {
  color: #8b95a4;
  font-size: .54rem;
  white-space: nowrap;
}

.analytics-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .8rem;
}

.analytics-card {
  min-width: 0;
  padding: 1rem;
  border: 1px solid #e0e6ef;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(42, 58, 88, .05);
}

.analytics-card > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .75rem;
  padding-bottom: .65rem;
  border-bottom: 1px solid #eef1f5;
}

.analytics-card h6 {
  margin: .08rem 0;
  color: #34445b;
  font-size: .78rem;
}

.analytics-card header p {
  margin: 0;
  color: #8c96a5;
  font-size: .59rem;
}

.card-icon {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  font-size: .95rem;
}

.card-icon--indigo { color: #506ad4; background: #edf1ff; }
.card-icon--green { color: #258f70; background: #eaf8f2; }
.card-icon--blue { color: #347da7; background: #eaf5fa; }
.card-icon--amber { color: #c88723; background: #fff4df; }

.chart-empty {
  min-height: 270px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  text-align: center;
}

.chart-empty i {
  margin-bottom: .5rem;
  color: #c3cad5;
  font-size: 2rem;
}

.chart-empty strong {
  color: #657287;
  font-size: .7rem;
}

.chart-empty span {
  max-width: 260px;
  margin-top: .2rem;
  color: #9aa3b1;
  font-size: .58rem;
}

.detail-section,
.alerts-panel {
  overflow: hidden;
  border: 1px solid #e0e6ef;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(42, 58, 88, .05);
}

.section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: .9rem 1rem;
  border-bottom: 1px solid #e9edf3;
  background: linear-gradient(135deg, #fbfcff, #f6f9f8);
}

.section-heading h5 {
  margin: .08rem 0;
  color: #34445b;
  font-size: .82rem;
}

.section-heading p {
  margin: 0;
  color: #8994a4;
  font-size: .59rem;
}

.section-heading > span {
  padding: .28rem .5rem;
  border-radius: 7px;
  color: #526cc8;
  background: #edf1ff;
  font-size: .57rem;
  font-weight: 750;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.detail-card {
  padding: .9rem 1rem;
}

.detail-card:nth-child(even) {
  border-left: 1px solid #edf0f4;
}

.detail-card:nth-child(n+3) {
  border-top: 1px solid #edf0f4;
}

.detail-card > header {
  display: flex;
  align-items: center;
  gap: .55rem;
  margin-bottom: .7rem;
}

.detail-icon {
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  font-size: .86rem;
}

.detail-icon--purple { color: #8055c5; background: #f3edff; }
.detail-icon--rose { color: #cf5265; background: #ffedf0; }
.detail-icon--amber { color: #be7c1e; background: #fff4df; }
.detail-icon--blue { color: #367ea8; background: #eaf5fa; }
.detail-icon--indigo { color: #5069ce; background: #edf1ff; }
.detail-icon--green { color: #258d6e; background: #eaf8f2; }

.detail-card h6 {
  margin: 0;
  color: #3d4d63;
  font-size: .7rem;
}

.detail-card header small {
  color: #98a1af;
  font-size: .54rem;
}

.detail-card ol {
  display: grid;
  gap: .55rem;
  padding: 0;
  margin: 0;
  list-style: none;
}

.detail-card li {
  display: grid;
  grid-template-columns: 22px minmax(0, 1fr);
  align-items: center;
  gap: .45rem;
}

.ranking {
  width: 21px;
  height: 21px;
  display: grid;
  place-items: center;
  border-radius: 6px;
  color: #75829a;
  background: #f0f3f7;
  font-size: .52rem;
  font-weight: 800;
}

.detail-card li > div > span {
  display: flex;
  justify-content: space-between;
  gap: .6rem;
  margin-bottom: .2rem;
}

.detail-card li strong {
  overflow: hidden;
  color: #5b687c;
  font-size: .59rem;
  font-weight: 650;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.detail-card li b {
  color: #3d4c61;
  font-size: .59rem;
}

.detail-card li i {
  height: 4px;
  display: block;
  overflow: hidden;
  border-radius: 99px;
  background: #edf0f4;
}

.detail-card li i span {
  height: 100%;
  display: block;
  border-radius: inherit;
  background: linear-gradient(90deg, #5872d9, #59aa98);
}

.alert-summary {
  display: flex;
  gap: .45rem;
}

.alert-summary span {
  padding: .32rem .5rem;
  border-radius: 8px;
  color: #69778b;
  background: #f0f3f7;
  font-size: .57rem;
}

.alert-summary .critical {
  color: #be4558;
  background: #ffedf0;
}

.alerts-table-wrap {
  overflow-x: auto;
}

.alerts-table {
  width: 100%;
  border-collapse: collapse;
}

.alerts-table th {
  padding: .62rem .85rem;
  border-bottom: 1px solid #e8ecf2;
  color: #8792a3;
  background: #fafbfc;
  font-size: .55rem;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.alerts-table td {
  padding: .7rem .85rem;
  border-bottom: 1px solid #edf0f4;
  color: #69768a;
  font-size: .61rem;
}

.alerts-table tbody tr:last-child td {
  border-bottom: 0;
}

.alert-title {
  display: flex;
  align-items: center;
  gap: .45rem;
}

.alert-title strong {
  color: #435269;
  font-size: .63rem;
}

.alert-dot {
  width: 8px;
  height: 8px;
  flex: 0 0 8px;
  border-radius: 50%;
}

.alert-dot--critical { background: #dc5a6d; box-shadow: 0 0 0 4px #ffedf0; }
.alert-dot--warning { background: #dda039; box-shadow: 0 0 0 4px #fff4df; }
.alert-dot--info { background: #4c8fc0; box-shadow: 0 0 0 4px #eaf5fa; }

.alert-level {
  display: inline-flex;
  padding: .22rem .4rem;
  border-radius: 6px;
  font-size: .53rem;
  font-weight: 750;
}

.alert-level--critical { color: #be4558; background: #ffedf0; }
.alert-level--warning { color: #a96c16; background: #fff4df; }
.alert-level--info { color: #35799f; background: #eaf5fa; }

.alerts-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .65rem;
  padding: 1.7rem;
}

.alerts-empty > i {
  color: #2a9b77;
  font-size: 1.55rem;
}

.alerts-empty div {
  display: flex;
  flex-direction: column;
}

.alerts-empty strong {
  color: #405168;
  font-size: .68rem;
}

.alerts-empty span {
  color: #8e98a7;
  font-size: .58rem;
}

@media (max-width: 1180px) {
  .filter-panel {
    grid-template-columns: 1fr;
  }

  .custom-range {
    padding-top: .7rem;
    border-top: 1px solid #edf0f4;
  }

  .primary-metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .secondary-metrics {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .secondary-metrics article:nth-child(4) {
    border-left: 0;
  }

  .secondary-metrics article:nth-child(n+4) {
    border-top: 1px solid #edf0f4;
  }
}

@media (max-width: 800px) {
  .report-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .report-head__actions {
    width: 100%;
  }

  .export-group {
    flex: 1;
  }

  .export-group button {
    flex: 1;
    justify-content: center;
  }

  .filter-panel__period {
    align-items: flex-start;
    flex-direction: column;
  }

  .period-switch {
    width: 100%;
    overflow-x: auto;
  }

  .period-switch button {
    flex: 1;
    min-width: max-content;
  }

  .analytics-grid,
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .detail-card:nth-child(even) {
    border-left: 0;
  }

  .detail-card:nth-child(n+2) {
    border-top: 1px solid #edf0f4;
  }
}

@media (max-width: 576px) {
  .report-range {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .report-head__actions {
    align-items: stretch;
    flex-direction: column;
  }

  .export-group button span {
    display: none;
  }

  .custom-range {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .custom-range > div {
    width: auto;
  }

  .custom-range > span {
    display: none;
  }

  .generate-button {
    grid-column: 1 / -1;
  }

  .clear-range {
    width: 100%;
    grid-column: 1 / -1;
  }

  .primary-metrics {
    grid-template-columns: 1fr;
  }

  .primary-metrics article {
    min-height: 94px;
  }

  .secondary-metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .secondary-metrics article:nth-child(3),
  .secondary-metrics article:nth-child(5) {
    border-left: 0;
  }

  .secondary-metrics article:nth-child(n+3) {
    border-top: 1px solid #edf0f4;
  }

  .section-heading {
    align-items: flex-start;
    flex-direction: column;
  }

  .alerts-table-wrap {
    overflow: visible;
    padding: .7rem;
    background: #f6f8fb;
  }

  .alerts-table,
  .alerts-table tbody {
    display: block;
  }

  .alerts-table thead {
    display: none;
  }

  .alerts-table tbody {
    display: grid;
    gap: .6rem;
  }

  .alerts-table tr {
    display: grid;
    padding: .65rem .75rem;
    border: 1px solid #e1e6ee;
    border-radius: 12px;
    background: #fff;
  }

  .alerts-table td {
    display: grid;
    grid-template-columns: 82px minmax(0, 1fr);
    align-items: center;
    padding: .42rem 0;
    border-bottom: 1px solid #edf0f4;
  }

  .alerts-table td::before {
    content: attr(data-label);
    color: #939dac;
    font-size: .52rem;
    font-weight: 800;
    letter-spacing: .07em;
    text-transform: uppercase;
  }

  .alerts-table td:last-child {
    border-bottom: 0;
  }
}
</style>
