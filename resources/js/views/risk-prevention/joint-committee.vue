<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import {
  downloadRiskFile,
  formatRiskDate,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
} from "../../components/risk-prevention/module-utils";

const emptyUpload = () => ({
  document_type: "acta_mensual",
  document_date: "",
  title: "",
  notes: "",
  file: null,
});

export default {
  components: { Layout, LoadingState, HelpButton },
  data() {
    return {
      loading: false,
      saving: false,
      committees: [],
      selectedCommitteeId: null,
      permissions: {
        can_view: false,
        can_upload_minutes: false,
        can_manage_committee: false,
      },
      activeTab: "minutes",
      selectedMinutesYear: new Date().getFullYear(),
      showUploadModal: false,
      uploadForm: emptyUpload(),
    };
  },
  computed: {
    selectedCommittee() {
      return this.committees.find((item) => Number(item.id) === Number(this.selectedCommitteeId)) || null;
    },
    committeeOptions() {
      return this.committees.map((item) => ({
        value: item.id,
        text: `${item.name}${item.active ? " · Vigente" : " · Histórico"}`,
      }));
    },
    activeMembers() {
      return (this.selectedCommittee?.staff_members || []).filter((member) => member.pivot?.active);
    },
    constitutionDocument() {
      return (this.selectedCommittee?.documents || [])
        .find((document) => document.document_type === "constitucion") || null;
    },
    monthlyDocuments() {
      return (this.selectedCommittee?.documents || [])
        .filter((document) => document.document_type === "acta_mensual");
    },
    committeeYearOptions() {
      const committee = this.selectedCommittee;
      if (!committee) return [];

      const currentYear = new Date().getFullYear();
      const startYear = Number(String(committee.starts_on || currentYear).slice(0, 4));
      const endYear = Math.min(
        Number(String(committee.ends_on || currentYear).slice(0, 4)),
        currentYear,
      );
      const years = new Set((committee.documents || [])
        .map((document) => Number(String(document.document_date || "").slice(0, 4)))
        .filter(Boolean));

      for (let year = startYear; year <= Math.max(startYear, endYear); year += 1) years.add(year);
      return Array.from(years).sort((a, b) => b - a).map((year) => ({ value: year, text: String(year) }));
    },
    selectedYearMonths() {
      const committee = this.selectedCommittee;
      if (!committee) return [];

      const year = Number(this.selectedMinutesYear);
      const currentDate = new Date();
      const lastMonth = year === currentDate.getFullYear() ? currentDate.getMonth() + 1 : 12;
      const start = new Date(`${committee.starts_on}T00:00:00`);
      const end = committee.ends_on ? new Date(`${committee.ends_on}T00:00:00`) : null;
      const months = [];

      for (let month = 1; month <= lastMonth; month += 1) {
        const periodDate = new Date(year, month - 1, 1);
        if (periodDate < new Date(start.getFullYear(), start.getMonth(), 1)) continue;
        if (end && periodDate > new Date(end.getFullYear(), end.getMonth(), 1)) continue;
        const period = `${year}-${String(month).padStart(2, "0")}`;
        months.push({
          period,
          label: new Intl.DateTimeFormat("es-CL", { month: "long" }).format(periodDate),
          document: this.monthlyDocuments.find((document) => document.period_key === period) || null,
        });
      }

      return months;
    },
    displayedMonthlyCount() {
      return this.selectedYearMonths.filter((month) => month.document).length;
    },
  },
  watch: {
    selectedCommitteeId() {
      this.syncSelectedMinutesYear();
    },
  },
  mounted() {
    this.loadCommittees();
  },
  methods: {
    formatRiskDate,
    async loadCommittees() {
      this.loading = true;
      try {
        const selectedId = this.selectedCommitteeId;
        const response = await axios.get("/api/risk-prevention/joint-committees");
        this.committees = response.data.data || [];
        this.permissions = response.data.permissions || this.permissions;
        this.selectedCommitteeId = this.committees.some((item) => Number(item.id) === Number(selectedId))
          ? selectedId
          : (this.committees.find((item) => item.active)?.id || this.committees[0]?.id || null);
        this.syncSelectedMinutesYear();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo cargar el Comité Paritario."));
      } finally {
        this.loading = false;
      }
    },
    memberRole(member) {
      return member.pivot?.position_name
        || (member.pivot?.member_role === "suplente" ? "Suplente" : "Titular");
    },
    representationLabel(value) {
      return value === "empleador" ? "Representante del empleador" : "Representante de trabajadores";
    },
    syncSelectedMinutesYear() {
      const committee = this.selectedCommittee;
      if (!committee) return;
      const currentYear = new Date().getFullYear();
      const startYear = Number(String(committee.starts_on || currentYear).slice(0, 4));
      const endYear = Number(String(committee.ends_on || currentYear).slice(0, 4));
      this.selectedMinutesYear = Math.min(Math.max(currentYear, startYear), endYear);
    },
    openUpload(documentType, period = null) {
      if (!this.permissions.can_upload_minutes) return;
      const committee = this.selectedCommittee;
      const now = new Date();
      const today = new Date(now.getTime() - (now.getTimezoneOffset() * 60000))
        .toISOString()
        .slice(0, 10);
      this.uploadForm = {
        ...emptyUpload(),
        document_type: documentType,
        document_date: period
          ? `${period}-01`
          : (documentType === "constitucion" ? (committee?.starts_on || today) : today),
      };
      this.showUploadModal = true;
    },
    onFile(event) {
      this.uploadForm.file = event?.target?.files?.[0] || null;
    },
    async uploadDocument() {
      if (!this.selectedCommittee || !this.uploadForm.file) {
        showRiskError("Selecciona el archivo del acta antes de continuar.");
        return;
      }

      this.saving = true;
      try {
        const formData = new FormData();
        formData.append("document_type", this.uploadForm.document_type);
        formData.append("document_date", this.uploadForm.document_date);
        formData.append("title", this.uploadForm.title || "");
        formData.append("notes", this.uploadForm.notes || "");
        formData.append("file", this.uploadForm.file);

        await axios.post(
          `/api/risk-prevention/joint-committees/${this.selectedCommittee.id}/documents`,
          formData,
        );
        this.showUploadModal = false;
        await this.loadCommittees();
        await showRiskSuccess("El acta quedó cargada y disponible para consulta autorizada.");
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo cargar el acta."));
      } finally {
        this.saving = false;
      }
    },
    async downloadDocument(document) {
      try {
        await downloadRiskFile(
          `/api/risk-prevention/joint-committees/${this.selectedCommittee.id}/documents/${document.id}/download`,
          document.original_name,
        );
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo descargar el acta."));
      }
    },
    trainingStatus(training) {
      const participants = training.participants || [];
      const completed = participants.filter((item) => item.compliance_status === "cumplido").length;
      return `${completed} de ${participants.length} participantes cumplidos`;
    },
  },
};
</script>

<template>
  <Layout>
    <section class="committee-hero mb-4">
      <div class="hero-copy">
        <span class="hero-kicker"><i class="bx bx-group"></i> Gobernanza preventiva</span>
        <h1>Comité Paritario</h1>
        <p>Constitución, actas mensuales, integrantes y capacitaciones en una sola trazabilidad.</p>
      </div>
      <div class="hero-actions">
        <HelpButton
          title="Ayuda: Comité Paritario"
          text="El permiso de consulta permite revisar actas y capacitaciones. El permiso Cargar actas del Comité Paritario puede asignarse a Secretaría y Presidencia sin entregar administración completa de Prevención."
        />
        <BButton
          v-if="permissions.can_upload_minutes && selectedCommittee"
          class="hero-button"
          @click="openUpload('acta_mensual')"
        >
          <i class="bx bx-upload"></i> Subir acta mensual
        </BButton>
      </div>
    </section>

    <LoadingState v-if="loading" message="Cargando Comité Paritario..." />

    <template v-else-if="selectedCommittee">
      <section class="committee-toolbar mb-4">
        <div>
          <label>Período del comité</label>
          <BFormSelect v-model="selectedCommitteeId" :options="committeeOptions" />
        </div>
        <div class="period-copy">
          <strong>{{ selectedCommittee.name }}</strong>
          <span>{{ formatRiskDate(selectedCommittee.starts_on) }} — {{ selectedCommittee.ends_on ? formatRiskDate(selectedCommittee.ends_on) : "Sin término" }}</span>
        </div>
        <router-link
          v-if="permissions.can_manage_committee"
          to="/risk-prevention/personnel"
          class="btn btn-outline-primary"
        >
          Administrar integrantes
        </router-link>
      </section>

      <section class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
          <article class="metric-card metric-blue">
            <span><i class="bx bx-user-check"></i></span>
            <div><strong>{{ selectedCommittee.summary?.active_members || 0 }}</strong><small>Integrantes activos</small></div>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="metric-card" :class="selectedCommittee.summary?.constitution_uploaded ? 'metric-green' : 'metric-red'">
            <span><i class="bx bx-file"></i></span>
            <div><strong>{{ selectedCommittee.summary?.constitution_uploaded ? "Cargada" : "Pendiente" }}</strong><small>Acta de constitución</small></div>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="metric-card metric-violet">
            <span><i class="bx bx-calendar-check"></i></span>
            <div><strong>{{ displayedMonthlyCount }}/{{ selectedYearMonths.length }}</strong><small>Actas mensuales {{ selectedMinutesYear }}</small></div>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="metric-card metric-amber">
            <span><i class="bx bx-certification"></i></span>
            <div><strong>{{ selectedCommittee.summary?.committee_trainings || 0 }}</strong><small>Capacitaciones vinculadas</small></div>
          </article>
        </div>
      </section>

      <nav class="committee-tabs mb-3" aria-label="Secciones del Comité Paritario">
        <button :class="{ active: activeTab === 'minutes' }" @click="activeTab = 'minutes'">
          <i class="bx bx-file-blank"></i> Actas
        </button>
        <button :class="{ active: activeTab === 'members' }" @click="activeTab = 'members'">
          <i class="bx bx-group"></i> Integrantes
        </button>
        <button :class="{ active: activeTab === 'trainings' }" @click="activeTab = 'trainings'">
          <i class="bx bx-book-reader"></i> Capacitaciones
        </button>
      </nav>

      <section v-if="activeTab === 'minutes'" class="content-card">
        <header class="content-header">
          <div><span>Documentación oficial</span><h2>Actas del comité</h2></div>
          <BButton
            v-if="permissions.can_upload_minutes && !constitutionDocument"
            variant="outline-primary"
            @click="openUpload('constitucion')"
          >
            <i class="bx bx-file-plus"></i> Subir constitución
          </BButton>
        </header>

        <article class="constitution-card" :class="{ ready: constitutionDocument }">
          <span class="constitution-icon"><i :class="constitutionDocument ? 'bx bx-check-shield' : 'bx bx-error-circle'"></i></span>
          <div>
            <small>Documento base</small>
            <h3>Acta de constitución</h3>
            <p v-if="constitutionDocument">
              {{ constitutionDocument.title }} · {{ formatRiskDate(constitutionDocument.document_date) }}
            </p>
            <p v-else>Aún no se ha cargado el documento que formaliza este período.</p>
          </div>
          <BButton v-if="constitutionDocument" variant="primary" @click="downloadDocument(constitutionDocument)">
            <i class="bx bx-download"></i> Descargar
          </BButton>
        </article>

        <div class="monthly-heading">
          <div><h3>Control mensual {{ selectedMinutesYear }}</h3><p>Cada mes admite una única acta para evitar duplicidad documental.</p></div>
          <BFormSelect v-model="selectedMinutesYear" :options="committeeYearOptions" class="year-selector" />
        </div>
        <div class="month-grid">
          <article v-for="month in selectedYearMonths" :key="month.period" :class="['month-card', { complete: month.document }]">
            <div class="month-status"><i :class="month.document ? 'bx bx-check' : 'bx bx-time-five'"></i></div>
            <div><strong class="text-capitalize">{{ month.label }}</strong><small>{{ month.document ? formatRiskDate(month.document.document_date) : "Pendiente" }}</small></div>
            <BButton v-if="month.document" size="sm" variant="link" @click="downloadDocument(month.document)">Descargar</BButton>
            <BButton v-else-if="permissions.can_upload_minutes" size="sm" variant="outline-primary" @click="openUpload('acta_mensual', month.period)">Subir</BButton>
          </article>
        </div>
      </section>

      <section v-else-if="activeTab === 'members'" class="content-card">
        <header class="content-header"><div><span>Conformación vigente</span><h2>Integrantes del comité</h2></div></header>
        <div class="member-grid">
          <article v-for="member in activeMembers" :key="member.id" class="member-card">
            <div class="member-avatar">{{ member.full_name?.slice(0, 1) }}</div>
            <div class="member-copy">
              <h3>{{ member.full_name }}</h3>
              <p>{{ member.cargo?.name || "Sin cargo registrado" }}</p>
              <div><span>{{ memberRole(member) }}</span><small>{{ representationLabel(member.pivot?.representation) }}</small></div>
            </div>
          </article>
          <div v-if="!activeMembers.length" class="empty-panel">No hay integrantes activos registrados para este período.</div>
        </div>
      </section>

      <section v-else class="content-card">
        <header class="content-header">
          <div><span>Origen: módulo Capacitaciones</span><h2>Capacitaciones del Comité Paritario</h2></div>
          <router-link
            v-if="permissions.can_manage_committee"
            :to="{ path: '/risk-prevention/trainings', query: { committee_id: selectedCommittee.id, action: 'new' } }"
            class="btn btn-primary"
          >
            <i class="bx bx-plus"></i> Registrar capacitación
          </router-link>
        </header>
        <div class="training-list">
          <article v-for="training in selectedCommittee.trainings || []" :key="training.id" class="training-card">
            <div class="training-date"><strong>{{ new Date(`${training.training_date}T00:00:00`).getDate() }}</strong><span>{{ new Intl.DateTimeFormat('es-CL', { month: 'short' }).format(new Date(`${training.training_date}T00:00:00`)) }}</span></div>
            <div><h3>{{ training.name }}</h3><p>{{ training.modality }} · {{ training.training_type }}</p><small>{{ trainingStatus(training) }}</small></div>
            <BBadge :variant="training.evidence_path ? 'success' : 'warning'">{{ training.evidence_path ? "Con evidencia" : "Sin evidencia" }}</BBadge>
          </article>
          <div v-if="!(selectedCommittee.trainings || []).length" class="empty-panel">
            Registra la capacitación en su módulo original y selecciona este Comité Paritario para verla aquí automáticamente.
          </div>
        </div>
      </section>
    </template>

    <section v-else class="empty-committee">
      <span><i class="bx bx-group"></i></span>
      <h2>No hay un Comité Paritario registrado</h2>
      <p>Primero crea el período y sus integrantes desde Gestión del personal.</p>
      <router-link v-if="permissions.can_manage_committee" to="/risk-prevention/personnel" class="btn btn-primary">Crear comité</router-link>
    </section>

    <BModal v-model="showUploadModal" size="lg" title="Cargar acta del Comité Paritario" hide-footer>
      <div class="upload-intro">
        <span><i class="bx bx-cloud-upload"></i></span>
        <div><strong>Archivo privado y trazable</strong><p>Se conservará el nombre original, fecha, tamaño y usuario que realizó la carga.</p></div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Tipo de acta</label>
          <BFormSelect v-model="uploadForm.document_type" :options="[
            { value: 'acta_mensual', text: 'Acta mensual' },
            { value: 'constitucion', text: 'Acta de constitución' },
          ]" />
        </div>
        <div class="col-md-6"><label class="form-label">Fecha del acta</label><BFormInput v-model="uploadForm.document_date" type="date" /></div>
        <div class="col-12"><label class="form-label">Título opcional</label><BFormInput v-model="uploadForm.title" placeholder="Ej: Sesión ordinaria de agosto" /></div>
        <div class="col-12">
          <label class="form-label">Archivo</label>
          <BFormFile accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" browse-text="Seleccionar" @change="onFile" />
          <small class="text-muted">PDF, Word o imagen. Máximo 20 MB.</small>
        </div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="uploadForm.notes" rows="3" /></div>
      </div>
      <div class="modal-actions">
        <BButton variant="light" @click="showUploadModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="uploadDocument">
          <BSpinner v-if="saving" small class="me-1" /> {{ saving ? "Cargando..." : "Guardar acta" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.committee-hero { display:flex; align-items:center; justify-content:space-between; gap:2rem; padding:2rem; color:#fff; border-radius:24px; background:radial-gradient(circle at 85% 12%, rgba(45,212,191,.25), transparent 34%), linear-gradient(135deg,#102a43 0%,#174b64 58%,#167d78 100%); box-shadow:0 18px 50px rgba(15,42,67,.2); }
.hero-kicker { display:inline-flex; align-items:center; gap:.45rem; color:#9ff4e6; font-size:.75rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
.committee-hero h1 { margin:.45rem 0 .35rem; font-size:clamp(1.8rem,3vw,2.6rem); font-weight:800; }
.committee-hero p { margin:0; max-width:660px; color:#d8edf0; }
.hero-actions { display:flex; align-items:center; gap:.75rem; flex-shrink:0; }
.hero-button { border:0; color:#103b44; background:#adf4e8; font-weight:800; }
.committee-toolbar { display:grid; grid-template-columns:minmax(230px,360px) 1fr auto; gap:1rem; align-items:end; padding:1.15rem 1.25rem; border:1px solid #dce8ee; border-radius:18px; background:#fff; }
.committee-toolbar label { display:block; margin-bottom:.35rem; color:#64748b; font-size:.72rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
.period-copy { display:flex; flex-direction:column; padding-bottom:.4rem; }.period-copy strong{color:#17324d}.period-copy span{color:#64748b;font-size:.85rem}
.metric-card { display:flex; align-items:center; gap:1rem; height:100%; padding:1.2rem; border:1px solid #e5edf2; border-radius:18px; background:#fff; box-shadow:0 7px 22px rgba(29,51,73,.06); }
.metric-card>span { display:grid; place-items:center; width:46px; height:46px; border-radius:14px; font-size:1.35rem; }.metric-card div{display:flex;flex-direction:column}.metric-card strong{font-size:1.35rem;color:#17324d}.metric-card small{color:#708092}
.metric-blue>span{color:#1d64b5;background:#e9f2ff}.metric-green>span{color:#087f5b;background:#e7f8f0}.metric-red>span{color:#c2414b;background:#fff0f1}.metric-violet>span{color:#6d4ccf;background:#f1edff}.metric-amber>span{color:#b56a0b;background:#fff4df}
.committee-tabs { display:flex; gap:.45rem; padding:.4rem; overflow:auto; border-radius:15px; background:#eaf1f4; }.committee-tabs button{display:flex;align-items:center;gap:.45rem;padding:.7rem 1rem;border:0;border-radius:11px;color:#526578;background:transparent;font-weight:700;white-space:nowrap}.committee-tabs button.active{color:#15485d;background:#fff;box-shadow:0 5px 15px rgba(19,59,78,.1)}
.content-card { padding:1.5rem; border:1px solid #dfe9ee; border-radius:20px; background:#fff; box-shadow:0 10px 30px rgba(23,50,77,.06); }.content-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem}.content-header span{color:#167d78;font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.content-header h2{margin:.15rem 0 0;color:#17324d;font-size:1.35rem}
.constitution-card { display:flex;align-items:center;gap:1rem;padding:1.15rem;margin-bottom:1.7rem;border:1px solid #f0c9cd;border-radius:16px;background:#fff7f8}.constitution-card.ready{border-color:#bce9d7;background:#f0fbf6}.constitution-icon{display:grid;place-items:center;width:48px;height:48px;border-radius:14px;color:#c2414b;background:#ffe7e9;font-size:1.45rem}.ready .constitution-icon{color:#087f5b;background:#dff7ec}.constitution-card>div{flex:1}.constitution-card small{color:#708092;text-transform:uppercase;font-weight:700}.constitution-card h3{margin:.15rem 0;color:#17324d;font-size:1.05rem}.constitution-card p{margin:0;color:#64748b;font-size:.85rem}
.monthly-heading{display:flex;justify-content:space-between;align-items:end;gap:1rem;margin-bottom:.8rem}.monthly-heading h3{margin:0;color:#17324d;font-size:1.05rem}.monthly-heading p{margin:.2rem 0 0;color:#708092;font-size:.82rem}.year-selector{width:110px}.month-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem}.month-card{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.7rem;padding:.8rem;border:1px solid #e5edf2;border-radius:14px;background:#fbfcfd}.month-status{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;color:#aa6a11;background:#fff3dd}.month-card.complete .month-status{color:#087f5b;background:#e3f8ef}.month-card div:nth-child(2){display:flex;flex-direction:column}.month-card small{color:#7b8998}
.member-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem}.member-card{display:flex;gap:.85rem;padding:1rem;border:1px solid #e3ebef;border-radius:16px;background:#fbfcfd}.member-avatar{display:grid;place-items:center;width:46px;height:46px;flex:0 0 46px;border-radius:14px;color:#fff;background:linear-gradient(135deg,#1d6480,#18a38f);font-weight:800}.member-copy h3{margin:0;color:#17324d;font-size:1rem}.member-copy p{margin:.15rem 0 .55rem;color:#708092;font-size:.82rem}.member-copy div{display:flex;flex-direction:column}.member-copy span{color:#176b65;font-weight:800}.member-copy small{color:#7b8998}
.training-list{display:grid;gap:.75rem}.training-card{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:1rem;padding:1rem;border:1px solid #e2ebef;border-radius:16px;background:#fbfcfd}.training-date{display:flex;flex-direction:column;align-items:center;min-width:52px;padding:.5rem;border-radius:12px;color:#145b61;background:#e4f5f2}.training-date strong{font-size:1.25rem}.training-date span{text-transform:uppercase;font-size:.68rem;font-weight:800}.training-card h3{margin:0;color:#17324d;font-size:1rem}.training-card p{margin:.15rem 0;color:#708092;font-size:.84rem}.training-card small{color:#177369;font-weight:700}.empty-panel,.empty-committee{padding:2rem;text-align:center;color:#718096;border:1px dashed #cad7de;border-radius:16px;background:#f8fafb}.empty-committee span{display:grid;place-items:center;width:60px;height:60px;margin:0 auto 1rem;border-radius:18px;color:#167d78;background:#e4f5f2;font-size:1.7rem}.empty-committee h2{color:#17324d}
.upload-intro{display:flex;gap:.8rem;padding:1rem;margin-bottom:1rem;border-radius:15px;background:#edf7f7}.upload-intro>span{display:grid;place-items:center;width:44px;height:44px;border-radius:12px;color:#fff;background:#167d78;font-size:1.3rem}.upload-intro strong{color:#17324d}.upload-intro p{margin:.15rem 0 0;color:#64748b;font-size:.82rem}.modal-actions{display:flex;justify-content:flex-end;gap:.65rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid #e5edf2}
@media (max-width: 991px){.committee-hero{align-items:flex-start;flex-direction:column}.committee-toolbar{grid-template-columns:1fr}.month-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width: 575px){.committee-hero{padding:1.35rem}.hero-actions{width:100%;justify-content:space-between}.month-grid,.member-grid{grid-template-columns:1fr}.training-card{grid-template-columns:auto 1fr}.training-card .badge{grid-column:1/-1}.constitution-card{align-items:flex-start;flex-wrap:wrap}.constitution-card .btn{width:100%}}
</style>
