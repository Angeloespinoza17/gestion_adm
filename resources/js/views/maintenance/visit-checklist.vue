<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import pdfMake from "pdfmake/build/pdfmake";
import pdfFonts from "pdfmake/build/vfs_fonts";

pdfMake.vfs = pdfFonts?.pdfMake?.vfs || pdfFonts;

export default {
  components: { Layout },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      success: null,
      visit: null,
      catalogs: null,
      items: [],
      responseByItem: {},
      photoUploading: {},
    };
  },
  computed: {
    visitId() {
      return this.$route.params.id;
    },
    groupedItems() {
      const groups = {};
      for (const item of this.items) {
        const system = item.system || "General";
        const sub = item.subdimension || "Sin subdimensión";
        groups[system] ||= {};
        groups[system][sub] ||= [];
        groups[system][sub].push(item);
      }
      return groups;
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
        const [catalogs, checklist] = await Promise.all([
          axios.get("/api/maintenance/visits/catalogs"),
          axios.get(`/api/maintenance/visits/${this.visitId}/checklist`),
        ]);

        this.catalogs = catalogs.data;
        this.visit = checklist.data.visit;
        this.items = checklist.data.items;

        const responseByItem = {};
        for (const response of checklist.data.responses || []) {
          responseByItem[response.maintenance_checklist_item_id] = response;
        }
        this.responseByItem = responseByItem;
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error cargando checklist";
      } finally {
        this.loading = false;
      }
    },
    responseFor(itemId) {
      return (
        this.responseByItem[itemId] || {
          maintenance_checklist_item_id: itemId,
          review_status: "",
          observations: "",
          finding_description: "",
          photo_url: null,
          work_order_id: null,
        }
      );
    },
    setResponse(itemId, patch) {
      this.responseByItem = {
        ...this.responseByItem,
        [itemId]: {
          ...this.responseFor(itemId),
          ...patch,
        },
      };
    },
    async saveChecklist() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        const responses = Object.values(this.responseByItem).map((row) => ({
          maintenance_checklist_item_id: row.maintenance_checklist_item_id,
          review_status: row.review_status || null,
          observations: row.observations || null,
          finding_description: row.finding_description || null,
        }));

        const response = await axios.post(`/api/maintenance/visits/${this.visitId}/checklist`, { responses });
        this.success = response.data.message;
        await this.load();
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors ? Object.values(errors).flat().join(" ") : error.response?.data?.message || error.message;
      } finally {
        this.saving = false;
      }
    },
    async uploadPhoto(item) {
      const input = this.$refs[`photo_${item.id}`]?.[0];
      const file = input?.files?.[0];
      if (!file) return;

      this.photoUploading = { ...this.photoUploading, [item.id]: true };
      this.error = null;
      this.success = null;
      try {
        const payload = new FormData();
        payload.append("maintenance_checklist_item_id", String(item.id));
        payload.append("photo", file);

        const response = await axios.post(`/api/maintenance/visits/${this.visitId}/checklist-photo`, payload);
        this.success = response.data.message;

        const updated = response.data.data;
        this.setResponse(item.id, updated);
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors ? Object.values(errors).flat().join(" ") : error.response?.data?.message || error.message;
      } finally {
        this.photoUploading = { ...this.photoUploading, [item.id]: false };
      }
    },
    async createWorkOrder(item) {
      let response = this.responseFor(item.id);
      if (!response.finding_description) {
        alert("Agrega un hallazgo antes de crear OT.");
        return;
      }
      if (response.work_order_id) return;

      try {
        if (!response.id) {
          await axios.post(`/api/maintenance/visits/${this.visitId}/checklist`, {
            responses: [
              {
                maintenance_checklist_item_id: item.id,
                review_status: response.review_status || null,
                observations: response.observations || null,
                finding_description: response.finding_description || null,
              },
            ],
          });
          await this.load();
          response = this.responseFor(item.id);
        }

        const result = await axios.post(`/api/maintenance/visit-checklist-responses/${response.id}/create-work-order`, {});
        this.success = result.data.message;
        await this.load();
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors ? Object.values(errors).flat().join(" ") : error.response?.data?.message || error.message;
      }
    },
    dependencyLabel(dep) {
      if (!dep) return "-";
      return `${dep.code} · ${dep.name}`;
    },
    formatDMY(value) {
      if (!value) return "-";
      const [y, m, d] = String(value).slice(0, 10).split("-");
      if (!y || !m || !d) return String(value);
      return `${d}-${m}-${y}`;
    },
    exportExcel() {
      const escapeHtml = (value) =>
        String(value ?? "")
          .replaceAll("&", "&amp;")
          .replaceAll("<", "&lt;")
          .replaceAll(">", "&gt;")
          .replaceAll('"', "&quot;")
          .replaceAll("'", "&#039;");

      const header = ["Sistema", "Subdimensión", "Revisión", "Estado", "Observaciones", "Hallazgos"];
      const rows = this.items.map((item) => {
        const r = this.responseFor(item.id);
        return [
          item.system,
          item.subdimension || "",
          item.review,
          r.review_status || "",
          r.observations || "",
          r.finding_description || "",
        ];
      });

      const html = `<!doctype html><html><head><meta charset=\"utf-8\"/></head><body>
        <h3>Checklist visita</h3>
        <p>Dependencia: ${escapeHtml(this.dependencyLabel(this.visit?.dependency))}</p>
        <p>Responsable: ${escapeHtml(this.visit?.responsible || "")} · Fecha: ${escapeHtml(this.formatDMY(this.visit?.visit_date))}</p>
        <table border=\"1\">
          <thead><tr>${header.map((h) => `<th>${escapeHtml(h)}</th>`).join("")}</tr></thead>
          <tbody>
            ${rows.map((r) => `<tr>${r.map((c) => `<td>${escapeHtml(c)}</td>`).join("")}</tr>`).join("")}
          </tbody>
        </table>
      </body></html>`;

      const blob = new Blob([html], { type: "application/vnd.ms-excel;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `checklist-visita-${this.visitId}.xls`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    },
    async exportPdf() {
      const fetchImageAsDataUrl = async (url) => {
        if (!url) return null;
        try {
          const absolute = url.startsWith("http") ? url : url.startsWith("/") ? url : `/${url}`;
          const res = await fetch(absolute);
          if (!res.ok) return null;
          const blob = await res.blob();
          return await new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(blob);
          });
        } catch {
          return null;
        }
      };

      const headerRow = ["Sistema", "Subdimensión", "Revisión", "Estado", "Obs", "Hallazgo"].map((t) => ({ text: t, bold: true }));
      const body = [headerRow];

      for (const item of this.items) {
        const r = this.responseFor(item.id);
        body.push([
          item.system,
          item.subdimension || "",
          item.review,
          r.review_status || "",
          (r.observations || "").slice(0, 60),
          (r.finding_description || "").slice(0, 60),
        ]);
      }

      const detailSections = [];
      const itemsWithPhoto = this.items.filter((item) => this.responseFor(item.id).photo_url);

      for (const item of itemsWithPhoto) {
        const r = this.responseFor(item.id);
        const photo = await fetchImageAsDataUrl(r.photo_url);
        detailSections.push(
          { text: `${item.system} / ${item.subdimension || "Sin subdimensión"}`, style: "taskTitle", pageBreak: detailSections.length ? "before" : undefined },
          { text: item.review, margin: [0, 0, 0, 6] },
          { text: `Estado: ${r.review_status || "-"}`, margin: [0, 0, 0, 6] },
          { text: `Observaciones: ${r.observations || "-"}`, margin: [0, 0, 0, 6] },
          { text: `Hallazgo: ${r.finding_description || "-"}`, margin: [0, 0, 0, 6] },
          photo ? { image: photo, width: 500 } : { text: "Sin foto disponible.", italics: true, color: "#666" }
        );
      }

      const docDefinition = {
        pageOrientation: "landscape",
        content: [
          { text: "Checklist de visita", style: "header" },
          { text: `Dependencia: ${this.dependencyLabel(this.visit?.dependency)}` },
          { text: `Responsable: ${this.visit?.responsible || "-"} · Fecha: ${this.formatDMY(this.visit?.visit_date)}`, margin: [0, 0, 0, 10] },
          {
            table: {
              headerRows: 1,
              widths: [90, 120, "*", 60, "*", "*"],
              body,
            },
            layout: "lightHorizontalLines",
          },
          itemsWithPhoto.length ? { text: "Detalle con fotos", style: "subheader", pageBreak: "before" } : null,
          ...detailSections,
        ].filter(Boolean),
        styles: {
          header: { fontSize: 16, bold: true, margin: [0, 0, 0, 10] },
          subheader: { fontSize: 13, bold: true, margin: [0, 12, 0, 6] },
          taskTitle: { fontSize: 13, bold: true, margin: [0, 0, 0, 8] },
        },
      };

      pdfMake.createPdf(docDefinition).download(`checklist-visita-${this.visitId}.pdf`);
    },
  },
};
</script>

<template>
  <Layout>
    <BRow>
      <BCol cols="12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0 font-size-18">Checklist de visita</h4>
          <div class="page-title-right">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="javascript:void(0)" @click="$router.push('/maintenance/visits')">Visitas</a></li>
              <li class="breadcrumb-item active">Checklist</li>
            </ol>
          </div>
        </div>
      </BCol>
    </BRow>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>

    <BCard no-body class="mb-3" v-if="visit">
      <BCardBody>
        <div class="d-flex flex-wrap justify-content-between gap-2">
          <div>
            <div class="text-muted">Dependencia</div>
            <div class="fw-bold">{{ dependencyLabel(visit.dependency) }}</div>
          </div>
          <div>
            <div class="text-muted">Responsable</div>
            <div class="fw-bold">{{ visit.responsible }}</div>
          </div>
          <div>
            <div class="text-muted">Fecha</div>
            <div class="fw-bold">{{ formatDMY(visit.visit_date) }} {{ visit.visit_time ? String(visit.visit_time).slice(11, 16) : "" }}</div>
          </div>
          <div>
            <div class="text-muted">Tipo / Estado</div>
            <div class="fw-bold">{{ visit.visit_type }} · {{ visit.status }}</div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <button class="btn btn-outline-success" type="button" @click="exportExcel">Exportar Excel</button>
          <button class="btn btn-outline-danger" type="button" @click="exportPdf">Exportar PDF</button>
          <button class="btn btn-primary" type="button" :disabled="saving" @click="saveChecklist">
            {{ saving ? "Guardando..." : "Guardar checklist" }}
          </button>
        </div>
      </BCardBody>
    </BCard>

    <BCard no-body>
      <BCardBody>
        <div v-if="loading" class="text-muted text-center py-4">Cargando...</div>
        <div v-else-if="items.length === 0" class="text-muted text-center py-4">No hay ítems de checklist.</div>

        <div v-else>
          <div v-for="(subGroups, system) in groupedItems" :key="system" class="mb-4">
            <h5 class="mb-3">{{ system }}</h5>

            <div v-for="(list, sub) in subGroups" :key="sub" class="mb-3">
              <h6 class="text-muted mb-2">{{ sub }}</h6>

              <div v-for="item in list" :key="item.id" class="border rounded p-3 mb-2">
                <div class="fw-bold mb-2">{{ item.review }}</div>

                <BRow>
                  <BCol md="2">
                    <label class="form-label">Estado</label>
                    <select
                      class="form-select"
                      :value="responseFor(item.id).review_status"
                      @change="setResponse(item.id, { review_status: $event.target.value })"
                    >
                      <option value="">-</option>
                      <option v-for="s in catalogs.review_statuses" :key="s" :value="s">{{ s }}</option>
                    </select>
                  </BCol>
                  <BCol md="5">
                    <label class="form-label">Observaciones</label>
                    <input
                      class="form-control"
                      type="text"
                      :value="responseFor(item.id).observations"
                      @input="setResponse(item.id, { observations: $event.target.value })"
                    />
                  </BCol>
                  <BCol md="5">
                    <label class="form-label">Hallazgo</label>
                    <input
                      class="form-control"
                      type="text"
                      :value="responseFor(item.id).finding_description"
                      @input="setResponse(item.id, { finding_description: $event.target.value })"
                    />
                  </BCol>
                </BRow>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                  <input :ref="`photo_${item.id}`" class="form-control" type="file" accept="image/*" style="max-width: 340px" />
                  <button class="btn btn-outline-secondary" type="button" :disabled="photoUploading[item.id]" @click="uploadPhoto(item)">
                    {{ photoUploading[item.id] ? "Subiendo..." : "Subir foto" }}
                  </button>
                  <a v-if="responseFor(item.id).photo_url" class="btn btn-outline-primary" :href="responseFor(item.id).photo_url" target="_blank" rel="noopener">
                    Ver foto
                  </a>
                  <button
                    v-if="responseFor(item.id).finding_description && !responseFor(item.id).work_order_id && responseFor(item.id).id"
                    class="btn btn-primary"
                    type="button"
                    @click="createWorkOrder(item)"
                  >
                    Generar OT
                  </button>
                  <span v-else-if="responseFor(item.id).work_order_id" class="badge bg-success">OT #{{ responseFor(item.id).work_order_id }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </BCardBody>
    </BCard>
  </Layout>
</template>
