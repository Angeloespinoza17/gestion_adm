<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { downloadSupplyRequestQuote } from "../../utils/supply-request-quote-pdf";

const lineKey = () => `${Date.now()}-${Math.random()}`;

export default {
  components: { Layout, LoadingState },
  data: () => ({
    loading: false,
    saving: false,
    exportingId: null,
    requests: [],
    statuses: [],
    catalogItems: [],
    catalogToAdd: "",
    summary: { total: 0, submitted: 0, in_progress: 0, quoted: 0 },
    meta: { current_page: 1, last_page: 1, total: 0 },
    filters: { search: "", status: "" },
    showReview: false,
    selected: null,
    form: { title: "", destination: "", needed_by: "", status: "under_review", review_notes: "", items: [] },
    units: ["unidad", "litro", "kilogramo", "saco", "cilindro", "metro_cubico", "rollo", "caja", "bidon"],
  }),
  mounted() { this.loadAll(); },
  beforeUnmount() { this.clearPreviews(); },
  methods: {
    async loadAll(page = 1) {
      this.loading = true;
      try {
        const [requests, items] = await Promise.all([
          axios.get("/api/superadmin/supply-requests", { params: { page, search: this.filters.search || undefined, status: this.filters.status || undefined } }),
          axios.get("/api/supplies/items", { params: { section: "cleaning", per_page: 100 } }),
        ]);
        this.requests = requests.data.data || [];
        this.statuses = requests.data.statuses || [];
        this.summary = requests.data.summary || this.summary;
        this.meta = requests.data.meta || this.meta;
        this.catalogItems = (items.data.data || []).filter((item) => item.inventory_item?.active !== false);
      } catch (error) { this.alertError(error); } finally { this.loading = false; }
    },
    async openReview(request) {
      this.clearPreviews();
      try {
        const { data } = await axios.get(`/api/superadmin/supply-requests/${request.id}`);
        this.selected = data.data;
        this.form = {
          title: this.selected.title || "",
          destination: this.selected.destination || "",
          needed_by: String(this.selected.needed_by || "").slice(0, 10),
          status: this.selected.status === "submitted" ? "under_review" : this.selected.status,
          review_notes: this.selected.review_notes || "",
          items: (this.selected.items || []).map((item) => ({
            key: lineKey(), id: item.id, supply_item_id: item.supply_item_id,
            name: item.item_name_snapshot, description: item.description_snapshot || "", unit: item.unit_snapshot,
            requested_quantity: Number(item.requested_quantity), final_quantity: Number(item.final_quantity),
            photo: null, preview: item.photo_url || null,
          })),
        };
        this.catalogToAdd = "";
        this.showReview = true;
      } catch (error) { this.alertError(error); }
    },
    addCatalogLine() {
      const item = this.catalogItems.find((candidate) => Number(candidate.id) === Number(this.catalogToAdd));
      if (!item) return;
      this.form.items.push({
        key: lineKey(), id: null, supply_item_id: item.id,
        name: item.inventory_item?.name || "", description: item.inventory_item?.description || "",
        unit: item.inventory_item?.unit_of_measure || "unidad", requested_quantity: 1, final_quantity: 1,
        photo: null, preview: item.photo_url || null,
      });
      this.catalogToAdd = "";
    },
    addCustomLine() {
      this.form.items.push({ key: lineKey(), id: null, supply_item_id: null, name: "", description: "", unit: "unidad", requested_quantity: 1, final_quantity: 1, photo: null, preview: null });
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
    async save(exportAfter = false) {
      if (!this.form.items.length) {
        Swal.fire({ icon: "info", title: "Lista final vacía", text: "Agrega al menos un producto antes de guardar." });
        return;
      }
      this.saving = true;
      try {
        if (exportAfter) this.form.status = "quoted";
        const payload = new FormData();
        ["title", "destination", "needed_by", "status", "review_notes"].forEach((key) => { if (this.form[key] !== "") payload.append(key, this.form[key]); });
        this.form.items.forEach((line, index) => {
          if (line.id) payload.append(`items[${index}][id]`, line.id);
          if (line.supply_item_id) payload.append(`items[${index}][supply_item_id]`, line.supply_item_id);
          payload.append(`items[${index}][name]`, line.name || "");
          payload.append(`items[${index}][description]`, line.description || "");
          payload.append(`items[${index}][unit]`, line.unit || "unidad");
          payload.append(`items[${index}][requested_quantity]`, line.requested_quantity || line.final_quantity);
          payload.append(`items[${index}][final_quantity]`, line.final_quantity);
          if (line.photo) payload.append(`items[${index}][photo]`, line.photo);
        });
        const { data } = await axios.post(`/api/superadmin/supply-requests/${this.selected.id}`, payload);
        this.selected = data.data;
        this.showReview = false;
        this.clearPreviews();
        await this.loadAll(this.meta.current_page);
        if (exportAfter) {
          await downloadSupplyRequestQuote(data.data, data.generated_at);
          Swal.fire({ icon: "success", title: "Cotización emitida", text: "Se guardó la lista final y se descargó el PDF con sus fotografías.", timer: 2600, showConfirmButton: false });
        } else {
          Swal.fire({ icon: "success", title: "Revisión guardada", text: data.message, timer: 2200, showConfirmButton: false });
        }
      } catch (error) { this.alertError(error); } finally { this.saving = false; }
    },
    async exportRequest(request) {
      this.exportingId = request.id;
      try {
        const { data } = await axios.get(`/api/superadmin/supply-requests/${request.id}`);
        await downloadSupplyRequestQuote(data.data, data.generated_at);
      } catch (error) { this.alertError(error); } finally { this.exportingId = null; }
    },
    statusClass(status) { return `status-pill--${status || "submitted"}`; },
    number(value) { return new Intl.NumberFormat("es-CL", { maximumFractionDigits: 2 }).format(Number(value || 0)); },
    unitLabel(value) { return ({ metro_cubico: "m³", bidon: "bidón" }[value] || String(value || "").replaceAll("_", " ")); },
    date(value) { return value ? new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString("es-CL") : "Sin fecha"; },
    dateTime(value) { return value ? new Date(value).toLocaleString("es-CL", { dateStyle: "medium", timeStyle: "short" }) : "—"; },
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
    <main class="admin-requests">
      <section class="admin-hero">
        <div><span class="admin-kicker"><i class="bx bx-lock-alt"></i> Herramienta exclusiva de Superadmin</span><h1>Revisión de solicitudes de abastecimiento</h1><p>Ajusta la lista y las cantidades finales antes de emitir un PDF de cotización con referencias fotográficas.</p><div class="admin-trust"><span><i class="bx bx-history"></i> Estados trazables</span><span><i class="bx bx-edit"></i> Lista final editable</span><span><i class="bx bx-file"></i> PDF listo para cotizar</span></div></div>
        <button type="button" class="refresh-button" :disabled="loading" @click="loadAll(meta.current_page)"><i class="bx bx-refresh" :class="{ 'bx-spin': loading }"></i>Actualizar</button>
      </section>

      <section class="admin-metrics">
        <article><span class="metric-icon blue"><i class="bx bx-layer"></i></span><div><small>Total recibidas</small><strong>{{ summary.total || 0 }}</strong></div></article>
        <article><span class="metric-icon amber"><i class="bx bx-bell"></i></span><div><small>Por revisar</small><strong>{{ summary.submitted || 0 }}</strong></div></article>
        <article><span class="metric-icon violet"><i class="bx bx-edit-alt"></i></span><div><small>En proceso</small><strong>{{ summary.in_progress || 0 }}</strong></div></article>
        <article><span class="metric-icon green"><i class="bx bx-check-shield"></i></span><div><small>PDF emitidos</small><strong>{{ summary.quoted || 0 }}</strong></div></article>
      </section>

      <section class="admin-workspace">
        <header class="workspace-head"><div><small>BANDEJA DE REVISIÓN</small><h2>Solicitudes institucionales</h2><p>Las cantidades iniciales nunca se reemplazan: la edición queda en la columna final.</p></div><div class="filters"><label><i class="bx bx-search"></i><input v-model="filters.search" type="search" placeholder="Folio, responsable o destino" @keyup.enter="loadAll(1)"></label><select v-model="filters.status" @change="loadAll(1)"><option value="">Todos los estados</option><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select></div></header>
        <LoadingState v-if="loading" />
        <div v-else-if="!requests.length" class="empty-state"><i class="bx bx-inbox"></i><h3>No hay solicitudes para revisar</h3><p>Cuando Abastecimiento envíe una solicitud aparecerá en esta bandeja.</p></div>
        <div v-else class="table-wrap"><table><thead><tr><th>Solicitud</th><th>Fotografías</th><th>Lista</th><th>Estado</th><th>Fecha requerida</th><th>Acciones</th></tr></thead><tbody><tr v-for="request in requests" :key="request.id">
          <td><button class="request-title" type="button" @click="openReview(request)"><small>{{ request.folio }}</small><strong>{{ request.title }}</strong><span>{{ request.creator?.name || "Sin responsable" }} · {{ request.destination || "Sin destino" }}</span></button></td>
          <td><div class="thumb-stack"><template v-for="item in request.items.slice(0, 3)" :key="item.id"><img v-if="item.photo_url" :src="item.photo_url" :alt="item.item_name_snapshot"><span v-else><i class="bx bx-package"></i></span></template></div></td>
          <td><strong>{{ request.items_count }} producto{{ request.items_count === 1 ? "" : "s" }}</strong><small class="product-preview">{{ request.items.slice(0, 2).map((item) => item.item_name_snapshot).join(" · ") }}</small></td>
          <td><span class="status-pill" :class="statusClass(request.status)">{{ request.status_label }}</span></td>
          <td><span class="date-cell"><i class="bx bx-calendar"></i>{{ date(request.needed_by) }}</span></td>
          <td><div class="row-actions"><button type="button" class="review-button" @click="openReview(request)"><i class="bx bx-edit"></i> Revisar</button><button type="button" class="pdf-button" :disabled="exportingId === request.id" @click="exportRequest(request)"><i class="bx" :class="exportingId === request.id ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'"></i> PDF</button></div></td>
        </tr></tbody></table></div>
      </section>

      <Teleport to="body">
      <div v-if="showReview && selected" class="modal-shell" role="dialog" aria-modal="true" aria-label="Revisar solicitud de abastecimiento">
        <div class="review-panel">
          <header><div><span>{{ selected.folio }} · REVISIÓN SUPERADMIN</span><h2>Preparar lista final de cotización</h2><p>Compara lo solicitado, modifica las cantidades finales y conserva las fotos como referencia.</p></div><button type="button" @click="showReview = false"><i class="bx bx-x"></i></button></header>
          <div class="review-body">
            <section class="review-card review-general"><div class="field-grid"><label><span>Nombre de solicitud</span><input v-model.trim="form.title"></label><label><span>Destino</span><input v-model.trim="form.destination"></label><label><span>Fecha requerida</span><input v-model="form.needed_by" type="date"></label><label><span>Estado</span><select v-model="form.status"><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select></label><label class="wide"><span>Indicaciones para cotización</span><textarea v-model.trim="form.review_notes" rows="2" placeholder="Formato, marcas aceptadas, entrega u observaciones"></textarea></label></div></section>

            <section class="final-list">
              <header><div><small>LISTA FINAL</small><h3>Productos y cantidades <span>{{ form.items.length }}</span></h3></div><div class="add-tools"><select v-model="catalogToAdd"><option value="">Seleccionar desde inventario…</option><option v-for="item in catalogItems" :key="item.id" :value="item.id">{{ item.inventory_item?.name }}</option></select><button type="button" :disabled="!catalogToAdd" @click="addCatalogLine"><i class="bx bx-plus"></i> Inventario</button><button type="button" @click="addCustomLine"><i class="bx bx-plus"></i> Otro producto</button></div></header>
              <div class="line-head"><span>Referencia</span><span>Producto / especificación</span><span>Solicitada</span><span>Cantidad final</span><span>Unidad</span><span></span></div>
              <article v-for="(line, index) in form.items" :key="line.key" class="review-line">
                <div class="line-photo"><img v-if="line.preview" :src="line.preview" alt="Referencia"><i v-else class="bx bx-image"></i><label><input type="file" accept="image/*" capture="environment" @change="selectPhoto(line, $event)"><i class="bx bx-camera"></i></label></div>
                <div class="line-name"><input v-model.trim="line.name" placeholder="Nombre del producto"><input v-model.trim="line.description" placeholder="Especificación opcional"></div>
                <div class="original-quantity"><small>Original</small><strong>{{ number(line.requested_quantity) }}</strong></div>
                <label class="final-quantity"><span>Final</span><input v-model.number="line.final_quantity" type="number" min="0.01" step="0.01"></label>
                <select v-model="line.unit"><option v-for="unit in units" :key="unit" :value="unit">{{ unitLabel(unit) }}</option></select>
                <button type="button" class="remove-button" @click="removeLine(index)"><i class="bx bx-trash"></i></button>
              </article>
            </section>

            <section v-if="selected.status_logs?.length" class="status-history"><header><small>TRAZABILIDAD</small><h3>Historial de estados</h3></header><div><article v-for="log in selected.status_logs" :key="log.id"><span></span><div><strong>{{ statuses.find((item) => item.value === log.to_status)?.label || log.to_status }}</strong><small>{{ dateTime(log.created_at) }} · {{ log.actor?.name || "Sistema" }}</small><p v-if="log.note">{{ log.note }}</p></div></article></div></section>
          </div>
          <footer><button type="button" class="cancel-button" @click="showReview = false">Cancelar</button><button type="button" class="save-button" :disabled="saving" @click="save(false)"><i class="bx bx-save"></i> Guardar revisión</button><button type="button" class="quote-button" :disabled="saving" @click="save(true)"><i class="bx" :class="saving ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'"></i> Guardar y generar PDF</button></footer>
        </div>
      </div>
      </Teleport>
    </main>
  </Layout>
</template>

<style scoped>
.admin-requests{--navy:#182e4b;--purple:#5952a7;--ink:#28374d;--muted:#758297;--line:#dfe6ed;max-width:1540px;margin:auto;padding:1rem}.admin-hero{display:flex;align-items:center;justify-content:space-between;gap:2rem;padding:1.6rem 1.9rem;border-radius:22px;color:#fff;background:radial-gradient(circle at 78% -20%,#8a79df66,transparent 34%),linear-gradient(120deg,#172b47,#233f61 60%,#514993);box-shadow:0 16px 34px #26395a2b}.admin-kicker{display:inline-flex;align-items:center;gap:.4rem;color:#d3cffb;font-size:.63rem;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.admin-hero h1{margin:.45rem 0 .35rem;font-size:1.6rem;font-weight:800;letter-spacing:-.03em}.admin-hero p{max-width:780px;margin:0;color:#dbe3ef;font-size:.76rem}.admin-trust{display:flex;gap:.5rem;margin-top:.85rem}.admin-trust span{display:flex;align-items:center;gap:.3rem;padding:.3rem .5rem;border:1px solid #ffffff1f;border-radius:999px;color:#e4e8f3;background:#ffffff0d;font-size:.56rem;font-weight:800}.refresh-button{display:flex;align-items:center;gap:.4rem;padding:.68rem .8rem;border:1px solid #ffffff38;border-radius:10px;color:#fff;background:#ffffff12;font-size:.66rem;font-weight:900}.admin-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem;margin:.75rem 0}.admin-metrics article{display:flex;align-items:center;gap:.7rem;padding:.8rem 1rem;border:1px solid var(--line);border-radius:14px;background:#fff}.admin-metrics small,.admin-metrics strong{display:block}.admin-metrics small{color:var(--muted);font-size:.6rem;font-weight:800}.admin-metrics strong{font-size:1.1rem}.metric-icon{display:grid;place-items:center;width:39px;height:39px;border-radius:11px;font-size:1.15rem}.blue{color:#2872aa;background:#eaf4fb}.amber{color:#a66b11;background:#fff4dc}.violet{color:#7057b4;background:#f0ecfb}.green{color:#19755f;background:#e6f6f0}.admin-workspace{overflow:hidden;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:0 8px 24px #1c39530f}.workspace-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.15rem;border-bottom:1px solid var(--line)}.workspace-head small,.final-list header small,.status-history header small{color:var(--purple);font-size:.54rem;font-weight:900;letter-spacing:.1em}.workspace-head h2,.final-list h3,.status-history h3{margin:.08rem 0;color:var(--ink);font-size:.85rem}.workspace-head p{margin:0;color:var(--muted);font-size:.63rem}.filters{display:flex;gap:.4rem}.filters label{display:flex;align-items:center;gap:.35rem;width:250px;height:36px;padding:0 .6rem;border:1px solid #d7e0e8;border-radius:9px}.filters input{width:100%;border:0;outline:0;font-size:.65rem}.filters select{height:36px;padding:0 .55rem;border:1px solid #d7e0e8;border-radius:9px;background:#fff;font-size:.65rem}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th{padding:.64rem .8rem;color:#7c8999;background:#f7f9fb;font-size:.56rem;letter-spacing:.06em;text-align:left;text-transform:uppercase;white-space:nowrap}td{padding:.7rem .8rem;border-top:1px solid #edf1f4;color:#415168;font-size:.65rem}.request-title{display:flex;flex-direction:column;gap:.08rem;max-width:390px;padding:0;border:0;background:none;text-align:left}.request-title small{color:var(--purple);font-size:.55rem;font-weight:900}.request-title strong{font-size:.69rem}.request-title span,.product-preview{display:block;max-width:240px;overflow:hidden;color:var(--muted);font-size:.55rem;text-overflow:ellipsis;white-space:nowrap}.thumb-stack{display:flex}.thumb-stack img,.thumb-stack>span{display:grid;place-items:center;width:34px;height:34px;margin-left:-7px;border:2px solid #fff;border-radius:10px;object-fit:cover;color:#7790a5;background:#eef3f7}.thumb-stack>*:first-child{margin-left:0}.status-pill{display:inline-flex;padding:.33rem .52rem;border-radius:999px;font-size:.55rem;font-weight:900;white-space:nowrap}.status-pill--submitted{color:#976413;background:#fff2d8}.status-pill--under_review{color:#5b4ca1;background:#eeeafd}.status-pill--ready_to_quote{color:#16766c;background:#def4ef}.status-pill--quoted{color:#197058;background:#dff3e9}.status-pill--rejected{color:#a74251;background:#fce7ea}.date-cell{display:flex;align-items:center;gap:.3rem;white-space:nowrap}.row-actions{display:flex;gap:.35rem}.review-button,.pdf-button{display:flex;align-items:center;gap:.3rem;padding:.45rem .58rem;border-radius:8px;font-size:.57rem;font-weight:900}.review-button{border:0;color:#fff;background:var(--purple)}.pdf-button{border:1px solid #f0cbd1;color:#a53b4d;background:#fff4f5}.empty-state{padding:4rem;text-align:center;color:var(--muted)}.empty-state>i{font-size:2.4rem}.empty-state h3{margin:.4rem 0 .2rem;color:var(--ink);font-size:.95rem}.empty-state p{font-size:.65rem}.modal-shell{position:fixed;z-index:1060;inset:0;display:grid;place-items:center;padding:1rem;background:#0d172799;backdrop-filter:blur(5px)}.review-panel{display:flex;flex-direction:column;width:min(1300px,97vw);max-height:95vh;border-radius:22px;background:#f6f8fb;box-shadow:0 28px 75px #07111f59}.review-panel>header{display:flex;justify-content:space-between;padding:1.05rem 1.3rem;border-bottom:1px solid var(--line);border-radius:22px 22px 0 0;background:#fff}.review-panel>header span{color:var(--purple);font-size:.57rem;font-weight:900;letter-spacing:.1em}.review-panel>header h2{margin:.1rem 0;font-size:1rem}.review-panel>header p{margin:0;color:var(--muted);font-size:.63rem}.review-panel>header button{display:grid;place-items:center;width:34px;height:34px;border:0;border-radius:9px;background:#eef1f5;font-size:1.2rem}.review-body{overflow:auto;padding:.9rem 1.1rem}.review-card,.final-list,.status-history{padding:.8rem;border:1px solid var(--line);border-radius:14px;background:#fff;margin-bottom:.7rem}.field-grid{display:grid;grid-template-columns:2fr 1.3fr 1fr 1fr;gap:.55rem}.field-grid label{display:flex;flex-direction:column;gap:.27rem}.field-grid label.wide{grid-column:1/-1}.field-grid span,.final-quantity span{color:#5c697b;font-size:.56rem;font-weight:900}.field-grid input,.field-grid select,.field-grid textarea,.add-tools select,.review-line input,.review-line select{width:100%;border:1px solid #d5dee7;border-radius:8px;color:#33445a;background:#fff;font-size:.65rem}.field-grid input,.field-grid select,.add-tools select,.review-line input,.review-line select{height:35px;padding:0 .58rem}.field-grid textarea{padding:.5rem .58rem;resize:vertical}.final-list>header{display:flex;align-items:center;justify-content:space-between;gap:.7rem;margin-bottom:.55rem}.final-list h3 span{display:inline-grid;place-items:center;width:20px;height:20px;border-radius:7px;color:#fff;background:var(--purple);font-size:.56rem}.add-tools{display:flex;gap:.35rem}.add-tools select{width:230px}.add-tools button{display:flex;align-items:center;gap:.25rem;padding:0 .55rem;border:1px solid #d4dce6;border-radius:8px;color:#4e587e;background:#fff;font-size:.56rem;font-weight:900}.line-head,.review-line{display:grid;grid-template-columns:70px minmax(300px,1fr) 80px 105px 110px 34px;align-items:center;gap:.5rem}.line-head{padding:.45rem .5rem;color:#8490a0;background:#f6f8fa;border-radius:8px;font-size:.5rem;font-weight:900;text-transform:uppercase}.review-line{padding:.55rem .5rem;border-top:1px solid #edf1f4}.line-photo{position:relative;display:grid;place-items:center;width:58px;height:48px;overflow:hidden;border-radius:10px;color:#8494a6;background:#edf2f5;font-size:1.2rem}.line-photo img{width:100%;height:100%;object-fit:cover}.line-photo label{position:absolute;right:2px;bottom:2px;display:grid;place-items:center;width:20px;height:20px;margin:0;border-radius:6px;color:#fff;background:#263f64;cursor:pointer;font-size:.7rem}.line-photo label input{display:none}.line-name{display:grid;grid-template-columns:1fr 1fr;gap:.35rem}.original-quantity{padding:.35rem .45rem;border-radius:8px;background:#f1f3f6;text-align:center}.original-quantity small,.original-quantity strong{display:block}.original-quantity small{color:#7d8999;font-size:.48rem}.original-quantity strong{font-size:.66rem}.final-quantity{position:relative}.final-quantity span{position:absolute;top:-15px}.final-quantity input{border-color:#9a93d3;background:#faf9ff;font-weight:900}.remove-button{display:grid;place-items:center;width:34px;height:35px;border:0;border-radius:8px;color:#b04a5a;background:#fbecee}.status-history>div{display:flex;gap:1.2rem;overflow:auto;margin-top:.55rem}.status-history article{display:flex;gap:.4rem;min-width:185px}.status-history article>span{width:9px;height:9px;margin-top:.18rem;border:2px solid #fff;border-radius:50%;background:var(--purple);box-shadow:0 0 0 2px #d8d4f2}.status-history strong,.status-history small{display:block}.status-history strong{font-size:.6rem}.status-history small{color:var(--muted);font-size:.5rem}.status-history p{margin:.12rem 0 0;font-size:.53rem}.review-panel>footer{display:flex;justify-content:flex-end;gap:.45rem;padding:.8rem 1.1rem;border-top:1px solid var(--line);border-radius:0 0 22px 22px;background:#fff}.cancel-button,.save-button,.quote-button{display:flex;align-items:center;gap:.35rem;padding:.62rem .78rem;border-radius:9px;font-size:.63rem;font-weight:900}.cancel-button{border:1px solid #d7dfe7;background:#fff}.save-button{border:1px solid #aaa4da;color:#514b96;background:#f7f6ff}.quote-button{border:0;color:#fff;background:linear-gradient(135deg,#a53f52,#d0606c);box-shadow:0 7px 16px #a53f5228}.bx-spin{animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:1000px){.admin-metrics{grid-template-columns:repeat(2,1fr)}.workspace-head{align-items:flex-start;flex-direction:column}.field-grid{grid-template-columns:repeat(2,1fr)}.line-head{display:none}.review-line{grid-template-columns:60px 1fr 80px 105px}.review-line .line-name{grid-column:2/-1}.add-tools{flex-wrap:wrap}}
@media(max-width:680px){.admin-requests{padding:.5rem}.admin-hero{align-items:flex-start;flex-direction:column;padding:1.15rem}.admin-hero h1{font-size:1.3rem}.admin-trust{flex-wrap:wrap}.admin-metrics{grid-template-columns:1fr 1fr}.filters{width:100%;flex-wrap:wrap}.filters label{width:100%}.filters select{flex:1}.table-wrap table{min-width:940px}.field-grid{grid-template-columns:1fr}.field-grid label.wide{grid-column:auto}.final-list>header{align-items:flex-start;flex-direction:column}.add-tools,.add-tools select{width:100%}.review-line{grid-template-columns:55px 1fr}.review-line .line-name,.review-line .original-quantity,.review-line .final-quantity,.review-line>select,.review-line>.remove-button{grid-column:2}.review-panel>footer{flex-wrap:wrap}.quote-button{flex:1}.modal-shell{padding:.35rem}}
.modal-shell{z-index:12000}
</style>
