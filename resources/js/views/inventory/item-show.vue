<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";
import { getPdfMake } from "../../utils/pdfmake";

export default {
  components: { Layout, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      item: null,
      catalogs: null,

      showMoveModal: false,
      moveForm: {
        to_dependency_id: null,
        to_user_id: null,
        movement_type: "Cambio de dependencia",
        movement_date: null,
        reason: "",
        observations: "",
      },

      showStockModal: false,
      stockForm: {
        movement_type: "in",
        quantity: null,
        reason: "",
      },

      uploadingPhoto: false,
      uploadingDoc: false,
      newPhotoFile: null,
      newDocFile: null,
      newDocType: "Otro",
      newDocObs: "",

      photoError: null,
      docError: null,
    };
  },
  computed: {
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (e) {
        return [];
      }
    },
    canExport() {
      return this.permissions.includes("exportar_inventario");
    },
    itemId() {
      return this.$route.params.id;
    },
    dependencyOptions() {
      return [{ value: null, label: "Sin dependencia" }].concat(
        (this.catalogs?.dependencies || []).map((d) => ({
          value: d.id,
          label: `${d.code} - ${d.name}`,
        }))
      );
    },
    userOptions() {
      return [{ value: null, label: "Sin responsable" }].concat(
        (this.catalogs?.users || []).map((u) => ({
          value: u.id,
          label: `${u.name} (${u.email})`,
        }))
      );
    },
    docTypes() {
      return [
        "Factura",
        "Boleta",
        "Cotización",
        "Guía de despacho",
        "Garantía",
        "Manual técnico",
        "Informe técnico",
        "Acta de entrega",
        "Otro",
      ];
    },
    isConsumable() {
      return this.item?.item_type === "consumable";
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
        const [catResp, itemResp] = await Promise.all([
          axios.get("/api/inventory/items/catalogs"),
          axios.get(`/api/inventory/items/${this.itemId}`),
        ]);
        this.catalogs = catResp.data;
        this.item = itemResp.data.data;

        this.moveForm.to_dependency_id = this.item.dependency_id ?? null;
        this.moveForm.to_user_id = this.item.responsible_user_id ?? null;
      } catch (error) {
        this.error =
          error?.response?.data?.message || error?.message || "Error desconocido";
      } finally {
        this.loading = false;
      }
    },

    openMove() {
      this.moveForm = {
        to_dependency_id: this.item.dependency_id ?? null,
        to_user_id: this.item.responsible_user_id ?? null,
        movement_type: "Cambio de dependencia",
        movement_date: null,
        reason: "",
        observations: "",
      };
      this.showMoveModal = true;
    },
    async saveMove() {
      this.saving = true;
      this.error = null;
      try {
        await axios.post(`/api/inventory/items/${this.item.id}/move`, {
          ...this.moveForm,
        });
        this.showMoveModal = false;
        await this.load();
      } catch (error) {
        this.error =
          error?.response?.data?.message || error?.message || "Error desconocido";
      } finally {
        this.saving = false;
      }
    },

    openStock() {
      this.stockForm = { movement_type: "in", quantity: null, reason: "" };
      this.showStockModal = true;
    },
    async saveStock() {
      this.saving = true;
      this.error = null;
      try {
        await axios.post(`/api/inventory/items/${this.item.id}/stock`, {
          ...this.stockForm,
        });
        this.showStockModal = false;
        await this.load();
      } catch (error) {
        this.error =
          error?.response?.data?.message || error?.message || "Error desconocido";
      } finally {
        this.saving = false;
      }
    },

    onNewPhoto(e) {
      this.newPhotoFile = e?.target?.files?.[0] || null;
    },
    async uploadPhoto(isMain = false) {
      this.photoError = null;
      if (!this.newPhotoFile) {
        this.photoError = "Selecciona una imagen.";
        return;
      }
      this.uploadingPhoto = true;
      try {
        const fd = new FormData();
        fd.append("photo", this.newPhotoFile);
        fd.append("is_main", isMain ? 1 : 0);
        await axios.post(`/api/inventory/items/${this.item.id}/photos`, fd);
        this.newPhotoFile = null;
        await this.load();
      } catch (error) {
        this.photoError =
          error?.response?.data?.message || error?.message || "Error subiendo foto";
      } finally {
        this.uploadingPhoto = false;
      }
    },
    async setMainPhoto(photo) {
      await axios.put(`/api/inventory/photos/${photo.id}/main`);
      await this.load();
    },
    async deletePhoto(photo) {
      if (!confirm("Eliminar esta foto?")) return;
      await axios.delete(`/api/inventory/photos/${photo.id}`);
      await this.load();
    },

    onNewDoc(e) {
      this.newDocFile = e?.target?.files?.[0] || null;
    },
    async uploadDoc() {
      this.docError = null;
      if (!this.newDocFile) {
        this.docError = "Selecciona un archivo.";
        return;
      }
      this.uploadingDoc = true;
      try {
        const fd = new FormData();
        fd.append("document", this.newDocFile);
        fd.append("document_type", this.newDocType);
        if (this.newDocObs) fd.append("observations", this.newDocObs);
        await axios.post(`/api/inventory/items/${this.item.id}/documents`, fd);
        this.newDocFile = null;
        this.newDocObs = "";
        this.newDocType = "Otro";
        await this.load();
      } catch (error) {
        this.docError =
          error?.response?.data?.message || error?.message || "Error subiendo documento";
      } finally {
        this.uploadingDoc = false;
      }
    },
    async deleteDoc(doc) {
      if (!confirm("Eliminar este documento?")) return;
      await axios.delete(`/api/inventory/documents/${doc.id}`);
      await this.load();
    },

    async toDataUrl(url) {
      const response = await fetch(url, { credentials: "same-origin" });
      const blob = await response.blob();
      return await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    },

    async exportPdf() {
      if (!this.item) return;
      this.saving = true;
      this.error = null;
      try {
        const pdfMake = getPdfMake();

        let mainImage = null;
        if (this.item.image_url) {
          try {
            mainImage = await this.toDataUrl(this.item.image_url);
          } catch (e) {
            mainImage = null;
          }
        }

        const normalize = (value) =>
          value === null || value === undefined || value === "" ? "-" : String(value);
        const dependencyLabel = this.item.dependency
          ? `${this.item.dependency.code} - ${this.item.dependency.name}`
          : "-";
        const stockSummary = this.isConsumable
          ? `${this.item.stock_quantity ?? 0} ${this.item.unit_of_measure || ""}`.trim()
          : "-";
        const sectionTable = (rows) => ({
          table: {
            widths: ["34%", "*"],
            body: rows.map(([label, value]) => [
              { text: normalize(label), style: "pdfLabel" },
              { text: normalize(value), style: "pdfValue" },
            ]),
          },
          layout: {
            hLineWidth: (i, node) => (i === node.table.body.length ? 0 : 0.7),
            vLineWidth: () => 0,
            hLineColor: () => "#dde8f4",
            paddingLeft: () => 8,
            paddingRight: () => 8,
            paddingTop: () => 6,
            paddingBottom: () => 6,
          },
        });
        const section = (title, rows) => [
          { text: title, style: "pdfSectionTitle" },
          sectionTable(rows),
        ];
        const summaryBox = (label, value, color = "#3152c9", fillColor = "#eef4ff") => ({
          table: {
            widths: ["*"],
            body: [
              [
                {
                  stack: [
                    { text: label, style: "pdfSummaryLabel" },
                    { text: normalize(value), color, bold: true, fontSize: 10 },
                  ],
                  fillColor,
                  border: [false, false, false, false],
                  margin: [8, 6, 8, 6],
                },
              ],
            ],
          },
          layout: "noBorders",
        });
        const paletteFor = (name) => {
          const palettes = {
            active: { color: "#047857", fill: "#ecfdf5" },
            stored: { color: "#3152c9", fill: "#eef4ff" },
            pending: { color: "#b45309", fill: "#fffbeb" },
            inactive: { color: "#475569", fill: "#f8fafc" },
            neutral: { color: "#475569", fill: "#f8fafc" },
            good: { color: "#047857", fill: "#ecfdf5" },
            warning: { color: "#b45309", fill: "#fffbeb" },
            danger: { color: "#b91c1c", fill: "#fef2f2" },
            asset: { color: "#3152c9", fill: "#eef4ff" },
            consumable: { color: "#047857", fill: "#ecfdf5" },
          };

          return palettes[name] || palettes.neutral;
        };
        const typePalette = paletteFor(this.typeClass(this.item.item_type));
        const statusPalette = paletteFor(this.statusClass(this.item.status));
        const conditionPalette = paletteFor(this.conditionClass(this.item.condition));

        const identificationRows = [
          ["Código", this.item.code],
          ["Nombre", this.item.name],
          ["Categoría", this.item.category?.name || "-"],
          ["Subcategoría", this.item.subcategory?.name || "-"],
          ["Tipo", this.typeLabel(this.item.item_type)],
          ["Estado", this.item.status || "-"],
          ["Condición", this.item.condition || "-"],
        ];
        const locationRows = [
          ["Dependencia", dependencyLabel],
          ["Responsable", this.item.responsible_user?.name || "-"],
          ["Proveedor", this.item.supplier?.name || "-"],
          ["Marca", this.item.brand || "-"],
          ["Modelo", this.item.model || "-"],
          ["N° serie", this.item.serial_number || "-"],
        ];
        const purchaseRows = [
          ["Fecha compra", this.formatDate(this.item.purchase_date)],
          ["Valor compra", this.formatMoney(this.item.purchase_value)],
          ["Garantía", this.item.has_warranty ? "Sí" : "No"],
          [
            "Duración",
            this.item.has_warranty && this.item.warranty_months
              ? `${this.item.warranty_months} meses`
              : "-",
          ],
          [
            "Vencimiento",
            this.item.has_warranty
              ? this.formatDate(this.item.warranty_expires_at)
              : "-",
          ],
        ];

        if (this.isConsumable) {
          purchaseRows.push(["Stock actual", stockSummary]);
          purchaseRows.push(["Stock mínimo", this.item.minimum_stock ?? "-"]);
        }

        const movementsBody = [
          ["Fecha", "Movimiento", "Desde", "Hacia"].map((text) => ({
            text,
            style: "pdfTableHead",
          })),
          ...(this.item.movements || []).slice(0, 50).map((movement) => [
            this.formatDate(movement.movement_date),
            movement.movement_type || "-",
            movement.from_dependency?.code || "-",
            movement.to_dependency?.code || "-",
          ]),
        ];

        const docDefinition = {
          pageSize: "A4",
          pageMargins: [36, 40, 36, 42],
          footer: (currentPage, pageCount) => ({
            text: `Página ${currentPage} de ${pageCount}`,
            alignment: "right",
            margin: [0, 0, 36, 0],
            style: "pdfFooter",
          }),
          content: [
            {
              table: {
                widths: ["*"],
                body: [
                  [
                    {
                      stack: [
                        { text: "Ficha de bien", style: "pdfTitle" },
                        { text: this.item.name || "-", style: "pdfSubtitle" },
                        {
                          text: `${this.item.code || "-"} · ${dependencyLabel}`,
                          style: "pdfMuted",
                          margin: [0, 2, 0, 10],
                        },
                        {
                          columns: [
                            summaryBox(
                              "Tipo",
                              this.typeLabel(this.item.item_type),
                              typePalette.color,
                              typePalette.fill
                            ),
                            summaryBox(
                              "Estado",
                              this.item.status || "-",
                              statusPalette.color,
                              statusPalette.fill
                            ),
                            summaryBox(
                              "Condición",
                              this.item.condition || "-",
                              conditionPalette.color,
                              conditionPalette.fill
                            ),
                            summaryBox(
                              "Garantía",
                              this.item.has_warranty ? "Vigente" : "Sin garantía",
                              this.item.has_warranty ? "#047857" : "#475569",
                              this.item.has_warranty ? "#ecfdf5" : "#f8fafc"
                            ),
                          ],
                          columnGap: 8,
                        },
                      ],
                      fillColor: "#f1f7ff",
                      border: [false, false, false, false],
                      margin: [16, 14, 16, 14],
                    },
                  ],
                ],
              },
              layout: "noBorders",
            },
            {
              columns: [
                {
                  width: "58%",
                  stack: [
                    ...section("Identificación", identificationRows),
                    { text: " ", margin: [0, 4] },
                    ...section("Ubicación y responsables", locationRows),
                  ],
                },
                {
                  width: "42%",
                  stack: [
                    mainImage
                      ? {
                          image: mainImage,
                          fit: [205, 150],
                          alignment: "center",
                          margin: [0, 0, 0, 10],
                        }
                      : {
                          text: "Sin foto principal",
                          style: "pdfPhotoPlaceholder",
                          margin: [0, 0, 0, 10],
                        },
                    ...section("Compra y garantía", purchaseRows),
                  ],
                },
              ],
              columnGap: 18,
              margin: [0, 16, 0, 0],
            },
            { text: "Descripción", style: "pdfSectionTitle", margin: [0, 16, 0, 5] },
            {
              text: this.item.description || "Sin descripción registrada.",
              style: "pdfDescription",
            },
            { text: "Movimientos recientes", style: "pdfSectionTitle", margin: [0, 16, 0, 5] },
            (this.item.movements || []).length > 0
              ? {
                  table: {
                    headerRows: 1,
                    widths: [70, 120, "*", "*"],
                    body: movementsBody,
                  },
                  layout: {
                    hLineWidth: (i) => (i === 0 ? 0 : 0.7),
                    vLineWidth: () => 0,
                    hLineColor: () => "#dde8f4",
                    paddingLeft: () => 8,
                    paddingRight: () => 8,
                    paddingTop: () => 6,
                    paddingBottom: () => 6,
                  },
                }
              : { text: "Sin movimientos registrados.", style: "pdfMuted" },
          ],
          styles: {
            pdfTitle: { fontSize: 20, bold: true, color: "#1f2937" },
            pdfSubtitle: { fontSize: 13, bold: true, color: "#334155" },
            pdfSectionTitle: {
              fontSize: 11,
              bold: true,
              color: "#334155",
              margin: [0, 0, 0, 5],
            },
            pdfLabel: {
              bold: true,
              color: "#64748b",
              fillColor: "#f8fbff",
              fontSize: 8.5,
            },
            pdfValue: { color: "#334155", fontSize: 9 },
            pdfTableHead: {
              bold: true,
              color: "#475569",
              fillColor: "#f1f7ff",
              fontSize: 8.5,
            },
            pdfMuted: { color: "#64748b", fontSize: 8.5 },
            pdfSummaryLabel: {
              color: "#64748b",
              fontSize: 7,
              bold: true,
              margin: [0, 0, 0, 2],
            },
            pdfDescription: {
              color: "#334155",
              fontSize: 9,
              fillColor: "#fbfdff",
              margin: [0, 0, 0, 0],
            },
            pdfPhotoPlaceholder: {
              alignment: "center",
              color: "#94a3b8",
              fillColor: "#f8fafc",
              margin: [0, 44, 0, 44],
            },
            pdfFooter: { color: "#94a3b8", fontSize: 8 },
          },
          defaultStyle: { fontSize: 9, color: "#334155" },
        };

        pdfMake.createPdf(docDefinition).download(`${this.item.code}.pdf`);
      } catch (error) {
        this.error =
          error?.response?.data?.message || error?.message || "Error generando PDF";
      } finally {
        this.saving = false;
      }
    },

    typeLabel(value) {
      const labels = {
        asset: "Activo fijo",
        consumable: "Consumible",
      };

      return labels[value] || value || "-";
    },
    typeClass(value) {
      return value === "consumable" ? "consumable" : "asset";
    },
    statusClass(value) {
      const status = String(value || "").toLowerCase();

      if (status.includes("baja") || status.includes("inactivo")) return "inactive";
      if (status.includes("pendiente") || status.includes("revisión") || status.includes("revision")) return "pending";
      if (status.includes("bodega") || status.includes("almac")) return "stored";
      if (status.includes("uso") || status.includes("activo")) return "active";

      return "neutral";
    },
    conditionClass(value) {
      const condition = String(value || "").toLowerCase();

      if (condition.includes("nuevo") || condition.includes("bueno")) return "good";
      if (condition.includes("regular")) return "warning";
      if (condition.includes("crítico") || condition.includes("critico") || condition.includes("malo")) return "danger";

      return "neutral";
    },
    formatMoney(value) {
      if (value === null || value === undefined || value === "") return "-";

      const amount = Number(value);
      if (!Number.isFinite(amount)) return value;

      return new Intl.NumberFormat("es-CL", {
        style: "currency",
        currency: "CLP",
        maximumFractionDigits: 0,
      }).format(amount);
    },
    formatDate(value) {
      if (!value) return "-";

      const rawValue = String(value);
      const normalizedDate = rawValue.slice(0, 10);
      const match = normalizedDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);

      if (match) {
        const [, year, month, day] = match;
        return `${day}-${month}-${year}`;
      }

      return rawValue;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="inventory-detail-page">
      <div class="inventory-detail-header">
        <div>
          <div class="inventory-detail-eyebrow">Inventario</div>
          <h4 class="inventory-detail-title">Ficha del bien</h4>
          <div class="inventory-detail-subtitle" v-if="item">
            <span>{{ item.code }}</span>
            <span>{{ item.name }}</span>
          </div>
        </div>
        <div class="inventory-detail-actions">
          <router-link class="btn btn-outline-secondary" to="/inventory/items">
            Volver
          </router-link>
          <BButton
            variant="outline-primary"
            v-if="item && canExport"
            :disabled="saving"
            @click="exportPdf"
          >
            PDF
          </BButton>
          <router-link v-if="item" class="btn btn-outline-secondary" to="/inventory/labels">
            Etiquetas
          </router-link>
          <BButton variant="warning" v-if="item" @click="openMove">Mover</BButton>
          <BButton variant="primary" v-if="item && isConsumable" @click="openStock">
            Stock
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <BCard v-if="loading" class="inventory-section-card mb-3">Cargando...</BCard>

      <div v-if="item" class="inventory-detail-grid">
        <section class="inventory-panel inventory-photo-panel">
          <div class="inventory-panel-heading">
            <div>
              <div class="inventory-panel-kicker">Imagen principal</div>
              <h5>Foto del bien</h5>
            </div>
            <span class="inventory-detail-chip inventory-detail-chip--code">
              {{ item.code }}
            </span>
          </div>

          <div class="inventory-main-photo">
            <img v-if="item.image_url" :src="item.image_url" alt="foto" />
            <div v-else class="inventory-photo-placeholder">
              Sin foto principal
            </div>
          </div>

          <div class="inventory-upload-block">
            <label class="inventory-field-label">Subir o tomar foto</label>
            <input
              class="form-control inventory-file-control"
              type="file"
              accept="image/*"
              capture="environment"
              @change="onNewPhoto"
            />
            <div v-if="photoError" class="text-danger small mt-1">
              {{ photoError }}
            </div>
            <div class="inventory-inline-actions mt-2">
              <BButton
                variant="outline-secondary"
                size="sm"
                :disabled="uploadingPhoto"
                @click="uploadPhoto(false)"
              >
                {{ uploadingPhoto ? "Subiendo..." : "Subir" }}
              </BButton>
              <BButton
                variant="primary"
                size="sm"
                :disabled="uploadingPhoto"
                @click="uploadPhoto(true)"
              >
                {{ uploadingPhoto ? "Subiendo..." : "Subir como principal" }}
              </BButton>
            </div>
          </div>
        </section>

        <section class="inventory-panel inventory-info-panel">
          <div class="inventory-panel-heading">
            <div>
              <div class="inventory-panel-kicker">Información general</div>
              <h5>{{ item.name }}</h5>
            </div>
            <div class="inventory-chip-row">
              <span
                class="inventory-detail-chip"
                :class="`inventory-detail-chip--type-${typeClass(item.item_type)}`"
              >
                {{ typeLabel(item.item_type) }}
              </span>
              <span
                class="inventory-detail-chip"
                :class="`inventory-detail-chip--status-${statusClass(item.status)}`"
              >
                {{ item.status || "-" }}
              </span>
              <span
                class="inventory-detail-chip"
                :class="`inventory-detail-chip--condition-${conditionClass(item.condition)}`"
              >
                {{ item.condition || "-" }}
              </span>
            </div>
          </div>

          <div class="inventory-info-grid">
            <div class="inventory-info-item">
              <div class="inventory-info-label">Categoría</div>
              <div class="inventory-info-value">{{ item.category?.name || "-" }}</div>
            </div>
            <div class="inventory-info-item">
              <div class="inventory-info-label">Subcategoría</div>
              <div class="inventory-info-value">{{ item.subcategory?.name || "-" }}</div>
            </div>
            <div class="inventory-info-item">
              <div class="inventory-info-label">Dependencia</div>
              <div class="inventory-info-value">
                {{
                  item.dependency
                    ? `${item.dependency.code} - ${item.dependency.name}`
                    : "-"
                }}
              </div>
            </div>
            <div class="inventory-info-item">
              <div class="inventory-info-label">Responsable</div>
              <div class="inventory-info-value">
                {{ item.responsible_user?.name || "-" }}
              </div>
            </div>
            <div class="inventory-info-item">
              <div class="inventory-info-label">Proveedor</div>
              <div class="inventory-info-value">{{ item.supplier?.name || "-" }}</div>
            </div>
            <div class="inventory-info-item">
              <div class="inventory-info-label">Marca</div>
              <div class="inventory-info-value">{{ item.brand || "-" }}</div>
            </div>
            <div class="inventory-info-item">
              <div class="inventory-info-label">Modelo</div>
              <div class="inventory-info-value">{{ item.model || "-" }}</div>
            </div>
            <div class="inventory-info-item">
              <div class="inventory-info-label">N° serie</div>
              <div class="inventory-info-value">{{ item.serial_number || "-" }}</div>
            </div>
            <div class="inventory-info-item">
              <div class="inventory-info-label">Fecha compra</div>
              <div class="inventory-info-value">{{ formatDate(item.purchase_date) }}</div>
            </div>
            <div class="inventory-info-item">
              <div class="inventory-info-label">Valor compra</div>
              <div class="inventory-info-value">{{ formatMoney(item.purchase_value) }}</div>
            </div>
            <div class="inventory-info-item" v-if="isConsumable">
              <div class="inventory-info-label">Stock</div>
              <div class="inventory-info-value">
                {{ item.stock_quantity ?? 0 }} {{ item.unit_of_measure || "" }}
              </div>
            </div>
            <div class="inventory-info-item" v-if="isConsumable">
              <div class="inventory-info-label">Stock mínimo</div>
              <div class="inventory-info-value">{{ item.minimum_stock ?? "-" }}</div>
            </div>
          </div>

          <div
            class="inventory-warranty-summary"
            :class="{ 'inventory-warranty-summary--active': item.has_warranty }"
          >
            <div>
              <div class="inventory-info-label">Garantía</div>
              <div class="inventory-warranty-title">
                {{ item.has_warranty ? "Con garantía" : "Sin garantía" }}
              </div>
            </div>
            <div class="inventory-warranty-meta" v-if="item.has_warranty">
              <span>{{ item.warranty_months ? `${item.warranty_months} meses` : "-" }}</span>
              <span>Vence {{ formatDate(item.warranty_expires_at) }}</span>
            </div>
          </div>

          <div class="inventory-description">
            <div class="inventory-info-label">Descripción</div>
            <p>{{ item.description || "Sin descripción registrada." }}</p>
          </div>
        </section>

        <BCard class="inventory-section-card inventory-grid-full">
          <div class="inventory-section-header">
            <div>
              <div class="inventory-panel-kicker">Registro visual</div>
              <h5>Fotos</h5>
            </div>
            <span class="inventory-count-pill">{{ (item.photos || []).length }}</span>
          </div>
          <div v-if="(item.photos || []).length === 0" class="inventory-empty-state">
            Sin fotos registradas.
          </div>
          <div class="inventory-photo-grid" v-else>
            <div class="inventory-photo-tile" v-for="p in item.photos" :key="p.id">
              <img :src="p.image_url" alt="foto" />
              <div class="inventory-photo-tile-footer">
                <span>{{ p.is_main ? "Principal" : "Secundaria" }}</span>
                <div class="inventory-inline-actions">
                  <BButton size="sm" variant="outline-primary" @click="setMainPhoto(p)">
                    Principal
                  </BButton>
                  <BButton size="sm" variant="outline-danger" @click="deletePhoto(p)">
                    Eliminar
                  </BButton>
                </div>
              </div>
            </div>
          </div>
        </BCard>

        <BCard class="inventory-section-card inventory-grid-full">
          <div class="inventory-section-header">
            <div>
              <div class="inventory-panel-kicker">Respaldos</div>
              <h5>Documentos</h5>
            </div>
            <span class="inventory-count-pill">{{ (item.documents || []).length }}</span>
          </div>

          <div class="row g-3 inventory-document-form">
            <div class="col-md-4">
              <label class="inventory-field-label">Archivo</label>
              <input class="form-control" type="file" @change="onNewDoc" />
            </div>
            <div class="col-md-4">
              <label class="inventory-field-label">Tipo</label>
              <BFormSelect v-model="newDocType" :options="docTypes" />
            </div>
            <div class="col-md-4">
              <label class="inventory-field-label">Observación</label>
              <BFormInput v-model="newDocObs" />
            </div>
            <div class="col-12">
              <div v-if="docError" class="text-danger small mb-2">
                {{ docError }}
              </div>
              <BButton variant="primary" size="sm" :disabled="uploadingDoc" @click="uploadDoc">
                {{ uploadingDoc ? "Subiendo..." : "Subir documento" }}
              </BButton>
            </div>
          </div>

          <div v-if="(item.documents || []).length === 0" class="inventory-empty-state">
            Sin documentos registrados.
          </div>
          <div class="table-responsive inventory-table-wrap" v-else>
            <BTable
              class="inventory-detail-table"
              :items="item.documents"
              :fields="[
                { key: 'document_type', label: 'Tipo', thClass: 'text-center', tdClass: 'text-center align-middle' },
                { key: 'original_name', label: 'Archivo', thClass: 'text-center', tdClass: 'align-middle' },
                { key: 'actions', label: 'Acciones', thClass: 'text-center', tdClass: 'text-center align-middle' },
              ]"
              small
            >
              <template #cell(actions)="{ item: doc }">
                <div class="inventory-inline-actions justify-content-center">
                  <a
                    class="btn btn-sm btn-outline-secondary"
                    :href="`/storage/${doc.file_path}`"
                    target="_blank"
                    rel="noreferrer"
                  >
                    Ver
                  </a>
                  <BButton size="sm" variant="outline-danger" @click="deleteDoc(doc)">
                    Eliminar
                  </BButton>
                </div>
              </template>
            </BTable>
          </div>
        </BCard>

        <BCard class="inventory-section-card inventory-grid-full">
          <div class="inventory-section-header">
            <div>
              <div class="inventory-panel-kicker">Historial</div>
              <h5>Movimientos</h5>
            </div>
            <span class="inventory-count-pill">{{ (item.movements || []).length }}</span>
          </div>
          <div v-if="(item.movements || []).length === 0" class="inventory-empty-state">
            Sin movimientos registrados.
          </div>
          <div class="table-responsive inventory-table-wrap" v-else>
            <BTable
              class="inventory-detail-table"
              :items="item.movements"
              :fields="[
                { key: 'movement_date', label: 'Fecha', thClass: 'text-center', tdClass: 'text-center align-middle' },
                { key: 'movement_type', label: 'Tipo', thClass: 'text-center', tdClass: 'text-center align-middle' },
                { key: 'fromDependency', label: 'Desde', thClass: 'text-center', tdClass: 'text-center align-middle' },
                { key: 'toDependency', label: 'Hacia', thClass: 'text-center', tdClass: 'text-center align-middle' },
                { key: 'fromUser', label: 'Resp. anterior', thClass: 'text-center', tdClass: 'align-middle' },
                { key: 'toUser', label: 'Resp. nuevo', thClass: 'text-center', tdClass: 'align-middle' },
              ]"
              small
            >
              <template #cell(movement_date)="{ item: m }">
                <span class="inventory-date-pill">{{ formatDate(m.movement_date) }}</span>
              </template>
              <template #cell(fromDependency)="{ item: m }">
                {{ m.from_dependency?.code || "-" }}
              </template>
              <template #cell(toDependency)="{ item: m }">
                {{ m.to_dependency?.code || "-" }}
              </template>
              <template #cell(fromUser)="{ item: m }">
                {{ m.from_user?.name || "-" }}
              </template>
              <template #cell(toUser)="{ item: m }">
                {{ m.to_user?.name || "-" }}
              </template>
            </BTable>
          </div>
        </BCard>

        <BCard class="inventory-section-card inventory-grid-full" v-if="isConsumable">
          <div class="inventory-section-header">
            <div>
              <div class="inventory-panel-kicker">Consumo</div>
              <h5>Movimientos de stock</h5>
            </div>
            <span class="inventory-count-pill">{{ (item.stock_movements || []).length }}</span>
          </div>
          <div v-if="(item.stock_movements || []).length === 0" class="inventory-empty-state">
            Sin movimientos de stock.
          </div>
          <div class="table-responsive inventory-table-wrap" v-else>
            <BTable
              class="inventory-detail-table"
              :items="item.stock_movements"
              :fields="[
                { key: 'id', label: '#', thClass: 'text-center', tdClass: 'text-center align-middle' },
                { key: 'movement_type', label: 'Tipo', thClass: 'text-center', tdClass: 'text-center align-middle' },
                { key: 'quantity', label: 'Cantidad', thClass: 'text-center', tdClass: 'text-center align-middle' },
                { key: 'previous_stock', label: 'Anterior', thClass: 'text-center', tdClass: 'text-center align-middle' },
                { key: 'new_stock', label: 'Nuevo', thClass: 'text-center', tdClass: 'text-center align-middle' },
              ]"
              small
            />
          </div>
        </BCard>
      </div>
    </div>

    <BModal v-model="showMoveModal" title="Mover bien" size="lg" hide-footer>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Dependencia destino</label>
          <Multiselect v-model="moveForm.to_dependency_id" :options="dependencyOptions" :searchable="true" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Responsable destino</label>
          <Multiselect v-model="moveForm.to_user_id" :options="userOptions" :searchable="true" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Tipo de movimiento</label>
          <BFormInput v-model="moveForm.movement_type" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha</label>
          <BFormInput v-model="moveForm.movement_date" type="date" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Motivo</label>
          <BFormInput v-model="moveForm.reason" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="moveForm.observations" rows="2" />
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showMoveModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="saveMove">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>

    <BModal v-model="showStockModal" title="Movimiento de stock" size="lg" hide-footer>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <BFormSelect
            v-model="stockForm.movement_type"
            :options="[
              { value: 'in', text: 'Entrada' },
              { value: 'out', text: 'Salida' },
              { value: 'adjust', text: 'Ajuste (set)' },
            ]"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label">Cantidad</label>
          <BFormInput v-model="stockForm.quantity" type="number" min="0.01" step="0.01" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Motivo</label>
          <BFormInput v-model="stockForm.reason" />
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showStockModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="saveStock">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.inventory-detail-page {
  color: #3f4754;
}

.inventory-detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.inventory-detail-eyebrow,
.inventory-panel-kicker,
.inventory-info-label,
.inventory-field-label {
  color: #74788d;
  font-size: 0.74rem;
  font-weight: 650;
  line-height: 1.2;
  letter-spacing: 0;
}

.inventory-detail-title {
  margin: 0;
  color: #3f4754;
  font-size: 1.45rem;
  font-weight: 650;
  line-height: 1.2;
}

.inventory-detail-subtitle {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.65rem;
  margin-top: 0.35rem;
  color: #667085;
  font-size: 0.9rem;
  font-weight: 500;
}

.inventory-detail-actions,
.inventory-inline-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.45rem;
}

.inventory-detail-actions .btn,
.inventory-detail-actions :deep(.btn),
.inventory-inline-actions .btn,
.inventory-inline-actions :deep(.btn) {
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 650;
  line-height: 1;
  white-space: nowrap;
}

.inventory-detail-actions .btn,
.inventory-detail-actions :deep(.btn) {
  min-height: 2.25rem;
  padding: 0.52rem 0.9rem;
}

.inventory-inline-actions .btn,
.inventory-inline-actions :deep(.btn) {
  min-height: 1.95rem;
  padding: 0.42rem 0.68rem;
}

.inventory-detail-actions :deep(.btn-warning) {
  color: #ffffff;
  background-color: #f6b540;
  border-color: #f6b540;
}

.inventory-detail-grid {
  display: grid;
  grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
  gap: 1rem;
}

.inventory-grid-full {
  grid-column: 1 / -1;
}

.inventory-panel,
.inventory-section-card {
  border: 1px solid #e1ebfb;
  border-radius: 0.85rem;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: 0 0.75rem 2rem rgba(31, 41, 55, 0.05);
}

.inventory-panel {
  padding: 1rem;
}

.inventory-section-card {
  overflow: hidden;
}

.inventory-section-card :deep(.card-body) {
  padding: 1rem;
}

.inventory-panel-heading,
.inventory-section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.9rem;
}

.inventory-panel-heading h5,
.inventory-section-header h5 {
  margin: 0.15rem 0 0;
  color: #3f4754;
  font-size: 1rem;
  font-weight: 650;
  line-height: 1.25;
}

.inventory-main-photo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  aspect-ratio: 4 / 3;
  overflow: hidden;
  border: 1px solid #dbe7ff;
  border-radius: 0.75rem;
  background: #f8fbff;
}

.inventory-main-photo img,
.inventory-photo-tile img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.inventory-photo-placeholder,
.inventory-empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 6rem;
  padding: 1rem;
  border: 1px dashed #cbd5e1;
  border-radius: 0.75rem;
  color: #7b8194;
  background: #f8fafc;
  font-size: 0.9rem;
  font-weight: 500;
  text-align: center;
}

.inventory-upload-block {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e6eef8;
}

.inventory-file-control {
  margin-top: 0.45rem;
}

.inventory-detail-page :deep(.form-control),
.inventory-detail-page :deep(.form-select) {
  min-height: 2.45rem;
  border-color: #dfe8fb;
  border-radius: 0.72rem;
  color: #4b5563;
  font-size: 0.88rem;
}

.inventory-chip-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.4rem;
  max-width: 55%;
}

.inventory-detail-chip,
.inventory-count-pill,
.inventory-date-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 1.75rem;
  padding: 0.32rem 0.7rem;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 650;
  line-height: 1.12;
  text-align: center;
  white-space: normal;
}

.inventory-detail-chip--code,
.inventory-date-pill {
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
}

.inventory-detail-chip--type-asset {
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
}

.inventory-detail-chip--type-consumable {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.inventory-detail-chip--status-active,
.inventory-detail-chip--condition-good {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.inventory-detail-chip--status-pending,
.inventory-detail-chip--condition-warning {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.inventory-detail-chip--status-stored {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.inventory-detail-chip--status-inactive,
.inventory-detail-chip--status-neutral,
.inventory-detail-chip--condition-neutral {
  color: #475569;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.inventory-detail-chip--condition-danger {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

.inventory-count-pill {
  min-width: 2rem;
  color: #5f76e8;
  background: #eef4ff;
  border-color: #d7e2ff;
}

.inventory-info-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}

.inventory-info-item {
  min-height: 4.15rem;
  padding: 0.72rem 0.78rem;
  border: 1px solid #e6eef8;
  border-radius: 0.75rem;
  background: rgba(248, 251, 255, 0.72);
}

.inventory-info-value {
  margin-top: 0.25rem;
  color: #3f4754;
  font-size: 0.9rem;
  font-weight: 580;
  line-height: 1.28;
  overflow-wrap: anywhere;
}

.inventory-warranty-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-top: 0.9rem;
  padding: 0.82rem 0.9rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  background: #f8fafc;
}

.inventory-warranty-summary--active {
  border-color: #a7f3d0;
  background: #ecfdf5;
}

.inventory-warranty-title {
  margin-top: 0.2rem;
  color: #334155;
  font-size: 0.96rem;
  font-weight: 650;
}

.inventory-warranty-summary--active .inventory-warranty-title {
  color: #047857;
}

.inventory-warranty-meta {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.4rem;
  color: #475569;
  font-size: 0.82rem;
  font-weight: 600;
}

.inventory-warranty-meta span {
  padding: 0.28rem 0.55rem;
  border: 1px solid rgba(4, 120, 87, 0.2);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.78);
}

.inventory-description {
  margin-top: 0.9rem;
  padding: 0.82rem 0.9rem;
  border: 1px solid #e6eef8;
  border-radius: 0.75rem;
  background: rgba(255, 255, 255, 0.62);
}

.inventory-description p {
  margin: 0.28rem 0 0;
  color: #4b5563;
  font-size: 0.9rem;
  line-height: 1.45;
}

.inventory-photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 0.75rem;
}

.inventory-photo-tile {
  overflow: hidden;
  border: 1px solid #dfe8fb;
  border-radius: 0.75rem;
  background: #ffffff;
}

.inventory-photo-tile img {
  height: 150px;
}

.inventory-photo-tile-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  padding: 0.65rem;
  color: #74788d;
  font-size: 0.78rem;
  font-weight: 650;
}

.inventory-document-form {
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e6eef8;
}

.inventory-table-wrap {
  border: 1px solid #e6eef8;
  border-radius: 0.75rem;
  overflow: hidden;
}

:deep(.inventory-detail-table) {
  width: 100%;
  margin-bottom: 0;
}

:deep(.inventory-detail-table thead th) {
  padding: 0.72rem 0.65rem;
  color: #74788d;
  font-size: 0.74rem;
  font-weight: 650;
  line-height: 1.15;
  text-align: center !important;
  vertical-align: middle;
  letter-spacing: 0;
  background: #f8fbff;
  border-bottom: 1px solid #dfe8f7;
}

:deep(.inventory-detail-table tbody td) {
  padding: 0.72rem 0.65rem;
  color: #4b5563;
  font-size: 0.84rem;
  font-weight: 500;
  line-height: 1.25;
  vertical-align: middle;
  border-bottom: 1px solid #e6eef8;
}

:deep(.inventory-detail-table tbody tr:last-child td) {
  border-bottom: 0;
}

@media (max-width: 1199.98px) {
  .inventory-detail-grid {
    grid-template-columns: 1fr;
  }

  .inventory-chip-row {
    max-width: none;
  }
}

@media (max-width: 767.98px) {
  .inventory-detail-header,
  .inventory-panel-heading,
  .inventory-section-header,
  .inventory-warranty-summary {
    flex-direction: column;
    align-items: stretch;
  }

  .inventory-detail-actions,
  .inventory-chip-row,
  .inventory-warranty-meta {
    justify-content: flex-start;
  }

  .inventory-info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
