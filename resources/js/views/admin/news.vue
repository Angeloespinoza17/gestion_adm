<script>
import axios from "axios";
import { Ckeditor } from "@ckeditor/ckeditor5-vue";
import { markRaw } from "vue";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import SiteAdminNavigation from "../../components/public-site/site-admin-navigation.vue";

const emptyTocItem = () => ({
  label: "",
  anchor: "",
});

const emptyIconCard = () => ({
  icon: "bi bi-lightbulb",
  title: "",
  description: "",
});

const emptyComparisonCard = () => ({
  icon: "bi bi-check-circle",
  title: "",
  items_text: "",
});

const emptyPrinciple = () => ({
  number: "",
  title: "",
  description: "",
});

const emptyForm = () => ({
  id: null,
  title: "",
  slug: "",
  excerpt: "",
  body: "",
  category: "",
  author_name: "",
  author_role: "",
  external_image_url: "",
  image_alt: "",
  header_image_url: "",
  author_image_url: "",
  author_image_alt: "",
  reading_minutes: "",
  comments_label: "",
  detail_categories: [""],
  toc_items: [emptyTocItem()],
  quote_text: "",
  quote_author: "",
  secondary_section_title: "",
  secondary_image_url: "",
  secondary_image_alt: "",
  secondary_image_caption: "",
  secondary_image_position: "right",
  feature_points: [emptyIconCard()],
  comparison_cards: [emptyComparisonCard()],
  key_principles: [emptyPrinciple()],
  info_box_icon: "bi bi-info-circle",
  info_box_title: "",
  info_box_text: "",
  future_trends: [emptyIconCard()],
  tags: [""],
  share_enabled: true,
  status: "draft",
  featured: false,
  sort_order: 0,
  published_at: "",
  remove_image: false,
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
      imageFile: null,
      imagePreview: "",
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
      return this.permissions.includes("__superadmin__") || this.permissions.includes("gestionar_noticias");
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
        { value: "", text: "Todas" },
        { value: "1", text: "Destacadas" },
        { value: "0", text: "No destacadas" },
      ];
    },
    previewImageUrl() {
      const value = this.imagePreview || this.form.external_image_url;
      if (!value) return "";
      if (value.startsWith("http://") || value.startsWith("https://") || value.startsWith("/") || value.startsWith("blob:")) {
        return value;
      }
      return `/${value}`;
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
        const response = await axios.get("/api/admin/news/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/admin/news", {
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
      this.imageFile = null;
      this.imagePreview = "";
      this.error = null;
      this.success = null;
      this.showModal = true;
      await this.ensureEditor();
      this.clearImageInput();
    },
    async openEdit(item) {
      this.form = {
        id: item.id,
        title: item.title || "",
        slug: item.slug || "",
        excerpt: item.excerpt || "",
        body: item.body || "",
        category: item.category || "",
        author_name: item.author_name || "",
        author_role: item.author_role || "",
        external_image_url: item.external_image_url || "",
        image_alt: item.image_alt || "",
        header_image_url: item.header_image_url || "",
        author_image_url: item.author_image_url || "",
        author_image_alt: item.author_image_alt || "",
        reading_minutes: item.reading_minutes || "",
        comments_label: item.comments_label || "",
        detail_categories: this.normalizeStringList(item.detail_categories),
        toc_items: this.normalizeTocItems(item.toc_items),
        quote_text: item.quote_text || "",
        quote_author: item.quote_author || "",
        secondary_section_title: item.secondary_section_title || "",
        secondary_image_url: item.secondary_image_url || "",
        secondary_image_alt: item.secondary_image_alt || "",
        secondary_image_caption: item.secondary_image_caption || "",
        secondary_image_position: item.secondary_image_position || "right",
        feature_points: this.normalizeIconCards(item.feature_points),
        comparison_cards: this.normalizeComparisonCards(item.comparison_cards),
        key_principles: this.normalizePrinciples(item.key_principles),
        info_box_icon: item.info_box_icon || "bi bi-info-circle",
        info_box_title: item.info_box_title || "",
        info_box_text: item.info_box_text || "",
        future_trends: this.normalizeIconCards(item.future_trends),
        tags: this.normalizeStringList(item.tags),
        share_enabled: item.share_enabled !== false,
        status: item.status || "draft",
        featured: Boolean(item.featured),
        sort_order: item.sort_order || 0,
        published_at: this.toDatetimeLocal(item.published_at),
        remove_image: false,
      };
      this.imageFile = null;
      this.imagePreview = item.image_url || "";
      this.error = null;
      this.success = null;
      this.showModal = true;
      await this.ensureEditor();
      this.clearImageInput();
    },
    onImage(event) {
      const [file] = event.target.files || [];
      this.imageFile = file || null;
      this.form.remove_image = false;

      if (!file) {
        return;
      }

      if (this.imagePreview && this.imagePreview.startsWith("blob:")) {
        URL.revokeObjectURL(this.imagePreview);
      }

      this.imagePreview = URL.createObjectURL(file);
    },
    clearImageInput() {
      this.$nextTick(() => {
        if (this.$refs.imageInput) {
          this.$refs.imageInput.value = "";
        }
      });
    },
    removeCurrentImage() {
      this.imageFile = null;
      this.imagePreview = "";
      this.form.external_image_url = "";
      this.form.remove_image = true;
      this.clearImageInput();
    },
    buildFormData() {
      const formData = new FormData();
      [
        "title",
        "slug",
        "excerpt",
        "body",
        "category",
        "author_name",
        "author_role",
        "external_image_url",
        "image_alt",
        "header_image_url",
        "author_image_url",
        "author_image_alt",
        "reading_minutes",
        "comments_label",
        "quote_text",
        "quote_author",
        "secondary_section_title",
        "secondary_image_url",
        "secondary_image_alt",
        "secondary_image_caption",
        "secondary_image_position",
        "info_box_icon",
        "info_box_title",
        "info_box_text",
        "status",
        "sort_order",
        "published_at",
      ].forEach((field) => formData.append(field, this.form[field] ?? ""));

      formData.append("featured", this.form.featured ? "1" : "0");
      formData.append("share_enabled", this.form.share_enabled ? "1" : "0");
      formData.append("remove_image", this.form.remove_image ? "1" : "0");
      this.appendJson(formData, "detail_categories", this.cleanStringList(this.form.detail_categories));
      this.appendJson(formData, "toc_items", this.cleanTocItems(this.form.toc_items));
      this.appendJson(formData, "feature_points", this.cleanIconCards(this.form.feature_points));
      this.appendJson(formData, "comparison_cards", this.cleanComparisonCards(this.form.comparison_cards));
      this.appendJson(formData, "key_principles", this.cleanPrinciples(this.form.key_principles));
      this.appendJson(formData, "future_trends", this.cleanIconCards(this.form.future_trends));
      this.appendJson(formData, "tags", this.cleanStringList(this.form.tags));

      if (this.imageFile) {
        formData.append("image", this.imageFile);
      }

      return formData;
    },
    appendJson(formData, field, value) {
      formData.append(field, JSON.stringify(value || []));
    },
    payloadFromItem(item) {
      return {
        title: item.title,
        slug: item.slug,
        excerpt: item.excerpt || "",
        body: item.body || "",
        category: item.category || "",
        author_name: item.author_name || "",
        author_role: item.author_role || "",
        external_image_url: item.external_image_url || "",
        image_alt: item.image_alt || "",
        header_image_url: item.header_image_url || "",
        author_image_url: item.author_image_url || "",
        author_image_alt: item.author_image_alt || "",
        reading_minutes: item.reading_minutes || "",
        comments_label: item.comments_label || "",
        detail_categories: this.cleanStringList(item.detail_categories || []),
        toc_items: this.cleanTocItems(item.toc_items || []),
        quote_text: item.quote_text || "",
        quote_author: item.quote_author || "",
        secondary_section_title: item.secondary_section_title || "",
        secondary_image_url: item.secondary_image_url || "",
        secondary_image_alt: item.secondary_image_alt || "",
        secondary_image_caption: item.secondary_image_caption || "",
        secondary_image_position: item.secondary_image_position || "right",
        feature_points: this.cleanIconCards(item.feature_points || []),
        comparison_cards: this.cleanComparisonCards(item.comparison_cards || []),
        key_principles: this.cleanPrinciples(item.key_principles || []),
        info_box_icon: item.info_box_icon || "",
        info_box_title: item.info_box_title || "",
        info_box_text: item.info_box_text || "",
        future_trends: this.cleanIconCards(item.future_trends || []),
        tags: this.cleanStringList(item.tags || []),
        share_enabled: item.share_enabled !== false,
        status: item.status || "draft",
        featured: Boolean(item.featured),
        sort_order: Number(item.sort_order || 0),
        published_at: item.published_at || "",
      };
    },
    async confirmSave() {
      const result = await Swal.fire({
        title: this.isEditing ? "Guardar cambios" : "Crear noticia",
        text: this.form.title || "La noticia será guardada.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: this.isEditing ? "Guardar cambios" : "Crear noticia",
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
        const payload = this.buildFormData();

        if (this.isEditing) {
          payload.append("_method", "PUT");
          const response = await axios.post(`/api/admin/news/${this.form.id}`, payload, {
            headers: { "Content-Type": "multipart/form-data" },
          });
          message = response.data.message || "Noticia actualizada.";
        } else {
          const response = await axios.post("/api/admin/news", payload, {
            headers: { "Content-Type": "multipart/form-data" },
          });
          message = response.data.message || "Noticia creada.";
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
      payload.published_at = item.status === "published" ? item.published_at : item.published_at || new Date().toISOString();

      try {
        const response = await axios.put(`/api/admin/news/${item.id}`, payload);
        this.success = response.data.message || "Estado actualizado.";
        await this.loadCatalogs();
        await this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async remove(item) {
      const result = await Swal.fire({
        title: "Eliminar noticia",
        text: item.title,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        const response = await axios.delete(`/api/admin/news/${item.id}`);
        this.success = response.data.message || "Noticia eliminada.";
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
    normalizeStringList(items) {
      const values = Array.isArray(items) ? items.map((item) => String(item || "")) : [];
      return values.length ? values : [""];
    },
    normalizeTocItems(items) {
      const values = Array.isArray(items)
        ? items.map((item) => ({
            label: item?.label || "",
            anchor: item?.anchor || "",
          }))
        : [];

      return values.length ? values : [emptyTocItem()];
    },
    normalizeIconCards(items) {
      const values = Array.isArray(items)
        ? items.map((item) => ({
            icon: item?.icon || "bi bi-lightbulb",
            title: item?.title || "",
            description: item?.description || "",
          }))
        : [];

      return values.length ? values : [emptyIconCard()];
    },
    normalizeComparisonCards(items) {
      const values = Array.isArray(items)
        ? items.map((item) => ({
            icon: item?.icon || "bi bi-check-circle",
            title: item?.title || "",
            items_text: Array.isArray(item?.items) ? item.items.join("\n") : item?.items || "",
          }))
        : [];

      return values.length ? values : [emptyComparisonCard()];
    },
    normalizePrinciples(items) {
      const values = Array.isArray(items)
        ? items.map((item) => ({
            number: item?.number || "",
            title: item?.title || "",
            description: item?.description || "",
          }))
        : [];

      return values.length ? values : [emptyPrinciple()];
    },
    cleanStringList(items) {
      return (Array.isArray(items) ? items : [])
        .map((item) => String(item || "").trim())
        .filter(Boolean);
    },
    cleanTocItems(items) {
      return (Array.isArray(items) ? items : [])
        .map((item) => ({
          label: String(item?.label || "").trim(),
          anchor: String(item?.anchor || "").trim(),
        }))
        .filter((item) => item.label);
    },
    cleanIconCards(items) {
      return (Array.isArray(items) ? items : [])
        .map((item) => ({
          icon: String(item?.icon || "").trim(),
          title: String(item?.title || "").trim(),
          description: String(item?.description || "").trim(),
        }))
        .filter((item) => item.icon || item.title || item.description);
    },
    cleanComparisonCards(items) {
      return (Array.isArray(items) ? items : [])
        .map((item) => ({
          icon: String(item?.icon || "").trim(),
          title: String(item?.title || "").trim(),
          items: Array.isArray(item?.items)
            ? item.items.map((value) => String(value || "").trim()).filter(Boolean)
            : String(item?.items_text || "")
                .split(/\r?\n/)
                .map((value) => value.trim())
                .filter(Boolean),
        }))
        .filter((item) => item.icon || item.title || item.items.length);
    },
    cleanPrinciples(items) {
      return (Array.isArray(items) ? items : [])
        .map((item) => ({
          number: String(item?.number || "").trim(),
          title: String(item?.title || "").trim(),
          description: String(item?.description || "").trim(),
        }))
        .filter((item) => item.number || item.title || item.description);
    },
    addListItem(field) {
      this.form[field].push("");
    },
    removeListItem(field, index) {
      this.form[field].splice(index, 1);
      if (!this.form[field].length) this.addListItem(field);
    },
    addTocItem() {
      this.form.toc_items.push(emptyTocItem());
    },
    removeTocItem(index) {
      this.form.toc_items.splice(index, 1);
      if (!this.form.toc_items.length) this.addTocItem();
    },
    addFeaturePoint() {
      this.form.feature_points.push(emptyIconCard());
    },
    removeFeaturePoint(index) {
      this.form.feature_points.splice(index, 1);
      if (!this.form.feature_points.length) this.addFeaturePoint();
    },
    addComparisonCard() {
      this.form.comparison_cards.push(emptyComparisonCard());
    },
    removeComparisonCard(index) {
      this.form.comparison_cards.splice(index, 1);
      if (!this.form.comparison_cards.length) this.addComparisonCard();
    },
    addPrinciple() {
      this.form.key_principles.push(emptyPrinciple());
    },
    removePrinciple(index) {
      this.form.key_principles.splice(index, 1);
      if (!this.form.key_principles.length) this.addPrinciple();
    },
    addFutureTrend() {
      this.form.future_trends.push(emptyIconCard());
    },
    removeFutureTrend(index) {
      this.form.future_trends.splice(index, 1);
      if (!this.form.future_trends.length) this.addFutureTrend();
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
      return item.public_url || `/noticias/${item.id}`;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "No se pudo gestionar la noticia."
      );
    },
  },
};
</script>

<template>
  <Layout>
    <main class="site-admin-page site-admin-page--news">
      <SiteAdminNavigation />

      <section class="site-admin-hero" aria-labelledby="news-admin-title">
        <div class="site-admin-hero__copy">
          <span class="site-admin-eyebrow"><i class="bx bx-news"></i> Contenido editorial</span>
          <h1 id="news-admin-title">Noticias</h1>
          <p>Administra las historias que aparecen en la portada y en la sección pública de noticias.</p>
          <BButton v-if="canManage" class="site-admin-primary-action" @click="openCreate">
            <i class="bx bx-plus"></i>
            Nueva noticia
          </BButton>
        </div>

        <div class="site-admin-metrics" aria-label="Resumen de noticias">
          <button type="button" :class="{ active: !activeFilterCount }" @click="resetFilters">
            <span>Total</span><strong>{{ formatNumber(catalogs.stats?.total) }}</strong><small>publicaciones</small>
          </button>
          <button type="button" :class="{ active: statusFilter === 'published' }" @click="applyStatusFilter('published')">
            <span>En línea</span><strong>{{ formatNumber(catalogs.stats?.published) }}</strong><small>publicadas</small>
          </button>
          <button type="button" :class="{ active: statusFilter === 'draft' }" @click="applyStatusFilter('draft')">
            <span>Pendientes</span><strong>{{ formatNumber(catalogs.stats?.draft) }}</strong><small>borradores</small>
          </button>
          <button type="button" :class="{ active: featuredFilter === '1' }" @click="applyFeaturedFilter('1')">
            <span>Portada</span><strong>{{ formatNumber(catalogs.stats?.featured) }}</strong><small>destacadas</small>
          </button>
          <article><span>Alcance</span><strong>{{ formatNumber(catalogs.stats?.views) }}</strong><small>visualizaciones</small></article>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="site-admin-alert">{{ error }}</BAlert>
      <BAlert v-if="success" variant="success" show class="site-admin-alert">{{ success }}</BAlert>

    <BCard class="site-admin-filter-card">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label">Buscar contenido</label>
          <div class="site-admin-search">
            <i class="bx bx-search"></i>
            <BFormInput v-model="search" placeholder="Título, resumen, categoría o autor" @keyup.enter="load" />
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
          <label class="form-label">Destacada</label>
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
        <div><span>Biblioteca editorial</span><strong>{{ pagination.total }} noticia(s)</strong></div>
        <p><i class="bx bx-info-circle"></i> La portada y el estado determinan su visibilidad pública.</p>
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
          { key: 'title', label: 'Noticia' },
          { key: 'status', label: 'Estado' },
          { key: 'published_at', label: 'Publicación' },
          { key: 'views_count', label: 'Vistas' },
          { key: 'featured', label: 'Portada' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando noticias..." compact />
        </template>
        <template #empty>
          <div class="site-admin-empty"><i class="bx bx-news"></i><span>No hay noticias para los filtros seleccionados.</span></div>
        </template>
        <template #cell(title)="{ item }">
          <div class="d-flex align-items-center gap-3 news-title-cell">
            <img
              v-if="item.image_url"
              :src="item.image_url"
              :alt="item.image_alt || item.title"
              class="news-thumb"
            />
            <div class="min-w-0">
              <div class="fw-semibold text-truncate">{{ item.title }}</div>
              <div class="text-muted small text-truncate">{{ item.category || "Sin categoría" }} · {{ item.slug }}</div>
              <div v-if="item.excerpt" class="text-muted small news-excerpt">{{ item.excerpt }}</div>
            </div>
          </div>
        </template>
        <template #cell(status)="{ item }">
          <span :class="['site-status-chip', `is-${item.status || 'draft'}`]">{{ statusLabel(item.status) }}</span>
        </template>
        <template #cell(published_at)="{ item }">
          <span class="small">{{ formatDate(item.published_at) }}</span>
        </template>
        <template #cell(views_count)="{ item }">
          <span class="fw-semibold">{{ formatNumber(item.views_count) }}</span>
        </template>
        <template #cell(featured)="{ item }">
          <span :class="['site-featured-chip', { active: item.featured }]"><i class="bx" :class="item.featured ? 'bxs-star' : 'bx-star'"></i>{{ item.featured ? "Destacada" : "Regular" }}</span>
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
        <div class="text-muted small">{{ pagination.total }} noticia(s)</div>
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

    <BModal v-model="showModal" :title="isEditing ? 'Editar noticia' : 'Nueva noticia'" size="xl" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="news-form-section">
        <div class="news-form-section-title">Información principal</div>
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
            <label class="form-label">Categoría principal</label>
            <BFormInput v-model="form.category" list="news-categories" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Autor</label>
            <BFormInput v-model="form.author_name" placeholder="Colegio Nuestra Señora del Carmen" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Rol del autor</label>
            <BFormInput v-model="form.author_role" placeholder="Equipo de Comunicaciones" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="form.status" :options="formStatusOptions" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Fecha de publicación</label>
            <BFormInput v-model="form.published_at" type="datetime-local" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Orden</label>
            <BFormInput v-model="form.sort_order" type="number" min="0" />
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <BFormCheckbox v-model="form.featured">Portada</BFormCheckbox>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="news-form-section-title">Imagen y cabecera</div>
        <div class="row g-3">
          <div class="col-lg-4">
            <label class="form-label">Imagen destacada</label>
            <input ref="imageInput" type="file" accept="image/*" class="form-control" @change="onImage" />
          </div>
          <div class="col-lg-5">
            <label class="form-label">URL de imagen destacada</label>
            <BFormInput v-model="form.external_image_url" placeholder="https://... o ruta pública" />
          </div>
          <div class="col-lg-3">
            <label class="form-label">Texto alternativo</label>
            <BFormInput v-model="form.image_alt" />
          </div>
          <div class="col-md-12">
            <label class="form-label">Imagen de cabecera del detalle</label>
            <BFormInput v-model="form.header_image_url" placeholder="niceschool/assets/img/education/showcase-1.webp" />
          </div>
          <div class="col-md-8">
            <label class="form-label">Imagen del autor</label>
            <BFormInput v-model="form.author_image_url" placeholder="niceschool/assets/img/person/person-m-6.webp" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Alt imagen autor</label>
            <BFormInput v-model="form.author_image_alt" />
          </div>
          <div v-if="previewImageUrl" class="col-md-5">
            <div class="news-preview">
              <img :src="previewImageUrl" :alt="form.image_alt || form.title" />
            </div>
            <BButton size="sm" variant="outline-danger" class="mt-2" @click="removeCurrentImage">
              Quitar imagen
            </BButton>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="news-form-section-title">Metadatos del detalle</div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Minutos de lectura</label>
            <BFormInput v-model="form.reading_minutes" type="number" min="1" placeholder="Se calcula si queda vacío" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Texto de comentarios</label>
            <BFormInput v-model="form.comments_label" placeholder="Sin comentarios" />
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <BFormCheckbox v-model="form.share_enabled">Mostrar botones de compartir</BFormCheckbox>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="news-form-section-title mb-0">Categorías adicionales</div>
          <BButton size="sm" variant="outline-primary" @click="addListItem('detail_categories')">
            <i class="bx bx-plus me-1"></i>
            Agregar
          </BButton>
        </div>
        <div class="news-repeat-list">
          <div v-for="(category, index) in form.detail_categories" :key="`detail-category-${index}`" class="news-repeat-row">
            <BFormInput v-model="form.detail_categories[index]" placeholder="Ej: Comunidad, Innovación, Pastoral" />
            <BButton size="sm" variant="outline-danger" class="news-repeat-delete" @click="removeListItem('detail_categories', index)">
              <i class="bx bx-trash"></i>
            </BButton>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="news-form-section-title mb-0">Índice lateral</div>
          <BButton size="sm" variant="outline-primary" @click="addTocItem">
            <i class="bx bx-plus me-1"></i>
            Agregar
          </BButton>
        </div>
        <div class="news-repeat-list">
          <div v-for="(item, index) in form.toc_items" :key="`toc-${index}`" class="news-card-editor">
            <div class="row g-3 align-items-end">
              <div class="col-md-5">
                <label class="form-label">Texto</label>
                <BFormInput v-model="item.label" placeholder="Introducción" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Ancla</label>
                <BFormInput v-model="item.anchor" placeholder="introduccion" />
              </div>
              <div class="col-md-1">
                <BButton size="sm" variant="outline-danger" class="news-repeat-delete" @click="removeTocItem(index)">
                  <i class="bx bx-trash"></i>
                </BButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="news-form-section-title">Contenido principal</div>
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label">Resumen / bajada</label>
            <BFormTextarea v-model="form.excerpt" rows="3" maxlength="700" />
          </div>
          <div class="col-md-12">
            <label class="form-label">Contenido</label>
            <div class="form-ckeditor news-editor">
              <div v-if="editorLoading" class="editor-loading"><span class="spinner-border spinner-border-sm"></span> Cargando editor…</div>
              <Ckeditor v-else-if="editor" v-model="form.body" :editor="editor" :config="editorConfig" />
            </div>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="news-form-section-title">Cita destacada</div>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Texto de cita</label>
            <BFormTextarea v-model="form.quote_text" rows="2" maxlength="1000" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Autor de cita</label>
            <BFormInput v-model="form.quote_author" />
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="news-form-section-title">Imagen o bloque secundario</div>
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label">Título de sección</label>
            <BFormInput v-model="form.secondary_section_title" />
          </div>
          <div class="col-md-5">
            <label class="form-label">URL de imagen</label>
            <BFormInput v-model="form.secondary_image_url" placeholder="niceschool/assets/img/blog/blog-hero-2.webp" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Posición</label>
            <BFormSelect
              v-model="form.secondary_image_position"
              :options="[
                { value: 'right', text: 'Derecha' },
                { value: 'left', text: 'Izquierda' },
                { value: 'full', text: 'Completa' },
              ]"
            />
          </div>
          <div class="col-md-6">
            <label class="form-label">Alt imagen</label>
            <BFormInput v-model="form.secondary_image_alt" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Pie de foto</label>
            <BFormInput v-model="form.secondary_image_caption" />
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="news-form-section-title mb-0">Puntos destacados con ícono</div>
          <BButton size="sm" variant="outline-primary" @click="addFeaturePoint">
            <i class="bx bx-plus me-1"></i>
            Agregar punto
          </BButton>
        </div>
        <div class="news-repeat-list">
          <div v-for="(point, index) in form.feature_points" :key="`feature-${index}`" class="news-card-editor">
            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label class="form-label">Ícono Bootstrap</label>
                <BFormInput v-model="point.icon" placeholder="bi bi-lightbulb" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Título</label>
                <BFormInput v-model="point.title" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Descripción</label>
                <BFormInput v-model="point.description" />
              </div>
              <div class="col-md-1">
                <BButton size="sm" variant="outline-danger" class="news-repeat-delete" @click="removeFeaturePoint(index)">
                  <i class="bx bx-trash"></i>
                </BButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="news-form-section-title mb-0">Comparativas</div>
          <BButton size="sm" variant="outline-primary" @click="addComparisonCard">
            <i class="bx bx-plus me-1"></i>
            Agregar tarjeta
          </BButton>
        </div>
        <div class="news-repeat-list">
          <div v-for="(card, index) in form.comparison_cards" :key="`comparison-${index}`" class="news-card-editor">
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Ícono Bootstrap</label>
                <BFormInput v-model="card.icon" placeholder="bi bi-check-circle" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Título</label>
                <BFormInput v-model="card.title" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Elementos</label>
                <BFormTextarea v-model="card.items_text" rows="3" placeholder="Un elemento por línea" />
              </div>
              <div class="col-md-1 d-flex align-items-end">
                <BButton size="sm" variant="outline-danger" class="news-repeat-delete" @click="removeComparisonCard(index)">
                  <i class="bx bx-trash"></i>
                </BButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="news-form-section-title mb-0">Principios o claves</div>
          <BButton size="sm" variant="outline-primary" @click="addPrinciple">
            <i class="bx bx-plus me-1"></i>
            Agregar clave
          </BButton>
        </div>
        <div class="news-repeat-list">
          <div v-for="(principle, index) in form.key_principles" :key="`principle-${index}`" class="news-card-editor">
            <div class="row g-3 align-items-end">
              <div class="col-md-2">
                <label class="form-label">Número</label>
                <BFormInput v-model="principle.number" placeholder="01" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Título</label>
                <BFormInput v-model="principle.title" />
              </div>
              <div class="col-md-5">
                <label class="form-label">Descripción</label>
                <BFormInput v-model="principle.description" />
              </div>
              <div class="col-md-1">
                <BButton size="sm" variant="outline-danger" class="news-repeat-delete" @click="removePrinciple(index)">
                  <i class="bx bx-trash"></i>
                </BButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="news-form-section-title">Caja informativa</div>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Ícono Bootstrap</label>
            <BFormInput v-model="form.info_box_icon" placeholder="bi bi-info-circle" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Título</label>
            <BFormInput v-model="form.info_box_title" />
          </div>
          <div class="col-md-5">
            <label class="form-label">Texto</label>
            <BFormTextarea v-model="form.info_box_text" rows="2" maxlength="1000" />
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="news-form-section-title mb-0">Tendencias o próximos pasos</div>
          <BButton size="sm" variant="outline-primary" @click="addFutureTrend">
            <i class="bx bx-plus me-1"></i>
            Agregar bloque
          </BButton>
        </div>
        <div class="news-repeat-list">
          <div v-for="(trend, index) in form.future_trends" :key="`trend-${index}`" class="news-card-editor">
            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label class="form-label">Ícono Bootstrap</label>
                <BFormInput v-model="trend.icon" placeholder="bi bi-phone" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Título</label>
                <BFormInput v-model="trend.title" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Descripción</label>
                <BFormInput v-model="trend.description" />
              </div>
              <div class="col-md-1">
                <BButton size="sm" variant="outline-danger" class="news-repeat-delete" @click="removeFutureTrend(index)">
                  <i class="bx bx-trash"></i>
                </BButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="news-form-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <div class="news-form-section-title mb-0">Etiquetas</div>
          <BButton size="sm" variant="outline-primary" @click="addListItem('tags')">
            <i class="bx bx-plus me-1"></i>
            Agregar
          </BButton>
        </div>
        <div class="news-repeat-list">
          <div v-for="(tag, index) in form.tags" :key="`tag-${index}`" class="news-repeat-row">
            <BFormInput v-model="form.tags[index]" placeholder="Ej: Vida escolar, Comunicaciones, Pastoral" />
            <BButton size="sm" variant="outline-danger" class="news-repeat-delete" @click="removeListItem('tags', index)">
              <i class="bx bx-trash"></i>
            </BButton>
          </div>
        </div>
      </div>

      <datalist id="news-categories">
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
.news-title-cell {
  max-width: 560px;
}

.news-thumb {
  border-radius: 6px;
  height: 54px;
  object-fit: cover;
  width: 72px;
}

.news-excerpt {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.news-preview {
  background: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  overflow: hidden;
}

.news-preview img {
  display: block;
  max-height: 240px;
  object-fit: cover;
  width: 100%;
}

.news-form-section {
  background: #fff;
  border: 1px solid #e9edf4;
  border-radius: 8px;
  margin-bottom: 1rem;
  padding: 1rem;
}

.news-form-section-title {
  color: #2f3a4a;
  font-size: 0.95rem;
  font-weight: 700;
  margin-bottom: 0.85rem;
}

.news-repeat-list {
  display: grid;
  gap: 0.75rem;
}

.news-repeat-row {
  align-items: center;
  display: grid;
  gap: 0.5rem;
  grid-template-columns: minmax(0, 1fr) 42px;
}

.news-card-editor {
  background: #f8fafc;
  border: 1px solid #edf1f7;
  border-radius: 8px;
  padding: 0.85rem;
}

.news-repeat-delete {
  align-items: center;
  display: inline-flex;
  height: 38px;
  justify-content: center;
  width: 38px;
}

.min-w-0 {
  min-width: 0;
}

:deep(.news-editor .ck-editor__editable) {
  min-height: 280px;
}

:deep(.news-editor .ck-content) {
  font-size: 0.95rem;
  line-height: 1.6;
}

.site-admin-page {
  --site-navy: #062f43;
  --site-blue: #0b6678;
  --site-green: #789978;
  --site-gold: #efc77f;
  --site-ink: #203f50;
  --site-muted: #718690;
  min-height: 100vh;
  padding: 1rem 1rem 2.5rem;
  background: linear-gradient(180deg, #edf4f5 0, #f8fafb 330px, #fbfcfc 100%);
  color: var(--site-ink);
}

.site-admin-hero {
  position: relative;
  display: grid;
  grid-template-columns: minmax(330px, 0.82fr) minmax(0, 1.18fr);
  align-items: center;
  gap: 1.35rem;
  overflow: hidden;
  margin-bottom: 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 26px;
  background: radial-gradient(circle at 88% -30%, rgba(128, 162, 123, 0.7), transparent 43%), linear-gradient(116deg, #062f43, #0b6073 70%, #456f6a);
  padding: clamp(1.3rem, 2.3vw, 1.8rem);
  color: #fff;
  box-shadow: 0 22px 48px rgba(7, 52, 70, 0.16);
}

.site-admin-hero::after {
  position: absolute;
  right: -70px;
  bottom: -125px;
  width: 270px;
  height: 270px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  content: "";
  pointer-events: none;
}

.site-admin-hero__copy,
.site-admin-metrics {
  position: relative;
  z-index: 1;
}

.site-admin-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--site-gold);
  font-size: 0.62rem;
  font-weight: 850;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.site-admin-hero h1 {
  margin: 0.42rem 0 0.25rem;
  color: #fff;
  font-size: clamp(2rem, 3.2vw, 2.8rem);
  font-weight: 850;
  letter-spacing: -0.045em;
  line-height: 1;
}

.site-admin-hero p {
  max-width: 620px;
  margin: 0;
  color: rgba(255, 255, 255, 0.72);
  font-size: 0.73rem;
  line-height: 1.6;
}

.site-admin-primary-action {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  min-height: 40px;
  margin-top: 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.24);
  border-radius: 999px;
  background: #fff;
  padding: 0.48rem 0.85rem;
  color: #075d70;
  font-size: 0.65rem;
  font-weight: 850;
  box-shadow: 0 10px 24px rgba(1, 31, 43, 0.2);
}

.site-admin-primary-action:hover,
.site-admin-primary-action:focus {
  border-color: #fff;
  background: #f5fbfb;
  color: #064f60;
}

.site-admin-metrics {
  display: grid;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 20px;
  background: rgba(3, 39, 54, 0.3);
  backdrop-filter: blur(12px);
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.site-admin-metrics > * {
  display: grid;
  min-width: 0;
  min-height: 104px;
  align-content: center;
  border: 0;
  border-right: 1px solid rgba(255, 255, 255, 0.11);
  background: transparent;
  padding: 0.8rem 0.62rem;
  color: #fff;
  text-align: left;
  transition: background-color 0.18s ease;
}

.site-admin-metrics > *:last-child {
  border-right: 0;
}

.site-admin-metrics button:hover,
.site-admin-metrics button.active {
  background: rgba(255, 255, 255, 0.11);
}

.site-admin-metrics button.active {
  box-shadow: inset 0 -3px 0 var(--site-gold);
}

.site-admin-metrics span {
  color: rgba(255, 255, 255, 0.61);
  font-size: 0.51rem;
  font-weight: 850;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.site-admin-metrics strong {
  margin: 0.14rem 0 0.08rem;
  color: #fff;
  font-size: 1.35rem;
  line-height: 1;
}

.site-admin-metrics small {
  overflow: hidden;
  color: rgba(255, 255, 255, 0.58);
  font-size: 0.52rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.site-admin-alert {
  margin-bottom: 0.9rem;
  border-radius: 14px;
}

.site-admin-filter-card,
.site-admin-table-card {
  margin-bottom: 0.9rem;
  overflow: hidden;
  border: 1px solid #dce7ea;
  border-radius: 18px;
  box-shadow: 0 12px 30px rgba(18, 58, 73, 0.055);
}

.site-admin-filter-card :deep(.card-body) {
  padding: 0.9rem 1rem;
}

.site-admin-filter-card .form-label {
  margin-bottom: 0.3rem;
  color: #667d88;
  font-size: 0.58rem;
  font-weight: 850;
  letter-spacing: 0.045em;
  text-transform: uppercase;
}

.site-admin-filter-card :deep(.form-control),
.site-admin-filter-card :deep(.form-select) {
  min-height: 40px;
  border-color: #d5e2e5;
  border-radius: 11px;
  background-color: #fbfcfd;
  color: #2c4e5e;
  font-size: 0.7rem;
}

.site-admin-search {
  position: relative;
}

.site-admin-search > i {
  position: absolute;
  z-index: 2;
  top: 50%;
  left: 0.72rem;
  color: #77909a;
  font-size: 1rem;
  transform: translateY(-50%);
  pointer-events: none;
}

.site-admin-search :deep(input) {
  padding-left: 2.25rem;
}

.site-admin-filter-actions .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.32rem;
  min-height: 40px;
  border-radius: 11px;
  font-size: 0.64rem;
  font-weight: 800;
}

.site-admin-filter-button {
  border-color: transparent;
  background: linear-gradient(115deg, #07546a, #0c7280);
  color: #fff;
}

.site-admin-clear-button span {
  display: inline-grid;
  min-width: 19px;
  height: 19px;
  place-items: center;
  border-radius: 999px;
  background: #e6eef0;
  color: #46636f;
  font-size: 0.54rem;
}

.site-admin-table-card :deep(.card-body) {
  padding: 0;
}

.site-admin-table-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 0.8rem;
  border-bottom: 1px solid #e5edef;
  padding: 0.9rem 1rem;
}

.site-admin-table-heading div {
  display: grid;
}

.site-admin-table-heading span {
  color: #84959e;
  font-size: 0.53rem;
  font-weight: 850;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.site-admin-table-heading strong {
  color: #284a5a;
  font-size: 0.8rem;
}

.site-admin-table-heading p {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  margin: 0;
  color: #7b8e98;
  font-size: 0.62rem;
}

:deep(.site-admin-table th) {
  border-bottom: 1px solid #e2eaed;
  background: #f5f8f9;
  padding: 0.72rem 0.75rem;
  color: #738893;
  font-size: 0.56rem;
  font-weight: 850;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  white-space: nowrap;
}

:deep(.site-admin-table td) {
  border-bottom: 1px solid #e9eff1;
  padding: 0.82rem 0.75rem;
  vertical-align: middle;
}

:deep(.site-admin-table tbody tr:hover) {
  background: #f9fcfc;
}

.news-thumb {
  width: 76px;
  height: 58px;
  border: 3px solid #edf3f4;
  border-radius: 12px;
  box-shadow: 0 6px 14px rgba(20, 63, 79, 0.08);
}

.site-status-chip,
.site-featured-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.24rem;
  min-height: 28px;
  border-radius: 999px;
  padding: 0.38rem 0.58rem;
  font-size: 0.58rem;
  font-weight: 850;
  white-space: nowrap;
}

.site-status-chip.is-published {
  background: #e4f5ef;
  color: #197c64;
}

.site-status-chip.is-draft {
  background: #eef2f4;
  color: #647781;
}

.site-status-chip.is-archived {
  background: #fff1df;
  color: #9a671f;
}

.site-featured-chip {
  border: 1px solid #e0e8ea;
  background: #f7f9fa;
  color: #788a93;
}

.site-featured-chip.active {
  border-color: #eed9a8;
  background: #fff5dd;
  color: #936516;
}

.site-row-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.3rem;
  min-width: 245px;
}

.site-row-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  min-height: 36px;
  border-radius: 10px;
  padding: 0.38rem 0.52rem;
  font-size: 0.58rem;
  font-weight: 800;
}

.site-row-action i {
  font-size: 0.88rem;
}

.site-row-action.is-danger:hover {
  background: #fff0f1;
}

.site-admin-empty {
  display: grid;
  min-height: 180px;
  place-items: center;
  align-content: center;
  gap: 0.45rem;
  color: #7b8e98;
  font-size: 0.68rem;
}

.site-admin-empty i {
  color: #98afb7;
  font-size: 2rem;
}

.site-admin-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border-top: 1px solid #e5edef;
  padding: 0.85rem 1rem;
}

.site-admin-pagination .btn {
  min-height: 35px;
  border-radius: 10px;
  font-size: 0.6rem;
  font-weight: 750;
}

.news-form-section {
  border-color: #dce7ea;
  border-radius: 15px;
  background: #fbfdfd;
  padding: 1.05rem;
}

.news-form-section-title {
  color: #284b5b;
}

.news-card-editor {
  border-color: #dfe9ec;
  border-radius: 13px;
  background: #f5f9fa;
}

@media (max-width: 1499.98px) {
  .site-row-actions {
    min-width: 168px;
  }

  .site-row-action {
    width: 38px;
    min-width: 38px;
    padding: 0;
  }

  .site-row-action span {
    display: none;
  }
}

@media (max-width: 1199.98px) {
  .site-admin-hero {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 767.98px) {
  .site-admin-page {
    padding: 0.7rem 0.6rem 1.8rem;
  }

  .site-admin-hero {
    border-radius: 21px;
    padding: 1.2rem;
  }

  .site-admin-metrics {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .site-admin-metrics > * {
    min-height: 88px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.11);
  }

  .site-admin-primary-action,
  .site-admin-filter-actions,
  .site-admin-filter-actions .btn {
    width: 100%;
  }

  .site-admin-table-heading,
  .site-admin-pagination {
    align-items: flex-start;
    flex-direction: column;
  }

  .site-admin-table-heading p {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .site-admin-page * {
    transition: none !important;
  }
}
</style>
