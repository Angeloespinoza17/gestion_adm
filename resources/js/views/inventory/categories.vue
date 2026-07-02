<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const emptyForm = () => ({
  id: null,
  name: "",
  code_prefix: "",
  description: "",
  active: true,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      search: "",
      categories: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
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
  },
  mounted() {
    this.loadCategories();
  },
  methods: {
    async loadCategories(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/inventory/categories", {
          params: { page, search: this.search },
        });
        this.categories = response.data.data;
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
      this.showModal = true;
    },
    openEdit(category) {
      this.form = {
        id: category.id,
        name: category.name || "",
        code_prefix: category.code_prefix || "",
        description: category.description || "",
        active: Boolean(category.active),
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/inventory/categories/${this.form.id}`, {
            name: this.form.name,
            code_prefix: this.form.code_prefix,
            description: this.form.description || null,
            active: this.form.active,
          });
          this.success = "Categoría actualizada.";
        } else {
          await axios.post("/api/inventory/categories", {
            name: this.form.name,
            code_prefix: this.form.code_prefix,
            description: this.form.description || null,
            active: this.form.active,
          });
          this.success = "Categoría creada.";
        }
        this.showModal = false;
        await this.loadCategories(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(category) {
      if (!confirm(`Eliminar categoría ${category.name}?`)) return;
      await axios.delete(`/api/inventory/categories/${category.id}`);
      this.loadCategories(this.pagination.current_page);
    },
    formatError(error) {
      return (
        error?.response?.data?.message ||
        error?.response?.data?.errors?.[
          Object.keys(error.response.data.errors || {})[0]
        ]?.[0] ||
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
      <h4 class="mb-0">Inventario · Categorías</h4>
      <BButton variant="primary" @click="openCreate">Nueva categoría</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <div class="row mb-3">
      <div class="col-md-4">
        <BFormInput v-model="search" placeholder="Buscar" @keyup.enter="loadCategories(1)" />
      </div>
      <div class="col-md-2">
        <BButton variant="secondary" @click="loadCategories(1)">Buscar</BButton>
      </div>
    </div>

    <div class="table-responsive">
      <BTable
        :items="categories"
        :busy="loading"
        :fields="[
          { key: 'name', label: 'Nombre' },
          { key: 'code_prefix', label: 'Prefijo' },
          { key: 'active', label: 'Activa' },
          { key: 'actions', label: 'Acciones' },
        ]"
        small
      >
        <template #table-busy>
          <LoadingState message="Cargando categorías..." compact />
        </template>
        <template #cell(active)="{ item }">
          <BBadge :variant="item.active ? 'success' : 'secondary'">
            {{ item.active ? "Sí" : "No" }}
          </BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <BButton size="sm" variant="info" @click="openEdit(item)">Editar</BButton>
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
        @update:model-value="loadCategories"
      />
    </div>

    <BModal
      v-model="showModal"
      :title="isEditing ? 'Editar categoría' : 'Nueva categoría'"
      size="lg"
      hide-footer
    >
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Prefijo</label>
          <BFormInput v-model="form.code_prefix" placeholder="ej: TEC" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>
        <div class="col-12">
          <BFormCheckbox v-model="form.active">Activa</BFormCheckbox>
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
