<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import StaffFieldLabel from "../../components/staff/field-label.vue";
import StaffModalIntro from "../../components/staff/modal-intro.vue";
import StaffPageHeader from "../../components/staff/page-header.vue";
import "../../components/staff/staff-ui.css";

const emptyForm = () => ({
  id: null,
  name: "",
  description: null,
  responsible_staff_id: null,
  staff_ids: [],
  active: true,
  color: "#0d6efd",
  sort_order: 0,
});

const colorOptions = [
  "#556ee6",
  "#34c38f",
  "#50a5f1",
  "#f1b44c",
  "#f46a6a",
  "#74788d",
  "#6f42c1",
  "#0d6efd",
];

export default {
  components: { Layout, LoadingState, Multiselect, StaffFieldLabel, StaffModalIntro, StaffPageHeader },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      success: null,
      search: "",
      activeFilter: null,
      departments: [],
      catalogs: { responsible_staff: [], staff: [] },
      showModal: false,
      form: emptyForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    responsibleOptions() {
      return [{ value: null, label: "Sin encargado" }].concat(
        (this.catalogs.responsible_staff || []).map((staff) => ({
          value: staff.id,
          label: `${staff.full_name}${staff.cargo?.name ? ` · ${staff.cargo.name}` : ""}${staff.active ? "" : " · Inactivo"}`,
        }))
      );
    },
    teamOptions() {
      return (this.catalogs.staff || []).map((staff) => ({
        value: staff.id,
        label: `${staff.full_name}${staff.cargo?.name ? ` · ${staff.cargo.name}` : ""}${staff.active ? "" : " · Inactivo"}`,
      }));
    },
    activeOptions() {
      return [
        { value: null, label: "Todos los estados" },
        { value: "1", label: "Solo activos" },
        { value: "0", label: "Solo desactivados" },
      ];
    },
    colorOptions() {
      return colorOptions;
    },
    summaryCards() {
      return [
        {
          label: "Departamentos",
          value: this.departments.length,
          detail: "áreas registradas",
          icon: "bx-buildings",
          tone: "primary",
        },
        {
          label: "Activos",
          value: this.departments.filter((item) => item.active).length,
          detail: "disponibles para asignación",
          icon: "bx-check-circle",
          tone: "success",
        },
        {
          label: "Asignaciones",
          value: this.departments.reduce((total, item) => total + Number(item.staff_count || 0), 0),
          detail: "vínculos con funcionarios",
          icon: "bx-group",
          tone: "info",
        },
        {
          label: "Sin encargado",
          value: this.departments.filter((item) => !item.responsible_staff_id).length,
          detail: "áreas por completar",
          icon: "bx-user-x",
          tone: "warning",
        },
      ];
    },
    previewName() {
      return this.form.name?.trim() || "Nombre del departamento";
    },
  },
  mounted() {
    this.loadCatalogs();
    this.load();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/staff/departments/catalogs");
      this.catalogs = response.data;
    },
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/departments", {
          params: {
            search: this.search || null,
            active: this.activeFilter === null ? null : this.activeFilter,
          },
        });
        this.departments = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    openEdit(department) {
      this.form = {
        id: department.id,
        name: department.name,
        description: department.description ?? null,
        responsible_staff_id: department.responsible_staff_id ?? null,
        staff_ids: (department.staff || []).map((staff) => staff.id),
        active: Boolean(department.active),
        color: department.color || "#0d6efd",
        sort_order: department.sort_order || 0,
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        const payload = {
          name: this.form.name,
          description: this.form.description || null,
          responsible_staff_id: this.form.responsible_staff_id,
          staff_ids: (this.form.staff_ids || []).map(Number),
          active: this.form.active,
          color: this.form.color || null,
          sort_order: this.form.sort_order || 0,
        };

        if (this.isEditing) {
          await axios.put(`/api/staff/departments/${this.form.id}`, payload);
          this.success = "Departamento actualizado correctamente.";
          this.showSuccessAlert("Departamento actualizado", this.success);
        } else {
          await axios.post("/api/staff/departments", payload);
          this.success = "Departamento creado correctamente.";
          this.showSuccessAlert("Departamento creado", this.success);
        }

        this.showModal = false;
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.saving = false;
      }
    },
    async toggleActive(department) {
      const result = await this.confirmAction({
        title: department.active ? "Desactivar departamento" : "Activar departamento",
        text: `${department.name} cambiará su estado.`,
        confirmButtonText: department.active ? "Sí, desactivar" : "Sí, activar",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.put(`/api/staff/departments/${department.id}/active`, {
          active: !department.active,
        });
        this.showSuccessAlert(
          department.active ? "Departamento desactivado" : "Departamento activado",
          "El estado fue actualizado correctamente."
        );
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      }
    },
    async remove(department) {
      const result = await this.confirmAction({
        title: "Eliminar departamento",
        text: `Se eliminará ${department.name}.`,
        confirmButtonText: "Sí, eliminar",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.delete(`/api/staff/departments/${department.id}`);
        this.showSuccessAlert("Departamento eliminado", "El departamento fue eliminado correctamente.");
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      }
    },
    confirmAction({ title, text, confirmButtonText }) {
      return Swal.fire({
        title,
        text,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        customClass: { popup: "staff-alert" },
      });
    },
    showSuccessAlert(title, text) {
      return Swal.fire({
        title,
        text,
        icon: "success",
        timer: 1800,
        showConfirmButton: false,
        customClass: { popup: "staff-alert" },
      });
    },
    showErrorAlert(text) {
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
        customClass: { popup: "staff-alert" },
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
    initials(name) {
      return String(name || "?")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
    },
    visibleTeam(department) {
      return (department?.staff || []).slice(0, 3);
    },
    remainingTeamCount(department) {
      return Math.max((department?.staff || []).length - 3, 0);
    },
  },
};
</script>

<template>
  <Layout>
    <StaffPageHeader
      eyebrow="Funcionarios · Organización"
      title="Departamentos"
      subtitle="Organiza áreas institucionales, responsables y equipos asociados."
      icon="bx-buildings"
    >
      <template #actions>
        <router-link to="/staff" class="btn btn-outline-secondary">
          <i class="bx bx-arrow-back me-1"></i>Funcionarios
        </router-link>
        <BButton variant="primary" @click="openCreate">
          <i class="bx bx-plus me-1"></i>Nuevo departamento
        </BButton>
      </template>
    </StaffPageHeader>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <div class="department-context-note mb-4">
      <i class="bx bx-sitemap"></i>
      <div>
        <strong>Los departamentos organizan personas; no entregan permisos.</strong>
        <p class="mb-0">
          Ser encargado y pertenecer al equipo son relaciones independientes. Una persona puede liderar varias áreas
          y pertenecer a un equipo diferente.
        </p>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div v-for="card in summaryCards" :key="card.label" class="col-xl-3 col-md-6">
        <BCard no-body class="department-summary-card h-100">
          <BCardBody class="d-flex align-items-start justify-content-between gap-3">
            <div>
              <span class="department-summary-label">{{ card.label }}</span>
              <h3>{{ card.value }}</h3>
              <p class="text-muted mb-0">{{ card.detail }}</p>
            </div>
            <span :class="`department-summary-icon is-${card.tone}`">
              <i :class="`bx ${card.icon}`"></i>
            </span>
          </BCardBody>
        </BCard>
      </div>
    </div>

    <BCard class="mb-4 staff-department-card department-filter-card">
      <div class="row g-3 align-items-end">
        <div class="col-lg-6">
          <label class="form-label">Buscar departamento</label>
          <div class="department-search">
            <i class="bx bx-search"></i>
            <BFormInput
              v-model="search"
              placeholder="Nombre, descripción o propósito"
              @keyup.enter="load"
            />
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <label class="form-label">Estado</label>
          <Multiselect v-model="activeFilter" :options="activeOptions" :searchable="false" />
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="d-flex gap-2">
            <BButton variant="primary" @click="load"><i class="bx bx-search me-1"></i>Buscar</BButton>
            <BButton
              variant="light"
              @click="search = ''; activeFilter = null; load()"
            >
              Limpiar
            </BButton>
          </div>
        </div>
      </div>
    </BCard>

    <BCard no-body class="staff-department-card">
      <div class="card-header border-bottom department-list-header">
        <div>
          <h5 class="mb-1">Estructura organizacional</h5>
          <p class="text-muted mb-0">{{ departments.length }} departamentos en esta vista</p>
        </div>
        <BButton variant="primary" @click="openCreate">
          <i class="bx bx-plus me-1"></i>Crear departamento
        </BButton>
      </div>
      <BTable
        :items="departments"
        :busy="loading"
        responsive
        small
        :fields="[
          { key: 'name', label: 'Departamento' },
          { key: 'responsibleStaff', label: 'Encargado/a' },
          { key: 'team', label: 'Equipo' },
          { key: 'active', label: 'Estado' },
          { key: 'actions', label: '', class: 'text-end' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando departamentos..." compact />
        </template>
        <template #cell(name)="{ item }">
          <div class="department-name-cell">
            <span class="department-color" :style="{ backgroundColor: item.color || '#adb5bd' }"></span>
            <div class="min-w-0">
              <div class="fw-semibold">{{ item.name }}</div>
              <div class="text-muted small department-description">
                {{ item.description || "Sin descripción" }}
              </div>
            </div>
          </div>
        </template>
        <template #cell(responsibleStaff)="{ item }">
          <div v-if="item.responsible_staff" class="department-responsible">
            <span>{{ initials(item.responsible_staff.full_name) }}</span>
            <div>
              <div class="fw-medium">{{ item.responsible_staff.full_name }}</div>
              <small class="text-muted">{{ item.responsible_staff.cargo?.name || "Funcionario" }}</small>
            </div>
          </div>
          <span v-else class="department-empty-value"><i class="bx bx-user-x"></i>Sin encargado</span>
        </template>
        <template #cell(team)="{ item }">
          <div v-if="item.staff_count" class="department-team-cell">
            <div class="department-avatar-stack">
              <span
                v-for="member in visibleTeam(item)"
                :key="member.id"
                :title="member.full_name"
              >
                {{ initials(member.full_name) }}
              </span>
            </div>
            <small>{{ item.staff_count }} {{ item.staff_count === 1 ? "integrante" : "integrantes" }}</small>
            <span v-if="remainingTeamCount(item)" class="department-team-more">
              +{{ remainingTeamCount(item) }}
            </span>
          </div>
          <span v-else class="department-empty-value"><i class="bx bx-group"></i>Sin integrantes</span>
        </template>
        <template #cell(active)="{ item }">
          <span :class="['department-status', item.active ? 'is-active' : 'is-inactive']">
            <i class="bx bxs-circle"></i>{{ item.active ? "Activo" : "Desactivado" }}
          </span>
        </template>
        <template #cell(actions)="{ item }">
          <div class="department-actions">
            <BButton size="sm" variant="outline-primary" title="Editar departamento" @click="openEdit(item)">
              <i class="bx bx-edit"></i>
            </BButton>
            <BButton
              size="sm"
              :variant="item.active ? 'outline-warning' : 'outline-success'"
              :title="item.active ? 'Desactivar departamento' : 'Activar departamento'"
              @click="toggleActive(item)"
            >
              <i :class="item.active ? 'bx bx-pause' : 'bx bx-play'"></i>
            </BButton>
            <BButton size="sm" variant="outline-danger" title="Eliminar departamento" @click="remove(item)">
              <i class="bx bx-trash"></i>
            </BButton>
          </div>
        </template>
      </BTable>
    </BCard>

    <BModal
      v-model="showModal"
      :title="isEditing ? 'Editar departamento' : 'Nuevo departamento'"
      size="lg"
      centered
      scrollable
      hide-footer
      modal-class="staff-modal"
    >
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <StaffModalIntro
        title="Construye un área de trabajo"
        text="Define su identidad, encargado y equipo. Esto organiza a los funcionarios, pero no modifica sus roles ni permisos."
        icon="bx-buildings"
      />

      <div class="department-preview" :style="{ '--department-color': form.color || '#556ee6' }">
        <span class="department-preview__icon"><i class="bx bx-buildings"></i></span>
        <div>
          <strong>{{ previewName }}</strong>
          <small>
            {{ form.staff_ids.length }} {{ form.staff_ids.length === 1 ? "integrante" : "integrantes" }}
            · {{ form.active ? "Activo" : "Desactivado" }}
          </small>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-md-9">
          <StaffFieldLabel label="Nombre del departamento" required />
          <BFormInput v-model="form.name" placeholder="Ej: Convivencia Escolar" />
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <BFormCheckbox v-model="form.active" switch>Departamento activo</BFormCheckbox>
        </div>
        <div class="col-12">
          <StaffFieldLabel label="Descripción o propósito" />
          <BFormTextarea
            v-model="form.description"
            rows="2"
            placeholder="Explica brevemente qué función cumple esta área."
          />
        </div>
        <div class="col-md-6">
          <StaffFieldLabel label="Encargado/a del departamento" />
          <Multiselect
            v-model="form.responsible_staff_id"
            :options="responsibleOptions"
            :searchable="true"
            placeholder="Seleccionar encargado"
          />
          <small class="text-muted d-block mt-2">
            Puede liderar este departamento sin pertenecer a su equipo.
          </small>
        </div>
        <div class="col-md-6">
          <StaffFieldLabel label="Color identificador" />
          <div class="department-color-picker">
            <button
              v-for="color in colorOptions"
              :key="color"
              type="button"
              :class="{ 'is-selected': form.color === color }"
              :style="{ backgroundColor: color }"
              :aria-label="`Usar color ${color}`"
              @click="form.color = color"
            ></button>
            <input v-model="form.color" type="color" class="form-control form-control-color" title="Color personalizado" />
          </div>
        </div>
        <div class="col-12">
          <StaffFieldLabel label="Integrantes del equipo" />
          <Multiselect
            v-model="form.staff_ids"
            :options="teamOptions"
            mode="multiple"
            :close-on-select="false"
            :searchable="true"
            placeholder="Busca y selecciona funcionarios"
          />
          <small class="text-muted d-block mt-2">
            Aquí defines pertenencia. Una persona puede integrar varios equipos y esto no cambia sus responsabilidades,
            roles ni permisos.
          </small>
        </div>
        <div class="col-12">
          <details class="department-advanced">
            <summary>Opciones avanzadas</summary>
            <div class="mt-3">
              <StaffFieldLabel label="Orden de aparición" :optional="false" />
              <BFormInput v-model="form.sort_order" type="number" min="0" />
              <small class="text-muted d-block mt-1">Los valores menores aparecen primero.</small>
            </div>
          </details>
        </div>
      </div>

      <div class="staff-modal-actions">
        <BButton variant="light" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          <i :class="saving ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-save me-1'"></i>
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.department-context-note {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 1rem 1.1rem;
  border: 1px solid #dce5ff;
  border-radius: 12px;
  background: linear-gradient(135deg, #f7f9ff, #f6fbff);
}

.department-context-note > i {
  display: inline-flex;
  width: 2.6rem;
  height: 2.6rem;
  flex: 0 0 2.6rem;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: #e8edff;
  color: #556ee6;
  font-size: 1.3rem;
}

.department-context-note strong {
  display: block;
  margin-bottom: 0.2rem;
  color: #2a3042;
}

.department-context-note p {
  color: #667085;
  font-size: 0.86rem;
}

.department-summary-card,
.staff-department-card {
  border: 1px solid #e7ebf3;
  border-radius: 14px;
  box-shadow: 0 10px 28px rgba(42, 48, 66, 0.05);
}

.department-summary-label {
  display: block;
  margin-bottom: 0.3rem;
  color: #74788d;
  font-size: 0.72rem;
  font-weight: 750;
  text-transform: uppercase;
}

.department-summary-card h3 {
  margin: 0 0 0.15rem;
  color: #2a3042;
}

.department-summary-card p {
  font-size: 0.8rem;
}

.department-summary-icon {
  display: inline-flex;
  width: 2.65rem;
  height: 2.65rem;
  flex: 0 0 2.65rem;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  font-size: 1.35rem;
}

.department-summary-icon.is-primary {
  background: #eef1ff;
  color: #556ee6;
}

.department-summary-icon.is-success {
  background: #e9f8f2;
  color: #2ca67a;
}

.department-summary-icon.is-info {
  background: #eaf5ff;
  color: #3577b8;
}

.department-summary-icon.is-warning {
  background: #fff6e5;
  color: #b7791f;
}

.department-search {
  position: relative;
}

.department-search > i {
  position: absolute;
  top: 50%;
  left: 0.9rem;
  z-index: 2;
  color: #8b93a4;
  font-size: 1.15rem;
  transform: translateY(-50%);
}

.department-search :deep(input) {
  padding-left: 2.7rem;
}

.department-list-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.15rem;
  background: #fff;
}

.department-name-cell,
.department-responsible,
.department-team-cell,
.department-actions {
  display: flex;
  align-items: center;
}

.department-name-cell {
  min-width: 230px;
  gap: 0.75rem;
}

.department-color {
  width: 0.6rem;
  height: 2.6rem;
  flex: 0 0 0.6rem;
  border-radius: 999px;
}

.department-description {
  max-width: 310px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.department-responsible {
  min-width: 190px;
  gap: 0.55rem;
}

.department-responsible > span {
  display: inline-flex;
  width: 2rem;
  height: 2rem;
  flex: 0 0 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: #eef1ff;
  color: #556ee6;
  font-size: 0.68rem;
  font-weight: 800;
}

.department-team-cell {
  min-width: 190px;
  gap: 0.55rem;
}

.department-avatar-stack {
  display: flex;
  padding-left: 0.35rem;
}

.department-avatar-stack span {
  display: inline-flex;
  width: 1.85rem;
  height: 1.85rem;
  align-items: center;
  justify-content: center;
  margin-left: -0.35rem;
  border: 2px solid #fff;
  border-radius: 50%;
  background: #edf2f7;
  color: #556070;
  font-size: 0.58rem;
  font-weight: 800;
}

.department-team-more {
  color: #556ee6;
  font-size: 0.7rem;
  font-weight: 750;
}

.department-empty-value {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: #98a2b3;
  font-size: 0.8rem;
}

.department-status {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.76rem;
  font-weight: 700;
}

.department-status i {
  font-size: 0.52rem;
}

.department-status.is-active {
  color: #2ca67a;
}

.department-status.is-inactive {
  color: #8b93a4;
}

.department-actions {
  justify-content: flex-end;
  gap: 0.35rem;
}

.department-preview {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
  padding: 0.85rem;
  border: 1px solid color-mix(in srgb, var(--department-color) 25%, #e7ebf3);
  border-radius: 12px;
  background: color-mix(in srgb, var(--department-color) 6%, #fff);
}

.department-preview__icon {
  display: inline-flex;
  width: 2.65rem;
  height: 2.65rem;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: var(--department-color);
  color: #fff;
  font-size: 1.25rem;
}

.department-preview strong,
.department-preview small {
  display: block;
}

.department-preview small {
  margin-top: 0.15rem;
  color: #74788d;
}

.department-color-picker {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.45rem;
  min-height: 2.7rem;
}

.department-color-picker button {
  width: 1.8rem;
  height: 1.8rem;
  padding: 0;
  border: 3px solid #fff;
  border-radius: 50%;
  box-shadow: 0 0 0 1px #d8deea;
}

.department-color-picker button.is-selected {
  box-shadow: 0 0 0 2px #2a3042;
}

.department-color-picker .form-control-color {
  width: 2.25rem;
  min-height: 2.25rem;
  padding: 0.2rem;
}

.department-advanced {
  padding: 0.8rem 0.9rem;
  border: 1px solid #e7ebf3;
  border-radius: 10px;
  background: #fafbfe;
}

.department-advanced summary {
  color: #556ee6;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}

:deep(.staff-department-card .table) {
  margin-bottom: 0;
}

:deep(.staff-department-card .table > :not(caption) > * > *) {
  padding: 0.9rem 0.75rem;
}

@media (max-width: 767.98px) {
  .department-list-header {
    align-items: stretch;
    flex-direction: column;
  }

  .department-list-header .btn {
    width: 100%;
  }
}
</style>
