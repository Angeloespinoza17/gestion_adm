<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import { getPdfMake } from "../../utils/pdfmake";

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      error: null,
      search: "",
      items: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      selected: [], // [{ item, qty }]
      printing: false,
    };
  },
  mounted() {
    this.loadItems();
  },
  methods: {
    async loadItems(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/inventory/items", {
          params: { page, per_page: 25, search: this.search, active: 1 },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error =
          error?.response?.data?.message || error?.message || "Error desconocido";
      } finally {
        this.loading = false;
      }
    },
    addItem(item) {
      const existing = this.selected.find((x) => x.item.id === item.id);
      if (existing) {
        existing.qty += 1;
        return;
      }
      this.selected.push({ item, qty: 1 });
    },
    removeSelected(index) {
      this.selected.splice(index, 1);
    },
    async printLabels() {
      if (this.selected.length === 0) {
        this.error = "Selecciona al menos un bien para imprimir etiquetas.";
        return;
      }

      this.printing = true;
      this.error = null;
      try {
        const pdfMake = getPdfMake();

        // Expandir a una lista plana de etiquetas
        const labels = [];
        for (const row of this.selected) {
          const qty = Math.max(1, Number(row.qty || 1));
          for (let i = 0; i < qty; i++) {
            labels.push(row.item);
          }
        }

        const mmToPt = (mm) => Number(((mm * 72) / 25.4).toFixed(2));
        const labelWidth = mmToPt(50);
        const labelHeight = mmToPt(30);
        const labelMargin = mmToPt(1.2);
        const contentWidth = labelWidth - labelMargin * 2;
        const truncate = (value, max) => {
          const text = String(value || "").trim();
          return text.length > max ? `${text.slice(0, max - 3)}...` : text;
        };
        const labelContent = (item, index) => {
          const code = item.code || "-";
          const type = item.item_type === "consumable" ? "CONSUMIBLE" : "ACTIVO";
          const name = truncate(item.name || "Bien sin nombre", 44).toUpperCase();
          const serial = truncate(item.serial_number || "SS", 22);
          const dependency = item.dependency
            ? truncate(`${item.dependency.code} · ${item.dependency.name}`, 30)
            : truncate(item.category?.name || "Sin ubicación", 30);

          return {
            margin: [0, 0, 0, 0],
            table: {
              widths: ["*"],
              body: [
                [
                  {
                    margin: [0, 0, 0, 0],
                    stack: [
                      {
                        table: {
                          widths: ["*", "auto"],
                          body: [
                            [
                              {
                                text: "INVENTARIO",
                                bold: true,
                                color: "#ffffff",
                                fontSize: 5.4,
                                margin: [2, 1, 2, 1],
                                fillColor: "#111111",
                              },
                              {
                                text: type,
                                bold: true,
                                color: "#ffffff",
                                fontSize: 5.4,
                                alignment: "right",
                                margin: [2, 1, 2, 1],
                                fillColor: "#111111",
                              },
                            ],
                          ],
                        },
                        layout: "noBorders",
                        margin: [0, 0, 0, 2],
                      },
                      {
                        text: code,
                        bold: true,
                        fontSize: 13.2,
                        alignment: "center",
                        lineHeight: 0.85,
                        margin: [2, 1, 2, 0],
                      },
                      {
                        canvas: [
                          {
                            type: "line",
                            x1: 0,
                            y1: 0,
                            x2: contentWidth - mmToPt(2.2),
                            y2: 0,
                            lineWidth: 0.7,
                            lineColor: "#111111",
                          },
                        ],
                        margin: [mmToPt(1.1), 2, mmToPt(1.1), 1],
                      },
                      {
                        text: name,
                        bold: true,
                        fontSize: 6.4,
                        alignment: "center",
                        lineHeight: 0.9,
                        margin: [2, 0, 2, 1],
                      },
                      {
                        columns: [
                          {
                            width: "42%",
                            stack: [
                              { text: "SERIE", bold: true, fontSize: 4.2, color: "#444444" },
                              { text: serial, fontSize: 5.2, lineHeight: 0.95 },
                            ],
                          },
                          {
                            width: "*",
                            stack: [
                              {
                                text: "UBICACION",
                                bold: true,
                                fontSize: 4.2,
                                color: "#444444",
                                alignment: "right",
                              },
                              {
                                text: dependency,
                                fontSize: 5.2,
                                lineHeight: 0.95,
                                alignment: "right",
                              },
                            ],
                          },
                        ],
                        columnGap: 4,
                        margin: [2, 1, 2, 0],
                      },
                    ],
                  },
                ],
              ],
            },
            layout: {
              hLineWidth: () => 0.7,
              vLineWidth: () => 0.7,
              hLineColor: () => "#111111",
              vLineColor: () => "#111111",
              paddingLeft: () => 0,
              paddingRight: () => 0,
              paddingTop: () => 0,
              paddingBottom: () => 0,
            },
            pageBreak: index > 0 ? "before" : undefined,
          };
        };

        const docDefinition = {
          pageSize: {
            width: labelWidth,
            height: labelHeight,
          },
          pageMargins: [labelMargin, labelMargin, labelMargin, labelMargin],
          content: labels.map(labelContent),
          defaultStyle: { fontSize: 6 },
        };

        pdfMake.createPdf(docDefinition).open();
      } catch (error) {
        this.error =
          error?.response?.data?.message ||
          error?.message ||
          "Error generando PDF de etiquetas";
      } finally {
        this.printing = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Inventario · Etiquetas Zebra 50 x 30 mm</h4>
      <BButton
        variant="primary"
        :disabled="printing || selected.length === 0"
        @click="printLabels"
      >
        {{ printing ? "Generando..." : "Imprimir etiquetas" }}
      </BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-lg-7">
        <BCard title="Buscar bienes">
          <div class="row g-2 mb-2">
            <div class="col-md-8">
              <BFormInput
                v-model="search"
                placeholder="Buscar por código o nombre..."
                @keyup.enter="loadItems(1)"
              />
            </div>
            <div class="col-md-4">
              <BButton variant="secondary" class="w-100" @click="loadItems(1)"
                >Buscar</BButton
              >
            </div>
          </div>

          <div class="table-responsive">
            <BTable
              :items="items"
              :busy="loading"
              :fields="[
                { key: 'code', label: 'Código' },
                { key: 'name', label: 'Nombre' },
                { key: 'category', label: 'Categoría' },
                { key: 'actions', label: 'Acción' },
              ]"
              small
            >
              <template #table-busy>
                <LoadingState message="Cargando items..." compact />
              </template>
              <template #cell(category)="{ item }">
                {{ item.category?.name || "-" }}
              </template>
              <template #cell(actions)="{ item }">
                <BButton size="sm" variant="outline-primary" @click="addItem(item)"
                  >Agregar</BButton
                >
              </template>
            </BTable>
          </div>

          <div class="d-flex justify-content-end">
            <BPagination
              v-model="pagination.current_page"
              :per-page="25"
              :total-rows="pagination.total"
              @update:model-value="loadItems"
            />
          </div>
        </BCard>
      </div>

      <div class="col-lg-5">
        <BCard title="Seleccionados">
          <div v-if="selected.length === 0" class="text-muted">
            Aún no agregas bienes.
          </div>

          <div v-else class="table-responsive">
            <BTable
              :items="selected"
              :fields="[
                { key: 'code', label: 'Código' },
                { key: 'name', label: 'Nombre' },
                { key: 'qty', label: 'Cantidad' },
                { key: 'actions', label: 'Acción' },
              ]"
              small
            >
              <template #cell(code)="{ item }">{{ item.item.code }}</template>
              <template #cell(name)="{ item }">{{ item.item.name }}</template>
              <template #cell(qty)="{ item }">
                <BFormInput v-model="item.qty" type="number" min="1" />
              </template>
              <template #cell(actions)="{ index }">
                <BButton
                  size="sm"
                  variant="outline-danger"
                  @click="removeSelected(index)"
                  >Quitar</BButton
                >
              </template>
            </BTable>
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
