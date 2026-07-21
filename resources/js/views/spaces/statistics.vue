<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";
import { getPdfMake } from "../../utils/pdfmake";
import "./shared.css";

const defaultRange = () => {
  const current = new Date();
  const start = new Date(current.getFullYear(), current.getMonth(), 1);
  const end = new Date(current.getFullYear(), current.getMonth() + 1, 0);
  const format = (value) => {
    const year = value.getFullYear();
    const month = `${value.getMonth() + 1}`.padStart(2, "0");
    const day = `${value.getDate()}`.padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  return {
    date_from: format(start),
    date_to: format(end),
  };
};

export default {
  components: {
    Layout,
    Multiselect,
  },
  data() {
    const range = defaultRange();

    return {
      loading: false,
      exporting: false,
      error: null,
      catalogs: {
        dependencies: [],
        dependency_types: [],
        staff: [],
        granularities: [],
      },
      filters: {
        granularity: "week",
        date_from: range.date_from,
        date_to: range.date_to,
        dependency_id: null,
        dependency_type_id: null,
        staff_id: null,
      },
      stats: {
        filters: {},
        summary: {
          total_reservations: 0,
          total_usage_hours: 0,
          approved_count: 0,
          finished_count: 0,
          pending_count: 0,
          rejected_count: 0,
          cancelled_count: 0,
          active_spaces: 0,
          active_requesters: 0,
          average_duration_hours: 0,
        },
        time_series: [],
        by_dependency: [],
        by_dependency_type: [],
        by_requester: [],
        by_status: [],
        selected_dependency: null,
      },
    };
  },
  computed: {
    permissions() {
      return JSON.parse(localStorage.getItem("permissions") || "[]");
    },
    canExport() {
      return this.permissions.includes("exportar_estadisticas_espacios") || this.permissions.includes("administrar_calendario");
    },
    granularityOptions() {
      return (this.catalogs.granularities || []).map((item) => ({
        value: item.value,
        label: item.label,
      }));
    },
    dependencyOptions() {
      return [{ value: null, label: "Todas" }].concat(
        (this.catalogs.dependencies || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    typeOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.dependency_types || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    staffOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.staff || []).map((item) => ({
          value: item.id,
          label: item.full_name,
        }))
      );
    },
    summaryCards() {
      return [
        {
          title: "Reservas en rango",
          value: this.formatInteger(this.stats.summary.total_reservations),
          help: "Incluye todas las reservas que tocan el período filtrado.",
          icon: "bx-calendar",
          tone: "blue",
        },
        {
          title: "Horas de uso",
          value: this.formatHours(this.stats.summary.total_usage_hours),
          help: "Solo considera reservas aprobadas o finalizadas.",
          icon: "bx-time-five",
          tone: "green",
        },
        {
          title: "Espacios activos",
          value: this.formatInteger(this.stats.summary.active_spaces),
          help: "Cantidad de dependencias utilizadas en el período.",
          icon: "bx-buildings",
          tone: "amber",
        },
        {
          title: "Solicitantes activos",
          value: this.formatInteger(this.stats.summary.active_requesters),
          help: "Personas que generaron uso efectivo de espacios.",
          icon: "bx-group",
          tone: "slate",
        },
      ];
    },
    usageChartSeries() {
      return [
        {
          name: "Horas de uso",
          type: "column",
          data: (this.stats.time_series || []).map((item) => Number(item.hours_used || 0)),
        },
        {
          name: "Reservas",
          type: "line",
          data: (this.stats.time_series || []).map((item) => Number(item.reservations_count || 0)),
        },
      ];
    },
    usageChartOptions() {
      return {
        chart: {
          toolbar: { show: false },
          fontFamily: "inherit",
        },
        stroke: {
          width: [0, 3],
          curve: "smooth",
        },
        plotOptions: {
          bar: {
            borderRadius: 6,
            columnWidth: "46%",
          },
        },
        dataLabels: {
          enabled: false,
        },
        colors: ["#556ee6", "#34c38f"],
        xaxis: {
          categories: (this.stats.time_series || []).map((item) => item.label),
          labels: {
            rotate: -25,
          },
        },
        yaxis: [
          {
            title: {
              text: "Horas",
            },
            labels: {
              formatter: (value) => this.formatNumber(value),
            },
          },
          {
            opposite: true,
            title: {
              text: "Reservas",
            },
            labels: {
              formatter: (value) => this.formatNumber(value),
            },
          },
        ],
        legend: {
          position: "top",
        },
        grid: {
          borderColor: "#eff2f7",
        },
      };
    },
    statusChartSeries() {
      return (this.stats.by_status || []).map((item) => Number(item.count || 0));
    },
    statusChartOptions() {
      return {
        chart: {
          type: "donut",
        },
        labels: (this.stats.by_status || []).map((item) => item.label),
        colors: ["#f1b44c", "#34c38f", "#50a5f1", "#f46a6a", "#74788d"],
        legend: {
          position: "bottom",
        },
        dataLabels: {
          enabled: false,
        },
        stroke: {
          colors: ["#fff"],
        },
      };
    },
    exportRangeLabel() {
      return `${this.formatDate(this.filters.date_from)} al ${this.formatDate(this.filters.date_to)}`;
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadStats();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/spaces/statistics/catalogs");
      this.catalogs = response.data || this.catalogs;
    },
    async loadStats() {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/spaces/statistics", {
          params: {
            granularity: this.filters.granularity,
            date_from: this.filters.date_from,
            date_to: this.filters.date_to,
            dependency_id: this.filters.dependency_id,
            dependency_type_id: this.filters.dependency_type_id,
            staff_id: this.filters.staff_id,
          },
        });
        this.stats = response.data || this.stats;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    applyFilters() {
      if (this.filters.date_from && this.filters.date_to && this.filters.date_from > this.filters.date_to) {
        this.error = "La fecha final no puede ser anterior a la fecha inicial.";
        return;
      }

      this.loadStats();
    },
    resetFilters() {
      const range = defaultRange();
      this.filters = {
        granularity: "week",
        date_from: range.date_from,
        date_to: range.date_to,
        dependency_id: null,
        dependency_type_id: null,
        staff_id: null,
      };
      this.loadStats();
    },
    async exportPdf() {
      if (!this.canExport) {
        return;
      }

      this.exporting = true;
      this.error = null;

      try {
        const pdfMake = getPdfMake();
        const summaryRows = this.summaryCards.map((item) => [item.title, item.value, item.help]);
        const timeRows = (this.stats.time_series || []).map((item) => [
          item.label,
          this.formatNumber(item.reservations_count),
          this.formatHours(item.hours_used),
        ]);
        const dependencyRows = (this.stats.by_dependency || []).slice(0, 10).map((item) => [
          item.label,
          item.secondary_label || "-",
          this.formatNumber(item.reservations_count),
          this.formatHours(item.hours_used),
          `${this.formatNumber(item.share_percent)}%`,
        ]);
        const typeRows = (this.stats.by_dependency_type || []).slice(0, 10).map((item) => [
          item.label,
          this.formatNumber(item.reservations_count),
          this.formatHours(item.hours_used),
          `${this.formatNumber(item.share_percent)}%`,
        ]);
        const requesterRows = (this.stats.by_requester || []).slice(0, 10).map((item) => [
          item.label,
          item.secondary_label || "-",
          this.formatNumber(item.reservations_count),
          this.formatHours(item.hours_used),
          `${this.formatNumber(item.share_percent)}%`,
        ]);
        const statusRows = (this.stats.by_status || []).map((item) => [item.label, this.formatNumber(item.count)]);

        const filtersText = [
          `Período: ${this.exportRangeLabel}`,
          `Unidad: ${this.granularityLabel(this.filters.granularity)}`,
          this.selectedDependencyName() ? `Dependencia: ${this.selectedDependencyName()}` : null,
          this.selectedTypeName() ? `Tipo: ${this.selectedTypeName()}` : null,
          this.selectedStaffName() ? `Solicitante: ${this.selectedStaffName()}` : null,
        ]
          .filter(Boolean)
          .join(" · ");

        const content = [
          { text: "Estadísticas de uso de espacios", style: "header" },
          { text: filtersText, margin: [0, 0, 0, 14] },
          {
            table: {
              headerRows: 1,
              widths: ["*", 90, "*"],
              body: [
                ["Indicador", "Valor", "Detalle"],
                ...summaryRows,
              ],
            },
            layout: "lightHorizontalLines",
            margin: [0, 0, 0, 16],
          },
          { text: "Uso por período", style: "section" },
          {
            table: {
              headerRows: 1,
              widths: ["*", 80, 80],
              body: [
                ["Período", "Reservas", "Horas"],
                ...timeRows,
              ],
            },
            layout: "lightHorizontalLines",
            margin: [0, 0, 0, 16],
          },
          { text: "Dependencias más usadas", style: "section" },
          {
            table: {
              headerRows: 1,
              widths: ["*", 90, 70, 70, 60],
              body: [
                ["Dependencia", "Tipo", "Reservas", "Horas", "% uso"],
                ...dependencyRows,
              ],
            },
            layout: "lightHorizontalLines",
            margin: [0, 0, 0, 16],
          },
          { text: "Tipos de espacios", style: "section" },
          {
            table: {
              headerRows: 1,
              widths: ["*", 70, 70, 60],
              body: [
                ["Tipo", "Reservas", "Horas", "% uso"],
                ...typeRows,
              ],
            },
            layout: "lightHorizontalLines",
            margin: [0, 0, 0, 16],
          },
          { text: "Solicitantes", style: "section" },
          {
            table: {
              headerRows: 1,
              widths: ["*", 90, 70, 70, 60],
              body: [
                ["Solicitante", "Tipo", "Reservas", "Horas", "% uso"],
                ...requesterRows,
              ],
            },
            layout: "lightHorizontalLines",
            margin: [0, 0, 0, 16],
          },
          { text: "Estados operativos", style: "section" },
          {
            table: {
              headerRows: 1,
              widths: ["*", 70],
              body: [
                ["Estado", "Cantidad"],
                ...statusRows,
              ],
            },
            layout: "lightHorizontalLines",
          },
        ];

        if (this.stats.selected_dependency) {
          content.push(
            { text: "Dependencia filtrada", style: "section", margin: [0, 16, 0, 8] },
            {
              table: {
                widths: ["*", "*"],
                body: [
                  ["Dependencia", this.stats.selected_dependency.name || "-"],
                  ["Tipo", this.stats.selected_dependency.type_name || "-"],
                  ["Capacidad", this.formatInteger(this.stats.selected_dependency.capacity_max || 0)],
                  ["Reservas", this.formatInteger(this.stats.selected_dependency.total_reservations || 0)],
                  ["Horas de uso", this.formatHours(this.stats.selected_dependency.hours_used || 0)],
                  ["Duración promedio", this.formatHours(this.stats.selected_dependency.average_duration_hours || 0)],
                  ["Principal solicitante", this.stats.selected_dependency.top_requester?.name || "-"],
                ],
              },
              layout: "lightHorizontalLines",
            }
          );
        }

        pdfMake.createPdf({
          pageOrientation: "landscape",
          content,
          styles: {
            header: {
              fontSize: 16,
              bold: true,
            },
            section: {
              fontSize: 12,
              bold: true,
              margin: [0, 0, 0, 8],
            },
          },
          defaultStyle: {
            fontSize: 9,
          },
        }).download(`estadisticas_espacios_${this.filters.date_from}_${this.filters.date_to}.pdf`);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.exporting = false;
      }
    },
    selectedDependencyName() {
      return this.catalogs.dependencies.find((item) => item.id === this.filters.dependency_id)?.name || null;
    },
    selectedTypeName() {
      return this.catalogs.dependency_types.find((item) => item.id === this.filters.dependency_type_id)?.name || null;
    },
    selectedStaffName() {
      return this.catalogs.staff.find((item) => item.id === this.filters.staff_id)?.full_name || null;
    },
    granularityLabel(value) {
      return this.granularityOptions.find((item) => item.value === value)?.label || value;
    },
    formatDate(value) {
      const [year, month, day] = String(value || "").split("-");
      if (!year || !month || !day) {
        return value || "-";
      }
      return `${day}-${month}-${year}`;
    },
    formatHours(value) {
      return `${this.formatNumber(value)} h`;
    },
    formatNumber(value) {
      return Number(value || 0).toLocaleString("es-CL", {
        minimumFractionDigits: Number(value || 0) % 1 === 0 ? 0 : 1,
        maximumFractionDigits: 1,
      });
    },
    formatInteger(value) {
      return Number(value || 0).toLocaleString("es-CL", {
        maximumFractionDigits: 0,
      });
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="spaces-shell">
      <section class="spaces-hero">
        <div class="spaces-hero__body">
          <div class="spaces-eyebrow">Dependencias y reservas</div>
          <h4>Estadísticas de espacios</h4>
          <p>Uso de dependencias por período, solicitante y tipología de espacio.</p>
        </div>
        <div class="spaces-actions">
          <router-link to="/spaces/calendar" class="btn btn-outline-secondary">
            <i class="bx bx-calendar-event"></i>
            <span>Calendario</span>
          </router-link>
          <BButton v-if="canExport" variant="outline-danger" :disabled="exporting || loading" @click="exportPdf">
            <i class="bx bx-file"></i>
            <span>Exportar PDF</span>
          </BButton>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="mb-0">
        {{ error }}
      </BAlert>

      <section class="spaces-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-eyebrow">Filtros</div>
            <h5 class="spaces-panel-title">Segmentar estadísticas</h5>
          </div>
        </div>
        <div class="spaces-filter-grid spaces-filter-grid--wide">
          <label class="spaces-field">
            <span>Unidad de tiempo</span>
            <Multiselect v-model="filters.granularity" :options="granularityOptions" :searchable="false" />
          </label>
          <label class="spaces-field">
            <span>Desde</span>
            <BFormInput v-model="filters.date_from" type="date" />
          </label>
          <label class="spaces-field">
            <span>Hasta</span>
            <BFormInput v-model="filters.date_to" type="date" />
          </label>
          <label class="spaces-field">
            <span>Dependencia</span>
            <Multiselect v-model="filters.dependency_id" :options="dependencyOptions" :searchable="true" />
          </label>
          <label class="spaces-field">
            <span>Tipo</span>
            <Multiselect v-model="filters.dependency_type_id" :options="typeOptions" :searchable="true" />
          </label>
          <label class="spaces-field">
            <span>Solicitante</span>
            <Multiselect v-model="filters.staff_id" :options="staffOptions" :searchable="true" />
          </label>
          <div class="spaces-filter-actions">
            <BButton variant="primary" :disabled="loading" @click="applyFilters">
              <i class="bx bx-refresh"></i>
              <span>Actualizar</span>
            </BButton>
            <BButton variant="outline-secondary" :disabled="loading" @click="resetFilters">
              <i class="bx bx-x"></i>
              <span>Limpiar</span>
            </BButton>
          </div>
        </div>
      </section>

      <div class="spaces-summary-grid">
        <div
          v-for="card in summaryCards"
          :key="card.title"
          class="spaces-summary-card"
          :class="`spaces-summary-card--${card.tone}`"
        >
          <div class="spaces-summary-icon">
            <i :class="`bx ${card.icon}`"></i>
          </div>
          <div>
            <span>{{ card.title }}</span>
            <strong>{{ card.value }}</strong>
            <small>{{ card.help }}</small>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-8">
          <section class="spaces-panel spaces-chart-panel">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Tendencia</div>
                <h5 class="spaces-panel-title">Uso por período</h5>
                <p class="spaces-panel-subtitle">Comportamiento del uso aprobado/finalizado en el rango seleccionado.</p>
              </div>
              <div class="spaces-panel-meta">{{ exportRangeLabel }}</div>
            </div>
            <div v-if="loading" class="spaces-empty-state">Cargando estadísticas...</div>
            <div v-else-if="(stats.time_series || []).length === 0" class="spaces-empty-state">
              <i class="bx bx-bar-chart-alt-2"></i>
              <strong>Sin datos para el rango</strong>
              <span>Ajusta las fechas o filtros aplicados.</span>
            </div>
            <apexchart
              v-else
              type="line"
              height="320"
              :series="usageChartSeries"
              :options="usageChartOptions"
            />
          </section>
        </div>

        <div class="col-xl-4">
          <section class="spaces-panel spaces-chart-panel">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Estados</div>
                <h5 class="spaces-panel-title">Resumen operativo</h5>
              </div>
            </div>
            <div class="stats-kpi-list">
              <div class="stats-kpi-item">
                <span>Pendientes</span>
                <strong>{{ formatInteger(stats.summary.pending_count) }}</strong>
              </div>
              <div class="stats-kpi-item">
                <span>Aprobadas</span>
                <strong>{{ formatInteger(stats.summary.approved_count) }}</strong>
              </div>
              <div class="stats-kpi-item">
                <span>Finalizadas</span>
                <strong>{{ formatInteger(stats.summary.finished_count) }}</strong>
              </div>
              <div class="stats-kpi-item">
                <span>Rechazadas</span>
                <strong>{{ formatInteger(stats.summary.rejected_count) }}</strong>
              </div>
              <div class="stats-kpi-item">
                <span>Canceladas</span>
                <strong>{{ formatInteger(stats.summary.cancelled_count) }}</strong>
              </div>
              <div class="stats-kpi-item">
                <span>Duración promedio</span>
                <strong>{{ formatHours(stats.summary.average_duration_hours) }}</strong>
              </div>
            </div>
            <div class="border-top mt-4 pt-4">
              <h6 class="mb-3">Distribución por estado</h6>
              <div v-if="(stats.by_status || []).some((item) => item.count > 0)">
                <apexchart type="donut" height="260" :series="statusChartSeries" :options="statusChartOptions" />
              </div>
              <div v-else class="spaces-muted">Sin estados para mostrar.</div>
            </div>
          </section>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-4">
          <section class="spaces-panel">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Top 8</div>
                <h5 class="spaces-panel-title">Dependencias más usadas</h5>
              </div>
            </div>
            <div class="spaces-table-wrap">
              <table class="table spaces-data-table spaces-data-table--compact">
                <thead>
                  <tr>
                    <th>Dependencia</th>
                    <th class="text-end">Horas</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in (stats.by_dependency || []).slice(0, 8)" :key="`dependency-${item.label}`">
                    <td>
                      <div class="spaces-table-title">{{ item.label }}</div>
                      <span class="spaces-table-subtitle">{{ item.secondary_label || "Sin tipo" }}</span>
                    </td>
                    <td class="text-end">
                      <div class="spaces-table-title">{{ formatHours(item.hours_used) }}</div>
                      <span class="spaces-table-subtitle">{{ formatInteger(item.reservations_count) }} reservas</span>
                    </td>
                  </tr>
                  <tr v-if="(stats.by_dependency || []).length === 0">
                    <td colspan="2">
                      <div class="spaces-empty-state">Sin uso registrado.</div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <div class="col-xl-4">
          <section class="spaces-panel">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Top 8</div>
                <h5 class="spaces-panel-title">Tipos de espacios</h5>
              </div>
            </div>
            <div class="spaces-table-wrap">
              <table class="table spaces-data-table spaces-data-table--compact">
                <thead>
                  <tr>
                    <th>Tipo</th>
                    <th class="text-end">% uso</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in (stats.by_dependency_type || []).slice(0, 8)" :key="`type-${item.label}`">
                    <td>
                      <div class="spaces-table-title">{{ item.label }}</div>
                      <span class="spaces-table-subtitle">{{ formatInteger(item.reservations_count) }} reservas</span>
                    </td>
                    <td class="text-end">
                      <div class="spaces-table-title">{{ formatNumber(item.share_percent) }}%</div>
                      <span class="spaces-table-subtitle">{{ formatHours(item.hours_used) }}</span>
                    </td>
                  </tr>
                  <tr v-if="(stats.by_dependency_type || []).length === 0">
                    <td colspan="2">
                      <div class="spaces-empty-state">Sin uso registrado.</div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <div class="col-xl-4">
          <section class="spaces-panel">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Top 8</div>
                <h5 class="spaces-panel-title">Solicitantes</h5>
              </div>
            </div>
            <div class="spaces-table-wrap">
              <table class="table spaces-data-table spaces-data-table--compact">
                <thead>
                  <tr>
                    <th>Solicitante</th>
                    <th class="text-end">Horas</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in (stats.by_requester || []).slice(0, 8)" :key="`requester-${item.label}`">
                    <td>
                      <div class="spaces-table-title">{{ item.label }}</div>
                      <span class="spaces-table-subtitle">{{ item.secondary_label || "Sin tipo" }}</span>
                    </td>
                    <td class="text-end">
                      <div class="spaces-table-title">{{ formatHours(item.hours_used) }}</div>
                      <span class="spaces-table-subtitle">{{ formatInteger(item.reservations_count) }} reservas</span>
                    </td>
                  </tr>
                  <tr v-if="(stats.by_requester || []).length === 0">
                    <td colspan="2">
                      <div class="spaces-empty-state">Sin uso registrado.</div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <section class="spaces-panel h-100">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Detalle</div>
                <h5 class="spaces-panel-title">Dependencia filtrada</h5>
              </div>
            </div>
            <div v-if="stats.selected_dependency" class="stats-detail-grid">
              <div class="stats-detail-row">
                <span>Dependencia</span>
                <strong>{{ stats.selected_dependency.name || "-" }}</strong>
              </div>
              <div class="stats-detail-row">
                <span>Tipo</span>
                <strong>{{ stats.selected_dependency.type_name || "-" }}</strong>
              </div>
              <div class="stats-detail-row">
                <span>Capacidad máxima</span>
                <strong>{{ formatInteger(stats.selected_dependency.capacity_max || 0) }}</strong>
              </div>
              <div class="stats-detail-row">
                <span>Reservas en rango</span>
                <strong>{{ formatInteger(stats.selected_dependency.total_reservations || 0) }}</strong>
              </div>
              <div class="stats-detail-row">
                <span>Horas utilizadas</span>
                <strong>{{ formatHours(stats.selected_dependency.hours_used || 0) }}</strong>
              </div>
              <div class="stats-detail-row">
                <span>Duración promedio</span>
                <strong>{{ formatHours(stats.selected_dependency.average_duration_hours || 0) }}</strong>
              </div>
              <div class="stats-detail-row">
                <span>Principal solicitante</span>
                <strong>{{ stats.selected_dependency.top_requester?.name || "-" }}</strong>
              </div>
            </div>
            <div v-else class="spaces-muted">
              Selecciona una dependencia para revisar su comportamiento específico dentro del período.
            </div>
          </section>
        </div>

        <div class="col-xl-6">
          <section class="spaces-panel h-100">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Lectura</div>
                <h5 class="spaces-panel-title">Resumen rápido</h5>
              </div>
            </div>
            <ul class="stats-insights mb-0">
              <li>
                <strong>{{ formatInteger(stats.summary.active_spaces) }}</strong>
                espacios muestran uso efectivo en el rango filtrado.
              </li>
              <li>
                Se registran
                <strong>{{ formatHours(stats.summary.total_usage_hours) }}</strong>
                de uso aprobado/finalizado.
              </li>
              <li>
                La duración promedio por reserva efectiva es de
                <strong>{{ formatHours(stats.summary.average_duration_hours) }}</strong>.
              </li>
              <li v-if="(stats.by_dependency || [])[0]">
                La dependencia más utilizada es
                <strong>{{ stats.by_dependency[0].label }}</strong>
                con
                <strong>{{ formatHours(stats.by_dependency[0].hours_used) }}</strong>.
              </li>
              <li v-if="(stats.by_requester || [])[0]">
                El principal solicitante es
                <strong>{{ stats.by_requester[0].label }}</strong>
                con
                <strong>{{ formatInteger(stats.by_requester[0].reservations_count) }}</strong>
                reservas.
              </li>
            </ul>
          </section>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.stats-filter-card,
.stats-summary-card {
  border: 1px solid #eff2f7;
  box-shadow: 0 10px 30px rgba(52, 58, 64, 0.04);
}

.stats-summary-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: #343a40;
  line-height: 1.1;
}

.stats-kpi-list {
  display: grid;
  gap: 0.9rem;
}

.stats-kpi-item,
.stats-detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #eff2f7;
}

.stats-kpi-item:last-child,
.stats-detail-row:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.stats-kpi-item span,
.stats-detail-row span {
  color: #74788d;
}

.stats-detail-grid {
  display: grid;
  gap: 0.85rem;
}

.stats-insights {
  margin: 0;
  padding-left: 1.1rem;
  color: #495057;
  display: grid;
  gap: 0.85rem;
}

.stats-insights strong {
  color: #343a40;
}
</style>
