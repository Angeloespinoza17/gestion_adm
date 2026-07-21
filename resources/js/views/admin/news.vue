<script>
import axios from "axios";
import { Ckeditor } from "@ckeditor/ckeditor5-vue";
import ClassicEditor from "@ckeditor/ckeditor5-build-classic";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

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
  components: { Ckeditor, Layout, LoadingState },
  data() {
    return {
      editor: ClassicEditor,
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
    openCreate() {
      this.form = emptyForm();
      this.imageFile = null;
      this.imagePreview = "";
      this.error = null;
      this.success = null;
      this.showModal = true;
      this.clearImageInput();
    },
    openEdit(item) {
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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Noticias del sitio web</h4>
        <div class="text-muted">Gestión de las publicaciones visibles en /noticias y en la portada.</div>
      </div>
      <BButton variant="primary" @click="openCreate">
        <i class="bx bx-plus me-1"></i>
        Nueva noticia
      </BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <div class="row g-3 mb-3">
      <div class="col-md-6 col-xl">
        <BCard class="h-100">
          <div class="text-muted small">Total</div>
          <div class="fs-4 fw-semibold">{{ formatNumber(catalogs.stats?.total) }}</div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl">
        <BCard class="h-100">
          <div class="text-muted small">Publicadas</div>
          <div class="fs-4 fw-semibold text-success">{{ formatNumber(catalogs.stats?.published) }}</div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl">
        <BCard class="h-100">
          <div class="text-muted small">Borradores</div>
          <div class="fs-4 fw-semibold">{{ formatNumber(catalogs.stats?.draft) }}</div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl">
        <BCard class="h-100">
          <div class="text-muted small">Destacadas</div>
          <div class="fs-4 fw-semibold text-primary">{{ formatNumber(catalogs.stats?.featured) }}</div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl">
        <BCard class="h-100">
          <div class="text-muted small">Visualizaciones</div>
          <div class="fs-4 fw-semibold text-info">{{ formatNumber(catalogs.stats?.views) }}</div>
        </BCard>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="search" placeholder="Título, resumen, categoría o autor" @keyup.enter="load" />
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
        <div class="col-md-3 col-lg-2 d-flex gap-2">
          <BButton variant="secondary" @click="load()">Filtrar</BButton>
          <BButton variant="light" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard>
      <BTable
        :items="items"
        :busy="loading"
        responsive
        small
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
          <BBadge :variant="statusVariant(item.status)">{{ statusLabel(item.status) }}</BBadge>
        </template>
        <template #cell(published_at)="{ item }">
          <span class="small">{{ formatDate(item.published_at) }}</span>
        </template>
        <template #cell(views_count)="{ item }">
          <span class="fw-semibold">{{ formatNumber(item.views_count) }}</span>
        </template>
        <template #cell(featured)="{ item }">
          <BBadge :variant="item.featured ? 'primary' : 'secondary'">{{ item.featured ? "Sí" : "No" }}</BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton v-if="item.status === 'published'" size="sm" variant="outline-secondary" :href="publicUrl(item)" target="_blank">Ver</BButton>
            <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton
              size="sm"
              :variant="item.status === 'published' ? 'outline-warning' : 'outline-success'"
              @click="togglePublished(item)"
            >
              {{ item.status === "published" ? "Archivar" : "Publicar" }}
            </BButton>
            <BButton size="sm" variant="outline-danger" @click="remove(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <div class="text-muted small">{{ pagination.total }} noticia(s)</div>
        <div class="d-flex align-items-center gap-2">
          <BButton size="sm" variant="light" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">
            Anterior
          </BButton>
          <span class="small">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
          <BButton
            size="sm"
            variant="light"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="load(pagination.current_page + 1)"
          >
            Siguiente
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
              <Ckeditor v-model="form.body" :editor="editor" :config="editorConfig" />
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
</style>
