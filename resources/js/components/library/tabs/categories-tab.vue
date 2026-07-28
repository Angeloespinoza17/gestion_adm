<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import LibraryHelpButton from "../help-button.vue";
import {
  confirmLibraryAction,
  formatLibraryError,
  showLibrarySuccess,
} from "../module-utils";

const blank = () => ({
  id: null,
  name: "",
  code: "",
  color: "#556ee6",
  description: "",
  sort_order: 0,
  active: true,
});

const blankSubcategory = (categoryId = null) => ({
  id: null,
  biblioteca_categoria_id: categoryId,
  name: "",
  description: "",
  sort_order: 0,
  active: true,
});

export default {
  components: { LoadingState, LibraryHelpButton },
  props: { catalogs: { type: Object, required: true } },
  emits: ["refresh-catalogs"],
  data() {
    return {
      loading: false,
      error: null,
      items: [],
      form: blank(),
      showModal: false,
      subcategoryForm: blankSubcategory(),
      showSubcategoryModal: false,
      saving: false,
    };
  },
  computed: {
    categoryOptions() {
      return this.items.map((item) => ({
        value: item.id,
        text: `${item.name} · ${item.code}`,
      }));
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/categorias");
        this.items = response.data.data || [];
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudieron cargar las categorías.");
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = blank();
      this.showModal = true;
    },
    openEdit(item) {
      this.form = { ...blank(), ...item };
      this.showModal = true;
    },
    openCreateSubcategory(category) {
      this.subcategoryForm = blankSubcategory(category?.id || null);
      this.showSubcategoryModal = true;
    },
    openEditSubcategory(subcategory) {
      this.subcategoryForm = {
        ...blankSubcategory(),
        ...subcategory,
      };
      this.showSubcategoryModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.form.id) {
          await axios.put(`/api/biblioteca/categorias/${this.form.id}`, this.form);
        } else {
          await axios.post("/api/biblioteca/categorias", this.form);
        }
        this.showModal = false;
        await this.load();
        this.$emit("refresh-catalogs");
        await showLibrarySuccess("Categoría guardada correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      const result = await confirmLibraryAction({
        title: "Eliminar categoría",
        text: `Se eliminará “${item.name}” si no tiene obras asociadas.`,
        confirmButtonText: "Eliminar",
        icon: "warning",
      });
      if (!result.isConfirmed) return;
      try {
        await axios.delete(`/api/biblioteca/categorias/${item.id}`);
        await this.load();
        this.$emit("refresh-catalogs");
      } catch (error) {
        this.error = formatLibraryError(error);
      }
    },
    async saveSubcategory() {
      this.saving = true;
      this.error = null;
      try {
        const payload = {
          biblioteca_categoria_id: this.subcategoryForm.biblioteca_categoria_id,
          name: this.subcategoryForm.name,
          description: this.subcategoryForm.description || null,
          sort_order: Number(this.subcategoryForm.sort_order || 0),
          active: Boolean(this.subcategoryForm.active),
        };

        if (this.subcategoryForm.id) {
          await axios.put(
            `/api/biblioteca/subcategorias/${this.subcategoryForm.id}`,
            payload
          );
        } else {
          await axios.post("/api/biblioteca/subcategorias", payload);
        }

        this.showSubcategoryModal = false;
        await this.load();
        this.$emit("refresh-catalogs");
        await showLibrarySuccess("Subcategoría guardada correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    async removeSubcategory(subcategory) {
      const result = await confirmLibraryAction({
        title: "Eliminar subcategoría",
        text: `Se eliminará “${subcategory.name}” si no tiene títulos asociados.`,
        confirmButtonText: "Eliminar",
        icon: "warning",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/biblioteca/subcategorias/${subcategory.id}`);
        await this.load();
        this.$emit("refresh-catalogs");
        await showLibrarySuccess("Subcategoría eliminada correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      }
    },
  },
};
</script>

<template>
  <div class="category-view">
    <section class="category-head">
      <div>
        <span>CLASIFICACIÓN INTERNA</span>
        <h5>Categorías y subcategorías fáciles de mantener</h5>
        <p>Organiza los títulos en una jerarquía clara y reutilizable.</p>
      </div>
      <div class="d-flex gap-2">
        <LibraryHelpButton title="Ayuda: categorías" text="Crea categorías generales y subcategorías dependientes. Luego podrás seleccionarlas al registrar o editar un título." />
        <BButton v-if="catalogs.capabilities?.manage_categories !== false" variant="primary" @click="openCreate"><i class="bx bx-plus me-1"></i>Nueva categoría</BButton>
      </div>
    </section>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <LoadingState v-if="loading" message="Cargando categorías..." compact />

    <div v-else class="category-grid">
      <article v-for="item in items" :key="item.id" class="category-card" :style="{ '--category-color': item.color || '#556ee6' }">
        <div class="category-icon"><i class="bx bx-category-alt"></i></div>
        <div class="category-content">
          <div class="d-flex align-items-start justify-content-between gap-2">
            <div><span>{{ item.code }}</span><h6>{{ item.name }}</h6></div>
            <span class="status-dot" :class="{ inactive: !item.active }">{{ item.active ? "Activa" : "Inactiva" }}</span>
          </div>
          <p>{{ item.description || "Sin descripción." }}</p>
          <div class="subcategory-block">
            <div class="subcategory-heading">
              <div>
                <span>SUBCATEGORÍAS</span>
                <strong>{{ item.subcategorias_count || 0 }}</strong>
              </div>
              <BButton
                v-if="catalogs.capabilities?.manage_categories !== false"
                size="sm"
                variant="outline-primary"
                @click="openCreateSubcategory(item)"
              >
                <i class="bx bx-plus me-1"></i>Agregar
              </BButton>
            </div>
            <div v-if="item.subcategorias?.length" class="subcategory-list">
              <div
                v-for="subcategory in item.subcategorias"
                :key="subcategory.id"
                class="subcategory-row"
                :class="{ inactive: !subcategory.active }"
              >
                <div>
                  <strong>{{ subcategory.name }}</strong>
                  <small>{{ subcategory.obras_count || 0 }} título(s)</small>
                </div>
                <div
                  v-if="catalogs.capabilities?.manage_categories !== false"
                  class="subcategory-actions"
                >
                  <button type="button" title="Editar subcategoría" @click="openEditSubcategory(subcategory)">
                    <i class="bx bx-edit"></i>
                  </button>
                  <button type="button" title="Eliminar subcategoría" class="danger" @click="removeSubcategory(subcategory)">
                    <i class="bx bx-trash"></i>
                  </button>
                </div>
              </div>
            </div>
            <button
              v-else
              type="button"
              class="subcategory-empty"
              @click="openCreateSubcategory(item)"
            >
              Aún no hay subcategorías · Crear la primera
            </button>
          </div>
          <div class="category-footer">
            <strong>{{ item.obras_count || 0 }}</strong><span>obras asociadas</span>
            <div class="ms-auto d-flex gap-1">
              <BButton size="sm" variant="light" @click="openEdit(item)"><i class="bx bx-edit"></i></BButton>
              <BButton size="sm" variant="light" class="text-danger" @click="remove(item)"><i class="bx bx-trash"></i></BButton>
            </div>
          </div>
        </div>
      </article>
      <button v-if="catalogs.capabilities?.manage_categories !== false" type="button" class="category-add" @click="openCreate">
        <i class="bx bx-plus-circle"></i><span>Crear una categoría</span>
      </button>
    </div>

    <BModal v-model="showModal" :title="form.id ? 'Editar categoría' : 'Nueva categoría'" hide-footer>
      <div class="category-preview" :style="{ '--category-color': form.color }">
        <i class="bx bx-category-alt"></i>
        <div><span>{{ form.code || "CÓDIGO" }}</span><strong>{{ form.name || "Nombre de categoría" }}</strong></div>
      </div>
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Nombre *</label><BFormInput v-model="form.name" /></div>
        <div class="col-md-4"><label class="form-label">Código *</label><BFormInput v-model="form.code" maxlength="20" /></div>
        <div class="col-md-4"><label class="form-label">Color</label><BFormInput v-model="form.color" type="color" /></div>
        <div class="col-md-4"><label class="form-label">Orden</label><BFormInput v-model="form.sort_order" type="number" min="0" /></div>
        <div class="col-md-4 d-flex align-items-end pb-2"><BFormCheckbox v-model="form.active">Categoría activa</BFormCheckbox></div>
        <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="form.description" rows="3" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar categoría" }}</BButton>
      </div>
    </BModal>

    <BModal
      v-model="showSubcategoryModal"
      :title="subcategoryForm.id ? 'Editar subcategoría' : 'Nueva subcategoría'"
      hide-footer
    >
      <div class="subcategory-modal-head">
        <i class="bx bx-git-branch"></i>
        <div>
          <strong>Clasificación secundaria</strong>
          <span>La subcategoría siempre pertenece a una categoría principal.</span>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Categoría principal *</label>
          <BFormSelect
            v-model="subcategoryForm.biblioteca_categoria_id"
            :options="categoryOptions"
          />
        </div>
        <div class="col-md-8">
          <label class="form-label">Nombre *</label>
          <BFormInput
            v-model="subcategoryForm.name"
            placeholder="Ej.: Novela histórica"
            maxlength="120"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label">Orden</label>
          <BFormInput v-model="subcategoryForm.sort_order" type="number" min="0" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea
            v-model="subcategoryForm.description"
            rows="3"
            placeholder="Uso o alcance de esta subcategoría"
          />
        </div>
        <div class="col-12">
          <BFormCheckbox v-model="subcategoryForm.active">
            Subcategoría activa y disponible en los formularios
          </BFormCheckbox>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="showSubcategoryModal = false">Cancelar</BButton>
        <BButton
          variant="primary"
          :disabled="saving || !subcategoryForm.biblioteca_categoria_id || !subcategoryForm.name.trim()"
          @click="saveSubcategory"
        >
          {{ saving ? "Guardando..." : "Guardar subcategoría" }}
        </BButton>
      </div>
    </BModal>
  </div>
</template>

<style scoped>
.category-view { display: flex; flex-direction: column; gap: 1rem; }
.category-head { background: linear-gradient(135deg,#fff7eb,#fff 60%); border: 1px solid #f3e4cd; border-radius: 18px; padding: 1.35rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.category-head > div:first-child > span { color: #c87c16; font-size: .68rem; font-weight: 800; letter-spacing: .14em; }
.category-head h5 { margin: .25rem 0; }
.category-head p { margin: 0; color: #7c8492; }
.category-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(290px,1fr)); gap: 1rem; }
.category-card { --category-color:#556ee6; background:#fff; border:1px solid #e8ebf2; border-top:4px solid var(--category-color); border-radius:16px; padding:1rem; display:flex; gap:.8rem; box-shadow:0 8px 22px rgba(31,45,69,.05); }
.category-icon { width:42px;height:42px;border-radius:12px;background:color-mix(in srgb,var(--category-color) 13%,white);color:var(--category-color);display:grid;place-items:center;font-size:1.25rem;flex:0 0 auto; }
.category-content { flex:1;min-width:0; }
.category-content span { font-size:.65rem;color:var(--category-color);font-weight:800;letter-spacing:.1em; }
.category-content h6 { margin:.15rem 0 .55rem; }
.category-content p { color:#7a8596;font-size:.78rem;min-height:34px; }
.subcategory-block { margin:.75rem 0;background:#f8faff;border:1px solid #edf0f7;border-radius:12px;padding:.7rem; }
.subcategory-heading { display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-bottom:.55rem; }
.subcategory-heading > div { display:flex;align-items:center;gap:.4rem; }
.subcategory-heading span { color:#7f899a; }
.subcategory-heading strong { display:grid;place-items:center;min-width:22px;height:22px;border-radius:99px;background:color-mix(in srgb,var(--category-color) 12%,white);color:var(--category-color);font-size:.72rem; }
.subcategory-list { display:flex;flex-direction:column;gap:.38rem; }
.subcategory-row { display:flex;align-items:center;justify-content:space-between;gap:.5rem;background:#fff;border:1px solid #e9edf5;border-radius:9px;padding:.48rem .55rem; }
.subcategory-row.inactive { opacity:.58; }
.subcategory-row > div:first-child { min-width:0;display:flex;flex-direction:column; }
.subcategory-row strong { font-size:.76rem;color:#354158;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.subcategory-row small { font-size:.65rem;color:#8a94a5; }
.subcategory-actions { display:flex;gap:.2rem; }
.subcategory-actions button { width:28px;height:28px;border:0;border-radius:7px;background:#f0f4ff;color:#526ee3;display:grid;place-items:center; }
.subcategory-actions button.danger { background:#fff0f2;color:#dc5666; }
.subcategory-empty { width:100%;border:1px dashed #cad3e1;background:#fff;border-radius:9px;padding:.55rem;color:#748197;font-size:.7rem; }
.status-dot { padding:.22rem .45rem;border-radius:99px;background:#e9f8f1;color:#2d8b69!important;letter-spacing:0!important;white-space:nowrap; }
.status-dot.inactive { background:#f0f1f4;color:#7f8896!important; }
.category-footer { display:flex;align-items:baseline;border-top:1px solid #eef1f5;padding-top:.65rem; }
.category-footer strong { color:#27344b;margin-right:.25rem; }
.category-footer > span { color:#8791a0;letter-spacing:0;font-weight:400; }
.category-add { border:1px dashed #bdc7d8;background:#f9fbfe;border-radius:16px;color:#77859a;display:grid;place-content:center;gap:.3rem;min-height:160px; }
.category-add i { font-size:2rem;color:#556ee6; }
.category-preview { --category-color:#556ee6;display:flex;align-items:center;gap:.7rem;padding:.85rem 1rem;border-radius:12px;background:color-mix(in srgb,var(--category-color) 9%,white);color:var(--category-color);margin-bottom:1rem; }
.category-preview i { font-size:1.6rem; }.category-preview span{display:block;font-size:.62rem;font-weight:800;letter-spacing:.1em}.category-preview strong{color:#2b3547}
.subcategory-modal-head { display:flex;align-items:center;gap:.75rem;padding:.85rem 1rem;margin-bottom:1rem;border-radius:12px;background:#f3f6ff;color:#4f6bdd; }
.subcategory-modal-head i { font-size:1.55rem; }
.subcategory-modal-head div { display:flex;flex-direction:column; }
.subcategory-modal-head strong { color:#2f3b52; }
.subcategory-modal-head span { color:#7b879a;font-size:.76rem; }
@media(max-width:700px){.category-head{align-items:flex-start;flex-direction:column}}
</style>
