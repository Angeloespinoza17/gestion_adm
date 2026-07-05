<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { getPdfMake } from "../../utils/pdfmake";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      exportingPdf: false,
      error: null,
      dashboard: null,
    };
  },
  mounted() {
    this.load();
  },
  computed: {
    totals() {
      return this.dashboard?.totals || {};
    },
    totalItems() {
      return Number(this.totals.total || 0);
    },
    primaryStats() {
      return [
        {
          label: "Total bienes",
          value: this.formatNumber(this.totals.total),
          meta: "Registros inventariados",
          icon: "mdi-package-variant-closed",
          tone: "blue",
        },
        {
          label: "Activos",
          value: this.formatNumber(this.totals.active),
          meta: `${this.percentage(this.totals.active, this.totals.total)} del total`,
          icon: "mdi-check-circle-outline",
          tone: "green",
        },
        {
          label: "Activos fijos",
          value: this.formatNumber(this.totals.assets),
          meta: `${this.percentage(this.totals.assets, this.totals.total)} del total`,
          icon: "mdi-monitor-dashboard",
          tone: "indigo",
        },
        {
          label: "Consumibles",
          value: this.formatNumber(this.totals.consumables),
          meta: `${this.percentage(this.totals.consumables, this.totals.total)} del total`,
          icon: "mdi-archive-outline",
          tone: "teal",
        },
        {
          label: "Valor inventario",
          value: this.formatMoney(this.totals.total_value),
          meta: `Activos ${this.formatMoney(this.totals.active_value)}`,
          icon: "mdi-cash-multiple",
          tone: "slate",
          compact: true,
        },
        {
          label: "Stock bajo",
          value: this.formatNumber(this.dashboard?.low_stock),
          meta: "Consumibles bajo mínimo",
          icon: "mdi-alert-outline",
          tone: "amber",
        },
      ];
    },
    riskStats() {
      return [
        {
          label: "Sin foto",
          value: this.totals.without_photo,
          detail: "Bienes sin respaldo visual",
          tone: "blue",
        },
        {
          label: "Sin responsable",
          value: this.totals.without_responsible,
          detail: "Pendientes de asignación",
          tone: "amber",
        },
        {
          label: "Condición crítica",
          value: this.totals.critical_condition,
          detail: "Crítico o inutilizable",
          tone: "red",
        },
        {
          label: "En reparación",
          value: this.totals.in_repair,
          detail: "Fuera de operación normal",
          tone: "indigo",
        },
        {
          label: "Dados de baja",
          value: this.totals.retired,
          detail: "Retirados del uso",
          tone: "slate",
        },
      ];
    },
    categoryRows() {
      const rows = this.dashboard?.by_category || [];
      const max = Math.max(...rows.map((row) => Number(row.total || 0)), 1);

      return rows.map((row) => ({
        ...row,
        total: Number(row.total || 0),
        value_total: Number(row.value_total || 0),
        percent: Math.round((Number(row.total || 0) / max) * 100),
      }));
    },
    statusRows() {
      return this.breakdownRows(this.dashboard?.by_status || []);
    },
    conditionRows() {
      return this.breakdownRows(this.dashboard?.by_condition || []);
    },
    lowStockItems() {
      return this.dashboard?.low_stock_items || [];
    },
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/inventory/reports/dashboard");
        this.dashboard = response.data;
      } catch (error) {
        this.error =
          error?.response?.data?.message || error?.message || "Error desconocido";
      } finally {
        this.loading = false;
      }
    },
    breakdownRows(rows) {
      return rows.map((row) => {
        const total = Number(row.total || 0);

        return {
          ...row,
          total,
          percent: this.totalItems > 0 ? Math.round((total / this.totalItems) * 100) : 0,
        };
      });
    },
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
    },
    formatMoney(value) {
      const amount = Number(value || 0);

      return new Intl.NumberFormat("es-CL", {
        style: "currency",
        currency: "CLP",
        maximumFractionDigits: 0,
      }).format(amount);
    },
    percentage(value, total) {
      const base = Number(total || 0);

      if (base <= 0) return "0%";

      return `${Math.round((Number(value || 0) / base) * 100)}%`;
    },
    stockText(item) {
      return `${item.stock_quantity ?? 0} / ${item.minimum_stock ?? 0} ${
        item.unit_of_measure || ""
      }`.trim();
    },
    formatReportDateTime(value = new Date()) {
      const date = value instanceof Date ? value : new Date(value);
      const day = String(date.getDate()).padStart(2, "0");
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const year = date.getFullYear();
      const hours = String(date.getHours()).padStart(2, "0");
      const minutes = String(date.getMinutes()).padStart(2, "0");

      return `${day}-${month}-${year} ${hours}:${minutes}`;
    },
    rowTone(label) {
      const value = String(label || "").toLowerCase();

      if (value.includes("crítico") || value.includes("critico") || value.includes("baja") || value.includes("inutilizable")) return "red";
      if (value.includes("reparación") || value.includes("revision") || value.includes("revisión") || value.includes("regular")) return "amber";
      if (value.includes("uso") || value.includes("activo") || value.includes("bueno") || value.includes("nuevo")) return "green";
      if (value.includes("bodega") || value.includes("prestado")) return "blue";

      return "slate";
    },
    async exportPdf() {
      if (!this.dashboard || this.exportingPdf) return;

      this.exportingPdf = true;
      this.error = null;

      try {
        const pdfMake = getPdfMake();
        const normalize = (value) =>
          value === null || value === undefined || value === "" ? "-" : String(value);
        const palette = {
          blue: { color: "#1d4ed8", fill: "#eff6ff", border: "#bfdbfe" },
          green: { color: "#047857", fill: "#ecfdf5", border: "#a7f3d0" },
          indigo: { color: "#3152c9", fill: "#eef4ff", border: "#c7d7fe" },
          teal: { color: "#0f766e", fill: "#f0fdfa", border: "#99f6e4" },
          slate: { color: "#475569", fill: "#f8fafc", border: "#cbd5e1" },
          amber: { color: "#b45309", fill: "#fffbeb", border: "#fcd34d" },
          red: { color: "#b91c1c", fill: "#fef2f2", border: "#fecaca" },
        };
        const tone = (value) => palette[value] || palette.slate;
        const card = (item, valueKey = "value", detailKey = "meta") => {
          const itemTone = tone(item.tone);

          return {
            stack: [
              { text: normalize(item.label), style: "pdfMetricLabel", color: itemTone.color },
              {
                text: normalize(item[valueKey]),
                style: item.compact ? "pdfMetricValueCompact" : "pdfMetricValue",
              },
              { text: normalize(item[detailKey]), style: "pdfMetricMeta" },
            ],
            fillColor: itemTone.fill,
            borderColor: itemTone.border,
            margin: [8, 8, 8, 8],
          };
        };
        const cardGrid = (items, columns = 3, valueKey = "value", detailKey = "meta") => {
          const widths = Array.from({ length: columns }, () => "*");
          const body = [];

          for (let index = 0; index < items.length; index += columns) {
            const row = items.slice(index, index + columns).map((item) => card(item, valueKey, detailKey));

            while (row.length < columns) {
              row.push({ text: "", border: [false, false, false, false] });
            }

            body.push(row);
          }

          return {
            table: { widths, body },
            layout: {
              hLineWidth: () => 0.8,
              vLineWidth: () => 0.8,
              hLineColor: (_i, _node) => "#e1ebfb",
              vLineColor: (_i, _node) => "#e1ebfb",
              paddingLeft: () => 4,
              paddingRight: () => 4,
              paddingTop: () => 4,
              paddingBottom: () => 4,
            },
          };
        };
        const sectionTitle = (eyebrow, title) => ({
          stack: [
            { text: eyebrow, style: "pdfEyebrow" },
            { text: title, style: "pdfSectionTitle" },
          ],
          margin: [0, 14, 0, 6],
        });
        const tableLayout = {
          hLineWidth: (i) => (i === 0 ? 0 : 0.7),
          vLineWidth: () => 0,
          hLineColor: () => "#dde8f4",
          paddingLeft: () => 7,
          paddingRight: () => 7,
          paddingTop: () => 6,
          paddingBottom: () => 6,
        };
        const categoryBody = [
          ["Categoría", "Valor", "% total", "Bienes"].map((text) => ({
            text,
            style: "pdfTableHead",
          })),
          ...this.categoryRows.map((row) => [
            normalize(row.category),
            this.formatMoney(row.value_total),
            this.percentage(row.total, this.totals.total),
            this.formatNumber(row.total),
          ]),
        ];
        const breakdownBody = (rows) => [
          ["Detalle", "%", "Total"].map((text) => ({ text, style: "pdfTableHead" })),
          ...rows.map((row) => [
            normalize(row.label),
            `${row.percent}%`,
            this.formatNumber(row.total),
          ]),
        ];
        const lowStockBody = [
          ["Código", "Bien", "Categoría", "Dependencia", "Stock / mínimo"].map((text) => ({
            text,
            style: "pdfTableHead",
          })),
          ...this.lowStockItems.map((item) => [
            normalize(item.code),
            normalize(item.name),
            normalize(item.category?.name),
            item.dependency
              ? `${item.dependency.code} - ${item.dependency.name}`
              : "-",
            this.stockText(item),
          ]),
        ];

        const docDefinition = {
          pageSize: "A4",
          pageMargins: [34, 36, 34, 40],
          footer: (currentPage, pageCount) => ({
            columns: [
              { text: "Inventario - Reportes", style: "pdfFooter" },
              {
                text: `Página ${currentPage} de ${pageCount}`,
                style: "pdfFooter",
                alignment: "right",
              },
            ],
            margin: [34, 0, 34, 0],
          }),
          content: [
            {
              table: {
                widths: ["*"],
                body: [
                  [
                    {
                      stack: [
                        { text: "Inventario", style: "pdfEyebrow" },
                        { text: "Reportes", style: "pdfTitle" },
                        {
                          text: "Resumen operativo de bienes, valorización, estado físico y alertas de stock.",
                          style: "pdfSubtitle",
                        },
                        {
                          text: `Generado el ${this.formatReportDateTime()}`,
                          style: "pdfMuted",
                          margin: [0, 7, 0, 0],
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
            sectionTitle("Resumen", "Indicadores principales"),
            cardGrid(this.primaryStats, 3),
            sectionTitle("Alertas", "Control operativo"),
            cardGrid(this.riskStats, 5, "value", "detail"),
            sectionTitle("Distribución", "Bienes por categoría"),
            this.categoryRows.length
              ? {
                  table: {
                    headerRows: 1,
                    widths: ["*", 76, 54, 48],
                    body: categoryBody,
                  },
                  layout: tableLayout,
                }
              : { text: "Sin datos de categorías.", style: "pdfMuted" },
            {
              columns: [
                {
                  width: "50%",
                  stack: [
                    sectionTitle("Estado", "Situación actual"),
                    this.statusRows.length
                      ? {
                          table: {
                            headerRows: 1,
                            widths: ["*", 38, 42],
                            body: breakdownBody(this.statusRows),
                          },
                          layout: tableLayout,
                        }
                      : { text: "Sin datos de estado.", style: "pdfMuted" },
                  ],
                },
                {
                  width: "50%",
                  stack: [
                    sectionTitle("Condición", "Estado físico"),
                    this.conditionRows.length
                      ? {
                          table: {
                            headerRows: 1,
                            widths: ["*", 38, 42],
                            body: breakdownBody(this.conditionRows),
                          },
                          layout: tableLayout,
                        }
                      : { text: "Sin datos de condición.", style: "pdfMuted" },
                  ],
                },
              ],
              columnGap: 14,
            },
            sectionTitle("Stock", "Consumibles con stock bajo"),
            this.lowStockItems.length
              ? {
                  table: {
                    headerRows: 1,
                    widths: [58, "*", 78, 112, 76],
                    body: lowStockBody,
                  },
                  layout: tableLayout,
                }
              : {
                  text: "No hay consumibles bajo el mínimo configurado.",
                  style: "pdfMuted",
                },
          ],
          styles: {
            pdfTitle: { fontSize: 22, bold: true, color: "#1f2937" },
            pdfSubtitle: { fontSize: 10, color: "#64748b", margin: [0, 3, 0, 0] },
            pdfEyebrow: { fontSize: 8, bold: true, color: "#74788d" },
            pdfSectionTitle: { fontSize: 12, bold: true, color: "#334155" },
            pdfMetricLabel: {
              fontSize: 8,
              bold: true,
              alignment: "center",
              margin: [0, 0, 0, 4],
            },
            pdfMetricValue: {
              fontSize: 17,
              bold: true,
              color: "#334155",
              alignment: "center",
              margin: [0, 0, 0, 4],
            },
            pdfMetricValueCompact: {
              fontSize: 13,
              bold: true,
              color: "#334155",
              alignment: "center",
              margin: [0, 1, 0, 4],
            },
            pdfMetricMeta: {
              fontSize: 7.6,
              color: "#667085",
              alignment: "center",
            },
            pdfTableHead: {
              bold: true,
              color: "#475569",
              fillColor: "#f1f7ff",
              fontSize: 8,
              alignment: "center",
            },
            pdfMuted: { color: "#64748b", fontSize: 8.5 },
            pdfFooter: { color: "#94a3b8", fontSize: 8 },
          },
          defaultStyle: { fontSize: 8.5, color: "#334155" },
        };

        pdfMake
          .createPdf(docDefinition)
          .download(`reportes_inventario_${new Date().toISOString().slice(0, 10)}.pdf`);
      } catch (error) {
        this.error =
          error?.response?.data?.message || error?.message || "Error generando PDF";
      } finally {
        this.exportingPdf = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="inventory-report-page">
      <div class="inventory-report-header">
        <div>
          <div class="inventory-report-eyebrow">Inventario</div>
          <h4>Reportes</h4>
          <p>Resumen operativo de bienes, valorización, estado físico y alertas de stock.</p>
        </div>
        <div class="inventory-report-actions">
          <BButton
            variant="outline-danger"
            class="inventory-pdf-button"
            @click="exportPdf"
            :disabled="loading || exportingPdf || !dashboard"
          >
            <i class="mdi mdi-file-pdf-box"></i>
            <span>{{ exportingPdf ? "Generando..." : "Exportar PDF" }}</span>
          </BButton>
          <BButton variant="secondary" class="inventory-refresh-button" @click="load" :disabled="loading">
            <i class="mdi mdi-refresh"></i>
            <span>{{ loading ? "Actualizando..." : "Actualizar" }}</span>
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <BCard v-if="loading && !dashboard" class="inventory-report-panel">
        <LoadingState message="Cargando reportes de inventario..." compact />
      </BCard>

      <div v-else-if="dashboard" class="inventory-report-content">
        <div class="inventory-stat-grid">
          <div
            v-for="stat in primaryStats"
            :key="stat.label"
            class="inventory-stat-card"
            :class="`inventory-stat-card--${stat.tone}`"
          >
            <div class="inventory-stat-icon">
              <i :class="`mdi ${stat.icon}`"></i>
            </div>
            <div class="inventory-stat-body">
              <div class="inventory-stat-label">{{ stat.label }}</div>
              <div
                class="inventory-stat-value"
                :class="{ 'inventory-stat-value--compact': stat.compact }"
              >
                {{ stat.value }}
              </div>
              <div class="inventory-stat-meta">{{ stat.meta }}</div>
            </div>
          </div>
        </div>

        <div class="inventory-risk-grid">
          <div
            v-for="risk in riskStats"
            :key="risk.label"
            class="inventory-risk-card"
            :class="`inventory-risk-card--${risk.tone}`"
          >
            <div>
              <div class="inventory-risk-label">{{ risk.label }}</div>
              <div class="inventory-risk-detail">{{ risk.detail }}</div>
            </div>
            <strong>{{ formatNumber(risk.value) }}</strong>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-xl-8">
            <section class="inventory-report-panel">
              <div class="inventory-panel-header">
                <div>
                  <div class="inventory-report-eyebrow">Distribución</div>
                  <h5>Bienes por categoría</h5>
                </div>
                <span class="inventory-panel-pill">{{ categoryRows.length }} categorías</span>
              </div>

              <div v-if="categoryRows.length === 0" class="inventory-empty-state">
                Sin datos de categorías.
              </div>
              <div v-else class="inventory-category-list">
                <div v-for="row in categoryRows" :key="row.category" class="inventory-category-row">
                  <div class="inventory-category-main">
                    <div class="inventory-category-name">{{ row.category }}</div>
                    <div class="inventory-category-value">{{ formatMoney(row.value_total) }}</div>
                  </div>
                  <div class="inventory-category-meter">
                    <span :style="{ width: `${row.percent}%` }"></span>
                  </div>
                  <div class="inventory-category-total">
                    {{ formatNumber(row.total) }}
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div class="col-xl-4">
            <section class="inventory-report-panel inventory-breakdown-panel">
              <div class="inventory-panel-header">
                <div>
                  <div class="inventory-report-eyebrow">Estado</div>
                  <h5>Situación actual</h5>
                </div>
              </div>
              <div class="inventory-breakdown-list">
                <div v-for="row in statusRows" :key="row.label" class="inventory-breakdown-row">
                  <span class="inventory-breakdown-dot" :class="`inventory-breakdown-dot--${rowTone(row.label)}`"></span>
                  <div class="inventory-breakdown-name">{{ row.label }}</div>
                  <div class="inventory-breakdown-percent">{{ row.percent }}%</div>
                  <strong>{{ formatNumber(row.total) }}</strong>
                </div>
              </div>
            </section>

            <section class="inventory-report-panel inventory-breakdown-panel">
              <div class="inventory-panel-header">
                <div>
                  <div class="inventory-report-eyebrow">Condición</div>
                  <h5>Estado físico</h5>
                </div>
              </div>
              <div class="inventory-breakdown-list">
                <div v-for="row in conditionRows" :key="row.label" class="inventory-breakdown-row">
                  <span class="inventory-breakdown-dot" :class="`inventory-breakdown-dot--${rowTone(row.label)}`"></span>
                  <div class="inventory-breakdown-name">{{ row.label }}</div>
                  <div class="inventory-breakdown-percent">{{ row.percent }}%</div>
                  <strong>{{ formatNumber(row.total) }}</strong>
                </div>
              </div>
            </section>
          </div>

          <div class="col-12">
            <section class="inventory-report-panel">
              <div class="inventory-panel-header">
                <div>
                  <div class="inventory-report-eyebrow">Alertas</div>
                  <h5>Consumibles con stock bajo</h5>
                </div>
                <span class="inventory-panel-pill inventory-panel-pill--warning">
                  {{ formatNumber(dashboard.low_stock) }} alertas
                </span>
              </div>

              <div v-if="lowStockItems.length === 0" class="inventory-empty-state">
                No hay consumibles bajo el mínimo configurado.
              </div>
              <div v-else class="table-responsive inventory-stock-table-wrap">
                <table class="table inventory-stock-table">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Bien</th>
                      <th>Categoría</th>
                      <th>Dependencia</th>
                      <th>Stock / mínimo</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in lowStockItems" :key="item.id">
                      <td>
                        <span class="inventory-code-pill">{{ item.code }}</span>
                      </td>
                      <td>{{ item.name }}</td>
                      <td>{{ item.category?.name || "-" }}</td>
                      <td>
                        {{
                          item.dependency
                            ? `${item.dependency.code} - ${item.dependency.name}`
                            : "-"
                        }}
                      </td>
                      <td>
                        <span class="inventory-stock-pill">{{ stockText(item) }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </div>
        </div>
      </div>

      <BCard v-else class="inventory-report-panel">
        <div class="inventory-empty-state">Sin datos para mostrar.</div>
      </BCard>
    </div>
  </Layout>
</template>

<style scoped>
.inventory-report-page {
  color: #3f4754;
}

.inventory-report-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.inventory-report-header h4 {
  margin: 0;
  color: #3f4754;
  font-size: 1.45rem;
  font-weight: 650;
  line-height: 1.2;
}

.inventory-report-header p {
  margin: 0.35rem 0 0;
  color: #74788d;
  font-size: 0.9rem;
  font-weight: 500;
}

.inventory-report-eyebrow {
  color: #74788d;
  font-size: 0.74rem;
  font-weight: 650;
  line-height: 1.2;
  letter-spacing: 0;
}

.inventory-report-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.inventory-pdf-button,
.inventory-refresh-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  min-height: 2.35rem;
  border-radius: 999px;
  font-size: 0.84rem;
  font-weight: 650;
}

.inventory-pdf-button {
  border-color: #fecaca;
  color: #b91c1c;
  background: #fff7f7;
}

.inventory-pdf-button:hover,
.inventory-pdf-button:focus {
  border-color: #f87171;
  color: #ffffff;
  background: #ef4444;
}

.inventory-report-content {
  display: grid;
  gap: 1rem;
}

.inventory-stat-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.85rem;
}

.inventory-stat-card,
.inventory-risk-card,
.inventory-report-panel {
  border: 1px solid #e1ebfb;
  border-radius: 0.85rem;
  background: rgba(255, 255, 255, 0.82);
  box-shadow: 0 0.75rem 2rem rgba(31, 41, 55, 0.05);
}

.inventory-stat-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  min-height: 8.4rem;
  padding: 0.95rem;
  text-align: center;
}

.inventory-stat-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 2.35rem;
  width: 2.35rem;
  height: 2.35rem;
  border-radius: 0.7rem;
  font-size: 1.3rem;
}

.inventory-stat-body {
  width: 100%;
  min-width: 0;
}

.inventory-stat-label,
.inventory-risk-label {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 1.8rem;
  color: #74788d;
  font-size: 0.74rem;
  font-weight: 650;
  line-height: 1.2;
  text-align: center;
  overflow-wrap: anywhere;
}

.inventory-stat-value {
  margin-top: 0.35rem;
  color: #334155;
  font-size: 1.28rem;
  font-weight: 700;
  line-height: 1.1;
  text-align: center;
  white-space: nowrap;
  word-break: keep-all;
  overflow-wrap: normal;
}

.inventory-stat-value--compact {
  font-size: 1.04rem;
  line-height: 1.15;
}

.inventory-stat-meta,
.inventory-risk-detail {
  margin-top: 0.35rem;
  color: #667085;
  font-size: 0.78rem;
  font-weight: 500;
  line-height: 1.25;
  text-align: center;
  overflow-wrap: anywhere;
}

.inventory-stat-card--blue .inventory-stat-icon,
.inventory-breakdown-dot--blue {
  color: #1d4ed8;
  background: #eff6ff;
}

.inventory-stat-card--green .inventory-stat-icon,
.inventory-breakdown-dot--green {
  color: #047857;
  background: #ecfdf5;
}

.inventory-stat-card--indigo .inventory-stat-icon {
  color: #3152c9;
  background: #eef4ff;
}

.inventory-stat-card--teal .inventory-stat-icon {
  color: #0f766e;
  background: #f0fdfa;
}

.inventory-stat-card--slate .inventory-stat-icon,
.inventory-breakdown-dot--slate {
  color: #475569;
  background: #f8fafc;
}

.inventory-stat-card--amber .inventory-stat-icon,
.inventory-breakdown-dot--amber {
  color: #b45309;
  background: #fffbeb;
}

.inventory-risk-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.85rem;
}

.inventory-risk-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
  min-height: 5.6rem;
  padding: 0.85rem;
  text-align: center;
}

.inventory-risk-card strong {
  color: #334155;
  font-size: 1.25rem;
  line-height: 1;
  text-align: center;
}

.inventory-risk-card--red {
  border-color: #fecaca;
  background: #fef2f2;
}

.inventory-risk-card--amber {
  border-color: #fcd34d;
  background: #fffbeb;
}

.inventory-risk-card--blue {
  border-color: #bfdbfe;
  background: #eff6ff;
}

.inventory-risk-card--indigo {
  border-color: #c7d7fe;
  background: #eef4ff;
}

.inventory-risk-card--slate {
  border-color: #cbd5e1;
  background: #f8fafc;
}

.inventory-report-panel {
  padding: 1rem;
}

.inventory-breakdown-panel + .inventory-breakdown-panel {
  margin-top: 1rem;
}

.inventory-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.85rem;
}

.inventory-panel-header > div {
  min-width: 0;
}

.inventory-panel-header h5 {
  margin: 0.15rem 0 0;
  color: #3f4754;
  font-size: 1rem;
  font-weight: 650;
}

.inventory-panel-pill,
.inventory-code-pill,
.inventory-stock-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 1.7rem;
  padding: 0.3rem 0.65rem;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.76rem;
  font-weight: 650;
  line-height: 1;
  white-space: nowrap;
}

.inventory-panel-pill--warning,
.inventory-stock-pill {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

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

.inventory-category-list {
  display: grid;
  gap: 0.65rem;
}

.inventory-category-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(12rem, 42%) 4.25rem;
  align-items: center;
  gap: 0.8rem;
  padding: 0.72rem 0;
  border-bottom: 1px solid #e6eef8;
}

.inventory-category-row:last-child {
  border-bottom: 0;
}

.inventory-category-main {
  min-width: 0;
}

.inventory-category-name {
  color: #334155;
  font-size: 0.9rem;
  font-weight: 650;
  line-height: 1.25;
  overflow-wrap: anywhere;
}

.inventory-category-value {
  margin-top: 0.18rem;
  color: #74788d;
  font-size: 0.78rem;
  font-weight: 500;
}

.inventory-category-meter {
  width: 100%;
  height: 0.55rem;
  overflow: hidden;
  border-radius: 999px;
  background: #edf2f7;
}

.inventory-category-meter span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, #5f76e8 0%, #22c55e 100%);
}

.inventory-category-total {
  color: #334155;
  font-size: 0.95rem;
  font-weight: 700;
  text-align: center;
}

.inventory-breakdown-list {
  display: grid;
  gap: 0.58rem;
}

.inventory-breakdown-row {
  display: grid;
  grid-template-columns: 0.7rem minmax(0, 1fr) 3.1rem 2.5rem;
  align-items: center;
  gap: 0.55rem;
  min-height: 2rem;
}

.inventory-breakdown-dot {
  width: 0.7rem;
  height: 0.7rem;
  border-radius: 999px;
}

.inventory-breakdown-dot--red {
  background: #fecaca;
}

.inventory-breakdown-name {
  color: #4b5563;
  font-size: 0.84rem;
  font-weight: 600;
  line-height: 1.2;
  overflow-wrap: anywhere;
}

.inventory-breakdown-percent {
  color: #74788d;
  font-size: 0.78rem;
  font-weight: 650;
  text-align: right;
}

.inventory-breakdown-row strong {
  color: #334155;
  font-size: 0.86rem;
  text-align: right;
}

.inventory-stock-table-wrap {
  border: 1px solid #e6eef8;
  border-radius: 0.75rem;
  overflow: hidden;
}

.inventory-stock-table {
  margin-bottom: 0;
}

.inventory-stock-table thead th {
  padding: 0.72rem 0.65rem;
  color: #74788d;
  font-size: 0.74rem;
  font-weight: 650;
  text-align: center;
  vertical-align: middle;
  background: #f8fbff;
  border-bottom: 1px solid #dfe8f7;
}

.inventory-stock-table tbody td {
  padding: 0.72rem 0.65rem;
  color: #4b5563;
  font-size: 0.84rem;
  font-weight: 500;
  text-align: center;
  vertical-align: middle;
  border-bottom: 1px solid #e6eef8;
}

.inventory-stock-table tbody tr:last-child td {
  border-bottom: 0;
}

@media (max-width: 1399.98px) {
  .inventory-stat-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .inventory-risk-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 991.98px) {
  .inventory-report-header,
  .inventory-panel-header {
    flex-direction: column;
    align-items: stretch;
  }

  .inventory-report-actions {
    justify-content: flex-start;
  }

  .inventory-stat-grid,
  .inventory-risk-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .inventory-category-row {
    grid-template-columns: minmax(0, 1fr);
  }

  .inventory-category-main,
  .inventory-category-total {
    text-align: center;
  }
}

@media (max-width: 575.98px) {
  .inventory-stat-grid,
  .inventory-risk-grid {
    grid-template-columns: 1fr;
  }
}
</style>
