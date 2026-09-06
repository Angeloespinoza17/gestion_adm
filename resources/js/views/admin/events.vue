<script>
import axios from "axios";
import { Ckeditor } from "@ckeditor/ckeditor5-vue";
import { markRaw } from "vue";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import SiteAdminNavigation from "../../components/public-site/site-admin-navigation.vue";
import "../../components/public-site/site-admin-legacy.css";

const emptyScheduleItem = () => ({
  time: "",
  title: "",
  description: "",
});

const emptyGalleryImage = () => ({
  url: "",
  alt: "",
});

const emptyForm = () => ({
  id: null,
  title: "",
  slug: "",
  summary: "",
  body: "",
  category: "",
  location: "",
  starts_at: "",
  ends_at: "",
  external_url: "",
  header_image_url: "",
  hero_image_url: "",
  hero_image_alt: "",
  highlights: [""],
  schedule_items: [emptyScheduleItem()],
  gallery_intro: "",
  gallery_images: [emptyGalleryImage()],
  registration_enabled: false,
  registration_title: "Inscripción al evento",
  registration_button_label: "Inscribirme",
  registration_url: "",
  organizer_name: "",
  organizer_position: "",
  organizer_description: "",
  organizer_email: "",
  organizer_phone: "",
  organizer_image_url: "",
  organizer_image_alt: "",
  status: "draft",
  featured: false,
  sort_order: 0,
});

export default {
  components: { Ckeditor, Layout, LoadingState, SiteAdminNavigation },
  data() {
    return {
      editor: null,
      editorLoading: false,
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
      loading: false,
      saving: false,
      search: "",
      statusFilter: "",
      categoryFilter: "",
      featuredFilter: "",
      items: [],
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      catalogs: {
        statuses: [],
        categories: [],
        stats: {},
      },
      form: emptyForm(),
      showModal: false,
      error: null,
      success: null,
    };
  },
  computed: {
    permissions() {
      try {
        const stored = JSON.parse(localStorage.getItem("permissions") || "[]");
        return Array.isArray(stored) ? stored : [];
      } catch (error) {
        return [];
      }
    },
    canManage() {
      const serverValue = this.catalogs.capabilities?.can_manage;
      if (typeof serverValue === "boolean") return serverValue;
      return this.permissions.includes("__superadmin__") || this.permissions.includes("gestionar_eventos");
    },
    activeFilterCount() {
      return [this.search, this.statusFilter, this.categoryFilter, this.featuredFilter]
        .filter((value) => String(value).trim() !== "").length;
    },
    isEditing() {
      return Boolean(this.form.id);
    },
    statusOptions() {
      return [{ value: "", text: "Todos" }].concat(
        (this.catalogs.statuses || []).map((status) => ({ value: status.value, text: status.label }))
      );
    },
    formStatusOptions() {
      return (this.catalogs.statuses || []).map((status) => ({ value: status.value, text: status.label }));
    },
    categoryOptions() {
      return [{ value: "", text: "Todas" }].concat(
        (this.catalogs.categories || []).map((category) => ({ value: category, text: category }))
      );
    },
    featuredOptions() {
      return [
        { value: "", text: "Todos" },
        { value: "1", text: "Destacados" },
        { value: "0", text: "No destacados" },
      ];
    },
  },
  mounted() {
    this.loadCatalogs();
    this.load();
  },
  methods: {
    applyStatusFilter(status = "") {
      this.statusFilter = status;
      this.load(1);
    },
    applyFeaturedFilter(value = "") {
      this.featuredFilter = value;
      this.load(1);
    },
    async ensureEditor() {
      if (this.editor || this.editorLoading) return;
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
        const response = await axios.get("/api/admin/events/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/admin/events", {
          params: {
            page,
            search: this.search || null,
            status: this.statusFilter || null,
            category: this.categoryFilter || null,
            featured: this.featuredFilter === "" ? null : this.featuredFilter,
          },
        });

        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          total: response.data.total || 0,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.search = "";
      this.statusFilter = "";
      this.categoryFilter = "";
      this.featuredFilter = "";
      this.load();
    },
    async openCreate() {
      this.form = emptyForm();
      this.error = null;
      this.success = null;
      this.showModal = true;
      await this.ensureEditor();
    },
    async openEdit(item) {
      this.form = {
        id: item.id,
        title: item.title || "",
        slug: item.slug || "",
        summary: item.summary || "",
        body: item.body || "",
        category: item.category || "",
        location: item.location || "",
        starts_at: this.toDatetimeLocal(item.starts_at),
        ends_at: this.toDatetimeLocal(item.ends_at),
        external_url: item.external_url || "",
        header_image_url: item.header_image_url || "",
        hero_image_url: item.hero_image_url || "",
        hero_image_alt: item.hero_image_alt || "",
        highlights: this.normalizeHighlights(item.highlights),
        schedule_items: this.normalizeScheduleItems(item.schedule_items),
        gallery_intro: item.gallery_intro || "",
        gallery_images: this.normalizeGalleryImages(item.gallery_images),
        registration_enabled: Boolean(item.registration_enabled),
        registration_title: item.registration_title || "Inscripción al evento",
        registration_button_label: item.registration_button_label || "Inscribirme",
        registration_url: item.registration_url || "",
        organizer_name: item.organizer_name || "",
        organizer_position: item.organizer_position || "",
        organizer_description: item.organizer_description || "",
        organizer_email: item.organizer_email || "",
        organizer_phone: item.organizer_phone || "",
        organizer_image_url: item.organizer_image_url || "",
        organizer_image_alt: item.organizer_image_alt || "",
        status: item.status || "draft",
        featured: Boolean(item.featured),
        sort_order: item.sort_order || 0,
      };
      this.error = null;
      this.success = null;
      this.showModal = true;
      await this.ensureEditor();
    },
    payload() {
      return {
        title: this.form.title,
        slug: this.form.slug,
        summary: this.form.summary,
        body: this.form.body,
        category: this.form.category,
        location: this.form.location,
        starts_at: this.form.starts_at,
        ends_at: this.form.ends_at,
        external_url: this.form.external_url,
        header_image_url: this.form.header_image_url,
        hero_image_url: this.form.hero_image_url,
        hero_image_alt: this.form.hero_image_alt,
        highlights: this.cleanHighlights(this.form.highlights),
        schedule_items: this.cleanScheduleItems(this.form.schedule_items),
        gallery_intro: this.form.gallery_intro,
        gallery_images: this.cleanGalleryImages(this.form.gallery_images),
        registration_enabled: Boolean(this.form.registration_enabled),
        registration_title: this.form.registration_title,
        registration_button_label: this.form.registration_button_label,
        registration_url: this.form.registration_url,
        organizer_name: this.form.organizer_name,
        organizer_position: this.form.organizer_position,
        organizer_description: this.form.organizer_description,
        organizer_email: this.form.organizer_email,
        organizer_phone: this.form.organizer_phone,
        organizer_image_url: this.form.organizer_image_url,
        organizer_image_alt: this.form.organizer_image_alt,
        status: this.form.status,
        featured: Boolean(this.form.featured),
        sort_order: Number(this.form.sort_order || 0),
      };
    },
    payloadFromItem(item) {
      return {
        title: item.title,
        slug: item.slug,
        summary: item.summary || "",
        body: item.body || "",
        category: item.category || "",
        location: item.location || "",
        starts_at: item.starts_at,
        ends_at: item.ends_at || "",
        external_url: item.external_url || "",
        header_image_url: item.header_image_url || "",
        hero_image_url: item.hero_image_url || "",
        hero_image_alt: item.hero_image_alt || "",
        highlights: this.cleanHighlights(item.highlights || []),
        schedule_items: this.cleanScheduleItems(item.schedule_items || []),
        gallery_intro: item.gallery_intro || "",
        gallery_images: this.cleanGalleryImages(item.gallery_images || []),
        registration_enabled: Boolean(item.registration_enabled),
        registration_title: item.registration_title || "",
        registration_button_label: item.registration_button_label || "",
        registration_url: item.registration_url || "",
        organizer_name: item.organizer_name || "",
        organizer_position: item.organizer_position || "",
        organizer_description: item.organizer_description || "",
        organizer_email: item.organizer_email || "",
        organizer_phone: item.organizer_phone || "",
        organizer_image_url: item.organizer_image_url || "",
        organizer_image_alt: item.organizer_image_alt || "",
        status: item.status || "draft",
        featured: Boolean(item.featured),
        sort_order: Number(item.sort_order || 0),
      };
    },
    async confirmSave() {
      const result = await Swal.fire({
        title: this.isEditing ? "Guardar cambios" : "Crear evento",
        text: this.form.title || "El evento será guardado.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: this.isEditing ? "Guardar cambios" : "Crear evento",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      await this.save();
    },
    async save() {
      this.saving = true;
      this.error = null;
      this.success = null;
      let message = "";

      try {
        if (this.isEditing) {
          const response = await axios.put(`/api/admin/events/${this.form.id}`, this.payload());
          message = response.data.message || "Evento actualizado.";
        } else {
          const response = await axios.post("/api/admin/events", this.payload());
          message = response.data.message || "Evento creado.";
        }

        this.success = message;
        this.showModal = false;
        await this.loadCatalogs();
        await this.load(this.pagination.current_page);
        await Swal.fire({
          title: "Listo",
          text: message,
          icon: "success",
          confirmButtonText: "Aceptar",
        });
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async togglePublished(item) {
      const payload = this.payloadFromItem(item);
      payload.status = item.status === "published" ? "archived" : "published";

      try {
        const response = await axios.put(`/api/admin/events/${item.id}`, payload);
        this.success = response.data.message || "Estado actualizado.";
        await this.loadCatalogs();
        await this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async remove(item) {
      const result = await Swal.fire({
        title: "Eliminar evento",
        text: item.title,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        const response = await axios.delete(`/api/admin/events/${item.id}`);
        this.success = response.data.message || "Evento eliminado.";
        await this.loadCatalogs();
        await this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    formatDate(value) {
      if (!value) return "-";

      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(value));
    },
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
    },
    normalizeHighlights(items) {
      const values = Array.isArray(items) ? items.map((item) => String(item || "")) : [];
      return values.length ? values : [""];
    },
    normalizeScheduleItems(items) {
      const values = Array.isArray(items)
        ? items.map((item) => ({
            time: item?.time || "",
            title: item?.title || "",
            description: item?.description || "",
          }))
        : [];

      return values.length ? values : [emptyScheduleItem()];
    },
    normalizeGalleryImages(items) {
      const values = Array.isArray(items)
        ? items.map((item) => ({
            url: item?.url || "",
            alt: item?.alt || "",
          }))
        : [];

      return values.length ? values : [emptyGalleryImage()];
    },
    cleanHighlights(items) {
      return (Array.isArray(items) ? items : [])
        .map((item) => String(item || "").trim())
        .filter(Boolean);
    },
    cleanScheduleItems(items) {
      return (Array.isArray(items) ? items : [])
        .map((item) => ({
          time: String(item?.time || "").trim(),
          title: String(item?.title || "").trim(),
          description: String(item?.description || "").trim(),
        }))
        .filter((item) => item.time || item.title || item.description);
    },
    cleanGalleryImages(items) {
      return (Array.isArray(items) ? items : [])
        .map((item) => ({
          url: String(item?.url || "").trim(),
          alt: String(item?.alt || "").trim(),
        }))
        .filter((item) => item.url);
    },
    addHighlight() {
      this.form.highlights.push("");
    },
    removeHighlight(index) {
      this.form.highlights.splice(index, 1);
      if (!this.form.highlights.length) this.addHighlight();
    },
    addScheduleItem() {
      this.form.schedule_items.push(emptyScheduleItem());
    },
    removeScheduleItem(index) {
      this.form.schedule_items.splice(index, 1);
      if (!this.form.schedule_items.length) this.addScheduleItem();
    },
    addGalleryImage() {
      this.form.gallery_images.push(emptyGalleryImage());
    },
    removeGalleryImage(index) {
      this.form.gallery_images.splice(index, 1);
      if (!this.form.gallery_images.length) this.addGalleryImage();
    },
    toDatetimeLocal(value) {
      if (!value) return "";
      const date = new Date(value);
      const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
      return local.toISOString().slice(0, 16);
    },
    statusLabel(value) {
      const status = (this.catalogs.statuses || []).find((entry) => entry.value === value);
      return status?.label || value || "-";
    },
    statusVariant(value) {
      return {
        published: "success",
        draft: "secondary",
        archived: "warning",
      }[value] || "light";
    },
    publicUrl(item) {
      return item.public_url || `/eventos/${item.id}`;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "No se pudo gestionar el evento."
      );
    },
  },
};
</script>

<template>
  <Layout>
    <main class="site-admin-page site-admin-page--events">
      <SiteAdminNavigation />

      <section class="site-admin-hero" aria-labelledby="events-admin-title">
        <div class="site-admin-hero__copy">
          <span class="site-admin-eyebrow"><i class="bx bx-calendar-event"></i> Agenda institucional</span>
          <h1 id="events-admin-title">Eventos</h1>
          <p>Organiza las actividades públicas, sus fechas, lugares, contenidos e inscripción desde una sola agenda.</p>
          <BButton v-if="canManage" class="site-admin-primary-action" @click="openCreate">
            <i class="bx bx-plus"></i>
            Nuevo evento
          </BButton>
        </div>

        <div class="site-admin-metrics site-admin-metrics--four" aria-label="Resumen de eventos">
          <button type="button" :class="{ active: !activeFilterCount }" @click="resetFilters">
            <span>Total</span><strong>{{ formatNumber(catalogs.stats?.total) }}</strong><small>actividades</small>
          </button>
          <button type="button" :class="{ active: statusFilter === 'published' }" @click="applyStatusFilter('published')">
            <span>En línea</span><strong>{{ formatNumber(catalogs.stats?.published) }}</strong><small>publicados</small>
          </button>
          <button type="button" :class="{ active: statusFilter === 'draft' }" @click="applyStatusFilter('draft')">
            <span>Pendientes</span><strong>{{ formatNumber(catalogs.stats?.draft) }}</strong><small>borradores</small>
          </button>
          <button type="button" :class="{ active: featuredFilter === '1' }" @click="applyFeaturedFilter('1')">
            <span>Portada</span><strong>{{ formatNumber(catalogs.stats?.featured) }}</strong><small>destacados</small>
          </button>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="site-admin-alert">{{ error }}</BAlert>
      <BAlert v-if="success" variant="success" show class="site-admin-alert">{{ success }}</BAlert>

    <BCard class="site-admin-filter-card">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label">Buscar actividad</label>
          <div class="site-admin-search">
            <i class="bx bx-search"></i>
            <BFormInput v-model="search" placeholder="Título, resumen, categoría o lugar" @keyup.enter="load" />
          </div>
        </div>
        <div class="col-md-3 col-lg-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="statusFilter" :options="statusOptions" />
        </div>
        <div class="col-md-3 col-lg-2">
          <label class="form-label">Categoría</label>
          <BFormSelect v-model="categoryFilter" :options="categoryOptions" />
        </div>
        <div class="col-md-3 col-lg-2">
          <label class="form-label">Destacado</label>
          <BFormSelect v-model="featuredFilter" :options="featuredOptions" />
        </div>
        <div class="col-md-3 col-lg-2 d-flex gap-2 site-admin-filter-actions">
          <BButton class="site-admin-filter-button" @click="load(1)"><i class="bx bx-filter-alt"></i> Aplicar</BButton>
          <BButton v-if="activeFilterCount" variant="light" class="site-admin-clear-button" :aria-label="`Limpiar ${activeFilterCount} filtros`" @click="resetFilters">
            Limpiar <span>{{ activeFilterCount }}</span>
          </BButton>
        </div>
      </div>
    </BCard>

    <BCard class="site-admin-table-card">
      <header class="site-admin-table-heading">
        <div><span>Agenda editorial</span><strong>{{ pagination.total }} evento(s)</strong></div>
        <p><i class="bx bx-info-circle"></i> Revisa fecha, estado y portada antes de publicar.</p>
      </header>
      <BTable
        :items="items"
        :busy="loading"
        responsive
        hover
        small
        show-empty
        table-class="site-admin-table align-middle mb-0"
        :fields="[
          { key: 'title', label: 'Evento' },
          { key: 'starts_at', label: 'Fecha' },
          { key: 'status', label: 'Estado' },
          { key: 'featured', label: 'Destacado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando eventos..." compact />
        </template>
        <template #empty>
          <div class="site-admin-empty"><i class="bx bx-calendar-x"></i><span>No hay eventos para los filtros seleccionados.</span></div>
        </template>
        <template #cell(title)="{ item }">
          <div class="event-title-cell site-entity-cell">
            <span class="event-title-icon"><i class="bx bx-calendar-event"></i></span>
            <div class="min-w-0">
              <div class="fw-semibold text-truncate">{{ item.title }}</div>
              <div class="text-muted small text-truncate">{{ item.category || "Sin categoría" }} · {{ item.slug }}</div>
              <div v-if="item.location" class="text-muted small text-truncate"><i class="bx bx-map"></i> {{ item.location }}</div>
            </div>
          </div>
        </template>
        <template #cell(starts_at)="{ item }">
          <span class="small">{{ formatDate(item.starts_at) }}</span>
        </template>
        <template #cell(status)="{ item }">
          <span :class="['site-status-chip', `is-${item.status || 'draft'}`]">{{ statusLabel(item.status) }}</span>
        </template>
        <template #cell(featured)="{ item }">
          <span :class="['site-featured-chip', { active: item.featured }]"><i class="bx" :class="item.featured ? 'bxs-star' : 'bx-star'"></i>{{ item.featured ? "Destacado" : "Regular" }}</span>
        </template>
        <template #cell(actions)="{ item }">
          <div class="site-row-actions">
            <BButton v-if="item.status === 'published'" size="sm" variant="outline-secondary" class="site-row-action" :href="publicUrl(item)" target="_blank" rel="noopener" :aria-label="`Ver ${item.title} en el sitio web`" title="Ver en el sitio web"><i class="bx bx-link-external"></i><span>Ver</span></BButton>
            <BButton v-if="canManage" size="sm" variant="outline-primary" class="site-row-action" title="Editar" :aria-label="`Editar ${item.title}`" @click="openEdit(item)"><i class="bx bx-edit-alt"></i><span>Editar</span></BButton>
            <BButton
              v-if="canManage"
              size="sm"
              :variant="item.status === 'published' ? 'outline-warning' : 'outline-success'"
              class="site-row-action"
              :title="item.status === 'published' ? 'Archivar' : 'Publicar'"
              :aria-label="`${item.status === 'published' ? 'Archivar' : 'Publicar'} ${item.title}`"
              @click="togglePublished(item)"
            >
              <i class="bx" :class="item.status === 'published' ? 'bx-archive' : 'bx-world'"></i><span>{{ item.status === "published" ? "Archivar" : "Publicar" }}</span>
            </BButton>
            <BButton v-if="canManage" size="sm" variant="outline-danger" class="site-row-action is-danger" title="Eliminar" :aria-label="`Eliminar ${item.title}`" @click="remove(item)"><i class="bx bx-trash"></i><span>Eliminar</span></BButton>
          </div>
        </template>
      </BTable>

      <div class="site-admin-pagination">
        <div class="text-muted small">{{ pagination.total }} evento(s)</div>
        <div class="d-flex align-items-center gap-2">
          <BButton size="sm" variant="outline-secondary" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">
            <i class="bx bx-chevron-left"></i> Anterior
          </BButton>
          <span class="small">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
          <BButton
            size="sm"
            variant="outline-secondary"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="load(pagination.current_page + 1)"
          >
            Siguiente <i class="bx bx-chevron-right"></i>
          </BButton>
        </div>
      </div>
    </BCard>

    <BModal v-model="showModal" :title="isEditing ? 'Editar evento' : 'Nuevo evento'" size="xl" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="event-form-section">
        <div class="event-form-section-title">Información principal</div>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Título</label>
            <BFormInput v-model="form.title" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Slug</label>
            <BFormInput v-model="form.slug" placeholder="se-genera-si-queda-vacio" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Categoría</label>
            <BFormInput v-model="form.category" list="event-categories" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Inicio</label>
            <BFormInput v-model="form.starts_at" type="datetime-local" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Término</label>
            <BFormInput v-model="form.ends_at" type="datetime-local" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Lugar</label>
            <BFormInput v-model="form.location" />
          </div>
          <div class="col-md-6">
            <label class="form-label">URL externa</label>
            <BFormInput v-model="form.external_url" placeholder="https://..." />
          </div>
          <div class="col-md-4">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="form.status" :options="formStatusOptions" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Orden</label>
            <BFormInput v-model="form.sort_order" type="number" min="0" />
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <BFormCheckbox v-model="form.featured">Destacado</BFormCheckbox>
          </div>
        </div>
      </div>

      <div class="event-form-section">
        <div class="event-form-section-title">Portada del detalle</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Imagen de cabecera</label>
            <BFormInput v-model="form.header_image_url" placeholder="niceschool/assets/img/education/showcase-1.webp" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Imagen principal del evento</label>
            <BFormInput v-model="form.hero_image_url" placeholder="niceschool/assets/img/education/events-9.webp" />
          </div>
          <div class="col-md-12">
            <label class="form-label">Texto alternativo de la imagen principal</label>
            <BFormInput v-model="form.hero_image_alt" />
          </div>
        </div>
      </div>

      <div class="event-form-section">
        <div class="event-form-section-title">Contenido del evento</div>
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label">Resumen</label>
            <BFormTextarea v-model="form.summary" rows="3" maxlength="700" />
          </div>
          <div class="col-md-12">
            <label class="form-label">Contenido</label>
            <div class="form-ckeditor event-editor">
              <div v-if="editorLoading" class="editor-loading"><span class="spinner-border spinner-border-sm"></span> Cargando editor…</div>
              <Ckeditor v-else-if="editor" v-model="form.body" :editor="editor" :config="editorConfig" />
            </div>
          </div>
        </div>
      </div>

      <div class="event-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="event-form-section-title mb-0">Puntos destacados</div>
          <BButton size="sm" variant="outline-primary" @click="addHighlight">
            <i class="bx bx-plus me-1"></i>
            Agregar
          </BButton>
        </div>
        <div class="event-repeat-list">
          <div v-for="(highlight, index) in form.highlights" :key="`highlight-${index}`" class="event-repeat-row">
            <BFormInput v-model="form.highlights[index]" placeholder="Ej: Charla con invitados, muestra de proyectos, etc." />
            <BButton size="sm" variant="outline-danger" class="event-repeat-delete" @click="removeHighlight(index)">
              <i class="bx bx-trash"></i>
            </BButton>
          </div>
        </div>
      </div>

      <div class="event-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="event-form-section-title mb-0">Programa del evento</div>
          <BButton size="sm" variant="outline-primary" @click="addScheduleItem">
            <i class="bx bx-plus me-1"></i>
            Agregar bloque
          </BButton>
        </div>
        <div class="event-repeat-list">
          <div v-for="(item, index) in form.schedule_items" :key="`schedule-${index}`" class="event-schedule-editor">
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Horario</label>
                <BFormInput v-model="item.time" placeholder="09:00 - 09:30" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Actividad</label>
                <BFormInput v-model="item.title" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Descripción</label>
                <BFormInput v-model="item.description" />
              </div>
              <div class="col-md-1 d-flex align-items-end">
                <BButton size="sm" variant="outline-danger" class="event-repeat-delete" @click="removeScheduleItem(index)">
                  <i class="bx bx-trash"></i>
                </BButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="event-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="event-form-section-title mb-0">Galería</div>
          <BButton size="sm" variant="outline-primary" @click="addGalleryImage">
            <i class="bx bx-plus me-1"></i>
            Agregar imagen
          </BButton>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-12">
            <label class="form-label">Texto introductorio de galería</label>
            <BFormInput v-model="form.gallery_intro" />
          </div>
        </div>
        <div class="event-repeat-list">
          <div v-for="(image, index) in form.gallery_images" :key="`gallery-${index}`" class="event-gallery-editor">
            <div class="row g-3 align-items-end">
              <div class="col-md-7">
                <label class="form-label">URL o ruta de imagen</label>
                <BFormInput v-model="image.url" placeholder="niceschool/assets/img/education/events-1.webp" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Texto alternativo</label>
                <BFormInput v-model="image.alt" />
              </div>
              <div class="col-md-1">
                <BButton size="sm" variant="outline-danger" class="event-repeat-delete" @click="removeGalleryImage(index)">
                  <i class="bx bx-trash"></i>
                </BButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="event-form-section">
        <div class="event-form-section-title">Inscripción</div>
        <div class="row g-3">
          <div class="col-md-12">
            <BFormCheckbox v-model="form.registration_enabled">Mostrar bloque de inscripción</BFormCheckbox>
          </div>
          <div class="col-md-4">
            <label class="form-label">Título del bloque</label>
            <BFormInput v-model="form.registration_title" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Texto del botón</label>
            <BFormInput v-model="form.registration_button_label" />
          </div>
          <div class="col-md-4">
            <label class="form-label">URL de inscripción</label>
            <BFormInput v-model="form.registration_url" placeholder="https://..." />
          </div>
        </div>
      </div>

      <div class="event-form-section">
        <div class="event-form-section-title">Organizador</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre</label>
            <BFormInput v-model="form.organizer_name" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Cargo o rol</label>
            <BFormInput v-model="form.organizer_position" />
          </div>
          <div class="col-md-12">
            <label class="form-label">Descripción</label>
            <BFormTextarea v-model="form.organizer_description" rows="2" maxlength="1000" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Correo</label>
            <BFormInput v-model="form.organizer_email" type="email" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Teléfono</label>
            <BFormInput v-model="form.organizer_phone" />
          </div>
          <div class="col-md-8">
            <label class="form-label">Imagen del organizador</label>
            <BFormInput v-model="form.organizer_image_url" placeholder="niceschool/assets/img/person/person-m-5.webp" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Texto alternativo</label>
            <BFormInput v-model="form.organizer_image_alt" />
          </div>
        </div>
      </div>

      <datalist id="event-categories">
        <option v-for="category in catalogs.categories" :key="category" :value="category" />
      </datalist>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="confirmSave">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
    </main>
  </Layout>
</template>

<style scoped>
.event-title-cell {
  max-width: 520px;
}

.site-entity-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 310px;
}

.event-title-icon {
  display: grid;
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  place-items: center;
  border: 1px solid #d5e5e8;
  border-radius: 12px;
  background: linear-gradient(145deg, #e7f2f3, #f6f9f5);
  color: #0b6c7c;
  font-size: 1.05rem;
}

.min-w-0 {
  min-width: 0;
}

.event-form-section {
  border: 1px solid #dce7ea;
  border-radius: 15px;
  padding: 1.05rem;
  margin-bottom: 1rem;
  background: #fbfdfd;
}

.event-form-section-title {
  color: #284b5b;
  font-size: 0.95rem;
  font-weight: 700;
  margin-bottom: 0.85rem;
}

.event-repeat-list {
  display: grid;
  gap: 0.75rem;
}

.event-repeat-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 42px;
  gap: 0.5rem;
  align-items: center;
}

.event-schedule-editor,
.event-gallery-editor {
  border: 1px solid #dfe9ec;
  border-radius: 13px;
  padding: 0.85rem;
  background: #f5f9fa;
}

.event-repeat-delete {
  width: 38px;
  height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

:deep(.event-editor .ck-editor__editable) {
  min-height: 260px;
}

:deep(.event-editor .ck-content) {
  font-size: 0.95rem;
  line-height: 1.6;
}
</style>
