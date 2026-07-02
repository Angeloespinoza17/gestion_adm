<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  name: "",
  description: "",
  responsible_staff_id: null,
  active: true,
  color: "#0d6efd",
  sort_order: 0,
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      success: null,
      search: "",
      departments: [],
      catalogs: { responsible_staff: [] },
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
          label: `${staff.full_name} (${staff.rut})`,
        }))
      );
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
          params: { search: this.search || null },
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
        description: department.description || "",
        responsible_staff_id: department.responsible_staff_id ?? null,
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
      });
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
        <h4 class="mb-0">Departamentos</h4>
        <div class="text-muted">Catálogo editable de áreas institucionales.</div>
      </div>
      <BButton variant="primary" @click="openCreate">Nuevo departamento</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="search" placeholder="Nombre o descripción" @keyup.enter="load" />
        </div>
        <div class="col-md-4 d-flex align-items-end gap-2">
          <BButton variant="secondary" @click="load">Buscar</BButton>
          <router-link to="/staff" class="btn btn-outline-secondary">Volver a funcionarios</router-link>
        </div>
      </div>
    </BCard>

    <BCard>
      <BTable
        :items="departments"
        :busy="loading"
        small
        :fields="[
          { key: 'name', label: 'Departamento' },
          { key: 'responsibleStaff', label: 'Encargado' },
          { key: 'staff_count', label: 'Funcionarios' },
          { key: 'active', label: 'Activo' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando departamentos..." compact />
        </template>
        <template #cell(name)="{ item }">
          <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle border" :style="{ width: '14px', height: '14px', backgroundColor: item.color || '#adb5bd' }"></span>
            <div>
              <div class="fw-semibold">{{ item.name }}</div>
              <div class="text-muted small">{{ item.description || "-" }}</div>
            </div>
          </div>
        </template>
        <template #cell(responsibleStaff)="{ item }">
          {{ item.responsible_staff?.full_name || "-" }}
        </template>
        <template #cell(active)="{ item }">
          <BBadge :variant="item.active ? 'success' : 'secondary'">
            {{ item.active ? "Sí" : "No" }}
          </BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <BButton size="sm" variant="info" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" :variant="item.active ? 'warning' : 'success'" @click="toggleActive(item)">
              {{ item.active ? "Desactivar" : "Activar" }}
            </BButton>
            <BButton size="sm" variant="danger" @click="remove(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>
    </BCard>

    <BModal v-model="showModal" :title="isEditing ? 'Editar departamento' : 'Nuevo departamento'" size="lg" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre del departamento</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Orden</label>
          <BFormInput v-model="form.sort_order" type="number" min="0" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Encargado</label>
          <Multiselect v-model="form.responsible_staff_id" :options="responsibleOptions" :searchable="true" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Color</label>
          <input v-model="form.color" type="color" class="form-control form-control-color" />
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="3" />
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
