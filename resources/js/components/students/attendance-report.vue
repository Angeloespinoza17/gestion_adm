<script>
import axios from "axios";
import LoadingState from "../ui/loading-state.vue";
import { downloadStudentReportExcel, downloadStudentReportPdf } from "./report-export";

const emptyData = () => ({
  meta: { academic_year: null, date_range: {}, capabilities: {} },
  catalogs: { academic_years: [], courses: [] },
  summary: {},
  daily: [],
  calendar: [],
  courses: [],
  students: [],
  alerts: [],
  projections: { settings: {}, scenarios: [], monthly: [] },
  imports: [],
});

export default {
  components: { LoadingState },
  props: {
    initialYearId: { type: [Number, String], default: null },
    initialCourseId: { type: [Number, String], default: null },
  },
  data() {
    return {
      loading: true,
      error: null,
      report: emptyData(),
      filters: {
        academic_year_id: this.initialYearId || null,
        course_section_id: this.initialCourseId || null,
        month: "",
      },
      exporting: "",
      importOpen: false,
      importStep: "upload",
      importFile: null,
      importCourseId: this.initialCourseId || null,
      importPreview: null,
      matchSelections: {},
      conflictStrategy: "reject",
      importLoading: false,
      importError: null,
      importSuccess: null,
      studentDetail: null,
      studentDetailLoading: false,
      studentGroup: null,
      studentList: { data: [], meta: {} },
      studentListLoading: false,
      studentListError: null,
      studentListFilters: {
        risk: "",
        search: "",
      },
      dayDetail: null,
      dayDetailLoading: false,
      alertGroup: null,
      alertDetail: { data: [], meta: {} },
      alertDetailLoading: false,
      alertDetailError: null,
      alertDetailFilters: {
        severity: "",
        type: "",
        search: "",
      },
      followupAlert: null,
      followupLoading: false,
      followupForm: {
        action_type: "contact_guardian",
        action_date: new Date().toISOString().slice(0, 10),
        status: "completed",
        notes: "",
        next_action_date: "",
      },
      settingsOpen: false,
      settingsLoading: false,
      settingsForm: {},
      dayMutationId: null,
      recordMutationId: null,
    };
  },
  computed: {
    capabilities() {
      return this.report.meta?.capabilities || {};
    },
    attendanceSeries() {
      return [{ name: "Asistencia", data: (this.report.daily || []).map((day) => Number(day.attendance_rate || 0)) }];
    },
    attendanceOptions() {
      return {
        chart: { toolbar: { show: false }, animations: { enabled: false }, fontFamily: "Inter, var(--bs-font-sans-serif)" },
        colors: ["#28764d"],
        stroke: { width: 2.5, curve: "straight" },
        markers: { size: 3, hover: { size: 5 } },
        dataLabels: { enabled: false },
        grid: { borderColor: "#e7ebf0", strokeDashArray: 3 },
        annotations: { yaxis: [{ y: 85, borderColor: "#dc9b19", strokeDashArray: 4, label: { text: "Umbral 85%", style: { background: "#fff4dc", color: "#805b10" } } }] },
        xaxis: { categories: (this.report.daily || []).map((day) => this.shortDate(day.date)), labels: { rotate: -40, hideOverlappingLabels: true } },
        yaxis: { min: 0, max: 100, tickAmount: 5, labels: { formatter: (value) => `${Math.round(value)}%` } },
        tooltip: { y: { formatter: (value) => `${Number(value).toFixed(2)}%` } },
      };
    },
    importRows() {
      return this.importPreview?.preview?.students || [];
    },
    unresolvedRows() {
      return this.importRows.filter((row) => !Number(this.matchSelections[row.row] || row.matched_student_id));
    },
    selectedYear() {
      return (this.report.catalogs.academic_years || []).find((year) => Number(year.id) === Number(this.filters.academic_year_id));
    },
    importCourses() {
      return this.report.catalogs.courses || [];
    },
    alertTypeOptions() {
      return this.alertGroup?.types || [];
    },
  },
  mounted() {
    this.loadReport();
  },
  methods: {
    async loadReport() {
      this.loading = true;
      this.error = null;
      try {
        const params = Object.fromEntries(Object.entries(this.filters).filter(([, value]) => value !== null && value !== ""));
        const { data } = await axios.get("/api/students/attendance/dashboard", { params });
        this.report = data;
        if (!this.filters.academic_year_id) this.filters.academic_year_id = data.meta?.academic_year?.id || null;
        this.settingsForm = { ...(data.projections?.settings || {}) };
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo cargar el reporte de asistencia.");
      } finally {
        this.loading = false;
      }
    },
    async changeYear() {
      this.filters.course_section_id = null;
      this.importCourseId = null;
      await this.loadReport();
    },
    openImport() {
      this.importOpen = true;
      this.importStep = "upload";
      this.importPreview = null;
      this.importError = null;
      this.importSuccess = null;
      this.importFile = null;
      this.matchSelections = {};
      this.conflictStrategy = "reject";
    },
    closeImport() {
      if (this.importLoading) return;
      this.importOpen = false;
    },
    handleFile(event) {
      this.importFile = event.target.files?.[0] || null;
      this.importError = null;
    },
    async previewImport() {
      if (!this.importCourseId || !this.importFile) {
        this.importError = "Selecciona el curso de destino y el PDF mensual.";
        return;
      }
      this.importLoading = true;
      this.importError = null;
      try {
        const form = new FormData();
        form.append("course_section_id", this.importCourseId);
        form.append("file", this.importFile);
        const { data } = await axios.post("/api/students/attendance/imports/preview", form, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.importPreview = data;
        this.matchSelections = Object.fromEntries((data.preview?.students || []).map((row) => [row.row, row.matched_student_id || ""]));
        if (data.status === "completed") {
          this.importStep = "completed";
          this.importSuccess = "Este archivo ya había sido importado para el curso seleccionado. No se duplicaron registros.";
        } else {
          this.importStep = "preview";
        }
      } catch (error) {
        this.importError = this.errorMessage(error, "No se pudo analizar el PDF.");
      } finally {
        this.importLoading = false;
      }
    },
    async confirmImport() {
      if (this.unresolvedRows.length) {
        this.importError = `Faltan ${this.unresolvedRows.length} filas por asociar.`;
        return;
      }
      this.importLoading = true;
      this.importError = null;
      try {
        const studentMatches = this.importRows.map((row) => ({
          row: row.row,
          student_profile_id: Number(this.matchSelections[row.row] || row.matched_student_id),
        }));
        const { data } = await axios.post(`/api/students/attendance/imports/${this.importPreview.id}/confirm`, {
          conflict_strategy: this.conflictStrategy,
          student_matches: studentMatches,
        });
        this.importPreview = data;
        this.importStep = "completed";
        this.importSuccess = `Importación confirmada: ${data.imported_records} registros escritos y ${data.conflict_records} conflictos detectados.`;
        await this.loadReport();
      } catch (error) {
        this.importError = this.errorMessage(error, "No se pudo confirmar la importación.");
      } finally {
        this.importLoading = false;
      }
    },
    async openStudent(student) {
      this.studentDetail = { student, summary: {}, months: [], absences: [], alerts: [] };
      this.studentDetailLoading = true;
      try {
        const { data } = await axios.get(`/api/students/attendance/students/${student.id}`, {
          params: { academic_year_id: this.filters.academic_year_id, course_section_id: student.course_id },
        });
        this.studentDetail = data;
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo abrir el detalle de la estudiante.");
        this.studentDetail = null;
      } finally {
        this.studentDetailLoading = false;
      }
    },
    async openStudentGroup(group) {
      this.studentGroup = { ...group };
      this.studentList = { data: [], meta: {} };
      this.studentListFilters = { risk: "", search: "" };
      await this.loadStudentList(1);
    },
    closeStudentGroup() {
      if (this.studentDetail) return;
      this.studentGroup = null;
      this.studentList = { data: [], meta: {} };
      this.studentListError = null;
    },
    async loadStudentList(page = 1) {
      if (!this.studentGroup) return;
      this.studentListLoading = true;
      this.studentListError = null;
      const groupKey = this.studentGroup.key;
      try {
        const params = {
          academic_year_id: this.filters.academic_year_id,
          course_section_id: this.studentGroup.course_id,
          month: this.filters.month || undefined,
          page,
          per_page: 30,
        };
        if (this.studentListFilters.risk) params.risk = this.studentListFilters.risk;
        if (this.studentListFilters.search) params.search = this.studentListFilters.search;
        const { data } = await axios.get("/api/students/attendance/students", { params });
        if (data.meta?.current_page > data.meta?.last_page && data.meta?.last_page > 0) {
          await this.loadStudentList(data.meta.last_page);
          return;
        }
        this.studentList = data;
        const groupIndex = (this.report.students || []).findIndex((group) => group.key === groupKey);
        if (!data.group) {
          if (groupIndex >= 0) this.report.students.splice(groupIndex, 1);
          this.closeStudentGroup();
          return;
        }
        this.studentGroup = data.group;
        if (groupIndex >= 0) this.report.students.splice(groupIndex, 1, data.group);
      } catch (error) {
        this.studentListError = this.errorMessage(error, "No se pudo cargar el seguimiento del curso.");
      } finally {
        this.studentListLoading = false;
      }
    },
    applyStudentListFilters() {
      this.loadStudentList(1);
    },
    clearStudentListFilters() {
      this.studentListFilters = { risk: "", search: "" };
      this.loadStudentList(1);
    },
    async confirmSchoolDay(day) {
      const dayId = day.school_day_id || day.id;
      const status = day.confirmation_status || day.status;
      if (!this.capabilities.can_edit || status !== "pending_confirmation" || !dayId) return;
      this.dayMutationId = dayId;
      try {
        await axios.patch(`/api/students/attendance/school-days/${dayId}`, { status: "confirmed" });
        await this.loadReport();
        if (this.dayDetail) await this.openDay({ school_day_id: dayId });
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo confirmar la jornada.");
      } finally {
        this.dayMutationId = null;
      }
    },
    async openDay(day) {
      if (!day.school_day_id) return;
      this.dayDetail = { day: { id: day.school_day_id, date: day.date }, students: [], alerts: [] };
      this.dayDetailLoading = true;
      try {
        const params = this.filters.course_section_id ? { course_section_id: this.filters.course_section_id } : {};
        const { data } = await axios.get(`/api/students/attendance/days/${day.school_day_id}`, { params });
        this.dayDetail = data;
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo abrir el detalle de la jornada.");
        this.dayDetail = null;
      } finally {
        this.dayDetailLoading = false;
      }
    },
    async updateDayRecord(record, status) {
      if (!this.capabilities.can_edit || !record.record_id || !this.dayDetail?.day?.id) return;
      this.recordMutationId = record.record_id;
      const dayId = this.dayDetail.day.id;
      try {
        await axios.patch(`/api/students/attendance/records/${record.record_id}`, {
          status,
          notes: "Corrección manual desde el detalle diario.",
        });
        await this.loadReport();
        await this.openDay({ school_day_id: dayId });
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo corregir el registro diario.");
      } finally {
        this.recordMutationId = null;
      }
    },
    async correctAbsence(absence) {
      if (!this.capabilities.can_edit || !absence.id || !this.studentDetail?.student) return;
      this.recordMutationId = absence.id;
      const selectedStudent = {
        id: this.studentDetail.student.id,
        course_id: this.studentDetail.student.course_id,
      };
      try {
        await axios.patch(`/api/students/attendance/records/${absence.id}`, {
          status: "present",
          notes: "Corrección manual desde el reporte de asistencia.",
        });
        await this.openStudent(selectedStudent);
        if (this.studentGroup) await this.loadStudentList(this.studentList.meta?.current_page || 1);
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo corregir el registro de asistencia.");
      } finally {
        this.recordMutationId = null;
      }
    },
    async updateAlert(alert, status) {
      try {
        await axios.patch(`/api/students/attendance/alerts/${alert.id}`, { status });
        if (status === "resolved") {
          this.report.summary.open_alerts = Math.max(0, Number(this.report.summary.open_alerts || 0) - 1);
          if (alert.severity === "critical") {
            this.report.summary.critical_alerts = Math.max(0, Number(this.report.summary.critical_alerts || 0) - 1);
          }
          const currentPage = Number(this.alertDetail.meta?.current_page || 1);
          await this.loadAlertDetails(currentPage);
        } else {
          alert.status = status;
        }
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo actualizar la alerta.");
      }
    },
    async openAlertGroup(group) {
      this.alertGroup = { ...group };
      this.alertDetail = { data: [], meta: {} };
      this.alertDetailFilters = { severity: "", type: "", search: "" };
      await this.loadAlertDetails(1);
    },
    closeAlertGroup() {
      if (this.followupAlert) return;
      this.alertGroup = null;
      this.alertDetail = { data: [], meta: {} };
      this.alertDetailError = null;
    },
    async loadAlertDetails(page = 1) {
      if (!this.alertGroup) return;
      this.alertDetailLoading = true;
      this.alertDetailError = null;
      const groupKey = this.alertGroup.key;
      try {
        const params = {
          academic_year_id: this.filters.academic_year_id,
          page,
          per_page: 30,
        };
        if (this.alertGroup.course_id) params.course_section_id = this.alertGroup.course_id;
        else params.unassigned = 1;
        if (this.alertDetailFilters.severity) params.severity = this.alertDetailFilters.severity;
        if (this.alertDetailFilters.type) params.type = this.alertDetailFilters.type;
        if (this.alertDetailFilters.search) params.search = this.alertDetailFilters.search;
        const { data } = await axios.get("/api/students/attendance/alerts", { params });
        if (data.meta?.current_page > data.meta?.last_page && data.meta?.last_page > 0) {
          await this.loadAlertDetails(data.meta.last_page);
          return;
        }
        this.alertDetail = data;
        const groupIndex = (this.report.alerts || []).findIndex((group) => group.key === groupKey);
        if (!data.group) {
          if (groupIndex >= 0) this.report.alerts.splice(groupIndex, 1);
          this.closeAlertGroup();
          return;
        }
        this.alertGroup = data.group;
        if (groupIndex >= 0) this.report.alerts.splice(groupIndex, 1, data.group);
      } catch (error) {
        this.alertDetailError = this.errorMessage(error, "No se pudieron cargar las alertas del curso.");
      } finally {
        this.alertDetailLoading = false;
      }
    },
    applyAlertFilters() {
      this.loadAlertDetails(1);
    },
    clearAlertFilters() {
      this.alertDetailFilters = { severity: "", type: "", search: "" };
      this.loadAlertDetails(1);
    },
    openFollowup(alert) {
      this.followupAlert = alert;
      this.followupForm = {
        action_type: "contact_guardian",
        action_date: new Date().toISOString().slice(0, 10),
        status: "completed",
        notes: "",
        next_action_date: "",
      };
    },
    async saveFollowup() {
      this.followupLoading = true;
      try {
        const payload = { ...this.followupForm };
        if (!payload.next_action_date) delete payload.next_action_date;
        await axios.post(`/api/students/attendance/alerts/${this.followupAlert.id}/followups`, payload);
        const alert = (this.alertDetail.data || []).find((item) => item.id === this.followupAlert.id);
        if (alert) {
          alert.followups_count = Number(alert.followups_count || 0) + 1;
          if (alert.status === "open") alert.status = "in_progress";
        }
        this.followupAlert = null;
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo registrar el seguimiento.");
      } finally {
        this.followupLoading = false;
      }
    },
    async saveSettings() {
      this.settingsLoading = true;
      try {
        await axios.put(`/api/students/attendance/projection-settings/${this.filters.academic_year_id}`, this.settingsForm);
        this.settingsOpen = false;
        await this.loadReport();
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudieron guardar los parámetros de proyección.");
      } finally {
        this.settingsLoading = false;
      }
    },
    exportReport(format) {
      this.exporting = format;
      const sections = this.exportSections();
      const fileName = `asistencia_${this.report.meta?.academic_year?.year || "periodo"}`;
      try {
        if (format === "excel") {
          downloadStudentReportExcel(fileName, sections);
        } else {
          downloadStudentReportPdf(
            fileName,
            "Reporte de asistencia escolar",
            `${this.formatDate(this.report.meta?.date_range?.from)} al ${this.formatDate(this.report.meta?.date_range?.to)}`,
            sections,
          );
        }
      } finally {
        this.exporting = "";
      }
    },
    exportSections() {
      const summary = this.report.summary || {};
      return [
        { title: "Resumen", rows: [
          ["Indicador", "Valor"],
          ["Estudiantes", summary.roster_students || 0],
          ["Días lectivos", summary.school_days || 0],
          ["Presentes", summary.present || 0],
          ["Ausentes", summary.absent || 0],
          ["Asistencia", `${Number(summary.attendance_rate || 0).toFixed(2)}%`],
          ["Promedio diario", summary.average_daily_attendance || 0],
        ] },
        { title: "Cursos", rows: [["Curso", "Estudiantes", "Días", "Presentes", "Ausentes", "Asistencia"], ...(this.report.courses || []).map((row) => [row.name, row.roster_students, row.school_days, row.present, row.absent, row.attendance_rate == null ? "Sin datos" : `${row.attendance_rate}%`])] },
        { title: "Seguimiento por curso", rows: [["Curso", "Matrícula", "Con asistencia", "Bajo 85%", "Sin datos", "Asistencia promedio"], ...(this.report.students || []).map((row) => [row.course, row.students, row.with_data, row.below_target, row.without_data, row.average_attendance == null ? "Sin datos" : `${row.average_attendance}%`])] },
        { title: "Alertas", rows: [["Curso", "Alertas activas", "Críticas", "Preventivas", "Estudiantes", "Tipos"], ...(this.report.alerts || []).map((row) => [row.course, row.total, row.critical, row.warning, row.students, (row.types || []).map((type) => `${this.alertTypeLabel(type.type)}: ${type.count}`).join(" · ")])] },
      ];
    },
    alertTypeLabel(type) {
      return ({
        low_attendance: "Asistencia bajo el umbral",
        consecutive_absences: "Ausencias consecutivas",
        monthly_drop: "Deterioro mensual",
        low_course_day: "Jornada de baja asistencia",
      })[type] || String(type || "Otra alerta").replaceAll("_", " ");
    },
    rateClass(rate) {
      if (rate == null) return "rate-empty";
      if (Number(rate) < 85) return "rate-critical";
      if (Number(rate) < 90) return "rate-warning";
      return "rate-ok";
    },
    formatRate(value) {
      return value == null ? "Sin datos" : `${Number(value).toFixed(2)}%`;
    },
    formatMoney(value) {
      return new Intl.NumberFormat("es-CL", { style: "currency", currency: this.report.projections?.settings?.currency || "CLP", maximumFractionDigits: 0 }).format(Number(value || 0));
    },
    formatDate(value) {
      if (!value) return "-";
      return new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "2-digit", year: "numeric", timeZone: "UTC" }).format(new Date(`${value}T00:00:00Z`));
    },
    shortDate(value) {
      if (!value) return "";
      return new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "short", timeZone: "UTC" }).format(new Date(`${value}T00:00:00Z`));
    },
    errorMessage(error, fallback) {
      const errors = error?.response?.data?.errors;
      return (errors && Object.values(errors)?.[0]?.[0]) || error?.response?.data?.message || fallback;
    },
  },
};
</script>

<template>
  <div class="attendance-module">
    <header class="attendance-toolbar">
      <div>
        <span class="attendance-kicker">Control académico y subvenciones</span>
        <h5>Asistencia escolar</h5>
        <p>Indicadores consolidados, riesgo de inasistencia y trazabilidad mensual.</p>
      </div>
      <div class="attendance-actions">
        <button v-if="capabilities.can_import" type="button" class="btn btn-primary" @click="openImport"><i class="bx bx-upload"></i>Importar PDF</button>
        <button type="button" class="btn btn-outline-success" :disabled="loading || !!exporting" @click="exportReport('excel')"><i class="bx bx-spreadsheet"></i>Excel</button>
        <button type="button" class="btn btn-danger" :disabled="loading || !!exporting" @click="exportReport('pdf')"><i class="bx bxs-file-pdf"></i>PDF</button>
      </div>
    </header>

    <section class="attendance-filters" aria-label="Filtros de asistencia">
      <label>Año académico<select v-model="filters.academic_year_id" class="form-select" @change="changeYear"><option v-for="year in report.catalogs.academic_years" :key="year.id" :value="year.id">{{ year.name }}{{ year.is_active ? " · Activo" : "" }}</option></select></label>
      <label>Curso<select v-model="filters.course_section_id" class="form-select"><option :value="null">Todos los cursos</option><option v-for="course in report.catalogs.courses" :key="course.id" :value="course.id">{{ course.display_name }}</option></select></label>
      <label>Mes<input v-model="filters.month" type="month" class="form-control" /></label>
      <button type="button" class="btn btn-primary filter-button" :disabled="loading" title="Aplicar filtros" @click="loadReport"><i class="bx bx-filter-alt"></i><span>Aplicar</span></button>
    </section>

    <div v-if="error" class="alert alert-danger attendance-alert"><i class="bx bx-error-circle"></i><span>{{ error }}</span><button type="button" class="btn btn-sm btn-outline-danger" @click="loadReport">Reintentar</button></div>
    <LoadingState v-if="loading" message="Consolidando asistencia de los cursos..." compact />

    <template v-else>
      <section class="attendance-kpis">
        <article><i class="bx bx-group"></i><span>Estudiantes</span><strong>{{ report.summary.roster_students || 0 }}</strong></article>
        <article><i class="bx bx-calendar-check"></i><span>Días lectivos</span><strong>{{ report.summary.school_days || 0 }}</strong></article>
        <article><i class="bx bx-check-circle"></i><span>Asistencia</span><strong :class="rateClass(report.summary.attendance_rate)">{{ formatRate(report.summary.attendance_rate) }}</strong></article>
        <article><i class="bx bx-user-check"></i><span>Promedio diario</span><strong>{{ report.summary.average_daily_attendance || 0 }}</strong></article>
        <article><i class="bx bx-time-five"></i><span>Días restantes</span><strong>{{ report.summary.remaining_school_days || 0 }}</strong></article>
        <article><i class="bx bx-trending-down"></i><span>Bajo 85%</span><strong :class="{ 'rate-critical': report.summary.students_below_target }">{{ report.summary.students_below_target || 0 }}</strong></article>
        <article><i class="bx bx-error"></i><span>Alertas abiertas</span><strong>{{ report.summary.open_alerts || 0 }}</strong></article>
        <article><i class="bx bx-shield-quarter"></i><span>Críticas</span><strong class="rate-critical">{{ report.summary.critical_alerts || 0 }}</strong></article>
      </section>

      <section class="attendance-grid attendance-grid-main">
        <article class="attendance-panel trend-panel">
          <div class="panel-title"><div><span>Evolución del periodo</span><h6>Asistencia diaria</h6></div><strong>{{ formatRate(report.summary.attendance_rate) }}</strong></div>
          <apexchart type="line" height="300" :options="attendanceOptions" :series="attendanceSeries" />
        </article>
        <article class="attendance-panel projection-panel">
          <div class="panel-title"><div><span>Proyección anual</span><h6>Escenarios e ingresos</h6></div><button v-if="capabilities.can_project_revenue" type="button" class="icon-button" title="Configurar parámetros" @click="settingsOpen = !settingsOpen"><i class="bx bx-cog"></i></button></div>
          <div v-if="settingsOpen" class="settings-form">
            <label>Valor unidad<input v-model.number="settingsForm.monthly_unit_value" type="number" min="0" class="form-control" /></label>
            <label>Factor<input v-model.number="settingsForm.attendance_factor" type="number" min="0" step="0.0001" class="form-control" /></label>
            <label>Ajuste CLP<input v-model.number="settingsForm.additional_adjustments" type="number" class="form-control" /></label>
            <label>Meta %<input v-model.number="settingsForm.target_attendance_rate" type="number" min="0" max="100" step="0.01" class="form-control" /></label>
            <label>Conservador (puntos)<input v-model.number="settingsForm.conservative_delta" type="number" min="0" max="100" step="0.01" class="form-control" /></label>
            <label>Personalizado %<input v-model.number="settingsForm.custom_attendance_rate" type="number" min="0" max="100" step="0.01" class="form-control" /></label>
            <label>Días anuales<input v-model.number="settingsForm.annual_school_days" type="number" min="1" max="366" class="form-control" /></label>
            <label>Ventana<select v-model="settingsForm.calculation_window" class="form-select"><option value="current_month">Mes actual</option><option value="rolling_three_months">Últimos 3 meses</option><option value="academic_period">Período académico</option><option value="custom">Personalizada</option></select></label>
            <button type="button" class="btn btn-sm btn-primary" :disabled="settingsLoading" @click="saveSettings">Guardar</button>
          </div>
          <div v-if="report.projections.configuration_required" class="projection-warning"><i class="bx bx-error-circle"></i><div><strong>Configuración financiera requerida</strong><span>Ingresa el valor de la unidad y el factor vigente para calcular ingresos.</span></div></div>
          <div class="scenario-list">
            <div v-for="scenario in report.projections.scenarios" :key="scenario.key" class="scenario-row">
              <div><strong>{{ scenario.label }}</strong><span>Proyección {{ formatRate(scenario.attendance_rate) }} · supuesto futuro {{ formatRate(scenario.scenario_rate) }}</span></div>
              <div><strong>{{ scenario.average_daily_attendance }}</strong><span>promedio diario proyectado</span></div>
              <div><strong>{{ report.projections.configuration_required ? 'Pendiente' : formatMoney(scenario.monthly_revenue) }}</strong><span>ingreso estimado · diferencia {{ report.projections.configuration_required ? '-' : formatMoney(scenario.revenue_difference) }}</span></div>
            </div>
          </div>
          <footer><span>Ingreso acumulado estimado</span><strong>{{ report.projections.configuration_required ? 'Configuración requerida' : formatMoney(report.projections.accumulated_actual_revenue) }}</strong></footer>
        </article>
      </section>

      <section class="attendance-grid attendance-grid-equal">
        <article class="attendance-panel">
          <div class="panel-title"><div><span>Lectura mensual</span><h6>Calendario y días anómalos</h6></div></div>
          <div v-if="report.calendar.length" class="calendar-grid">
            <button v-for="day in report.calendar" :key="day.date" type="button" class="calendar-day actionable" :class="[rateClass(day.attendance_rate), { pending: day.confirmation_status === 'pending_confirmation' }]" :title="`${formatDate(day.date)} · ${formatRate(day.attendance_rate)} · ${day.present} presentes / ${day.absent} ausentes · Abrir detalle`" @click="openDay(day)">
              <span>{{ shortDate(day.date) }}</span><strong>{{ Math.round(day.attendance_rate) }}%</strong><i v-if="day.confirmation_status === 'pending_confirmation'" class="bx bx-error"></i>
            </button>
          </div>
          <div v-else class="empty-state"><i class="bx bx-calendar-x"></i><strong>Sin jornadas importadas</strong><span>Selecciona un curso e importa su PDF mensual.</span></div>
        </article>
        <article class="attendance-panel">
          <div class="panel-title"><div><span>Comparación institucional</span><h6>Asistencia por curso</h6></div></div>
          <div class="table-responsive compact-table-wrap">
            <table class="table compact-table mb-0"><thead><tr><th>Curso</th><th>Est.</th><th>Días</th><th>Aus.</th><th>Asistencia</th></tr></thead><tbody>
              <tr v-for="course in report.courses" :key="course.id"><td><strong>{{ course.name }}</strong></td><td>{{ course.roster_students }}</td><td>{{ course.school_days }}</td><td>{{ course.absent }}</td><td><span class="rate-badge" :class="rateClass(course.attendance_rate)">{{ formatRate(course.attendance_rate) }}</span></td></tr>
              <tr v-if="!report.courses.length"><td colspan="5" class="empty-cell">Sin cursos para el período.</td></tr>
            </tbody></table>
          </div>
        </article>
      </section>

      <article class="attendance-panel student-panel">
        <div class="panel-title student-heading"><div><span>Seguimiento individual</span><h6>Estudiantes y faltas permitidas</h6></div><div class="student-panel-count"><strong>{{ report.summary.roster_students || 0 }}</strong><span>estudiantes en {{ report.students.length }} {{ report.students.length === 1 ? 'curso' : 'cursos' }}</span></div></div>
        <div class="table-responsive"><table class="table attendance-table student-group-table mb-0"><thead><tr><th>Curso</th><th>Matrícula</th><th>Con registros</th><th>Bajo 85%</th><th>Sin datos</th><th>Asistencia promedio</th><th></th></tr></thead><tbody>
          <tr v-for="group in report.students" :key="group.key"><td><strong>{{ group.course }}</strong><small>{{ group.with_data }} con seguimiento calculado</small></td><td>{{ group.students }}</td><td>{{ group.with_data }}</td><td><span class="student-risk-badge" :class="{ active: group.below_target }">{{ group.below_target }}</span></td><td><span class="student-missing-badge" :class="{ active: group.without_data }">{{ group.without_data }}</span></td><td><span class="rate-badge" :class="rateClass(group.average_attendance)">{{ formatRate(group.average_attendance) }}</span></td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary student-group-button" @click="openStudentGroup(group)">Ver estudiantes<i class="bx bx-right-arrow-alt"></i></button></td></tr>
          <tr v-if="!report.students.length"><td colspan="7" class="empty-cell">No hay matrículas vigentes para mostrar.</td></tr>
        </tbody></table></div>
      </article>

      <article class="attendance-panel alerts-panel">
        <div class="panel-title"><div><span>Gestión de riesgo</span><h6>Alertas y seguimientos</h6></div><div class="alert-panel-count"><strong>{{ report.summary.open_alerts || 0 }}</strong><span>activas en {{ report.alerts.length }} {{ report.alerts.length === 1 ? 'curso' : 'cursos' }}</span></div></div>
        <div v-if="report.alerts.length" class="alert-list">
          <button v-for="group in report.alerts" :key="group.key" type="button" class="alert-group-row" @click="openAlertGroup(group)">
            <span class="alert-group-icon" :class="{ critical: group.critical }"><i class="bx" :class="group.critical ? 'bxs-error' : 'bx-error-circle'"></i></span>
            <span class="alert-group-copy"><strong>{{ group.course }}</strong><small>{{ group.students }} {{ group.students === 1 ? 'estudiante' : 'estudiantes' }} en seguimiento · última detección {{ formatDate(group.latest_detected_on) }}</small><span class="alert-type-breakdown"><span v-for="type in group.types" :key="type.type">{{ alertTypeLabel(type.type) }} <strong>{{ type.count }}</strong></span></span></span>
            <span class="alert-group-metrics"><span class="risk-count critical"><strong>{{ group.critical }}</strong> críticas</span><span class="risk-count warning"><strong>{{ group.warning }}</strong> preventivas</span></span>
            <span class="alert-group-open"><strong>Ver {{ group.total }}</strong><i class="bx bx-right-arrow-alt"></i></span>
          </button>
        </div>
        <div v-else class="empty-state horizontal"><i class="bx bx-check-shield"></i><div><strong>Sin alertas activas</strong><span>No hay riesgos detectados en el período seleccionado.</span></div></div>
      </article>

      <article class="attendance-panel imports-panel">
        <div class="panel-title"><div><span>Auditoría</span><h6>Últimas importaciones</h6></div></div>
        <div class="table-responsive"><table class="table compact-table mb-0"><thead><tr><th>Archivo</th><th>Curso</th><th>Período</th><th>Estado</th><th>Registros</th><th>Conflictos</th></tr></thead><tbody>
          <tr v-for="item in report.imports" :key="item.id"><td><strong>{{ item.filename }}</strong></td><td>{{ item.course }}</td><td>{{ item.period || "-" }}</td><td><span class="status-badge" :class="`status-${item.status}`">{{ item.status }}</span></td><td>{{ item.records }}</td><td>{{ item.conflicts }}</td></tr>
          <tr v-if="!report.imports.length"><td colspan="6" class="empty-cell">Aún no hay importaciones registradas.</td></tr>
        </tbody></table></div>
      </article>
    </template>

    <Teleport to="body">
    <div v-if="importOpen" class="attendance-modal" role="dialog" aria-modal="true" aria-label="Importar asistencia mensual" @click.self="closeImport" @keydown.esc="closeImport">
      <section class="modal-surface import-surface" :class="{ 'import-surface--preview': importStep === 'preview' }">
        <header><div><span>Importación mensual</span><h5>{{ importStep === 'upload' ? 'Seleccionar curso y archivo' : importStep === 'preview' ? 'Revisar y conciliar' : 'Importación finalizada' }}</h5></div><button type="button" class="icon-button" title="Cerrar" @click="closeImport"><i class="bx bx-x"></i></button></header>
        <div v-if="importError" class="alert alert-danger py-2">{{ importError }}</div>
        <div v-if="importSuccess" class="alert alert-success py-2">{{ importSuccess }}</div>
        <div v-if="importStep === 'upload'" class="upload-step">
          <label>Curso de destino<span>Obligatorio</span><select v-model="importCourseId" class="form-select"><option :value="null">Selecciona un curso existente</option><option v-for="course in importCourses" :key="course.id" :value="course.id">{{ course.display_name }}</option></select></label>
          <label class="file-drop"><input type="file" accept="application/pdf,.pdf" @change="handleFile" /><i class="bx bxs-file-pdf"></i><strong>{{ importFile?.name || "Seleccionar PDF de asistencia Lirmi" }}</strong><span>Formato mensual · máximo 25 MB</span></label>
          <footer><button type="button" class="btn btn-light" @click="closeImport">Cancelar</button><button type="button" class="btn btn-primary" :disabled="importLoading || !importCourseId || !importFile" @click="previewImport"><i class="bx" :class="importLoading ? 'bx-loader-alt bx-spin' : 'bx-scan'"></i>Analizar archivo</button></footer>
        </div>
        <template v-else-if="importStep === 'preview'">
          <div class="import-summary"><div><span>Curso seleccionado</span><strong>{{ importPreview.course }}</strong></div><div><span>Período PDF</span><strong>{{ importPreview.preview.document.month_label }} {{ importPreview.preview.document.year }}</strong></div><div><span>Estudiantes</span><strong>{{ importPreview.preview.summary.students }}</strong></div><div><span>Asistencia</span><strong>{{ formatRate(importPreview.preview.summary.attendance_rate) }}</strong></div><div><span>Posibles</span><strong>{{ importPreview.preview.summary.possible }}</strong></div></div>
          <div v-for="warning in importPreview.validation?.warnings || []" :key="warning.code" class="import-warning"><i class="bx bx-error-circle"></i>{{ warning.message }}</div>
          <div class="match-table-wrap"><table class="table match-table mb-0"><thead><tr><th>Fila</th><th>Nombre en PDF</th><th>Coincidencia</th><th>Estado</th><th>Totales</th></tr></thead><tbody>
            <tr v-for="row in importRows" :key="row.row" :class="{ unresolved: !Number(matchSelections[row.row]) }"><td>{{ row.row }}</td><td><strong>{{ row.name }}</strong></td><td><select v-model="matchSelections[row.row]" class="form-select form-select-sm"><option value="">Seleccionar estudiante</option><option v-for="candidate in row.candidates" :key="candidate.student_profile_id" :value="candidate.student_profile_id">{{ candidate.name }} · {{ candidate.rut || "Sin RUT" }} · {{ candidate.score }}%</option></select></td><td><span class="match-status" :class="`match-${row.match_status}`">{{ row.match_status }}</span></td><td>{{ row.present }} A · {{ row.absent }} I</td></tr>
          </tbody></table></div>
          <div class="strategy-row"><label>Estrategia ante conflictos<select v-model="conflictStrategy" class="form-select"><option value="reject">Rechazar si existen diferencias</option><option value="overwrite">Sobrescribir con el PDF</option><option value="skip">Conservar registros existentes</option></select></label><span>{{ unresolvedRows.length ? `${unresolvedRows.length} filas pendientes` : "Todas las filas están asociadas" }}</span></div>
          <footer><button type="button" class="btn btn-light" :disabled="importLoading" @click="importStep = 'upload'">Volver</button><button type="button" class="btn btn-primary" :disabled="importLoading || unresolvedRows.length" @click="confirmImport"><i class="bx" :class="importLoading ? 'bx-loader-alt bx-spin' : 'bx-check-shield'"></i>Confirmar importación</button></footer>
        </template>
        <div v-else class="completed-step"><i class="bx bx-check-circle"></i><strong>Proceso completado</strong><p>Los datos quedaron asociados al curso y disponibles en las estadísticas.</p><button type="button" class="btn btn-primary" @click="closeImport">Cerrar</button></div>
      </section>
    </div>

    <div v-if="dayDetail" class="attendance-modal" role="dialog" aria-modal="true" aria-label="Detalle diario de asistencia">
      <section class="modal-surface day-surface">
        <header><div><span>Detalle de jornada</span><h5>{{ formatDate(dayDetail.day?.date) }}</h5></div><button type="button" class="icon-button" title="Cerrar" @click="dayDetail = null"><i class="bx bx-x"></i></button></header>
        <LoadingState v-if="dayDetailLoading" message="Cargando registros del día..." compact />
        <template v-else>
          <div class="day-kpis"><div><span>Presentes</span><strong>{{ dayDetail.day.present }}</strong></div><div><span>Ausentes</span><strong>{{ dayDetail.day.absent }}</strong></div><div><span>Esperados</span><strong>{{ dayDetail.day.total }}</strong></div><div><span>Asistencia</span><strong :class="rateClass(dayDetail.day.attendance_rate)">{{ formatRate(dayDetail.day.attendance_rate) }}</strong></div></div>
          <div v-if="dayDetail.day.status === 'pending_confirmation'" class="anomaly-callout"><i class="bx bx-error"></i><div><strong>Anomalía: {{ formatRate(dayDetail.day.attendance_rate) }} de asistencia</strong><span>Confirma si hubo clases o corrige los registros antes de validar la jornada.</span></div><button v-if="capabilities.can_edit" type="button" class="btn btn-sm btn-warning" :disabled="dayMutationId === dayDetail.day.id" @click="confirmSchoolDay(dayDetail.day)"><i class="bx bx-check-shield"></i>Confirmar jornada</button></div>
          <div class="table-responsive day-table-wrap"><table class="table compact-table mb-0"><thead><tr><th>Estudiante</th><th>Curso</th><th>Estado</th><th>Fuente</th><th v-if="capabilities.can_edit"></th></tr></thead><tbody>
            <tr v-for="record in dayDetail.students" :key="record.record_id"><td><strong>{{ record.name }}</strong><small>{{ record.rut || 'Sin RUT' }}</small></td><td>{{ record.course }}</td><td><span class="day-status" :class="`day-${record.status}`">{{ record.status === 'present' ? 'Presente' : 'Ausente' }}</span></td><td><span>{{ record.origin === 'manual' ? 'Corrección manual' : record.import_filename || 'Importación' }}</span></td><td v-if="capabilities.can_edit" class="text-end"><button type="button" class="icon-button" :title="record.status === 'absent' ? 'Marcar presente' : 'Marcar ausente'" :disabled="recordMutationId === record.record_id" @click="updateDayRecord(record, record.status === 'absent' ? 'present' : 'absent')"><i class="bx" :class="recordMutationId === record.record_id ? 'bx-loader-alt bx-spin' : record.status === 'absent' ? 'bx-check' : 'bx-x'"></i></button></td></tr>
            <tr v-if="!dayDetail.students.length"><td :colspan="capabilities.can_edit ? 5 : 4" class="empty-cell">No existen registros para esta jornada.</td></tr>
          </tbody></table></div>
        </template>
      </section>
    </div>

    <div v-if="studentGroup" class="attendance-modal" role="dialog" aria-modal="true" :aria-label="`Estudiantes de ${studentGroup.course}`" @click.self="closeStudentGroup" @keydown.esc="closeStudentGroup">
      <section class="modal-surface student-list-surface">
        <header><div><span>Seguimiento individual por curso</span><h5>{{ studentGroup.course }}</h5></div><button type="button" class="icon-button" title="Cerrar" @click="closeStudentGroup"><i class="bx bx-x"></i></button></header>
        <div class="student-list-kpis"><div><span>Matrícula</span><strong>{{ studentGroup.students }}</strong></div><div><span>Con registros</span><strong>{{ studentGroup.with_data }}</strong></div><div><span>Bajo 85%</span><strong class="rate-critical">{{ studentGroup.below_target }}</strong></div><div><span>Sin datos</span><strong :class="{ 'rate-warning': studentGroup.without_data }">{{ studentGroup.without_data }}</strong></div><div><span>Promedio</span><strong :class="rateClass(studentGroup.average_attendance)">{{ formatRate(studentGroup.average_attendance) }}</strong></div></div>
        <form class="student-list-filters" @submit.prevent="applyStudentListFilters">
          <label class="student-list-search"><span>Buscar estudiante</span><div><i class="bx bx-search"></i><input v-model.trim="studentListFilters.search" type="search" class="form-control" placeholder="Nombre o RUT" /></div></label>
          <label><span>Estado de asistencia</span><select v-model="studentListFilters.risk" class="form-select"><option value="">Todos</option><option value="below_target">Bajo 85%</option><option value="warning">Entre 85% y 89,99%</option><option value="on_track">90% o superior</option><option value="no_data">Sin registros</option></select></label>
          <button type="submit" class="btn btn-primary" :disabled="studentListLoading" title="Aplicar filtros"><i class="bx bx-filter-alt"></i><span>Aplicar</span></button>
          <button type="button" class="icon-button" title="Limpiar filtros" :disabled="studentListLoading" @click="clearStudentListFilters"><i class="bx bx-reset"></i></button>
        </form>
        <div v-if="studentListError" class="alert alert-danger student-list-error"><span>{{ studentListError }}</span><button type="button" class="btn btn-sm btn-outline-danger" @click="loadStudentList(studentList.meta?.current_page || 1)">Reintentar</button></div>
        <LoadingState v-if="studentListLoading" message="Cargando estudiantes del curso..." compact />
        <div v-else-if="studentList.data.length" class="table-responsive student-list-table-wrap"><table class="table attendance-table mb-0"><thead><tr><th>Estudiante</th><th>Presentes</th><th>Ausentes</th><th>Asistencia</th><th>Faltas restantes</th><th></th></tr></thead><tbody>
          <tr v-for="student in studentList.data" :key="`${student.id}-${student.course_id}`"><td><strong>{{ student.name }}</strong><small>{{ student.rut || 'Sin RUT' }}</small></td><td>{{ student.present }}</td><td>{{ student.absent }}</td><td><span class="rate-badge" :class="rateClass(student.attendance_rate)">{{ formatRate(student.attendance_rate) }}</span></td><td>{{ student.total ? student.remaining_allowed_absences : '-' }}</td><td class="text-end"><button type="button" class="icon-button" title="Abrir ficha de asistencia" @click="openStudent(student)"><i class="bx bx-right-arrow-alt"></i></button></td></tr>
        </tbody></table></div>
        <div v-else-if="!studentListError" class="empty-state"><i class="bx bx-filter"></i><strong>No hay estudiantes con estos filtros</strong><span>Limpia los filtros para revisar toda la matrícula del curso.</span></div>
        <footer class="student-list-pagination"><span v-if="studentList.meta?.total">Mostrando {{ studentList.meta.from }}–{{ studentList.meta.to }} de {{ studentList.meta.total }}</span><span v-else></span><div><button type="button" class="icon-button" title="Página anterior" :disabled="studentListLoading || studentList.meta?.current_page <= 1" @click="loadStudentList(studentList.meta.current_page - 1)"><i class="bx bx-chevron-left"></i></button><strong>Página {{ studentList.meta?.current_page || 1 }} de {{ studentList.meta?.last_page || 1 }}</strong><button type="button" class="icon-button" title="Página siguiente" :disabled="studentListLoading || studentList.meta?.current_page >= studentList.meta?.last_page" @click="loadStudentList(studentList.meta.current_page + 1)"><i class="bx bx-chevron-right"></i></button></div></footer>
      </section>
    </div>

    <div v-if="studentDetail" class="attendance-modal" role="dialog" aria-modal="true" aria-label="Detalle de asistencia de estudiante">
      <section class="modal-surface detail-surface"><header><div><span>Detalle individual</span><h5>{{ studentDetail.student?.name }}</h5></div><button type="button" class="icon-button" title="Cerrar" @click="studentDetail = null"><i class="bx bx-x"></i></button></header>
        <LoadingState v-if="studentDetailLoading" message="Cargando trayectoria..." compact />
        <template v-else><div class="detail-kpis"><div><span>Asistencia</span><strong :class="rateClass(studentDetail.summary.attendance_rate)">{{ formatRate(studentDetail.summary.attendance_rate) }}</strong></div><div><span>Presentes</span><strong>{{ studentDetail.summary.present }}</strong></div><div><span>Ausentes</span><strong>{{ studentDetail.summary.absent }}</strong></div><div><span>Faltas restantes</span><strong>{{ studentDetail.summary.remaining_allowed_absences }}</strong></div></div>
          <h6>Evolución mensual</h6><div class="month-list"><div v-for="month in studentDetail.months" :key="month.period"><span>{{ month.period }}</span><strong :class="rateClass(month.attendance_rate)">{{ formatRate(month.attendance_rate) }}</strong><small>{{ month.present }} presentes · {{ month.absent }} ausentes</small></div></div>
          <h6>Fechas de inasistencia</h6><div class="absence-list"><button v-for="absence in studentDetail.absences" :key="absence.id" type="button" :disabled="recordMutationId === absence.id || !capabilities.can_edit" :title="capabilities.can_edit ? 'Marcar esta fecha como presente' : 'Registro de inasistencia'" @click="correctAbsence(absence)"><span>{{ formatDate(absence.date) }}</span><i v-if="recordMutationId === absence.id" class="bx bx-loader-alt bx-spin"></i><i v-else-if="capabilities.can_edit" class="bx bx-check"></i></button><span v-if="!studentDetail.absences.length">Sin inasistencias</span></div>
        </template>
      </section>
    </div>

    <div v-if="alertGroup" class="attendance-modal" role="dialog" aria-modal="true" :aria-label="`Alertas de ${alertGroup.course}`" @click.self="closeAlertGroup" @keydown.esc="closeAlertGroup">
      <section class="modal-surface alert-detail-surface">
        <header><div><span>Gestión de riesgo por curso</span><h5>{{ alertGroup.course }}</h5></div><button type="button" class="icon-button" title="Cerrar" @click="closeAlertGroup"><i class="bx bx-x"></i></button></header>
        <div class="alert-detail-kpis"><div><span>Alertas activas</span><strong>{{ alertGroup.total }}</strong></div><div><span>Estudiantes</span><strong>{{ alertGroup.students }}</strong></div><div><span>Críticas</span><strong class="rate-critical">{{ alertGroup.critical }}</strong></div><div><span>Preventivas</span><strong class="rate-warning">{{ alertGroup.warning }}</strong></div></div>
        <form class="alert-detail-filters" @submit.prevent="applyAlertFilters">
          <label class="alert-search"><span>Buscar estudiante</span><div><i class="bx bx-search"></i><input v-model.trim="alertDetailFilters.search" type="search" class="form-control" placeholder="Nombre o RUT" /></div></label>
          <label><span>Severidad</span><select v-model="alertDetailFilters.severity" class="form-select"><option value="">Todas</option><option value="critical">Críticas</option><option value="warning">Preventivas</option></select></label>
          <label><span>Tipo de alerta</span><select v-model="alertDetailFilters.type" class="form-select"><option value="">Todos los tipos</option><option v-for="type in alertTypeOptions" :key="type.type" :value="type.type">{{ alertTypeLabel(type.type) }} ({{ type.count }})</option></select></label>
          <button type="submit" class="btn btn-primary" :disabled="alertDetailLoading" title="Aplicar filtros"><i class="bx bx-filter-alt"></i><span>Aplicar</span></button>
          <button type="button" class="icon-button" title="Limpiar filtros" :disabled="alertDetailLoading" @click="clearAlertFilters"><i class="bx bx-reset"></i></button>
        </form>
        <div v-if="alertDetailError" class="alert alert-danger alert-detail-error"><span>{{ alertDetailError }}</span><button type="button" class="btn btn-sm btn-outline-danger" @click="loadAlertDetails(alertDetail.meta?.current_page || 1)">Reintentar</button></div>
        <LoadingState v-if="alertDetailLoading" message="Cargando alertas del curso..." compact />
        <div v-else-if="alertDetail.data.length" class="individual-alert-list">
          <div v-for="alert in alertDetail.data" :key="alert.id" class="alert-row" :class="`alert-${alert.severity}`">
            <i class="bx" :class="alert.severity === 'critical' ? 'bxs-error' : 'bx-error-circle'"></i>
            <div><strong>{{ alert.title }}</strong><span>{{ alert.student || alert.course }}<template v-if="alert.student_rut"> · {{ alert.student_rut }}</template> · {{ alert.description }}</span><small>{{ formatDate(alert.detected_on) }} · {{ alert.followups_count }} seguimientos</small></div>
            <div v-if="capabilities.can_manage_alerts" class="alert-actions"><button type="button" class="btn btn-sm btn-outline-primary" @click="openFollowup(alert)"><i class="bx bx-message-square-add"></i>Seguimiento</button><button type="button" class="icon-button" title="Resolver alerta" @click="updateAlert(alert, 'resolved')"><i class="bx bx-check"></i></button></div>
          </div>
        </div>
        <div v-else-if="!alertDetailError" class="empty-state"><i class="bx bx-filter"></i><strong>No hay alertas con estos filtros</strong><span>Limpia los filtros para revisar todas las alertas activas del curso.</span></div>
        <footer class="alert-pagination"><span v-if="alertDetail.meta?.total">Mostrando {{ alertDetail.meta.from }}–{{ alertDetail.meta.to }} de {{ alertDetail.meta.total }}</span><span v-else></span><div><button type="button" class="icon-button" title="Página anterior" :disabled="alertDetailLoading || alertDetail.meta?.current_page <= 1" @click="loadAlertDetails(alertDetail.meta.current_page - 1)"><i class="bx bx-chevron-left"></i></button><strong>Página {{ alertDetail.meta?.current_page || 1 }} de {{ alertDetail.meta?.last_page || 1 }}</strong><button type="button" class="icon-button" title="Página siguiente" :disabled="alertDetailLoading || alertDetail.meta?.current_page >= alertDetail.meta?.last_page" @click="loadAlertDetails(alertDetail.meta.current_page + 1)"><i class="bx bx-chevron-right"></i></button></div></footer>
      </section>
    </div>

    <div v-if="followupAlert" class="attendance-modal" role="dialog" aria-modal="true" aria-label="Registrar seguimiento">
      <section class="modal-surface followup-surface"><header><div><span>Gestión de alerta</span><h5>Registrar seguimiento</h5></div><button type="button" class="icon-button" title="Cerrar" @click="followupAlert = null"><i class="bx bx-x"></i></button></header><p class="followup-context">{{ followupAlert.title }} · {{ followupAlert.student || followupAlert.course }}</p>
        <div class="followup-grid"><label>Acción<select v-model="followupForm.action_type" class="form-select"><option value="contact_guardian">Contacto con apoderado</option><option value="student_interview">Entrevista con estudiante</option><option value="referral">Derivación interna</option><option value="attendance_commitment">Compromiso de asistencia</option><option value="other">Otra acción</option></select></label><label>Fecha<input v-model="followupForm.action_date" type="date" class="form-control" /></label><label class="full">Notas<textarea v-model.trim="followupForm.notes" rows="4" class="form-control"></textarea></label><label>Próxima acción<input v-model="followupForm.next_action_date" type="date" class="form-control" /></label></div>
        <footer><button type="button" class="btn btn-light" @click="followupAlert = null">Cancelar</button><button type="button" class="btn btn-primary" :disabled="followupLoading || !followupForm.notes" @click="saveFollowup">Guardar seguimiento</button></footer>
      </section>
    </div>
    </Teleport>
  </div>
</template>

<style scoped>
.attendance-module { --ink: #273244; --muted: #6c788b; --line: #e1e6ed; display: grid; gap: 0.85rem; color: var(--ink); }
.attendance-toolbar, .attendance-filters, .attendance-panel, .attendance-kpis { border: 1px solid var(--line); border-radius: 8px; background: #fff; }
.attendance-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.1rem; }
.attendance-kicker, .panel-title span, .modal-surface header span { color: #64748b; font-size: 0.66rem; font-weight: 700; text-transform: uppercase; }
.attendance-toolbar h5 { margin: 0.12rem 0; font-size: 1.12rem; font-weight: 750; }
.attendance-toolbar p { margin: 0; color: var(--muted); font-size: 0.74rem; }
.attendance-actions { display: flex; gap: 0.45rem; }
.attendance-actions .btn, .filter-button { display: inline-flex; align-items: center; gap: 0.35rem; }
.attendance-filters { display: grid; grid-template-columns: repeat(3, minmax(160px, 1fr)) auto; align-items: end; gap: 0.65rem; padding: 0.75rem; }
.attendance-filters label, .settings-form label, .upload-step > label:not(.file-drop), .strategy-row label, .followup-grid label { margin: 0; color: #526071; font-size: 0.68rem; font-weight: 650; }
.attendance-filters .form-select, .attendance-filters .form-control { min-height: 36px; margin-top: 0.25rem; font-size: 0.75rem; }
.filter-button { min-height: 36px; }
.attendance-alert { display: flex; align-items: center; gap: 0.55rem; margin: 0; font-size: 0.75rem; }
.attendance-alert span { flex: 1; }
.attendance-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); overflow: hidden; }
.attendance-kpis article { display: grid; grid-template-columns: 30px 1fr; grid-template-rows: auto auto; column-gap: 0.48rem; padding: 0.78rem; }
.attendance-kpis article + article { border-left: 1px solid var(--line); }
.attendance-kpis article:nth-child(5) { border-left: 0; }
.attendance-kpis article:nth-child(n + 5) { border-top: 1px solid var(--line); }
.attendance-kpis i { grid-row: 1 / 3; align-self: center; color: #405189; font-size: 1.2rem; }
.attendance-kpis span { color: var(--muted); font-size: 0.66rem; }
.attendance-kpis strong { margin-top: 0.1rem; font-size: 1rem; }
.attendance-grid { display: grid; gap: 0.85rem; }
.attendance-grid-main { grid-template-columns: minmax(0, 1.35fr) minmax(360px, 0.85fr); }
.attendance-grid-equal { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.attendance-panel { min-width: 0; padding: 0.95rem; }
.panel-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.8rem; margin-bottom: 0.65rem; }
.panel-title h6 { margin: 0.12rem 0 0; font-size: 0.9rem; font-weight: 750; }
.panel-title > strong { font-size: 1.05rem; }
.icon-button { display: inline-flex; width: 30px; height: 30px; flex: 0 0 30px; align-items: center; justify-content: center; border: 1px solid #d8dee8; border-radius: 6px; background: #fff; color: #405189; }
.icon-button i { font-size: 1rem; }
.settings-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: end; gap: 0.45rem; margin-bottom: 0.7rem; padding: 0.65rem; background: #f7f9fb; }
.settings-form .form-control, .settings-form .form-select { margin-top: 0.22rem; font-size: 0.72rem; }
.projection-warning { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem; padding: 0.55rem 0.65rem; border: 1px solid #f0cf86; background: #fff9eb; color: #805b10; font-size: 0.68rem; }
.projection-warning i { font-size: 1.1rem; }
.projection-warning strong, .projection-warning span { display: block; }
.projection-warning span { margin-top: 0.08rem; }
.scenario-list { display: grid; }
.scenario-row { display: grid; grid-template-columns: minmax(150px, 1fr) minmax(90px, 0.6fr) minmax(120px, 0.8fr); gap: 0.6rem; padding: 0.68rem 0; border-bottom: 1px solid #edf0f4; }
.scenario-row strong, .scenario-row span { display: block; }
.scenario-row strong { font-size: 0.76rem; }
.scenario-row span { margin-top: 0.12rem; color: var(--muted); font-size: 0.64rem; }
.projection-panel footer { display: flex; justify-content: space-between; gap: 1rem; padding-top: 0.75rem; font-size: 0.75rem; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(54px, 1fr)); gap: 0.38rem; }
.calendar-day { position: relative; display: grid; min-height: 58px; align-content: center; border: 1px solid #dfe5ec; border-radius: 6px; background: #f8fafc; text-align: center; }
.calendar-day span { color: #6b7789; font-size: 0.62rem; }
.calendar-day strong { margin-top: 0.12rem; font-size: 0.76rem; }
.calendar-day.pending { box-shadow: inset 0 0 0 2px #d97706; }
.calendar-day.actionable { cursor: pointer; }
.calendar-day.actionable:hover { border-color: #b56c05; transform: translateY(-1px); }
.calendar-day > i { position: absolute; top: 3px; right: 3px; color: #d97706; }
.calendar-day.rate-ok { background: #eef8f2; color: #28764d; }
.calendar-day.rate-warning { background: #fff7e6; color: #926515; }
.calendar-day.rate-critical { background: #fff0f1; color: #b42332; }
.compact-table-wrap { max-height: 345px; overflow: auto; }
.compact-table, .attendance-table, .match-table { font-size: 0.71rem; }
.compact-table th, .attendance-table th, .match-table th { background: #f7f9fb; color: #526071; font-size: 0.64rem; white-space: nowrap; }
.compact-table td, .compact-table th, .attendance-table td, .attendance-table th, .match-table td, .match-table th { padding: 0.5rem 0.55rem; border-color: #edf0f4; vertical-align: middle; }
.rate-badge, .status-badge, .match-status { display: inline-flex; padding: 0.22rem 0.38rem; border-radius: 4px; font-size: 0.64rem; font-weight: 700; }
.rate-ok { color: #28764d; } .rate-badge.rate-ok { background: #e7f5ec; }
.rate-warning { color: #926515; } .rate-badge.rate-warning { background: #fff4dc; }
.rate-critical { color: #b42332; } .rate-badge.rate-critical { background: #fbeaec; }
.rate-empty { color: #7b8796; } .rate-badge.rate-empty { background: #edf1f5; }
.student-panel { padding: 0; overflow: hidden; }
.student-heading { align-items: center; margin: 0; padding: 0.8rem 0.95rem; border-bottom: 1px solid var(--line); }
.student-panel-count { text-align: right; }
.student-panel-count strong, .student-panel-count span { display: block; }
.student-panel-count strong { font-size: 1rem; line-height: 1; }
.student-panel-count span { margin-top: 0.16rem; color: var(--muted); font-size: 0.63rem; text-transform: none; }
.attendance-table td strong, .attendance-table td small { display: block; }
.attendance-table td small { color: var(--muted); font-size: 0.62rem; }
.student-group-table tbody tr:hover td { background: #f8fafc; }
.student-risk-badge, .student-missing-badge { display: inline-flex; min-width: 28px; justify-content: center; padding: 0.2rem 0.34rem; border-radius: 4px; background: #edf1f5; color: #64748b; font-size: 0.64rem; font-weight: 700; }
.student-risk-badge.active { background: #fbeaec; color: #b42332; }
.student-missing-badge.active { background: #fff4dc; color: #805b10; }
.student-group-button { display: inline-flex; align-items: center; gap: 0.25rem; white-space: nowrap; font-size: 0.65rem; }
.empty-cell { padding: 1.5rem !important; color: var(--muted) !important; text-align: center; }
.panel-count { color: var(--muted); font-size: 0.7rem; }
.alert-panel-count { text-align: right; }
.alert-panel-count strong, .alert-panel-count span { display: block; }
.alert-panel-count strong { font-size: 1rem; line-height: 1; }
.alert-panel-count span { margin-top: 0.16rem; color: var(--muted); font-size: 0.63rem; text-transform: none; }
.alert-list { display: grid; border-top: 1px solid var(--line); }
.alert-group-row { display: grid; width: 100%; grid-template-columns: 34px minmax(0, 1fr) auto 76px; align-items: center; gap: 0.7rem; padding: 0.75rem 0.2rem; border: 0; border-bottom: 1px solid #edf0f4; background: #fff; color: var(--ink); text-align: left; }
.alert-group-row:last-child { border-bottom: 0; }
.alert-group-row:hover { background: #f8fafc; }
.alert-group-row:focus-visible { position: relative; z-index: 1; outline: 2px solid #405189; outline-offset: 1px; }
.alert-group-icon { display: inline-flex; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 6px; background: #fff4dc; color: #9a6700; }
.alert-group-icon.critical { background: #fbeaec; color: #c5303d; }
.alert-group-icon i { font-size: 1.05rem; }
.alert-group-copy { min-width: 0; }
.alert-group-copy > strong, .alert-group-copy > small { display: block; }
.alert-group-copy > strong { font-size: 0.77rem; }
.alert-group-copy > small { margin-top: 0.12rem; color: var(--muted); font-size: 0.63rem; }
.alert-type-breakdown { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.35rem; }
.alert-type-breakdown > span { padding: 0.18rem 0.32rem; border: 1px solid #e2e7ee; border-radius: 4px; background: #f8fafc; color: #526071; font-size: 0.59rem; }
.alert-type-breakdown > span strong { margin-left: 0.15rem; }
.alert-group-metrics { display: flex; gap: 0.35rem; }
.risk-count { display: grid; min-width: 70px; justify-items: center; padding: 0.3rem 0.42rem; border-radius: 4px; font-size: 0.59rem; }
.risk-count strong { font-size: 0.77rem; }
.risk-count.critical { background: #fbeaec; color: #b42332; }
.risk-count.warning { background: #fff4dc; color: #805b10; }
.alert-group-open { display: inline-flex; align-items: center; justify-content: flex-end; gap: 0.22rem; color: #405189; font-size: 0.65rem; }
.alert-group-open i { font-size: 1rem; }
.alert-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; align-items: center; gap: 0.55rem; padding: 0.62rem 0.7rem; border-left: 3px solid #d69e2e; background: #fffaf0; }
.alert-row.alert-critical { border-left-color: #d64550; background: #fff5f5; }
.alert-row > i { color: #b7791f; font-size: 1.15rem; }
.alert-row.alert-critical > i { color: #c5303d; }
.alert-row strong, .alert-row span, .alert-row small { display: block; }
.alert-row strong { font-size: 0.75rem; }
.alert-row span { margin-top: 0.1rem; color: #526071; font-size: 0.68rem; }
.alert-row small { margin-top: 0.12rem; color: #8a94a3; font-size: 0.6rem; }
.alert-actions { display: flex; gap: 0.35rem; }
.alert-actions .btn { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.65rem; }
.empty-state { display: grid; justify-items: center; padding: 2.2rem; color: var(--muted); text-align: center; }
.empty-state i { margin-bottom: 0.35rem; font-size: 1.5rem; }
.empty-state strong, .empty-state span { display: block; }
.empty-state strong { color: #445065; font-size: 0.76rem; }
.empty-state span { margin-top: 0.15rem; font-size: 0.67rem; }
.empty-state.horizontal { grid-template-columns: auto 1fr; justify-items: start; align-items: center; gap: 0.65rem; text-align: left; }
.status-preview { background: #edf1f5; color: #526071; } .status-completed { background: #e7f5ec; color: #28764d; }
.attendance-modal { --ink: #273244; --muted: #6c788b; --line: #e1e6ed; position: fixed; z-index: 1080; inset: 0; display: grid; place-items: center; padding: 1rem; overflow-y: auto; overscroll-behavior: contain; background: rgba(26, 34, 48, 0.48); }
.modal-surface { width: min(680px, calc(100vw - 2rem)); max-height: calc(100dvh - 2rem); overflow: auto; border-radius: 8px; background: #fff; box-shadow: 0 20px 60px rgba(17, 24, 39, 0.28); }
.import-surface { width: min(760px, calc(100vw - 2rem)); }
.import-surface--preview { width: min(1080px, calc(100vw - 2rem)); }
.modal-surface > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 0.9rem 1rem; border-bottom: 1px solid var(--line); }
.modal-surface > header h5 { margin: 0.12rem 0 0; font-size: 1rem; }
.modal-surface > .alert { margin: 0.7rem 1rem 0; font-size: 0.72rem; }
.upload-step { display: grid; gap: 0.85rem; padding: 1rem; }
.upload-step label > span { float: right; color: #b42332; font-size: 0.62rem; }
.upload-step .form-select { margin-top: 0.3rem; }
.file-drop { display: grid; min-height: 190px; place-content: center; justify-items: center; border: 1px dashed #aeb8c6; background: #f8fafc; cursor: pointer; text-align: center; }
.file-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
.file-drop i { color: #d82732; font-size: 2rem; }
.file-drop strong { margin-top: 0.4rem; color: #344054; font-size: 0.78rem; }
.file-drop span { margin-top: 0.18rem; color: var(--muted); font-size: 0.66rem; }
.modal-surface footer { display: flex; justify-content: flex-end; gap: 0.45rem; padding: 0.8rem 1rem; border-top: 1px solid var(--line); }
.import-summary { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); margin: 0.8rem 1rem; border: 1px solid var(--line); }
.import-summary > div { padding: 0.65rem; }
.import-summary > div + div { border-left: 1px solid var(--line); }
.import-summary span, .import-summary strong { display: block; }
.import-summary span { color: var(--muted); font-size: 0.62rem; }
.import-summary strong { margin-top: 0.18rem; font-size: 0.78rem; }
.import-warning { display: flex; align-items: center; gap: 0.4rem; margin: 0.35rem 1rem; padding: 0.48rem 0.6rem; background: #fff7e6; color: #805b10; font-size: 0.68rem; }
.match-table-wrap { max-height: 430px; margin-top: 0.7rem; overflow: auto; border-block: 1px solid var(--line); }
.match-table .form-select { min-width: 280px; font-size: 0.67rem; }
.match-table tr.unresolved td { background: #fff9e8; }
.match-exact, .match-manual { background: #e7f5ec; color: #28764d; } .match-fuzzy { background: #e9effb; color: #405189; } .match-ambiguous, .match-unmatched { background: #fbeaec; color: #b42332; }
.strategy-row { display: flex; align-items: end; justify-content: space-between; gap: 1rem; padding: 0.75rem 1rem; }
.strategy-row label { width: min(430px, 70%); }
.strategy-row .form-select { margin-top: 0.25rem; font-size: 0.72rem; }
.strategy-row > span { color: var(--muted); font-size: 0.68rem; }
.completed-step { display: grid; justify-items: center; padding: 3rem 1rem; text-align: center; }
.completed-step > i { color: #2f855a; font-size: 3rem; }
.completed-step strong { margin-top: 0.5rem; font-size: 1rem; }
.completed-step p { color: var(--muted); font-size: 0.72rem; }
.detail-surface, .followup-surface { width: min(720px, 96vw); }
.day-surface { width: min(920px, 96vw); }
.alert-detail-surface { width: min(980px, calc(100vw - 2rem)); }
.student-list-surface { width: min(940px, calc(100vw - 2rem)); }
.student-list-kpis { display: grid; grid-template-columns: repeat(5, 1fr); margin: 0.8rem 1rem; border: 1px solid var(--line); }
.student-list-kpis > div { padding: 0.6rem 0.7rem; }
.student-list-kpis > div + div { border-left: 1px solid var(--line); }
.student-list-kpis span, .student-list-kpis strong { display: block; }
.student-list-kpis span { color: var(--muted); font-size: 0.62rem; }
.student-list-kpis strong { margin-top: 0.12rem; font-size: 0.88rem; }
.student-list-filters { display: grid; grid-template-columns: minmax(210px, 1fr) minmax(190px, 0.7fr) auto 30px; align-items: end; gap: 0.45rem; margin: 0 1rem 0.8rem; padding: 0.65rem; background: #f7f9fb; }
.student-list-filters label { margin: 0; }
.student-list-filters label > span { display: block; margin-bottom: 0.22rem; color: #526071; font-size: 0.62rem; font-weight: 650; }
.student-list-filters .form-select, .student-list-filters .form-control { min-height: 34px; font-size: 0.68rem; }
.student-list-filters .btn { display: inline-flex; min-height: 34px; align-items: center; gap: 0.28rem; font-size: 0.67rem; }
.student-list-search > div { position: relative; }
.student-list-search i { position: absolute; top: 50%; left: 0.58rem; transform: translateY(-50%); color: #8994a3; }
.student-list-search input { padding-left: 1.85rem; }
.student-list-error { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; margin: 0.8rem 1rem !important; }
.student-list-table-wrap { max-height: 50vh; overflow-y: auto; border-top: 1px solid var(--line); }
.student-list-pagination { align-items: center; justify-content: space-between !important; }
.student-list-pagination > span { color: var(--muted); font-size: 0.63rem; }
.student-list-pagination > div { display: flex; align-items: center; gap: 0.45rem; }
.student-list-pagination > div strong { min-width: 96px; color: #526071; font-size: 0.64rem; text-align: center; }
.alert-detail-kpis { display: grid; grid-template-columns: repeat(4, 1fr); margin: 0.8rem 1rem; border: 1px solid var(--line); }
.alert-detail-kpis > div { padding: 0.6rem 0.7rem; }
.alert-detail-kpis > div + div { border-left: 1px solid var(--line); }
.alert-detail-kpis span, .alert-detail-kpis strong { display: block; }
.alert-detail-kpis span { color: var(--muted); font-size: 0.62rem; }
.alert-detail-kpis strong { margin-top: 0.12rem; font-size: 0.88rem; }
.alert-detail-filters { display: grid; grid-template-columns: minmax(190px, 1fr) 150px minmax(190px, 0.8fr) auto 30px; align-items: end; gap: 0.45rem; margin: 0 1rem 0.8rem; padding: 0.65rem; background: #f7f9fb; }
.alert-detail-filters label { margin: 0; }
.alert-detail-filters label > span { display: block; margin-bottom: 0.22rem; color: #526071; font-size: 0.62rem; font-weight: 650; }
.alert-detail-filters .form-select, .alert-detail-filters .form-control { min-height: 34px; font-size: 0.68rem; }
.alert-detail-filters .btn { display: inline-flex; min-height: 34px; align-items: center; gap: 0.28rem; font-size: 0.67rem; }
.alert-search > div { position: relative; }
.alert-search i { position: absolute; top: 50%; left: 0.58rem; transform: translateY(-50%); color: #8994a3; }
.alert-search input { padding-left: 1.85rem; }
.alert-detail-error { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; margin: 0.8rem 1rem !important; }
.individual-alert-list { display: grid; max-height: 50vh; gap: 0.42rem; padding: 0 1rem 0.8rem; overflow-y: auto; }
.alert-pagination { align-items: center; justify-content: space-between !important; }
.alert-pagination > span { color: var(--muted); font-size: 0.63rem; }
.alert-pagination > div { display: flex; align-items: center; gap: 0.45rem; }
.alert-pagination > div strong { min-width: 96px; color: #526071; font-size: 0.64rem; text-align: center; }
.day-kpis { display: grid; grid-template-columns: repeat(4, 1fr); margin: 1rem; border: 1px solid var(--line); }
.day-kpis > div { padding: 0.65rem; } .day-kpis > div + div { border-left: 1px solid var(--line); }
.day-kpis span, .day-kpis strong { display: block; } .day-kpis span { color: var(--muted); font-size: 0.63rem; } .day-kpis strong { margin-top: 0.12rem; font-size: 0.9rem; }
.anomaly-callout { display: flex; align-items: center; gap: 0.55rem; margin: 0 1rem 0.8rem; padding: 0.65rem; border: 1px solid #f0cf86; background: #fff8e7; color: #805b10; }
.anomaly-callout > i { font-size: 1.25rem; } .anomaly-callout > div { flex: 1; } .anomaly-callout strong, .anomaly-callout span { display: block; } .anomaly-callout strong { font-size: 0.72rem; } .anomaly-callout span { margin-top: 0.1rem; font-size: 0.65rem; }
.day-table-wrap { max-height: 55vh; border-top: 1px solid var(--line); }
.day-table-wrap td strong, .day-table-wrap td small { display: block; } .day-table-wrap td small { color: var(--muted); font-size: 0.6rem; }
.day-status { display: inline-flex; padding: 0.2rem 0.38rem; border-radius: 4px; font-size: 0.63rem; font-weight: 700; }
.day-present { background: #e7f5ec; color: #28764d; } .day-absent { background: #fbeaec; color: #b42332; }
.detail-kpis { display: grid; grid-template-columns: repeat(4, 1fr); margin: 1rem; border: 1px solid var(--line); }
.detail-kpis > div { padding: 0.7rem; } .detail-kpis > div + div { border-left: 1px solid var(--line); }
.detail-kpis span, .detail-kpis strong { display: block; } .detail-kpis span { color: var(--muted); font-size: 0.64rem; } .detail-kpis strong { margin-top: 0.15rem; font-size: 0.9rem; }
.detail-surface > h6 { margin: 1rem 1rem 0.5rem; font-size: 0.76rem; }
.month-list { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.45rem; margin: 0 1rem; }
.month-list > div { padding: 0.6rem; border: 1px solid var(--line); } .month-list span, .month-list strong, .month-list small { display: block; } .month-list span, .month-list small { color: var(--muted); font-size: 0.62rem; } .month-list strong { margin: 0.12rem 0; font-size: 0.82rem; }
.absence-list { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0 1rem 1rem; } .absence-list > span, .absence-list button { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.45rem; border: 1px solid transparent; background: #f4f6f8; color: #526071; font-size: 0.65rem; } .absence-list button:not(:disabled):hover { border-color: #28764d; color: #28764d; } .absence-list button:disabled { cursor: default; opacity: 1; }
.followup-context { margin: 0; padding: 0.7rem 1rem; background: #f7f9fb; color: #526071; font-size: 0.7rem; }
.followup-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.7rem; padding: 1rem; }
.followup-grid .full { grid-column: 1 / -1; }
.followup-grid .form-select, .followup-grid .form-control { margin-top: 0.25rem; font-size: 0.72rem; }
@media (max-width: 1100px) { .attendance-kpis { grid-template-columns: repeat(4, 1fr); } .attendance-grid-main { grid-template-columns: 1fr; } }
@media (max-width: 767px) { .attendance-toolbar { align-items: stretch; flex-direction: column; } .attendance-actions .btn { flex: 1; justify-content: center; } .attendance-filters { grid-template-columns: 1fr 1fr; } .attendance-grid-equal { grid-template-columns: 1fr; } .calendar-grid { grid-template-columns: repeat(4, 1fr); } .student-heading { align-items: stretch; flex-direction: column; } .search-input { width: 100%; } .scenario-row { grid-template-columns: 1fr 1fr; } .scenario-row > div:first-child { grid-column: 1 / -1; } .import-summary { grid-template-columns: repeat(2, 1fr); } .import-summary > div { border: 0 !important; border-bottom: 1px solid var(--line) !important; } .settings-form { grid-template-columns: 1fr 1fr; } .alert-group-row { grid-template-columns: 34px minmax(0, 1fr) 68px; } .alert-group-metrics { grid-row: 2; grid-column: 2 / -1; justify-content: flex-start; } .alert-detail-filters { grid-template-columns: 1fr 1fr auto 30px; } .alert-search { grid-column: 1 / -1; } }
@media (max-width: 520px) { .attendance-actions { flex-wrap: wrap; } .attendance-filters { grid-template-columns: 1fr; } .attendance-kpis { grid-template-columns: repeat(2, 1fr); } .attendance-kpis article:nth-child(odd) { border-left: 0; } .attendance-kpis article:nth-child(n + 3) { border-top: 1px solid var(--line); } .calendar-grid { grid-template-columns: repeat(3, 1fr); } .alert-group-row { grid-template-columns: 32px minmax(0, 1fr) 60px; } .alert-type-breakdown { display: none; } .alert-group-open strong { display: none; } .alert-row { grid-template-columns: 26px 1fr; } .alert-actions { grid-column: 1 / -1; justify-content: flex-end; } .detail-kpis, .month-list, .day-kpis, .alert-detail-kpis { grid-template-columns: repeat(2, 1fr); } .alert-detail-kpis > div:nth-child(3) { border-left: 0; } .alert-detail-kpis > div:nth-child(n + 3) { border-top: 1px solid var(--line); } .alert-detail-filters { grid-template-columns: 1fr auto 30px; } .alert-detail-filters label { grid-column: 1 / -1; } .day-kpis > div:nth-child(3) { border-left: 0; } .day-kpis > div:nth-child(n + 3) { border-top: 1px solid var(--line); } .anomaly-callout { align-items: flex-start; flex-wrap: wrap; } .anomaly-callout .btn { width: 100%; justify-content: center; } .followup-grid { grid-template-columns: 1fr; } .followup-grid .full { grid-column: auto; } .attendance-modal { align-items: end; padding: 0.5rem; } .modal-surface, .import-surface, .import-surface--preview, .alert-detail-surface { width: 100%; max-height: calc(100dvh - 1rem); } .modal-surface > header, .upload-step, .modal-surface footer { padding-inline: 0.8rem; } .file-drop { min-height: 160px; } .modal-surface footer .btn { flex: 1; } .alert-pagination > div strong { min-width: auto; } }
@media (max-width: 767px) { .student-panel-count { text-align: left; } .student-list-kpis { grid-template-columns: repeat(3, 1fr); } .student-list-kpis > div { border-left: 0 !important; border-bottom: 1px solid var(--line); } .student-list-filters { grid-template-columns: 1fr auto 30px; } .student-list-filters label { grid-column: 1 / -1; } }
@media (max-width: 520px) { .student-list-surface { width: 100%; max-height: calc(100dvh - 1rem); } .student-list-kpis { grid-template-columns: repeat(2, 1fr); } .student-list-pagination > div strong { min-width: auto; } }
</style>
