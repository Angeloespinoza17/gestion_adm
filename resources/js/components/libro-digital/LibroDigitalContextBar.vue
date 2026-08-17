<script setup>
import { computed, reactive, watch } from "vue";
import { bookLabel } from "./module-utils";

const props = defineProps({
  modelValue: { type: Object, required: true },
  catalogs: { type: Object, default: () => ({}) },
  books: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["apply", "refresh"]);
const local = reactive({});

const sync = () => Object.assign(local, {
  school_id: props.modelValue.school_id ?? null,
  academic_year_id: props.modelValue.academic_year_id ?? null,
  education_level_id: props.modelValue.education_level_id ?? null,
  course_section_id: props.modelValue.course_section_id ?? null,
  schedule_subject_id: props.modelValue.schedule_subject_id ?? null,
  book_id: props.modelValue.book_id ?? null,
});

watch(() => props.modelValue, sync, { immediate: true, deep: true });

const schools = computed(() => props.catalogs.schools || props.catalogs.establishments || []);
const years = computed(() => props.catalogs.academic_years || []);
const levels = computed(() => props.catalogs.education_levels || []);
const allCourses = computed(() => props.catalogs.course_sections || props.catalogs.courses || []);
const courses = computed(() => allCourses.value.filter((course) =>
  !local.education_level_id || Number(course.education_level_id) === Number(local.education_level_id),
));
const subjects = computed(() => props.catalogs.subjects || props.catalogs.schedule_subjects || []);
const activeFilterCount = computed(() => [
  local.school_id,
  local.academic_year_id,
  local.education_level_id,
  local.course_section_id,
  local.schedule_subject_id,
  local.book_id,
].filter((value) => value !== null && value !== undefined && value !== "").length);

const apply = () => emit("apply", { ...local });
</script>

<template>
  <section class="lcd-context" aria-label="Contexto académico global">
    <header class="lcd-context__header">
      <div class="lcd-context__heading">
        <span class="lcd-context__icon" aria-hidden="true"><i class="bx bx-slider-alt"></i></span>
        <div>
          <span class="lcd-eyebrow">Contexto académico</span>
          <strong>Delimita la información visible</strong>
          <p>Los cambios se aplican a todas las áreas del módulo.</p>
        </div>
      </div>
      <span class="lcd-context__count" aria-label="Cantidad de filtros seleccionados">{{ activeFilterCount }} seleccionados</span>
    </header>

    <div class="lcd-context__form">
      <div v-if="schools.length > 1" class="lcd-context__field lcd-context__field--school">
        <label for="lcd-context-school"><i class="bx bx-buildings" aria-hidden="true"></i> Establecimiento</label>
        <BFormSelect id="lcd-context-school" v-model="local.school_id" size="sm">
          <option :value="null">Seleccionar establecimiento</option>
          <option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}<template v-if="school.rbd"> · RBD {{ school.rbd }}</template></option>
        </BFormSelect>
      </div>
      <div class="lcd-context__field">
        <label for="lcd-context-year"><i class="bx bx-calendar" aria-hidden="true"></i> Año académico</label>
        <BFormSelect id="lcd-context-year" v-model="local.academic_year_id" size="sm">
          <option :value="null">Seleccionar año</option>
          <option v-for="year in years" :key="year.id" :value="year.id">{{ year.name || year.year }}{{ year.is_active ? " · activo" : "" }}</option>
        </BFormSelect>
      </div>
      <div v-if="levels.length" class="lcd-context__field">
        <label for="lcd-context-level"><i class="bx bx-layer" aria-hidden="true"></i> Nivel</label>
        <BFormSelect id="lcd-context-level" v-model="local.education_level_id" size="sm">
          <option :value="null">Todos los niveles</option>
          <option v-for="level in levels" :key="level.id" :value="level.id">{{ level.name }}</option>
        </BFormSelect>
      </div>
      <div class="lcd-context__field">
        <label for="lcd-context-course"><i class="bx bx-group" aria-hidden="true"></i> Curso</label>
        <BFormSelect id="lcd-context-course" v-model="local.course_section_id" size="sm">
          <option :value="null">Todos los cursos</option>
          <option v-for="course in courses" :key="course.id" :value="course.id">{{ course.display_name || course.name }}</option>
        </BFormSelect>
      </div>
      <div v-if="subjects.length" class="lcd-context__field">
        <label for="lcd-context-subject"><i class="bx bx-grid-alt" aria-hidden="true"></i> Asignatura</label>
        <BFormSelect id="lcd-context-subject" v-model="local.schedule_subject_id" size="sm">
          <option :value="null">Todas las asignaturas</option>
          <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
        </BFormSelect>
      </div>
      <div class="lcd-context__field lcd-context__field--book">
        <label for="lcd-context-book"><i class="bx bx-book-open" aria-hidden="true"></i> Libro activo</label>
        <BFormSelect id="lcd-context-book" v-model="local.book_id" size="sm">
          <option :value="null">Sin libro seleccionado</option>
          <option v-for="book in books" :key="book.id" :value="book.id">{{ bookLabel(book) }}</option>
        </BFormSelect>
      </div>
      <div class="lcd-context__actions">
        <BButton type="button" size="sm" variant="primary" :disabled="loading" @click="apply">
          <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
          <i v-else class="bx bx-check" aria-hidden="true"></i>
          <span>{{ loading ? "Aplicando…" : "Aplicar contexto" }}</span>
        </BButton>
        <BButton type="button" size="sm" variant="outline-secondary" :disabled="loading" aria-label="Actualizar catálogos y contexto" title="Actualizar catálogos y contexto" @click="$emit('refresh')">
          <i class="bx bx-reset" aria-hidden="true"></i>
        </BButton>
      </div>
    </div>
  </section>
</template>

<style scoped>
.lcd-context {
  overflow: hidden;
  border: 1px solid var(--lcd-border);
  border-radius: var(--lcd-radius-lg);
  background: var(--lcd-surface-raised);
  box-shadow: var(--lcd-shadow-sm);
}

.lcd-context__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--lcd-border);
  background: linear-gradient(90deg, var(--lcd-brand-50), var(--lcd-surface-raised) 72%);
}

.lcd-context__heading {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  min-width: 0;
}

.lcd-context__icon {
  display: grid;
  flex: 0 0 38px;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 11px;
  background: var(--lcd-brand-700);
  box-shadow: 0 5px 13px rgba(36, 84, 134, 0.18);
  color: #fff;
  font-size: 1.05rem;
}

.lcd-context__heading .lcd-eyebrow {
  margin-bottom: 0.04rem;
  font-size: 0.68rem;
}

.lcd-context__heading strong {
  display: block;
  color: var(--lcd-ink);
  font-size: 0.84rem;
  line-height: 1.25;
}

.lcd-context__heading p {
  margin: 0.12rem 0 0;
  color: var(--lcd-muted);
  font-size: 0.72rem;
}

.lcd-context__count {
  flex: 0 0 auto;
  padding: 0.28rem 0.5rem;
  border: 1px solid var(--lcd-border);
  border-radius: 999px;
  background: var(--lcd-surface);
  color: var(--lcd-muted);
  font-size: 0.7rem;
  font-weight: 700;
}

.lcd-context__form {
  display: grid;
  grid-template-columns: repeat(6, minmax(120px, 1fr)) auto;
  gap: 0.75rem;
  align-items: end;
  padding: 0.9rem 1rem 1rem;
}

.lcd-context__field {
  min-width: 0;
}

.lcd-context__field--school {
  grid-column: span 2;
}

.lcd-context__field label {
  display: flex;
  align-items: center;
  gap: 0.32rem;
  margin-bottom: 0.32rem;
  color: var(--lcd-muted);
  font-size: 0.72rem;
  font-weight: 750;
}

.lcd-context__field label i {
  color: var(--lcd-brand-600);
  font-size: 0.84rem;
}

.lcd-context__field :deep(.form-select) {
  min-height: 38px;
  border-color: var(--lcd-border-strong);
  background-color: var(--lcd-surface);
  color: var(--lcd-ink);
  font-size: 0.78rem;
}

.lcd-context__actions {
  display: flex;
  gap: 0.4rem;
}

.lcd-context__actions .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  min-height: 38px;
  white-space: nowrap;
}

.lcd-context__actions .btn:last-child {
  width: 38px;
  padding-inline: 0;
}

@media (max-width: 1399.98px) {
  .lcd-context__form {
    grid-template-columns: repeat(4, minmax(130px, 1fr)) auto;
  }

  .lcd-context__field--school {
    grid-column: span 1;
  }
}

@media (max-width: 899.98px) {
  .lcd-context__form {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .lcd-context__actions {
    grid-column: 1 / -1;
  }

  .lcd-context__actions .btn:first-child {
    flex: 1;
  }
}

@media (max-width: 575.98px) {
  .lcd-context__header {
    align-items: flex-start;
    padding: 0.75rem;
  }

  .lcd-context__heading p,
  .lcd-context__count {
    display: none;
  }

  .lcd-context__form {
    grid-template-columns: 1fr;
    padding: 0.75rem;
  }

  .lcd-context__actions,
  .lcd-context__field--school {
    grid-column: auto;
  }
}
</style>
