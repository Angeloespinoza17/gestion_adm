<script setup>
import { computed } from "vue";
import AttendanceKpiCard from "./attendance-kpi-card.vue";
import AttendanceChartPanel from "./attendance-chart-panel.vue";

const props = defineProps({ data: { type: Object, required: true } });
const emit = defineEmits(["drill-course"]);
const pct = (value) => value === null || value === undefined ? "Sin datos" : `${Number(value).toLocaleString("es-CL", { minimumFractionDigits: 1, maximumFractionDigits: 2 })}%`;
const base = (type = "line") => ({
  chart: { type, toolbar: { show: true, tools: { download: true, selection: false, zoom: false, zoomin: false, zoomout: false, pan: false, reset: false } }, animations: { enabled: false }, redrawOnParentResize: true, fontFamily: "inherit" },
  dataLabels: { enabled: false }, stroke: { width: 2.5, curve: "straight" }, grid: { borderColor: "#edf0f4", strokeDashArray: 3 },
  legend: { position: "top", horizontalAlign: "left", fontSize: "11px" },
  tooltip: { theme: "light" }, noData: { text: "Sin datos" },
});
const target = computed(() => Number(props.data.summary?.target_rate || 0));
const timelineSeries = computed(() => [
  { name: "Asistencia real", data: (props.data.timeline || []).map((row) => row.attendance_rate) },
  { name: "Meta", data: (props.data.timeline || []).map(() => target.value) },
]);
const timelineOptions = computed(() => ({ ...base("line"), colors: ["#405189", "#d59b26"], xaxis: { categories: (props.data.timeline || []).map((row) => row.date), type: "datetime", labels: { datetimeUTC: false, format: "dd MMM" } }, yaxis: { min: 0, max: 100, labels: { formatter: (value) => `${Math.round(value)}%` } }, tooltip: { x: { format: "dd MMM yyyy" }, y: { formatter: pct } } }));
const courseSeries = computed(() => [{ name: "Asistencia", data: (props.data.courses || []).map((row) => row.attendance_rate) }]);
const courseOptions = computed(() => ({ ...base("bar"), colors: ["#287f74"], chart: { ...base("bar").chart, events: { dataPointSelection: (_event, _context, config) => { const course = props.data.courses?.[config.dataPointIndex]; if (course) emit("drill-course", course.id); } } }, plotOptions: { bar: { horizontal: true, borderRadius: 2, barHeight: "56%" } }, xaxis: { categories: (props.data.courses || []).map((row) => row.name), min: 0, max: 100, labels: { formatter: (value) => `${Math.round(value)}%` } }, tooltip: { y: { formatter: pct } } }));
const levelSeries = computed(() => [{ name: "Asistencia", data: (props.data.levels || []).map((row) => row.attendance_rate) }]);
const levelOptions = computed(() => ({ ...base("bar"), colors: ["#3b82a0"], plotOptions: { bar: { borderRadius: 2, columnWidth: "52%" } }, xaxis: { categories: (props.data.levels || []).map((row) => row.name), labels: { rotate: -35, trim: true } }, yaxis: { min: 0, max: 100, labels: { formatter: (value) => `${Math.round(value)}%` } }, tooltip: { y: { formatter: pct } } }));
const riskSeries = computed(() => (props.data.risk_distribution || []).map((row) => row.value));
const riskOptions = computed(() => ({ ...base("donut"), labels: (props.data.risk_distribution || []).map((row) => row.name), colors: (props.data.risk_distribution || []).map((row) => row.color), plotOptions: { pie: { donut: { size: "62%", labels: { show: true, total: { show: true, label: "Estudiantes" } } } } }, tooltip: { y: { formatter: (value) => `${value} estudiantes` } } }));
const monthlySeries = computed(() => [{ name: "Asistencia", data: (props.data.monthly || []).map((row) => row.attendance_rate) }]);
const monthlyOptions = computed(() => ({ ...base("area"), colors: ["#405189"], fill: { type: "solid", opacity: .12 }, xaxis: { categories: (props.data.monthly || []).map((row) => row.label) }, yaxis: { min: 0, max: 100, labels: { formatter: (value) => `${Math.round(value)}%` } }, tooltip: { y: { formatter: pct } } }));
const weekdaySeries = computed(() => [{ name: "Asistencia", data: (props.data.weekdays || []).map((row) => row.attendance_rate) }]);
const weekdayOptions = computed(() => ({ ...base("bar"), colors: ["#7866a3"], plotOptions: { bar: { borderRadius: 2, columnWidth: "48%" } }, xaxis: { categories: (props.data.weekdays || []).map((row) => row.label) }, yaxis: { min: 0, max: 100, labels: { formatter: (value) => `${Math.round(value)}%` } }, tooltip: { y: { formatter: pct } } }));
const statusSeries = computed(() => [{ name: "Registros", data: (props.data.status_distribution || []).map((row) => row.value) }]);
const statusOptions = computed(() => ({ ...base("bar"), colors: ["#2b8a66", "#4b83c4", "#c84f5a", "#d59b26", "#7866a3"], plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 2, barHeight: "52%" } }, xaxis: { categories: (props.data.status_distribution || []).map((row) => row.label), labels: { formatter: (value) => Math.round(value) } }, legend: { show: false } }));
const funnelSeries = computed(() => [{ name: "Casos", data: (props.data.alert_funnel || []).map((row) => row.value) }]);
const funnelOptions = computed(() => ({ ...base("bar"), colors: ["#405189"], plotOptions: { bar: { horizontal: true, isFunnel: true, borderRadius: 1, barHeight: "70%" } }, xaxis: { categories: (props.data.alert_funnel || []).map((row) => row.label), labels: { formatter: (value) => Math.round(value) } }, legend: { show: false } }));
const dateRows = computed(() => (props.data.timeline || []).map((row) => [row.date, row.present, row.absent, pct(row.attendance_rate)]));
</script>

<template>
  <section class="dashboard-stack">
    <div class="kpi-band"><AttendanceKpiCard v-for="item in data.kpis || []" :key="item.key" :item="item" /></div>
    <div class="executive-summary">
      <div><span>Presentes</span><strong>{{ Number(data.summary?.present || 0).toLocaleString('es-CL') }}</strong></div>
      <div><span>Ausentes</span><strong>{{ Number(data.summary?.absent || 0).toLocaleString('es-CL') }}</strong></div>
      <div><span>Justificadas</span><strong>{{ Number(data.summary?.justified_absent || 0).toLocaleString('es-CL') }}</strong></div>
      <div><span>Injustificadas</span><strong>{{ Number(data.summary?.unjustified_absent || 0).toLocaleString('es-CL') }}</strong></div>
      <div><span>En riesgo</span><strong>{{ Number(data.summary?.students_at_risk || 0).toLocaleString('es-CL') }}</strong></div>
      <div><span>Cursos bajo meta</span><strong>{{ Number(data.summary?.courses_below_target || 0).toLocaleString('es-CL') }}</strong></div>
      <div><span>Alertas activas</span><strong>{{ Number(data.summary?.open_alerts || 0).toLocaleString('es-CL') }}</strong></div>
      <div><span>Jornadas pendientes</span><strong>{{ Number(data.summary?.pending_school_days || 0).toLocaleString('es-CL') }}</strong></div>
    </div>
    <div class="dashboard-grid grid-main">
      <AttendanceChartPanel title="Evolución diaria y meta" kicker="Tendencia" help="Compara la tasa diaria ponderada con la meta institucional vigente." :empty="!(data.timeline || []).length" :headers="['Fecha','Presentes','Ausentes','Asistencia']" :rows="dateRows"><apexchart type="line" height="300" :options="timelineOptions" :series="timelineSeries" /></AttendanceChartPanel>
      <AttendanceChartPanel title="Distribución por riesgo" kicker="Detección temprana" help="Clasifica estudiantes con datos según los tramos configurados por la institución." :empty="!riskSeries.length" :headers="['Tramo','Estudiantes']" :rows="(data.risk_distribution || []).map(row => [row.name,row.value])"><apexchart type="donut" height="300" :options="riskOptions" :series="riskSeries" /></AttendanceChartPanel>
    </div>
    <div class="dashboard-grid grid-equal">
      <AttendanceChartPanel title="Asistencia por curso" kicker="Drill-down" help="Tasa ponderada de cada curso. Selecciona una barra para aplicar el filtro del curso." :empty="!(data.courses || []).length" :headers="['Curso','Estudiantes','Días','Asistencia']" :rows="(data.courses || []).map(row => [row.name,row.students,row.school_days,pct(row.attendance_rate)])"><apexchart type="bar" height="340" :options="courseOptions" :series="courseSeries" /></AttendanceChartPanel>
      <AttendanceChartPanel title="Asistencia por nivel" kicker="Comparación" help="Compara niveles usando presentes sobre registros esperados, no un promedio simple de cursos." :empty="!(data.levels || []).length" :headers="['Nivel','Estudiantes','Asistencia']" :rows="(data.levels || []).map(row => [row.name,row.students,pct(row.attendance_rate)])"><apexchart type="bar" height="340" :options="levelOptions" :series="levelSeries" /></AttendanceChartPanel>
      <AttendanceChartPanel title="Evolución mensual" kicker="Año académico" help="Agrupa todos los registros del año por mes y calcula su tasa ponderada." :empty="!(data.monthly || []).length" :headers="['Mes','Presentes','Ausentes','Asistencia']" :rows="(data.monthly || []).map(row => [row.label,row.present,row.absent,pct(row.attendance_rate)])"><apexchart type="area" height="280" :options="monthlyOptions" :series="monthlySeries" /></AttendanceChartPanel>
      <AttendanceChartPanel title="Patrón por día de la semana" kicker="Estacionalidad" help="Permite identificar días recurrentemente bajos dentro del periodo seleccionado." :empty="!(data.weekdays || []).length" :headers="['Día','Días lectivos','Asistencia']" :rows="(data.weekdays || []).map(row => [row.label,row.school_days,pct(row.attendance_rate)])"><apexchart type="bar" height="280" :options="weekdayOptions" :series="weekdaySeries" /></AttendanceChartPanel>
      <AttendanceChartPanel title="Estados y eventos" kicker="Composición" help="Muestra volúmenes de presencia, ausencias, justificaciones, atrasos y retiros. Una estudiante puede estar presente y además registrar atraso o retiro." :empty="!(data.status_distribution || []).some(row => row.value)" :headers="['Estado','Registros']" :rows="(data.status_distribution || []).map(row => [row.label,row.value])"><apexchart type="bar" height="280" :options="statusOptions" :series="statusSeries" /></AttendanceChartPanel>
      <AttendanceChartPanel title="Alertas e intervenciones" kicker="Gestión" help="Embudo operativo desde alertas activas hasta intervenciones con resultado de mejora." :empty="!(data.alert_funnel || []).some(row => row.value)" :headers="['Etapa','Casos']" :rows="(data.alert_funnel || []).map(row => [row.label,row.value])"><apexchart type="bar" height="280" :options="funnelOptions" :series="funnelSeries" /></AttendanceChartPanel>
    </div>
  </section>
</template>

<style scoped>
.dashboard-stack{display:grid;gap:.8rem}.kpi-band{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));overflow:hidden;border:1px solid #dfe5ec;border-radius:8px;background:#fff}.kpi-band :deep(.attendance-kpi:nth-child(4n)){border-right:0}.kpi-band :deep(.attendance-kpi:nth-child(n+5)){border-top:1px solid #e5e9ef}.executive-summary{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));overflow:hidden;border:1px solid #dfe5ec;border-radius:8px;background:#fff}.executive-summary div{min-width:0;padding:.65rem .7rem;border-right:1px solid #e5e9ef}.executive-summary div:last-child{border-right:0}.executive-summary span,.executive-summary strong{display:block}.executive-summary span{overflow:hidden;color:#718096;font-size:.6rem;text-overflow:ellipsis;white-space:nowrap}.executive-summary strong{margin-top:.18rem;color:#2e394b;font-size:.92rem}.dashboard-grid{display:grid;gap:.8rem}.grid-main{grid-template-columns:minmax(0,1.45fr) minmax(330px,.55fr)}.grid-equal{grid-template-columns:repeat(2,minmax(0,1fr))}@media(max-width:1200px){.executive-summary{grid-template-columns:repeat(4,1fr)}.executive-summary div:nth-child(4){border-right:0}.executive-summary div:nth-child(n+5){border-top:1px solid #e5e9ef}.grid-main{grid-template-columns:1fr}.kpi-band{grid-template-columns:repeat(3,1fr)}.kpi-band :deep(.attendance-kpi){border-top:0;border-right:1px solid #e5e9ef}.kpi-band :deep(.attendance-kpi:nth-child(3n)){border-right:0}.kpi-band :deep(.attendance-kpi:nth-child(n+4)){border-top:1px solid #e5e9ef}}@media(max-width:767px){.grid-equal{grid-template-columns:1fr}.kpi-band{grid-template-columns:repeat(2,1fr)}.kpi-band :deep(.attendance-kpi:nth-child(odd)){border-right:1px solid #e5e9ef}.kpi-band :deep(.attendance-kpi:nth-child(even)){border-right:0}.kpi-band :deep(.attendance-kpi:nth-child(n+3)){border-top:1px solid #e5e9ef}.executive-summary{grid-template-columns:repeat(2,1fr)}.executive-summary div:nth-child(even){border-right:0}.executive-summary div:nth-child(n+3){border-top:1px solid #e5e9ef}}
</style>
