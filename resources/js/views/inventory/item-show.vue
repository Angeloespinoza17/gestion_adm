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

        const infoTable = [
          ["Código", this.item.code],
          ["Nombre", this.item.name],
          ["Categoría", this.item.category?.name || "-"],
          ["Subcategoría", this.item.subcategory?.name || "-"],
          [
            "Dependencia",
            this.item.dependency
              ? `${this.item.dependency.code} - ${this.item.dependency.name}`
              : "-",
          ],
          ["Responsable", this.item.responsible_user?.name || "-"],
          ["Estado", this.item.status || "-"],
          ["Condición", this.item.condition || "-"],
          ["Tipo", this.item.item_type || "-"],
          ["Marca", this.item.brand || "-"],
          ["Modelo", this.item.model || "-"],
          ["Serie", this.item.serial_number || "-"],
          ["Compra", this.formatDate(this.item.purchase_date)],
          ["Valor compra", this.item.purchase_value ?? "-"],
        ];

        if (this.isConsumable) {
          infoTable.push([
            "Stock",
            `${this.item.stock_quantity ?? 0} ${this.item.unit_of_measure || ""}`.trim(),
          ]);
          infoTable.push(["Stock mínimo", this.item.minimum_stock ?? "-"]);
        }

        const docDefinition = {
          pageSize: "A4",
          pageMargins: [40, 50, 40, 50],
          content: [
            {
              columns: [
                [
                  { text: "Ficha Inventario", style: "h1" },
                  { text: this.item.code, style: "muted" },
                ],
                {
                  width: 90,
                  qr: this.item.qr_code || this.item.code,
                  fit: 80,
                  alignment: "right",
                },
              ],
            },
            { text: " ", margin: [0, 6] },
            {
              table: {
                widths: [120, "*"],
                body: infoTable.map(([k, v]) => [
                  { text: String(k), style: "th" },
                  { text: String(v ?? "-") },
                ]),
              },
              layout: "lightHorizontalLines",
            },
            { text: " ", margin: [0, 10] },
            { text: "Descripción", style: "h2" },
            { text: this.item.description || "-", margin: [0, 0, 0, 10] },
            { text: "Foto principal", style: "h2" },
            mainImage
              ? { image: mainImage, width: 320 }
              : { text: "Sin foto", style: "muted" },
            { text: " ", margin: [0, 10] },
            { text: "Movimientos", style: "h2" },
            (this.item.movements || []).length > 0
              ? {
                  table: {
                    headerRows: 1,
                    widths: [60, 120, "*", "*"],
                    body: [
                      ["Fecha", "Tipo", "Desde", "Hacia"].map((x) => ({
                        text: x,
                        style: "th",
                      })),
                      ...(this.item.movements || []).slice(0, 50).map((m) => [
                        this.formatDate(m.movement_date),
                        m.movement_type || "-",
                        m.from_dependency?.code || "-",
                        m.to_dependency?.code || "-",
                      ]),
                    ],
                  },
                  layout: "lightHorizontalLines",
                }
              : { text: "Sin movimientos", style: "muted" },
          ],
          styles: {
            h1: { fontSize: 18, bold: true },
            h2: { fontSize: 12, bold: true, margin: [0, 0, 0, 6] },
            th: { bold: true, fillColor: "#f3f3f3" },
            muted: { color: "#666666", fontSize: 9 },
          },
          defaultStyle: { fontSize: 10 },
        };

        pdfMake.createPdf(docDefinition).download(`${this.item.code}.pdf`);
      } catch (error) {
        this.error =
          error?.response?.data?.message || error?.message || "Error generando PDF";
      } finally {
        this.saving = false;
      }
    },

    formatDate(value) {
      if (!value) return "-";
      // value viene YYYY-MM-DD
      const [y, m, d] = String(value).split("-");
      if (!y || !m || !d) return value;
      return `${d}-${m}-${y}`;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Inventario · Ficha</h4>
        <div class="text-muted" v-if="item">
          {{ item.code }} · {{ item.name }}
        </div>
      </div>
      <div class="d-flex gap-2">
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
        <BButton
          variant="primary"
          v-if="item && isConsumable"
          @click="openStock"
        >
          Stock
        </BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading" class="mb-3">Cargando...</BCard>

    <div v-if="item" class="row g-3">
      <div class="col-lg-4">
        <BCard title="Foto principal">
          <div v-if="item.image_url">
            <img :src="item.image_url" class="img-fluid rounded" alt="foto" />
          </div>
          <div v-else class="text-muted">Sin foto principal.</div>

          <div class="mt-3">
            <label class="form-label">Subir foto</label>
            <input
              class="form-control"
              type="file"
              accept="image/*"
              capture="environment"
              @change="onNewPhoto"
            />
            <div v-if="photoError" class="text-danger small mt-1">
              {{ photoError }}
            </div>
            <div class="d-flex gap-2 mt-2">
              <BButton
                variant="secondary"
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
        </BCard>
      </div>

      <div class="col-lg-8">
        <BCard title="Información">
          <div class="row g-2">
            <div class="col-md-4">
              <div class="text-muted small">Código</div>
              <div class="fw-semibold">{{ item.code }}</div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Categoría</div>
              <div class="fw-semibold">{{ item.category?.name || "-" }}</div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Subcategoría</div>
              <div class="fw-semibold">{{ item.subcategory?.name || "-" }}</div>
            </div>

            <div class="col-md-4">
              <div class="text-muted small">Dependencia</div>
              <div class="fw-semibold">
                {{
                  item.dependency
                    ? `${item.dependency.code} - ${item.dependency.name}`
                    : "-"
                }}
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Responsable</div>
              <div class="fw-semibold">
                {{ item.responsible_user?.name || "-" }}
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Proveedor</div>
              <div class="fw-semibold">{{ item.supplier?.name || "-" }}</div>
            </div>

            <div class="col-md-4">
              <div class="text-muted small">Estado</div>
              <div class="fw-semibold">{{ item.status }}</div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Condición</div>
              <div class="fw-semibold">{{ item.condition }}</div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Tipo</div>
              <div class="fw-semibold">{{ item.item_type }}</div>
            </div>

            <div class="col-md-4">
              <div class="text-muted small">Marca</div>
              <div class="fw-semibold">{{ item.brand || "-" }}</div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Modelo</div>
              <div class="fw-semibold">{{ item.model || "-" }}</div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Serie</div>
              <div class="fw-semibold">{{ item.serial_number || "-" }}</div>
            </div>

            <div class="col-md-4">
              <div class="text-muted small">Compra</div>
              <div class="fw-semibold">{{ formatDate(item.purchase_date) }}</div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Valor compra</div>
              <div class="fw-semibold">
                {{ item.purchase_value ?? "-" }}
              </div>
            </div>
            <div class="col-md-4" v-if="isConsumable">
              <div class="text-muted small">Stock</div>
              <div class="fw-semibold">
                {{ item.stock_quantity ?? 0 }} {{ item.unit_of_measure || "" }}
              </div>
            </div>

            <div class="col-12">
              <div class="text-muted small">Descripción</div>
              <div>{{ item.description || "-" }}</div>
            </div>
          </div>
        </BCard>
      </div>

      <div class="col-12">
        <BCard title="Fotos">
          <div v-if="(item.photos || []).length === 0" class="text-muted">
            Sin fotos.
          </div>
          <div class="row g-2" v-else>
            <div class="col-6 col-md-3" v-for="p in item.photos" :key="p.id">
              <div class="border rounded p-2 h-100">
                <img
                  :src="p.image_url"
                  class="img-fluid rounded"
                  style="max-height: 160px; object-fit: cover; width: 100%"
                  alt="foto"
                />
                <div class="d-flex justify-content-between align-items-center mt-2">
                  <small class="text-muted">{{
                    p.is_main ? "Principal" : ""
                  }}</small>
                  <div class="d-flex gap-1">
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
          </div>
        </BCard>
      </div>

      <div class="col-12">
        <BCard title="Documentos">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Archivo</label>
              <input class="form-control" type="file" @change="onNewDoc" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Tipo</label>
              <BFormSelect v-model="newDocType" :options="docTypes" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Observación</label>
              <BFormInput v-model="newDocObs" />
            </div>
            <div class="col-12">
              <div v-if="docError" class="text-danger small">
                {{ docError }}
              </div>
              <BButton variant="primary" size="sm" :disabled="uploadingDoc" @click="uploadDoc">
                {{ uploadingDoc ? "Subiendo..." : "Subir documento" }}
              </BButton>
            </div>
          </div>

          <hr />

          <div v-if="(item.documents || []).length === 0" class="text-muted">
            Sin documentos.
          </div>
          <div class="table-responsive" v-else>
            <BTable
              :items="item.documents"
              :fields="[
                { key: 'document_type', label: 'Tipo' },
                { key: 'original_name', label: 'Archivo' },
                { key: 'actions', label: 'Acciones' },
              ]"
              small
            >
              <template #cell(actions)="{ item: doc }">
                <div class="d-flex gap-2">
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
      </div>

      <div class="col-12">
        <BCard title="Movimientos">
          <div v-if="(item.movements || []).length === 0" class="text-muted">
            Sin movimientos.
          </div>
          <div class="table-responsive" v-else>
            <BTable
              :items="item.movements"
              :fields="[
                { key: 'movement_date', label: 'Fecha' },
                { key: 'movement_type', label: 'Tipo' },
                { key: 'fromDependency', label: 'Desde' },
                { key: 'toDependency', label: 'Hacia' },
                { key: 'fromUser', label: 'Resp. anterior' },
                { key: 'toUser', label: 'Resp. nuevo' },
              ]"
              small
            >
              <template #cell(movement_date)="{ item: m }">
                {{ formatDate(m.movement_date) }}
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
      </div>

      <div class="col-12" v-if="isConsumable">
        <BCard title="Movimientos de stock">
          <div v-if="(item.stock_movements || []).length === 0" class="text-muted">
            Sin movimientos de stock.
          </div>
          <div class="table-responsive" v-else>
            <BTable
              :items="item.stock_movements"
              :fields="[
                { key: 'id', label: '#' },
                { key: 'movement_type', label: 'Tipo' },
                { key: 'quantity', label: 'Cantidad' },
                { key: 'previous_stock', label: 'Anterior' },
                { key: 'new_stock', label: 'Nuevo' },
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
