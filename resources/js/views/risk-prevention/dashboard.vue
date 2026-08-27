<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import { formatRiskDate, formatRiskDateTime, formatRiskError, showRiskError } from "../../components/risk-prevention/module-utils";
import { riskMatrixApi } from "../../services/risk-matrix-api";

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      loading: false,
      error: null,
      iper: { metrics: {} },
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
        const [dashboard, iper] = await Promise.allSettled([
          axios.get("/api/risk-prevention/dashboard"),
          riskMatrixApi.dashboard(),
        ]);
        if (dashboard.status === "rejected") throw dashboard.reason;
        this.data = dashboard.value.data;
        this.iper = iper.status === "fulfilled" ? iper.value : { metrics: {} };
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo cargar el dashboard del módulo.");
        showRiskError(this.error);
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <section class="prevention-hero">
      <div class="hero-copy">
        <span>Gestión preventiva institucional</span>
        <h1>Prevención de Riesgos</h1>
        <p>Una visión integrada de peligros, controles, personas, activos y cumplimiento documental del establecimiento.</p>
      </div>
      <div class="hero-actions">
        <HelpButton
          title="Ayuda del dashboard"
          text="Este panel resume los vencimientos y pendientes críticos del módulo para priorizar acciones preventivas."
        />
        <BButton variant="light" @click="loadDashboard"><i class="bx bx-refresh"></i> Actualizar</BButton>
      </div>
      <div class="hero-orb"></div>
    </section>

    <nav class="prevention-shortcuts" aria-label="Áreas de prevención">
      <router-link to="/risk-prevention/matrices"><i class="bx bx-grid-alt"></i><span><strong>Matrices IPER</strong><small>Peligros y evaluación</small></span><i class="bx bx-chevron-right"></i></router-link>
      <router-link to="/risk-prevention/preventive-program"><i class="bx bx-task"></i><span><strong>Programa preventivo</strong><small>Medidas y responsables</small></span><i class="bx bx-chevron-right"></i></router-link>
      <router-link to="/risk-prevention/accidents"><i class="bx bx-first-aid"></i><span><strong>Accidentes</strong><small>Registro y seguimiento</small></span><i class="bx bx-chevron-right"></i></router-link>
      <router-link to="/risk-prevention/extinguishers"><i class="bx bx-shield-quarter"></i><span><strong>Extintores</strong><small>Vigencia y ubicación</small></span><i class="bx bx-chevron-right"></i></router-link>
      <router-link to="/risk-prevention/epp"><i class="bx bx-check-shield"></i><span><strong>EPP</strong><small>Entrega y reposición</small></span><i class="bx bx-chevron-right"></i></router-link>
      <router-link to="/risk-prevention/joint-committee"><i class="bx bx-group"></i><span><strong>Comité Paritario</strong><small>Actas y capacitación</small></span><i class="bx bx-chevron-right"></i></router-link>
      <router-link to="/risk-prevention/documents"><i class="bx bx-folder-open"></i><span><strong>Documentación</strong><small>Vigencia y difusión</small></span><i class="bx bx-chevron-right"></i></router-link>
    </nav>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <LoadingState v-if="loading" message="Cargando dashboard de prevención..." />

    <template v-else>
      <BAlert
        v-if="(data.metrics.extinguishers_due || 0) + (data.metrics.documents_due || 0)"
        show
        variant="warning"
        class="mb-3"
      >
        Hay <strong>{{ data.metrics.extinguishers_due || 0 }}</strong> extintores y
        <strong>{{ data.metrics.documents_due || 0 }}</strong> documentos con vencimiento próximo o vencido.
        Revisa los paneles de seguimiento de esta página.
      </BAlert>

      <section class="iper-command mb-3">
        <div class="iper-command__intro"><span>Control estratégico IPER/MIPER</span><h2>Riesgos y ejecución preventiva</h2><p>Indicadores de las matrices vigentes y su programa de medidas.</p><router-link to="/risk-prevention/matrices">Abrir gestión IPER <i class="bx bx-right-arrow-alt"></i></router-link></div>
        <div class="iper-command__metrics">
          <article><span>Matrices vigentes</span><strong>{{ iper.metrics.current_matrices || 0 }}</strong><small>{{ iper.metrics.draft_matrices || 0 }} borradores</small></article>
          <article class="important"><span>Riesgos importantes</span><strong>{{ iper.metrics.important_risks || 0 }}</strong><small>requieren medidas</small></article>
          <article class="critical"><span>Riesgos intolerables</span><strong>{{ iper.metrics.intolerable_risks || 0 }}</strong><small>respuesta inmediata</small></article>
          <article class="program-progress"><span>Avance preventivo</span><strong>{{ iper.metrics.program_progress || 0 }}%</strong><div><span :style="{ width: `${iper.metrics.program_progress || 0}%` }"></span></div></article>
        </div>
      </section>

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
.prevention-hero{position:relative;overflow:hidden;display:flex;align-items:flex-end;justify-content:space-between;gap:2rem;margin-bottom:.85rem;padding:1.6rem 1.8rem;border-radius:22px;background:linear-gradient(125deg,#102b46,#155e69 70%,#16806f);color:#fff;box-shadow:0 18px 40px rgba(16,43,70,.18)}.hero-copy{position:relative;z-index:2}.hero-copy>span{color:#a9dde0;font-size:.7rem;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.hero-copy h1{margin:.2rem 0 .35rem;font-size:1.9rem;letter-spacing:-.035em}.hero-copy p{max-width:720px;margin:0;color:#d9ebed;font-size:.86rem}.hero-actions{position:relative;z-index:2;display:flex;gap:.55rem}.hero-actions :deep(button){display:inline-flex;align-items:center;gap:.35rem}.hero-orb{position:absolute;right:-65px;top:-105px;width:270px;height:270px;border:42px solid rgba(255,255,255,.055);border-radius:50%}.prevention-shortcuts{display:grid;grid-template-columns:repeat(7,1fr);gap:.6rem;margin-bottom:.85rem}.prevention-shortcuts>a{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.5rem;min-width:0;padding:.7rem;border:1px solid #dce5ea;border-radius:12px;background:#fff;color:#344054;box-shadow:0 5px 16px rgba(16,24,40,.035);transition:.18s}.prevention-shortcuts>a:hover{border-color:#9bc8c5;transform:translateY(-1px)}.prevention-shortcuts>a>i:first-child{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:#e7f3f1;color:#16806f;font-size:1.05rem}.prevention-shortcuts strong,.prevention-shortcuts small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.prevention-shortcuts strong{font-size:.66rem}.prevention-shortcuts small{color:#667085;font-size:.56rem}.prevention-shortcuts>a>i:last-child{color:#98a2b3}.iper-command{display:grid;grid-template-columns:300px 1fr;overflow:hidden;border:1px solid #d9e5e6;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(16,24,40,.04)}.iper-command__intro{padding:1rem 1.15rem;background:linear-gradient(145deg,#eff8f7,#f7fbfb)}.iper-command__intro>span{color:#16806f;font-size:.61rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase}.iper-command__intro h2{margin:.2rem 0;font-size:1rem}.iper-command__intro p{margin:0 0 .5rem;color:#667085;font-size:.63rem}.iper-command__intro a{display:inline-flex;align-items:center;gap:.25rem;color:#176b62;font-size:.64rem;font-weight:800}.iper-command__metrics{display:grid;grid-template-columns:repeat(4,1fr)}.iper-command__metrics article{padding:1rem;border-left:1px solid #e8edf1}.iper-command__metrics span,.iper-command__metrics strong,.iper-command__metrics small{display:block}.iper-command__metrics>article>span{color:#667085;font-size:.61rem}.iper-command__metrics strong{margin:.15rem 0;color:#25364a;font-size:1.35rem}.iper-command__metrics small{color:#98a2b3;font-size:.58rem}.iper-command__metrics .important strong{color:#c2410c}.iper-command__metrics .critical strong{color:#be123c}.iper-command__metrics .program-progress>div{height:6px;overflow:hidden;margin-top:.4rem;border-radius:99px;background:#e4ecea}.iper-command__metrics .program-progress>div span{height:100%;border-radius:99px;background:#16806f}
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

@media (max-width: 1200px) {.prevention-shortcuts{grid-template-columns:repeat(3,1fr)}.iper-command{grid-template-columns:1fr}.iper-command__metrics article:first-child{border-left:0}}
@media (max-width: 700px) {.prevention-hero{align-items:flex-start;flex-direction:column;padding:1.3rem}.prevention-shortcuts{grid-template-columns:1fr 1fr}.iper-command__metrics{grid-template-columns:1fr 1fr}.iper-command__metrics article{border-top:1px solid #e8edf1}.hero-copy h1{font-size:1.55rem}}
@media (max-width: 450px) {.prevention-shortcuts,.iper-command__metrics{grid-template-columns:1fr}}
</style>
