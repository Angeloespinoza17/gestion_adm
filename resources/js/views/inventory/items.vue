<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  name: "",
  description: "",
  category_id: null,
  subcategory_id: null,
  brand: "",
  model: "",
  serial_number: "",
  purchase_date: null,
  purchase_value: null,
  useful_life_years: null,
  status: "Activo",
  condition: "Bueno",
  dependency_id: null,
  responsible_user_id: null,
  supplier_id: null,
  active: true,
  item_type: "asset",
  stock_quantity: null,
  minimum_stock: null,
  unit_of_measure: "",
  photo: null,
});

export default {
  components: { Layout, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      exporting: false,
      search: "",
      filters: {
        category_id: null,
        subcategory_id: null,
        dependency_id: null,
        responsible_user_id: null,
        supplier_id: null,
        status: "",
        condition: "",
        item_type: "",
        low_stock: false,
      },
      items: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      catalogs: {
        categories: [],
        subcategories: [],
        dependencies: [],
        users: [],
        suppliers: [],
        item_types: [],
        statuses: [],
        conditions: [],
      },
      showModal: false,
      form: emptyForm(),
      error: null,
      success: null,
    };
  },
  computed: {
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (e) {
        return [];
      }
    },
    canExport() {
      return this.permissions.includes("exportar_inventario");
    },
    isEditing() {
      return Boolean(this.form.id);
    },
    categoryOptions() {
      return [{ value: null, label: "Selecciona..." }].concat(
        this.catalogs.categories.map((c) => ({ value: c.id, label: c.name }))
      );
    },
    subcategoryOptions() {
      const categoryId = this.form.category_id || this.filters.category_id;
      const subs = (this.catalogs.subcategories || []).filter((s) =>
        categoryId ? s.category_id === categoryId : true
      );
      return [{ value: null, label: "Sin subcategoría" }].concat(
        subs.map((s) => ({ value: s.id, label: s.name }))
      );
    },
    dependencyOptions() {
      return [{ value: null, label: "Sin dependencia" }].concat(
        this.catalogs.dependencies.map((d) => ({
          value: d.id,
          label: `${d.code} - ${d.name}`,
        }))
      );
    },
    userOptions() {
      return [{ value: null, label: "Sin responsable" }].concat(
        this.catalogs.users.map((u) => ({
          value: u.id,
          label: `${u.name} (${u.email})`,
        }))
      );
    },
    supplierOptions() {
      return [{ value: null, label: "Sin proveedor" }].concat(
        this.catalogs.suppliers.map((s) => ({
          value: s.id,
          label: s.business_name ? `${s.name} (${s.business_name})` : s.name,
        }))
      );
    },
    statusOptions() {
      return [{ value: "", label: "Todos" }].concat(
        (this.catalogs.statuses || []).map((s) => ({ value: s, label: s }))
      );
    },
    conditionOptions() {
      return [{ value: "", label: "Todos" }].concat(
        (this.catalogs.conditions || []).map((s) => ({ value: s, label: s }))
      );
    },
    itemTypeOptions() {
      return [{ value: "", label: "Todos" }].concat(
        (this.catalogs.item_types || []).map((t) => ({ value: t, label: t }))
      );
    },
  },
  watch: {
    "form.category_id"(newValue, oldValue) {
      if (newValue !== oldValue) {
        this.form.subcategory_id = null;
      }
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadItems();
  },
  methods: {
    validateForm() {
      const missing = [];

      if (!this.form.name || !String(this.form.name).trim()) missing.push("Nombre");
      if (!this.form.category_id) missing.push("Categoría");

      if (missing.length > 0) {
        Swal.fire({
          icon: "warning",
          title: "Faltan campos por completar",
          html: `<div class="text-start">Completa:<ul>${missing
            .map((m) => `<li>${m}</li>`)
            .join("")}</ul></div>`,
          confirmButtonText: "OK",
        });
        return false;
      }

      return true;
    },
    async loadCatalogs() {
      const response = await axios.get("/api/inventory/items/catalogs");
      this.catalogs = response.data;
    },
    async loadItems(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/inventory/items", {
          params: {
            page,
            search: this.search,
            ...this.filters,
            low_stock: this.filters.low_stock ? 1 : "",
          },
        });
        this.items = response.data.data;
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
    openEdit(item) {
      this.form = {
        ...emptyForm(),
        id: item.id,
        name: item.name || "",
        description: item.description || "",
        category_id: item.category_id ?? item.category?.id ?? null,
        subcategory_id: item.subcategory_id ?? item.subcategory?.id ?? null,
        brand: item.brand || "",
        model: item.model || "",
        serial_number: item.serial_number || "",
        purchase_date: item.purchase_date || null,
        purchase_value: item.purchase_value ?? null,
        useful_life_years: item.useful_life_years ?? null,
        status: item.status || "Activo",
        condition: item.condition || "Bueno",
        dependency_id: item.dependency_id ?? item.dependency?.id ?? null,
        responsible_user_id:
          item.responsible_user_id ?? item.responsible_user?.id ?? null,
        supplier_id: item.supplier_id ?? item.supplier?.id ?? null,
        active: Boolean(item.active),
        item_type: item.item_type || "asset",
        stock_quantity: item.stock_quantity ?? null,
        minimum_stock: item.minimum_stock ?? null,
        unit_of_measure: item.unit_of_measure || "",
        photo: null,
      };
      this.showModal = true;
    },
    buildFormData() {
      const fd = new FormData();
      const add = (key, value) => {
        if (value === undefined) return;
        if (value === null) return;
        if (value === "") return;
        fd.append(key, value);
      };

      add("name", this.form.name);
      add("description", this.form.description);
      add("category_id", this.form.category_id);
      add("subcategory_id", this.form.subcategory_id);
      add("brand", this.form.brand);
      add("model", this.form.model);
      add("serial_number", this.form.serial_number);
      add("purchase_date", this.form.purchase_date);
      add("purchase_value", this.form.purchase_value);
      add("useful_life_years", this.form.useful_life_years);
      add("status", this.form.status);
      add("condition", this.form.condition);
      add("dependency_id", this.form.dependency_id);
      add("responsible_user_id", this.form.responsible_user_id);
      add("supplier_id", this.form.supplier_id);
      add("active", this.form.active ? 1 : 0);
      add("item_type", this.form.item_type);
      add("stock_quantity", this.form.stock_quantity);
      add("minimum_stock", this.form.minimum_stock);
      add("unit_of_measure", this.form.unit_of_measure);

      if (this.form.photo) {
        fd.append("photo", this.form.photo);
      }

      return fd;
    },
    async save() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        if (!this.validateForm()) return;
        const fd = this.buildFormData();
        if (this.isEditing) {
          fd.append("_method", "PUT");
          await axios.post(`/api/inventory/items/${this.form.id}`, fd);
          this.success = "Bien actualizado.";
        } else {
          await axios.post("/api/inventory/items", fd);
          this.success = "Bien creado.";
        }
        this.showModal = false;
        await this.loadItems(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      if (!confirm(`Eliminar bien ${item.code} (${item.name})?`)) return;
      await axios.delete(`/api/inventory/items/${item.id}`);
      this.loadItems(this.pagination.current_page);
    },
    async exportCsv() {
      this.exporting = true;
      this.error = null;
      try {
        const all = [];
        let page = 1;
        let lastPage = 1;
        do {
          const response = await axios.get("/api/inventory/items", {
            params: {
              page,
              per_page: 200,
              search: this.search,
              ...this.filters,
              low_stock: this.filters.low_stock ? 1 : "",
            },
          });
          all.push(...(response.data.data || []));
          lastPage = response.data.last_page || 1;
          page += 1;
        } while (page <= lastPage);

        const headers = [
          "Código",
          "Nombre",
          "Categoría",
          "Subcategoría",
          "Dependencia",
          "Responsable",
          "Tipo",
          "Estado",
          "Condición",
          "Stock",
          "Unidad stock",
          "Proveedor",
          "Marca",
          "Modelo",
          "Serie",
          "Fecha compra",
          "Valor compra",
          "Activo",
        ];

        const escape = (value) => {
          const str = String(value ?? "");
          const needsQuotes = /[",\n\r;]/.test(str);
          const normalized = str.replace(/"/g, '""');
          return needsQuotes ? `"${normalized}"` : normalized;
        };

        const lines = [headers.join(";")];

        for (const it of all) {
          lines.push(
            [
              it.code,
              it.name,
              it.category?.name || "",
              it.subcategory?.name || "",
              it.dependency ? `${it.dependency.code} - ${it.dependency.name}` : "",
              it.responsible_user?.name || "",
              it.item_type,
              it.status,
              it.condition,
              it.item_type === "consumable" ? Number(it.stock_quantity || 0) : "",
              it.item_type === "consumable" ? it.unit_of_measure || "" : "",
              it.supplier?.name || "",
              it.brand || "",
              it.model || "",
              it.serial_number || "",
              it.purchase_date || "",
              it.purchase_value ?? "",
              it.active ? "Sí" : "No",
            ]
              .map(escape)
              .join(";")
          );
        }

        const csv = "\uFEFF" + lines.join("\n");
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `inventario_${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.exporting = false;
      }
    },
    onPhotoSelected(e) {
      const file = e?.target?.files?.[0];
      this.form.photo = file || null;
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
      <h4 class="mb-0">Inventario · Bienes</h4>
      <div class="d-flex gap-2">
        <BButton
          v-if="canExport"
          variant="outline-secondary"
          :disabled="exporting"
          @click="exportCsv"
        >
          {{ exporting ? "Exportando..." : "Exportar CSV" }}
        </BButton>
        <BButton variant="primary" @click="openCreate">Nuevo bien</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <div class="row mb-3 g-2">
      <div class="col-md-3">
        <BFormInput
          v-model="search"
          placeholder="Buscar por código, nombre, serie..."
          @keyup.enter="loadItems(1)"
        />
      </div>
      <div class="col-md-2">
        <Multiselect v-model="filters.item_type" :options="itemTypeOptions" />
      </div>
      <div class="col-md-2">
        <Multiselect v-model="filters.status" :options="statusOptions" />
      </div>
      <div class="col-md-2">
        <Multiselect v-model="filters.condition" :options="conditionOptions" />
      </div>
      <div class="col-md-2 d-flex align-items-center">
        <BFormCheckbox v-model="filters.low_stock">Stock bajo</BFormCheckbox>
      </div>
      <div class="col-md-1">
        <BButton variant="secondary" class="w-100" @click="loadItems(1)"
          >Buscar</BButton
        >
      </div>
    </div>

    <div class="table-responsive">
      <BTable
        :items="items"
        :busy="loading"
        :fields="[
          { key: 'code', label: 'Código' },
          { key: 'name', label: 'Nombre' },
          { key: 'category', label: 'Categoría' },
          { key: 'dependency', label: 'Dependencia' },
          { key: 'responsible', label: 'Responsable' },
          { key: 'item_type', label: 'Tipo' },
          { key: 'status', label: 'Estado' },
          { key: 'condition', label: 'Condición' },
          { key: 'stock', label: 'Stock' },
          { key: 'actions', label: 'Acciones' },
        ]"
        small
      >
        <template #cell(category)="{ item }">
          {{ item.category?.name || "-" }}
        </template>
        <template #cell(dependency)="{ item }">
          {{ item.dependency ? `${item.dependency.code} - ${item.dependency.name}` : "-" }}
        </template>
        <template #cell(responsible)="{ item }">
          {{ item.responsible_user?.name || "-" }}
        </template>
        <template #cell(stock)="{ item }">
          <span v-if="item.item_type !== 'consumable'">-</span>
          <span v-else>{{ item.stock_quantity ?? 0 }} {{ item.unit_of_measure || "" }}</span>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <router-link class="btn btn-sm btn-outline-secondary" :to="`/inventory/items/${item.id}`">
              Ver
            </router-link>
            <BButton size="sm" variant="info" @click="openEdit(item)"
              >Editar</BButton
            >
            <BButton size="sm" variant="danger" @click="remove(item)"
              >Eliminar</BButton
            >
          </div>
        </template>
      </BTable>
    </div>

    <div class="d-flex justify-content-end">
      <BPagination
        v-model="pagination.current_page"
        :per-page="15"
        :total-rows="pagination.total"
        @update:model-value="loadItems"
      />
    </div>

    <BModal
      v-model="showModal"
      :title="isEditing ? 'Editar bien' : 'Nuevo bien'"
      size="xl"
      hide-footer
      scrollable
      body-class="p-4"
    >
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label mb-1">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Tipo</label>
          <Multiselect
            v-model="form.item_type"
            :options="catalogs.item_types.map((t) => ({ value: t, label: t }))"
            :append-to-body="false"
            :close-on-select="true"
          />
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Categoría</label>
          <Multiselect v-model="form.category_id" :options="categoryOptions" :append-to-body="false" :close-on-select="true" />
        </div>
        <div class="col-md-6">
          <label class="form-label mb-1">Subcategoría</label>
          <Multiselect v-model="form.subcategory_id" :options="subcategoryOptions" :append-to-body="false" :close-on-select="true" />
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Estado</label>
          <Multiselect
            v-model="form.status"
            :options="catalogs.statuses.map((s) => ({ value: s, label: s }))"
            :append-to-body="false"
            :close-on-select="true"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Condición</label>
          <Multiselect
            v-model="form.condition"
            :options="catalogs.conditions.map((s) => ({ value: s, label: s }))"
            :append-to-body="false"
            :close-on-select="true"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Dependencia</label>
          <Multiselect v-model="form.dependency_id" :options="dependencyOptions" :append-to-body="false" :close-on-select="true" />
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Responsable</label>
          <Multiselect v-model="form.responsible_user_id" :options="userOptions" :append-to-body="false" :close-on-select="true" />
        </div>
        <div class="col-md-6">
          <label class="form-label mb-1">Proveedor</label>
          <Multiselect v-model="form.supplier_id" :options="supplierOptions" :append-to-body="false" :close-on-select="true" />
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Marca</label>
          <BFormInput v-model="form.brand" />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Modelo</label>
          <BFormInput v-model="form.model" />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">N° Serie</label>
          <BFormInput v-model="form.serial_number" />
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Fecha compra</label>
          <BFormInput v-model="form.purchase_date" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Valor compra</label>
          <BFormInput v-model="form.purchase_value" type="number" min="0" />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Vida útil (años)</label>
          <BFormInput v-model="form.useful_life_years" type="number" min="0" />
        </div>

        <div class="col-12">
          <label class="form-label mb-1">Descripción</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>

        <template v-if="form.item_type === 'consumable'">
          <div class="col-md-4">
            <label class="form-label mb-1">Stock</label>
            <BFormInput v-model="form.stock_quantity" type="number" min="0" />
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Stock mínimo</label>
            <BFormInput v-model="form.minimum_stock" type="number" min="0" />
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Unidad</label>
            <BFormInput v-model="form.unit_of_measure" placeholder="ej: un, caja, resma" />
          </div>
        </template>

        <div class="col-12">
          <label class="form-label mb-1">Foto principal</label>
          <input
            class="form-control"
            type="file"
            accept="image/*"
            capture="environment"
            @change="onPhotoSelected"
          />
          <small class="text-muted"
            >En móviles permite tomar foto con la cámara.</small
          >
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

<style scoped>
:deep(.multiselect-dropdown) {
  z-index: 3000;
}

:deep(.multiselect) {
  max-width: 100%;
}
</style>
