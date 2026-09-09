<script>
import axios from "axios";
import Swal from "sweetalert2";
import Multiselect from "@vueform/multiselect";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { downloadSupplyDeliveryAct } from "../../utils/supply-delivery-pdf";
import { canUseLiveCamera, captureCameraPhoto, openRearCamera, stopMediaStream } from "../../utils/camera-capture";

const today = () => {
  const local = new Date();
  local.setMinutes(local.getMinutes() - local.getTimezoneOffset());
  return local.toISOString().slice(0, 10);
};
const emptyItem = () => ({ id: null, storeroom_id: null, name: "", description: "", supply_type: "", unit_of_measure: "unidad", minimum_stock: null, supplier_id: null, active: true, photo: null });
const emptyReceipt = () => ({ storeroom_id: null, purchased_at: today(), supplier_id: null, document_type: "Factura", document_number: "", total_amount: null, notes: "", items: [{ supply_item_id: null, quantity: null, unit_cost: null }] });
const emptyDelivery = () => ({ storeroom_id: null, delivered_at: today(), recipient_staff_id: null, destination: "", notes: "", items: [{ supply_item_id: null, quantity: null, notes: "" }] });
const emptyStoreroom = () => ({ name: "", code: "", description: "" });

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      detailLoading: false,
      saving: false,
      deletingId: null,
      exportingId: null,
      activeTab: "stock",
      search: "",
      stockStatus: "",
      items: [],
      receipts: [],
      deliveries: [],
      storerooms: [],
      selectedStoreroomId: null,
      showStoreroomModal: false,
      showItemDetailModal: false,
      storeroomForm: emptyStoreroom(),
      inventoryLoading: false,
      inventoryCandidates: [],
      inventorySearch: "",
      inventoryType: "",
      selectedInventoryIds: [],
      inventoryPagination: { current_page: 1, last_page: 1, total: 0 },
      summary: { total_items: 0, active_items: 0, low_stock_items: 0, empty_items: 0 },
      pagination: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      catalogs: { types: { cleaning: [], heating: [], maintenance_storeroom: [] }, units: [], suppliers: [], delivery_recipients: [], capabilities: {} },
      showItemModal: false,
      showReceiptModal: false,
      showDeliveryModal: false,
      itemForm: emptyItem(),
      selectedItemDetail: null,
      receiptForm: emptyReceipt(),
      deliveryForm: emptyDelivery(),
      deliveryItemOptions: {},
      deliveryItemsError: "",
      photoPreview: null,
      cameraStream: null,
      cameraActive: false,
      cameraError: null,
    };
  },
  computed: {
    section() {
      return this.$route.meta.supplySection || (this.$route.path.includes("heating") ? "heating" : "cleaning");
    },
    isHeating() { return this.section === "heating"; },
    isMaintenanceStoreroom() { return this.section === "maintenance_storeroom"; },
    isCleaning() { return this.section === "cleaning"; },
    sectionTitle() {
      if (this.isMaintenanceStoreroom) return "Pañol de mantenimiento";
      return this.isHeating ? "Combustibles y calefacción" : "Insumos de aseo";
    },
    sectionDescription() {
      if (this.isMaintenanceStoreroom) {
        return "Registra herramientas, repuestos y artículos del pañol, controla sus existencias y conserva cada ingreso y salida.";
      }
      return this.isHeating
        ? "Controla pellet, cilindros de gas cargados, leña y otros combustibles desde una sola bodega trazable."
        : "Administra existencias, compras y entregas de todos los productos de limpieza institucional.";
    },
    sectionIcon() {
      if (this.isMaintenanceStoreroom) return "bx-wrench";
      return this.isHeating ? "bx-hot" : "bx-spray-can";
    },
    catalogNoun() { return this.isMaintenanceStoreroom ? "herramientas y artículos" : "insumos"; },
    itemNoun() { return this.isMaintenanceStoreroom ? "herramienta o artículo" : "insumo"; },
    stockLocationLabel() { return this.isMaintenanceStoreroom ? (this.selectedStoreroom?.name || "Pañol de mantenimiento") : "Stock central"; },
    movementLabel() { return this.isMaintenanceStoreroom ? "Ingresos + salidas" : "Compras + entregas"; },
    selectedStoreroom() {
      return this.storerooms.find((storeroom) => Number(storeroom.id) === Number(this.selectedStoreroomId)) || null;
    },
    selectedInventoryCount() { return this.selectedInventoryIds.length; },
    itemTypes() { return this.catalogs.types?.[this.section] || []; },
    activeItems() { return this.items.filter((item) => item.inventory_item?.active); },
    permissions() {
      try { return JSON.parse(localStorage.getItem("permissions") || "[]"); } catch (_) { return []; }
    },
    canManage() { return this.catalogs.capabilities?.manage_items ?? this.hasPermission("gestionar_insumos_abastecimiento"); },
    canReceive() { return this.catalogs.capabilities?.receive_stock ?? this.hasPermission("registrar_compras_abastecimiento"); },
    canDeliver() { return this.catalogs.capabilities?.create_deliveries ?? this.hasPermission("registrar_entregas_abastecimiento"); },
    canExport() { return this.catalogs.capabilities?.export_acts ?? this.hasPermission("exportar_actas_abastecimiento"); },
    paginationFrom() {
      if (!this.pagination.total || !this.items.length) return 0;
      return ((this.pagination.current_page - 1) * this.pagination.per_page) + 1;
    },
    paginationTo() {
      if (!this.paginationFrom) return 0;
      return Math.min(this.paginationFrom + this.items.length - 1, this.pagination.total);
    },
    paginationPages() {
      const lastPage = Number(this.pagination.last_page || 1);
      const currentPage = Number(this.pagination.current_page || 1);
      const firstPage = Math.max(1, Math.min(currentPage - 2, lastPage - 4));
      const visibleCount = Math.min(5, lastPage);
      return Array.from({ length: visibleCount }, (_, index) => firstPage + index);
    },
    deliverySelectedCount() {
      return this.deliveryForm.items.filter((line) => line.supply_item_id).length;
    },
    deliveryIsReady() {
      return Boolean(this.deliveryForm.recipient_staff_id)
        && this.deliveryForm.items.length > 0
        && this.deliveryForm.items.every((line) => line.supply_item_id && Number(line.quantity) > 0);
    },
    selectedDeliveryRecipient() {
      return this.catalogs.delivery_recipients?.find((recipient) => Number(recipient.id) === Number(this.deliveryForm.recipient_staff_id)) || null;
    },
  },
  watch: {
    "$route.meta.supplySection"() {
      this.activeTab = "stock";
      this.search = "";
      this.stockStatus = "";
      this.selectedStoreroomId = null;
      this.selectedInventoryIds = [];
      this.pagination.current_page = 1;
      this.loadAll();
    },
    showItemModal(isOpen) {
      if (!isOpen) this.stopCamera();
    },
  },
  mounted() { this.loadAll(); },
  beforeUnmount() {
    this.stopCamera();
    this.clearPhotoPreview();
  },
  methods: {
    hasPermission(slug) { return this.permissions.includes(slug); },
    sectionParams() {
      return {
        section: this.section,
        storeroom_id: this.isMaintenanceStoreroom ? (this.selectedStoreroomId || undefined) : undefined,
      };
    },
    async loadAll() {
      this.loading = true;
      try {
        const [catalogs, storerooms] = await Promise.all([
          axios.get("/api/supplies/catalogs"),
          this.isMaintenanceStoreroom ? axios.get("/api/supplies/storerooms") : Promise.resolve({ data: { data: [] } }),
        ]);
        this.catalogs = catalogs.data;
        this.storerooms = storerooms.data.data || [];
        if (this.isMaintenanceStoreroom && !this.storerooms.some((item) => Number(item.id) === Number(this.selectedStoreroomId))) {
          this.selectedStoreroomId = this.storerooms[0]?.id || null;
        }

        const params = this.sectionParams();
        const [items, receipts, deliveries] = await Promise.all([
          axios.get("/api/supplies/items", { params: { ...params, search: this.search || undefined, stock_status: this.stockStatus || undefined, page: this.pagination.current_page } }),
          axios.get("/api/supplies/receipts", { params }),
          axios.get("/api/supplies/deliveries", { params }),
        ]);
        this.applyItems(items.data);
        this.receipts = receipts.data.data || [];
        this.deliveries = deliveries.data.data || [];
      } catch (error) {
        this.alertError(error);
      } finally {
        this.loading = false;
      }
    },
    async loadItems(resetPage = false) {
      if (resetPage) this.pagination.current_page = 1;
      this.loading = true;
      try {
        const { data } = await axios.get("/api/supplies/items", { params: { ...this.sectionParams(), search: this.search || undefined, stock_status: this.stockStatus || undefined, page: this.pagination.current_page } });
        this.applyItems(data);
      } catch (error) { this.alertError(error); } finally { this.loading = false; }
    },
    async goToPage(page) {
      const targetPage = Math.min(Math.max(Number(page) || 1, 1), Number(this.pagination.last_page || 1));
      if (targetPage === this.pagination.current_page || this.loading) return;
      this.pagination.current_page = targetPage;
      await this.loadItems();
    },
    async changeStoreroom() {
      this.pagination.current_page = 1;
      this.selectedInventoryIds = [];
      await this.loadAll();
      if (this.activeTab === "inventory") await this.loadInventoryCandidates(1);
    },
    applyItems(payload) {
      this.items = payload.data || [];
      this.summary = payload.summary || this.summary;
      this.pagination = { ...(payload.meta || this.pagination) };
    },
    openNewItem() {
      this.stopCamera();
      this.clearPhotoPreview();
      this.itemForm = emptyItem();
      this.itemForm.supply_type = this.itemTypes[0]?.value || "other";
      this.itemForm.unit_of_measure = this.isHeating ? "saco" : "unidad";
      this.itemForm.storeroom_id = this.isMaintenanceStoreroom ? this.selectedStoreroomId : null;
      this.showItemModal = true;
    },
    async openViewItem(item) {
      this.selectedItemDetail = item;
      this.showItemDetailModal = true;
      this.detailLoading = true;
      try {
        const { data } = await axios.get(`/api/supplies/items/${item.id}`);
        this.selectedItemDetail = data.data;
      } catch (error) {
        this.showItemDetailModal = false;
        this.alertError(error);
      } finally {
        this.detailLoading = false;
      }
    },
    openEditItem(item) {
      this.stopCamera();
      this.clearPhotoPreview();
      const inventory = item.inventory_item || {};
      this.itemForm = {
        id: item.id,
        storeroom_id: item.storeroom_id || this.selectedStoreroomId,
        name: inventory.name || "",
        description: inventory.description || "",
        supply_type: item.supply_type,
        unit_of_measure: inventory.unit_of_measure || "unidad",
        minimum_stock: inventory.minimum_stock,
        supplier_id: inventory.supplier_id,
        active: inventory.active !== false,
        photo: null,
      };
      this.photoPreview = item.photo_url || null;
      this.showItemModal = true;
    },
    openEditFromDetail() {
      const item = this.selectedItemDetail;
      this.showItemDetailModal = false;
      if (item) this.openEditItem(item);
    },
    selectPhoto(event) {
      const file = event.target.files?.[0] || null;
      this.setPhoto(file);
      this.stopCamera();
      if (event.target) event.target.value = "";
    },
    setPhoto(file) {
      this.clearPhotoPreview();
      this.itemForm.photo = file;
      this.photoPreview = file ? URL.createObjectURL(file) : null;
    },
    async startCamera() {
      this.cameraError = null;

      if (!canUseLiveCamera()) {
        this.$refs.cameraInput?.click?.();
        return;
      }

      this.stopCamera(false);

      try {
        const stream = await openRearCamera();

        if (!this.showItemModal) {
          stream.getTracks().forEach((track) => track.stop());
          return;
        }

        this.cameraStream = stream;
        this.cameraActive = true;
        await this.$nextTick();

        const video = this.$refs.cameraVideo;
        if (video) {
          video.srcObject = stream;
          await video.play().catch(() => {});
        }
      } catch (_) {
        this.cameraStream = null;
        this.cameraActive = false;
        this.cameraError = "No se pudo abrir la cámara. Revisa el permiso del navegador o usa la galería.";
      }
    },
    stopCamera(clearError = true) {
      if (this.cameraStream) {
        stopMediaStream(this.cameraStream);
      }
      this.cameraStream = null;
      this.cameraActive = false;

      const video = this.$refs.cameraVideo;
      if (video) video.srcObject = null;
      if (clearError) this.cameraError = null;
    },
    async capturePhoto() {
      const video = this.$refs.cameraVideo;
      const canvas = this.$refs.cameraCanvas;

      if (!video || !canvas || !video.videoWidth || !video.videoHeight) {
        this.cameraError = "La cámara aún no está lista para capturar.";
        return;
      }

      const photo = await captureCameraPhoto(video, canvas, "insumo");
      if (!photo) {
        this.cameraError = "No se pudo generar la foto capturada.";
        return;
      }
      this.setPhoto(photo);
      this.stopCamera();
    },
    clearPhotoPreview() {
      if (this.photoPreview?.startsWith("blob:")) URL.revokeObjectURL(this.photoPreview);
      this.photoPreview = null;
    },
    async saveItem() {
      this.saving = true;
      try {
        if (this.itemForm.id) {
          await axios.put(`/api/supplies/items/${this.itemForm.id}`, {
            supply_type: this.itemForm.supply_type,
            storeroom_id: this.isMaintenanceStoreroom ? this.itemForm.storeroom_id : null,
            name: this.itemForm.name,
            description: this.itemForm.description || null,
            unit_of_measure: this.itemForm.unit_of_measure,
            minimum_stock: this.itemForm.minimum_stock === "" ? null : this.itemForm.minimum_stock,
            supplier_id: this.itemForm.supplier_id || null,
            active: this.itemForm.active,
          });
          if (this.itemForm.photo) {
            const photoData = new FormData(); photoData.append("photo", this.itemForm.photo);
            await axios.post(`/api/supplies/items/${this.itemForm.id}/photo`, photoData);
          }
        } else {
          const form = new FormData();
          Object.entries({ ...this.itemForm, section: this.section }).forEach(([key, value]) => {
            if (key !== "id" && key !== "active" && value !== null && value !== "") form.append(key, value);
          });
          await axios.post("/api/supplies/items", form);
        }
        this.showItemModal = false;
        await this.loadAll();
        Swal.fire({ icon: "success", title: `${this.isMaintenanceStoreroom ? "Artículo de pañol" : "Insumo"} guardado`, text: "El catálogo de Abastecimiento quedó actualizado.", timer: 1800, showConfirmButton: false });
      } catch (error) { this.alertError(error); } finally { this.saving = false; }
    },
    async destroyItem(item) {
      const name = item?.inventory_item?.name || this.itemNoun;
      const confirmation = await Swal.fire({
        icon: "warning",
        title: `¿Eliminar ${name} del registro?`,
        text: "Se retirará del catálogo operativo. Si tiene stock o movimientos, la información se conservará de forma segura para mantener la trazabilidad.",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar del registro",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#c34858",
        reverseButtons: true,
      });
      if (!confirmation.isConfirmed) return;

      this.deletingId = item.id;
      try {
        const { data } = await axios.delete(`/api/supplies/items/${item.id}`);
        this.showItemDetailModal = false;
        this.selectedItemDetail = null;
        await this.loadAll();
        Swal.fire({ icon: "success", title: "Producto eliminado", text: data.message, timer: 2300, showConfirmButton: false });
      } catch (error) {
        this.alertError(error);
      } finally {
        this.deletingId = null;
      }
    },
    openNewStoreroom() {
      this.storeroomForm = emptyStoreroom();
      this.showStoreroomModal = true;
    },
    async saveStoreroom() {
      this.saving = true;
      try {
        const { data } = await axios.post("/api/supplies/storerooms", this.storeroomForm);
        this.selectedStoreroomId = data.data.id;
        this.showStoreroomModal = false;
        await this.loadAll();
        Swal.fire({ icon: "success", title: "Bodega creada", text: `${data.data.name} ya está disponible en el Pañol.`, timer: 1900, showConfirmButton: false });
      } catch (error) { this.alertError(error); } finally { this.saving = false; }
    },
    async openInventoryView() {
      this.activeTab = "inventory";
      this.selectedInventoryIds = [];
      await this.loadInventoryCandidates(1);
    },
    async loadInventoryCandidates(page = 1) {
      this.inventoryLoading = true;
      try {
        const { data } = await axios.get("/api/supplies/storerooms/inventory-candidates", {
          params: {
            page,
            search: this.inventorySearch || undefined,
            item_type: this.inventoryType || undefined,
          },
        });
        this.inventoryCandidates = data.data || [];
        this.inventoryPagination = data.meta || this.inventoryPagination;
      } catch (error) { this.alertError(error); } finally { this.inventoryLoading = false; }
    },
    toggleInventoryItem(id) {
      const normalized = Number(id);
      this.selectedInventoryIds = this.selectedInventoryIds.includes(normalized)
        ? this.selectedInventoryIds.filter((itemId) => itemId !== normalized)
        : [...this.selectedInventoryIds, normalized];
    },
    isInventoryItemSelected(id) { return this.selectedInventoryIds.includes(Number(id)); },
    async attachInventoryItems() {
      if (!this.selectedStoreroomId || !this.selectedInventoryIds.length) return;
      this.saving = true;
      try {
        const { data } = await axios.post(`/api/supplies/storerooms/${this.selectedStoreroomId}/items`, {
          inventory_item_ids: this.selectedInventoryIds,
        });
        this.selectedInventoryIds = [];
        this.activeTab = "stock";
        await this.loadAll();
        Swal.fire({ icon: "success", title: "Inventario incorporado", text: data.message, timer: 2200, showConfirmButton: false });
      } catch (error) { this.alertError(error); } finally { this.saving = false; }
    },
    openReceipt() {
      this.receiptForm = { ...emptyReceipt(), storeroom_id: this.isMaintenanceStoreroom ? this.selectedStoreroomId : null };
      this.showReceiptModal = true;
    },
    openDelivery() {
      this.deliveryForm = { ...emptyDelivery(), storeroom_id: this.isMaintenanceStoreroom ? this.selectedStoreroomId : null };
      this.deliveryItemOptions = {};
      this.deliveryItemsError = "";
      this.showDeliveryModal = true;
    },
    onDeliveryRecipientChange(recipientId) {
      const recipient = this.catalogs.delivery_recipients?.find((item) => Number(item.id) === Number(recipientId));
      this.deliveryForm.destination = recipient?.suggested_destination || "";
    },
    addReceiptLine() { this.receiptForm.items.push({ supply_item_id: null, quantity: null, unit_cost: null }); },
    addDeliveryLine() { this.deliveryForm.items.push({ supply_item_id: null, quantity: null, notes: "" }); },
    removeLine(lines, index) { if (lines.length > 1) lines.splice(index, 1); },
    async searchDeliveryItems(search = "") {
      this.deliveryItemsError = "";
      try {
        const { data } = await axios.get("/api/supplies/items", {
          params: {
            ...this.sectionParams(),
            search: String(search || "").trim() || undefined,
            stock_status: "available",
            active_only: 1,
            per_page: 20,
          },
        });
        const options = (data.data || []).map((item) => {
          const inventory = item.inventory_item || {};
          return {
            id: Number(item.id),
            label: inventory.name || `Insumo #${item.id}`,
            name: inventory.name || `Insumo #${item.id}`,
            code: inventory.code || "Sin código",
            type_label: this.typeLabel(item.supply_type),
            stock: this.displayedStock(item),
            unit_label: this.displayedUnit(item),
            photo_url: item.photo_url || null,
            source: item,
          };
        });

        const optionMap = { ...this.deliveryItemOptions };
        options.forEach((option) => { optionMap[String(option.id)] = option; });
        this.deliveryItemOptions = optionMap;

        const selectedIds = new Set(this.deliveryForm.items.map((line) => Number(line.supply_item_id)).filter(Boolean));
        selectedIds.forEach((id) => {
          const selected = optionMap[String(id)];
          if (selected && !options.some((option) => option.id === id)) options.push(selected);
        });

        return options;
      } catch (error) {
        this.deliveryItemsError = error?.response?.data?.message || "No pudimos buscar los insumos. Intenta nuevamente.";
        return Object.values(this.deliveryItemOptions);
      }
    },
    selectedDeliveryProduct(id) {
      return this.deliveryItemOptions[String(id)] || null;
    },
    onDeliveryPhotoError(option) {
      if (option) option.photo_url = null;
    },
    onDeliveryItemChange(line) {
      const selected = this.selectedItem(line.supply_item_id);
      const available = Number(this.selectedDeliveryProduct(line.supply_item_id)?.stock ?? this.displayedStock(selected || {}));
      if (Number(line.quantity || 0) > available) line.quantity = null;
    },
    async saveReceipt() {
      this.saving = true;
      try {
        await axios.post("/api/supplies/receipts", { ...this.receiptForm, section: this.section });
        this.showReceiptModal = false;
        await this.loadAll();
        Swal.fire({ icon: "success", title: "Compra ingresada", text: "Las existencias fueron actualizadas y el movimiento quedó trazado.", timer: 2200, showConfirmButton: false });
      } catch (error) { this.alertError(error); } finally { this.saving = false; }
    },
    async saveDelivery() {
      this.saving = true;
      try {
        const { data } = await axios.post("/api/supplies/deliveries", { ...this.deliveryForm, section: this.section });
        this.showDeliveryModal = false;
        await this.loadAll();
        Swal.fire({ icon: "success", title: "Entrega registrada", text: `Se creó el folio ${data.data.folio} y se descontó el stock.`, timer: 2200, showConfirmButton: false });
        if (this.canExport) await downloadSupplyDeliveryAct(data.data, new Date().toISOString());
      } catch (error) { this.alertError(error); } finally { this.saving = false; }
    },
    async exportDelivery(delivery) {
      this.exportingId = delivery.id;
      try {
        const { data } = await axios.get(`/api/supplies/deliveries/${delivery.id}`);
        await downloadSupplyDeliveryAct(data.data, data.generated_at);
      } catch (error) { this.alertError(error); } finally { this.exportingId = null; }
    },
    selectedItem(id) {
      return this.deliveryItemOptions[String(id)]?.source
        || this.items.find((item) => Number(item.id) === Number(id));
    },
    typeLabel(value) { return this.itemTypes.find((item) => item.value === value)?.label || value || "Otro"; },
    unitLabel(value) { return this.catalogs.units.find((item) => item.value === value)?.label || String(value || "").replaceAll("_", " "); },
    displayedStock(item) {
      return item.inventory_item?.item_type === "asset" ? 1 : Number(item.inventory_item?.stock_quantity || 0);
    },
    displayedUnit(item) {
      return item.inventory_item?.item_type === "asset" ? "artículo individual" : this.unitLabel(item.inventory_item?.unit_of_measure);
    },
    stockClass(item) {
      const inv = item.inventory_item || {};
      if (inv.item_type === "asset") return "is-ok";
      const stock = Number(inv.stock_quantity || 0); const minimum = Number(inv.minimum_stock || 0);
      if (stock <= 0) return "is-empty";
      if (inv.minimum_stock !== null && stock <= minimum) return "is-low";
      return "is-ok";
    },
    stockLabel(item) {
      if (item.inventory_item?.item_type === "asset") return "Individual";
      const status = this.stockClass(item);
      return status === "is-empty" ? "Sin stock" : status === "is-low" ? "Stock bajo" : "Disponible";
    },
    number(value) { return new Intl.NumberFormat("es-CL", { maximumFractionDigits: 2 }).format(Number(value || 0)); },
    money(value) { return value === null || value === undefined ? "—" : new Intl.NumberFormat("es-CL", { style: "currency", currency: "CLP", maximumFractionDigits: 0 }).format(Number(value)); },
    date(value) { if (!value) return "—"; return new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString("es-CL"); },
    alertError(error) {
      const errors = error?.response?.data?.errors;
      const message = errors ? Object.values(errors).flat()[0] : (error?.response?.data?.message || "No fue posible completar la operación.");
      Swal.fire({ icon: "error", title: "Revisa la información", text: message });
    },
  },
};
</script>

<template>
  <Layout>
    <main class="supply-page" :class="{ 'is-heating': isHeating, 'is-storeroom': isMaintenanceStoreroom }">
      <section class="supply-hero">
        <div class="supply-hero__copy">
          <span class="supply-eyebrow"><i class="bx bx-package"></i> ABASTECIMIENTO</span>
          <h1>{{ sectionTitle }}</h1>
          <p>{{ sectionDescription }}</p>
          <nav class="supply-switch" aria-label="Submódulos de abastecimiento">
            <router-link to="/supplies/cleaning" :class="{ active: isCleaning }"><i class="bx bx-spray-can"></i> Insumos de aseo</router-link>
            <router-link to="/supplies/requests"><i class="bx bx-list-check"></i> Solicitudes</router-link>
            <router-link to="/supplies/heating" :class="{ active: isHeating }"><i class="bx bx-hot"></i> Combustibles y calefacción</router-link>
            <router-link to="/supplies/maintenance-storeroom" :class="{ active: isMaintenanceStoreroom }"><i class="bx bx-wrench"></i> Pañol de mantenimiento</router-link>
          </nav>
        </div>
        <div class="supply-hero__mark"><i class="bx" :class="sectionIcon"></i><span>{{ stockLocationLabel }}</span><strong>{{ movementLabel }}</strong></div>
      </section>

      <section v-if="isMaintenanceStoreroom" class="storeroom-switcher" aria-label="Bodegas del pañol de mantenimiento">
        <div class="storeroom-switcher__copy"><span><i class="bx bx-buildings"></i></span><div><small>BODEGA ACTIVA</small><strong>{{ selectedStoreroom?.name || "Selecciona una bodega" }}</strong><p>El stock, los ingresos y las salidas se muestran sólo para esta ubicación.</p></div></div>
        <div class="storeroom-switcher__controls">
          <label><span>Bodega</span><select v-model="selectedStoreroomId" @change="changeStoreroom"><option v-for="storeroom in storerooms" :key="storeroom.id" :value="storeroom.id">{{ storeroom.name }} · {{ storeroom.items_count || 0 }} artículo(s)</option></select></label>
          <button v-if="canManage" type="button" class="storeroom-action storeroom-action--inventory" @click="openInventoryView"><i class="bx bx-search-alt"></i><span>Buscar en Inventario<small>Incorporar artículos existentes</small></span></button>
          <button v-if="canManage" type="button" class="storeroom-action" @click="openNewStoreroom"><i class="bx bx-plus"></i><span>Nueva bodega<small>Crear otra ubicación</small></span></button>
        </div>
      </section>

      <section class="supply-stats" aria-label="Resumen de existencias">
        <article><span class="stat-icon stat-icon--blue"><i class="bx bx-grid-alt"></i></span><div><small>Catálogo</small><strong>{{ summary.total_items || 0 }}</strong><p>{{ catalogNoun }} registrados</p></div></article>
        <article><span class="stat-icon stat-icon--green"><i class="bx bx-check-circle"></i></span><div><small>Activos</small><strong>{{ summary.active_items || 0 }}</strong><p>disponibles para operar</p></div></article>
        <article><span class="stat-icon stat-icon--amber"><i class="bx bx-error-circle"></i></span><div><small>Stock bajo</small><strong>{{ summary.low_stock_items || 0 }}</strong><p>requieren reposición</p></div></article>
        <article><span class="stat-icon stat-icon--red"><i class="bx bx-x-circle"></i></span><div><small>Agotados</small><strong>{{ summary.empty_items || 0 }}</strong><p>sin existencias</p></div></article>
      </section>

      <section class="supply-workspace">
        <header class="workspace-head">
          <div class="workspace-tabs" role="tablist">
            <button :class="{ active: activeTab === 'stock' }" @click="activeTab = 'stock'"><i class="bx bx-package"></i> Stock</button>
            <button :class="{ active: activeTab === 'receipts' }" @click="activeTab = 'receipts'"><i class="bx bx-cart-download"></i> {{ isMaintenanceStoreroom ? "Ingresos" : "Compras" }}</button>
            <button :class="{ active: activeTab === 'deliveries' }" @click="activeTab = 'deliveries'"><i class="bx bx-file"></i> {{ isMaintenanceStoreroom ? "Salidas y actas" : "Entregas y actas" }}</button>
            <button v-if="isMaintenanceStoreroom" :class="{ active: activeTab === 'inventory' }" @click="openInventoryView"><i class="bx bx-transfer-alt"></i> Incorporar desde Inventario</button>
          </div>
          <div class="workspace-actions">
            <button v-if="canManage" class="action-button action-button--ghost" @click="openNewItem"><i class="bx bx-plus"></i> {{ isMaintenanceStoreroom ? "Nueva herramienta o artículo" : "Nuevo insumo" }}</button>
            <button v-if="canReceive" class="action-button action-button--in" @click="openReceipt"><i class="bx bx-cart-download"></i> {{ isMaintenanceStoreroom ? "Registrar ingreso" : "Cargar compra" }}</button>
            <button v-if="canDeliver" class="action-button action-button--out" @click="openDelivery"><i class="bx bx-package"></i> {{ isMaintenanceStoreroom ? "Registrar salida" : "Emitir entrega" }}</button>
          </div>
        </header>

        <div v-if="activeTab === 'stock'" class="stock-panel">
          <div class="compact-filters">
            <label class="search-control"><i class="bx bx-search"></i><input v-model.trim="search" type="search" placeholder="Buscar por nombre o código" @keyup.enter="loadItems(true)" /></label>
            <select v-model="stockStatus" @change="loadItems(true)"><option value="">Todos los estados</option><option value="available">Disponible</option><option value="low">Stock bajo</option><option value="empty">Sin stock</option></select>
            <button class="filter-button" @click="loadItems(true)"><i class="bx bx-filter-alt"></i> Aplicar</button>
          </div>
          <LoadingState v-if="loading" :message="isMaintenanceStoreroom ? 'Cargando pañol...' : 'Cargando abastecimiento...'" compact />
          <div v-else-if="!items.length" class="empty-state"><i class="bx" :class="sectionIcon"></i><strong>{{ isMaintenanceStoreroom ? "Aún no hay herramientas ni artículos" : `Aún no hay ${catalogNoun}` }}</strong><p>Crea el primer registro y luego ingresa existencias para cargar su stock.</p><button v-if="canManage" @click="openNewItem">Crear {{ itemNoun }}</button></div>
          <div v-else class="stock-table-block">
            <div class="table-wrap">
              <table class="supply-table">
              <colgroup>
                <col class="supply-col supply-col--product" />
                <col class="supply-col supply-col--type" />
                <col class="supply-col supply-col--stock" />
                <col class="supply-col supply-col--minimum" />
                <col class="supply-col supply-col--supplier" />
                <col class="supply-col supply-col--status" />
                <col class="supply-col supply-col--actions" />
              </colgroup>
              <thead><tr><th>{{ isMaintenanceStoreroom ? "Herramienta / artículo" : "Insumo" }}</th><th>Tipo</th><th>Existencias</th><th>Mínimo</th><th>Proveedor ref.</th><th>Estado</th><th>Acciones</th></tr></thead>
              <tbody>
                <tr v-for="item in items" :key="item.id">
                  <td><div class="product-cell"><span class="product-photo"><img v-if="item.photo_url" :src="item.photo_url" :alt="`Foto de ${item.inventory_item?.name}`" /><i v-else class="bx" :class="sectionIcon"></i></span><div><strong>{{ item.inventory_item?.name }}</strong><small>{{ item.inventory_item?.code }}</small><p>{{ item.inventory_item?.description || 'Sin descripción' }}</p></div></div></td>
                  <td><span class="type-chip">{{ typeLabel(item.supply_type) }}</span></td>
                  <td><div class="stock-value"><strong>{{ number(displayedStock(item)) }}</strong><span>{{ displayedUnit(item) }}</span></div></td>
                  <td>{{ item.inventory_item?.item_type === 'asset' || item.inventory_item?.minimum_stock === null ? '—' : `${number(item.inventory_item?.minimum_stock)} ${unitLabel(item.inventory_item?.unit_of_measure)}` }}</td>
                  <td>{{ item.inventory_item?.supplier?.name || 'Sin proveedor' }}</td>
                  <td><span class="stock-status" :class="stockClass(item)"><i class="bx bxs-circle"></i>{{ stockLabel(item) }}</span></td>
                  <td>
                    <div class="row-actions">
                      <button class="row-action row-action--view" :title="`Ver ${itemNoun}`" :aria-label="`Ver ${itemNoun}`" @click="openViewItem(item)"><i class="bx bx-show"></i><span>Ver</span></button>
                      <button v-if="canManage" class="row-action row-action--edit" :title="`Editar ${itemNoun}`" :aria-label="`Editar ${itemNoun}`" @click="openEditItem(item)"><i class="bx bx-edit-alt"></i><span>Editar</span></button>
                      <button v-if="canManage" class="row-action row-action--delete" :title="`Eliminar ${itemNoun}`" :aria-label="`Eliminar ${itemNoun}`" :disabled="deletingId === item.id" @click="destroyItem(item)"><i class="bx bx-trash"></i><span>{{ deletingId === item.id ? "Eliminando..." : "Eliminar" }}</span></button>
                    </div>
                  </td>
                </tr>
              </tbody>
              </table>
            </div>
            <footer class="table-pagination" aria-label="Paginación del catálogo de abastecimiento">
              <p>
                Mostrando <strong>{{ paginationFrom }}–{{ paginationTo }}</strong>
                de <strong>{{ pagination.total }}</strong> {{ catalogNoun }}
              </p>
              <nav class="table-pagination__controls" aria-label="Páginas del catálogo">
                <button type="button" class="table-pagination__nav" :disabled="pagination.current_page <= 1 || loading" aria-label="Ir a la página anterior" @click="goToPage(pagination.current_page - 1)">
                  <i class="bx bx-chevron-left"></i><span>Anterior</span>
                </button>
                <button
                  v-for="page in paginationPages"
                  :key="page"
                  type="button"
                  class="table-pagination__page"
                  :class="{ active: page === pagination.current_page }"
                  :aria-label="`Ir a la página ${page}`"
                  :aria-current="page === pagination.current_page ? 'page' : undefined"
                  :disabled="loading"
                  @click="goToPage(page)"
                >{{ page }}</button>
                <button type="button" class="table-pagination__nav" :disabled="pagination.current_page >= pagination.last_page || loading" aria-label="Ir a la página siguiente" @click="goToPage(pagination.current_page + 1)">
                  <span>Siguiente</span><i class="bx bx-chevron-right"></i>
                </button>
              </nav>
            </footer>
          </div>
        </div>

        <div v-else-if="activeTab === 'receipts'" class="history-panel">
          <div class="history-intro"><div><span>INGRESOS A BODEGA</span><h2>{{ isMaintenanceStoreroom ? "Ingresos registrados" : "Compras registradas" }}</h2><p>Cada línea aumenta el inventario central y conserva el saldo anterior y posterior.</p></div><button v-if="canReceive" class="action-button action-button--in" @click="openReceipt"><i class="bx bx-plus"></i> {{ isMaintenanceStoreroom ? "Nuevo ingreso" : "Nueva compra" }}</button></div>
          <div v-if="!receipts.length" class="empty-state"><i class="bx bx-cart-download"></i><strong>Sin ingresos registrados</strong><p>Las nuevas adquisiciones o incorporaciones aparecerán aquí con su documento y proveedor.</p></div>
          <article v-for="receipt in receipts" :key="receipt.id" class="movement-card">
            <div class="movement-card__folio"><span>INGRESO</span><strong>{{ receipt.folio }}</strong><small>{{ date(receipt.purchased_at) }}</small></div>
            <div class="movement-card__body"><h3>{{ receipt.supplier?.name || 'Proveedor no informado' }}</h3><p>{{ receipt.document_type || 'Documento' }} {{ receipt.document_number || 'sin número' }} · {{ money(receipt.total_amount) }}</p><div class="line-chips"><span v-for="line in receipt.items" :key="line.id"><strong>+{{ number(line.quantity) }}</strong> {{ unitLabel(line.unit_snapshot) }} · {{ line.supply_item?.inventory_item?.name }}</span></div></div>
            <div class="movement-card__user"><i class="bx bx-user-circle"></i><span><small>Recibido por</small><strong>{{ receipt.receiver?.name || 'No informado' }}</strong></span></div>
          </article>
        </div>

        <div v-else-if="activeTab === 'deliveries'" class="history-panel">
          <div class="history-intro"><div><span>SALIDAS DE BODEGA</span><h2>{{ isMaintenanceStoreroom ? "Salidas y actas" : "Entregas y actas" }}</h2><p>Consulta el receptor, destino y detalle; vuelve a descargar el acta cuando la necesites.</p></div><button v-if="canDeliver" class="action-button action-button--out" @click="openDelivery"><i class="bx bx-plus"></i> {{ isMaintenanceStoreroom ? "Nueva salida" : "Nueva entrega" }}</button></div>
          <div v-if="!deliveries.length" class="empty-state"><i class="bx bx-file"></i><strong>Sin entregas registradas</strong><p>La primera entrega generará automáticamente su folio y acta PDF.</p></div>
          <article v-for="delivery in deliveries" :key="delivery.id" class="movement-card movement-card--delivery">
            <div class="movement-card__folio"><span>ENTREGA</span><strong>{{ delivery.folio }}</strong><small>{{ date(delivery.delivered_at) }}</small></div>
            <div class="movement-card__body"><h3>{{ delivery.recipient_name }}</h3><p>{{ delivery.recipient_role || 'Cargo no informado' }} · {{ delivery.destination || 'Destino no informado' }}</p><div class="line-chips"><span v-for="line in delivery.items" :key="line.id"><strong>-{{ number(line.quantity) }}</strong> {{ unitLabel(line.unit_snapshot) }} · {{ line.item_name_snapshot }}</span></div></div>
            <button v-if="canExport" class="pdf-button" :disabled="exportingId === delivery.id" @click="exportDelivery(delivery)"><i class="bx bxs-file-pdf"></i><span><strong>{{ exportingId === delivery.id ? 'Generando...' : 'Descargar acta' }}</strong><small>PDF con firmas</small></span></button>
          </article>
        </div>

        <div v-else class="inventory-import-panel">
          <header class="inventory-import-head">
            <div class="inventory-import-head__icon"><i class="bx bx-transfer-alt"></i></div>
            <div><span>INVENTARIO INSTITUCIONAL</span><h2>Incorporar artículos a {{ selectedStoreroom?.name || "la bodega" }}</h2><p>Busca herramientas y artículos ya registrados. Al incorporarlos se conserva su ficha, código, fotografía y trazabilidad original.</p></div>
          </header>

          <form class="inventory-search" @submit.prevent="loadInventoryCandidates(1)">
            <label class="inventory-search__text"><i class="bx bx-search"></i><span>Buscar artículo</span><input v-model.trim="inventorySearch" type="search" placeholder="Nombre, código, marca, modelo o serie" /></label>
            <label><span>Tipo</span><select v-model="inventoryType"><option value="">Todos</option><option value="asset">Activo individual</option><option value="consumable">Consumible</option></select></label>
            <button type="submit"><i class="bx bx-search-alt"></i> Buscar</button>
          </form>

          <LoadingState v-if="inventoryLoading" message="Buscando artículos disponibles en Inventario..." compact />
          <div v-else-if="!inventoryCandidates.length" class="inventory-empty">
            <i class="bx bx-search-alt"></i><strong>No hay artículos disponibles</strong><p>Prueba con otro término o tipo. Los artículos ya incorporados a un pañol no se vuelven a mostrar.</p>
          </div>
          <template v-else>
            <div class="inventory-results-summary"><span><strong>{{ inventoryPagination.total || inventoryCandidates.length }}</strong> artículo(s) disponible(s)</span><small>Selecciona uno o varios para llevarlos a la bodega activa.</small></div>
            <div class="inventory-grid">
              <button v-for="candidate in inventoryCandidates" :key="candidate.id" type="button" class="inventory-card" :class="{ selected: isInventoryItemSelected(candidate.id) }" @click="toggleInventoryItem(candidate.id)">
                <span class="inventory-card__check"><i class="bx" :class="isInventoryItemSelected(candidate.id) ? 'bx-check' : 'bx-plus'"></i></span>
                <span class="inventory-card__image"><img v-if="candidate.image_url" :src="candidate.image_url" :alt="`Foto de ${candidate.name}`" /><i v-else class="bx" :class="candidate.item_type === 'asset' ? 'bx-wrench' : 'bx-package'"></i></span>
                <span class="inventory-card__body"><small>{{ candidate.code }}</small><strong>{{ candidate.name }}</strong><em>{{ candidate.category?.name || "Sin categoría" }}</em><span>{{ candidate.brand || candidate.model ? [candidate.brand, candidate.model].filter(Boolean).join(" · ") : (candidate.description || "Sin referencia adicional") }}</span></span>
                <span class="inventory-card__meta"><span><i class="bx bx-buildings"></i>{{ candidate.dependency?.name || "Sin dependencia" }}</span><span><i class="bx bx-cube"></i>{{ candidate.item_type === "asset" ? "Activo individual" : `${number(candidate.available_quantity)} ${unitLabel(candidate.unit_of_measure)}` }}</span></span>
              </button>
            </div>
            <div v-if="inventoryPagination.last_page > 1" class="inventory-pagination">
              <button type="button" :disabled="inventoryPagination.current_page <= 1" @click="loadInventoryCandidates(inventoryPagination.current_page - 1)"><i class="bx bx-chevron-left"></i> Anterior</button>
              <span>Página <strong>{{ inventoryPagination.current_page }}</strong> de {{ inventoryPagination.last_page }}</span>
              <button type="button" :disabled="inventoryPagination.current_page >= inventoryPagination.last_page" @click="loadInventoryCandidates(inventoryPagination.current_page + 1)">Siguiente <i class="bx bx-chevron-right"></i></button>
            </div>
          </template>

          <footer class="inventory-selection-bar">
            <div><span>{{ selectedInventoryCount }}</span><p><strong>{{ selectedInventoryCount === 1 ? "artículo seleccionado" : "artículos seleccionados" }}</strong><small>Destino: {{ selectedStoreroom?.name || "Selecciona una bodega" }}</small></p></div>
            <button type="button" :disabled="saving || !selectedInventoryCount || !selectedStoreroomId" @click="attachInventoryItems"><i class="bx bx-transfer-alt"></i>{{ saving ? "Incorporando..." : "Incorporar al pañol" }}</button>
          </footer>
        </div>
      </section>
    </main>

    <BModal v-model="showStoreroomModal" size="lg" hide-footer title="Nueva bodega del pañol" modal-class="supply-modal">
      <form class="supply-form" @submit.prevent="saveStoreroom">
        <div class="form-banner form-banner--storeroom"><i class="bx bx-buildings"></i><div><strong>Crea una ubicación independiente</strong><p>Cada bodega tendrá su propio catálogo, ingresos, salidas y stock visible.</p></div></div>
        <div class="form-grid">
          <label class="wide">Nombre de la bodega<input v-model.trim="storeroomForm.name" required maxlength="255" placeholder="Ej.: Pañol edificio central" /></label>
          <label>Código interno<input v-model.trim="storeroomForm.code" maxlength="40" placeholder="Automático si lo omites" /></label>
          <label class="wide">Descripción o ubicación<textarea v-model.trim="storeroomForm.description" rows="3" maxlength="2000" placeholder="Sector, responsable o referencia para encontrar esta bodega"></textarea></label>
        </div>
        <footer><button type="button" class="btn btn-light" @click="showStoreroomModal = false">Cancelar</button><button class="btn btn-primary" :disabled="saving"><i class="bx bx-plus"></i>{{ saving ? "Creando..." : "Crear bodega" }}</button></footer>
      </form>
    </BModal>

    <BModal v-model="showItemModal" size="lg" hide-footer :title="itemForm.id ? `Editar ${itemNoun}` : `Nueva ${itemNoun}`" modal-class="supply-modal">
      <form class="supply-form" @submit.prevent="saveItem">
        <div class="form-photo">
          <div class="photo-picker" aria-live="polite"><img v-if="photoPreview" :src="photoPreview" alt="Vista previa del insumo" /><span v-else><i class="bx bx-image-add"></i><strong>Foto de referencia</strong><small>Aún no seleccionada</small></span></div>
          <div class="form-photo__content">
            <h3>Identificación visual</h3>
            <p>{{ isMaintenanceStoreroom ? "Fotografía el elemento en el momento o elige una imagen guardada." : "Fotografía el insumo en el momento o elige una imagen guardada." }}</p>
            <div class="photo-actions">
              <button type="button" class="photo-action photo-action--camera" @click="startCamera"><i class="bx bx-camera"></i> Tomar foto</button>
              <input ref="cameraInput" type="file" class="d-none" accept="image/*" capture="environment" @change="selectPhoto" />
              <label class="photo-action photo-action--gallery"><i class="bx bx-images"></i> Elegir de galería<input type="file" class="d-none" accept="image/*" @change="selectPhoto" /></label>
            </div>
          </div>
        </div>
        <section v-if="cameraActive || cameraError" class="supply-camera" aria-label="Cámara para fotografiar el insumo">
          <div v-if="cameraError" class="supply-camera__error"><i class="bx bx-error-circle"></i><span>{{ cameraError }}</span></div>
          <template v-else>
            <div class="supply-camera__preview"><video ref="cameraVideo" autoplay muted playsinline></video><span><i class="bx bx-camera"></i> Cámara activa</span></div>
            <canvas ref="cameraCanvas" class="d-none"></canvas>
            <div class="supply-camera__actions"><button type="button" class="btn btn-light" @click="stopCamera"><i class="bx bx-x"></i> Cerrar</button><button type="button" class="btn btn-primary" @click="capturePhoto"><i class="bx bx-camera"></i> Capturar foto</button></div>
          </template>
        </section>
        <div class="form-grid"><label class="wide">Nombre de {{ itemNoun }}<input v-model.trim="itemForm.name" required maxlength="255" /></label><label v-if="isMaintenanceStoreroom">Bodega del pañol<select v-model="itemForm.storeroom_id" required><option :value="null" disabled>Seleccionar...</option><option v-for="storeroom in storerooms" :key="storeroom.id" :value="storeroom.id">{{ storeroom.name }}</option></select></label><label>Tipo<select v-model="itemForm.supply_type" required><option v-for="type in itemTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select></label><label>Unidad de control<select v-model="itemForm.unit_of_measure" required><option v-for="unit in catalogs.units" :key="unit.value" :value="unit.value">{{ unit.label }}</option></select></label><label>Stock mínimo<input v-model.number="itemForm.minimum_stock" type="number" min="0" step="0.01" placeholder="Opcional" /></label><label>Proveedor de referencia<select v-model="itemForm.supplier_id"><option :value="null">Sin proveedor</option><option v-for="supplier in catalogs.suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select></label><label class="wide">Descripción<textarea v-model.trim="itemForm.description" rows="3" placeholder="Marca, modelo, medida, uso o referencia útil"></textarea></label><label v-if="itemForm.id" class="active-check"><input v-model="itemForm.active" type="checkbox" /> Disponible para ingresos y salidas</label></div>
        <footer><button type="button" class="btn btn-light" @click="showItemModal = false">Cancelar</button><button class="btn btn-primary" :disabled="saving"><i class="bx bx-save"></i> {{ saving ? 'Guardando...' : `Guardar ${itemNoun}` }}</button></footer>
      </form>
    </BModal>

    <BModal v-model="showItemDetailModal" size="lg" hide-footer :title="`Detalle de ${itemNoun}`" modal-class="supply-modal">
      <LoadingState v-if="detailLoading" message="Cargando detalle del insumo..." compact />
      <section v-else-if="selectedItemDetail" class="item-detail">
        <header class="item-detail__hero">
          <span class="item-detail__photo"><img v-if="selectedItemDetail.photo_url" :src="selectedItemDetail.photo_url" :alt="`Foto de ${selectedItemDetail.inventory_item?.name}`" /><i v-else class="bx" :class="sectionIcon"></i></span>
          <div class="item-detail__identity">
            <small>{{ selectedItemDetail.inventory_item?.code || "SIN CÓDIGO" }}</small>
            <h3>{{ selectedItemDetail.inventory_item?.name }}</h3>
            <div><span class="type-chip">{{ typeLabel(selectedItemDetail.supply_type) }}</span><span class="stock-status" :class="stockClass(selectedItemDetail)"><i class="bx bxs-circle"></i>{{ stockLabel(selectedItemDetail) }}</span></div>
          </div>
        </header>
        <div class="item-detail__grid">
          <article><small>Existencias</small><strong>{{ number(displayedStock(selectedItemDetail)) }}</strong><span>{{ displayedUnit(selectedItemDetail) }}</span></article>
          <article><small>Stock mínimo</small><strong>{{ selectedItemDetail.inventory_item?.minimum_stock === null ? "—" : number(selectedItemDetail.inventory_item?.minimum_stock) }}</strong><span>{{ selectedItemDetail.inventory_item?.minimum_stock === null ? "No definido" : unitLabel(selectedItemDetail.inventory_item?.unit_of_measure) }}</span></article>
          <article><small>Proveedor de referencia</small><strong>{{ selectedItemDetail.inventory_item?.supplier?.name || "Sin proveedor" }}</strong><span>{{ selectedItemDetail.inventory_item?.supplier?.rut || "No informado" }}</span></article>
          <article><small>Disponibilidad</small><strong>{{ selectedItemDetail.inventory_item?.active ? "Activo" : "Inactivo" }}</strong><span>{{ selectedItemDetail.inventory_item?.active ? "Habilitado para operar" : "Fuera de operación" }}</span></article>
        </div>
        <div class="item-detail__description"><small>DESCRIPCIÓN</small><p>{{ selectedItemDetail.inventory_item?.description || "Este insumo no tiene una descripción registrada." }}</p></div>
        <div class="item-detail__trace"><span><i class="bx bx-cart-download"></i><strong>{{ selectedItemDetail.receipt_items_count || 0 }}</strong><small>compras</small></span><span><i class="bx bx-package"></i><strong>{{ selectedItemDetail.delivery_items_count || 0 }}</strong><small>entregas</small></span><span><i class="bx bx-list-check"></i><strong>{{ selectedItemDetail.request_items_count || 0 }}</strong><small>solicitudes</small></span></div>
        <footer class="item-detail__actions">
          <button type="button" class="btn btn-light" @click="showItemDetailModal = false">Cerrar</button>
          <button v-if="canManage" type="button" class="btn item-detail__delete" :disabled="deletingId === selectedItemDetail.id" @click="destroyItem(selectedItemDetail)"><i class="bx bx-trash"></i>{{ deletingId === selectedItemDetail.id ? "Eliminando..." : "Eliminar" }}</button>
          <button v-if="canManage" type="button" class="btn btn-primary" @click="openEditFromDetail"><i class="bx bx-edit-alt"></i> Editar</button>
        </footer>
      </section>
    </BModal>

    <BModal v-model="showReceiptModal" size="xl" hide-footer :title="isMaintenanceStoreroom ? 'Registrar ingreso al pañol' : 'Cargar compra al inventario'" modal-class="supply-modal">
      <form class="supply-form" @submit.prevent="saveReceipt">
        <div class="form-banner form-banner--in"><i class="bx bx-cart-download"></i><div><strong>{{ isMaintenanceStoreroom ? "Ingreso al pañol" : "Ingreso a bodega" }}</strong><p>Las cantidades se sumarán al stock actual cuando confirmes.</p></div></div>
        <div class="form-grid"><label>Fecha de compra<input v-model="receiptForm.purchased_at" type="date" required /></label><label>Proveedor<select v-model="receiptForm.supplier_id"><option :value="null">No informado</option><option v-for="supplier in catalogs.suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select></label><label>Tipo de documento<select v-model="receiptForm.document_type"><option>Factura</option><option>Boleta</option><option>Guía de despacho</option><option>Orden de compra</option><option>Otro</option></select></label><label>Número de documento<input v-model.trim="receiptForm.document_number" maxlength="100" /></label><label>Monto total (CLP)<input v-model.number="receiptForm.total_amount" type="number" min="0" step="1" /></label><label class="wide">Observaciones<textarea v-model.trim="receiptForm.notes" rows="2"></textarea></label></div>
        <section class="lines-editor"><header><div><span>{{ isMaintenanceStoreroom ? "DETALLE DEL INGRESO" : "DETALLE DE LA COMPRA" }}</span><strong>{{ receiptForm.items.length }} línea(s)</strong></div><button type="button" @click="addReceiptLine"><i class="bx bx-plus"></i> Agregar {{ itemNoun }}</button></header><div v-for="(line, index) in receiptForm.items" :key="index" class="editor-line"><span class="line-number">{{ index + 1 }}</span><label>{{ isMaintenanceStoreroom ? "Herramienta / artículo" : "Insumo" }}<select v-model="line.supply_item_id" required><option :value="null" disabled>Seleccionar...</option><option v-for="item in activeItems" :key="item.id" :value="item.id">{{ item.inventory_item?.name }} · stock {{ number(item.inventory_item?.stock_quantity) }}</option></select></label><label>Cantidad<input v-model.number="line.quantity" type="number" min="0.01" step="0.01" required /></label><label>Costo unitario<input v-model.number="line.unit_cost" type="number" min="0" step="1" /></label><button type="button" class="remove-line" :disabled="receiptForm.items.length === 1" @click="removeLine(receiptForm.items, index)"><i class="bx bx-trash"></i></button></div></section>
        <footer><button type="button" class="btn btn-light" @click="showReceiptModal = false">Cancelar</button><button class="btn btn-success" :disabled="saving"><i class="bx bx-check"></i> {{ saving ? 'Registrando...' : (isMaintenanceStoreroom ? 'Registrar ingreso' : 'Registrar compra') }}</button></footer>
      </form>
    </BModal>

    <BModal v-model="showDeliveryModal" size="xl" hide-header hide-footer centered scrollable body-class="p-0" modal-class="supply-modal supply-delivery-modal">
      <form class="supply-form delivery-form" @submit.prevent="saveDelivery">
        <header class="delivery-modal-hero">
          <span class="delivery-modal-hero__icon"><i class="bx bx-package"></i></span>
          <div>
            <small>ABASTECIMIENTO · SALIDA DE STOCK</small>
            <h2>{{ isMaintenanceStoreroom ? "Registrar salida del pañol" : "Registrar entrega de insumos" }}</h2>
            <p>Selecciona al receptor y busca cada producto. Al confirmar se descontará el stock y quedará disponible el acta PDF.</p>
          </div>
          <span class="delivery-modal-hero__badge"><i class="bx bxs-file-pdf"></i> Acta trazable</span>
          <button type="button" class="delivery-modal-close" aria-label="Cerrar formulario de entrega" @click="showDeliveryModal = false"><i class="bx bx-x"></i></button>
        </header>

        <div class="delivery-modal-body">
          <section class="delivery-section delivery-section--context">
            <header class="delivery-section__head">
              <span>1</span>
              <div><h3>Datos de la entrega</h3><p>Indica cuándo, quién recibe y hacia qué dependencia se dirige.</p></div>
            </header>
            <div class="delivery-context-grid">
              <label class="delivery-field delivery-field--date"><span>Fecha de entrega</span><input v-model="deliveryForm.delivered_at" type="date" required /></label>
              <div class="delivery-field delivery-field--recipient">
                <span>Funcionario receptor</span>
                <Multiselect v-model="deliveryForm.recipient_staff_id" class="recipient-select" :options="catalogs.delivery_recipients" value-prop="id" label="name" :searchable="true" :can-clear="true" :close-on-select="true" placeholder="Buscar por nombre, cargo o RUT..." no-options-text="No hay personal habilitado para recibir" no-results-text="No encontramos funcionarios" aria-label="Buscar funcionario receptor" @change="onDeliveryRecipientChange">
                  <template #option="{ option }"><div class="recipient-option"><strong>{{ option.name }}</strong><small>{{ option.role || 'Cargo no registrado' }}<template v-if="option.rut"> · {{ option.rut }}</template></small></div></template>
                </Multiselect>
              </div>
              <div v-if="selectedDeliveryRecipient" class="recipient-summary"><span class="recipient-summary__avatar"><i class="bx bx-user-check"></i></span><div><small>RECEPTOR SELECCIONADO</small><strong>{{ selectedDeliveryRecipient.name }}</strong><p>{{ selectedDeliveryRecipient.role || 'Cargo no registrado' }} · {{ selectedDeliveryRecipient.rut || 'RUT no registrado' }}</p></div><span class="recipient-summary__permission"><i class="bx bx-check-shield"></i> Recibe OT</span></div>
              <label class="delivery-field"><span>Destino / dependencia</span><input v-model.trim="deliveryForm.destination" maxlength="255" placeholder="Ej.: Auxiliares, internado..." /></label>
              <label class="delivery-field delivery-field--notes"><span>Observaciones generales</span><textarea v-model.trim="deliveryForm.notes" rows="2" placeholder="Información adicional para el acta (opcional)"></textarea></label>
            </div>
          </section>

          <section class="delivery-section delivery-lines-editor">
            <header class="delivery-section__head delivery-lines-head">
              <span>2</span>
              <div><h3>{{ isMaintenanceStoreroom ? "Herramientas y artículos" : "Productos a entregar" }}</h3><p>Escribe parte del nombre o código para encontrar rápidamente el producto.</p></div>
              <div class="delivery-lines-head__actions"><small><strong>{{ deliverySelectedCount }}</strong> seleccionado(s)</small><button type="button" @click="addDeliveryLine"><i class="bx bx-plus"></i> Agregar {{ itemNoun }}</button></div>
            </header>

            <div class="delivery-lines-list">
              <article v-for="(line, index) in deliveryForm.items" :key="index" class="delivery-line">
                <span class="delivery-line__number">{{ index + 1 }}</span>
                <div class="delivery-field delivery-line__product">
                  <span>{{ isMaintenanceStoreroom ? "Herramienta / artículo" : "Insumo" }}</span>
                  <Multiselect
                    v-model="line.supply_item_id"
                    class="delivery-product-select"
                    :options="searchDeliveryItems"
                    value-prop="id"
                    label="label"
                    :track-by="['label', 'code']"
                    :searchable="true"
                    :filter-results="false"
                    :resolve-on-load="true"
                    :delay="260"
                    :min-chars="0"
                    :clear-on-search="true"
                    :can-clear="true"
                    :can-deselect="true"
                    :close-on-select="true"
                    :append-to-body="false"
                    :allow-absent="true"
                    input-type="search"
                    autocomplete="off"
                    placeholder="Buscar producto por nombre o código..."
                    no-options-text="Escribe para buscar productos con stock"
                    no-results-text="No encontramos productos disponibles"
                    :aria-label="`Buscar producto para la línea ${index + 1}`"
                    required
                    @change="onDeliveryItemChange(line)"
                  >
                    <template #singlelabel="{ value }">
                      <div class="delivery-product-selected">
                        <span class="delivery-product-selected__image"><img v-if="value.photo_url" :src="value.photo_url" alt="" @error="onDeliveryPhotoError(value)" /><i v-else class="bx" :class="sectionIcon"></i></span>
                        <span><strong>{{ value.name || value.label }}</strong><small>{{ value.code }} · {{ number(value.stock) }} {{ value.unit_label }} disponibles</small></span>
                      </div>
                    </template>
                    <template #option="{ option }">
                      <div class="delivery-product-option">
                        <span class="delivery-product-option__image"><img v-if="option.photo_url" :src="option.photo_url" alt="" @error="onDeliveryPhotoError(option)" /><i v-else class="bx" :class="sectionIcon"></i></span>
                        <span class="delivery-product-option__copy"><strong>{{ option.name }}</strong><small>{{ option.code }} · {{ option.type_label }}</small></span>
                        <span class="delivery-product-option__stock"><small>DISPONIBLE</small><strong>{{ number(option.stock) }}</strong><em>{{ option.unit_label }}</em></span>
                      </div>
                    </template>
                    <template #nooptions><div class="delivery-product-empty"><i class="bx bx-search-alt"></i><strong>Busca un producto</strong><span>Escribe su nombre o código interno.</span></div></template>
                    <template #noresults><div class="delivery-product-empty"><i class="bx bx-package"></i><strong>Sin coincidencias con stock</strong><span>Prueba con otro nombre o revisa el código.</span></div></template>
                  </Multiselect>
                  <small v-if="selectedDeliveryProduct(line.supply_item_id)" class="delivery-stock-hint"><i class="bx bx-check-circle"></i> Stock máximo para esta entrega: <strong>{{ number(selectedDeliveryProduct(line.supply_item_id).stock) }} {{ selectedDeliveryProduct(line.supply_item_id).unit_label }}</strong></small>
                </div>
                <label class="delivery-field delivery-line__quantity"><span>Cantidad</span><input v-model.number="line.quantity" type="number" min="0.01" :max="selectedDeliveryProduct(line.supply_item_id)?.stock" step="0.01" placeholder="0" required /><small v-if="selectedDeliveryProduct(line.supply_item_id)">Máx. {{ number(selectedDeliveryProduct(line.supply_item_id).stock) }}</small></label>
                <label class="delivery-field delivery-line__notes"><span>Observación</span><input v-model.trim="line.notes" maxlength="255" placeholder="Opcional" /></label>
                <button type="button" class="delivery-line__remove" :disabled="deliveryForm.items.length === 1" :aria-label="`Eliminar línea ${index + 1}`" @click="removeLine(deliveryForm.items, index)"><i class="bx bx-trash"></i></button>
              </article>
            </div>
            <div v-if="deliveryItemsError" class="delivery-search-error" role="alert"><i class="bx bx-error-circle"></i><span>{{ deliveryItemsError }}</span></div>
            <div class="delivery-search-note"><i class="bx bx-info-circle"></i><span>El buscador muestra únicamente productos activos con stock disponible.</span></div>
          </section>
        </div>

        <footer class="delivery-modal-footer">
          <div><i class="bx bx-shield-quarter"></i><span><strong>Movimiento protegido y trazable</strong><small>El stock se descuenta sólo después de confirmar el registro.</small></span></div>
          <nav><button type="button" class="btn delivery-cancel" @click="showDeliveryModal = false">Cancelar</button><button class="btn delivery-submit" :disabled="saving || !deliveryIsReady"><i class="bx bxs-file-pdf"></i> {{ saving ? 'Registrando...' : (isMaintenanceStoreroom ? 'Registrar salida y descargar acta' : 'Registrar y descargar acta') }}</button></nav>
        </footer>
      </form>
    </BModal>
  </Layout>
</template>

<style scoped>
.supply-page { --primary:#173f67; --accent:#1a8d86; --soft:#eaf6f4; --warm:#f2a65a; color:#27374d; padding:1.15rem 0 2.5rem; }
.supply-page.is-heating { --primary:#5b3a2a; --accent:#d26a3b; --soft:#fff1e9; --warm:#e9a33d; }
.supply-page.is-storeroom { --primary:#263f50; --accent:#b76a2d; --soft:#fff4e8; --warm:#e59a4b; }
.supply-hero { position:relative; overflow:hidden; display:flex; justify-content:space-between; gap:2rem; min-height:245px; padding:2rem 2.15rem; border-radius:24px; color:#fff; background:linear-gradient(128deg,var(--primary),color-mix(in srgb,var(--primary) 70%,#0b223b)); box-shadow:0 18px 42px rgba(28,47,75,.16); }
.supply-hero::before,.supply-hero::after { content:""; position:absolute; border-radius:50%; border:1px solid rgba(255,255,255,.1); }
.supply-hero::before { width:340px;height:340px;right:-90px;top:-160px; }.supply-hero::after { width:210px;height:210px;right:170px;bottom:-150px; }
.supply-hero__copy { position:relative;z-index:1;max-width:750px; }.supply-eyebrow { display:inline-flex;align-items:center;gap:.45rem;font-size:.68rem;font-weight:800;letter-spacing:.16em;color:#bfe9e5; }.supply-hero h1 { margin:.62rem 0 .5rem;font-size:clamp(1.8rem,3vw,2.7rem);font-weight:800;letter-spacing:-.035em; }.supply-hero p { max-width:690px;margin:0;color:rgba(255,255,255,.72);font-size:.96rem;line-height:1.55; }
.supply-switch { display:flex;flex-wrap:wrap;gap:.55rem;margin-top:1.5rem; }.supply-switch a { display:flex;align-items:center;gap:.48rem;padding:.62rem .86rem;border:1px solid rgba(255,255,255,.18);border-radius:11px;color:rgba(255,255,255,.72);font-size:.77rem;font-weight:700;background:rgba(255,255,255,.06);transition:.2s; }.supply-switch a:hover,.supply-switch a.active { color:var(--primary);background:#fff;border-color:#fff; }
.supply-hero__mark { position:relative;z-index:1;align-self:center;min-width:185px;padding:1.35rem;border:1px solid rgba(255,255,255,.17);border-radius:18px;background:rgba(255,255,255,.08);backdrop-filter:blur(8px); }.supply-hero__mark>i { display:grid;place-items:center;width:48px;height:48px;border-radius:14px;font-size:1.65rem;color:var(--primary);background:#fff; }.supply-hero__mark span,.supply-hero__mark strong { display:block; }.supply-hero__mark span { margin-top:1rem;color:rgba(255,255,255,.6);font-size:.67rem;text-transform:uppercase;letter-spacing:.1em; }.supply-hero__mark strong { margin-top:.18rem;font-size:.86rem; }
.storeroom-switcher { position:relative;z-index:3;display:flex;align-items:center;justify-content:space-between;gap:1.25rem;margin:-1.2rem 1rem .85rem;padding:.9rem 1rem;border:1px solid #e3e7eb;border-radius:17px;background:rgba(255,255,255,.97);box-shadow:0 12px 30px rgba(37,55,72,.11);backdrop-filter:blur(10px); }.storeroom-switcher__copy { display:flex;align-items:center;gap:.75rem;min-width:250px; }.storeroom-switcher__copy>span { display:grid;place-items:center;flex:0 0 44px;height:44px;border-radius:13px;color:#fff;background:linear-gradient(145deg,var(--primary),#365f73);font-size:1.25rem;box-shadow:0 7px 16px rgba(38,63,80,.2); }.storeroom-switcher__copy small,.storeroom-switcher__copy strong,.storeroom-switcher__copy p { display:block; }.storeroom-switcher__copy small { color:var(--accent);font-size:.56rem;font-weight:900;letter-spacing:.12em; }.storeroom-switcher__copy strong { margin:.08rem 0;color:#2d4051;font-size:.86rem; }.storeroom-switcher__copy p { margin:0;color:#85909d;font-size:.62rem; }.storeroom-switcher__controls { display:flex;align-items:end;justify-content:flex-end;gap:.55rem;flex:1; }.storeroom-switcher__controls label { display:flex;flex:1;max-width:310px;flex-direction:column;gap:.25rem;margin:0;color:#6f7d8c;font-size:.58rem;font-weight:900;text-transform:uppercase;letter-spacing:.07em; }.storeroom-switcher__controls select { width:100%;height:42px;padding:0 2rem 0 .7rem;border:1px solid #d8e0e8;border-radius:10px;color:#34485b;background:#f9fbfc;font-size:.7rem;font-weight:750; }.storeroom-action { display:flex;align-items:center;gap:.48rem;min-height:42px;padding:.48rem .68rem;border:1px solid #d9e0e7;border-radius:10px;color:#506174;background:#fff;text-align:left; }.storeroom-action>i { font-size:1.1rem;color:var(--accent); }.storeroom-action span,.storeroom-action small { display:block; }.storeroom-action span { font-size:.66rem;font-weight:850; }.storeroom-action small { margin-top:.05rem;color:#8a95a2;font-size:.52rem;font-weight:600; }.storeroom-action--inventory { color:#fff;border-color:var(--primary);background:var(--primary); }.storeroom-action--inventory>i,.storeroom-action--inventory small { color:#fff; }.storeroom-action--inventory small { opacity:.68; }.is-storeroom .supply-stats { margin:0 1rem 1rem; }
.supply-stats { position:relative;z-index:2;display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;margin:-1.15rem 1rem 1rem; }.supply-stats article { display:flex;align-items:center;gap:.85rem;min-width:0;padding:1rem 1.05rem;border:1px solid #e4eaf1;border-radius:16px;background:#fff;box-shadow:0 10px 28px rgba(45,61,84,.08); }.stat-icon { display:grid;place-items:center;flex:0 0 42px;height:42px;border-radius:13px;font-size:1.28rem; }.stat-icon--blue{color:#4568d4;background:#edf1ff}.stat-icon--green{color:#188b69;background:#e8f8f2}.stat-icon--amber{color:#b97716;background:#fff5df}.stat-icon--red{color:#c84e5e;background:#ffedf0}.supply-stats small,.supply-stats p { color:#8290a2; }.supply-stats small { display:block;font-size:.64rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em; }.supply-stats strong { font-size:1.35rem;line-height:1.15; }.supply-stats p { margin:0;font-size:.67rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.supply-workspace { overflow:hidden;border:1px solid #e2e8ef;border-radius:20px;background:#fff;box-shadow:0 12px 36px rgba(40,56,79,.07); }.workspace-head { display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.78rem 1rem;border-bottom:1px solid #e7ebf0;background:#fbfcfe; }.workspace-tabs,.workspace-actions { display:flex;align-items:center;gap:.45rem;flex-wrap:wrap; }.workspace-tabs button { display:flex;align-items:center;gap:.4rem;padding:.62rem .78rem;border:0;border-radius:10px;color:#728095;background:transparent;font-size:.75rem;font-weight:800; }.workspace-tabs button.active { color:var(--primary);background:var(--soft); }.action-button { display:inline-flex;align-items:center;gap:.4rem;padding:.62rem .78rem;border:1px solid transparent;border-radius:10px;font-size:.74rem;font-weight:800;white-space:nowrap; }.action-button--ghost{color:#4c5b70;border-color:#dce3ec;background:#fff}.action-button--in{color:#fff;background:#21866b}.action-button--out{color:#fff;background:var(--primary)}
.stock-panel,.history-panel { padding:1rem; }.compact-filters { display:grid;grid-template-columns:minmax(230px,1fr) 190px auto;gap:.55rem;margin-bottom:1rem;padding:.65rem;border:1px solid #e5eaf0;border-radius:13px;background:#f8fafc; }.search-control { position:relative;margin:0; }.search-control i { position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#8794a5;font-size:1.05rem; }.search-control input,.compact-filters select { width:100%;height:39px;border:1px solid #dce3eb;border-radius:9px;background:#fff;color:#35455a;font-size:.77rem; }.search-control input { padding:0 .8rem 0 2.25rem; }.compact-filters select { padding:0 .65rem; }.filter-button { padding:0 .9rem;border:0;border-radius:9px;color:#fff;background:var(--primary);font-size:.75rem;font-weight:800; }
.table-wrap { overflow:auto;border:1px solid #e5eaf0;border-radius:14px; }
.stock-table-block { overflow:hidden;border:1px solid #e5eaf0;border-radius:14px;background:#fff; }
.stock-table-block .table-wrap { border:0;border-radius:0; }
.table-pagination { display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:64px;padding:.72rem .82rem;border-top:1px solid #e5eaf0;background:linear-gradient(180deg,#fff,#f8fafc); }
.table-pagination p { margin:0;color:#7b8899;font-size:.66rem; }
.table-pagination p strong { color:#35485d;font-weight:850; }
.table-pagination__controls { display:flex;align-items:center;justify-content:flex-end;gap:.3rem; }
.table-pagination__controls button { display:inline-flex;align-items:center;justify-content:center;height:34px;border:1px solid #dce3ea;border-radius:9px;color:#53657a;background:#fff;font-size:.63rem;font-weight:800;transition:.16s ease; }
.table-pagination__controls button:not(:disabled):hover { transform:translateY(-1px);border-color:color-mix(in srgb,var(--accent) 45%,#dce3ea);color:var(--primary);box-shadow:0 5px 12px rgba(38,56,76,.08); }
.table-pagination__controls button:disabled { cursor:not-allowed;opacity:.42; }
.table-pagination__nav { gap:.22rem;padding:0 .62rem; }
.table-pagination__nav i { font-size:1rem; }
.table-pagination__page { width:34px;padding:0; }
.table-pagination__page.active { color:#fff;border-color:var(--primary);background:var(--primary);box-shadow:0 5px 12px color-mix(in srgb,var(--primary) 24%,transparent); }
.supply-table { width:100%;min-width:980px;table-layout:fixed;border-collapse:separate;border-spacing:0; }
.supply-col--product{width:26%}.supply-col--type{width:16%}.supply-col--stock{width:9%}.supply-col--minimum{width:8%}.supply-col--supplier{width:12%}.supply-col--status{width:11%}.supply-col--actions{width:18%}
.supply-table th { padding:.56rem .52rem;color:#78869a;background:#f7f9fc;font-size:.57rem;font-weight:800;text-transform:uppercase;letter-spacing:.045em;text-align:left;border-bottom:1px solid #e4e9ef;white-space:nowrap; }
.supply-table td { padding:.52rem;font-size:.68rem;line-height:1.25;vertical-align:middle;border-bottom:1px solid #edf0f4;background:#fff; }
.supply-table tbody tr:last-child td { border-bottom:0; }
.supply-table tbody tr:hover td { background:#fbfcfd; }
.supply-table th:first-child,.supply-table td:first-child{position:sticky;left:0;z-index:2}.supply-table th:first-child{z-index:3}
.supply-table th:last-child,.supply-table td:last-child{position:sticky;right:0;z-index:2;box-shadow:-10px 0 18px -18px rgba(34,52,72,.55)}.supply-table th:last-child{z-index:3}
.product-cell { display:flex;align-items:center;gap:.55rem;min-width:0; }
.product-cell>div{min-width:0}
.product-photo { display:grid;place-items:center;flex:0 0 40px;height:40px;overflow:hidden;border:1px solid #e1e7ed;border-radius:10px;color:var(--accent);background:var(--soft); }
.product-photo img { width:100%;height:100%;object-fit:cover; }
.product-photo i { font-size:1.2rem; }
.product-cell strong,.product-cell small,.product-cell p { display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.product-cell strong { color:#293a50;font-size:.72rem; }
.product-cell small { margin-top:.06rem;color:var(--accent);font-size:.56rem;font-weight:800; }
.product-cell p { margin:.08rem 0 0;color:#8692a2;font-size:.59rem; }
.type-chip { display:inline-flex;max-width:100%;padding:.27rem .4rem;border-radius:7px;color:#526177;background:#f0f3f7;font-size:.58rem;font-weight:700;line-height:1.18; }
.stock-value { display:flex;align-items:baseline;gap:.2rem;white-space:nowrap; }
.stock-value strong { color:var(--primary);font-size:.96rem; }
.stock-value span { overflow:hidden;color:#8491a1;font-size:.57rem;text-overflow:ellipsis; }
.stock-status { display:inline-flex;align-items:center;gap:.25rem;max-width:100%;padding:.28rem .4rem;border-radius:999px;font-size:.57rem;font-weight:800;white-space:nowrap; }
.stock-status i{flex:0 0 auto;font-size:.4rem}.stock-status.is-ok{color:#1e8067;background:#e9f7f2}.stock-status.is-low{color:#a96c13;background:#fff3dc}.stock-status.is-empty{color:#ba4857;background:#ffecef}
.row-actions{display:flex;align-items:center;gap:.12rem;white-space:nowrap}
.row-action{display:inline-flex;align-items:center;justify-content:center;gap:.15rem;min-height:28px;padding:0 .2rem;border:1px solid #dfe5ec;border-radius:8px;color:#58687c;background:#fff;font-size:.56rem;font-weight:800;transition:.16s ease}
.row-action i{font-size:.8rem}.row-action:hover{transform:translateY(-1px);border-color:#bfcbd8;box-shadow:0 5px 12px rgba(38,56,76,.08)}.row-action:disabled{cursor:not-allowed;opacity:.5}.row-action--view{color:var(--primary);background:var(--soft);border-color:color-mix(in srgb,var(--accent) 25%,#dfe5ec)}.row-action--edit{color:#52647a}.row-action--delete{color:#bd4656;border-color:#f0d4d9;background:#fff7f8}
.empty-state { display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:280px;padding:2rem;text-align:center;color:#8793a3; }.empty-state>i { display:grid;place-items:center;width:66px;height:66px;border-radius:20px;color:var(--accent);background:var(--soft);font-size:2rem; }.empty-state strong { margin-top:.9rem;color:#405066;font-size:1rem; }.empty-state p { max-width:470px;margin:.25rem auto .8rem;font-size:.77rem; }.empty-state button { padding:.55rem .8rem;border:0;border-radius:9px;color:#fff;background:var(--primary);font-size:.73rem;font-weight:800; }
.inventory-import-panel { min-height:500px;padding:1rem;background:linear-gradient(180deg,#fff,#fbfcfd); }.inventory-import-head { display:flex;align-items:center;gap:.8rem;padding:.3rem .2rem .95rem; }.inventory-import-head__icon { display:grid;place-items:center;flex:0 0 48px;height:48px;border-radius:14px;color:#fff;background:linear-gradient(145deg,var(--primary),#416679);box-shadow:0 8px 18px rgba(38,63,80,.19);font-size:1.4rem; }.inventory-import-head span { color:var(--accent);font-size:.58rem;font-weight:900;letter-spacing:.13em; }.inventory-import-head h2 { margin:.12rem 0 .1rem;color:#2d4052;font-size:1.08rem; }.inventory-import-head p { max-width:720px;margin:0;color:#7d8998;font-size:.69rem;line-height:1.45; }.inventory-search { display:grid;grid-template-columns:minmax(280px,1fr) 190px auto;align-items:end;gap:.6rem;margin-bottom:.8rem;padding:.75rem;border:1px solid #e0e6eb;border-radius:14px;background:#f5f8fa; }.inventory-search label { display:flex;flex-direction:column;gap:.26rem;margin:0;color:#637184;font-size:.59rem;font-weight:850; }.inventory-search input,.inventory-search select { width:100%;height:42px;padding:0 .72rem;border:1px solid #d6dfe7;border-radius:10px;color:#34475a;background:#fff;font-size:.72rem; }.inventory-search__text { position:relative; }.inventory-search__text>i { position:absolute;left:.72rem;bottom:.71rem;color:#8995a2;font-size:1rem; }.inventory-search__text input { padding-left:2.15rem; }.inventory-search button { display:flex;align-items:center;justify-content:center;gap:.38rem;height:42px;padding:0 1rem;border:0;border-radius:10px;color:#fff;background:var(--primary);font-size:.69rem;font-weight:850; }.inventory-results-summary { display:flex;align-items:center;justify-content:space-between;gap:.8rem;margin:.1rem .1rem .6rem;color:#6f7d8c;font-size:.64rem; }.inventory-results-summary strong { color:var(--primary); }.inventory-results-summary small { color:#929ca7;font-size:.6rem; }.inventory-grid { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem; }.inventory-card { position:relative;display:grid;grid-template-columns:64px minmax(0,1fr);gap:.7rem;min-height:142px;padding:.75rem;border:1px solid #e0e6ec;border-radius:14px;color:#405165;background:#fff;text-align:left;transition:.18s ease; }.inventory-card:hover { transform:translateY(-1px);border-color:#c2d0db;box-shadow:0 8px 20px rgba(38,54,73,.07); }.inventory-card.selected { border-color:var(--accent);background:linear-gradient(145deg,#fff,#fff8f0);box-shadow:0 0 0 2px color-mix(in srgb,var(--accent) 15%,transparent),0 9px 22px rgba(75,55,35,.09); }.inventory-card__check { position:absolute;top:.55rem;right:.55rem;display:grid;place-items:center;width:24px;height:24px;border:1px solid #d7dfe7;border-radius:8px;color:#82909f;background:#fff;font-size:.9rem; }.inventory-card.selected .inventory-card__check { color:#fff;border-color:var(--accent);background:var(--accent); }.inventory-card__image { display:grid;place-items:center;width:64px;height:64px;overflow:hidden;border-radius:12px;color:var(--accent);background:var(--soft);font-size:1.55rem; }.inventory-card__image img { width:100%;height:100%;object-fit:cover; }.inventory-card__body { display:flex;min-width:0;flex-direction:column;padding-right:1.4rem; }.inventory-card__body small { color:var(--accent);font-size:.56rem;font-weight:900; }.inventory-card__body strong { margin:.12rem 0;color:#304356;font-size:.76rem;line-height:1.3; }.inventory-card__body em { color:#738193;font-size:.59rem;font-style:normal;font-weight:750; }.inventory-card__body>span { overflow:hidden;margin-top:.24rem;color:#929ca7;font-size:.58rem;white-space:nowrap;text-overflow:ellipsis; }.inventory-card__meta { display:flex;grid-column:1/-1;align-items:center;justify-content:space-between;gap:.35rem;padding-top:.48rem;border-top:1px solid #edf0f3;color:#728092;font-size:.56rem; }.inventory-card__meta span { display:flex;align-items:center;gap:.25rem;min-width:0; }.inventory-card__meta span:first-child { overflow:hidden;white-space:nowrap;text-overflow:ellipsis; }.inventory-card__meta i { color:var(--accent);font-size:.8rem; }.inventory-empty { display:flex;min-height:260px;flex-direction:column;align-items:center;justify-content:center;color:#8793a0;text-align:center; }.inventory-empty>i { display:grid;place-items:center;width:62px;height:62px;border-radius:18px;color:var(--accent);background:var(--soft);font-size:1.8rem; }.inventory-empty strong { margin-top:.75rem;color:#405164;font-size:.88rem; }.inventory-empty p { max-width:460px;margin:.25rem 0;font-size:.68rem; }.inventory-pagination { display:flex;align-items:center;justify-content:center;gap:.7rem;margin-top:.9rem;color:#748192;font-size:.64rem; }.inventory-pagination button { display:flex;align-items:center;gap:.25rem;padding:.45rem .6rem;border:1px solid #dce3ea;border-radius:8px;color:#516174;background:#fff;font-size:.62rem;font-weight:800; }.inventory-pagination button:disabled { opacity:.42; }.inventory-selection-bar { position:sticky;bottom:.55rem;z-index:3;display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:1rem;padding:.72rem .85rem;border:1px solid #d8e0e7;border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 11px 28px rgba(37,53,70,.13);backdrop-filter:blur(10px); }.inventory-selection-bar>div { display:flex;align-items:center;gap:.6rem; }.inventory-selection-bar>div>span { display:grid;place-items:center;width:39px;height:39px;border-radius:11px;color:#fff;background:var(--accent);font-size:.9rem;font-weight:900; }.inventory-selection-bar p,.inventory-selection-bar strong,.inventory-selection-bar small { display:block;margin:0; }.inventory-selection-bar strong { color:#34475a;font-size:.69rem; }.inventory-selection-bar small { color:#84909d;font-size:.58rem; }.inventory-selection-bar>button { display:flex;align-items:center;gap:.4rem;padding:.65rem .82rem;border:0;border-radius:10px;color:#fff;background:var(--primary);font-size:.68rem;font-weight:850; }.inventory-selection-bar>button:disabled { cursor:not-allowed;opacity:.45; }
.history-intro { display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.45rem .2rem 1rem; }.history-intro span { color:var(--accent);font-size:.62rem;font-weight:900;letter-spacing:.12em; }.history-intro h2 { margin:.16rem 0;font-size:1.05rem; }.history-intro p { margin:0;color:#8390a1;font-size:.73rem; }.movement-card { display:grid;grid-template-columns:185px 1fr 180px;align-items:center;gap:1rem;margin-bottom:.65rem;padding:.85rem;border:1px solid #e2e8ef;border-radius:14px;background:#fff;transition:.18s; }.movement-card:hover { border-color:#cbd7e3;box-shadow:0 8px 20px rgba(44,60,82,.06); }.movement-card__folio { padding:.65rem .8rem;border-left:3px solid #21866b;border-radius:6px;background:#f1faf7; }.movement-card--delivery .movement-card__folio{border-color:var(--primary);background:var(--soft)}.movement-card__folio span,.movement-card__folio strong,.movement-card__folio small{display:block}.movement-card__folio span{color:#21866b;font-size:.59rem;font-weight:900;letter-spacing:.1em}.movement-card__folio strong{margin:.18rem 0;color:#34455a;font-size:.72rem}.movement-card__folio small{color:#8995a5;font-size:.63rem}.movement-card__body h3{margin:0;font-size:.82rem}.movement-card__body p{margin:.18rem 0 .45rem;color:#8491a2;font-size:.67rem}.line-chips{display:flex;flex-wrap:wrap;gap:.35rem}.line-chips span{padding:.3rem .48rem;border-radius:7px;color:#617085;background:#f1f4f7;font-size:.62rem}.line-chips strong{color:var(--accent)}.movement-card__user{display:flex;align-items:center;gap:.55rem;color:#8190a2}.movement-card__user>i{font-size:1.5rem}.movement-card__user small,.movement-card__user strong{display:block}.movement-card__user small{font-size:.58rem}.movement-card__user strong{color:#48586d;font-size:.67rem}.pdf-button { display:flex;align-items:center;gap:.55rem;padding:.6rem .72rem;border:1px solid #f0cbd0;border-radius:10px;color:#bd4253;background:#fff6f7;text-align:left; }.pdf-button>i{font-size:1.45rem}.pdf-button strong,.pdf-button small{display:block}.pdf-button strong{font-size:.68rem}.pdf-button small{color:#a97880;font-size:.58rem}
:global(.supply-delivery-modal .modal-dialog) { max-width:min(1180px,calc(100vw - 2rem)); }
:global(.supply-delivery-modal .modal-content) { overflow:hidden;border:0;border-radius:22px;background:#f4f7fa;box-shadow:0 28px 80px rgba(20,38,61,.3); }
:global(.supply-delivery-modal .modal-body) { padding:0;background:#f4f7fa; }
.delivery-form { color:#34465a;background:#f4f7fa; }
.delivery-modal-hero { position:relative;display:grid;grid-template-columns:62px minmax(0,1fr) auto 40px;align-items:center;gap:1rem;overflow:hidden;padding:1.25rem 1.35rem;color:#fff;background:linear-gradient(125deg,#153b63 0%,#1c5876 55%,#16827d 100%); }
.delivery-modal-hero::after { position:absolute;right:-70px;top:-125px;width:310px;height:310px;border:1px solid rgba(255,255,255,.13);border-radius:50%;content:""; }
.delivery-modal-hero__icon { position:relative;z-index:1;display:grid;place-items:center;width:62px;height:62px;border:1px solid rgba(255,255,255,.2);border-radius:18px;background:rgba(255,255,255,.12);box-shadow:inset 0 1px rgba(255,255,255,.16);font-size:1.8rem; }
.delivery-modal-hero>div { position:relative;z-index:1;min-width:0; }
.delivery-modal-hero small { display:block;color:#aee7e2;font-size:.59rem;font-weight:900;letter-spacing:.14em; }
.delivery-modal-hero h2 { margin:.18rem 0 .25rem;color:#fff;font-size:1.35rem;font-weight:850;letter-spacing:-.02em; }
.delivery-modal-hero p { max-width:720px;margin:0;color:rgba(255,255,255,.72);font-size:.71rem;line-height:1.45; }
.delivery-modal-hero__badge { position:relative;z-index:1;display:inline-flex;align-items:center;gap:.36rem;padding:.48rem .65rem;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:rgba(255,255,255,.1);font-size:.62rem;font-weight:850;white-space:nowrap; }
.delivery-modal-close { position:relative;z-index:2;display:grid;place-items:center;width:36px;height:36px;border:1px solid rgba(255,255,255,.18);border-radius:11px;color:#fff;background:rgba(7,28,49,.18);font-size:1.35rem;transition:.16s; }
.delivery-modal-close:hover { transform:rotate(4deg);background:rgba(255,255,255,.16); }
.delivery-modal-body { display:grid;gap:.8rem;padding:.9rem; }
.delivery-section { border:1px solid #dee6ee;border-radius:16px;background:#fff;box-shadow:0 5px 18px rgba(38,55,76,.04); }
.delivery-section__head { display:flex;align-items:center;gap:.7rem;padding:.78rem .9rem;border-bottom:1px solid #e8edf2;background:linear-gradient(100deg,#fbfcfd,#f5f9fa); }
.delivery-section__head>span { display:grid;place-items:center;flex:0 0 34px;height:34px;border-radius:10px;color:#fff;background:linear-gradient(145deg,#173f67,#1a8d86);font-size:.72rem;font-weight:900;box-shadow:0 6px 13px rgba(23,63,103,.18); }
.delivery-section__head>div { min-width:0; }
.delivery-section__head h3 { margin:0;color:#2f4257;font-size:.82rem;font-weight:850; }
.delivery-section__head p { margin:.1rem 0 0;color:#8190a1;font-size:.6rem; }
.delivery-context-grid { display:grid;grid-template-columns:minmax(180px,.62fr) minmax(340px,1.38fr);gap:.72rem;padding:.85rem .9rem 1rem; }
.delivery-field { display:flex;min-width:0;flex-direction:column;gap:.3rem;margin:0; }
.delivery-field>span { color:#53647a;font-size:.61rem;font-weight:850; }
.delivery-field input,.delivery-field textarea { width:100%;border:1px solid #d9e2eb;border-radius:10px;color:#314459;background:#fff;font-size:.72rem;outline:none;transition:.16s; }
.delivery-field input { height:42px;padding:0 .72rem; }
.delivery-field textarea { min-height:70px;padding:.62rem .72rem;resize:vertical; }
.delivery-field input:focus,.delivery-field textarea:focus { border-color:#5d98a6;box-shadow:0 0 0 3px rgba(26,141,134,.1); }
.delivery-field--notes { grid-column:2; }
.delivery-field>small { color:#8996a5;font-size:.54rem; }
.delivery-context-grid .recipient-summary { grid-column:1/-1;margin:-.12rem 0 0; }
.delivery-lines-editor { overflow:visible; }
.delivery-lines-head { padding:.82rem .9rem; }
.delivery-lines-head__actions { display:flex;align-items:center;gap:.55rem;margin-left:auto; }
.delivery-lines-head__actions>small { color:#788697;font-size:.57rem;white-space:nowrap; }
.delivery-lines-head__actions>small strong { color:#173f67;font-size:.72rem; }
.delivery-lines-head__actions button { display:inline-flex;align-items:center;gap:.32rem;padding:.5rem .65rem;border:1px solid #cfdbe6;border-radius:9px;color:#173f67;background:#fff;font-size:.62rem;font-weight:850;white-space:nowrap;transition:.16s; }
.delivery-lines-head__actions button:hover { border-color:#86aaa9;background:#f3faf9; }
.delivery-lines-list { display:grid;gap:.58rem;padding:.72rem .78rem; }
.delivery-line { position:relative;display:grid;grid-template-columns:34px minmax(330px,1.7fr) minmax(110px,.42fr) minmax(170px,.75fr) 34px;align-items:start;gap:.58rem;padding:.7rem;border:1px solid #e1e8ef;border-radius:13px;background:#fbfcfd;transition:.16s; }
.delivery-line:focus-within { border-color:#b9d4d5;background:#fff;box-shadow:0 5px 16px rgba(37,73,88,.06); }
.delivery-line__number { display:grid;place-items:center;width:30px;height:30px;margin-top:20px;border-radius:9px;color:#173f67;background:#eaf2f7;font-size:.66rem;font-weight:900; }
.delivery-line__remove { display:grid;place-items:center;width:34px;height:42px;margin-top:20px;border:1px solid #efd8dc;border-radius:10px;color:#bf4f5e;background:#fff6f7;font-size:.92rem;transition:.16s; }
.delivery-line__remove:not(:disabled):hover { border-color:#dfaab2;background:#ffecef; }
.delivery-line__remove:disabled { cursor:not-allowed;opacity:.32; }
.delivery-line__quantity small { color:#1a8179;font-weight:800; }
.delivery-stock-hint { display:flex;align-items:center;gap:.24rem;color:#5d7281!important; }
.delivery-stock-hint i { color:#1a8d86;font-size:.74rem; }
.delivery-stock-hint strong { color:#17766f; }
:deep(.delivery-product-select) { --ms-border-color:#d9e2eb;--ms-border-color-active:#5d98a6;--ms-ring-color:rgba(26,141,134,.1);--ms-radius:10px;--ms-py:.45rem;--ms-font-size:.7rem;min-height:42px; }
:deep(.delivery-product-select .multiselect-wrapper) { min-height:42px; }
:deep(.delivery-product-select .multiselect-dropdown) { z-index:1100;overflow:hidden;border:1px solid #dbe5eb;border-radius:13px;box-shadow:0 18px 42px rgba(31,51,70,.18); }
:deep(.delivery-product-select .multiselect-options) { max-height:min(320px,42vh);padding:.3rem; }
:deep(.delivery-product-select .multiselect-option) { margin:.12rem 0;padding:.48rem .55rem;border-radius:9px; }
:deep(.delivery-product-select .multiselect-option.is-pointed) { color:#29495a;background:#edf7f6; }
:deep(.delivery-product-select .multiselect-option.is-selected) { color:#244856;background:#dff2ef; }
.delivery-product-selected { display:flex;align-items:center;gap:.48rem;min-width:0;padding-right:1.8rem; }
.delivery-product-selected__image { display:grid;place-items:center;flex:0 0 28px;height:28px;overflow:hidden;border-radius:7px;color:#1a8d86;background:#eaf6f4;font-size:.9rem; }
.delivery-product-selected__image img,.delivery-product-option__image img { width:100%;height:100%;object-fit:cover; }
.delivery-product-selected>span:last-child { display:grid;min-width:0; }
.delivery-product-selected strong { overflow:hidden;color:#304458;font-size:.67rem;text-overflow:ellipsis;white-space:nowrap; }
.delivery-product-selected small { overflow:hidden;color:#758697;font-size:.52rem;text-overflow:ellipsis;white-space:nowrap; }
.delivery-product-option { display:grid;grid-template-columns:38px minmax(0,1fr) auto;align-items:center;gap:.55rem;width:100%; }
.delivery-product-option__image { display:grid;place-items:center;width:38px;height:38px;overflow:hidden;border-radius:10px;color:#1a8d86;background:#eaf6f4;font-size:1.05rem; }
.delivery-product-option__copy { display:grid;min-width:0; }
.delivery-product-option__copy strong { overflow:hidden;color:#2e4257;font-size:.67rem;text-overflow:ellipsis;white-space:nowrap; }
.delivery-product-option__copy small { overflow:hidden;margin-top:.08rem;color:#8090a1;font-size:.53rem;text-overflow:ellipsis;white-space:nowrap; }
.delivery-product-option__stock { display:grid;min-width:76px;padding:.34rem .46rem;border-radius:9px;color:#166f67;background:#e8f6f3;text-align:right; }
.delivery-product-option__stock small { color:#4a918a;font-size:.43rem;font-weight:900;letter-spacing:.06em; }
.delivery-product-option__stock strong { color:#176e67;font-size:.77rem;line-height:1.05; }
.delivery-product-option__stock em { color:#60938f;font-size:.48rem;font-style:normal; }
.delivery-product-empty { display:flex;min-height:105px;flex-direction:column;align-items:center;justify-content:center;color:#8391a0;text-align:center; }
.delivery-product-empty i { color:#1a8d86;font-size:1.35rem; }
.delivery-product-empty strong { margin-top:.25rem;color:#405468;font-size:.65rem; }
.delivery-product-empty span { font-size:.55rem; }
.delivery-search-error,.delivery-search-note { display:flex;align-items:center;gap:.38rem;margin:0 .78rem .72rem;padding:.55rem .65rem;border-radius:9px;font-size:.59rem; }
.delivery-search-error { color:#ae3f4e;background:#fff0f2; }
.delivery-search-note { color:#587080;background:#eff5f7; }
.delivery-search-note i { color:#1a8d86;font-size:.86rem; }
.supply-form .delivery-modal-footer { position:sticky;bottom:0;z-index:12;display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:0;padding:.82rem 1rem;border-top:1px solid #dde5ec;background:rgba(255,255,255,.97);box-shadow:0 -8px 22px rgba(34,54,74,.06);backdrop-filter:blur(10px); }
.delivery-modal-footer>div { display:flex;align-items:center;gap:.52rem;color:#718092; }
.delivery-modal-footer>div>i { display:grid;place-items:center;width:36px;height:36px;border-radius:10px;color:#1a8d86;background:#e8f6f3;font-size:1.05rem; }
.delivery-modal-footer>div span { display:grid; }
.delivery-modal-footer>div strong { color:#405468;font-size:.63rem; }
.delivery-modal-footer>div small { color:#8794a2;font-size:.54rem; }
.delivery-modal-footer nav { display:flex;align-items:center;gap:.48rem; }
.supply-form .delivery-modal-footer .btn { display:inline-flex;align-items:center;justify-content:center;gap:.38rem;min-height:40px;border-radius:10px;padding:.55rem .78rem;font-size:.65rem;font-weight:850; }
.delivery-cancel { border:1px solid #dbe3ea;color:#5f6e80;background:#fff; }
.delivery-submit { border:1px solid #173f67;color:#fff;background:linear-gradient(120deg,#173f67,#1a7778);box-shadow:0 7px 16px rgba(23,63,103,.17); }
.delivery-submit:disabled { border-color:#b8c4cf;background:#aebac6;box-shadow:none;opacity:.68; }
.item-detail{color:#35455a}.item-detail__hero{display:flex;align-items:center;gap:1rem;padding:1rem;border:1px solid #e0e6ed;border-radius:16px;background:linear-gradient(145deg,#f8fbfc,#fff)}.item-detail__photo{display:grid;place-items:center;flex:0 0 112px;height:112px;overflow:hidden;border:1px solid #dce5eb;border-radius:16px;color:var(--accent,#1a8d86);background:var(--soft,#eaf6f4);font-size:2.5rem}.item-detail__photo img{width:100%;height:100%;object-fit:cover}.item-detail__identity{min-width:0}.item-detail__identity>small{color:var(--accent,#1a8d86);font-size:.62rem;font-weight:900;letter-spacing:.08em}.item-detail__identity h3{margin:.18rem 0 .65rem;color:#2c3f54;font-size:1.25rem}.item-detail__identity>div{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}.item-detail__grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.65rem;margin-top:.75rem}.item-detail__grid article{padding:.78rem .85rem;border:1px solid #e4e9ee;border-radius:12px;background:#fff}.item-detail__grid small,.item-detail__grid strong,.item-detail__grid span{display:block}.item-detail__grid small{color:#8491a1;font-size:.59rem;font-weight:850;text-transform:uppercase;letter-spacing:.06em}.item-detail__grid strong{overflow:hidden;margin:.22rem 0 .08rem;color:#33465a;font-size:.82rem;text-overflow:ellipsis;white-space:nowrap}.item-detail__grid span{color:#8995a3;font-size:.62rem}.item-detail__description{margin-top:.7rem;padding:.8rem .9rem;border-left:3px solid var(--accent,#1a8d86);border-radius:7px;background:#f6f9fa}.item-detail__description small{color:var(--accent,#1a8d86);font-size:.57rem;font-weight:900;letter-spacing:.09em}.item-detail__description p{margin:.24rem 0 0;color:#657386;font-size:.71rem;line-height:1.55}.item-detail__trace{display:grid;grid-template-columns:repeat(3,1fr);gap:.55rem;margin-top:.7rem}.item-detail__trace>span{display:grid;grid-template-columns:28px auto;align-items:center;padding:.62rem .7rem;border:1px solid #e6eaef;border-radius:11px;background:#fbfcfd}.item-detail__trace i{grid-row:1/3;color:var(--accent,#1a8d86);font-size:1.2rem}.item-detail__trace strong{color:#36495d;font-size:.78rem}.item-detail__trace small{color:#8995a3;font-size:.59rem}.item-detail__actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem;padding-top:.9rem;border-top:1px solid #e5eaf0}.item-detail__actions .btn{display:inline-flex;align-items:center;gap:.35rem;font-size:.7rem;font-weight:800}.item-detail__delete{color:#b84051;border:1px solid #eccbd1;background:#fff5f6}.supply-form { color:#35455a; }.form-photo { display:flex;align-items:center;gap:1rem;margin-bottom:1rem;padding:1rem;border:1px solid #dfe6ee;border-radius:14px;background:#f8fafc; }.photo-picker { display:grid;place-items:center;flex:0 0 120px;height:94px;overflow:hidden;margin:0;border:1px dashed #b9c7d6;border-radius:12px;background:#fff;text-align:center; }.photo-picker img{width:100%;height:100%;object-fit:cover}.photo-picker span{display:flex;flex-direction:column;align-items:center;color:#728196}.photo-picker i{font-size:1.35rem;color:var(--accent,#2d8ca8)}.photo-picker strong{font-size:.65rem}.photo-picker small{font-size:.56rem}.form-photo__content{min-width:0}.form-photo h3{margin:0;font-size:.86rem}.form-photo p{max-width:390px;margin:.25rem 0 .65rem;color:#7d8a9b;font-size:.7rem}.photo-actions{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}.photo-action{display:inline-flex;align-items:center;gap:.38rem;min-height:37px;margin:0;padding:.52rem .7rem;border:1px solid #d5dfe9;border-radius:9px;color:#47586e;background:#fff;font-size:.68rem;font-weight:800;cursor:pointer}.photo-action--camera{border-color:var(--primary,#17385f);color:#fff;background:var(--primary,#17385f)}.photo-action i{font-size:1rem}.supply-camera{margin:-.35rem 0 1rem;padding:.75rem;border:1px solid #dce5ee;border-radius:14px;background:#f3f6fa}.supply-camera__preview{position:relative;overflow:hidden;aspect-ratio:16/9;border-radius:11px;background:#132033}.supply-camera__preview video{display:block;width:100%;height:100%;object-fit:cover}.supply-camera__preview span{position:absolute;top:.65rem;left:.65rem;display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .52rem;border-radius:999px;color:#fff;background:rgba(10,24,43,.72);font-size:.62rem;font-weight:800}.supply-camera__error{display:flex;align-items:center;gap:.55rem;padding:.65rem;color:#a84655;background:#fff0f2;border-radius:9px;font-size:.7rem;font-weight:700}.supply-camera__error i{font-size:1.2rem}.supply-camera__actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:.7rem}.supply-camera__actions .btn{display:inline-flex;align-items:center;gap:.35rem;font-size:.7rem;font-weight:800}.form-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem; }.form-grid label,.editor-line label { display:flex;flex-direction:column;gap:.32rem;margin:0;color:#536176;font-size:.65rem;font-weight:800; }.form-grid label.wide{grid-column:span 2}.form-grid input,.form-grid select,.form-grid textarea,.editor-line input,.editor-line select { width:100%;border:1px solid #d9e1e9;border-radius:9px;color:#33445a;background:#fff;font-size:.75rem; }.form-grid input,.form-grid select,.editor-line input,.editor-line select{height:40px;padding:0 .7rem}.form-grid textarea{padding:.65rem .7rem;resize:vertical}.active-check{flex-direction:row!important;align-items:center;grid-column:span 2}.active-check input{width:16px;height:16px}.delivery-form-grid{align-items:start}:deep(.recipient-select){--ms-border-color:#d9e1e9;--ms-border-color-active:#7998b8;--ms-ring-color:rgba(23,63,103,.12);--ms-radius:9px;--ms-py:.47rem;--ms-font-size:.75rem;min-height:40px;font-weight:500}:deep(.recipient-select .multiselect-wrapper){min-height:40px}:deep(.recipient-select .multiselect-dropdown){z-index:1080}.recipient-option strong,.recipient-option small{display:block}.recipient-option strong{font-size:.75rem}.recipient-option small{margin-top:.1rem;color:#7d8b9d;font-size:.63rem}.recipient-summary{grid-column:1/-1;display:flex;align-items:center;gap:.7rem;padding:.72rem .85rem;border:1px solid #cde5dc;border-radius:12px;background:#f0faf6}.recipient-summary__avatar{display:grid;place-items:center;flex:0 0 40px;height:40px;border-radius:11px;color:#fff;background:#21866b;font-size:1.25rem}.recipient-summary div{min-width:0}.recipient-summary small,.recipient-summary strong,.recipient-summary p{display:block}.recipient-summary small{color:#21866b;font-size:.55rem;font-weight:900;letter-spacing:.08em}.recipient-summary strong{margin-top:.08rem;color:#304258;font-size:.76rem}.recipient-summary p{margin:.08rem 0 0;color:#758497;font-size:.64rem}.recipient-summary__permission{display:inline-flex;align-items:center;gap:.3rem;margin-left:auto;padding:.35rem .52rem;border-radius:999px;color:#1f735e;background:#dbf2e9;font-size:.61rem;font-weight:900;white-space:nowrap}.supply-form footer { display:flex;justify-content:flex-end;gap:.55rem;margin-top:1.2rem;padding-top:1rem;border-top:1px solid #e6ebf0; }.supply-form footer .btn{display:inline-flex;align-items:center;gap:.4rem;font-size:.73rem;font-weight:800}.form-banner { display:flex;align-items:center;gap:.8rem;margin-bottom:1rem;padding:.85rem 1rem;border-radius:13px; }.form-banner>i{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;font-size:1.3rem}.form-banner strong,.form-banner p{display:block}.form-banner strong{font-size:.78rem}.form-banner p{margin:.15rem 0 0;font-size:.67rem}.form-banner--in{color:#286c59;background:#eaf8f3}.form-banner--in>i{color:#fff;background:#21866b}.form-banner--out{color:var(--primary);background:var(--soft)}.form-banner--out>i{color:#fff;background:var(--primary)}.form-banner--storeroom{color:#375265;background:#eef4f6}.form-banner--storeroom>i{color:#fff;background:#365f73}
.lines-editor { margin-top:1rem;border:1px solid #dfe6ed;border-radius:13px;overflow:hidden; }.lines-editor>header { display:flex;align-items:center;justify-content:space-between;padding:.7rem .8rem;background:#f6f8fb; }.lines-editor>header span,.lines-editor>header strong{display:block}.lines-editor>header span{color:#7b889a;font-size:.57rem;font-weight:900;letter-spacing:.1em}.lines-editor>header strong{font-size:.72rem}.lines-editor>header button{padding:.45rem .62rem;border:1px solid #cfdae5;border-radius:8px;color:#42536a;background:#fff;font-size:.65rem;font-weight:800}.editor-line { display:grid;grid-template-columns:30px minmax(260px,1fr) 135px 150px 34px;align-items:end;gap:.65rem;padding:.7rem .8rem;border-top:1px solid #e8edf2; }.editor-line--delivery{grid-template-columns:30px minmax(260px,1fr) 135px minmax(190px,.7fr) 34px}.line-number{display:grid;place-items:center;width:26px;height:26px;margin-bottom:7px;border-radius:8px;color:var(--primary);background:var(--soft);font-size:.67rem;font-weight:900}.remove-line{display:grid;place-items:center;width:34px;height:40px;border:0;border-radius:8px;color:#c05261;background:#fff0f2}.remove-line:disabled{opacity:.35}
@media (max-width:1100px){.supply-stats{grid-template-columns:repeat(2,1fr)}.storeroom-switcher{align-items:stretch;flex-direction:column}.storeroom-switcher__controls{justify-content:flex-start}.storeroom-switcher__controls label{max-width:none}.workspace-head{align-items:flex-start;flex-direction:column}.movement-card{grid-template-columns:165px 1fr}.movement-card__user,.pdf-button{grid-column:2}.inventory-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid{grid-template-columns:repeat(2,1fr)}}
@media (max-width:720px){.supply-page{padding-top:.5rem}.supply-hero{padding:1.4rem;min-height:auto;border-radius:18px}.supply-hero__mark{display:none}.supply-switch{flex-direction:column}.storeroom-switcher{margin:.7rem 0;padding:.75rem}.storeroom-switcher__copy p{display:none}.storeroom-switcher__controls{align-items:stretch;flex-direction:column}.storeroom-switcher__controls label,.storeroom-action{width:100%;max-width:none}.storeroom-action{justify-content:center}.is-storeroom .supply-stats,.supply-stats{grid-template-columns:1fr 1fr;margin:.7rem 0}.supply-stats article{padding:.8rem}.workspace-head{padding:.7rem}.workspace-tabs,.workspace-actions{width:100%;overflow:auto;flex-wrap:nowrap}.stock-panel,.history-panel,.inventory-import-panel{padding:.7rem}.compact-filters,.inventory-search{grid-template-columns:1fr}.supply-table{min-width:760px}.supply-col--product{width:22%}.supply-col--type{width:17%}.supply-col--stock{width:10%}.supply-col--minimum{width:9%}.supply-col--supplier{width:14%}.supply-col--status{width:14%}.supply-col--actions{width:14%}.supply-table th,.supply-table td{padding:.46rem}.supply-table th:first-child,.supply-table td:first-child{position:static}.supply-table th:last-child,.supply-table td:last-child{min-width:0;padding-left:.3rem;padding-right:.3rem}.product-photo{flex-basis:36px;height:36px}.row-actions{gap:.08rem}.row-action span{display:none}.row-action{width:28px;min-height:28px;padding:0}.table-pagination{align-items:stretch;flex-direction:column;padding:.68rem}.table-pagination p{text-align:center}.table-pagination__controls{justify-content:center}.table-pagination__nav span{display:none}.inventory-import-head{align-items:flex-start}.inventory-grid{grid-template-columns:1fr}.inventory-results-summary{align-items:flex-start;flex-direction:column}.inventory-selection-bar{align-items:stretch;flex-direction:column}.inventory-selection-bar>button{justify-content:center}.movement-card{grid-template-columns:1fr}.movement-card__user,.pdf-button{grid-column:1}.history-intro{align-items:flex-start;flex-direction:column}.item-detail__hero{align-items:flex-start;flex-direction:column}.item-detail__photo{width:100%;height:170px;flex-basis:auto}.item-detail__grid{grid-template-columns:1fr}.item-detail__trace{grid-template-columns:1fr}.item-detail__actions{flex-wrap:wrap}.form-grid{grid-template-columns:1fr}.form-grid label.wide,.active-check{grid-column:span 1}.recipient-summary{grid-column:span 1;align-items:flex-start}.recipient-summary__permission{display:none}.form-photo{align-items:flex-start;flex-direction:column}.photo-picker{width:100%;height:150px;flex-basis:auto}.form-photo__content,.photo-actions,.photo-action{width:100%}.photo-action{justify-content:center}.editor-line,.editor-line--delivery{grid-template-columns:28px 1fr}.editor-line label{grid-column:2}.remove-line{grid-column:2;justify-self:end}.supply-stats p{display:none}}
@media (max-width:900px){.delivery-context-grid{grid-template-columns:1fr 1.5fr}.delivery-field--notes{grid-column:1/-1}.delivery-line{grid-template-columns:34px minmax(260px,1fr) 120px 34px}.delivery-line__notes{grid-column:2/4}.delivery-line__remove{grid-column:4;grid-row:1}.delivery-modal-hero__badge{display:none}}
@media (max-width:720px){:global(.supply-delivery-modal .modal-dialog){max-width:calc(100vw - .8rem);margin:.4rem auto}:global(.supply-delivery-modal .modal-content){border-radius:16px}.delivery-modal-hero{grid-template-columns:48px minmax(0,1fr) 34px;gap:.7rem;padding:1rem}.delivery-modal-hero__icon{width:48px;height:48px;border-radius:14px;font-size:1.35rem}.delivery-modal-hero h2{font-size:1.02rem}.delivery-modal-hero p{font-size:.61rem}.delivery-modal-hero__badge{display:none}.delivery-modal-close{grid-column:3;grid-row:1}.delivery-modal-body{gap:.6rem;padding:.55rem}.delivery-section{border-radius:13px}.delivery-section__head{align-items:flex-start;flex-wrap:wrap;padding:.7rem}.delivery-context-grid{grid-template-columns:1fr;padding:.7rem}.delivery-field--notes{grid-column:1}.delivery-context-grid .recipient-summary{grid-column:1}.delivery-lines-head__actions{width:100%;justify-content:space-between;margin-left:41px}.delivery-lines-list{padding:.55rem}.delivery-line{grid-template-columns:30px minmax(0,1fr) 34px;gap:.48rem;padding:.58rem}.delivery-line__number{grid-column:1;grid-row:1}.delivery-line__product{grid-column:2;grid-row:1}.delivery-line__quantity,.delivery-line__notes{grid-column:2/4}.delivery-line__remove{grid-column:3;grid-row:1}.delivery-product-option{grid-template-columns:34px minmax(0,1fr)}.delivery-product-option__image{width:34px;height:34px}.delivery-product-option__stock{grid-column:2;display:flex;align-items:baseline;gap:.25rem;padding:.25rem .4rem;text-align:left}.delivery-product-option__stock small{margin-right:auto}.supply-form .delivery-modal-footer{align-items:stretch;flex-direction:column;padding:.68rem}.delivery-modal-footer>div{display:none}.delivery-modal-footer nav{width:100%}.supply-form .delivery-modal-footer .btn{flex:1;padding:.5rem}.delivery-submit{flex:1.5!important}}
</style>
