<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  academic_year_id: null,
  education_level_id: null,
  section_name: "A",
  capacity: 35,
  active: true,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      deletingCourseId: null,
      error: null,
      catalogs: {
        academic_years: [],
        education_levels: [],
        active_academic_year_id: null,
      },
      selectedAcademicYearId: null,
      courseSections: [],
      selectedCourse: null,
      selectedCourseLoading: false,
      showCourseModal: false,
      showModal: false,
      form: emptyForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    academicYearOptions() {
      return (this.catalogs.academic_years || []).map((year) => ({
        value: year.id,
        text: `${year.name}${year.is_active ? " · activo" : ""}`,
      }));
    },
    levelOptions() {
      return [{ value: null, text: "Seleccionar..." }].concat(
        (this.catalogs.education_levels || []).map((level) => ({
          value: level.id,
          text: level.name,
        }))
      );
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadCourses();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/students/catalogs");
      this.catalogs = response.data;
      this.selectedAcademicYearId = this.catalogs.active_academic_year_id || this.catalogs.academic_years?.[0]?.id || null;
    },
    async loadCourses() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/students/courses", {
          params: { academic_year_id: this.selectedAcademicYearId },
        });
        this.courseSections = response.data.data || [];
        this.selectedCourse = null;
        this.showCourseModal = false;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async showCourse(course) {
      this.selectedCourse = null;
      this.selectedCourseLoading = true;
      this.showCourseModal = true;
      this.error = null;

      try {
        const response = await axios.get(`/api/students/courses/${course.id}`);
        this.selectedCourse = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
        this.showCourseModal = false;
        this.showErrorAlert(this.error);
      } finally {
        this.selectedCourseLoading = false;
      }
    },
    openCreate() {
      this.form = {
        ...emptyForm(),
        academic_year_id: this.selectedAcademicYearId,
      };
      this.showModal = true;
    },
    openEdit(course) {
      this.form = {
        id: course.id,
        academic_year_id: course.academic_year_id,
        education_level_id: course.education_level_id,
        section_name: course.section_name,
        capacity: course.capacity || 35,
        active: Boolean(course.active),
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/students/courses/${this.form.id}`, this.form);
        } else {
          await axios.post("/api/students/courses", this.form);
        }
        this.showModal = false;
        await this.loadCourses();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.saving = false;
      }
    },
    async deleteCourse(course) {
      const confirmation = await Swal.fire({
        title: `Eliminar ${course.display_name}`,
        text: "Esta acción solo se realizará si el curso no tiene información académica asociada.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#dc3545",
        reverseButtons: true,
      });

      if (!confirmation.isConfirmed) return;

      this.deletingCourseId = course.id;
      this.error = null;
      try {
        const response = await axios.delete(`/api/students/courses/${course.id}`);
        if (this.selectedCourse?.id === course.id) this.showCourseModal = false;
        await this.loadCourses();
        await Swal.fire({
          title: "Curso eliminado",
          text: response.data?.message || "El curso fue eliminado correctamente.",
          icon: "success",
          confirmButtonText: "Aceptar",
        });
      } catch (error) {
        this.error = error?.response?.data?.message || this.formatError(error);
        await this.showErrorAlert(this.error);
      } finally {
        this.deletingCourseId = null;
      }
    },
    showErrorAlert(text) {
      return Swal.fire({ title: "Error", text, icon: "error" });
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
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Cursos por año</h4>
        <div class="text-muted">Cada curso pertenece a un año académico específico.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/students" class="btn btn-outline-secondary">Volver</router-link>
        <router-link to="/students/levels" class="btn btn-outline-secondary">Niveles</router-link>
        <BButton variant="primary" @click="openCreate">Nuevo curso</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="!catalogs.education_levels?.length" variant="warning" show class="mb-3">
      No hay niveles cargados. Crea niveles desde
      <router-link to="/students/levels">Niveles</router-link>
      o ejecuta el seeder `EducationLevelSeeder`.
    </BAlert>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Año académico</label>
          <BFormSelect v-model="selectedAcademicYearId" :options="academicYearOptions" @change="loadCourses" />
        </div>
      </div>
    </BCard>

    <BCard class="students-courses-card">
      <BTable
        class="students-courses-table"
        :items="courseSections"
        :busy="loading"
        responsive
        :fields="[
          { key: 'display_name', label: 'Curso' },
          { key: 'capacity', label: 'Capacidad' },
          { key: 'enrollments_count', label: 'Matriculadas' },
          { key: 'active', label: 'Activo' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando cursos..." compact />
        </template>
        <template #cell(active)="{ item }">
          <BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? "Sí" : "No" }}</BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="students-courses-actions">
            <BButton size="sm" variant="outline-primary" @click="showCourse(item)">Ver curso</BButton>
            <BButton size="sm" variant="warning" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" variant="outline-danger" :disabled="deletingCourseId === item.id" @click="deleteCourse(item)">
              <i class="bx" :class="deletingCourseId === item.id ? 'bx-loader-alt bx-spin' : 'bx-trash'"></i>
              <span>{{ deletingCourseId === item.id ? "Eliminando" : "Eliminar" }}</span>
            </BButton>
          </div>
        </template>
      </BTable>
    </BCard>

    <BModal
      v-model="showCourseModal"
      :title="selectedCourse?.display_name || 'Curso seleccionado'"
      size="xl"
      hide-footer
      @hidden="selectedCourse = null"
    >
      <LoadingState v-if="selectedCourseLoading" message="Cargando curso..." compact />

      <template v-else-if="selectedCourse">
        <div class="course-detail-summary">
          <div class="course-detail-summary__item">
            <span>Nivel</span>
            <strong>{{ selectedCourse.education_level?.name || "-" }}</strong>
          </div>
          <div class="course-detail-summary__item">
            <span>Capacidad</span>
            <strong>{{ selectedCourse.capacity || "-" }}</strong>
          </div>
          <div class="course-detail-summary__item">
            <span>Matrículas</span>
            <strong>{{ (selectedCourse.enrollments || []).length }}</strong>
          </div>
          <div class="course-detail-summary__item">
            <span>Estado</span>
            <strong>{{ selectedCourse.active ? "Activo" : "Inactivo" }}</strong>
          </div>
        </div>

        <BTable
          class="course-detail-enrollments-table"
          :items="selectedCourse.enrollments || []"
          responsive
          :fields="[
            { key: 'student', label: 'Estudiante' },
            { key: 'enrollment_status', label: 'Estado' },
            { key: 'enrolled_at', label: 'Matrícula' },
          ]"
        >
          <template #cell(student)="{ item }">
            <router-link :to="`/students/${item.student_profile?.id}`">
              {{ item.student_profile?.full_name || "-" }}
            </router-link>
          </template>
          <template #empty>
            <div class="text-center text-muted py-3">Este curso no tiene estudiantes matriculadas.</div>
          </template>
        </BTable>
      </template>
    </BModal>

    <BModal v-model="showModal" :title="isEditing ? 'Editar curso' : 'Nuevo curso'" hide-footer>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Año académico</label>
          <BFormSelect v-model="form.academic_year_id" :options="academicYearOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Nivel</label>
          <BFormSelect v-model="form.education_level_id" :options="levelOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Paralelo</label>
          <BFormSelect v-model="form.section_name" :options="['A', 'B', 'C']" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Capacidad</label>
          <BFormInput v-model="form.capacity" type="number" min="1" />
        </div>
        <div class="col-md-12">
          <BFormCheckbox v-model="form.active">Curso activo</BFormCheckbox>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.students-courses-card {
  width: 100%;
}

.students-courses-table {
  margin-bottom: 0;
}

.students-courses-table :deep(th),
.students-courses-table :deep(td),
.course-detail-enrollments-table :deep(th),
.course-detail-enrollments-table :deep(td) {
  text-align: center;
  vertical-align: middle;
}

.students-courses-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.students-courses-actions :deep(.btn) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.3rem;
}

.course-detail-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.course-detail-summary__item {
  min-width: 0;
  padding: 0.75rem 0.9rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}

.course-detail-summary__item span {
  display: block;
  margin-bottom: 0.25rem;
  color: #64748b;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  line-height: 1;
  text-transform: uppercase;
}

.course-detail-summary__item strong {
  display: block;
  overflow-wrap: anywhere;
  color: #334155;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.25;
}

.course-detail-enrollments-table {
  margin-bottom: 0;
}

@media (max-width: 991.98px) {
  .course-detail-summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 575.98px) {
  .students-courses-actions {
    justify-content: center;
  }

  .course-detail-summary {
    grid-template-columns: 1fr;
  }
}
</style>
