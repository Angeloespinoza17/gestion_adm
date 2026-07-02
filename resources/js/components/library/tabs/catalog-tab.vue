<script>
import axios from "axios";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmLibraryAction,
  confirmLibraryCancel,
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
  category: "",
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
  physical_location: "",
  shelf: "",
  section: "",
  general_status: "disponible",
  observations: "",
});

export default {
  components: {
    LibraryHelpButton,
    LibraryStatusBadge,
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
      error: null,
      items: [],
      pagination: { current_page: 1, total: 0, per_page: 12 },
      filters: {
        search: "",
        material_type: null,
        category: null,
        recommended_course_section_id: null,
        general_status: null,
        available_only: false,
      },
      showModal: false,
      form: emptyForm(),
    };
  },
  mounted() {
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
      if (!this.$route.query.obra) return;
      await this.openEditById(this.$route.query.obra);
    },
    buildPayload() {
      return {
        material_type: this.form.material_type,
        title: this.form.title,
        subtitle: this.form.subtitle,
        main_author: this.form.main_author,
        secondary_authors: this.form.secondary_authors_text
          .split(",")
          .map((item) => item.trim())
          .filter(Boolean),
        publisher: this.form.publisher || null,
        publication_year: this.form.publication_year || null,
        isbn: this.form.isbn || null,
        category: this.form.category || null,
        subcategory: this.form.subcategory || null,
        genre: this.form.genre || null,
        recommended_level: this.form.recommended_level || null,
        recommended_course_section_id: this.form.recommended_course_section_id || null,
        language: this.form.language || null,
        page_count: this.form.page_count || null,
        description: this.form.description || null,
        keywords: this.form.keywords_text.split(",").map((item) => item.trim()).filter(Boolean),
        cover_image_url: this.form.cover_image_url || null,
        internal_code: this.form.internal_code,
        barcode: this.form.barcode || null,
        physical_location: this.form.physical_location || null,
        shelf: this.form.shelf || null,
        section: this.form.section || null,
        general_status: this.form.general_status,
        observations: this.form.observations || null,
      };
    },
    resetForm() {
      this.form = emptyForm();
    },
    openCreate() {
      this.resetForm();
      this.showModal = true;
    },
    async openEdit(item) {
      await this.openEditById(item.id);
    },
    async openEditById(id) {
      const response = await axios.get(`/api/biblioteca/obras/${id}`);
      const obra = response.data.data;
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
        category: obra.category || "",
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
        physical_location: obra.physical_location || "",
        shelf: obra.shelf || "",
        section: obra.section || "",
        general_status: obra.general_status,
        observations: obra.observations || "",
      };
      this.showModal = true;
    },
    async save() {
      const confirmed = await confirmLibraryAction({
        title: this.form.id ? "Confirmar edición" : "Confirmar registro",
        text: this.form.id
          ? "Se actualizará la obra bibliográfica seleccionada."
          : "Se registrará una nueva obra bibliográfica en el catálogo.",
        confirmButtonText: this.form.id ? "Sí, actualizar" : "Sí, guardar",
      });

      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const payload = this.buildPayload();
        if (this.form.id) {
          await axios.put(`/api/biblioteca/obras/${this.form.id}`, payload);
        } else {
          await axios.post("/api/biblioteca/obras", payload);
        }
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showLibrarySuccess(this.form.id ? "Obra actualizada correctamente." : "Obra registrada correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    async destroy(item) {
      const confirmed = await confirmLibraryAction({
        title: "Eliminar obra",
        text: `Se eliminará "${item.title}" del catálogo si no tiene historial asociado.`,
        confirmButtonText: "Sí, eliminar",
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
      if (confirmed.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Gestión del catálogo</div>
      <div class="d-flex gap-2">
        <LibraryHelpButton
          title="Ayuda: gestión del catálogo"
          text="Aquí se registran y actualizan obras bibliográficas o recursos del CRA con sus datos editoriales, clasificación pedagógica y ubicación física."
        />
        <BButton variant="primary" @click="openCreate">Nueva obra</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Título, autor, ISBN, código..." @keyup.enter="load" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="filters.material_type" :options="[{ value: null, text: 'Todos' }].concat((catalogs.material_types || []).map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Categoría</label>
          <BFormSelect v-model="filters.category" :options="[{ value: null, text: 'Todas' }].concat((catalogs.categories || []).map((item) => ({ value: item, text: item })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Curso recomendado</label>
          <BFormSelect v-model="filters.recommended_course_section_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.general_status" :options="[{ value: null, text: 'Todos' }].concat((catalogs.obra_statuses || []).map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <BFormCheckbox v-model="filters.available_only">Solo disponibilidad vigente</BFormCheckbox>
        </div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="filters = { search: '', material_type: null, category: null, recommended_course_section_id: null, general_status: null, available_only: false }; load();">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading" message="Cargando catálogo..." compact />
      <BTable
        v-else
        responsive
        :items="items"
        :fields="[
          { key: 'title', label: 'Obra' },
          { key: 'main_author', label: 'Autor principal' },
          { key: 'category', label: 'Categoría' },
          { key: 'available_copies', label: 'Disponibles' },
          { key: 'general_status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(title)="{ item }">
          <div class="fw-semibold">{{ item.title }}</div>
          <div class="small text-muted">{{ item.internal_code }} <span v-if="item.isbn">· ISBN {{ item.isbn }}</span></div>
        </template>
        <template #cell(available_copies)="{ item }">
          {{ item.available_copies }} / {{ item.total_copies }}
        </template>
        <template #cell(general_status)="{ item }">
          <LibraryStatusBadge :status="item.general_status" />
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" variant="outline-danger" @click="destroy(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="load"
        />
      </div>
    </BCard>

    <BModal v-model="showModal" size="xl" :title="form.id ? 'Editar obra' : 'Nueva obra'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Registro bibliográfico y criterios pedagógicos.</div>
        <LibraryHelpButton
          title="Ayuda: formulario de catálogo"
          text="Completa la identificación bibliográfica, clasificación, ubicación física y observaciones de la obra. Los ejemplares se administran en la pestaña de inventario."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Tipo de recurso</label><BFormSelect v-model="form.material_type" :options="(catalogs.material_types || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-6"><label class="form-label">Título</label><BFormInput v-model="form.title" /></div>
        <div class="col-md-3"><label class="form-label">Subtítulo</label><BFormInput v-model="form.subtitle" /></div>
        <div class="col-md-4"><label class="form-label">Autor principal</label><BFormInput v-model="form.main_author" /></div>
        <div class="col-md-4"><label class="form-label">Autores secundarios</label><BFormInput v-model="form.secondary_authors_text" placeholder="Separar por coma" /></div>
        <div class="col-md-4"><label class="form-label">Editorial</label><BFormInput v-model="form.publisher" /></div>
        <div class="col-md-2"><label class="form-label">Año</label><BFormInput v-model="form.publication_year" type="number" /></div>
        <div class="col-md-3"><label class="form-label">ISBN</label><BFormInput v-model="form.isbn" /></div>
        <div class="col-md-3"><label class="form-label">Código interno</label><BFormInput v-model="form.internal_code" /></div>
        <div class="col-md-2"><label class="form-label">Código barra / QR</label><BFormInput v-model="form.barcode" /></div>
        <div class="col-md-2"><label class="form-label">Páginas</label><BFormInput v-model="form.page_count" type="number" /></div>
        <div class="col-md-3"><label class="form-label">Categoría</label><BFormInput v-model="form.category" list="biblioteca-categories" /></div>
        <div class="col-md-3"><label class="form-label">Subcategoría</label><BFormInput v-model="form.subcategory" list="biblioteca-subcategories" /></div>
        <div class="col-md-3"><label class="form-label">Género</label><BFormInput v-model="form.genre" list="biblioteca-genres" /></div>
        <div class="col-md-3"><label class="form-label">Idioma</label><BFormInput v-model="form.language" list="biblioteca-languages" /></div>
        <div class="col-md-3"><label class="form-label">Nivel recomendado</label><BFormInput v-model="form.recommended_level" /></div>
        <div class="col-md-3"><label class="form-label">Curso recomendado</label><BFormSelect v-model="form.recommended_course_section_id" :options="[{ value: null, text: 'Sin curso' }].concat((catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name })))" /></div>
        <div class="col-md-3"><label class="form-label">Ubicación física</label><BFormInput v-model="form.physical_location" /></div>
        <div class="col-md-3"><label class="form-label">Estantería / Sección</label><BFormInput v-model="form.shelf" placeholder="Estantería" /></div>
        <div class="col-md-3"><label class="form-label">Sección CRA</label><BFormInput v-model="form.section" /></div>
        <div class="col-md-3"><label class="form-label">Estado general</label><BFormSelect v-model="form.general_status" :options="(catalogs.obra_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-6"><label class="form-label">Imagen de portada (URL)</label><BFormInput v-model="form.cover_image_url" /></div>
        <div class="col-md-6"><label class="form-label">Palabras clave</label><BFormInput v-model="form.keywords_text" placeholder="Separar por coma" /></div>
        <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="form.description" rows="3" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.observations" rows="3" /></div>
      </div>

      <datalist id="biblioteca-categories">
        <option v-for="item in catalogs.categories || []" :key="item" :value="item"></option>
      </datalist>
      <datalist id="biblioteca-subcategories">
        <option v-for="item in catalogs.subcategories || []" :key="item" :value="item"></option>
      </datalist>
      <datalist id="biblioteca-genres">
        <option v-for="item in catalogs.genres || []" :key="item" :value="item"></option>
      </datalist>
      <datalist id="biblioteca-languages">
        <option v-for="item in catalogs.languages || []" :key="item" :value="item"></option>
      </datalist>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : form.id ? "Actualizar" : "Guardar" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
