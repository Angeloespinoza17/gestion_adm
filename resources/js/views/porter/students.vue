<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStudentSummaryCard from "../../components/porter/student-summary-card.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

export default {
  components: { Layout, LoadingState, PorterStudentSummaryCard, PorterStatusBadge },
  data() {
    return {
      loading: false,
      detailLoading: false,
      error: null,
      catalogs: {
        courses: [],
        education_levels: [],
      },
      filters: {
        search: "",
        course_section_id: null,
        education_level_id: null,
      },
      students: [],
      pagination: { current_page: 1, total: 0, per_page: 12 },
      selectedStudent: null,
    };
  },
  computed: {
    courseOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.courses || []).map((course) => ({ value: course.id, text: course.display_name }))
      );
    },
    levelOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.education_levels || []).map((level) => ({ value: level.id, text: level.name }))
      );
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadStudents();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/porter/catalogs");
      this.catalogs = response.data;
    },
    async loadStudents(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/students", {
          params: {
            page,
            search: this.filters.search || null,
            course_section_id: this.filters.course_section_id,
            education_level_id: this.filters.education_level_id,
          },
        });
        this.students = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 12,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async openStudent(student) {
      this.detailLoading = true;
      try {
        const response = await axios.get(`/api/porter/students/${student.id}`);
        this.selectedStudent = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.detailLoading = false;
      }
    },
    goToWithdrawal(student) {
      this.$router.push({ path: "/porter/withdrawals", query: { student_id: student.id } });
    },
    resetFilters() {
      this.filters = {
        search: "",
        course_section_id: null,
        education_level_id: null,
      };
      this.loadStudents(1);
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudieron cargar las estudiantes.";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0">Consulta de estudiantes</h4>
        <div class="text-muted">Búsqueda rápida para uso operativo de portería.</div>
      </div>
      <router-link to="/porter/withdrawals" class="btn btn-primary">Registrar retiro</router-link>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-7">
        <BCard class="mb-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre, apellido o RUT</label>
              <BFormInput v-model="filters.search" placeholder="Buscar estudiante o apoderado" @keyup.enter="loadStudents(1)" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Nivel</label>
              <BFormSelect v-model="filters.education_level_id" :options="levelOptions" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Curso</label>
              <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
            </div>
            <div class="col-12 d-flex gap-2">
              <BButton variant="primary" @click="loadStudents(1)">Buscar</BButton>
              <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
            </div>
          </div>
        </BCard>

        <BCard>
          <BTable
            :items="students"
            :busy="loading"
            responsive
            :fields="[
              { key: 'full_name', label: 'Estudiante' },
              { key: 'course', label: 'Curso' },
              { key: 'status', label: 'Estado' },
              { key: 'guardian', label: 'Apoderado' },
              { key: 'actions', label: 'Acciones' },
            ]"
          >
            <template #table-busy>
              <LoadingState message="Buscando estudiantes..." compact />
            </template>
            <template #cell(full_name)="{ item }">
              <div class="fw-semibold">{{ item.full_name }}</div>
              <div class="small text-muted">{{ item.rut || "Sin RUT" }}</div>
            </template>
            <template #cell(course)="{ item }">
              <div>{{ item.current_enrollment?.course_name || "-" }}</div>
              <div class="small text-muted">{{ item.current_enrollment?.academic_year_name || "-" }}</div>
            </template>
            <template #cell(status)="{ item }">
              <PorterStatusBadge :value="item.current_enrollment?.enrollment_status" :label="item.current_enrollment?.enrollment_status || '-'" />
            </template>
            <template #cell(guardian)="{ item }">
              <div>{{ item.guardian_name || "-" }}</div>
              <div class="small text-muted">{{ item.guardian_phone || "Sin teléfono" }}</div>
            </template>
            <template #cell(actions)="{ item }">
              <div class="d-flex gap-2">
                <BButton variant="outline-primary" size="sm" @click="openStudent(item)">Ver ficha</BButton>
                <BButton variant="primary" size="sm" @click="goToWithdrawal(item)">Retirar</BButton>
              </div>
            </template>
          </BTable>

          <div class="d-flex justify-content-end mt-3">
            <BPagination
              v-model="pagination.current_page"
              :total-rows="pagination.total"
              :per-page="pagination.per_page"
              @update:model-value="loadStudents"
            />
          </div>
        </BCard>
      </div>

      <div class="col-xl-5">
        <LoadingState v-if="detailLoading" message="Cargando ficha..." compact />
        <PorterStudentSummaryCard v-else :student="selectedStudent" @register-withdrawal="goToWithdrawal" />
      </div>
    </div>
  </Layout>
</template>
