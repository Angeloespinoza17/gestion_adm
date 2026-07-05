<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";

const emptyForm = () => ({
  id: null,
  maintenance_dependency_id: "",
  technical_area_id: "",
  inventory_item_id: "",
  dependency_component: "",
  reported_at: new Date().toISOString().slice(0, 10),
  requested_by: "",
  assigned_to: [],
  priority: "Media",
  status: "Sin comenzar",
  due_date: "",
  description: "",
  resolution_notes: "",
  photo_reference: "",
});

export default {
  components: { Layout, Multiselect },
  data() {
    return {
      debugModals: false,
      loading: false,
      saving: false,
      search: "",
      statusFilter: "",
      priorityFilter: "",
      assigneeFilter: "",
      sortMode: "created",
      showModalCrearOT: false,
      showModalDetalleOT: false,
      showModalTomarFoto: false,
      dependencySearch: "",
      selectedPhoto: null,
      activeWorkOrder: null,
      cameraStream: null,
      cameraError: null,
      detailPhotoLoading: false,
      detailPhotoError: null,
      workOrders: [],
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      catalogs: {
        priorities: ["Crítico", "Alta", "Media", "Baja"],
        statuses: ["Sin comenzar", "En proceso", "En espera", "Pausado", "Terminado", "Anulado"],
        dependencies: [],
        technical_areas: [],
        inventory_items: [],
        dependency_components: [],
        assignees: [],
        maintenance_assignees: [],
        requesters: ["Pedro Nahuelpan", "Angelo Espinoza", "Laura Davinson", "Jeaqueline Sandoval"],
        summary: {
          total: 0,
          open: 0,
          critical: 0,
          finished: 0,
        },
      },
      form: emptyForm(),
      error: null,
      success: null,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    summaryCards() {
      return [
        {
          label: "Total OT",
          value: this.catalogs.summary.total,
          detail: "Órdenes registradas",
          icon: "mdi-clipboard-text-outline",
          tone: "blue",
        },
        {
          label: "Abiertas",
          value: this.catalogs.summary.open,
          detail: "Pendientes o en curso",
          icon: "mdi-progress-clock",
          tone: "green",
        },
        {
          label: "Críticas",
          value: this.catalogs.summary.critical,
          detail: "Prioridad crítica",
          icon: "mdi-alert-outline",
          tone: "red",
        },
        {
          label: "Terminadas",
          value: this.catalogs.summary.finished,
          detail: "Trabajo cerrado",
          icon: "mdi-check-circle-outline",
          tone: "slate",
        },
      ];
    },
    activeFiltersCount() {
      return [this.search, this.statusFilter, this.priorityFilter, this.assigneeFilter].filter(Boolean).length;
    },
    assigneeOptions() {
      const catalog = this.catalogs.maintenance_assignees || [];

      if (catalog.length) {
        return catalog.map((assignee) => ({
          value: assignee.value || assignee.full_name,
          label: assignee.label || assignee.full_name,
        }));
      }

      return (this.catalogs.assignees || []).map((assignee) => ({
        value: assignee,
        label: assignee,
      }));
    },
    filteredInventoryItems() {
      const items = this.catalogs.inventory_items || [];

      if (!this.form.maintenance_dependency_id) {
        return items;
      }

      return items.filter((item) => Number(item.dependency_id) === Number(this.form.maintenance_dependency_id));
    },
    filteredTechnicalAreas() {
      const areas = this.catalogs.technical_areas || [];

      if (!this.form.maintenance_dependency_id) {
        return areas;
      }

      return areas.filter((area) => Number(area.parent_dependency_id) === Number(this.form.maintenance_dependency_id));
    },
  },
  mounted() {
    try {
      this.debugModals = localStorage.getItem("CNSC_DEBUG_MODALS") === "1";
    } catch (e) {
      this.debugModals = false;
    }
    this.debugLog("mounted", { debugModals: this.debugModals });
    this.loadCatalogs();
    this.loadWorkOrders();
  },
  beforeUnmount() {
    this.stopCameraStream();
  },
  methods: {
    selectedAssigneesLabel(values, select$ = null) {
      const selectedValues = Array.isArray(values)
        ? values
        : Array.isArray(select$?.value)
          ? select$.value
          : [];
      const labels = selectedValues
        .map((value) => {
          const rawValue = typeof value === "object" && value !== null ? value.value : value;
          return this.assigneeOptions.find((option) => option.value === rawValue)?.label || value?.label || rawValue;
        })
        .filter(Boolean);

      if (labels.length <= 2) {
        return labels.join(", ");
      }

      return `${labels.slice(0, 2).join(", ")} +${labels.length - 2}`;
    },
    debugLog(...args) {
      if (!this.debugModals) return;
      // eslint-disable-next-line no-console
      console.log("[CNSC][OT][modals]", ...args);
    },
    onModalEvent(modal, eventName) {
      this.debugLog("modal-event", modal, eventName);
    },
    async loadCatalogs() {
      const response = await axios.get("/api/maintenance/work-orders/catalogs");
      this.catalogs = response.data;
    },
    async loadWorkOrders(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/maintenance/work-orders", {
          params: {
            page,
            search: this.search,
            status: this.statusFilter,
            priority: this.priorityFilter,
            assignee: this.assigneeFilter,
            sort: this.sortMode,
          },
        });

        this.workOrders = response.data.data;
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
    async saveWorkOrder() {
      this.saving = true;
      this.error = null;
      this.success = null;

      try {
        const payload = new FormData();

        payload.append("maintenance_dependency_id", this.form.maintenance_dependency_id || "");
        payload.append("technical_area_id", this.form.technical_area_id || "");
        payload.append("inventory_item_id", this.form.inventory_item_id || "");
        payload.append("dependency_component", this.form.dependency_component || "");

        if (this.form.reported_at) {
          payload.append("reported_at", this.form.reported_at);
        }

        if (this.form.requested_by) {
          payload.append("requested_by", this.form.requested_by);
        }

        if (Array.isArray(this.form.assigned_to)) {
          this.form.assigned_to.forEach((assignee) => {
            payload.append("assigned_to[]", assignee);
          });
        }

        payload.append("priority", this.form.priority);
        payload.append("status", this.form.status);

        if (this.form.due_date) {
          payload.append("due_date", this.form.due_date);
        }

        payload.append("description", this.form.description);

        if (this.form.resolution_notes) {
          payload.append("resolution_notes", this.form.resolution_notes);
        }

        if (this.selectedPhoto) {
          payload.append("photo", this.selectedPhoto);
        }

        if (this.isEditing) {
          payload.append("_method", "PUT");
        }

        const response = await axios.post(
          this.isEditing ? `/api/maintenance/work-orders/${this.form.id}` : "/api/maintenance/work-orders",
          payload
        );

        this.success = response.data.message;
        this.showModalCrearOT = false;
        this.resetForm();
        await this.loadCatalogs();
        await this.loadWorkOrders(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    editWorkOrder(workOrder) {
      this.debugLog("editWorkOrder(click)", { id: workOrder?.id });
      this.error = null;
      this.success = null;
      const assigned = this.parseAssignees(workOrder.assigned_to);

      this.showModalDetalleOT = false;

      this.form = {
        ...emptyForm(),
        ...workOrder,
        maintenance_dependency_id: workOrder.maintenance_dependency_id || "",
        technical_area_id: workOrder.technical_area_id || workOrder.technical_area?.id || "",
        inventory_item_id: workOrder.inventory_item_id || workOrder.inventory_item?.id || "",
        dependency_component: workOrder.dependency_component || "",
        assigned_to: assigned,
        reported_at: this.formatInputDate(workOrder.reported_at),
        due_date: this.formatInputDate(workOrder.due_date),
      };

      this.dependencySearch = workOrder.dependency ? this.dependencyLabel(workOrder.dependency) : "";
      this.selectedPhoto = null;
      this.showModalCrearOT = true;
      this.debugLog("showModalCrearOT=true (edit)");
    },
    viewWorkOrder(workOrder) {
      this.debugLog("viewWorkOrder(click)", { id: workOrder?.id });
      this.activeWorkOrder = workOrder;
      this.detailPhotoError = null;
      this.detailPhotoLoading = Boolean(workOrder?.photo_url);
      this.showModalDetalleOT = true;
      this.debugLog("showModalDetalleOT=true (view)");
    },
    async deleteWorkOrder(workOrder) {
      if (!confirm(`¿Eliminar la OT #${workOrder.id}?`)) return;

      try {
        const response = await axios.delete(`/api/maintenance/work-orders/${workOrder.id}`);
        this.success = response.data.message;
        await this.loadCatalogs();
        await this.loadWorkOrders(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    resetForm() {
      this.form = emptyForm();
      this.dependencySearch = "";
      this.selectedPhoto = null;
    },
    formatInputDate(value) {
      return value ? String(value).slice(0, 10) : "";
    },
    formatDisplayDate(value) {
      if (!value) return "-";

      const parts = String(value).slice(0, 10).split("-");
      if (parts.length !== 3) return String(value);

      return `${parts[2]}-${parts[1]}-${parts[0]}`;
    },
    dependencyLabel(dependency) {
      if (!dependency) return "Sin dependencia";

      return `${dependency.code} · ${dependency.name}${dependency.is_reservable ? " · Reservable" : " · No reservable"}`;
    },
    inventoryItemLabel(item) {
      if (!item) return "Sin bien inventariado asociado";

      const dependency = item.dependency ? ` · ${item.dependency.code}` : "";
      const condition = item.condition ? ` · ${item.condition}` : "";

      return `${item.code} · ${item.name}${dependency}${condition}`;
    },
    technicalAreaLabel(area) {
      if (!area) return "Sin área técnica asociada";

      const parent = area.parent_dependency ? ` · ${area.parent_dependency.code}` : "";
      const sector = area.sector ? ` · ${area.sector}` : "";

      return `${area.code} · ${area.name}${parent}${sector}`;
    },
    syncDependencySelection() {
      const match = this.catalogs.dependencies.find(
        (dependency) => this.dependencyLabel(dependency) === this.dependencySearch
      );

      this.form.maintenance_dependency_id = match ? match.id : "";
      this.clearIncompatibleTechnicalArea();
      this.clearIncompatibleInventoryItem();
    },
    syncTechnicalAreaSelection() {
      const area = (this.catalogs.technical_areas || []).find(
        (technicalArea) => Number(technicalArea.id) === Number(this.form.technical_area_id)
      );

      if (!area) return;

      if (!this.form.maintenance_dependency_id && area.parent_dependency_id) {
        this.form.maintenance_dependency_id = area.parent_dependency_id;
        const dependency = (this.catalogs.dependencies || []).find(
          (catalogDependency) => Number(catalogDependency.id) === Number(area.parent_dependency_id)
        );
        this.dependencySearch = dependency ? this.dependencyLabel(dependency) : this.dependencySearch;
      }

      this.clearIncompatibleInventoryItem();
    },
    syncInventoryItemSelection() {
      const item = (this.catalogs.inventory_items || []).find(
        (inventoryItem) => Number(inventoryItem.id) === Number(this.form.inventory_item_id)
      );

      if (!item) return;

      if (!this.form.maintenance_dependency_id && item.dependency_id) {
        this.form.maintenance_dependency_id = item.dependency_id;
        const dependency = (this.catalogs.dependencies || []).find(
          (catalogDependency) => Number(catalogDependency.id) === Number(item.dependency_id)
        );
        this.dependencySearch = dependency ? this.dependencyLabel(dependency) : this.dependencySearch;
      }

      this.clearIncompatibleTechnicalArea();
    },
    clearIncompatibleTechnicalArea() {
      if (!this.form.technical_area_id || !this.form.maintenance_dependency_id) return;

      const area = (this.catalogs.technical_areas || []).find(
        (technicalArea) => Number(technicalArea.id) === Number(this.form.technical_area_id)
      );

      if (area?.parent_dependency_id && Number(area.parent_dependency_id) !== Number(this.form.maintenance_dependency_id)) {
        this.form.technical_area_id = "";
      }
    },
    clearIncompatibleInventoryItem() {
      if (!this.form.inventory_item_id || !this.form.maintenance_dependency_id) return;

      const item = (this.catalogs.inventory_items || []).find(
        (inventoryItem) => Number(inventoryItem.id) === Number(this.form.inventory_item_id)
      );

      if (item && Number(item.dependency_id) !== Number(this.form.maintenance_dependency_id)) {
        this.form.inventory_item_id = "";
      }
    },
    handlePhotoSelection(event) {
      const file = event.target?.files?.[0];
      this.selectedPhoto = file || null;
    },
    async startCameraCapture() {
      this.cameraError = null;
      if (!confirm("¿Quieres abrir la cámara para tomar una foto?")) return;

      const canUseWebcam =
        typeof navigator !== "undefined" &&
        navigator.mediaDevices &&
        typeof navigator.mediaDevices.getUserMedia === "function";

      if (!canUseWebcam) {
        // Fallback (principalmente móviles): abre cámara vía input capture.
        this.$refs.cameraInput?.click?.();
        return;
      }

      this.showModalTomarFoto = true;

      await this.$nextTick();
      await this.openCameraStream();
    },
    async openCameraStream() {
      try {
        this.stopCameraStream();
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: "environment" } },
          audio: false,
        });
        this.cameraStream = stream;
        const video = this.$refs.cameraVideo;
        if (video) {
          video.srcObject = stream;
          await video.play();
        }
      } catch (error) {
        this.cameraError = error?.message || "No se pudo acceder a la cámara.";
      }
    },
    stopCameraStream() {
      if (!this.cameraStream) return;
      this.cameraStream.getTracks().forEach((track) => track.stop());
      this.cameraStream = null;
    },
    async capturePhotoFromCamera() {
      const video = this.$refs.cameraVideo;
      const canvas = this.$refs.cameraCanvas;
      if (!video || !canvas) return;

      const width = video.videoWidth || 1280;
      const height = video.videoHeight || 720;
      canvas.width = width;
      canvas.height = height;
      const context = canvas.getContext("2d");
      context.drawImage(video, 0, 0, width, height);

      const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.9));
      if (!blob) return;

      const filename = `ot-foto-${Date.now()}.jpg`;
      this.selectedPhoto = new File([blob], filename, { type: "image/jpeg" });

      this.stopCameraStream();
      this.showModalTomarFoto = false;
    },
    onHideCameraModal() {
      this.stopCameraStream();
    },
    onDetailPhotoLoaded() {
      this.detailPhotoLoading = false;
      this.detailPhotoError = null;
    },
    onDetailPhotoError() {
      this.detailPhotoLoading = false;
      this.detailPhotoError = "No se pudo cargar la foto. Puede que el archivo no exista o que falte el enlace de almacenamiento.";
    },
    resolvePhotoUrl(url) {
      if (!url) return null;
      const value = String(url);
      if (value.startsWith("http://") || value.startsWith("https://")) return value;
      if (value.startsWith("//")) return value;
      if (value.startsWith("/")) return value;
      return `/${value}`;
    },
    openCreateModal() {
      this.debugLog("openCreateModal(click)");
      this.error = null;
      this.success = null;
      this.resetForm();
      this.showModalCrearOT = true;
      this.debugLog("showModalCrearOT=true (create)");
    },
    toggleSortMode() {
      this.sortMode = this.sortMode === "created" ? "priority" : "created";
      this.loadWorkOrders();
    },
    resetFilters() {
      this.search = "";
      this.statusFilter = "";
      this.priorityFilter = "";
      this.assigneeFilter = "";
      this.loadWorkOrders();
    },
    parseAssignees(value) {
      if (!value) return [];

      return String(value)
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);
    },
    workOrderAssigneeList(workOrder) {
      return this.parseAssignees(workOrder?.assigned_to);
    },
    workOrderLocation(workOrder) {
      if (workOrder.dependency) {
        return `${workOrder.dependency.code} · ${workOrder.dependency.name}`;
      }

      const location = [
        workOrder.location_code,
        workOrder.location_distribution,
        workOrder.location_sector,
        workOrder.location_name,
        workOrder.location_usage,
      ].filter(Boolean);

      return location.length ? location.join(" · ") : "Sin dependencia";
    },
    workOrderDependencyComponent(workOrder) {
      return workOrder.dependency_component || "-";
    },
    workOrderFocusItems(workOrder) {
      const items = [];

      if (workOrder.dependency_component) {
        items.push({ label: "Elemento", value: workOrder.dependency_component, tone: "amber" });
      }

      if (workOrder.technical_area) {
        items.push({ label: "Área", value: `${workOrder.technical_area.code} · ${workOrder.technical_area.name}`, tone: "blue" });
      }

      if (workOrder.inventory_item) {
        items.push({ label: "Bien", value: `${workOrder.inventory_item.code} · ${workOrder.inventory_item.name}`, tone: "green" });
      }

      if (items.length === 0) {
        items.push({ label: "Dependencia", value: "General", tone: "slate" });
      }

      return items;
    },
    workOrderInventoryItem(workOrder) {
      if (!workOrder.inventory_item) return "-";

      return `${workOrder.inventory_item.code} · ${workOrder.inventory_item.name}`;
    },
    workOrderTechnicalArea(workOrder) {
      if (!workOrder.technical_area) return "-";

      return `${workOrder.technical_area.code} · ${workOrder.technical_area.name}`;
    },
    priorityClass(priority) {
      return {
        "Crítico": "work-order-pill--critical",
        "Alta": "work-order-pill--high",
        "Media": "work-order-pill--medium",
        "Baja": "work-order-pill--low",
      }[priority] || "work-order-pill--neutral";
    },
    statusClass(status) {
      return {
        "Sin comenzar": "work-order-pill--neutral",
        "En proceso": "work-order-pill--active",
        "En espera": "work-order-pill--waiting",
        "Pausado": "work-order-pill--waiting",
        "Terminado": "work-order-pill--done",
        "Anulado": "work-order-pill--cancelled",
      }[status] || "work-order-pill--neutral";
    },
    formatError(error) {
      const errors = error.response?.data?.errors;

      if (errors) {
        return Object.values(errors).flat().join(" ");
      }

      return error.response?.data?.message || error.message || "Error desconocido";
    },
  },
  watch: {
    showModalCrearOT(value) {
      this.debugLog("watch showModalCrearOT", value);
    },
    showModalDetalleOT(value) {
      this.debugLog("watch showModalDetalleOT", value);
    },
    showModalTomarFoto(value) {
      this.debugLog("watch showModalTomarFoto", value);
    },
  },
};
</script>

<template>
  <Layout>
    <div class="work-orders-page">
      <div class="work-orders-header">
        <div>
          <div class="work-orders-eyebrow">Mantención</div>
          <h4>Órdenes de trabajo</h4>
          <p>Seguimiento operativo de solicitudes, responsables, prioridades y cierres.</p>
        </div>
        <div class="work-orders-header-actions">
          <button class="work-order-secondary-button" type="button" @click="toggleSortMode">
            <i class="mdi mdi-sort"></i>
            {{ sortMode === "created" ? "Prioridad" : "Fecha" }}
          </button>
          <button class="work-order-primary-button" type="button" @click="openCreateModal()">
            <i class="mdi mdi-plus"></i>
            Nueva OT
          </button>
        </div>
      </div>

      <div class="work-order-summary-grid">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="work-order-summary-card"
          :class="`work-order-summary-card--${card.tone}`"
        >
          <div class="work-order-summary-icon">
            <i :class="`mdi ${card.icon}`"></i>
          </div>
          <div class="work-order-summary-content">
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
            <small>{{ card.detail }}</small>
          </div>
        </div>
      </div>

      <section class="work-order-panel">
        <div class="work-order-panel-head">
          <div>
            <span class="work-orders-eyebrow">Listado</span>
            <h5>Órdenes registradas</h5>
          </div>
          <div class="work-order-filter-count" :class="{ 'is-active': activeFiltersCount > 0 }">
            {{ activeFiltersCount }} filtros
          </div>
        </div>

        <div class="work-order-filters">
          <label class="work-order-filter-field work-order-filter-field--search">
            <span>Búsqueda</span>
            <input v-model="search" type="search" placeholder="Buscar OT, dependencia, elemento..." @keyup.enter="loadWorkOrders()" />
          </label>
          <label class="work-order-filter-field">
            <span>Prioridad</span>
            <select v-model="priorityFilter">
              <option value="">Todas</option>
              <option v-for="priority in catalogs.priorities" :key="priority" :value="priority">{{ priority }}</option>
            </select>
          </label>
          <label class="work-order-filter-field">
            <span>Estado</span>
            <select v-model="statusFilter">
              <option value="">Todos</option>
              <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
            </select>
          </label>
          <label class="work-order-filter-field">
            <span>Asignado</span>
            <select v-model="assigneeFilter">
              <option value="">Todos</option>
              <option v-for="assignee in assigneeOptions" :key="assignee.value" :value="assignee.value">{{ assignee.label }}</option>
            </select>
          </label>
          <div class="work-order-filter-actions">
            <button class="work-order-primary-button" type="button" @click="loadWorkOrders()">
              <i class="mdi mdi-filter-outline"></i>
              Filtrar
            </button>
            <button class="work-order-secondary-button" type="button" @click="resetFilters()">
              Limpiar
            </button>
          </div>
        </div>

        <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
        <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>

        <div class="work-order-table-wrap">
          <table class="work-order-table">
            <colgroup>
              <col class="work-order-col-date" />
              <col class="work-order-col-order" />
              <col class="work-order-col-location" />
              <col class="work-order-col-focus" />
              <col class="work-order-col-assignee" />
              <col class="work-order-col-priority" />
              <col class="work-order-col-status" />
              <col class="work-order-col-due" />
              <col class="work-order-col-actions" />
            </colgroup>
            <thead>
              <tr>
                <th class="work-order-col-date">Agregado</th>
                <th class="work-order-col-order">OT</th>
                <th class="work-order-col-location">Dependencia</th>
                <th class="work-order-col-focus">Foco de trabajo</th>
                <th class="work-order-col-assignee">Asignado</th>
                <th class="work-order-col-priority">Prioridad</th>
                <th class="work-order-col-status">Estado</th>
                <th class="work-order-col-due">Límite</th>
                <th class="work-order-col-actions">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="9">
                  <div class="work-order-empty-state">Cargando órdenes...</div>
                </td>
              </tr>
              <tr v-else-if="workOrders.length === 0">
                <td colspan="9">
                  <div class="work-order-empty-state">No hay órdenes de trabajo registradas.</div>
                </td>
              </tr>
              <tr v-for="workOrder in workOrders" :key="workOrder.id">
                <td class="work-order-date-cell">{{ formatDisplayDate(workOrder.created_at) }}</td>
                <td>
                  <div class="work-order-title">#{{ workOrder.id }}</div>
                  <div class="work-order-description">{{ workOrder.description }}</div>
                </td>
                <td>
                  <div class="work-order-location">{{ workOrderLocation(workOrder) }}</div>
                </td>
                <td>
                  <div class="work-order-focus-list">
                    <span
                      v-for="focus in workOrderFocusItems(workOrder)"
                      :key="`${workOrder.id}-${focus.label}-${focus.value}`"
                      class="work-order-focus-chip"
                      :class="`work-order-focus-chip--${focus.tone}`"
                    >
                      <small>{{ focus.label }}</small>
                      {{ focus.value }}
                    </span>
                  </div>
                </td>
                <td>
                  <div class="work-order-assignee">{{ workOrder.assigned_to || "-" }}</div>
                </td>
                <td><span class="work-order-pill" :class="priorityClass(workOrder.priority)">{{ workOrder.priority }}</span></td>
                <td><span class="work-order-pill" :class="statusClass(workOrder.status)">{{ workOrder.status }}</span></td>
                <td class="work-order-date-cell work-order-due-cell">{{ formatDisplayDate(workOrder.due_date) }}</td>
                <td class="work-order-actions-cell">
                  <div class="work-order-actions">
                    <button class="work-order-icon-button" type="button" title="Ver" @click="viewWorkOrder(workOrder)">
                      <i class="mdi mdi-eye-outline"></i>
                    </button>
                    <button class="work-order-icon-button work-order-icon-button--edit" type="button" title="Editar" @click="editWorkOrder(workOrder)">
                      <i class="mdi mdi-pencil-outline"></i>
                    </button>
                    <button class="work-order-icon-button work-order-icon-button--danger" type="button" title="Eliminar" @click="deleteWorkOrder(workOrder)">
                      <i class="mdi mdi-trash-can-outline"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="work-order-pagination">
          <span>Total: {{ pagination.total }}</span>
          <div class="work-order-pagination-actions">
            <button type="button" :disabled="pagination.current_page <= 1" @click="loadWorkOrders(pagination.current_page - 1)">
              Anterior
            </button>
            <span class="work-order-page-current">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadWorkOrders(pagination.current_page + 1)">
              Siguiente
            </button>
          </div>
        </div>
      </section>
    </div>

    <BModal
      ref="crearOTModal"
      v-model="showModalCrearOT"
      :title="isEditing ? 'Editar OT' : 'Nueva OT'"
      title-class="work-order-modal-title"
      header-class="work-order-modal-header"
      body-class="work-order-modal-body p-0"
      modal-class="work-order-modal"
      size="xl"
      scrollable
      hide-footer
      centered
      teleport-to="body"
      lazy
      no-fade
      @show="onModalEvent('crearOT', 'show')"
      @shown="onModalEvent('crearOT', 'shown')"
      @hide="onModalEvent('crearOT', 'hide')"
      @hidden="onModalEvent('crearOT', 'hidden')"
    >
      <form class="work-order-form" @submit.prevent="saveWorkOrder">
        <div class="work-order-modal-scroll">
          <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
          <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>

          <section class="work-order-form-section">
            <div class="work-order-form-section-head">
              <i class="mdi mdi-map-marker-outline"></i>
              <div>
                <h6>Alcance</h6>
                <span>Dependencia, elemento y activos relacionados</span>
              </div>
            </div>

            <div class="work-order-form-grid work-order-form-grid--two">
              <label class="work-order-form-field work-order-form-field--wide">
                <span>Dependencia</span>
                <input
                  v-model="dependencySearch"
                  type="text"
                  list="dependency-options"
                  class="work-order-form-control"
                  @change="syncDependencySelection"
                />
                <datalist id="dependency-options">
                  <option v-for="dependency in catalogs.dependencies" :key="dependency.id" :value="dependencyLabel(dependency)" />
                </datalist>
              </label>

              <label class="work-order-form-field">
                <span>Elemento</span>
                <input
                  v-model="form.dependency_component"
                  type="text"
                  class="work-order-form-control"
                  list="dependency-component-options"
                  placeholder="Ventana, puerta, pared..."
                />
                <datalist id="dependency-component-options">
                  <option v-for="component in catalogs.dependency_components" :key="component" :value="component" />
                </datalist>
              </label>

              <label class="work-order-form-field">
                <span>Área técnica</span>
                <select v-model="form.technical_area_id" class="work-order-form-control" @change="syncTechnicalAreaSelection">
                  <option value="">Sin área técnica asociada</option>
                  <option v-for="area in filteredTechnicalAreas" :key="area.id" :value="area.id">
                    {{ technicalAreaLabel(area) }}
                  </option>
                </select>
              </label>

              <label class="work-order-form-field">
                <span>Bien inventariado</span>
                <select v-model="form.inventory_item_id" class="work-order-form-control" @change="syncInventoryItemSelection">
                  <option value="">Sin bien inventariado asociado</option>
                  <option v-for="item in filteredInventoryItems" :key="item.id" :value="item.id">
                    {{ inventoryItemLabel(item) }}
                  </option>
                </select>
              </label>
            </div>
          </section>

          <section class="work-order-form-section">
            <div class="work-order-form-section-head">
              <i class="mdi mdi-calendar-clock"></i>
              <div>
                <h6>Planificación</h6>
                <span>Fechas, prioridad, estado y responsables</span>
              </div>
            </div>

            <div class="work-order-form-grid work-order-form-grid--three">
              <label class="work-order-form-field">
                <span>Fecha ingreso</span>
                <input v-model="form.reported_at" type="date" class="work-order-form-control" />
              </label>

              <label class="work-order-form-field">
                <span>Fecha límite</span>
                <input v-model="form.due_date" type="date" class="work-order-form-control" />
              </label>

              <label class="work-order-form-field">
                <span>Prioridad</span>
                <select v-model="form.priority" class="work-order-form-control" required>
                  <option v-for="priority in catalogs.priorities" :key="priority" :value="priority">{{ priority }}</option>
                </select>
              </label>

              <label class="work-order-form-field">
                <span>Estado</span>
                <select v-model="form.status" class="work-order-form-control" required>
                  <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
                </select>
              </label>

              <label class="work-order-form-field">
                <span>Quién asigna</span>
                <select v-model="form.requested_by" class="work-order-form-control" required>
                  <option value="">Selecciona...</option>
                  <option v-for="requester in catalogs.requesters" :key="requester" :value="requester">{{ requester }}</option>
                </select>
              </label>

              <label class="work-order-form-field">
                <span>Asignación</span>
                <Multiselect
                  class="work-order-multiselect"
                  v-model="form.assigned_to"
                  :options="assigneeOptions"
                  mode="multiple"
                  :multiple-label="selectedAssigneesLabel"
                  :close-on-select="false"
                  :searchable="true"
                  :append-to-body="false"
                  :style="{ '--ms-max-height': '220px' }"
                />
              </label>
            </div>
          </section>

          <section class="work-order-form-section">
            <div class="work-order-form-section-head">
              <i class="mdi mdi-file-document-edit-outline"></i>
              <div>
                <h6>Trabajo</h6>
                <span>Solicitud, resolución y respaldo visual</span>
              </div>
            </div>

            <div class="work-order-form-grid">
              <label class="work-order-form-field work-order-form-field--wide">
                <span>Trabajo solicitado</span>
                <textarea v-model="form.description" class="work-order-form-control" rows="4" required></textarea>
              </label>

              <label class="work-order-form-field work-order-form-field--wide">
                <span>Notas de cierre / resolución</span>
                <textarea v-model="form.resolution_notes" class="work-order-form-control" rows="3"></textarea>
              </label>

              <div class="work-order-photo-field">
                <span>Foto</span>
                <div class="work-order-photo-actions">
                  <button class="work-order-secondary-button" type="button" @click="startCameraCapture">
                    <i class="mdi mdi-camera-outline"></i>
                    Tomar foto
                  </button>
                  <input ref="cameraInput" type="file" class="d-none" accept="image/*" capture="environment" @change="handlePhotoSelection" />

                  <label class="work-order-secondary-button mb-0">
                    <i class="mdi mdi-paperclip"></i>
                    Adjuntar
                    <input type="file" class="d-none" accept="image/*" @change="handlePhotoSelection" />
                  </label>
                  <span class="work-order-photo-name">{{ selectedPhoto ? selectedPhoto.name : "Sin archivo seleccionado" }}</span>
                </div>
                <small v-if="cameraError" class="text-danger d-block mt-1">{{ cameraError }}</small>
              </div>
            </div>
          </section>
        </div>

        <div class="work-order-modal-footer">
          <button class="work-order-secondary-button" type="button" @click="showModalCrearOT = false">Cancelar</button>
          <button class="work-order-primary-button" type="submit" :disabled="saving">
            {{ saving ? "Guardando..." : isEditing ? "Actualizar OT" : "Crear OT" }}
          </button>
        </div>
      </form>
    </BModal>

    <BModal
      ref="detalleOTModal"
      v-model="showModalDetalleOT"
      title="Detalle de OT"
      title-class="work-order-modal-title"
      header-class="work-order-modal-header"
      body-class="work-order-modal-body p-0"
      modal-class="work-order-modal work-order-detail-modal"
      size="xl"
      scrollable
      hide-footer
      centered
      teleport-to="body"
      lazy
      no-fade
      @show="onModalEvent('detalleOT', 'show')"
      @shown="onModalEvent('detalleOT', 'shown')"
      @hide="onModalEvent('detalleOT', 'hide')"
      @hidden="onModalEvent('detalleOT', 'hidden')"
    >
      <div v-if="activeWorkOrder" class="work-order-detail">
        <div class="work-order-detail-scroll">
          <section class="work-order-detail-hero">
            <div class="work-order-detail-hero-main">
              <span class="work-orders-eyebrow">Orden de trabajo</span>
              <div class="work-order-detail-heading">
                <h3>OT #{{ activeWorkOrder.id }}</h3>
                <div class="work-order-detail-pills">
                  <span class="work-order-pill" :class="priorityClass(activeWorkOrder.priority)">
                    {{ activeWorkOrder.priority }}
                  </span>
                  <span class="work-order-pill" :class="statusClass(activeWorkOrder.status)">
                    {{ activeWorkOrder.status }}
                  </span>
                </div>
              </div>
              <p>{{ activeWorkOrder.description }}</p>
            </div>

            <div class="work-order-detail-facts">
              <div>
                <span>Ingresada</span>
                <strong>{{ formatDisplayDate(activeWorkOrder.reported_at || activeWorkOrder.created_at) }}</strong>
              </div>
              <div>
                <span>Límite</span>
                <strong>{{ formatDisplayDate(activeWorkOrder.due_date) }}</strong>
              </div>
              <div>
                <span>Creada</span>
                <strong>{{ formatDisplayDate(activeWorkOrder.created_at) }}</strong>
              </div>
            </div>
          </section>

          <section class="work-order-detail-section">
            <div class="work-order-detail-section-head">
              <i class="mdi mdi-map-marker-outline"></i>
              <div>
                <h6>Ubicación y foco</h6>
                <span>Dependencia, elemento o activo relacionado</span>
              </div>
            </div>

            <div class="work-order-detail-grid work-order-detail-grid--four">
              <div class="work-order-detail-field">
                <span>Dependencia</span>
                <strong>{{ workOrderLocation(activeWorkOrder) }}</strong>
              </div>
              <div class="work-order-detail-field">
                <span>Elemento</span>
                <strong>{{ workOrderDependencyComponent(activeWorkOrder) }}</strong>
              </div>
              <div class="work-order-detail-field">
                <span>Área técnica</span>
                <strong>{{ workOrderTechnicalArea(activeWorkOrder) }}</strong>
              </div>
              <div class="work-order-detail-field">
                <span>Bien inventariado</span>
                <strong>{{ workOrderInventoryItem(activeWorkOrder) }}</strong>
              </div>
            </div>

            <div class="work-order-detail-focus">
              <span
                v-for="item in workOrderFocusItems(activeWorkOrder)"
                :key="`${activeWorkOrder.id}-${item.label}-${item.value}`"
                class="work-order-focus-chip"
                :class="`work-order-focus-chip--${item.tone}`"
              >
                <small>{{ item.label }}</small>
                {{ item.value }}
              </span>
            </div>
          </section>

          <section class="work-order-detail-section">
            <div class="work-order-detail-section-head">
              <i class="mdi mdi-account-hard-hat-outline"></i>
              <div>
                <h6>Responsables</h6>
                <span>Asignación y origen de la solicitud</span>
              </div>
            </div>

            <div class="work-order-detail-grid work-order-detail-grid--two">
              <div class="work-order-detail-field">
                <span>Quién asigna</span>
                <strong>{{ activeWorkOrder.requested_by || "-" }}</strong>
              </div>
              <div class="work-order-detail-field">
                <span>Asignados</span>
                <div v-if="workOrderAssigneeList(activeWorkOrder).length" class="work-order-detail-assignees">
                  <span v-for="assignee in workOrderAssigneeList(activeWorkOrder)" :key="assignee">
                    {{ assignee }}
                  </span>
                </div>
                <strong v-else>-</strong>
              </div>
            </div>
          </section>

          <section class="work-order-detail-section">
            <div class="work-order-detail-section-head">
              <i class="mdi mdi-file-document-edit-outline"></i>
              <div>
                <h6>Trabajo</h6>
                <span>Solicitud y resolución registrada</span>
              </div>
            </div>

            <div class="work-order-detail-notes">
              <div>
                <span>Trabajo solicitado</span>
                <p>{{ activeWorkOrder.description }}</p>
              </div>
              <div>
                <span>Notas de cierre / resolución</span>
                <p>{{ activeWorkOrder.resolution_notes || "Sin notas de cierre registradas." }}</p>
              </div>
            </div>
          </section>

          <section class="work-order-detail-section">
            <div class="work-order-detail-section-head">
              <i class="mdi mdi-camera-outline"></i>
              <div>
                <h6>Foto</h6>
                <span>Respaldo visual de la orden</span>
              </div>
            </div>

            <div v-if="activeWorkOrder.photo_url || activeWorkOrder.photo_reference" class="work-order-detail-photo">
              <div v-if="detailPhotoError" class="work-order-detail-alert">
                <i class="mdi mdi-alert-circle-outline"></i>
                <div>
                  {{ detailPhotoError }}
                  <a
                    v-if="resolvePhotoUrl(activeWorkOrder.photo_url)"
                    :href="resolvePhotoUrl(activeWorkOrder.photo_url)"
                    target="_blank"
                    rel="noopener"
                  >
                    Abrir enlace de la foto
                  </a>
                </div>
              </div>

              <div v-if="activeWorkOrder.photo_url && !detailPhotoError" class="work-order-detail-photo-preview">
                <div v-if="detailPhotoLoading" class="work-order-detail-photo-loading">
                  <div class="spinner-border text-light" role="status"></div>
                </div>
                <img
                  :src="resolvePhotoUrl(activeWorkOrder.photo_url)"
                  alt="Foto OT"
                  @load="onDetailPhotoLoaded"
                  @error="onDetailPhotoError"
                />
                <div class="work-order-detail-photo-actions">
                  <a
                    class="work-order-secondary-button"
                    :href="resolvePhotoUrl(activeWorkOrder.photo_url)"
                    target="_blank"
                    rel="noopener"
                  >
                    <i class="mdi mdi-open-in-new"></i>
                    Ver
                  </a>
                  <a class="work-order-secondary-button" :href="resolvePhotoUrl(activeWorkOrder.photo_url)" download>
                    <i class="mdi mdi-download-outline"></i>
                    Descargar
                  </a>
                </div>
              </div>

              <div v-else-if="activeWorkOrder.photo_reference && !activeWorkOrder.photo_url" class="work-order-detail-empty">
                Hay una referencia de foto, pero no hay URL disponible.
              </div>
            </div>
            <div v-else class="work-order-detail-empty">
              Esta OT no tiene foto adjunta.
            </div>
          </section>
        </div>

        <div class="work-order-detail-footer">
          <button class="work-order-secondary-button" type="button" @click="showModalDetalleOT = false">
            Cerrar
          </button>
          <button class="work-order-warning-button" type="button" @click="editWorkOrder(activeWorkOrder)">
            <i class="mdi mdi-pencil-outline"></i>
            Editar
          </button>
        </div>
      </div>
      <div v-else class="work-order-detail-empty work-order-detail-empty--modal">
        Selecciona una OT para ver el detalle.
      </div>
    </BModal>

    <BModal
      v-model="showModalTomarFoto"
      title="Tomar foto"
      title-class="font-18"
      body-class="p-3"
      size="lg"
      hide-footer
      centered
      teleport-to="body"
      lazy
      no-fade
      @hide="onHideCameraModal"
      @show="onModalEvent('tomarFoto', 'show')"
      @shown="onModalEvent('tomarFoto', 'shown')"
      @hidden="onModalEvent('tomarFoto', 'hidden')"
    >
      <div v-if="cameraError" class="alert alert-danger mb-3">{{ cameraError }}</div>

      <div class="ratio ratio-16x9 bg-dark rounded overflow-hidden">
        <video ref="cameraVideo" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover"></video>
      </div>
      <canvas ref="cameraCanvas" class="d-none"></canvas>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <button class="btn btn-light" type="button" @click="showModalTomarFoto = false">Cancelar</button>
        <button class="btn btn-primary" type="button" :disabled="!!cameraError" @click="capturePhotoFromCamera">
          Capturar
        </button>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.work-orders-page {
  padding: 4px 0 24px;
}

.work-orders-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding: 18px 0 16px;
  border-bottom: 1px solid #e3ebfb;
  margin-bottom: 22px;
}

.work-orders-eyebrow {
  display: block;
  color: #6d7690;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0;
  text-transform: uppercase;
  line-height: 1.2;
}

.work-orders-header h4,
.work-order-panel-head h5 {
  margin: 4px 0 0;
  color: #303848;
  font-weight: 700;
  letter-spacing: 0;
}

.work-orders-header p {
  margin: 8px 0 0;
  color: #717b94;
  font-size: 15px;
  font-weight: 400;
}

.work-orders-header-actions,
.work-order-filter-actions,
.work-order-photo-actions,
.work-order-actions,
.work-order-pagination-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.work-order-primary-button,
.work-order-secondary-button,
.work-order-warning-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 0 18px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-weight: 600;
  font-size: 14px;
  line-height: 1;
  transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
  cursor: pointer;
}

.work-order-primary-button {
  background: #5b74df;
  color: #fff;
  border-color: #5b74df;
}

.work-order-primary-button:hover {
  background: #4f66ca;
  border-color: #4f66ca;
  color: #fff;
}

.work-order-primary-button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.work-order-secondary-button {
  background: #fff;
  color: #566079;
  border-color: #b9c3d8;
}

.work-order-secondary-button:hover {
  background: #f5f7fb;
  border-color: #8d99b2;
  color: #384154;
}

.work-order-warning-button {
  background: #f6b73c;
  color: #fff;
  border-color: #f6b73c;
}

.work-order-warning-button:hover {
  background: #e7a72e;
  border-color: #e7a72e;
  color: #fff;
}

.work-order-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.work-order-summary-card {
  display: grid;
  grid-template-columns: 46px minmax(0, 1fr);
  gap: 14px;
  align-items: center;
  min-height: 116px;
  padding: 20px;
  border: 1px solid #dfebfb;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.76);
  box-shadow: 0 18px 42px rgba(63, 84, 120, 0.06);
}

.work-order-summary-icon {
  width: 46px;
  height: 46px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.work-order-summary-content {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.work-order-summary-content span {
  color: #6d7690;
  font-size: 14px;
  font-weight: 600;
}

.work-order-summary-content strong {
  color: #303848;
  font-size: 28px;
  line-height: 1;
  font-weight: 700;
}

.work-order-summary-content small {
  color: #7b849c;
  font-size: 13px;
  font-weight: 400;
}

.work-order-summary-card--blue .work-order-summary-icon {
  color: #3152c9;
  background: #eef4ff;
}

.work-order-summary-card--green .work-order-summary-icon {
  color: #047857;
  background: #ecfdf5;
}

.work-order-summary-card--red .work-order-summary-icon {
  color: #b91c1c;
  background: #fef2f2;
}

.work-order-summary-card--slate .work-order-summary-icon {
  color: #475569;
  background: #f8fafc;
}

.work-order-panel {
  padding: 22px;
  border: 1px solid #dfebfb;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.82);
  box-shadow: 0 18px 44px rgba(63, 84, 120, 0.06);
}

.work-order-panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.work-order-filter-count {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  color: #647089;
  background: #f4f7fb;
  border: 1px solid #dce5f4;
  font-size: 13px;
  font-weight: 600;
}

.work-order-filter-count.is-active {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.work-order-filters {
  display: grid;
  grid-template-columns: minmax(260px, 1.7fr) minmax(150px, 0.8fr) minmax(150px, 0.8fr) minmax(170px, 0.9fr) auto;
  gap: 12px;
  align-items: end;
  margin-bottom: 18px;
}

.work-order-filter-field,
.work-order-form-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
  margin: 0;
}

.work-order-filter-field span,
.work-order-form-field span,
.work-order-photo-field > span {
  color: #4c5568;
  font-size: 13px;
  line-height: 1.2;
  font-weight: 600;
}

.work-order-filter-field input,
.work-order-filter-field select,
.work-order-form-control {
  width: 100%;
  min-height: 44px;
  border: 1px solid #dce5f4;
  border-radius: 8px;
  background: #fff;
  color: #303848;
  padding: 0 14px;
  font-size: 14px;
  font-weight: 400;
  outline: none;
}

.work-order-form-control {
  min-height: 46px;
}

textarea.work-order-form-control {
  padding-top: 12px;
  resize: vertical;
}

.work-order-filter-field input:focus,
.work-order-filter-field select:focus,
.work-order-form-control:focus {
  border-color: #9db1f8;
  box-shadow: 0 0 0 3px rgba(91, 116, 223, 0.12);
}

.work-order-filter-actions {
  justify-content: flex-end;
}

.work-order-table-wrap {
  overflow-x: auto;
  border-top: 1px solid #e2eaf8;
}

.work-order-table {
  width: 100%;
  min-width: 1520px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}

.work-order-table th {
  padding: 18px 14px;
  color: #727b92;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
  text-align: left;
  border-bottom: 1px solid #dce7f7;
}

.work-order-table td {
  padding: 18px 14px;
  color: #364154;
  font-size: 14px;
  font-weight: 400;
  vertical-align: middle;
  border-bottom: 1px solid #e5edf9;
}

.work-order-col-date {
  width: 116px;
}

.work-order-col-due {
  width: 136px;
}

.work-order-col-order {
  width: 210px;
}

.work-order-col-location {
  width: 210px;
}

.work-order-col-focus {
  width: 250px;
}

.work-order-col-assignee {
  width: 150px;
}

.work-order-col-priority,
.work-order-col-status {
  width: 120px;
  text-align: center;
}

.work-order-col-due {
  text-align: center;
}

.work-order-col-actions {
  width: 176px;
  text-align: center;
}

.work-order-date-cell {
  color: #67718a;
  text-align: center;
  white-space: nowrap;
}

.work-order-due-cell {
  overflow: hidden;
  text-overflow: clip;
}

.work-order-actions-cell {
  text-align: center;
}

.work-order-title {
  color: #263042;
  font-weight: 700;
  line-height: 1.2;
}

.work-order-description,
.work-order-location,
.work-order-assignee {
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  color: #68728b;
  font-size: 13px;
  line-height: 1.35;
}

.work-order-focus-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.work-order-focus-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 100%;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 12px;
  line-height: 1.2;
  font-weight: 600;
}

.work-order-focus-chip small {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
}

.work-order-focus-chip--blue {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.work-order-focus-chip--green {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.work-order-focus-chip--amber {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.work-order-focus-chip--slate {
  color: #475569;
  background: #f8fafc;
  border-color: #d7dee9;
}

.work-order-pill {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  min-width: 86px;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
}

.work-order-pill--critical,
.work-order-pill--cancelled {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

.work-order-pill--high {
  color: #be123c;
  background: #fff1f2;
  border-color: #fecdd3;
}

.work-order-pill--medium,
.work-order-pill--waiting {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.work-order-pill--low {
  color: #0369a1;
  background: #f0f9ff;
  border-color: #bae6fd;
}

.work-order-pill--active {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.work-order-pill--done {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.work-order-pill--neutral {
  color: #475569;
  background: #f8fafc;
  border-color: #d7dee9;
}

.work-order-actions {
  justify-content: center;
  gap: 10px;
  min-width: 146px;
}

.work-order-actions .cnsc-action-btn + .cnsc-action-btn {
  margin-left: 0 !important;
}

.work-order-icon-button {
  width: 42px;
  height: 42px;
  border-radius: 8px;
  border: 1px solid #cfd8ea;
  background: #fff;
  color: #647089;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 42px;
  font-size: 20px;
}

.work-order-icon-button--edit {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.work-order-icon-button--danger {
  color: #dc2626;
  background: #fff7f7;
  border-color: #fecaca;
}

.work-order-empty-state {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 90px;
  color: #7a849a;
  font-weight: 500;
}

.work-order-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 16px;
  color: #717b94;
  font-size: 13px;
  font-weight: 400;
}

.work-order-pagination-actions button {
  min-height: 36px;
  border-radius: 8px;
  border: 1px solid #cfd8ea;
  background: #fff;
  color: #566079;
  padding: 0 12px;
  font-weight: 600;
}

.work-order-pagination-actions button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.work-order-page-current {
  color: #303848;
  font-weight: 600;
}

:deep(.work-order-modal .modal-dialog) {
  max-width: min(1120px, calc(100vw - 32px));
}

:deep(.work-order-modal .modal-content) {
  border-radius: 8px;
  border: 1px solid #dce5f4;
  overflow: hidden;
}

:deep(.work-order-modal-header) {
  min-height: 68px;
  padding: 18px 24px;
  border-bottom: 1px solid #e2eaf8;
  background: #fff;
}

:deep(.work-order-modal-title) {
  color: #303848;
  font-weight: 700;
  font-size: 20px;
}

.work-order-detail {
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 110px);
  background: #f8fafc;
}

.work-order-detail-scroll {
  overflow-y: auto;
  padding: 18px 22px;
}

.work-order-detail-hero,
.work-order-detail-section {
  border: 1px solid #e0e8f6;
  border-radius: 8px;
  background: #fff;
}

.work-order-detail-hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 18px;
  padding: 20px;
  margin-bottom: 14px;
}

.work-order-detail-hero-main {
  min-width: 0;
}

.work-order-detail-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  margin-top: 6px;
}

.work-order-detail-heading h3 {
  margin: 0;
  color: #303848;
  font-size: 24px;
  font-weight: 700;
  letter-spacing: 0;
}

.work-order-detail-pills {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.work-order-detail-hero-main p {
  margin: 12px 0 0;
  color: #556078;
  font-size: 14px;
  line-height: 1.55;
  font-weight: 400;
}

.work-order-detail-facts {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.work-order-detail-facts div,
.work-order-detail-field,
.work-order-detail-notes > div {
  min-width: 0;
  padding: 14px;
  border: 1px solid #e3ebf8;
  border-radius: 8px;
  background: #fbfdff;
}

.work-order-detail-facts span,
.work-order-detail-field span,
.work-order-detail-notes span {
  display: block;
  margin-bottom: 6px;
  color: #778199;
  font-size: 12px;
  line-height: 1.2;
  font-weight: 500;
}

.work-order-detail-facts strong,
.work-order-detail-field strong {
  display: block;
  color: #303848;
  font-size: 14px;
  line-height: 1.35;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.work-order-detail-section {
  padding: 18px;
}

.work-order-detail-section + .work-order-detail-section {
  margin-top: 14px;
}

.work-order-detail-section-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.work-order-detail-section-head i {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #3152c9;
  background: #eef4ff;
  font-size: 20px;
}

.work-order-detail-section-head h6 {
  margin: 0;
  color: #303848;
  font-size: 15px;
  font-weight: 600;
}

.work-order-detail-section-head span {
  color: #778199;
  font-size: 12px;
  font-weight: 400;
}

.work-order-detail-grid {
  display: grid;
  gap: 12px;
}

.work-order-detail-grid--four {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.work-order-detail-grid--two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.work-order-detail-focus {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.work-order-detail-assignees {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.work-order-detail-assignees span {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  color: #3152c9;
  background: #eef4ff;
  border: 1px solid #c7d7fe;
  font-size: 12px;
  font-weight: 600;
}

.work-order-detail-notes {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.work-order-detail-notes p {
  margin: 0;
  color: #364154;
  font-size: 14px;
  line-height: 1.55;
  white-space: pre-wrap;
}

.work-order-detail-photo {
  display: grid;
  gap: 12px;
}

.work-order-detail-photo-preview {
  position: relative;
  overflow: hidden;
  border: 1px solid #e3ebf8;
  border-radius: 8px;
  background: #0f172a;
}

.work-order-detail-photo-preview img {
  display: block;
  width: 100%;
  max-height: 420px;
  object-fit: contain;
  background: #111827;
}

.work-order-detail-photo-loading {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.42);
}

.work-order-detail-photo-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
  padding: 10px;
  background: #fff;
}

.work-order-detail-alert {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid #fcd34d;
  border-radius: 8px;
  color: #92400e;
  background: #fffbeb;
  font-size: 13px;
  line-height: 1.4;
}

.work-order-detail-alert a {
  display: block;
  margin-top: 4px;
  color: #3152c9;
  font-weight: 600;
}

.work-order-detail-empty {
  padding: 18px;
  border: 1px dashed #d0dbec;
  border-radius: 8px;
  color: #778199;
  background: #fbfdff;
  text-align: center;
  font-size: 14px;
  font-weight: 400;
}

.work-order-detail-empty--modal {
  margin: 18px;
}

.work-order-detail-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px;
  border-top: 1px solid #e2eaf8;
  background: #fff;
}

.work-order-form {
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 110px);
  background: #f8fafc;
}

.work-order-modal-scroll {
  overflow-y: auto;
  padding: 18px 22px;
}

.work-order-form-section {
  padding: 18px;
  border: 1px solid #e0e8f6;
  border-radius: 8px;
  background: #fff;
}

.work-order-form-section + .work-order-form-section {
  margin-top: 14px;
}

.work-order-form-section-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.work-order-form-section-head i {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #3152c9;
  background: #eef4ff;
  font-size: 20px;
}

.work-order-form-section-head h6 {
  margin: 0;
  color: #303848;
  font-size: 15px;
  font-weight: 700;
}

.work-order-form-section-head span {
  color: #778199;
  font-size: 12px;
  font-weight: 400;
}

.work-order-form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

.work-order-form-grid--two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.work-order-form-grid--three {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.work-order-form-field--wide {
  grid-column: 1 / -1;
}

.work-order-photo-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.work-order-photo-name {
  color: #717b94;
  font-size: 13px;
  font-weight: 400;
}

.work-order-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px;
  border-top: 1px solid #e2eaf8;
  background: #fff;
}

:deep(.work-order-multiselect) {
  min-height: 46px;
  border-radius: 8px;
}

:deep(.work-order-multiselect .multiselect-wrapper) {
  min-height: 46px;
  border: 1px solid #dce5f4;
  border-radius: 8px;
  background: #fff;
}

:deep(.work-order-multiselect .multiselect-placeholder),
:deep(.work-order-multiselect .multiselect-single-label),
:deep(.work-order-multiselect .multiselect-multiple-label) {
  color: #303848;
  font-size: 14px;
  font-weight: 400;
}

:deep(.multiselect-dropdown) {
  z-index: 2000;
  max-width: 100%;
}

:deep(.multiselect) {
  max-width: 100%;
}

@media (max-width: 1200px) {
  .work-order-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .work-order-detail-hero,
  .work-order-detail-grid--four {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .work-order-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .work-order-filter-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 768px) {
  .work-orders-header,
  .work-order-panel-head,
  .work-order-pagination {
    flex-direction: column;
    align-items: stretch;
  }

  .work-orders-header-actions,
  .work-order-filter-actions,
  .work-order-photo-actions,
  .work-order-pagination-actions {
    flex-wrap: wrap;
  }

  .work-order-summary-grid,
  .work-order-filters,
  .work-order-detail-hero,
  .work-order-detail-grid--four,
  .work-order-detail-grid--two,
  .work-order-detail-notes,
  .work-order-form-grid--two,
  .work-order-form-grid--three {
    grid-template-columns: 1fr;
  }

  .work-order-detail-heading,
  .work-order-detail-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .work-order-detail-pills,
  .work-order-detail-photo-actions {
    justify-content: flex-start;
  }

  .work-order-panel {
    padding: 16px;
  }

  .work-order-detail-scroll,
  .work-order-detail-footer,
  .work-order-modal-scroll,
  .work-order-modal-footer {
    padding-left: 16px;
    padding-right: 16px;
  }
}
</style>
