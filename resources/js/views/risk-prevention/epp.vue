<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import { getPdfMake } from "../../utils/pdfmake";
import {
  confirmRiskAction,
  formatRiskDate,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
  showRiskWarning,
} from "../../components/risk-prevention/module-utils";

const emptyItem = () => ({
  id: null,
  name: "",
  epp_type: "",
  stock: 0,
  minimum_stock: 0,
  unit: "unidad",
  description: "",
  active: true,
});

const emptyDeliveryLine = () => ({
  _key: `${Date.now()}-${Math.random()}`,
  epp_item_id: null,
  quantity: 1,
  replacement_due_at: "",
});

const emptyDelivery = () => ({
  staff_id: null,
  employee_name: "",
  employee_rut: "",
  employee_position: "",
  delivered_at: "",
  received_conformity: false,
  notes: "",
  items: [emptyDeliveryLine()],
});

const normalizedHeader = (value) => String(value || "")
  .trim()
  .toLowerCase()
  .normalize("NFD")
  .replace(/[\u0300-\u036f]/g, "")
  .replace(/[^a-z0-9]+/g, "_")
  .replace(/^_|_$/g, "");

const parseDelimitedRows = (text) => {
  const source = String(text || "").replace(/^\uFEFF/, "");
  const firstLine = source.split(/\r?\n/, 1)[0] || "";
  const delimiters = [";", "\t", ","];
  const delimiter = delimiters
    .map((value) => ({ value, count: firstLine.split(value).length }))
    .sort((a, b) => b.count - a.count)[0].value;
  const rows = [];
  let row = [];
  let cell = "";
  let quoted = false;

  for (let index = 0; index < source.length; index += 1) {
    const character = source[index];
    const next = source[index + 1];

    if (character === '"') {
      if (quoted && next === '"') {
        cell += '"';
        index += 1;
      } else {
        quoted = !quoted;
      }
    } else if (character === delimiter && !quoted) {
      row.push(cell);
      cell = "";
    } else if ((character === "\n" || character === "\r") && !quoted) {
      if (character === "\r" && next === "\n") index += 1;
      row.push(cell);
      if (row.some((value) => String(value).trim() !== "")) rows.push(row);
      row = [];
      cell = "";
    } else {
      cell += character;
    }
  }

  row.push(cell);
  if (row.some((value) => String(value).trim() !== "")) rows.push(row);
  return rows;
};

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      activeTab: "deliveries",
      loadingItems: false,
      loadingRecords: false,
      savingItem: false,
      savingDelivery: false,
      importing: false,
      downloadingAct: null,
      catalogs: { epp_types: [], epp_items: [], epp_recipients: [] },
      items: [],
      records: [],
      itemFilters: { search: "", epp_type: "", low_stock: false },
      recordFilters: { search: "", received_conformity: "", from: "", to: "" },
      itemPagination: { current_page: 1, last_page: 1, total: 0, per_page: 15 },
      recordPagination: { current_page: 1, last_page: 1, total: 0, per_page: 12 },
      recordSummary: { total_records: 0, pending_conformity: 0, month_records: 0, delivered_units: 0 },
      showItemModal: false,
      showImportModal: false,
      showDeliveryModal: false,
      showActPreview: false,
      itemForm: emptyItem(),
      deliveryForm: emptyDelivery(),
      importFile: null,
      importRows: [],
      importErrors: [],
      actPreviewRecord: null,
      logoDataUrl: null,
    };
  },
  computed: {
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canManage() {
      return this.permissions.includes("gestionar_prevencion_riesgos")
        || this.permissions.includes("__superadmin__");
    },
    isEditingItem() {
      return Boolean(this.itemForm.id);
    },
    availableEppItems() {
      return (this.catalogs.epp_items || []).filter((item) => Number(item.stock) > 0);
    },
    lowStockCount() {
      return this.items.filter((item) => ["critico", "agotado"].includes(item.stock_status)).length;
    },
    totalStock() {
      return this.items.reduce((total, item) => total + Number(item.stock || 0), 0);
    },
    selectedDeliveryUnits() {
      return this.deliveryForm.items.reduce((total, line) => total + Number(line.quantity || 0), 0);
    },
    validImportRows() {
      return this.importRows.filter((row) => row.name && row.epp_type);
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadItems();
    this.loadRecords();
  },
  methods: {
    formatRiskDate,
    localDate() {
      const now = new Date();
      const timezoneOffset = now.getTimezoneOffset() * 60000;
      return new Date(now.getTime() - timezoneOffset).toISOString().slice(0, 10);
    },
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/risk-prevention/catalogs");
        this.catalogs = response.data || this.catalogs;
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudieron cargar los catálogos de EPP."));
      }
    },
    async loadItems(page = 1) {
      this.loadingItems = true;
      try {
        const response = await axios.get("/api/risk-prevention/epp/items", {
          params: {
            ...this.itemFilters,
            low_stock: this.itemFilters.low_stock ? 1 : "",
            page,
            per_page: this.itemPagination.per_page,
          },
        });
        this.items = response.data.data || [];
        this.itemPagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo cargar el catálogo EPP."));
      } finally {
        this.loadingItems = false;
      }
    },
    async loadRecords(page = 1) {
      this.loadingRecords = true;
      try {
        const response = await axios.get("/api/risk-prevention/epp/delivery-records", {
          params: {
            ...this.recordFilters,
            page,
            per_page: this.recordPagination.per_page,
          },
        });
        this.records = response.data.data || [];
        this.recordSummary = response.data.summary || this.recordSummary;
        this.recordPagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 12,
        };
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudieron cargar las actas de entrega."));
      } finally {
        this.loadingRecords = false;
      }
    },
    clearRecordFilters() {
      this.recordFilters = { search: "", received_conformity: "", from: "", to: "" };
      this.loadRecords(1);
    },
    clearItemFilters() {
      this.itemFilters = { search: "", epp_type: "", low_stock: false };
      this.loadItems(1);
    },
    openCreateItem() {
      if (!this.canManage) return;
      this.itemForm = emptyItem();
      this.showItemModal = true;
    },
    openEditItem(item) {
      if (!this.canManage) return;
      this.itemForm = {
        id: item.id,
        name: item.name || "",
        epp_type: item.epp_type || "",
        stock: item.stock ?? 0,
        minimum_stock: item.minimum_stock ?? 0,
        unit: item.unit || "unidad",
        description: item.description || "",
        active: Boolean(item.active),
      };
      this.showItemModal = true;
    },
    async saveItem() {
      if (!this.itemForm.name.trim() || !this.itemForm.epp_type.trim()) {
        await showRiskWarning("Completa el nombre y el tipo de EPP.", "Falta información");
        return;
      }

      this.savingItem = true;
      try {
        const payload = { ...this.itemForm };
        delete payload.id;
        if (this.isEditingItem) {
          await axios.put(`/api/risk-prevention/epp/items/${this.itemForm.id}`, payload);
        } else {
          await axios.post("/api/risk-prevention/epp/items", payload);
        }
        this.showItemModal = false;
        await Promise.all([this.loadCatalogs(), this.loadItems(this.itemPagination.current_page)]);
        await showRiskSuccess(this.isEditingItem ? "EPP actualizado correctamente." : "EPP creado correctamente.");
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo guardar el EPP."));
      } finally {
        this.savingItem = false;
      }
    },
    async removeItem(item) {
      const result = await confirmRiskAction({
        title: "Eliminar EPP",
        text: `Se eliminará “${item.name}” del catálogo.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/epp/items/${item.id}`);
        await Promise.all([this.loadCatalogs(), this.loadItems(this.itemPagination.current_page)]);
        await showRiskSuccess("El EPP fue eliminado correctamente.");
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el EPP."));
      }
    },
    openImport() {
      if (!this.canManage) return;
      this.importFile = null;
      this.importRows = [];
      this.importErrors = [];
      this.showImportModal = true;
    },
    downloadImportTemplate() {
      const csv = [
        "Nombre;Tipo;Stock;Stock mínimo;Unidad;Descripción;Activo",
        '"Casco de seguridad";"Protección de cabeza";20;5;unidad;"Casco certificado para trabajos generales";Sí',
        '"Guante anticorte";"Protección de manos";40;10;par;"Guante nivel de corte según ficha técnica";Sí',
      ].join("\r\n");
      const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: "text/csv;charset=utf-8" }));
      const link = document.createElement("a");
      link.href = url;
      link.download = "plantilla_listado_epp.csv";
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
    async onImportFile(event) {
      const file = event?.target?.files?.[0] || null;
      this.importFile = file;
      this.importRows = [];
      this.importErrors = [];
      if (!file) return;

      try {
        const parsedRows = parseDelimitedRows(await file.text());
        const headers = (parsedRows.shift() || []).map(normalizedHeader);
        const rows = parsedRows.map((values) => headers.reduce((result, header, index) => {
          result[header] = values[index] ?? "";
          return result;
        }, {}));

        this.importRows = rows.map((raw, index) => {
          const row = raw;
          const activeValue = String(row.activo ?? row.active ?? "si").trim().toLowerCase();

          return {
            _row: index + 2,
            name: String(row.nombre ?? row.epp ?? row.elemento ?? "").trim(),
            epp_type: String(row.tipo ?? row.tipo_epp ?? row.categoria ?? "").trim(),
            stock: Math.max(0, Number(row.stock ?? row.cantidad ?? 0) || 0),
            minimum_stock: Math.max(0, Number(row.stock_minimo ?? row.minimo ?? 0) || 0),
            unit: String(row.unidad ?? row.unit ?? "unidad").trim() || "unidad",
            description: String(row.descripcion ?? row.description ?? "").trim(),
            active: !["no", "0", "false", "inactivo"].includes(activeValue),
          };
        });

        this.importErrors = this.importRows
          .filter((row) => !row.name || !row.epp_type)
          .map((row) => `Fila ${row._row}: falta Nombre o Tipo.`);

        if (!this.importRows.length) {
          await showRiskWarning("La primera hoja no contiene registros.", "Archivo vacío");
        }
      } catch (error) {
        this.importFile = null;
        this.importRows = [];
        await showRiskError("No pudimos leer el archivo. Usa un CSV válido o descarga la plantilla.");
      }
    },
    async saveBulkItems() {
      if (!this.validImportRows.length) {
        await showRiskWarning("No hay filas válidas para cargar.", "Revisa el listado");
        return;
      }
      if (this.importErrors.length) {
        await showRiskWarning("Corrige las filas incompletas antes de continuar.", "Revisa el listado");
        return;
      }

      this.importing = true;
      try {
        const payload = this.validImportRows.map(({ _row, ...row }) => row);
        const response = await axios.post("/api/risk-prevention/epp/items/bulk", { items: payload });
        this.showImportModal = false;
        await Promise.all([this.loadCatalogs(), this.loadItems(1)]);
        const data = response.data.data || {};
        await showRiskSuccess(`Listado cargado: ${data.created || 0} creados y ${data.updated || 0} actualizados.`);
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo cargar el listado EPP."));
      } finally {
        this.importing = false;
      }
    },
    openCreateDelivery() {
      if (!this.canManage) return;
      if (!this.availableEppItems.length) {
        showRiskWarning("Primero registra EPP con stock disponible.", "Catálogo sin disponibilidad");
        this.activeTab = "catalog";
        return;
      }
      this.deliveryForm = {
        ...emptyDelivery(),
        delivered_at: this.localDate(),
      };
      this.showDeliveryModal = true;
    },
    onRecipientChange() {
      const recipient = (this.catalogs.epp_recipients || [])
        .find((item) => Number(item.id) === Number(this.deliveryForm.staff_id));
      if (!recipient) return;
      this.deliveryForm.employee_name = recipient.name || "";
      this.deliveryForm.employee_rut = recipient.rut || "";
      this.deliveryForm.employee_position = recipient.position || "";
    },
    addDeliveryLine() {
      this.deliveryForm.items.push(emptyDeliveryLine());
    },
    removeDeliveryLine(index) {
      if (this.deliveryForm.items.length === 1) {
        this.deliveryForm.items = [emptyDeliveryLine()];
        return;
      }
      this.deliveryForm.items.splice(index, 1);
    },
    eppById(id) {
      return (this.catalogs.epp_items || []).find((item) => Number(item.id) === Number(id));
    },
    lineStockLabel(line) {
      const item = this.eppById(line.epp_item_id);
      return item ? `${item.stock} ${item.unit} disponibles` : "Selecciona un elemento";
    },
    validateDelivery() {
      if (!this.deliveryForm.employee_name.trim()) return "Selecciona o ingresa al funcionario.";
      if (!this.deliveryForm.delivered_at) return "Ingresa la fecha de entrega.";
      if (!this.deliveryForm.items.length) return "Agrega al menos un EPP.";

      const ids = [];
      for (const line of this.deliveryForm.items) {
        const item = this.eppById(line.epp_item_id);
        if (!item) return "Selecciona un EPP en cada fila.";
        if (ids.includes(Number(item.id))) return `El EPP “${item.name}” está repetido.`;
        if (Number(line.quantity) < 1) return `Ingresa una cantidad válida para “${item.name}”.`;
        if (Number(line.quantity) > Number(item.stock)) {
          return `Stock insuficiente para “${item.name}”. Disponible: ${item.stock} ${item.unit}.`;
        }
        ids.push(Number(item.id));
      }
      return null;
    },
    deliveryPayload() {
      return {
        staff_id: this.deliveryForm.staff_id || null,
        employee_name: this.deliveryForm.employee_name.trim(),
        employee_rut: this.deliveryForm.employee_rut.trim() || null,
        employee_position: this.deliveryForm.employee_position.trim() || null,
        delivered_at: this.deliveryForm.delivered_at,
        received_conformity: Boolean(this.deliveryForm.received_conformity),
        notes: this.deliveryForm.notes.trim() || null,
        items: this.deliveryForm.items.map((line) => ({
          epp_item_id: Number(line.epp_item_id),
          quantity: Number(line.quantity),
          replacement_due_at: line.replacement_due_at || null,
        })),
      };
    },
    async saveDelivery() {
      const validationError = this.validateDelivery();
      if (validationError) {
        await showRiskWarning(validationError, "Falta información");
        return;
      }

      this.savingDelivery = true;
      try {
        const response = await axios.post("/api/risk-prevention/epp/delivery-records", this.deliveryPayload());
        this.showDeliveryModal = false;
        this.actPreviewRecord = response.data.data;
        await Promise.all([this.loadCatalogs(), this.loadItems(1), this.loadRecords(1)]);
        await showRiskSuccess("Entrega registrada. El acta FO-PREV-03 está lista.");
        this.showActPreview = true;
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo registrar la entrega."));
      } finally {
        this.savingDelivery = false;
      }
    },
    async previewDraftAct() {
      const validationError = this.validateDelivery();
      if (validationError) {
        await showRiskWarning(validationError, "Falta información");
        return;
      }
      this.actPreviewRecord = {
        folio: "BORRADOR",
        form_code: "FO-PREV-03",
        form_revision: "01",
        employee_name_snapshot: this.deliveryForm.employee_name,
        employee_rut_snapshot: this.deliveryForm.employee_rut,
        employee_position_snapshot: this.deliveryForm.employee_position,
        delivered_at: this.deliveryForm.delivered_at,
        received_conformity: this.deliveryForm.received_conformity,
        notes: this.deliveryForm.notes,
        deliveries: this.deliveryForm.items.map((line) => {
          const item = this.eppById(line.epp_item_id);
          return {
            epp_name_snapshot: item?.name || "",
            unit_snapshot: item?.unit || "unidad",
            quantity: Number(line.quantity),
            delivered_at: this.deliveryForm.delivered_at,
            replacement_due_at: line.replacement_due_at || null,
          };
        }),
      };
      this.showActPreview = true;
    },
    openRecord(record) {
      this.actPreviewRecord = record;
      this.showActPreview = true;
    },
    actItemName(line) {
      const name = line.epp_name_snapshot || line.item?.name || "EPP";
      const unit = line.unit_snapshot || line.item?.unit || "unidad";
      return `${name} — ${line.quantity} ${Number(line.quantity) === 1 ? unit : unit}`;
    },
    async ensureLogoDataUrl() {
      if (this.logoDataUrl) return this.logoDataUrl;
      const response = await fetch("/brand/logo-cnsc.png");
      const blob = await response.blob();
      this.logoDataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
      return this.logoDataUrl;
    },
    async buildActDefinition(record) {
      const logo = await this.ensureLogoDataUrl();
      const deliveries = record.deliveries || [];
      const receptionDate = this.formatRiskDate(record.delivered_at);
      const receiptText = record.received_conformity
        ? "RECIBÍ CONFORME\n\n____________________\nFirma trabajador"
        : "\n\n____________________\nFirma trabajador";
      const itemRows = deliveries.map((line) => [
        { text: this.actItemName(line), margin: [4, 6, 4, 6] },
        { text: receptionDate, alignment: "center", margin: [3, 6, 3, 6] },
        { text: receiptText, alignment: "center", fontSize: 8, margin: [3, 4, 3, 4] },
      ]);

      return {
        pageSize: "LETTER",
        pageMargins: [52, 42, 52, 45],
        content: [
          {
            columns: [
              { image: logo, width: 58, height: 65 },
              { text: "" },
              {
                width: 122,
                stack: [
                  { text: `COD: ${record.form_code || "FO-PREV-03"}`, fontSize: 10 },
                  { text: `REV:${record.form_revision || "01"}`, fontSize: 10 },
                  { text: `FOLIO: ${record.folio || "BORRADOR"}`, fontSize: 8, margin: [0, 4, 0, 0] },
                ],
              },
            ],
            margin: [0, 0, 0, 12],
          },
          {
            text: "ENTREGA DE ELEMENTOS DE PROTECCIÓN PERSONAL",
            alignment: "center",
            bold: true,
            decoration: "underline",
            fontSize: 14,
            margin: [0, 0, 0, 16],
          },
          {
            text: "De acuerdo con lo estipulado en la Ley 16.744, artículo 68, inciso tercero, las empresas deberán proporcionar a sus trabajadores los equipos e implementos de protección necesarios, no pudiendo en caso alguno cobrarles su valor.",
            alignment: "justify",
            fontSize: 10,
            lineHeight: 1.2,
            margin: [0, 0, 0, 14],
          },
          {
            table: {
              widths: [150, "*"],
              body: [
                [{ text: "NOMBRE TRABAJADOR", bold: true }, record.employee_name_snapshot || "-"],
                [{ text: "RUT", bold: true }, record.employee_rut_snapshot || "-"],
                [{ text: "CARGO", bold: true }, record.employee_position_snapshot || "-"],
                [{ text: "FECHA", bold: true }, receptionDate],
              ],
            },
            layout: {
              hLineColor: () => "#303b4f",
              vLineColor: () => "#303b4f",
              paddingLeft: () => 6,
              paddingRight: () => 6,
              paddingTop: () => 4,
              paddingBottom: () => 4,
            },
            margin: [0, 0, 0, 18],
          },
          {
            table: {
              headerRows: 1,
              dontBreakRows: true,
              widths: [250, 105, 153],
              body: [
                [
                  { text: "ELEMENTO ENTREGADO", style: "actHeader" },
                  { text: "FECHA DE RECEPCIÓN", style: "actHeader" },
                  { text: "RECIBÍ CONFORME", style: "actHeader" },
                ],
                ...itemRows,
              ],
            },
            layout: {
              hLineColor: () => "#1f4f7a",
              vLineColor: () => "#1f4f7a",
              hLineWidth: () => 1,
              vLineWidth: () => 1,
              paddingLeft: () => 3,
              paddingRight: () => 3,
              paddingTop: () => 2,
              paddingBottom: () => 2,
            },
            margin: [0, 0, 0, 14],
          },
          {
            text: "El trabajador se compromete a mantener los elementos de protección personal en buen estado y declara haberlos recibido en forma gratuita.",
            fontSize: 9,
            lineHeight: 1.15,
          },
          ...(record.notes ? [{ text: `Observaciones: ${record.notes}`, fontSize: 8, color: "#4f5d75", margin: [0, 10, 0, 0] }] : []),
        ],
        styles: {
          actHeader: {
            bold: true,
            alignment: "center",
            color: "#173b5d",
            fillColor: "#edf5fb",
            fontSize: 9,
            margin: [2, 5, 2, 5],
          },
        },
        defaultStyle: {
          font: "Roboto",
          fontSize: 9,
          color: "#111827",
        },
        footer: (page, pages) => ({
          text: `${record.folio || "BORRADOR"} · Página ${page} de ${pages}`,
          alignment: "right",
          fontSize: 7,
          color: "#6b7280",
          margin: [0, 10, 52, 0],
        }),
      };
    },
    async downloadAct(record) {
      this.downloadingAct = record.id || record.folio;
      try {
        const pdfMake = getPdfMake();
        const definition = await this.buildActDefinition(record);
        pdfMake.createPdf(definition).download(`${record.folio || "acta-entrega-epp"}.pdf`);
        await showRiskSuccess("El acta se descargó correctamente.");
      } catch (error) {
        showRiskError("No se pudo generar el acta PDF.");
      } finally {
        this.downloadingAct = null;
      }
    },
    conformityLabel(value) {
      return value ? "Recibido conforme" : "Pendiente de firma";
    },
  },
};
</script>

<template>
  <Layout>
    <section class="epp-hero mb-4">
      <div>
        <span class="epp-eyebrow">Prevención de riesgos · FO-PREV-03</span>
        <h1>Entrega de EPP</h1>
        <p>Administra el catálogo, registra entregas múltiples y genera el acta de recepción.</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <HelpButton
          title="Ayuda: entrega de EPP"
          text="Carga el catálogo de elementos, selecciona un funcionario, registra uno o más EPP y descarga el acta FO-PREV-03."
        />
        <BButton v-if="canManage" class="hero-action" @click="openCreateDelivery">
          <i class="bx bx-plus-circle"></i>
          Nueva entrega
        </BButton>
      </div>
    </section>

    <section class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3">
        <article class="summary-card">
          <span class="summary-icon blue"><i class="bx bx-file"></i></span>
          <div><strong>{{ recordSummary.total_records }}</strong><span>Actas generadas</span></div>
        </article>
      </div>
      <div class="col-sm-6 col-xl-3">
        <article class="summary-card">
          <span class="summary-icon green"><i class="bx bx-package"></i></span>
          <div><strong>{{ recordSummary.delivered_units }}</strong><span>Unidades entregadas</span></div>
        </article>
      </div>
      <div class="col-sm-6 col-xl-3">
        <article class="summary-card">
          <span class="summary-icon amber"><i class="bx bx-error-circle"></i></span>
          <div><strong>{{ lowStockCount }}</strong><span>EPP con stock crítico</span></div>
        </article>
      </div>
      <div class="col-sm-6 col-xl-3">
        <article class="summary-card">
          <span class="summary-icon violet"><i class="bx bx-calendar-check"></i></span>
          <div><strong>{{ recordSummary.month_records }}</strong><span>Entregas este mes</span></div>
        </article>
      </div>
    </section>

    <div class="epp-tabs mb-3">
      <button :class="{ active: activeTab === 'deliveries' }" @click="activeTab = 'deliveries'">
        <i class="bx bx-receipt"></i> Actas de entrega
      </button>
      <button :class="{ active: activeTab === 'catalog' }" @click="activeTab = 'catalog'">
        <i class="bx bx-hard-hat"></i> Catálogo de EPP
      </button>
    </div>

    <section v-if="activeTab === 'deliveries'" class="content-panel">
      <header class="panel-header">
        <div>
          <h2>Actas de entrega</h2>
          <p>Historial agrupado por funcionario y fecha de recepción.</p>
        </div>
        <BButton v-if="canManage" variant="primary" @click="openCreateDelivery">
          <i class="bx bx-plus"></i> Registrar entrega
        </BButton>
      </header>

      <div class="filters-grid">
        <div class="filter-search">
          <label>Buscar</label>
          <div class="input-icon">
            <i class="bx bx-search"></i>
            <BFormInput v-model="recordFilters.search" placeholder="Folio, funcionario, RUT o EPP" @keyup.enter="loadRecords(1)" />
          </div>
        </div>
        <div>
          <label>Conformidad</label>
          <BFormSelect v-model="recordFilters.received_conformity" :options="[
            { value: '', text: 'Todas' },
            { value: '1', text: 'Recibido conforme' },
            { value: '0', text: 'Pendiente de firma' },
          ]" />
        </div>
        <div>
          <label>Desde</label>
          <BFormInput v-model="recordFilters.from" type="date" />
        </div>
        <div>
          <label>Hasta</label>
          <BFormInput v-model="recordFilters.to" type="date" />
        </div>
        <div class="filter-actions">
          <BButton variant="primary" @click="loadRecords(1)"><i class="bx bx-filter-alt"></i> Filtrar</BButton>
          <BButton variant="light" @click="clearRecordFilters">Limpiar</BButton>
        </div>
      </div>

      <LoadingState v-if="loadingRecords" message="Cargando actas de entrega..." />
      <div v-else-if="records.length" class="table-responsive">
        <table class="table epp-table align-middle">
          <thead>
            <tr>
              <th>Acta</th>
              <th>Funcionario</th>
              <th>Entrega</th>
              <th>Elementos</th>
              <th>Conformidad</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in records" :key="record.id">
              <td>
                <strong class="folio">{{ record.folio }}</strong>
                <span class="cell-muted">{{ record.form_code }} · Rev. {{ record.form_revision }}</span>
              </td>
              <td>
                <strong>{{ record.employee_name_snapshot }}</strong>
                <span class="cell-muted">{{ record.employee_rut_snapshot || "Sin RUT" }} · {{ record.employee_position_snapshot || "Sin cargo" }}</span>
              </td>
              <td>
                <strong>{{ formatRiskDate(record.delivered_at) }}</strong>
                <span class="cell-muted">{{ record.deliveries?.length || 0 }} tipos · {{ record.total_units }} unidades</span>
              </td>
              <td>
                <div class="epp-chips">
                  <span v-for="line in (record.deliveries || []).slice(0, 2)" :key="line.id">
                    {{ line.epp_name_snapshot || line.item?.name }} × {{ line.quantity }}
                  </span>
                  <span v-if="(record.deliveries || []).length > 2">+{{ record.deliveries.length - 2 }}</span>
                </div>
              </td>
              <td>
                <span class="conformity-badge" :class="{ confirmed: record.received_conformity }">
                  <i :class="record.received_conformity ? 'bx bx-check-circle' : 'bx bx-time-five'"></i>
                  {{ conformityLabel(record.received_conformity) }}
                </span>
              </td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2">
                  <BButton size="sm" variant="outline-primary" title="Ver acta" @click="openRecord(record)">
                    <i class="bx bx-show"></i>
                  </BButton>
                  <BButton
                    size="sm"
                    variant="primary"
                    title="Descargar PDF"
                    :disabled="downloadingAct === record.id"
                    @click="downloadAct(record)"
                  >
                    <i class="bx bx-download"></i>
                  </BButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="empty-state">
        <span><i class="bx bx-file-blank"></i></span>
        <h3>No hay actas de entrega</h3>
        <p>Registra la primera entrega para generar automáticamente el FO-PREV-03.</p>
        <BButton v-if="canManage" variant="primary" @click="openCreateDelivery">Registrar primera entrega</BButton>
      </div>

      <BPagination
        v-if="recordPagination.last_page > 1"
        v-model="recordPagination.current_page"
        :total-rows="recordPagination.total"
        :per-page="recordPagination.per_page"
        class="justify-content-end mt-3"
        @update:model-value="loadRecords"
      />
    </section>

    <section v-else class="content-panel">
      <header class="panel-header">
        <div>
          <h2>Catálogo de EPP</h2>
          <p>Listado disponible para formularios de entrega y control de stock.</p>
        </div>
        <div v-if="canManage" class="d-flex flex-wrap gap-2">
          <BButton variant="outline-primary" @click="openImport"><i class="bx bx-spreadsheet"></i> Cargar listado</BButton>
          <BButton variant="primary" @click="openCreateItem"><i class="bx bx-plus"></i> Nuevo EPP</BButton>
        </div>
      </header>

      <div class="catalog-toolbar">
        <div class="input-icon">
          <i class="bx bx-search"></i>
          <BFormInput v-model="itemFilters.search" placeholder="Buscar por nombre o tipo" @keyup.enter="loadItems(1)" />
        </div>
        <BFormInput v-model="itemFilters.epp_type" list="epp-catalog-types" placeholder="Tipo de EPP" />
        <datalist id="epp-catalog-types">
          <option v-for="type in catalogs.epp_types || []" :key="type" :value="type"></option>
        </datalist>
        <BFormCheckbox v-model="itemFilters.low_stock" switch>Solo stock crítico</BFormCheckbox>
        <BButton variant="primary" @click="loadItems(1)">Filtrar</BButton>
        <BButton variant="light" @click="clearItemFilters">Limpiar</BButton>
      </div>

      <LoadingState v-if="loadingItems" message="Cargando catálogo EPP..." />
      <div v-else-if="items.length" class="table-responsive">
        <table class="table epp-table align-middle">
          <thead>
            <tr>
              <th>Elemento</th>
              <th>Tipo</th>
              <th>Stock disponible</th>
              <th>Mínimo</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td><strong>{{ item.name }}</strong><span class="cell-muted">{{ item.description || "Sin descripción" }}</span></td>
              <td>{{ item.epp_type }}</td>
              <td><strong>{{ item.stock }}</strong> {{ item.unit }}</td>
              <td>{{ item.minimum_stock }} {{ item.unit }}</td>
              <td><StatusBadge :status="item.stock_status" /></td>
              <td class="text-end">
                <div v-if="canManage" class="d-flex justify-content-end gap-2">
                  <BButton size="sm" variant="outline-primary" @click="openEditItem(item)"><i class="bx bx-edit"></i></BButton>
                  <BButton size="sm" variant="outline-danger" @click="removeItem(item)"><i class="bx bx-trash"></i></BButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="empty-state">
        <span><i class="bx bx-hard-hat"></i></span>
        <h3>Catálogo sin elementos</h3>
        <p>Carga una planilla o registra manualmente el primer EPP.</p>
        <div v-if="canManage" class="d-flex justify-content-center gap-2">
          <BButton variant="outline-primary" @click="openImport">Cargar listado</BButton>
          <BButton variant="primary" @click="openCreateItem">Nuevo EPP</BButton>
        </div>
      </div>

      <BPagination
        v-if="itemPagination.last_page > 1"
        v-model="itemPagination.current_page"
        :total-rows="itemPagination.total"
        :per-page="itemPagination.per_page"
        class="justify-content-end mt-3"
        @update:model-value="loadItems"
      />
    </section>

    <BModal v-model="showItemModal" size="lg" :title="isEditingItem ? 'Editar EPP' : 'Nuevo EPP'" hide-footer>
      <div class="modal-intro">
        <span><i class="bx bx-hard-hat"></i></span>
        <div>
          <strong>{{ isEditingItem ? "Actualiza el elemento" : "Agrega un elemento al catálogo" }}</strong>
          <p>Define nombre, clasificación, unidad y niveles de stock.</p>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-7">
          <label class="form-label">Nombre <em>*</em></label>
          <BFormInput v-model="itemForm.name" placeholder="Ej: Casco de seguridad" />
        </div>
        <div class="col-md-5">
          <label class="form-label">Tipo <em>*</em></label>
          <BFormInput v-model="itemForm.epp_type" list="epp-item-types" placeholder="Protección de cabeza" />
          <datalist id="epp-item-types">
            <option v-for="type in catalogs.epp_types || []" :key="type" :value="type"></option>
          </datalist>
        </div>
        <div class="col-md-3">
          <label class="form-label">Stock</label>
          <BFormInput v-model.number="itemForm.stock" type="number" min="0" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Stock mínimo</label>
          <BFormInput v-model.number="itemForm.minimum_stock" type="number" min="0" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Unidad</label>
          <BFormInput v-model="itemForm.unit" placeholder="unidad, par..." />
        </div>
        <div class="col-md-3 d-flex align-items-end pb-2">
          <BFormCheckbox v-model="itemForm.active" switch>Elemento activo</BFormCheckbox>
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="itemForm.description" rows="3" placeholder="Certificación, uso recomendado u otra referencia" />
        </div>
      </div>
      <div class="modal-actions">
        <BButton variant="light" @click="showItemModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="savingItem" @click="saveItem">
          <BSpinner v-if="savingItem" small class="me-1" /> {{ savingItem ? "Guardando..." : "Guardar EPP" }}
        </BButton>
      </div>
    </BModal>

    <BModal v-model="showImportModal" size="xl" title="Cargar listado de EPP" hide-footer>
      <div class="import-drop">
        <span><i class="bx bx-spreadsheet"></i></span>
        <div>
          <strong>Importa un listado CSV</strong>
          <p>La plantilla se abre y edita normalmente con Excel. Columnas: Nombre, Tipo, Stock, Stock mínimo, Unidad, Descripción y Activo.</p>
        </div>
        <BButton variant="outline-primary" @click="downloadImportTemplate"><i class="bx bx-download"></i> Descargar plantilla</BButton>
      </div>
      <BFormFile
        accept=".csv,.txt,text/csv"
        browse-text="Seleccionar archivo"
        placeholder="Selecciona la planilla con el listado EPP"
        @change="onImportFile"
      />
      <div v-if="importErrors.length" class="alert alert-warning mt-3 mb-0">
        <strong>Filas por corregir</strong>
        <ul class="mb-0 mt-1"><li v-for="error in importErrors.slice(0, 8)" :key="error">{{ error }}</li></ul>
      </div>
      <div v-if="importRows.length" class="mt-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong>Vista previa</strong>
          <span class="text-muted">{{ validImportRows.length }} elementos</span>
        </div>
        <div class="table-responsive import-preview">
          <table class="table table-sm align-middle">
            <thead><tr><th>Fila</th><th>Nombre</th><th>Tipo</th><th>Stock</th><th>Mínimo</th><th>Unidad</th><th>Activo</th></tr></thead>
            <tbody>
              <tr v-for="row in importRows.slice(0, 12)" :key="row._row" :class="{ 'table-warning': !row.name || !row.epp_type }">
                <td>{{ row._row }}</td><td>{{ row.name || "—" }}</td><td>{{ row.epp_type || "—" }}</td>
                <td>{{ row.stock }}</td><td>{{ row.minimum_stock }}</td><td>{{ row.unit }}</td><td>{{ row.active ? "Sí" : "No" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-actions">
        <BButton variant="light" @click="showImportModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="importing || !validImportRows.length" @click="saveBulkItems">
          <BSpinner v-if="importing" small class="me-1" /> {{ importing ? "Cargando..." : `Cargar ${validImportRows.length} elementos` }}
        </BButton>
      </div>
    </BModal>

    <BModal v-model="showDeliveryModal" size="xl" title="Nueva entrega de EPP" hide-footer>
      <div class="modal-intro delivery">
        <span><i class="bx bx-file"></i></span>
        <div>
          <strong>Formulario de entrega · FO-PREV-03</strong>
          <p>Los datos quedan guardados como fotografía histórica del acta.</p>
        </div>
      </div>

      <div class="form-section">
        <div class="section-number">01</div>
        <div class="section-content">
          <h3>Datos del trabajador</h3>
          <p>Selecciona un funcionario para completar automáticamente sus datos.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Funcionario</label>
              <BFormSelect
                v-model="deliveryForm.staff_id"
                :options="[{ value: null, text: 'Seleccionar funcionario' }].concat((catalogs.epp_recipients || []).map((item) => ({ value: item.id, text: `${item.name}${item.rut ? ` · ${item.rut}` : ''}` })))"
                @change="onRecipientChange"
              />
            </div>
            <div class="col-md-6">
              <label class="form-label">Nombre trabajador <em>*</em></label>
              <BFormInput v-model="deliveryForm.employee_name" placeholder="Nombre completo" />
            </div>
            <div class="col-md-4">
              <label class="form-label">RUT</label>
              <BFormInput v-model="deliveryForm.employee_rut" placeholder="12.345.678-9" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Cargo</label>
              <BFormInput v-model="deliveryForm.employee_position" placeholder="Cargo o función" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha de entrega <em>*</em></label>
              <BFormInput v-model="deliveryForm.delivered_at" type="date" />
            </div>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="section-number">02</div>
        <div class="section-content">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div><h3>Elementos entregados</h3><p>Agrega todos los EPP que formarán parte de esta acta.</p></div>
            <BButton size="sm" variant="outline-primary" @click="addDeliveryLine"><i class="bx bx-plus"></i> Agregar EPP</BButton>
          </div>
          <div class="delivery-lines">
            <div v-for="(line, index) in deliveryForm.items" :key="line._key" class="delivery-line">
              <span class="line-index">{{ index + 1 }}</span>
              <div class="line-item">
                <label>EPP <em>*</em></label>
                <BFormSelect
                  v-model="line.epp_item_id"
                  :options="[{ value: null, text: 'Seleccionar elemento' }].concat(availableEppItems.map((item) => ({ value: item.id, text: `${item.name} · ${item.stock} ${item.unit}` })))"
                />
                <small>{{ lineStockLabel(line) }}</small>
              </div>
              <div>
                <label>Cantidad</label>
                <BFormInput v-model.number="line.quantity" type="number" min="1" />
              </div>
              <div>
                <label>Próxima reposición</label>
                <BFormInput v-model="line.replacement_due_at" type="date" />
              </div>
              <BButton class="remove-line" variant="outline-danger" title="Quitar fila" @click="removeDeliveryLine(index)">
                <i class="bx bx-trash"></i>
              </BButton>
            </div>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="section-number">03</div>
        <div class="section-content">
          <h3>Recepción y observaciones</h3>
          <p>Indica si la entrega queda recibida conforme o pendiente de firma.</p>
          <div class="conformity-card" :class="{ selected: deliveryForm.received_conformity }">
            <div><i class="bx bx-check-shield"></i><strong>Recibí conforme</strong><span>El funcionario declara la recepción gratuita de los elementos.</span></div>
            <BFormCheckbox v-model="deliveryForm.received_conformity" switch size="lg">
              {{ deliveryForm.received_conformity ? "Sí" : "Pendiente" }}
            </BFormCheckbox>
          </div>
          <label class="form-label mt-3">Observaciones</label>
          <BFormTextarea v-model="deliveryForm.notes" rows="3" placeholder="Tallas, condiciones de uso, certificaciones u otra información relevante" />
        </div>
      </div>

      <div class="delivery-footer">
        <div><strong>{{ deliveryForm.items.length }}</strong> tipos · <strong>{{ selectedDeliveryUnits }}</strong> unidades</div>
        <div class="d-flex gap-2">
          <BButton variant="light" @click="showDeliveryModal = false">Cancelar</BButton>
          <BButton variant="outline-primary" @click="previewDraftAct"><i class="bx bx-show"></i> Vista previa</BButton>
          <BButton variant="primary" :disabled="savingDelivery" @click="saveDelivery">
            <BSpinner v-if="savingDelivery" small class="me-1" />
            {{ savingDelivery ? "Registrando..." : "Registrar y generar acta" }}
          </BButton>
        </div>
      </div>
    </BModal>

    <BModal v-model="showActPreview" size="xl" title="Acta de entrega FO-PREV-03" hide-footer>
      <div v-if="actPreviewRecord" class="act-preview">
        <div class="act-header">
          <img src="/brand/logo-cnsc.png" alt="Colegio Nuestra Señora del Carmen" />
          <div><span>COD: {{ actPreviewRecord.form_code || "FO-PREV-03" }}</span><span>REV:{{ actPreviewRecord.form_revision || "01" }}</span><small>FOLIO: {{ actPreviewRecord.folio || "BORRADOR" }}</small></div>
        </div>
        <h2>ENTREGA DE ELEMENTOS DE PROTECCIÓN PERSONAL</h2>
        <p class="act-law">De acuerdo con lo estipulado en la Ley 16.744, artículo 68, inciso tercero, las empresas deberán proporcionar a sus trabajadores los equipos e implementos de protección necesarios, no pudiendo en caso alguno cobrarles su valor.</p>
        <dl class="act-person">
          <div><dt>NOMBRE TRABAJADOR</dt><dd>{{ actPreviewRecord.employee_name_snapshot || "—" }}</dd></div>
          <div><dt>RUT</dt><dd>{{ actPreviewRecord.employee_rut_snapshot || "—" }}</dd></div>
          <div><dt>CARGO</dt><dd>{{ actPreviewRecord.employee_position_snapshot || "—" }}</dd></div>
          <div><dt>FECHA</dt><dd>{{ formatRiskDate(actPreviewRecord.delivered_at) }}</dd></div>
        </dl>
        <div class="table-responsive">
          <table class="act-table">
            <thead><tr><th>ELEMENTO ENTREGADO</th><th>FECHA DE RECEPCIÓN</th><th>RECIBÍ CONFORME</th></tr></thead>
            <tbody>
              <tr v-for="(line, index) in actPreviewRecord.deliveries || []" :key="line.id || index">
                <td>{{ actItemName(line) }}</td>
                <td>{{ formatRiskDate(actPreviewRecord.delivered_at) }}</td>
                <td><span v-if="actPreviewRecord.received_conformity">RECIBÍ CONFORME</span><i>Firma trabajador</i></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="act-commitment">El trabajador se compromete a mantener los elementos de protección personal en buen estado y declara haberlos recibido en forma gratuita.</p>
        <p v-if="actPreviewRecord.notes" class="act-notes"><strong>Observaciones:</strong> {{ actPreviewRecord.notes }}</p>
      </div>
      <div class="modal-actions">
        <BButton variant="light" @click="showActPreview = false">Cerrar</BButton>
        <BButton variant="primary" :disabled="!actPreviewRecord || downloadingAct" @click="downloadAct(actPreviewRecord)">
          <i class="bx bx-download"></i> Descargar acta PDF
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.epp-hero{display:flex;justify-content:space-between;align-items:center;gap:1.5rem;padding:2rem;border-radius:24px;color:#fff;background:linear-gradient(120deg,#152c61 0%,#2457bb 58%,#2b86a6 100%);box-shadow:0 18px 45px rgba(32,70,150,.18);position:relative;overflow:hidden}.epp-hero:after{content:"";position:absolute;width:260px;height:260px;border:42px solid rgba(255,255,255,.06);border-radius:50%;right:-70px;top:-105px}.epp-hero>div{position:relative;z-index:1}.epp-eyebrow{display:block;text-transform:uppercase;letter-spacing:.16em;font-size:.72rem;font-weight:800;color:#cfe6ff;margin-bottom:.55rem}.epp-hero h1{font-size:2rem;font-weight:800;margin:0 0 .35rem}.epp-hero p{margin:0;max-width:650px;color:#e7efff;font-size:1rem}.hero-action{background:#fff!important;color:#1f4eae!important;border:0!important;font-weight:700;white-space:nowrap;box-shadow:0 8px 22px rgba(0,0,0,.14)}.summary-card{height:100%;display:flex;align-items:center;gap:1rem;background:#fff;border:1px solid #e8edf6;border-radius:18px;padding:1rem 1.1rem;box-shadow:0 8px 26px rgba(31,45,74,.05)}.summary-icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;font-size:1.35rem}.summary-icon.blue{background:#edf3ff;color:#2b5dca}.summary-icon.green{background:#ebfbf4;color:#15875d}.summary-icon.amber{background:#fff6e5;color:#cc7a00}.summary-icon.violet{background:#f4efff;color:#7651c9}.summary-card strong{display:block;font-size:1.45rem;line-height:1.1;color:#1f2a44}.summary-card span:last-child{display:block;margin-top:.25rem;color:#7a8499;font-size:.82rem}.epp-tabs{display:inline-flex;background:#eef2f8;border-radius:14px;padding:4px;gap:4px}.epp-tabs button{border:0;background:transparent;border-radius:11px;padding:.68rem 1rem;color:#68748a;font-weight:700}.epp-tabs button.active{background:#fff;color:#2857b8;box-shadow:0 4px 14px rgba(38,62,110,.1)}.content-panel{background:#fff;border:1px solid #e5eaf3;border-radius:20px;padding:1.35rem;box-shadow:0 10px 32px rgba(32,48,78,.05)}.panel-header{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.2rem}.panel-header h2{font-size:1.14rem;font-weight:800;margin:0;color:#202c46}.panel-header p{margin:.25rem 0 0;color:#7b8599;font-size:.86rem}.filters-grid{display:grid;grid-template-columns:minmax(240px,1.6fr) repeat(3,minmax(140px,.7fr)) auto;gap:.75rem;align-items:end;background:#f7f9fc;border:1px solid #e9edf4;padding:1rem;border-radius:16px;margin-bottom:1.2rem}.filters-grid label,.catalog-toolbar+label{display:block;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#69758b;margin-bottom:.35rem}.input-icon{position:relative}.input-icon i{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);z-index:2;color:#96a0b3}.input-icon :deep(input){padding-left:2.2rem}.filter-actions{display:flex;gap:.5rem}.catalog-toolbar{display:grid;grid-template-columns:minmax(250px,1.3fr) minmax(170px,.7fr) auto auto auto;gap:.75rem;align-items:center;background:#f7f9fc;border:1px solid #e9edf4;padding:1rem;border-radius:16px;margin-bottom:1.2rem}.epp-table{margin:0}.epp-table thead th{background:#f7f9fc;border-bottom:1px solid #e7ebf2;color:#647087;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;padding:.8rem .7rem;white-space:nowrap}.epp-table tbody td{padding:.85rem .7rem;border-color:#edf0f5;color:#39445a}.epp-table strong{display:block;color:#263149}.cell-muted{display:block;color:#8a94a7;font-size:.76rem;margin-top:.18rem}.folio{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#2858b5!important}.epp-chips{display:flex;flex-wrap:wrap;gap:.3rem}.epp-chips span{background:#eef3fb;color:#53627a;border-radius:999px;padding:.25rem .5rem;font-size:.72rem}.conformity-badge{display:inline-flex;align-items:center;gap:.32rem;border-radius:999px;background:#fff3dc;color:#a96700;padding:.35rem .6rem;font-size:.74rem;font-weight:700;white-space:nowrap}.conformity-badge.confirmed{background:#e8f8f1;color:#177557}.empty-state{text-align:center;padding:3.6rem 1rem}.empty-state>span{display:grid;place-items:center;width:58px;height:58px;margin:0 auto 1rem;border-radius:18px;background:#eef3ff;color:#2f63cb;font-size:1.65rem}.empty-state h3{font-size:1.05rem;font-weight:800;color:#2b364d}.empty-state p{color:#8992a4;margin-bottom:1.1rem}.modal-intro{display:flex;align-items:center;gap:.85rem;padding:1rem;border-radius:16px;background:#f4f7fc;margin-bottom:1.25rem}.modal-intro>span{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:#e7efff;color:#2b5ec3;font-size:1.3rem}.modal-intro strong{display:block;color:#25314a}.modal-intro p{margin:.2rem 0 0;color:#7c8799;font-size:.82rem}.modal-intro.delivery{background:linear-gradient(100deg,#edf3ff,#eef9fb)}.form-label{font-weight:700;color:#4e5a70;font-size:.8rem}.form-label em,label em{color:#d64b5d;font-style:normal}.modal-actions{display:flex;justify-content:flex-end;gap:.6rem;border-top:1px solid #e9edf4;margin-top:1.3rem;padding-top:1rem}.import-drop{display:flex;align-items:center;gap:1rem;padding:1rem;border:1px dashed #9eb6e5;background:#f5f8ff;border-radius:16px;margin-bottom:1rem}.import-drop>span{font-size:1.8rem;color:#2b5fc4}.import-drop div{flex:1}.import-drop strong{display:block;color:#26334d}.import-drop p{margin:.2rem 0 0;color:#7e899d;font-size:.82rem}.import-preview{max-height:330px;border:1px solid #e8ecf3;border-radius:12px}.form-section{display:grid;grid-template-columns:44px 1fr;gap:1rem;padding:1.1rem 0;border-bottom:1px solid #edf0f5}.section-number{width:36px;height:36px;border-radius:11px;background:#eaf1ff;color:#2c5fc3;font-size:.75rem;font-weight:800;display:grid;place-items:center}.section-content h3{font-size:1rem;font-weight:800;margin:0;color:#27334c}.section-content>p,.section-content>div>div>p{margin:.2rem 0 1rem;color:#818b9e;font-size:.82rem}.delivery-lines{display:flex;flex-direction:column;gap:.65rem}.delivery-line{display:grid;grid-template-columns:30px minmax(260px,1.5fr) 105px minmax(170px,.8fr) 42px;gap:.65rem;align-items:center;padding:.8rem;background:#f8fafc;border:1px solid #e8edf4;border-radius:14px}.line-index{width:27px;height:27px;border-radius:9px;display:grid;place-items:center;background:#e9eff9;color:#456089;font-weight:800;font-size:.75rem}.delivery-line label{display:block;font-size:.72rem;font-weight:800;color:#58647a;margin-bottom:.25rem}.delivery-line small{display:block;color:#8791a3;margin-top:.2rem}.remove-line{align-self:end}.conformity-card{display:flex;justify-content:space-between;align-items:center;gap:1rem;border:1px solid #e2e7ef;border-radius:15px;padding:1rem;background:#fafbfd}.conformity-card.selected{border-color:#8ed5b8;background:#f0fbf6}.conformity-card>div{display:grid;grid-template-columns:32px 1fr;column-gap:.65rem}.conformity-card i{grid-row:1/3;font-size:1.5rem;color:#2a64c7}.conformity-card strong{color:#2c374e}.conformity-card span{font-size:.78rem;color:#7c8798}.delivery-footer{position:sticky;bottom:-1rem;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:1rem;background:#fff;border-top:1px solid #e7ebf2;margin:1.2rem -1rem -1rem;padding:1rem;box-shadow:0 -8px 22px rgba(35,47,70,.06)}.act-preview{max-width:820px;margin:0 auto;background:#fff;border:1px solid #d9dee8;padding:2.2rem;box-shadow:0 12px 34px rgba(32,44,66,.08);font-family:Arial,sans-serif;color:#111}.act-header{display:flex;justify-content:space-between;align-items:flex-start}.act-header img{width:66px;height:70px;object-fit:contain}.act-header div{display:flex;flex-direction:column;font-size:.82rem}.act-header small{margin-top:.3rem}.act-preview h2{text-align:center;text-decoration:underline;font-size:1.1rem;font-weight:800;margin:1rem 0 1.4rem}.act-law{text-align:justify;line-height:1.4}.act-person{border:1px solid #222;margin:1.2rem 0}.act-person div{display:grid;grid-template-columns:190px 1fr;border-bottom:1px solid #222}.act-person div:last-child{border-bottom:0}.act-person dt,.act-person dd{padding:.38rem .55rem;margin:0}.act-person dt{font-weight:800}.act-table{width:100%;border-collapse:collapse}.act-table th,.act-table td{border:2px solid #1e4c79;padding:.55rem;text-align:center}.act-table th{background:#eef5fb;color:#173b5d;font-size:.8rem}.act-table td:first-child{text-align:left}.act-table td:last-child{min-width:165px;height:78px;font-size:.72rem;font-weight:700}.act-table td i{display:block;border-top:1px solid #222;margin:1.3rem .5rem 0;padding-top:.2rem;font-style:normal;font-weight:400}.act-commitment{font-size:.82rem;margin:1.2rem 0 0}.act-notes{font-size:.78rem;color:#4d596e;margin-top:.7rem}
.filters-grid{grid-template-columns:minmax(190px,1.3fr) minmax(125px,.7fr) repeat(2,minmax(125px,.7fr)) auto;gap:.6rem}.filter-actions{gap:.35rem}.filter-actions .btn{padding-left:.7rem;padding-right:.7rem}
@media(max-width:1199px){.filters-grid{grid-template-columns:repeat(2,1fr)}.filter-search{grid-column:1/-1}.catalog-toolbar{grid-template-columns:1fr 1fr auto}.delivery-line{grid-template-columns:30px 1fr 95px}.delivery-line>div:nth-of-type(3){grid-column:2/4}.remove-line{grid-column:4;grid-row:1/3}}
@media(max-width:767px){.epp-hero{align-items:flex-start;flex-direction:column;padding:1.4rem}.epp-hero h1{font-size:1.65rem}.panel-header{align-items:flex-start;flex-direction:column}.filters-grid,.catalog-toolbar{grid-template-columns:1fr}.filter-search{grid-column:auto}.filter-actions{width:100%}.filter-actions .btn{flex:1}.epp-tabs{display:flex}.epp-tabs button{flex:1}.form-section{grid-template-columns:1fr}.delivery-line{grid-template-columns:30px 1fr}.delivery-line>div{grid-column:2}.remove-line{grid-column:2;grid-row:auto;width:42px}.delivery-footer{align-items:flex-start;flex-direction:column}.delivery-footer>div:last-child{width:100%;flex-wrap:wrap}.delivery-footer .btn{flex:1}.act-preview{padding:1rem;min-width:680px}.import-drop{align-items:flex-start;flex-wrap:wrap}}
</style>
