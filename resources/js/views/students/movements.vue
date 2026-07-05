<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";

const emptyActionForm = () => ({
  id: null,
  course_section_id: null,
  effective_date: new Date().toISOString().slice(0, 10),
  enrollment_status: "regular",
  notes: "",
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      actionLoading: false,
      error: null,
      catalogs: {
        academic_years: [],
        enrollment_statuses: [],
        active_academic_year_id: null,
      },
      courseSections: [],
      enrollments: [],
      summary: { total: 0, active: 0, retired: 0 },
      pagination: { current_page: 1, last_page: 1, total: 0 },
      filters: {
        academic_year_id: null,
        course_section_id: null,
        enrollment_status: null,
        search: "",
      },
      showModal: false,
      actionType: null,
      selectedEnrollment: null,
      actionForm: emptyActionForm(),
    };
  },
  computed: {
    activeAcademicYear() {
      const activeYearId = this.catalogs.active_academic_year_id;

      return (
        (this.catalogs.academic_years || []).find((year) => Number(year.id) === Number(activeYearId))
        || (this.catalogs.academic_years || []).find((year) => year.is_active)
        || null
      );
    },
    activeAcademicYearId() {
      return this.activeAcademicYear?.id || this.catalogs.active_academic_year_id || null;
    },
    academicYearOptions() {
      return this.activeAcademicYear
        ? [{ value: this.activeAcademicYear.id, text: `${this.activeAcademicYear.name} · activo` }]
        : [];
    },
    courseOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.courseSections || []).map((course) => ({
          value: course.id,
          text: course.display_name,
        }))
      );
    },
    actionCourseOptions() {
      return [{ value: null, text: "Seleccionar..." }].concat(
        (this.courseSections || []).map((course) => ({
          value: course.id,
          text: course.display_name,
        }))
      );
    },
    statusOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.enrollment_statuses || []).map((status) => ({
          value: status.value,
          text: status.label,
        }))
      );
    },
    reentryStatusOptions() {
      return [
        { value: "regular", text: "Regular" },
        { value: "matriculada", text: "Matriculada" },
        { value: "suspendida", text: "Suspendida" },
      ];
    },
    modalTitle() {
      return this.actionType === "transfer"
        ? "Cambiar de curso"
        : this.actionType === "withdraw"
          ? "Retirar estudiante"
          : "Reingresar estudiante";
    },
    enrollmentFields() {
      const headerClass = "text-center movements-table__head";
      const centeredCell = "text-center align-middle movements-table__cell";

      return [
        { key: "student", label: "Estudiante", thClass: headerClass, tdClass: `${centeredCell} movements-table__cell--student` },
        { key: "snapshot_course_display_name", label: "Curso actual", thClass: headerClass, tdClass: centeredCell },
        { key: "enrollment_status", label: "Estado", thClass: headerClass, tdClass: centeredCell },
        { key: "enrolled_at", label: "Matrícula", thClass: headerClass, tdClass: centeredCell },
        { key: "withdrawn_at", label: "Retiro", thClass: headerClass, tdClass: centeredCell },
        { key: "last_movement", label: "Último movimiento", thClass: headerClass, tdClass: `${centeredCell} movements-table__cell--last-movement` },
        { key: "actions", label: "Acciones", thClass: headerClass, tdClass: `${centeredCell} movements-table__cell--actions` },
      ];
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadCourses();
    await this.loadEnrollments();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/students/catalogs");
      this.catalogs = response.data;
      this.filters.academic_year_id = this.activeAcademicYearId;
    },
    async loadCourses() {
      if (!this.activeAcademicYearId) {
        this.courseSections = [];
        return;
      }

      const response = await axios.get("/api/students/courses", {
        params: {
          academic_year_id: this.activeAcademicYearId,
        },
      });
      this.courseSections = response.data.data || [];
    },
    async loadEnrollments(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/students/enrollment-management", {
          params: {
            page,
            academic_year_id: this.activeAcademicYearId,
            course_section_id: this.filters.course_section_id,
            enrollment_status: this.filters.enrollment_status,
            search: this.filters.search || null,
          },
        });

        this.enrollments = response.data.data || [];
        this.summary = response.data.summary || { total: 0, active: 0, retired: 0 };
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async onAcademicYearChange() {
      this.filters.academic_year_id = this.activeAcademicYearId;
      this.filters.course_section_id = null;
      await this.loadCourses();
      await this.loadEnrollments(1);
    },
    openTransfer(item) {
      this.actionType = "transfer";
      this.selectedEnrollment = item;
      this.actionForm = {
        ...emptyActionForm(),
        id: item.id,
        course_section_id: item.course_section_id,
      };
      this.showModal = true;
    },
    openWithdraw(item) {
      this.actionType = "withdraw";
      this.selectedEnrollment = item;
      this.actionForm = {
        ...emptyActionForm(),
        id: item.id,
      };
      this.showModal = true;
    },
    openReenter(item) {
      this.actionType = "reenter";
      this.selectedEnrollment = item;
      this.actionForm = {
        ...emptyActionForm(),
        id: item.id,
        course_section_id: item.course_section_id,
        enrollment_status: "regular",
      };
      this.showModal = true;
    },
    quickFilter(status) {
      this.filters.enrollment_status = status;
      this.loadEnrollments(1);
    },
    async submitAction() {
      if (!this.selectedEnrollment) {
        return;
      }

      const result = await this.confirmAction();
      if (!result.isConfirmed) {
        return;
      }

      this.actionLoading = true;
      this.error = null;
      try {
        if (this.actionType === "transfer") {
          await axios.post(`/api/students/enrollment-management/${this.selectedEnrollment.id}/transfer`, {
            course_section_id: this.actionForm.course_section_id,
            effective_date: this.actionForm.effective_date,
            notes: this.actionForm.notes,
          });
        } else if (this.actionType === "withdraw") {
          await axios.post(`/api/students/enrollment-management/${this.selectedEnrollment.id}/withdraw`, {
            effective_date: this.actionForm.effective_date,
            notes: this.actionForm.notes,
          });
        } else {
          await axios.post(`/api/students/enrollment-management/${this.selectedEnrollment.id}/reenter`, {
            course_section_id: this.actionForm.course_section_id,
            effective_date: this.actionForm.effective_date,
            enrollment_status: this.actionForm.enrollment_status,
            notes: this.actionForm.notes,
          });
        }

        this.showModal = false;
        await this.loadEnrollments(this.pagination.current_page || 1);
        this.showSuccessAlert("Movimiento guardado", "La matrícula anual fue actualizada correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.actionLoading = false;
      }
    },
    resetFilters() {
      this.filters = {
        academic_year_id: this.activeAcademicYearId,
        course_section_id: null,
        enrollment_status: null,
        search: "",
      };

      this.loadCourses().then(() => this.loadEnrollments(1));
    },
    lastMovement(item) {
      return item.movements?.[0] || null;
    },
    confirmAction() {
      const studentName = `${this.selectedEnrollment?.student_profile?.first_name || ""} ${this.selectedEnrollment?.student_profile?.last_name || ""}`.trim();
      const config = {
        transfer: {
          title: "Confirmar cambio de curso",
          text: `Se registrará un cambio de curso para ${studentName || "la estudiante"}.`,
          confirmButtonText: "Sí, cambiar",
          confirmButtonColor: "#556ee6",
        },
        withdraw: {
          title: "Confirmar retiro",
          text: `Se retirará a ${studentName || "la estudiante"} del año académico activo.`,
          confirmButtonText: "Sí, retirar",
          confirmButtonColor: "#f1b44c",
        },
        reenter: {
          title: "Confirmar reingreso",
          text: `Se reingresará a ${studentName || "la estudiante"} al año académico activo.`,
          confirmButtonText: "Sí, reingresar",
          confirmButtonColor: "#34c38f",
        },
      }[this.actionType] || {
        title: "Confirmar acción",
        text: "Se registrará el movimiento seleccionado.",
        confirmButtonText: "Confirmar",
        confirmButtonColor: "#556ee6",
      };

      return Swal.fire({
        ...config,
        icon: "warning",
        showCancelButton: true,
        cancelButtonText: "Cancelar",
      });
    },
    statusLabel(status) {
      return (this.catalogs.enrollment_statuses || []).find((option) => option.value === status)?.label || status || "-";
    },
    statusClass(status) {
      return {
        matriculada: "enrolled",
        regular: "regular",
        retirada: "retired",
        egresada: "graduated",
        suspendida: "suspended",
        trasladada: "transferred",
      }[status] || "neutral";
    },
    movementLabel(type) {
      return {
        matricula: "Matrícula",
        cambio_curso: "Cambio de curso",
        retiro: "Retiro",
        reingreso: "Reingreso",
      }[type] || type || "-";
    },
    movementClass(type) {
      return {
        matricula: "enrolled",
        cambio_curso: "transfer",
        retiro: "withdraw",
        reingreso: "reentry",
      }[type] || "neutral";
    },
    displayDate(value) {
      return value || "-";
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
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
      });
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
        <h4 class="mb-0">Cambios y retiros</h4>
        <div class="text-muted">Gestiona cambios de curso, retiros y reingresos por año académico.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/students" class="btn btn-outline-secondary">Volver</router-link>
        <router-link to="/students/courses" class="btn btn-outline-secondary">Cursos</router-link>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="row g-3 mb-3 movements-stats">
      <div class="col-md-4">
        <BCard class="movements-stat-card h-100">
          <div class="movements-stat-card__content">
            <div>
              <div class="movements-stat-card__label">Total año</div>
              <div class="movements-stat-card__value">{{ summary.total }}</div>
            </div>
          </div>
        </BCard>
      </div>
      <div class="col-md-4">
        <BCard class="movements-stat-card h-100">
          <div class="movements-stat-card__content">
            <div>
              <div class="movements-stat-card__label">Activas</div>
              <div class="movements-stat-card__value">{{ summary.active }}</div>
            </div>
            <BButton size="sm" variant="outline-success" @click="quickFilter(null)">Ver todas</BButton>
          </div>
        </BCard>
      </div>
      <div class="col-md-4">
        <BCard class="movements-stat-card h-100">
          <div class="movements-stat-card__content">
            <div>
              <div class="movements-stat-card__label">Retiradas</div>
              <div class="movements-stat-card__value">{{ summary.retired }}</div>
            </div>
            <BButton size="sm" variant="outline-warning" @click="quickFilter('retirada')">Ver retiradas</BButton>
          </div>
        </BCard>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Año académico</label>
          <BFormSelect v-model="filters.academic_year_id" :options="academicYearOptions" disabled @change="onAcademicYearChange" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Curso</label>
          <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.enrollment_status" :options="statusOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Nombre o RUT</label>
          <BFormInput v-model="filters.search" placeholder="Buscar..." @keyup.enter="loadEnrollments(1)" />
        </div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="primary" @click="loadEnrollments(1)">Buscar</BButton>
          <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="movements-table-card">
      <BTable
        class="movements-table"
        :items="enrollments"
        :busy="loading"
        responsive
        :fields="enrollmentFields"
      >
        <template #table-busy>
          <LoadingState message="Cargando matrículas..." compact />
        </template>

        <template #cell(student)="{ item }">
          <div class="movement-student-cell">
            <div class="movement-student-cell__name">{{ item.student_profile?.first_name }} {{ item.student_profile?.last_name }}</div>
            <div class="movement-student-cell__rut">{{ item.student_profile?.rut || "-" }}</div>
          </div>
        </template>

        <template #cell(snapshot_course_display_name)="{ item }">
          <span class="movement-course-pill">{{ item.snapshot_course_display_name || "-" }}</span>
        </template>

        <template #cell(enrollment_status)="{ item }">
          <span class="movement-status-chip" :class="`movement-status-chip--${statusClass(item.enrollment_status)}`">
            {{ statusLabel(item.enrollment_status) }}
          </span>
        </template>

        <template #cell(enrolled_at)="{ item }">
          <span class="movement-date-pill">{{ displayDate(item.enrolled_at) }}</span>
        </template>

        <template #cell(withdrawn_at)="{ item }">
          <span class="movement-date-pill" :class="{ 'movement-date-pill--empty': !item.withdrawn_at }">
            {{ displayDate(item.withdrawn_at) }}
          </span>
        </template>

        <template #cell(last_movement)="{ item }">
          <div v-if="lastMovement(item)" class="movement-last-cell">
            <span class="movement-type-chip" :class="`movement-type-chip--${movementClass(lastMovement(item).movement_type)}`">
              {{ movementLabel(lastMovement(item).movement_type) }}
            </span>
            <div class="movement-last-cell__route">
              <span>{{ lastMovement(item).snapshot_from_course_display_name || "-" }}</span>
              <span class="movement-last-cell__arrow" aria-hidden="true">&rarr;</span>
              <span>{{ lastMovement(item).snapshot_to_course_display_name || "-" }}</span>
            </div>
          </div>
          <span v-else>-</span>
        </template>

        <template #cell(actions)="{ item }">
          <div class="movement-actions">
            <router-link :to="`/students/${item.student_profile_id}`" class="btn btn-sm btn-outline-secondary">Ficha</router-link>
            <BButton v-if="item.enrollment_status !== 'retirada'" size="sm" variant="outline-primary" @click="openTransfer(item)">
              Cambiar curso
            </BButton>
            <BButton v-if="item.enrollment_status !== 'retirada'" size="sm" variant="outline-warning" @click="openWithdraw(item)">
              Retirar
            </BButton>
            <BButton v-if="item.enrollment_status === 'retirada'" size="sm" variant="outline-success" @click="openReenter(item)">
              Reingresar
            </BButton>
          </div>
        </template>
      </BTable>

      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">Total: {{ pagination.total }}</div>
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="20"
          @update:model-value="loadEnrollments"
        />
      </div>
    </BCard>

    <BModal v-model="showModal" :title="modalTitle" hide-footer>
      <div class="mb-3">
        <div class="fw-semibold">{{ selectedEnrollment?.student_profile?.first_name }} {{ selectedEnrollment?.student_profile?.last_name }}</div>
        <div class="text-muted small">
          {{ selectedEnrollment?.snapshot_year_name }} · {{ selectedEnrollment?.snapshot_course_display_name }}
        </div>
      </div>

      <div class="row g-3">
        <div v-if="actionType !== 'withdraw'" class="col-md-12">
          <label class="form-label">Curso destino</label>
          <BFormSelect v-model="actionForm.course_section_id" :options="actionCourseOptions" />
        </div>
        <div v-if="actionType === 'reenter'" class="col-md-12">
          <label class="form-label">Estado al reingresar</label>
          <BFormSelect v-model="actionForm.enrollment_status" :options="reentryStatusOptions" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Fecha efectiva</label>
          <BFormInput v-model="actionForm.effective_date" type="date" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Notas</label>
          <BFormTextarea v-model="actionForm.notes" rows="3" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="actionLoading" @click="submitAction">
          {{ actionLoading ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.movements-stats {
  align-items: stretch;
}

.movements-stat-card :deep(.card-body) {
  display: flex;
  height: 100%;
}

.movements-stat-card__content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  width: 100%;
  min-height: 5.75rem;
}

.movements-stat-card__label {
  margin-bottom: 0.35rem;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1;
}

.movements-stat-card__value {
  color: #334155;
  font-size: 1.55rem;
  font-weight: 700;
  line-height: 1;
}

.movements-table-card {
  overflow: hidden;
}

.movements-table-card :deep(.card-body) {
  overflow-x: auto;
}

.movements-table {
  width: 100%;
  min-width: 0;
  table-layout: fixed;
}

.movements-table :deep(table) {
  width: 100%;
  min-width: 0;
  table-layout: fixed;
}

.movements-table :deep(th),
.movements-table :deep(td) {
  text-align: center;
  vertical-align: middle;
  overflow-wrap: anywhere;
}

.movements-table :deep(.movements-table__head) {
  letter-spacing: 0.08em;
  white-space: normal;
}

.movements-table :deep(.movements-table__cell) {
  color: #4b5563;
}

.movements-table :deep(.movements-table__cell--student) {
  text-align: left;
}

.movements-table :deep(th:nth-child(1)),
.movements-table :deep(td:nth-child(1)) {
  width: 17%;
}

.movements-table :deep(th:nth-child(2)),
.movements-table :deep(td:nth-child(2)) {
  width: 12%;
}

.movements-table :deep(th:nth-child(3)),
.movements-table :deep(td:nth-child(3)) {
  width: 10%;
}

.movements-table :deep(th:nth-child(4)),
.movements-table :deep(td:nth-child(4)),
.movements-table :deep(th:nth-child(5)),
.movements-table :deep(td:nth-child(5)) {
  width: 12%;
}

.movements-table :deep(th:nth-child(6)),
.movements-table :deep(td:nth-child(6)) {
  width: 18%;
}

.movements-table :deep(th:nth-child(7)),
.movements-table :deep(td:nth-child(7)) {
  width: 19%;
}

.movement-student-cell {
  text-align: left;
}

.movement-student-cell__name {
  color: #374151;
  font-weight: 700;
  line-height: 1.25;
}

.movement-student-cell__rut {
  margin-top: 0.2rem;
  color: #64748b;
  font-size: 0.8rem;
  line-height: 1.1;
}

.movement-course-pill,
.movement-date-pill,
.movement-status-chip,
.movement-type-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  font-weight: 700;
  line-height: 1.1;
  white-space: nowrap;
}

.movement-course-pill {
  min-width: 0;
  width: 100%;
  max-width: 8.8rem;
  padding: 0.34rem 0.72rem;
  border: 1px solid #dbeafe;
  background: #eff6ff;
  color: #1e40af;
}

.movement-date-pill {
  box-sizing: border-box;
  min-width: 8.25rem;
  width: 100%;
  max-width: 8.5rem;
  padding: 0.34rem 0.8rem;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  overflow: hidden;
  text-overflow: clip;
}

.movement-date-pill--empty {
  color: #94a3b8;
  background: #f8fafc;
}

.movement-status-chip {
  min-width: 0;
  width: 100%;
  max-width: 6.7rem;
  padding: 0.35rem 0.78rem;
  border: 1px solid transparent;
  font-size: 0.78rem;
  text-transform: capitalize;
}

.movement-status-chip--regular,
.movement-status-chip--enrolled {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.movement-status-chip--retired {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.movement-status-chip--suspended {
  color: #9a3412;
  background: #fff7ed;
  border-color: #fed7aa;
}

.movement-status-chip--transferred,
.movement-status-chip--graduated {
  color: #4338ca;
  background: #eef2ff;
  border-color: #c7d2fe;
}

.movement-status-chip--neutral {
  color: #475569;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.movement-last-cell {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 0.3rem;
  width: 100%;
  min-width: 0;
}

.movement-type-chip {
  padding: 0.3rem 0.65rem;
  border: 1px solid transparent;
  font-size: 0.74rem;
}

.movement-type-chip--enrolled {
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
}

.movement-type-chip--transfer {
  color: #4338ca;
  background: #eef2ff;
  border-color: #c7d2fe;
}

.movement-type-chip--withdraw {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.movement-type-chip--reentry {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.movement-type-chip--neutral {
  color: #475569;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.movement-last-cell__route {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 600;
  line-height: 1.15;
  max-width: 100%;
  flex-wrap: wrap;
  white-space: normal;
}

.movement-last-cell__arrow {
  color: #94a3b8;
}

.movement-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  flex-wrap: wrap;
  width: 100%;
  min-width: 0;
}

.movement-actions :deep(.btn) {
  padding-right: 0.6rem;
  padding-left: 0.6rem;
  white-space: nowrap;
}

@media (max-width: 767.98px) {
  .movements-stat-card__content {
    min-height: 4.75rem;
  }

  .movement-last-cell,
  .movement-actions {
    min-width: 0;
  }

  .movement-last-cell__route {
    flex-wrap: wrap;
    white-space: normal;
  }
}
</style>
