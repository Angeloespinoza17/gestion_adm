<script>
import { Ckeditor } from "@ckeditor/ckeditor5-vue";
import { markRaw } from "vue";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../ui/loading-state.vue";
import SiteAdminNavigation from "./site-admin-navigation.vue";
import {
  createInstallation,
  createStudentLife,
  createTestimonial,
  getInstallationCatalogs,
  getStudentLifeCatalogs,
  getTestimonialCatalogs,
  listInstallations,
  listStudentLife,
  listTestimonials,
  removeInstallation,
  removeStudentLife,
  removeTestimonial,
  reorderInstallations,
  updateInstallation,
  updateStudentLife,
  updateTestimonial,
} from "../../services/public-site-content-api";

const IMAGE_TYPES = ["image/jpeg", "image/png", "image/webp"];

const testimonialForm = () => ({
  id: null,
  quote: "",
  author_name: "",
  author_role: "",
  external_image_url: "",
  image_alt: "",
  image: null,
  remove_image: false,
  status: "draft",
  active: true,
  featured: false,
  sort_order: 0,
  published_at: "",
  authorization_confirmed: false,
});

const studentLifeForm = () => ({
  id: null,
  title: "",
  slug: "",
  category: "",
  summary: "",
  body: "",
  external_cover_image_url: "",
  cover_image_alt: "",
  cover_image: null,
  remove_cover_image: false,
  event_date: "",
  meta_title: "",
  meta_description: "",
  status: "draft",
  active: true,
  featured: false,
  sort_order: 0,
  published_at: "",
  gallery: [],
  gallery_alts: [],
  remove_gallery_image_ids: [],
});

const installationForm = () => ({
  id: null,
  title: "",
  slug: "",
  category: "",
  summary: "",
  body: "",
  location_label: "",
  capacity: "",
  accessibility_notes: "",
  features: [""],
  icon: "buildings",
  meta_title: "",
  meta_description: "",
  cover_image_alt: "",
  cover_image: null,
  remove_cover_image: false,
  status: "draft",
  active: true,
  featured: false,
  sort_order: 0,
  published_at: "",
  gallery: [],
  gallery_alts: [],
  remove_gallery_image_ids: [],
  gallery_order: [],
});

const defaultStatuses = [
  { value: "draft", label: "Borrador" },
  { value: "published", label: "Publicado" },
  { value: "archived", label: "Archivado" },
];

const defaultInstallationIcons = [
  { value: "buildings", label: "Edificio" },
  { value: "book", label: "Aprendizaje" },
  { value: "flower", label: "Capilla y pastoral" },
  { value: "trophy", label: "Deporte" },
  { value: "people", label: "Comunidad" },
  { value: "laptop", label: "Tecnología" },
  { value: "science", label: "Ciencias" },
  { value: "palette", label: "Arte" },
  { value: "music", label: "Música" },
  { value: "heart", label: "Bienestar" },
  { value: "tree", label: "Áreas verdes" },
];

const asOption = (option) => {
  if (typeof option === "string") return { value: option, label: option };
  return {
    value: option?.value ?? option?.id ?? "",
    label: option?.label ?? option?.name ?? option?.value ?? "",
  };
};

export default {
  components: { Ckeditor, Layout, LoadingState, SiteAdminNavigation },
  props: {
    contentType: {
      type: String,
      required: true,
      validator: (value) => ["testimonials", "student-life", "installations"].includes(value),
    },
  },
  data() {
    return {
      loading: true,
      saving: false,
      deletingId: null,
      updatingId: null,
      error: null,
      success: null,
      items: [],
      pagination: { current_page: 1, last_page: 1, per_page: 12, total: 0, from: 0, to: 0 },
      summary: { total: 0, published: 0, featured: 0, active: 0 },
      catalogs: { statuses: defaultStatuses, categories: [], icons: [] },
      capabilities: {},
      filters: { search: "", status: "", active: "", featured: "", category: "" },
      form: this.contentType === "testimonials"
        ? testimonialForm()
        : (this.contentType === "installations" ? installationForm() : studentLifeForm()),
      validationErrors: {},
      showForm: false,
      modalTrigger: null,
      imagePreview: "",
      galleryPreviews: [],
      existingGallery: [],
      editor: null,
      editorLoading: false,
      slugManuallyEdited: false,
      editorConfig: {
        toolbar: [
          "heading",
          "|",
          "bold",
          "italic",
          "link",
          "bulletedList",
          "numberedList",
          "blockQuote",
          "|",
          "undo",
          "redo",
        ],
      },
    };
  },
  computed: {
    isTestimonials() {
      return this.contentType === "testimonials";
    },
    isInstallations() {
      return this.contentType === "installations";
    },
    isStudentLife() {
      return this.contentType === "student-life";
    },
    isEditing() {
      return Boolean(this.form.id);
    },
    copy() {
      if (this.isTestimonials) {
        return {
            eyebrow: "Voces de la comunidad",
            title: "Testimonios",
            description: "Administra relatos breves, orden editorial y personas destacadas que aparecen en la portada institucional.",
            singular: "testimonio",
            action: "Nuevo testimonio",
            icon: "bx-message-rounded-dots",
            empty: "Aún no hay testimonios para mostrar.",
          };
      }
      if (this.isInstallations) {
        return {
          eyebrow: "Espacios que educan",
          title: "Instalaciones",
          description: "Administra los espacios del colegio, su información, orden visual, portada y galería accesible en la página pública.",
          singular: "instalación",
          action: "Nueva instalación",
          icon: "bx-buildings",
          empty: "Aún no hay instalaciones registradas.",
          metricLabel: "espacios",
        };
      }
      return {
            eyebrow: "Experiencia CNSC",
            title: "Vida estudiantil",
            description: "Publica experiencias, actividades y espacios formativos con una portada editorial y una lectura individual cuidada.",
            singular: "publicación",
            action: "Nueva publicación",
            icon: "bx-images",
            empty: "Aún no hay publicaciones de vida estudiantil.",
          };
    },
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canManage() {
      const serverValue = this.capabilities.can_manage ?? this.capabilities.manage;
      if (typeof serverValue === "boolean") return serverValue;
      const permission = this.isTestimonials
        ? "gestionar_testimonios"
        : (this.isInstallations ? "gestionar_instalaciones_sitio" : "gestionar_vida_estudiantil");
      return this.permissions.includes("__superadmin__") || this.permissions.includes(permission);
    },
    statusOptions() {
      return this.mergeOptions(defaultStatuses, this.catalogs.statuses);
    },
    categoryOptions() {
      return this.mergeOptions([], this.catalogs.categories);
    },
    iconOptions() {
      return this.mergeOptions(defaultInstallationIcons, this.catalogs.icons);
    },
    activeFilterCount() {
      const fields = this.isTestimonials
        ? ["search", "status", "active", "featured"]
        : ["search", "status", "active", "featured", "category"];
      return fields.filter((field) => String(this.filters[field]).trim() !== "").length;
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
    document.body.classList.remove("web-content-modal-open");
    this.revokePreviews();
  },
  watch: {
    showForm(value) {
      document.body.classList.toggle("web-content-modal-open", Boolean(value));
    },
  },
  methods: {
    emptyForm() {
      if (this.isTestimonials) return testimonialForm();
      return this.isInstallations ? installationForm() : studentLifeForm();
    },
    mergeOptions(defaults, configured = []) {
      const entries = Array.isArray(configured)
        ? configured
        : Object.entries(configured || {}).map(([value, label]) => ({ value, label }));
      return Array.from(new Map([...defaults, ...entries.map(asOption)]
        .filter((option) => option.value)
        .map((option) => [String(option.value), option])).values());
    },
    async ensureEditor() {
      if (this.isTestimonials || this.editor || this.editorLoading) return;
      this.editorLoading = true;
      try {
        const module = await import("@ckeditor/ckeditor5-build-classic");
        this.editor = markRaw(module.default);
      } catch (error) {
        this.error = "No fue posible cargar el editor de contenido.";
      } finally {
        this.editorLoading = false;
      }
    },
    async loadCatalogs() {
      try {
        const response = this.isTestimonials
          ? await getTestimonialCatalogs()
          : (this.isInstallations ? await getInstallationCatalogs() : await getStudentLifeCatalogs());
        const data = response.data?.data || response.data || {};
        this.catalogs = {
          statuses: data.statuses || this.catalogs.statuses,
          categories: data.categories || this.catalogs.categories,
          icons: data.icons || this.catalogs.icons,
        };
        this.summary = this.normalizeSummary(data.stats || this.summary);
        this.capabilities = { ...this.capabilities, ...(data.capabilities || response.data?.capabilities || {}) };
      } catch (error) {
        // El listado mantiene catálogos locales si el endpoint auxiliar no responde.
      }
    },
    normalizeSummary(summary = {}) {
      return {
        total: Number(summary.total ?? this.summary.total ?? 0),
        published: Number(summary.published ?? summary.published_count ?? 0),
        featured: Number(summary.featured ?? summary.featured_count ?? 0),
        active: Number(summary.active ?? summary.active_count ?? 0),
      };
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
        capabilities: payload.capabilities || nested?.capabilities || {},
        pagination: {
          current_page: Number(meta.current_page || 1),
          last_page: Number(meta.last_page || 1),
          per_page: Number(meta.per_page || 12),
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
        const params = {
          page,
          per_page: this.pagination.per_page,
          search: this.filters.search.trim() || null,
          status: this.filters.status || null,
          active: this.filters.active === "" ? null : this.filters.active,
          featured: this.filters.featured === "" ? null : this.filters.featured,
          category: !this.isTestimonials && this.filters.category ? this.filters.category : null,
        };
        const response = this.isTestimonials
          ? await listTestimonials(params)
          : (this.isInstallations ? await listInstallations(params) : await listStudentLife(params));
        const normalized = this.normalizeResponse(response.data || {});
        this.items = normalized.items;
        this.pagination = normalized.pagination;
        this.summary = {
          ...this.normalizeSummary(normalized.summary),
          total: normalized.pagination.total,
        };
        this.capabilities = { ...this.capabilities, ...normalized.capabilities };
      } catch (error) {
        const noun = this.isTestimonials ? "los testimonios" : (this.isInstallations ? "las instalaciones" : "la vida estudiantil");
        this.error = this.errorMessage(error, `No fue posible cargar ${noun}.`);
      } finally {
        this.loading = false;
      }
    },
    applyFilters() {
      this.load(1);
    },
    clearFilters() {
      this.filters = { search: "", status: "", active: "", featured: "", category: "" };
      this.load(1);
    },
    rememberModalTrigger() {
      this.modalTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    },
    restoreModalFocus() {
      const trigger = this.modalTrigger;
      this.modalTrigger = null;
      this.$nextTick(() => trigger?.focus?.());
    },
    async openCreate() {
      if (!this.canManage) return;
      this.rememberModalTrigger();
      this.revokePreviews();
      this.form = this.emptyForm();
      this.slugManuallyEdited = false;
      this.existingGallery = [];
      this.validationErrors = {};
      this.showForm = true;
      await this.ensureEditor();
      this.$nextTick(() => this.$refs.primaryInput?.focus());
    },
    async openEdit(item) {
      if (!this.canManage) return;
      this.rememberModalTrigger();
      this.revokePreviews();
      if (this.isTestimonials) {
        this.form = {
            ...testimonialForm(),
            id: item.id,
            quote: item.quote || "",
            author_name: item.author_name || "",
            author_role: item.author_role || "",
            external_image_url: item.external_image_url || "",
            image_alt: item.image_alt || "",
            status: item.status || "draft",
            active: this.asBoolean(item.active, true),
            featured: this.asBoolean(item.featured),
            sort_order: Number(item.sort_order || 0),
            published_at: this.toDatetimeLocal(item.published_at),
            authorization_confirmed: this.testimonialAuthorized(item),
          };
      } else if (this.isInstallations) {
        this.form = {
          ...installationForm(),
          id: item.id,
          title: item.title || "",
          slug: item.slug || "",
          category: item.category || "",
          summary: item.summary || "",
          body: item.body_html || item.body || "",
          location_label: item.location_label || "",
          capacity: item.capacity ?? "",
          accessibility_notes: item.accessibility_notes || "",
          features: Array.isArray(item.features) && item.features.length ? [...item.features] : [""],
          icon: item.icon || "buildings",
          meta_title: item.meta_title || "",
          meta_description: item.meta_description || "",
          cover_image_alt: item.cover_image_alt || "",
          status: item.status || "draft",
          active: this.asBoolean(item.active, true),
          featured: this.asBoolean(item.featured),
          sort_order: Number(item.sort_order || 0),
          published_at: this.toDatetimeLocal(item.published_at),
          gallery_order: (item.gallery_images || []).map((image) => image.id),
        };
      } else {
        this.form = {
            ...studentLifeForm(),
            id: item.id,
            title: item.title || "",
            slug: item.slug || "",
            category: item.category || "",
            summary: item.summary || "",
            body: item.body_html || item.body || "",
            external_cover_image_url: item.external_cover_image_url || "",
            cover_image_alt: item.cover_image_alt || "",
            event_date: this.toDateInput(item.event_date),
            meta_title: item.meta_title || "",
            meta_description: item.meta_description || "",
            status: item.status || "draft",
            active: this.asBoolean(item.active, true),
            featured: this.asBoolean(item.featured),
            sort_order: Number(item.sort_order || 0),
            published_at: this.toDatetimeLocal(item.published_at),
          };
      }
      this.slugManuallyEdited = !this.isTestimonials;
      this.imagePreview = this.isTestimonials
        ? item.preview_image_url || item.image_url || ""
        : item.preview_cover_image_url || item.cover_image_url || "";
      this.existingGallery = this.isTestimonials ? [] : (item.gallery_images || []).map((image) => ({ ...image }));
      this.validationErrors = {};
      this.showForm = true;
      await this.ensureEditor();
      this.$nextTick(() => this.$refs.primaryInput?.focus());
    },
    closeForm() {
      if (this.saving) return;
      this.showForm = false;
      this.validationErrors = {};
      this.revokePreviews();
      this.form = this.emptyForm();
      this.existingGallery = [];
      if (this.$refs.imageInput) this.$refs.imageInput.value = "";
      if (this.$refs.galleryInput) this.$refs.galleryInput.value = "";
      this.restoreModalFocus();
    },
    handleEscape(event) {
      if (event.key === "Escape" && this.showForm && !this.saving) this.closeForm();
    },
    trapFocus(event) {
      if (event.key !== "Tab") return;
      const dialog = event.currentTarget;
      const focusable = Array.from(dialog?.querySelectorAll?.(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ) || []).filter((element) => element.offsetParent !== null);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    },
    onImageSelected(event) {
      const file = event?.target?.files?.[0] || null;
      this.validationErrors.image = "";
      if (!file) return;
      const maxBytes = (this.isTestimonials ? 5 : 8) * 1024 * 1024;
      if (!IMAGE_TYPES.includes(file.type) || file.size > maxBytes) {
        this.validationErrors.image = `Usa una imagen JPG, PNG o WebP de hasta ${this.isTestimonials ? 5 : 8} MB.`;
        event.target.value = "";
        return;
      }
      if (this.imagePreview.startsWith("blob:")) URL.revokeObjectURL(this.imagePreview);
      this.imagePreview = URL.createObjectURL(file);
      if (this.isTestimonials) {
        this.form.image = file;
        this.form.remove_image = false;
      } else {
        this.form.cover_image = file;
        this.form.remove_cover_image = false;
      }
    },
    removeCurrentImage() {
      if (this.imagePreview.startsWith("blob:")) URL.revokeObjectURL(this.imagePreview);
      this.imagePreview = "";
      if (this.isTestimonials) {
        this.form.image = null;
        this.form.external_image_url = "";
        this.form.remove_image = true;
      } else {
        this.form.cover_image = null;
        this.form.external_cover_image_url = "";
        this.form.remove_cover_image = true;
      }
      if (this.$refs.imageInput) this.$refs.imageInput.value = "";
    },
    onGallerySelected(event) {
      const files = Array.from(event?.target?.files || []);
      this.validationErrors.gallery = "";
      if (this.isInstallations && this.existingGallery.length + files.length > 12) {
        this.validationErrors.gallery = "La galería admite hasta 12 imágenes en total.";
        event.target.value = "";
        return;
      }
      const invalid = files.find((file) => !IMAGE_TYPES.includes(file.type) || file.size > 8 * 1024 * 1024);
      if (invalid) {
        this.validationErrors.gallery = "Cada imagen de galería debe ser JPG, PNG o WebP y pesar hasta 8 MB.";
        event.target.value = "";
        return;
      }
      this.galleryPreviews.forEach((preview) => URL.revokeObjectURL(preview.url));
      this.form.gallery = files;
      this.form.gallery_alts = files.map(() => "");
      this.galleryPreviews = files.map((file) => ({ name: file.name, url: URL.createObjectURL(file) }));
    },
    markGalleryForRemoval(image) {
      if (!image?.id) return;
      this.form.remove_gallery_image_ids.push(image.id);
      this.existingGallery = this.existingGallery.filter((current) => current.id !== image.id);
      if (this.isInstallations) this.form.gallery_order = this.existingGallery.map((current) => current.id);
    },
    addFeature() {
      if (!this.isInstallations || this.form.features.length >= 12) return;
      this.form.features.push("");
    },
    removeFeature(index) {
      if (!this.isInstallations) return;
      this.form.features.splice(index, 1);
      if (!this.form.features.length) this.form.features.push("");
    },
    moveExistingGallery(index, direction) {
      const target = index + direction;
      if (target < 0 || target >= this.existingGallery.length) return;
      const images = [...this.existingGallery];
      [images[index], images[target]] = [images[target], images[index]];
      this.existingGallery = images;
      if (this.isInstallations) this.form.gallery_order = images.map((image) => image.id);
    },
    revokePreviews() {
      if (this.imagePreview.startsWith("blob:")) URL.revokeObjectURL(this.imagePreview);
      this.galleryPreviews.forEach((preview) => {
        if (preview.url.startsWith("blob:")) URL.revokeObjectURL(preview.url);
      });
      this.imagePreview = "";
      this.galleryPreviews = [];
    },
    validate() {
      const errors = {};
      if (this.isTestimonials) {
        if (!this.form.quote.trim()) errors.quote = "Escribe el testimonio.";
        if (this.form.quote.length > 2000) errors.quote = "El testimonio admite hasta 2.000 caracteres.";
        if (!this.form.author_name.trim()) errors.author_name = "Indica el nombre de la persona.";
        if (!this.form.authorization_confirmed) errors.authorization_confirmed = "Confirma la autorización antes de guardar.";
      } else {
        if (!this.form.title.trim()) errors.title = "Ingresa un título.";
        if (!this.form.slug.trim()) errors.slug = "Ingresa una URL legible.";
        if (!this.form.category.trim()) errors.category = "Selecciona una categoría.";
        if (!this.form.summary.trim()) errors.summary = "Escribe un resumen breve.";
        if (!String(this.form.body || "").trim()) errors.body = this.isInstallations
          ? "Agrega una descripción del espacio."
          : "Agrega el contenido de la publicación.";
        if (this.isInstallations) {
          const features = (this.form.features || []).map((feature) => String(feature).trim()).filter(Boolean);
          this.form.features = features.length ? features : [""];
          if (features.length > 12) errors.features = "Puedes registrar hasta 12 características.";
          if (this.form.capacity !== "" && Number(this.form.capacity) < 1) {
            errors.capacity = "La capacidad debe ser de al menos una persona.";
          }
          if ((this.imagePreview || this.form.cover_image) && !this.form.cover_image_alt.trim()) {
            errors.cover_image_alt = "Describe la imagen principal para lectores de pantalla.";
          }
          const missingGalleryAlt = (this.form.gallery || []).some((file, index) => file && !String(this.form.gallery_alts[index] || "").trim());
          if (missingGalleryAlt) errors.gallery_alts = "Cada imagen nueva necesita un texto alternativo.";
          if (this.form.status === "published" && !this.imagePreview && !this.form.cover_image) {
            errors.cover_image = "Agrega una portada antes de publicar la instalación.";
          }
        }
      }
      this.validationErrors = errors;
      return !Object.keys(errors).length;
    },
    syncSlugFromTitle() {
      if (this.isTestimonials || this.slugManuallyEdited) return;
      this.form.slug = String(this.form.title || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .slice(0, 191);
    },
    async confirmSave() {
      if (!this.validate()) return;
      const result = await Swal.fire({
        title: this.isEditing ? "¿Guardar los cambios?" : `¿Crear ${this.copy.singular}?`,
        text: this.isTestimonials ? this.form.author_name : this.form.title,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: this.isEditing ? "Guardar cambios" : "Crear",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#0b536d",
      });
      if (result.isConfirmed) await this.save();
    },
    async save() {
      if (!this.validate()) return;
      this.saving = true;
      this.error = null;
      try {
        let response;
        if (this.isTestimonials) {
          response = this.isEditing
            ? await updateTestimonial(this.form.id, this.form)
            : await createTestimonial(this.form);
        } else if (this.isInstallations) {
          response = this.isEditing
            ? await updateInstallation(this.form.id, this.form)
            : await createInstallation(this.form);
        } else {
          response = this.isEditing
            ? await updateStudentLife(this.form.id, this.form)
            : await createStudentLife(this.form);
        }
        const message = response.data?.message || `${this.copy.singular} guardado correctamente.`;
        this.showForm = false;
        this.revokePreviews();
        this.form = this.emptyForm();
        this.existingGallery = [];
        await Promise.all([this.loadCatalogs(), this.load(this.pagination.current_page)]);
        await Swal.fire({ title: "Contenido guardado", text: message, icon: "success", confirmButtonColor: "#0b536d" });
        this.restoreModalFocus();
      } catch (error) {
        this.validationErrors = this.validationFromResponse(error);
        this.error = this.errorMessage(error, `No fue posible guardar ${this.copy.singular}.`);
      } finally {
        this.saving = false;
      }
    },
    itemPayload(item) {
      if (this.isTestimonials) {
        return {
          quote: item.quote || "",
          author_name: item.author_name || "",
          author_role: item.author_role || "",
          external_image_url: item.external_image_url || "",
          image_alt: item.image_alt || "",
          status: item.status || "draft",
          active: this.asBoolean(item.active, true),
          featured: this.asBoolean(item.featured),
          sort_order: Number(item.sort_order || 0),
          published_at: item.published_at || "",
          remove_image: false,
          authorization_confirmed: this.testimonialAuthorized(item),
        };
      }
      if (this.isInstallations) {
        return {
          title: item.title || "",
          slug: item.slug || "",
          category: item.category || "",
          summary: item.summary || "",
          body: item.body_html || item.body || "",
          location_label: item.location_label || "",
          capacity: item.capacity ?? "",
          accessibility_notes: item.accessibility_notes || "",
          features: Array.isArray(item.features) ? item.features : [],
          icon: item.icon || "buildings",
          meta_title: item.meta_title || "",
          meta_description: item.meta_description || "",
          cover_image_alt: item.cover_image_alt || "",
          status: item.status || "draft",
          active: this.asBoolean(item.active, true),
          featured: this.asBoolean(item.featured),
          sort_order: Number(item.sort_order || 0),
          published_at: item.published_at || "",
          remove_cover_image: false,
          gallery_order: (item.gallery_images || []).map((image) => image.id),
        };
      }
      return {
        title: item.title || "",
        slug: item.slug || "",
        category: item.category || "",
        summary: item.summary || "",
        body: item.body_html || item.body || "",
        external_cover_image_url: item.external_cover_image_url || "",
        cover_image_alt: item.cover_image_alt || "",
        event_date: item.event_date || "",
        meta_title: item.meta_title || "",
        meta_description: item.meta_description || "",
        status: item.status || "draft",
        active: this.asBoolean(item.active, true),
        featured: this.asBoolean(item.featured),
        sort_order: Number(item.sort_order || 0),
        published_at: item.published_at || "",
        remove_cover_image: false,
      };
    },
    async quickUpdate(item, changes, successMessage) {
      if (!this.canManage || this.updatingId) return;
      this.updatingId = item.id;
      this.error = null;
      try {
        const payload = { ...this.itemPayload(item), ...changes };
        if (payload.status === "published" && !payload.published_at) payload.published_at = new Date().toISOString();
        if (this.isTestimonials) await updateTestimonial(item.id, payload);
        else if (this.isInstallations) await updateInstallation(item.id, payload);
        else await updateStudentLife(item.id, payload);
        this.success = successMessage;
        await Promise.all([this.loadCatalogs(), this.load(this.pagination.current_page)]);
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible actualizar el contenido.");
      } finally {
        this.updatingId = null;
      }
    },
    togglePublished(item) {
      const publishing = item.status !== "published";
      if (this.isTestimonials && publishing && !this.testimonialAuthorized(item)) {
        this.error = "Antes de publicar, edita el testimonio y confirma la autorización de nombre, cita e imagen.";
        return Promise.resolve();
      }
      if (this.isInstallations && publishing) {
        const galleryWithoutAlt = (item.gallery_images || []).some((image) => !String(image.alt || "").trim());
        if (!this.imageUrl(item) || !String(item.cover_image_alt || "").trim() || galleryWithoutAlt) {
          this.error = "Antes de publicar, agrega una portada y describe todas las imágenes para que el contenido sea accesible.";
          return Promise.resolve();
        }
      }
      return this.quickUpdate(
        item,
        { status: publishing ? "published" : "archived", active: publishing ? true : this.asBoolean(item.active, true) },
        publishing ? "Contenido publicado." : "Contenido retirado de la web.",
      );
    },
    toggleFeatured(item) {
      const featured = !this.asBoolean(item.featured);
      return this.quickUpdate(item, { featured }, featured ? "Contenido destacado." : "Contenido retirado de destacados.");
    },
    async move(item, direction) {
      if (this.isInstallations) {
        if (!this.canManage || this.updatingId) return;
        const currentIndex = this.items.findIndex((current) => current.id === item.id);
        const targetIndex = currentIndex + direction;
        if (currentIndex < 0 || targetIndex < 0 || targetIndex >= this.items.length) return;
        const target = this.items[targetIndex];
        let currentOrder = Number(item.sort_order || currentIndex + 1);
        let targetOrder = Number(target.sort_order || targetIndex + 1);
        if (currentOrder === targetOrder) {
          currentOrder = currentIndex + 1;
          targetOrder = targetIndex + 1;
        }
        this.updatingId = item.id;
        this.error = null;
        try {
          await reorderInstallations([
            { id: item.id, sort_order: targetOrder },
            { id: target.id, sort_order: currentOrder },
          ]);
          this.success = "Orden de instalaciones actualizado.";
          await Promise.all([this.loadCatalogs(), this.load(this.pagination.current_page)]);
        } catch (error) {
          this.error = this.errorMessage(error, "No fue posible actualizar el orden de las instalaciones.");
        } finally {
          this.updatingId = null;
        }
        return;
      }
      const nextOrder = Math.max(0, Number(item.sort_order || 0) + direction);
      if (nextOrder === Number(item.sort_order || 0)) return;
      return this.quickUpdate(item, { sort_order: nextOrder }, "Orden editorial actualizado.");
    },
    async remove(item) {
      if (!this.canManage) return;
      const result = await Swal.fire({
        title: `¿Eliminar ${this.copy.singular}?`,
        text: this.isTestimonials ? item.author_name : item.title,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar definitivamente",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#b33d4b",
      });
      if (!result.isConfirmed) return;
      this.deletingId = item.id;
      try {
        const response = this.isTestimonials
          ? await removeTestimonial(item.id)
          : (this.isInstallations ? await removeInstallation(item.id) : await removeStudentLife(item.id));
        this.success = response.data?.message || "Contenido eliminado.";
        await Promise.all([this.loadCatalogs(), this.load(this.pagination.current_page)]);
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible eliminar el contenido.");
      } finally {
        this.deletingId = null;
      }
    },
    imageUrl(item) {
      return this.isTestimonials
        ? item.preview_image_url || item.image_url || ""
        : item.preview_cover_image_url || item.cover_image_url || "";
    },
    testimonialAuthorized(item) {
      return Boolean(item?.consent_confirmed_at || this.asBoolean(item?.authorization_confirmed));
    },
    initials(value) {
      return String(value || "CNSC").split(/\s+/).slice(0, 2).map((part) => part[0]).join("").toUpperCase();
    },
    statusLabel(status) {
      return this.statusOptions.find((option) => option.value === status)?.label || status || "Borrador";
    },
    asBoolean(value, fallback = false) {
      if (value === undefined || value === null || value === "") return fallback;
      return value === true || value === 1 || value === "1" || value === "true";
    },
    formatDate(value, withTime = false) {
      if (!value) return "Sin fecha";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return "Sin fecha";
      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        ...(withTime ? { hour: "2-digit", minute: "2-digit" } : {}),
      }).format(date);
    },
    toDatetimeLocal(value) {
      if (!value) return "";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return "";
      const offset = date.getTimezoneOffset();
      return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 16);
    },
    toDateInput(value) {
      if (!value) return "";
      return String(value).slice(0, 10);
    },
    validationFromResponse(error) {
      const errors = error?.response?.status === 422 ? error.response?.data?.errors || {} : {};
      return Object.entries(errors).reduce((normalized, [key, messages]) => {
        const field = String(key).split(".")[0];
        if (!normalized[field]) normalized[field] = Array.isArray(messages) ? messages[0] : messages;
        return normalized;
      }, {});
    },
    errorMessage(error, fallback) {
      const status = Number(error?.response?.status || 0);
      if (status >= 500 || !status) return fallback;
      const message = String(error?.response?.data?.message || "").trim();
      if (!message || /sqlstate|database|connection|\/var\/|\\app\\/i.test(message)) return fallback;
      return message;
    },
  },
};
</script>

<template>
  <Layout>
    <main class="web-content" :class="`web-content--${contentType}`">
      <SiteAdminNavigation />
      <section class="web-content-hero" aria-labelledby="web-content-title">
        <div class="web-content-hero__copy">
          <span class="web-content-eyebrow"><i class="bx bx-globe"></i> Sitio web · {{ copy.eyebrow }}</span>
          <h1 id="web-content-title">{{ copy.title }}</h1>
          <p>{{ copy.description }}</p>
        </div>
        <div class="web-content-hero__metrics" aria-label="Resumen editorial">
          <article><span>Total</span><strong>{{ summary.total }}</strong><small>{{ copy.metricLabel || "contenidos" }}</small></article>
          <article><span>En línea</span><strong>{{ summary.published }}</strong><small>publicados</small></article>
          <article><span>Selección</span><strong>{{ summary.featured }}</strong><small>destacados</small></article>
        </div>
      </section>

      <section class="web-content-toolbar" aria-label="Herramientas de contenido">
        <form class="web-content-filters" @submit.prevent="applyFilters">
          <label class="web-content-search">
            <span class="visually-hidden">Buscar</span>
            <i class="bx bx-search"></i>
            <input v-model="filters.search" type="search" :placeholder="isTestimonials ? 'Buscar persona o testimonio…' : (isInstallations ? 'Buscar espacio, ubicación o característica…' : 'Buscar título, resumen o categoría…')">
          </label>
          <label>
            <span>Estado</span>
            <select v-model="filters.status">
              <option value="">Todos</option>
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <label v-if="!isTestimonials">
            <span>Categoría</span>
            <select v-model="filters.category">
              <option value="">Todas</option>
              <option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <label>
            <span>Visibilidad</span>
            <select v-model="filters.active">
              <option value="">Todas</option>
              <option value="1">Activas</option>
              <option value="0">Inactivas</option>
            </select>
          </label>
          <label>
            <span>Selección</span>
            <select v-model="filters.featured">
              <option value="">Todas</option>
              <option value="1">Destacadas</option>
              <option value="0">Regulares</option>
            </select>
          </label>
          <button class="web-content-filter-button" type="submit" aria-label="Aplicar filtros"><i class="bx bx-filter-alt"></i></button>
          <button v-if="activeFilterCount" class="web-content-clear" type="button" @click="clearFilters">
            Limpiar <span>{{ activeFilterCount }}</span>
          </button>
        </form>
        <button v-if="canManage" class="web-content-primary-action" type="button" @click="openCreate">
          <span><i class="bx" :class="copy.icon"></i></span>{{ copy.action }}<i class="bx bx-plus"></i>
        </button>
      </section>

      <div v-if="error" class="web-content-alert is-error" role="alert">
        <i class="bx bx-error-circle"></i><span>{{ error }}</span><button type="button" aria-label="Cerrar alerta" @click="error = null"><i class="bx bx-x"></i></button>
      </div>
      <div v-if="success" class="web-content-alert is-success" role="status">
        <i class="bx bx-check-circle"></i><span>{{ success }}</span><button type="button" aria-label="Cerrar mensaje" @click="success = null"><i class="bx bx-x"></i></button>
      </div>

      <LoadingState v-if="loading" :message="`Cargando ${copy.title.toLowerCase()}…`" />

      <template v-else>
        <header class="web-content-results">
          <div><span>Biblioteca editorial</span><strong>{{ resultRange }}</strong></div>
          <p><i class="bx bx-info-circle"></i> El orden menor se muestra primero en la web pública.</p>
        </header>

        <section v-if="items.length" class="web-content-grid" :class="{ 'is-testimonials': isTestimonials }">
          <article v-for="item in items" :key="item.id" class="web-content-card" :class="{ 'is-featured': asBoolean(item.featured), 'is-updating': updatingId === item.id }">
            <template v-if="isTestimonials">
              <div class="testimonial-card__top">
                <div class="testimonial-avatar">
                  <img v-if="imageUrl(item)" :src="imageUrl(item)" :alt="item.image_alt || `Retrato de ${item.author_name}`">
                  <span v-else>{{ initials(item.author_name) }}</span>
                </div>
                <div class="web-content-badges">
                  <span class="status-pill" :class="`is-${item.status || 'draft'}`">{{ statusLabel(item.status) }}</span>
                  <span v-if="asBoolean(item.featured)" class="featured-pill"><i class="bx bxs-star"></i> Destacado</span>
                  <span class="consent-pill" :class="{ 'is-pending': !testimonialAuthorized(item) }"><i class="bx" :class="testimonialAuthorized(item) ? 'bx-check-shield' : 'bx-error-circle'"></i>{{ testimonialAuthorized(item) ? "Autorizado" : "Falta autorización" }}</span>
                </div>
              </div>
              <blockquote>“{{ item.quote }}”</blockquote>
              <div class="testimonial-author">
                <strong>{{ item.author_name }}</strong>
                <span>{{ item.author_role || "Comunidad CNSC" }}</span>
              </div>
            </template>

            <template v-else>
              <div class="student-life-card__media">
                <img v-if="imageUrl(item)" :src="imageUrl(item)" :alt="item.cover_image_alt || item.title">
                <div v-else class="student-life-card__placeholder"><i class="bx bx-image"></i><span>Portada pendiente</span></div>
                <div class="web-content-badges">
                  <span class="status-pill" :class="`is-${item.status || 'draft'}`">{{ statusLabel(item.status) }}</span>
                  <span v-if="asBoolean(item.featured)" class="featured-pill"><i class="bx bxs-star"></i> Destacado</span>
                </div>
              </div>
              <div class="student-life-card__body">
                <div class="student-life-card__meta">
                  <span>{{ item.category || (isInstallations ? "Espacio CNSC" : "Vida estudiantil") }}</span>
                  <time v-if="!isInstallations">{{ formatDate(item.event_date || item.published_at) }}</time>
                  <time v-else-if="item.location_label"><i class="bx bx-map"></i> {{ item.location_label }}</time>
                </div>
                <h2>{{ item.title }}</h2>
                <p>{{ item.summary }}</p>
                <div v-if="isInstallations && (item.capacity || (item.features || []).length)" class="installation-card__details">
                  <span v-if="item.capacity"><i class="bx bx-group"></i> Capacidad {{ item.capacity }}</span>
                  <span v-for="feature in (item.features || []).slice(0, 2)" :key="feature"><i class="bx bx-check"></i> {{ feature }}</span>
                </div>
              </div>
            </template>

            <footer class="web-content-card__footer">
              <div class="editorial-order" aria-label="Orden editorial">
                <span>#{{ Number(item.sort_order || 0) }}</span>
                <button v-if="canManage" type="button" title="Mover antes" aria-label="Mover antes" :disabled="isInstallations && items.length && items[0].id === item.id" @click="move(item, -1)"><i class="bx bx-chevron-up"></i></button>
                <button v-if="canManage" type="button" title="Mover después" aria-label="Mover después" :disabled="isInstallations && items.length && items[items.length - 1].id === item.id" @click="move(item, 1)"><i class="bx bx-chevron-down"></i></button>
              </div>
              <div class="web-content-card__actions">
                <a v-if="!isTestimonials && item.public_url && item.status === 'published'" :href="item.public_url" target="_blank" rel="noopener" :title="isInstallations ? 'Ver en instalaciones' : 'Ver publicación'" :aria-label="isInstallations ? 'Ver en instalaciones' : 'Ver publicación'"><i class="bx bx-link-external"></i></a>
                <button v-if="canManage" type="button" :class="{ selected: asBoolean(item.featured) }" :title="asBoolean(item.featured) ? 'Quitar destacado' : 'Destacar'" :aria-label="asBoolean(item.featured) ? 'Quitar destacado' : 'Destacar'" @click="toggleFeatured(item)"><i class="bx" :class="asBoolean(item.featured) ? 'bxs-star' : 'bx-star'"></i></button>
                <button v-if="canManage" type="button" :class="{ selected: item.status === 'published' }" :title="item.status === 'published' ? 'Retirar de la web' : 'Publicar'" :aria-label="item.status === 'published' ? 'Retirar de la web' : 'Publicar'" @click="togglePublished(item)"><i class="bx" :class="item.status === 'published' ? 'bx-hide' : 'bx-world'"></i></button>
                <button v-if="canManage" type="button" title="Editar" aria-label="Editar" @click="openEdit(item)"><i class="bx bx-edit-alt"></i></button>
                <button v-if="canManage" class="is-danger" type="button" title="Eliminar" aria-label="Eliminar" :disabled="deletingId === item.id" @click="remove(item)"><i class="bx" :class="deletingId === item.id ? 'bx-loader-alt bx-spin' : 'bx-trash'"></i></button>
              </div>
            </footer>
          </article>
        </section>

        <section v-else class="web-content-empty">
          <span><i class="bx" :class="copy.icon"></i></span>
          <h2>{{ copy.empty }}</h2>
          <p>Prueba limpiando los filtros o crea el primer contenido para esta sección.</p>
          <button v-if="canManage" type="button" @click="openCreate"><i class="bx bx-plus"></i>{{ copy.action }}</button>
        </section>

        <nav v-if="pagination.last_page > 1" class="web-content-pagination" aria-label="Paginación">
          <button type="button" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)"><i class="bx bx-chevron-left"></i> Anterior</button>
          <span>Página <strong>{{ pagination.current_page }}</strong> de {{ pagination.last_page }}</span>
          <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Siguiente <i class="bx bx-chevron-right"></i></button>
        </nav>
      </template>

      <Teleport to="body">
        <div v-if="showForm" class="web-content-modal" role="presentation" @mousedown.self="closeForm">
          <section class="web-content-dialog" role="dialog" aria-modal="true" aria-labelledby="web-content-modal-title" @keydown="trapFocus">
            <header class="web-content-dialog__header">
              <span class="web-content-dialog__icon"><i class="bx" :class="copy.icon"></i></span>
              <div>
                <small>Sitio web · {{ copy.eyebrow }}</small>
                <h2 id="web-content-modal-title">{{ isEditing ? `Editar ${copy.singular}` : copy.action }}</h2>
                <p>{{ isTestimonials ? "Una voz auténtica, breve y fácil de leer." : (isInstallations ? "Presenta cada espacio con claridad, identidad y accesibilidad." : "Construye una experiencia editorial clara y visual.") }}</p>
              </div>
              <button class="web-content-dialog__close" type="button" aria-label="Cerrar" :disabled="saving" @click="closeForm"><i class="bx bx-x"></i></button>
            </header>

            <form class="web-content-form" @submit.prevent="confirmSave">
              <div class="web-content-form__body">
                <section class="web-content-form__section">
                  <header><span>01</span><div><h3>{{ isTestimonials ? "Relato y autoría" : (isInstallations ? "Identidad del espacio" : "Identidad editorial") }}</h3><p>{{ isTestimonials ? "Mantén el testimonio fiel, concreto y legible." : (isInstallations ? "Nombre, categoría y datos prácticos para reconocer la instalación." : "Título, URL y resumen que orientan la lectura.") }}</p></div></header>

                  <template v-if="isTestimonials">
                    <label class="field is-full" :class="{ 'has-error': validationErrors.quote }">
                      <span>Testimonio <em>*</em><small>{{ form.quote.length }}/2000</small></span>
                      <textarea ref="primaryInput" v-model="form.quote" rows="6" maxlength="2000" placeholder="Escribe el relato en primera persona…"></textarea>
                      <strong v-if="validationErrors.quote">{{ validationErrors.quote }}</strong>
                    </label>
                    <div class="field-grid">
                      <label class="field" :class="{ 'has-error': validationErrors.author_name }"><span>Nombre <em>*</em></span><input v-model="form.author_name" type="text" placeholder="Nombre y apellido"><strong v-if="validationErrors.author_name">{{ validationErrors.author_name }}</strong></label>
                      <label class="field"><span>Rol en la comunidad</span><input v-model="form.author_role" type="text" placeholder="Estudiante, apoderada, docente…"></label>
                    </div>
                    <label class="consent-check" :class="{ 'has-error': validationErrors.authorization_confirmed }">
                      <input v-model="form.authorization_confirmed" type="checkbox">
                      <span><i class="bx bx-check-shield"></i></span>
                      <div>
                        <strong>Autorización confirmada <em>*</em></strong>
                        <small>Confirmo que la persona autorizó publicar su nombre, cita e imagen. El contenido corresponde a su testimonio real y no fue inventado.</small>
                        <b v-if="validationErrors.authorization_confirmed">{{ validationErrors.authorization_confirmed }}</b>
                      </div>
                    </label>
                  </template>

                  <template v-else>
                    <div class="field-grid">
                      <label class="field" :class="{ 'has-error': validationErrors.title }"><span>Título <em>*</em></span><input ref="primaryInput" v-model="form.title" type="text" :placeholder="isInstallations ? 'Biblioteca escolar' : 'Una experiencia que deja huella'" @input="syncSlugFromTitle"><strong v-if="validationErrors.title">{{ validationErrors.title }}</strong></label>
                      <label class="field" :class="{ 'has-error': validationErrors.slug }"><span>URL <em>*</em></span><div class="slug-field"><span>{{ isInstallations ? "/instalaciones#" : "/vidaestudiantil/" }}</span><input v-model="form.slug" type="text" :placeholder="isInstallations ? 'biblioteca-escolar' : 'experiencia-que-deja-huella'" @input="slugManuallyEdited = true"></div><strong v-if="validationErrors.slug">{{ validationErrors.slug }}</strong></label>
                    </div>
                    <div class="field-grid">
                      <label class="field" :class="{ 'has-error': validationErrors.category }"><span>Categoría <em>*</em></span><input v-model="form.category" type="text" list="web-content-categories" :placeholder="isInstallations ? 'Académico' : 'Pastoral'"><datalist id="web-content-categories"><option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option></datalist><strong v-if="validationErrors.category">{{ validationErrors.category }}</strong></label>
                      <label v-if="isStudentLife" class="field"><span>Fecha de la experiencia</span><input v-model="form.event_date" type="date"></label>
                      <label v-else class="field"><span>Ícono</span><select v-model="form.icon"><option v-for="option in iconOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
                    </div>
                    <div v-if="isInstallations" class="field-grid installation-practical-fields">
                      <label class="field"><span>Ubicación dentro del colegio</span><input v-model="form.location_label" type="text" placeholder="Primer piso · Ala norte"></label>
                      <label class="field" :class="{ 'has-error': validationErrors.capacity }"><span>Capacidad aproximada</span><input v-model.number="form.capacity" type="number" min="1" step="1" placeholder="40"><strong v-if="validationErrors.capacity">{{ validationErrors.capacity }}</strong></label>
                    </div>
                    <label class="field is-full" :class="{ 'has-error': validationErrors.summary }"><span>Resumen <em>*</em><small>{{ form.summary.length }}/{{ isInstallations ? 700 : 500 }}</small></span><textarea v-model="form.summary" rows="3" :maxlength="isInstallations ? 700 : 500" :placeholder="isInstallations ? 'Describe brevemente el uso y valor formativo de este espacio…' : 'Una introducción breve para la tarjeta y el encabezado…'"></textarea><strong v-if="validationErrors.summary">{{ validationErrors.summary }}</strong></label>
                    <label v-if="isInstallations" class="field is-full"><span>Accesibilidad</span><textarea v-model="form.accessibility_notes" rows="3" maxlength="1000" placeholder="Describe accesos, rampas, ascensor u otras condiciones de accesibilidad disponibles."></textarea></label>
                  </template>
                </section>

                <section v-if="!isTestimonials" class="web-content-form__section">
                  <header><span>02</span><div><h3>{{ isInstallations ? "Descripción del espacio" : "Contenido individual" }}</h3><p>{{ isInstallations ? "Explica cómo se utiliza, qué aporta y a quién está destinado." : "Desarrolla la historia con jerarquía, aire y lectura cómoda." }}</p></div></header>
                  <label class="field is-full" :class="{ 'has-error': validationErrors.body }"><span>Contenido <em>*</em></span><div v-if="editorLoading" class="editor-placeholder"><i class="bx bx-loader-alt bx-spin"></i> Cargando editor…</div><Ckeditor v-else-if="editor" v-model="form.body" :editor="editor" :config="editorConfig" /><textarea v-else v-model="form.body" rows="9" :placeholder="isInstallations ? 'Describe el espacio, su equipamiento y uso educativo…' : 'Contenido de la publicación…'"></textarea><strong v-if="validationErrors.body">{{ validationErrors.body }}</strong></label>

                  <div v-if="isInstallations" class="installation-features" :class="{ 'has-error': validationErrors.features }">
                    <div class="installation-features__heading"><div><strong>Características del espacio</strong><span>Agrega hasta 12 atributos concretos.</span></div><button type="button" :disabled="form.features.length >= 12" @click="addFeature"><i class="bx bx-plus"></i> Agregar</button></div>
                    <div class="installation-features__list">
                      <label v-for="(feature, index) in form.features" :key="`feature-${index}`"><span class="visually-hidden">Característica {{ index + 1 }}</span><input v-model="form.features[index]" type="text" maxlength="120" :placeholder="`Característica ${index + 1}`"><button type="button" :aria-label="`Quitar característica ${index + 1}`" @click="removeFeature(index)"><i class="bx bx-x"></i></button></label>
                    </div>
                    <p v-if="validationErrors.features" class="field-error">{{ validationErrors.features }}</p>
                  </div>
                </section>

                <section class="web-content-form__section">
                  <header><span>{{ isTestimonials ? "02" : "03" }}</span><div><h3>{{ isTestimonials ? "Retrato" : "Portada y galería" }}</h3><p>{{ isTestimonials ? "Una imagen natural refuerza la cercanía del relato." : (isInstallations ? "Muestra el espacio con fotografías nítidas y descripciones accesibles." : "Elige imágenes nítidas, horizontales y representativas.") }}</p></div></header>
                  <div class="media-editor">
                    <div class="media-preview" :class="{ 'is-portrait': isTestimonials }">
                      <img v-if="imagePreview" :src="imagePreview" alt="Vista previa">
                      <span v-else><i class="bx bx-image-add"></i>{{ isTestimonials ? "Sin retrato" : "Sin portada" }}</span>
                    </div>
                    <div class="media-fields">
                      <label class="file-picker" :class="{ 'has-error': validationErrors.image || validationErrors.cover_image }"><input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" @change="onImageSelected"><span><i class="bx bx-upload"></i><strong>{{ imagePreview ? "Reemplazar imagen" : "Seleccionar imagen" }}</strong><small>JPG, PNG o WebP · máximo {{ isTestimonials ? 5 : 8 }} MB</small></span></label>
                      <p v-if="validationErrors.image || validationErrors.cover_image" class="field-error">{{ validationErrors.image || validationErrors.cover_image }}</p>
                      <label v-if="!isInstallations" class="field"><span>URL externa opcional</span><input v-if="isTestimonials" v-model="form.external_image_url" type="url" placeholder="https://…"><input v-else v-model="form.external_cover_image_url" type="url" placeholder="https://…"></label>
                      <label class="field" :class="{ 'has-error': validationErrors.cover_image_alt }"><span>Texto alternativo <em v-if="isInstallations">*</em></span><input v-if="isTestimonials" v-model="form.image_alt" type="text" placeholder="Retrato de…"><input v-else v-model="form.cover_image_alt" type="text" :placeholder="isInstallations ? 'Vista interior de la biblioteca escolar' : 'Estudiantes durante…'"><strong v-if="validationErrors.cover_image_alt">{{ validationErrors.cover_image_alt }}</strong></label>
                      <button v-if="imagePreview" class="remove-media" type="button" @click="removeCurrentImage"><i class="bx bx-trash"></i> Quitar imagen</button>
                    </div>
                  </div>

                  <div v-if="!isTestimonials" class="gallery-editor">
                    <div class="gallery-editor__heading"><div><strong>Galería opcional</strong><span>{{ isInstallations ? "Hasta 12 imágenes, todas con una descripción accesible." : "Acompaña la página individual con imágenes adicionales." }}</span></div><label><input ref="galleryInput" type="file" multiple accept="image/jpeg,image/png,image/webp" @change="onGallerySelected"><i class="bx bx-images"></i> Agregar imágenes</label></div>
                    <p v-if="validationErrors.gallery || validationErrors.gallery_alts" class="field-error">{{ validationErrors.gallery || validationErrors.gallery_alts }}</p>
                    <div v-if="existingGallery.length || galleryPreviews.length" class="gallery-grid">
                      <article v-for="(image, index) in existingGallery" :key="`stored-${image.id}`"><img :src="image.preview_url || image.url || image.image_url" :alt="image.alt || ''"><button type="button" aria-label="Quitar imagen" @click="markGalleryForRemoval(image)"><i class="bx bx-x"></i></button><div v-if="isInstallations" class="gallery-order"><button type="button" :disabled="index === 0" aria-label="Mover imagen antes" @click="moveExistingGallery(index, -1)"><i class="bx bx-left-arrow-alt"></i></button><button type="button" :disabled="index === existingGallery.length - 1" aria-label="Mover imagen después" @click="moveExistingGallery(index, 1)"><i class="bx bx-right-arrow-alt"></i></button></div><span>{{ image.alt || "Sin descripción" }}</span></article>
                      <article v-for="(preview, index) in galleryPreviews" :key="preview.url"><img :src="preview.url" :alt="form.gallery_alts[index] || ''"><label><span class="visually-hidden">Texto alternativo</span><input v-model="form.gallery_alts[index]" type="text" placeholder="Descripción breve"></label><span>Nueva</span></article>
                    </div>
                  </div>
                </section>

                <section class="web-content-form__section publication-section">
                  <header><span>{{ isTestimonials ? "03" : "04" }}</span><div><h3>Publicación</h3><p>Controla cuándo aparece y qué prioridad tendrá en la web.</p></div></header>
                  <div class="field-grid field-grid--three">
                    <label class="field"><span>Estado</span><select v-model="form.status"><option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
                    <label class="field"><span>Fecha de publicación</span><input v-model="form.published_at" type="datetime-local"></label>
                    <label class="field"><span>Orden editorial</span><input v-model.number="form.sort_order" type="number" min="0" step="1"></label>
                  </div>
                  <div class="toggle-grid">
                    <label><input v-model="form.active" type="checkbox"><span><i class="bx bx-show"></i></span><div><strong>Contenido activo</strong><small>Disponible para su publicación.</small></div></label>
                    <label><input v-model="form.featured" type="checkbox"><span><i class="bx bx-star"></i></span><div><strong>Destacar contenido</strong><small>Mayor prioridad visual en la web.</small></div></label>
                  </div>
                  <details v-if="!isTestimonials" class="seo-panel"><summary><span><i class="bx bx-search-alt"></i> Metadatos para buscadores</span><i class="bx bx-chevron-down"></i></summary><div class="field-grid"><label class="field"><span>Título SEO</span><input v-model="form.meta_title" type="text" maxlength="70" placeholder="Título para resultados de búsqueda"></label><label class="field"><span>Descripción SEO</span><textarea v-model="form.meta_description" rows="2" maxlength="170" placeholder="Descripción breve para buscadores"></textarea></label></div></details>
                </section>
              </div>

              <footer class="web-content-form__footer">
                <p><i class="bx bx-shield-quarter"></i> Los cambios se registran con tu usuario.</p>
                <div><button type="button" :disabled="saving" @click="closeForm">Cancelar</button><button class="is-primary" type="submit" :disabled="saving"><i class="bx" :class="saving ? 'bx-loader-alt bx-spin' : 'bx-check'"></i>{{ saving ? "Guardando…" : (isEditing ? "Guardar cambios" : `Crear ${copy.singular}`) }}</button></div>
              </footer>
            </form>
          </section>
        </div>
      </Teleport>
    </main>
  </Layout>
</template>

<style scoped>
:global(body.web-content-modal-open){overflow:hidden}
:global(body.web-content-modal-open .swal2-container){z-index:1200!important}
.web-content{--ink:#102f42;--muted:#667c8d;--line:#dce7eb;--navy:#082f43;--teal:#0e7181;--green:#789a77;--gold:#dfa548;display:grid;gap:1rem;max-width:1460px;margin:0 auto;padding:1.1rem 1.15rem 2.5rem;color:var(--ink)}
.web-content-hero{position:relative;display:grid;grid-template-columns:minmax(0,1.45fr) minmax(330px,.55fr);gap:2rem;overflow:hidden;min-height:230px;border:1px solid rgba(151,202,211,.22);border-radius:28px;background:radial-gradient(circle at 82% 10%,rgba(126,166,128,.37),transparent 28%),linear-gradient(118deg,#062d41 0%,#0a5066 55%,#175e70 100%);padding:2rem 2.15rem;color:#fff;box-shadow:0 24px 55px rgba(8,47,67,.16)}
.web-content-hero::after{position:absolute;right:-70px;bottom:-145px;width:350px;height:350px;border:1px solid rgba(255,255,255,.1);border-radius:46% 54% 42% 58%;content:"";transform:rotate(18deg)}
.web-content-hero__copy{position:relative;z-index:1;display:flex;flex-direction:column;align-items:flex-start;justify-content:center}.web-content-eyebrow{display:inline-flex;align-items:center;gap:.45rem;margin-bottom:.75rem;color:#e8bd72;font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.web-content-eyebrow i{font-size:1rem}.web-content-hero h1{margin:0;font-size:clamp(2rem,3.2vw,3.15rem);line-height:1;letter-spacing:-.055em}.web-content-hero__copy>p{max-width:720px;margin:.8rem 0 1.25rem;color:rgba(255,255,255,.74);font-size:.92rem;line-height:1.6}.web-content-switcher{display:flex;gap:.45rem;padding:.3rem;border:1px solid rgba(255,255,255,.12);border-radius:999px;background:rgba(0,23,34,.24);-ms-overflow-style:none;scrollbar-width:none}.web-content-switcher::-webkit-scrollbar{display:none}.web-content-switcher a{display:inline-flex;align-items:center;gap:.4rem;border-radius:999px;padding:.55rem .8rem;color:rgba(255,255,255,.72);font-size:.72rem;font-weight:750}.web-content-switcher a:hover,.web-content-switcher a.active{background:rgba(255,255,255,.14);color:#fff}.web-content-hero__metrics{position:relative;z-index:1;display:grid;grid-template-columns:repeat(3,1fr);align-self:center;overflow:hidden;border:1px solid rgba(255,255,255,.15);border-radius:20px;background:rgba(4,37,52,.46);backdrop-filter:blur(12px)}.web-content-hero__metrics article{display:grid;place-items:center;min-width:0;padding:1.25rem .7rem;text-align:center}.web-content-hero__metrics article+article{border-left:1px solid rgba(255,255,255,.12)}.web-content-hero__metrics span{color:rgba(255,255,255,.58);font-size:.64rem;text-transform:uppercase;letter-spacing:.08em}.web-content-hero__metrics strong{margin:.15rem 0;color:#fff;font-size:1.8rem;line-height:1}.web-content-hero__metrics small{color:#e6bd75;font-size:.58rem}
.web-content-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;border:1px solid var(--line);border-radius:19px;background:#fff;padding:.8rem;box-shadow:0 12px 30px rgba(20,62,79,.055)}.web-content-filters{display:flex;align-items:flex-end;gap:.55rem;min-width:0}.web-content-filters>label{display:grid;gap:.28rem;color:#607888;font-size:.57rem;font-weight:800;text-transform:uppercase;letter-spacing:.055em}.web-content-filters select,.web-content-filters input{height:39px;border:1px solid #d8e4e8;border-radius:10px;background:#f9fbfc;padding:0 .68rem;color:#264759;font-size:.7rem;outline:none}.web-content-filters select:focus,.web-content-filters input:focus{border-color:#2d8494;box-shadow:0 0 0 3px rgba(14,113,129,.1)}.web-content-search{position:relative;display:block!important;width:min(320px,28vw)}.web-content-search>i{position:absolute;bottom:11px;left:.7rem;color:#718894;font-size:1rem}.web-content-search input{width:100%;padding-left:2.15rem}.web-content-filter-button{display:grid;place-items:center;width:39px;height:39px;border:0;border-radius:11px;background:#e9f3f4;color:#0c6778;font-size:1rem}.web-content-clear{height:39px;border:0;background:transparent;padding:0 .25rem;color:#697f8d;font-size:.65rem;font-weight:750}.web-content-clear span{display:inline-grid;place-items:center;width:18px;height:18px;margin-left:.2rem;border-radius:50%;background:#e8f2f3;color:#0d6877}.web-content-primary-action,.web-content-empty button{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;flex:0 0 auto;min-height:45px;border:1px solid rgba(255,255,255,.24);border-radius:999px;background:linear-gradient(108deg,#074660 0%,#0a7182 55%,#7d9c78 100%);padding:.35rem .55rem .35rem .42rem;color:#fff;font-size:.72rem;font-weight:800;box-shadow:0 12px 25px rgba(8,80,102,.2)}.web-content-primary-action>span{display:grid;place-items:center;width:33px;height:33px;border-radius:50%;background:rgba(255,255,255,.14);font-size:1rem}.web-content-primary-action>i{display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.12)}
.web-content-alert{display:flex;align-items:center;gap:.55rem;border:1px solid;border-radius:13px;padding:.65rem .75rem;font-size:.72rem}.web-content-alert>i{font-size:1.05rem}.web-content-alert button{margin-left:auto;border:0;background:transparent;color:inherit}.web-content-alert.is-error{border-color:#edc9cf;background:#fff4f5;color:#a63847}.web-content-alert.is-success{border-color:#c9e6dd;background:#f2fbf8;color:#1b7867}.web-content-results{display:flex;align-items:flex-end;justify-content:space-between;padding:.15rem .2rem}.web-content-results>div{display:grid}.web-content-results span{color:#8a9aa5;font-size:.57rem;font-weight:850;text-transform:uppercase;letter-spacing:.1em}.web-content-results strong{color:#203f51;font-size:.78rem}.web-content-results p{display:flex;align-items:center;gap:.35rem;margin:0;color:#81939e;font-size:.64rem}
.web-content-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.9rem}.web-content-grid.is-testimonials{grid-template-columns:repeat(3,minmax(0,1fr))}.web-content-card{position:relative;display:flex;flex-direction:column;min-width:0;overflow:hidden;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 12px 28px rgba(19,58,74,.055)}.web-content-card::before{position:absolute;z-index:2;top:0;left:24px;width:54px;height:3px;border-radius:0 0 4px 4px;background:#cbd9de;content:""}.web-content-card.is-featured::before{background:linear-gradient(90deg,#dfa548,#8fa376)}.web-content-card.is-updating{opacity:.62;pointer-events:none}.testimonial-card__top{display:flex;align-items:flex-start;justify-content:space-between;gap:.7rem;padding:1.2rem 1.2rem .3rem}.testimonial-avatar{display:grid;place-items:center;width:60px;height:60px;overflow:hidden;border:4px solid #f2f7f8;border-radius:18px;background:linear-gradient(145deg,#d8e9eb,#f4f8f7);color:#0b6576;font-size:.82rem;font-weight:850}.testimonial-avatar img{width:100%;height:100%;object-fit:cover}.web-content-badges{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.3rem}.web-content-badges>span{display:inline-flex;align-items:center;gap:.2rem;border-radius:999px;padding:.25rem .42rem;font-size:.53rem;font-weight:800}.status-pill.is-draft{background:#f1f3f5;color:#65737d}.status-pill.is-published{background:#e5f5ef;color:#188066}.status-pill.is-archived{background:#fbeced;color:#aa4a55}.featured-pill{background:#fff5dd;color:#9b6b18}.web-content-card blockquote{display:-webkit-box;overflow:hidden;min-height:84px;margin:.7rem 1.2rem .8rem;color:#345162;font-size:.82rem;line-height:1.65;-webkit-box-orient:vertical;-webkit-line-clamp:4}.testimonial-author{display:grid;margin:auto 1.2rem 1rem;padding-top:.8rem;border-top:1px solid #edf2f3}.testimonial-author strong{font-size:.74rem}.testimonial-author span{color:#82939d;font-size:.62rem}.student-life-card__media{position:relative;height:175px;overflow:hidden;background:#e9f1f3}.student-life-card__media>img{width:100%;height:100%;object-fit:cover}.student-life-card__media::after{position:absolute;inset:50% 0 0;background:linear-gradient(transparent,rgba(3,37,53,.36));content:""}.student-life-card__media .web-content-badges{position:absolute;z-index:2;top:.8rem;right:.8rem}.student-life-card__placeholder{display:grid;place-items:center;height:100%;color:#77909b}.student-life-card__placeholder i{font-size:2rem}.student-life-card__placeholder span{font-size:.62rem}.student-life-card__body{display:flex;flex:1;flex-direction:column;padding:1rem 1.05rem}.student-life-card__meta{display:flex;align-items:center;justify-content:space-between;gap:.5rem;color:#77909d;font-size:.58rem}.student-life-card__meta span{color:#0c6c7c;font-weight:850;text-transform:uppercase;letter-spacing:.06em}.student-life-card__body h2{display:-webkit-box;overflow:hidden;margin:.45rem 0;color:#15384b;font-size:1rem;line-height:1.3;-webkit-box-orient:vertical;-webkit-line-clamp:2}.student-life-card__body p{display:-webkit-box;overflow:hidden;margin:0;color:#6d818e;font-size:.68rem;line-height:1.55;-webkit-box-orient:vertical;-webkit-line-clamp:3}.web-content-card__footer{display:flex;align-items:center;justify-content:space-between;gap:.55rem;border-top:1px solid #edf2f3;padding:.65rem .75rem}.editorial-order{display:flex;align-items:center;gap:.22rem}.editorial-order>span{display:inline-grid;place-items:center;min-width:31px;height:28px;border-radius:8px;background:#eef4f5;color:#476573;font-size:.58rem;font-weight:850}.editorial-order button,.web-content-card__actions>*{display:grid;place-items:center;width:29px;height:29px;border:1px solid #e0e9ec;border-radius:9px;background:#fff;color:#55717f;font-size:.85rem}.editorial-order button:hover,.web-content-card__actions>*:hover,.web-content-card__actions>*.selected{border-color:#a9cfd4;background:#e9f5f5;color:#096b7c}.web-content-card__actions{display:flex;gap:.28rem}.web-content-card__actions>*.is-danger{color:#a94856}.web-content-card__actions>*:disabled{opacity:.45}.web-content-empty{display:grid;place-items:center;min-height:300px;border:1px dashed #cbdadd;border-radius:22px;background:linear-gradient(145deg,#fbfdfd,#f4f8f8);padding:2rem;text-align:center}.web-content-empty>span{display:grid;place-items:center;width:62px;height:62px;border-radius:20px;background:#e4f0f1;color:#0b6d7c;font-size:1.6rem}.web-content-empty h2{margin:.8rem 0 .25rem;font-size:1rem}.web-content-empty p{margin:0 0 1rem;color:#738994;font-size:.68rem}.web-content-empty button{padding:.55rem .9rem}.web-content-pagination{display:flex;align-items:center;justify-content:flex-end;gap:.65rem;border-top:1px solid var(--line);padding-top:.8rem;color:#758b96;font-size:.64rem}.web-content-pagination button{display:flex;align-items:center;gap:.25rem;border:1px solid #dbe6e9;border-radius:10px;background:#fff;padding:.46rem .65rem;color:#315365;font-size:.62rem;font-weight:750}.web-content-pagination button:disabled{opacity:.4}
.web-content-modal{position:fixed;z-index:1090;inset:0;display:grid;place-items:center;background:rgba(3,28,40,.68);padding:1rem;backdrop-filter:blur(7px)}.web-content-dialog{display:flex;flex-direction:column;width:min(1040px,calc(100vw - 2rem));max-height:calc(100vh - 2rem);overflow:hidden;border:1px solid rgba(255,255,255,.28);border-radius:25px;background:#f7fafb;box-shadow:0 36px 90px rgba(0,20,30,.35)}.web-content-dialog__header{position:relative;display:flex;align-items:center;gap:.85rem;flex:0 0 auto;background:radial-gradient(circle at 82% -20%,rgba(141,168,119,.55),transparent 36%),linear-gradient(110deg,#062f43,#0c6676);padding:1.15rem 1.35rem;color:#fff}.web-content-dialog__icon{display:grid;place-items:center;flex:0 0 48px;height:48px;border:1px solid rgba(255,255,255,.18);border-radius:15px;background:rgba(255,255,255,.12);font-size:1.35rem}.web-content-dialog__header>div{display:grid}.web-content-dialog__header small{color:#edc179;font-size:.56rem;font-weight:850;text-transform:uppercase;letter-spacing:.1em}.web-content-dialog__header h2{margin:.05rem 0;color:#fff;font-size:1.12rem}.web-content-dialog__header p{margin:0;color:rgba(255,255,255,.66);font-size:.61rem}.web-content-dialog__close{display:grid;place-items:center;width:38px;height:38px;margin-left:auto;border:1px solid rgba(255,255,255,.18);border-radius:12px;background:rgba(255,255,255,.1);color:#fff;font-size:1.2rem}.web-content-form{display:flex;min-height:0;flex:1;flex-direction:column}.web-content-form__body{display:grid;gap:.8rem;overflow-y:auto;padding:1rem}.web-content-form__section{border:1px solid #dce7e9;border-radius:17px;background:#fff;padding:1rem}.web-content-form__section>header{display:flex;align-items:center;gap:.65rem;margin-bottom:.9rem}.web-content-form__section>header>span{display:grid;place-items:center;width:34px;height:34px;border-radius:11px;background:#e8f2f3;color:#0c6a79;font-size:.62rem;font-weight:900}.web-content-form__section>header>div{display:grid}.web-content-form__section h3{margin:0;color:#1f4254;font-size:.82rem}.web-content-form__section header p{margin:.1rem 0 0;color:#82939c;font-size:.58rem}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.field-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}.field{display:grid;align-content:start;gap:.3rem;margin:0}.field.is-full{margin-top:.7rem}.field>span{display:flex;align-items:center;gap:.2rem;color:#527080;font-size:.58rem;font-weight:850;text-transform:uppercase;letter-spacing:.045em}.field>span em{color:#bd4856;font-style:normal}.field>span small{margin-left:auto;color:#92a0a8;font-size:.52rem;font-weight:650;letter-spacing:0}.field input,.field select,.field textarea,.slug-field{width:100%;border:1px solid #d6e3e7;border-radius:10px;background:#fbfcfd;padding:.65rem .7rem;color:#294b5d;font-size:.68rem;outline:none}.field input,.field select{height:40px}.field textarea{resize:vertical;line-height:1.5}.field input:focus,.field select:focus,.field textarea:focus,.slug-field:focus-within{border-color:#218092;box-shadow:0 0 0 3px rgba(14,113,129,.1)}.field.has-error input,.field.has-error textarea,.field.has-error select{border-color:#d16570}.field>strong,.field-error{margin:0;color:#b4424e;font-size:.56rem;font-weight:700}.slug-field{display:flex;align-items:center;padding:0;overflow:hidden}.slug-field>span{display:flex;align-items:center;align-self:stretch;background:#edf3f4;padding:0 .6rem;color:#6c818c;font-size:.58rem}.slug-field input{height:38px;border:0;border-radius:0;background:transparent;box-shadow:none!important}.editor-placeholder{display:flex;align-items:center;justify-content:center;gap:.45rem;min-height:150px;border:1px solid #d6e3e7;border-radius:10px;color:#758b96;font-size:.64rem}.field :deep(.ck-editor__editable_inline){min-height:210px;max-height:420px}.field :deep(.ck.ck-editor__main>.ck-editor__editable),.field :deep(.ck.ck-toolbar){border-color:#d6e3e7}.media-editor{display:grid;grid-template-columns:220px minmax(0,1fr);gap:1rem}.media-preview{display:grid;place-items:center;overflow:hidden;min-height:150px;border:1px dashed #bfd1d6;border-radius:15px;background:#f1f6f7}.media-preview.is-portrait{align-self:start;aspect-ratio:1;min-height:0;border-radius:22px}.media-preview img{width:100%;height:100%;object-fit:cover}.media-preview>span{display:grid;place-items:center;gap:.25rem;color:#82959f;font-size:.6rem}.media-preview>span i{font-size:1.7rem}.media-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-content:start;gap:.65rem}.file-picker{grid-column:1/-1;display:block;border:1px dashed #bcd0d5;border-radius:12px;background:#f7fafb;padding:.55rem;cursor:pointer}.file-picker input{position:absolute;opacity:0;pointer-events:none}.file-picker>span{display:flex;align-items:center;gap:.65rem}.file-picker>span>i{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#e3f0f1;color:#0c6b7b;font-size:1.1rem}.file-picker>span>strong{color:#315465;font-size:.65rem}.file-picker>span>small{margin-left:auto;color:#8799a2;font-size:.56rem}.remove-media{justify-self:start;border:0;background:transparent;padding:0;color:#a84552;font-size:.58rem;font-weight:750}.gallery-editor{margin-top:1rem;border-top:1px solid #e8eff1;padding-top:.85rem}.gallery-editor__heading{display:flex;align-items:center;justify-content:space-between;gap:.6rem}.gallery-editor__heading>div{display:grid}.gallery-editor__heading strong{font-size:.68rem}.gallery-editor__heading span{color:#82949e;font-size:.56rem}.gallery-editor__heading label{display:flex;align-items:center;gap:.35rem;border:1px solid #c9dbde;border-radius:999px;background:#f3f8f8;padding:.42rem .65rem;color:#0d6c7a;font-size:.58rem;font-weight:800;cursor:pointer}.gallery-editor__heading label input{position:absolute;opacity:0;pointer-events:none}.gallery-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.55rem;margin-top:.7rem}.gallery-grid article{position:relative;display:grid;overflow:hidden;border:1px solid #dce7e9;border-radius:11px;background:#f6f9fa}.gallery-grid img{width:100%;height:88px;object-fit:cover}.gallery-grid article>button{position:absolute;top:.3rem;right:.3rem;display:grid;place-items:center;width:24px;height:24px;border:0;border-radius:50%;background:rgba(5,38,52,.76);color:#fff}.gallery-grid article>span{padding:.3rem .42rem;color:#7d909a;font-size:.5rem}.gallery-grid article label input{width:calc(100% - .5rem);height:31px;margin:.25rem;border:1px solid #d8e4e7;border-radius:7px;padding:.3rem;font-size:.54rem}.toggle-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem;margin-top:.75rem}.toggle-grid>label{display:flex;align-items:center;gap:.6rem;border:1px solid #dce7e9;border-radius:12px;background:#fafcfc;padding:.65rem;cursor:pointer}.toggle-grid input{position:absolute;opacity:0}.toggle-grid label>span{display:grid;place-items:center;width:36px;height:36px;border-radius:11px;background:#e8eff1;color:#718892}.toggle-grid label>div{display:grid}.toggle-grid strong{color:#355665;font-size:.62rem}.toggle-grid small{color:#8798a1;font-size:.52rem}.toggle-grid input:checked+span{background:linear-gradient(145deg,#0c6c7c,#779778);color:#fff}.toggle-grid label:focus-within{outline:3px solid rgba(14,113,129,.12)}.seo-panel{margin-top:.8rem;border:1px solid #dce7e9;border-radius:12px}.seo-panel summary{display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:.65rem .75rem;color:#476674;font-size:.61rem;font-weight:750;list-style:none}.seo-panel summary span{display:flex;align-items:center;gap:.35rem}.seo-panel summary::-webkit-details-marker{display:none}.seo-panel>div{border-top:1px solid #e7eff1;padding:.75rem}.web-content-form__footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex:0 0 auto;border-top:1px solid #dce7e9;background:#fff;padding:.75rem 1rem}.web-content-form__footer p{display:flex;align-items:center;gap:.35rem;margin:0;color:#7a8e99;font-size:.57rem}.web-content-form__footer>div{display:flex;gap:.45rem}.web-content-form__footer button{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;min-height:39px;border:1px solid #d7e2e5;border-radius:999px;background:#fff;padding:.45rem .85rem;color:#496675;font-size:.62rem;font-weight:800}.web-content-form__footer button.is-primary{border-color:transparent;background:linear-gradient(108deg,#074660,#0a7182 58%,#789977);color:#fff;box-shadow:0 9px 20px rgba(7,79,99,.18)}.web-content-form__footer button:disabled{opacity:.55}
.consent-pill{background:#e8f4f0;color:#287765}.consent-pill.is-pending{background:#fff0e3;color:#a76025}.consent-check{display:flex;align-items:flex-start;gap:.65rem;margin-top:.75rem;border:1px solid #d8e5e8;border-radius:13px;background:#f7fafb;padding:.72rem;cursor:pointer}.consent-check input{position:absolute;opacity:0}.consent-check>span{display:grid;place-items:center;flex:0 0 38px;height:38px;border-radius:11px;background:#e7eff1;color:#708792;font-size:1.05rem}.consent-check>div{display:grid;gap:.08rem}.consent-check strong{color:#355665;font-size:.62rem}.consent-check strong em{color:#b94452;font-style:normal}.consent-check small{max-width:760px;color:#718792;font-size:.56rem;line-height:1.45}.consent-check b{margin-top:.15rem;color:#b4424e;font-size:.55rem}.consent-check input:checked+span{background:linear-gradient(145deg,#0c6c7c,#789978);color:#fff}.consent-check.has-error{border-color:#d16570;background:#fff7f8}.consent-check:focus-within{outline:3px solid rgba(14,113,129,.12)}
.installation-card__details{display:flex;flex-wrap:wrap;gap:.32rem;margin-top:auto;padding-top:.75rem}.installation-card__details span{display:inline-flex;align-items:center;gap:.22rem;max-width:100%;border:1px solid #dbe7e9;border-radius:999px;background:#f4f8f8;padding:.28rem .48rem;color:#617986;font-size:.54rem;font-weight:700;white-space:nowrap}.installation-card__details i{color:#0d7180;font-size:.7rem}.installation-practical-fields{margin-top:.7rem}.installation-features{margin-top:1rem;border-top:1px solid #e8eff1;padding-top:.85rem}.installation-features__heading{display:flex;align-items:center;justify-content:space-between;gap:.75rem}.installation-features__heading>div{display:grid}.installation-features__heading strong{color:#315465;font-size:.68rem}.installation-features__heading span{color:#82949e;font-size:.56rem}.installation-features__heading button{display:inline-flex;align-items:center;gap:.3rem;border:1px solid #c9dbde;border-radius:999px;background:#f3f8f8;padding:.42rem .65rem;color:#0d6c7a;font-size:.58rem;font-weight:800}.installation-features__heading button:disabled{opacity:.45}.installation-features__list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem;margin-top:.65rem}.installation-features__list label{display:flex;min-width:0;overflow:hidden;border:1px solid #d8e4e7;border-radius:10px;background:#fbfcfd}.installation-features__list input{width:100%;min-width:0;height:38px;border:0;background:transparent;padding:.58rem .65rem;color:#294b5d;font-size:.64rem;outline:none}.installation-features__list button{display:grid;place-items:center;flex:0 0 36px;border:0;border-left:1px solid #e3ecee;background:#fff;color:#a54b57}.installation-features__list label:focus-within{border-color:#218092;box-shadow:0 0 0 3px rgba(14,113,129,.1)}.gallery-grid article>.gallery-order{position:absolute;right:.32rem;bottom:2.25rem;display:flex;gap:.2rem}.gallery-order button{display:grid;place-items:center;width:23px;height:23px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:rgba(5,38,52,.78);color:#fff}.gallery-order button:disabled{opacity:.35}.web-content--installations .student-life-card__media{height:195px}.web-content--installations .student-life-card__body{min-height:190px}
@media(max-width:1400px){.web-content-toolbar{align-items:stretch;flex-direction:column}.web-content-filters{display:grid;grid-template-columns:minmax(200px,1.5fr) repeat(4,minmax(100px,1fr)) auto}.web-content-search{width:auto}.web-content-primary-action{align-self:flex-end}}
@media(max-width:1100px){.web-content-hero{grid-template-columns:1fr}.web-content-hero__metrics{max-width:480px}.web-content-toolbar{align-items:stretch;flex-direction:column}.web-content-filters{display:grid;grid-template-columns:minmax(210px,1.5fr) repeat(4,minmax(120px,1fr)) auto}.web-content-search{width:auto}.web-content-primary-action{align-self:flex-end}.web-content-grid,.web-content-grid.is-testimonials{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767.98px){.web-content{padding:.75rem .65rem 1.75rem}.web-content-hero{min-height:0;border-radius:21px;padding:1.3rem}.web-content-hero h1{font-size:2rem}.web-content-hero__copy>p{font-size:.75rem}.web-content-switcher{width:100%;overflow:auto}.web-content-switcher a{flex:0 0 auto}.web-content-hero__metrics{width:100%}.web-content-hero__metrics article{padding:.85rem .3rem}.web-content-hero__metrics strong{font-size:1.3rem}.web-content-toolbar{border-radius:16px}.web-content-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.web-content-search{grid-column:1/-1}.web-content-filter-button{width:100%}.web-content-clear{justify-self:start}.web-content-primary-action{width:100%}.web-content-results{align-items:flex-start;flex-direction:column;gap:.25rem}.web-content-results p{font-size:.56rem}.web-content-grid,.web-content-grid.is-testimonials{grid-template-columns:1fr}.web-content-pagination{justify-content:space-between}.web-content-pagination button{font-size:0}.web-content-pagination button i{font-size:1rem}.web-content-modal{place-items:stretch;padding:0}.web-content-dialog{width:100%;max-height:100vh;border:0;border-radius:0}.web-content-dialog__header{padding:.85rem}.web-content-dialog__header p{display:none}.web-content-dialog__icon{flex-basis:40px;height:40px}.web-content-form__body{padding:.7rem}.web-content-form__section{padding:.8rem}.field-grid,.field-grid--three,.media-editor,.media-fields,.toggle-grid,.installation-features__list{grid-template-columns:1fr}.media-preview{min-height:180px}.media-preview.is-portrait{width:160px}.file-picker>span{align-items:flex-start;flex-wrap:wrap}.file-picker>span>small{width:100%;margin-left:44px}.gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.gallery-editor__heading{align-items:flex-start;flex-direction:column}.web-content-form__footer{align-items:stretch;flex-direction:column}.web-content-form__footer p{display:none}.web-content-form__footer>div{display:grid;grid-template-columns:.8fr 1.2fr}.web-content-form__footer button{width:100%}}
.web-content-form__section,.field{min-width:0}.field :deep(.ck-editor){width:100%;min-width:0;max-width:100%;overflow:hidden}.field :deep(.ck.ck-toolbar>.ck-toolbar__items){flex-wrap:wrap}
@media(prefers-reduced-motion:reduce){.web-content *{scroll-behavior:auto!important;transition:none!important}}
.editorial-order button,
.web-content-card__actions > * {
  width: 38px;
  height: 38px;
  border-color: #dbe7ea;
  border-radius: 11px;
  color: #4c6b79;
  font-size: 1rem;
  box-shadow: 0 4px 10px rgba(18, 58, 73, 0.04);
  transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
}

.editorial-order button:hover,
.web-content-card__actions > *:hover,
.web-content-card__actions > *.selected {
  border-color: #9bc7cd;
  background: #e8f4f4;
  color: #07677a;
  transform: translateY(-1px);
}

.web-content-card__actions {
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.32rem;
}

.web-content-card__actions > *.is-danger:hover {
  border-color: #e6b8bf;
  background: #fff2f3;
  color: #a23847;
}

.web-content-card__actions > *:disabled {
  transform: none;
}

@media (prefers-reduced-motion: reduce) {
  .editorial-order button,
  .web-content-card__actions > * {
    transition: none;
  }
}
</style>
