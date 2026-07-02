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
      error: null,
      catalogs: {
        academic_years: [],
        education_levels: [],
        active_academic_year_id: null,
      },
      selectedAcademicYearId: null,
      courseSections: [],
      selectedCourse: null,
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
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async showCourse(course) {
      const response = await axios.get(`/api/students/courses/${course.id}`);
      this.selectedCourse = response.data.data;
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

    <div class="row g-3">
      <div class="col-lg-7">
        <BCard>
          <BTable
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
              <div class="d-flex gap-2">
                <BButton size="sm" variant="outline-primary" @click="showCourse(item)">Ver curso</BButton>
                <BButton size="sm" variant="outline-secondary" @click="openEdit(item)">Editar</BButton>
              </div>
            </template>
          </BTable>
        </BCard>
      </div>

      <div class="col-lg-5">
        <BCard>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Curso seleccionado</h5>
            <span class="text-muted small">{{ selectedCourse?.display_name || "Sin selección" }}</span>
          </div>

          <div v-if="!selectedCourse" class="text-muted">Selecciona un curso para revisar a sus estudiantes matriculadas.</div>

          <template v-else>
            <div class="mb-3">
              <div><span class="text-muted">Nivel:</span> {{ selectedCourse.education_level?.name }}</div>
              <div><span class="text-muted">Capacidad:</span> {{ selectedCourse.capacity || "-" }}</div>
              <div><span class="text-muted">Matrículas:</span> {{ (selectedCourse.enrollments || []).length }}</div>
            </div>

            <BTable
              :items="selectedCourse.enrollments || []"
              small
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
            </BTable>
          </template>
        </BCard>
      </div>
    </div>

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
