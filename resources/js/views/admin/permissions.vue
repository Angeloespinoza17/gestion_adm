<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const emptyForm = () => ({
  name: "",
  slug: "",
  description: "",
  active: true,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      permissions: [],
      showModal: false,
      form: emptyForm(),
      error: null,
      success: null,
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      try {
        const response = await axios.get("/api/admin/permissions");
        this.permissions = response.data.data;
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
    async save() {
      this.saving = true;
      this.error = null;
      try {
        await axios.post("/api/admin/permissions", {
          name: this.form.name,
          slug: this.form.slug,
          description: this.form.description || null,
          active: this.form.active,
        });
        this.success = "Permiso creado.";
        this.showModal = false;
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
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
      <h4 class="mb-0">Permisos</h4>
      <BButton variant="primary" @click="openCreate">Nuevo permiso</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <BTable
      :items="permissions"
      :busy="loading"
      small
      :fields="[
        { key: 'name', label: 'Nombre' },
        { key: 'slug', label: 'Slug' },
        { key: 'active', label: 'Activo' },
      ]"
    >
      <template #table-busy>
        <LoadingState message="Cargando permisos..." compact />
      </template>
      <template #cell(active)="{ item }">
        <BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? "Sí" : "No" }}</BBadge>
      </template>
    </BTable>

    <BModal v-model="showModal" title="Nuevo permiso" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <div class="mb-3">
        <label class="form-label">Nombre</label>
        <BFormInput v-model="form.name" />
      </div>
      <div class="mb-3">
        <label class="form-label">Slug</label>
        <BFormInput v-model="form.slug" />
      </div>
      <div class="mb-3">
        <label class="form-label">Descripción</label>
        <BFormTextarea v-model="form.description" rows="2" />
      </div>
      <div class="mb-3">
        <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
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
