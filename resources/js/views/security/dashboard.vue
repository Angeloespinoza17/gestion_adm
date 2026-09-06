<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      data: {
        totals: {},
        rounds_by_date: [],
        rounds_by_staff: [],
        sectors_with_most_incidents: [],
        recent_notifications: [],
        recent_rounds: [],
        upcoming_shifts: [],
      },
      logoDataUrl: null,
    };
  },
  mounted() {
    this.loadDashboard();
    this.loadLogo();
  },
  methods: {
    async loadDashboard() {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/security/dashboard");
        this.data = response.data;
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.loading = false;
      }
    },
    async loadLogo() {
      try {
        const response = await fetch("/brand/logo-cnsc.png");
        if (!response.ok) return;
        const blob = await response.blob();
        this.logoDataUrl = await this.blobToDataUrl(blob);
      } catch (error) {
        this.logoDataUrl = null;
      }
    },
    blobToDataUrl(blob) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    },
    async exportPdf() {
      this.exporting = true;
      try {
        const pdfMake = await getPdfMake();
        const totals = this.data.totals || {};
        const docDefinition = {
          pageOrientation: "portrait",
          content: [
            this.logoDataUrl
              ? {
                  columns: [
                    { image: this.logoDataUrl, width: 70 },
                    {
                      width: "*",
                      stack: [
                        { text: "Control de Nochero", style: "title" },
                        { text: "Resumen institucional de rondas de seguridad", color: "#6c757d" },
                      ],
                    },
                  ],
                  margin: [0, 0, 0, 12],
                }
              : { text: "Control de Nochero", style: "title" },
            {
              columns: [
                { text: `Rondas realizadas: ${totals.rounds_total || 0}` },
                { text: `Novedades: ${totals.incidents_total || 0}` },
                { text: `Críticas: ${totals.critical_incidents || 0}` },
              ],
              margin: [0, 0, 0, 8],
            },
            {
              columns: [
                { text: `Pendientes: ${totals.pending_incidents || 0}` },
                { text: `Resueltas: ${totals.resolved_incidents || 0}` },
                { text: `Promedio respuesta: ${totals.average_response_minutes ?? "-"} min` },
              ],
              margin: [0, 0, 0, 14],
            },
            { text: "Rondas por fecha", style: "section" },
            {
              table: {
                headerRows: 1,
                widths: ["*", 90],
                body: [
                  ["Fecha", "Rondas"],
                  ...(this.data.rounds_by_date || []).map((item) => [item.label, String(item.total)]),
                ],
              },
              layout: "lightHorizontalLines",
              margin: [0, 0, 0, 12],
            },
            { text: "Sectores con más incidencias", style: "section" },
            {
              table: {
                headerRows: 1,
                widths: ["*", 90],
                body: [
                  ["Sector", "Incidencias"],
                  ...(this.data.sectors_with_most_incidents || []).map((item) => [item.label, String(item.total)]),
                ],
              },
              layout: "lightHorizontalLines",
            },
          ],
          styles: {
            title: { fontSize: 18, bold: true },
            section: { fontSize: 13, bold: true, margin: [0, 8, 0, 6] },
          },
          defaultStyle: {
            fontSize: 10,
          },
        };

        pdfMake.createPdf(docDefinition).download(`control-nochero-resumen-${new Date().toISOString().slice(0, 10)}.pdf`);
        await this.showSuccess("Resumen exportado correctamente.");
      } finally {
        this.exporting = false;
      }
    },
    formatDateTime(value) {
      if (!value) return "-";
      return new Date(value).toLocaleString("es-CL", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    roundStatusLabel(value) {
      return {
        sin_novedad: "Sin novedad",
        observado: "Observado",
        requiere_atencion: "Requiere atención",
      }[value] || value || "Sin estado";
    },
    roundStatusClass(value) {
      return {
        sin_novedad: "night-status--ok",
        observado: "night-status--warning",
        requiere_atencion: "night-status--danger",
      }[value] || "night-status--neutral";
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudo cargar el panel.";
    },
    showSuccess(message) {
      return Swal.fire({
        icon: "success",
        title: "Operación realizada",
        text: message,
        confirmButtonText: "OK",
      });
    },
    showError(message) {
      return Swal.fire({
        icon: "error",
        title: "No se pudo completar la operación",
        text: message,
        confirmButtonText: "OK",
      });
    },
  },
};
</script>

<template>
  <Layout>
    <main class="night-dashboard">
      <section class="night-hero">
        <div class="night-hero__orb night-hero__orb--one"></div>
        <div class="night-hero__orb night-hero__orb--two"></div>
        <div class="night-hero__copy">
          <span class="night-eyebrow"><i class="bx bx-moon"></i> Control nocturno institucional</span>
          <h1>Panel de nocheros</h1>
          <p>Turnos, rondas y novedades de seguridad en una vista clara para trabajar durante la noche y entregar una bitácora trazable.</p>
          <div class="night-hero__links">
            <RouterLink to="/security/shifts"><i class="bx bx-walk"></i> Gestionar turnos y rondas</RouterLink>
            <RouterLink to="/security/incidents"><i class="bx bx-error-circle"></i> Revisar novedades</RouterLink>
          </div>
        </div>
        <div class="night-hero__actions">
          <button type="button" :disabled="loading" @click="loadDashboard"><i class="bx bx-refresh" :class="{ 'bx-spin': loading }"></i> Actualizar</button>
          <button type="button" class="night-action--primary" :disabled="exporting" @click="exportPdf"><i class="bx bx-download"></i> Exportar PDF</button>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="night-alert">{{ error }}</BAlert>
      <LoadingState v-if="loading && !data.recent_rounds.length" message="Cargando control nocturno..." />

      <template v-else>
        <section class="night-metrics" aria-label="Resumen de control nocturno">
          <article class="night-metric night-metric--indigo"><span><i class="bx bx-run"></i></span><div><small>Turnos activos</small><strong>{{ data.totals.active_shifts || 0 }}</strong><p>en curso ahora</p></div></article>
          <article class="night-metric night-metric--cyan"><span><i class="bx bx-check-shield"></i></span><div><small>Rondas de hoy</small><strong>{{ data.totals.rounds_today || 0 }}</strong><p>{{ data.totals.rounds_total || 0 }} históricas</p></div></article>
          <article class="night-metric night-metric--amber"><span><i class="bx bx-bell"></i></span><div><small>Novedades abiertas</small><strong>{{ data.totals.pending_incidents || 0 }}</strong><p>requieren gestión</p></div></article>
          <article class="night-metric night-metric--rose"><span><i class="bx bx-error-alt"></i></span><div><small>Rondas con alerta</small><strong>{{ data.totals.attention_rounds || 0 }}</strong><p>{{ data.totals.critical_incidents || 0 }} novedades críticas</p></div></article>
          <article class="night-metric night-metric--emerald"><span><i class="bx bx-timer"></i></span><div><small>Respuesta promedio</small><strong>{{ data.totals.average_response_minutes ?? "—" }}</strong><p>minutos</p></div></article>
        </section>

        <section class="night-panel night-logbook">
          <header class="night-panel__header">
            <div><span>Registro cronológico</span><h2>Bitácora nocturna reciente</h2><p>Últimas rondas registradas por el equipo de nocheros.</p></div>
            <RouterLink to="/security/shifts">Abrir bitácora completa <i class="bx bx-right-arrow-alt"></i></RouterLink>
          </header>
          <div v-if="!data.recent_rounds.length" class="night-empty"><i class="bx bx-moon"></i><strong>Aún no hay rondas registradas</strong><span>Las nuevas rondas aparecerán aquí automáticamente.</span></div>
          <div v-else class="night-logbook__grid">
            <article v-for="round in data.recent_rounds" :key="round.id" class="night-round">
              <div class="night-round__head">
                <span class="night-round__number">Ronda #{{ round.round_number }}</span>
                <span class="night-status" :class="roundStatusClass(round.overall_status)">{{ roundStatusLabel(round.overall_status) }}</span>
              </div>
              <h3>{{ round.shift?.staff?.full_name || round.recorded_by?.name || "Nochero sin identificar" }}</h3>
              <time><i class="bx bx-time-five"></i> {{ formatDateTime(round.recorded_at) }}</time>
              <p>{{ round.observations || "Recorrido completado sin observaciones generales." }}</p>
              <div class="night-round__facts">
                <span><i class="bx bx-map-alt"></i> {{ round.sectors_count || 0 }} sectores</span>
                <span :class="{ 'night-round__pending': round.pending_incidents_count }"><i class="bx bx-error-circle"></i> {{ round.incidents_count || 0 }} novedades</span>
                <span><i class="bx bx-file"></i> {{ round.act_number }}</span>
              </div>
            </article>
          </div>
        </section>

        <div class="night-columns">
          <section class="night-panel">
            <header class="night-panel__header night-panel__header--compact"><div><span>Tendencia</span><h2>Rondas por fecha</h2></div></header>
            <div v-if="!data.rounds_by_date.length" class="night-empty night-empty--small">Sin datos para mostrar.</div>
            <div v-else class="night-bars">
              <div v-for="item in data.rounds_by_date" :key="item.label" class="night-bar-row">
                <span>{{ item.label }}</span><div><i :style="{ width: `${Math.min(100, Math.max(8, Number(item.total) * 12))}%` }"></i></div><strong>{{ item.total }}</strong>
              </div>
            </div>
          </section>

          <section class="night-panel">
            <header class="night-panel__header night-panel__header--compact"><div><span>Cobertura</span><h2>Rondas por funcionario</h2></div></header>
            <div v-if="!data.rounds_by_staff.length" class="night-empty night-empty--small">Sin datos para mostrar.</div>
            <div v-else class="night-ranking">
              <div v-for="(item, index) in data.rounds_by_staff" :key="item.label"><b>{{ index + 1 }}</b><span>{{ item.label }}</span><strong>{{ item.total }}</strong></div>
            </div>
          </section>
        </div>

        <div class="night-columns">
          <section class="night-panel">
            <header class="night-panel__header night-panel__header--compact"><div><span>Focos operativos</span><h2>Sectores con más incidencias</h2></div></header>
            <div v-if="!data.sectors_with_most_incidents.length" class="night-empty night-empty--small">No hay incidencias por sector.</div>
            <div v-else class="night-ranking">
              <div v-for="(item, index) in data.sectors_with_most_incidents" :key="item.label"><b>{{ index + 1 }}</b><span>{{ item.label }}</span><strong>{{ item.total }}</strong></div>
            </div>
          </section>

          <section class="night-panel">
            <header class="night-panel__header night-panel__header--compact"><div><span>Seguimiento</span><h2>Alertas recientes</h2></div><RouterLink to="/security/incidents">Ver novedades</RouterLink></header>
            <div v-if="!data.recent_notifications.length" class="night-empty night-empty--small">No hay alertas recientes.</div>
            <div v-else class="night-alerts">
              <article v-for="notification in data.recent_notifications" :key="notification.id"><span :class="`night-alert-dot--${notification.priority}`"></span><div><strong>{{ notification.title }}</strong><p>{{ notification.message }}</p><time>{{ formatDateTime(notification.created_at) }}</time></div></article>
            </div>
          </section>
        </div>

        <section class="night-panel">
          <header class="night-panel__header"><div><span>Planificación</span><h2>Próximos turnos</h2><p>Programación inmediata del equipo nocturno.</p></div><RouterLink to="/security/shifts">Administrar turnos <i class="bx bx-right-arrow-alt"></i></RouterLink></header>
          <div v-if="!data.upcoming_shifts.length" class="night-empty night-empty--small">No hay turnos próximos o en curso.</div>
          <div v-else class="night-shifts">
            <article v-for="shift in data.upcoming_shifts" :key="shift.id"><span class="night-shift__avatar"><i class="bx bx-user"></i></span><div><strong>{{ shift.staff?.full_name || "Sin funcionario" }}</strong><p>{{ shift.schedule_summary }}</p><time>{{ formatDateTime(shift.is_weekly_template ? shift.next_occurrence_at : shift.scheduled_start_at) }}</time></div><span class="night-status night-status--neutral">{{ shift.status }}</span></article>
          </div>
        </section>
      </template>
    </main>
  </Layout>
</template>

<style scoped>
.night-dashboard { --night-ink: #172033; --night-muted: #6f7a8f; padding-bottom: 2rem; color: var(--night-ink); }
.night-hero { position: relative; isolation: isolate; display: flex; align-items: flex-end; justify-content: space-between; gap: 2rem; overflow: hidden; min-height: 245px; margin-bottom: 1rem; padding: 2.25rem 2.4rem; border-radius: 26px; color: #fff; background: linear-gradient(128deg, #0b1739 0%, #172554 45%, #312e81 100%); box-shadow: 0 24px 56px rgba(15, 23, 67, .24); }
.night-hero__orb { position: absolute; z-index: -1; border-radius: 50%; opacity: .3; filter: blur(1px); }
.night-hero__orb--one { width: 300px; height: 300px; top: -185px; right: 23%; background: #38bdf8; }
.night-hero__orb--two { width: 250px; height: 250px; right: -90px; bottom: -150px; background: #818cf8; }
.night-hero__copy { max-width: 730px; }
.night-eyebrow { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: .9rem; padding: .38rem .7rem; border: 1px solid rgba(255,255,255,.22); border-radius: 999px; color: #c7d2fe; background: rgba(255,255,255,.08); font-size: .72rem; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
.night-hero h1 { margin: 0 0 .65rem; color: #fff; font-size: clamp(2rem, 3vw, 2.8rem); font-weight: 760; letter-spacing: -.04em; }
.night-hero p { max-width: 670px; margin: 0; color: rgba(255,255,255,.76); line-height: 1.65; }
.night-hero__links { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1.25rem; }
.night-hero__links a { display: inline-flex; align-items: center; gap: .35rem; color: #e0e7ff; font-size: .78rem; font-weight: 650; }
.night-hero__actions { display: flex; flex: 0 0 auto; gap: .65rem; }
.night-hero__actions button { display: inline-flex; align-items: center; gap: .45rem; padding: .68rem .9rem; border: 1px solid rgba(255,255,255,.24); border-radius: 12px; color: #fff; background: rgba(255,255,255,.1); font-size: .78rem; font-weight: 700; }
.night-hero__actions .night-action--primary { border-color: #fff; color: #172554; background: #fff; }
.night-alert { border: 0; border-radius: 14px; }
.night-metrics { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .8rem; margin-bottom: 1rem; }
.night-metric { display: flex; align-items: center; gap: .8rem; min-height: 108px; padding: 1rem; border: 1px solid #e7ebf3; border-radius: 18px; background: #fff; box-shadow: 0 8px 22px rgba(36,48,79,.055); }
.night-metric > span { display: grid; flex: 0 0 44px; width: 44px; height: 44px; place-items: center; border-radius: 14px; font-size: 1.25rem; }
.night-metric small, .night-metric p { display: block; margin: 0; color: var(--night-muted); }
.night-metric small { font-size: .7rem; font-weight: 700; }
.night-metric strong { display: block; margin: .1rem 0; font-size: 1.6rem; line-height: 1; }
.night-metric p { font-size: .66rem; }
.night-metric--indigo > span { color: #4f46e5; background: #eef2ff; }.night-metric--cyan > span { color: #047a9c; background: #e8f8fc; }.night-metric--amber > span { color: #b56809; background: #fff6df; }.night-metric--rose > span { color: #be3453; background: #fff0f3; }.night-metric--emerald > span { color: #087a5c; background: #e9f9f3; }
.night-panel { margin-bottom: 1rem; overflow: hidden; border: 1px solid #e6eaf2; border-radius: 20px; background: #fff; box-shadow: 0 10px 28px rgba(36,48,79,.055); }
.night-panel__header { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; padding: 1.15rem 1.25rem; border-bottom: 1px solid #edf0f5; }
.night-panel__header span { color: #5d57c9; font-size: .66rem; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
.night-panel__header h2 { margin: .12rem 0 0; font-size: 1.05rem; font-weight: 760; }.night-panel__header p { margin: .2rem 0 0; color: var(--night-muted); font-size: .72rem; }
.night-panel__header a { display: inline-flex; align-items: center; gap: .25rem; color: #4f46e5; font-size: .72rem; font-weight: 750; white-space: nowrap; }.night-panel__header--compact { align-items: center; }
.night-logbook__grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .8rem; padding: 1rem; }
.night-round { position: relative; overflow: hidden; padding: 1rem; border: 1px solid #e6eaf2; border-radius: 16px; background: linear-gradient(180deg, #fff, #fbfcff); }
.night-round::before { position: absolute; inset: 0 auto 0 0; width: 3px; background: #4f46e5; content: ""; }
.night-round__head, .night-round__facts { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }.night-round__number { color: #4f46e5; font-size: .68rem; font-weight: 800; text-transform: uppercase; }
.night-round h3 { overflow: hidden; margin: .8rem 0 .2rem; font-size: .9rem; text-overflow: ellipsis; white-space: nowrap; }.night-round time { color: #7a8497; font-size: .67rem; }.night-round p { display: -webkit-box; min-height: 42px; overflow: hidden; margin: .75rem 0; color: #5f697c; font-size: .72rem; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.night-round__facts { justify-content: flex-start; flex-wrap: wrap; padding-top: .65rem; border-top: 1px solid #edf0f5; }.night-round__facts span { display: inline-flex; align-items: center; gap: .25rem; color: #788296; font-size: .63rem; }.night-round__facts .night-round__pending { color: #b45309; font-weight: 700; }
.night-status { display: inline-flex; padding: .28rem .48rem; border-radius: 999px; font-size: .61rem; font-weight: 750; white-space: nowrap; }.night-status--ok { color: #08785e; background: #e5f8f1; }.night-status--warning { color: #9a5b03; background: #fff1cf; }.night-status--danger { color: #b4233e; background: #ffe9ee; }.night-status--neutral { color: #536176; background: #edf1f6; }
.night-columns { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }.night-bars, .night-ranking, .night-alerts { padding: .75rem 1.1rem 1rem; }
.night-bar-row { display: grid; grid-template-columns: 92px minmax(0, 1fr) 28px; align-items: center; gap: .65rem; padding: .42rem 0; font-size: .7rem; }.night-bar-row > span { color: #6e788c; }.night-bar-row > div { height: 7px; overflow: hidden; border-radius: 999px; background: #edf0f6; }.night-bar-row i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #4f46e5, #38bdf8); }.night-bar-row strong { text-align: right; }
.night-ranking > div { display: grid; grid-template-columns: 30px minmax(0,1fr) auto; align-items: center; gap: .6rem; padding: .58rem 0; border-bottom: 1px solid #eff2f6; }.night-ranking > div:last-child { border-bottom: 0; }.night-ranking b { display: grid; width: 26px; height: 26px; place-items: center; border-radius: 9px; color: #4f46e5; background: #eef2ff; font-size: .68rem; }.night-ranking span { overflow: hidden; color: #4c566a; font-size: .73rem; text-overflow: ellipsis; white-space: nowrap; }.night-ranking strong { font-size: .78rem; }
.night-alerts article { display: grid; grid-template-columns: 8px minmax(0,1fr); gap: .7rem; padding: .65rem 0; border-bottom: 1px solid #eff2f6; }.night-alerts article:last-child { border-bottom: 0; }.night-alerts article > span { width: 8px; height: 8px; margin-top: .3rem; border-radius: 50%; background: #94a3b8; }.night-alerts .night-alert-dot--critica { background: #e11d48; }.night-alerts .night-alert-dot--alta { background: #f59e0b; }.night-alerts .night-alert-dot--media { background: #0ea5e9; }.night-alerts strong { display: block; font-size: .74rem; }.night-alerts p { margin: .15rem 0; color: #687286; font-size: .68rem; }.night-alerts time { color: #98a0af; font-size: .61rem; }
.night-shifts { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: .75rem; padding: 1rem; }.night-shifts article { display: grid; grid-template-columns: auto minmax(0,1fr) auto; align-items: center; gap: .7rem; padding: .85rem; border: 1px solid #e8ebf2; border-radius: 15px; }.night-shift__avatar { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 12px; color: #4f46e5; background: #eef2ff; }.night-shifts strong, .night-shifts p, .night-shifts time { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.night-shifts strong { font-size: .75rem; }.night-shifts p { margin: .1rem 0; color: #737d90; font-size: .64rem; }.night-shifts time { color: #939aaa; font-size: .61rem; }
.night-empty { display: grid; justify-items: center; padding: 2.5rem 1rem; color: #81899a; text-align: center; }.night-empty i { margin-bottom: .55rem; color: #6366f1; font-size: 1.7rem; }.night-empty strong, .night-empty span { display: block; }.night-empty strong { color: #4b5568; font-size: .82rem; }.night-empty span { margin-top: .15rem; font-size: .68rem; }.night-empty--small { min-height: 120px; place-content: center; padding: 1.5rem; font-size: .72rem; }
.bx-spin { animation: night-spin .8s linear infinite; } @keyframes night-spin { to { transform: rotate(360deg); } }
@media (max-width: 1399px) { .night-metrics { grid-template-columns: repeat(3, minmax(0,1fr)); }.night-logbook__grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (max-width: 991px) { .night-hero { align-items: flex-start; min-height: 0; padding: 1.6rem; flex-direction: column; }.night-hero__actions { width: 100%; }.night-hero__actions button { flex: 1; justify-content: center; }.night-columns { grid-template-columns: 1fr; }.night-shifts { grid-template-columns: repeat(2,minmax(0,1fr)); } }
@media (max-width: 767px) { .night-metrics { grid-template-columns: repeat(2,minmax(0,1fr)); }.night-metric { min-height: 96px; }.night-logbook__grid, .night-shifts { grid-template-columns: 1fr; }.night-panel__header { align-items: flex-start; flex-direction: column; }.night-panel__header--compact { flex-direction: row; }.night-round p { min-height: 0; } }
@media (max-width: 480px) { .night-hero { padding: 1.25rem; border-radius: 19px; }.night-hero__actions { flex-direction: column; }.night-metrics { grid-template-columns: 1fr; }.night-metric { min-height: 88px; }.night-logbook__grid { padding: .75rem; }.night-shifts { padding: .75rem; } }
</style>
