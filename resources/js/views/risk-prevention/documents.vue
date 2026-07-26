<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import {
  confirmRiskAction,
  downloadRiskFile,
  formatRiskDate,
  formatRiskDateTime,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
  showRiskWarning,
} from "../../components/risk-prevention/module-utils";

const MAX_FILE_SIZE = 25 * 1024 * 1024;
const ALLOWED_EXTENSIONS = [
  "pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx",
  "odt", "ods", "odp", "csv", "txt", "jpg", "jpeg", "png", "webp",
];

const documentTypeOptions = [
  { value: "", text: "Todos los tipos" },
  { value: "protocolo", text: "Protocolo" },
  { value: "reglamento", text: "Reglamento" },
  { value: "instructivo", text: "Instructivo" },
  { value: "informe", text: "Informe" },
];

const statusOptions = [
  { value: "", text: "Todos los estados" },
  { value: "vigente", text: "Vigente" },
  { value: "por_vencer", text: "Por vencer" },
  { value: "vencido", text: "Vencido" },
  { value: "archivado", text: "Archivado" },
];

const emptyForm = () => ({
  id: null,
  document_type: "protocolo",
  title: "",
  document_group: "",
  version_number: "",
  valid_from: "",
  valid_until: "",
  status: "vigente",
  is_disseminable: false,
  responsible_name: "",
  notes: "",
  document: null,
  current_document_name: "",
  current_file_size: null,
  current_file_extension: "",
});

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      loading: false,
      saving: false,
      downloadingId: null,
      warningShown: false,
      error: null,
      items: [],
      summary: { total: 0, disseminable: 0, due: 0, without_file: 0 },
      filters: { search: "", document_type: "", status: "", dissemination: "" },
      pagination: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
      showModal: false,
      form: emptyForm(),
      cameraActive: false,
      cameraStream: null,
      cameraError: null,
      documentTypeOptions,
      statusOptions,
      fileAccept: ALLOWED_EXTENSIONS.map((extension) => `.${extension}`).join(","),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canManage() {
      return this.permissions.includes("gestionar_prevencion_riesgos")
        || this.permissions.includes("__superadmin__");
    },
    selectedFile() {
      return this.form.document;
    },
    activeFilterCount() {
      return Object.values(this.filters).filter((value) => String(value || "").trim() !== "").length;
    },
    modalTitle() {
      return this.isEditing ? "Editar documento" : "Nuevo documento";
    },
  },
  mounted() {
    this.loadItems();
  },
  beforeUnmount() {
    this.stopCamera();
  },
  methods: {
    formatRiskDate,
    formatRiskDateTime,
    async loadItems(page = this.pagination.current_page) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/risk-prevention/documents", {
          params: {
            ...this.filters,
            page,
            per_page: this.pagination.per_page,
          },
        });

        this.items = response.data.data || [];
        this.summary = response.data.summary || this.summary;
        this.pagination = {
          current_page: response.data.current_page || 1,
          per_page: response.data.per_page || 15,
          total: response.data.total || 0,
          last_page: response.data.last_page || 1,
        };
        this.maybeShowWarnings();
      } catch (error) {
        this.error = formatRiskError(error, "No se pudieron cargar los documentos.");
        showRiskError(this.error);
      } finally {
        this.loading = false;
      }
    },
    async maybeShowWarnings() {
      if (this.warningShown || !this.summary.due) return;
      this.warningShown = true;
      await showRiskWarning(
        `Hay ${this.summary.due} documentos próximos a vencer o vencidos.`,
        "Vigencia documental",
      );
    },
    applyFilters() {
      this.pagination.current_page = 1;
      this.loadItems(1);
    },
    clearFilters() {
      this.filters = { search: "", document_type: "", status: "", dissemination: "" };
      this.applyFilters();
    },
    openCreate() {
      if (!this.canManage) return;
      this.stopCamera();
      this.form = {
        ...emptyForm(),
        valid_from: this.localDate(),
      };
      this.showModal = true;
    },
    localDate() {
      const now = new Date();
      const timezoneOffset = now.getTimezoneOffset() * 60000;

      return new Date(now.getTime() - timezoneOffset).toISOString().slice(0, 10);
    },
    openEdit(item) {
      if (!this.canManage) return;
      this.stopCamera();
      this.form = {
        id: item.id,
        document_type: item.document_type || "protocolo",
        title: item.title || "",
        document_group: item.document_group || "",
        version_number: item.version_number || "",
        valid_from: item.valid_from || "",
        valid_until: item.valid_until || "",
        status: item.status || "vigente",
        is_disseminable: Boolean(item.is_disseminable),
        responsible_name: item.responsible_name || "",
        notes: item.notes || "",
        document: null,
        current_document_name: item.document_name || "",
        current_file_size: item.file_size || null,
        current_file_extension: item.file_extension || "",
      };
      this.showModal = true;
    },
    resetModal() {
      this.stopCamera();
      this.cameraError = null;
      this.form = emptyForm();
      if (this.$refs.fileInput) this.$refs.fileInput.value = "";
      if (this.$refs.cameraInput) this.$refs.cameraInput.value = "";
    },
    triggerFilePicker() {
      this.$refs.fileInput?.click();
    },
    async onFileSelected(event) {
      const file = event?.target?.files?.[0] || null;
      if (event?.target) event.target.value = "";
      if (!file) return;

      const extension = this.extensionFromName(file.name);
      if (!ALLOWED_EXTENSIONS.includes(extension)) {
        await showRiskWarning(
          "Selecciona un PDF, documento Office/OpenDocument, archivo de texto o imagen.",
          "Formato no permitido",
        );
        return;
      }

      if (file.size > MAX_FILE_SIZE) {
        await showRiskWarning("El archivo no puede superar los 25 MB.", "Archivo demasiado grande");
        return;
      }

      this.form.document = file;
      this.stopCamera();
      this.cameraError = null;
    },
    clearSelectedFile() {
      this.form.document = null;
    },
    async startCamera() {
      this.cameraError = null;

      if (!navigator.mediaDevices?.getUserMedia) {
        this.$refs.cameraInput?.click();
        return;
      }

      this.stopCamera(false);

      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: "environment" } },
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

      if (clearError) this.cameraError = null;
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
      canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);

      const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.9));
      if (!blob) {
        this.cameraError = "No se pudo generar la fotografía.";
        return;
      }

      const stamp = new Date().toISOString().replace(/[:.]/g, "-");
      this.form.document = new File([blob], `documento-${stamp}.jpg`, {
        type: "image/jpeg",
        lastModified: Date.now(),
      });
      this.stopCamera();
    },
    buildFormData() {
      const formData = new FormData();
      const fields = [
        "document_type", "title", "document_group", "version_number",
        "valid_from", "valid_until", "status", "responsible_name", "notes",
      ];

      fields.forEach((key) => formData.append(key, this.form[key] ?? ""));
      formData.append("is_disseminable", this.form.is_disseminable ? "1" : "0");
      if (this.form.document) formData.append("document", this.form.document);

      return formData;
    },
    async validateForm() {
      if (!this.form.title.trim()) {
        await showRiskWarning("Ingresa el título del documento.", "Falta información");
        return false;
      }
      if (!this.form.version_number.trim()) {
        await showRiskWarning("Ingresa la versión del documento.", "Falta información");
        return false;
      }
      if (!this.isEditing && !this.form.document) {
        await showRiskWarning(
          "Sube un archivo o toma una fotografía antes de guardar.",
          "Falta el archivo",
        );
        return false;
      }
      return true;
    },
    async save() {
      if (!(await this.validateForm())) return;

      this.saving = true;
      try {
        const payload = this.buildFormData();
        if (this.isEditing) {
          payload.append("_method", "PUT");
          await axios.post(`/api/risk-prevention/documents/${this.form.id}`, payload);
          await showRiskSuccess("El documento fue actualizado correctamente.");
        } else {
          await axios.post("/api/risk-prevention/documents", payload);
          await showRiskSuccess("El documento fue registrado correctamente.");
        }
        this.showModal = false;
        await this.loadItems(this.pagination.current_page);
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo guardar el documento."));
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      const result = await confirmRiskAction({
        title: "Eliminar documento",
        text: `Se eliminará “${item.title}” y su archivo asociado. Esta acción no se puede deshacer.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/documents/${item.id}`);
        await showRiskSuccess("El documento fue eliminado correctamente.");
        await this.loadItems(this.pagination.current_page);
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el documento."));
      }
    },
    async download(item) {
      this.downloadingId = item.id;
      try {
        await downloadRiskFile(
          `/api/risk-prevention/documents/${item.id}/download`,
          item.document_name || item.title,
        );
        await showRiskSuccess("La descarga comenzó correctamente.", "Archivo preparado");
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo descargar el documento."));
      } finally {
        this.downloadingId = null;
      }
    },
    typeLabel(value) {
      return documentTypeOptions.find((option) => option.value === value)?.text || value || "-";
    },
    extensionFromName(name) {
      return String(name || "").split(".").pop()?.toLowerCase() || "";
    },
    fileExtension(item) {
      return (item.file_extension || this.extensionFromName(item.document_name)).toUpperCase() || "ARCHIVO";
    },
    fileIcon(itemOrFile) {
      const extension = itemOrFile?.file_extension
        || this.extensionFromName(itemOrFile?.document_name || itemOrFile?.name);

      if (extension === "pdf") return "bx-file";
      if (["doc", "docx", "odt", "txt"].includes(extension)) return "bx-file-blank";
      if (["xls", "xlsx", "ods", "csv"].includes(extension)) return "bx-spreadsheet";
      if (["ppt", "pptx", "odp"].includes(extension)) return "bx-slideshow";
      if (["jpg", "jpeg", "png", "webp"].includes(extension)) return "bx-image";
      return "bx-paperclip";
    },
    formatBytes(value) {
      const bytes = Number(value || 0);
      if (!bytes) return "-";
      if (bytes < 1024) return `${bytes} B`;
      if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
      return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    },
  },
};
</script>

<template>
  <Layout>
    <main class="document-admin-page">
      <section class="document-hero">
        <div class="document-hero__content">
          <span class="document-hero__eyebrow">Prevención de riesgos</span>
          <div class="document-hero__title-row">
            <span class="document-hero__icon"><i class="bx bx-folder-open"></i></span>
            <div>
              <h1>Gestión documental empresa</h1>
              <p>Centraliza, controla y difunde la documentación preventiva del establecimiento.</p>
            </div>
          </div>
        </div>
        <div class="document-hero__actions">
          <HelpButton
            title="Ayuda: gestión documental"
            text="Carga archivos o fotografías, controla su vigencia y decide cuáles estarán disponibles para los funcionarios."
          />
          <BButton v-if="canManage" class="document-primary-button" @click="openCreate">
            <i class="bx bx-plus"></i>
            Nuevo documento
          </BButton>
        </div>
      </section>

      <section class="document-metrics" aria-label="Resumen documental">
        <article class="document-metric document-metric--blue">
          <span class="document-metric__icon"><i class="bx bx-folder"></i></span>
          <div><strong>{{ summary.total }}</strong><span>Documentos registrados</span></div>
        </article>
        <article class="document-metric document-metric--green">
          <span class="document-metric__icon"><i class="bx bx-broadcast"></i></span>
          <div><strong>{{ summary.disseminable }}</strong><span>Disponibles para funcionarios</span></div>
        </article>
        <article class="document-metric document-metric--amber">
          <span class="document-metric__icon"><i class="bx bx-time-five"></i></span>
          <div><strong>{{ summary.due }}</strong><span>Por vencer o vencidos</span></div>
        </article>
        <article class="document-metric document-metric--slate">
          <span class="document-metric__icon"><i class="bx bx-file-find"></i></span>
          <div><strong>{{ summary.without_file }}</strong><span>Registros sin archivo</span></div>
        </article>
      </section>

      <section class="document-filter-card">
        <div class="document-filter-card__heading">
          <div>
            <h2>Biblioteca de documentos</h2>
            <p>Busca y filtra la información registrada.</p>
          </div>
          <span v-if="activeFilterCount" class="document-filter-count">
            {{ activeFilterCount }} {{ activeFilterCount === 1 ? "filtro activo" : "filtros activos" }}
          </span>
        </div>
        <div class="document-filter-grid">
          <label class="document-field document-field--search">
            <span>Buscar</span>
            <div class="document-input-icon">
              <i class="bx bx-search"></i>
              <input
                v-model="filters.search"
                type="search"
                placeholder="Título, archivo, grupo o responsable"
                @keyup.enter="applyFilters"
              />
            </div>
          </label>
          <label class="document-field">
            <span>Tipo</span>
            <BFormSelect v-model="filters.document_type" :options="documentTypeOptions" />
          </label>
          <label class="document-field">
            <span>Estado</span>
            <BFormSelect v-model="filters.status" :options="statusOptions" />
          </label>
          <label class="document-field">
            <span>Difusión</span>
            <BFormSelect v-model="filters.dissemination" :options="[
              { value: '', text: 'Todos' },
              { value: 'yes', text: 'Difundibles' },
              { value: 'no', text: 'No difundibles' },
            ]" />
          </label>
          <div class="document-filter-actions">
            <BButton variant="primary" @click="applyFilters">
              <i class="bx bx-filter-alt"></i> Filtrar
            </BButton>
            <BButton variant="light" @click="clearFilters">Limpiar</BButton>
          </div>
        </div>
      </section>

      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

      <section class="document-table-card">
        <LoadingState v-if="loading" message="Cargando documentos..." />
        <template v-else>
          <div v-if="items.length" class="table-responsive">
            <table class="document-table">
              <thead>
                <tr>
                  <th>Documento</th>
                  <th>Clasificación</th>
                  <th>Versión y vigencia</th>
                  <th>Estado</th>
                  <th>Difusión</th>
                  <th>Actualización</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in items" :key="item.id">
                  <td>
                    <div class="document-file-cell">
                      <span class="document-file-cell__icon">
                        <i class="bx" :class="fileIcon(item)"></i>
                      </span>
                      <div>
                        <strong>{{ item.title }}</strong>
                        <span>{{ item.document_name || "Sin archivo adjunto" }}</span>
                        <small>{{ fileExtension(item) }} · {{ formatBytes(item.file_size) }}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="document-type-pill">{{ typeLabel(item.document_type) }}</span>
                    <small class="document-table-muted">{{ item.document_group || "Sin grupo" }}</small>
                  </td>
                  <td>
                    <strong>v{{ item.version_number }}</strong>
                    <small class="document-table-muted">
                      {{ formatRiskDate(item.valid_from) }} — {{ formatRiskDate(item.valid_until) }}
                    </small>
                  </td>
                  <td><StatusBadge :status="item.current_status" /></td>
                  <td>
                    <span
                      class="document-diffusion-pill"
                      :class="item.is_disseminable ? 'document-diffusion-pill--on' : 'document-diffusion-pill--off'"
                    >
                      <i class="bx" :class="item.is_disseminable ? 'bx-check-circle' : 'bx-lock-alt'"></i>
                      {{ item.is_disseminable ? "Difundible" : "Interno" }}
                    </span>
                  </td>
                  <td>
                    <span class="document-updated-by">{{ item.updated_by?.name || "Sistema" }}</span>
                    <small class="document-table-muted">{{ formatRiskDateTime(item.updated_at) }}</small>
                  </td>
                  <td>
                    <div class="document-row-actions">
                      <BButton
                        size="sm"
                        variant="outline-info"
                        :disabled="!item.has_file || downloadingId === item.id"
                        title="Descargar archivo"
                        @click="download(item)"
                      >
                        <i class="bx" :class="downloadingId === item.id ? 'bx-loader-alt bx-spin' : 'bx-download'"></i>
                      </BButton>
                      <BButton
                        v-if="canManage"
                        size="sm"
                        variant="outline-primary"
                        title="Editar documento"
                        @click="openEdit(item)"
                      >
                        <i class="bx bx-edit-alt"></i>
                      </BButton>
                      <BButton
                        v-if="canManage"
                        size="sm"
                        variant="outline-danger"
                        title="Eliminar documento"
                        @click="remove(item)"
                      >
                        <i class="bx bx-trash"></i>
                      </BButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="document-empty-state">
            <span><i class="bx bx-folder-open"></i></span>
            <h3>No encontramos documentos</h3>
            <p>Ajusta los filtros o registra el primer documento preventivo.</p>
            <BButton v-if="canManage" variant="primary" @click="openCreate">Registrar documento</BButton>
          </div>

          <footer v-if="pagination.total > pagination.per_page" class="document-pagination">
            <span>
              Mostrando {{ items.length }} de {{ pagination.total }} documentos
            </span>
            <BPagination
              v-model="pagination.current_page"
              :total-rows="pagination.total"
              :per-page="pagination.per_page"
              @update:model-value="loadItems"
            />
          </footer>
        </template>
      </section>
    </main>

    <BModal
      v-model="showModal"
      :title="modalTitle"
      size="xl"
      centered
      scrollable
      hide-footer
      modal-class="document-form-modal"
      header-class="document-form-modal__header"
      body-class="document-form-modal__body"
      @hidden="resetModal"
    >
      <form class="document-form" @submit.prevent="save">
        <div class="document-form__intro">
          <span><i class="bx bx-file-blank"></i></span>
          <div>
            <strong>{{ isEditing ? "Actualiza la información del documento" : "Registra un nuevo documento" }}</strong>
            <p>Los campos con asterisco son obligatorios. Puedes subir un archivo o tomar una fotografía.</p>
          </div>
        </div>

        <section class="document-form-section">
          <div class="document-form-section__heading">
            <span>01</span>
            <div><h3>Identificación</h3><p>Datos para reconocer y ordenar el documento.</p></div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Tipo <em>*</em></label>
              <BFormSelect v-model="form.document_type" :options="documentTypeOptions.slice(1)" />
            </div>
            <div class="col-md-8">
              <label class="form-label">Título <em>*</em></label>
              <BFormInput v-model="form.title" placeholder="Ej: Protocolo de evacuación" maxlength="180" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Grupo documental</label>
              <BFormInput v-model="form.document_group" placeholder="Ej: Emergencias" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Versión <em>*</em></label>
              <BFormInput v-model="form.version_number" placeholder="Ej: 2.1" maxlength="30" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Responsable</label>
              <BFormInput v-model="form.responsible_name" placeholder="Nombre o unidad responsable" />
            </div>
          </div>
        </section>

        <section class="document-form-section">
          <div class="document-form-section__heading">
            <span>02</span>
            <div><h3>Archivo o fotografía</h3><p>Selecciona una de las dos alternativas de carga.</p></div>
          </div>

          <div class="document-upload-panel">
            <input
              ref="fileInput"
              class="d-none"
              type="file"
              :accept="fileAccept"
              @change="onFileSelected"
            />
            <input
              ref="cameraInput"
              class="d-none"
              type="file"
              accept="image/*"
              capture="environment"
              @change="onFileSelected"
            />

            <div class="document-upload-options">
              <button type="button" class="document-upload-option" @click="triggerFilePicker">
                <span><i class="bx bx-cloud-upload"></i></span>
                <strong>Subir archivo</strong>
                <small>PDF, Office, texto o imagen</small>
              </button>
              <div class="document-upload-divider"><span>o</span></div>
              <button type="button" class="document-upload-option document-upload-option--camera" @click="startCamera">
                <span><i class="bx bx-camera"></i></span>
                <strong>Sacar foto</strong>
                <small>Usa la cámara del dispositivo</small>
              </button>
            </div>
            <p class="document-upload-help">Formatos permitidos: PDF, Word, Excel, PowerPoint, OpenDocument, CSV, TXT e imágenes. Máximo 25 MB.</p>
          </div>

          <div v-if="cameraActive || cameraError" class="document-camera-panel">
            <BAlert v-if="cameraError" show variant="warning" class="mb-0">
              {{ cameraError }}
              <button type="button" class="btn btn-link btn-sm" @click="$refs.cameraInput?.click()">Usar cámara del dispositivo</button>
            </BAlert>
            <template v-else>
              <video ref="cameraVideo" autoplay muted playsinline></video>
              <canvas ref="cameraCanvas" class="d-none"></canvas>
              <div class="document-camera-panel__actions">
                <BButton variant="primary" size="sm" @click="capturePhoto">
                  <i class="bx bx-camera"></i> Capturar foto
                </BButton>
                <BButton variant="light" size="sm" @click="stopCamera">Cerrar cámara</BButton>
              </div>
            </template>
          </div>

          <div v-if="selectedFile" class="document-selected-file">
            <span class="document-selected-file__icon"><i class="bx" :class="fileIcon(selectedFile)"></i></span>
            <div>
              <strong>{{ selectedFile.name }}</strong>
              <small>{{ extensionFromName(selectedFile.name).toUpperCase() }} · {{ formatBytes(selectedFile.size) }}</small>
            </div>
            <button type="button" title="Quitar archivo" @click="clearSelectedFile"><i class="bx bx-x"></i></button>
          </div>
          <div v-else-if="isEditing && form.current_document_name" class="document-selected-file document-selected-file--current">
            <span class="document-selected-file__icon"><i class="bx bx-file"></i></span>
            <div>
              <strong>Archivo actual: {{ form.current_document_name }}</strong>
              <small>{{ form.current_file_extension.toUpperCase() || "ARCHIVO" }} · {{ formatBytes(form.current_file_size) }}</small>
            </div>
            <span class="document-current-badge">Se conservará</span>
          </div>
        </section>

        <section class="document-form-section">
          <div class="document-form-section__heading">
            <span>03</span>
            <div><h3>Vigencia y difusión</h3><p>Controla el período de validez y quién podrá consultarlo.</p></div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Vigente desde</label>
              <BFormInput v-model="form.valid_from" type="date" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Vigente hasta</label>
              <BFormInput v-model="form.valid_until" type="date" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Estado</label>
              <BFormSelect v-model="form.status" :options="statusOptions.slice(1)" />
            </div>
            <div class="col-12">
              <div class="document-diffusion-control" :class="{ 'document-diffusion-control--active': form.is_disseminable }">
                <span class="document-diffusion-control__icon"><i class="bx bx-broadcast"></i></span>
                <div>
                  <strong>Difundible para funcionarios</strong>
                  <p>Al activarlo, el documento aparecerá en la vista “Gestión documental” de los funcionarios mientras esté vigente.</p>
                </div>
                <BFormCheckbox v-model="form.is_disseminable" switch size="lg">
                  {{ form.is_disseminable ? "Sí" : "No" }}
                </BFormCheckbox>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="form.notes" rows="3" placeholder="Información adicional, alcance o instrucciones de uso" />
            </div>
          </div>
        </section>

        <footer class="document-form__footer">
          <BButton variant="light" type="button" @click="showModal = false">Cancelar</BButton>
          <BButton variant="primary" type="submit" :disabled="saving">
            <i class="bx" :class="saving ? 'bx-loader-alt bx-spin' : 'bx-save'"></i>
            {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Registrar documento" }}
          </BButton>
        </footer>
      </form>
    </BModal>
  </Layout>
</template>

<style scoped>
.document-admin-page {
  --document-ink: #18233f;
  --document-muted: #6e7891;
  --document-border: #e5eaf2;
  --document-blue: #3159d9;
  padding: 0.25rem 0 2rem;
}

.document-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1.6rem 1.75rem;
  margin-bottom: 1rem;
  color: white;
  border-radius: 1.25rem;
  background:
    radial-gradient(circle at 82% 15%, rgba(118, 171, 255, 0.34), transparent 32%),
    linear-gradient(125deg, #17275a 0%, #274bb4 58%, #3477dc 100%);
  box-shadow: 0 18px 36px rgba(35, 66, 147, 0.2);
}

.document-hero__eyebrow {
  display: block;
  margin-bottom: 0.7rem;
  color: #bcd0ff;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.document-hero__title-row {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.document-hero__icon {
  display: grid;
  flex: 0 0 3.25rem;
  width: 3.25rem;
  height: 3.25rem;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.13);
  font-size: 1.7rem;
}

.document-hero h1 {
  margin: 0 0 0.3rem;
  color: white;
  font-size: clamp(1.4rem, 2.2vw, 2rem);
  font-weight: 750;
}

.document-hero p {
  max-width: 44rem;
  margin: 0;
  color: rgba(255, 255, 255, 0.76);
}

.document-hero__actions {
  display: flex;
  align-items: center;
  gap: 0.7rem;
}

.document-primary-button {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.72rem 1rem;
  border-color: white;
  background: white;
  color: #244cae;
  font-weight: 700;
}

.document-primary-button:hover {
  border-color: #edf3ff;
  background: #edf3ff;
  color: #173c96;
}

.document-metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.85rem;
  margin-bottom: 1rem;
}

.document-metric {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  min-height: 6rem;
  padding: 1rem 1.1rem;
  border: 1px solid var(--document-border);
  border-radius: 1rem;
  background: white;
  box-shadow: 0 7px 20px rgba(31, 45, 84, 0.06);
}

.document-metric__icon {
  display: grid;
  width: 2.75rem;
  height: 2.75rem;
  place-items: center;
  border-radius: 0.85rem;
  font-size: 1.35rem;
}

.document-metric strong,
.document-metric span {
  display: block;
}

.document-metric strong {
  color: var(--document-ink);
  font-size: 1.45rem;
  line-height: 1.1;
}

.document-metric div span {
  margin-top: 0.25rem;
  color: var(--document-muted);
  font-size: 0.78rem;
}

.document-metric--blue .document-metric__icon { background: #eaf0ff; color: #3159d9; }
.document-metric--green .document-metric__icon { background: #e7f8f1; color: #15976d; }
.document-metric--amber .document-metric__icon { background: #fff3db; color: #ce8210; }
.document-metric--slate .document-metric__icon { background: #eef1f6; color: #657086; }

.document-filter-card,
.document-table-card {
  margin-bottom: 1rem;
  border: 1px solid var(--document-border);
  border-radius: 1.05rem;
  background: white;
  box-shadow: 0 8px 24px rgba(31, 45, 84, 0.055);
}

.document-filter-card {
  padding: 1.1rem 1.2rem;
}

.document-filter-card__heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.9rem;
}

.document-filter-card h2 {
  margin: 0;
  color: var(--document-ink);
  font-size: 1rem;
}

.document-filter-card p {
  margin: 0.2rem 0 0;
  color: var(--document-muted);
  font-size: 0.8rem;
}

.document-filter-count {
  padding: 0.3rem 0.6rem;
  border-radius: 999px;
  background: #edf2ff;
  color: #3159d9;
  font-size: 0.72rem;
  font-weight: 700;
}

.document-filter-grid {
  display: grid;
  grid-template-columns: minmax(16rem, 1.8fr) repeat(3, minmax(9rem, 1fr)) auto;
  gap: 0.75rem;
  align-items: end;
}

.document-field > span {
  display: block;
  margin-bottom: 0.35rem;
  color: #48536b;
  font-size: 0.74rem;
  font-weight: 700;
}

.document-input-icon {
  position: relative;
}

.document-input-icon i {
  position: absolute;
  top: 50%;
  left: 0.85rem;
  color: #929cb0;
  font-size: 1.05rem;
  transform: translateY(-50%);
}

.document-input-icon input {
  width: 100%;
  min-height: 2.35rem;
  padding: 0.5rem 0.75rem 0.5rem 2.35rem;
  border: 1px solid #ced5e1;
  border-radius: 0.4rem;
  color: #39445d;
  outline: none;
}

.document-input-icon input:focus {
  border-color: #86a4ef;
  box-shadow: 0 0 0 0.18rem rgba(49, 89, 217, 0.12);
}

.document-filter-actions {
  display: flex;
  gap: 0.45rem;
}

.document-table-card {
  overflow: hidden;
}

.document-table {
  width: 100%;
  border-collapse: collapse;
}

.document-table th {
  padding: 0.8rem 1rem;
  border-bottom: 1px solid var(--document-border);
  background: #f7f9fc;
  color: #68738b;
  font-size: 0.7rem;
  font-weight: 750;
  letter-spacing: 0.035em;
  text-align: left;
  text-transform: uppercase;
  white-space: nowrap;
}

.document-table td {
  padding: 0.92rem 1rem;
  border-bottom: 1px solid #edf0f5;
  color: #364159;
  vertical-align: middle;
}

.document-table tbody tr:last-child td {
  border-bottom: 0;
}

.document-table tbody tr:hover {
  background: #fbfcff;
}

.document-file-cell {
  display: flex;
  align-items: center;
  min-width: 15rem;
  gap: 0.75rem;
}

.document-file-cell__icon {
  display: grid;
  flex: 0 0 2.65rem;
  width: 2.65rem;
  height: 2.65rem;
  place-items: center;
  border-radius: 0.8rem;
  background: #edf2ff;
  color: #3159d9;
  font-size: 1.25rem;
}

.document-file-cell strong,
.document-file-cell span,
.document-file-cell small,
.document-table-muted,
.document-updated-by {
  display: block;
}

.document-file-cell strong {
  max-width: 19rem;
  overflow: hidden;
  color: var(--document-ink);
  font-size: 0.86rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.document-file-cell div > span {
  max-width: 18rem;
  margin-top: 0.12rem;
  overflow: hidden;
  color: #6e7891;
  font-size: 0.74rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.document-file-cell small,
.document-table-muted {
  margin-top: 0.2rem;
  color: #9aa3b4;
  font-size: 0.69rem;
}

.document-type-pill {
  display: inline-flex;
  padding: 0.26rem 0.55rem;
  border-radius: 999px;
  background: #eff2f8;
  color: #566179;
  font-size: 0.69rem;
  font-weight: 700;
}

.document-diffusion-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  padding: 0.3rem 0.55rem;
  border-radius: 999px;
  font-size: 0.69rem;
  font-weight: 700;
  white-space: nowrap;
}

.document-diffusion-pill--on { background: #e7f8f1; color: #13845f; }
.document-diffusion-pill--off { background: #f0f2f6; color: #70798b; }
.document-updated-by { max-width: 9rem; overflow: hidden; font-size: 0.78rem; text-overflow: ellipsis; white-space: nowrap; }

.document-row-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.35rem;
}

.document-row-actions :deep(.btn) {
  display: inline-grid;
  width: 2rem;
  height: 2rem;
  padding: 0;
  place-items: center;
  border-radius: 0.55rem;
}

.document-empty-state {
  padding: 4.5rem 1rem;
  color: var(--document-muted);
  text-align: center;
}

.document-empty-state > span {
  display: grid;
  width: 4rem;
  height: 4rem;
  margin: 0 auto 1rem;
  place-items: center;
  border-radius: 1.2rem;
  background: #edf2ff;
  color: #3159d9;
  font-size: 2rem;
}

.document-empty-state h3 { margin: 0; color: var(--document-ink); font-size: 1.1rem; }
.document-empty-state p { margin: 0.4rem 0 1rem; }

.document-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 1rem;
  border-top: 1px solid var(--document-border);
  color: var(--document-muted);
  font-size: 0.78rem;
}

.document-pagination :deep(.pagination) { margin: 0; }

.document-form__intro {
  display: flex;
  gap: 0.85rem;
  padding: 1rem 1.15rem;
  margin-bottom: 1rem;
  border-radius: 0.9rem;
  background: linear-gradient(100deg, #edf2ff, #f6f8ff);
}

.document-form__intro > span {
  display: grid;
  flex: 0 0 2.5rem;
  height: 2.5rem;
  place-items: center;
  border-radius: 0.7rem;
  background: white;
  color: #3159d9;
  font-size: 1.25rem;
}

.document-form__intro strong { color: var(--document-ink); }
.document-form__intro p { margin: 0.2rem 0 0; color: var(--document-muted); font-size: 0.8rem; }

.document-form-section {
  padding: 1.05rem 1.15rem;
  margin-bottom: 0.85rem;
  border: 1px solid var(--document-border);
  border-radius: 0.95rem;
}

.document-form-section__heading {
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
  margin-bottom: 1rem;
}

.document-form-section__heading > span {
  display: inline-grid;
  flex: 0 0 1.8rem;
  height: 1.8rem;
  place-items: center;
  border-radius: 0.55rem;
  background: #253f8f;
  color: white;
  font-size: 0.65rem;
  font-weight: 800;
}

.document-form-section h3 { margin: 0; color: var(--document-ink); font-size: 0.95rem; }
.document-form-section__heading p { margin: 0.12rem 0 0; color: var(--document-muted); font-size: 0.75rem; }
.document-form .form-label { color: #48536b; font-size: 0.78rem; font-weight: 700; }
.document-form .form-label em { color: #dc4c64; font-style: normal; }

.document-upload-panel {
  padding: 1rem;
  border: 1px dashed #bdc9e4;
  border-radius: 0.9rem;
  background: #fafbff;
}

.document-upload-options {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: stretch;
  gap: 0.85rem;
}

.document-upload-option {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  min-height: 7.25rem;
  padding: 0.9rem;
  border: 1px solid #dce3f0;
  border-radius: 0.85rem;
  background: white;
  color: #354159;
  transition: 150ms ease;
}

.document-upload-option:hover {
  border-color: #7393e8;
  box-shadow: 0 8px 22px rgba(49, 89, 217, 0.1);
  transform: translateY(-1px);
}

.document-upload-option > span {
  display: grid;
  width: 2.5rem;
  height: 2.5rem;
  margin-bottom: 0.45rem;
  place-items: center;
  border-radius: 0.75rem;
  background: #edf2ff;
  color: #3159d9;
  font-size: 1.25rem;
}

.document-upload-option--camera > span { background: #e7f8f1; color: #168768; }
.document-upload-option strong { font-size: 0.86rem; }
.document-upload-option small { margin-top: 0.2rem; color: #8a94a8; }

.document-upload-divider {
  display: flex;
  align-items: center;
  color: #9aa4b7;
  font-size: 0.72rem;
  text-transform: uppercase;
}

.document-upload-divider span {
  display: grid;
  width: 1.7rem;
  height: 1.7rem;
  place-items: center;
  border-radius: 50%;
  background: #eef1f6;
}

.document-upload-help { margin: 0.75rem 0 0; color: #8993a6; font-size: 0.7rem; text-align: center; }

.document-camera-panel {
  position: relative;
  max-width: 42rem;
  padding: 0.75rem;
  margin: 0.85rem auto 0;
  border-radius: 0.9rem;
  background: #111827;
}

.document-camera-panel video {
  display: block;
  width: 100%;
  max-height: 22rem;
  border-radius: 0.65rem;
  object-fit: cover;
}

.document-camera-panel__actions {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  padding-top: 0.7rem;
}

.document-selected-file {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.7rem 0.8rem;
  margin-top: 0.8rem;
  border: 1px solid #cfe0d7;
  border-radius: 0.8rem;
  background: #f5fbf8;
}

.document-selected-file__icon {
  display: grid;
  flex: 0 0 2.25rem;
  height: 2.25rem;
  place-items: center;
  border-radius: 0.65rem;
  background: #dcf5e9;
  color: #168768;
  font-size: 1.1rem;
}

.document-selected-file > div { min-width: 0; flex: 1; }
.document-selected-file strong,
.document-selected-file small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.document-selected-file strong { color: #244436; font-size: 0.8rem; }
.document-selected-file small { margin-top: 0.15rem; color: #719080; font-size: 0.68rem; }
.document-selected-file > button { border: 0; background: transparent; color: #68748a; font-size: 1.25rem; }
.document-selected-file--current { border-color: #dce3f0; background: #f8f9fc; }
.document-current-badge { padding: 0.25rem 0.5rem; border-radius: 999px; background: #e8edf7; color: #68748a; font-size: 0.68rem; font-weight: 700; }

.document-diffusion-control {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.85rem;
  border: 1px solid #dfe4ed;
  border-radius: 0.85rem;
  background: #f9fafc;
  transition: 150ms ease;
}

.document-diffusion-control--active { border-color: #a9ddc9; background: #f1fbf7; }

.document-diffusion-control__icon {
  display: grid;
  flex: 0 0 2.5rem;
  height: 2.5rem;
  place-items: center;
  border-radius: 0.7rem;
  background: #e8edf7;
  color: #59657a;
  font-size: 1.2rem;
}

.document-diffusion-control--active .document-diffusion-control__icon { background: #d9f5e8; color: #168768; }
.document-diffusion-control > div { min-width: 0; flex: 1; }
.document-diffusion-control strong { color: var(--document-ink); font-size: 0.82rem; }
.document-diffusion-control p { margin: 0.15rem 0 0; color: var(--document-muted); font-size: 0.72rem; }

.document-form__footer {
  position: sticky;
  bottom: -1rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.55rem;
  padding: 0.9rem 0 0.2rem;
  background: white;
}

:global(.document-form-modal .modal-content) {
  overflow: hidden;
  border: 0;
  border-radius: 1.1rem;
  box-shadow: 0 24px 60px rgba(25, 37, 72, 0.22);
}

:global(.document-form-modal .document-form-modal__header) {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e8ecf3;
  background: #f8faff;
}

:global(.document-form-modal .document-form-modal__body) {
  padding: 1rem 1.25rem 1.25rem;
}

@media (max-width: 1199.98px) {
  .document-filter-grid { grid-template-columns: 1.5fr repeat(2, 1fr); }
  .document-filter-actions { grid-column: span 2; }
}

@media (max-width: 991.98px) {
  .document-hero { align-items: flex-start; flex-direction: column; }
  .document-metrics { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 767.98px) {
  .document-hero { padding: 1.25rem; }
  .document-hero__actions { width: 100%; justify-content: space-between; }
  .document-metrics { grid-template-columns: 1fr; }
  .document-filter-grid { grid-template-columns: 1fr; }
  .document-filter-actions { grid-column: auto; }
  .document-upload-options { grid-template-columns: 1fr; }
  .document-upload-divider { justify-content: center; }
  .document-diffusion-control { align-items: flex-start; flex-wrap: wrap; }
  .document-diffusion-control :deep(.form-check) { margin-left: 3.3rem; }
  .document-pagination { align-items: flex-start; flex-direction: column; }
}
</style>
