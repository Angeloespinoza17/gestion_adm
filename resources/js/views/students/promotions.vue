<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      catalogs: {
        academic_years: [],
        promotion_statuses: [],
        active_academic_year_id: null,
      },
      sourceAcademicYearId: null,
      sourceCourseSectionId: null,
      destinationAcademicYearId: null,
      destinationCourseSectionId: null,
      sourceCourses: [],
      destinationCourses: [],
      candidates: [],
    };
  },
  computed: {
    academicYearOptions() {
      return [{ value: null, text: "Seleccionar..." }].concat(
        (this.catalogs.academic_years || []).map((year) => ({
          value: year.id,
          text: `${year.name}${year.is_active ? " · activo" : ""}`,
        }))
      );
    },
    sourceCourseOptions() {
      return [{ value: null, text: "Seleccionar..." }].concat(
        (this.sourceCourses || []).map((course) => ({
          value: course.id,
          text: course.display_name,
        }))
      );
    },
    destinationCourseOptions() {
      return [{ value: null, text: "Seleccionar..." }].concat(
        (this.destinationCourses || []).map((course) => ({
          value: course.id,
          text: course.display_name,
        }))
      );
    },
    promotionStatusOptions() {
      return (this.catalogs.promotion_statuses || []).map((status) => ({
        value: status.value,
        text: status.label,
      }));
    },
    selectedRows() {
      return this.candidates.filter((item) => item.selected);
    },
  },
  async mounted() {
    await this.loadCatalogs();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/students/catalogs");
      this.catalogs = response.data;
      this.sourceAcademicYearId = this.catalogs.active_academic_year_id || null;
      await this.loadSourceCourses();
    },
    async loadSourceCourses() {
      if (!this.sourceAcademicYearId) {
        this.sourceCourses = [];
        return;
      }

      const response = await axios.get("/api/students/courses", {
        params: { academic_year_id: this.sourceAcademicYearId },
      });
      this.sourceCourses = response.data.data || [];
      this.sourceCourseSectionId = null;
      this.candidates = [];
    },
    async loadDestinationCourses() {
      if (!this.destinationAcademicYearId) {
        this.destinationCourses = [];
        return;
      }

      const response = await axios.get("/api/students/courses", {
        params: { academic_year_id: this.destinationAcademicYearId },
      });
      this.destinationCourses = response.data.data || [];
    },
    async loadCandidates() {
      if (!this.sourceCourseSectionId) {
        this.candidates = [];
        return;
      }

      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get(`/api/students/courses/${this.sourceCourseSectionId}`);
        this.candidates = (response.data.data.enrollments || []).map((item) => ({
          student_profile_id: item.student_profile?.id,
          student_name: item.student_profile?.full_name || "-",
          selected: true,
          from_course: item.snapshot_course_display_name,
          promotion_status: "promovida",
          to_course_section_id: this.destinationCourseSectionId || null,
          notes: "",
        }));
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async save() {
      if (!this.selectedRows.length) {
        this.showErrorAlert("Selecciona al menos una estudiante para promover.");
        return;
      }

      const result = await Swal.fire({
        title: "Confirmar promoción",
        text: "Se crearán nuevas matrículas en el año destino sin modificar el historial anterior.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, ejecutar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
      });

      if (!result.isConfirmed) {
        return;
      }

      this.saving = true;
      this.error = null;
      try {
        await axios.post("/api/students/promotions", {
          from_academic_year_id: this.sourceAcademicYearId,
          from_course_section_id: this.sourceCourseSectionId,
          to_academic_year_id: this.destinationAcademicYearId,
          to_course_section_id: this.destinationCourseSectionId,
          students: this.selectedRows.map((item) => ({
            student_profile_id: item.student_profile_id,
            promotion_status: item.promotion_status,
            to_course_section_id: item.to_course_section_id || null,
            notes: item.notes || null,
          })),
        });

        this.showSuccessAlert("Promoción ejecutada", "Se registraron las nuevas matrículas sin sobrescribir años anteriores.");
        await this.loadCandidates();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.saving = false;
      }
    },
    showSuccessAlert(title, text) {
      return Swal.fire({
        title,
        text,
        icon: "success",
        timer: 1800,
        showConfirmButton: false,
      });
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
        <h4 class="mb-0">Promoción anual</h4>
        <div class="text-muted">Crea nuevas matrículas para el año siguiente sin sobrescribir el historial.</div>
      </div>
      <router-link to="/students" class="btn btn-outline-secondary">Volver</router-link>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Año origen</label>
          <BFormSelect v-model="sourceAcademicYearId" :options="academicYearOptions" @change="loadSourceCourses" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Curso origen</label>
          <BFormSelect v-model="sourceCourseSectionId" :options="sourceCourseOptions" @change="loadCandidates" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Año destino</label>
          <BFormSelect v-model="destinationAcademicYearId" :options="academicYearOptions" @change="loadDestinationCourses" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Curso destino por defecto</label>
          <BFormSelect v-model="destinationCourseSectionId" :options="destinationCourseOptions" />
        </div>
      </div>
      <div class="alert alert-warning mt-3 mb-0">
        La promoción nunca modifica la matrícula del año origen. Cada cambio genera un nuevo registro para el año destino.
      </div>
    </BCard>

    <BCard>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Estudiantes candidatas</h5>
        <BButton variant="primary" :disabled="saving || !selectedRows.length" @click="save">
          {{ saving ? "Procesando..." : `Ejecutar promoción (${selectedRows.length})` }}
        </BButton>
      </div>

      <BTable
        :items="candidates"
        :busy="loading"
        responsive
        :fields="[
          { key: 'selected', label: '' },
          { key: 'student_name', label: 'Estudiante' },
          { key: 'from_course', label: 'Origen' },
          { key: 'promotion_status', label: 'Resultado' },
          { key: 'to_course_section_id', label: 'Curso destino' },
          { key: 'notes', label: 'Notas' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando candidatas..." compact />
        </template>
        <template #cell(selected)="{ item }">
          <BFormCheckbox v-model="item.selected" />
        </template>
        <template #cell(promotion_status)="{ item }">
          <BFormSelect v-model="item.promotion_status" :options="promotionStatusOptions" />
        </template>
        <template #cell(to_course_section_id)="{ item }">
          <BFormSelect v-model="item.to_course_section_id" :options="destinationCourseOptions" />
        </template>
        <template #cell(notes)="{ item }">
          <BFormInput v-model="item.notes" placeholder="Opcional" />
        </template>
      </BTable>
    </BCard>
  </Layout>
</template>
