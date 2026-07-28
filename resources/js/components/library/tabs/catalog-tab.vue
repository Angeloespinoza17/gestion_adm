<script>
import axios from "axios";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmLibraryAction,
  confirmLibraryCancel,
  downloadPdfReport,
  formatLibraryError,
  showLibrarySuccess,
} from "../module-utils";

const emptyForm = () => ({
  id: null,
  material_type: "libro",
  title: "",
  subtitle: "",
  main_author: "",
  secondary_authors_text: "",
  publisher: "",
  publication_year: "",
  isbn: "",
  biblioteca_categoria_id: null,
  biblioteca_subcategoria_id: null,
  subcategory: "",
  genre: "",
  recommended_level: "",
  recommended_course_section_id: null,
  language: "Español",
  page_count: "",
  description: "",
  keywords_text: "",
  cover_image_url: "",
  internal_code: "",
  barcode: "",
  biblioteca_ubicacion_id: null,
  physical_location: "",
  shelf: "",
  section: "",
  general_status: "disponible",
  observations: "",
  quantity: 1,
  additional_quantity: 0,
  open_library_work_key: null,
  open_library_edition_key: null,
  open_library_cover_id: null,
  source_metadata: null,
});

export default {
  components: { LibraryHelpButton, LibraryStatusBadge, LoadingState },
  props: {
    catalogs: { type: Object, required: true },
  },
  data() {
    return {
      loading: false,
      saving: false,
      detailsLoading: false,
      error: null,
      items: [],
      failedCovers: {},
      pagination: { current_page: 1, total: 0, per_page: 12 },
      viewMode: "cards",
      filters: {
        search: "",
        material_type: null,
        biblioteca_categoria_id: null,
        recommended_course_section_id: null,
        general_status: null,
        available_only: false,
      },
      showModal: false,
      showOpenLibrary: false,
      openLibraryQuery: "",
      openLibraryLoading: false,
      openLibraryResults: [],
      openLibraryError: null,
      showDetailsModal: false,
      selectedItem: null,
      form: emptyForm(),
    };
  },
  computed: {
    categoryOptions() {
      return [{ value: null, text: "Todas las categorías" }].concat(
        (this.catalogs.categories || []).map((item) => ({
          value: item.id,
          text: `${item.name} · ${item.code}`,
        }))
      );
    },
    formCategoryOptions() {
      return [{ value: null, text: "Sin categoría" }].concat(
        (this.catalogs.categories || []).map((item) => ({
          value: item.id,
          text: `${item.name} · ${item.code}`,
        }))
      );
    },
    subcategoryOptions() {
      if (!this.form.biblioteca_categoria_id) {
        return [{ value: null, text: "Selecciona primero una categoría" }];
      }

      const options = (this.catalogs.subcategories || [])
        .filter(
          (item) => Number(item.biblioteca_categoria_id)
            === Number(this.form.biblioteca_categoria_id)
        )
        .map((item) => ({
          value: item.id,
          text: item.name,
        }));

      return [{ value: null, text: "Sin subcategoría" }].concat(options);
    },
    locationOptions() {
      return [{ value: null, text: "Sin ubicación definida" }].concat(
        (this.catalogs.locations || []).map((item) => ({
          value: item.id,
          text: `${item.code} · ${item.name}`,
        }))
      );
    },
    resultRange() {
      if (!this.pagination.total || !this.items.length) return "Sin resultados";
      const start = (this.pagination.current_page - 1) * this.pagination.per_page + 1;
      const end = Math.min(start + this.items.length - 1, this.pagination.total);
      return `${start}–${end} de ${this.pagination.total}`;
    },
    hasActiveFilters() {
      return Boolean(
        this.filters.search ||
        this.filters.material_type ||
        this.filters.biblioteca_categoria_id ||
        this.filters.general_status ||
        this.filters.available_only
      );
    },
  },
  mounted() {
    const savedView = window.localStorage.getItem("biblioteca-catalog-view");
    if (["cards", "table"].includes(savedView)) this.viewMode = savedView;
    this.load();
    this.consumeRouteFocus();
  },
  methods: {
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/obras", {
          params: { page, ...this.filters, available_only: this.filters.available_only ? 1 : "" },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo cargar el catálogo bibliográfico.");
      } finally {
        this.loading = false;
      }
    },
    async consumeRouteFocus() {
      if (this.$route.query.obra) await this.openEditById(this.$route.query.obra);
    },
    setViewMode(mode) {
      this.viewMode = mode;
      window.localStorage.setItem("biblioteca-catalog-view", mode);
    },
    resetFilters() {
      this.filters = {
        search: "",
        material_type: null,
        biblioteca_categoria_id: null,
        recommended_course_section_id: null,
        general_status: null,
        available_only: false,
      };
      this.load(1);
    },
    stockPercent(item) {
      if (!Number(item.total_copies)) return 0;
      return Math.min(100, Number(item.available_copies || 0) / Number(item.total_copies) * 100);
    },
    coverAvailable(item) {
      return Boolean(item?.cover_image_url && !this.failedCovers[item.id]);
    },
    markCoverFailed(item) {
      this.failedCovers = { ...this.failedCovers, [item.id]: true };
    },
    async openDetails(item) {
      this.selectedItem = item;
      this.showDetailsModal = true;
      this.detailsLoading = true;
      try {
        const response = await axios.get(`/api/biblioteca/obras/${item.id}`);
        this.selectedItem = response.data.data || item;
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo cargar la ficha completa del libro.");
      } finally {
        this.detailsLoading = false;
      }
    },
    closeDetails() {
      this.showDetailsModal = false;
      this.selectedItem = null;
    },
    editFromDetails() {
      const item = this.selectedItem;
      this.closeDetails();
      if (item) this.openEdit(item);
    },
    deleteFromDetails() {
      const item = this.selectedItem;
      this.closeDetails();
      if (item) this.destroy(item);
    },
    buildPayload() {
      return {
        material_type: this.form.material_type,
        title: this.form.title,
        subtitle: this.form.subtitle || null,
        main_author: this.form.main_author,
        secondary_authors: this.form.secondary_authors_text.split(",").map((item) => item.trim()).filter(Boolean),
        publisher: this.form.publisher || null,
        publication_year: this.form.publication_year || null,
        isbn: this.form.isbn || null,
        biblioteca_categoria_id: this.form.biblioteca_categoria_id || null,
        biblioteca_subcategoria_id: this.form.biblioteca_subcategoria_id || null,
        category: null,
        subcategory: this.form.subcategory || null,
        genre: this.form.genre || null,
        recommended_level: this.form.recommended_level || null,
        recommended_course_section_id: this.form.recommended_course_section_id || null,
        language: this.form.language || null,
        page_count: this.form.page_count || null,
        description: this.form.description || null,
        keywords: this.form.keywords_text.split(",").map((item) => item.trim()).filter(Boolean),
        cover_image_url: this.form.cover_image_url || null,
        internal_code: this.form.internal_code || null,
        barcode: this.form.barcode || null,
        biblioteca_ubicacion_id: this.form.biblioteca_ubicacion_id || null,
        physical_location: this.form.physical_location || null,
        shelf: this.form.shelf || null,
        section: this.form.section || null,
        general_status: this.form.general_status,
        observations: this.form.observations || null,
        quantity: Number(this.form.quantity || 0),
        additional_quantity: Number(this.form.additional_quantity || 0),
        open_library_work_key: this.form.open_library_work_key || null,
        open_library_edition_key: this.form.open_library_edition_key || null,
        open_library_cover_id: this.form.open_library_cover_id || null,
        source_metadata: this.form.source_metadata || null,
      };
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    changeFormCategory(categoryId) {
      this.form.biblioteca_categoria_id = categoryId || null;
      const currentSubcategory = (this.catalogs.subcategories || []).find(
        (item) => Number(item.id) === Number(this.form.biblioteca_subcategoria_id)
      );

      if (
        !currentSubcategory
        || Number(currentSubcategory.biblioteca_categoria_id) !== Number(categoryId)
      ) {
        this.form.biblioteca_subcategoria_id = null;
        this.form.subcategory = "";
      }
    },
    changeFormSubcategory(subcategoryId) {
      this.form.biblioteca_subcategoria_id = subcategoryId || null;
      this.form.subcategory = (this.catalogs.subcategories || []).find(
        (item) => Number(item.id) === Number(subcategoryId)
      )?.name || "";
    },
    async openEdit(item) {
      await this.openEditById(item.id);
    },
    async openEditById(id) {
      const response = await axios.get(`/api/biblioteca/obras/${id}`);
      const obra = response.data.data;
      const normalizedSubcategory = (this.catalogs.subcategories || []).find(
        (item) => Number(item.id) === Number(obra.biblioteca_subcategoria_id)
          || (
            Number(item.biblioteca_categoria_id) === Number(obra.biblioteca_categoria_id)
            && item.name === obra.subcategory
          )
      );
      this.form = {
        ...emptyForm(),
        id: obra.id,
        material_type: obra.material_type,
        title: obra.title,
        subtitle: obra.subtitle || "",
        main_author: obra.main_author,
        secondary_authors_text: (obra.secondary_authors || []).join(", "),
        publisher: obra.publisher || "",
        publication_year: obra.publication_year || "",
        isbn: obra.isbn || "",
        biblioteca_categoria_id: obra.biblioteca_categoria_id || null,
        biblioteca_subcategoria_id: obra.biblioteca_subcategoria_id
          || normalizedSubcategory?.id
          || null,
        subcategory: obra.subcategory || "",
        genre: obra.genre || "",
        recommended_level: obra.recommended_level || "",
        recommended_course_section_id: obra.recommended_course_section_id || null,
        language: obra.language || "",
        page_count: obra.page_count || "",
        description: obra.description || "",
        keywords_text: (obra.keywords || []).join(", "),
        cover_image_url: obra.cover_image_url || "",
        internal_code: obra.internal_code,
        barcode: obra.barcode || "",
        biblioteca_ubicacion_id: obra.biblioteca_ubicacion_id || null,
        physical_location: obra.physical_location || "",
        shelf: obra.shelf || "",
        section: obra.section || "",
        general_status: obra.general_status,
        observations: obra.observations || "",
        open_library_work_key: obra.open_library_work_key,
        open_library_edition_key: obra.open_library_edition_key,
        open_library_cover_id: obra.open_library_cover_id,
        source_metadata: obra.source_metadata,
      };
      this.showModal = true;
    },
    openMetadataSearch() {
      this.openLibraryQuery = this.form.isbn || this.form.title || "";
      this.openLibraryResults = [];
      this.openLibraryError = null;
      this.showOpenLibrary = true;
      if (this.openLibraryQuery.length >= 3) this.searchOpenLibrary();
    },
    async searchOpenLibrary() {
      this.openLibraryLoading = true;
      this.openLibraryError = null;
      try {
        const response = await axios.get("/api/biblioteca/open-library", {
          params: { q: this.openLibraryQuery, limit: 10 },
        });
        this.openLibraryResults = response.data.data || [];
        if (!this.openLibraryResults.length) {
          this.openLibraryError = "No se encontraron coincidencias. Prueba con ISBN, título o autor.";
        }
      } catch (error) {
        this.openLibraryError = formatLibraryError(error, "No se pudo consultar Open Library.");
      } finally {
        this.openLibraryLoading = false;
      }
    },
    applyOpenLibrary(book) {
      Object.assign(this.form, {
        title: book.title || this.form.title,
        subtitle: book.subtitle || "",
        main_author: book.main_author || this.form.main_author,
        secondary_authors_text: (book.secondary_authors || []).join(", "),
        publisher: book.publisher || "",
        publication_year: book.publication_year || "",
        isbn: book.isbn || this.form.isbn,
        language: book.language || this.form.language,
        page_count: book.page_count || "",
        keywords_text: (book.keywords || []).join(", "),
        cover_image_url: book.cover_image_url || "",
        open_library_work_key: book.open_library_work_key,
        open_library_edition_key: book.open_library_edition_key,
        open_library_cover_id: book.open_library_cover_id,
        source_metadata: book.source_metadata,
      });
      this.showOpenLibrary = false;
    },
    async save() {
      const confirmed = await confirmLibraryAction({
        title: this.form.id ? "Actualizar ficha bibliográfica" : "Registrar obra y ejemplares",
        text: this.form.id
          ? "Se guardarán los cambios y los ejemplares adicionales indicados."
          : `Se creará la obra y ${Number(this.form.quantity || 0)} ejemplar(es) con código institucional automático.`,
        confirmButtonText: this.form.id ? "Actualizar" : "Registrar",
      });
      if (!confirmed.isConfirmed) return;

      this.saving = true;
      this.error = null;
      try {
        const payload = this.buildPayload();
        if (this.form.id) {
          await axios.put(`/api/biblioteca/obras/${this.form.id}`, payload);
        } else {
          await axios.post("/api/biblioteca/obras", payload);
        }
        const wasEdit = Boolean(this.form.id);
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showLibrarySuccess(wasEdit ? "Obra actualizada correctamente." : "Obra y ejemplares registrados correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    exportCard(item) {
      downloadPdfReport(
        `ficha-${item.internal_code}`,
        "Ficha bibliográfica",
        `${item.internal_code} · Biblioteca Escolar`,
        [{
          title: item.title,
          headers: ["Campo", "Información"],
          rows: [
            ["Título", item.title],
            ["Autor", item.main_author],
            ["Editorial", item.publisher || "Sin editorial"],
            ["ISBN", item.isbn || "Sin ISBN"],
            ["Código", item.internal_code],
            ["Categoría", item.categoria?.name || item.category || "Sin categoría"],
            ["Cantidad", `${item.available_copies} disponible(s) de ${item.total_copies}`],
            ["Ubicación", item.ubicacion?.name || item.physical_location || "Sin ubicación"],
          ],
        }]
      );
    },
    exportDateCard(item) {
      downloadPdfReport(
        `ficha-fechas-${item.internal_code}`,
        "Ficha de fechas de préstamo",
        `${item.title} · ${item.main_author} · ${item.internal_code}`,
        [{
          title: "Registro",
          headers: ["Fecha préstamo", "Fecha devolución", "Nombre / curso", "Firma"],
          rows: Array.from({ length: 14 }, () => ["", "", "", ""]),
        }]
      );
    },
    async destroy(item) {
      const confirmed = await confirmLibraryAction({
        title: "Eliminar obra",
        text: `Se eliminará "${item.title}" solamente si no tiene ejemplares ni historial.`,
        confirmButtonText: "Eliminar",
        icon: "warning",
      });
      if (!confirmed.isConfirmed) return;
      try {
        await axios.delete(`/api/biblioteca/obras/${item.id}`);
        await this.load(this.pagination.current_page);
        this.$emit("refresh-catalogs");
        await showLibrarySuccess("Obra eliminada correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      }
    },
    async closeModal() {
      const confirmed = await confirmLibraryCancel("los cambios del formulario");
      if (confirmed.isConfirmed) this.showModal = false;
    },
  },
};
</script>

<template>
  <div class="catalog-shell">
    <section class="catalog-hero">
      <div>
        <span class="catalog-eyebrow">CATÁLOGO CENTRAL</span>
        <h5>Libros, recursos y ejemplares en una sola ficha</h5>
        <p>Importa metadatos desde Open Library, genera códigos institucionales y crea varias copias en un único paso.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <LibraryHelpButton title="Ayuda: catálogo" text="El catálogo separa la obra bibliográfica de sus copias físicas. Puedes completar los datos manualmente o usar Open Library como apoyo." />
        <BButton v-if="catalogs.capabilities?.manage_catalog !== false" class="hero-button" @click="openCreate">
          <i class="bx bx-plus-circle me-1"></i>Nueva obra
        </BButton>
      </div>
    </section>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <section class="filter-panel">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label">Búsqueda inteligente</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <BFormInput v-model="filters.search" placeholder="Título, autor, ISBN o código" @keyup.enter="load" />
          </div>
        </div>
        <div class="col-md-3 col-lg-2"><label class="form-label">Tipo</label><BFormSelect v-model="filters.material_type" :options="[{ value: null, text: 'Todos' }].concat((catalogs.material_types || []).map((item) => ({ value: item.value, text: item.label })))" @change="load(1)" /></div>
        <div class="col-md-3 col-lg-2"><label class="form-label">Categoría</label><BFormSelect v-model="filters.biblioteca_categoria_id" :options="categoryOptions" @change="load(1)" /></div>
        <div class="col-md-3 col-lg-2"><label class="form-label">Estado</label><BFormSelect v-model="filters.general_status" :options="[{ value: null, text: 'Todos' }].concat((catalogs.obra_statuses || []).map((item) => ({ value: item.value, text: item.label })))" @change="load(1)" /></div>
        <div class="col-md-3 col-lg-2 d-flex gap-2">
          <BButton variant="primary" class="flex-grow-1" @click="load(1)"><i class="bx bx-search me-1"></i>Buscar</BButton>
          <BButton variant="light" class="filter-reset" :disabled="!hasActiveFilters" title="Limpiar filtros" @click="resetFilters"><i class="bx bx-reset"></i></BButton>
        </div>
        <div class="col-12">
          <BFormCheckbox v-model="filters.available_only" @change="load(1)">Mostrar únicamente obras con ejemplares disponibles</BFormCheckbox>
        </div>
      </div>
    </section>

    <LoadingState v-if="loading" message="Organizando el catálogo..." compact />

    <section v-else class="catalog-results">
      <header class="results-toolbar">
        <div class="results-summary">
          <span class="results-icon"><i class="bx bx-book-content"></i></span>
          <div>
            <strong>{{ pagination.total }} {{ pagination.total === 1 ? "obra" : "obras" }}</strong>
            <small>Mostrando {{ resultRange }}</small>
          </div>
        </div>
        <div class="view-switch" role="group" aria-label="Modo de visualización">
          <button type="button" :class="{ active: viewMode === 'cards' }" :aria-pressed="viewMode === 'cards'" @click="setViewMode('cards')">
            <i class="bx bx-grid-alt"></i><span>Tarjetas</span>
          </button>
          <button type="button" :class="{ active: viewMode === 'table' }" :aria-pressed="viewMode === 'table'" @click="setViewMode('table')">
            <i class="bx bx-list-ul"></i><span>Tabla</span>
          </button>
        </div>
      </header>

      <div v-if="!items.length" class="empty-catalog">
        <span class="empty-catalog__icon"><i class="bx bx-book-open"></i></span>
        <h5>No hay obras para estos filtros</h5>
        <p>Prueba otra búsqueda o limpia los filtros para volver a ver el catálogo completo.</p>
        <BButton v-if="hasActiveFilters" variant="outline-primary" @click="resetFilters"><i class="bx bx-reset me-1"></i>Limpiar filtros</BButton>
        <BButton v-else-if="catalogs.capabilities?.manage_catalog !== false" variant="primary" @click="openCreate"><i class="bx bx-plus me-1"></i>Registrar primer libro</BButton>
      </div>

      <div v-else-if="viewMode === 'cards'" class="catalog-grid">
        <article v-for="item in items" :key="item.id" class="book-card">
          <button type="button" class="book-cover" :aria-label="`Ver ficha de ${item.title}`" @click="openDetails(item)">
            <img v-if="coverAvailable(item)" :src="item.cover_image_url" :alt="`Portada de ${item.title}`" @error="markCoverFailed(item)" />
            <span v-else class="book-cover__fallback"><i class="bx bx-book-open"></i><span>Sin portada</span></span>
            <span class="book-code">{{ item.internal_code }}</span>
          </button>
          <div class="book-body">
            <div class="book-heading">
              <div>
                <span class="book-category">{{ item.categoria?.name || item.category || "Sin categoría" }}</span>
                <button type="button" class="book-title" @click="openDetails(item)">{{ item.title }}</button>
                <p>{{ item.main_author }}</p>
              </div>
              <LibraryStatusBadge :status="item.general_status" />
            </div>
            <div class="book-meta">
              <span><i class="bx bx-library"></i>{{ item.publisher || "Sin editorial" }}</span>
              <span><i class="bx bx-barcode"></i>{{ item.isbn || "Sin ISBN" }}</span>
              <span><i class="bx bx-map"></i>{{ item.ubicacion?.name || item.physical_location || "Sin ubicación" }}</span>
            </div>
            <div class="availability">
              <div><strong>{{ item.available_copies }}</strong><span>disponibles</span></div>
              <div class="availability__line"><span :style="{ width: `${stockPercent(item)}%` }"></span></div>
              <small>de {{ item.total_copies }}</small>
            </div>
            <div class="book-actions">
              <button type="button" class="action-button action-button--view" @click="openDetails(item)">
                <i class="bx bx-show"></i><span>Ver ficha</span>
              </button>
              <button v-if="catalogs.capabilities?.manage_catalog !== false" type="button" class="action-button action-button--edit" @click="openEdit(item)">
                <i class="bx bx-edit-alt"></i><span>Editar ficha</span>
              </button>
              <button v-if="catalogs.capabilities?.manage_catalog !== false" type="button" class="action-button action-button--delete" @click="destroy(item)">
                <i class="bx bx-trash"></i><span>Eliminar libro</span>
              </button>
            </div>
          </div>
        </article>
      </div>

      <div v-else class="catalog-table-wrap">
        <table class="catalog-table">
          <thead>
            <tr>
              <th>Libro</th>
              <th>Identificación</th>
              <th>Categoría y ubicación</th>
              <th>Disponibilidad</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <div class="table-book">
                  <button type="button" class="table-cover" :aria-label="`Ver ficha de ${item.title}`" @click="openDetails(item)">
                    <img v-if="coverAvailable(item)" :src="item.cover_image_url" :alt="`Portada de ${item.title}`" @error="markCoverFailed(item)" />
                    <i v-else class="bx bx-book-open"></i>
                  </button>
                  <div>
                    <button type="button" class="table-title" @click="openDetails(item)">{{ item.title }}</button>
                    <span>{{ item.main_author }}</span>
                    <small>{{ item.publisher || "Sin editorial" }}</small>
                  </div>
                </div>
              </td>
              <td>
                <div class="table-stack">
                  <code>{{ item.internal_code }}</code>
                  <span><i class="bx bx-barcode"></i>{{ item.isbn || "Sin ISBN" }}</span>
                </div>
              </td>
              <td>
                <div class="table-stack">
                  <strong>{{ item.categoria?.name || item.category || "Sin categoría" }}</strong>
                  <span><i class="bx bx-map"></i>{{ item.ubicacion?.name || item.physical_location || "Sin ubicación" }}</span>
                </div>
              </td>
              <td>
                <div class="table-stock">
                  <strong>{{ item.available_copies }} <span>de {{ item.total_copies }}</span></strong>
                  <div class="availability__line"><span :style="{ width: `${stockPercent(item)}%` }"></span></div>
                  <small>ejemplares disponibles</small>
                </div>
              </td>
              <td><LibraryStatusBadge :status="item.general_status" /></td>
              <td>
                <div class="table-actions">
                  <button type="button" class="icon-action icon-action--view" title="Ver ficha" :aria-label="`Ver ficha de ${item.title}`" @click="openDetails(item)"><i class="bx bx-show"></i></button>
                  <button v-if="catalogs.capabilities?.manage_catalog !== false" type="button" class="icon-action icon-action--edit" title="Editar ficha" :aria-label="`Editar ficha de ${item.title}`" @click="openEdit(item)"><i class="bx bx-edit-alt"></i></button>
                  <button v-if="catalogs.capabilities?.manage_catalog !== false" type="button" class="icon-action icon-action--delete" title="Eliminar libro" :aria-label="`Eliminar ${item.title}`" @click="destroy(item)"><i class="bx bx-trash"></i></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="pagination.total" class="catalog-pagination">
        <span>{{ resultRange }} obras</span>
        <BPagination v-if="pagination.total > pagination.per_page" v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" />
      </footer>
    </section>

    <BModal v-model="showDetailsModal" size="lg" title="Ficha del libro" hide-footer scrollable @hidden="selectedItem = null">
      <LoadingState v-if="detailsLoading && !selectedItem" message="Cargando ficha..." compact />
      <div v-else-if="selectedItem" class="details-sheet">
        <div class="details-hero">
          <div class="details-cover">
            <img v-if="coverAvailable(selectedItem)" :src="selectedItem.cover_image_url" :alt="`Portada de ${selectedItem.title}`" @error="markCoverFailed(selectedItem)" />
            <div v-else><i class="bx bx-book-open"></i><span>Sin portada</span></div>
          </div>
          <div class="details-heading">
            <div class="details-heading__top">
              <span>{{ selectedItem.categoria?.name || selectedItem.category || "Sin categoría" }}</span>
              <LibraryStatusBadge :status="selectedItem.general_status" />
            </div>
            <h4>{{ selectedItem.title }}</h4>
            <p>{{ selectedItem.subtitle || selectedItem.main_author }}</p>
            <strong v-if="selectedItem.subtitle">{{ selectedItem.main_author }}</strong>
            <div class="details-codes">
              <span><small>Código interno</small>{{ selectedItem.internal_code }}</span>
              <span><small>ISBN</small>{{ selectedItem.isbn || "Sin ISBN" }}</span>
            </div>
          </div>
        </div>

        <div class="details-stats">
          <article><i class="bx bx-layer"></i><div><strong>{{ selectedItem.total_copies || 0 }}</strong><span>Ejemplares</span></div></article>
          <article class="available"><i class="bx bx-check-circle"></i><div><strong>{{ selectedItem.available_copies || 0 }}</strong><span>Disponibles</span></div></article>
          <article><i class="bx bx-map"></i><div><strong>{{ selectedItem.ubicacion?.name || "Sin asignar" }}</strong><span>Ubicación</span></div></article>
        </div>

        <div class="details-actions">
          <div class="details-export">
            <button type="button" @click="exportCard(selectedItem)"><i class="bx bx-file"></i>Ficha bibliográfica</button>
            <button type="button" @click="exportDateCard(selectedItem)"><i class="bx bx-calendar"></i>Ficha de fechas</button>
          </div>
          <div class="details-management">
            <button type="button" class="details-close" @click="closeDetails">Cerrar</button>
            <button v-if="catalogs.capabilities?.manage_catalog !== false" type="button" class="details-edit" @click="editFromDetails"><i class="bx bx-edit-alt"></i>Editar ficha</button>
            <button v-if="catalogs.capabilities?.manage_catalog !== false" type="button" class="details-delete" @click="deleteFromDetails"><i class="bx bx-trash"></i>Eliminar libro</button>
          </div>
        </div>

        <section class="details-section">
          <div class="details-section__title"><span><i class="bx bx-book-content"></i></span><div><small>Información editorial</small><h6>Datos bibliográficos</h6></div></div>
          <dl class="details-grid">
            <div><dt>Autor principal</dt><dd>{{ selectedItem.main_author }}</dd></div>
            <div><dt>Editorial</dt><dd>{{ selectedItem.publisher || "No informada" }}</dd></div>
            <div><dt>Año de publicación</dt><dd>{{ selectedItem.publication_year || "No informado" }}</dd></div>
            <div><dt>Idioma</dt><dd>{{ selectedItem.language || "No informado" }}</dd></div>
            <div><dt>Páginas</dt><dd>{{ selectedItem.page_count || "No informado" }}</dd></div>
            <div><dt>Tipo de recurso</dt><dd>{{ selectedItem.material_type || "Libro" }}</dd></div>
          </dl>
        </section>

        <section class="details-section">
          <div class="details-section__title"><span><i class="bx bx-category"></i></span><div><small>Organización interna</small><h6>Clasificación y almacenaje</h6></div></div>
          <dl class="details-grid">
            <div><dt>Categoría</dt><dd>{{ selectedItem.categoria?.name || selectedItem.category || "Sin categoría" }}</dd></div>
            <div><dt>Subcategoría</dt><dd>{{ selectedItem.subcategoria?.name || selectedItem.subcategory || "No informada" }}</dd></div>
            <div><dt>Género</dt><dd>{{ selectedItem.genre || "No informado" }}</dd></div>
            <div><dt>Ubicación</dt><dd>{{ selectedItem.ubicacion?.name || selectedItem.physical_location || "Sin ubicación" }}</dd></div>
            <div><dt>Estante</dt><dd>{{ selectedItem.shelf || "No informado" }}</dd></div>
            <div><dt>Sección CRA</dt><dd>{{ selectedItem.section || "No informada" }}</dd></div>
          </dl>
        </section>

        <section v-if="selectedItem.description || selectedItem.observations" class="details-section">
          <div class="details-section__title"><span><i class="bx bx-align-left"></i></span><div><small>Contenido</small><h6>Descripción y observaciones</h6></div></div>
          <p v-if="selectedItem.description" class="details-copy">{{ selectedItem.description }}</p>
          <p v-if="selectedItem.observations" class="details-note"><strong>Observaciones:</strong> {{ selectedItem.observations }}</p>
        </section>
      </div>
    </BModal>

    <BModal v-model="showModal" size="xl" :title="form.id ? 'Editar ficha del libro' : 'Registrar nueva obra'" hide-footer scrollable>
      <div class="book-form-head">
        <div class="book-form-cover">
          <img v-if="form.cover_image_url" :src="form.cover_image_url" alt="Vista previa de portada" />
          <i v-else class="bx bx-image-add"></i>
        </div>
        <div>
          <span>DATOS BIBLIOGRÁFICOS</span>
          <h5>{{ form.title || "Nueva obra" }}</h5>
          <p>{{ form.main_author || "Completa los datos manualmente o usa la fuente externa." }}</p>
        </div>
        <BButton variant="outline-primary" class="ms-auto" @click="openMetadataSearch">
          <i class="bx bx-cloud-download me-1"></i>Completar con Open Library
        </BButton>
      </div>

      <div class="form-section">
        <h6><span>1</span> Identificación</h6>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">Tipo de recurso</label><BFormSelect v-model="form.material_type" :options="(catalogs.material_types || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Título *</label><BFormInput v-model="form.title" /></div>
          <div class="col-md-3"><label class="form-label">Subtítulo</label><BFormInput v-model="form.subtitle" /></div>
          <div class="col-md-4"><label class="form-label">Autor principal *</label><BFormInput v-model="form.main_author" /></div>
          <div class="col-md-4"><label class="form-label">Autores secundarios</label><BFormInput v-model="form.secondary_authors_text" placeholder="Separados por coma" /></div>
          <div class="col-md-4"><label class="form-label">Editorial</label><BFormInput v-model="form.publisher" /></div>
          <div class="col-md-2"><label class="form-label">Año</label><BFormInput v-model="form.publication_year" type="number" /></div>
          <div class="col-md-3"><label class="form-label">ISBN</label><BFormInput v-model="form.isbn" /></div>
          <div class="col-md-3"><label class="form-label">Código interno</label><BFormInput v-model="form.internal_code" placeholder="Automático al guardar" /></div>
          <div class="col-md-2"><label class="form-label">Páginas</label><BFormInput v-model="form.page_count" type="number" /></div>
          <div class="col-md-2"><label class="form-label">Idioma</label><BFormInput v-model="form.language" /></div>
        </div>
      </div>

      <div class="form-section">
        <h6><span>2</span> Clasificación y ubicación</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Categoría interna</label>
            <BFormSelect
              :model-value="form.biblioteca_categoria_id"
              :options="formCategoryOptions"
              @update:model-value="changeFormCategory"
            />
          </div>
          <div class="col-md-4">
            <label class="form-label">Subcategoría</label>
            <BFormSelect
              :model-value="form.biblioteca_subcategoria_id"
              :options="subcategoryOptions"
              :disabled="!form.biblioteca_categoria_id"
              @update:model-value="changeFormSubcategory"
            />
            <div class="form-text">
              Las opciones se administran en la sección Categorías.
            </div>
          </div>
          <div class="col-md-4"><label class="form-label">Género</label><BFormInput v-model="form.genre" /></div>
          <div class="col-md-4"><label class="form-label">Ubicación normalizada</label><BFormSelect v-model="form.biblioteca_ubicacion_id" :options="locationOptions" /></div>
          <div class="col-md-3"><label class="form-label">Estantería histórica</label><BFormInput v-model="form.shelf" /></div>
          <div class="col-md-2"><label class="form-label">Sección CRA</label><BFormInput v-model="form.section" /></div>
          <div class="col-md-3"><label class="form-label">Nivel recomendado</label><BFormInput v-model="form.recommended_level" /></div>
          <div class="col-md-4"><label class="form-label">Curso recomendado</label><BFormSelect v-model="form.recommended_course_section_id" :options="[{ value: null, text: 'Sin curso específico' }].concat((catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name })))" /></div>
          <div class="col-md-4"><label class="form-label">Estado general</label><BFormSelect v-model="form.general_status" :options="(catalogs.obra_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div v-if="!form.id" class="col-md-4"><label class="form-label">Cantidad inicial</label><BFormInput v-model="form.quantity" type="number" min="0" max="500" /><small class="text-muted">Se crearán códigos individuales.</small></div>
          <div v-else class="col-md-4"><label class="form-label">Agregar ejemplares</label><BFormInput v-model="form.additional_quantity" type="number" min="0" max="500" /></div>
        </div>
      </div>

      <div class="form-section">
        <h6><span>3</span> Contenido y metadatos</h6>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Portada</label><BFormInput v-model="form.cover_image_url" /></div>
          <div class="col-md-6"><label class="form-label">Palabras clave</label><BFormInput v-model="form.keywords_text" placeholder="Separadas por coma" /></div>
          <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="form.description" rows="3" /></div>
          <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.observations" rows="2" /></div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
          {{ form.id ? "Guardar cambios" : "Registrar obra" }}
        </BButton>
      </div>
    </BModal>

    <BModal v-model="showOpenLibrary" size="xl" title="Buscar en Open Library" hide-footer scrollable>
      <div class="open-library-banner">
        <i class="bx bx-globe"></i>
        <div><strong>Metadatos bibliográficos abiertos</strong><span>Busca por ISBN, título o autor. Tú decides qué resultado aplicar y puedes editar todos los campos.</span></div>
      </div>
      <div class="input-group input-group-lg mb-3">
        <BFormInput v-model="openLibraryQuery" placeholder="Ej. 978956... o Cien años de soledad" @keyup.enter="searchOpenLibrary" />
        <BButton variant="primary" :disabled="openLibraryLoading" @click="searchOpenLibrary">Buscar</BButton>
      </div>
      <BAlert v-if="openLibraryError" show variant="warning">{{ openLibraryError }}</BAlert>
      <LoadingState v-if="openLibraryLoading" message="Consultando Open Library..." compact />
      <div v-else class="open-results">
        <button v-for="book in openLibraryResults" :key="`${book.open_library_work_key}-${book.isbn}`" type="button" class="open-result" @click="applyOpenLibrary(book)">
          <img v-if="book.cover_image_url" :src="book.cover_image_url" :alt="book.title" />
          <span v-else class="open-result__cover"><i class="bx bx-book"></i></span>
          <span class="open-result__body">
            <strong>{{ book.title }}</strong>
            <small>{{ book.main_author }} · {{ book.publication_year || "Año no informado" }}</small>
            <small>{{ book.publisher || "Editorial no informada" }} · {{ book.isbn || "Sin ISBN" }}</small>
            <em>Usar estos datos <i class="bx bx-right-arrow-alt"></i></em>
          </span>
        </button>
      </div>
      <div class="small text-muted mt-3">Fuente: Open Library, un proyecto de Internet Archive. La información debe ser revisada antes de guardar.</div>
    </BModal>
  </div>
</template>

<style scoped>
.catalog-shell { display: flex; flex-direction: column; gap: 1rem; }
.catalog-hero { background: linear-gradient(135deg, #182848 0%, #2f4f7f 55%, #4c6fff 100%); border-radius: 20px; padding: 1.45rem 1.6rem; color: white; display: flex; justify-content: space-between; align-items: center; gap: 1rem; box-shadow: 0 18px 40px rgba(24, 40, 72, .18); }
.catalog-hero h5 { color: white; font-size: 1.25rem; margin: .2rem 0 .35rem; }
.catalog-hero p { margin: 0; color: rgba(255,255,255,.75); max-width: 680px; }
.catalog-eyebrow { font-size: .68rem; letter-spacing: .18em; font-weight: 800; color: #b9c8ff; }
.hero-button { background: white; color: #263d76; border-color: white; font-weight: 700; white-space: nowrap; }
.filter-panel { background: #fff; border: 1px solid #e8edf5; border-radius: 16px; padding: 1rem 1.1rem; box-shadow: 0 8px 24px rgba(29, 47, 78, .06); }
.filter-panel .form-label { color: #4e5d73; font-size: .72rem; font-weight: 750; letter-spacing: .02em; margin-bottom: .4rem; }
.filter-panel :deep(.form-control),
.filter-panel :deep(.form-select),
.filter-panel .input-group-text { min-height: 42px; border-color: #e1e7f0; }
.filter-panel .input-group-text { background: #f7f9fc; color: #75839a; }
.filter-reset { width: 42px; flex: 0 0 42px; color: #66748a; }
.catalog-results { display: flex; flex-direction: column; gap: 1rem; }
.results-toolbar { background: #fff; border: 1px solid #e6ebf3; border-radius: 16px; padding: .72rem .8rem .72rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 7px 22px rgba(28, 45, 75, .05); }
.results-summary { display: flex; align-items: center; gap: .7rem; min-width: 0; }
.results-summary > div { display: flex; flex-direction: column; }
.results-summary strong { color: #24324a; font-size: .9rem; }
.results-summary small { color: #8a96a9; font-size: .7rem; }
.results-icon { width: 36px; height: 36px; display: grid; place-items: center; flex: 0 0 36px; border-radius: 11px; color: #4c6fff; background: #edf1ff; font-size: 1.15rem; }
.view-switch { display: inline-flex; gap: .25rem; padding: .25rem; border-radius: 11px; background: #f0f3f8; }
.view-switch button { min-height: 34px; padding: .38rem .78rem; border: 0; border-radius: 8px; background: transparent; color: #758197; display: inline-flex; align-items: center; gap: .42rem; font-size: .75rem; font-weight: 700; transition: color .15s ease, background .15s ease, box-shadow .15s ease; }
.view-switch button:hover { color: #3f5cc9; }
.view-switch button.active { color: #314dbe; background: #fff; box-shadow: 0 3px 10px rgba(43, 62, 103, .11); }
.view-switch i { font-size: 1rem; }
.catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 1rem; }
.book-card { min-width: 0; background: #fff; border: 1px solid #e6ebf3; border-radius: 18px; overflow: hidden; display: grid; grid-template-columns: 112px 1fr; min-height: 250px; transition: transform .18s ease, box-shadow .18s ease; box-shadow: 0 8px 24px rgba(28, 45, 75, .055); }
.book-card:hover { transform: translateY(-3px); box-shadow: 0 16px 36px rgba(28, 45, 75, .11); }
.book-cover { position: relative; background: #edf1f8; min-height: 100%; width: 100%; padding: 0; border: 0; color: inherit; cursor: pointer; overflow: hidden; }
.book-cover img { width: 100%; height: 100%; object-fit: cover; }
.book-cover__fallback { height: 100%; display: grid; place-content: center; text-align: center; color: #8793aa; gap: .4rem; }
.book-cover__fallback i { font-size: 2.5rem; }
.book-cover__fallback span { font-size: .72rem; }
.book-code { position: absolute; left: .5rem; right: .5rem; bottom: .55rem; padding: .3rem .4rem; background: rgba(18, 28, 48, .86); color: white; border-radius: 7px; font-size: .62rem; text-align: center; letter-spacing: .03em; }
.book-body { padding: 1rem; display: flex; flex-direction: column; min-width: 0; }
.book-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: .6rem; }
.book-heading > div { min-width: 0; }
.book-category { display: inline-block; color: #4c6fff; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .3rem; }
.book-title { width: 100%; display: block; padding: 0; border: 0; background: transparent; text-align: left; font-size: 1rem; font-weight: 700; margin-bottom: .25rem; color: #202b3c; line-height: 1.35; transition: color .15s ease; }
.book-title:hover { color: #4c6fff; }
.book-body p { color: #6f7b90; margin-bottom: .7rem; font-size: .82rem; }
.book-meta { display: grid; gap: .3rem; color: #6d788a; font-size: .72rem; }
.book-meta span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.book-meta i { width: 18px; color: #94a0b4; }
.availability { display: grid; grid-template-columns: auto 1fr auto; gap: .55rem; align-items: center; margin-top: auto; padding-top: .8rem; }
.availability div:first-child { display: flex; align-items: baseline; gap: .22rem; }
.availability strong { color: #2f4f7f; }
.availability span, .availability small { font-size: .68rem; color: #8792a5; }
.availability__line { height: 5px; background: #edf1f7; border-radius: 99px; overflow: hidden; }
.availability__line span { display: block; height: 100%; background: linear-gradient(90deg,#4c6fff,#53c6a2); border-radius: 99px; }
.book-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .42rem; margin-top: .8rem; padding-top: .75rem; border-top: 1px solid #edf0f5; }
.action-button { min-width: 0; height: 32px; padding: 0 .5rem; border-radius: 8px; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; gap: .3rem; font-size: .66rem; line-height: 1; font-weight: 750; white-space: nowrap; transition: transform .15s ease, background .15s ease, border-color .15s ease, box-shadow .15s ease; }
.action-button i { flex: 0 0 auto; font-size: .88rem; }
.action-button span { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.action-button:hover { transform: translateY(-1px); }
.action-button--view { grid-column: 1 / -1; width: 100%; height: 36px; color: #fff; background: #405fd2; border-color: #405fd2; box-shadow: 0 5px 12px rgba(64, 95, 210, .18); }
.action-button--view:hover { background: #3452c1; }
.action-button--edit { color: #506079; background: #f6f8fb; border-color: #dfe5ee; }
.action-button--edit:hover { color: #3452c1; border-color: #bdc9ef; background: #f1f4ff; }
.action-button--delete { color: #cf5260; background: #fff8f8; border-color: #f4d9dd; }
.action-button--delete:hover { color: #b63848; border-color: #eebbc2; background: #fff1f2; }
.empty-catalog { text-align: center; border: 1px dashed #cfd7e5; background: linear-gradient(145deg,#fff,#f8faff); border-radius: 18px; padding: 3.2rem 1.5rem; color: #79869a; }
.empty-catalog__icon { width: 66px; height: 66px; display: grid; place-items: center; margin: 0 auto 1rem; border-radius: 20px; background: #edf1ff; color: #6078d8; }
.empty-catalog__icon i { font-size: 2rem; }
.empty-catalog h5 { color: #2f3d54; margin-bottom: .4rem; }
.empty-catalog p { margin: 0 auto 1.1rem; max-width: 480px; }
.catalog-table-wrap { overflow-x: auto; background: #fff; border: 1px solid #e6ebf3; border-radius: 18px; box-shadow: 0 8px 24px rgba(28, 45, 75, .05); }
.catalog-table { width: 100%; min-width: 1040px; border-collapse: separate; border-spacing: 0; }
.catalog-table th { padding: .82rem 1rem; background: #f7f9fc; border-bottom: 1px solid #e6ebf3; color: #7e8a9e; font-size: .66rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; white-space: nowrap; }
.catalog-table th:first-child { border-top-left-radius: 17px; }
.catalog-table th:last-child { position: sticky; right: 0; z-index: 2; border-top-right-radius: 17px; box-shadow: -10px 0 18px rgba(34,49,76,.05); }
.catalog-table td { padding: .82rem 1rem; border-bottom: 1px solid #edf0f5; vertical-align: middle; color: #536076; font-size: .74rem; }
.catalog-table td:last-child { position: sticky; right: 0; z-index: 1; background: #fff; box-shadow: -10px 0 18px rgba(34,49,76,.05); }
.catalog-table tbody tr:last-child td { border-bottom: 0; }
.catalog-table tbody tr { transition: background .15s ease; }
.catalog-table tbody tr:hover { background: #fafbff; }
.catalog-table tbody tr:hover td:last-child { background: #fafbff; }
.table-book { display: flex; align-items: center; gap: .75rem; min-width: 230px; }
.table-cover { width: 44px; height: 60px; padding: 0; border: 0; border-radius: 7px; overflow: hidden; flex: 0 0 44px; display: grid; place-items: center; background: #edf1f7; color: #8693a8; font-size: 1.3rem; }
.table-cover img { width: 100%; height: 100%; object-fit: cover; }
.table-book > div { display: flex; flex-direction: column; min-width: 0; }
.table-title { max-width: 250px; padding: 0; border: 0; background: transparent; color: #26354c; font-size: .79rem; font-weight: 750; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.table-title:hover { color: #4c6fff; }
.table-book span { color: #69768b; margin-top: .12rem; }
.table-book small { color: #98a2b3; margin-top: .08rem; }
.table-stack { display: flex; flex-direction: column; gap: .32rem; min-width: 145px; }
.table-stack code { width: max-content; padding: .23rem .4rem; border-radius: 6px; background: #eef2ff; color: #405cc3; font-size: .67rem; }
.table-stack strong { color: #36455c; }
.table-stack span { color: #7e899a; white-space: nowrap; }
.table-stack i { margin-right: .3rem; color: #99a4b7; }
.table-stock { min-width: 140px; }
.table-stock > strong { display: block; color: #2f4f7f; margin-bottom: .34rem; font-size: .85rem; }
.table-stock > strong span { color: #99a3b3; font-weight: 500; }
.table-stock small { display: block; color: #929daf; margin-top: .3rem; font-size: .65rem; }
.table-actions { width: max-content; min-width: 102px; margin-left: auto; padding: .2rem; display: flex; justify-content: flex-end; gap: .16rem; border: 1px solid #e7ebf2; border-radius: 10px; background: #f7f9fc; }
.table-actions .icon-action { width: 30px !important; height: 30px !important; min-width: 30px !important; min-height: 30px !important; padding: 0 !important; border: 0 !important; border-radius: 7px !important; display: grid !important; place-items: center; font-size: .92rem; background: transparent; box-shadow: none; transition: transform .15s ease, color .15s ease, background .15s ease, box-shadow .15s ease; }
.icon-action:hover { transform: translateY(-1px); }
.table-actions .icon-action--view { color: #65748e; }
.table-actions .icon-action--view:hover { color: #fff; background: #405fd2; box-shadow: 0 3px 8px rgba(64,95,210,.18); }
.table-actions .icon-action--edit { color: #405fd2; }
.table-actions .icon-action--edit:hover { color: #314ebd; background: #e8edff; }
.table-actions .icon-action--delete { color: #c9505d; }
.table-actions .icon-action--delete:hover { color: #b63b49; background: #ffecee; }
.catalog-pagination { min-height: 44px; display: flex; align-items: center; justify-content: space-between; gap: 1rem; color: #8792a4; font-size: .72rem; }
.catalog-pagination :deep(.pagination) { margin-bottom: 0; }
.details-sheet { display: flex; flex-direction: column; gap: 1rem; }
.details-hero { display: grid; grid-template-columns: 118px 1fr; gap: 1.25rem; padding: 1.1rem; border-radius: 18px; background: linear-gradient(135deg,#eff3ff 0%,#f7f9ff 58%,#edf8f5 100%); border: 1px solid #e0e7f5; }
.details-cover { width: 118px; height: 160px; border-radius: 12px; overflow: hidden; background: #dfe6f1; box-shadow: 0 10px 24px rgba(41,57,90,.15); }
.details-cover img { width: 100%; height: 100%; object-fit: cover; }
.details-cover > div { width: 100%; height: 100%; display: grid; place-content: center; gap: .4rem; text-align: center; color: #8995a9; }
.details-cover i { font-size: 2rem; }
.details-cover span { font-size: .68rem; }
.details-heading { min-width: 0; display: flex; flex-direction: column; }
.details-heading__top { display: flex; justify-content: space-between; align-items: center; gap: .6rem; }
.details-heading__top > span { color: #4c6fff; font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.details-heading h4 { margin: .55rem 0 .2rem; color: #233149; line-height: 1.25; }
.details-heading p { margin: 0 0 .25rem; color: #68758b; }
.details-heading > strong { color: #46556c; font-size: .8rem; }
.details-codes { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: auto; padding-top: .8rem; }
.details-codes > span { min-width: 130px; padding: .5rem .65rem; border-radius: 10px; background: rgba(255,255,255,.75); color: #334561; font-size: .72rem; font-weight: 750; }
.details-codes small { display: block; margin-bottom: .1rem; color: #8996aa; font-size: .58rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
.details-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: .7rem; }
.details-stats article { min-width: 0; padding: .8rem .9rem; border: 1px solid #e5eaf2; border-radius: 13px; display: flex; align-items: center; gap: .7rem; background: #fff; }
.details-stats article > i { width: 36px; height: 36px; flex: 0 0 36px; display: grid; place-items: center; border-radius: 10px; background: #eef2ff; color: #4c6fff; font-size: 1.1rem; }
.details-stats article.available > i { color: #249472; background: #e9f8f2; }
.details-stats article > div { min-width: 0; display: flex; flex-direction: column; }
.details-stats strong { color: #2c3a51; font-size: .88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.details-stats span { color: #8a95a7; font-size: .65rem; }
.details-section { padding: 1rem; border: 1px solid #e6ebf3; border-radius: 15px; }
.details-section__title { display: flex; align-items: center; gap: .7rem; margin-bottom: .9rem; }
.details-section__title > span { width: 34px; height: 34px; flex: 0 0 34px; display: grid; place-items: center; border-radius: 10px; color: #4c6fff; background: #eef2ff; }
.details-section__title > div { display: flex; flex-direction: column; }
.details-section__title small { color: #909aab; font-size: .6rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.details-section__title h6 { color: #314057; margin: 0; }
.details-grid { margin: 0; display: grid; grid-template-columns: repeat(3,1fr); gap: .85rem 1.2rem; }
.details-grid > div { min-width: 0; }
.details-grid dt { color: #909aac; font-size: .63rem; font-weight: 700; margin-bottom: .15rem; }
.details-grid dd { color: #3f4e65; font-size: .76rem; font-weight: 600; margin: 0; overflow-wrap: anywhere; }
.details-copy { color: #59677d; line-height: 1.65; font-size: .77rem; margin-bottom: .7rem; }
.details-note { padding: .7rem .8rem; border-radius: 10px; background: #fff8e9; color: #785d28; font-size: .72rem; margin: 0; }
.details-actions { display: flex; justify-content: space-between; align-items: center; gap: .8rem; padding: .75rem; border: 1px solid #e2e8f1; border-radius: 13px; background: #f9faff; }
.details-export,
.details-management { display: flex; flex-wrap: wrap; gap: .45rem; }
.details-actions button { min-height: 38px; padding: .48rem .75rem; border-radius: 9px; border: 1px solid #dfe5ee; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; font-size: .71rem; font-weight: 750; }
.details-export button { background: #f7f9fc; color: #53627a; }
.details-close { background: #fff; color: #657289; }
.details-edit { color: #fff; background: #405fd2; border-color: #405fd2 !important; }
.details-delete { color: #bc3d4c; background: #fff4f5; border-color: #f2d3d7 !important; }
.book-form-head { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: linear-gradient(135deg,#f4f7ff,#edf6ff); border-radius: 15px; margin-bottom: 1rem; }
.book-form-cover { width: 62px; height: 84px; border-radius: 9px; background: #dce4f2; overflow: hidden; display: grid; place-items: center; color: #75839a; flex: 0 0 auto; }
.book-form-cover img { width: 100%; height: 100%; object-fit: cover; }
.book-form-cover i { font-size: 1.8rem; }
.book-form-head span { color: #4c6fff; font-size: .67rem; font-weight: 800; letter-spacing: .13em; }
.book-form-head h5 { margin: .2rem 0; }
.book-form-head p { margin: 0; color: #7b8799; }
.form-section { border: 1px solid #e7ebf2; border-radius: 15px; padding: 1rem; margin-top: .8rem; }
.form-section h6 { display: flex; align-items: center; gap: .55rem; margin-bottom: 1rem; color: #2e3c53; }
.form-section h6 span { display: grid; place-items: center; width: 25px; height: 25px; border-radius: 8px; background: #e8edff; color: #4c6fff; font-size: .75rem; }
.open-library-banner { display: flex; gap: .8rem; align-items: center; padding: .9rem 1rem; border-radius: 13px; background: #eef4ff; color: #34518b; margin-bottom: 1rem; }
.open-library-banner i { font-size: 2rem; }
.open-library-banner span { display: block; font-size: .78rem; color: #6f7f99; }
.open-results { display: grid; grid-template-columns: repeat(auto-fill,minmax(300px,1fr)); gap: .8rem; }
.open-result { text-align: left; border: 1px solid #e2e8f1; background: #fff; border-radius: 14px; padding: .75rem; display: flex; gap: .8rem; color: inherit; transition: border-color .15s, box-shadow .15s; }
.open-result:hover { border-color: #7990ff; box-shadow: 0 8px 24px rgba(76,111,255,.12); }
.open-result img, .open-result__cover { width: 62px; height: 86px; border-radius: 7px; object-fit: cover; background: #eef2f7; flex: 0 0 auto; display: grid; place-items: center; color: #8d99ac; font-size: 1.6rem; }
.open-result__body { display: flex; flex-direction: column; min-width: 0; }
.open-result__body strong { color: #27344a; line-height: 1.3; }
.open-result__body small { color: #77849a; margin-top: .25rem; }
.open-result__body em { color: #4c6fff; font-style: normal; font-size: .75rem; font-weight: 700; margin-top: auto; }
@media (max-width: 767px) {
  .catalog-hero { align-items: flex-start; flex-direction: column; }
  .results-toolbar { align-items: stretch; flex-direction: column; }
  .view-switch { width: 100%; }
  .view-switch button { flex: 1; justify-content: center; }
  .catalog-grid { grid-template-columns: 1fr; }
  .book-card { grid-template-columns: 92px 1fr; }
  .catalog-pagination { align-items: flex-start; flex-direction: column; }
  .details-hero { grid-template-columns: 82px 1fr; gap: .85rem; padding: .85rem; }
  .details-cover { width: 82px; height: 114px; }
  .details-heading h4 { font-size: 1.05rem; }
  .details-codes { margin-top: .5rem; }
  .details-codes > span { min-width: 0; flex: 1 1 120px; }
  .details-stats { grid-template-columns: 1fr; }
  .details-grid { grid-template-columns: repeat(2,1fr); }
  .details-actions { align-items: stretch; flex-direction: column; }
  .details-export,
  .details-management { width: 100%; }
  .details-actions button { flex: 1; }
  .book-form-head { align-items: flex-start; flex-wrap: wrap; }
  .book-form-head .ms-auto { margin-left: 0 !important; width: 100%; }
}
@media (max-width: 430px) {
  .catalog-hero { border-radius: 16px; padding: 1.15rem; }
  .catalog-hero .d-flex { width: 100%; }
  .catalog-hero .hero-button { flex: 1; }
  .book-card { grid-template-columns: 78px 1fr; }
  .book-body { padding: .85rem; }
  .book-meta { font-size: .68rem; }
  .action-button { padding: 0 .35rem; font-size: .62rem; }
  .details-hero { grid-template-columns: 1fr; }
  .details-cover { width: 96px; height: 132px; }
  .details-heading__top { align-items: flex-start; }
  .details-grid { grid-template-columns: 1fr; }
  .details-export,
  .details-management { display: grid; grid-template-columns: 1fr; }
}
</style>
