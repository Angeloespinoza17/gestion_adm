<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const localDateTime = (value = new Date()) => {
  const date = new Date(value);
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
};

const emptyForm = () => ({
  id: null,
  student_profile_id: null,
  course_section_id: null,
  happened_at: localDateTime(),
  category: "observacion_general",
  priority: "media",
  status: "registrado",
  title: "",
  detail: "",
  action_taken: "",
  requires_follow_up: false,
  follow_up_note: "",
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: true,
      saving: false,
      error: null,
      items: [],
      summary: { total_records: 0, today_records: 0, pending_follow_up: 0, high_priority: 0 },
      capabilities: { can_manage: false },
      pagination: { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 },
      filters: { search: "", date: "", category: "", priority: "", status: "", follow_up: false },
      categories: [],
      priorities: [],
      statuses: [],
      courses: [],
      academicYear: null,
      form: emptyForm(),
      showFormModal: false,
      selectedEntry: null,
      showFileModal: false,
      studentSearch: "",
      studentOptions: [],
      studentSearchLoading: false,
      studentSearchTimer: null,
      studentSearchSequence: 0,
    };
  },
  computed: {
    datalistStudents() {
      return this.studentOptions.map((student) => ({
        ...student,
        label: [student.name, student.rut || "Sin RUT", student.course || "Sin curso vigente"].join(" · "),
      }));
    },
    selectedStudent() {
      return this.datalistStudents.find((student) => Number(student.id) === Number(this.form.student_profile_id)) || null;
    },
    activeFilterCount() {
      return [this.filters.search.trim(), this.filters.date, this.filters.category, this.filters.priority, this.filters.status]
        .filter(Boolean).length + Number(this.filters.follow_up);
    },
  },
  watch: {
    "form.requires_follow_up"(value) {
      if (!value) this.form.follow_up_note = "";
      if (value && this.form.status === "cerrado") this.form.status = "en_seguimiento";
    },
    "form.status"(value) {
      if (value === "cerrado") this.form.requires_follow_up = false;
    },
  },
  async mounted() {
    await Promise.all([this.loadCatalogs(), this.load(1)]);
  },
  beforeUnmount() {
    window.clearTimeout(this.studentSearchTimer);
  },
  methods: {
    async loadCatalogs() {
      try {
        const { data } = await axios.get("/api/infirmary/daily-log/catalogs");
        this.categories = data.categories || [];
        this.priorities = data.priorities || [];
        this.statuses = data.statuses || [];
        this.courses = data.courses || [];
        this.academicYear = data.current_academic_year || null;
        this.capabilities = { ...this.capabilities, ...(data.capabilities || {}) };
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible cargar las opciones de la bitácora.");
      }
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get("/api/infirmary/daily-log", {
          params: {
            page,
            search: this.filters.search.trim() || null,
            date: this.filters.date || null,
            category: this.filters.category || null,
            priority: this.filters.priority || null,
            status: this.filters.status || null,
            follow_up: this.filters.follow_up ? 1 : null,
          },
        });
        this.items = data.data || [];
        this.summary = { ...this.summary, ...(data.summary || {}) };
        this.capabilities = { ...this.capabilities, ...(data.capabilities || {}) };
        this.pagination = {
          current_page: data.current_page || 1,
          last_page: data.last_page || 1,
          total: data.total || 0,
          from: data.from || 0,
          to: data.to || 0,
        };
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible cargar la bitácora de Enfermería.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = { search: "", date: "", category: "", priority: "", status: "", follow_up: false };
      this.load(1);
    },
    toggleFollowUpFilter() {
      this.filters.follow_up = !this.filters.follow_up;
      this.load(1);
    },
    openCreate() {
      this.form = emptyForm();
      this.studentSearch = "";
      this.studentOptions = [];
      this.showFormModal = true;
    },
    openFile(item) {
      this.selectedEntry = item;
      this.showFileModal = true;
    },
    openEdit(item) {
      const student = item.student ? {
        id: item.student.id,
        name: this.studentName(item.student),
        rut: item.student.rut,
        course_id: item.course_section?.id || null,
        course: item.course_section?.display_name || null,
      } : null;
      this.studentOptions = student ? [student] : [];
      this.studentSearch = student ? [student.name, student.rut || "Sin RUT", student.course || "Sin curso vigente"].join(" · ") : "";
      this.form = {
        id: item.id,
        student_profile_id: item.student_profile_id,
        course_section_id: item.course_section_id,
        happened_at: localDateTime(String(item.happened_at).replace(" ", "T")),
        category: item.category,
        priority: item.priority,
        status: item.status,
        title: item.title || "",
        detail: item.detail || "",
        action_taken: item.action_taken || "",
        requires_follow_up: Boolean(item.requires_follow_up),
        follow_up_note: item.follow_up_note || "",
      };
      this.showFormModal = true;
    },
    closeForm() {
      this.showFormModal = false;
      this.form = emptyForm();
      this.studentSearch = "";
      this.studentOptions = [];
      this.studentSearchLoading = false;
      window.clearTimeout(this.studentSearchTimer);
    },
    onStudentInput() {
      const exact = this.datalistStudents.find((student) => student.label === this.studentSearch);
      this.form.student_profile_id = exact?.id || null;
      this.form.course_section_id = exact?.course_id || null;
      window.clearTimeout(this.studentSearchTimer);
      if (this.studentSearch.trim().length < 2 || exact) return;
      this.studentSearchTimer = window.setTimeout(() => this.searchStudents(), 280);
    },
    syncStudent() {
      const exact = this.datalistStudents.find((student) => student.label === this.studentSearch);
      this.form.student_profile_id = exact?.id || null;
      if (exact) this.form.course_section_id = exact.course_id || null;
    },
    async searchStudents() {
      const search = this.studentSearch.trim();
      if (search.length < 2) return;
      const sequence = ++this.studentSearchSequence;
      this.studentSearchLoading = true;
      try {
        const { data } = await axios.get("/api/infirmary/daily-log/students", { params: { search } });
        if (sequence !== this.studentSearchSequence || search !== this.studentSearch.trim()) return;
        this.studentOptions = data.data || [];
        this.syncStudent();
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible buscar alumnas.");
      } finally {
        if (sequence === this.studentSearchSequence) this.studentSearchLoading = false;
      }
    },
    async save() {
      this.syncStudent();
      if (!this.form.happened_at) return this.warn("Indica la fecha y hora del hecho.");
      if (!this.form.category) return this.warn("Selecciona una categoría de Enfermería.");
      if (!this.form.title.trim()) return this.warn("Ingresa un título breve.");
      if (!this.form.detail.trim()) return this.warn("Describe el hecho relevante de la jornada.");

      this.saving = true;
      try {
        const payload = {
          ...this.form,
          title: this.form.title.trim(),
          detail: this.form.detail.trim(),
          action_taken: this.form.action_taken.trim() || null,
          follow_up_note: this.form.requires_follow_up ? (this.form.follow_up_note.trim() || null) : null,
        };
        const { data } = this.form.id
          ? await axios.put(`/api/infirmary/daily-log/${this.form.id}`, payload)
          : await axios.post("/api/infirmary/daily-log", payload);
        this.closeForm();
        await this.load(1);
        await Swal.fire({ icon: "success", title: "Bitácora actualizada", text: data.message, confirmButtonText: "Entendido" });
      } catch (error) {
        await Swal.fire({ icon: "error", title: "No se pudo guardar", text: this.errorMessage(error, "Revisa los datos e intenta nuevamente."), confirmButtonText: "Entendido" });
      } finally {
        this.saving = false;
      }
    },
    studentName(student) {
      return student?.registered_name || [student?.first_name, student?.last_name].filter(Boolean).join(" ") || "Alumna sin nombre";
    },
    optionLabel(options, value) {
      return options.find((option) => option.value === value)?.label || value || "Sin información";
    },
    formatDate(value) {
      if (!value) return "Sin fecha";
      return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium", timeStyle: "short" })
        .format(new Date(String(value).replace(" ", "T")));
    },
    errorMessage(error, fallback) {
      const errors = error?.response?.data?.errors || {};
      const first = Object.values(errors)[0];
      return first?.[0] || error?.response?.data?.message || fallback;
    },
    warn(text) {
      return Swal.fire({ icon: "warning", title: "Revisa el formulario", text, confirmButtonText: "Entendido" });
    },
  },
};
</script>

<template>
  <Layout>
    <main class="nursing-log-page">
      <header class="nursing-log-hero">
        <div class="nursing-log-hero__icon"><i class="bx bx-notepad"></i></div>
        <div class="nursing-log-hero__copy">
          <span>ENFERMERÍA · REGISTRO CRONOLÓGICO</span>
          <h2>Bitácora diaria</h2>
          <p>Hechos sanitarios y operativos relevantes de la jornada, con responsable y seguimiento trazable.</p>
        </div>
        <div class="nursing-log-hero__guardrail">
          <i class="bx bx-shield-quarter"></i>
          <div><small>REGISTRO COMPLEMENTARIO</small><strong>No reemplaza la ficha de atención</strong></div>
        </div>
      </header>

      <section class="nursing-log-summary" aria-label="Resumen de la bitácora">
        <article><i class="bx bx-book-content"></i><div><span>Registros totales</span><strong>{{ summary.total_records }}</strong><small>Histórico disponible</small></div></article>
        <article class="is-today"><i class="bx bx-calendar-check"></i><div><span>Registros de hoy</span><strong>{{ summary.today_records }}</strong><small>Actividad de la jornada</small></div></article>
        <article class="is-follow-up"><i class="bx bx-time-five"></i><div><span>Seguimientos pendientes</span><strong>{{ summary.pending_follow_up }}</strong><small>Requieren continuidad</small></div></article>
        <article class="is-priority"><i class="bx bx-error-circle"></i><div><span>Prioridad alta</span><strong>{{ summary.high_priority }}</strong><small>Abiertos o en seguimiento</small></div></article>
      </section>

      <BAlert v-if="error" show dismissible variant="danger" @dismissed="error=null">{{ error }}</BAlert>

      <section class="nursing-log-panel">
        <div class="nursing-log-panel__heading">
          <div><span>TRAZABILIDAD DE JORNADA</span><h3>Registros de Enfermería</h3><p>{{ pagination.total }} hechos encontrados según los filtros seleccionados.</p></div>
          <button v-if="capabilities.can_manage" type="button" class="btn nursing-log-create" @click="openCreate"><i class="bx bx-plus"></i>Nuevo registro</button>
        </div>

        <div class="nursing-log-filters">
          <label class="nursing-log-search"><i class="bx bx-search"></i><input v-model="filters.search" type="search" class="form-control" placeholder="Buscar título, detalle o alumna" @keyup.enter="load(1)"></label>
          <input v-model="filters.date" type="date" class="form-control" aria-label="Fecha de bitácora">
          <select v-model="filters.category" class="form-select" aria-label="Categoría"><option value="">Todas las categorías</option><option v-for="option in categories" :key="option.value" :value="option.value">{{ option.label }}</option></select>
          <select v-model="filters.priority" class="form-select" aria-label="Prioridad"><option value="">Todas las prioridades</option><option v-for="option in priorities" :key="option.value" :value="option.value">{{ option.label }}</option></select>
          <select v-model="filters.status" class="form-select" aria-label="Estado"><option value="">Todos los estados</option><option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select>
          <button type="button" class="follow-up-filter" :class="{ active: filters.follow_up }" :aria-pressed="filters.follow_up" @click="toggleFollowUpFilter"><i class="bx bx-time-five"></i>Seguimientos</button>
          <button type="button" class="btn btn-outline-primary" @click="load(1)"><i class="bx bx-filter-alt me-1"></i>Filtrar</button>
          <button v-if="activeFilterCount" type="button" class="btn btn-link text-decoration-none" @click="resetFilters">Limpiar</button>
        </div>

        <LoadingState v-if="loading" compact message="Cargando bitácora de Enfermería..." />
        <template v-else>
          <div class="table-responsive">
            <table class="table nursing-log-table align-middle">
              <thead><tr><th>Fecha y hora</th><th>Categoría</th><th>Hecho registrado</th><th>Alumna / curso</th><th>Responsable</th><th>Prioridad</th><th>Seguimiento</th><th class="text-end">Acciones</th></tr></thead>
              <tbody>
                <tr v-for="item in items" :key="item.id">
                  <td><strong>{{ formatDate(item.happened_at) }}</strong><small>{{ optionLabel(statuses, item.status) }}</small></td>
                  <td><span class="category-chip" :class="item.category">{{ optionLabel(categories, item.category) }}</span></td>
                  <td><strong>{{ item.title }}</strong><small class="detail-preview">{{ item.detail }}</small></td>
                  <td><strong>{{ item.student ? studentName(item.student) : "Registro general" }}</strong><small>{{ item.course_section?.display_name || "Sin curso asociado" }}</small></td>
                  <td><strong>{{ item.registered_by?.name || "Enfermería" }}</strong></td>
                  <td><span class="priority-chip" :class="item.priority">{{ optionLabel(priorities, item.priority) }}</span></td>
                  <td><span class="follow-up-chip" :class="{ pending: item.requires_follow_up && item.status !== 'cerrado' }"><i :class="item.requires_follow_up && item.status !== 'cerrado' ? 'bx bx-time-five' : 'bx bx-check'"></i>{{ item.requires_follow_up && item.status !== "cerrado" ? "Pendiente" : "Sin pendiente" }}</span></td>
                  <td class="text-end"><div class="nursing-log-actions"><button type="button" title="Ver ficha" :aria-label="`Ver ${item.title}`" @click="openFile(item)"><i class="bx bx-show-alt"></i></button><button v-if="capabilities.can_manage" type="button" title="Editar registro" :aria-label="`Editar ${item.title}`" @click="openEdit(item)"><i class="bx bx-edit"></i></button></div></td>
                </tr>
              </tbody>
            </table>
            <div v-if="!items.length" class="nursing-log-empty"><i class="bx bx-notepad"></i><strong>No hay registros para estos filtros</strong><span>Registra un hecho de jornada o cambia los criterios de búsqueda.</span></div>
          </div>

          <footer v-if="pagination.last_page > 1" class="nursing-log-pagination"><span>Mostrando {{ pagination.from }}–{{ pagination.to }} de {{ pagination.total }}</span><div><button class="btn btn-sm btn-outline-primary" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Anterior</button><b>{{ pagination.current_page }} / {{ pagination.last_page }}</b><button class="btn btn-sm btn-outline-primary" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Siguiente</button></div></footer>
        </template>
      </section>

      <BModal v-model="showFormModal" size="xl" :title="form.id ? 'Editar registro de bitácora' : 'Nuevo registro de bitácora'" hide-footer scrollable @hidden="closeForm">
        <form class="nursing-log-form" @submit.prevent="save">
          <div class="form-guidance"><i class="bx bx-info-circle"></i><div><strong>Registra hechos de jornada</strong><span>Las atenciones, medicamentos y accidentes deben conservar su detalle en sus fichas específicas.</span></div></div>

          <div class="form-grid form-grid--top">
            <div class="form-field"><label for="nursing-log-date">Fecha y hora <b>*</b></label><input id="nursing-log-date" v-model="form.happened_at" type="datetime-local" class="form-control" required></div>
            <div class="form-field"><label for="nursing-log-category">Categoría <b>*</b></label><select id="nursing-log-category" v-model="form.category" class="form-select" required><option v-for="option in categories" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
            <div class="form-field"><label for="nursing-log-priority">Prioridad <b>*</b></label><select id="nursing-log-priority" v-model="form.priority" class="form-select" required><option v-for="option in priorities" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
            <div class="form-field"><label for="nursing-log-status">Estado <b>*</b></label><select id="nursing-log-status" v-model="form.status" class="form-select" required><option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
          </div>

          <div class="form-grid">
            <div class="form-field"><label for="nursing-log-student">Alumna relacionada <span>Opcional</span></label><div class="datalist-control"><i class="bx bx-search-alt"></i><input id="nursing-log-student" v-model="studentSearch" list="nursing-log-student-options" class="form-control" autocomplete="off" placeholder="Escribe nombre, apellido o RUT" @input="onStudentInput" @change="syncStudent"><span v-if="studentSearchLoading" class="spinner-border spinner-border-sm"></span><datalist id="nursing-log-student-options"><option v-for="student in datalistStudents" :key="student.id" :value="student.label"></option></datalist></div><small>{{ selectedStudent ? `${selectedStudent.name} · ${selectedStudent.course || 'Sin curso vigente'}` : "Déjalo vacío para un registro general de jornada." }}</small></div>
            <div class="form-field"><label for="nursing-log-course">Curso relacionado <span>Opcional</span></label><select id="nursing-log-course" v-model="form.course_section_id" class="form-select" :disabled="Boolean(selectedStudent)"><option :value="null">Sin curso asociado</option><option v-for="course in courses" :key="course.id" :value="course.id">{{ course.display_name }}</option></select><small>{{ selectedStudent ? "Se completa desde la matrícula vigente de la alumna." : `Cursos del año ${academicYear?.year || 'actual'}.` }}</small></div>
          </div>

          <div class="form-field"><label for="nursing-log-title">Título breve <b>*</b></label><input id="nursing-log-title" v-model="form.title" type="text" class="form-control" maxlength="191" placeholder="Ej.: Coordinación de traslado a centro asistencial" required></div>
          <div class="form-field"><label for="nursing-log-detail">Descripción del hecho <b>*</b></label><textarea id="nursing-log-detail" v-model="form.detail" class="form-control" rows="4" maxlength="4000" placeholder="Describe qué ocurrió y el contexto necesario para la continuidad de la jornada" required></textarea><small class="text-end">{{ form.detail.length }} / 4000</small></div>
          <div class="form-field"><label for="nursing-log-action">Acción o medida realizada <span>Opcional</span></label><textarea id="nursing-log-action" v-model="form.action_taken" class="form-control" rows="3" maxlength="2000" placeholder="Coordinación, resguardo, aviso o medida operativa adoptada"></textarea></div>

          <label class="follow-up-card" :class="{ active: form.requires_follow_up }"><input v-model="form.requires_follow_up" type="checkbox"><span><i class="bx bx-time-five"></i></span><div><strong>Requiere seguimiento</strong><small>Déjalo marcado si otro momento de la jornada o un día posterior requiere continuidad.</small></div><i class="bx" :class="form.requires_follow_up ? 'bxs-check-circle' : 'bx-circle'"></i></label>
          <div v-if="form.requires_follow_up" class="form-field"><label for="nursing-log-follow-up">Indicación para el seguimiento</label><textarea id="nursing-log-follow-up" v-model="form.follow_up_note" class="form-control" rows="3" maxlength="2000" placeholder="Qué debe verificarse, cuándo o con quién continuar"></textarea></div>

          <div class="form-actions"><button type="button" class="btn btn-light" :disabled="saving" @click="closeForm">Cancelar</button><button type="submit" class="btn nursing-log-submit" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-check-shield"></i>{{ saving ? "Guardando..." : "Guardar registro" }}</button></div>
        </form>
      </BModal>

      <BModal v-model="showFileModal" size="lg" title="Ficha de bitácora" hide-header hide-footer centered scrollable body-class="p-0" modal-class="nursing-log-file-modal">
        <article v-if="selectedEntry" class="log-file">
          <header class="log-file__hero">
            <span class="log-file__hero-icon"><i class="bx bx-notepad"></i></span>
            <div class="log-file__hero-copy">
              <span class="log-file__eyebrow">{{ optionLabel(categories, selectedEntry.category) }}</span>
              <h3>{{ selectedEntry.title }}</h3>
              <p><i class="bx bx-calendar"></i>{{ formatDate(selectedEntry.happened_at) }}</p>
            </div>
            <div class="log-file__hero-badges">
              <span class="log-file__status"><i class="bx bx-radio-circle-marked"></i>{{ optionLabel(statuses, selectedEntry.status) }}</span>
              <span class="priority-chip" :class="selectedEntry.priority">{{ optionLabel(priorities, selectedEntry.priority) }}</span>
            </div>
            <button type="button" class="log-file__close" aria-label="Cerrar ficha de bitácora" @click="showFileModal=false"><i class="bx bx-x"></i></button>
          </header>

          <div class="log-file__content">
            <div class="log-file__meta">
              <section>
                <span class="log-file__meta-icon"><i class="bx bx-user"></i></span>
                <div><span>Alumna relacionada</span><strong>{{ selectedEntry.student ? studentName(selectedEntry.student) : "Registro general" }}</strong><small>{{ selectedEntry.course_section?.display_name || "Sin curso asociado" }}</small></div>
              </section>
              <section>
                <span class="log-file__meta-icon is-owner"><i class="bx bx-user-check"></i></span>
                <div><span>Responsable del registro</span><strong>{{ selectedEntry.registered_by?.name || "Enfermería" }}</strong><small>Registro trazable de Enfermería</small></div>
              </section>
            </div>

            <div class="log-file__details">
              <section class="log-file__detail" :class="{ 'is-wide': !selectedEntry.action_taken }">
                <div class="log-file__detail-heading"><span><i class="bx bx-file"></i></span><div><small>Antecedente registrado</small><strong>Descripción del hecho</strong></div></div>
                <p>{{ selectedEntry.detail }}</p>
              </section>
              <section v-if="selectedEntry.action_taken" class="log-file__detail is-action">
                <div class="log-file__detail-heading"><span><i class="bx bx-check-shield"></i></span><div><small>Respuesta adoptada</small><strong>Acción o medida realizada</strong></div></div>
                <p>{{ selectedEntry.action_taken }}</p>
              </section>
            </div>

            <section class="log-file__continuity" :class="{ pending: selectedEntry.requires_follow_up && selectedEntry.status !== 'cerrado' }">
              <span><i :class="selectedEntry.requires_follow_up && selectedEntry.status !== 'cerrado' ? 'bx bx-time-five' : 'bx bx-check-circle'"></i></span>
              <div><small>Continuidad del caso</small><strong>{{ selectedEntry.requires_follow_up && selectedEntry.status !== "cerrado" ? "Seguimiento pendiente" : "Sin seguimiento pendiente" }}</strong><p>{{ selectedEntry.requires_follow_up && selectedEntry.status !== "cerrado" ? (selectedEntry.follow_up_note || "Sin indicación adicional.") : "El registro no requiere una acción posterior." }}</p></div>
            </section>

            <footer class="log-file__footer">
              <div class="log-file__privacy"><i class="bx bx-shield-quarter"></i><span><strong>Registro protegido</strong><small>Acceso restringido al personal autorizado</small></span></div>
              <div class="log-file__actions"><button type="button" class="btn log-file__secondary-action" @click="showFileModal=false">Cerrar</button><button v-if="capabilities.can_manage" type="button" class="btn log-file__primary-action" @click="showFileModal=false; openEdit(selectedEntry)"><i class="bx bx-edit"></i>Editar registro</button></div>
            </footer>
          </div>
        </article>
      </BModal>
    </main>
  </Layout>
</template>

<style scoped>
.nursing-log-page,.nursing-log-form{--ink:#173a50;--blue:#176b87;--teal:#289b97;--green:#168166;--coral:#c45f43;--line:#dce8ed;--soft:#f4f8fa}.nursing-log-page{display:flex;flex-direction:column;gap:1rem;padding-bottom:2rem}.nursing-log-hero{align-items:center;background:linear-gradient(125deg,#15394f 0%,#176b87 58%,#26958e 100%);border-radius:20px;box-shadow:0 18px 38px rgba(23,58,80,.18);color:#fff;display:flex;gap:1rem;overflow:hidden;padding:1.3rem 1.5rem;position:relative}.nursing-log-hero::after{background:rgba(255,255,255,.07);border-radius:50%;content:"";height:280px;position:absolute;right:17%;top:-190px;width:280px}.nursing-log-hero__icon{align-items:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);border-radius:16px;display:flex;flex:0 0 58px;font-size:1.65rem;height:58px;justify-content:center}.nursing-log-hero__copy{flex:1;position:relative;z-index:1}.nursing-log-hero__copy>span{font-size:.61rem;font-weight:850;letter-spacing:.13em;opacity:.76}.nursing-log-hero h2{color:#fff;font-size:1.45rem;margin:.2rem 0}.nursing-log-hero p{font-size:.75rem;margin:0;opacity:.82}.nursing-log-hero__guardrail{align-items:center;background:rgba(9,38,53,.25);border:1px solid rgba(255,255,255,.15);border-radius:14px;display:flex;gap:.6rem;padding:.7rem .85rem;position:relative;z-index:1}.nursing-log-hero__guardrail>i{font-size:1.35rem}.nursing-log-hero__guardrail div{display:flex;flex-direction:column}.nursing-log-hero__guardrail small{font-size:.52rem;font-weight:800;opacity:.68}.nursing-log-hero__guardrail strong{font-size:.68rem}.nursing-log-summary{display:grid;gap:.7rem;grid-template-columns:repeat(4,minmax(0,1fr))}.nursing-log-summary article{align-items:center;background:#fff;border:1px solid var(--line);border-radius:15px;box-shadow:0 8px 22px rgba(31,64,85,.055);display:flex;gap:.7rem;padding:.9rem 1rem}.nursing-log-summary article>i{align-items:center;background:#e9f3f7;border-radius:12px;color:var(--blue);display:flex;flex:0 0 43px;font-size:1.25rem;height:43px;justify-content:center}.nursing-log-summary article>div{display:grid;grid-template-columns:1fr auto;width:100%}.nursing-log-summary span{color:#718591;font-size:.64rem;font-weight:750}.nursing-log-summary strong{color:var(--ink);font-size:1.45rem;grid-column:2;grid-row:1/3}.nursing-log-summary small{color:#8a99a1;font-size:.57rem}.nursing-log-summary .is-today>i{background:#edf7f4;color:var(--green)}.nursing-log-summary .is-follow-up>i{background:#fff6e8;color:#b6761d}.nursing-log-summary .is-priority>i{background:#fff0eb;color:var(--coral)}.nursing-log-panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 14px 35px rgba(31,64,85,.07);overflow:hidden}.nursing-log-panel__heading{align-items:center;display:flex;justify-content:space-between;padding:1.2rem 1.35rem}.nursing-log-panel__heading span{color:var(--teal);font-size:.6rem;font-weight:850;letter-spacing:.12em}.nursing-log-panel__heading h3{color:var(--ink);font-size:1.1rem;margin:.15rem 0}.nursing-log-panel__heading p{color:#7b8d98;font-size:.68rem;margin:0}.nursing-log-create,.nursing-log-submit{align-items:center;background:linear-gradient(120deg,var(--blue),var(--teal));border:0;color:#fff;display:inline-flex;font-weight:750;gap:.4rem}.nursing-log-create{border-radius:11px;box-shadow:0 7px 16px rgba(23,107,135,.2);padding:.6rem .85rem}.nursing-log-create:hover,.nursing-log-submit:hover{color:#fff;filter:brightness(1.04)}.nursing-log-filters{align-items:center;background:var(--soft);border-block:1px solid #e3ecef;display:grid;gap:.55rem;grid-template-columns:minmax(230px,1fr) 145px 180px 150px 150px auto auto auto;padding:.85rem 1.25rem}.nursing-log-search{position:relative}.nursing-log-search>i{color:#7b8d98;left:.75rem;position:absolute;top:.68rem}.nursing-log-search input{padding-left:2.15rem}.follow-up-filter{align-items:center;background:#fff;border:1px solid #dfd8c9;border-radius:9px;color:#7a6848;display:flex;font-size:.68rem;font-weight:750;gap:.3rem;height:38px;padding:0 .65rem}.follow-up-filter.active{background:#fff6e8;border-color:#dfa950;color:#9d6414}.nursing-log-table{margin:0;min-width:1120px}.nursing-log-table thead{background:#fbfcfd}.nursing-log-table th{border-color:var(--line);color:#708490;font-size:.59rem;font-weight:850;letter-spacing:.07em;padding:.72rem .8rem;text-transform:uppercase;white-space:nowrap}.nursing-log-table td{border-color:#edf2f4;color:#526976;font-size:.7rem;padding:.85rem .8rem}.nursing-log-table td>strong,.nursing-log-table td>small{display:block}.nursing-log-table td>strong{color:var(--ink);font-size:.72rem}.nursing-log-table td>small{color:#84949d;font-size:.59rem;margin-top:.2rem}.detail-preview{-webkit-box-orient:vertical;-webkit-line-clamp:2;display:-webkit-box;max-width:330px;overflow:hidden}.category-chip,.priority-chip,.follow-up-chip{align-items:center;border-radius:99px;display:inline-flex;font-size:.59rem;font-weight:800;line-height:1.2;padding:.35rem .52rem;white-space:nowrap}.category-chip{background:#eaf4f7;color:var(--blue)}.category-chip.insumos_equipamiento{background:#f1eef9;color:#6f57a0}.category-chip.bioseguridad{background:#edf7f4;color:var(--green)}.category-chip.derivacion_traslado{background:#fff0eb;color:#a84e34}.category-chip.contacto_apoderado{background:#fff6e8;color:#a06a1d}.priority-chip{background:#eef1f3;color:#667984}.priority-chip.media{background:#eaf4f7;color:var(--blue)}.priority-chip.alta{background:#fff6e8;color:#9f671b}.priority-chip.urgente{background:#fff0eb;color:#ad4d31}.follow-up-chip{background:#edf7f4;color:var(--green);gap:.25rem}.follow-up-chip.pending{background:#fff6e8;color:#a26a1b}.nursing-log-actions{display:flex;gap:.35rem;justify-content:flex-end}.nursing-log-actions button{align-items:center;background:#eef5f8;border:0;border-radius:8px;color:var(--blue);display:flex;font-size:1rem;height:31px;justify-content:center;width:31px}.nursing-log-actions button+button{background:#edf7f4;color:var(--green)}.nursing-log-empty{align-items:center;color:#82939d;display:flex;flex-direction:column;gap:.25rem;padding:3.3rem 1rem}.nursing-log-empty i{font-size:2.1rem}.nursing-log-empty strong{color:var(--ink)}.nursing-log-empty span{font-size:.7rem}.nursing-log-pagination{align-items:center;border-top:1px solid var(--line);display:flex;justify-content:space-between;padding:.8rem 1.1rem}.nursing-log-pagination>span{color:#7c8e98;font-size:.66rem}.nursing-log-pagination>div{align-items:center;display:flex;gap:.5rem}.nursing-log-pagination b{font-size:.65rem}.form-guidance{align-items:center;background:#eef8f7;border:1px solid #d5ebe7;border-radius:12px;color:#1d665f;display:flex;gap:.65rem;margin-bottom:1rem;padding:.75rem .85rem}.form-guidance>i{font-size:1.35rem}.form-guidance>div{display:flex;flex-direction:column}.form-guidance strong{font-size:.73rem}.form-guidance span{font-size:.62rem}.form-grid{display:grid;gap:.75rem;grid-template-columns:1.35fr 1fr}.form-grid--top{grid-template-columns:1.25fr 1.2fr .75fr .85fr}.form-field{display:flex;flex-direction:column;margin-bottom:.85rem}.form-field label{color:var(--ink);font-size:.68rem;font-weight:800;margin-bottom:.32rem}.form-field label b{color:#bd4d35}.form-field label span{color:#8a99a1;font-size:.57rem;font-weight:650;margin-left:.2rem}.form-field>small{color:#82939d;font-size:.59rem;margin-top:.26rem}.datalist-control{position:relative}.datalist-control>i{color:#7b8d98;left:.75rem;position:absolute;top:.68rem}.datalist-control input{padding-left:2.15rem;padding-right:2rem}.datalist-control>.spinner-border{position:absolute;right:.7rem;top:.7rem}.follow-up-card{align-items:center;background:#fbfcfd;border:1px solid var(--line);border-radius:13px;cursor:pointer;display:flex;gap:.7rem;margin-bottom:.9rem;padding:.8rem .9rem}.follow-up-card>input{opacity:0;position:absolute}.follow-up-card>span{align-items:center;background:#fff6e8;border-radius:10px;color:#a26a1b;display:flex;flex:0 0 39px;font-size:1.1rem;height:39px;justify-content:center}.follow-up-card>div{display:flex;flex:1;flex-direction:column}.follow-up-card strong{color:var(--ink);font-size:.72rem}.follow-up-card small{color:#81919a;font-size:.61rem}.follow-up-card>i{color:#b8c2c7;font-size:1.15rem}.follow-up-card.active{background:#fff9ef;border-color:#e0b76f}.follow-up-card.active>i{color:#b6761d}.form-actions{border-top:1px solid var(--line);display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem;padding-top:.85rem}.nursing-log-submit{border-radius:9px;padding:.53rem .8rem}.log-file header{align-items:flex-start;background:linear-gradient(135deg,#eef7f8,#f8fbfc);border:1px solid var(--line);border-radius:14px;display:flex;justify-content:space-between;padding:1rem}.log-file header>div>span,.log-file__body>span{color:var(--teal);font-size:.58rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase}.log-file h3{color:var(--ink);font-size:1.1rem;margin:.2rem 0}.log-file header p{color:#728590;font-size:.66rem;margin:0}.log-file__meta{display:grid;gap:.7rem;grid-template-columns:1fr 1fr;margin-top:.8rem}.log-file__meta section{background:#fff;border:1px solid var(--line);border-radius:12px;display:flex;flex-direction:column;padding:.75rem .85rem}.log-file__meta span{color:#7e909a;font-size:.58rem;font-weight:800;text-transform:uppercase}.log-file__meta strong{color:var(--ink);font-size:.73rem;margin-top:.16rem}.log-file__meta small{color:#86959d;font-size:.6rem}.log-file__body{border-bottom:1px solid var(--line);padding:1rem .2rem}.log-file__body p{color:#4e6673;font-size:.72rem;line-height:1.6;margin:.35rem 0 0;white-space:pre-wrap}.log-file__body.is-action>span{color:var(--green)}.log-file__follow{align-items:flex-start;background:#fff8ed;border:1px solid #edd4a6;border-radius:12px;color:#8a5b17;display:flex;gap:.6rem;margin-top:.8rem;padding:.75rem .85rem}.log-file__follow>i{font-size:1.2rem}.log-file__follow strong{font-size:.7rem}.log-file__follow p{font-size:.64rem;margin:.15rem 0 0;white-space:pre-wrap}

:global(.nursing-log-file-modal .modal-dialog) {
  max-width: min(920px, calc(100vw - 2rem));
}

:global(.nursing-log-file-modal .modal-content) {
  background: #f3f7f8;
  border: 0;
  border-radius: 22px;
  box-shadow: 0 28px 80px rgba(15, 42, 58, 0.28);
  overflow: hidden;
}

:global(.nursing-log-file-modal .modal-body) {
  padding: 0 !important;
}

.log-file {
  --ink: #173a50;
  --blue: #176b87;
  --teal: #289b97;
  --green: #168166;
  --line: #dce8ed;
  background: #f3f7f8;
  color: #4e6673;
}

.log-file > .log-file__hero {
  align-items: center;
  background:
    radial-gradient(circle at 86% -20%, rgba(83, 206, 191, 0.28), transparent 42%),
    linear-gradient(125deg, #123247 0%, #176b77 62%, #248e84 100%);
  border: 0;
  border-radius: 0;
  color: #fff;
  display: grid;
  gap: 1rem;
  grid-template-columns: auto minmax(0, 1fr) auto;
  min-height: 146px;
  overflow: hidden;
  padding: 1.35rem 4.1rem 1.35rem 1.45rem;
  position: relative;
}

.log-file__hero::after {
  background-image: radial-gradient(rgba(255, 255, 255, 0.2) 0.7px, transparent 0.7px);
  background-size: 14px 14px;
  content: "";
  inset: 0 0 0 66%;
  opacity: 0.28;
  pointer-events: none;
  position: absolute;
}

.log-file__hero-icon {
  align-items: center;
  background: rgba(255, 255, 255, 0.13);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 16px;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.65rem;
  height: 58px;
  justify-content: center;
  position: relative;
  width: 58px;
  z-index: 1;
}

.log-file__hero-copy {
  min-width: 0;
  position: relative;
  z-index: 1;
}

.log-file__eyebrow {
  color: #8de3d7 !important;
  display: block;
  font-size: 0.61rem !important;
  font-weight: 850;
  letter-spacing: 0.11em !important;
  margin-bottom: 0.28rem;
  text-transform: uppercase;
}

.log-file .log-file__hero h3 {
  color: #fff;
  font-size: 1.28rem;
  font-weight: 760;
  letter-spacing: -0.02em;
  margin: 0;
  overflow-wrap: anywhere;
}

.log-file .log-file__hero p {
  align-items: center;
  color: rgba(255, 255, 255, 0.72);
  display: flex;
  font-size: 0.68rem;
  gap: 0.32rem;
  margin: 0.42rem 0 0;
}

.log-file__hero-badges {
  align-items: flex-end;
  display: flex;
  flex-direction: column;
  gap: 0.42rem;
  position: relative;
  z-index: 1;
}

.log-file__status,
.log-file__hero .priority-chip {
  align-items: center;
  backdrop-filter: blur(5px);
  background: rgba(255, 255, 255, 0.13);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 999px;
  color: #fff;
  display: inline-flex;
  font-size: 0.6rem;
  font-weight: 800;
  gap: 0.3rem;
  padding: 0.38rem 0.58rem;
}

.log-file__status i {
  color: #8de3d7;
}

.log-file__close {
  align-items: center;
  background: rgba(7, 32, 45, 0.24);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 11px;
  color: #fff;
  display: inline-flex;
  font-size: 1.35rem;
  height: 38px;
  justify-content: center;
  position: absolute;
  right: 1rem;
  top: 1rem;
  transition: 0.18s ease;
  width: 38px;
  z-index: 2;
}

.log-file__close:hover,
.log-file__close:focus-visible {
  background: rgba(255, 255, 255, 0.2);
  outline: none;
  transform: translateY(-1px);
}

.log-file__content {
  padding: 1rem 1.15rem 1.1rem;
}

.log-file .log-file__meta {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: 1.2fr 1fr;
  margin: 0;
}

.log-file .log-file__meta section {
  align-items: center;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  box-shadow: 0 7px 18px rgba(24, 62, 81, 0.045);
  display: flex;
  flex-direction: row;
  gap: 0.72rem;
  min-height: 76px;
  padding: 0.78rem 0.88rem;
}

.log-file__meta-icon {
  align-items: center;
  background: #eaf4f7;
  border-radius: 11px;
  color: var(--blue) !important;
  display: inline-flex;
  flex: 0 0 42px;
  font-size: 1.18rem !important;
  height: 42px;
  justify-content: center;
}

.log-file__meta-icon.is-owner {
  background: #eaf7f3;
  color: var(--green) !important;
}

.log-file__meta section > div {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.log-file .log-file__meta section div > span {
  color: #7e909a;
  font-size: 0.57rem;
  font-weight: 820;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.log-file .log-file__meta strong {
  color: var(--ink);
  font-size: 0.76rem;
  line-height: 1.35;
  margin-top: 0.12rem;
  overflow-wrap: anywhere;
}

.log-file .log-file__meta small {
  color: #84949d;
  font-size: 0.61rem;
  margin-top: 0.08rem;
}

.log-file__details {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: 1.15fr 1fr;
  margin-top: 0.75rem;
}

.log-file__detail {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  min-height: 132px;
  padding: 0.85rem 0.9rem;
}

.log-file__detail.is-wide {
  grid-column: 1 / -1;
}

.log-file__detail-heading {
  align-items: center;
  display: flex;
  gap: 0.58rem;
}

.log-file__detail-heading > span {
  align-items: center;
  background: #eaf4f7;
  border-radius: 9px;
  color: var(--blue);
  display: inline-flex;
  flex: 0 0 34px;
  font-size: 1rem;
  height: 34px;
  justify-content: center;
}

.log-file__detail.is-action .log-file__detail-heading > span {
  background: #eaf7f3;
  color: var(--green);
}

.log-file__detail-heading div {
  display: flex;
  flex-direction: column;
}

.log-file__detail-heading small {
  color: #8a9aa3;
  font-size: 0.53rem;
  font-weight: 760;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.log-file__detail-heading strong {
  color: var(--ink);
  font-size: 0.7rem;
}

.log-file__detail > p {
  color: #4f6774;
  font-size: 0.7rem;
  line-height: 1.65;
  margin: 0.68rem 0 0;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.log-file__continuity {
  align-items: center;
  background: #edf8f4;
  border: 1px solid #cde9de;
  border-radius: 13px;
  color: #176b57;
  display: flex;
  gap: 0.68rem;
  margin-top: 0.75rem;
  padding: 0.68rem 0.8rem;
}

.log-file__continuity.pending {
  background: #fff8eb;
  border-color: #ecd5a9;
  color: #8a5b17;
}

.log-file__continuity > span {
  align-items: center;
  background: rgba(255, 255, 255, 0.75);
  border-radius: 9px;
  display: inline-flex;
  flex: 0 0 36px;
  font-size: 1.08rem;
  height: 36px;
  justify-content: center;
}

.log-file__continuity > div {
  display: grid;
  flex: 1;
  grid-template-columns: auto 1fr;
  min-width: 0;
}

.log-file__continuity small {
  font-size: 0.52rem;
  font-weight: 800;
  grid-column: 1;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.log-file__continuity strong {
  font-size: 0.7rem;
  grid-column: 1;
}

.log-file__continuity p {
  align-self: center;
  color: inherit;
  font-size: 0.62rem;
  grid-column: 2;
  grid-row: 1 / 3;
  margin: 0 0 0 1rem;
  opacity: 0.82;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.log-file__footer {
  align-items: center;
  border-top: 1px solid var(--line);
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  margin-top: 0.85rem;
  padding-top: 0.85rem;
}

.log-file__privacy {
  align-items: center;
  color: #6f838e;
  display: flex;
  gap: 0.48rem;
}

.log-file__privacy > i {
  color: var(--teal);
  font-size: 1.15rem;
}

.log-file__privacy span {
  display: flex;
  flex-direction: column;
}

.log-file__privacy strong {
  color: var(--ink);
  font-size: 0.62rem;
}

.log-file__privacy small {
  font-size: 0.54rem;
}

.log-file__actions {
  display: flex;
  gap: 0.5rem;
}

.log-file__secondary-action,
.log-file__primary-action {
  align-items: center;
  border-radius: 10px;
  display: inline-flex;
  font-size: 0.7rem;
  font-weight: 760;
  gap: 0.35rem;
  justify-content: center;
  min-height: 38px;
  padding: 0.55rem 0.85rem;
}

.log-file__secondary-action {
  background: #fff;
  border: 1px solid #d7e3e8;
  color: #536c78;
}

.log-file__secondary-action:hover {
  background: #edf3f5;
  border-color: #c7d7de;
  color: var(--ink);
}

.log-file__primary-action {
  background: linear-gradient(120deg, var(--blue), var(--teal));
  border: 0;
  box-shadow: 0 8px 18px rgba(23, 107, 135, 0.22);
  color: #fff;
}

.log-file__primary-action:hover,
.log-file__primary-action:focus-visible {
  box-shadow: 0 10px 22px rgba(23, 107, 135, 0.3);
  color: #fff;
  filter: brightness(1.05);
}
@media(max-width:1200px){.nursing-log-summary{grid-template-columns:repeat(2,1fr)}.nursing-log-filters{grid-template-columns:1fr 1fr 1fr}.nursing-log-search{grid-column:1/-1}.nursing-log-hero__guardrail{display:none}}
@media(max-width:768px){.nursing-log-summary{grid-template-columns:1fr}.nursing-log-panel__heading{align-items:flex-start;flex-direction:column;gap:.8rem}.nursing-log-create{justify-content:center;width:100%}.nursing-log-filters,.form-grid,.form-grid--top{grid-template-columns:1fr}.nursing-log-search{grid-column:auto}.nursing-log-pagination{align-items:flex-start;flex-direction:column;gap:.6rem}.log-file__meta{grid-template-columns:1fr}}

@media (max-width: 768px) {
  :global(.nursing-log-file-modal .modal-dialog) {
    margin: 0.5rem;
    max-width: calc(100vw - 1rem);
  }

  .log-file > .log-file__hero {
    align-items: flex-start;
    grid-template-columns: auto minmax(0, 1fr);
    min-height: 0;
    padding: 1.05rem 3.6rem 1.05rem 1rem;
  }

  .log-file__hero-badges {
    align-items: flex-start;
    flex-direction: row;
    flex-wrap: wrap;
    grid-column: 2;
  }

  .log-file .log-file__meta,
  .log-file__details {
    grid-template-columns: 1fr;
  }

  .log-file__detail.is-wide {
    grid-column: auto;
  }

  .log-file__continuity > div {
    display: flex;
    flex-direction: column;
  }

  .log-file__continuity p {
    margin: 0.18rem 0 0;
  }
}

@media (max-width: 575.98px) {
  .log-file__hero-icon {
    display: none;
  }

  .log-file > .log-file__hero {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
    padding: 1.05rem 3.5rem 1.05rem 1rem;
  }

  .log-file__content {
    padding: 0.75rem;
  }

  .log-file .log-file__meta section {
    min-height: 70px;
  }

  .log-file__footer {
    align-items: stretch;
    flex-direction: column;
  }

  .log-file__privacy {
    justify-content: center;
  }

  .log-file__actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }
}
</style>
