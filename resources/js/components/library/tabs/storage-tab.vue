<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import LibraryHelpButton from "../help-button.vue";
import { confirmLibraryAction, formatLibraryError, showLibrarySuccess } from "../module-utils";

const blank = () => ({
  id: null,
  parent_id: null,
  type: "estante",
  name: "",
  code: "",
  audience_type: "mixta",
  sort_order: 0,
  active: true,
  notes: "",
});

export default {
  components: { LoadingState, LibraryHelpButton },
  props: { catalogs: { type: Object, required: true } },
  emits: ["refresh-catalogs"],
  data() {
    return { loading: false, error: null, items: [], form: blank(), showModal: false, saving: false };
  },
  computed: {
    roots() {
      return this.items.filter((item) => !item.parent_id);
    },
    parentOptions() {
      return [{ value: null, text: "Nivel raíz" }].concat(
        this.items.map((item) => ({ value: item.id, text: `${item.code} · ${item.name}` }))
      );
    },
  },
  mounted() { this.load(); },
  methods: {
    async load() {
      this.loading = true;
      try {
        const response = await axios.get("/api/biblioteca/ubicaciones");
        this.items = response.data.data || [];
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.loading = false;
      }
    },
    descendants(parentId) {
      return this.items.filter((item) => Number(item.parent_id) === Number(parentId));
    },
    openCreate(parent = null) {
      this.form = { ...blank(), parent_id: parent?.id || null, type: parent ? (parent.type === "sala" ? "estante" : "repisa") : "sala", audience_type: parent?.audience_type || "mixta" };
      this.showModal = true;
    },
    openEdit(item) { this.form = { ...blank(), ...item }; this.showModal = true; },
    async save() {
      this.saving = true;
      try {
        if (this.form.id) await axios.put(`/api/biblioteca/ubicaciones/${this.form.id}`, this.form);
        else await axios.post("/api/biblioteca/ubicaciones", this.form);
        this.showModal = false;
        await this.load();
        this.$emit("refresh-catalogs");
        await showLibrarySuccess("Ubicación guardada correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally { this.saving = false; }
    },
    async remove(item) {
      const result = await confirmLibraryAction({ title: "Eliminar ubicación", text: "Solo se eliminará si está vacía y no contiene subniveles.", confirmButtonText: "Eliminar", icon: "warning" });
      if (!result.isConfirmed) return;
      try { await axios.delete(`/api/biblioteca/ubicaciones/${item.id}`); await this.load(); this.$emit("refresh-catalogs"); }
      catch (error) { this.error = formatLibraryError(error); }
    },
  },
};
</script>

<template>
  <div class="storage-view">
    <section class="storage-head">
      <div class="storage-head__icon"><i class="bx bx-library"></i></div>
      <div><span>MAPA DE ALMACENAJE</span><h5>Sala → estante → repisa</h5><p>Ubicaciones normalizadas para encontrar cada ejemplar sin depender de texto libre.</p></div>
      <div class="ms-auto d-flex gap-2"><LibraryHelpButton title="Ayuda: almacenaje" text="Crea salas, sectores, estantes y repisas. No podrás eliminar una ubicación con libros o subniveles." /><BButton variant="primary" @click="openCreate()"><i class="bx bx-plus me-1"></i>Nueva ubicación</BButton></div>
    </section>
    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <LoadingState v-if="loading" message="Dibujando el mapa de la biblioteca..." compact />
    <div v-else class="room-grid">
      <article v-for="room in roots" :key="room.id" class="room-card" :class="`room-card--${room.audience_type}`">
        <header>
          <div><span>{{ room.code }}</span><h5>{{ room.name }}</h5><p>{{ room.notes || "Espacio de biblioteca" }}</p></div>
          <div class="d-flex gap-1"><BButton size="sm" variant="light" @click="openEdit(room)"><i class="bx bx-edit"></i></BButton><BButton size="sm" variant="light" @click="openCreate(room)"><i class="bx bx-plus"></i></BButton></div>
        </header>
        <div class="room-stats"><div><strong>{{ room.ejemplares_count || 0 }}</strong><span>ejemplares directos</span></div><div><strong>{{ descendants(room.id).length }}</strong><span>ubicaciones internas</span></div></div>
        <div class="shelf-list">
          <div v-for="shelf in descendants(room.id)" :key="shelf.id" class="shelf">
            <div class="shelf-title"><i :class="shelf.type === 'repisa' ? 'bx bx-minus' : 'bx bx-cabinet'"></i><div><strong>{{ shelf.name }}</strong><small>{{ shelf.code }} · {{ shelf.type }}</small></div><span>{{ shelf.ejemplares_count || 0 }}</span><BButton size="sm" variant="link" @click="openEdit(shelf)"><i class="bx bx-edit-alt"></i></BButton></div>
            <div v-if="descendants(shelf.id).length" class="repisas">
              <button v-for="child in descendants(shelf.id)" :key="child.id" type="button" @click="openEdit(child)"><i class="bx bx-book-content"></i><span>{{ child.name }}<small>{{ child.code }}</small></span><strong>{{ child.ejemplares_count || 0 }}</strong></button>
            </div>
          </div>
          <button type="button" class="add-shelf" @click="openCreate(room)"><i class="bx bx-plus"></i>Agregar estante o sector</button>
        </div>
      </article>
    </div>

    <BModal v-model="showModal" :title="form.id ? 'Editar ubicación' : 'Nueva ubicación'" hide-footer>
      <div class="location-path"><i class="bx bx-map-pin"></i><span>{{ parentOptions.find((item) => item.value === form.parent_id)?.text || "Biblioteca" }}</span><i class="bx bx-chevron-right"></i><strong>{{ form.name || "Nueva ubicación" }}</strong></div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Nivel superior</label><BFormSelect v-model="form.parent_id" :options="parentOptions" /></div>
        <div class="col-md-6"><label class="form-label">Tipo</label><BFormSelect v-model="form.type" :options="[{value:'sala',text:'Sala'},{value:'sector',text:'Sector'},{value:'estante',text:'Estante'},{value:'repisa',text:'Repisa'}]" /></div>
        <div class="col-md-8"><label class="form-label">Nombre *</label><BFormInput v-model="form.name" /></div>
        <div class="col-md-4"><label class="form-label">Código *</label><BFormInput v-model="form.code" /></div>
        <div class="col-md-6"><label class="form-label">Público</label><BFormSelect v-model="form.audience_type" :options="[{value:'basica',text:'Enseñanza Básica'},{value:'media',text:'Enseñanza Media'},{value:'mixta',text:'Uso mixto'}]" /></div>
        <div class="col-md-3"><label class="form-label">Orden</label><BFormInput v-model="form.sort_order" type="number" min="0" /></div>
        <div class="col-md-3 d-flex align-items-end pb-2"><BFormCheckbox v-model="form.active">Activa</BFormCheckbox></div>
        <div class="col-12"><label class="form-label">Notas</label><BFormTextarea v-model="form.notes" rows="2" /></div>
      </div>
      <div class="d-flex justify-content-between mt-4"><BButton v-if="form.id" variant="outline-danger" @click="remove(form); showModal = false">Eliminar</BButton><div class="ms-auto d-flex gap-2"><BButton variant="light" @click="showModal = false">Cancelar</BButton><BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton></div></div>
    </BModal>
  </div>
</template>

<style scoped>
.storage-view{display:flex;flex-direction:column;gap:1rem}.storage-head{display:flex;align-items:center;gap:1rem;background:linear-gradient(135deg,#edf9f5,#fff);border:1px solid #dcedea;border-radius:18px;padding:1.25rem 1.4rem}.storage-head__icon{width:54px;height:54px;border-radius:16px;background:#d9f3e9;color:#27846c;display:grid;place-items:center;font-size:1.7rem}.storage-head span{font-size:.65rem;font-weight:800;letter-spacing:.14em;color:#27846c}.storage-head h5{margin:.2rem 0}.storage-head p{margin:0;color:#7a8793}.room-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:1rem}.room-card{--room:#556ee6;background:#fff;border:1px solid #e5eaf1;border-top:5px solid var(--room);border-radius:18px;overflow:hidden;box-shadow:0 9px 25px rgba(35,50,75,.06)}.room-card--basica{--room:#50a5f1}.room-card--media{--room:#7b61ff}.room-card--mixta{--room:#34c38f}.room-card>header{padding:1.1rem 1.2rem;display:flex;justify-content:space-between;background:linear-gradient(180deg,color-mix(in srgb,var(--room) 6%,white),white)}.room-card header span{font-size:.65rem;color:var(--room);font-weight:800;letter-spacing:.1em}.room-card header h5{margin:.15rem 0}.room-card header p{font-size:.75rem;color:#818b9b;margin:0}.room-stats{display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #edf0f4;border-bottom:1px solid #edf0f4}.room-stats div{padding:.75rem 1rem;display:flex;gap:.35rem;align-items:baseline}.room-stats div+div{border-left:1px solid #edf0f4}.room-stats span{font-size:.7rem;color:#8893a4}.shelf-list{padding:1rem;display:grid;gap:.65rem}.shelf{border:1px solid #e5e9f0;border-radius:12px;overflow:hidden}.shelf-title{display:flex;align-items:center;gap:.55rem;padding:.7rem .8rem}.shelf-title>i{color:var(--room);font-size:1.2rem}.shelf-title div{display:flex;flex-direction:column;flex:1}.shelf-title small{color:#8a94a4}.shelf-title>span{font-size:.7rem;background:#eef2f7;padding:.2rem .45rem;border-radius:99px}.repisas{padding:.25rem .65rem .65rem;display:grid;grid-template-columns:repeat(2,1fr);gap:.4rem}.repisas button{border:0;background:#f7f9fc;border-radius:8px;padding:.5rem;display:flex;gap:.4rem;align-items:center;text-align:left;color:#647187}.repisas button span{display:flex;flex:1;flex-direction:column;font-size:.72rem}.repisas small{font-size:.6rem;color:#99a3b1}.add-shelf{border:1px dashed #c6cedb;background:#fafbfd;border-radius:10px;padding:.6rem;color:#7a8798}.location-path{display:flex;align-items:center;gap:.35rem;background:#f3f6fa;border-radius:10px;padding:.7rem;margin-bottom:1rem;color:#718096;font-size:.75rem}.location-path strong{color:#33415a}@media(max-width:700px){.storage-head{align-items:flex-start;flex-wrap:wrap}.storage-head .ms-auto{margin-left:0!important;width:100%}.room-grid{grid-template-columns:1fr}.repisas{grid-template-columns:1fr}}
</style>
