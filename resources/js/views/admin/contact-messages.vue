<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      search: "",
      statusFilter: "",
      items: [],
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      catalogs: {
        statuses: [],
        stats: {},
      },
      selected: null,
      detailForm: {
        status: "read",
        internal_notes: "",
      },
      showDetailModal: false,
      error: null,
      success: null,
    };
  },
  computed: {
    statusOptions() {
      return [{ value: "", text: "Todos" }].concat(
        (this.catalogs.statuses || []).map((status) => ({ value: status.value, text: status.label }))
      );
    },
    formStatusOptions() {
      return (this.catalogs.statuses || []).map((status) => ({ value: status.value, text: status.label }));
    },
  },
  mounted() {
    this.loadCatalogs();
    this.load();
  },
  methods: {
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/admin/contact-messages/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/admin/contact-messages", {
          params: {
            page,
            search: this.search || null,
            status: this.statusFilter || null,
          },
        });

        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          total: response.data.total || 0,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.search = "";
      this.statusFilter = "";
      this.load();
    },
    async openDetail(item) {
      this.error = null;
      this.success = null;

      try {
        const response = await axios.get(`/api/admin/contact-messages/${item.id}`);
        this.selected = response.data.data;
        this.detailForm = {
          status: this.selected.status || "read",
          internal_notes: this.selected.internal_notes || "",
        };
        this.showDetailModal = true;
        await this.loadCatalogs();
        await this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async saveDetail() {
      if (!this.selected) return;

      this.saving = true;
      this.error = null;

      try {
        const response = await axios.put(`/api/admin/contact-messages/${this.selected.id}`, this.detailForm);
        this.selected = response.data.data;
        this.detailForm = {
          status: this.selected.status || "read",
          internal_notes: this.selected.internal_notes || "",
        };
        this.success = response.data.message || "Mensaje actualizado.";
        await this.loadCatalogs();
        await this.load(this.pagination.current_page);
        await Swal.fire({
          title: "Listo",
          text: this.success,
          icon: "success",
          confirmButtonText: "Aceptar",
        });
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async setStatus(item, status) {
      const result = await Swal.fire({
        title: "Actualizar estado",
        text: `${item.subject} quedará como ${this.statusLabel(status)}.`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Actualizar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        const response = await axios.put(`/api/admin/contact-messages/${item.id}`, {
          status,
          internal_notes: item.internal_notes || "",
        });
        this.success = response.data.message || "Mensaje actualizado.";
        await this.loadCatalogs();
        await this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async remove(item) {
      const result = await Swal.fire({
        title: "Eliminar mensaje",
        text: `${item.full_name} - ${item.subject}`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        const response = await axios.delete(`/api/admin/contact-messages/${item.id}`);
        this.success = response.data.message || "Mensaje eliminado.";
        await this.loadCatalogs();
        await this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    statusLabel(value) {
      const status = (this.catalogs.statuses || []).find((entry) => entry.value === value);
      return status?.label || value || "-";
    },
    statusClass(value) {
      return {
        new: "status-new",
        read: "status-read",
        responded: "status-responded",
        archived: "status-archived",
      }[value] || "status-default";
    },
    initials(value) {
      const initials = String(value || "")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join("")
        .toUpperCase();

      return initials || "C";
    },
    formatDate(value) {
      if (!value) return "-";

      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(value));
    },
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
    },
    truncate(value, length = 120) {
      const text = String(value || "");
      return text.length > length ? `${text.slice(0, length)}...` : text;
    },
    mailto(item) {
      const subject = encodeURIComponent(`Respuesta: ${item.subject || "Contacto Colegio Nuestra Señora del Carmen"}`);
      return `mailto:${item.email}?subject=${subject}`;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "No se pudo gestionar el mensaje."
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Contactos del sitio web</h4>
        <div class="text-muted">Registro de mensajes enviados desde /contacto.</div>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <div class="row g-3 mb-3 contact-stat-grid">
      <div class="col-md-6 col-xl-3">
        <BCard class="h-100 contact-stat-card">
          <div class="contact-stat-icon contact-stat-icon-danger"><i class="bx bx-envelope"></i></div>
          <div>
            <div class="contact-stat-label">Nuevos</div>
            <div class="contact-stat-value text-danger">{{ formatNumber(catalogs.stats?.new) }}</div>
          </div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl-3">
        <BCard class="h-100 contact-stat-card">
          <div class="contact-stat-icon contact-stat-icon-primary"><i class="bx bx-show"></i></div>
          <div>
            <div class="contact-stat-label">Leídos</div>
            <div class="contact-stat-value text-primary">{{ formatNumber(catalogs.stats?.read) }}</div>
          </div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl-3">
        <BCard class="h-100 contact-stat-card">
          <div class="contact-stat-icon contact-stat-icon-success"><i class="bx bx-check-circle"></i></div>
          <div>
            <div class="contact-stat-label">Respondidos</div>
            <div class="contact-stat-value text-success">{{ formatNumber(catalogs.stats?.responded) }}</div>
          </div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl-3">
        <BCard class="h-100 contact-stat-card">
          <div class="contact-stat-icon contact-stat-icon-neutral"><i class="bx bx-layer"></i></div>
          <div>
            <div class="contact-stat-label">Total</div>
            <div class="contact-stat-value">{{ formatNumber(catalogs.stats?.total) }}</div>
          </div>
        </BCard>
      </div>
    </div>

    <BCard class="mb-3 contact-filter-card">
      <div class="row g-3 align-items-end">
        <div class="col-lg-6">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="search" placeholder="Nombre, correo, teléfono, asunto o mensaje" @keyup.enter="load" />
        </div>
        <div class="col-md-4 col-lg-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="statusFilter" :options="statusOptions" />
        </div>
        <div class="col-md-8 col-lg-3 d-flex gap-2 contact-filter-actions">
          <BButton variant="primary" @click="load()">
            <i class="bx bx-filter-alt me-1"></i>
            Filtrar
          </BButton>
          <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="contact-list-card">
      <BTable
        :items="items"
        :busy="loading"
        responsive
        hover
        small
        show-empty
        table-class="contact-table align-middle mb-0"
        :fields="[
          { key: 'contact', label: 'Contacto', thClass: 'contact-th contact-col', tdClass: 'contact-td contact-col' },
          { key: 'message', label: 'Mensaje', thClass: 'contact-th message-col', tdClass: 'contact-td message-col' },
          { key: 'status', label: 'Estado', thClass: 'contact-th status-col', tdClass: 'contact-td status-col' },
          { key: 'created_at', label: 'Recibido', thClass: 'contact-th date-col', tdClass: 'contact-td date-col' },
          { key: 'actions', label: 'Acciones', thClass: 'contact-th actions-col text-end', tdClass: 'contact-td actions-col text-end' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando mensajes..." compact />
        </template>
        <template #empty>
          <div class="contact-empty-state">
            <i class="bx bx-inbox"></i>
            <span>No hay mensajes para los filtros seleccionados.</span>
          </div>
        </template>
        <template #cell(contact)="{ item }">
          <div class="contact-person-cell">
            <div class="contact-avatar">{{ initials(item.full_name) }}</div>
            <div class="contact-cell">
              <div class="contact-name text-truncate">{{ item.full_name }}</div>
              <a :href="mailto(item)" class="contact-email text-truncate">{{ item.email }}</a>
              <div v-if="item.phone" class="contact-phone text-truncate">{{ item.phone }}</div>
            </div>
          </div>
        </template>
        <template #cell(message)="{ item }">
          <div class="message-cell">
            <button type="button" class="message-subject" @click="openDetail(item)">{{ item.subject }}</button>
            <div class="message-preview">{{ truncate(item.message, 150) }}</div>
          </div>
        </template>
        <template #cell(status)="{ item }">
          <span :class="['contact-status-chip', statusClass(item.status)]">{{ statusLabel(item.status) }}</span>
        </template>
        <template #cell(created_at)="{ item }">
          <span class="contact-date">{{ formatDate(item.created_at) }}</span>
        </template>
        <template #cell(actions)="{ item }">
          <div class="contact-row-actions">
            <BButton
              size="sm"
              variant="outline-primary"
              class="contact-action-btn"
              title="Ver detalle"
              :aria-label="`Ver mensaje de ${item.full_name}`"
              @click="openDetail(item)"
            >
              <i class="bx bx-show"></i>
            </BButton>
            <BButton
              size="sm"
              variant="outline-success"
              class="contact-action-btn"
              title="Responder por correo"
              :aria-label="`Responder a ${item.full_name}`"
              :href="mailto(item)"
            >
              <i class="bx bx-envelope"></i>
            </BButton>
            <BButton
              v-if="item.status !== 'responded'"
              size="sm"
              variant="outline-success"
              class="contact-action-btn"
              title="Marcar respondido"
              :aria-label="`Marcar como respondido el mensaje de ${item.full_name}`"
              @click="setStatus(item, 'responded')"
            >
              <i class="bx bx-check-circle"></i>
            </BButton>
            <BButton
              v-if="item.status !== 'archived'"
              size="sm"
              variant="outline-secondary"
              class="contact-action-btn"
              title="Archivar"
              :aria-label="`Archivar mensaje de ${item.full_name}`"
              @click="setStatus(item, 'archived')"
            >
              <i class="bx bx-archive"></i>
            </BButton>
            <BButton
              size="sm"
              variant="outline-danger"
              class="contact-action-btn"
              title="Eliminar"
              :aria-label="`Eliminar mensaje de ${item.full_name}`"
              @click="remove(item)"
            >
              <i class="bx bx-trash"></i>
            </BButton>
          </div>
        </template>
      </BTable>

      <div class="contact-pagination">
        <div class="text-muted small">{{ pagination.total }} mensaje(s)</div>
        <div class="d-flex align-items-center gap-2">
          <BButton size="sm" variant="outline-secondary" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">
            Anterior
          </BButton>
          <span class="small">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
          <BButton
            size="sm"
            variant="outline-secondary"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="load(pagination.current_page + 1)"
          >
            Siguiente
          </BButton>
        </div>
      </div>
    </BCard>

    <BModal v-model="showDetailModal" title="Mensaje de contacto" size="xl" hide-footer scrollable>
      <div v-if="selected" class="contact-detail">
        <div class="contact-detail-header">
          <div class="contact-detail-heading">
            <span class="contact-detail-kicker">Recibido {{ formatDate(selected.created_at) }}</span>
            <h5>{{ selected.subject }}</h5>
          </div>
          <span :class="['contact-status-chip contact-status-chip-lg', statusClass(selected.status)]">{{ statusLabel(selected.status) }}</span>
        </div>

        <div class="contact-detail-layout">
          <section class="contact-message-panel">
            <div class="contact-message-author">
              <div class="contact-avatar contact-avatar-lg">{{ initials(selected.full_name) }}</div>
              <div class="min-w-0">
                <div class="contact-name">{{ selected.full_name }}</div>
                <a :href="mailto(selected)" class="contact-email text-truncate">{{ selected.email }}</a>
              </div>
            </div>

            <div class="contact-message-body">{{ selected.message }}</div>
          </section>

          <aside class="contact-management-panel">
            <div class="contact-panel-title">Datos del contacto</div>
            <div class="contact-detail-list">
              <div>
                <span>Teléfono</span>
                <strong>{{ selected.phone || "No informado" }}</strong>
              </div>
              <div>
                <span>Origen</span>
                <strong>{{ selected.source_page || "/contacto" }}</strong>
              </div>
              <div>
                <span>Leído</span>
                <strong>{{ formatDate(selected.read_at) }}</strong>
              </div>
              <div>
                <span>Respondido</span>
                <strong>{{ formatDate(selected.responded_at) }}</strong>
              </div>
              <div v-if="selected.handled_by">
                <span>Última gestión</span>
                <strong>{{ selected.handled_by.name || selected.handled_by.email }}</strong>
              </div>
            </div>

            <div class="contact-management-form">
              <div>
                <label class="form-label">Estado</label>
                <BFormSelect v-model="detailForm.status" :options="formStatusOptions" />
              </div>
              <div>
                <label class="form-label">Notas internas</label>
                <BFormTextarea v-model="detailForm.internal_notes" rows="5" placeholder="Seguimiento, responsable o acuerdo interno" />
              </div>
            </div>
          </aside>
        </div>

        <div class="contact-detail-actions">
          <BButton variant="outline-secondary" @click="showDetailModal = false">Cerrar</BButton>
          <BButton variant="outline-success" :href="mailto(selected)">
            <i class="bx bx-envelope me-1"></i>
            Responder por correo
          </BButton>
          <BButton variant="primary" :disabled="saving" @click="saveDetail">
            <i class="bx bx-save me-1"></i>
            {{ saving ? "Guardando..." : "Guardar gestión" }}
          </BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.contact-stat-card {
  border: 1px solid #e8edf7;
  box-shadow: 0 10px 26px rgba(18, 38, 63, 0.05);
}

.contact-stat-card :deep(.card-body) {
  align-items: center;
  display: flex;
  gap: 0.9rem;
  min-height: 5.6rem;
}

.contact-stat-icon {
  align-items: center;
  border-radius: 8px;
  display: inline-flex;
  flex: 0 0 2.65rem;
  height: 2.65rem;
  justify-content: center;
  width: 2.65rem;
}

.contact-stat-icon i {
  font-size: 1.35rem;
}

.contact-stat-icon-danger {
  background: rgba(244, 106, 106, 0.12);
  color: #d64f4f;
}

.contact-stat-icon-primary {
  background: rgba(85, 110, 230, 0.12);
  color: #556ee6;
}

.contact-stat-icon-success {
  background: rgba(52, 195, 143, 0.13);
  color: #22a978;
}

.contact-stat-icon-neutral {
  background: #eef2f7;
  color: #495057;
}

.contact-stat-label {
  color: #74788d;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0;
}

.contact-stat-value {
  color: #343a40;
  font-size: 1.55rem;
  font-weight: 800;
  line-height: 1.15;
}

.contact-filter-card,
.contact-list-card {
  border: 1px solid #e8edf7;
  box-shadow: 0 10px 26px rgba(18, 38, 63, 0.05);
}

.contact-filter-actions .btn {
  min-height: 2.45rem;
}

.contact-cell {
  min-width: 0;
}

.contact-person-cell {
  align-items: center;
  display: flex;
  gap: 0.85rem;
  min-width: 16rem;
}

.contact-avatar {
  align-items: center;
  background: linear-gradient(135deg, #eef2ff, #f8fbff);
  border: 1px solid #dfe6f5;
  border-radius: 8px;
  color: #556ee6;
  display: inline-flex;
  flex: 0 0 2.55rem;
  font-size: 0.82rem;
  font-weight: 800;
  height: 2.55rem;
  justify-content: center;
  width: 2.55rem;
}

.contact-avatar-lg {
  flex-basis: 3.25rem;
  font-size: 1rem;
  height: 3.25rem;
  width: 3.25rem;
}

.contact-name {
  color: #343a40;
  font-weight: 800;
  line-height: 1.25;
}

.contact-email {
  color: #556ee6;
  display: block;
  font-size: 0.86rem;
  font-weight: 700;
  line-height: 1.3;
  max-width: 100%;
}

.contact-phone,
.message-preview,
.contact-date {
  color: #74788d;
  font-size: 0.82rem;
  line-height: 1.35;
}

.message-cell {
  max-width: 34rem;
}

.message-subject {
  background: transparent;
  border: 0;
  color: #343a40;
  display: block;
  font-weight: 800;
  line-height: 1.25;
  max-width: 100%;
  overflow: hidden;
  padding: 0;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.message-subject:hover {
  color: #556ee6;
}

.message-preview {
  display: -webkit-box;
  margin-top: 0.18rem;
  max-width: 100%;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.contact-status-chip {
  align-items: center;
  border-radius: 999px;
  display: inline-flex;
  font-size: 0.76rem;
  font-weight: 800;
  line-height: 1;
  min-height: 1.7rem;
  padding: 0.45rem 0.72rem;
  white-space: nowrap;
}

.contact-status-chip-lg {
  font-size: 0.82rem;
  min-height: 2rem;
  padding: 0.55rem 0.9rem;
}

.status-new {
  background: #fff0f0;
  color: #d34b4b;
}

.status-read {
  background: #eef2ff;
  color: #4b63d9;
}

.status-responded {
  background: #e9fbf4;
  color: #199864;
}

.status-archived,
.status-default {
  background: #f1f3f7;
  color: #5f6678;
}

.contact-row-actions {
  align-items: center;
  display: inline-flex;
  gap: 0.38rem;
  justify-content: flex-end;
  min-width: 11rem;
}

.contact-action-btn {
  align-items: center;
  border-radius: 7px;
  display: inline-flex;
  height: 2.15rem;
  justify-content: center;
  padding: 0;
  width: 2.15rem;
}

.contact-action-btn i {
  font-size: 1.12rem;
  line-height: 1;
}

.contact-pagination {
  align-items: center;
  border-top: 1px solid #eef2f7;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: space-between;
  margin-top: 0;
  padding-top: 1rem;
}

.contact-empty-state {
  align-items: center;
  color: #74788d;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  justify-content: center;
  min-height: 8rem;
}

.contact-empty-state i {
  color: #a6b0cf;
  font-size: 2rem;
}

:deep(.contact-table th) {
  background: #f8faff;
  border-bottom: 1px solid #e8edf7;
  color: #74788d;
  font-size: 0.73rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  padding-bottom: 0.85rem;
  padding-top: 0.85rem;
  text-transform: uppercase;
  white-space: nowrap;
}

:deep(.contact-table td) {
  border-bottom: 1px solid #edf1f7;
  padding-bottom: 0.95rem;
  padding-top: 0.95rem;
  vertical-align: middle;
}

:deep(.contact-table tbody tr:last-child td) {
  border-bottom: 0;
}

:deep(.contact-table tbody tr:hover) {
  background: #fbfdff;
}

:deep(.contact-col) {
  min-width: 17rem;
}

:deep(.message-col) {
  min-width: 24rem;
}

:deep(.status-col) {
  min-width: 8rem;
}

:deep(.date-col) {
  min-width: 10rem;
}

:deep(.actions-col) {
  min-width: 12.5rem;
}

:deep(.modal-body) {
  padding: 0;
}

.contact-detail {
  background: #f6f8fc;
}

.contact-detail-header {
  align-items: flex-start;
  background: #ffffff;
  border-bottom: 1px solid #e8edf7;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: 1.35rem 1.5rem;
}

.contact-detail-heading {
  min-width: 0;
}

.contact-detail-kicker {
  color: #74788d;
  display: block;
  font-size: 0.82rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.contact-detail-heading h5 {
  color: #343a40;
  font-size: 1.25rem;
  font-weight: 800;
  line-height: 1.25;
  margin: 0;
}

.contact-detail-layout {
  display: grid;
  gap: 1rem;
  grid-template-columns: minmax(0, 1.25fr) minmax(20rem, 0.75fr);
  padding: 1rem;
}

.contact-message-panel,
.contact-management-panel {
  background: #ffffff;
  border: 1px solid #e8edf7;
  border-radius: 8px;
}

.contact-message-panel {
  display: flex;
  flex-direction: column;
  min-height: 22rem;
}

.contact-message-author {
  align-items: center;
  border-bottom: 1px solid #eef2f7;
  display: flex;
  gap: 0.9rem;
  padding: 1rem 1.15rem;
}

.contact-message-body {
  color: #343a40;
  flex: 1;
  font-size: 1rem;
  line-height: 1.72;
  padding: 1.15rem;
  white-space: pre-wrap;
}

.contact-management-panel {
  padding: 1rem;
}

.contact-panel-title {
  color: #343a40;
  font-weight: 800;
  margin-bottom: 0.85rem;
}

.contact-detail-list {
  border: 1px solid #eef2f7;
  border-radius: 8px;
  display: grid;
  gap: 0;
  overflow: hidden;
}

.contact-detail-list div {
  background: #fbfcff;
  display: grid;
  gap: 0.2rem;
  padding: 0.72rem 0.85rem;
}

.contact-detail-list div + div {
  border-top: 1px solid #eef2f7;
}

.contact-detail-list span {
  color: #74788d;
  font-size: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
}

.contact-detail-list strong {
  color: #343a40;
  font-size: 0.92rem;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.contact-management-form {
  display: grid;
  gap: 0.95rem;
  margin-top: 1rem;
}

.contact-detail-actions {
  align-items: center;
  background: #ffffff;
  border-top: 1px solid #e8edf7;
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  justify-content: flex-end;
  padding: 1rem 1.5rem;
}

.contact-detail-actions .btn {
  min-height: 2.45rem;
}

@media (max-width: 991px) {
  .contact-detail-layout {
    grid-template-columns: 1fr;
  }

  .contact-management-panel {
    order: -1;
  }
}

@media (max-width: 575px) {
  .contact-stat-card :deep(.card-body) {
    min-height: auto;
  }

  .contact-filter-actions,
  .contact-filter-actions .btn,
  .contact-detail-actions,
  .contact-detail-actions .btn {
    width: 100%;
  }

  .contact-detail-header {
    flex-direction: column;
  }

  .contact-row-actions {
    justify-content: flex-start;
  }
}
</style>
