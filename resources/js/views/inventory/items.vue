<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
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
  has_warranty: false,
  warranty_months: null,
  warranty_expires_at: null,
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
  components: { Layout, LoadingState, Multiselect },
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
      showOccupiedDependenciesOnly: false,
      showModal: false,
      form: emptyForm(),
      templateSearch: "",
      templateLoading: false,
      templateResults: [],
      templateError: null,
      creation: {
        quantity: 1,
      },
      cameraActive: false,
      cameraStream: null,
      cameraError: null,
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
    normalizedCreateQuantity() {
      const quantity = Number(this.creation.quantity);

      return Number.isInteger(quantity) && quantity > 0 ? quantity : 1;
    },
    willCreateIndividualUnits() {
      return (
        !this.isEditing &&
        this.form.item_type === "asset" &&
        this.normalizedCreateQuantity > 1
      );
    },
    categoryOptions() {
      return [{ value: null, label: "Sin categoría" }].concat(
        this.catalogs.categories.map((c) => ({ value: c.id, label: c.name }))
      );
    },
    subcategoryOptions() {
      const categoryId = this.form.category_id || this.filters.category_id;
      const subs = (this.catalogs.subcategories || []).filter((s) =>
        categoryId ? s.category_id === categoryId : true
      );
      return [{ value: null, label: "Sin subcategoría" }].concat(
        subs.map((s) => ({
          value: s.id,
          label: categoryId || !s.category?.name ? s.name : `${s.name} · ${s.category.name}`,
        }))
      );
    },
    dependencyOptions() {
      return [{ value: null, label: "Sin dependencia" }].concat(
        this.catalogs.dependencies.map((d) => {
          const shortLabel = `${d.code || "Sin código"} - ${d.name || "Sin nombre"}`;
          const usage = String(d.usage || "").trim();
          const itemsCount = Number(d.inventory_items_count || 0);

          return {
            value: d.id,
            label: `${usage ? `${shortLabel} · Uso: ${usage}` : shortLabel} · ${this.dependencyItemsLabel(itemsCount)}`,
            shortLabel,
            usage,
            itemsCount,
          };
        })
      );
    },
    dependencyFilterOptions() {
      const dependencies = this.dependencyOptions
        .slice(1)
        .filter((dependency) => !this.showOccupiedDependenciesOnly || dependency.itemsCount > 0);

      return [
        {
          value: null,
          label: "Todas las dependencias",
          shortLabel: "Todas las dependencias",
          usage: "",
          itemsCount: null,
        },
        ...dependencies,
      ];
    },
    occupiedDependenciesCount() {
      return (this.catalogs.dependencies || []).filter(
        (dependency) => Number(dependency.inventory_items_count || 0) > 0
      ).length;
    },
    locatedInventoryItemsCount() {
      return (this.catalogs.dependencies || []).reduce(
        (total, dependency) => total + Number(dependency.inventory_items_count || 0),
        0
      );
    },
    categoryFilterOptions() {
      return [{ value: null, label: "Todas las categorías" }].concat(
        this.catalogs.categories.map((category) => ({
          value: category.id,
          label: category.name,
        }))
      );
    },
    responsibleFilterOptions() {
      return [{ value: null, label: "Todos los responsables" }].concat(
        this.userOptions.slice(1)
      );
    },
    selectedDependency() {
      return (this.catalogs.dependencies || []).find(
        (dependency) => Number(dependency.id) === Number(this.filters.dependency_id)
      ) || null;
    },
    activeFiltersCount() {
      return [
        String(this.search || "").trim(),
        this.filters.category_id,
        this.filters.dependency_id,
        this.filters.responsible_user_id,
        this.filters.item_type,
        this.filters.status,
        this.filters.condition,
        this.filters.low_stock,
      ].filter(Boolean).length;
    },
    activeFilterLabels() {
      const labels = [];
      const category = (this.catalogs.categories || []).find(
        (item) => Number(item.id) === Number(this.filters.category_id)
      );
      const responsible = (this.catalogs.users || []).find(
        (item) => Number(item.id) === Number(this.filters.responsible_user_id)
      );

      if (String(this.search || "").trim()) labels.push(`Búsqueda: ${String(this.search).trim()}`);
      if (this.selectedDependency) labels.push(`Ubicación: ${this.selectedDependency.code} - ${this.selectedDependency.name}`);
      if (category) labels.push(`Categoría: ${category.name}`);
      if (responsible) labels.push(`Responsable: ${responsible.name}`);
      if (this.filters.item_type) labels.push(`Tipo: ${this.typeLabel(this.filters.item_type)}`);
      if (this.filters.status) labels.push(`Estado: ${this.filters.status}`);
      if (this.filters.condition) labels.push(`Condición: ${this.filters.condition}`);
      if (this.filters.low_stock) labels.push("Stock bajo");

      return labels;
    },
    userOptions() {
      const users = this.catalogs.users || [];
      const hasStaffMarkers = users.some(
        (u) =>
          Object.prototype.hasOwnProperty.call(u, "user_type") ||
          Object.prototype.hasOwnProperty.call(u, "staff_id")
      );
      const staffUsers = hasStaffMarkers
        ? users.filter((u) => u.user_type === "staff" || u.staff_id)
        : users;

      return [{ value: null, label: "Sin responsable" }].concat(
        staffUsers.map((u) => ({
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
    itemTypeCatalogOptions() {
      return [{ value: null, label: "Sin tipo" }].concat(
        (this.catalogs.item_types || []).map((t) => ({
          value: t,
          label: this.typeLabel(t),
        }))
      );
    },
    itemTypeOptions() {
      return [{ value: "", label: "Todos" }].concat(
        (this.catalogs.item_types || []).map((t) => ({
          value: t,
          label: this.typeLabel(t),
        }))
      );
    },
    statusFormOptions() {
      return [{ value: null, label: "Sin estado" }].concat(
        (this.catalogs.statuses || []).map((s) => ({ value: s, label: s }))
      );
    },
    conditionFormOptions() {
      return [{ value: null, label: "Sin condición" }].concat(
        (this.catalogs.conditions || []).map((s) => ({ value: s, label: s }))
      );
    },
    itemFields() {
      const head = "inventory-table__head";
      const cell = "align-middle inventory-table__cell";

      return [
        {
          key: "summary",
          label: "Bien",
          thClass: `${head} inventory-col-summary`,
          tdClass: `${cell} inventory-col-summary`,
        },
        {
          key: "classification",
          label: "Clasificación",
          thClass: `${head} inventory-col-classification`,
          tdClass: `${cell} inventory-col-classification`,
        },
        {
          key: "location",
          label: "Ubicación y responsable",
          thClass: `${head} inventory-col-location`,
          tdClass: `${cell} inventory-col-location`,
        },
        {
          key: "operational",
          label: "Estado operativo",
          thClass: `${head} inventory-col-operational`,
          tdClass: `${cell} inventory-col-operational`,
        },
        {
          key: "stock",
          label: "Stock",
          thClass: `${head} inventory-col-stock`,
          tdClass: `${cell} inventory-col-stock`,
        },
        {
          key: "actions",
          label: "Acciones",
          thClass: `${head} inventory-col-actions`,
          tdClass: `${cell} inventory-col-actions`,
        },
      ];
    },
    warrantyExpirationDate() {
      if (!this.form.has_warranty) return null;

      return (
        this.calculateWarrantyExpiration(
          this.form.purchase_date,
          this.form.warranty_months
        ) ||
        this.normalizeDateInput(this.form.warranty_expires_at) ||
        null
      );
    },
    warrantyExpirationLabel() {
      if (!this.form.has_warranty) return "Sin garantía";
      if (!this.warrantyExpirationDate) return "Pendiente";

      return this.formatDateForDisplay(this.warrantyExpirationDate);
    },
  },
  watch: {
    "form.category_id"(newValue, oldValue) {
      if (newValue !== oldValue) {
        this.form.subcategory_id = null;
      }
    },
    "form.has_warranty"(enabled) {
      if (!enabled) {
        this.form.warranty_months = null;
        this.form.warranty_expires_at = null;
      }
    },
    "form.item_type"(type) {
      if (type === "consumable") {
        this.creation.quantity = 1;
      }
    },
    showOccupiedDependenciesOnly(enabled) {
      if (
        enabled &&
        this.selectedDependency &&
        Number(this.selectedDependency.inventory_items_count || 0) === 0
      ) {
        this.filters.dependency_id = null;
      }
    },
    showModal(isOpen) {
      if (!isOpen) {
        this.stopCamera();
        this.resetCreationTools();
      }
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadItems();
  },
  beforeUnmount() {
    this.stopCamera();
  },
  methods: {
    validateForm() {
      if (!this.form.name || !String(this.form.name).trim()) {
        Swal.fire({
          icon: "warning",
          title: "Falta el nombre del bien",
          text: "Ingresa el nombre del bien antes de guardar.",
          confirmButtonText: "OK",
        });

        return false;
      }

      if (!this.isEditing && this.form.item_type === "asset") {
        const quantity = Number(this.creation.quantity);

        if (!Number.isInteger(quantity) || quantity < 1 || quantity > 100) {
          Swal.fire({
            icon: "warning",
            title: "Cantidad de unidades inválida",
            text: "Ingresa un número entero entre 1 y 100.",
            confirmButtonText: "OK",
          });

          return false;
        }
      }

      return true;
    },
    generateFallbackSerialNumber() {
      const now = new Date();
      const date = [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, "0"),
        String(now.getDate()).padStart(2, "0"),
      ].join("");
      const time = [
        String(now.getHours()).padStart(2, "0"),
        String(now.getMinutes()).padStart(2, "0"),
        String(now.getSeconds()).padStart(2, "0"),
      ].join("");
      const suffix = Math.floor(Math.random() * 36 ** 4)
        .toString(36)
        .padStart(4, "0")
        .toUpperCase();

      this.form.serial_number = `SS-${date}-${time}-${suffix}`;
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
    resetFilters() {
      this.search = "";
      this.showOccupiedDependenciesOnly = false;
      this.filters = {
        category_id: null,
        subcategory_id: null,
        dependency_id: null,
        responsible_user_id: null,
        supplier_id: null,
        status: "",
        condition: "",
        item_type: "",
        low_stock: false,
      };
      this.loadItems(1);
    },
    dependencyLocationDetail(dependency = this.selectedDependency) {
      if (!dependency) return "";

      return [dependency.distribution, dependency.sector, dependency.zone, dependency.usage]
        .map((value) => String(value || "").trim())
        .filter((value, index, values) => value && values.indexOf(value) === index)
        .join(" · ") || "Sin detalle adicional de ubicación";
    },
    dependencyItemsLabel(count) {
      const safeCount = Number(count || 0);
      return `${safeCount} ${safeCount === 1 ? "bien" : "bienes"}`;
    },
    itemTechnicalDetail(item) {
      return [item.brand, item.model, item.serial_number ? `Serie ${item.serial_number}` : ""]
        .map((value) => String(value || "").trim())
        .filter(Boolean)
        .join(" · ");
    },
    resetCreationTools() {
      this.templateSearch = "";
      this.templateLoading = false;
      this.templateResults = [];
      this.templateError = null;
      this.creation = { quantity: 1 };
    },
    openCreate() {
      this.form = emptyForm();
      this.resetCreationTools();
      this.showModal = true;
    },
    openEdit(item) {
      this.resetCreationTools();
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
        has_warranty: Boolean(item.has_warranty),
        warranty_months: item.warranty_months ?? null,
        warranty_expires_at: item.warranty_expires_at || null,
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
    async searchSimilarItems() {
      const search = String(this.templateSearch || "").trim();
      this.templateError = null;

      if (search.length < 2) {
        this.templateResults = [];
        return;
      }

      this.templateLoading = true;
      try {
        const response = await axios.get("/api/inventory/items/similar", {
          params: { search },
        });
        this.templateResults = response.data.data || [];
      } catch (error) {
        this.templateResults = [];
        this.templateError = this.formatError(error);
      } finally {
        this.templateLoading = false;
      }
    },
    applySimilarItem(item) {
      const subcategoryId = item.subcategory_id ?? item.subcategory?.id ?? null;

      this.form = {
        ...this.form,
        name: item.name || "",
        description: item.description || "",
        category_id: item.category_id ?? item.category?.id ?? null,
        subcategory_id: null,
        brand: item.brand || "",
        model: item.model || "",
        serial_number: "",
        purchase_value: item.purchase_value ?? null,
        useful_life_years: item.useful_life_years ?? null,
        has_warranty: Boolean(item.has_warranty),
        warranty_months: item.warranty_months ?? null,
        warranty_expires_at: null,
        status: item.status || "Activo",
        condition: item.condition || "Bueno",
        supplier_id: item.supplier_id ?? item.supplier?.id ?? null,
        item_type: item.item_type || "asset",
        stock_quantity:
          item.item_type === "consumable" ? item.stock_quantity ?? null : null,
        minimum_stock: item.minimum_stock ?? null,
        unit_of_measure: item.unit_of_measure || "",
        photo: null,
      };

      this.$nextTick(() => {
        this.form.subcategory_id = subcategoryId;
      });

      this.templateSearch = item.name || "";
      this.templateResults = [];
    },
    similarItemMeta(item) {
      return [
        item.code,
        item.brand,
        item.model,
        item.category?.name,
      ]
        .filter(Boolean)
        .join(" · ");
    },
    similarItemLocation(item) {
      if (!item.dependency) return "Sin dependencia";

      return `${item.dependency.code} - ${item.dependency.name}`;
    },
    buildFormData() {
      const fd = new FormData();
      const add = (key, value) => {
        if (value === undefined) return;
        if (value === null) return;
        if (value === "") return;
        fd.append(key, value);
      };
      const addNullable = (key, value) => {
        fd.append(key, value === undefined || value === null ? "" : value);
      };

      addNullable("name", this.form.name);
      addNullable("description", this.form.description);
      addNullable("category_id", this.form.category_id);
      addNullable("subcategory_id", this.form.subcategory_id);
      addNullable("brand", this.form.brand);
      addNullable("model", this.form.model);
      addNullable("serial_number", this.form.serial_number);
      addNullable("purchase_date", this.form.purchase_date);
      addNullable("purchase_value", this.form.purchase_value);
      addNullable("useful_life_years", this.form.useful_life_years);
      add("has_warranty", this.form.has_warranty ? 1 : 0);
      addNullable(
        "warranty_months",
        this.form.has_warranty ? this.form.warranty_months : null
      );
      addNullable(
        "warranty_expires_at",
        this.form.has_warranty ? this.warrantyExpirationDate : null
      );
      addNullable("status", this.form.status);
      addNullable("condition", this.form.condition);
      addNullable("dependency_id", this.form.dependency_id);
      addNullable("responsible_user_id", this.form.responsible_user_id);
      addNullable("supplier_id", this.form.supplier_id);
      add("active", this.form.active ? 1 : 0);
      addNullable("item_type", this.form.item_type);
      addNullable("stock_quantity", this.form.stock_quantity);
      addNullable("minimum_stock", this.form.minimum_stock);
      addNullable("unit_of_measure", this.form.unit_of_measure);

      if (!this.isEditing) {
        add(
          "create_quantity",
          this.form.item_type === "asset" ? this.normalizedCreateQuantity : 1
        );
        add("create_mode", this.form.item_type === "asset" ? "units" : "single");
      }

      if (this.form.photo) {
        fd.append("photo", this.form.photo);
      }

      return fd;
    },
    async save() {
      this.error = null;
      this.success = null;
      try {
        if (!this.validateForm()) return;

        if (!this.isEditing) {
          const createQuantity = this.normalizedCreateQuantity;
          const confirmation = await Swal.fire({
            icon: "question",
            title: this.willCreateIndividualUnits
              ? `Crear ${createQuantity} bienes`
              : "Confirmar creación",
            text: this.willCreateIndividualUnits
              ? "Se generará una ficha individual y un código para cada unidad."
              : "Se creará un nuevo bien de inventario con los datos ingresados.",
            showCancelButton: true,
            confirmButtonText: this.willCreateIndividualUnits
              ? "Crear bienes"
              : "Crear bien",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#556ee6",
          });

          if (!confirmation.isConfirmed) return;
        }

        this.saving = true;
        const fd = this.buildFormData();
        if (this.isEditing) {
          fd.append("_method", "PUT");
          await axios.post(`/api/inventory/items/${this.form.id}`, fd);
          this.success = "Bien actualizado.";
        } else {
          const response = await axios.post("/api/inventory/items", fd);
          const createdCount = Number(response.data.created_count || 1);
          this.success =
            createdCount > 1 ? `${createdCount} bienes creados.` : "Bien creado.";
        }
        this.showModal = false;
        await Promise.all([
          this.loadCatalogs(),
          this.loadItems(this.pagination.current_page),
        ]);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      const result = await Swal.fire({
        icon: "warning",
        title: "Eliminar bien",
        text: `Se eliminará ${item.code} (${item.name}).`,
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#f46a6a",
      });

      if (!result.isConfirmed) return;
      await axios.delete(`/api/inventory/items/${item.id}`);
      await Promise.all([
        this.loadCatalogs(),
        this.loadItems(this.pagination.current_page),
      ]);
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
          "Garantía",
          "Duración garantía meses",
          "Vencimiento garantía",
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
              it.stock_quantity === null || it.stock_quantity === undefined
                ? ""
                : Number(it.stock_quantity || 0),
              it.unit_of_measure || "",
              it.supplier?.name || "",
              it.brand || "",
              it.model || "",
              it.serial_number || "",
              it.purchase_date || "",
              it.purchase_value ?? "",
              it.has_warranty ? "Sí" : "No",
              it.has_warranty ? it.warranty_months ?? "" : "",
              it.has_warranty ? it.warranty_expires_at || "" : "",
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
    async onPhotoSelected(e) {
      const file = e?.target?.files?.[0];
      this.error = null;

      try {
        this.form.photo = file ? await this.normalizePhotoFile(file) : null;
        this.stopCamera();
      } catch (error) {
        this.form.photo = null;
        this.error =
          error?.message || "No se pudo preparar la imagen para subir.";
      } finally {
        if (e?.target) {
          e.target.value = "";
        }
      }
    },
    async startCamera() {
      this.cameraError = null;

      if (!navigator.mediaDevices?.getUserMedia) {
        this.cameraError = "Este navegador no permite abrir la cámara desde la página.";
        return;
      }

      this.stopCamera(false);

      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { ideal: "environment" },
          },
          audio: false,
        });

        if (!this.showModal) {
          stream.getTracks().forEach((track) => track.stop());
          return;
        }

        this.cameraStream = stream;
        this.cameraActive = true;

        await this.$nextTick();

        if (this.$refs.cameraVideo) {
          this.$refs.cameraVideo.srcObject = stream;
          await this.$refs.cameraVideo.play().catch(() => {});
        }
      } catch (error) {
        this.cameraActive = false;
        this.cameraStream = null;
        this.cameraError = "No se pudo abrir la cámara. Revisa los permisos del navegador.";
      }
    },
    stopCamera(clearError = true) {
      if (this.cameraStream) {
        this.cameraStream.getTracks().forEach((track) => track.stop());
      }

      this.cameraStream = null;
      this.cameraActive = false;

      if (this.$refs.cameraVideo) {
        this.$refs.cameraVideo.srcObject = null;
      }

      if (clearError) {
        this.cameraError = null;
      }
    },
    async capturePhoto() {
      const video = this.$refs.cameraVideo;
      const canvas = this.$refs.cameraCanvas;

      if (!video || !canvas || !video.videoWidth || !video.videoHeight) {
        this.cameraError = "La cámara aún no está lista para capturar.";
        return;
      }

      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;

      const context = canvas.getContext("2d");
      context.drawImage(video, 0, 0, canvas.width, canvas.height);

      const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, "image/jpeg", 0.9)
      );

      if (!blob) {
        this.cameraError = "No se pudo generar la foto capturada.";
        return;
      }

      const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
      const photo = new File([blob], `bien-${timestamp}.jpg`, {
        type: "image/jpeg",
        lastModified: Date.now(),
      });
      this.form.photo = await this.normalizePhotoFile(photo);
      this.stopCamera();
    },
    async normalizePhotoFile(file) {
      if (!file || !file.type?.startsWith("image/")) {
        return file;
      }

      const imageUrl = URL.createObjectURL(file);

      try {
        const image = await new Promise((resolve, reject) => {
          const img = new Image();
          img.onload = () => resolve(img);
          img.onerror = () => reject(new Error("No se pudo leer la imagen seleccionada."));
          img.src = imageUrl;
        });

        const maxDimension = 1600;
        const largestSide = Math.max(image.naturalWidth, image.naturalHeight);
        const scale = largestSide > maxDimension ? maxDimension / largestSide : 1;
        const width = Math.max(1, Math.round(image.naturalWidth * scale));
        const height = Math.max(1, Math.round(image.naturalHeight * scale));
        const canvas = document.createElement("canvas");
        canvas.width = width;
        canvas.height = height;
        canvas.getContext("2d").drawImage(image, 0, 0, width, height);

        const blob = await new Promise((resolve) =>
          canvas.toBlob(resolve, "image/jpeg", 0.82)
        );

        if (!blob) {
          throw new Error("No se pudo comprimir la imagen seleccionada.");
        }

        const baseName = String(file.name || "foto-bien")
          .replace(/\.[^.]+$/, "")
          .replace(/[^\w-]+/g, "-")
          .replace(/^-+|-+$/g, "") || "foto-bien";

        return new File([blob], `${baseName}.jpg`, {
          type: "image/jpeg",
          lastModified: Date.now(),
        });
      } finally {
        URL.revokeObjectURL(imageUrl);
      }
    },
    normalizeDateInput(value) {
      if (!value) return null;

      return String(value).slice(0, 10);
    },
    calculateWarrantyExpiration(purchaseDate, months) {
      const normalizedDate = this.normalizeDateInput(purchaseDate);
      const duration = Number(months);

      if (!normalizedDate || !Number.isFinite(duration) || duration < 1) {
        return null;
      }

      const [year, month, day] = normalizedDate.split("-").map(Number);

      if (!year || !month || !day) {
        return null;
      }

      const targetMonthIndex = month - 1 + duration;
      const lastDayOfTargetMonth = new Date(
        year,
        targetMonthIndex + 1,
        0
      ).getDate();
      const targetDate = new Date(
        year,
        targetMonthIndex,
        Math.min(day, lastDayOfTargetMonth)
      );
      const targetYear = targetDate.getFullYear();
      const targetMonth = String(targetDate.getMonth() + 1).padStart(2, "0");
      const targetDay = String(targetDate.getDate()).padStart(2, "0");

      return `${targetYear}-${targetMonth}-${targetDay}`;
    },
    formatDateForDisplay(value) {
      const normalizedDate = this.normalizeDateInput(value);

      if (!normalizedDate) return "-";

      const [year, month, day] = normalizedDate.split("-");

      return `${day}/${month}/${year}`;
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
    typeLabel(value) {
      const labels = {
        asset: "Activo fijo",
        consumable: "Consumible",
      };

      return labels[value] || value || "-";
    },
    typeClass(value) {
      return value === "consumable" ? "consumable" : "asset";
    },
    statusClass(value) {
      const status = String(value || "").toLowerCase();

      if (status.includes("baja") || status.includes("inactivo")) return "inactive";
      if (status.includes("uso") || status.includes("activo")) return "active";
      if (status.includes("pendiente") || status.includes("revisión") || status.includes("revision")) return "pending";
      if (status.includes("bodega") || status.includes("almac")) return "stored";

      return "neutral";
    },
    conditionClass(value) {
      const condition = String(value || "").toLowerCase();

      if (condition.includes("nuevo") || condition.includes("bueno")) return "good";
      if (condition.includes("regular")) return "warning";
      if (condition.includes("crítico") || condition.includes("critico") || condition.includes("malo")) return "danger";

      return "neutral";
    },
    stockText(item) {
      if (
        item.item_type !== "consumable" &&
        (item.stock_quantity === null ||
          item.stock_quantity === undefined ||
          item.stock_quantity === "")
      ) {
        return "-";
      }

      const quantity = Number(item.stock_quantity ?? 0);
      const safeQuantity = Number.isFinite(quantity) ? quantity : 0;
      const displayValue = Number.isInteger(safeQuantity) ? safeQuantity : safeQuantity.toFixed(2);
      return `${displayValue} ${item.unit_of_measure || ""}`.trim();
    },
  },
};
</script>

<template>
  <Layout>
    <section class="inventory-page-hero">
      <div class="inventory-page-heading">
        <span class="inventory-page-eyebrow">Inventario institucional</span>
        <h4 class="inventory-page-title">Bienes y ubicaciones</h4>
        <p>Consulta el inventario por dependencia, responsable y condición operativa.</p>
      </div>
      <div class="inventory-page-summary">
        <div class="inventory-page-metric">
          <i class="mdi mdi-package-variant-closed"></i>
          <span><strong>{{ pagination.total }}</strong> bienes encontrados</span>
        </div>
        <div v-if="selectedDependency" class="inventory-page-metric inventory-page-metric--location">
          <i class="mdi mdi-map-marker-outline"></i>
          <span>{{ selectedDependency.code }} - {{ selectedDependency.name }}</span>
        </div>
      </div>
      <div class="inventory-page-actions">
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
    </section>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <section class="inventory-filter-panel">
      <div class="inventory-filter-panel__head">
        <div>
          <span class="inventory-page-eyebrow">Filtros</span>
          <h5>Localiza bienes con precisión</h5>
          <p>La dependencia corresponde a la ubicación actual registrada para el bien.</p>
        </div>
        <span class="inventory-filter-count" :class="{ 'is-active': activeFiltersCount > 0 }">
          {{ activeFiltersCount }} filtros activos
        </span>
      </div>

      <div class="inventory-filters row g-3 align-items-end">
        <div class="col-12 col-lg-4 inventory-filter-field">
          <label class="form-label inventory-filter-label mb-1">Búsqueda</label>
          <BFormInput
            v-model="search"
            class="inventory-filter-control inventory-filter-search"
            placeholder="Código, nombre, marca, modelo o serie"
            @keyup.enter="loadItems(1)"
          />
        </div>
        <div class="col-12 col-lg-5 inventory-filter-field inventory-filter-field--location">
          <div class="inventory-dependency-filter-head">
            <label class="form-label inventory-filter-label mb-0">
              <i class="mdi mdi-map-marker-outline"></i> Dependencia / ubicación
            </label>
            <button
              type="button"
              class="inventory-occupied-toggle"
              :class="{ 'is-active': showOccupiedDependenciesOnly }"
              :aria-pressed="showOccupiedDependenciesOnly"
              @click="showOccupiedDependenciesOnly = !showOccupiedDependenciesOnly"
            >
              <i class="mdi mdi-package-variant-closed-check"></i>
              Solo con bienes
              <span>{{ occupiedDependenciesCount }}</span>
            </button>
          </div>
          <Multiselect
            v-model="filters.dependency_id"
            class="inventory-filter-control inventory-filter-select"
            :options="dependencyFilterOptions"
            :searchable="true"
            :close-on-select="true"
            :append-to-body="true"
            placeholder="Buscar por código, nombre o uso"
          >
            <template #singlelabel="{ value }">
              <div class="multiselect-single-label">
                <span class="multiselect-single-label-text">{{ value.shortLabel || value.label }}</span>
                <span v-if="value.itemsCount !== null" class="inventory-dependency-count inventory-dependency-count--selected">
                  <i class="mdi mdi-package-variant-closed"></i>{{ value.itemsCount }}
                </span>
              </div>
            </template>
            <template #option="{ option }">
              <div class="inventory-dependency-option">
                <span class="inventory-dependency-option__content">
                  <span class="inventory-dependency-option__main">{{ option.shortLabel || option.label }}</span>
                  <span v-if="option.usage" class="inventory-dependency-option__usage">Uso: {{ option.usage }}</span>
                </span>
                <span v-if="option.itemsCount !== null" class="inventory-dependency-count">
                  <i class="mdi mdi-package-variant-closed"></i>{{ option.itemsCount }}
                </span>
              </div>
            </template>
          </Multiselect>
          <small class="inventory-dependency-filter-summary">
            {{ occupiedDependenciesCount }} de {{ catalogs.dependencies.length }} dependencias contienen
            {{ dependencyItemsLabel(locatedInventoryItemsCount) }}.
          </small>
        </div>
        <div class="col-12 col-md-6 col-lg-3 inventory-filter-field">
          <label class="form-label inventory-filter-label mb-1">Categoría</label>
          <Multiselect
            v-model="filters.category_id"
            class="inventory-filter-control inventory-filter-select"
            :options="categoryFilterOptions"
            :searchable="true"
            :close-on-select="true"
            :append-to-body="true"
          />
        </div>
        <div class="col-12 col-md-6 col-xl-3 inventory-filter-field">
          <label class="form-label inventory-filter-label mb-1">Responsable</label>
          <Multiselect
            v-model="filters.responsible_user_id"
            class="inventory-filter-control inventory-filter-select"
            :options="responsibleFilterOptions"
            :searchable="true"
            :close-on-select="true"
            :append-to-body="true"
          />
        </div>
        <div class="col-12 col-md-4 col-xl-2 inventory-filter-field">
          <label class="form-label inventory-filter-label mb-1">Tipo</label>
          <Multiselect
            v-model="filters.item_type"
            class="inventory-filter-control inventory-filter-select"
            :options="itemTypeOptions"
            :searchable="false"
            :close-on-select="true"
            :append-to-body="true"
          />
        </div>
        <div class="col-12 col-md-4 col-xl-2 inventory-filter-field">
          <label class="form-label inventory-filter-label mb-1">Estado</label>
          <Multiselect
            v-model="filters.status"
            class="inventory-filter-control inventory-filter-select"
            :options="statusOptions"
            :searchable="false"
            :close-on-select="true"
            :append-to-body="true"
          />
        </div>
        <div class="col-12 col-md-4 col-xl-2 inventory-filter-field">
          <label class="form-label inventory-filter-label mb-1">Condición</label>
          <Multiselect
            v-model="filters.condition"
            class="inventory-filter-control inventory-filter-select"
            :options="conditionOptions"
            :searchable="false"
            :close-on-select="true"
            :append-to-body="true"
          />
        </div>
        <div class="col-6 col-md-4 col-xl-1 inventory-filter-field inventory-filter-field--checkbox">
          <label class="form-label inventory-filter-label mb-1">Stock</label>
          <div class="inventory-low-stock">
            <BFormCheckbox v-model="filters.low_stock">Bajo</BFormCheckbox>
          </div>
        </div>
        <div class="col-12 col-md-8 col-xl-2 inventory-filter-action">
          <div class="inventory-filter-buttons">
            <BButton variant="primary" class="inventory-search-button" :disabled="loading" @click="loadItems(1)">
              <i class="mdi mdi-filter-outline"></i> Aplicar
            </BButton>
            <BButton variant="outline-secondary" class="inventory-search-button" :disabled="loading || !activeFiltersCount" @click="resetFilters">
              Limpiar
            </BButton>
          </div>
        </div>
      </div>

      <div v-if="activeFilterLabels.length" class="inventory-filter-chips">
        <span v-for="label in activeFilterLabels" :key="label">{{ label }}</span>
      </div>

      <div v-if="selectedDependency" class="inventory-selected-location">
        <i class="mdi mdi-office-building-marker-outline"></i>
        <div>
          <span>Mostrando bienes ubicados en</span>
          <strong>{{ selectedDependency.code }} - {{ selectedDependency.name }}</strong>
          <small>{{ dependencyLocationDetail() }}</small>
        </div>
        <span class="inventory-selected-location__count">
          <i class="mdi mdi-package-variant-closed"></i>
          {{ dependencyItemsLabel(selectedDependency.inventory_items_count) }}
        </span>
      </div>
    </section>

    <div class="inventory-table-card">
      <div class="inventory-results-head">
        <div>
          <span>Resultados</span>
          <strong>{{ pagination.total }} bienes</strong>
        </div>
        <div class="inventory-results-meta">
          <span><i class="mdi mdi-map-marker-multiple-outline"></i> {{ occupiedDependenciesCount }} ubicaciones con bienes</span>
          <small>Página {{ pagination.current_page }} de {{ pagination.last_page }}</small>
        </div>
      </div>
      <div class="inventory-table-scroll">
        <BTable
          class="inventory-items-table"
          :items="items"
          :busy="loading"
          :fields="itemFields"
          show-empty
          empty-text="No se encontraron bienes para los filtros seleccionados."
        >
          <template #table-busy>
            <LoadingState message="Cargando inventario..." compact />
          </template>
          <template #cell(summary)="{ item }">
            <div class="inventory-item-summary">
              <span class="inventory-code-pill"><i class="mdi mdi-barcode-scan"></i>{{ item.code || "-" }}</span>
              <div class="inventory-name-cell">
                <strong>{{ item.name || "-" }}</strong>
                <small v-if="itemTechnicalDetail(item)">{{ itemTechnicalDetail(item) }}</small>
              </div>
            </div>
          </template>
          <template #cell(classification)="{ item }">
            <div class="inventory-classification-cell">
              <strong>{{ item.category?.name || "Sin categoría" }}</strong>
              <small v-if="item.subcategory?.name">{{ item.subcategory.name }}</small>
              <span
                class="inventory-chip"
                :class="`inventory-chip--type-${typeClass(item.item_type)}`"
              >
                {{ typeLabel(item.item_type) }}
              </span>
            </div>
          </template>
          <template #cell(location)="{ item }">
            <div class="inventory-location-stack">
              <div v-if="item.dependency" class="inventory-location-cell">
                <i class="mdi mdi-map-marker-outline"></i>
                <span>{{ item.dependency.code }} - {{ item.dependency.name }}</span>
                <small>{{ dependencyLocationDetail(item.dependency) }}</small>
              </div>
              <span v-else class="inventory-location-cell inventory-location-cell--empty">
                <i class="mdi mdi-map-marker-off-outline"></i> Sin dependencia
              </span>
              <span class="inventory-responsible-row" :class="{ 'is-empty': !item.responsible_user }">
                <i class="mdi mdi-account-outline"></i>
                {{ item.responsible_user?.name || "Sin responsable" }}
              </span>
            </div>
          </template>
          <template #cell(operational)="{ item }">
            <div class="inventory-operational-cell">
              <span class="inventory-operational-label">Estado</span>
              <span
                class="inventory-chip"
                :class="`inventory-chip--status-${statusClass(item.status)}`"
              >
                {{ item.status || "-" }}
              </span>
              <span class="inventory-operational-label">Condición</span>
              <span
                class="inventory-chip"
                :class="`inventory-chip--condition-${conditionClass(item.condition)}`"
              >
                {{ item.condition || "-" }}
              </span>
            </div>
          </template>
          <template #cell(stock)="{ item }">
            <span
              class="inventory-stock-pill"
              :class="{ 'inventory-stock-pill--empty': item.item_type !== 'consumable' }"
            >
              {{ stockText(item) }}
            </span>
          </template>
          <template #cell(actions)="{ item }">
            <div class="inventory-actions">
              <router-link
                class="btn inventory-action-button inventory-action-button--view"
                :to="`/inventory/items/${item.id}`"
                title="Ver detalle"
                :aria-label="`Ver ${item.name || item.code}`"
              >
                <i class="mdi mdi-eye-outline"></i>
                <span class="visually-hidden">Ver detalle</span>
              </router-link>
              <BButton class="inventory-action-button inventory-action-button--edit" title="Editar bien" :aria-label="`Editar ${item.name || item.code}`" @click="openEdit(item)">
                <i class="mdi mdi-pencil-outline"></i>
                <span class="visually-hidden">Editar</span>
              </BButton>
              <BButton class="inventory-action-button inventory-action-button--delete" title="Eliminar bien" :aria-label="`Eliminar ${item.name || item.code}`" @click="remove(item)">
                <i class="mdi mdi-delete-outline"></i>
                <span class="visually-hidden">Eliminar</span>
              </BButton>
            </div>
          </template>
        </BTable>
      </div>
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

      <div v-if="!isEditing" class="inventory-template-panel mb-3">
        <div class="inventory-template-grid row g-2 align-items-start">
          <div class="col-12 col-lg-9 inventory-template-search">
            <label class="form-label mb-1">Buscar bien similar</label>
            <div class="input-group">
              <BFormInput
                v-model="templateSearch"
                placeholder="Nombre, marca, modelo o código"
                @keyup.enter="searchSimilarItems"
              />
              <BButton
                type="button"
                variant="outline-primary"
                :disabled="templateLoading"
                @click="searchSimilarItems"
              >
                {{ templateLoading ? "Buscando..." : "Buscar" }}
              </BButton>
            </div>

            <BAlert v-if="templateError" variant="warning" show class="mt-2 mb-0">
              {{ templateError }}
            </BAlert>

            <div v-if="templateResults.length" class="inventory-template-results">
              <button
                v-for="item in templateResults"
                :key="item.id"
                type="button"
                class="btn btn-light border inventory-template-result w-100 text-start d-flex align-items-center justify-content-between gap-2"
                @click="applySimilarItem(item)"
              >
                <span class="inventory-template-result__content d-flex flex-column gap-1 min-w-0">
                  <span class="inventory-template-result__main d-block">
                    {{ item.name || "Sin nombre" }}
                  </span>
                  <span class="inventory-template-result__meta d-block">
                    {{ similarItemMeta(item) || "Sin datos técnicos" }}
                  </span>
                  <span class="inventory-template-result__location d-block">
                    {{ similarItemLocation(item) }}
                  </span>
                </span>
                <span class="inventory-template-result__action badge rounded-pill text-bg-primary flex-shrink-0">
                  Usar base
                </span>
              </button>
            </div>
          </div>
          <div v-if="form.item_type === 'asset'" class="col-12 col-lg-3 inventory-template-quantity">
            <label class="form-label mb-1">Unidades a crear</label>
            <BFormInput
              v-model="creation.quantity"
              type="number"
              min="1"
              max="100"
              step="1"
            />
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label mb-1">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Tipo</label>
          <Multiselect
            v-model="form.item_type"
            :options="itemTypeCatalogOptions"
            :append-to-body="false"
            :close-on-select="true"
            :searchable="false"
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
            :options="statusFormOptions"
            :append-to-body="false"
            :close-on-select="true"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Condición</label>
          <Multiselect
            v-model="form.condition"
            :options="conditionFormOptions"
            :append-to-body="false"
            :close-on-select="true"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Dependencia</label>
          <Multiselect
            v-model="form.dependency_id"
            :options="dependencyOptions"
            :append-to-body="false"
            :close-on-select="true"
            :searchable="true"
            placeholder="Escribe código, nombre o uso"
          >
            <template #singlelabel="{ value }">
              <div class="multiselect-single-label">
                <span class="multiselect-single-label-text">
                  {{ value.shortLabel || value.label }}
                </span>
                <span v-if="value.itemsCount !== null" class="inventory-dependency-count inventory-dependency-count--selected">
                  <i class="mdi mdi-package-variant-closed"></i>{{ value.itemsCount }}
                </span>
              </div>
            </template>
            <template #option="{ option }">
              <div class="inventory-dependency-option">
                <span class="inventory-dependency-option__content">
                  <span class="inventory-dependency-option__main">
                    {{ option.shortLabel || option.label }}
                  </span>
                  <span v-if="option.usage" class="inventory-dependency-option__usage">
                    Uso: {{ option.usage }}
                  </span>
                </span>
                <span v-if="option.itemsCount !== null" class="inventory-dependency-count">
                  <i class="mdi mdi-package-variant-closed"></i>{{ option.itemsCount }}
                </span>
              </div>
            </template>
          </Multiselect>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Responsable</label>
          <Multiselect
            v-model="form.responsible_user_id"
            :options="userOptions"
            :append-to-body="false"
            :close-on-select="true"
            :searchable="true"
            placeholder="Buscar funcionario"
          />
        </div>
        <div class="col-md-6">
          <label class="form-label mb-1">Proveedor (opcional)</label>
          <Multiselect
            v-model="form.supplier_id"
            :options="supplierOptions"
            :append-to-body="false"
            :close-on-select="true"
            :searchable="true"
            placeholder="Sin proveedor"
          />
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
          <div class="input-group">
            <BFormInput
              v-model="form.serial_number"
              placeholder="Serie física o código interno"
            />
            <BButton
              type="button"
              variant="outline-primary"
              title="Generar código SS para bien sin número de serie"
              @click="generateFallbackSerialNumber"
            >
              SS
            </BButton>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Fecha compra</label>
          <BFormInput v-model="form.purchase_date" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Valor compra (opcional)</label>
          <BFormInput
            v-model="form.purchase_value"
            type="number"
            min="0"
            placeholder="Sin valor"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Vida útil (años)</label>
          <BFormInput v-model="form.useful_life_years" type="number" min="0" />
        </div>

        <div class="col-12">
          <div class="inventory-warranty-box">
            <div class="inventory-warranty-header">
              <BFormCheckbox v-model="form.has_warranty" switch>
                Producto con garantía
              </BFormCheckbox>
              <span
                class="inventory-warranty-status"
                :class="{ 'inventory-warranty-status--active': form.has_warranty }"
              >
                {{ form.has_warranty ? "Con garantía" : "Sin garantía" }}
              </span>
            </div>
            <div
              v-if="form.has_warranty"
              class="row g-3 mt-2 align-items-end inventory-warranty-grid"
            >
              <div class="col-md-4 inventory-warranty-field">
                <label class="form-label mb-1">Duración garantía (meses)</label>
                <BFormInput
                  v-model="form.warranty_months"
                  type="number"
                  min="1"
                  max="600"
                  placeholder="Ej: 12"
                />
              </div>
              <div class="col-md-4 inventory-warranty-field">
                <label class="form-label mb-1">Vencimiento garantía</label>
                <div
                  class="inventory-warranty-expiration"
                  :class="{ 'inventory-warranty-expiration--pending': !warrantyExpirationDate }"
                >
                  {{ warrantyExpirationLabel }}
                </div>
              </div>
              <div class="col-md-4 inventory-warranty-field">
                <label class="form-label mb-1">Base de cálculo</label>
                <div class="inventory-warranty-base">
                  {{ form.purchase_date ? formatDateForDisplay(form.purchase_date) : "Fecha compra pendiente" }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label mb-1">Descripción</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Cantidad</label>
          <BFormInput
            v-model="form.stock_quantity"
            type="number"
            min="0"
            step="0.01"
            placeholder="Ej: 1"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Cantidad mínima</label>
          <BFormInput
            v-model="form.minimum_stock"
            type="number"
            min="0"
            step="0.01"
            placeholder="Opcional"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Unidad</label>
          <BFormInput v-model="form.unit_of_measure" placeholder="ej: un, caja, resma" />
        </div>

        <div class="col-12">
          <label class="form-label mb-1">Foto principal</label>
          <div class="inventory-photo-actions">
            <label class="btn btn-outline-secondary inventory-photo-button">
              Adjuntar imagen
              <input
                class="inventory-photo-input"
                type="file"
                accept="image/*"
                capture="environment"
                @change="onPhotoSelected"
              />
            </label>
            <BButton
              type="button"
              variant="outline-primary"
              class="inventory-photo-button"
              @click="startCamera"
            >
              Tomar foto
            </BButton>
          </div>
          <div
            v-if="cameraActive || cameraError"
            class="inventory-camera-panel"
          >
            <BAlert v-if="cameraError" variant="warning" show class="mb-0">
              {{ cameraError }}
            </BAlert>
            <template v-else>
              <video
                ref="cameraVideo"
                class="inventory-camera-video"
                autoplay
                muted
                playsinline
              ></video>
              <canvas ref="cameraCanvas" class="inventory-camera-canvas"></canvas>
              <div class="inventory-camera-actions">
                <BButton size="sm" variant="primary" @click="capturePhoto">
                  Capturar foto
                </BButton>
                <BButton size="sm" variant="outline-secondary" @click="stopCamera">
                  Cerrar cámara
                </BButton>
              </div>
            </template>
          </div>
          <div v-if="form.photo" class="inventory-photo-file">
            {{ form.photo.name }}
          </div>
          <small class="text-muted"
            >“Tomar foto” abre la cámara y guarda la captura como foto principal.</small
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
.inventory-page-title {
  margin: 3px 0 0;
  color: #273247;
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.2;
}

.inventory-page-hero {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) auto auto;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 1.15rem 1.25rem;
  border: 1px solid #dfe8fb;
  border-radius: 1rem;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(241, 246, 255, 0.9));
  box-shadow: 0 1rem 2.8rem rgba(49, 82, 201, 0.07);
}

.inventory-page-eyebrow {
  display: block;
  color: #62708d;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  line-height: 1.2;
  text-transform: uppercase;
}

.inventory-page-heading p,
.inventory-filter-panel__head p {
  margin: 0.4rem 0 0;
  color: #71809b;
  font-size: 0.84rem;
  line-height: 1.45;
}

.inventory-page-summary {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.45rem;
}

.inventory-page-metric {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  min-height: 2.35rem;
  padding: 0.45rem 0.7rem;
  border: 1px solid #dbe7ff;
  border-radius: 0.75rem;
  color: #53607a;
  background: #fff;
  font-size: 0.78rem;
  font-weight: 600;
}

.inventory-page-metric i {
  color: #526ad7;
  font-size: 1rem;
}

.inventory-page-metric--location {
  max-width: 20rem;
  color: #3152c9;
  background: #eef4ff;
}

.inventory-page-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

.inventory-page-actions :deep(.btn) {
  min-height: 2.35rem;
  border-radius: 0.75rem;
  font-size: 0.82rem;
  font-weight: 650;
}

.inventory-template-panel {
  padding: 0.9rem;
  border: 1px solid #dbe7ff;
  border-radius: 0.95rem;
  background: #f8fbff;
}

.inventory-template-grid {
  align-items: start;
}

.inventory-template-search,
.inventory-template-quantity {
  min-width: 0;
}

.inventory-template-results {
  display: grid;
  gap: 0.45rem;
  margin-top: 0.75rem;
}

.inventory-template-result {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.75rem;
  align-items: center;
  width: 100%;
  padding: 0.55rem 0.7rem;
  border: 1px solid #dbe7ff;
  border-radius: 0.75rem;
  color: #334155;
  background: #ffffff;
  text-align: left;
}

.inventory-template-result__content {
  min-width: 0;
}

.inventory-template-result:hover {
  border-color: #9eb8ff;
  background: #f1f5ff;
}

.inventory-template-result__main {
  color: #1f2937;
  font-size: 0.84rem;
  font-weight: 700;
  line-height: 1.15;
  overflow-wrap: anywhere;
}

.inventory-template-result__meta,
.inventory-template-result__location {
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 550;
  line-height: 1.15;
  overflow-wrap: anywhere;
}

.inventory-template-result__action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 1.65rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  color: #3152c9;
  background: #eef4ff;
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
}

.inventory-filters {
  position: relative;
  z-index: 20;
}

.inventory-filter-panel {
  position: relative;
  z-index: 20;
  margin-bottom: 1rem;
  padding: 1rem 1.1rem;
  border: 1px solid #dfe8fb;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: 0 1.1rem 2.8rem rgba(37, 99, 235, 0.06);
}

.inventory-filter-panel__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.9rem;
}

.inventory-filter-panel__head h5 {
  margin: 0.2rem 0 0;
  color: #303a4d;
  font-size: 1rem;
  font-weight: 700;
}

.inventory-filter-count {
  display: inline-flex;
  align-items: center;
  min-height: 1.9rem;
  padding: 0.3rem 0.65rem;
  border: 1px solid #dbe3ed;
  border-radius: 999px;
  color: #64748b;
  background: #f8fafc;
  font-size: 0.73rem;
  font-weight: 650;
  white-space: nowrap;
}

.inventory-filter-count.is-active {
  color: #3152c9;
  border-color: #c7d7fe;
  background: #eef4ff;
}

.inventory-filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.32rem;
  min-width: 0;
}

.inventory-filter-label {
  margin: 0;
  color: #74788d;
  font-size: 0.68rem;
  font-weight: 650;
  line-height: 1;
  letter-spacing: 0;
  text-transform: uppercase;
}

.inventory-filter-field--location .inventory-filter-label {
  color: #3152c9;
}

.inventory-filter-field--location .inventory-filter-label i {
  margin-right: 0.18rem;
  font-size: 0.9rem;
  vertical-align: -1px;
}

.inventory-dependency-filter-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
}

.inventory-occupied-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  min-height: 1.55rem;
  padding: 0.18rem 0.42rem;
  border: 1px solid #d7e1f2;
  border-radius: 999px;
  color: #64748b;
  background: #f8fafc;
  font-size: 0.65rem;
  font-weight: 650;
  line-height: 1;
  transition: border-color 0.18s ease, color 0.18s ease, background-color 0.18s ease;
}

.inventory-occupied-toggle:hover,
.inventory-occupied-toggle.is-active {
  color: #3152c9;
  border-color: #aebffd;
  background: #eef4ff;
}

.inventory-occupied-toggle span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.2rem;
  height: 1.2rem;
  padding: 0 0.25rem;
  border-radius: 999px;
  color: #ffffff;
  background: #526ad7;
  font-size: 0.62rem;
}

.inventory-dependency-filter-summary {
  color: #7b8498;
  font-size: 0.66rem;
  line-height: 1.25;
}

.inventory-filter-control,
.inventory-search-button {
  min-height: 2.45rem;
}

.inventory-filter-search {
  width: 100%;
  padding: 0.48rem 0.8rem;
  border-color: #dfe8fb;
  border-radius: 0.8rem;
  color: #4b5563;
  font-size: 0.84rem;
  font-weight: 500;
  box-shadow: 0 0.25rem 1rem rgba(99, 102, 241, 0.05);
}

.inventory-filter-search::placeholder {
  color: #7f879c;
}

.inventory-low-stock {
  display: flex;
  align-items: center;
  min-height: 2.45rem;
  color: #4b5563;
  font-size: 0.84rem;
  font-weight: 550;
  white-space: nowrap;
}

.inventory-low-stock :deep(.form-check) {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin-bottom: 0;
}

.inventory-low-stock :deep(.form-check-input) {
  width: 1rem;
  height: 1rem;
  margin-top: 0;
  border-color: #cbd5e1;
}

.inventory-search-button {
  border-radius: 0.85rem;
  font-size: 0.84rem;
  font-weight: 650;
}

.inventory-filter-action {
  display: flex;
  align-items: end;
}

.inventory-filter-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.45rem;
  width: 100%;
}

.inventory-filter-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.85rem;
}

.inventory-filter-chips span {
  display: inline-flex;
  align-items: center;
  min-height: 1.75rem;
  padding: 0.28rem 0.62rem;
  border: 1px solid #d7e1f2;
  border-radius: 999px;
  color: #53607a;
  background: #f8fafc;
  font-size: 0.72rem;
  font-weight: 600;
}

.inventory-selected-location {
  display: grid;
  grid-template-columns: 2.35rem minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.7rem;
  margin-top: 0.85rem;
  padding: 0.7rem 0.8rem;
  border: 1px solid #c7d7fe;
  border-radius: 0.8rem;
  color: #3152c9;
  background: #f3f7ff;
}

.inventory-selected-location > i {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.35rem;
  height: 2.35rem;
  border-radius: 0.7rem;
  background: #e3ebff;
  font-size: 1.15rem;
}

.inventory-selected-location div {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.inventory-selected-location span,
.inventory-selected-location small {
  color: #6c7891;
  font-size: 0.72rem;
}

.inventory-selected-location strong {
  overflow: hidden;
  color: #303a4d;
  font-size: 0.84rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.inventory-selected-location .inventory-selected-location__count {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.38rem 0.62rem;
  border: 1px solid #c7d7fe;
  border-radius: 999px;
  color: #3152c9;
  background: #ffffff;
  font-size: 0.7rem;
  font-weight: 700;
  white-space: nowrap;
}

.inventory-table-card {
  max-width: 100%;
  padding: 0.55rem 0.75rem 0.2rem;
  border: 1px solid rgba(223, 232, 251, 0.9);
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.48);
  box-shadow: 0 1.25rem 3rem rgba(37, 99, 235, 0.06);
}

.inventory-results-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.45rem 0.25rem 0.8rem;
}

.inventory-results-head div {
  display: flex;
  align-items: baseline;
  gap: 0.42rem;
}

.inventory-results-head span,
.inventory-results-head small {
  color: #7b8498;
  font-size: 0.74rem;
}

.inventory-results-head strong {
  color: #303a4d;
  font-size: 0.9rem;
}

.inventory-results-head .inventory-results-meta {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.8rem;
}

.inventory-results-meta span {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.inventory-table-scroll {
  width: 100%;
  max-width: 100%;
  overflow-x: auto;
  overflow-y: visible;
  padding-bottom: 0.25rem;
}

.inventory-table-scroll::-webkit-scrollbar {
  height: 0.55rem;
}

.inventory-table-scroll::-webkit-scrollbar-thumb {
  border-radius: 999px;
  background: #cbd5e1;
}

.inventory-code-pill,
.inventory-chip,
.inventory-stock-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  max-width: 100%;
  min-height: 1.65rem;
  padding: 0.28rem 0.55rem;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 600;
  line-height: 1.12;
  text-align: center;
  white-space: normal;
}

.inventory-code-pill {
  gap: 0.35rem;
  width: fit-content;
  min-width: 0;
  max-width: 100%;
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
  white-space: nowrap;
}

.inventory-item-summary,
.inventory-classification-cell,
.inventory-location-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.42rem;
  min-width: 0;
  text-align: left;
}

.inventory-name-cell {
  display: block;
  max-width: 100%;
  color: #4b5563;
  font-size: 0.82rem;
  font-weight: 500;
  line-height: 1.22;
  text-align: left;
  overflow-wrap: anywhere;
}

.inventory-name-cell strong {
  display: block;
  color: #374151;
  font-size: 0.86rem;
  font-weight: 700;
}

.inventory-name-cell small {
  display: block;
}

.inventory-name-cell small {
  margin-top: 0.2rem;
  color: #7b8498;
  font-size: 0.7rem;
  font-weight: 500;
  line-height: 1.25;
}

.inventory-classification-cell strong {
  color: #3f4a5e;
  font-size: 0.8rem;
  font-weight: 650;
  line-height: 1.2;
  overflow-wrap: anywhere;
}

.inventory-classification-cell small {
  color: #7b8498;
  font-size: 0.69rem;
}

.inventory-classification-cell .inventory-chip {
  margin-top: 0.05rem;
}

.inventory-location-cell {
  display: grid;
  grid-template-columns: 1rem minmax(0, 1fr);
  gap: 0.12rem 0.3rem;
  align-items: start;
  color: #475569;
  font-size: 0.78rem;
  font-weight: 600;
  line-height: 1.25;
  text-align: left;
}

.inventory-location-cell i {
  grid-row: 1 / 3;
  color: #526ad7;
  font-size: 0.95rem;
}

.inventory-location-cell span,
.inventory-location-cell small {
  overflow-wrap: anywhere;
}

.inventory-location-cell small {
  color: #7b8498;
  font-size: 0.68rem;
  font-weight: 500;
}

.inventory-location-cell--empty {
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  gap: 0.3rem;
  color: #94a3b8;
  font-weight: 500;
  text-align: left;
}

.inventory-location-cell--empty i {
  color: #94a3b8;
}

.inventory-responsible-row {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  max-width: 100%;
  padding-top: 0.38rem;
  border-top: 1px dashed #dbe4f0;
  color: #526078;
  font-size: 0.7rem;
  font-weight: 550;
  line-height: 1.2;
}

.inventory-responsible-row i {
  color: #526ad7;
  font-size: 0.88rem;
}

.inventory-responsible-row.is-empty {
  color: #94a3b8;
}

.inventory-operational-cell {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 0.38rem 0.48rem;
  min-width: 0;
}

.inventory-operational-label {
  color: #8a94a7;
  font-size: 0.62rem;
  font-weight: 650;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.inventory-operational-cell .inventory-chip {
  justify-self: start;
}

.inventory-chip--type-asset {
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
}

.inventory-chip--type-consumable {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.inventory-chip--status-active {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.inventory-chip--status-pending {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.inventory-chip--status-stored {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.inventory-chip--status-inactive,
.inventory-chip--status-neutral {
  color: #475569;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.inventory-chip--condition-good {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.inventory-chip--condition-warning {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.inventory-chip--condition-danger {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

.inventory-chip--condition-neutral {
  color: #475569;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.inventory-stock-pill {
  min-width: 4rem;
  color: #475569;
  background: #ffffff;
  border-color: #dbe3ed;
  box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.08);
}

.inventory-stock-pill--empty {
  min-width: 2.25rem;
  color: #94a3b8;
  background: #f8fafc;
}

.inventory-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  gap: 0.32rem;
  flex-wrap: nowrap;
}

.inventory-actions .inventory-action-button,
.inventory-actions :deep(.inventory-action-button) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 2.05rem;
  width: 2.05rem;
  height: 2.05rem;
  padding: 0;
  border-width: 1px;
  border-radius: 0.65rem;
  background: #ffffff;
  font-size: 1rem;
  line-height: 1;
  box-shadow: 0 0.2rem 0.7rem rgba(15, 23, 42, 0.05);
}

.inventory-actions .inventory-action-button--view {
  color: #526078;
  border-color: #cbd5e1;
}

.inventory-actions :deep(.inventory-action-button--edit) {
  color: #b45309;
  border-color: #f6c55a;
}

.inventory-actions :deep(.inventory-action-button--delete) {
  color: #dc2626;
  border-color: #fecaca;
}

.inventory-actions .inventory-action-button:hover,
.inventory-actions :deep(.inventory-action-button:hover) {
  transform: translateY(-1px);
  box-shadow: 0 0.35rem 0.9rem rgba(15, 23, 42, 0.1);
}

:deep(.inventory-items-table) {
  width: 100%;
  min-width: 920px;
  margin-bottom: 0;
  table-layout: fixed;
}

:deep(.inventory-items-table thead th) {
  padding: 0.72rem 0.45rem;
  color: #74788d;
  font-size: 0.7rem;
  font-weight: 650;
  line-height: 1.15;
  text-align: left !important;
  vertical-align: middle;
  letter-spacing: 0;
  background: #f8fbff;
  border-bottom: 1px solid #dfe8f7;
  white-space: normal;
}

:deep(.inventory-items-table tbody td) {
  padding: 0.82rem 0.62rem;
  color: #4b5563;
  font-size: 0.82rem;
  font-weight: 500;
  line-height: 1.24;
  vertical-align: middle;
  border-bottom: 1px solid #e6eef8;
  overflow-wrap: anywhere;
}

:deep(.inventory-items-table tbody tr) {
  transition: background-color 0.16s ease;
}

:deep(.inventory-items-table tbody tr:nth-child(even)) {
  background: rgba(248, 251, 255, 0.72);
}

:deep(.inventory-items-table tbody tr:hover) {
  background: #f1f6ff;
}

:deep(.inventory-items-table tbody tr:last-child td) {
  border-bottom: 0;
}

:deep(.inventory-col-summary) {
  width: 25%;
}

:deep(.inventory-col-classification) {
  width: 15%;
}

:deep(.inventory-col-location) {
  width: 27%;
}

:deep(.inventory-col-operational) {
  width: 17%;
}

:deep(.inventory-col-stock) {
  width: 7%;
  text-align: center !important;
}

:deep(.inventory-col-actions) {
  width: 9%;
  text-align: center !important;
}

:deep(.inventory-filter-select.multiselect) {
  --ms-radius: 0.8rem;
  --ms-border-color: #dfe8fb;
  --ms-border-width: 1px;
  --ms-bg: #ffffff;
  --ms-font-size: 0.84rem;
  --ms-line-height: 1.35;
  --ms-option-bg-selected: #5f76e8;
  --ms-option-color-selected: #ffffff;
  --ms-option-bg-pointed: #f1f5ff;
  --ms-option-color-pointed: #3152c9;
  --ms-option-bg-selected-pointed: #5069dd;
  width: 100%;
  min-height: 2.45rem;
  box-shadow: 0 0.25rem 1rem rgba(99, 102, 241, 0.05);
}

:deep(.inventory-filter-select .multiselect-wrapper) {
  min-height: 2.45rem;
}

:deep(.inventory-filter-select .multiselect-placeholder),
:deep(.inventory-filter-select .multiselect-single-label) {
  display: flex;
  align-items: center;
  min-height: 2.45rem;
  padding-right: 2.85rem;
  padding-left: 0.8rem;
  color: #4b5563 !important;
  font-size: 0.84rem;
  font-weight: 500;
}

:deep(.inventory-filter-select .multiselect-single-label) {
  justify-content: space-between;
  gap: 0.55rem;
}

:deep(.inventory-filter-select .multiselect-single-label-text) {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:deep(.inventory-filter-select .multiselect-clear) {
  margin-right: 1.6rem;
}

:deep(.inventory-filter-select .multiselect-caret) {
  margin-right: 0.6rem;
}

:deep(.inventory-filter-select .multiselect-dropdown) {
  top: calc(100% + 0.35rem);
  z-index: 9000;
  width: 100%;
  max-height: 18rem;
  padding: 0.3rem;
  border: 1px solid #dbe7ff;
  border-radius: 0.8rem;
  background: #ffffff;
  box-shadow: 0 0.85rem 2rem rgba(15, 23, 42, 0.14);
  overflow: auto;
}

:deep(.inventory-filter-select .multiselect-option) {
  display: flex;
  align-items: center;
  min-height: 2rem;
  padding: 0.45rem 0.7rem;
  border-radius: 0.6rem;
  color: #334155 !important;
  font-size: 0.82rem;
  font-weight: 500;
  line-height: 1.2;
}

:deep(.inventory-filter-select .multiselect-option.is-selected),
:deep(.inventory-filter-select .multiselect-option.is-selected.is-pointed) {
  color: #ffffff !important;
  background: #5f76e8 !important;
}

:deep(.inventory-filter-select .multiselect-option.is-pointed:not(.is-selected)) {
  color: #3152c9 !important;
  background: #eef4ff !important;
}

:deep(.multiselect-dropdown) {
  z-index: 9000;
}

:deep(.multiselect) {
  max-width: 100%;
}

:global(.multiselect-dropdown) {
  z-index: 9000 !important;
}

:global(.multiselect-dropdown .multiselect-option) {
  display: flex;
  align-items: center;
  min-height: 2rem;
  padding: 0.45rem 0.7rem;
  color: #334155 !important;
  font-size: 0.82rem;
  font-weight: 500;
  line-height: 1.2;
  background: #ffffff;
}

:global(.multiselect-dropdown .multiselect-option.is-selected),
:global(.multiselect-dropdown .multiselect-option.is-selected.is-pointed) {
  color: #ffffff !important;
  background: #5f76e8 !important;
}

:global(.multiselect-dropdown .multiselect-option.is-pointed:not(.is-selected)) {
  color: #3152c9 !important;
  background: #eef4ff !important;
}

.inventory-dependency-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  width: 100%;
  min-width: 0;
  line-height: 1.15;
}

.inventory-dependency-option__content {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: 0.12rem;
  min-width: 0;
}

.inventory-dependency-option__main,
.inventory-dependency-option__usage {
  display: block;
  overflow-wrap: anywhere;
}

.inventory-dependency-option__main {
  font-weight: 650;
}

.inventory-dependency-option__usage {
  font-size: 0.72rem;
  font-weight: 500;
  opacity: 0.78;
}

.inventory-dependency-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  gap: 0.25rem;
  min-width: 2.25rem;
  min-height: 1.55rem;
  padding: 0.18rem 0.42rem;
  border: 1px solid #c7d7fe;
  border-radius: 999px;
  color: #3152c9;
  background: #eef4ff;
  font-size: 0.68rem;
  font-weight: 750;
  line-height: 1;
}

.inventory-dependency-count--selected {
  margin-right: 0.15rem;
}

:deep(.multiselect-option.is-selected) .inventory-dependency-count,
:global(.multiselect-option.is-selected) .inventory-dependency-count {
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.5);
  background: rgba(255, 255, 255, 0.16);
}

.inventory-photo-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}

.inventory-photo-button {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.35rem;
  margin-bottom: 0;
  padding: 0.45rem 0.85rem;
  border-radius: 0.75rem;
  font-size: 0.84rem;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
}

.inventory-photo-input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

.inventory-photo-file {
  margin-top: 0.45rem;
  color: #4b5563;
  font-size: 0.82rem;
  font-weight: 500;
  overflow-wrap: anywhere;
}

.inventory-warranty-box {
  padding: 1rem;
  border: 1px solid #dbe7ff;
  border-radius: 0.9rem;
  background: rgba(248, 251, 255, 0.8);
}

.inventory-warranty-header {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: center;
  justify-content: space-between;
}

.inventory-warranty-header :deep(.form-check) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0;
  color: #4b5563;
  font-size: 0.88rem;
  font-weight: 650;
}

.inventory-warranty-grid {
  align-items: end;
}

.inventory-warranty-field {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.inventory-warranty-field .form-label {
  display: block;
  min-height: 1.35rem;
  margin-bottom: 0.42rem !important;
  line-height: 1.15;
}

.inventory-warranty-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.15rem;
  padding: 0.42rem 0.85rem;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  color: #475569;
  background: #ffffff;
  font-size: 0.84rem;
  font-weight: 650;
  line-height: 1.1;
  text-align: center;
}

.inventory-warranty-expiration,
.inventory-warranty-base {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  width: 100%;
  min-height: 2.65rem;
  padding: 0.55rem 0.85rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.75rem;
  color: #475569;
  background: #ffffff;
  font-size: 0.84rem;
  font-weight: 650;
  line-height: 1.1;
  text-align: left;
  overflow-wrap: anywhere;
}

.inventory-warranty-status--active,
.inventory-warranty-expiration {
  color: #047857;
  border-color: #a7f3d0;
  background: #ecfdf5;
}

.inventory-warranty-expiration--pending {
  color: #b45309;
  border-color: #fcd34d;
  background: #fffbeb;
}

.inventory-warranty-base {
  border-color: #dbe7ff;
  color: #3152c9;
  background: #eef4ff;
}

.inventory-warranty-field :deep(.form-control) {
  min-height: 2.65rem;
}

.inventory-camera-panel {
  max-width: 30rem;
  margin-top: 0.65rem;
  padding: 0.75rem;
  border: 1px solid #dbe7ff;
  border-radius: 0.9rem;
  background: #f8fbff;
}

.inventory-camera-video {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: 0.75rem;
  background: #0f172a;
  object-fit: cover;
}

.inventory-camera-canvas {
  display: none;
}

.inventory-camera-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  justify-content: flex-end;
  margin-top: 0.6rem;
}

.inventory-camera-actions .btn,
.inventory-camera-actions :deep(.btn) {
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 650;
}

@media (max-width: 1399.98px) {
  .inventory-page-hero {
    grid-template-columns: minmax(260px, 1fr) auto;
  }

  .inventory-page-actions {
    grid-column: 1 / -1;
  }

  .inventory-low-stock {
    justify-content: flex-start;
  }
}

@media (max-width: 767.98px) {
  .inventory-page-hero {
    grid-template-columns: 1fr;
    align-items: stretch;
    padding: 1rem;
  }

  .inventory-page-summary,
  .inventory-page-actions {
    grid-column: auto;
    justify-content: flex-start;
  }

  .inventory-page-actions :deep(.btn) {
    flex: 1 1 9rem;
  }

  .inventory-filter-panel {
    padding: 0.9rem;
  }

  .inventory-filter-panel__head {
    flex-direction: column;
  }

  .inventory-dependency-filter-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .inventory-selected-location {
    grid-template-columns: 2.35rem minmax(0, 1fr);
  }

  .inventory-selected-location .inventory-selected-location__count {
    grid-column: 1 / -1;
    justify-self: start;
  }

  .inventory-results-head,
  .inventory-results-head .inventory-results-meta {
    align-items: flex-start;
    flex-direction: column;
  }

  .inventory-filter-buttons {
    grid-template-columns: 1fr;
  }

  .inventory-template-result {
    grid-template-columns: 1fr;
  }

  .inventory-template-result__action {
    justify-self: flex-start;
  }

  .inventory-table-card {
    padding-right: 0.75rem;
    padding-left: 0.75rem;
  }
}
</style>
