<script>
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import {
  createDocumentation,
  downloadDocumentation,
  filenameFromDisposition,
  getDocumentationCatalogs,
  listDocumentation,
  removeDocumentation,
  updateDocumentation,
} from "../../services/documentation-api";

const currentYear = new Date().getFullYear();
const MAX_FILE_SIZE = 25 * 1024 * 1024;
const ACCEPTED_EXTENSIONS = ["pdf", "doc", "docx", "xls", "xlsx"];

const defaultCategories = [
  { value: "proyecto-educativo", label: "Proyecto educativo" },
  { value: "reglamento", label: "Reglamento" },
  { value: "protocolo", label: "Protocolo" },
  { value: "plan-institucional", label: "Plan institucional" },
  { value: "circular", label: "Circular" },
  { value: "otro", label: "Otro" },
];

const emptyForm = () => ({
  id: null,
  title: "",
  category: "proyecto-educativo",
  year: currentYear,
  version: "",
  description: "",
  is_public: false,
  is_active: true,
  file: null,
  current_file_name: "",
});

const normalizedOption = (option) => {
  if (typeof option === "string") return { value: option, label: option };
  return {
    value: option?.value ?? option?.id ?? "",
    label: option?.label ?? option?.name ?? option?.value ?? "",
  };
};

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: true,
      saving: false,
      downloadingId: null,
      deletingId: null,
      error: null,
      items: [],
      summary: { total: 0, published: 0, public: 0, years: 0 },
      catalogStats: {},
      pagination: { current_page: 1, last_page: 1, per_page: 15, total: 0, from: 0, to: 0 },
      filters: { search: "", category: "", year: "", is_active: "", is_public: "" },
      catalogs: { categories: defaultCategories, years: [] },
      capabilities: {},
      form: emptyForm(),
      validationErrors: {},
      showForm: false,
      modalTrigger: null,
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
    isSuperAdmin() {
      return this.permissions.includes("__superadmin__");
    },
    canCreate() {
      return this.capability("create", "documentation.create");
    },
    canUpdate() {
      return this.capability("update", "documentation.update");
    },
    canDelete() {
      return this.capability("delete", "documentation.delete");
    },
    categoryOptions() {
      return this.mergeOptions(defaultCategories, this.catalogs.categories);
    },
    yearOptions() {
      const configured = (this.catalogs.years || []).map((year) => Number(year)).filter(Boolean);
      const itemYears = this.items.map((item) => Number(item.year)).filter(Boolean);
      return Array.from(new Set([currentYear, ...configured, ...itemYears])).sort((a, b) => b - a);
    },
    activeFilterCount() {
      return Object.values(this.filters).filter((value) => String(value).trim() !== "").length;
    },
    resultRange() {
      if (!this.pagination.total) return "Sin resultados";
      const from = this.pagination.from || ((this.pagination.current_page - 1) * this.pagination.per_page) + 1;
      const to = this.pagination.to || Math.min(this.pagination.current_page * this.pagination.per_page, this.pagination.total);
      return `${from}–${to} de ${this.pagination.total}`;
    },
  },
  async mounted() {
    window.addEventListener("keydown", this.handleEscape);
    await Promise.all([this.loadCatalogs(), this.load(1)]);
  },
  beforeUnmount() {
    window.removeEventListener("keydown", this.handleEscape);
  },
  methods: {
    capability(action, permission) {
      const serverValue = this.capabilities[`can_${action}`] ?? this.capabilities[action];
      if (typeof serverValue === "boolean") return serverValue;
      return this.isSuperAdmin || this.permissions.includes(permission);
    },
    mergeOptions(defaults, configured = []) {
      const configuredOptions = Array.isArray(configured)
        ? configured
        : Object.entries(configured || {}).map(([value, label]) => ({ value, label }));
      const entries = [...defaults, ...configuredOptions.map(normalizedOption)].filter((option) => option.value);
      return Array.from(new Map(entries.map((option) => [String(option.value), option])).values());
    },
    async loadCatalogs() {
      try {
        const response = await getDocumentationCatalogs();
        const data = response.data?.data || response.data || {};
        this.catalogs = {
          categories: data.categories || this.catalogs.categories,
          years: data.years || this.catalogs.years,
        };
        this.catalogStats = data.stats || {};
        this.summary = {
          ...this.summary,
          total: Number(this.catalogStats.total ?? this.summary.total),
          published: Number(this.catalogStats.active ?? this.summary.published),
          public: Number(this.catalogStats.public ?? this.summary.public),
          years: (data.years || this.catalogs.years || []).length,
        };
        this.capabilities = { ...this.capabilities, ...(data.capabilities || response.data?.capabilities || {}) };
      } catch (error) {
        // El listado sigue siendo utilizable con catálogos base si este apoyo no responde.
      }
    },
    normalizeResponse(payload = {}) {
      const nested = payload.data && !Array.isArray(payload.data) ? payload.data : null;
      const items = Array.isArray(payload.data)
        ? payload.data
        : Array.isArray(nested?.data)
          ? nested.data
          : [];
      const meta = payload.meta || nested?.meta || nested || payload;

      return {
        items,
        summary: payload.summary || nested?.summary || {},
        catalogs: payload.catalogs || nested?.catalogs || {},
        capabilities: payload.capabilities || nested?.capabilities || {},
        pagination: {
          current_page: Number(meta.current_page || 1),
          last_page: Number(meta.last_page || 1),
          per_page: Number(meta.per_page || 15),
          total: Number(meta.total ?? items.length),
          from: Number(meta.from || 0),
          to: Number(meta.to || 0),
        },
      };
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await listDocumentation({
          page,
          per_page: this.pagination.per_page,
          search: this.filters.search.trim() || null,
          category: this.filters.category || null,
          year: this.filters.year || null,
          is_active: this.filters.is_active === "" ? null : this.filters.is_active,
          is_public: this.filters.is_public === "" ? null : this.filters.is_public,
        });
        const normalized = this.normalizeResponse(response.data || {});
        this.items = normalized.items;
        this.pagination = normalized.pagination;
        const summary = { ...this.catalogStats, ...(normalized.summary || {}) };
        this.summary = {
          total: normalized.pagination.total,
          published: Number(summary.active ?? summary.published ?? summary.active_count ?? 0),
          public: Number(summary.public ?? summary.public_count ?? 0),
          years: (this.catalogs.years || []).length,
        };
        this.catalogs = {
          categories: normalized.catalogs.categories || this.catalogs.categories,
          years: normalized.catalogs.years || this.catalogs.years,
        };
        this.capabilities = { ...this.capabilities, ...normalized.capabilities };
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible cargar la documentación institucional.");
      } finally {
        this.loading = false;
      }
    },
    applyFilters() {
      this.load(1);
    },
    clearFilters() {
      this.filters = { search: "", category: "", year: "", is_active: "", is_public: "" };
      this.load(1);
    },
    openCreate() {
      if (!this.canCreate) return;
      this.rememberModalTrigger();
      this.form = emptyForm();
      this.validationErrors = {};
      this.showForm = true;
      this.$nextTick(() => this.$refs.titleInput?.focus());
    },
    openEdit(item) {
      if (!this.canUpdate) return;
      this.rememberModalTrigger();
      this.form = {
        id: item.id,
        title: item.title || "",
        category: item.category || "otro",
        year: Number(item.year) || currentYear,
        version: item.version || "",
        description: item.description || "",
        is_public: this.asBoolean(item.is_public),
        is_active: this.asBoolean(item.is_active, true),
        file: null,
        current_file_name: item.original_name || item.file_name || item.filename || "",
      };
      this.validationErrors = {};
      this.showForm = true;
      this.$nextTick(() => this.$refs.titleInput?.focus());
    },
    closeForm() {
      if (this.saving) return;
      this.showForm = false;
      this.validationErrors = {};
      this.form = emptyForm();
      if (this.$refs.fileInput) this.$refs.fileInput.value = "";
      this.restoreModalFocus();
    },
    handleEscape(event) {
      if (event.key === "Escape" && this.showForm && !this.saving) this.closeForm();
    },
    onFileSelected(event) {
      const file = event?.target?.files?.[0] || null;
      this.validationErrors.file = "";
      if (!file) {
        this.form.file = null;
        return;
      }

      const extension = file.name.split(".").pop()?.toLowerCase() || "";
      if (!ACCEPTED_EXTENSIONS.includes(extension)) {
        this.form.file = null;
        this.validationErrors.file = "Selecciona un archivo PDF u Office válido.";
        event.target.value = "";
        return;
      }
      if (file.size > MAX_FILE_SIZE) {
        this.form.file = null;
        this.validationErrors.file = "El archivo no puede superar los 25 MB.";
        event.target.value = "";
        return;
      }

      this.form.file = file;
    },
    validate() {
      const errors = {};
      if (!this.form.title.trim()) errors.title = "Ingresa el título del documento.";
      if (!this.form.category) errors.category = "Selecciona una categoría.";
      const year = Number(this.form.year);
      if (!Number.isInteger(year) || year < 1900 || year > currentYear + 1) errors.year = "Ingresa un año válido.";
      if (!this.isEditing && !this.form.file) errors.file = "Selecciona el archivo que deseas publicar.";
      this.validationErrors = errors;
      return Object.keys(errors).length === 0;
    },
    async save() {
      if (!this.validate()) return;
      const wasEditing = this.isEditing;
      this.saving = true;
      this.validationErrors = {};

      try {
        const payload = {
          ...this.form,
          title: this.form.title.trim(),
          category: this.form.category,
          year: Number(this.form.year),
          version: this.form.version.trim(),
          description: this.form.description.trim(),
          is_public: Boolean(this.form.is_public),
        };
        const response = this.isEditing
          ? await updateDocumentation(this.form.id, payload)
          : await createDocumentation(payload);

        this.showForm = false;
        await this.loadCatalogs();
        await this.load(wasEditing ? this.pagination.current_page : 1);
        this.form = emptyForm();
        this.restoreModalFocus();
        await Swal.fire({
          icon: "success",
          title: wasEditing ? "Documento actualizado" : "Documento incorporado",
          text: response?.data?.message || "La documentación quedó disponible con su trazabilidad de año y versión.",
          confirmButtonText: "Entendido",
        });
      } catch (error) {
        const serverErrors = error?.response?.data?.errors || {};
        this.validationErrors = Object.fromEntries(
          Object.entries(serverErrors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]),
        );
        await Swal.fire({
          icon: "error",
          title: "No se pudo guardar",
          text: this.errorMessage(error, "Revisa los datos e intenta nuevamente."),
          confirmButtonText: "Entendido",
        });
      } finally {
        this.saving = false;
      }
    },
    async download(item) {
      if (this.downloadingId) return;
      this.downloadingId = item.id;

      try {
        const response = await downloadDocumentation(item.id);
        const filename = filenameFromDisposition(
          response.headers?.["content-disposition"],
          item.original_name || item.file_name || `${item.title || "documento"}.pdf`,
        );
        const url = URL.createObjectURL(response.data);
        const link = document.createElement("a");
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      } catch (error) {
        await Swal.fire({
          icon: "error",
          title: "No se pudo descargar",
          text: this.errorMessage(error, "El archivo no está disponible en este momento."),
          confirmButtonText: "Entendido",
        });
      } finally {
        this.downloadingId = null;
      }
    },
    async remove(item) {
      if (!this.canDelete || this.deletingId) return;
      const result = await Swal.fire({
        icon: "warning",
        title: "¿Eliminar este documento?",
        html: `<strong>${this.escapeHtml(item.title || "Documento")}</strong><br><small>Se eliminará también el archivo asociado.</small>`,
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#c33f4a",
        reverseButtons: true,
      });
      if (!result.isConfirmed) return;

      this.deletingId = item.id;
      try {
        const response = await removeDocumentation(item.id);
        await this.loadCatalogs();
        await this.load(this.pagination.current_page);
        await Swal.fire({
          icon: "success",
          title: "Documento eliminado",
          text: response?.data?.message || "El registro fue eliminado correctamente.",
          confirmButtonText: "Entendido",
        });
      } catch (error) {
        await Swal.fire({
          icon: "error",
          title: "No se pudo eliminar",
          text: this.errorMessage(error, "Intenta nuevamente."),
          confirmButtonText: "Entendido",
        });
      } finally {
        this.deletingId = null;
      }
    },
    escapeHtml(value) {
      const element = document.createElement("div");
      element.textContent = String(value || "");
      return element.innerHTML;
    },
    rememberModalTrigger() {
      this.modalTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    },
    restoreModalFocus() {
      const trigger = this.modalTrigger;
      this.modalTrigger = null;
      this.$nextTick(() => {
        if (trigger?.isConnected) trigger.focus();
      });
    },
    asBoolean(value, fallback = false) {
      if (value === undefined || value === null || value === "") return fallback;
      return value === true || value === 1 || value === "1" || value === "true";
    },
    errorMessage(error, fallback) {
      const status = Number(error?.response?.status || 0);
      const serverMessage = String(error?.response?.data?.message || "");
      const exposesInfrastructure = /SQLSTATE|QueryException|PDOException|Connection:\s|select\s.+\sfrom\s[`\"]/i.test(serverMessage);
      if (status >= 500 || exposesInfrastructure) return fallback;

      const errors = error?.response?.data?.errors || {};
      const first = Object.values(errors)[0];
      return (Array.isArray(first) ? first[0] : first) || serverMessage || fallback;
    },
    optionLabel(options, value) {
      return options.find((option) => String(option.value) === String(value))?.label || value || "Sin información";
    },
    statusLabel(value) {
      return value ? "Activo" : "Inactivo";
    },
    statusClass(value) {
      return value ? "is-published" : "is-archived";
    },
    formatDate(value) {
      if (!value) return "Sin registro";
      return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium" }).format(new Date(String(value).replace(" ", "T")));
    },
    formatBytes(bytes) {
      if (typeof bytes === "string" && /[a-z]/i.test(bytes)) return bytes;
      const size = Number(bytes || 0);
      if (!size) return "";
      if (size < 1024 * 1024) return `${Math.max(1, Math.round(size / 1024))} KB`;
      return `${(size / (1024 * 1024)).toFixed(1)} MB`;
    },
    fileExtension(item) {
      return String(item.extension || item.file_extension || item.original_name?.split(".").pop() || "DOC").toUpperCase();
    },
  },
};
</script>

<template>
  <Layout>
    <main class="documentation-page">
      <header class="documentation-hero">
        <div class="documentation-hero__main">
          <div class="documentation-hero__icon" aria-hidden="true"><i class="bx bx-folder-open"></i></div>
          <div>
            <span class="documentation-eyebrow">PÁGINA WEB · REPOSITORIO INSTITUCIONAL</span>
            <h2>Documentación</h2>
            <p>Administra publicaciones oficiales, años y versiones desde un solo lugar, con visibilidad pública controlada.</p>
          </div>
        </div>
        <button v-if="canCreate" class="documentation-primary-action" type="button" @click="openCreate">
          <i class="bx bx-cloud-upload"></i><span>Subir documento</span>
        </button>
      </header>

      <section class="documentation-summary" aria-label="Resumen documental">
        <article>
          <span class="documentation-summary__icon"><i class="bx bx-library"></i></span>
          <div><small>Documentos</small><strong>{{ summary.total }}</strong><span>Registros encontrados</span></div>
        </article>
        <article>
          <span class="documentation-summary__icon is-green"><i class="bx bx-check-shield"></i></span>
          <div><small>Activos</small><strong>{{ summary.published }}</strong><span>Versiones vigentes</span></div>
        </article>
        <article>
          <span class="documentation-summary__icon is-gold"><i class="bx bx-world"></i></span>
          <div><small>Visibles en la web</small><strong>{{ summary.public }}</strong><span>Acceso público habilitado</span></div>
        </article>
        <article>
          <span class="documentation-summary__icon is-blue"><i class="bx bx-calendar-star"></i></span>
          <div><small>Años disponibles</small><strong>{{ summary.years }}</strong><span>Periodos documentados</span></div>
        </article>
      </section>

      <section class="documentation-workspace">
        <div class="documentation-workspace__heading">
          <div>
            <span>ARCHIVO CENTRAL</span>
            <h3>Biblioteca de documentos</h3>
            <p>Busca por nombre y acota los resultados por categoría, año, estado o publicación.</p>
          </div>
          <span class="documentation-result-count">{{ resultRange }}</span>
        </div>

        <form class="documentation-filters" @submit.prevent="applyFilters">
          <label class="documentation-search">
            <span class="visually-hidden">Buscar documento</span>
            <i class="bx bx-search"></i>
            <input v-model="filters.search" type="search" placeholder="Buscar por título, versión o descripción…">
          </label>
          <label>
            <span>Categoría</span>
            <select v-model="filters.category">
              <option value="">Todas</option>
              <option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <label>
            <span>Año</span>
            <select v-model="filters.year">
              <option value="">Todos</option>
              <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
            </select>
          </label>
          <label>
            <span>Estado</span>
            <select v-model="filters.is_active">
              <option value="">Todos</option>
              <option value="1">Activos</option>
              <option value="0">Inactivos</option>
            </select>
          </label>
          <label>
            <span>Visibilidad</span>
            <select v-model="filters.is_public">
              <option value="">Todas</option>
              <option value="1">Público</option>
              <option value="0">Interno</option>
            </select>
          </label>
          <button class="documentation-filter-button" type="submit" :disabled="loading">
            <i class="bx bx-filter-alt"></i><span>Filtrar</span>
          </button>
          <button v-if="activeFilterCount" class="documentation-clear-button" type="button" @click="clearFilters">
            <i class="bx bx-x"></i><span>Limpiar</span><b>{{ activeFilterCount }}</b>
          </button>
        </form>

        <div v-if="error" class="documentation-alert" role="alert">
          <i class="bx bx-error-circle"></i>
          <div><strong>No pudimos cargar los documentos</strong><span>{{ error }}</span></div>
          <button type="button" @click="load(pagination.current_page)">Reintentar</button>
        </div>

        <LoadingState v-else-if="loading" class="documentation-loading" message="Cargando documentación…" />

        <div v-else-if="!items.length" class="documentation-empty">
          <span><i class="bx bx-folder-open"></i></span>
          <h4>{{ activeFilterCount ? "No encontramos coincidencias" : "Aún no hay documentos" }}</h4>
          <p>{{ activeFilterCount ? "Ajusta los filtros para ampliar la búsqueda." : "Incorpora el primer documento para comenzar el repositorio institucional." }}</p>
          <button v-if="activeFilterCount" type="button" @click="clearFilters">Limpiar filtros</button>
          <button v-else-if="canCreate" type="button" @click="openCreate">Subir primer documento</button>
        </div>

        <div v-else class="documentation-list" aria-live="polite">
          <article v-for="item in items" :key="item.id" class="documentation-item">
            <div class="documentation-file-mark" aria-hidden="true">
              <i class="bx bxs-file-blank"></i>
              <small>{{ fileExtension(item) }}</small>
            </div>
            <div class="documentation-item__main">
              <div class="documentation-item__labels">
                <span class="documentation-category">{{ optionLabel(categoryOptions, item.category) }}</span>
                <span :class="['documentation-status', statusClass(asBoolean(item.is_active, true))]"><i></i>{{ statusLabel(asBoolean(item.is_active, true)) }}</span>
                <span :class="['documentation-visibility', { 'is-public': asBoolean(item.is_public) }]">
                  <i :class="asBoolean(item.is_public) ? 'bx bx-world' : 'bx bx-lock-alt'"></i>{{ asBoolean(item.is_public) ? "Público" : "Interno" }}
                </span>
              </div>
              <h4>{{ item.title }}</h4>
              <p>{{ item.description || "Sin descripción editorial." }}</p>
              <div class="documentation-item__meta">
                <span><i class="bx bx-calendar"></i>{{ item.year || "Sin año" }}</span>
                <span><i class="bx bx-git-branch"></i>Versión {{ item.version || "—" }}</span>
                <span v-if="item.file_size_human || item.file_size"><i class="bx bx-data"></i>{{ item.file_size_human || formatBytes(item.file_size) }}</span>
                <span><i class="bx bx-time-five"></i>{{ formatDate(item.updated_at || item.created_at) }}</span>
              </div>
            </div>
            <div class="documentation-item__actions">
              <button type="button" class="is-download" :disabled="downloadingId === item.id" title="Descargar archivo" @click="download(item)">
                <i :class="downloadingId === item.id ? 'bx bx-loader-alt bx-spin' : 'bx bx-download'"></i><span>Descargar</span>
              </button>
              <button v-if="canUpdate" type="button" title="Editar metadatos o reemplazar archivo" @click="openEdit(item)">
                <i class="bx bx-edit-alt"></i><span>Editar</span>
              </button>
              <button v-if="canDelete" type="button" class="is-danger" :disabled="deletingId === item.id" title="Eliminar documento" @click="remove(item)">
                <i :class="deletingId === item.id ? 'bx bx-loader-alt bx-spin' : 'bx bx-trash'"></i><span>Eliminar</span>
              </button>
            </div>
          </article>
        </div>

        <nav v-if="pagination.last_page > 1" class="documentation-pagination" aria-label="Paginación de documentos">
          <button type="button" :disabled="pagination.current_page <= 1 || loading" @click="load(pagination.current_page - 1)">
            <i class="bx bx-chevron-left"></i><span>Anterior</span>
          </button>
          <span>Página <strong>{{ pagination.current_page }}</strong> de {{ pagination.last_page }}</span>
          <button type="button" :disabled="pagination.current_page >= pagination.last_page || loading" @click="load(pagination.current_page + 1)">
            <span>Siguiente</span><i class="bx bx-chevron-right"></i>
          </button>
        </nav>
      </section>

      <Teleport to="body">
        <div v-if="showForm" class="documentation-modal" role="presentation" @mousedown.self="closeForm">
          <section class="documentation-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="documentation-modal-title">
            <header class="documentation-modal__header">
              <div class="documentation-modal__icon"><i :class="isEditing ? 'bx bx-edit-alt' : 'bx bx-cloud-upload'"></i></div>
              <div>
                <span>{{ isEditing ? "ACTUALIZAR VERSIÓN" : "NUEVO REGISTRO" }}</span>
                <h3 id="documentation-modal-title">{{ isEditing ? "Editar documento" : "Subir documento" }}</h3>
                <p>{{ isEditing ? "Ajusta los metadatos o reemplaza el archivo manteniendo su registro." : "Identifica el archivo para que sea fácil encontrarlo y publicar la versión correcta." }}</p>
              </div>
              <button type="button" class="documentation-modal__close" :disabled="saving" aria-label="Cerrar" @click="closeForm"><i class="bx bx-x"></i></button>
            </header>

            <form class="documentation-form" @submit.prevent="save">
              <div class="documentation-form__body">
                <label class="documentation-field is-wide">
                  <span>Título <b>*</b></span>
                  <input ref="titleInput" v-model="form.title" type="text" maxlength="180" placeholder="Ej.: Proyecto Educativo Institucional 2023" :class="{ 'is-invalid': validationErrors.title }">
                  <small v-if="validationErrors.title" class="documentation-field__error">{{ validationErrors.title }}</small>
                </label>

                <label class="documentation-field">
                  <span>Categoría <b>*</b></span>
                  <select v-model="form.category" :class="{ 'is-invalid': validationErrors.category }">
                    <option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                  <small v-if="validationErrors.category" class="documentation-field__error">{{ validationErrors.category }}</small>
                </label>

                <label class="documentation-field">
                  <span>Año <b>*</b></span>
                  <input v-model.number="form.year" type="number" min="1900" :max="new Date().getFullYear() + 1" :class="{ 'is-invalid': validationErrors.year }">
                  <small v-if="validationErrors.year" class="documentation-field__error">{{ validationErrors.year }}</small>
                </label>

                <label class="documentation-field">
                  <span>Versión <em>Opcional</em></span>
                  <input v-model="form.version" type="text" maxlength="40" placeholder="Ej.: 1.0, Final o revisión 2026">
                </label>

                <label class="documentation-field">
                  <span>Estado <b>*</b></span>
                  <select v-model="form.is_active">
                    <option :value="true">Activo</option>
                    <option :value="false">Inactivo</option>
                  </select>
                </label>

                <label class="documentation-field is-wide">
                  <span>Descripción</span>
                  <textarea v-model="form.description" rows="3" maxlength="5000" placeholder="Explica brevemente el alcance y contenido del documento."></textarea>
                  <small class="documentation-field__counter">{{ form.description.length }}/5000</small>
                </label>

                <div class="documentation-file-field is-wide">
                  <div class="documentation-file-field__copy">
                    <span>Archivo {{ isEditing ? "de reemplazo" : "" }} <b v-if="!isEditing">*</b></span>
                    <p>{{ isEditing ? "Déjalo vacío para conservar el archivo actual." : "PDF, Word o Excel, hasta 25 MB." }}</p>
                    <small v-if="isEditing && form.current_file_name"><i class="bx bx-paperclip"></i> Actual: {{ form.current_file_name }}</small>
                  </div>
                  <label class="documentation-file-picker" :class="{ 'has-file': form.file, 'is-invalid': validationErrors.file }">
                    <input ref="fileInput" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" @change="onFileSelected">
                    <i :class="form.file ? 'bx bx-check' : 'bx bx-upload'"></i>
                    <span>{{ form.file ? form.file.name : (isEditing ? "Reemplazar archivo" : "Seleccionar archivo") }}</span>
                  </label>
                  <small v-if="validationErrors.file" class="documentation-field__error">{{ validationErrors.file }}</small>
                </div>

                <label class="documentation-public-toggle is-wide">
                  <input v-model="form.is_public" type="checkbox">
                  <span class="documentation-public-toggle__control"><i></i></span>
                  <span class="documentation-public-toggle__copy">
                    <strong>Visible en la página web</strong>
                    <small>Solo los documentos publicados y con esta opción activa podrán ofrecerse al público.</small>
                  </span>
                  <i class="bx bx-world"></i>
                </label>
              </div>

              <footer>
                <button type="button" class="documentation-modal__cancel" :disabled="saving" @click="closeForm">Cancelar</button>
                <button type="submit" class="documentation-modal__save" :disabled="saving">
                  <i :class="saving ? 'bx bx-loader-alt bx-spin' : 'bx bx-check'"></i>
                  <span>{{ saving ? "Guardando…" : (isEditing ? "Guardar cambios" : "Subir documento") }}</span>
                </button>
              </footer>
            </form>
          </section>
        </div>
      </Teleport>
    </main>
  </Layout>
</template>

<style scoped>
.documentation-page {
  --doc-navy: #123f59;
  --doc-deep: #0a2f43;
  --doc-teal: #28798a;
  --doc-green: #6e987a;
  --doc-gold: #d9a24d;
  --doc-ink: #203b4b;
  --doc-muted: #6b7f8c;
  --doc-line: #dfe9ed;
  min-height: 100vh;
  padding: 1.5rem;
  color: var(--doc-ink);
  background:
    radial-gradient(circle at 96% 0%, rgba(40, 121, 138, .1), transparent 26rem),
    linear-gradient(180deg, #f5f9fa 0, #fbfcfc 24rem);
}

.documentation-hero {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  overflow: hidden;
  padding: 1.75rem 2rem;
  border: 1px solid rgba(255, 255, 255, .18);
  border-radius: 1.5rem;
  color: #fff;
  background:
    radial-gradient(circle at 86% 18%, rgba(217, 162, 77, .19), transparent 24rem),
    linear-gradient(118deg, #0a3045 0%, #12546d 52%, #297f8c 100%);
  box-shadow: 0 1.1rem 2.8rem rgba(17, 61, 82, .17);
}

.documentation-hero::after {
  position: absolute;
  inset: 0;
  pointer-events: none;
  content: "";
  opacity: .35;
  background-image: linear-gradient(120deg, transparent 0 68%, rgba(255,255,255,.08) 68% 69%, transparent 69% 75%, rgba(255,255,255,.05) 75% 76%, transparent 76%);
}

.documentation-hero__main,
.documentation-primary-action { position: relative; z-index: 1; }
.documentation-hero__main { display: flex; align-items: center; gap: 1.15rem; max-width: 52rem; }
.documentation-hero__icon { display: grid; flex: 0 0 4.25rem; width: 4.25rem; height: 4.25rem; place-items: center; border: 1px solid rgba(255,255,255,.26); border-radius: 1.25rem; font-size: 2rem; background: rgba(255,255,255,.12); box-shadow: inset 0 1px rgba(255,255,255,.18); }
.documentation-eyebrow { display: block; margin-bottom: .3rem; color: #f2c675; font-size: .68rem; font-weight: 800; letter-spacing: .14em; }
.documentation-hero h2 { margin: 0; color: #fff; font-size: clamp(1.85rem, 3vw, 2.45rem); font-weight: 760; letter-spacing: -.035em; }
.documentation-hero p { max-width: 47rem; margin: .4rem 0 0; color: rgba(255,255,255,.78); font-size: .93rem; line-height: 1.55; }
.documentation-primary-action { display: inline-flex; flex: 0 0 auto; align-items: center; gap: .65rem; min-height: 3.15rem; padding: .75rem 1.15rem; border: 1px solid rgba(255,255,255,.34); border-radius: .9rem; color: var(--doc-deep); font-weight: 750; background: linear-gradient(120deg, #fff 0%, #f3f1df 54%, #c6dcbf 100%); box-shadow: 0 .65rem 1.4rem rgba(4, 30, 44, .2); transition: box-shadow .2s ease, border-color .2s ease; }
.documentation-primary-action:hover { border-color: #fff; box-shadow: 0 .85rem 1.8rem rgba(4, 30, 44, .28); }
.documentation-primary-action i { font-size: 1.25rem; }

.documentation-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .9rem; margin: 1rem 0; }
.documentation-summary article { display: flex; align-items: center; gap: .85rem; min-width: 0; padding: 1rem 1.05rem; border: 1px solid var(--doc-line); border-radius: 1.05rem; background: rgba(255,255,255,.92); box-shadow: 0 .45rem 1.35rem rgba(16, 60, 79, .055); }
.documentation-summary__icon { display: grid; flex: 0 0 2.75rem; width: 2.75rem; height: 2.75rem; place-items: center; border-radius: .85rem; color: #fff; font-size: 1.3rem; background: linear-gradient(145deg, var(--doc-deep), var(--doc-teal)); }
.documentation-summary__icon.is-green { background: linear-gradient(145deg, #397062, #7da27f); }
.documentation-summary__icon.is-gold { background: linear-gradient(145deg, #aa762f, #e3b65d); }
.documentation-summary__icon.is-blue { background: linear-gradient(145deg, #315b79, #6390a3); }
.documentation-summary article div { min-width: 0; }
.documentation-summary small, .documentation-summary span { display: block; }
.documentation-summary small { margin-bottom: .12rem; color: var(--doc-muted); font-size: .67rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
.documentation-summary strong { display: block; color: var(--doc-deep); font-size: 1.35rem; line-height: 1.05; }
.documentation-summary article div > span { overflow: hidden; margin-top: .18rem; color: #83939d; font-size: .7rem; text-overflow: ellipsis; white-space: nowrap; }

.documentation-workspace { padding: 1.45rem; border: 1px solid var(--doc-line); border-radius: 1.35rem; background: #fff; box-shadow: 0 1rem 2.8rem rgba(18, 63, 89, .07); }
.documentation-workspace__heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: 1.15rem; }
.documentation-workspace__heading > div > span { color: var(--doc-teal); font-size: .66rem; font-weight: 850; letter-spacing: .14em; }
.documentation-workspace h3 { margin: .2rem 0 .25rem; color: var(--doc-deep); font-size: 1.35rem; font-weight: 760; letter-spacing: -.02em; }
.documentation-workspace__heading p { margin: 0; color: var(--doc-muted); font-size: .82rem; }
.documentation-result-count { flex: 0 0 auto; padding: .4rem .7rem; border: 1px solid #d9e5e9; border-radius: 999px; color: var(--doc-muted); font-size: .72rem; font-weight: 700; background: #f7fafb; }

.documentation-filters { display: grid; grid-template-columns: minmax(14rem, 1.7fr) repeat(4, minmax(8.5rem, 1fr)) auto auto; align-items: end; gap: .65rem; margin-bottom: 1.15rem; padding: .85rem; border: 1px solid #e1eaed; border-radius: 1rem; background: #f7fafb; }
.documentation-filters label { min-width: 0; }
.documentation-filters label > span { display: block; margin: 0 0 .3rem .1rem; color: #607784; font-size: .65rem; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; }
.documentation-filters input, .documentation-filters select { width: 100%; height: 2.65rem; padding: 0 .75rem; border: 1px solid #d6e2e6; border-radius: .75rem; outline: 0; color: var(--doc-ink); font-size: .8rem; background: #fff; transition: border-color .18s ease, box-shadow .18s ease; }
.documentation-filters input:focus, .documentation-filters select:focus { border-color: #65a3aa; box-shadow: 0 0 0 .18rem rgba(40,121,138,.12); }
.documentation-search { position: relative; }
.documentation-search > i { position: absolute; z-index: 1; left: .8rem; bottom: .78rem; color: #7b909b; font-size: 1rem; }
.documentation-search input { padding-left: 2.25rem; }
.documentation-filter-button, .documentation-clear-button { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; height: 2.65rem; padding: 0 .85rem; border-radius: .75rem; font-size: .76rem; font-weight: 750; white-space: nowrap; }
.documentation-filter-button { border: 0; color: #fff; background: linear-gradient(125deg, var(--doc-deep), var(--doc-teal) 68%, var(--doc-green)); box-shadow: 0 .35rem .8rem rgba(18,63,89,.16); }
.documentation-clear-button { border: 1px solid #d6e2e6; color: #536c79; background: #fff; }
.documentation-clear-button b { display: grid; min-width: 1.15rem; height: 1.15rem; place-items: center; border-radius: 999px; color: #fff; font-size: .62rem; background: var(--doc-teal); }

.documentation-alert { display: flex; align-items: center; gap: .8rem; min-height: 5rem; padding: 1rem; border: 1px solid #f0cbd0; border-radius: 1rem; color: #913841; background: #fff5f6; }
.documentation-alert > i { font-size: 1.5rem; }
.documentation-alert div { flex: 1; }
.documentation-alert strong, .documentation-alert span { display: block; }
.documentation-alert span { margin-top: .15rem; font-size: .78rem; }
.documentation-alert button { border: 1px solid #dfaab0; border-radius: .65rem; padding: .45rem .7rem; color: #8f3540; font-size: .75rem; font-weight: 700; background: #fff; }
.documentation-loading { min-height: 15rem; }
.documentation-empty { display: grid; min-height: 18rem; padding: 2rem; place-items: center; align-content: center; text-align: center; border: 1px dashed #cbdce1; border-radius: 1.05rem; background: linear-gradient(150deg, #fbfdfd, #f4f9fa); }
.documentation-empty > span { display: grid; width: 4.25rem; height: 4.25rem; place-items: center; margin-bottom: .8rem; border-radius: 1.25rem; color: var(--doc-teal); font-size: 2rem; background: #e4f1f2; }
.documentation-empty h4 { margin: 0; color: var(--doc-deep); font-size: 1.15rem; }
.documentation-empty p { max-width: 30rem; margin: .4rem 0 1rem; color: var(--doc-muted); font-size: .82rem; }
.documentation-empty button { padding: .55rem .85rem; border: 0; border-radius: .7rem; color: #fff; font-size: .75rem; font-weight: 750; background: linear-gradient(125deg, var(--doc-deep), var(--doc-teal)); }

.documentation-list { display: grid; gap: .7rem; }
.documentation-item { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 1rem; padding: .95rem; border: 1px solid #deeaed; border-radius: 1rem; background: linear-gradient(115deg, #fff 0%, #fff 74%, #f7faf9 100%); transition: border-color .18s ease, box-shadow .18s ease; }
.documentation-item:hover { border-color: #bad2d8; box-shadow: 0 .55rem 1.25rem rgba(18,63,89,.075); }
.documentation-file-mark { position: relative; display: grid; flex: 0 0 auto; width: 3.6rem; height: 4rem; place-items: center; border-radius: .85rem; color: #fff; background: linear-gradient(145deg, var(--doc-deep), #1d7083); box-shadow: 0 .45rem .9rem rgba(18,63,89,.16); }
.documentation-file-mark > i { font-size: 2rem; }
.documentation-file-mark small { position: absolute; right: .3rem; bottom: .28rem; padding: .08rem .24rem; border-radius: .25rem; color: var(--doc-deep); font-size: .48rem; font-weight: 900; background: #f2c675; }
.documentation-item__main { min-width: 0; }
.documentation-item__labels { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin-bottom: .35rem; }
.documentation-category, .documentation-status, .documentation-visibility { display: inline-flex; align-items: center; gap: .3rem; padding: .22rem .45rem; border-radius: 999px; font-size: .6rem; font-weight: 800; letter-spacing: .025em; }
.documentation-category { color: #1f6573; background: #e6f2f3; }
.documentation-status { color: #8b6c34; background: #fff4dd; }
.documentation-status i { width: .35rem; height: .35rem; border-radius: 50%; background: currentColor; }
.documentation-status.is-published { color: #3f745c; background: #e7f3eb; }
.documentation-status.is-archived { color: #6f7780; background: #eceff1; }
.documentation-visibility { color: #6d7780; background: #f0f2f3; }
.documentation-visibility.is-public { color: #456f52; background: #e8f2e7; }
.documentation-item h4 { overflow: hidden; margin: 0; color: var(--doc-deep); font-size: .98rem; font-weight: 760; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
.documentation-item p { display: -webkit-box; overflow: hidden; margin: .23rem 0 .48rem; color: var(--doc-muted); font-size: .75rem; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.documentation-item__meta { display: flex; flex-wrap: wrap; gap: .45rem .85rem; color: #788c97; font-size: .66rem; }
.documentation-item__meta span { display: inline-flex; align-items: center; gap: .25rem; }
.documentation-item__meta i { color: #4f8490; font-size: .85rem; }
.documentation-item__actions { display: flex; align-items: center; gap: .4rem; }
.documentation-item__actions button { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; min-width: 2.4rem; height: 2.4rem; padding: 0 .65rem; border: 1px solid #d8e4e8; border-radius: .65rem; color: #45616f; font-size: .69rem; font-weight: 750; background: #fff; transition: border-color .18s ease, box-shadow .18s ease; }
.documentation-item__actions button:hover { border-color: #a7c7cf; box-shadow: 0 .3rem .7rem rgba(18,63,89,.1); }
.documentation-item__actions button.is-download { border-color: transparent; color: #fff; background: linear-gradient(125deg, var(--doc-deep), var(--doc-teal) 70%, var(--doc-green)); }
.documentation-item__actions button.is-danger { color: #a13e48; }
.documentation-item__actions button:disabled { cursor: wait; opacity: .65; }

.documentation-pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e6edef; }
.documentation-pagination button { display: inline-flex; align-items: center; gap: .3rem; min-height: 2.3rem; padding: .4rem .7rem; border: 1px solid #d8e4e8; border-radius: .65rem; color: #45616f; font-size: .72rem; font-weight: 700; background: #fff; }
.documentation-pagination button:disabled { opacity: .45; }
.documentation-pagination > span { color: var(--doc-muted); font-size: .72rem; }

.documentation-modal { position: fixed; z-index: 1100; inset: 0; display: grid; padding: 1.25rem; place-items: center; background: rgba(5, 25, 37, .62); backdrop-filter: blur(5px); }
.documentation-modal__dialog { overflow: hidden; width: min(46rem, 100%); max-height: calc(100dvh - 2.5rem); border: 1px solid rgba(255,255,255,.2); border-radius: 1.35rem; background: #fff; box-shadow: 0 2rem 5rem rgba(4,25,38,.35); }
.documentation-modal .documentation-modal__dialog > .documentation-modal__header { display: flex; align-items: flex-start; gap: .85rem; padding: 1.2rem 1.3rem; color: #fff !important; background-color: #0a2f43 !important; background-image: linear-gradient(120deg, #0a2f43 0%, #165a70 62%, #438483 100%) !important; }
.documentation-modal__icon { display: grid; flex: 0 0 2.9rem; width: 2.9rem; height: 2.9rem; place-items: center; border: 1px solid rgba(255,255,255,.25); border-radius: .85rem; font-size: 1.35rem; background: rgba(255,255,255,.12); }
.documentation-modal__header > div:nth-child(2) { flex: 1; }
.documentation-modal__header span { color: #f3c976; font-size: .61rem; font-weight: 850; letter-spacing: .13em; }
.documentation-modal__header h3 { margin: .12rem 0 .15rem; color: #fff !important; font-size: 1.25rem; }
.documentation-modal__header p { margin: 0; color: rgba(255,255,255,.78) !important; font-size: .72rem; line-height: 1.4; }
.documentation-modal__header .documentation-modal__icon,
.documentation-modal__header .documentation-modal__close { color: #fff !important; }
.documentation-modal__close { display: grid; flex: 0 0 2rem; width: 2rem; height: 2rem; padding: 0; place-items: center; border: 1px solid rgba(255,255,255,.2); border-radius: .6rem; color: #fff; font-size: 1.25rem; background: rgba(255,255,255,.1); }
.documentation-form { overflow-y: auto; max-height: calc(100dvh - 10.5rem); }
.documentation-form__body { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: .9rem; padding: 1.25rem; }
.documentation-field { position: relative; min-width: 0; }
.documentation-field.is-wide, .documentation-file-field.is-wide, .documentation-public-toggle.is-wide { grid-column: 1 / -1; }
.documentation-field > span, .documentation-file-field__copy > span { display: block; margin-bottom: .35rem; color: #526c79; font-size: .68rem; font-weight: 800; }
.documentation-field b, .documentation-file-field b { color: #b7444f; }
.documentation-field em { margin-left: .3rem; color: #82949d; font-size: .58rem; font-style: normal; font-weight: 650; }
.documentation-field input, .documentation-field select, .documentation-field textarea { width: 100%; border: 1px solid #d5e2e6; border-radius: .72rem; outline: 0; color: var(--doc-ink); font-size: .8rem; background: #fbfdfd; transition: border-color .18s ease, box-shadow .18s ease; }
.documentation-field input, .documentation-field select { height: 2.65rem; padding: 0 .75rem; }
.documentation-field textarea { resize: vertical; min-height: 5.25rem; padding: .65rem .75rem; }
.documentation-field input:focus, .documentation-field select:focus, .documentation-field textarea:focus { border-color: #65a3aa; box-shadow: 0 0 0 .18rem rgba(40,121,138,.12); }
.documentation-field .is-invalid, .documentation-file-picker.is-invalid { border-color: #cf6670; box-shadow: 0 0 0 .16rem rgba(190,62,74,.09); }
.documentation-field__error { display: block; margin-top: .25rem; color: #b33d49; font-size: .66rem; }
.documentation-field__counter { position: absolute; right: .55rem; bottom: .35rem; color: #95a4ab; font-size: .6rem; }
.documentation-file-field { display: grid; grid-template-columns: minmax(0, 1fr) minmax(13rem, auto); align-items: center; gap: .75rem; padding: .8rem; border: 1px dashed #bdd2d8; border-radius: .85rem; background: #f5f9fa; }
.documentation-file-field__copy p { margin: 0; color: var(--doc-muted); font-size: .68rem; }
.documentation-file-field__copy small { display: block; overflow: hidden; max-width: 24rem; margin-top: .35rem; color: #496b79; font-size: .66rem; text-overflow: ellipsis; white-space: nowrap; }
.documentation-file-picker { display: flex; align-items: center; justify-content: center; gap: .45rem; min-height: 2.65rem; padding: .55rem .7rem; border: 1px solid #cbdde1; border-radius: .7rem; color: #366876; font-size: .7rem; font-weight: 750; background: #fff; cursor: pointer; }
.documentation-file-picker.has-file { border-color: #8db79b; color: #3c7053; background: #f2f8f3; }
.documentation-file-picker:focus-within { border-color: #4f96a2; outline: 3px solid rgba(40,121,138,.16); outline-offset: 2px; }
.documentation-file-picker input { position: absolute; width: 1px; height: 1px; opacity: 0; }
.documentation-file-picker span { overflow: hidden; max-width: 15rem; text-overflow: ellipsis; white-space: nowrap; }
.documentation-public-toggle { display: grid; grid-template-columns: auto minmax(0,1fr) auto; align-items: center; gap: .7rem; padding: .8rem; border: 1px solid #dae6e9; border-radius: .85rem; background: linear-gradient(120deg, #f8fbfb, #f3f8f6); cursor: pointer; }
.documentation-public-toggle > input { position: absolute; opacity: 0; pointer-events: none; }
.documentation-public-toggle:focus-within { border-color: #5e99a2; outline: 3px solid rgba(40,121,138,.14); outline-offset: 2px; }
.documentation-public-toggle__control { position: relative; width: 2.4rem; height: 1.35rem; border-radius: 999px; background: #bac8ce; transition: background .18s ease; }
.documentation-public-toggle__control i { position: absolute; top: .2rem; left: .2rem; width: .95rem; height: .95rem; border-radius: 50%; background: #fff; box-shadow: 0 .1rem .25rem rgba(0,0,0,.18); transition: left .18s ease; }
.documentation-public-toggle input:checked + .documentation-public-toggle__control { background: var(--doc-green); }
.documentation-public-toggle input:checked + .documentation-public-toggle__control i { left: 1.25rem; }
.documentation-public-toggle__copy strong, .documentation-public-toggle__copy small { display: block; }
.documentation-public-toggle__copy strong { color: var(--doc-deep); font-size: .75rem; }
.documentation-public-toggle__copy small { margin-top: .1rem; color: var(--doc-muted); font-size: .65rem; }
.documentation-public-toggle > i { color: #568274; font-size: 1.2rem; }
.documentation-form footer { display: flex; justify-content: flex-end; gap: .6rem; padding: .85rem 1.25rem; border-top: 1px solid #e4ecef; background: #f8fafa; }
.documentation-modal__cancel, .documentation-modal__save { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 2.6rem; padding: .55rem .9rem; border-radius: .72rem; font-size: .73rem; font-weight: 750; }
.documentation-modal__cancel { border: 1px solid #d2dfe3; color: #5d737f; background: #fff; }
.documentation-modal__save { border: 0; color: #fff; background: linear-gradient(125deg, var(--doc-deep), var(--doc-teal) 68%, var(--doc-green)); box-shadow: 0 .35rem .8rem rgba(18,63,89,.16); }
.documentation-modal__save:disabled, .documentation-modal__cancel:disabled { cursor: wait; opacity: .65; }

@media (max-width: 1199.98px) {
  .documentation-summary { grid-template-columns: repeat(2, minmax(0,1fr)); }
  .documentation-filters { grid-template-columns: minmax(14rem,2fr) repeat(2,minmax(9rem,1fr)) auto; }
  .documentation-filter-button, .documentation-clear-button { grid-row: 3; }
}

@media (max-width: 767.98px) {
  .documentation-page { padding: .8rem; }
  .documentation-hero { align-items: stretch; padding: 1.2rem; border-radius: 1.15rem; }
  .documentation-hero, .documentation-hero__main { flex-direction: column; }
  .documentation-hero__main { align-items: flex-start; gap: .75rem; }
  .documentation-hero__icon { width: 3.3rem; height: 3.3rem; flex-basis: 3.3rem; border-radius: .9rem; font-size: 1.55rem; }
  .documentation-primary-action { width: 100%; }
  .documentation-summary { grid-template-columns: repeat(2,minmax(0,1fr)); gap: .55rem; }
  .documentation-summary article { align-items: flex-start; gap: .55rem; padding: .75rem; }
  .documentation-summary__icon { width: 2.15rem; height: 2.15rem; flex-basis: 2.15rem; border-radius: .65rem; font-size: 1rem; }
  .documentation-summary article div > span { display: none; }
  .documentation-workspace { padding: .9rem; border-radius: 1.05rem; }
  .documentation-workspace__heading { align-items: flex-start; }
  .documentation-workspace__heading p, .documentation-result-count { display: none; }
  .documentation-filters { grid-template-columns: repeat(2,minmax(0,1fr)); gap: .55rem; padding: .65rem; }
  .documentation-search { grid-column: 1/-1; }
  .documentation-filter-button, .documentation-clear-button { grid-row: auto; }
  .documentation-item { grid-template-columns: auto minmax(0,1fr); align-items: start; gap: .7rem; padding: .8rem; }
  .documentation-file-mark { width: 3rem; height: 3.4rem; }
  .documentation-item h4 { white-space: normal; }
  .documentation-item__actions { grid-column: 1/-1; }
  .documentation-item__actions button { flex: 1; }
  .documentation-pagination { justify-content: space-between; gap: .4rem; }
  .documentation-pagination button span { display: none; }
  .documentation-modal { padding: .5rem; place-items: end center; }
  .documentation-modal__dialog { max-height: calc(100dvh - 1rem); border-radius: 1.2rem 1.2rem .85rem .85rem; }
  .documentation-modal .documentation-modal__dialog > .documentation-modal__header { padding: 1rem; }
  .documentation-modal__header p { display: none; }
  .documentation-form { max-height: calc(100dvh - 7rem); }
  .documentation-form__body { grid-template-columns: 1fr; gap: .75rem; padding: 1rem; }
  .documentation-field.is-wide, .documentation-file-field.is-wide, .documentation-public-toggle.is-wide { grid-column: auto; }
  .documentation-file-field { grid-template-columns: 1fr; }
  .documentation-file-picker { width: 100%; }
  .documentation-form footer { position: sticky; bottom: 0; padding: .75rem 1rem; }
  .documentation-modal__cancel, .documentation-modal__save { flex: 1; }
}

@media (max-width: 430px) {
  .documentation-summary { grid-template-columns: 1fr 1fr; }
  .documentation-summary small { font-size: .58rem; }
  .documentation-filters { grid-template-columns: 1fr; }
  .documentation-search { grid-column: auto; }
  .documentation-filter-button, .documentation-clear-button { width: 100%; }
  .documentation-item__labels { gap: .3rem; }
  .documentation-visibility { display: none; }
}

@media (prefers-reduced-motion: reduce) {
  .documentation-page *, .documentation-page *::before, .documentation-page *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
}
</style>
