<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const emptyForm = () => ({ title: "", destination: "", needed_by: "", notes: "", items: [] });
const newKey = () => `${Date.now()}-${Math.random()}`;

export default {
  components: { Layout, LoadingState },
  data: () => ({
    loading: false,
    saving: false,
    requests: [],
    catalogItems: [],
    statuses: [],
    summary: { total: 0, submitted: 0, in_progress: 0, quoted: 0 },
    pagination: { current_page: 1, last_page: 1, total: 0 },
    filters: { search: "", status: "" },
    showForm: false,
    showDetail: false,
    selected: null,
    form: emptyForm(),
    catalogSearch: "",
    units: ["unidad", "litro", "kilogramo", "saco", "cilindro", "metro_cubico", "rollo", "caja", "bidon"],
  }),
  computed: {
    filteredCatalog() {
      const search = this.catalogSearch.trim().toLowerCase();
      return this.catalogItems.filter((item) => !search || [item.inventory_item?.name, item.inventory_item?.code, item.inventory_item?.description]
        .some((value) => String(value || "").toLowerCase().includes(search)));
    },
    requestItemSummary() {
      const total = this.form.items.length;
      return `${total} producto${total === 1 ? "" : "s"} agregado${total === 1 ? "" : "s"}`;
    },
  },
  mounted() { this.loadAll(); },
  beforeUnmount() { this.clearPreviews(); },
  methods: {
    async loadAll(page = 1) {
      this.loading = true;
      try {
        const params = { page, search: this.filters.search || undefined, status: this.filters.status || undefined };
        const [requests, items] = await Promise.all([
          axios.get("/api/supplies/requests", { params }),
          axios.get("/api/supplies/items", { params: { section: "cleaning", per_page: 100 } }),
        ]);
        this.requests = requests.data.data || [];
        this.statuses = requests.data.statuses || [];
        this.summary = requests.data.summary || this.summary;
        this.pagination = requests.data.meta || this.pagination;
        this.catalogItems = (items.data.data || []).filter((item) => item.inventory_item?.active !== false);
      } catch (error) { this.alertError(error); } finally { this.loading = false; }
    },
    openNew() {
      this.clearPreviews();
      this.form = { ...emptyForm(), title: "Solicitud de insumos de aseo" };
      this.catalogSearch = "";
      this.showForm = true;
    },
    addCatalogItem(item) {
      const existing = this.form.items.find((line) => Number(line.supply_item_id) === Number(item.id));
      if (existing) {
        existing.requested_quantity = Number(existing.requested_quantity || 0) + 1;
        return;
      }
      this.form.items.push({
        key: newKey(), supply_item_id: item.id,
        name: item.inventory_item?.name || "", description: item.inventory_item?.description || "",
        unit: item.inventory_item?.unit_of_measure || "unidad", requested_quantity: 1,
        photo: null, preview: item.photo_url || null,
      });
    },
    addCustom() {
      this.form.items.push({ key: newKey(), supply_item_id: null, name: "", description: "", unit: "unidad", requested_quantity: 1, photo: null, preview: null });
    },
    removeLine(index) {
      const [line] = this.form.items.splice(index, 1);
      if (line?.preview?.startsWith("blob:")) URL.revokeObjectURL(line.preview);
    },
    selectPhoto(line, event) {
      const file = event.target.files?.[0] || null;
      if (line.preview?.startsWith("blob:")) URL.revokeObjectURL(line.preview);
      line.photo = file;
      line.preview = file ? URL.createObjectURL(file) : null;
      event.target.value = "";
    },
    clearPreviews() {
      (this.form.items || []).forEach((line) => { if (line.preview?.startsWith("blob:")) URL.revokeObjectURL(line.preview); });
    },
    async save() {
      if (!this.form.items.length) {
        Swal.fire({ icon: "info", title: "Agrega productos", text: "Precarga al menos un insumo del inventario o agrega uno nuevo." });
        return;
      }
      this.saving = true;
      try {
        const data = new FormData();
        ["title", "destination", "needed_by", "notes"].forEach((key) => { if (this.form[key]) data.append(key, this.form[key]); });
        this.form.items.forEach((line, index) => {
          if (line.supply_item_id) data.append(`items[${index}][supply_item_id]`, line.supply_item_id);
          data.append(`items[${index}][name]`, line.name || "");
          data.append(`items[${index}][description]`, line.description || "");
          data.append(`items[${index}][unit]`, line.unit || "unidad");
          data.append(`items[${index}][requested_quantity]`, line.requested_quantity);
          if (line.photo) data.append(`items[${index}][photo]`, line.photo);
        });
        const { data: response } = await axios.post("/api/supplies/requests", data);
        this.showForm = false;
        this.clearPreviews();
        await this.loadAll(1);
        Swal.fire({ icon: "success", title: "Solicitud enviada", text: response.message, timer: 2400, showConfirmButton: false });
      } catch (error) { this.alertError(error); } finally { this.saving = false; }
    },
    async openDetail(request) {
      try {
        const { data } = await axios.get(`/api/supplies/requests/${request.id}`);
        this.selected = data.data;
        this.showDetail = true;
      } catch (error) { this.alertError(error); }
    },
    statusClass(status) { return `status-pill--${status || "submitted"}`; },
    number(value) { return new Intl.NumberFormat("es-CL", { maximumFractionDigits: 2 }).format(Number(value || 0)); },
    unitLabel(value) { return ({ metro_cubico: "m³", bidon: "bidón" }[value] || String(value || "").replaceAll("_", " ")); },
    date(value) { return value ? new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString("es-CL") : "Sin fecha definida"; },
    alertError(error) {
      const errors = error.response?.data?.errors;
      const detail = errors ? Object.values(errors).flat().join(" ") : error.response?.data?.message;
      Swal.fire({ icon: "error", title: "No fue posible completar la acción", text: detail || "Intenta nuevamente." });
    },
  },
};
</script>

<template>
  <Layout>
    <main class="request-page">
      <section class="request-hero">
        <div class="request-hero__copy">
          <span class="eyebrow"><i class="bx bx-list-check"></i> Abastecimiento · Insumos de aseo</span>
          <h1>Solicitudes claras, compras más rápidas</h1>
          <p>Selecciona productos existentes o agrega necesidades nuevas. Superadmin revisará la cantidad final antes de emitir la solicitud de cotización.</p>
          <div class="flow-tags"><span><b>1</b> Solicitar</span><i class="bx bx-chevron-right"></i><span><b>2</b> Revisar</span><i class="bx bx-chevron-right"></i><span><b>3</b> Cotizar</span></div>
        </div>
        <button type="button" class="primary-action" @click="openNew"><i class="bx bx-plus"></i>Nueva solicitud</button>
      </section>

      <nav class="supply-nav" aria-label="Submódulos de Abastecimiento">
        <router-link to="/supplies/cleaning"><i class="bx bx-spray-can"></i> Insumos de aseo</router-link>
        <router-link to="/supplies/requests" class="is-active"><i class="bx bx-list-check"></i> Solicitudes</router-link>
        <router-link to="/supplies/heating"><i class="bx bx-hot"></i> Combustibles y calefacción</router-link>
        <router-link to="/supplies/maintenance-storeroom"><i class="bx bx-wrench"></i> Pañol de mantenimiento</router-link>
      </nav>

      <section class="metrics">
        <article><span class="metric-icon blue"><i class="bx bx-layer"></i></span><div><small>Total solicitudes</small><strong>{{ summary.total || 0 }}</strong></div></article>
        <article><span class="metric-icon amber"><i class="bx bx-send"></i></span><div><small>Esperando revisión</small><strong>{{ summary.submitted || 0 }}</strong></div></article>
        <article><span class="metric-icon violet"><i class="bx bx-edit-alt"></i></span><div><small>En proceso</small><strong>{{ summary.in_progress || 0 }}</strong></div></article>
        <article><span class="metric-icon green"><i class="bx bx-file"></i></span><div><small>Cotizaciones emitidas</small><strong>{{ summary.quoted || 0 }}</strong></div></article>
      </section>

      <section class="request-workspace">
        <header class="workspace-head">
          <div><small>SEGUIMIENTO</small><h2>Solicitudes de insumos de aseo</h2><p>Consulta el avance y la lista revisada de cada requerimiento.</p></div>
          <div class="filters">
            <label><i class="bx bx-search"></i><input v-model="filters.search" type="search" placeholder="Folio, solicitud o producto" @keyup.enter="loadAll(1)"></label>
            <select v-model="filters.status" @change="loadAll(1)"><option value="">Todos los estados</option><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
            <button type="button" @click="loadAll(1)" :disabled="loading"><i class="bx bx-refresh" :class="{ 'bx-spin': loading }"></i></button>
          </div>
        </header>

        <LoadingState v-if="loading" />
        <div v-else-if="!requests.length" class="empty-state"><span><i class="bx bx-list-plus"></i></span><h3>Aún no hay solicitudes</h3><p>Crea la primera solicitud usando productos del inventario o agregando uno nuevo.</p><button type="button" @click="openNew">Crear solicitud</button></div>
        <div v-else class="table-wrap">
          <table>
            <thead><tr><th>Solicitud</th><th>Referencias</th><th>Productos</th><th>Estado</th><th>Fecha requerida</th><th></th></tr></thead>
            <tbody><tr v-for="request in requests" :key="request.id">
              <td><button class="request-title" type="button" @click="openDetail(request)"><small>{{ request.folio }}</small><strong>{{ request.title }}</strong><span><i class="bx bx-map"></i> {{ request.destination || "Sin destino" }} · {{ request.creator?.name || "Sin responsable" }}</span></button></td>
              <td><div class="thumb-stack"><template v-for="item in request.items.slice(0, 3)" :key="item.id"><img v-if="item.photo_url" :src="item.photo_url" :alt="item.item_name_snapshot"><span v-else><i class="bx bx-package"></i></span></template></div></td>
              <td><strong class="line-count">{{ request.items_count }} producto{{ request.items_count === 1 ? "" : "s" }}</strong><small class="product-preview">{{ request.items.slice(0, 2).map((item) => item.item_name_snapshot).join(" · ") }}</small></td>
              <td><span class="status-pill" :class="statusClass(request.status)">{{ request.status_label }}</span></td>
              <td><span class="date-cell"><i class="bx bx-calendar"></i>{{ date(request.needed_by) }}</span></td>
              <td><button class="icon-button" type="button" title="Ver solicitud" @click="openDetail(request)"><i class="bx bx-right-arrow-alt"></i></button></td>
            </tr></tbody>
          </table>
        </div>
      </section>

      <Teleport to="body">
      <div v-if="showForm" class="modal-shell request-form-shell" role="dialog" aria-modal="true" aria-label="Nueva solicitud de abastecimiento">
        <div class="modal-panel request-form-modal">
          <header class="request-form-header">
            <div class="request-form-header__icon"><i class="bx bx-basket"></i></div>
            <div class="request-form-header__copy">
              <span>SOLICITUD DE ABASTECIMIENTO</span>
              <h2>Prepara tu lista de insumos de aseo</h2>
              <p>Selecciona productos del inventario o agrega una necesidad nueva.</p>
              <div class="request-form-steps" aria-label="Pasos del formulario">
                <span class="is-active"><b>1</b> Datos</span><i class="bx bx-chevron-right"></i>
                <span><b>2</b> Productos</span><i class="bx bx-chevron-right"></i>
                <span><b>3</b> Enviar</span>
              </div>
            </div>
            <button class="request-form-close" type="button" aria-label="Cerrar formulario" @click="showForm = false"><i class="bx bx-x"></i></button>
          </header>

          <div class="modal-body request-form-body">
            <section class="request-form-section request-data-card">
              <header class="request-section-head">
                <span class="request-section-number">1</span>
                <div><small>DATOS BÁSICOS</small><h3>¿Dónde y cuándo se necesitan?</h3></div>
              </header>
              <div class="request-fields">
                <label class="request-field request-field--title"><span>Nombre de la solicitud</span><input v-model.trim="form.title" required maxlength="255"></label>
                <label class="request-field"><span>Destino / dependencia</span><input v-model.trim="form.destination" placeholder="Ej.: Internado, cocina o bodega"></label>
                <label class="request-field"><span>Fecha requerida</span><input v-model="form.needed_by" type="date"></label>
                <label class="request-field request-field--notes"><span>Observaciones <em>Opcional</em></span><textarea v-model.trim="form.notes" rows="2" placeholder="Urgencia, formato preferido u otra indicación"></textarea></label>
              </div>
            </section>

            <div class="request-builder">
              <section class="request-form-section request-catalog-card">
                <header class="request-section-head request-section-head--split">
                  <div class="request-section-title"><span class="request-section-number">2</span><div><small>SELECCIONAR</small><h3>Productos del inventario</h3></div></div>
                  <label class="request-catalog-search"><i class="bx bx-search"></i><input v-model="catalogSearch" type="search" placeholder="Buscar producto"></label>
                </header>

                <div v-if="filteredCatalog.length" class="catalog-grid request-catalog-grid">
                  <button v-for="item in filteredCatalog" :key="item.id" type="button" class="catalog-product" @click="addCatalogItem(item)">
                    <img v-if="item.photo_url" :src="item.photo_url" :alt="item.inventory_item?.name"><span v-else class="catalog-product__empty"><i class="bx bx-image"></i></span>
                    <span class="catalog-product__info"><strong>{{ item.inventory_item?.name }}</strong><small>Stock: {{ number(item.inventory_item?.stock_quantity) }} {{ unitLabel(item.inventory_item?.unit_of_measure) }}</small></span><i class="bx bx-plus-circle"></i>
                  </button>
                </div>
                <div v-else class="request-catalog-empty">
                  <span><i class="bx" :class="catalogItems.length ? 'bx-search-alt' : 'bx-package'"></i></span>
                  <div><strong>{{ catalogItems.length ? "No encontramos ese producto" : "El inventario de aseo está vacío" }}</strong><p>{{ catalogItems.length ? "Prueba otra búsqueda o agrégalo como producto nuevo." : "Puedes continuar agregando el producto manualmente a esta solicitud." }}</p></div>
                </div>

                <button type="button" class="request-add-custom" @click="addCustom"><i class="bx bx-plus"></i><span>Agregar producto fuera de inventario<small>Incluye nombre, unidad y foto de referencia</small></span></button>
              </section>

              <section class="request-form-section request-cart-card">
                <header class="request-section-head request-section-head--split">
                  <div class="request-section-title"><span class="request-section-number">3</span><div><small>REVISAR CANTIDADES</small><h3>Lista solicitada</h3></div></div>
                  <span class="request-cart-count">{{ form.items.length }}</span>
                </header>

                <div v-if="!form.items.length" class="request-cart-empty">
                  <span><i class="bx bx-list-plus"></i></span>
                  <strong>Tu lista está vacía</strong>
                  <p>Selecciona un producto del inventario o crea uno nuevo.</p>
                  <button type="button" @click="addCustom"><i class="bx bx-plus"></i> Agregar producto</button>
                </div>

                <div v-else class="request-cart-lines">
                  <article v-for="(line, index) in form.items" :key="line.key" class="request-cart-line">
                    <div class="line-photo request-line-photo">
                      <img v-if="line.preview" :src="line.preview" alt="Referencia"><i v-else class="bx bx-image-add"></i>
                      <label v-if="!line.supply_item_id" class="request-photo-action" :title="line.photo ? 'Cambiar foto' : 'Tomar o elegir foto'"><input type="file" accept="image/*" capture="environment" @change="selectPhoto(line, $event)"><i class="bx bx-camera"></i></label>
                    </div>
                    <div class="request-line-content">
                      <span class="source-tag" :class="{ 'source-tag--new': !line.supply_item_id }"><i class="bx" :class="line.supply_item_id ? 'bx-data' : 'bx-plus'"></i>{{ line.supply_item_id ? "Inventario" : "Producto nuevo" }}</span>
                      <input v-model.trim="line.name" :readonly="!!line.supply_item_id" placeholder="Nombre del producto" required>
                      <input v-model.trim="line.description" :readonly="!!line.supply_item_id" placeholder="Descripción o especificación (opcional)">
                    </div>
                    <div class="request-line-controls">
                      <label><span>Cantidad</span><input v-model.number="line.requested_quantity" type="number" min="0.01" step="0.01" required></label>
                      <label><span>Unidad</span><select v-model="line.unit" :disabled="!!line.supply_item_id"><option v-for="unit in units" :key="unit" :value="unit">{{ unitLabel(unit) }}</option></select></label>
                      <button type="button" class="remove-button" title="Quitar producto" aria-label="Quitar producto" @click="removeLine(index)"><i class="bx bx-trash"></i></button>
                    </div>
                  </article>
                </div>
              </section>
            </div>
          </div>

          <footer class="request-form-footer">
            <div class="request-form-summary"><span><i class="bx bx-check"></i></span><div><strong>{{ requestItemSummary }}</strong><small>Superadmin podrá revisar las cantidades finales.</small></div></div>
            <div class="request-form-actions"><button type="button" class="cancel-button" @click="showForm = false">Cancelar</button><button type="button" class="submit-button request-submit-button" :disabled="saving" @click="save"><i class="bx" :class="saving ? 'bx-loader-alt bx-spin' : 'bx-send'"></i>{{ saving ? "Enviando…" : "Enviar a Superadmin" }}</button></div>
          </footer>
        </div>
      </div>
      </Teleport>

      <Teleport to="body">
      <div v-if="showDetail && selected" class="modal-shell" role="dialog" aria-modal="true" aria-label="Detalle de solicitud">
        <div class="modal-panel modal-panel--detail">
          <header><div><span>{{ selected.folio }}</span><h2>{{ selected.title }}</h2><p>{{ selected.destination || "Sin destino definido" }}</p></div><button type="button" @click="showDetail = false"><i class="bx bx-x"></i></button></header>
          <div class="modal-body">
            <div class="detail-summary"><span class="status-pill" :class="statusClass(selected.status)">{{ selected.status_label }}</span><div><small>Fecha requerida</small><strong>{{ date(selected.needed_by) }}</strong></div><div><small>Solicitado por</small><strong>{{ selected.creator?.name || "—" }}</strong></div><div><small>Revisado por</small><strong>{{ selected.reviewer?.name || "Pendiente" }}</strong></div></div>
            <h3 class="detail-title">Productos solicitados</h3>
            <article v-for="item in selected.items" :key="item.id" class="detail-line"><div class="line-photo"><img v-if="item.photo_url" :src="item.photo_url" :alt="item.item_name_snapshot"><i v-else class="bx bx-package"></i></div><div><strong>{{ item.item_name_snapshot }}</strong><small>{{ item.description_snapshot || "Sin especificación adicional" }}</small></div><div class="quantity-compare"><small>Solicitada</small><b>{{ number(item.requested_quantity) }}</b></div><i class="bx bx-right-arrow-alt"></i><div class="quantity-compare final"><small>Lista final</small><b>{{ number(item.final_quantity) }} {{ unitLabel(item.unit_snapshot) }}</b></div></article>
            <div v-if="selected.review_notes || selected.notes" class="notes-card"><strong>Observaciones</strong><p>{{ selected.review_notes || selected.notes }}</p></div>
          </div>
          <footer><button type="button" class="submit-button" @click="showDetail = false">Cerrar</button></footer>
        </div>
      </div>
      </Teleport>
    </main>
  </Layout>
</template>

<style scoped>
.request-page{--navy:#173f67;--teal:#16857f;--ink:#26374d;--muted:#738196;--line:#dfe7ee;max-width:1500px;margin:auto;padding:1rem}.request-hero{position:relative;display:flex;align-items:center;justify-content:space-between;gap:2rem;overflow:hidden;padding:1.65rem 2rem;border-radius:22px;color:#fff;background:linear-gradient(125deg,#143a60,#1c5676 58%,#16857f);box-shadow:0 16px 34px rgba(25,61,96,.18)}.request-hero:after{content:"";position:absolute;right:12%;top:-110px;width:280px;height:280px;border:1px solid #ffffff22;border-radius:50%;box-shadow:0 0 0 42px #ffffff09,0 0 0 88px #ffffff06}.request-hero__copy,.primary-action{position:relative;z-index:1}.eyebrow{display:inline-flex;align-items:center;gap:.4rem;margin-bottom:.5rem;color:#bde8e4;font-size:.64rem;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.request-hero h1{margin:0;font-size:1.7rem;font-weight:800;letter-spacing:-.035em}.request-hero p{max-width:760px;margin:.4rem 0 .85rem;color:#d7e7f1;font-size:.77rem;line-height:1.55}.flow-tags{display:flex;align-items:center;gap:.5rem}.flow-tags span{display:flex;align-items:center;gap:.35rem;font-size:.64rem;font-weight:800}.flow-tags b{display:grid;place-items:center;width:22px;height:22px;border-radius:7px;background:#ffffff24}.primary-action{display:flex;align-items:center;gap:.5rem;padding:.8rem 1rem;border:0;border-radius:12px;color:var(--navy);background:#fff;font-size:.72rem;font-weight:900;box-shadow:0 8px 24px #0002}.primary-action i{font-size:1.15rem}.supply-nav{display:flex;gap:.4rem;margin:.75rem 0;padding:.35rem;border:1px solid var(--line);border-radius:13px;background:#fff}.supply-nav a{display:flex;align-items:center;gap:.4rem;padding:.56rem .8rem;border-radius:9px;color:#68768a;font-size:.67rem;font-weight:800}.supply-nav a.is-active,.supply-nav a.router-link-active{color:var(--navy);background:#eaf2f8}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem;margin-bottom:.75rem}.metrics article{display:flex;align-items:center;gap:.7rem;padding:.8rem 1rem;border:1px solid var(--line);border-radius:14px;background:#fff}.metrics small,.metrics strong{display:block}.metrics small{color:var(--muted);font-size:.61rem;font-weight:800}.metrics strong{font-size:1.13rem}.metric-icon{display:grid;place-items:center;width:39px;height:39px;border-radius:11px;font-size:1.15rem}.blue{color:#2872aa;background:#eaf4fb}.amber{color:#a66b11;background:#fff4dc}.violet{color:#7057b4;background:#f0ecfb}.green{color:#19755f;background:#e6f6f0}.request-workspace{overflow:hidden;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:0 8px 24px #1c39530f}.workspace-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.15rem;border-bottom:1px solid var(--line)}.workspace-head small,.catalog-picker header small,.selected-lines header small{color:var(--teal);font-size:.55rem;font-weight:900;letter-spacing:.1em}.workspace-head h2{margin:.1rem 0;font-size:.9rem}.workspace-head p{margin:0;color:var(--muted);font-size:.64rem}.filters{display:flex;gap:.4rem}.filters label{display:flex;align-items:center;gap:.35rem;width:245px;height:36px;padding:0 .6rem;border:1px solid #d7e0e8;border-radius:9px}.filters input{width:100%;border:0;outline:0;font-size:.66rem}.filters select{height:36px;padding:0 .55rem;border:1px solid #d7e0e8;border-radius:9px;color:#536176;background:#fff;font-size:.66rem}.filters button,.icon-button{display:grid;place-items:center;border:0;border-radius:9px;color:var(--navy);background:#eaf2f8}.filters button{width:36px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th{padding:.64rem .8rem;color:#7c8999;background:#f7f9fb;font-size:.57rem;letter-spacing:.06em;text-align:left;text-transform:uppercase;white-space:nowrap}td{padding:.7rem .8rem;border-top:1px solid #edf1f4;color:#415168;font-size:.67rem}.request-title{display:flex;flex-direction:column;gap:.08rem;max-width:410px;padding:0;border:0;background:none;text-align:left}.request-title small{color:var(--teal);font-size:.56rem;font-weight:900}.request-title strong{font-size:.7rem}.request-title span,.product-preview{overflow:hidden;color:var(--muted);font-size:.57rem;text-overflow:ellipsis;white-space:nowrap}.thumb-stack{display:flex}.thumb-stack img,.thumb-stack>span{display:grid;place-items:center;width:34px;height:34px;margin-left:-7px;border:2px solid #fff;border-radius:10px;object-fit:cover;color:#7790a5;background:#eef3f7}.thumb-stack>*:first-child{margin-left:0}.line-count,.product-preview{display:block}.status-pill{display:inline-flex;padding:.33rem .52rem;border-radius:999px;font-size:.56rem;font-weight:900;white-space:nowrap}.status-pill--submitted{color:#976413;background:#fff2d8}.status-pill--under_review{color:#5b4ca1;background:#eeeafd}.status-pill--ready_to_quote{color:#16766c;background:#def4ef}.status-pill--quoted{color:#197058;background:#dff3e9}.status-pill--rejected{color:#a74251;background:#fce7ea}.date-cell{display:flex;align-items:center;gap:.3rem;white-space:nowrap}.icon-button{width:32px;height:32px}.empty-state{padding:4rem 1rem;text-align:center}.empty-state>span{display:grid;place-items:center;width:62px;height:62px;margin:0 auto .8rem;border-radius:18px;color:var(--teal);background:#e7f6f3;font-size:1.8rem}.empty-state h3{margin:0;font-size:1rem}.empty-state p{margin:.3rem 0 1rem;color:var(--muted);font-size:.68rem}.empty-state button,.secondary-action{padding:.56rem .75rem;border:1px solid #cddae5;border-radius:9px;color:var(--navy);background:#fff;font-size:.65rem;font-weight:900}.modal-shell{position:fixed;z-index:1060;inset:0;display:grid;place-items:center;padding:1rem;background:#0d1d2f94;backdrop-filter:blur(5px)}.modal-panel{display:flex;flex-direction:column;width:min(1180px,96vw);max-height:94vh;border-radius:22px;background:#f7f9fb;box-shadow:0 26px 70px #0b19294d}.modal-panel--detail{width:min(900px,96vw)}.modal-panel>header{display:flex;justify-content:space-between;padding:1.05rem 1.25rem;border-bottom:1px solid var(--line);border-radius:22px 22px 0 0;background:#fff}.modal-panel>header span{color:var(--teal);font-size:.57rem;font-weight:900;letter-spacing:.1em}.modal-panel>header h2{margin:.1rem 0;font-size:1rem}.modal-panel>header p{margin:0;color:var(--muted);font-size:.64rem}.modal-panel>header button{display:grid;place-items:center;width:34px;height:34px;border:0;border-radius:9px;background:#eef2f5;font-size:1.2rem}.modal-body{overflow:auto;padding:1rem 1.2rem}.general-card,.catalog-picker,.selected-lines{padding:.8rem;border:1px solid var(--line);border-radius:14px;background:#fff;margin-bottom:.7rem}.field-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:.6rem}.field-grid label,.request-line label{display:flex;flex-direction:column;gap:.28rem}.field-grid label.full{grid-column:1/-1}.field-grid span,.request-line label span{color:#5c6a7d;font-size:.57rem;font-weight:900}.field-grid input,.field-grid textarea,.request-line input,.request-line select{width:100%;border:1px solid #d5dfe8;border-radius:8px;color:#33445a;background:#fff;font-size:.66rem}.field-grid input,.request-line input,.request-line select{height:36px;padding:0 .6rem}.field-grid textarea{padding:.5rem .6rem;resize:vertical}.catalog-picker>header,.selected-lines>header{display:flex;align-items:center;justify-content:space-between;gap:.7rem;margin-bottom:.6rem}.catalog-picker h3,.selected-lines h3{margin:.08rem 0;font-size:.78rem}.catalog-picker>header label{display:flex;align-items:center;gap:.3rem;width:240px;height:34px;padding:0 .55rem;border:1px solid #d8e1e8;border-radius:8px}.catalog-picker>header input{width:100%;border:0;outline:0;font-size:.64rem}.catalog-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.45rem;max-height:180px;overflow:auto;margin-bottom:.6rem}.catalog-product{display:grid;grid-template-columns:40px 1fr 18px;align-items:center;gap:.45rem;padding:.45rem;border:1px solid #e0e7ed;border-radius:10px;background:#fff;text-align:left}.catalog-product:hover{border-color:#76aaa5;background:#f3fbf9}.catalog-product img,.catalog-product__empty{display:grid;place-items:center;width:40px;height:40px;border-radius:9px;object-fit:cover;color:#8796a7;background:#edf2f5}.catalog-product__info{min-width:0}.catalog-product strong,.catalog-product small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.catalog-product strong{font-size:.62rem}.catalog-product small{color:var(--muted);font-size:.52rem}.catalog-product>i{color:var(--teal)}.catalog-empty{grid-column:1/-1;text-align:center;font-size:.64rem}.secondary-action{display:inline-flex;align-items:center;gap:.3rem}.selected-lines h3 span{display:inline-grid;place-items:center;width:20px;height:20px;border-radius:7px;color:#fff;background:var(--navy);font-size:.57rem}.lines-empty{display:flex;justify-content:center;gap:.4rem;padding:1.3rem;color:var(--muted);background:#f8fafb;font-size:.64rem}.request-line{display:grid;grid-template-columns:52px minmax(260px,1fr) 90px 110px 66px 34px;align-items:end;gap:.5rem;padding:.62rem 0;border-top:1px solid #edf1f4}.line-photo{display:grid;place-items:center;width:48px;height:48px;border-radius:10px;overflow:hidden;color:#8091a4;background:#edf2f5;font-size:1.2rem}.line-photo img{width:100%;height:100%;object-fit:cover}.line-main{display:grid;grid-template-columns:1fr 1fr;gap:.3rem}.line-main .source-tag{grid-column:1/-1}.source-tag{display:inline-flex;align-items:center;gap:.22rem;width:max-content;padding:.18rem .38rem;border-radius:6px;color:#2b6d64;background:#e5f4f0;font-size:.47rem;font-weight:900;text-transform:uppercase}.source-tag--new{color:#725a9c;background:#f0ebf8}.photo-upload{align-items:center!important;justify-content:center;height:36px;margin:0;border:1px dashed #a9bac9;border-radius:8px;color:var(--navy);cursor:pointer}.photo-upload input{display:none}.photo-upload span{font-size:.52rem!important}.remove-button{display:grid;place-items:center;width:34px;height:36px;border:0;border-radius:8px;color:#b34e5e;background:#fbecee}.modal-panel>footer{display:flex;justify-content:flex-end;gap:.5rem;padding:.8rem 1.2rem;border-top:1px solid var(--line);border-radius:0 0 22px 22px;background:#fff}.cancel-button,.submit-button{display:inline-flex;align-items:center;gap:.35rem;padding:.62rem .82rem;border-radius:9px;font-size:.65rem;font-weight:900}.cancel-button{border:1px solid #d8e1e8;background:#fff}.submit-button{border:0;color:#fff;background:linear-gradient(135deg,var(--navy),#28759b)}.detail-summary{display:grid;grid-template-columns:auto repeat(3,1fr);align-items:center;gap:.6rem;padding:.7rem;border:1px solid var(--line);border-radius:12px;background:#fff}.detail-summary small,.detail-summary strong{display:block}.detail-summary small{color:var(--muted);font-size:.52rem}.detail-summary strong{font-size:.63rem}.detail-title{margin:1rem 0 .5rem;font-size:.74rem}.detail-line{display:grid;grid-template-columns:52px 1fr 78px 18px 142px;align-items:center;gap:.55rem;padding:.55rem;border:1px solid var(--line);border-radius:11px;background:#fff;margin-bottom:.4rem}.detail-line>div:nth-child(2) strong,.detail-line>div:nth-child(2) small,.quantity-compare small,.quantity-compare b{display:block}.detail-line>div:nth-child(2) strong{font-size:.66rem}.detail-line>div:nth-child(2) small,.quantity-compare small{color:var(--muted);font-size:.54rem}.quantity-compare b{font-size:.65rem}.quantity-compare.final{padding:.38rem .5rem;border-radius:8px;color:#187064;background:#e2f4ef}.notes-card{margin-top:.7rem;padding:.7rem;border-left:3px solid var(--teal);border-radius:8px;background:#eef7f6}.notes-card strong,.notes-card p{font-size:.6rem}.notes-card p{margin:.2rem 0 0}.bx-spin{animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:980px){.metrics{grid-template-columns:repeat(2,1fr)}.workspace-head{align-items:flex-start;flex-direction:column}.catalog-grid{grid-template-columns:repeat(2,1fr)}.request-line{grid-template-columns:50px 1fr 90px}.request-line .line-main{grid-column:2/-1}.detail-summary{grid-template-columns:repeat(2,1fr)}}
@media(max-width:680px){.request-page{padding:.5rem}.request-hero{align-items:flex-start;flex-direction:column;padding:1.2rem}.request-hero h1{font-size:1.35rem}.primary-action{width:100%;justify-content:center}.supply-nav{overflow:auto}.supply-nav a{white-space:nowrap}.metrics{grid-template-columns:1fr 1fr}.metrics article{padding:.6rem}.workspace-head{padding:.8rem}.filters{width:100%;flex-wrap:wrap}.filters label{width:100%}.filters select{flex:1}.catalog-picker>header{align-items:flex-start;flex-direction:column}.catalog-picker>header label{width:100%}.catalog-grid{grid-template-columns:1fr}.field-grid{grid-template-columns:1fr}.field-grid label.full{grid-column:auto}.request-line{grid-template-columns:48px 1fr}.request-line .line-main,.request-line label,.request-line .remove-button{grid-column:2}.modal-shell{padding:.4rem}.detail-summary{grid-template-columns:1fr}.detail-line{grid-template-columns:50px 1fr}.detail-line .quantity-compare,.detail-line>i{grid-column:2}.table-wrap table{min-width:900px}}
.modal-shell{--navy:#173f67;--teal:#16857f;--ink:#26374d;--muted:#738196;--line:#dfe7ee;z-index:12000}

/* Solicitud de abastecimiento: flujo compacto y guiado */
.request-form-shell{padding:1.25rem;background:rgba(10,26,43,.72);backdrop-filter:blur(8px)}
.request-form-modal{width:min(1120px,96vw);max-height:92vh;overflow:hidden;border:1px solid rgba(255,255,255,.28);border-radius:24px;background:#f4f7f9;box-shadow:0 32px 90px rgba(5,23,39,.42)}
.request-form-modal>.request-form-header{position:relative;display:grid;grid-template-columns:52px minmax(0,1fr) 40px;align-items:start;gap:1rem;padding:1.05rem 1.2rem;border:0;border-radius:23px 23px 0 0;color:#fff;background:linear-gradient(120deg,#153e65 0%,#1c5d76 64%,#16857f 100%)}
.request-form-modal>.request-form-header:after{position:absolute;right:9%;top:-115px;width:250px;height:250px;border:1px solid rgba(255,255,255,.13);border-radius:50%;box-shadow:0 0 0 38px rgba(255,255,255,.04),0 0 0 78px rgba(255,255,255,.025);content:"";pointer-events:none}
.request-form-header__icon{position:relative;z-index:1;display:grid;place-items:center;width:52px;height:52px;border:1px solid rgba(255,255,255,.22);border-radius:15px;color:#d4f3ef;background:rgba(255,255,255,.12);font-size:1.45rem;box-shadow:inset 0 1px 0 rgba(255,255,255,.16)}
.request-form-header__copy{position:relative;z-index:1;min-width:0}
.request-form-modal>.request-form-header .request-form-header__copy>span{display:block;margin-bottom:.12rem;color:#aee3dd;font-size:.59rem;font-weight:900;letter-spacing:.13em}
.request-form-modal>.request-form-header h2{margin:0;color:#fff;font-size:1.12rem;font-weight:800;letter-spacing:-.025em}
.request-form-modal>.request-form-header p{margin:.18rem 0 .65rem;color:#d3e4ed;font-size:.66rem}
.request-form-modal>.request-form-header .request-form-close{position:relative;z-index:2;display:grid;place-items:center;width:38px;height:38px;border:1px solid rgba(255,255,255,.18);border-radius:11px;color:#fff;background:rgba(255,255,255,.1);font-size:1.2rem;transition:.2s ease}
.request-form-modal>.request-form-header .request-form-close:hover{background:rgba(255,255,255,.2);transform:rotate(4deg)}
.request-form-steps{display:flex;align-items:center;gap:.4rem}
.request-form-modal>.request-form-header .request-form-steps span{display:inline-flex;align-items:center;gap:.34rem;color:#c8d9e3;font-size:.57rem;font-weight:800;letter-spacing:0}
.request-form-steps span b{display:grid;place-items:center;width:20px;height:20px;border:1px solid rgba(255,255,255,.2);border-radius:7px;color:#d8e5ec;background:rgba(255,255,255,.1);font-size:.53rem}
.request-form-steps span.is-active{color:#fff}.request-form-steps span.is-active b{border-color:#80d8cf;color:#135867;background:#bdece6}
.request-form-steps>i{color:rgba(255,255,255,.36);font-size:.8rem}

.request-form-body{display:flex;flex-direction:column;gap:.75rem;padding:.8rem;overflow:auto}
.request-form-section{border:1px solid #dfe7ed;border-radius:15px;background:#fff;box-shadow:0 5px 16px rgba(22,57,81,.035)}
.request-data-card{padding:.72rem .82rem}
.request-section-head{display:flex;align-items:center;gap:.55rem;margin-bottom:.62rem}
.request-section-title{display:flex;align-items:center;gap:.55rem;min-width:0}
.request-section-head--split{justify-content:space-between}
.request-section-number{display:grid;flex:0 0 auto;place-items:center;width:29px;height:29px;border-radius:9px;color:#fff;background:linear-gradient(135deg,#1b5577,#16857f);font-size:.64rem;font-weight:900;box-shadow:0 5px 11px rgba(22,133,127,.16)}
.request-section-head small{display:block;color:#16857f;font-size:.51rem;font-weight:900;letter-spacing:.11em}
.request-section-head h3{margin:.05rem 0 0;color:#293a50;font-size:.75rem;font-weight:800}
.request-fields{display:grid;grid-template-columns:1.7fr 1fr .8fr;gap:.55rem}
.request-field{display:flex;min-width:0;flex-direction:column;gap:.28rem;margin:0}
.request-field>span{display:flex;justify-content:space-between;color:#5b6b7e;font-size:.56rem;font-weight:900}
.request-field em{color:#9aa6b3;font-size:.49rem;font-style:normal;font-weight:700}
.request-field input,.request-field textarea{width:100%;border:1px solid #d5dfe7;border-radius:9px;outline:0;color:#304258;background:#fbfcfd;font-size:.65rem;transition:border-color .18s ease,box-shadow .18s ease,background .18s ease}
.request-field input{height:36px;padding:0 .62rem}.request-field textarea{min-height:52px;padding:.5rem .62rem;resize:vertical}
.request-field input:focus,.request-field textarea:focus,.request-catalog-search:focus-within,.request-line-content input:focus,.request-line-controls input:focus,.request-line-controls select:focus{border-color:#55a39d;background:#fff;box-shadow:0 0 0 3px rgba(22,133,127,.1)}
.request-field--notes{grid-column:1/-1}

.request-builder{display:grid;grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr);align-items:stretch;gap:.75rem;min-height:285px}
.request-catalog-card,.request-cart-card{display:flex;min-width:0;flex-direction:column;padding:.78rem}
.request-catalog-search{display:flex;align-items:center;gap:.35rem;width:174px;height:33px;margin:0;padding:0 .55rem;border:1px solid #d6e0e7;border-radius:9px;color:#718095;background:#fbfcfd;transition:.18s ease}
.request-catalog-search input{min-width:0;width:100%;border:0;outline:0;background:transparent;font-size:.59rem}
.request-catalog-grid{grid-template-columns:repeat(2,minmax(0,1fr));max-height:198px;margin:0 0 .55rem;padding-right:.18rem}
.request-catalog-card .catalog-product{position:relative;grid-template-columns:42px minmax(0,1fr) 18px;min-height:58px;padding:.42rem;border-color:#e1e8ed;transition:border-color .18s ease,background .18s ease,transform .18s ease}
.request-catalog-card .catalog-product:hover{border-color:#72b5ae;background:#f4fbfa;transform:translateY(-1px)}
.request-catalog-card .catalog-product:focus-visible{outline:3px solid rgba(22,133,127,.16);outline-offset:1px}
.request-catalog-empty{display:flex;align-items:center;justify-content:center;gap:.75rem;min-height:118px;margin-bottom:.55rem;padding:1rem;border:1px dashed #cfdee4;border-radius:12px;background:linear-gradient(145deg,#f9fbfc,#f2f8f7)}
.request-catalog-empty>span{display:grid;flex:0 0 auto;place-items:center;width:46px;height:46px;border-radius:13px;color:#16857f;background:#e2f3f0;font-size:1.25rem}
.request-catalog-empty strong{display:block;color:#304258;font-size:.68rem}.request-catalog-empty p{max-width:260px;margin:.18rem 0 0;color:#758497;font-size:.57rem;line-height:1.45}
.request-add-custom{display:flex;align-items:center;justify-content:center;gap:.55rem;width:100%;min-height:46px;margin-top:auto;padding:.45rem .65rem;border:1px solid #bcd2dc;border-radius:11px;color:#174e70;background:#f7fbfc;text-align:left;transition:.18s ease}
.request-add-custom>i{display:grid;place-items:center;width:26px;height:26px;border-radius:8px;color:#fff;background:#16857f;font-size:.9rem}.request-add-custom span{font-size:.61rem;font-weight:900}.request-add-custom small{display:block;margin-top:.06rem;color:#7d8b9d;font-size:.5rem;font-weight:600}.request-add-custom:hover{border-color:#72a9b5;background:#eff8f8}

.request-cart-count{display:grid;place-items:center;min-width:28px;height:28px;padding:0 .45rem;border-radius:9px;color:#fff;background:#173f67;font-size:.6rem;font-weight:900}
.request-cart-empty{display:flex;flex:1;min-height:198px;align-items:center;justify-content:center;flex-direction:column;padding:1.15rem;border:1px dashed #d3dfe7;border-radius:12px;background:#f8fafb;text-align:center}
.request-cart-empty>span{display:grid;place-items:center;width:50px;height:50px;margin-bottom:.48rem;border-radius:15px;color:#476d8b;background:#e8f0f6;font-size:1.35rem}.request-cart-empty strong{color:#34465c;font-size:.7rem}.request-cart-empty p{margin:.18rem 0 .65rem;color:#7b899a;font-size:.57rem}.request-cart-empty button{display:inline-flex;align-items:center;gap:.28rem;padding:.48rem .68rem;border:1px solid #cad8e2;border-radius:9px;color:#174e70;background:#fff;font-size:.58rem;font-weight:900}
.request-cart-lines{display:flex;max-height:258px;overflow:auto;flex-direction:column;gap:.5rem;padding-right:.12rem}
.request-cart-line{display:grid;grid-template-columns:48px minmax(0,1fr) 180px;align-items:center;gap:.55rem;padding:.55rem;border:1px solid #e0e8ed;border-radius:12px;background:#fbfcfd}
.request-line-photo{position:relative;width:46px;height:46px;overflow:visible;border:1px solid #e2e9ee}
.request-line-photo img{border-radius:9px}.request-photo-action{position:absolute;right:-5px;bottom:-5px;display:grid!important;place-items:center;width:21px;height:21px;border:2px solid #fff;border-radius:7px;color:#fff;background:#16857f;cursor:pointer;box-shadow:0 3px 8px rgba(18,57,70,.22)}
.request-photo-action input{display:none}.request-photo-action i{font-size:.65rem}
.request-line-content{display:grid;min-width:0;grid-template-columns:1fr;gap:.26rem}
.request-line-content .source-tag{margin-bottom:.02rem}
.request-line-content input,.request-line-controls input,.request-line-controls select{width:100%;height:31px;border:1px solid #d6e0e7;border-radius:8px;outline:0;color:#34465b;background:#fff;font-size:.58rem}
.request-line-content input{padding:0 .48rem}.request-line-content input[readonly]{border-color:transparent;background:transparent;font-weight:800}.request-line-content input[readonly]+input{color:#738196;font-weight:500}
.request-line-controls{display:grid;grid-template-columns:64px minmax(78px,1fr) 32px;align-items:end;gap:.35rem}
.request-line-controls label{display:flex;min-width:0;flex-direction:column;gap:.22rem;margin:0}.request-line-controls label>span{color:#647286;font-size:.49rem;font-weight:900}.request-line-controls input,.request-line-controls select{padding:0 .38rem}.request-line-controls .remove-button{width:32px;height:31px}

.request-form-modal>.request-form-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.7rem 1rem;border-color:#dfe7ed;background:#fff;box-shadow:0 -8px 24px rgba(28,61,84,.045)}
.request-form-summary{display:flex;align-items:center;gap:.48rem;min-width:0}.request-form-summary>span{display:grid;flex:0 0 auto;place-items:center;width:31px;height:31px;border-radius:9px;color:#16857f;background:#e4f4f1}.request-form-summary strong,.request-form-summary small{display:block}.request-form-summary strong{color:#34465b;font-size:.62rem}.request-form-summary small{color:#7b899a;font-size:.51rem}
.request-form-actions{display:flex;align-items:center;gap:.5rem;flex:0 0 auto}.request-form-actions .cancel-button,.request-form-actions .submit-button{min-height:38px;padding:.58rem .85rem}.request-submit-button{min-width:166px;justify-content:center;background:linear-gradient(135deg,#1a5277,#16857f);box-shadow:0 7px 16px rgba(22,100,111,.18)}.request-submit-button:hover:not(:disabled){filter:brightness(1.06);transform:translateY(-1px)}.request-submit-button:disabled{opacity:.65;cursor:wait}

@media(max-width:900px){.request-form-modal{width:min(760px,96vw)}.request-builder{grid-template-columns:1fr}.request-fields{grid-template-columns:1.4fr 1fr}.request-field--title,.request-field--notes{grid-column:1/-1}.request-cart-lines{max-height:none}.request-form-body{overflow:auto}.request-cart-line{grid-template-columns:48px minmax(0,1fr) 180px}}
@media(max-width:620px){.request-form-shell{align-items:end;padding:0}.request-form-modal{width:100%;max-height:96vh;border-radius:22px 22px 0 0}.request-form-modal>.request-form-header{grid-template-columns:42px minmax(0,1fr) 34px;gap:.65rem;padding:.85rem;border-radius:21px 21px 0 0}.request-form-header__icon{width:42px;height:42px;border-radius:12px;font-size:1.15rem}.request-form-modal>.request-form-header h2{font-size:.94rem}.request-form-modal>.request-form-header p{display:none}.request-form-modal>.request-form-header .request-form-close{width:34px;height:34px}.request-form-steps{margin-top:.45rem}.request-form-steps>i{display:none}.request-form-modal>.request-form-header .request-form-steps span{font-size:.51rem}.request-form-body{gap:.6rem;padding:.6rem}.request-data-card,.request-catalog-card,.request-cart-card{padding:.65rem}.request-fields{grid-template-columns:1fr}.request-field--title,.request-field--notes{grid-column:auto}.request-section-head--split{align-items:flex-start;flex-direction:column}.request-section-head--split .request-section-title{width:100%}.request-catalog-search{width:100%}.request-catalog-grid{grid-template-columns:1fr}.request-cart-line{grid-template-columns:44px minmax(0,1fr)}.request-line-controls{grid-column:2;grid-template-columns:70px minmax(90px,1fr) 32px}.request-form-modal>.request-form-footer{align-items:stretch;flex-direction:column;padding:.65rem}.request-form-summary{display:none}.request-form-actions{display:grid;grid-template-columns:.75fr 1.25fr;width:100%}.request-form-actions .cancel-button,.request-form-actions .submit-button{justify-content:center;margin:0}.request-submit-button{min-width:0}}
@media(max-height:760px) and (min-width:901px){.request-form-modal>.request-form-header{padding:.72rem 1.2rem}.request-form-header__icon{width:46px;height:46px}.request-form-modal>.request-form-header p{display:none}.request-form-steps{margin-top:.42rem}.request-form-body{gap:.58rem;padding:.62rem}.request-data-card,.request-catalog-card,.request-cart-card{padding:.62rem}.request-data-card .request-section-head{margin-bottom:.42rem}.request-field textarea{min-height:40px;height:40px}.request-builder{min-height:246px}.request-catalog-empty{min-height:94px;padding:.7rem}.request-cart-empty{min-height:164px;padding:.8rem}.request-add-custom{min-height:40px}.request-cart-lines{max-height:220px}.request-form-modal>.request-form-footer{padding:.55rem .85rem}}
</style>
