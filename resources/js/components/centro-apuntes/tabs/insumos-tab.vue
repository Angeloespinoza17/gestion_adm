<script>
import axios from "axios";
import CentroApuntesHelpButton from "../help-button.vue";
import CentroApuntesModalIntro from "../modal-intro.vue";
import CentroApuntesSectionToolbar from "../section-toolbar.vue";
import CentroApuntesStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmCentroApuntesAction,
  confirmCentroApuntesCancel,
  downloadExcelWorkbook,
  downloadPdfReport,
  formatCentroApuntesDate,
  formatCentroApuntesDateTime,
  formatCentroApuntesError,
  humanizeCentroApuntesStatus,
  normalizeCentroApuntesNullableFields,
  normalizeOptions,
  showCentroApuntesError,
  showCentroApuntesSuccess,
} from "../module-utils";

const emptyForm = () => ({
  id: null,
  name: "",
  category: "papel",
  unit_of_measure: "unidad",
  current_stock: 0,
  minimum_stock: 0,
  maximum_stock: null,
  location: null,
  supplier_id: null,
  last_purchase_at: null,
  expires_at: null,
  status: "disponible",
  observations: null,
  active: true,
  photo: null,
});

export default {
  components: {
    CentroApuntesHelpButton,
    CentroApuntesModalIntro,
    CentroApuntesSectionToolbar,
    CentroApuntesStatusBadge,
    LoadingState,
  },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      saving: false,
      detailLoading: false,
      exportingPdf: false,
      exportingExcel: false,
      error: null,
      items: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      summary: {
        total: 0,
        available: 0,
        low_stock: 0,
        out_of_stock: 0,
        expiring_soon: 0,
      },
      filters: {
        search: "",
        category: null,
        status: null,
        critical_only: false,
      },
      showModal: false,
      showDetailModal: false,
      form: emptyForm(),
      selectedSupply: null,
    };
  },
  computed: {
    canManage() {
      return Boolean(this.catalogs.capabilities?.can_manage_inventory);
    },
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export_reports);
    },
    categoryOptions() {
      return normalizeOptions(this.catalogs.supply_categories || []);
    },
    unitOptions() {
      return normalizeOptions(this.catalogs.supply_units || []);
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.supply_statuses || []);
    },
    supplierOptions() {
      return normalizeOptions(this.catalogs.suppliers || []);
    },
    metricCards() {
      return [
        { key: "total", label: "Insumos", value: this.summary.total, icon: "bx-package", tone: "primary", hint: "Resultados con los filtros actuales" },
        { key: "available", label: "Disponibles", value: this.summary.available, icon: "bx-check-shield", tone: "success", hint: "Stock por sobre el mínimo" },
        { key: "low_stock", label: "Stock bajo", value: this.summary.low_stock, icon: "bx-trending-down", tone: "warning", hint: "Requieren reposición" },
        { key: "out_of_stock", label: "Agotados", value: this.summary.out_of_stock, icon: "bx-error-circle", tone: "danger", hint: "Sin existencias disponibles" },
        { key: "expiring_soon", label: "Por vencer", value: this.summary.expiring_soon, icon: "bx-calendar", tone: "info", hint: "Dentro de los próximos 30 días" },
      ];
    },
    tableFields() {
      return [
        { key: "name", label: "Insumo" },
        { key: "category", label: "Categoría" },
        { key: "current_stock", label: "Nivel de stock" },
        { key: "supplier", label: "Proveedor" },
        { key: "expires_at", label: "Vencimiento" },
        { key: "status", label: "Estado" },
        { key: "actions", label: "", thClass: "text-end", tdClass: "text-end" },
      ];
    },
    resultRangeLabel() {
      if (!this.pagination.total) return "0 resultados";
      const start = (this.pagination.current_page - 1) * this.pagination.per_page + 1;
      const end = Math.min(this.pagination.current_page * this.pagination.per_page, this.pagination.total);
      return `${start}-${end} de ${this.pagination.total} insumos`;
    },
    hasActiveFilters() {
      return Boolean(
        String(this.filters.search || "").trim()
        || this.filters.category
        || this.filters.status
        || this.filters.critical_only
      );
    },
  },
  mounted() {
    this.load();
    this.consumeRouteFocus();
  },
  methods: {
    formatCentroApuntesDate,
    formatCentroApuntesDateTime,
    humanizeCentroApuntesStatus,
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/insumos", {
          params: {
            page,
            ...this.filters,
            critical_only: this.filters.critical_only ? 1 : "",
          },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
        this.summary = {
          ...this.summary,
          ...(response.data.summary || {}),
        };
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudieron cargar los insumos.");
      } finally {
        this.loading = false;
      }
    },
    async consumeRouteFocus() {
      if (!this.$route.query.supply) return;
      await this.openDetail(this.$route.query.supply);
    },
    resetForm() {
      this.form = emptyForm();
      this.selectedSupply = null;
    },
    openCreate() {
      this.resetForm();
      this.showModal = true;
    },
    async openEdit(item) {
      await this.openDetail(item.id, true);
    },
    async openDetail(itemOrId, alsoEdit = false) {
      const id = typeof itemOrId === "object" ? itemOrId.id : itemOrId;
      this.detailLoading = true;
      try {
        const response = await axios.get(`/api/centro-apuntes/insumos/${id}`);
        this.selectedSupply = response.data.data;
        if (alsoEdit) {
          this.form = {
            id: this.selectedSupply.id,
            name: this.selectedSupply.name,
            category: this.selectedSupply.category,
            unit_of_measure: this.selectedSupply.unit_of_measure,
            current_stock: this.selectedSupply.current_stock,
            minimum_stock: this.selectedSupply.minimum_stock,
            maximum_stock: this.selectedSupply.maximum_stock,
            location: this.selectedSupply.location ?? null,
            supplier_id: this.selectedSupply.supplier_id || null,
            last_purchase_at: this.selectedSupply.last_purchase_at ? String(this.selectedSupply.last_purchase_at).slice(0, 10) : null,
            expires_at: this.selectedSupply.expires_at ? String(this.selectedSupply.expires_at).slice(0, 10) : null,
            status: this.selectedSupply.status,
            observations: this.selectedSupply.observations ?? null,
            active: this.selectedSupply.active,
            photo: null,
          };
          this.showModal = true;
        } else {
          this.showDetailModal = true;
        }
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo cargar el detalle del insumo.");
      } finally {
        this.detailLoading = false;
      }
    },
    buildFormData() {
      const formData = new FormData();
      const photo = Array.isArray(this.form.photo) ? this.form.photo[0] : this.form.photo;
      const normalized = normalizeCentroApuntesNullableFields(this.form, [
        "maximum_stock",
        "location",
        "supplier_id",
        "last_purchase_at",
        "expires_at",
        "observations",
      ]);
      [
        "name",
        "category",
        "unit_of_measure",
        "current_stock",
        "minimum_stock",
        "maximum_stock",
        "location",
        "supplier_id",
        "last_purchase_at",
        "expires_at",
        "status",
        "observations",
      ].forEach((field) => formData.append(field, normalized[field] ?? ""));
      formData.append("active", this.form.active ? 1 : 0);

      if (photo) {
        formData.append("photo", photo);
      }

      return formData;
    },
    async save() {
      const confirmed = await confirmCentroApuntesAction({
        title: this.form.id ? "Guardar cambios" : "Crear insumo",
        text: this.form.id
          ? "Se actualizará el insumo seleccionado."
          : "Se registrará un nuevo insumo para el pañol.",
        confirmButtonText: "Guardar",
      });

      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const formData = this.buildFormData();
        if (this.form.id) {
          formData.append("_method", "PUT");
          await axios.post(`/api/centro-apuntes/insumos/${this.form.id}`, formData, {
            headers: { "Content-Type": "multipart/form-data" },
          });
        } else {
          await axios.post("/api/centro-apuntes/insumos", formData, {
            headers: { "Content-Type": "multipart/form-data" },
          });
        }
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess(this.form.id ? "Insumo actualizado correctamente." : "Insumo registrado correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      } finally {
        this.saving = false;
      }
    },
    async destroy(item) {
      const confirmed = await confirmCentroApuntesAction({
        title: "Eliminar insumo",
        text: `Se eliminará el insumo ${item.name} si no tiene movimientos asociados.`,
        confirmButtonText: "Sí, eliminar",
        icon: "warning",
      });

      if (!confirmed.isConfirmed) return;

      try {
        await axios.delete(`/api/centro-apuntes/insumos/${item.id}`);
        await this.load(this.pagination.current_page);
        this.$emit("refresh-catalogs");
        await showCentroApuntesSuccess("Insumo eliminado correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    clearFilters() {
      this.filters = {
        search: "",
        category: null,
        status: null,
        critical_only: false,
      };
      this.load();
    },
    categoryLabel(value) {
      return this.categoryOptions.find((item) => item.value === value)?.label
        || humanizeCentroApuntesStatus(value);
    },
    categoryIcon(category) {
      const icons = {
        papel: "bx-file",
        tinta: "bx-droplet",
        toner: "bx-printer",
        espirales: "bx-link",
        micas: "bx-layer",
        tapas: "bx-book",
        contratapas: "bx-book",
        corchetes: "bx-paperclip",
        carpetas: "bx-folder",
        plumones: "bx-highlight",
        lapices: "bx-pencil",
        cartulinas: "bx-palette",
        material_de_oficina: "bx-briefcase",
        material_pedagogico: "bx-book-reader",
      };
      return icons[category] || "bx-box";
    },
    formatStock(value) {
      return Number(value || 0).toLocaleString("es-CL", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      });
    },
    stockPercent(item) {
      const current = Number(item.current_stock || 0);
      const maximum = Number(item.maximum_stock || 0);
      const minimum = Number(item.minimum_stock || 0);
      const reference = maximum > 0 ? maximum : Math.max(minimum * 2, current, 1);
      return Math.max(0, Math.min(100, Math.round((current / reference) * 100)));
    },
    stockTone(item) {
      const current = Number(item.current_stock || 0);
      const minimum = Number(item.minimum_stock || 0);
      if (current <= 0) return "danger";
      if (current <= minimum) return "warning";
      return "success";
    },
    expirationMeta(value) {
      if (!value) {
        return { label: "Sin vencimiento", tone: "neutral", hint: "No informado" };
      }
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
      const days = Math.ceil((date.getTime() - today.getTime()) / 86400000);
      if (days < 0) return { label: formatCentroApuntesDate(value), tone: "danger", hint: "Vencido" };
      if (days <= 30) return { label: formatCentroApuntesDate(value), tone: "warning", hint: days === 0 ? "Vence hoy" : `En ${days} días` };
      return { label: formatCentroApuntesDate(value), tone: "success", hint: "Vigente" };
    },
    exportFileBase() {
      return `inventario-insumos-${new Date().toISOString().slice(0, 10)}`;
    },
    exportSubtitle(total) {
      const filters = [];
      if (String(this.filters.search || "").trim()) filters.push(`Búsqueda: ${this.filters.search.trim()}`);
      if (this.filters.category) filters.push(`Categoría: ${this.categoryLabel(this.filters.category)}`);
      if (this.filters.status) filters.push(`Estado: ${humanizeCentroApuntesStatus(this.filters.status)}`);
      if (this.filters.critical_only) filters.push("Solo stock crítico");
      return `${total} insumos - ${filters.length ? filters.join(" - ") : "Inventario completo"}`;
    },
    async loadExportData() {
      const response = await axios.get("/api/centro-apuntes/insumos", {
        params: {
          ...this.filters,
          critical_only: this.filters.critical_only ? 1 : "",
          export: 1,
        },
      });
      if (response.data.truncated) {
        throw new Error("La exportación supera el límite de 10.000 insumos. Aplica filtros más específicos e inténtalo nuevamente.");
      }
      return response.data;
    },
    exportSections(items, summary) {
      return [
        {
          title: "Resumen",
          subtitle: this.exportSubtitle(summary.total ?? items.length),
          headers: ["Indicador", "Cantidad"],
          widths: ["*", 80],
          columnWidths: [220, 90],
          rows: [
            ["Insumos", Number(summary.total || 0)],
            ["Disponibles", Number(summary.available || 0)],
            ["Stock bajo", Number(summary.low_stock || 0)],
            ["Agotados", Number(summary.out_of_stock || 0)],
            ["Por vencer en 30 días", Number(summary.expiring_soon || 0)],
          ],
        },
        {
          title: "Inventario",
          subtitle: "Detalle de existencias y abastecimiento",
          headers: ["Insumo", "Categoría", "Stock", "Mínimo", "Máximo", "Unidad", "Estado", "Vencimiento", "Ubicación", "Proveedor"],
          widths: [110, 72, 45, 45, 45, 52, 62, 62, 82, 88],
          columnWidths: [190, 115, 72, 72, 72, 85, 100, 100, 140, 150],
          rows: items.map((item) => [
            item.name,
            this.categoryLabel(item.category),
            Number(item.current_stock || 0),
            Number(item.minimum_stock || 0),
            item.maximum_stock === null ? "-" : Number(item.maximum_stock || 0),
            humanizeCentroApuntesStatus(item.unit_of_measure),
            humanizeCentroApuntesStatus(item.status),
            item.expires_at ? formatCentroApuntesDate(item.expires_at) : "-",
            item.location || "-",
            item.supplier?.name || "-",
          ]),
        },
      ];
    },
    async exportPdf() {
      if (!this.canExport || this.exportingPdf) return;
      this.exportingPdf = true;
      try {
        const payload = await this.loadExportData();
        const items = payload.data || [];
        if (!items.length) {
          await showCentroApuntesError("No hay insumos que coincidan con los filtros actuales.", "Sin datos para exportar");
          return;
        }
        downloadPdfReport(
          this.exportFileBase(),
          "Inventario de insumos",
          this.exportSubtitle(payload.summary?.total ?? items.length),
          this.exportSections(items, payload.summary || {}),
          {
            pageOrientation: "landscape",
            tableFontSize: 6.8,
            headerText: "CENTRO DE APUNTES - INVENTARIO DE INSUMOS",
          }
        );
      } catch (error) {
        await showCentroApuntesError(formatCentroApuntesError(error, "No fue posible generar el PDF."));
      } finally {
        this.exportingPdf = false;
      }
    },
    async exportExcel() {
      if (!this.canExport || this.exportingExcel) return;
      this.exportingExcel = true;
      try {
        const payload = await this.loadExportData();
        const items = payload.data || [];
        if (!items.length) {
          await showCentroApuntesError("No hay insumos que coincidan con los filtros actuales.", "Sin datos para exportar");
          return;
        }
        downloadExcelWorkbook(
          this.exportFileBase(),
          this.exportSections(items, payload.summary || {}),
          {
            title: "Inventario de insumos",
            subtitle: this.exportSubtitle(payload.summary?.total ?? items.length),
          }
        );
      } catch (error) {
        await showCentroApuntesError(formatCentroApuntesError(error, "No fue posible generar el archivo Excel."));
      } finally {
        this.exportingExcel = false;
      }
    },
    async closeModal() {
      const confirmed = await confirmCentroApuntesCancel("la edición del insumo");
      if (confirmed.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div class="centro-apuntes-tab d-flex flex-column gap-3">
    <CentroApuntesSectionToolbar
      title="Inventario de insumos"
      description="Controla existencias, mínimos, vencimientos y abastecimiento desde una sola vista."
      icon="bx-box"
      eyebrow="Pañol y abastecimiento"
    >
      <div class="d-flex flex-wrap gap-2">
        <CentroApuntesHelpButton
          title="Ayuda: inventario de insumos"
          text="Aquí se registran y actualizan los insumos del pañol de librería, controlando stock, vencimientos, proveedor, ubicación y estado de disponibilidad."
        />
        <BButton v-if="canExport" variant="outline-danger" :disabled="loading || exportingPdf" @click="exportPdf">
          <i class="bx bxs-file-pdf me-1"></i>{{ exportingPdf ? "Generando..." : "PDF" }}
        </BButton>
        <BButton v-if="canExport" variant="outline-success" :disabled="loading || exportingExcel" @click="exportExcel">
          <i class="bx bx-spreadsheet me-1"></i>{{ exportingExcel ? "Generando..." : "Excel" }}
        </BButton>
        <BButton v-if="canManage" variant="primary" @click="openCreate"><i class="bx bx-plus me-1"></i>Nuevo insumo</BButton>
      </div>
    </CentroApuntesSectionToolbar>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <div class="inventory-metrics">
      <article v-for="card in metricCards" :key="card.key" class="inventory-metric" :class="`inventory-metric--${card.tone}`">
        <span class="inventory-metric__icon"><i class="bx" :class="card.icon"></i></span>
        <span class="inventory-metric__copy">
          <small>{{ card.label }}</small>
          <strong>{{ card.value }}</strong>
          <span>{{ card.hint }}</span>
        </span>
      </article>
    </div>

    <BCard class="filter-card inventory-filter-card border-0 shadow-sm">
      <div class="inventory-filter-card__header">
        <div>
          <span class="inventory-filter-card__eyebrow">Búsqueda avanzada</span>
          <h6>Filtros de inventario</h6>
        </div>
        <span v-if="hasActiveFilters" class="inventory-filter-card__active">
          <i class="bx bx-filter-alt"></i> Filtros activos
        </span>
      </div>
      <div class="row g-3 align-items-end">
        <div class="col-lg-5">
          <label class="form-label">Buscar</label>
          <div class="input-group inventory-search">
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <BFormInput v-model="filters.search" placeholder="Nombre, categoría, ubicación o estado..." @keyup.enter="load(1)" />
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <label class="form-label">Categoría</label>
          <BFormSelect v-model="filters.category" :options="[{ value: null, text: 'Todas' }].concat(categoryOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat(statusOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-lg-2">
          <div class="critical-filter" :class="{ 'critical-filter--active': filters.critical_only }">
            <BFormCheckbox v-model="filters.critical_only" class="mb-0">Solo críticos</BFormCheckbox>
            <i class="bx bx-error-circle"></i>
          </div>
        </div>
        <div class="col-12">
          <div class="inventory-filter-actions">
            <BButton variant="primary" :disabled="loading" @click="load(1)">
              <i class="bx bx-filter-alt me-1"></i>Aplicar filtros
            </BButton>
            <BButton variant="light" :disabled="loading || !hasActiveFilters" @click="clearFilters">
              <i class="bx bx-reset me-1"></i>Limpiar
            </BButton>
          </div>
        </div>
      </div>
    </BCard>

    <BCard class="data-card inventory-data-card border-0 shadow-sm">
      <div class="inventory-data-card__header">
        <div>
          <span class="inventory-data-card__eyebrow">Existencias</span>
          <h6>Detalle del inventario</h6>
        </div>
        <span class="inventory-data-card__count">{{ resultRangeLabel }}</span>
      </div>
      <LoadingState v-if="loading || detailLoading" message="Cargando insumos..." compact />
      <BTable
        v-else
        responsive
        hover
        show-empty
        empty-text="No hay insumos que coincidan con los filtros."
        :items="items"
        :fields="tableFields"
        class="inventory-table align-middle"
      >
        <template #cell(name)="{ item }">
          <div class="inventory-item">
            <span class="inventory-item__icon"><i class="bx" :class="categoryIcon(item.category)"></i></span>
            <span>
              <strong>{{ item.name }}</strong>
              <small><i class="bx bx-map me-1"></i>{{ item.location || "Sin ubicación asignada" }}</small>
            </span>
          </div>
        </template>
        <template #cell(category)="{ item }">
          <span class="inventory-category">{{ categoryLabel(item.category) }}</span>
        </template>
        <template #cell(current_stock)="{ item }">
          <div class="stock-cell">
            <div class="stock-cell__top">
              <strong>{{ formatStock(item.current_stock) }}</strong>
              <span>{{ humanizeCentroApuntesStatus(item.unit_of_measure) }}</span>
            </div>
            <div class="stock-cell__track">
              <span :class="`stock-cell__bar stock-cell__bar--${stockTone(item)}`" :style="{ width: `${stockPercent(item)}%` }"></span>
            </div>
            <small>Mín. {{ formatStock(item.minimum_stock) }}<template v-if="item.maximum_stock !== null"> · Máx. {{ formatStock(item.maximum_stock) }}</template></small>
          </div>
        </template>
        <template #cell(supplier)="{ item }">
          <div class="supplier-cell">
            <i class="bx bx-store-alt"></i>
            <span>{{ item.supplier?.name || "Sin proveedor" }}</span>
          </div>
        </template>
        <template #cell(expires_at)="{ item }">
          <div class="expiry-cell" :class="`expiry-cell--${expirationMeta(item.expires_at).tone}`">
            <span class="expiry-cell__dot"></span>
            <span>
              <strong>{{ expirationMeta(item.expires_at).label }}</strong>
              <small>{{ expirationMeta(item.expires_at).hint }}</small>
            </span>
          </div>
        </template>
        <template #cell(status)="{ item }">
          <CentroApuntesStatusBadge :status="item.status" />
        </template>
        <template #cell(actions)="{ item }">
          <div class="inventory-actions">
            <BButton size="sm" variant="light" title="Ver detalle" aria-label="Ver detalle" @click="openDetail(item)"><i class="bx bx-show"></i></BButton>
            <BButton v-if="canManage" size="sm" variant="light" title="Editar insumo" aria-label="Editar insumo" @click="openEdit(item)"><i class="bx bx-edit-alt"></i></BButton>
            <BButton v-if="canManage" size="sm" variant="light" class="inventory-actions__danger" title="Eliminar insumo" aria-label="Eliminar insumo" @click="destroy(item)"><i class="bx bx-trash"></i></BButton>
          </div>
        </template>
      </BTable>
      <div v-if="pagination.total > pagination.per_page" class="inventory-pagination">
        <span>{{ resultRangeLabel }}</span>
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="load"
        />
      </div>
    </BCard>

    <BModal v-model="showModal" size="lg" :title="form.id ? 'Editar insumo' : 'Nuevo insumo'" hide-footer centered scrollable modal-class="centro-apuntes-modal">
      <CentroApuntesModalIntro title="Ficha de inventario" text="Proveedor, ubicación, stock máximo, fechas, foto y observaciones pueden quedar sin información." icon="bx-box">
        <CentroApuntesHelpButton
          title="Ayuda: formulario de insumo"
          text="Use este formulario para registrar insumos, su stock, proveedor, ubicación, vencimiento, foto y estado operativo."
        />
      </CentroApuntesModalIntro>
      <div class="modal-form-grid row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre <span class="field-required">*</span></label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.status" :options="statusOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Categoría <span class="field-required">*</span></label>
          <BFormSelect v-model="form.category" :options="categoryOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Unidad de medida <span class="field-required">*</span></label>
          <BFormSelect v-model="form.unit_of_measure" :options="unitOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Stock actual <span class="field-required">*</span></label>
          <BFormInput v-model="form.current_stock" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Stock mínimo <span class="field-required">*</span></label>
          <BFormInput v-model="form.minimum_stock" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Stock máximo <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.maximum_stock" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Ubicación <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.location" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Proveedor <span class="field-optional">Opcional</span></label>
          <BFormSelect v-model="form.supplier_id" :options="[{ value: null, text: 'Sin proveedor' }].concat(supplierOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Última compra <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.last_purchase_at" type="date" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de vencimiento <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.expires_at" type="date" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Foto <span class="field-optional">Opcional</span></label>
          <BFormFile v-model="form.photo" browse-text="Seleccionar" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Observaciones <span class="field-optional">Opcional</span></label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
        <div class="col-md-12">
          <BFormCheckbox v-model="form.active">Insumo activo</BFormCheckbox>
        </div>
      </div>

      <div v-if="selectedSupply?.photo_url" class="mt-4">
        <div class="fw-semibold mb-2">Foto actual</div>
        <img :src="selectedSupply.photo_url" alt="Foto insumo" class="img-thumbnail" style="max-width: 220px" />
      </div>

      <div class="modal-actions">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDetailModal" size="lg" title="Detalle de insumo" hide-footer centered scrollable modal-class="centro-apuntes-modal">
      <template v-if="selectedSupply">
        <div class="detail-grid row g-3">
          <div class="col-md-4">
            <div class="text-muted small">Insumo</div>
            <div class="fw-semibold">{{ selectedSupply.name }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Estado</div>
            <CentroApuntesStatusBadge :status="selectedSupply.status" />
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Stock actual</div>
            <div>{{ formatStock(selectedSupply.current_stock) }} {{ humanizeCentroApuntesStatus(selectedSupply.unit_of_measure) }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Categoría</div>
            <div>{{ categoryLabel(selectedSupply.category) }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Stock mínimo</div>
            <div>{{ formatStock(selectedSupply.minimum_stock) }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Stock máximo</div>
            <div>{{ selectedSupply.maximum_stock === null ? "Sin límite" : formatStock(selectedSupply.maximum_stock) }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Proveedor</div>
            <div>{{ selectedSupply.supplier?.name || "Sin proveedor" }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Ubicación</div>
            <div>{{ selectedSupply.location || "-" }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Última compra</div>
            <div>{{ formatCentroApuntesDate(selectedSupply.last_purchase_at) }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Vencimiento</div>
            <div>{{ formatCentroApuntesDate(selectedSupply.expires_at) }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Registro</div>
            <div>{{ selectedSupply.active ? "Activo" : "Inactivo" }}</div>
          </div>
        </div>

        <div v-if="selectedSupply.observations || selectedSupply.photo_url" class="row g-3 mt-1">
          <div v-if="selectedSupply.observations" class="col-md-8">
            <div class="modal-section-title">Observaciones</div>
            <div class="supply-detail-note">{{ selectedSupply.observations }}</div>
          </div>
          <div v-if="selectedSupply.photo_url" class="col-md-4">
            <div class="modal-section-title">Fotografía</div>
            <img :src="selectedSupply.photo_url" :alt="`Foto de ${selectedSupply.name}`" class="supply-detail-photo" />
          </div>
        </div>

        <div class="mt-4">
          <div class="modal-section-title">Movimientos recientes</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Tipo</th>
                  <th>Cantidad</th>
                  <th>Responsable</th>
                  <th>Motivo</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!(selectedSupply.movements || []).length">
                  <td colspan="5" class="text-center text-muted py-4">
                    Sin movimientos registrados.
                  </td>
                </tr>
                <tr v-for="movement in selectedSupply.movements || []" :key="movement.id">
                  <td>{{ formatCentroApuntesDateTime(movement.moved_at) }}</td>
                  <td><CentroApuntesStatusBadge :status="movement.movement_type" /></td>
                  <td>{{ movement.quantity }}</td>
                  <td>{{ movement.responsible_user?.name || movement.responsibleUser?.name || "-" }}</td>
                  <td>{{ movement.reason || "-" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </BModal>
  </div>
</template>

<style scoped>
.inventory-metrics {
  display: grid;
  gap: .8rem;
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.inventory-metric {
  --metric-rgb: var(--bs-primary-rgb);
  align-items: center;
  background: var(--bs-body-bg);
  border: 1px solid rgba(var(--metric-rgb), .16);
  border-radius: .85rem;
  box-shadow: 0 .55rem 1.5rem rgba(48, 65, 102, .055);
  display: flex;
  gap: .75rem;
  min-height: 6rem;
  overflow: hidden;
  padding: .9rem;
  position: relative;
}

.inventory-metric::before {
  background: rgb(var(--metric-rgb));
  border-radius: 0 999px 999px 0;
  content: "";
  height: 2.2rem;
  left: 0;
  opacity: .9;
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: .2rem;
}

.inventory-metric--success { --metric-rgb: var(--bs-success-rgb); }
.inventory-metric--warning { --metric-rgb: var(--bs-warning-rgb); }
.inventory-metric--danger { --metric-rgb: var(--bs-danger-rgb); }
.inventory-metric--info { --metric-rgb: var(--bs-info-rgb); }

.inventory-metric__icon {
  align-items: center;
  background: rgba(var(--metric-rgb), .1);
  border-radius: .7rem;
  color: rgb(var(--metric-rgb));
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.3rem;
  height: 2.75rem;
  justify-content: center;
  width: 2.75rem;
}

.inventory-metric__copy {
  display: grid;
  min-width: 0;
}

.inventory-metric__copy small {
  color: var(--bs-secondary-color);
  font-size: .66rem;
  font-weight: 750;
  letter-spacing: .045em;
  text-transform: uppercase;
}

.inventory-metric__copy strong {
  color: var(--bs-heading-color);
  font-size: 1.45rem;
  line-height: 1.15;
}

.inventory-metric__copy > span {
  color: var(--bs-secondary-color);
  font-size: .65rem;
  line-height: 1.25;
  margin-top: .12rem;
}

.inventory-filter-card :deep(.card-body) { padding: 0 !important; }

.inventory-filter-card__header,
.inventory-data-card__header {
  align-items: center;
  border-bottom: 1px solid var(--bs-border-color);
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: .9rem 1.1rem;
}

.inventory-filter-card__header + .row {
  margin: 0;
  padding: .15rem 1.1rem 1.1rem;
}

.inventory-filter-card__eyebrow,
.inventory-data-card__eyebrow {
  color: var(--bs-primary);
  display: block;
  font-size: .61rem;
  font-weight: 800;
  letter-spacing: .08em;
  margin-bottom: .08rem;
  text-transform: uppercase;
}

.inventory-filter-card__header h6,
.inventory-data-card__header h6 {
  color: var(--bs-heading-color);
  font-size: .86rem;
  font-weight: 750;
  margin: 0;
}

.inventory-filter-card__active,
.inventory-data-card__count {
  align-items: center;
  background: rgba(var(--bs-primary-rgb), .08);
  border: 1px solid rgba(var(--bs-primary-rgb), .14);
  border-radius: 999px;
  color: var(--bs-primary);
  display: inline-flex;
  font-size: .67rem;
  font-weight: 700;
  gap: .3rem;
  padding: .3rem .58rem;
  white-space: nowrap;
}

.inventory-search .input-group-text {
  background: var(--bs-body-bg);
  border-color: var(--bs-border-color);
  border-radius: .55rem 0 0 .55rem;
  border-right: 0;
  color: var(--bs-secondary-color);
  padding-left: .8rem;
  padding-right: .35rem;
}

.inventory-search :deep(.form-control) {
  border-left: 0;
  border-radius: 0 .55rem .55rem 0 !important;
  padding-left: .35rem;
}

.critical-filter {
  align-items: center;
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: .55rem;
  display: flex;
  justify-content: space-between;
  min-height: 2.55rem;
  padding: .48rem .7rem;
  transition: border-color .15s ease, background-color .15s ease;
}

.critical-filter > i {
  color: var(--bs-secondary-color);
  font-size: 1rem;
}

.critical-filter--active {
  background: rgba(var(--bs-warning-rgb), .09);
  border-color: rgba(var(--bs-warning-rgb), .32);
}

.critical-filter--active > i { color: rgb(var(--bs-warning-rgb)); }
.critical-filter :deep(.form-check-label) { font-size: .74rem; font-weight: 650; }

.inventory-filter-actions {
  align-items: center;
  border-top: 1px dashed var(--bs-border-color);
  display: flex;
  gap: .5rem;
  justify-content: flex-end;
  padding-top: .85rem;
}

.inventory-data-card :deep(.card-body) { padding: 0 !important; }
.inventory-data-card__header { padding: .95rem 1.15rem; }

.inventory-table { min-width: 1040px; }

.inventory-table :deep(tbody tr) {
  transition: background-color .15s ease, box-shadow .15s ease;
}

.inventory-table :deep(tbody td) { padding-block: .78rem; }

.inventory-item {
  align-items: center;
  display: flex;
  gap: .68rem;
  min-width: 205px;
}

.inventory-item__icon {
  align-items: center;
  background: rgba(var(--bs-primary-rgb), .085);
  border: 1px solid rgba(var(--bs-primary-rgb), .12);
  border-radius: .62rem;
  color: var(--bs-primary);
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1rem;
  height: 2.35rem;
  justify-content: center;
  width: 2.35rem;
}

.inventory-item strong {
  color: var(--bs-heading-color);
  display: block;
  font-size: .78rem;
  font-weight: 720;
  line-height: 1.25;
}

.inventory-item small {
  color: var(--bs-secondary-color);
  display: block;
  font-size: .66rem;
  margin-top: .18rem;
}

.inventory-category {
  background: var(--bs-tertiary-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 999px;
  color: var(--bs-body-color);
  display: inline-flex;
  font-size: .66rem;
  font-weight: 650;
  padding: .3rem .55rem;
  white-space: nowrap;
}

.stock-cell { min-width: 150px; }

.stock-cell__top {
  align-items: baseline;
  display: flex;
  gap: .32rem;
}

.stock-cell__top strong {
  color: var(--bs-heading-color);
  font-size: .9rem;
}

.stock-cell__top span,
.stock-cell small {
  color: var(--bs-secondary-color);
  font-size: .63rem;
}

.stock-cell__track {
  background: var(--bs-tertiary-bg);
  border-radius: 999px;
  height: .32rem;
  margin: .33rem 0 .25rem;
  overflow: hidden;
  width: 100%;
}

.stock-cell__bar {
  border-radius: inherit;
  display: block;
  height: 100%;
  min-width: .25rem;
}

.stock-cell__bar--success { background: var(--bs-success); }
.stock-cell__bar--warning { background: var(--bs-warning); }
.stock-cell__bar--danger { background: var(--bs-danger); }

.supplier-cell {
  align-items: center;
  color: var(--bs-body-color);
  display: flex;
  font-size: .71rem;
  gap: .4rem;
  max-width: 165px;
}

.supplier-cell i {
  color: var(--bs-secondary-color);
  flex: 0 0 auto;
  font-size: .95rem;
}

.expiry-cell {
  --expiry-color: var(--bs-secondary-rgb);
  align-items: center;
  display: flex;
  gap: .45rem;
  min-width: 95px;
}

.expiry-cell--success { --expiry-color: var(--bs-success-rgb); }
.expiry-cell--warning { --expiry-color: var(--bs-warning-rgb); }
.expiry-cell--danger { --expiry-color: var(--bs-danger-rgb); }

.expiry-cell__dot {
  background: rgb(var(--expiry-color));
  border-radius: 50%;
  box-shadow: 0 0 0 .22rem rgba(var(--expiry-color), .1);
  flex: 0 0 auto;
  height: .42rem;
  width: .42rem;
}

.expiry-cell strong {
  color: var(--bs-heading-color);
  display: block;
  font-size: .7rem;
  font-weight: 650;
  white-space: nowrap;
}

.expiry-cell small {
  color: rgb(var(--expiry-color));
  display: block;
  font-size: .61rem;
  margin-top: .12rem;
}

.expiry-cell--neutral small { color: var(--bs-secondary-color); }

.inventory-actions {
  display: inline-flex;
  gap: .3rem;
  justify-content: flex-end;
}

.inventory-actions :deep(.btn) {
  align-items: center;
  border: 1px solid var(--bs-border-color);
  color: var(--bs-secondary-color);
  display: inline-flex;
  height: 2rem;
  justify-content: center;
  padding: 0;
  width: 2rem;
}

.inventory-actions :deep(.btn:hover) {
  border-color: rgba(var(--bs-primary-rgb), .25);
  color: var(--bs-primary);
}

.inventory-actions :deep(.inventory-actions__danger:hover) {
  background: rgba(var(--bs-danger-rgb), .08);
  border-color: rgba(var(--bs-danger-rgb), .2);
  color: var(--bs-danger);
}

.inventory-pagination {
  align-items: center;
  border-top: 1px solid var(--bs-border-color);
  display: flex;
  justify-content: space-between;
  padding: .85rem 1.15rem .35rem;
}

.inventory-pagination > span {
  color: var(--bs-secondary-color);
  font-size: .68rem;
}

.inventory-pagination :deep(.pagination) { margin-bottom: .5rem; }

.supply-detail-note {
  background: var(--bs-tertiary-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: .72rem;
  color: var(--bs-body-color);
  font-size: .76rem;
  line-height: 1.55;
  min-height: 5.2rem;
  padding: .8rem;
  white-space: pre-line;
}

.supply-detail-photo {
  aspect-ratio: 16 / 10;
  border: 1px solid var(--bs-border-color);
  border-radius: .72rem;
  object-fit: cover;
  width: 100%;
}

@media (max-width: 1399.98px) {
  .inventory-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 767.98px) {
  .inventory-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .inventory-filter-actions { justify-content: stretch; }
  .inventory-filter-actions :deep(.btn) { flex: 1 1 auto; }
}

@media (max-width: 575.98px) {
  .inventory-metrics { grid-template-columns: 1fr; }
  .inventory-metric { min-height: 5.4rem; }
  .inventory-filter-card__header,
  .inventory-data-card__header { align-items: flex-start; }
  .inventory-filter-card__active,
  .inventory-data-card__count { font-size: .6rem; }
  .inventory-filter-actions { align-items: stretch; flex-direction: column; }
  .inventory-pagination { align-items: stretch; flex-direction: column; gap: .5rem; }
}
</style>
