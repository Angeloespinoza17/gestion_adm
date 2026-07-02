<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
import {
  basicApexOptions,
  downloadExcelWorkbook,
  downloadPdfReport,
  extractChartLabels,
  extractChartTotals,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  humanizeInfirmaryStatus,
  normalizeOptions,
  printInfirmaryHtml,
} from "../../components/infirmary/module-utils";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryHelpButton,
    InfirmaryStudentSearch,
  },
  data() {
    return {
      loading: false,
      error: null,
      selectedStudentLabel: "",
      catalogs: {
        report_period_options: [],
        courses: [],
        dependencies: [],
        medications: [],
        referral_options: [],
        attention_categories: [],
        users: [],
        capabilities: {},
      },
      filters: {
        period: "mensual",
        from: "",
        to: "",
        course_section_id: null,
        student_profile_id: null,
        accident_type: "",
        medication_id: null,
        referral_type: null,
        professional_id: null,
        dependency_id: null,
        attention_category: null,
      },
      report: {
        date_range: {},
        summary: {},
        attentions_by_course: [],
        attentions_by_category: [],
        accidents_by_type: [],
        accidents_by_dependency: [],
        medications_by_name: [],
        referrals_by_type: [],
        calls_by_result: [],
        professionals: [],
        detail_rows: {
          attentions: [],
          accidents: [],
          administrations: [],
          calls: [],
        },
      },
    };
  },
  computed: {
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    periodOptions() {
      return normalizeOptions(this.catalogs.report_period_options);
    },
    courseOptions() {
      return normalizeOptions(this.catalogs.courses, true);
    },
    medicationOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.medications || []).map((item) => ({
          value: item.id,
          text: item.commercial_name || item.name,
        }))
      );
    },
    referralOptions() {
      return normalizeOptions(this.catalogs.referral_options, true);
    },
    dependencyOptions() {
      return normalizeOptions(this.catalogs.dependencies, true);
    },
    categoryOptions() {
      return normalizeOptions(this.catalogs.attention_categories, true);
    },
    userOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.users || []).map((item) => ({
          value: item.id,
          text: item.name,
        }))
      );
    },
    chartOptions() {
      return {
        attentionsByCourse: {
          ...basicApexOptions({
            categories: extractChartLabels(this.report.attentions_by_course),
            colors: ["#556ee6"],
          }),
        },
        attentionsByCategory: {
          labels: extractChartLabels(this.report.attentions_by_category),
          legend: { position: "bottom" },
          colors: ["#34c38f", "#556ee6", "#f1b44c", "#f46a6a", "#50a5f1", "#74788d", "#8e44ad", "#ff7f50"],
        },
        accidentsByType: {
          ...basicApexOptions({
            categories: extractChartLabels(this.report.accidents_by_type),
            colors: ["#f46a6a"],
          }),
        },
        medicationsByName: {
          ...basicApexOptions({
            categories: extractChartLabels(this.report.medications_by_name),
            horizontal: true,
            colors: ["#34c38f"],
          }),
        },
      };
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadReports();
  },
  methods: {
    formatInfirmaryDateTime,
    humanizeInfirmaryStatus,
    async loadCatalogs() {
      const response = await axios.get("/api/infirmary/catalogs");
      this.catalogs = response.data;
    },
    selectStudent(student) {
      this.filters.student_profile_id = student.id;
      this.selectedStudentLabel = student.full_name;
    },
    async loadReports() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/reports", {
          params: this.filters,
        });
        this.report = response.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar los reportes.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        period: "mensual",
        from: "",
        to: "",
        course_section_id: null,
        student_profile_id: null,
        accident_type: "",
        medication_id: null,
        referral_type: null,
        professional_id: null,
        dependency_id: null,
        attention_category: null,
      };
      this.selectedStudentLabel = "";
      this.loadReports();
    },
    exportExcel() {
      downloadExcelWorkbook(`reporte_enfermeria_${this.report.date_range?.from}_${this.report.date_range?.to}`, [
        {
          title: "Resumen",
          headers: ["Indicador", "Total"],
          rows: [
            ["Atenciones", this.report.summary.attentions_total || 0],
            ["Accidentes", this.report.summary.accidents_total || 0],
            ["Medicamentos administrados", this.report.summary.medications_administered_total || 0],
            ["Derivaciones", this.report.summary.referrals_total || 0],
            ["Llamados", this.report.summary.calls_total || 0],
            ["Promedio de atención (min)", this.report.summary.average_attention_minutes || 0],
          ],
        },
        {
          title: "Atenciones por curso",
          headers: ["Curso", "Total"],
          rows: (this.report.attentions_by_course || []).map((item) => [item.label, item.total]),
        },
        {
          title: "Accidentes por tipo",
          headers: ["Tipo", "Total"],
          rows: (this.report.accidents_by_type || []).map((item) => [item.label, item.total]),
        },
        {
          title: "Medicamentos administrados",
          headers: ["Medicamento", "Total"],
          rows: (this.report.medications_by_name || []).map((item) => [item.label, item.total]),
        },
      ]);
    },
    exportPdf() {
      downloadPdfReport(
        `reporte_enfermeria_${this.report.date_range?.from}_${this.report.date_range?.to}`,
        "Reporte de Enfermería Escolar",
        `Periodo ${this.report.date_range?.from || "-"} al ${this.report.date_range?.to || "-"}`,
        [
          {
            title: "Resumen",
            headers: ["Indicador", "Total"],
            rows: [
              ["Atenciones", this.report.summary.attentions_total || 0],
              ["Accidentes", this.report.summary.accidents_total || 0],
              ["Medicamentos administrados", this.report.summary.medications_administered_total || 0],
              ["Derivaciones", this.report.summary.referrals_total || 0],
              ["Llamados", this.report.summary.calls_total || 0],
            ],
          },
          {
            title: "Atenciones por categoría",
            headers: ["Categoría", "Total"],
            rows: (this.report.attentions_by_category || []).map((item) => [humanizeInfirmaryStatus(item.label), item.total]),
          },
          {
            title: "Accidentes por dependencia",
            headers: ["Dependencia", "Total"],
            rows: (this.report.accidents_by_dependency || []).map((item) => [item.label, item.total]),
          },
        ]
      );
    },
    printReport() {
      printInfirmaryHtml(
        "Reporte de Enfermería",
        `
          <h1>Reporte de Enfermería Escolar</h1>
          <div class="muted">Periodo ${this.report.date_range?.from || "-"} al ${this.report.date_range?.to || "-"}</div>
          <h3>Resumen</h3>
          <table>
            <tr><th>Indicador</th><th>Total</th></tr>
            <tr><td>Atenciones</td><td>${this.report.summary.attentions_total || 0}</td></tr>
            <tr><td>Accidentes</td><td>${this.report.summary.accidents_total || 0}</td></tr>
            <tr><td>Medicamentos administrados</td><td>${this.report.summary.medications_administered_total || 0}</td></tr>
            <tr><td>Derivaciones</td><td>${this.report.summary.referrals_total || 0}</td></tr>
            <tr><td>Llamados</td><td>${this.report.summary.calls_total || 0}</td></tr>
          </table>
          <h3>Atenciones por curso</h3>
          <table>
            <tr><th>Curso</th><th>Total</th></tr>
            ${(this.report.attentions_by_course || []).map((item) => `<tr><td>${item.label}</td><td>${item.total}</td></tr>`).join("")}
          </table>
        `
      );
    },
    barSeries(items, name) {
      return [{ name, data: extractChartTotals(items) }];
    },
    donutSeries(items) {
      return extractChartTotals(items);
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Reportes de Enfermería</h4>
        <div class="text-muted">
          Reportes diarios, semanales, mensuales, semestrales y anuales por curso, estudiante, dependencia y tipo de atención.
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <InfirmaryHelpButton
          title="Ayuda: reportes de enfermería"
          text="Esta pantalla consolida estadísticas del módulo de enfermería y permite exportar la información a Excel, PDF o impresión."
        />
        <BButton variant="secondary" @click="loadReports">Actualizar</BButton>
        <BButton v-if="canExport" variant="outline-success" @click="exportExcel">Excel</BButton>
        <BButton v-if="canExport" variant="outline-danger" @click="exportPdf">PDF</BButton>
        <BButton variant="primary" @click="printReport">Imprimir</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div class="fw-semibold">Filtros del reporte</div>
          <InfirmaryHelpButton
            title="Ayuda: filtros del reporte"
            text="Puedes generar reportes por período o rango libre, además de filtrar por estudiante, curso, medicamento, derivación, dependencia y profesional."
          />
        </div>
      </template>

      <div class="row g-3">
        <div class="col-xl-4">
          <label class="form-label">Buscador global</label>
          <InfirmaryStudentSearch button-label="Seleccionar" @selected="selectStudent" />
          <div v-if="selectedStudentLabel" class="small text-muted mt-2">Seleccionada: {{ selectedStudentLabel }}</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">Periodo</label>
          <BFormSelect v-model="filters.period" :options="periodOptions" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.from" type="date" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.to" type="date" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Curso</label>
          <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Medicamento</label>
          <BFormSelect v-model="filters.medication_id" :options="medicationOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Derivación</label>
          <BFormSelect v-model="filters.referral_type" :options="referralOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Dependencia</label>
          <BFormSelect v-model="filters.dependency_id" :options="dependencyOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo de atención</label>
          <BFormSelect v-model="filters.attention_category" :options="categoryOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Profesional</label>
          <BFormSelect v-model="filters.professional_id" :options="userOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo de accidente</label>
          <BFormInput v-model="filters.accident_type" placeholder="Caída, golpe..." />
        </div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="primary" @click="loadReports">Generar reporte</BButton>
          <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading">
      <LoadingState message="Generando reporte de enfermería..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3 mb-3">
        <div class="col-md-4 col-xl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Atenciones</div>
            <div class="display-6 fw-semibold">{{ report.summary.attentions_total || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-4 col-xl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Accidentes</div>
            <div class="display-6 fw-semibold">{{ report.summary.accidents_total || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-4 col-xl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Administraciones</div>
            <div class="display-6 fw-semibold">{{ report.summary.medications_administered_total || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-4 col-xl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Derivaciones</div>
            <div class="display-6 fw-semibold">{{ report.summary.referrals_total || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-4 col-xl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Llamados</div>
            <div class="display-6 fw-semibold">{{ report.summary.calls_total || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-4 col-xl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Promedio atención</div>
            <div class="display-6 fw-semibold">{{ report.summary.average_attention_minutes || 0 }}</div>
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Atenciones por curso</div>
                <InfirmaryHelpButton
                  title="Ayuda: atenciones por curso"
                  text="Este gráfico permite comparar la carga asistencial entre cursos durante el período seleccionado."
                />
              </div>
            </template>
            <apexchart type="bar" height="320" :options="chartOptions.attentionsByCourse" :series="barSeries(report.attentions_by_course, 'Atenciones')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Atenciones por categoría</div>
                <InfirmaryHelpButton
                  title="Ayuda: atenciones por categoría"
                  text="Aquí se distribuyen las atenciones según tipo clínico o motivo de consulta."
                />
              </div>
            </template>
            <apexchart type="donut" height="320" :options="chartOptions.attentionsByCategory" :series="donutSeries(report.attentions_by_category)" />
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Accidentes por tipo</div>
                <InfirmaryHelpButton
                  title="Ayuda: accidentes por tipo"
                  text="Este gráfico resume los tipos de accidentes escolares ocurridos durante el período analizado."
                />
              </div>
            </template>
            <apexchart type="bar" height="320" :options="chartOptions.accidentsByType" :series="barSeries(report.accidents_by_type, 'Accidentes')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Medicamentos administrados</div>
                <InfirmaryHelpButton
                  title="Ayuda: medicamentos administrados"
                  text="Ranking de medicamentos administrados durante el período seleccionado."
                />
              </div>
            </template>
            <apexchart type="bar" height="320" :options="chartOptions.medicationsByName" :series="barSeries(report.medications_by_name, 'Administraciones')" />
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-4">
          <BCard class="h-100">
            <h5 class="mb-3">Accidentes por dependencia</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Dependencia</th>
                    <th class="text-end">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in report.accidents_by_dependency" :key="item.label">
                    <td>{{ item.label }}</td>
                    <td class="text-end">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="h-100">
            <h5 class="mb-3">Derivaciones</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Destino</th>
                    <th class="text-end">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in report.referrals_by_type" :key="item.label">
                    <td>{{ humanizeInfirmaryStatus(item.label) }}</td>
                    <td class="text-end">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="h-100">
            <h5 class="mb-3">Llamados por resultado</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Resultado</th>
                    <th class="text-end">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in report.calls_by_result" :key="item.label">
                    <td>{{ humanizeInfirmaryStatus(item.label) }}</td>
                    <td class="text-end">{{ item.total }}</td>
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
            <h5 class="mb-3">Detalle de atenciones</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Estudiante</th>
                    <th>Categoría</th>
                    <th>Profesional</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in report.detail_rows?.attentions" :key="item.id">
                    <td>{{ formatInfirmaryDateTime(item.attended_at) }}</td>
                    <td>{{ item.student?.first_name }} {{ item.student?.last_name }}</td>
                    <td>{{ humanizeInfirmaryStatus(item.attention_category) }}</td>
                    <td>{{ item.attended_by?.name || "-" }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="h-100">
            <h5 class="mb-3">Detalle de accidentes</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Estudiante</th>
                    <th>Tipo</th>
                    <th>Dependencia</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in report.detail_rows?.accidents" :key="item.id">
                    <td>{{ formatInfirmaryDateTime(item.occurred_at) }}</td>
                    <td>{{ item.student?.first_name }} {{ item.student?.last_name }}</td>
                    <td>{{ item.accident_type }}</td>
                    <td>{{ item.dependency?.name || "-" }}</td>
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

