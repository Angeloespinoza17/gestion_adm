<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const emptyCategoryForm = () => ({
  id: null,
  name: "",
  code_prefix: "",
  description: "",
  active: true,
});

const emptySubcategoryForm = () => ({
  id: null,
  category_id: null,
  name: "",
  description: "",
  active: true,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      activeTab: "categories",
      loading: false,
      loadingSubcategories: false,
      saving: false,
      search: "",
      subcategorySearch: "",
      subcategoryCategoryFilter: null,
      categories: [],
      categoryCatalog: [],
      subcategories: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      subcategoryPagination: { current_page: 1, last_page: 1, total: 0 },
      showCategoryModal: false,
      showSubcategoryModal: false,
      categoryForm: emptyCategoryForm(),
      subcategoryForm: emptySubcategoryForm(),
      error: null,
      success: null,
    };
  },
  computed: {
    isEditingCategory() {
      return Boolean(this.categoryForm.id);
    },
    isEditingSubcategory() {
      return Boolean(this.subcategoryForm.id);
    },
    categoryFilterOptions() {
      return [{ value: null, text: "Todas" }].concat(
        this.categoryCatalog.map((category) => ({
          value: category.id,
          text: category.name,
        }))
      );
    },
    categoryFormOptions() {
      return [{ value: null, text: "Selecciona categoría" }].concat(
        this.categoryCatalog.map((category) => ({
          value: category.id,
          text: category.name,
        }))
      );
    },
  },
  mounted() {
    this.loadCategories();
    this.loadCategoryCatalog();
    this.loadSubcategories();
  },
  methods: {
    switchTab(tab) {
      this.activeTab = tab;
      this.error = null;
    },
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
    async loadCategoryCatalog() {
      const response = await axios.get("/api/inventory/categories", {
        params: { per_page: 500 },
      });
      this.categoryCatalog = response.data.data || [];
    },
    async loadSubcategories(page = 1) {
      this.loadingSubcategories = true;
      this.error = null;
      try {
        const response = await axios.get("/api/inventory/subcategories", {
          params: {
            page,
            search: this.subcategorySearch,
            category_id: this.subcategoryCategoryFilter || "",
          },
        });
        this.subcategories = response.data.data;
        this.subcategoryPagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingSubcategories = false;
      }
    },
    openCreateCategory() {
      this.categoryForm = emptyCategoryForm();
      this.showCategoryModal = true;
    },
    openEditCategory(category) {
      this.categoryForm = {
        id: category.id,
        name: category.name || "",
        code_prefix: category.code_prefix || "",
        description: category.description || "",
        active: Boolean(category.active),
      };
      this.showCategoryModal = true;
    },
    openCreateSubcategory(category = null) {
      this.subcategoryForm = {
        ...emptySubcategoryForm(),
        category_id: category?.id || this.subcategoryCategoryFilter || null,
      };
      this.showSubcategoryModal = true;
    },
    openEditSubcategory(subcategory) {
      this.subcategoryForm = {
        id: subcategory.id,
        category_id: subcategory.category_id ?? subcategory.category?.id ?? null,
        name: subcategory.name || "",
        description: subcategory.description || "",
        active: Boolean(subcategory.active),
      };
      this.showSubcategoryModal = true;
    },
    async saveCategory() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        const payload = {
          name: this.categoryForm.name,
          code_prefix: this.categoryForm.code_prefix,
          description: this.categoryForm.description || null,
          active: this.categoryForm.active,
        };

        if (this.isEditingCategory) {
          await axios.put(`/api/inventory/categories/${this.categoryForm.id}`, payload);
          this.success = "Categoría actualizada.";
        } else {
          await axios.post("/api/inventory/categories", payload);
          this.success = "Categoría creada.";
        }

        this.showCategoryModal = false;
        await Promise.all([
          this.loadCategories(this.pagination.current_page),
          this.loadCategoryCatalog(),
          this.loadSubcategories(this.subcategoryPagination.current_page),
        ]);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async saveSubcategory() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        const payload = {
          category_id: this.subcategoryForm.category_id,
          name: this.subcategoryForm.name,
          description: this.subcategoryForm.description || null,
          active: this.subcategoryForm.active,
        };

        if (this.isEditingSubcategory) {
          await axios.put(
            `/api/inventory/subcategories/${this.subcategoryForm.id}`,
            payload
          );
          this.success = "Subcategoría actualizada.";
        } else {
          await axios.post("/api/inventory/subcategories", payload);
          this.success = "Subcategoría creada.";
        }

        this.showSubcategoryModal = false;
        await Promise.all([
          this.loadCategories(this.pagination.current_page),
          this.loadSubcategories(this.subcategoryPagination.current_page),
        ]);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async removeCategory(category) {
      if (!confirm(`Eliminar categoría ${category.name}?`)) return;
      await axios.delete(`/api/inventory/categories/${category.id}`);
      await Promise.all([
        this.loadCategories(this.pagination.current_page),
        this.loadCategoryCatalog(),
        this.loadSubcategories(this.subcategoryPagination.current_page),
      ]);
    },
    async removeSubcategory(subcategory) {
      if (!confirm(`Eliminar subcategoría ${subcategory.name}?`)) return;
      await axios.delete(`/api/inventory/subcategories/${subcategory.id}`);
      await Promise.all([
        this.loadCategories(this.pagination.current_page),
        this.loadSubcategories(this.subcategoryPagination.current_page),
      ]);
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
      <BButton
        variant="primary"
        @click="activeTab === 'categories' ? openCreateCategory() : openCreateSubcategory()"
      >
        {{ activeTab === "categories" ? "Nueva categoría" : "Nueva subcategoría" }}
      </BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <div class="inventory-category-tabs mb-3">
      <BButton
        :variant="activeTab === 'categories' ? 'primary' : 'outline-primary'"
        @click="switchTab('categories')"
      >
        Categorías
      </BButton>
      <BButton
        :variant="activeTab === 'subcategories' ? 'primary' : 'outline-primary'"
        @click="switchTab('subcategories')"
      >
        Subcategorías
      </BButton>
    </div>

    <template v-if="activeTab === 'categories'">
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
            { key: 'subcategories_count', label: 'Subcategorías' },
            { key: 'items_count', label: 'Bienes' },
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
          <template #cell(subcategories_count)="{ item }">
            <span class="inventory-category-count">
              {{ item.subcategories_count ?? 0 }}
            </span>
          </template>
          <template #cell(items_count)="{ item }">
            <span class="inventory-category-count">
              {{ item.items_count ?? 0 }}
            </span>
          </template>
          <template #cell(actions)="{ item }">
            <div class="d-flex gap-2 flex-wrap">
              <BButton size="sm" variant="outline-primary" @click="openCreateSubcategory(item)">
                Subcategoría
              </BButton>
              <BButton size="sm" variant="warning" @click="openEditCategory(item)">Editar</BButton>
              <BButton size="sm" variant="danger" @click="removeCategory(item)">Eliminar</BButton>
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
    </template>

    <template v-else>
      <div class="row mb-3 g-2">
        <div class="col-md-4">
          <BFormInput
            v-model="subcategorySearch"
            placeholder="Buscar subcategoría"
            @keyup.enter="loadSubcategories(1)"
          />
        </div>
        <div class="col-md-4">
          <BFormSelect
            v-model="subcategoryCategoryFilter"
            :options="categoryFilterOptions"
          />
        </div>
        <div class="col-md-2">
          <BButton variant="secondary" @click="loadSubcategories(1)">Buscar</BButton>
        </div>
      </div>

      <div class="table-responsive">
        <BTable
          :items="subcategories"
          :busy="loadingSubcategories"
          :fields="[
            { key: 'name', label: 'Nombre' },
            { key: 'category', label: 'Categoría' },
            { key: 'items_count', label: 'Bienes' },
            { key: 'active', label: 'Activa' },
            { key: 'actions', label: 'Acciones' },
          ]"
          small
        >
          <template #table-busy>
            <LoadingState message="Cargando subcategorías..." compact />
          </template>
          <template #cell(category)="{ item }">
            {{ item.category?.name || "-" }}
          </template>
          <template #cell(items_count)="{ item }">
            <span class="inventory-category-count">
              {{ item.items_count ?? 0 }}
            </span>
          </template>
          <template #cell(active)="{ item }">
            <BBadge :variant="item.active ? 'success' : 'secondary'">
              {{ item.active ? "Sí" : "No" }}
            </BBadge>
          </template>
          <template #cell(actions)="{ item }">
            <div class="d-flex gap-2 flex-wrap">
              <BButton size="sm" variant="warning" @click="openEditSubcategory(item)">Editar</BButton>
              <BButton size="sm" variant="danger" @click="removeSubcategory(item)">Eliminar</BButton>
            </div>
          </template>
        </BTable>
      </div>

      <div class="d-flex justify-content-end">
        <BPagination
          v-model="subcategoryPagination.current_page"
          :per-page="15"
          :total-rows="subcategoryPagination.total"
          @update:model-value="loadSubcategories"
        />
      </div>
    </template>

    <BModal
      v-model="showCategoryModal"
      :title="isEditingCategory ? 'Editar categoría' : 'Nueva categoría'"
      size="lg"
      hide-footer
    >
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="categoryForm.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Prefijo</label>
          <BFormInput v-model="categoryForm.code_prefix" placeholder="ej: TEC" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="categoryForm.description" rows="3" />
        </div>
        <div class="col-12">
          <BFormCheckbox v-model="categoryForm.active">Activa</BFormCheckbox>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showCategoryModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="saveCategory">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>

    <BModal
      v-model="showSubcategoryModal"
      :title="isEditingSubcategory ? 'Editar subcategoría' : 'Nueva subcategoría'"
      size="lg"
      hide-footer
    >
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Categoría</label>
          <BFormSelect
            v-model="subcategoryForm.category_id"
            :options="categoryFormOptions"
          />
        </div>
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="subcategoryForm.name" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="subcategoryForm.description" rows="3" />
        </div>
        <div class="col-12">
          <BFormCheckbox v-model="subcategoryForm.active">Activa</BFormCheckbox>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showSubcategoryModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="saveSubcategory">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.inventory-category-tabs {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.inventory-category-tabs .btn {
  min-width: 8.5rem;
  border-radius: 0.5rem;
  font-weight: 600;
}

.inventory-category-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.25rem;
  min-height: 1.65rem;
  padding: 0.24rem 0.6rem;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.78rem;
  font-weight: 650;
  line-height: 1;
}
</style>
