<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import { formatRiskDate, formatRiskDateTime, formatRiskError, showRiskError, showRiskWarning } from "../../components/risk-prevention/module-utils";

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      loading: false,
      error: null,
      warningShown: false,
      data: {
        metrics: {},
        extinguisher_alert_summary: {},
        extinguisher_alerts: [],
        recent_accidents: [],
        pending_trainings: [],
        epp_due_list: [],
        documents_due_list: [],
      },
    };
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    formatRiskDate,
    formatRiskDateTime,
    async loadDashboard() {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/risk-prevention/dashboard");
        this.data = response.data;
        this.maybeShowAlerts();
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo cargar el dashboard del módulo.");
        showRiskError(this.error);
      } finally {
        this.loading = false;
      }
    },
    async maybeShowAlerts() {
      if (this.warningShown) return;

      const due = Number(this.data.metrics?.extinguishers_due || 0);
      const docs = Number(this.data.metrics?.documents_due || 0);
      if (due <= 0 && docs <= 0) return;

      this.warningShown = true;
      await showRiskWarning(
        `Hay ${due} extintores y ${docs} documentos con vencimiento próximo o vencido.`,
        "Alertas del módulo"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Dashboard de Prevención de Riesgos</h4>
        <div class="text-muted">Control de alertas, vencimientos y trazabilidad preventiva del establecimiento.</div>
      </div>
      <div class="d-flex gap-2">
        <HelpButton
          title="Ayuda del dashboard"
          text="Este panel resume los vencimientos y pendientes críticos del módulo para priorizar acciones preventivas."
        />
        <BButton variant="primary" @click="loadDashboard">Actualizar</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <LoadingState v-if="loading" message="Cargando dashboard de prevención..." />

    <template v-else>
      <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
          <BCard class="shadow-sm border-0 h-100 risk-card risk-card--warning">
            <div class="text-muted small">Extintores por vencer</div>
            <div class="display-6 fw-semibold">{{ data.metrics.extinguishers_due || 0 }}</div>
            <div class="small text-muted">Alertas activas a 30, 15 y 7 días.</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="shadow-sm border-0 h-100 risk-card risk-card--danger">
            <div class="text-muted small">Accidentes del mes</div>
            <div class="display-6 fw-semibold">{{ data.metrics.accidents_month || 0 }}</div>
            <div class="small text-muted">Escolares, laborales y de visitas.</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="shadow-sm border-0 h-100 risk-card risk-card--info">
            <div class="text-muted small">Capacitaciones pendientes</div>
            <div class="display-6 fw-semibold">{{ data.metrics.trainings_pending || 0 }}</div>
            <div class="small text-muted">Funcionarios sin cumplimiento registrado.</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="shadow-sm border-0 h-100 risk-card risk-card--secondary">
            <div class="text-muted small">EPP / Documentos por revisar</div>
            <div class="display-6 fw-semibold">{{ (data.metrics.epp_due || 0) + (data.metrics.documents_due || 0) }}</div>
            <div class="small text-muted">Reposiciones y vigencias documentales.</div>
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-4">
          <BCard class="h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-0">Alertas de extintores</h5>
                <div class="small text-muted">Semáforo de vencimiento.</div>
              </div>
              <HelpButton
                title="Ayuda: alertas de extintores"
                text="Esta sección muestra los extintores próximos a vencer o ya vencidos para programar recarga o reposición."
              />
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <BBadge variant="warning">30 días: {{ data.extinguisher_alert_summary.days_30 || 0 }}</BBadge>
              <BBadge variant="warning">15 días: {{ data.extinguisher_alert_summary.days_15 || 0 }}</BBadge>
              <BBadge variant="danger">7 días: {{ data.extinguisher_alert_summary.days_7 || 0 }}</BBadge>
              <BBadge variant="danger">Vencidos: {{ data.extinguisher_alert_summary.expired || 0 }}</BBadge>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Código</th>
                    <th>Ubicación</th>
                    <th>Vence</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.extinguisher_alerts" :key="item.id">
                    <td class="fw-semibold">{{ item.code }}</td>
                    <td>{{ item.location_label }}</td>
                    <td>{{ formatRiskDate(item.expires_at) }}</td>
                    <td><StatusBadge :status="item.current_status" /></td>
                  </tr>
                  <tr v-if="!data.extinguisher_alerts.length">
                    <td colspan="4" class="text-center text-muted py-3">Sin alertas registradas.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <div class="col-xl-4">
          <BCard class="h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-0">Accidentes recientes</h5>
                <div class="small text-muted">Seguimiento operativo del último período.</div>
              </div>
              <HelpButton
                title="Ayuda: accidentes recientes"
                text="Aquí se muestran los registros más recientes para facilitar el seguimiento y evitar casos sin cierre."
              />
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Persona</th>
                    <th>Lugar</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.recent_accidents" :key="item.id">
                    <td>{{ formatRiskDateTime(item.occurred_at) }}</td>
                    <td>{{ item.involved_person_name }}</td>
                    <td>{{ item.location }}</td>
                    <td><StatusBadge :status="item.case_status" /></td>
                  </tr>
                  <tr v-if="!data.recent_accidents.length">
                    <td colspan="4" class="text-center text-muted py-3">Sin accidentes registrados.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <div class="col-xl-4">
          <BCard class="h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-0">Capacitaciones pendientes</h5>
                <div class="small text-muted">Cumplimiento por funcionario.</div>
              </div>
              <HelpButton
                title="Ayuda: capacitaciones pendientes"
                text="Esta sección ayuda a detectar funcionarios que aún no completan capacitaciones obligatorias o de inducción."
              />
            </div>
            <div v-for="training in data.pending_trainings" :key="training.id" class="border rounded p-2 mb-2 bg-light-subtle">
              <div class="fw-semibold">{{ training.name }}</div>
              <div class="small text-muted mb-1">
                {{ formatRiskDate(training.training_date) }} · {{ training.modality }}
              </div>
              <div class="d-flex flex-wrap gap-1">
                <BBadge v-for="participant in training.participants" :key="participant.id" variant="warning">
                  {{ participant.employee_name }}
                </BBadge>
              </div>
            </div>
            <div v-if="!data.pending_trainings.length" class="text-muted small">No hay pendientes de cumplimiento.</div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-0">EPP por reponer</h5>
                <div class="small text-muted">Entregas con reposición próxima o vencida.</div>
              </div>
              <HelpButton
                title="Ayuda: EPP por reponer"
                text="Permite controlar reposiciones de EPP antes de que se transformen en un incumplimiento operativo."
              />
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Funcionario</th>
                    <th>EPP</th>
                    <th>Reposición</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.epp_due_list" :key="item.id">
                    <td>{{ item.employee_name }}</td>
                    <td>{{ item.item?.name || "-" }}</td>
                    <td>{{ formatRiskDate(item.replacement_due_at) }}</td>
                    <td><StatusBadge :status="item.current_status" /></td>
                  </tr>
                  <tr v-if="!data.epp_due_list.length">
                    <td colspan="4" class="text-center text-muted py-3">No hay reposiciones pendientes.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard class="h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-0">Documentos próximos a vencer</h5>
                <div class="small text-muted">Control de vigencia documental.</div>
              </div>
              <HelpButton
                title="Ayuda: documentos próximos a vencer"
                text="Muestra protocolos, reglamentos, instructivos e informes cuya vigencia requiere revisión o actualización."
              />
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Documento</th>
                    <th>Versión</th>
                    <th>Vence</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.documents_due_list" :key="item.id">
                    <td>{{ item.title }}</td>
                    <td>{{ item.version_number }}</td>
                    <td>{{ formatRiskDate(item.valid_until) }}</td>
                    <td><StatusBadge :status="item.current_status" /></td>
                  </tr>
                  <tr v-if="!data.documents_due_list.length">
                    <td colspan="4" class="text-center text-muted py-3">Sin documentos por revisar.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>
    </template>
  </Layout>
</template>

<style scoped>
.risk-card {
  overflow: hidden;
}

.risk-card--warning {
  background: linear-gradient(135deg, rgba(255, 193, 7, 0.18), rgba(255, 255, 255, 1));
}

.risk-card--danger {
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.14), rgba(255, 255, 255, 1));
}

.risk-card--info {
  background: linear-gradient(135deg, rgba(13, 202, 240, 0.15), rgba(255, 255, 255, 1));
}

.risk-card--secondary {
  background: linear-gradient(135deg, rgba(108, 117, 125, 0.15), rgba(255, 255, 255, 1));
}
</style>
