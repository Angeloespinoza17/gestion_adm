<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";

const emptyForm = () => ({
  id: null,
  maintenance_dependency_id: "",
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
        assignees: [
          "Ivan",
          "Oscar",
          "Carlos cayul",
          "Laura davinson",
          "Lucia pailla",
          "Lucila valladares",
          "Llineth",
          "Maria paz",
          "Pilar cocio",
          "Sofia navarro",
          "Javier casas",
          "Ariel Villanueva",
          "Manuel Lara",
          "Pedro",
          "Jeaqueline sandoval",
        ],
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

      return `${dependency.code} · ${dependency.name}${dependency.is_reservable ? " · [Espacio]" : " · [Activo técnico]"}`;
    },
    syncDependencySelection() {
      const match = this.catalogs.dependencies.find(
        (dependency) => this.dependencyLabel(dependency) === this.dependencySearch
      );

      this.form.maintenance_dependency_id = match ? match.id : "";
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
    parseAssignees(value) {
      if (!value) return [];

      return String(value)
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);
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
    priorityClass(priority) {
      return {
        "Crítico": "bg-dark",
        "Alta": "bg-danger",
        "Media": "bg-warning",
        "Baja": "bg-info",
      }[priority] || "bg-secondary";
    },
    statusClass(status) {
      return {
        "Sin comenzar": "bg-secondary",
        "En proceso": "bg-primary",
        "En espera": "bg-warning",
        "Pausado": "bg-warning",
        "Terminado": "bg-success",
        "Anulado": "bg-dark",
      }[status] || "bg-secondary";
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
    <BRow>
      <BCol cols="12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0 font-size-18">Órdenes de trabajo</h4>
          <div class="page-title-right">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item active">Gestión de mantención</li>
            </ol>
          </div>
        </div>
      </BCol>
    </BRow>

    <BRow>
      <BCol md="3">
        <BCard no-body><BCardBody><p class="text-muted mb-1">Total OT</p><h4 class="mb-0">{{ catalogs.summary.total }}</h4></BCardBody></BCard>
      </BCol>
      <BCol md="3">
        <BCard no-body><BCardBody><p class="text-muted mb-1">Abiertas</p><h4 class="mb-0">{{ catalogs.summary.open }}</h4></BCardBody></BCard>
      </BCol>
      <BCol md="3">
        <BCard no-body><BCardBody><p class="text-muted mb-1">Críticas</p><h4 class="mb-0">{{ catalogs.summary.critical }}</h4></BCardBody></BCard>
      </BCol>
      <BCol md="3">
        <BCard no-body><BCardBody><p class="text-muted mb-1">Terminadas</p><h4 class="mb-0">{{ catalogs.summary.finished }}</h4></BCardBody></BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol cols="12">
        <BCard no-body>
          <BCardBody>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <h5 class="card-title mb-0">Listado de OT</h5>
              <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="button" @click="openCreateModal()">Agregar OT</button>
                <button class="btn btn-outline-secondary" type="button" @click="toggleSortMode">
                  {{ sortMode === "created" ? "Ordenar por prioridad" : "Ordenar por fecha" }}
                </button>
                <input v-model="search" type="search" class="form-control" placeholder="Buscar OT..." @keyup.enter="loadWorkOrders()" />
                <select v-model="priorityFilter" class="form-select">
                  <option value="">Todas las prioridades</option>
                  <option v-for="priority in catalogs.priorities" :key="priority" :value="priority">{{ priority }}</option>
                </select>
                <select v-model="statusFilter" class="form-select">
                  <option value="">Todos los estados</option>
                  <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
                </select>
                <select v-model="assigneeFilter" class="form-select">
                  <option value="">Todas las personas</option>
                  <option v-for="assignee in catalogs.assignees" :key="assignee" :value="assignee">{{ assignee }}</option>
                </select>
                <button class="btn btn-outline-primary" type="button" @click="loadWorkOrders()">Filtrar</button>
              </div>
            </div>

            <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
            <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>

            <div class="table-responsive">
              <table class="table table-centered table-nowrap align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Agregado</th>
                    <th>OT</th>
                    <th>Dependencia</th>
                    <th>Asignado</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Fecha límite</th>
                    <th class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="8" class="text-center text-muted py-4">Cargando órdenes...</td>
                  </tr>
                  <tr v-else-if="workOrders.length === 0">
                    <td colspan="8" class="text-center text-muted py-4">No hay órdenes de trabajo registradas.</td>
                  </tr>
                  <tr v-for="workOrder in workOrders" :key="workOrder.id">
                    <td>{{ formatDisplayDate(workOrder.created_at) }}</td>
                    <td>
                      <strong>#{{ workOrder.id }}</strong>
                      <div class="text-muted text-truncate work-order-description">{{ workOrder.description }}</div>
                    </td>
                    <td>{{ workOrderLocation(workOrder) }}</td>
                    <td>{{ workOrder.assigned_to || "-" }}</td>
                    <td><span class="badge" :class="priorityClass(workOrder.priority)">{{ workOrder.priority }}</span></td>
                    <td><span class="badge" :class="statusClass(workOrder.status)">{{ workOrder.status }}</span></td>
                    <td>{{ formatDisplayDate(workOrder.due_date) }}</td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary me-2" type="button" @click="viewWorkOrder(workOrder)">Ver</button>
                      <button class="btn btn-sm btn-outline-secondary me-2" type="button" @click="editWorkOrder(workOrder)">Editar</button>
                      <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteWorkOrder(workOrder)">Eliminar</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex align-items-center justify-content-between">
              <span class="text-muted">Total: {{ pagination.total }}</span>
              <div class="btn-group">
                <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page <= 1" @click="loadWorkOrders(pagination.current_page - 1)">
                  Anterior
                </button>
                <button class="btn btn-outline-secondary" type="button" disabled>
                  {{ pagination.current_page }} / {{ pagination.last_page }}
                </button>
                <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadWorkOrders(pagination.current_page + 1)">
                  Siguiente
                </button>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BModal
      ref="crearOTModal"
      v-model="showModalCrearOT"
      :title="isEditing ? 'Editar OT' : 'Nueva OT'"
      title-class="font-18"
      body-class="p-3"
      size="lg"
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
      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
      <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>

      <form @submit.prevent="saveWorkOrder">
        <div class="mb-3">
          <label class="form-label">Dependencia</label>
          <input v-model="dependencySearch" type="text" class="form-control" list="dependency-options" @change="syncDependencySelection" />
          <datalist id="dependency-options">
            <option v-for="dependency in catalogs.dependencies" :key="dependency.id" :value="dependencyLabel(dependency)" />
          </datalist>
        </div>

        <BRow>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Fecha ingreso</label>
              <input v-model="form.reported_at" type="date" class="form-control" />
            </div>
          </BCol>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Fecha límite</label>
              <input v-model="form.due_date" type="date" class="form-control" />
            </div>
          </BCol>
        </BRow>

        <BRow>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Prioridad</label>
              <select v-model="form.priority" class="form-select" required>
                <option v-for="priority in catalogs.priorities" :key="priority" :value="priority">{{ priority }}</option>
              </select>
            </div>
          </BCol>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Estado</label>
              <select v-model="form.status" class="form-select" required>
                <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
              </select>
            </div>
          </BCol>
        </BRow>

        <div class="mb-3">
          <label class="form-label">Quién asigna</label>
          <select v-model="form.requested_by" class="form-select" required>
            <option value="">Selecciona...</option>
            <option v-for="requester in catalogs.requesters" :key="requester" :value="requester">{{ requester }}</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Asignación</label>
          <Multiselect
            class="w-100"
            v-model="form.assigned_to"
            :options="catalogs.assignees"
            mode="multiple"
            :close-on-select="false"
            :searchable="true"
            :append-to-body="false"
            :style="{ '--ms-max-height': '220px' }"
          />
        </div>

        <div class="mb-3">
          <label class="form-label">Trabajo solicitado</label>
          <textarea v-model="form.description" class="form-control" rows="4" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Notas de cierre / resolución</label>
          <textarea v-model="form.resolution_notes" class="form-control" rows="3"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Foto</label>
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-primary" type="button" @click="startCameraCapture">Tomar foto</button>
            <input ref="cameraInput" type="file" class="d-none" accept="image/*" capture="environment" @change="handlePhotoSelection" />

            <label class="btn btn-outline-secondary mb-0">
              Adjuntar imagen
              <input type="file" class="d-none" accept="image/*" @change="handlePhotoSelection" />
            </label>
            <span v-if="selectedPhoto" class="text-muted align-self-center">{{ selectedPhoto.name }}</span>
            <span v-else class="text-muted align-self-center">Sin archivo seleccionado</span>
          </div>
          <small v-if="cameraError" class="text-danger d-block mt-1">{{ cameraError }}</small>
        </div>

        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-light" type="button" @click="showModalCrearOT = false">Cancelar</button>
          <button class="btn btn-primary" type="submit" :disabled="saving">
            {{ saving ? "Guardando..." : isEditing ? "Actualizar OT" : "Crear OT" }}
          </button>
        </div>
      </form>
    </BModal>

    <BModal
      ref="detalleOTModal"
      v-model="showModalDetalleOT"
      title="Detalle OT"
      title-class="font-18"
      body-class="p-3"
      size="lg"
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
      <div v-if="activeWorkOrder">
        <div class="mb-3">
          <strong>OT #{{ activeWorkOrder.id }}</strong>
          <div class="text-muted">Agregada: {{ formatDisplayDate(activeWorkOrder.created_at) }}</div>
        </div>

        <BRow class="mb-3">
          <BCol md="6">
            <div class="text-muted">Dependencia</div>
            <div>{{ workOrderLocation(activeWorkOrder) }}</div>
          </BCol>
          <BCol md="6">
            <div class="text-muted">Fecha límite</div>
            <div>{{ formatDisplayDate(activeWorkOrder.due_date) }}</div>
          </BCol>
        </BRow>

        <BRow class="mb-3">
          <BCol md="6">
            <div class="text-muted">Quién asigna</div>
            <div>{{ activeWorkOrder.requested_by || "-" }}</div>
          </BCol>
          <BCol md="6">
            <div class="text-muted">Asignados</div>
            <div>{{ activeWorkOrder.assigned_to || "-" }}</div>
          </BCol>
        </BRow>

        <BRow class="mb-3">
          <BCol md="6">
            <div class="text-muted">Prioridad</div>
            <span class="badge" :class="priorityClass(activeWorkOrder.priority)">{{ activeWorkOrder.priority }}</span>
          </BCol>
          <BCol md="6">
            <div class="text-muted">Estado</div>
            <span class="badge" :class="statusClass(activeWorkOrder.status)">{{ activeWorkOrder.status }}</span>
          </BCol>
        </BRow>

        <div class="mb-3">
          <div class="text-muted">Trabajo solicitado</div>
          <div class="border rounded p-2 bg-light">{{ activeWorkOrder.description }}</div>
        </div>

        <div class="mb-3">
          <div class="text-muted">Notas de cierre / resolución</div>
          <div class="border rounded p-2 bg-light">{{ activeWorkOrder.resolution_notes || "-" }}</div>
        </div>

        <div class="mb-3">
          <div class="text-muted">Foto</div>
          <div v-if="activeWorkOrder.photo_url || activeWorkOrder.photo_reference">
            <div v-if="detailPhotoError" class="alert alert-warning mb-2">
              {{ detailPhotoError }}
              <div v-if="resolvePhotoUrl(activeWorkOrder.photo_url)" class="mt-2">
                <a :href="resolvePhotoUrl(activeWorkOrder.photo_url)" target="_blank" rel="noopener">Abrir enlace de la foto</a>
              </div>
            </div>

            <div v-if="activeWorkOrder.photo_url && !detailPhotoError" class="position-relative">
              <div v-if="detailPhotoLoading" class="position-absolute top-50 start-50 translate-middle">
                <div class="spinner-border text-light" role="status"></div>
              </div>
              <img
                :src="resolvePhotoUrl(activeWorkOrder.photo_url)"
                class="img-fluid rounded"
                alt="Foto OT"
                @load="onDetailPhotoLoaded"
                @error="onDetailPhotoError"
              />
              <div class="mt-2 d-flex flex-wrap gap-2">
                <a
                  class="btn btn-sm btn-outline-secondary"
                  :href="resolvePhotoUrl(activeWorkOrder.photo_url)"
                  target="_blank"
                  rel="noopener"
                >
                  Ver en pestaña nueva
                </a>
                <a class="btn btn-sm btn-outline-secondary" :href="resolvePhotoUrl(activeWorkOrder.photo_url)" download>
                  Descargar
                </a>
              </div>
            </div>

            <div v-else-if="activeWorkOrder.photo_reference && !activeWorkOrder.photo_url" class="text-muted">
              Hay una referencia de foto pero no hay URL disponible (revisar configuración de almacenamiento).
            </div>
          </div>
          <div v-else class="text-muted">Esta OT no tiene foto adjunta.</div>
        </div>

        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-outline-secondary" type="button" @click="editWorkOrder(activeWorkOrder)">Editar</button>
          <button class="btn btn-light" type="button" @click="showModalDetalleOT = false">Cerrar</button>
        </div>
      </div>
      <div v-else class="text-muted text-center py-4">Selecciona una OT para ver el detalle.</div>
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
.work-order-description {
  max-width: 260px;
}

:deep(.multiselect-dropdown) {
  z-index: 2000;
  max-width: 100%;
}

:deep(.multiselect) {
  max-width: 100%;
}
</style>
