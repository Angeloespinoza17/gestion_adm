<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const routeMap = {
  "/schedule/teacher": "teacher",
  "/schedule/course": "course",
  "/schedule/config": "config",
  "/schedule/jornadas": "jornadas",
  "/schedule/study-plans": "studyPlans",
  "/schedule/contracts": "contracts",
  "/schedule/conflicts": "conflicts",
};

const emptyJornadaForm = () => ({
  id: null,
  academic_year_id: null,
  name: "",
  start_time: "08:00",
  end_time: "15:30",
  days_of_week: [1, 2, 3, 4, 5],
  active: true,
  notes: "",
  blocks: [],
});

const emptySubjectForm = () => ({
  id: null,
  name: "",
  code: "",
  color: "#0d6efd",
  area: "",
  active: true,
});

const emptyStudyPlanForm = () => ({
  id: null,
  academic_year_id: null,
  education_level_id: null,
  course_section_id: null,
  name: "",
  active: true,
});

const emptyContractForm = () => ({
  id: null,
  staff_id: null,
  academic_year_id: null,
  weekly_contract_hours: 44,
  hour_type: "chronological",
  lective_percentage: 65,
  non_lective_percentage: 35,
  valid_from: "",
  valid_to: "",
  active: true,
});

const emptyLayerForm = () => ({
  id: null,
  academic_year_id: null,
  name: "",
  type: "lective",
  color: "#0d6efd",
  visible_by_default: true,
  priority: 10,
  active: true,
});

const emptyEventForm = () => ({
  id: null,
  academic_year_id: null,
  staff_id: null,
  teacher_schedule_layer_id: null,
  course_section_id: null,
  education_level_id: null,
  schedule_subject_id: null,
  school_day_template_id: null,
  school_day_block_id: null,
  day_of_week: 1,
  start_time: "08:00",
  end_time: "08:45",
  activity_type: "lective_class",
  room_name: "",
  status: "draft",
  source: "manual",
  notes: "",
  force_validation_exception: false,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: true,
      saving: false,
      error: null,
      success: null,
      catalogs: {
        academic_years: [],
        jornadas: [],
        education_levels: [],
        courses: [],
        subjects: [],
        teachers: [],
        layer_types: [],
        activity_types: [],
        days_of_week: [],
      },
      selectedAcademicYearId: null,
      selectedTeacherId: null,
      selectedCourseId: null,
      visibleLayerIds: [],
      config: null,
      configForm: {
        academic_year_id: null,
        pedagogical_hour_minutes: 45,
        default_lective_percentage: 65,
        default_non_lective_percentage: 35,
        calculation_base: "pedagogical",
        rounding_mode: "nearest",
        strict_validation_enabled: true,
      },
      jornadas: [],
      subjects: [],
      studyPlans: [],
      contracts: [],
      layers: [],
      events: [],
      conflicts: [],
      teacherSummary: null,
      courseSummary: null,
      studyPlanProgress: [],
      showJornadaModal: false,
      showSubjectModal: false,
      showStudyPlanModal: false,
      showContractModal: false,
      showLayerModal: false,
      showEventModal: false,
      jornadaForm: emptyJornadaForm(),
      subjectForm: emptySubjectForm(),
      studyPlanForm: emptyStudyPlanForm(),
      studyPlanSubjectForm: { study_plan_id: null, schedule_subject_id: null, weekly_pedagogical_hours: 2, required: true, notes: "" },
      contractForm: emptyContractForm(),
      layerForm: emptyLayerForm(),
      eventForm: emptyEventForm(),
      assignmentForm: { jornada_id: null, level_ids: [], course_ids: [] },
    };
  },
  computed: {
    activeTab() {
      return routeMap[this.$route.path] || "teacher";
    },
    tabs() {
      return [
        { key: "teacher", route: "/schedule/teacher", label: "Docente" },
        { key: "course", route: "/schedule/course", label: "Curso" },
        { key: "config", route: "/schedule/config", label: "Configuracion" },
        { key: "jornadas", route: "/schedule/jornadas", label: "Jornadas" },
        { key: "studyPlans", route: "/schedule/study-plans", label: "Plan de estudio" },
        { key: "contracts", route: "/schedule/contracts", label: "Contratos" },
        { key: "conflicts", route: "/schedule/conflicts", label: "Conflictos" },
      ];
    },
    activeTitle() {
      return this.tabs.find((tab) => tab.key === this.activeTab)?.label || "Horarios docentes";
    },
    academicYearOptions() {
      return (this.catalogs.academic_years || []).map((year) => ({
        value: year.id,
        text: `${year.name}${year.is_active ? " · activo" : ""}`,
      }));
    },
    teacherOptions() {
      return [{ value: null, text: "Seleccionar docente" }].concat(
        (this.catalogs.teachers || []).map((teacher) => ({ value: teacher.id, text: teacher.full_name }))
      );
    },
    courseOptions() {
      return [{ value: null, text: "Seleccionar curso" }].concat(
        (this.catalogs.courses || []).map((course) => ({ value: course.id, text: course.display_name }))
      );
    },
    levelOptions() {
      return [{ value: null, text: "Nivel completo" }].concat(
        (this.catalogs.education_levels || []).map((level) => ({ value: level.id, text: level.name }))
      );
    },
    subjectOptions() {
      return [{ value: null, text: "Sin asignatura" }].concat(
        (this.subjects || []).map((subject) => ({ value: subject.id, text: subject.name }))
      );
    },
    jornadaOptions() {
      return [{ value: null, text: "Sin jornada directa" }].concat(
        (this.jornadas || []).map((jornada) => ({ value: jornada.id, text: jornada.name }))
      );
    },
    layerOptions() {
      return [{ value: null, text: "Seleccionar capa" }].concat(
        (this.layers || []).map((layer) => ({ value: layer.id, text: `${layer.name} · ${this.layerTypeLabel(layer.type)}` }))
      );
    },
    selectedTeacher() {
      return (this.catalogs.teachers || []).find((teacher) => teacher.id === this.selectedTeacherId) || null;
    },
    selectedCourse() {
      return (this.catalogs.courses || []).find((course) => course.id === this.selectedCourseId) || null;
    },
    selectedCourseJornada() {
      if (!this.selectedCourse) return null;
      const directId = this.selectedCourse.school_day_template_id;
      const inheritedId = this.selectedCourse.education_level?.default_school_day_template_id;
      return this.jornadas.find((jornada) => jornada.id === (directId || inheritedId)) || null;
    },
    timeSlots() {
      const jornada = this.activeTab === "course" ? this.selectedCourseJornada : null;
      const start = jornada?.start_time || "08:00";
      const end = jornada?.end_time || "16:15";
      const slots = [];
      let cursor = this.toMinutes(start);
      const limit = this.toMinutes(end);
      const step = Number(this.configForm.pedagogical_hour_minutes || 45);
      while (cursor < limit) {
        slots.push({ start: this.fromMinutes(cursor), end: this.fromMinutes(Math.min(cursor + step, limit)) });
        cursor += step;
      }
      return slots;
    },
    teacherEvents() {
      return this.events
        .filter((event) => event.staff_id === this.selectedTeacherId)
        .filter((event) => this.visibleLayerIds.includes(event.teacher_schedule_layer_id));
    },
    courseEvents() {
      return this.events.filter((event) => event.course_section_id === this.selectedCourseId);
    },
    days() {
      return this.catalogs.days_of_week?.slice(0, 5) || [];
    },
    dayOptions() {
      return this.days.map((day) => ({ value: day.value, text: day.label }));
    },
  },
  watch: {
    selectedTeacherId() {
      this.loadLayers();
      this.loadTeacherSummary();
    },
    selectedCourseId() {
      this.loadCourseSummary();
      this.loadStudyPlanProgress();
    },
  },
  async mounted() {
    await this.loadAll();
  },
  methods: {
    async loadAll() {
      this.loading = true;
      this.error = null;
      try {
        await this.loadCatalogs();
        await Promise.all([
          this.loadConfig(),
          this.loadJornadas(),
          this.loadSubjects(),
          this.loadStudyPlans(),
          this.loadContracts(),
          this.loadEvents(),
          this.loadConflicts(),
        ]);
        await this.loadLayers();
        await this.loadTeacherSummary();
        await this.loadCourseSummary();
        await this.loadStudyPlanProgress();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async loadCatalogs() {
      const response = await axios.get("/api/schedule/catalogs");
      this.catalogs = response.data;
      this.selectedAcademicYearId = this.selectedAcademicYearId || response.data.active_academic_year_id;
      this.selectedTeacherId = this.selectedTeacherId || response.data.teachers?.[0]?.id || null;
      this.selectedCourseId = this.selectedCourseId || response.data.courses?.[0]?.id || null;
    },
    async refreshForYear() {
      this.configForm.academic_year_id = this.selectedAcademicYearId;
      await Promise.all([this.loadConfig(), this.loadJornadas(), this.loadStudyPlans(), this.loadContracts(), this.loadEvents(), this.loadConflicts()]);
      await this.loadLayers();
      await this.loadTeacherSummary();
      await this.loadCourseSummary();
      await this.loadStudyPlanProgress();
    },
    async loadConfig() {
      const response = await axios.get("/api/schedule/config", { params: { academic_year_id: this.selectedAcademicYearId } });
      this.config = response.data.data;
      this.configForm = {
        academic_year_id: this.config.academic_year_id,
        pedagogical_hour_minutes: this.config.pedagogical_hour_minutes,
        default_lective_percentage: this.config.default_lective_percentage,
        default_non_lective_percentage: this.config.default_non_lective_percentage,
        calculation_base: this.config.calculation_base,
        rounding_mode: this.config.rounding_mode,
        strict_validation_enabled: Boolean(this.config.strict_validation_enabled),
      };
    },
    async saveConfig() {
      await this.persist(async () => axios.put("/api/schedule/config", this.configForm), "Configuracion actualizada.");
      await this.loadConfig();
    },
    async loadJornadas() {
      const response = await axios.get("/api/schedule/jornadas", { params: { academic_year_id: this.selectedAcademicYearId } });
      this.jornadas = response.data.data || [];
    },
    openCreateJornada() {
      this.jornadaForm = { ...emptyJornadaForm(), academic_year_id: this.selectedAcademicYearId };
      this.addDefaultBlocks();
      this.showJornadaModal = true;
    },
    openEditJornada(jornada) {
      this.jornadaForm = {
        id: jornada.id,
        academic_year_id: jornada.academic_year_id,
        name: jornada.name,
        start_time: this.trimTime(jornada.start_time),
        end_time: this.trimTime(jornada.end_time),
        days_of_week: jornada.days_of_week || [1, 2, 3, 4, 5],
        active: Boolean(jornada.active),
        notes: jornada.notes || "",
        blocks: (jornada.blocks || []).map((block) => ({
          day_of_week: block.day_of_week,
          start_time: this.trimTime(block.start_time),
          end_time: this.trimTime(block.end_time),
          type: block.type,
          label: block.label,
          order: block.order,
          assignable: Boolean(block.assignable),
          pedagogical_hours_equivalent: block.pedagogical_hours_equivalent,
        })),
      };
      this.showJornadaModal = true;
    },
    addDefaultBlocks() {
      this.jornadaForm.blocks = [1, 2, 3, 4, 5].flatMap((day) => [
        { day_of_week: day, start_time: "08:00", end_time: "08:45", type: "pedagogical_block", label: "Bloque 1", assignable: true, order: 1, pedagogical_hours_equivalent: 1 },
        { day_of_week: day, start_time: "08:45", end_time: "09:30", type: "pedagogical_block", label: "Bloque 2", assignable: true, order: 2, pedagogical_hours_equivalent: 1 },
        { day_of_week: day, start_time: "09:30", end_time: "09:45", type: "recess", label: "Recreo", assignable: false, order: 3, pedagogical_hours_equivalent: null },
      ]);
    },
    addJornadaBlock() {
      this.jornadaForm.blocks.push({
        day_of_week: 1,
        start_time: "08:00",
        end_time: "08:45",
        type: "pedagogical_block",
        label: "Bloque",
        order: this.jornadaForm.blocks.length + 1,
        assignable: true,
        pedagogical_hours_equivalent: 1,
      });
    },
    removeJornadaBlock(index) {
      this.jornadaForm.blocks.splice(index, 1);
    },
    async saveJornada() {
      const payload = { ...this.jornadaForm };
      await this.persist(
        async () => (payload.id ? axios.put(`/api/schedule/jornadas/${payload.id}`, payload) : axios.post("/api/schedule/jornadas", payload)),
        "Jornada guardada."
      );
      this.showJornadaModal = false;
      await this.loadJornadas();
      await this.loadCatalogs();
    },
    async duplicateJornada(jornada) {
      await this.persist(() => axios.post(`/api/schedule/jornadas/${jornada.id}/duplicate`, { name: `${jornada.name} copia` }), "Jornada duplicada.");
      await this.loadJornadas();
    },
    async assignJornada() {
      if (!this.assignmentForm.jornada_id) return;
      await this.persist(async () => {
        await axios.post(`/api/schedule/jornadas/${this.assignmentForm.jornada_id}/assign-levels`, { level_ids: this.assignmentForm.level_ids });
        await axios.post(`/api/schedule/jornadas/${this.assignmentForm.jornada_id}/assign-courses`, { course_ids: this.assignmentForm.course_ids });
      }, "Asignacion de jornada actualizada.");
      await this.loadCatalogs();
    },
    async loadSubjects() {
      const response = await axios.get("/api/schedule/subjects");
      this.subjects = response.data.data || [];
    },
    openCreateSubject() {
      this.subjectForm = emptySubjectForm();
      this.showSubjectModal = true;
    },
    openEditSubject(subject) {
      this.subjectForm = { ...subject };
      this.showSubjectModal = true;
    },
    async saveSubject() {
      const payload = { ...this.subjectForm };
      await this.persist(
        async () => (payload.id ? axios.put(`/api/schedule/subjects/${payload.id}`, payload) : axios.post("/api/schedule/subjects", payload)),
        "Asignatura guardada."
      );
      this.showSubjectModal = false;
      await this.loadSubjects();
      await this.loadCatalogs();
    },
    async loadStudyPlans() {
      const response = await axios.get("/api/schedule/study-plans", { params: { academic_year_id: this.selectedAcademicYearId } });
      this.studyPlans = response.data.data || [];
    },
    openCreateStudyPlan() {
      this.studyPlanForm = { ...emptyStudyPlanForm(), academic_year_id: this.selectedAcademicYearId };
      this.showStudyPlanModal = true;
    },
    openEditStudyPlan(plan) {
      this.studyPlanForm = {
        id: plan.id,
        academic_year_id: plan.academic_year_id,
        education_level_id: plan.education_level_id,
        course_section_id: plan.course_section_id,
        name: plan.name,
        active: Boolean(plan.active),
      };
      this.showStudyPlanModal = true;
    },
    async saveStudyPlan() {
      const payload = { ...this.studyPlanForm };
      await this.persist(
        async () => (payload.id ? axios.put(`/api/schedule/study-plans/${payload.id}`, payload) : axios.post("/api/schedule/study-plans", payload)),
        "Plan de estudio guardado."
      );
      this.showStudyPlanModal = false;
      await this.loadStudyPlans();
    },
    async saveStudyPlanSubject() {
      const { study_plan_id, ...payload } = this.studyPlanSubjectForm;
      if (!study_plan_id) return;
      await this.persist(() => axios.post(`/api/schedule/study-plans/${study_plan_id}/subjects`, payload), "Horas de asignatura actualizadas.");
      await this.loadStudyPlans();
      await this.loadStudyPlanProgress();
    },
    async loadContracts() {
      const response = await axios.get("/api/schedule/teacher-contracts", { params: { academic_year_id: this.selectedAcademicYearId } });
      this.contracts = response.data.data || [];
    },
    openCreateContract() {
      this.contractForm = { ...emptyContractForm(), academic_year_id: this.selectedAcademicYearId };
      this.showContractModal = true;
    },
    openEditContract(contract) {
      this.contractForm = { ...contract };
      this.showContractModal = true;
    },
    async saveContract() {
      const payload = { ...this.contractForm };
      await this.persist(
        async () => (payload.id ? axios.put(`/api/schedule/teacher-contracts/${payload.id}`, payload) : axios.post("/api/schedule/teacher-contracts", payload)),
        "Contrato docente guardado."
      );
      this.showContractModal = false;
      await this.loadContracts();
      await this.loadTeacherSummary();
    },
    async loadLayers() {
      if (!this.selectedTeacherId) {
        this.layers = [];
        this.visibleLayerIds = [];
        return;
      }
      const response = await axios.get(`/api/schedule/teachers/${this.selectedTeacherId}/layers`, { params: { academic_year_id: this.selectedAcademicYearId } });
      this.layers = response.data.data || [];
      this.visibleLayerIds = this.layers.filter((layer) => layer.visible_by_default).map((layer) => layer.id);
    },
    openCreateLayer() {
      this.layerForm = { ...emptyLayerForm(), academic_year_id: this.selectedAcademicYearId };
      this.showLayerModal = true;
    },
    openEditLayer(layer) {
      this.layerForm = { ...layer };
      this.showLayerModal = true;
    },
    async saveLayer() {
      const payload = { ...this.layerForm };
      await this.persist(
        async () => (payload.id ? axios.put(`/api/schedule/layers/${payload.id}`, payload) : axios.post(`/api/schedule/teachers/${this.selectedTeacherId}/layers`, payload)),
        "Capa guardada."
      );
      this.showLayerModal = false;
      await this.loadLayers();
    },
    async loadEvents() {
      const response = await axios.get("/api/schedule/events", { params: { academic_year_id: this.selectedAcademicYearId } });
      this.events = response.data.data || [];
    },
    openCreateEvent(day = 1, slot = null) {
      const course = this.selectedCourse;
      this.eventForm = {
        ...emptyEventForm(),
        academic_year_id: this.selectedAcademicYearId,
        staff_id: this.selectedTeacherId,
        teacher_schedule_layer_id: this.layers[0]?.id || null,
        course_section_id: this.activeTab === "course" ? this.selectedCourseId : null,
        education_level_id: this.activeTab === "course" ? course?.education_level_id || null : null,
        school_day_template_id: this.activeTab === "course" ? this.selectedCourseJornada?.id || null : null,
        day_of_week: day,
        start_time: slot?.start || "08:00",
        end_time: slot?.end || "08:45",
      };
      this.showEventModal = true;
    },
    openEditEvent(event) {
      this.eventForm = {
        ...emptyEventForm(),
        ...event,
        start_time: this.trimTime(event.start_time),
        end_time: this.trimTime(event.end_time),
        force_validation_exception: false,
      };
      this.showEventModal = true;
    },
    async saveEvent() {
      const payload = { ...this.eventForm };
      await this.persist(
        async () => (payload.id ? axios.put(`/api/schedule/events/${payload.id}`, payload) : axios.post("/api/schedule/events", payload)),
        "Bloque de horario guardado."
      );
      this.showEventModal = false;
      await this.loadEvents();
      await this.loadConflicts();
      await this.loadTeacherSummary();
      await this.loadCourseSummary();
      await this.loadStudyPlanProgress();
    },
    async removeEvent(event) {
      const result = await Swal.fire({
        title: "Eliminar bloque",
        text: "Se eliminara el bloque seleccionado del horario.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;
      await this.persist(() => axios.delete(`/api/schedule/events/${event.id}`), "Bloque eliminado.");
      await this.loadEvents();
    },
    async loadTeacherSummary() {
      if (!this.selectedTeacherId || !this.selectedAcademicYearId) return;
      const response = await axios.get(`/api/schedule/teachers/${this.selectedTeacherId}/summary`, { params: { academic_year_id: this.selectedAcademicYearId } });
      this.teacherSummary = response.data.data;
    },
    async loadCourseSummary() {
      if (!this.selectedCourseId) return;
      const response = await axios.get(`/api/schedule/courses/${this.selectedCourseId}/summary`);
      this.courseSummary = response.data.data;
    },
    async loadStudyPlanProgress() {
      if (!this.selectedCourseId) return;
      const response = await axios.get(`/api/schedule/courses/${this.selectedCourseId}/study-plan-progress`);
      this.studyPlanProgress = response.data.data || [];
    },
    async loadConflicts() {
      const response = await axios.get("/api/schedule/conflicts", { params: { limit: 100 } });
      this.conflicts = response.data.data || [];
    },
    eventsForCell(events, day, slot) {
      return events.filter((event) => event.day_of_week === day && this.trimTime(event.start_time) === slot.start);
    },
    nonAssignableForCell(day, slot) {
      const blocks = this.selectedCourseJornada?.blocks || [];
      return blocks.find((block) => block.day_of_week === day && !block.assignable && this.trimTime(block.start_time) === slot.start);
    },
    layerTypeLabel(type) {
      return this.catalogs.layer_types?.find((item) => item.value === type)?.label || type;
    },
    activityTypeLabel(type) {
      return this.catalogs.activity_types?.find((item) => item.value === type)?.label || type;
    },
    subjectName(id) {
      return this.subjects.find((subject) => subject.id === id)?.name || "-";
    },
    toMinutes(time) {
      const [hours, minutes] = this.trimTime(time).split(":").map(Number);
      return (hours || 0) * 60 + (minutes || 0);
    },
    fromMinutes(minutes) {
      const hours = String(Math.floor(minutes / 60)).padStart(2, "0");
      const mins = String(minutes % 60).padStart(2, "0");
      return `${hours}:${mins}`;
    },
    trimTime(time) {
      return String(time || "").slice(0, 5);
    },
    async persist(action, successMessage) {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        await action();
        this.success = successMessage;
      } catch (error) {
        this.error = this.formatError(error);
        await Swal.fire({ title: "Error", text: this.error, icon: "error" });
        throw error;
      } finally {
        this.saving = false;
      }
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      if (errors) {
        const first = errors[Object.keys(errors)[0]];
        return Array.isArray(first) ? first.join(" ") : first;
      }
      return error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-column gap-3 schedule-module">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h4 class="mb-1">Horarios docentes</h4>
          <div class="text-muted">Jornadas, planes, contratos, capas, asignaciones y conflictos.</div>
        </div>
        <div class="d-flex gap-2 align-items-end flex-wrap">
          <div>
            <label class="form-label mb-1">Año académico</label>
            <BFormSelect v-model="selectedAcademicYearId" :options="academicYearOptions" @change="refreshForYear" />
          </div>
          <BButton variant="outline-secondary" @click="loadAll">Actualizar</BButton>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <router-link v-for="tab in tabs" :key="tab.key" :to="tab.route" class="btn" :class="activeTab === tab.key ? 'btn-primary' : 'btn-outline-secondary'">
          {{ tab.label }}
        </router-link>
      </div>

      <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
      <BAlert v-if="success" show variant="success">{{ success }}</BAlert>

      <BCard v-if="loading" class="border-0 shadow-sm">
        <LoadingState message="Cargando modulo de horarios..." compact />
      </BCard>

      <template v-else>
        <section v-if="activeTab === 'config'">
          <BCard class="border-0 shadow-sm">
            <h5 class="mb-3">Configuración general</h5>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Duración hora pedagógica</label>
                <BFormInput v-model="configForm.pedagogical_hour_minutes" type="number" min="20" max="120" />
              </div>
              <div class="col-md-3">
                <label class="form-label">% lectivo</label>
                <BFormInput v-model="configForm.default_lective_percentage" type="number" step="0.01" />
              </div>
              <div class="col-md-3">
                <label class="form-label">% no lectivo</label>
                <BFormInput v-model="configForm.default_non_lective_percentage" type="number" step="0.01" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Base de cálculo</label>
                <BFormSelect v-model="configForm.calculation_base" :options="[{ value: 'pedagogical', text: 'Pedagógica' }, { value: 'chronological', text: 'Cronológica' }]" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Redondeo</label>
                <BFormSelect v-model="configForm.rounding_mode" :options="[{ value: 'none', text: 'Sin redondeo' }, { value: 'nearest', text: 'Más cercano' }, { value: 'up', text: 'Hacia arriba' }, { value: 'down', text: 'Hacia abajo' }]" />
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <BFormCheckbox v-model="configForm.strict_validation_enabled">Validaciones estrictas</BFormCheckbox>
              </div>
              <div class="col-md-5 d-flex justify-content-md-end align-items-end">
                <BButton variant="primary" :disabled="saving" @click="saveConfig">Guardar configuración</BButton>
              </div>
            </div>
          </BCard>
        </section>

        <section v-if="activeTab === 'jornadas'" class="d-flex flex-column gap-3">
          <div class="d-flex justify-content-between flex-wrap gap-2">
            <h5 class="mb-0">Administrador de jornadas</h5>
            <BButton variant="primary" @click="openCreateJornada">Nueva jornada</BButton>
          </div>
          <BCard class="border-0 shadow-sm">
            <BTable :items="jornadas" responsive :fields="[
              { key: 'name', label: 'Jornada' },
              { key: 'hours', label: 'Horario' },
              { key: 'blocks_count', label: 'Bloques' },
              { key: 'active', label: 'Estado' },
              { key: 'actions', label: 'Acciones' },
            ]">
              <template #cell(hours)="{ item }">{{ trimTime(item.start_time) }} - {{ trimTime(item.end_time) }}</template>
              <template #cell(active)="{ item }"><BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? 'Activa' : 'Inactiva' }}</BBadge></template>
              <template #cell(actions)="{ item }">
                <div class="d-flex gap-2 flex-wrap">
                  <BButton size="sm" variant="outline-primary" @click="openEditJornada(item)">Editar</BButton>
                  <BButton size="sm" variant="outline-secondary" @click="duplicateJornada(item)">Duplicar</BButton>
                </div>
              </template>
            </BTable>
          </BCard>

          <BCard class="border-0 shadow-sm">
            <h6 class="mb-3">Asignar jornada a niveles o cursos</h6>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Jornada</label>
                <BFormSelect v-model="assignmentForm.jornada_id" :options="jornadaOptions" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Niveles</label>
                <BFormSelect v-model="assignmentForm.level_ids" :options="levelOptions.filter((item) => item.value)" multiple />
              </div>
              <div class="col-md-4">
                <label class="form-label">Cursos</label>
                <BFormSelect v-model="assignmentForm.course_ids" :options="courseOptions.filter((item) => item.value)" multiple />
              </div>
              <div class="col-12 d-flex justify-content-end">
                <BButton variant="primary" @click="assignJornada">Aplicar asignación</BButton>
              </div>
            </div>
          </BCard>
        </section>

        <section v-if="activeTab === 'studyPlans'" class="d-flex flex-column gap-3">
          <div class="d-flex justify-content-between flex-wrap gap-2">
            <h5 class="mb-0">Asignaturas y plan de estudio</h5>
            <div class="d-flex gap-2">
              <BButton variant="outline-primary" @click="openCreateSubject">Nueva asignatura</BButton>
              <BButton variant="primary" @click="openCreateStudyPlan">Nuevo plan</BButton>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-xl-5">
              <BCard class="border-0 shadow-sm h-100">
                <BTable :items="subjects" small responsive :fields="[
                  { key: 'name', label: 'Asignatura' },
                  { key: 'code', label: 'Codigo' },
                  { key: 'active', label: 'Estado' },
                  { key: 'actions', label: '' },
                ]">
                  <template #cell(name)="{ item }"><span class="subject-dot" :style="{ background: item.color }"></span>{{ item.name }}</template>
                  <template #cell(active)="{ item }"><BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? 'Activa' : 'Inactiva' }}</BBadge></template>
                  <template #cell(actions)="{ item }"><BButton size="sm" variant="outline-secondary" @click="openEditSubject(item)">Editar</BButton></template>
                </BTable>
              </BCard>
            </div>
            <div class="col-xl-7">
              <BCard class="border-0 shadow-sm h-100">
                <BTable :items="studyPlans" small responsive :fields="[
                  { key: 'name', label: 'Plan' },
                  { key: 'target', label: 'Aplicacion' },
                  { key: 'subjects', label: 'Asignaturas' },
                  { key: 'active', label: 'Estado' },
                  { key: 'actions', label: '' },
                ]">
                  <template #cell(target)="{ item }">{{ item.course_section?.display_name || item.education_level?.name || 'General' }}</template>
                  <template #cell(subjects)="{ item }">{{ (item.subjects || []).length }}</template>
                  <template #cell(active)="{ item }"><BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? 'Activo' : 'Inactivo' }}</BBadge></template>
                  <template #cell(actions)="{ item }"><BButton size="sm" variant="outline-secondary" @click="openEditStudyPlan(item)">Editar</BButton></template>
                </BTable>

                <div class="border-top pt-3 mt-2">
                  <h6>Agregar o actualizar horas</h6>
                  <div class="row g-2">
                    <div class="col-md-4"><BFormSelect v-model="studyPlanSubjectForm.study_plan_id" :options="studyPlans.map((plan) => ({ value: plan.id, text: plan.name }))" /></div>
                    <div class="col-md-4"><BFormSelect v-model="studyPlanSubjectForm.schedule_subject_id" :options="subjectOptions.filter((item) => item.value)" /></div>
                    <div class="col-md-2"><BFormInput v-model="studyPlanSubjectForm.weekly_pedagogical_hours" type="number" step="0.5" /></div>
                    <div class="col-md-2"><BButton class="w-100" variant="primary" @click="saveStudyPlanSubject">Guardar</BButton></div>
                  </div>
                </div>
              </BCard>
            </div>
          </div>
        </section>

        <section v-if="activeTab === 'contracts'" class="d-flex flex-column gap-3">
          <div class="d-flex justify-content-between flex-wrap gap-2">
            <h5 class="mb-0">Contratos docentes</h5>
            <BButton variant="primary" @click="openCreateContract">Nuevo contrato</BButton>
          </div>
          <BCard class="border-0 shadow-sm">
            <BTable :items="contracts" responsive :fields="[
              { key: 'teacher', label: 'Docente' },
              { key: 'weekly_contract_hours', label: 'Total' },
              { key: 'calculated_lective_hours', label: 'Lectivas' },
              { key: 'calculated_non_lective_hours', label: 'No lectivas' },
              { key: 'active', label: 'Estado' },
              { key: 'actions', label: '' },
            ]">
              <template #cell(teacher)="{ item }">{{ item.teacher?.full_name }}</template>
              <template #cell(active)="{ item }"><BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? 'Activo' : 'Inactivo' }}</BBadge></template>
              <template #cell(actions)="{ item }"><BButton size="sm" variant="outline-secondary" @click="openEditContract(item)">Editar</BButton></template>
            </BTable>
          </BCard>
        </section>

        <section v-if="activeTab === 'teacher'" class="d-flex flex-column gap-3">
          <BCard class="border-0 shadow-sm">
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label">Docente</label>
                <BFormSelect v-model="selectedTeacherId" :options="teacherOptions" />
              </div>
              <div class="col-md-8 d-flex gap-2 flex-wrap justify-content-md-end">
                <BButton variant="outline-secondary" @click="openCreateLayer">Nueva capa</BButton>
                <BButton variant="primary" @click="openCreateEvent()">Agregar bloque</BButton>
              </div>
            </div>
          </BCard>

          <div class="row g-3">
            <div class="col-xl-3">
              <BCard class="border-0 shadow-sm h-100">
                <h6>Capas</h6>
                <div v-for="layer in layers" :key="layer.id" class="d-flex justify-content-between align-items-center py-2 border-bottom">
                  <BFormCheckbox v-model="visibleLayerIds" :value="layer.id">
                    <span class="subject-dot" :style="{ background: layer.color }"></span>{{ layer.name }}
                  </BFormCheckbox>
                  <BButton size="sm" variant="link" @click="openEditLayer(layer)">Editar</BButton>
                </div>
              </BCard>
            </div>
            <div class="col-xl-9">
              <BCard class="border-0 shadow-sm">
                <div class="row g-2 mb-3">
                  <div class="col-md-3"><div class="metric"><span>Total</span><strong>{{ teacherSummary?.assigned_total_hours || 0 }} / {{ teacherSummary?.expected_total_hours || 0 }}</strong></div></div>
                  <div class="col-md-3"><div class="metric"><span>Lectivas</span><strong>{{ teacherSummary?.assigned_lective_hours || 0 }} / {{ teacherSummary?.expected_lective_hours || 0 }}</strong></div></div>
                  <div class="col-md-3"><div class="metric"><span>No lectivas</span><strong>{{ teacherSummary?.assigned_non_lective_hours || 0 }} / {{ teacherSummary?.expected_non_lective_hours || 0 }}</strong></div></div>
                  <div class="col-md-3"><div class="metric"><span>Saldo</span><strong>{{ teacherSummary?.total_balance || 0 }}</strong></div></div>
                </div>
                <div class="schedule-grid">
                  <div class="grid-head">Hora</div>
                  <div v-for="day in days" :key="day.value" class="grid-head">{{ day.label }}</div>
                  <template v-for="slot in timeSlots" :key="slot.start">
                    <div class="grid-time">{{ slot.start }}</div>
                    <div v-for="day in days" :key="`${day.value}-${slot.start}`" class="grid-cell" @dblclick="openCreateEvent(day.value, slot)">
                      <div v-for="event in eventsForCell(teacherEvents, day.value, slot)" :key="event.id" class="event-chip" :style="{ borderColor: event.layer?.color, background: `${event.layer?.color || '#0d6efd'}18` }" @click.stop="openEditEvent(event)">
                        <strong>{{ event.subject?.name || activityTypeLabel(event.activity_type) }}</strong>
                        <small>{{ event.course_section?.display_name || event.notes }}</small>
                        <BBadge v-if="event.validation_issues?.length" variant="danger">conflicto</BBadge>
                      </div>
                    </div>
                  </template>
                </div>
              </BCard>
            </div>
          </div>
        </section>

        <section v-if="activeTab === 'course'" class="d-flex flex-column gap-3">
          <BCard class="border-0 shadow-sm">
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label">Curso</label>
                <BFormSelect v-model="selectedCourseId" :options="courseOptions" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Jornada aplicada</label>
                <BFormInput :model-value="selectedCourseJornada?.name || 'Sin jornada configurada'" disabled />
              </div>
              <div class="col-md-4 d-flex justify-content-md-end">
                <BButton variant="primary" @click="openCreateEvent()">Agregar bloque</BButton>
              </div>
            </div>
          </BCard>

          <div class="row g-3">
            <div class="col-xl-8">
              <BCard class="border-0 shadow-sm">
                <div class="schedule-grid">
                  <div class="grid-head">Hora</div>
                  <div v-for="day in days" :key="day.value" class="grid-head">{{ day.label }}</div>
                  <template v-for="slot in timeSlots" :key="slot.start">
                    <div class="grid-time">{{ slot.start }}</div>
                    <div v-for="day in days" :key="`${day.value}-${slot.start}`" class="grid-cell" :class="{ 'grid-cell--blocked': nonAssignableForCell(day.value, slot) }" @dblclick="openCreateEvent(day.value, slot)">
                      <div v-if="nonAssignableForCell(day.value, slot)" class="blocked-label">{{ nonAssignableForCell(day.value, slot).label }}</div>
                      <div v-for="event in eventsForCell(courseEvents, day.value, slot)" :key="event.id" class="event-chip" :style="{ borderColor: event.subject?.color, background: `${event.subject?.color || '#0d6efd'}18` }" @click.stop="openEditEvent(event)">
                        <strong>{{ event.subject?.name || activityTypeLabel(event.activity_type) }}</strong>
                        <small>{{ event.teacher?.full_name }}</small>
                        <BBadge v-if="event.validation_issues?.length" variant="danger">conflicto</BBadge>
                      </div>
                    </div>
                  </template>
                </div>
              </BCard>
            </div>
            <div class="col-xl-4">
              <BCard class="border-0 shadow-sm h-100">
                <h6>Avance plan de estudio</h6>
                <div v-if="!studyPlanProgress.length" class="text-muted">Sin plan vigente para este curso o nivel.</div>
                <div v-for="item in studyPlanProgress" :key="item.subject_id" class="plan-progress">
                  <div class="d-flex justify-content-between">
                    <span><span class="subject-dot" :style="{ background: item.subject_color }"></span>{{ item.subject_name }}</span>
                    <strong>{{ item.assigned_hours }} / {{ item.required_hours }}</strong>
                  </div>
                  <div class="progress mt-1">
                    <div class="progress-bar" :class="item.exceeded_hours > 0 ? 'bg-warning' : 'bg-primary'" :style="{ width: `${Math.min(100, (item.assigned_hours / Math.max(1, item.required_hours)) * 100)}%` }"></div>
                  </div>
                  <small :class="item.exceeded_hours > 0 ? 'text-warning' : 'text-muted'">Pendiente {{ item.pending_hours }} · Exceso {{ item.exceeded_hours }}</small>
                </div>
              </BCard>
            </div>
          </div>
        </section>

        <section v-if="activeTab === 'conflicts'">
          <BCard class="border-0 shadow-sm">
            <div class="d-flex justify-content-between mb-3">
              <h5 class="mb-0">Conflictos activos</h5>
              <BButton variant="outline-secondary" @click="loadConflicts">Actualizar</BButton>
            </div>
            <BTable :items="conflicts" responsive :fields="[
              { key: 'severity', label: 'Severidad' },
              { key: 'code', label: 'Regla' },
              { key: 'message', label: 'Mensaje' },
              { key: 'context', label: 'Contexto' },
            ]">
              <template #cell(severity)="{ item }"><BBadge :variant="item.severity === 'error' ? 'danger' : 'warning'">{{ item.severity }}</BBadge></template>
              <template #cell(context)="{ item }">
                {{ item.schedule_event?.teacher?.full_name || '-' }} · {{ item.schedule_event?.course_section?.display_name || '-' }}
              </template>
            </BTable>
          </BCard>
        </section>
      </template>
    </div>

    <BModal v-model="showJornadaModal" :title="jornadaForm.id ? 'Editar jornada' : 'Nueva jornada'" size="xl" hide-footer>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Nombre</label><BFormInput v-model="jornadaForm.name" /></div>
        <div class="col-md-2"><label class="form-label">Inicio</label><BFormInput v-model="jornadaForm.start_time" type="time" /></div>
        <div class="col-md-2"><label class="form-label">Termino</label><BFormInput v-model="jornadaForm.end_time" type="time" /></div>
        <div class="col-md-4 d-flex align-items-end"><BFormCheckbox v-model="jornadaForm.active">Activa</BFormCheckbox></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="jornadaForm.notes" rows="2" /></div>
      </div>
      <div class="d-flex justify-content-between align-items-center my-3">
        <h6 class="mb-0">Bloques</h6>
        <BButton size="sm" variant="outline-primary" @click="addJornadaBlock">Agregar bloque</BButton>
      </div>
      <div class="jornada-block-list">
        <div v-for="(block, index) in jornadaForm.blocks" :key="index" class="row g-2 align-items-end jornada-block-row">
          <div class="col-md-2"><label class="form-label">Dia</label><BFormSelect v-model="block.day_of_week" :options="dayOptions" /></div>
          <div class="col-md-2"><label class="form-label">Inicio</label><BFormInput v-model="block.start_time" type="time" /></div>
          <div class="col-md-2"><label class="form-label">Termino</label><BFormInput v-model="block.end_time" type="time" /></div>
          <div class="col-md-2"><label class="form-label">Tipo</label><BFormSelect v-model="block.type" :options="['pedagogical_block', 'recess', 'lunch', 'non_assignable']" /></div>
          <div class="col-md-2"><label class="form-label">Etiqueta</label><BFormInput v-model="block.label" /></div>
          <div class="col-md-1"><BFormCheckbox v-model="block.assignable">Asignable</BFormCheckbox></div>
          <div class="col-md-1"><BButton size="sm" variant="outline-danger" @click="removeJornadaBlock(index)">Quitar</BButton></div>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showJornadaModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="saveJornada">Guardar</BButton>
      </div>
    </BModal>

    <BModal v-model="showSubjectModal" :title="subjectForm.id ? 'Editar asignatura' : 'Nueva asignatura'" hide-footer>
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Nombre</label><BFormInput v-model="subjectForm.name" /></div>
        <div class="col-md-4"><label class="form-label">Codigo</label><BFormInput v-model="subjectForm.code" /></div>
        <div class="col-md-6"><label class="form-label">Area</label><BFormInput v-model="subjectForm.area" /></div>
        <div class="col-md-3"><label class="form-label">Color</label><BFormInput v-model="subjectForm.color" type="color" /></div>
        <div class="col-md-3 d-flex align-items-end"><BFormCheckbox v-model="subjectForm.active">Activa</BFormCheckbox></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3"><BButton variant="secondary" @click="showSubjectModal = false">Cancelar</BButton><BButton variant="primary" @click="saveSubject">Guardar</BButton></div>
    </BModal>

    <BModal v-model="showStudyPlanModal" :title="studyPlanForm.id ? 'Editar plan' : 'Nuevo plan'" hide-footer>
      <div class="row g-3">
        <div class="col-md-12"><label class="form-label">Nombre</label><BFormInput v-model="studyPlanForm.name" /></div>
        <div class="col-md-6"><label class="form-label">Nivel</label><BFormSelect v-model="studyPlanForm.education_level_id" :options="levelOptions" /></div>
        <div class="col-md-6"><label class="form-label">Curso especifico</label><BFormSelect v-model="studyPlanForm.course_section_id" :options="courseOptions" /></div>
        <div class="col-md-12"><BFormCheckbox v-model="studyPlanForm.active">Plan activo</BFormCheckbox></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3"><BButton variant="secondary" @click="showStudyPlanModal = false">Cancelar</BButton><BButton variant="primary" @click="saveStudyPlan">Guardar</BButton></div>
    </BModal>

    <BModal v-model="showContractModal" :title="contractForm.id ? 'Editar contrato' : 'Nuevo contrato'" hide-footer>
      <div class="row g-3">
        <div class="col-md-12"><label class="form-label">Docente</label><BFormSelect v-model="contractForm.staff_id" :options="teacherOptions" /></div>
        <div class="col-md-4"><label class="form-label">Horas contrato</label><BFormInput v-model="contractForm.weekly_contract_hours" type="number" step="0.5" /></div>
        <div class="col-md-4"><label class="form-label">% lectivo</label><BFormInput v-model="contractForm.lective_percentage" type="number" step="0.01" /></div>
        <div class="col-md-4"><label class="form-label">% no lectivo</label><BFormInput v-model="contractForm.non_lective_percentage" type="number" step="0.01" /></div>
        <div class="col-md-6"><label class="form-label">Tipo de hora</label><BFormSelect v-model="contractForm.hour_type" :options="[{ value: 'chronological', text: 'Cronologica' }, { value: 'pedagogical', text: 'Pedagogica' }]" /></div>
        <div class="col-md-6 d-flex align-items-end"><BFormCheckbox v-model="contractForm.active">Activo</BFormCheckbox></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3"><BButton variant="secondary" @click="showContractModal = false">Cancelar</BButton><BButton variant="primary" @click="saveContract">Guardar</BButton></div>
    </BModal>

    <BModal v-model="showLayerModal" :title="layerForm.id ? 'Editar capa' : 'Nueva capa'" hide-footer>
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Nombre</label><BFormInput v-model="layerForm.name" /></div>
        <div class="col-md-4"><label class="form-label">Color</label><BFormInput v-model="layerForm.color" type="color" /></div>
        <div class="col-md-6"><label class="form-label">Tipo</label><BFormSelect v-model="layerForm.type" :options="catalogs.layer_types.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Prioridad</label><BFormInput v-model="layerForm.priority" type="number" /></div>
        <div class="col-md-3 d-flex align-items-end"><BFormCheckbox v-model="layerForm.visible_by_default">Visible</BFormCheckbox></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3"><BButton variant="secondary" @click="showLayerModal = false">Cancelar</BButton><BButton variant="primary" @click="saveLayer">Guardar</BButton></div>
    </BModal>

    <BModal v-model="showEventModal" :title="eventForm.id ? 'Editar bloque' : 'Nuevo bloque'" size="lg" hide-footer>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Docente</label><BFormSelect v-model="eventForm.staff_id" :options="teacherOptions" /></div>
        <div class="col-md-6"><label class="form-label">Capa</label><BFormSelect v-model="eventForm.teacher_schedule_layer_id" :options="layerOptions" /></div>
        <div class="col-md-6"><label class="form-label">Curso</label><BFormSelect v-model="eventForm.course_section_id" :options="courseOptions" /></div>
        <div class="col-md-6"><label class="form-label">Asignatura</label><BFormSelect v-model="eventForm.schedule_subject_id" :options="subjectOptions" /></div>
        <div class="col-md-3"><label class="form-label">Dia</label><BFormSelect v-model="eventForm.day_of_week" :options="dayOptions" /></div>
        <div class="col-md-3"><label class="form-label">Inicio</label><BFormInput v-model="eventForm.start_time" type="time" /></div>
        <div class="col-md-3"><label class="form-label">Termino</label><BFormInput v-model="eventForm.end_time" type="time" /></div>
        <div class="col-md-3"><label class="form-label">Actividad</label><BFormSelect v-model="eventForm.activity_type" :options="catalogs.activity_types.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-6"><label class="form-label">Sala</label><BFormInput v-model="eventForm.room_name" /></div>
        <div class="col-md-6 d-flex align-items-end"><BFormCheckbox v-model="eventForm.force_validation_exception">Forzar si hay advertencias o errores permitidos</BFormCheckbox></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="eventForm.notes" rows="2" /></div>
      </div>
      <div class="d-flex justify-content-between gap-2 mt-3">
        <BButton v-if="eventForm.id" variant="outline-danger" @click="removeEvent(eventForm)">Eliminar</BButton>
        <div class="ms-auto d-flex gap-2">
          <BButton variant="secondary" @click="showEventModal = false">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="saveEvent">Guardar</BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.schedule-module :deep(.card) {
  border-radius: 0.5rem;
}

.subject-dot {
  display: inline-block;
  width: 0.7rem;
  height: 0.7rem;
  border-radius: 999px;
  margin-right: 0.45rem;
  vertical-align: -0.05rem;
}

.metric {
  border: 1px solid #e9ecef;
  border-radius: 0.5rem;
  padding: 0.75rem;
  background: #f8f9fa;
}

.metric span {
  display: block;
  color: #6c757d;
  font-size: 0.78rem;
}

.metric strong {
  font-size: 1rem;
}

.schedule-grid {
  display: grid;
  grid-template-columns: 86px repeat(5, minmax(132px, 1fr));
  overflow-x: auto;
  border: 1px solid #e9ecef;
  border-radius: 0.5rem;
}

.grid-head,
.grid-time,
.grid-cell {
  min-height: 52px;
  padding: 0.5rem;
  border-right: 1px solid #e9ecef;
  border-bottom: 1px solid #e9ecef;
}

.grid-head {
  background: #f1f3f5;
  font-weight: 600;
  min-height: auto;
}

.grid-time {
  background: #f8f9fa;
  color: #495057;
  font-weight: 600;
}

.grid-cell {
  background: #fff;
  cursor: pointer;
}

.grid-cell--blocked {
  background: repeating-linear-gradient(135deg, #f8f9fa, #f8f9fa 8px, #eef1f4 8px, #eef1f4 16px);
  color: #6c757d;
}

.blocked-label {
  font-size: 0.75rem;
  font-weight: 600;
}

.event-chip {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  border-left: 4px solid #0d6efd;
  border-radius: 0.35rem;
  padding: 0.4rem 0.5rem;
  margin-bottom: 0.35rem;
  font-size: 0.8rem;
}

.event-chip small {
  color: #6c757d;
}

.plan-progress {
  padding: 0.65rem 0;
  border-bottom: 1px solid #eef1f4;
}

.jornada-block-list {
  max-height: 50vh;
  overflow: auto;
}

.jornada-block-row {
  padding: 0.6rem 0;
  border-bottom: 1px solid #eef1f4;
}
</style>
