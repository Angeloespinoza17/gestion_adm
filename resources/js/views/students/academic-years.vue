<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  name: "",
  year: "",
  starts_at: "",
  ends_at: "",
  is_active: false,
  is_closed: false,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      academicYears: [],
      showModal: false,
      form: emptyForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    tableFields() {
      const headerClass = "text-center academic-years-table__head";
      const cellClass = "text-center align-middle academic-years-table__cell";

      return [
        { key: "name", label: "Nombre", thClass: headerClass, tdClass: cellClass },
        { key: "year", label: "Año", thClass: headerClass, tdClass: cellClass },
        { key: "range", label: "Rango", thClass: headerClass, tdClass: `${cellClass} academic-years-table__cell--range` },
        { key: "course_sections_count", label: "Cursos", thClass: headerClass, tdClass: cellClass },
        { key: "enrollments_count", label: "Matrículas", thClass: headerClass, tdClass: cellClass },
        { key: "status", label: "Estado", thClass: headerClass, tdClass: cellClass },
        { key: "actions", label: "Acciones", thClass: headerClass, tdClass: cellClass },
      ];
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/students/academic-years");
        this.academicYears = response.data.data || [];
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        name: item.name,
        year: item.year,
        starts_at: item.starts_at || "",
        ends_at: item.ends_at || "",
        is_active: Boolean(item.is_active),
        is_closed: Boolean(item.is_closed),
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/students/academic-years/${this.form.id}`, this.form);
        } else {
          await axios.post("/api/students/academic-years", this.form);
        }

        this.showModal = false;
        await this.load();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.saving = false;
      }
    },
    async activate(item) {
      const result = await Swal.fire({
        title: "Activar año académico",
        text: `Se activará ${item.name}. El año académico activo actual quedará inactivo.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, activar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#f1b44c",
      });

      if (!result.isConfirmed) {
        return;
      }

      this.error = null;

      try {
        await axios.put(`/api/students/academic-years/${item.id}/activate`);
        await this.load();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
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
        <h4 class="mb-0">Años académicos</h4>
        <div class="text-muted">Un solo año puede quedar activo a la vez.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/students" class="btn btn-outline-secondary">Volver</router-link>
        <BButton variant="primary" @click="openCreate">Nuevo año</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard>
      <BTable
        class="academic-years-table"
        :items="academicYears"
        :busy="loading"
        responsive
        :fields="tableFields"
      >
        <template #table-busy>
          <LoadingState message="Cargando años académicos..." compact />
        </template>
        <template #cell(range)="{ item }">
          <div class="academic-year-range">
            <span class="academic-year-range__date">
              <span class="academic-year-range__label">Inicio</span>
              <span class="academic-year-range__value">{{ item.starts_at || "-" }}</span>
            </span>
            <span class="academic-year-range__separator" aria-hidden="true">&rarr;</span>
            <span class="academic-year-range__date">
              <span class="academic-year-range__label">Término</span>
              <span class="academic-year-range__value">{{ item.ends_at || "-" }}</span>
            </span>
          </div>
        </template>
        <template #cell(status)="{ item }">
          <div class="academic-year-status-chips">
            <span
              class="academic-year-status-chip"
              :class="item.is_active ? 'academic-year-status-chip--active' : 'academic-year-status-chip--inactive'"
            >
              <span class="academic-year-status-chip__dot"></span>
              {{ item.is_active ? "Activo" : "Inactivo" }}
            </span>
            <span
              class="academic-year-status-chip"
              :class="item.is_closed ? 'academic-year-status-chip--closed' : 'academic-year-status-chip--open'"
            >
              <span class="academic-year-status-chip__dot"></span>
              {{ item.is_closed ? "Cerrado" : "Abierto" }}
            </span>
          </div>
        </template>
        <template #cell(actions)="{ item }">
          <div class="academic-year-actions">
            <BButton size="sm" variant="warning" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" variant="outline-primary" :disabled="item.is_active" @click="activate(item)">Activar</BButton>
          </div>
        </template>
      </BTable>
    </BCard>

    <BModal v-model="showModal" :title="isEditing ? 'Editar año académico' : 'Nuevo año académico'" hide-footer>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Año</label>
          <BFormInput v-model="form.year" type="number" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Inicio</label>
          <BFormInput v-model="form.starts_at" type="date" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Término</label>
          <BFormInput v-model="form.ends_at" type="date" />
        </div>
        <div class="col-md-6">
          <BFormCheckbox v-model="form.is_active">Dejar activo</BFormCheckbox>
        </div>
        <div class="col-md-6">
          <BFormCheckbox v-model="form.is_closed">Marcar cerrado</BFormCheckbox>
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
.academic-years-table :deep(th),
.academic-years-table :deep(td) {
  text-align: center;
  vertical-align: middle;
}

.academic-years-table :deep(.academic-years-table__head) {
  letter-spacing: 0.08em;
  white-space: nowrap;
}

.academic-years-table :deep(.academic-years-table__cell) {
  color: #4b5563;
}

.academic-years-table :deep(.academic-years-table__cell--range) {
  min-width: 280px;
}

.academic-year-range {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
  padding: 0.32rem 0.5rem;
  border: 1px solid #dbeafe;
  border-radius: 999px;
  background: rgba(239, 246, 255, 0.72);
  color: #334155;
  white-space: nowrap;
}

.academic-year-range__date {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 0.05rem;
  min-width: 6.4rem;
}

.academic-year-range__label {
  color: #64748b;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  line-height: 1;
  text-transform: uppercase;
}

.academic-year-range__value {
  color: #334155;
  font-size: 0.82rem;
  font-weight: 700;
  line-height: 1.15;
}

.academic-year-range__separator {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.55rem;
  height: 1.55rem;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  background: #ffffff;
  color: #2563eb;
  font-weight: 700;
  line-height: 1;
}

.academic-year-status-chips {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.academic-year-status-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.38rem;
  min-width: 92px;
  padding: 0.34rem 0.72rem;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1.1;
  white-space: nowrap;
}

.academic-year-status-chip__dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  background: currentColor;
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.72);
}

.academic-year-status-chip--active {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.academic-year-status-chip--inactive {
  color: #475569;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.academic-year-status-chip--open {
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
}

.academic-year-status-chip--closed {
  color: #6b21a8;
  background: #faf5ff;
  border-color: #e9d5ff;
}

.academic-year-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

@media (max-width: 767.98px) {
  .academic-year-range {
    flex-direction: column;
    align-items: stretch;
    gap: 0.35rem;
    border-radius: 14px;
  }

  .academic-year-range__separator {
    align-self: center;
    transform: rotate(90deg);
  }
}
</style>
