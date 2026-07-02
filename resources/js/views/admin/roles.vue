<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";

const emptyForm = () => ({
  id: null,
  name: "",
  slug: "",
  description: "",
  active: true,
  permissions: [],
  modules: [],
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      roles: [],
      catalogs: { permissions: [], modules: [] },
      showModal: false,
      form: emptyForm(),
      error: null,
      success: null,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    permissionOptions() {
      return this.catalogs.permissions.map((p) => ({ value: p.id, label: `${p.name} (${p.slug})` }));
    },
    moduleOptions() {
      return this.catalogs.modules.map((m) => ({
        value: m.id,
        label: m.parent_id ? `↳ ${m.name}` : m.name,
      }));
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadRoles();
  },
  methods: {
    normalizeIdList(values) {
      return (values || [])
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0);
    },
    async loadCatalogs() {
      const response = await axios.get("/api/admin/roles/catalogs");
      this.catalogs = response.data;
    },
    async loadRoles() {
      this.loading = true;
      try {
        const response = await axios.get("/api/admin/roles");
        this.roles = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async openEdit(role) {
      this.error = null;
      const response = await axios.get(`/api/admin/roles/${role.id}`);
      const data = response.data.data;

      this.form = {
        id: data.id,
        name: data.name,
        slug: data.slug,
        description: data.description || "",
        active: Boolean(data.active),
        permissions: (data.permissions || []).map((p) => p.id),
        modules: (data.modules || []).map((m) => m.id),
      };
      this.showModal = true;
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/admin/roles/${this.form.id}`, {
            name: this.form.name,
            slug: this.form.slug,
            description: this.form.description || null,
            active: this.form.active,
          });
          await axios.put(`/api/admin/roles/${this.form.id}/permissions`, {
            permissions: this.normalizeIdList(this.form.permissions),
          });
          await axios.put(`/api/admin/roles/${this.form.id}/modules`, {
            modules: this.normalizeIdList(this.form.modules),
          });
          this.success = "Rol actualizado.";
        } else {
          const response = await axios.post(`/api/admin/roles`, {
            name: this.form.name,
            slug: this.form.slug,
            description: this.form.description || null,
            active: this.form.active,
          });
          const id = response.data.data.id;
          await axios.put(`/api/admin/roles/${id}/permissions`, {
            permissions: this.normalizeIdList(this.form.permissions),
          });
          await axios.put(`/api/admin/roles/${id}/modules`, {
            modules: this.normalizeIdList(this.form.modules),
          });
          this.success = "Rol creado.";
        }
        this.showModal = false;
        this.loadRoles();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(role) {
      if (!confirm(`Eliminar rol ${role.name}?`)) return;
      await axios.delete(`/api/admin/roles/${role.id}`);
      this.loadRoles();
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Roles</h4>
      <BButton variant="primary" @click="openCreate">Nuevo rol</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <BTable
      :items="roles"
      :busy="loading"
      small
      :fields="[
        { key: 'name', label: 'Nombre' },
        { key: 'slug', label: 'Slug' },
        { key: 'users_count', label: 'Usuarios' },
        { key: 'permissions_count', label: 'Permisos' },
        { key: 'modules_count', label: 'Módulos' },
        { key: 'active', label: 'Activo' },
        { key: 'actions', label: 'Acciones' },
      ]"
    >
      <template #table-busy>
        <LoadingState message="Cargando roles..." compact />
      </template>
      <template #cell(active)="{ item }">
        <BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? "Sí" : "No" }}</BBadge>
      </template>
      <template #cell(actions)="{ item }">
        <div class="d-flex gap-2">
          <BButton size="sm" variant="info" @click="openEdit(item)">Editar</BButton>
          <BButton size="sm" variant="danger" @click="remove(item)">Eliminar</BButton>
        </div>
      </template>
    </BTable>

    <BModal v-model="showModal" :title="isEditing ? 'Editar rol' : 'Nuevo rol'" size="lg" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Slug</label>
          <BFormInput v-model="form.slug" />
        </div>
        <div class="col-12 mb-3">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="2" />
        </div>
        <div class="col-12 mb-3">
          <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label">Permisos</label>
          <Multiselect
            v-model="form.permissions"
            :options="permissionOptions"
            mode="multiple"
            :close-on-select="false"
            :searchable="true"
          />
        </div>
        <div class="col-12 mb-3">
          <label class="form-label">Módulos visibles</label>
          <Multiselect
            v-model="form.modules"
            :options="moduleOptions"
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
