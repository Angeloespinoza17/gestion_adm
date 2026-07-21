<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import {
  confirmInfirmaryAction,
  formatInfirmaryError,
  showInfirmaryError,
  showInfirmarySuccess,
} from "../../components/infirmary/module-utils";

const emptyForm = () => ({
  id: null,
  name: "",
  code: "",
  description: "",
  sort_order: 1,
  active: true,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      search: "",
      activeFilter: null,
      categories: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      catalogs: { capabilities: {} },
      showModal: false,
      form: emptyForm(),
    };
  },
  computed: {
    canManage() {
      return Boolean(this.catalogs.capabilities?.can_manage_catalogs);
    },
    isEditing() {
      return Boolean(this.form.id);
    },
    activeOptions() {
      return [
        { value: null, text: "Todas" },
        { value: "1", text: "Activas" },
        { value: "0", text: "Inactivas" },
      ];
    },
    tableFields() {
      return [
        { key: "sort_order", label: "Orden", thStyle: { width: "90px" } },
        { key: "name", label: "Categoría" },
        { key: "code", label: "Código" },
        { key: "attentions_count", label: "Usos", thStyle: { width: "90px" } },
        { key: "active", label: "Estado", thStyle: { width: "110px" } },
        { key: "actions", label: "Acciones", thClass: "text-end", tdClass: "text-end" },
      ];
    },
  },
  async mounted() {
    await Promise.all([this.loadCatalogs(), this.loadCategories()]);
  },
  methods: {
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/infirmary/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar los permisos del módulo.");
      }
    },
    async loadCategories(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/categories", {
          params: {
            page,
            search: this.search,
            active: this.activeFilter ?? "",
          },
        });
        this.categories = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar las categorías.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.search = "";
      this.activeFilter = null;
      this.loadCategories(1);
    },
    openCreate() {
      const nextOrder = Math.max(0, ...this.categories.map((item) => Number(item.sort_order || 0))) + 1;
      this.form = {
        ...emptyForm(),
        sort_order: nextOrder,
      };
      this.error = null;
      this.showModal = true;
    },
    openEdit(category) {
      this.form = {
        id: category.id,
        name: category.name || "",
        code: category.code || "",
        description: category.description || "",
        sort_order: category.sort_order || 1,
        active: Boolean(category.active),
      };
      this.error = null;
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        const payload = {
          name: this.form.name,
          code: this.form.code || null,
          description: this.form.description || null,
          sort_order: Number(this.form.sort_order || 1),
          active: Boolean(this.form.active),
        };

        if (this.isEditing) {
          await axios.put(`/api/infirmary/categories/${this.form.id}`, payload);
          await showInfirmarySuccess("Categoría actualizada correctamente.");
        } else {
          await axios.post("/api/infirmary/categories", payload);
          await showInfirmarySuccess("Categoría creada correctamente.");
        }

        this.showModal = false;
        await Promise.all([this.loadCatalogs(), this.loadCategories(this.pagination.current_page || 1)]);
      } catch (error) {
        const message = formatInfirmaryError(error, "No se pudo guardar la categoría.");
        this.error = message;
        await showInfirmaryError(message);
      } finally {
        this.saving = false;
      }
    },
    async toggleActive(category) {
      const nextActive = !category.active;
      const confirmation = await confirmInfirmaryAction({
        title: nextActive ? "Activar categoría" : "Desactivar categoría",
        text: nextActive
          ? `La categoría ${category.name} volverá a aparecer en la ficha de atención.`
          : `La categoría ${category.name} dejará de aparecer como opción nueva, pero se mantiene el historial.`,
        confirmButtonText: nextActive ? "Activar" : "Desactivar",
        icon: "question",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.put(`/api/infirmary/categories/${category.id}`, {
          name: category.name,
          code: category.code,
          description: category.description,
          sort_order: category.sort_order || 1,
          active: nextActive,
        });
        await this.loadCategories(this.pagination.current_page || 1);
        await this.loadCatalogs();
        await showInfirmarySuccess(nextActive ? "Categoría activada." : "Categoría desactivada.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo cambiar el estado."));
      }
    },
    async remove(category) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar categoría",
        text: `Se eliminará la categoría ${category.name}.`,
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/categories/${category.id}`);
        await this.loadCategories(this.pagination.current_page || 1);
        await this.loadCatalogs();
        await showInfirmarySuccess("Categoría eliminada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar la categoría."));
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Categorías de atención</h4>
        <div class="text-muted">Opciones que se muestran en la ficha de atención de enfermería.</div>
      </div>
      <BButton v-if="canManage" variant="primary" @click="openCreate">
        <i class="bx bx-plus me-1"></i>
        Nueva categoría
      </BButton>
    </div>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-lg-5">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="search" placeholder="Nombre, código o descripción" @keyup.enter="loadCategories(1)" />
        </div>
        <div class="col-md-4 col-lg-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="activeFilter" :options="activeOptions" />
        </div>
        <div class="col-md-auto d-flex gap-2">
          <BButton variant="primary" @click="loadCategories(1)">
            <i class="bx bx-search me-1"></i>
            Filtrar
          </BButton>
          <BButton variant="outline-secondary" @click="resetFilters">
            <i class="bx bx-x me-1"></i>
            Limpiar
          </BButton>
        </div>
      </div>
    </BCard>

    <BCard>
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div>
            <div class="fw-semibold">Listado de categorías</div>
            <div class="text-muted small">{{ pagination.total }} registros encontrados</div>
          </div>
        </div>
      </template>

      <LoadingState v-if="loading" message="Cargando categorías..." compact />

      <div v-else class="table-responsive">
        <BTable :items="categories" :fields="tableFields" small responsive class="align-middle mb-0">
          <template #empty>
            <div class="text-center text-muted py-4">No hay categorías para los filtros seleccionados.</div>
          </template>
          <template #cell(name)="{ item }">
            <div class="fw-semibold">{{ item.name }}</div>
            <div class="small text-muted">{{ item.description || "Sin descripción." }}</div>
          </template>
          <template #cell(code)="{ item }">
            <code>{{ item.code }}</code>
          </template>
          <template #cell(attentions_count)="{ item }">
            <span class="category-count">{{ item.attentions_count || 0 }}</span>
          </template>
          <template #cell(active)="{ item }">
            <BBadge :variant="item.active ? 'success' : 'secondary'">
              {{ item.active ? "Activa" : "Inactiva" }}
            </BBadge>
          </template>
          <template #cell(actions)="{ item }">
            <div v-if="canManage" class="d-flex justify-content-end gap-2 flex-wrap">
              <BButton size="sm" variant="outline-primary" @click="openEdit(item)">
                <i class="bx bx-edit-alt me-1"></i>
                Editar
              </BButton>
              <BButton
                size="sm"
                :variant="item.active ? 'outline-secondary' : 'outline-success'"
                @click="toggleActive(item)"
              >
                <i :class="item.active ? 'bx bx-hide me-1' : 'bx bx-show me-1'"></i>
                {{ item.active ? "Desactivar" : "Activar" }}
              </BButton>
              <BButton
                v-if="!item.attentions_count"
                size="sm"
                variant="outline-danger"
                @click="remove(item)"
              >
                <i class="bx bx-trash me-1"></i>
                Eliminar
              </BButton>
            </div>
            <span v-else class="text-muted">Solo lectura</span>
          </template>
        </BTable>
      </div>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="loadCategories"
        />
      </div>
    </BCard>

    <BModal
      v-model="showModal"
      :title="isEditing ? 'Editar categoría' : 'Nueva categoría'"
      size="lg"
      hide-footer
    >
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" placeholder="Ej: Dolor de cabeza" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Código</label>
          <BFormInput v-model="form.code" placeholder="Auto si queda vacío" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Orden</label>
          <BFormInput v-model="form.sort_order" type="number" min="1" max="999" />
        </div>
        <div class="col-md-8 d-flex align-items-end">
          <BFormCheckbox v-model="form.active">Activa</BFormCheckbox>
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          <i class="bx bx-save me-1"></i>
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.category-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2rem;
  min-height: 1.5rem;
  border: 1px solid #d7deea;
  border-radius: 8px;
  color: #495057;
  background: #f8f9fa;
  font-size: 0.78rem;
  font-weight: 600;
}
</style>
