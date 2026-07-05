<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  name: "",
  order: "",
  type: "parvularia",
});

const TYPE_CHIP_CLASS_BY_TYPE = {
  parvularia: "levels-type-chip--parvularia",
  basica: "levels-type-chip--basica",
  media: "levels-type-chip--media",
  tecnico: "levels-type-chip--tecnico",
  diferencial: "levels-type-chip--diferencial",
};

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      refreshingTable: false,
      hasLoadedOnce: false,
      saving: false,
      error: null,
      success: null,
      levels: [],
      academicYears: [],
      selectedAcademicYearId: null,
      typeOptions: [],
      showModal: false,
      form: emptyForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    normalizedTypeOptions() {
      return (this.typeOptions || []).map((option) => ({
        value: option.value,
        text: option.label,
      }));
    },
    academicYearOptions() {
      return [{ value: null, text: "Todos los años" }].concat(
        (this.academicYears || []).map((year) => ({
          value: year.id,
          text: `${year.name}${year.is_active ? " · activo" : ""}`,
        }))
      );
    },
    showInitialTableLoader() {
      return this.loading && !this.hasLoadedOnce;
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load({ preserveRows = false } = {}) {
      if (preserveRows && this.hasLoadedOnce) {
        this.refreshingTable = true;
      } else {
        this.loading = true;
      }

      this.error = null;
      try {
        const response = await axios.get("/api/students/levels", {
          params: {
            academic_year_id: this.selectedAcademicYearId,
          },
        });
        this.levels = response.data.data || [];
        this.academicYears = response.data.academic_years || [];
        this.selectedAcademicYearId = response.data.selected_academic_year_id ?? response.data.active_academic_year_id ?? null;
        this.typeOptions = response.data.type_options || [];
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
        this.refreshingTable = false;
        this.hasLoadedOnce = true;
      }
    },
    async onAcademicYearChange() {
      await this.load({ preserveRows: true });
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    openEdit(level) {
      this.form = {
        id: level.id,
        name: level.name,
        order: level.order,
        type: level.type,
      };
      this.showModal = true;
    },
    async remove(level) {
      const result = await Swal.fire({
        title: "Eliminar nivel",
        text: `Se intentará eliminar el nivel ${level.name}. Si tiene cursos asociados, el sistema lo bloqueará para no perder trazabilidad.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#f1b44c",
      });

      if (!result.isConfirmed) {
        return;
      }

      this.error = null;
      this.success = null;

      try {
        await axios.delete(`/api/students/levels/${level.id}`);
        this.success = "Nivel eliminado.";
        await this.load({ preserveRows: true });
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      }
    },
    async save() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/students/levels/${this.form.id}`, this.form);
          this.success = "Nivel actualizado.";
        } else {
          await axios.post("/api/students/levels", this.form);
          this.success = "Nivel creado.";
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
    typeLabel(level) {
      return this.typeOptions.find((option) => option.value === level.type)?.label || level.type || "-";
    },
    typeChipClass(level) {
      return TYPE_CHIP_CLASS_BY_TYPE[this.normalizeType(level.type)] || "levels-type-chip--neutral";
    },
    normalizeType(value) {
      return String(value || "")
        .trim()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase();
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
        <h4 class="mb-0">Niveles</h4>
        <div class="text-muted">Catálogo base para cursos, matrículas y promoción anual.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/students" class="btn btn-outline-secondary">Volver</router-link>
        <BButton variant="primary" @click="openCreate">Nuevo nivel</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <BCard>
      <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-5">
          <label class="form-label">Año académico</label>
          <BFormSelect class="levels-year-select" v-model="selectedAcademicYearId" :options="academicYearOptions" @change="onAcademicYearChange" />
        </div>
      </div>
      <div class="levels-table-shell" :class="{ 'levels-table-shell--refreshing': refreshingTable }">
        <BTable
          :items="levels"
          :busy="showInitialTableLoader"
          responsive
          :fields="[
            { key: 'order', label: 'Orden' },
            { key: 'name', label: 'Nivel' },
            { key: 'type', label: 'Tipo' },
            { key: 'course_sections_count', label: 'Cursos asociados en el año' },
            { key: 'active_students_count', label: 'Estudiantes del nivel' },
            { key: 'actions', label: 'Acciones' },
          ]"
        >
          <template #table-busy>
            <LoadingState message="Cargando niveles..." compact />
          </template>
          <template #cell(type)="{ item }">
            <span class="levels-type-chip" :class="typeChipClass(item)">{{ typeLabel(item) }}</span>
          </template>
          <template #cell(active_students_count)="{ item }">
            <span class="levels-counter">{{ item.active_students_count || 0 }}</span>
          </template>
          <template #cell(actions)="{ item }">
            <div class="d-flex gap-2 flex-wrap">
              <BButton size="sm" variant="warning" class="levels-action-btn" @click="openEdit(item)">Editar</BButton>
              <BButton size="sm" variant="outline-danger" class="levels-action-btn" @click="remove(item)">Eliminar</BButton>
            </div>
          </template>
        </BTable>

        <transition name="levels-fade">
          <div v-if="refreshingTable" class="levels-table-shell__overlay">
            <LoadingState message="Actualizando cursos del año..." compact />
          </div>
        </transition>
      </div>
    </BCard>

    <BModal v-model="showModal" :title="isEditing ? 'Editar nivel' : 'Nuevo nivel'" hide-footer>
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" placeholder="Ej: 7° básico" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Orden</label>
          <BFormInput v-model="form.order" type="number" min="1" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="form.type" :options="normalizedTypeOptions" class="levels-type-select" />
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
.levels-table-shell {
  position: relative;
  transition: opacity 0.18s ease;
}

.levels-table-shell--refreshing {
  opacity: 0.62;
}

.levels-table-shell__overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(255, 255, 255, 0.28);
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
  pointer-events: none;
}

.levels-fade-enter-active,
.levels-fade-leave-active {
  transition: opacity 0.18s ease;
}

.levels-fade-enter-from,
.levels-fade-leave-to {
  opacity: 0;
}

.levels-year-select {
  width: 100%;
}

.levels-year-select :deep(.form-select) {
  min-height: 44px;
  width: 100%;
  padding-right: 2.75rem;
}

.levels-counter {
  display: inline-flex;
  min-width: 2.25rem;
  justify-content: center;
  font-weight: 600;
  color: #495057;
}

.levels-type-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 76px;
  padding: 0.28rem 0.75rem;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1.15;
  white-space: nowrap;
}

.levels-type-chip--parvularia {
  color: #9a3412;
  background: #fff7ed;
  border-color: #fed7aa;
}

.levels-type-chip--basica {
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
}

.levels-type-chip--media {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.levels-type-chip--tecnico {
  color: #6d28d9;
  background: #f5f3ff;
  border-color: #ddd6fe;
}

.levels-type-chip--diferencial {
  color: #be185d;
  background: #fdf2f8;
  border-color: #fbcfe8;
}

.levels-type-chip--neutral {
  color: #475569;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.levels-action-btn {
  min-width: 92px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.levels-type-select :deep(.form-select) {
  min-height: 44px;
  width: 100%;
  padding-right: 2.75rem;
}
</style>
