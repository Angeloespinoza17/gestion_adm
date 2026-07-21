<script>
import axios from "axios";
import PmeHelpButton from "../help-button.vue";
import PmePagination from "../pagination.vue";
import PmeStatusBadge from "../status-badge.vue";
import {
  downloadExcelWorkbook,
  downloadPdfReport,
  formatCurrency,
  formatPmeDate,
  formatPmeError,
  humanizePmeStatus,
  normalizeOptions,
  normalizePagination,
  showPmeError,
  showPmeSuccess,
} from "../module-utils";

const incomeForm = () => ({
  id: null,
  pme_plan_id: null,
  school_year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
  income_type: "sep_regular",
  estimated_amount: 0,
  received_amount: 0,
  received_at: new Date().toISOString().slice(0, 10),
  bank_account: "",
  state: "registrado",
  observations: "",
  document: null,
});

const studentForm = () => ({
  id: null,
  student_profile_id: null,
  course_section_id: null,
  academic_year_id: null,
  classification: "prioritaria",
  loaded_at: new Date().toISOString().slice(0, 10),
  source: "Carga manual",
  state: "vigente",
  observations: "",
  document: null,
});

export default {
  components: { PmeHelpButton, PmePagination, PmeStatusBadge },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
    section: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      items: [],
      pagination: normalizePagination(),
      incomeForm: incomeForm(),
      studentForm: studentForm(),
      importFile: null,
      importSource: "Carga masiva",
      importAcademicYearId: null,
      editing: false,
      filters: {
        school_year: null,
        month: null,
        state: null,
        academic_year_id: null,
        course_section_id: null,
        classification: null,
        search: "",
      },
    };
  },
  computed: {
    planOptions() {
      return normalizeOptions(this.catalogs.plans, true);
    },
    yearOptions() {
      return normalizeOptions(this.catalogs.academic_years, true);
    },
    incomeYearOptions() {
      const years = [...new Set([
        ...(this.catalogs.academic_years || []).map((item) => Number(item.year || item.school_year || item.name)),
        ...(this.catalogs.plans || []).map((item) => Number(item.school_year || item.year)),
      ].filter(Number.isFinite))].sort((a, b) => b - a);
      return [{ value: null, label: "Todos" }, ...years.map((year) => ({ value: year, label: String(year) }))];
    },
    incomeTypeOptions() {
      return normalizeOptions(this.catalogs.options?.income_types || [], false);
    },
    incomeStateOptions() {
      return normalizeOptions(this.catalogs.options?.income_states || [], true);
    },
    monthOptions() {
      return normalizeOptions(this.catalogs.options?.months || [], true);
    },
    studentClassificationOptions() {
      return normalizeOptions(this.catalogs.options?.student_classifications || [], false);
    },
    studentStateOptions() {
      return normalizeOptions(this.catalogs.options?.student_classification_states || [], true);
    },
    courseOptions() {
      return normalizeOptions(this.catalogs.courses || [], true);
    },
    studentOptions() {
      return normalizeOptions((this.catalogs.students || []).map((item) => ({ value: item.id, label: `${item.name}${item.course ? ` · ${item.course}` : ""}` })), true, "Selecciona estudiante");
    },
    summary() {
      if (this.section === "ingresos") {
        const estimated = this.items.reduce((sum, item) => sum + Number(item.estimated_amount || 0), 0);
        const received = this.items.reduce((sum, item) => sum + Number(item.received_amount || 0), 0);
        return [
          { label: "Registros", value: this.items.length, icon: "bx-receipt" },
          { label: "Total estimado", value: formatCurrency(estimated), icon: "bx-calculator" },
          { label: "Total recibido", value: formatCurrency(received), icon: "bx-wallet", tone: received >= estimated && estimated > 0 ? "success" : "primary" },
          { label: "Diferencia", value: formatCurrency(received - estimated), icon: "bx-transfer", tone: received - estimated < 0 ? "warning" : "success" },
        ];
      }

      return [
        { label: "Clasificaciones", value: this.items.length, icon: "bx-group" },
        { label: "Prioritarias", value: this.items.filter((item) => item.classification === "prioritaria").length, icon: "bx-user-pin", tone: "primary" },
        { label: "Preferentes", value: this.items.filter((item) => item.classification === "preferente").length, icon: "bx-user-check", tone: "success" },
        { label: "Pendientes", value: this.items.filter((item) => String(item.state).includes("pendiente")).length, icon: "bx-time-five", tone: "warning" },
      ];
    },
  },
  watch: {
    section: {
      immediate: true,
      handler() {
        this.loadItems();
        this.startCreate();
      },
    },
  },
  methods: {
    formatCurrency,
    formatPmeDate,
    humanizePmeStatus,
    startCreate() {
      this.editing = false;
      if (this.section === "ingresos") {
        this.incomeForm = incomeForm();
        this.incomeForm.pme_plan_id = this.catalogs.active_plan_id || null;
      } else {
        this.studentForm = studentForm();
        this.studentForm.academic_year_id = this.catalogs.active_academic_year_id || null;
        this.importAcademicYearId = this.catalogs.active_academic_year_id || null;
      }
    },
    async loadItems(page = 1) {
      const requestedPage = Number.isInteger(page) ? page : 1;
      this.loading = true;
      this.error = null;
      try {
        if (this.section === "ingresos") {
          const response = await axios.get("/api/pme-sep/incomes", { params: { ...this.filters, page: requestedPage } });
          this.items = response.data.data || [];
          this.pagination = normalizePagination(response.data);
        } else {
          const response = await axios.get("/api/pme-sep/students", { params: { ...this.filters, page: requestedPage } });
          this.items = response.data.data || [];
          this.pagination = normalizePagination(response.data);
        }
      } catch (error) {
        this.error = formatPmeError(error, "No se pudieron cargar los datos del submódulo SEP.");
      } finally {
        this.loading = false;
      }
    },
    editIncome(item) {
      this.editing = true;
      this.incomeForm = {
        id: item.id,
        pme_plan_id: item.pme_plan_id,
        school_year: item.school_year,
        month: item.month,
        income_type: item.income_type,
        estimated_amount: item.estimated_amount,
        received_amount: item.received_amount,
        received_at: item.received_at,
        bank_account: item.bank_account || "",
        state: item.state,
        observations: item.observations || "",
        document: null,
      };
    },
    editStudent(item) {
      this.editing = true;
      this.studentForm = {
        id: item.id,
        student_profile_id: item.student_profile_id,
        course_section_id: item.course_section_id,
        academic_year_id: item.academic_year_id,
        classification: item.classification,
        loaded_at: item.loaded_at,
        source: item.source || "",
        state: item.state,
        observations: item.observations || "",
        document: null,
      };
    },
    async saveIncome() {
      this.saving = true;
      try {
        const formData = new FormData();
        Object.entries(this.incomeForm).forEach(([key, value]) => {
          if (value !== null && value !== undefined && key !== "id") {
            formData.append(key, value);
          }
        });

        if (this.editing && this.incomeForm.id) {
          formData.append("_method", "PUT");
          await axios.post(`/api/pme-sep/incomes/${this.incomeForm.id}`, formData);
        } else {
          await axios.post("/api/pme-sep/incomes", formData);
        }
        showPmeSuccess("Ingreso SEP guardado correctamente.");
        this.startCreate();
        this.loadItems();
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar el ingreso SEP."));
      } finally {
        this.saving = false;
      }
    },
    async saveStudent() {
      this.saving = true;
      try {
        const formData = new FormData();
        Object.entries(this.studentForm).forEach(([key, value]) => {
          if (value !== null && value !== undefined && key !== "id") {
            formData.append(key, value);
          }
        });

        if (this.editing && this.studentForm.id) {
          formData.append("_method", "PUT");
          await axios.post(`/api/pme-sep/students/${this.studentForm.id}`, formData);
        } else {
          await axios.post("/api/pme-sep/students", formData);
        }
        showPmeSuccess("Clasificación SEP guardada correctamente.");
        this.startCreate();
        this.loadItems();
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar la clasificación SEP."));
      } finally {
        this.saving = false;
      }
    },
    async importStudents() {
      if (!this.importFile || !this.importAcademicYearId) {
        showPmeError("Debes seleccionar archivo y año académico para la carga masiva.");
        return;
      }

      this.saving = true;
      try {
        const formData = new FormData();
        formData.append("file", this.importFile);
        formData.append("academic_year_id", this.importAcademicYearId);
        formData.append("source", this.importSource);
        const response = await axios.post("/api/pme-sep/students/import", formData);
        showPmeSuccess(response.data.message || "Carga masiva procesada.");
        this.importFile = null;
        this.loadItems();
        this.$emit("refresh-catalogs");
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo procesar la carga masiva SEP."));
      } finally {
        this.saving = false;
      }
    },
    resetFilters() {
      this.filters = {
        school_year: null,
        month: null,
        state: null,
        academic_year_id: null,
        course_section_id: null,
        classification: null,
        search: "",
      };
      this.loadItems(1);
    },
    exportCurrent(format) {
      const sections = [
        {
          title: this.section === "ingresos" ? "Ingresos SEP" : "Estudiantes SEP",
          headers: this.section === "ingresos"
            ? ["Año", "Mes", "Tipo", "Estimado", "Recibido", "Estado"]
            : ["Estudiante", "Curso", "Año", "Clasificación", "Estado", "Fuente"],
          rows: this.section === "ingresos"
            ? this.items.map((item) => [item.school_year, item.month, item.income_type, item.estimated_amount, item.received_amount, item.state])
            : this.items.map((item) => [item.student?.registered_name_resolved || item.student?.full_name, item.course_section?.display_name, item.academic_year?.year, item.classification, item.state, item.source]),
        },
      ];

      const fileName = this.section === "ingresos" ? "ingresos-sep" : "estudiantes-sep";
      if (format === "pdf") {
        downloadPdfReport(fileName, "Reporte PME / SEP", "Exportación rápida del submódulo", sections);
      } else {
        downloadExcelWorkbook(fileName, sections);
      }
    },
  },
};
</script>

<template>
  <div class="row g-3">
    <div class="col-xl-5">
      <BCard class="border-0 shadow-sm">
        <template #header>
          <div class="d-flex justify-content-between align-items-center gap-2">
            <div><div class="fw-semibold">{{ editing ? 'Editar' : 'Nuevo' }} {{ section === "ingresos" ? "ingreso SEP" : "registro SEP" }}</div><div class="small text-muted">{{ section === 'ingresos' ? 'Monto, fecha y respaldo' : 'Clasificación anual del estudiante' }}</div></div>
            <PmeHelpButton :title="section === 'ingresos' ? 'Ayuda: ingresos SEP' : 'Ayuda: estudiantes SEP'" :text="section === 'ingresos' ? 'Aquí se registran ingresos mensuales SEP, respaldo, cuenta bancaria, observaciones y estado.' : 'Aquí se registra la clasificación SEP por estudiante y año escolar, con carga manual o masiva.'" />
          </div>
        </template>

        <div v-if="section === 'ingresos'" class="row g-3">
          <div class="col-md-6"><label class="form-label">Plan</label><BFormSelect v-model="incomeForm.pme_plan_id" :options="planOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Año</label><BFormInput v-model="incomeForm.school_year" type="number" /></div>
          <div class="col-md-6"><label class="form-label">Mes</label><BFormSelect v-model="incomeForm.month" :options="monthOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Tipo ingreso</label><BFormSelect v-model="incomeForm.income_type" :options="incomeTypeOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Estimado</label><BFormInput v-model="incomeForm.estimated_amount" type="number" /></div>
          <div class="col-md-6"><label class="form-label">Recibido</label><BFormInput v-model="incomeForm.received_amount" type="number" /></div>
          <div class="col-md-6"><label class="form-label">Fecha recepción</label><BFormInput v-model="incomeForm.received_at" type="date" /></div>
          <div class="col-md-6"><label class="form-label">Estado</label><BFormSelect v-model="incomeForm.state" :options="incomeStateOptions.filter((item) => item.value).map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-12"><label class="form-label">Cuenta bancaria</label><BFormInput v-model="incomeForm.bank_account" /></div>
          <div class="col-12"><label class="form-label">Documento respaldo</label><BFormFile @change="incomeForm.document = $event.target.files[0]" /></div>
          <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="incomeForm.observations" rows="3" /></div>
          <div class="col-12 d-flex gap-2"><BButton variant="primary" :disabled="saving" @click="saveIncome"><span v-if="saving" class="spinner-border spinner-border-sm"></span>{{ editing ? 'Actualizar ingreso' : 'Guardar ingreso' }}</BButton><BButton variant="outline-secondary" :disabled="saving" @click="startCreate">Cancelar</BButton></div>
        </div>

        <div v-else class="row g-3">
          <div class="col-md-6"><label class="form-label">Estudiante</label><BFormSelect v-model="studentForm.student_profile_id" :options="studentOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Curso</label><BFormSelect v-model="studentForm.course_section_id" :options="courseOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Año</label><BFormSelect v-model="studentForm.academic_year_id" :options="yearOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Clasificación</label><BFormSelect v-model="studentForm.classification" :options="studentClassificationOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Fecha carga</label><BFormInput v-model="studentForm.loaded_at" type="date" /></div>
          <div class="col-md-6"><label class="form-label">Estado</label><BFormSelect v-model="studentForm.state" :options="studentStateOptions.filter((item) => item.value).map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-12"><label class="form-label">Fuente</label><BFormInput v-model="studentForm.source" /></div>
          <div class="col-12"><label class="form-label">Documento respaldo</label><BFormFile @change="studentForm.document = $event.target.files[0]" /></div>
          <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="studentForm.observations" rows="2" /></div>
          <div class="col-12 d-flex gap-2"><BButton variant="primary" :disabled="saving" @click="saveStudent"><span v-if="saving" class="spinner-border spinner-border-sm"></span>{{ editing ? 'Actualizar clasificación' : 'Guardar clasificación' }}</BButton><BButton variant="outline-secondary" :disabled="saving" @click="startCreate">Cancelar</BButton></div>
          <div class="col-12"><hr /></div>
          <div class="col-md-4"><label class="form-label">Año carga masiva</label><BFormSelect v-model="importAcademicYearId" :options="yearOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-4"><label class="form-label">Fuente</label><BFormInput v-model="importSource" /></div>
          <div class="col-md-4"><label class="form-label">Archivo CSV / XLSX</label><BFormFile @change="importFile = $event.target.files[0]" /></div>
          <div class="col-12 d-flex align-items-center justify-content-between gap-2 flex-wrap"><small class="text-muted"><i class="bx bx-info-circle me-1"></i>La primera fila debe contener los encabezados del archivo.</small><BButton variant="outline-primary" :disabled="saving" @click="importStudents"><i class="bx bx-upload"></i>Cargar archivo</BButton></div>
        </div>
      </BCard>
    </div>

    <div class="col-xl-7">
      <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
      <BCard class="border-0 shadow-sm">
        <template #header>
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div><div class="fw-semibold">{{ section === "ingresos" ? "Ingresos registrados" : "Clasificaciones SEP registradas" }}</div><div class="small text-muted">{{ items.length }} registros en la vista actual</div></div>
            <div class="d-flex gap-2">
              <PmeHelpButton :title="section === 'ingresos' ? 'Ayuda: tabla de ingresos' : 'Ayuda: tabla de estudiantes SEP'" :text="section === 'ingresos' ? 'La tabla muestra ingresos SEP, estimado, recibido y estado.' : 'La tabla muestra el historial anual SEP por estudiante, curso, clasificación, fuente y estado.'" />
              <BButton size="sm" variant="outline-success" :disabled="!items.length" @click="exportCurrent('excel')"><i class="bx bx-spreadsheet"></i>Excel</BButton>
              <BButton size="sm" variant="outline-danger" :disabled="!items.length" @click="exportCurrent('pdf')"><i class="bx bxs-file-pdf"></i>PDF</BButton>
            </div>
          </div>
        </template>

        <div class="sep-summary mb-3">
          <div v-for="metric in summary" :key="metric.label" :class="metric.tone ? `is-${metric.tone}` : ''"><i class="bx" :class="metric.icon"></i><span>{{ metric.label }}</span><strong>{{ metric.value }}</strong></div>
        </div>

        <div class="pme-filter-bar row g-2 mb-3">
          <div v-if="section === 'ingresos'" class="col-md-4"><label class="form-label">Año</label><BFormSelect v-model="filters.school_year" :options="incomeYearOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div v-if="section === 'ingresos'" class="col-md-4"><label class="form-label">Mes</label><BFormSelect v-model="filters.month" :options="monthOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div v-if="section === 'ingresos'" class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="filters.state" :options="incomeStateOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div v-if="section === 'estudiantes'" class="col-md-3"><label class="form-label">Año</label><BFormSelect v-model="filters.academic_year_id" :options="yearOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div v-if="section === 'estudiantes'" class="col-md-3"><label class="form-label">Curso</label><BFormSelect v-model="filters.course_section_id" :options="courseOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div v-if="section === 'estudiantes'" class="col-md-3"><label class="form-label">Clasificación</label><BFormSelect v-model="filters.classification" :options="studentClassificationOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div v-if="section === 'estudiantes'" class="col-md-3"><label class="form-label">Estudiante</label><BFormInput v-model="filters.search" placeholder="Nombre o RUT..." @keyup.enter="loadItems" /></div>
          <div class="col-12 d-flex gap-2 justify-content-end"><BButton variant="outline-secondary" size="sm" @click="resetFilters"><i class="bx bx-reset"></i>Limpiar</BButton><BButton variant="primary" size="sm" :disabled="loading" @click="loadItems(1)"><i class="bx bx-filter-alt"></i>Aplicar filtros</BButton></div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr v-if="section === 'ingresos'"><th>Año</th><th>Mes</th><th>Tipo</th><th>Estimado</th><th>Recibido</th><th>Estado</th><th></th></tr>
              <tr v-else><th>Estudiante</th><th>Curso</th><th>Año</th><th>Clasificación</th><th>Estado</th><th>Fuente</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-if="loading"><td :colspan="section === 'ingresos' ? 7 : 7" class="text-center text-muted">Cargando...</td></tr>
              <tr v-else-if="!items.length"><td :colspan="section === 'ingresos' ? 7 : 7" class="text-center text-muted">Sin registros.</td></tr>
              <tr v-for="item in items" :key="item.id">
                <template v-if="section === 'ingresos'">
                  <td>{{ item.school_year }}</td><td>{{ monthOptions.find((month) => Number(month.value) === Number(item.month))?.label || item.month }}</td><td>{{ humanizePmeStatus(item.income_type) }}</td><td>{{ formatCurrency(item.estimated_amount) }}</td><td><strong>{{ formatCurrency(item.received_amount) }}</strong></td><td><PmeStatusBadge :status="item.state" /></td>
                  <td class="text-end"><BButton size="sm" variant="outline-primary" @click="editIncome(item)"><i class="bx bx-edit-alt"></i>Editar</BButton></td>
                </template>
                <template v-else>
                  <td>{{ item.student?.registered_name_resolved || item.student?.full_name }}</td><td>{{ item.course_section?.display_name || '-' }}</td><td>{{ item.academic_year?.year }}</td><td><PmeStatusBadge :status="item.classification" /></td><td><PmeStatusBadge :status="item.state" /></td><td>{{ item.source || '-' }}</td>
                  <td class="text-end"><BButton size="sm" variant="outline-primary" @click="editStudent(item)"><i class="bx bx-edit-alt"></i>Editar</BButton></td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>
        <PmePagination :pagination="pagination" :loading="loading" @change="loadItems" />
      </BCard>
    </div>
  </div>
</template>

<style scoped>
.sep-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));overflow:hidden;border:1px solid var(--pme-border);border-radius:9px;background:#fff}.sep-summary>div{display:grid;grid-template-columns:auto 1fr;gap:.08rem .45rem;align-items:center;padding:.66rem;border-right:1px solid #e7ebf1}.sep-summary>div:last-child{border:0}.sep-summary i{grid-row:1/3;color:#6680b1;font-size:1rem}.sep-summary span{color:#7b8798;font-size:.56rem}.sep-summary strong{overflow:hidden;color:#273449;font-size:.75rem;text-overflow:ellipsis;white-space:nowrap}.sep-summary .is-success i,.sep-summary .is-success strong{color:#16866f}.sep-summary .is-warning i,.sep-summary .is-warning strong{color:#bd7010}@media(max-width:767px){.sep-summary{grid-template-columns:1fr 1fr}.sep-summary>div:nth-child(2){border-right:0}.sep-summary>div:nth-child(-n+2){border-bottom:1px solid #e7ebf1}}
</style>
