<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
import {
  basicApexOptions,
  extractChartLabels,
  extractChartTotals,
  formatInfirmaryDate,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  humanizeInfirmaryStatus,
  statusVariant,
} from "../../components/infirmary/module-utils";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
    InfirmaryStudentSearch,
  },
  data() {
    return {
      loading: false,
      error: null,
      dashboard: {
        metrics: {},
        alerts: {},
        charts: {
          attentions_by_day: { labels: [], series: [] },
          accidents_by_month: { labels: [], series: [] },
          medications_administered: [],
          frequent_treatments: [],
          referrals: [],
          accidents_by_place: [],
          attentions_by_course: [],
        },
        breakdowns: {
          accidents_by_course: [],
          accidents_by_dependency: [],
          attentions_by_category: [],
          attentions_by_hour: [],
        },
        recent: {
          attentions: [],
          accidents: [],
          medication_alerts: [],
        },
      },
    };
  },
  computed: {
    metricCards() {
      const metrics = this.dashboard.metrics || {};
      return [
        { label: "Atenciones hoy", value: metrics.attentions_today || 0, icon: "bx-plus-medical", variant: "primary" },
        { label: "Atenciones del mes", value: metrics.attentions_month || 0, icon: "bx-calendar-event", variant: "info" },
        { label: "Accidentes escolares", value: metrics.accidents_month || 0, icon: "bx-run", variant: "warning" },
        { label: "Medicamentos administrados", value: metrics.medications_administered_month || 0, icon: "bx-capsule", variant: "success" },
        { label: "Derivaciones", value: metrics.referrals_month || 0, icon: "bx-transfer-alt", variant: "secondary" },
        { label: "Llamados a apoderados", value: metrics.calls_month || 0, icon: "bx-phone-call", variant: "dark" },
        { label: "Medicamentos permanentes", value: metrics.students_with_permanent_medication || 0, icon: "bx-notepad", variant: "primary" },
        { label: "Stock crítico", value: metrics.critical_stock || 0, icon: "bx-error-circle", variant: "danger" },
        { label: "Próximos a vencer", value: metrics.expiring_medications || 0, icon: "bx-timer", variant: "warning" },
        { label: "Promedio de atención", value: `${metrics.average_attention_minutes || 0} min`, icon: "bx-time-five", variant: "info" },
      ];
    },
    alertCards() {
      const alerts = this.dashboard.alerts || {};
      return [
        { label: "Medicamentos vencidos", value: alerts.expired_medications || 0, status: "vencido" },
        { label: "Medicamentos por vencer", value: alerts.expiring_medications || 0, status: "proximo_a_vencer" },
        { label: "Stock crítico", value: alerts.critical_stock || 0, status: "stock_bajo" },
        { label: "Accidentes abiertos", value: alerts.open_accidents || 0, status: "abierto" },
        { label: "Atenciones abiertas", value: alerts.open_attentions || 0, status: "abierta" },
        { label: "Seguimientos pendientes", value: alerts.pending_follow_ups || 0, status: "pendiente" },
        { label: "Llamados pendientes", value: alerts.pending_calls || 0, status: "pendiente" },
        { label: "Autorizaciones por vencer", value: alerts.expiring_authorizations || 0, status: "proxima_a_vencer" },
      ];
    },
    attentionLineOptions() {
      return {
        ...basicApexOptions({
          categories: this.dashboard.charts?.attentions_by_day?.labels || [],
          colors: ["#3454d1"],
        }),
        chart: {
          ...basicApexOptions().chart,
          type: "line",
        },
        xaxis: {
          categories: (this.dashboard.charts?.attentions_by_day?.labels || []).map((label) =>
            formatInfirmaryDate(label)
          ),
        },
      };
    },
    accidentsMonthOptions() {
      return {
        ...basicApexOptions({
          categories: (this.dashboard.charts?.accidents_by_month?.labels || []).map((label) => label),
          colors: ["#f1b44c"],
        }),
      };
    },
    medicationsChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.medications_administered),
          horizontal: true,
          colors: ["#34c38f"],
        }),
      };
    },
    treatmentChartOptions() {
      return {
        labels: extractChartLabels(this.dashboard.charts?.frequent_treatments),
        legend: { position: "bottom" },
        dataLabels: { enabled: true },
        colors: ["#556ee6", "#34c38f", "#f1b44c", "#f46a6a", "#50a5f1", "#74788d", "#ff7f50", "#8e44ad"],
      };
    },
    referralChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.referrals),
          horizontal: true,
          colors: ["#50a5f1"],
        }),
      };
    },
    accidentsPlaceOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.accidents_by_place),
          colors: ["#f46a6a"],
        }),
      };
    },
    attentionsCourseOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.attentions_by_course),
          colors: ["#34c38f"],
        }),
      };
    },
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    formatInfirmaryDate,
    formatInfirmaryDateTime,
    humanizeInfirmaryStatus,
    statusVariant,
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/dashboard");
        this.dashboard = response.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el dashboard de enfermería.");
      } finally {
        this.loading = false;
      }
    },
    openHistory(student) {
      this.$router.push({ path: "/infirmary/history", query: { student_id: student.id } });
    },
    chartSeries(items, key = "total", name = "Total") {
      return [{ name, data: extractChartTotals(items, key) }];
    },
    lineSeries() {
      return [{ name: "Atenciones", data: this.dashboard.charts?.attentions_by_day?.series || [] }];
    },
    monthSeries() {
      return [{ name: "Accidentes", data: this.dashboard.charts?.accidents_by_month?.series || [] }];
    },
    donutSeries() {
      return extractChartTotals(this.dashboard.charts?.frequent_treatments);
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Dashboard de Enfermería</h4>
        <div class="text-muted">
          Panel clínico y operativo para trazabilidad de atenciones, medicamentos, accidentes y seguimiento.
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <InfirmaryHelpButton
          title="Ayuda: dashboard de enfermería"
          text="En esta pantalla se visualizan indicadores en tiempo real de atenciones, accidentes, medicamentos, seguimientos y alertas críticas del módulo de enfermería."
        />
        <BButton variant="outline-primary" @click="$router.push('/infirmary/attentions')">Nueva atención</BButton>
        <BButton variant="outline-secondary" @click="$router.push('/infirmary/inventory')">Inventario</BButton>
        <BButton variant="outline-danger" @click="$router.push('/infirmary/accidents')">Accidentes</BButton>
        <BButton variant="primary" @click="loadDashboard">Actualizar</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3 align-items-center">
        <div class="col-xl-8">
          <InfirmaryStudentSearch auto-navigate />
        </div>
        <div class="col-xl-4">
          <div class="text-muted small">
            El buscador global abre de inmediato la ficha médica completa del estudiante desde cualquier punto del módulo.
          </div>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading">
      <LoadingState message="Cargando dashboard clínico..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3 mb-3">
        <div v-for="card in metricCards" :key="card.label" class="col-sm-6 col-xl-3 col-xxl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="text-muted small">{{ card.label }}</div>
                <div class="display-6 fw-semibold">{{ card.value }}</div>
              </div>
              <div :class="`avatar-title rounded-circle bg-soft-${card.variant} text-${card.variant}`" style="width: 42px; height: 42px">
                <i :class="`bx ${card.icon} fs-4`"></i>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <BCard class="mb-3">
        <template #header>
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div class="fw-semibold">Alertas del dashboard</div>
            <InfirmaryHelpButton
              title="Ayuda: alertas del dashboard"
              text="Estas tarjetas permiten priorizar medicamentos vencidos, stock crítico, atenciones abiertas, seguimientos y autorizaciones próximas a vencer."
            />
          </div>
        </template>
        <div class="row g-3">
          <div v-for="item in alertCards" :key="item.label" class="col-md-6 col-xl-3">
            <div class="border rounded p-3 h-100">
              <div class="d-flex justify-content-between gap-2 align-items-center">
                <div class="fw-semibold">{{ item.label }}</div>
                <InfirmaryStatusBadge :status="item.status" />
              </div>
              <div class="display-6 fw-semibold mt-2">{{ item.value }}</div>
            </div>
          </div>
        </div>
      </BCard>

      <div class="row g-3 mb-3">
        <div class="col-xl-8">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Atenciones por día</div>
                <InfirmaryHelpButton
                  title="Ayuda: atenciones por día"
                  text="Este gráfico muestra la carga de trabajo diaria de enfermería durante las últimas dos semanas."
                />
              </div>
            </template>
            <apexchart type="line" height="320" :options="attentionLineOptions" :series="lineSeries()" />
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Accidentes por mes</div>
                <InfirmaryHelpButton
                  title="Ayuda: accidentes por mes"
                  text="Aquí se compara la evolución mensual de accidentes escolares registrados por enfermería."
                />
              </div>
            </template>
            <apexchart type="bar" height="320" :options="accidentsMonthOptions" :series="monthSeries()" />
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Medicamentos administrados</div>
                <InfirmaryHelpButton
                  title="Ayuda: medicamentos administrados"
                  text="Este ranking permite identificar los medicamentos más utilizados por el equipo de enfermería."
                />
              </div>
            </template>
            <apexchart
              type="bar"
              height="320"
              :options="medicationsChartOptions"
              :series="chartSeries(dashboard.charts?.medications_administered, 'total', 'Administraciones')"
            />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Tratamientos más frecuentes</div>
                <InfirmaryHelpButton
                  title="Ayuda: tratamientos frecuentes"
                  text="Este gráfico resume los procedimientos aplicados con mayor frecuencia en las atenciones de enfermería."
                />
              </div>
            </template>
            <apexchart type="donut" height="320" :options="treatmentChartOptions" :series="donutSeries()" />
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-4">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Derivaciones</div>
                <InfirmaryHelpButton
                  title="Ayuda: derivaciones"
                  text="Muestra la distribución de derivaciones realizadas desde enfermería según su destino."
                />
              </div>
            </template>
            <apexchart
              type="bar"
              height="320"
              :options="referralChartOptions"
              :series="chartSeries(dashboard.charts?.referrals, 'total', 'Derivaciones')"
            />
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Accidentes por lugar</div>
                <InfirmaryHelpButton
                  title="Ayuda: accidentes por lugar"
                  text="Permite detectar los lugares con mayor concentración de accidentes escolares."
                />
              </div>
            </template>
            <apexchart
              type="bar"
              height="320"
              :options="accidentsPlaceOptions"
              :series="chartSeries(dashboard.charts?.accidents_by_place, 'total', 'Accidentes')"
            />
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Atenciones por curso</div>
                <InfirmaryHelpButton
                  title="Ayuda: atenciones por curso"
                  text="Este gráfico muestra qué cursos concentran una mayor demanda de atenciones de enfermería."
                />
              </div>
            </template>
            <apexchart
              type="bar"
              height="320"
              :options="attentionsCourseOptions"
              :series="chartSeries(dashboard.charts?.attentions_by_course, 'total', 'Atenciones')"
            />
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Distribución operativa</div>
                <InfirmaryHelpButton
                  title="Ayuda: distribución operativa"
                  text="Estas tablas ayudan a entender en qué cursos, dependencias, categorías y horarios se concentra la atención clínica."
                />
              </div>
            </template>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="fw-semibold mb-2">Accidentes por curso</div>
                <ul class="list-group list-group-flush">
                  <li v-for="item in dashboard.breakdowns?.accidents_by_course" :key="`course-${item.label}`" class="list-group-item px-0 d-flex justify-content-between">
                    <span>{{ item.label }}</span>
                    <span class="fw-semibold">{{ item.total }}</span>
                  </li>
                </ul>
              </div>
              <div class="col-md-6">
                <div class="fw-semibold mb-2">Accidentes por dependencia</div>
                <ul class="list-group list-group-flush">
                  <li v-for="item in dashboard.breakdowns?.accidents_by_dependency" :key="`dependency-${item.label}`" class="list-group-item px-0 d-flex justify-content-between">
                    <span>{{ item.label }}</span>
                    <span class="fw-semibold">{{ item.total }}</span>
                  </li>
                </ul>
              </div>
              <div class="col-md-6">
                <div class="fw-semibold mb-2">Atenciones por categoría</div>
                <ul class="list-group list-group-flush">
                  <li v-for="item in dashboard.breakdowns?.attentions_by_category" :key="`category-${item.label}`" class="list-group-item px-0 d-flex justify-content-between">
                    <span>{{ humanizeInfirmaryStatus(item.label) }}</span>
                    <span class="fw-semibold">{{ item.total }}</span>
                  </li>
                </ul>
              </div>
              <div class="col-md-6">
                <div class="fw-semibold mb-2">Atenciones por hora</div>
                <ul class="list-group list-group-flush">
                  <li v-for="item in dashboard.breakdowns?.attentions_by_hour" :key="`hour-${item.label}`" class="list-group-item px-0 d-flex justify-content-between">
                    <span>{{ item.label }}</span>
                    <span class="fw-semibold">{{ item.total }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Alertas de medicamentos</div>
                <InfirmaryHelpButton
                  title="Ayuda: alertas de medicamentos"
                  text="Esta sección prioriza productos vencidos, próximos a vencer o con stock bajo para una gestión preventiva del botiquín."
                />
              </div>
            </template>
            <div v-if="!(dashboard.recent?.medication_alerts || []).length" class="text-muted">
              No hay alertas activas de medicamentos.
            </div>
            <div v-else class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Medicamento</th>
                    <th>Stock</th>
                    <th>Vence</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in dashboard.recent?.medication_alerts" :key="item.id">
                    <td>
                      <div class="fw-semibold">{{ item.commercial_name || item.name }}</div>
                      <div class="small text-muted">{{ item.name }}</div>
                    </td>
                    <td>{{ item.current_stock }} / {{ item.minimum_stock }}</td>
                    <td>{{ formatInfirmaryDate(item.expires_at) }}</td>
                    <td><InfirmaryStatusBadge :status="item.status" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Atenciones recientes</div>
                <InfirmaryHelpButton
                  title="Ayuda: atenciones recientes"
                  text="Lista las últimas atenciones registradas para revisión rápida y continuidad clínica."
                />
              </div>
            </template>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Estudiante</th>
                    <th>Categoría</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in dashboard.recent?.attentions" :key="item.id">
                    <td>{{ item.student?.first_name }} {{ item.student?.last_name }}</td>
                    <td>{{ humanizeInfirmaryStatus(item.attention_category) }}</td>
                    <td><InfirmaryStatusBadge :status="item.priority" /></td>
                    <td><InfirmaryStatusBadge :status="item.status" /></td>
                    <td>{{ formatInfirmaryDateTime(item.attended_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Accidentes recientes</div>
                <InfirmaryHelpButton
                  title="Ayuda: accidentes recientes"
                  text="Muestra los últimos accidentes escolares registrados y su estado de seguimiento."
                />
              </div>
            </template>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Estudiante</th>
                    <th>Tipo</th>
                    <th>Severidad</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in dashboard.recent?.accidents" :key="item.id">
                    <td>{{ item.student?.first_name }} {{ item.student?.last_name }}</td>
                    <td>{{ item.accident_type }}</td>
                    <td><InfirmaryStatusBadge :status="item.severity" /></td>
                    <td><InfirmaryStatusBadge :status="item.case_status" /></td>
                    <td>{{ formatInfirmaryDateTime(item.occurred_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>
    </template>
  </Layout>
</template>
