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
      bulkDeleting: false,
      search: "",
      users: [],
      selectedUserIds: [],
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
    selectedUserSet() {
      return new Set(this.normalizeIdList(this.selectedUserIds));
    },
    selectableUsers() {
      return this.users.filter((user) => !this.isProtectedUser(user));
    },
    pageSelectableIds() {
      return this.selectableUsers.map((user) => Number(user.id));
    },
    selectedUsers() {
      return this.users.filter((user) => this.selectedUserSet.has(Number(user.id)));
    },
    selectedCount() {
      return this.selectedUserSet.size;
    },
    allPageSelected() {
      return this.pageSelectableIds.length > 0
        && this.pageSelectableIds.every((id) => this.selectedUserSet.has(id));
    },
    somePageSelected() {
      return !this.allPageSelected
        && this.pageSelectableIds.some((id) => this.selectedUserSet.has(id));
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
        this.selectedUserIds = [];
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
    async showError(message, title = "No se pudo guardar") {
      this.error = message;
      await Swal.fire({
        icon: "error",
        title,
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
    isProtectedUser(user) {
      if (user.can_delete === false) return true;

      return (user.roles || []).some((role) => role.slug === "super_admin");
    },
    isUserSelected(userId) {
      return this.selectedUserSet.has(Number(userId));
    },
    toggleUserSelection(user) {
      if (this.isProtectedUser(user)) return;

      const selected = new Set(this.selectedUserSet);
      const userId = Number(user.id);

      if (selected.has(userId)) {
        selected.delete(userId);
      } else {
        selected.add(userId);
      }

      this.selectedUserIds = [...selected];
    },
    togglePageSelection() {
      this.selectedUserIds = this.allPageSelected ? [] : [...this.pageSelectableIds];
    },
    clearSelection() {
      this.selectedUserIds = [];
    },
    async bulkRemove() {
      if (!this.selectedCount || this.bulkDeleting) return;

      const count = this.selectedCount;
      const previewLimit = 4;
      const preview = this.selectedUsers
        .slice(0, previewLimit)
        .map((user) => user.email)
        .join(", ");
      const remaining = count - previewLimit;
      const result = await Swal.fire({
        icon: "warning",
        title: `Eliminar ${count} ${count === 1 ? "usuario" : "usuarios"}`,
        text: `Se eliminarán ${preview}${remaining > 0 ? ` y ${remaining} más` : ""}. Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: `Sí, eliminar ${count}`,
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#f46a6a",
        focusCancel: true,
        reverseButtons: true,
      });

      if (!result.isConfirmed) return;

      this.bulkDeleting = true;
      this.error = null;
      this.success = null;

      try {
        const response = await axios.delete("/api/admin/users/bulk", {
          data: { users: this.normalizeIdList(this.selectedUserIds) },
        });
        this.clearSelection();
        await this.showSuccess(response.data.message || "Usuarios eliminados correctamente.");
        await this.loadUsers(this.pagination.current_page);
        if (this.users.length === 0 && this.pagination.current_page > 1) {
          await this.loadUsers(this.pagination.current_page - 1);
        }
      } catch (error) {
        await this.showError(this.formatError(error), "No se pudieron eliminar");
      } finally {
        this.bulkDeleting = false;
      }
    },
    userRowClass(item) {
      return item && this.isUserSelected(item.id) ? "users-table-row--selected" : "";
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
    <div class="users-page-header">
      <div>
        <div class="text-uppercase text-muted small fw-semibold mb-1">Administración</div>
        <h4 class="mb-1">Usuarios</h4>
        <p class="text-muted mb-0">Administra cuentas, roles y accesos del sistema.</p>
      </div>
      <BButton variant="primary" @click="openCreate">
        <i class="bx bx-user-plus me-1"></i>
        Nuevo usuario
      </BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <section class="users-panel">
      <div class="users-search-row">
        <div class="users-search">
          <i class="bx bx-search users-search__icon"></i>
          <BFormInput
            v-model="search"
            class="users-search__input"
            placeholder="Buscar por nombre o email"
            @keyup.enter="loadUsers(1)"
          />
        </div>
        <BButton variant="outline-primary" @click="loadUsers(1)">
          Buscar
        </BButton>
      </div>

      <div class="bulk-actions-bar" :class="{ 'bulk-actions-bar--active': selectedCount > 0 }">
        <div class="bulk-actions-summary">
          <span class="bulk-actions-summary__icon">
            <i class="bx bx-check-square"></i>
          </span>
          <div>
            <strong>{{ selectedCount }} {{ selectedCount === 1 ? "usuario seleccionado" : "usuarios seleccionados" }}</strong>
            <small>
              {{ users.length }} en esta página · {{ pagination.total }} en total
            </small>
          </div>
        </div>

        <div class="bulk-actions-buttons">
          <BButton
            variant="outline-primary"
            :disabled="pageSelectableIds.length === 0 || loading || bulkDeleting"
            @click="togglePageSelection"
          >
            <i :class="allPageSelected ? 'bx bx-checkbox-minus' : 'bx bx-select-multiple'" class="me-1"></i>
            {{ allPageSelected ? "Deseleccionar página" : "Seleccionar toda la página" }}
          </BButton>
          <BButton
            v-if="selectedCount > 0"
            variant="link"
            class="text-muted text-decoration-none"
            :disabled="bulkDeleting"
            @click="clearSelection"
          >
            Limpiar selección
          </BButton>
          <BButton
            variant="danger"
            :disabled="selectedCount === 0 || bulkDeleting"
            @click="bulkRemove"
          >
            <span v-if="bulkDeleting" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="bx bx-trash me-1"></i>
            Eliminar seleccionados{{ selectedCount ? ` (${selectedCount})` : "" }}
          </BButton>
        </div>
      </div>

      <div class="table-responsive users-table-wrap">
        <BTable
          :items="users"
          :busy="loading"
          :tbody-tr-class="userRowClass"
          :fields="[
            { key: 'select', label: '', class: 'users-select-column' },
            { key: 'name', label: 'Nombre' },
            { key: 'email', label: 'Email' },
            { key: 'cargo', label: 'Cargo' },
            { key: 'roles', label: 'Roles' },
            { key: 'active', label: 'Activo' },
            { key: 'actions', label: 'Acciones' },
          ]"
          small
          hover
        >
          <template #table-busy>
            <LoadingState message="Cargando usuarios..." compact />
          </template>
          <template #head(select)>
            <input
              class="form-check-input users-selection-checkbox"
              type="checkbox"
              aria-label="Seleccionar todos los usuarios de esta página"
              :checked="allPageSelected"
              :indeterminate="somePageSelected"
              :disabled="pageSelectableIds.length === 0 || loading || bulkDeleting"
              @change="togglePageSelection"
            />
          </template>
          <template #cell(select)="{ item }">
            <input
              class="form-check-input users-selection-checkbox"
              type="checkbox"
              :checked="isUserSelected(item.id)"
              :disabled="isProtectedUser(item) || bulkDeleting"
              :aria-label="`Seleccionar a ${item.name}`"
              :title="isProtectedUser(item) ? 'Esta cuenta está protegida y no puede eliminarse' : `Seleccionar a ${item.name}`"
              @change="toggleUserSelection(item)"
            />
          </template>
          <template #cell(cargo)="{ item }">
            {{ item.cargo?.name || "Sin cargo" }}
          </template>
          <template #cell(roles)="{ item }">
            <span v-if="(item.roles || []).length === 0" class="text-muted">Sin rol</span>
            <div v-else class="users-role-list">
              <BBadge v-for="role in item.roles" :key="role.id" variant="light" class="text-primary">
                {{ role.name }}
              </BBadge>
            </div>
          </template>
          <template #cell(active)="{ item }">
            <BBadge :variant="item.active ? 'success' : 'secondary'">
              {{ item.active ? "Activo" : "Inactivo" }}
            </BBadge>
          </template>
          <template #cell(actions)="{ item }">
            <div class="d-flex flex-wrap gap-2">
              <BButton size="sm" variant="info" @click="openEdit(item)">Editar</BButton>
              <BButton size="sm" :variant="item.active ? 'warning' : 'success'" @click="toggleActive(item)">
                {{ item.active ? "Desactivar" : "Activar" }}
              </BButton>
              <BButton
                size="sm"
                variant="danger"
                :disabled="isProtectedUser(item)"
                :title="isProtectedUser(item) ? 'Esta cuenta está protegida y no puede eliminarse' : 'Eliminar usuario'"
                @click="remove(item)"
              >
                Eliminar
              </BButton>
            </div>
          </template>
        </BTable>
      </div>

      <div class="users-pagination">
        <span class="text-muted small">{{ pagination.total }} usuarios registrados</span>
        <BPagination
          v-model="pagination.current_page"
          :per-page="15"
          :total-rows="pagination.total"
          class="mb-0"
          @update:model-value="loadUsers"
        />
      </div>
    </section>

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

<style scoped>
.users-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.users-panel {
  overflow: hidden;
  border: 1px solid #e9edf5;
  border-radius: 1rem;
  background: #fff;
  box-shadow: 0 0.25rem 1rem rgba(30, 49, 100, 0.05);
}

.users-search-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.25rem;
}

.users-search {
  position: relative;
  width: min(32rem, 100%);
}

.users-search__icon {
  position: absolute;
  top: 50%;
  left: 1rem;
  z-index: 2;
  color: #74788d;
  font-size: 1.2rem;
  transform: translateY(-50%);
}

.users-search__input {
  min-height: 2.75rem;
  padding-left: 2.75rem;
  border-radius: 0.75rem;
}

.bulk-actions-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin: 0 1.25rem 1rem;
  padding: 0.9rem 1rem;
  border: 1px solid #e7eaf3;
  border-radius: 0.85rem;
  background: #f8f9fc;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.bulk-actions-bar--active {
  border-color: rgba(85, 110, 230, 0.35);
  background: rgba(85, 110, 230, 0.06);
}

.bulk-actions-summary,
.bulk-actions-buttons {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.bulk-actions-summary__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.7rem;
  background: rgba(85, 110, 230, 0.12);
  color: #556ee6;
  font-size: 1.35rem;
}

.bulk-actions-summary strong,
.bulk-actions-summary small {
  display: block;
}

.bulk-actions-summary small {
  margin-top: 0.15rem;
  color: #74788d;
}

.users-table-wrap {
  border-top: 1px solid #eef0f5;
}

.users-table-wrap :deep(table) {
  margin-bottom: 0;
}

.users-table-wrap :deep(th) {
  padding: 0.95rem 0.75rem;
  border-bottom-width: 1px;
  background: #fafbfe;
  color: #74788d;
  font-size: 0.76rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  white-space: nowrap;
}

.users-table-wrap :deep(td) {
  padding: 0.95rem 0.75rem;
  vertical-align: middle;
}

.users-table-wrap :deep(.users-table-row--selected > td) {
  background: rgba(85, 110, 230, 0.07);
}

.users-table-wrap :deep(.users-select-column) {
  width: 3.25rem;
  text-align: center;
}

.users-selection-checkbox {
  width: 1.1rem;
  height: 1.1rem;
  cursor: pointer;
}

.users-selection-checkbox:disabled {
  cursor: not-allowed;
}

.users-role-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.users-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-top: 1px solid #eef0f5;
}

@media (max-width: 991.98px) {
  .bulk-actions-bar,
  .bulk-actions-buttons {
    align-items: stretch;
    flex-direction: column;
  }

  .bulk-actions-bar {
    align-items: stretch;
  }
}

@media (max-width: 575.98px) {
  .users-page-header,
  .users-search-row,
  .users-pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .users-page-header > .btn,
  .users-search-row > .btn,
  .bulk-actions-buttons > .btn {
    width: 100%;
  }
}
</style>
