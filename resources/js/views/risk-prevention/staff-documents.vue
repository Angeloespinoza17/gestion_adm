<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import {
  downloadRiskFile,
  formatRiskDate,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
} from "../../components/risk-prevention/module-utils";

const typeOptions = [
  { value: "", text: "Todos los tipos" },
  { value: "protocolo", text: "Protocolos" },
  { value: "reglamento", text: "Reglamentos" },
  { value: "instructivo", text: "Instructivos" },
  { value: "informe", text: "Informes" },
];

export default {
  components: { Layout, LoadingState, StatusBadge },
  data() {
    return {
      loading: false,
      downloadingId: null,
      items: [],
      filters: { search: "", document_type: "" },
      pagination: { current_page: 1, per_page: 15, total: 0 },
      typeOptions,
    };
  },
  mounted() {
    this.loadItems();
  },
  methods: {
    formatRiskDate,
    async loadItems(page = this.pagination.current_page) {
      this.loading = true;
      try {
        const response = await axios.get("/api/risk-prevention/disseminated-documents", {
          params: {
            ...this.filters,
            page,
            per_page: this.pagination.per_page,
          },
        });

        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          per_page: response.data.per_page || 15,
          total: response.data.total || 0,
        };
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudieron cargar los documentos disponibles."));
      } finally {
        this.loading = false;
      }
    },
    search() {
      this.pagination.current_page = 1;
      this.loadItems(1);
    },
    clearFilters() {
      this.filters = { search: "", document_type: "" };
      this.search();
    },
    async download(item) {
      this.downloadingId = item.id;
      try {
        await downloadRiskFile(
          `/api/risk-prevention/disseminated-documents/${item.id}/download`,
          item.document_name || item.title,
        );
        await showRiskSuccess("La descarga comenzó correctamente.", "Archivo preparado");
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo descargar el documento."));
      } finally {
        this.downloadingId = null;
      }
    },
    extension(item) {
      return (
        item.file_extension
        || String(item.document_name || "").split(".").pop()
        || "archivo"
      ).toUpperCase();
    },
    formatBytes(value) {
      const bytes = Number(value || 0);
      if (!bytes) return "-";
      if (bytes < 1024) return `${bytes} B`;
      if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
      return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    },
    typeLabel(value) {
      return typeOptions.find((option) => option.value === value)?.text?.replace(/s$/, "") || value || "-";
    },
    fileIcon(item) {
      const extension = String(item.file_extension || "").toLowerCase();
      if (extension === "pdf") return "bx-file";
      if (["xls", "xlsx", "ods", "csv"].includes(extension)) return "bx-spreadsheet";
      if (["ppt", "pptx", "odp"].includes(extension)) return "bx-slideshow";
      if (["jpg", "jpeg", "png", "webp"].includes(extension)) return "bx-image";
      return "bx-file-blank";
    },
  },
};
</script>

<template>
  <Layout>
    <main class="staff-documents-page">
      <section class="staff-documents-hero">
        <div>
          <span class="staff-documents-hero__eyebrow">Documentación preventiva</span>
          <h1>Gestión documental</h1>
          <p>Consulta y descarga los documentos oficiales difundidos para funcionarios.</p>
        </div>
        <span class="staff-documents-hero__illustration">
          <i class="bx bx-shield-quarter"></i>
          <i class="bx bx-file-blank"></i>
        </span>
      </section>

      <section class="staff-documents-toolbar">
        <div class="staff-documents-search">
          <i class="bx bx-search"></i>
          <input
            v-model="filters.search"
            type="search"
            placeholder="Buscar por documento, archivo o responsable"
            @keyup.enter="search"
          />
        </div>
        <BFormSelect v-model="filters.document_type" :options="typeOptions" />
        <BButton variant="primary" @click="search"><i class="bx bx-search"></i> Buscar</BButton>
        <BButton variant="light" @click="clearFilters">Limpiar</BButton>
      </section>

      <section class="staff-documents-content">
        <div class="staff-documents-content__heading">
          <div>
            <h2>Documentos disponibles</h2>
            <p>{{ pagination.total }} {{ pagination.total === 1 ? "documento publicado" : "documentos publicados" }}</p>
          </div>
          <span class="staff-documents-security-note">
            <i class="bx bx-check-shield"></i>
            Documentación oficial
          </span>
        </div>

        <LoadingState v-if="loading" message="Cargando documentos disponibles..." />

        <div v-else-if="items.length" class="staff-documents-grid">
          <article v-for="item in items" :key="item.id" class="staff-document-card">
            <div class="staff-document-card__top">
              <span class="staff-document-card__icon"><i class="bx" :class="fileIcon(item)"></i></span>
              <StatusBadge :status="item.current_status" />
            </div>
            <div class="staff-document-card__body">
              <span class="staff-document-card__type">{{ typeLabel(item.document_type) }}</span>
              <h3>{{ item.title }}</h3>
              <p>{{ item.notes || "Documento preventivo disponible para consulta interna." }}</p>
            </div>
            <dl class="staff-document-card__metadata">
              <div><dt>Versión</dt><dd>v{{ item.version_number }}</dd></div>
              <div><dt>Vigencia</dt><dd>{{ formatRiskDate(item.valid_until) }}</dd></div>
              <div><dt>Formato</dt><dd>{{ extension(item) }} · {{ formatBytes(item.file_size) }}</dd></div>
              <div><dt>Responsable</dt><dd>{{ item.responsible_name || "Prevención de Riesgos" }}</dd></div>
            </dl>
            <footer class="staff-document-card__footer">
              <span :title="item.document_name">{{ item.document_name }}</span>
              <BButton
                variant="primary"
                :disabled="downloadingId === item.id"
                @click="download(item)"
              >
                <i class="bx" :class="downloadingId === item.id ? 'bx-loader-alt bx-spin' : 'bx-download'"></i>
                {{ downloadingId === item.id ? "Preparando..." : "Descargar" }}
              </BButton>
            </footer>
          </article>
        </div>

        <div v-else class="staff-documents-empty">
          <span><i class="bx bx-folder-open"></i></span>
          <h3>No hay documentos disponibles</h3>
          <p>No encontramos documentación difundida con los filtros seleccionados.</p>
          <BButton variant="outline-primary" @click="clearFilters">Limpiar búsqueda</BButton>
        </div>

        <footer v-if="pagination.total > pagination.per_page" class="staff-documents-pagination">
          <span>Página {{ pagination.current_page }}</span>
          <BPagination
            v-model="pagination.current_page"
            :total-rows="pagination.total"
            :per-page="pagination.per_page"
            @update:model-value="loadItems"
          />
        </footer>
      </section>
    </main>
  </Layout>
</template>

<style scoped>
.staff-documents-page {
  --staff-docs-ink: #19243e;
  --staff-docs-muted: #737e95;
  padding: 0.25rem 0 2rem;
}

.staff-documents-hero {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 12.5rem;
  padding: 2rem;
  margin-bottom: 1rem;
  overflow: hidden;
  border-radius: 1.3rem;
  background:
    radial-gradient(circle at 85% 25%, rgba(101, 211, 177, 0.25), transparent 29%),
    linear-gradient(118deg, #152454 0%, #254bab 66%, #23799e 100%);
  box-shadow: 0 18px 38px rgba(34, 64, 132, 0.18);
}

.staff-documents-hero__eyebrow {
  color: #acd3ff;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.staff-documents-hero h1 {
  margin: 0.55rem 0 0.4rem;
  color: white;
  font-size: clamp(1.65rem, 3vw, 2.35rem);
  font-weight: 760;
}

.staff-documents-hero p {
  max-width: 35rem;
  margin: 0;
  color: rgba(255, 255, 255, 0.76);
  font-size: 0.96rem;
}

.staff-documents-hero__illustration {
  position: relative;
  display: block;
  width: 8.5rem;
  height: 7rem;
  color: white;
}

.staff-documents-hero__illustration .bx-shield-quarter {
  position: absolute;
  right: 2.4rem;
  bottom: 0;
  font-size: 6.5rem;
  opacity: 0.16;
}

.staff-documents-hero__illustration .bx-file-blank {
  position: absolute;
  top: 0.3rem;
  right: 0;
  display: grid;
  width: 4.5rem;
  height: 4.5rem;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 1.2rem;
  background: rgba(255, 255, 255, 0.14);
  font-size: 2.3rem;
  backdrop-filter: blur(8px);
}

.staff-documents-toolbar {
  display: grid;
  grid-template-columns: minmax(16rem, 1fr) minmax(11rem, 15rem) auto auto;
  gap: 0.65rem;
  padding: 0.9rem;
  margin-bottom: 1rem;
  border: 1px solid #e4e9f2;
  border-radius: 1rem;
  background: white;
  box-shadow: 0 8px 22px rgba(31, 45, 84, 0.05);
}

.staff-documents-search {
  position: relative;
}

.staff-documents-search i {
  position: absolute;
  top: 50%;
  left: 0.9rem;
  color: #929cb0;
  font-size: 1.1rem;
  transform: translateY(-50%);
}

.staff-documents-search input {
  width: 100%;
  height: 100%;
  min-height: 2.35rem;
  padding: 0.5rem 0.75rem 0.5rem 2.45rem;
  border: 1px solid #ced5e1;
  border-radius: 0.4rem;
  outline: none;
}

.staff-documents-search input:focus {
  border-color: #86a4ef;
  box-shadow: 0 0 0 0.18rem rgba(49, 89, 217, 0.12);
}

.staff-documents-content {
  padding: 1.15rem;
  border: 1px solid #e4e9f2;
  border-radius: 1rem;
  background: #f8f9fc;
}

.staff-documents-content__heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.staff-documents-content h2 {
  margin: 0;
  color: var(--staff-docs-ink);
  font-size: 1.05rem;
}

.staff-documents-content__heading p {
  margin: 0.2rem 0 0;
  color: var(--staff-docs-muted);
  font-size: 0.78rem;
}

.staff-documents-security-note {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.36rem 0.65rem;
  border-radius: 999px;
  background: #e5f7ef;
  color: #147d5e;
  font-size: 0.72rem;
  font-weight: 700;
}

.staff-documents-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.85rem;
}

.staff-document-card {
  display: flex;
  min-height: 22rem;
  overflow: hidden;
  flex-direction: column;
  border: 1px solid #e1e6ef;
  border-radius: 1rem;
  background: white;
  box-shadow: 0 7px 18px rgba(31, 45, 84, 0.055);
  transition: 160ms ease;
}

.staff-document-card:hover {
  border-color: #c8d4ed;
  box-shadow: 0 13px 26px rgba(31, 52, 105, 0.1);
  transform: translateY(-2px);
}

.staff-document-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 1rem 1rem 0;
}

.staff-document-card__icon {
  display: grid;
  width: 3rem;
  height: 3rem;
  place-items: center;
  border-radius: 0.9rem;
  background: #eaf0ff;
  color: #3159d9;
  font-size: 1.45rem;
}

.staff-document-card__body {
  padding: 0.85rem 1rem 0;
}

.staff-document-card__type {
  color: #3159d9;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.staff-document-card h3 {
  display: -webkit-box;
  min-height: 2.65rem;
  margin: 0.4rem 0 0.35rem;
  overflow: hidden;
  color: var(--staff-docs-ink);
  font-size: 1rem;
  line-height: 1.3;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.staff-document-card__body p {
  display: -webkit-box;
  min-height: 2.25rem;
  margin: 0;
  overflow: hidden;
  color: var(--staff-docs-muted);
  font-size: 0.76rem;
  line-height: 1.45;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.staff-document-card__metadata {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
  padding: 0.85rem 1rem;
  margin: 0.85rem 0 0;
  border-top: 1px solid #edf0f5;
}

.staff-document-card__metadata dt {
  margin-bottom: 0.18rem;
  color: #969fb1;
  font-size: 0.64rem;
  font-weight: 700;
  text-transform: uppercase;
}

.staff-document-card__metadata dd {
  margin: 0;
  overflow: hidden;
  color: #435069;
  font-size: 0.74rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.staff-document-card__footer {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.8rem 1rem;
  margin-top: auto;
  border-top: 1px solid #edf0f5;
  background: #fbfcfe;
}

.staff-document-card__footer > span {
  min-width: 0;
  overflow: hidden;
  flex: 1;
  color: #8b95a8;
  font-size: 0.68rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.staff-document-card__footer :deep(.btn) {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border-radius: 0.6rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.staff-documents-empty {
  padding: 4rem 1rem;
  color: var(--staff-docs-muted);
  text-align: center;
}

.staff-documents-empty > span {
  display: grid;
  width: 4rem;
  height: 4rem;
  margin: 0 auto 1rem;
  place-items: center;
  border-radius: 1.2rem;
  background: #eaf0ff;
  color: #3159d9;
  font-size: 2rem;
}

.staff-documents-empty h3 { margin: 0; color: var(--staff-docs-ink); font-size: 1.05rem; }
.staff-documents-empty p { margin: 0.4rem 0 1rem; }

.staff-documents-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 1rem;
  color: var(--staff-docs-muted);
  font-size: 0.75rem;
}

.staff-documents-pagination :deep(.pagination) { margin: 0; }

@media (max-width: 1199.98px) {
  .staff-documents-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 767.98px) {
  .staff-documents-hero { min-height: auto; padding: 1.4rem; }
  .staff-documents-hero__illustration { display: none; }
  .staff-documents-toolbar { grid-template-columns: 1fr 1fr; }
  .staff-documents-search { grid-column: 1 / -1; }
  .staff-documents-grid { grid-template-columns: 1fr; }
}

@media (max-width: 479.98px) {
  .staff-documents-toolbar { grid-template-columns: 1fr; }
  .staff-documents-content__heading { flex-direction: column; }
}
</style>
