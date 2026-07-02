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

        const colsPerRow = 3;
        const rows = [];
        for (let i = 0; i < labels.length; i += colsPerRow) {
          const slice = labels.slice(i, i + colsPerRow);
          while (slice.length < colsPerRow) slice.push(null);
          rows.push(slice);
        }

        const labelCell = (item) => {
          if (!item) return { text: "" };

          const qrValue = item.qr_code || item.code;
          const name = String(item.name || "").slice(0, 40);

          return {
            margin: [0, 0, 0, 0],
            table: {
              widths: ["*", "*"],
              body: [
                [
                  {
                    qr: qrValue,
                    fit: 70,
                    margin: [2, 2, 2, 2],
                  },
                  {
                    stack: [
                      { text: item.code, bold: true, fontSize: 10 },
                      { text: name, fontSize: 9, margin: [0, 2, 0, 0] },
                      {
                        text: item.category?.name || "",
                        fontSize: 8,
                        color: "#666666",
                        margin: [0, 2, 0, 0],
                      },
                    ],
                    margin: [2, 2, 2, 2],
                  },
                ],
              ],
            },
            layout: {
              hLineWidth: () => 0.5,
              vLineWidth: () => 0.5,
              hLineColor: () => "#cccccc",
              vLineColor: () => "#cccccc",
              paddingLeft: () => 2,
              paddingRight: () => 2,
              paddingTop: () => 2,
              paddingBottom: () => 2,
            },
          };
        };

        const docDefinition = {
          pageSize: "A4",
          pageMargins: [18, 20, 18, 20],
          content: rows.flatMap((r, idx) => {
            const table = {
              table: {
                widths: ["*", "*", "*"],
                body: [[labelCell(r[0]), labelCell(r[1]), labelCell(r[2])]],
              },
              layout: "noBorders",
              margin: [0, 0, 0, 10],
            };

            // Cada ~9 filas cortar página para mantener consistencia
            if ((idx + 1) % 9 === 0 && idx !== rows.length - 1) {
              return [table, { text: "", pageBreak: "after" }];
            }
            return [table];
          }),
          defaultStyle: { fontSize: 10 },
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
      <h4 class="mb-0">Inventario · Etiquetas (QR)</h4>
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
