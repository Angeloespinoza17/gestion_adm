<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import {
  confirmLibraryAction,
  downloadExcelWorkbook,
  downloadPdfReport,
  formatLibraryDate,
  formatLibraryError,
  showLibrarySuccess,
} from "../module-utils";

const receptionItem = () => ({
  biblioteca_texto_titulo_id: null,
  education_level_id: null,
  title: "",
  subject: "",
  publisher: "",
  isbn: "",
  quantity_received: 1,
  unit_cost: null,
  notes: "",
});
const orderItem = () => ({
  biblioteca_texto_titulo_id: null,
  title: "",
  subject: "",
  quantity_required: 1,
  notes: "",
});

export default {
  components: { LoadingState, LibraryHelpButton, LibraryStatusBadge },
  props: { catalogs: { type: Object, required: true } },
  data() {
    return {
      loading: false,
      error: null,
      data: { titles: [], stock: [], receptions: [], orders: [] },
      showReception: false,
      showOrder: false,
      showRoster: false,
      showDelivery: false,
      selectedOrder: null,
      selectedDelivery: null,
      saving: false,
      receptionForm: { received_at: new Date().toISOString().slice(0, 10), source_name: "MINEDUC", document_reference: "", notes: "", items: [receptionItem()] },
      orderForm: { academic_year_id: null, education_level_id: null, course_section_id: null, prepared_at: new Date().toISOString().slice(0, 10), notes: "", items: [orderItem()] },
      deliveryForm: { status: "entregado", signature_name: "", signature_rut: "", pending_reason: "", notes: "", items: [] },
    };
  },
  computed: {
    summary() {
      return {
        received: this.data.stock.reduce((sum, item) => sum + Number(item.received || 0), 0),
        available: this.data.stock.reduce((sum, item) => sum + Number(item.available || 0), 0),
        pending: this.data.orders.reduce((sum, item) => sum + Number(item.pending_deliveries_count || 0), 0),
        shortages: this.data.orders.filter((order) => (order.items || []).some((item) => Number(item.shortage_quantity) > 0)).length,
      };
    },
  },
  mounted() { this.load(); },
  methods: {
    formatLibraryDate,
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/textos-escolares");
        this.data = response.data;
        if (!this.orderForm.academic_year_id) {
          this.orderForm.academic_year_id = (this.catalogs.academic_years || []).find((item) => item.is_active)?.id || this.catalogs.academic_years?.[0]?.id || null;
        }
      } catch (error) { this.error = formatLibraryError(error); }
      finally { this.loading = false; }
    },
    addReceptionItem() { this.receptionForm.items.push(receptionItem()); },
    addOrderItem() { this.orderForm.items.push(orderItem()); },
    syncReceptionTitle(item) {
      const title = (this.data.titles || []).find((entry) => Number(entry.id) === Number(item.biblioteca_texto_titulo_id));
      if (!title) return;
      item.title = title.title;
      item.subject = title.subject;
      item.publisher = title.publisher || "";
      item.isbn = title.isbn || "";
      item.education_level_id = title.education_level_id || null;
    },
    syncOrderTitle(item) {
      const title = (this.data.titles || []).find((entry) => Number(entry.id) === Number(item.biblioteca_texto_titulo_id));
      if (!title) return;
      item.title = title.title;
      item.subject = title.subject;
    },
    syncOrderCourse() {
      const course = (this.catalogs.courses || []).find((item) => Number(item.id) === Number(this.orderForm.course_section_id));
      this.orderForm.education_level_id = course?.education_level_id || null;
    },
    async saveReception() {
      this.saving = true;
      try {
        await axios.post("/api/biblioteca/textos-escolares/recepciones", this.receptionForm);
        this.showReception = false;
        this.receptionForm = { received_at: new Date().toISOString().slice(0, 10), source_name: "MINEDUC", document_reference: "", notes: "", items: [receptionItem()] };
        await this.load();
        await showLibrarySuccess("Recepción registrada y stock actualizado.");
      } catch (error) { this.error = formatLibraryError(error); }
      finally { this.saving = false; }
    },
    async saveOrder() {
      this.saving = true;
      try {
        const response = await axios.post("/api/biblioteca/textos-escolares/ordenes", this.orderForm);
        this.showOrder = false;
        await this.load();
        await this.openOrder(response.data.data);
        await showLibrarySuccess("Orden creada. Revisa faltantes y genera el listado.");
      } catch (error) { this.error = formatLibraryError(error); }
      finally { this.saving = false; }
    },
    async openOrder(order) {
      const response = await axios.get(`/api/biblioteca/textos-escolares/ordenes/${order.id}`);
      this.selectedOrder = response.data.data;
      this.showRoster = true;
    },
    async generateRoster() {
      const result = await confirmLibraryAction({ title: "Generar listado del curso", text: "Se incorporarán las estudiantes matriculadas actualmente y los libros de esta orden.", confirmButtonText: "Generar" });
      if (!result.isConfirmed) return;
      const response = await axios.post(`/api/biblioteca/textos-escolares/ordenes/${this.selectedOrder.id}/listado`);
      this.selectedOrder = response.data.data;
      await this.load();
      await showLibrarySuccess("Listado generado correctamente.");
    },
    openDelivery(delivery) {
      this.selectedDelivery = delivery;
      this.deliveryForm = {
        status: delivery.status === "entregado" ? "entregado" : "entregado",
        signature_name: delivery.signature_name || delivery.student_name_snapshot,
        signature_rut: delivery.signature_rut || delivery.student_rut_snapshot || "",
        pending_reason: delivery.pending_reason || "",
        notes: delivery.notes || "",
        items: (delivery.items || []).map((item) => ({ id: item.id, quantity: item.quantity || 1, status: item.status, pending_reason: item.pending_reason || "" })),
      };
      this.showDelivery = true;
    },
    markAll(status) {
      this.deliveryForm.status = status === "entregado" ? "entregado" : "pendiente";
      this.deliveryForm.items.forEach((item) => { item.status = status; });
    },
    async saveDelivery() {
      this.saving = true;
      try {
        await axios.put(`/api/biblioteca/textos-escolares/entregas/${this.selectedDelivery.id}`, this.deliveryForm);
        this.showDelivery = false;
        const response = await axios.get(`/api/biblioteca/textos-escolares/ordenes/${this.selectedOrder.id}`);
        this.selectedOrder = response.data.data;
        await this.load();
        await showLibrarySuccess("Entrega actualizada correctamente.");
      } catch (error) { this.error = formatLibraryError(error); }
      finally { this.saving = false; }
    },
    rosterRows() {
      return (this.selectedOrder?.deliveries || []).map((delivery) => [
        delivery.student_name_snapshot,
        delivery.student_rut_snapshot || "",
        (delivery.items || []).map((item) => item.order_item?.title).filter(Boolean).join(", "),
        delivery.status,
        delivery.pending_reason || "",
        "",
      ]);
    },
    exportRoster(format) {
      const section = { title: `Orden ${this.selectedOrder.order_code}`, headers: ["Nombre completo", "RUT", "Libro(s)", "Estado", "Pendiente", "Firma"], rows: this.rosterRows() };
      if (format === "excel") downloadExcelWorkbook(`orden-${this.selectedOrder.order_code}`, [section]);
      else downloadPdfReport(`orden-${this.selectedOrder.order_code}`, "Orden de entrega de textos escolares", this.selectedOrder.course_section?.display_name || "", [section]);
    },
  },
};
</script>

<template>
  <div class="textbook-view">
    <section class="textbook-head">
      <div><span>TEXTOS ESCOLARES · INVENTARIO INDEPENDIENTE</span><h5>Desde la recepción MINEDUC hasta la firma de cada estudiante</h5><p>Stock, órdenes por curso, faltantes y listados de entrega, separados del catálogo de biblioteca.</p></div>
      <div class="d-flex gap-2 flex-wrap"><LibraryHelpButton title="Ayuda: textos escolares" text="Registra recepciones, prepara órdenes por curso y genera automáticamente un listado con nombre, RUT, estado y firma." /><BButton variant="outline-primary" @click="showReception = true"><i class="bx bx-package me-1"></i>Registrar recepción</BButton><BButton variant="primary" @click="showOrder = true"><i class="bx bx-list-check me-1"></i>Nueva orden</BButton></div>
    </section>
    <div class="catalog-separation">
      <i class="bx bx-shield-quarter"></i>
      <div>
        <strong>Catálogo exclusivo de textos escolares</strong>
        <span>Las recepciones del Gobierno no crean obras ni modifican ejemplares del catálogo CRA.</span>
      </div>
      <span class="catalog-count">{{ data.titles?.length || 0 }} títulos escolares</span>
    </div>
    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <LoadingState v-if="loading" message="Consolidando textos escolares..." compact />
    <template v-else>
      <div class="metric-grid">
        <article><i class="bx bx-package"></i><div><span>Textos recibidos</span><strong>{{ summary.received }}</strong></div></article>
        <article><i class="bx bx-layer"></i><div><span>Stock disponible</span><strong>{{ summary.available }}</strong></div></article>
        <article class="warning"><i class="bx bx-user-check"></i><div><span>Entregas pendientes</span><strong>{{ summary.pending }}</strong></div></article>
        <article class="danger"><i class="bx bx-error-circle"></i><div><span>Órdenes con faltantes</span><strong>{{ summary.shortages }}</strong></div></article>
      </div>
      <div class="row g-3">
        <div class="col-xl-5">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="d-flex justify-content-between"><strong>Stock consolidado</strong><span class="text-muted small">{{ data.stock.length }} títulos</span></div></template>
            <div class="stock-list">
              <div v-for="item in data.stock" :key="item.biblioteca_texto_titulo_id">
                <div><strong>{{ item.title }}</strong><span>{{ item.subject }}<template v-if="item.education_level?.name"> · {{ item.education_level.name }}</template></span></div>
                <div class="stock-numbers"><span>{{ item.delivered }} entregados</span><strong>{{ item.available }}</strong><small>disponibles</small></div>
              </div>
              <div v-if="!data.stock.length" class="text-center text-muted py-4">Aún no hay recepciones registradas.</div>
            </div>
          </BCard>
        </div>
        <div class="col-xl-7">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><strong>Órdenes de entrega</strong></template>
            <BTable responsive hover :items="data.orders" :fields="[{key:'order_code',label:'Orden'},{key:'course',label:'Curso'},{key:'progress',label:'Progreso'},{key:'status',label:'Estado'},{key:'actions',label:''}]">
              <template #cell(course)="{item}"><strong>{{ item.course_section?.display_name || "-" }}</strong><small class="d-block text-muted">{{ item.academic_year?.name }}</small></template>
              <template #cell(progress)="{item}"><div class="order-progress"><strong>{{ (item.deliveries_count || 0) - (item.pending_deliveries_count || 0) }}/{{ item.deliveries_count || 0 }}</strong><span>{{ item.pending_deliveries_count || 0 }} pendiente(s)</span></div></template>
              <template #cell(status)="{item}"><LibraryStatusBadge :status="item.status" /></template>
              <template #cell(actions)="{item}"><BButton size="sm" variant="outline-primary" @click="openOrder(item)">Abrir</BButton></template>
            </BTable>
          </BCard>
        </div>
      </div>
    </template>

    <BModal v-model="showReception" size="xl" title="Recepción de textos escolares" hide-footer scrollable>
      <div class="operation-intro"><i class="bx bx-package"></i><div><strong>Ingreso de stock gubernamental</strong><span>Selecciona un texto escolar ya registrado o crea uno nuevo aquí. No se utilizará el catálogo CRA.</span></div></div>
      <div class="row g-3 mb-3">
        <div class="col-md-3"><label class="form-label">Fecha</label><BFormInput v-model="receptionForm.received_at" type="date" /></div>
        <div class="col-md-4"><label class="form-label">Origen / proveedor</label><BFormInput v-model="receptionForm.source_name" /></div>
        <div class="col-md-3"><label class="form-label">Documento</label><BFormInput v-model="receptionForm.document_reference" /></div>
        <div class="col-md-2"><label class="form-label">Observación</label><BFormInput v-model="receptionForm.notes" /></div>
      </div>
      <div v-for="(item,index) in receptionForm.items" :key="index" class="line-item">
        <div class="row g-2 align-items-end">
          <div class="col-md-5"><label class="form-label">Texto escolar registrado</label><BFormSelect v-model="item.biblioteca_texto_titulo_id" :options="[{value:null,text:'＋ Registrar un título escolar nuevo'}].concat((data.titles || []).map((title)=>({value:title.id,text:`${title.title} · ${title.subject}${title.education_level?.name ? ` · ${title.education_level.name}` : ''}`})))" @change="syncReceptionTitle(item)" /></div>
          <div class="col-md-4"><label class="form-label">Título *</label><BFormInput v-model="item.title" :disabled="!!item.biblioteca_texto_titulo_id" placeholder="Nombre impreso del texto" /></div>
          <div class="col-md-3"><label class="form-label">Asignatura *</label><BFormInput v-model="item.subject" :disabled="!!item.biblioteca_texto_titulo_id" placeholder="Ej. Matemática" /></div>
          <div class="col-md-4"><label class="form-label">Editorial</label><BFormInput v-model="item.publisher" :disabled="!!item.biblioteca_texto_titulo_id" /></div>
          <div class="col-md-3"><label class="form-label">ISBN</label><BFormInput v-model="item.isbn" :disabled="!!item.biblioteca_texto_titulo_id" /></div>
          <div class="col-md-3"><label class="form-label">Nivel</label><BFormSelect v-model="item.education_level_id" :disabled="!!item.biblioteca_texto_titulo_id" :options="[{value:null,text:'Todos los niveles'}].concat((catalogs.education_levels || []).map((level)=>({value:level.id,text:level.name})))" /></div>
          <div class="col-md-1"><label class="form-label">Cantidad</label><BFormInput v-model="item.quantity_received" type="number" min="1" /></div>
          <div class="col-md-1"><BButton variant="light" class="text-danger line-remove" :disabled="receptionForm.items.length===1" title="Quitar fila" @click="receptionForm.items.splice(index,1)"><i class="bx bx-trash"></i></BButton></div>
        </div>
      </div>
      <BButton variant="outline-primary" size="sm" @click="addReceptionItem"><i class="bx bx-plus me-1"></i>Agregar otro título</BButton>
      <div class="d-flex justify-content-end gap-2 mt-4"><BButton variant="light" @click="showReception=false">Cancelar</BButton><BButton variant="primary" :disabled="saving" @click="saveReception">{{saving?"Guardando...":"Registrar recepción"}}</BButton></div>
    </BModal>

    <BModal v-model="showOrder" size="xl" title="Nueva orden de entrega" hide-footer scrollable>
      <div class="operation-intro blue"><i class="bx bx-list-check"></i><div><strong>Preparación por curso</strong><span>La orden usa exclusivamente textos escolares y compara la cantidad requerida con su stock independiente.</span></div></div>
      <div class="row g-3 mb-3">
        <div class="col-md-4"><label class="form-label">Año escolar</label><BFormSelect v-model="orderForm.academic_year_id" :options="(catalogs.academic_years || []).map((year)=>({value:year.id,text:year.name}))" /></div>
        <div class="col-md-5"><label class="form-label">Curso *</label><BFormSelect v-model="orderForm.course_section_id" :options="(catalogs.courses || []).filter((course)=>!orderForm.academic_year_id||Number(course.academic_year_id)===Number(orderForm.academic_year_id)).map((course)=>({value:course.id,text:course.display_name}))" @change="syncOrderCourse" /></div>
        <div class="col-md-3"><label class="form-label">Fecha preparación</label><BFormInput v-model="orderForm.prepared_at" type="date" /></div>
      </div>
      <div v-for="(item,index) in orderForm.items" :key="index" class="line-item">
        <div class="row g-2 align-items-end">
          <div class="col-md-5"><label class="form-label">Texto escolar</label><BFormSelect v-model="item.biblioteca_texto_titulo_id" :options="[{value:null,text:'＋ Incluir texto aún no recepcionado'}].concat((data.titles || []).map((title)=>({value:title.id,text:`${title.title} · ${title.subject} · ${title.available} disponibles`})))" @change="syncOrderTitle(item)" /></div>
          <div class="col-md-3"><label class="form-label">Título *</label><BFormInput v-model="item.title" :disabled="!!item.biblioteca_texto_titulo_id" /></div>
          <div class="col-md-2"><label class="form-label">Asignatura *</label><BFormInput v-model="item.subject" :disabled="!!item.biblioteca_texto_titulo_id" /></div>
          <div class="col-md-1"><label class="form-label">Cantidad</label><BFormInput v-model="item.quantity_required" type="number" min="1" /></div>
          <div class="col-md-1"><BButton variant="light" class="text-danger line-remove" :disabled="orderForm.items.length===1" title="Quitar fila" @click="orderForm.items.splice(index,1)"><i class="bx bx-trash"></i></BButton></div>
        </div>
      </div>
      <BButton variant="outline-primary" size="sm" @click="addOrderItem"><i class="bx bx-plus me-1"></i>Agregar otro libro</BButton>
      <div class="d-flex justify-content-end gap-2 mt-4"><BButton variant="light" @click="showOrder=false">Cancelar</BButton><BButton variant="primary" :disabled="saving" @click="saveOrder">{{saving?"Creando...":"Crear orden"}}</BButton></div>
    </BModal>

    <BModal v-model="showRoster" v-if="selectedOrder" size="xl" :title="`Orden ${selectedOrder.order_code}`" hide-footer scrollable>
      <div class="roster-head"><div><span>{{ selectedOrder.course_section?.display_name }}</span><strong>{{ selectedOrder.deliveries?.length || 0 }} estudiantes</strong></div><div class="d-flex gap-2"><BButton variant="outline-success" size="sm" @click="exportRoster('excel')">Excel</BButton><BButton variant="outline-danger" size="sm" @click="exportRoster('pdf')">PDF / firmas</BButton><BButton variant="primary" size="sm" @click="generateRoster">Actualizar listado</BButton></div></div>
      <BAlert v-if="selectedOrder.items?.some((item)=>item.shortage_quantity>0)" show variant="warning"><strong>Stock insuficiente:</strong> {{ selectedOrder.items.filter((item)=>item.shortage_quantity>0).map((item)=>`${item.title}: faltan ${item.shortage_quantity}`).join(" · ") }}</BAlert>
      <BTable responsive hover :items="selectedOrder.deliveries || []" :fields="[{key:'student_name_snapshot',label:'Nombre completo'},{key:'student_rut_snapshot',label:'RUT'},{key:'books',label:'Libros'},{key:'status',label:'Estado'},{key:'actions',label:''}]">
        <template #cell(books)="{item}"><span class="small">{{ (item.items||[]).map((line)=>line.order_item?.title).filter(Boolean).join(", ") }}</span></template>
        <template #cell(status)="{item}"><LibraryStatusBadge :status="item.status" /><small v-if="item.pending_reason" class="d-block text-danger mt-1">{{item.pending_reason}}</small></template>
        <template #cell(actions)="{item}"><BButton size="sm" variant="outline-primary" @click="openDelivery(item)">Gestionar entrega</BButton></template>
      </BTable>
      <div v-if="!selectedOrder.deliveries?.length" class="text-center py-5"><i class="bx bx-group fs-1 text-muted"></i><p class="text-muted">Genera el listado desde la matrícula vigente.</p><BButton variant="primary" @click="generateRoster">Generar listado</BButton></div>
    </BModal>

    <BModal v-model="showDelivery" v-if="selectedDelivery" title="Gestionar entrega individual" hide-footer>
      <div class="student-delivery"><span>{{selectedDelivery.student_rut_snapshot}}</span><strong>{{selectedDelivery.student_name_snapshot}}</strong></div>
      <div class="d-flex gap-2 mb-3"><BButton size="sm" variant="outline-success" @click="markAll('entregado')">Entregar todo</BButton><BButton size="sm" variant="outline-warning" @click="markAll('pendiente')">Dejar pendiente</BButton></div>
      <div v-for="item in deliveryForm.items" :key="item.id" class="delivery-line"><BFormCheckbox v-model="item.status" value="entregado" unchecked-value="pendiente">{{ selectedDelivery.items.find((line)=>line.id===item.id)?.order_item?.title }}</BFormCheckbox><BFormInput v-if="item.status==='pendiente'" v-model="item.pending_reason" size="sm" placeholder="Motivo pendiente" /></div>
      <div class="row g-3 mt-1">
        <div class="col-md-6"><label class="form-label">Nombre de quien firma</label><BFormInput v-model="deliveryForm.signature_name" /></div>
        <div class="col-md-6"><label class="form-label">RUT</label><BFormInput v-model="deliveryForm.signature_rut" /></div>
        <div class="col-12"><label class="form-label">Observaciones / motivo pendiente</label><BFormTextarea v-model="deliveryForm.pending_reason" rows="2" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4"><BButton variant="light" @click="showDelivery=false">Cancelar</BButton><BButton variant="primary" :disabled="saving" @click="saveDelivery">Guardar entrega</BButton></div>
    </BModal>
  </div>
</template>

<style scoped>
.textbook-view{display:flex;flex-direction:column;gap:1rem}.textbook-head{background:linear-gradient(135deg,#2c3e50,#4b6584);color:white;border-radius:19px;padding:1.4rem 1.5rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;box-shadow:0 15px 35px rgba(44,62,80,.18)}.textbook-head>div:first-child>span{color:#b8d6ff;font-size:.66rem;font-weight:800;letter-spacing:.15em}.textbook-head h5{color:white;margin:.25rem 0}.textbook-head p{color:rgba(255,255,255,.72);margin:0}.catalog-separation{display:flex;align-items:center;gap:.75rem;padding:.85rem 1rem;background:#eef8f5;border:1px solid #cfece2;border-radius:14px;color:#236d5a}.catalog-separation>i{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:#d8f2e9;font-size:1.35rem}.catalog-separation>div{display:flex;flex:1;flex-direction:column}.catalog-separation>div span{font-size:.74rem;color:#64847b}.catalog-count{padding:.35rem .65rem;border-radius:999px;background:#fff;font-size:.7rem;font-weight:800;white-space:nowrap}.metric-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem}.metric-grid article{background:#fff;border:1px solid #e7ebf1;border-radius:15px;padding:1rem;display:flex;align-items:center;gap:.8rem}.metric-grid article>i{width:42px;height:42px;border-radius:12px;background:#e8f4ff;color:#3f8fd3;display:grid;place-items:center;font-size:1.3rem}.metric-grid article.warning>i{background:#fff5df;color:#d49725}.metric-grid article.danger>i{background:#ffebeb;color:#d95b5b}.metric-grid article div{display:flex;flex-direction:column}.metric-grid span{font-size:.72rem;color:#8993a3}.metric-grid strong{font-size:1.3rem;color:#29354a}.stock-list{display:grid;gap:.4rem}.stock-list>div{display:flex;justify-content:space-between;align-items:center;padding:.7rem .8rem;border-radius:11px;background:#f7f9fc}.stock-list>div>div:first-child{display:flex;flex-direction:column}.stock-list span{font-size:.7rem;color:#8791a0}.stock-numbers{text-align:right;display:grid;grid-template-columns:auto auto;gap:0 .35rem;align-items:baseline}.stock-numbers span{grid-column:1/-1}.stock-numbers small{font-size:.62rem;color:#98a1ad}.order-progress{display:flex;flex-direction:column}.order-progress span{font-size:.68rem;color:#8792a2}.operation-intro{display:flex;gap:.7rem;align-items:center;padding:.8rem 1rem;border-radius:12px;background:#edf9f4;color:#2c8769;margin-bottom:1rem}.operation-intro.blue{background:#eef3ff;color:#4668c5}.operation-intro>i{font-size:1.7rem}.operation-intro span{display:block;font-size:.72rem;color:#7b8797}.line-item{background:#f8fafc;border:1px solid #e8ecf2;border-radius:12px;padding:.85rem;margin-bottom:.7rem}.line-remove{width:100%;min-height:38px}.roster-head{display:flex;align-items:center;justify-content:space-between;padding:.8rem 1rem;background:#f3f6fa;border-radius:12px;margin-bottom:1rem}.roster-head>div:first-child{display:flex;flex-direction:column}.roster-head span{color:#69768a;font-size:.72rem}.student-delivery{display:flex;flex-direction:column;padding:.8rem 1rem;background:#eef3ff;border-radius:11px;margin-bottom:1rem}.student-delivery span{font-size:.68rem;color:#6f7d94}.delivery-line{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;align-items:center;border-bottom:1px solid #edf0f4;padding:.65rem 0}@media(max-width:900px){.textbook-head{align-items:flex-start;flex-direction:column}.catalog-separation{align-items:flex-start;flex-wrap:wrap}.catalog-count{margin-left:3.15rem}.metric-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.metric-grid{grid-template-columns:1fr}.catalog-count{margin-left:0}.roster-head{align-items:flex-start;flex-direction:column;gap:.6rem}}
</style>
