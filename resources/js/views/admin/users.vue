<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";

const emptyForm = () => ({
  id: null,
  name: "",
  email: "",
  password: "",
  cargo_id: null,
  user_type: "",
  active: true,
  roles: [],
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      search: "",
      users: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      catalogs: { cargos: [], roles: [] },
      showModal: false,
      form: emptyForm(),
      passwordTouched: false,
      error: null,
      success: null,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    roleOptions() {
      return this.catalogs.roles.map((r) => ({ value: r.id, label: r.name }));
    },
    cargoOptions() {
      return [{ value: null, label: "Sin cargo" }].concat(
        this.catalogs.cargos.map((c) => ({ value: c.id, label: c.name }))
      );
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadUsers();
  },
  methods: {
    normalizeIdList(values) {
      return (values || [])
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0);
    },
    async loadCatalogs() {
      const response = await axios.get("/api/admin/users/catalogs");
      this.catalogs = response.data;
    },
    async loadUsers(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/admin/users", {
          params: { page, search: this.search },
        });
        this.users = response.data.data;
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
    openCreate() {
      this.form = emptyForm();
      this.passwordTouched = false;
      this.showModal = true;
    },
    openEdit(user) {
      this.form = {
        id: user.id,
        name: user.name,
        email: user.email,
        password: "",
        cargo_id: user.cargo_id ?? null,
        user_type: user.user_type ?? "",
        active: Boolean(user.active),
        roles: (user.roles || []).map((r) => r.id),
      };
      this.passwordTouched = false;
      this.showModal = true;
    },
    onPasswordInput(value) {
      this.passwordTouched = true;
      this.form.password = value;
    },
    async showSuccess(message) {
      this.success = message;
      await Swal.fire({
        icon: "success",
        title: "Listo",
        text: message,
        confirmButtonText: "Aceptar",
      });
    },
    async showError(message) {
      this.error = message;
      await Swal.fire({
        icon: "error",
        title: "No se pudo guardar",
        text: message,
        confirmButtonText: "Aceptar",
      });
    },
    async save() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/admin/users/${this.form.id}`, {
            name: this.form.name,
            email: this.form.email,
            password: this.passwordTouched && this.form.password ? this.form.password : null,
            cargo_id: this.form.cargo_id,
            user_type: this.form.user_type || null,
            active: this.form.active,
          });
          await axios.put(`/api/admin/users/${this.form.id}/roles`, {
            roles: this.normalizeIdList(this.form.roles),
          });
          await axios.put(`/api/admin/users/${this.form.id}/cargo`, {
            cargo_id: this.form.cargo_id,
          });
          await this.showSuccess("Usuario actualizado.");
        } else {
          await axios.post("/api/admin/users", {
            name: this.form.name,
            email: this.form.email,
            password: this.form.password,
            cargo_id: this.form.cargo_id,
            user_type: this.form.user_type || null,
            active: this.form.active,
            roles: this.normalizeIdList(this.form.roles),
          });
          await this.showSuccess("Usuario creado.");
        }
        this.showModal = false;
        this.loadUsers(this.pagination.current_page);
      } catch (error) {
        await this.showError(this.formatError(error));
      } finally {
        this.saving = false;
      }
    },
    async toggleActive(user) {
      try {
        await axios.put(`/api/admin/users/${user.id}/active`, {
          active: !user.active,
        });
        await this.showSuccess(`Usuario ${user.active ? "desactivado" : "activado"}.`);
        this.loadUsers(this.pagination.current_page);
      } catch (error) {
        await this.showError(this.formatError(error));
      }
    },
    async remove(user) {
      const result = await Swal.fire({
        icon: "warning",
        title: "Eliminar usuario",
        text: `Se eliminará ${user.email}.`,
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/admin/users/${user.id}`);
        await this.showSuccess("Usuario eliminado.");
        this.loadUsers(this.pagination.current_page);
      } catch (error) {
        await this.showError(this.formatError(error));
      }
    },
    formatError(error) {
      const validationErrors = error?.response?.data?.errors;
      if (validationErrors && typeof validationErrors === "object") {
        const firstKey = Object.keys(validationErrors)[0];
        if (firstKey && Array.isArray(validationErrors[firstKey]) && validationErrors[firstKey][0]) {
          return validationErrors[firstKey][0];
        }
      }

      return error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Usuarios</h4>
      <BButton variant="primary" @click="openCreate">Nuevo usuario</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <div class="row mb-3">
      <div class="col-md-4">
        <BFormInput v-model="search" placeholder="Buscar por nombre o email" @keyup.enter="loadUsers(1)" />
      </div>
      <div class="col-md-2">
        <BButton variant="secondary" @click="loadUsers(1)">Buscar</BButton>
      </div>
    </div>

    <div class="table-responsive">
      <BTable
        :items="users"
        :busy="loading"
        :fields="[
          { key: 'name', label: 'Nombre' },
          { key: 'email', label: 'Email' },
          { key: 'cargo', label: 'Cargo' },
          { key: 'roles', label: 'Roles' },
          { key: 'active', label: 'Activo' },
          { key: 'actions', label: 'Acciones' },
        ]"
        small
      >
        <template #table-busy>
          <LoadingState message="Cargando usuarios..." compact />
        </template>
        <template #cell(cargo)="{ item }">
          {{ item.cargo?.name || "-" }}
        </template>
        <template #cell(roles)="{ item }">
          <span v-if="(item.roles || []).length === 0">-</span>
          <span v-else>{{ item.roles.map((r) => r.name).join(", ") }}</span>
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
    </div>

    <div class="d-flex justify-content-end">
      <BPagination
        v-model="pagination.current_page"
        :per-page="15"
        :total-rows="pagination.total"
        @update:model-value="loadUsers"
      />
    </div>

    <BModal v-model="showModal" :title="isEditing ? 'Editar usuario' : 'Nuevo usuario'" size="lg" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Email</label>
          <BFormInput v-model="form.email" type="email" />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ isEditing ? "Password (opcional)" : "Password" }}</label>
          <BFormInput
            :model-value="form.password"
            type="password"
            autocomplete="new-password"
            name="new-password"
            @update:model-value="onPasswordInput"
          />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Cargo</label>
          <Multiselect v-model="form.cargo_id" :options="cargoOptions" :searchable="true" />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Tipo usuario</label>
          <BFormInput v-model="form.user_type" placeholder="Ej: funcionario / estudiante / apoderado" />
        </div>
        <div class="col-md-6 mb-3 d-flex align-items-end">
          <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label">Roles</label>
          <Multiselect
            v-model="form.roles"
            :options="roleOptions"
            mode="multiple"
            :close-on-select="false"
            :searchable="true"
          />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
