<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../../layouts/main.vue";

const emptyWatcher = () => ({
  target_type: "manager",
  role_id: null,
  user_id: null,
  notify: true,
  can_view: true,
  active: true,
});

export default {
  components: { Layout },
  data() {
    return {
      loadingCatalogs: false,
      loadingItems: false,
      saving: false,
      error: null,
      success: null,
      scope: "type",
      types: [],
      staff: [],
      roles: [],
      users: [],
      targetOptions: [],
      selectedTypeId: null,
      selectedStaffId: null,
      items: [],
      form: emptyWatcher(),
    };
  },
  computed: {
    selectedType() {
      return this.types.find((item) => Number(item.id) === Number(this.selectedTypeId)) || null;
    },
    selectedStaff() {
      return this.staff.find((item) => Number(item.id) === Number(this.selectedStaffId)) || null;
    },
    currentLabel() {
      return this.scope === "type"
        ? this.selectedType?.name || "Sin tipo seleccionado"
        : this.selectedStaff?.full_name || "Sin funcionario seleccionado";
    },
    currentEmptyLabel() {
      return this.scope === "type"
        ? "Selecciona un tipo de permiso."
        : "Selecciona un funcionario.";
    },
  },
  watch: {
    "$route.query": {
      deep: true,
      async handler() {
        if (!this.types.length && !this.staff.length) {
          return;
        }

        this.applyRouteSelection();
        await this.loadItems();
      },
    },
  },
  mounted() {
    this.loadCatalogs();
  },
  methods: {
    applyRouteSelection() {
      const scope = this.$route.query?.scope;
      const staffId = this.$route.query?.staff_id;
      const typeId = this.$route.query?.type_id;

      if (scope === "staff") {
        this.scope = "staff";
      } else if (scope === "type") {
        this.scope = "type";
      }

      if (staffId && this.staff.some((item) => Number(item.id) === Number(staffId))) {
        this.selectedStaffId = Number(staffId);
      }

      if (typeId && this.types.some((item) => Number(item.id) === Number(typeId))) {
        this.selectedTypeId = Number(typeId);
      }
    },
    async loadCatalogs() {
      this.loadingCatalogs = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/permission-type-watchers/catalogs");
        this.types = response.data.types || [];
        this.staff = response.data.staff || [];
        this.roles = response.data.roles || [];
        this.users = response.data.users || [];
        this.targetOptions = response.data.target_options || [];

        if (!this.selectedTypeId && this.types.length) {
          this.selectedTypeId = this.types[0].id;
        }

        if (!this.selectedStaffId && this.staff.length) {
          this.selectedStaffId = this.staff[0].id;
        }

        this.applyRouteSelection();
        await this.loadItems();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingCatalogs = false;
      }
    },
    async loadItems() {
      if (this.scope === "type" && !this.selectedTypeId) {
        this.items = [];
        return;
      }

      if (this.scope === "staff" && !this.selectedStaffId) {
        this.items = [];
        return;
      }

      this.loadingItems = true;
      this.error = null;
      this.success = null;
      try {
        const response = this.scope === "type"
          ? await axios.get(`/api/staff/permission-types/${this.selectedTypeId}/watchers`)
          : await axios.get(`/api/staff/${this.selectedStaffId}/permission-watchers`);
        this.items = (response.data.data || []).map((item) => ({
          id: item.id,
          target_type: item.target_type,
          role_id: item.role_id,
          user_id: item.user_id,
          notify: !!item.notify,
          can_view: !!item.can_view,
          active: !!item.active,
          role: item.role || null,
          user: item.user || null,
        }));
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingItems = false;
      }
    },
    addWatcher() {
      this.error = null;

      if (this.form.target_type === "role" && !this.form.role_id) {
        this.error = "Debes seleccionar un rol.";
        return;
      }

      if (this.form.target_type === "user" && !this.form.user_id) {
        this.error = "Debes seleccionar un usuario.";
        return;
      }

      const duplicate = this.items.some((item) =>
        item.target_type === this.form.target_type &&
        Number(item.role_id || 0) === Number(this.form.role_id || 0) &&
        Number(item.user_id || 0) === Number(this.form.user_id || 0)
      );

      if (duplicate) {
        this.error = "Ese destinatario ya está agregado.";
        return;
      }

      this.items.push({
        ...emptyWatcher(),
        ...this.form,
        role: this.roles.find((item) => Number(item.id) === Number(this.form.role_id)) || null,
        user: this.users.find((item) => Number(item.id) === Number(this.form.user_id)) || null,
      });

      this.form = emptyWatcher();
    },
    resetForm() {
      this.form = emptyWatcher();
    },
    removeWatcher(index) {
      this.items.splice(index, 1);
    },
    async save() {
      if (this.scope === "type" && !this.selectedTypeId) return;
      if (this.scope === "staff" && !this.selectedStaffId) return;

      this.saving = true;
      this.error = null;
      this.success = null;

      try {
        const payload = {
          watchers: this.items.map((item) => ({
            target_type: item.target_type,
            role_id: item.target_type === "role" ? item.role_id : null,
            user_id: item.target_type === "user" ? item.user_id : null,
            notify: !!item.notify,
            can_view: !!item.can_view,
            active: !!item.active,
          })),
        };

        if (this.scope === "type") {
          await axios.put(`/api/staff/permission-types/${this.selectedTypeId}/watchers`, payload);
          this.success = "Destinatarios por tipo guardados correctamente.";
        } else {
          await axios.put(`/api/staff/${this.selectedStaffId}/permission-watchers`, payload);
          this.success = "Destinatarios por solicitante guardados correctamente.";
        }

        await this.loadItems();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async confirmReset() {
      const result = await Swal.fire({
        title: "Descartar cambios",
        text: "Se volverá a cargar la configuración guardada.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Recargar",
        cancelButtonText: "Cancelar",
      });

      if (result.isConfirmed) {
        await this.loadItems();
      }
    },
    watcherLabel(item) {
      if (item.target_type === "manager") return "Jefatura directa";
      if (item.target_type === "direction") return "Dirección";
      if (item.target_type === "hr") return "RRHH / Administración";
      if (item.target_type === "role") return `Rol: ${item.role?.name || "Sin rol"}`;
      if (item.target_type === "user") return `Usuario: ${item.user?.name || "Sin usuario"}`;
      return item.target_type;
    },
    formatUserOption(user) {
      return {
        value: user.id,
        text: `${user.name}${user.email ? ` · ${user.email}` : ""}`,
      };
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (errors ? errors[Object.keys(errors)[0]]?.[0] : null) || error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Quién debe enterarse</h4>
        <div class="text-muted">Configura destinatarios por tipo de permiso o por solicitante específico.</div>
      </div>
      <BButton variant="outline-primary" @click="$router.push('/staff/permissions/watchers-summary')">
        Ver resumen por funcionario
      </BButton>
    </div>

    <BAlert show variant="info" class="mb-3">
      Ejemplo: para <strong>Permiso administrativo</strong> puedes agregar <strong>RRHH / Administración</strong>, luego un
      <strong>usuario específico</strong> como la Coordinadora Académica X, y otro <strong>usuario específico</strong> como el Subdirector Y.
      Y si quieres que <strong>cualquier permiso</strong> de un solicitante específico llegue además a ciertas personas, usa el modo
      <strong>Por solicitante</strong>.
    </BAlert>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <BAlert v-if="success" show variant="success">{{ success }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-4">
        <BCard title="Base de configuración">
          <div v-if="loadingCatalogs" class="text-muted">Cargando tipos...</div>
          <div v-else>
            <label class="form-label">Modo</label>
            <BFormSelect v-model="scope" @change="loadItems">
              <option value="type">Por tipo de permiso</option>
              <option value="staff">Por solicitante específico</option>
            </BFormSelect>

            <div v-if="scope === 'type'" class="mt-3">
              <label class="form-label">Tipo de permiso</label>
              <BFormSelect v-model="selectedTypeId" @change="loadItems">
                <option :value="null">Seleccionar...</option>
                <option v-for="item in types" :key="item.id" :value="item.id">
                  {{ item.name }}{{ item.active ? "" : " (inactivo)" }}
                </option>
              </BFormSelect>
            </div>

            <div v-else class="mt-3">
              <label class="form-label">Solicitante</label>
              <BFormSelect v-model="selectedStaffId" @change="loadItems">
                <option :value="null">Seleccionar...</option>
                <option v-for="item in staff" :key="item.id" :value="item.id">
                  {{ item.full_name }}
                </option>
              </BFormSelect>
            </div>
          </div>
        </BCard>

        <BCard class="mt-3" title="Agregar destinatario">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Tipo de destinatario</label>
              <BFormSelect v-model="form.target_type">
                <option v-for="option in targetOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </BFormSelect>
            </div>

            <div v-if="form.target_type === 'role'" class="col-12">
              <label class="form-label">Rol</label>
              <BFormSelect v-model="form.role_id">
                <option :value="null">Seleccionar rol...</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
              </BFormSelect>
            </div>

            <div v-if="form.target_type === 'user'" class="col-12">
              <label class="form-label">Usuario</label>
              <BFormSelect v-model="form.user_id">
                <option :value="null">Seleccionar usuario...</option>
                <option v-for="user in users" :key="user.id" :value="user.id">
                  {{ formatUserOption(user).text }}
                </option>
              </BFormSelect>
            </div>

            <div class="col-md-6">
              <BFormCheckbox v-model="form.notify">Enviar aviso</BFormCheckbox>
            </div>
            <div class="col-md-6">
              <BFormCheckbox v-model="form.can_view">Puede ver solicitudes</BFormCheckbox>
            </div>
            <div class="col-md-6">
              <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
            </div>
          </div>

          <div class="d-flex gap-2 mt-3">
            <BButton variant="primary" :disabled="!selectedTypeId" @click="addWatcher">Agregar</BButton>
            <BButton variant="outline-secondary" @click="resetForm">Limpiar</BButton>
          </div>
        </BCard>
      </div>

      <div class="col-xl-8">
        <BCard>
          <template #header>
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h5 class="mb-0">Configuración actual</h5>
                <div class="text-muted small">{{ currentLabel }}</div>
              </div>
              <div class="d-flex gap-2">
                <BButton
                  variant="outline-secondary"
                  :disabled="loadingItems || (scope === 'type' ? !selectedTypeId : !selectedStaffId)"
                  @click="confirmReset"
                >
                  Recargar
                </BButton>
                <BButton
                  variant="success"
                  :disabled="saving || (scope === 'type' ? !selectedTypeId : !selectedStaffId)"
                  @click="save"
                >
                  {{ saving ? "Guardando..." : "Guardar cambios" }}
                </BButton>
              </div>
            </div>
          </template>

          <div v-if="loadingItems" class="text-muted">Cargando destinatarios...</div>
          <div v-else-if="scope === 'type' && !selectedTypeId" class="text-muted">{{ currentEmptyLabel }}</div>
          <div v-else-if="scope === 'staff' && !selectedStaffId" class="text-muted">{{ currentEmptyLabel }}</div>
          <div v-else-if="!items.length" class="text-muted">
            {{ scope === 'type' ? 'No hay destinatarios configurados para este tipo.' : 'No hay destinatarios configurados para este solicitante.' }}
          </div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Destinatario</th>
                  <th>Aviso</th>
                  <th>Puede ver</th>
                  <th>Activo</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in items" :key="`${item.target_type}-${item.role_id || 0}-${item.user_id || 0}-${index}`">
                  <td>{{ watcherLabel(item) }}</td>
                  <td><BFormCheckbox v-model="item.notify" switch /></td>
                  <td><BFormCheckbox v-model="item.can_view" switch /></td>
                  <td><BFormCheckbox v-model="item.active" switch /></td>
                  <td class="text-end">
                    <BButton size="sm" variant="outline-danger" @click="removeWatcher(index)">Quitar</BButton>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
