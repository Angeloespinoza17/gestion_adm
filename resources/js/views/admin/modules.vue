<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";

const emptyForm = () => ({
  id: null,
  name: "",
  slug: "",
  frontend_route: "",
  icon: "",
  sort_order: 0,
  active: true,
  parent_id: null,
});

export default {
  components: { Layout },
  data() {
    return {
      loading: false,
      saving: false,
      modules: [],
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
    parentOptions() {
      return [{ value: null, text: "Sin padre" }].concat(
        this.modules
          .filter((m) => m.parent_id === null)
          .map((m) => ({ value: m.id, text: m.name }))
      );
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      try {
        const response = await axios.get("/api/admin/modules");
        this.modules = response.data.data;
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
    openEdit(mod) {
      this.form = {
        id: mod.id,
        name: mod.name,
        slug: mod.slug,
        frontend_route: mod.frontend_route || "",
        icon: mod.icon || "",
        sort_order: mod.sort_order || 0,
        active: Boolean(mod.active),
        parent_id: mod.parent_id ?? null,
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/admin/modules/${this.form.id}`, this.form);
          this.success = "Módulo actualizado.";
        } else {
          await axios.post("/api/admin/modules", this.form);
          this.success = "Módulo creado.";
        }
        this.showModal = false;
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async toggle(mod) {
      await axios.put(`/api/admin/modules/${mod.id}/active`, { active: !mod.active });
      this.load();
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
      <h4 class="mb-0">Módulos</h4>
      <BButton variant="primary" @click="openCreate">Nuevo módulo</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <BTable
      :items="modules"
      :busy="loading"
      small
      :fields="[
        { key: 'name', label: 'Nombre' },
        { key: 'slug', label: 'Slug' },
        { key: 'frontend_route', label: 'Ruta' },
        { key: 'parent_id', label: 'Padre' },
        { key: 'sort_order', label: 'Orden' },
        { key: 'active', label: 'Activo' },
        { key: 'actions', label: 'Acciones' },
      ]"
    >
      <template #cell(parent_id)="{ item }">
        <span v-if="!item.parent_id">-</span>
        <span v-else>
          {{ modules.find((m) => m.id === item.parent_id)?.name || item.parent_id }}
        </span>
      </template>
      <template #cell(active)="{ item }">
        <BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? "Sí" : "No" }}</BBadge>
      </template>
      <template #cell(actions)="{ item }">
        <div class="d-flex gap-2">
          <BButton size="sm" variant="info" @click="openEdit(item)">Editar</BButton>
          <BButton size="sm" :variant="item.active ? 'warning' : 'success'" @click="toggle(item)">
            {{ item.active ? "Desactivar" : "Activar" }}
          </BButton>
        </div>
      </template>
    </BTable>

    <BModal v-model="showModal" :title="isEditing ? 'Editar módulo' : 'Nuevo módulo'" size="lg" hide-footer>
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
        <div class="col-md-6 mb-3">
          <label class="form-label">Ruta frontend</label>
          <BFormInput v-model="form.frontend_route" placeholder="/ruta" />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Ícono (boxicons)</label>
          <BFormInput v-model="form.icon" placeholder="bx-home-circle" />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Padre</label>
          <BFormSelect v-model="form.parent_id" :options="parentOptions" />
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Orden</label>
          <BFormInput v-model="form.sort_order" type="number" />
        </div>
        <div class="col-md-3 mb-3 d-flex align-items-end">
          <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
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

