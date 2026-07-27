<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";

export default {
  components: { Layout },
  data() {
    return {
      backups: [],
      meta: {
        count: 0,
        total_size_human: "0 B",
        retention_days: 0,
      },
      loading: true,
      downloading: null,
      error: "",
    };
  },
  mounted() {
    this.loadBackups();
  },
  methods: {
    async loadBackups() {
      this.loading = true;
      this.error = "";

      try {
        const response = await axios.get("/api/admin/backups");
        this.backups = response.data.data || [];
        this.meta = response.data.meta || this.meta;
      } catch (error) {
        this.error = error.response?.data?.message || "No fue posible cargar los respaldos.";
      } finally {
        this.loading = false;
      }
    },
    async downloadBackup(backup) {
      this.downloading = backup.id;

      try {
        const response = await axios.get(
          `/api/admin/backups/${encodeURIComponent(backup.id)}/download`,
          { responseType: "blob" },
        );
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement("a");
        link.href = url;
        link.download = backup.filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
      } catch (error) {
        await Swal.fire({
          icon: "error",
          title: "No se pudo descargar",
          text: error.response?.data?.message || "El respaldo ya no está disponible.",
          confirmButtonText: "Entendido",
        });
      } finally {
        this.downloading = null;
      }
    },
    formatDate(value) {
      if (!value) return "Sin fecha";

      return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "long",
        timeStyle: "short",
      }).format(new Date(value));
    },
  },
};
</script>

<template>
  <Layout>
    <BRow>
      <BCol cols="12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <div>
            <h4 class="mb-1 font-size-18">Respaldos</h4>
            <p class="text-muted mb-0">Copias de seguridad disponibles de la base de datos.</p>
          </div>
          <BButton variant="outline-primary" :disabled="loading" @click="loadBackups">
            <i class="bx bx-refresh me-1"></i>
            Actualizar
          </BButton>
        </div>
      </BCol>
    </BRow>

    <BRow class="mb-4">
      <BCol md="4">
        <BCard class="backup-metric h-100 mb-3 mb-md-0">
          <div class="d-flex align-items-center gap-3">
            <span class="backup-metric__icon backup-metric__icon--primary">
              <i class="bx bx-data"></i>
            </span>
            <div>
              <div class="text-muted font-size-13">Respaldos disponibles</div>
              <div class="backup-metric__value">{{ meta.count }}</div>
            </div>
          </div>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard class="backup-metric h-100 mb-3 mb-md-0">
          <div class="d-flex align-items-center gap-3">
            <span class="backup-metric__icon backup-metric__icon--success">
              <i class="bx bx-hdd"></i>
            </span>
            <div>
              <div class="text-muted font-size-13">Espacio utilizado</div>
              <div class="backup-metric__value">{{ meta.total_size_human }}</div>
            </div>
          </div>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard class="backup-metric h-100">
          <div class="d-flex align-items-center gap-3">
            <span class="backup-metric__icon backup-metric__icon--info">
              <i class="bx bx-calendar-check"></i>
            </span>
            <div>
              <div class="text-muted font-size-13">Retención configurada</div>
              <div class="backup-metric__value">{{ meta.retention_days }} días</div>
            </div>
          </div>
        </BCard>
      </BCol>
    </BRow>

    <BAlert v-if="error" show variant="danger" class="d-flex align-items-center">
      <i class="bx bx-error-circle font-size-20 me-2"></i>
      <span>{{ error }}</span>
    </BAlert>

    <BCard no-body class="backup-card">
      <BCardHeader class="bg-transparent border-bottom d-flex align-items-center justify-content-between">
        <div>
          <h5 class="mb-1">Historial de respaldos</h5>
          <p class="text-muted mb-0 font-size-13">Ordenados desde el más reciente.</p>
        </div>
        <span class="badge bg-light text-dark">{{ meta.count }} archivos</span>
      </BCardHeader>

      <BCardBody v-if="loading" class="text-center py-5">
        <BSpinner variant="primary" class="mb-3"></BSpinner>
        <p class="text-muted mb-0">Consultando respaldos disponibles...</p>
      </BCardBody>

      <BCardBody v-else-if="backups.length === 0" class="text-center py-5">
        <span class="backup-empty-icon">
          <i class="bx bx-archive-in"></i>
        </span>
        <h5 class="mt-3 mb-2">No hay respaldos disponibles</h5>
        <p class="text-muted mb-0">Cuando se genere una copia de la base de datos aparecerá aquí.</p>
      </BCardBody>

      <div v-else class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Fecha de creación</th>
              <th>Archivo</th>
              <th>Formato</th>
              <th>Tamaño</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="backup in backups" :key="backup.id">
              <td>
                <div class="fw-semibold">{{ formatDate(backup.created_at) }}</div>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="backup-file-icon"><i class="bx bx-file-blank"></i></span>
                  <code class="backup-filename">{{ backup.filename }}</code>
                </div>
              </td>
              <td><span class="badge bg-soft-primary text-primary">{{ backup.format }}</span></td>
              <td>{{ backup.size_human }}</td>
              <td class="text-end">
                <BButton
                  variant="primary"
                  size="sm"
                  :disabled="downloading === backup.id"
                  @click="downloadBackup(backup)"
                >
                  <BSpinner v-if="downloading === backup.id" small class="me-1"></BSpinner>
                  <i v-else class="bx bx-download me-1"></i>
                  {{ downloading === backup.id ? "Descargando..." : "Descargar" }}
                </BButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>

    <BAlert show variant="info" class="mt-4 mb-0">
      <i class="bx bx-shield-quarter me-2"></i>
      Esta sección es exclusiva para Super Admin. La descarga no modifica ni elimina información de producción.
    </BAlert>
  </Layout>
</template>

<style scoped>
.backup-metric,
.backup-card {
  border: 1px solid #e9edf5;
  box-shadow: 0 8px 24px rgba(20, 37, 63, 0.05);
}

.backup-metric__icon,
.backup-file-icon,
.backup-empty-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.backup-metric__icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  font-size: 24px;
}

.backup-metric__icon--primary {
  color: #556ee6;
  background: rgba(85, 110, 230, 0.12);
}

.backup-metric__icon--success {
  color: #34c38f;
  background: rgba(52, 195, 143, 0.12);
}

.backup-metric__icon--info {
  color: #50a5f1;
  background: rgba(80, 165, 241, 0.12);
}

.backup-metric__value {
  margin-top: 2px;
  color: #263238;
  font-size: 1.35rem;
  font-weight: 700;
}

.backup-file-icon {
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  border-radius: 9px;
  color: #556ee6;
  background: rgba(85, 110, 230, 0.1);
  font-size: 18px;
}

.backup-filename {
  color: #495057;
  font-size: 0.82rem;
  word-break: break-all;
}

.backup-empty-icon {
  width: 68px;
  height: 68px;
  border-radius: 20px;
  color: #74788d;
  background: #f5f6f8;
  font-size: 34px;
}

@media (max-width: 767.98px) {
  .page-title-box {
    align-items: flex-start !important;
    gap: 1rem;
  }
}
</style>
