<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import { riskMatrixApi } from "../../../services/risk-matrix-api";
import { formatRiskDate, formatRiskDateTime, formatRiskError, showRiskError, showRiskSuccess } from "../../../components/risk-prevention/module-utils";

const router = useRouter();
const catalogs = ref({ work_centers: [] });
const batches = ref([]);
const batch = ref(null);
const loading = ref(true);
const analyzing = ref(false);
const committing = ref(false);
const file = ref(null);
const workCenter = ref("");
const destination = reactive({ type: "new_matrix", code: "", folio: "", name: "", work_center_id: "", matrix_id: null, version_id: null, skip_error_rows: false, confirm_duplicate_file: false });
const previewRows = computed(() => batch.value?.preview_payload?.rows || []);
const completion = computed(() => batch.value ? (committing.value ? 100 : 66) : (file.value ? 33 : 0));
const canCommit = computed(() => Boolean(batch.value && !committing.value && (destination.type !== "new_matrix" || (destination.code.trim() && destination.name.trim()))));
const metadata = computed(() => batch.value?.summary?.matrix_metadata || {});

const sheetLabels = { matrix: "Matriz IPER", criteria: "Criterios", program: "Programa de trabajo", unknown: "Hoja auxiliar" };
const statusLabels = { preview_ready: "Lista para confirmar", committed: "Importada", failed: "Con error", cancelled: "Cancelada", analyzing: "Analizando" };

function applyMetadata() {
  const source = metadata.value;
  const year = String(source.updated_on || source.prepared_on || new Date().getFullYear()).slice(0, 4);
  if (!destination.code) destination.code = source.source_code || `IPER-${year}`;
  if (!destination.folio) destination.folio = source.source_folio || "";
  if (!destination.name) destination.name = `Matriz IPER Institucional ${year}`;
  if (!destination.work_center_id && workCenter.value) destination.work_center_id = workCenter.value;
}

function selectFile(event) {
  file.value = event.target.files?.[0] || null;
}

async function load() {
  loading.value = true;
  try {
    const [options, history] = await Promise.all([riskMatrixApi.catalogs(), riskMatrixApi.imports()]);
    catalogs.value = options;
    batches.value = history.data || [];
  } catch (error) {
    showRiskError(formatRiskError(error));
  } finally {
    loading.value = false;
  }
}

async function preview() {
  if (!file.value) return showRiskError("Selecciona un libro XLSX.");
  analyzing.value = true;
  try {
    const data = new FormData();
    data.append("file", file.value);
    if (workCenter.value) data.append("work_center_id", workCenter.value);
    batch.value = await riskMatrixApi.previewImport(data);
    applyMetadata();
    batches.value = [batch.value, ...batches.value.filter((item) => item.id !== batch.value.id)];
    showRiskSuccess("El libro fue analizado sin escribir datos en la matriz.");
  } catch (error) {
    showRiskError(formatRiskError(error, "No se pudo analizar el libro."));
  } finally {
    analyzing.value = false;
  }
}

async function openBatch(id) {
  try {
    batch.value = await riskMatrixApi.importBatch(id);
    applyMetadata();
  } catch (error) {
    showRiskError(formatRiskError(error));
  }
}

async function commit() {
  if (!canCommit.value) return;
  committing.value = true;
  try {
    const response = await riskMatrixApi.commitImport(batch.value.id, destination);
    showRiskSuccess(response.message);
    await router.push(`/risk-prevention/matrices/${response.data.risk_matrix_id}/editar?version=${response.data.id}`);
  } catch (error) {
    showRiskError(formatRiskError(error, "No se pudo confirmar la importación."));
  } finally {
    committing.value = false;
  }
}

onMounted(load);
</script>

<template>
  <Layout>
    <section class="import-hero">
      <div class="hero-copy">
        <router-link to="/risk-prevention/matrices"><i class="bx bx-left-arrow-alt"></i> Matrices IPER/MIPER</router-link>
        <span class="eyebrow">Integración documental segura</span>
        <h1>Importación histórica</h1>
        <p>Convierte el libro institucional en una matriz trazable, valida cada fila y conserva sus datos de cabecera sin alterar el archivo original.</p>
      </div>
      <div class="hero-security"><i class="bx bx-lock-alt"></i><div><strong>Archivo privado</strong><span>Hash SHA-256 · operación transaccional</span></div></div>
      <div class="hero-orb"></div>
    </section>

    <nav class="stage-rail" aria-label="Progreso de la importación">
      <div class="stage-progress"><span :style="{ width: `${completion}%` }"></span></div>
      <article :class="{ active: !batch }"><span>1</span><div><strong>Analizar</strong><small>Sin escribir en la matriz</small></div></article>
      <article :class="{ active: batch && !committing }"><span>2</span><div><strong>Revisar</strong><small>Mapeo, alertas y calidad</small></div></article>
      <article :class="{ active: committing }"><span>3</span><div><strong>Confirmar</strong><small>Guardado íntegro y trazable</small></div></article>
    </nav>

    <LoadingState v-if="loading" message="Cargando historial de importaciones…" />
    <div v-else class="import-layout">
      <main>
        <section class="import-card upload-card">
          <header><div><span class="section-label">Etapa 1</span><h2>Carga y análisis seguro</h2></div><span class="file-rule">XLSX · máximo 20 MB</span></header>
          <label class="drop-zone">
            <span class="file-icon"><i class="bx bx-spreadsheet"></i></span>
            <span class="file-copy"><strong>{{ file?.name || 'Selecciona el libro de matriz IPER' }}</strong><small>{{ file ? `${(file.size / 1024 / 1024).toFixed(2)} MB · listo para analizar` : 'Arrastra o elige el archivo; todavía no se guardará información.' }}</small></span>
            <span class="choose-file">{{ file ? 'Cambiar archivo' : 'Elegir archivo' }}</span>
            <input type="file" accept=".xlsx" @change="selectFile" />
          </label>
          <div class="upload-options">
            <label><span>Centro de trabajo opcional</span><select v-model="workCenter" class="form-select"><option value="">Usar el centro informado en el Excel</option><option v-for="item in catalogs.work_centers" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
            <button class="btn btn-primary" :disabled="analyzing || !file" @click="preview"><span v-if="analyzing" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-analyse"></i>{{ analyzing ? 'Analizando libro…' : 'Analizar sin importar' }}</button>
          </div>
        </section>

        <section v-if="!batch" class="trust-grid">
          <article><i class="bx bx-shield-quarter"></i><div><strong>Vista previa protegida</strong><span>Ninguna fila se escribe antes de tu confirmación.</span></div></article>
          <article><i class="bx bx-git-compare"></i><div><strong>Normalización explicable</strong><span>VEP y clasificaciones se recalculan en el servidor.</span></div></article>
          <article><i class="bx bx-undo"></i><div><strong>Confirmación atómica</strong><span>Si una fila falla, la operación completa se revierte.</span></div></article>
        </section>

        <section v-if="batch" class="import-card preview-card">
          <header><div><span class="section-label">Etapa 2</span><h2>Calidad de la integración</h2></div><div class="source-file"><i class="bx bx-spreadsheet"></i><span><strong>{{ batch.original_filename }}</strong><small>{{ formatRiskDateTime(batch.created_at) }}</small></span></div></header>
          <div class="import-metrics">
            <article><span>Filas detectadas</span><strong>{{ batch.total_rows }}</strong><small>registros IPER</small></article>
            <article class="valid"><span>Listas para integrar</span><strong>{{ batch.valid_rows }}</strong><small>sin errores</small></article>
            <article class="warning"><span>Con advertencias</span><strong>{{ batch.warning_rows }}</strong><small>requieren revisión</small></article>
            <article class="error"><span>Con errores</span><strong>{{ batch.error_rows }}</strong><small>{{ batch.error_rows ? 'no se importarán' : 'ninguno' }}</small></article>
            <article><span>Duplicados posibles</span><strong>{{ batch.duplicate_rows }}</strong><small>comparación semántica</small></article>
          </div>

          <div v-if="Object.keys(metadata).length" class="metadata-panel">
            <div class="metadata-title"><i class="bx bx-building-house"></i><div><span>Cabecera institucional reconocida</span><strong>{{ metadata.company_name || 'Entidad del libro' }}</strong></div><em>Se integrará en la versión</em></div>
            <div class="metadata-grid">
              <div><span>Código / folio</span><strong>{{ metadata.source_code || '—' }} · {{ metadata.source_folio || '—' }}</strong></div>
              <div><span>RUT</span><strong>{{ metadata.company_tax_id || '—' }}</strong></div>
              <div><span>Centro de trabajo</span><strong>{{ metadata.work_center_name || '—' }}</strong></div>
              <div><span>Dotación</span><strong>{{ metadata.total_workers ?? '—' }} personas</strong></div>
              <div><span>Elaboración</span><strong>{{ formatRiskDate(metadata.prepared_on) }}</strong></div>
              <div><span>Actualización</span><strong>{{ formatRiskDate(metadata.updated_on) }}</strong></div>
            </div>
          </div>

          <div v-if="batch.summary?.duplicate_file_warning" class="quality-alert warning"><i class="bx bx-copy"></i><span>{{ batch.summary.duplicate_file_warning }}</span></div>
          <div v-if="batch.summary?.ignored_footer_rows" class="quality-alert info"><i class="bx bx-check-circle"></i><span>Se excluyeron {{ batch.summary.ignored_footer_rows }} filas de firma o pie documental; no corresponden a riesgos.</span></div>
          <div v-if="batch.summary?.criteria_sheets_differ" class="quality-alert warning"><i class="bx bx-info-circle"></i><span>Las hojas de criterios tienen diferencias. Se conservaron como referencia y no se duplicaron catálogos.</span></div>

          <div class="sheet-list">
            <article v-for="sheet in batch.summary?.sheets || []" :key="sheet.name" :class="`sheet--${sheet.type}`"><i class="bx" :class="sheet.type === 'matrix' ? 'bx-table' : sheet.type === 'criteria' ? 'bx-list-check' : sheet.type === 'program' ? 'bx-task' : 'bx-file'"></i><span><strong>{{ sheet.name }}</strong><small>{{ sheetLabels[sheet.type] || sheet.type }}</small></span><i class="bx bx-check"></i></article>
          </div>

          <div class="preview-table-heading"><div><span>Detalle normalizado</span><strong>Primeras {{ Math.min(previewRows.length, 50) }} de {{ previewRows.length }} filas</strong></div><small>El texto completo se conserva; la tabla ajusta el contenido para lectura.</small></div>
          <div class="preview-table"><table class="table"><thead><tr><th>Fila</th><th>Actividad y tarea</th><th>Riesgo específico</th><th>Evaluación</th><th>Medida de control</th><th>Calidad</th></tr></thead><tbody><tr v-for="row in previewRows.slice(0, 50)" :key="`${row.sheet_name}-${row.row_number}`"><td><span class="row-number">{{ row.row_number }}</span></td><td class="activity-cell"><strong>{{ row.activity }}</strong><small>{{ row.task }}</small></td><td>{{ row.risk }}</td><td><strong>{{ row.probability }} × {{ row.consequence }} = {{ row.score }}</strong><small>{{ row.risk_level_code }}</small></td><td>{{ row.control || 'Sin medida informada' }}</td><td><span :class="row.has_error ? 'result-error' : row.has_warning ? 'result-warning' : 'result-ok'"><i class="bx" :class="row.has_error ? 'bx-x' : row.has_warning ? 'bx-error' : 'bx-check'"></i>{{ row.has_error ? 'Error' : row.has_warning ? 'Advertencia' : 'Válida' }}</span></td></tr></tbody></table></div>
        </section>

        <section v-if="batch" class="import-card confirm-card">
          <header><div><span class="section-label">Etapa 3</span><h2>Destino y confirmación</h2></div><span class="transaction-chip"><i class="bx bx-lock"></i> Transacción protegida</span></header>
          <div class="destination-grid">
            <label><span>Destino</span><select v-model="destination.type" class="form-select"><option value="new_matrix">Crear matriz nueva</option><option value="existing_matrix">Agregar a borrador / crear nueva versión</option></select></label>
            <template v-if="destination.type === 'new_matrix'">
              <label><span>Código *</span><input v-model="destination.code" class="form-control" /></label>
              <label><span>Folio</span><input v-model="destination.folio" class="form-control" /></label>
              <label class="span-2"><span>Nombre de la matriz *</span><input v-model="destination.name" class="form-control" /></label>
              <label><span>Centro vinculado</span><select v-model="destination.work_center_id" class="form-select"><option value="">Conservar nombre del Excel</option><option v-for="item in catalogs.work_centers" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
            </template>
            <template v-else>
              <label><span>ID matriz *</span><input v-model.number="destination.matrix_id" type="number" class="form-control" /></label>
              <label><span>ID versión destino *</span><input v-model.number="destination.version_id" type="number" class="form-control" /></label>
            </template>
          </div>
          <label v-if="batch.error_rows" class="decision"><input v-model="destination.skip_error_rows" type="checkbox" /><span><strong>Omitir explícitamente las filas con error</strong><small>Seguirán disponibles en la trazabilidad de la importación.</small></span></label>
          <label v-if="batch.summary?.duplicate_file_warning" class="decision"><input v-model="destination.confirm_duplicate_file" type="checkbox" /><span><strong>Confirmar archivo repetido</strong><small>Reconozco que este hash ya fue importado anteriormente.</small></span></label>
          <footer><div><i class="bx bx-shield-quarter"></i><span><strong>{{ batch.valid_rows }} filas listas</strong><small>Si ocurre un error, no se guardará ninguna fila parcial.</small></span></div><button class="btn btn-success" :disabled="!canCommit" @click="commit"><span v-if="committing" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-check-shield"></i>{{ committing ? 'Integrando matriz…' : 'Confirmar e integrar matriz' }}</button></footer>
        </section>
      </main>

      <aside class="history-card">
        <header><span>Trazabilidad</span><h2>Importaciones recientes</h2><p>Retoma una vista previa o consulta su estado.</p></header>
        <button v-for="item in batches" :key="item.id" :class="{ active: batch?.id === item.id }" @click="openBatch(item.id)"><span class="history-icon"><i class="bx bx-spreadsheet"></i></span><span><strong>{{ item.original_filename }}</strong><small>{{ formatRiskDateTime(item.created_at) }}</small><em :class="`state--${item.status}`">{{ statusLabels[item.status] || item.status }}</em></span><i class="bx bx-chevron-right"></i></button>
        <div v-if="!batches.length" class="history-empty"><i class="bx bx-history"></i><span>Aún no hay importaciones.</span></div>
      </aside>
    </div>
  </Layout>
</template>

<style scoped>
.import-hero{position:relative;overflow:hidden;display:flex;align-items:flex-end;justify-content:space-between;gap:2rem;padding:1.75rem 2rem;border-radius:22px;background:linear-gradient(125deg,#102b46 0%,#155e69 68%,#16806f 100%);color:#fff;box-shadow:0 20px 46px rgba(16,43,70,.2)}.hero-copy{position:relative;z-index:2;max-width:780px}.hero-copy>a{display:inline-flex;align-items:center;gap:.35rem;margin-bottom:.8rem;color:#d4edf0;font-size:.78rem}.eyebrow{display:block;margin-bottom:.25rem;color:#a9dde0;font-size:.7rem;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.hero-copy h1{margin:0 0 .35rem;font-size:2rem;letter-spacing:-.035em}.hero-copy p{max-width:720px;margin:0;color:#d9ebed;font-size:.9rem;line-height:1.55}.hero-security{position:relative;z-index:2;display:flex;align-items:center;gap:.7rem;min-width:270px;padding:.8rem 1rem;border:1px solid rgba(255,255,255,.16);border-radius:14px;background:rgba(255,255,255,.1);backdrop-filter:blur(8px)}.hero-security>i{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.13);font-size:1.15rem}.hero-security strong,.hero-security span{display:block}.hero-security strong{font-size:.78rem}.hero-security span{color:#d9ebed;font-size:.66rem}.hero-orb{position:absolute;right:-70px;top:-110px;width:280px;height:280px;border:42px solid rgba(255,255,255,.055);border-radius:50%}.stage-rail{position:relative;display:grid;grid-template-columns:repeat(3,1fr);margin:.85rem 0;padding:.8rem 1rem;border:1px solid #dce5ea;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(16,24,40,.045)}.stage-progress{position:absolute;left:7%;right:7%;top:31px;height:3px;overflow:hidden;border-radius:99px;background:#e6edf0}.stage-progress span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#16806f,#3b82f6);transition:width .3s}.stage-rail article{z-index:1;display:flex;align-items:center;justify-content:center;gap:.65rem;color:#7a8797}.stage-rail article>span{display:grid;place-items:center;width:32px;height:32px;border:4px solid #fff;border-radius:50%;background:#eef2f5;font-size:.7rem;font-weight:800}.stage-rail article.active{color:#174b58}.stage-rail article.active>span{background:#16806f;color:#fff}.stage-rail strong,.stage-rail small{display:block}.stage-rail strong{font-size:.73rem}.stage-rail small{font-size:.61rem}.import-layout{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:1rem}.import-layout main{display:grid;gap:1rem}.import-card,.history-card{overflow:hidden;border:1px solid #dce5ea;border-radius:16px;background:#fff;box-shadow:0 8px 26px rgba(16,24,40,.045)}.import-card>header,.history-card>header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.15rem;border-bottom:1px solid #e8edf1}.section-label,.history-card header>span{color:#16806f;font-size:.64rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.import-card h2,.history-card h2{margin:.12rem 0 0;color:#1d2939;font-size:1.05rem}.file-rule,.transaction-chip{padding:.32rem .55rem;border-radius:999px;background:#eff7f6;color:#176b62;font-size:.65rem;font-weight:750}.drop-zone{position:relative;display:flex;align-items:center;gap:.85rem;margin:1rem;padding:1.15rem;border:1.5px dashed #73aeb1;border-radius:14px;background:linear-gradient(135deg,#f1f9f8,#f8fbfd);cursor:pointer}.drop-zone:hover{border-color:#16806f;background:#edf8f6}.file-icon{display:grid;place-items:center;flex:0 0 48px;height:48px;border-radius:13px;background:#dff1ed;color:#16806f;font-size:1.6rem}.file-copy{min-width:0;flex:1}.file-copy strong,.file-copy small{display:block}.file-copy strong{overflow:hidden;color:#1d2939;font-size:.82rem;text-overflow:ellipsis;white-space:nowrap}.file-copy small{margin-top:.18rem;color:#667085;font-size:.67rem}.choose-file{padding:.48rem .7rem;border-radius:8px;background:#fff;color:#176b62;font-size:.66rem;font-weight:800;box-shadow:0 2px 8px rgba(16,24,40,.07)}.drop-zone input{position:absolute;inset:0;opacity:0;cursor:pointer}.upload-options{display:flex;align-items:flex-end;justify-content:flex-end;gap:.65rem;padding:0 1rem 1rem}.upload-options label{min-width:300px;flex:1}.upload-options label>span,.destination-grid label>span{display:block;margin-bottom:.32rem;color:#536174;font-size:.67rem;font-weight:750}.upload-options .btn,.confirm-card .btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;min-height:39px;white-space:nowrap}.trust-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}.trust-grid article{display:flex;gap:.65rem;padding:.9rem;border:1px solid #e1e8ed;border-radius:14px;background:linear-gradient(145deg,#fff,#f7fafb)}.trust-grid i{font-size:1.4rem;color:#16806f}.trust-grid strong,.trust-grid span{display:block}.trust-grid strong{color:#25364a;font-size:.73rem}.trust-grid span{margin-top:.15rem;color:#667085;font-size:.63rem;line-height:1.45}.source-file{display:flex;align-items:center;gap:.55rem;max-width:45%}.source-file>i{font-size:1.45rem;color:#16806f}.source-file strong,.source-file small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.source-file strong{font-size:.71rem}.source-file small{color:#667085;font-size:.61rem}.import-metrics{display:grid;grid-template-columns:repeat(5,1fr);border-bottom:1px solid #e8edf1}.import-metrics article{padding:.85rem 1rem;border-right:1px solid #e8edf1}.import-metrics article:last-child{border-right:0}.import-metrics span,.import-metrics strong,.import-metrics small{display:block}.import-metrics span{color:#667085;font-size:.62rem}.import-metrics strong{margin:.14rem 0;color:#25364a;font-size:1.35rem;line-height:1}.import-metrics small{color:#98a2b3;font-size:.58rem}.import-metrics .valid strong{color:#15803d}.import-metrics .warning strong{color:#d97706}.import-metrics .error strong{color:#b42318}.metadata-panel{margin:.85rem 1rem;padding:.9rem;border:1px solid #cde4df;border-radius:13px;background:#f1f9f7}.metadata-title{display:flex;align-items:center;gap:.6rem}.metadata-title>i{font-size:1.45rem;color:#16806f}.metadata-title>div{min-width:0;flex:1}.metadata-title span,.metadata-title strong{display:block}.metadata-title span{color:#667085;font-size:.61rem}.metadata-title strong{overflow:hidden;font-size:.75rem;text-overflow:ellipsis;white-space:nowrap}.metadata-title em{padding:.25rem .45rem;border-radius:99px;background:#dcefe9;color:#176b62;font-size:.58rem;font-style:normal;font-weight:750}.metadata-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem;margin-top:.75rem;padding-top:.75rem;border-top:1px solid #d9ebe7}.metadata-grid span,.metadata-grid strong{display:block}.metadata-grid span{color:#667085;font-size:.57rem}.metadata-grid strong{margin-top:.12rem;color:#344054;font-size:.65rem}.quality-alert{display:flex;align-items:center;gap:.55rem;margin:.6rem 1rem;padding:.65rem .75rem;border-radius:9px;font-size:.67rem}.quality-alert.warning{background:#fff7e8;color:#9a5b08}.quality-alert.info{background:#edf8f7;color:#176b62}.quality-alert i{font-size:1rem}.sheet-list{display:flex;gap:.55rem;overflow:auto;padding:.75rem 1rem;border-top:1px solid #edf1f4}.sheet-list article{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.45rem;min-width:195px;padding:.55rem .65rem;border:1px solid #dde5eb;border-radius:10px;background:#fff}.sheet-list article>i:first-child{display:grid;place-items:center;width:30px;height:30px;border-radius:8px;background:#eef5f5;color:#16806f;font-size:1rem}.sheet-list article>i:last-child{color:#16a34a}.sheet-list strong,.sheet-list small{display:block}.sheet-list strong{max-width:130px;overflow:hidden;font-size:.65rem;text-overflow:ellipsis;white-space:nowrap}.sheet-list small{color:#667085;font-size:.56rem}.preview-table-heading{display:flex;align-items:flex-end;justify-content:space-between;padding:.8rem 1rem;border-top:1px solid #edf1f4}.preview-table-heading span,.preview-table-heading strong{display:block}.preview-table-heading span{color:#667085;font-size:.6rem}.preview-table-heading strong{font-size:.75rem}.preview-table-heading>small{color:#98a2b3;font-size:.59rem}.preview-table{max-height:470px;overflow:auto;border-top:1px solid #edf1f4}.preview-table table{min-width:1050px;margin:0;font-size:.67rem}.preview-table th{position:sticky;z-index:1;top:0;padding:.65rem .7rem;background:#f6f8fa;color:#536174;font-size:.59rem;letter-spacing:.04em;text-transform:uppercase}.preview-table td{max-width:280px;padding:.65rem .7rem;vertical-align:top;line-height:1.45}.preview-table td strong,.preview-table td small{display:block}.preview-table td small{margin-top:.15rem;color:#667085}.activity-cell strong{color:#25364a}.row-number{display:grid;place-items:center;width:27px;height:27px;border-radius:7px;background:#eef3f6;color:#536174;font-weight:800}.result-ok,.result-warning,.result-error{display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .38rem;border-radius:999px;font-size:.57rem;font-weight:750;white-space:nowrap}.result-ok{background:#ecfdf3;color:#15803d}.result-warning{background:#fff7ed;color:#c2410c}.result-error{background:#fff1f2;color:#be123c}.destination-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;padding:1rem}.destination-grid .span-2{grid-column:span 2}.decision{display:flex;align-items:flex-start;gap:.55rem;margin:0 1rem .7rem;padding:.7rem;border:1px solid #fed7aa;border-radius:9px;background:#fff7ed;color:#9a3412}.decision input{margin-top:.18rem}.decision strong,.decision small{display:block}.decision strong{font-size:.67rem}.decision small{font-size:.59rem}.confirm-card>footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1rem;border-top:1px solid #e8edf1;background:#fbfcfd}.confirm-card>footer>div{display:flex;align-items:center;gap:.55rem}.confirm-card>footer>div>i{font-size:1.4rem;color:#16806f}.confirm-card footer strong,.confirm-card footer small{display:block}.confirm-card footer strong{font-size:.7rem}.confirm-card footer small{color:#667085;font-size:.6rem}.history-card{align-self:start}.history-card>header{display:block}.history-card header p{margin:.25rem 0 0;color:#667085;font-size:.64rem}.history-card>button{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.55rem;width:100%;padding:.75rem;border:0;border-top:1px solid #edf1f4;background:#fff;text-align:left}.history-card>button:hover,.history-card>button.active{background:#f0f8f7}.history-icon{display:grid;place-items:center;width:36px;height:36px;border-radius:9px;background:#e7f3f1;color:#16806f;font-size:1.1rem}.history-card button strong,.history-card button small,.history-card button em{display:block}.history-card button strong{overflow:hidden;color:#25364a;font-size:.65rem;text-overflow:ellipsis;white-space:nowrap}.history-card button small{margin:.1rem 0;color:#667085;font-size:.57rem}.history-card button em{width:max-content;padding:.17rem .32rem;border-radius:999px;background:#eef2f6;color:#536174;font-size:.52rem;font-style:normal}.history-card button .state--committed{background:#ecfdf3;color:#15803d}.history-card button .state--preview_ready{background:#eff6ff;color:#175cd3}.history-card button>i{color:#98a2b3}.history-empty{display:flex;align-items:center;gap:.5rem;padding:1rem;color:#667085;font-size:.65rem}.history-empty i{font-size:1.2rem}@media(max-width:1100px){.import-layout{grid-template-columns:1fr}.history-card{order:-1}.history-card>header{display:none}.history-card{display:flex;overflow:auto}.history-card>button{min-width:255px;border-top:0;border-right:1px solid #edf1f4}.import-metrics{grid-template-columns:repeat(3,1fr)}}@media(max-width:760px){.import-hero{align-items:flex-start;flex-direction:column;padding:1.35rem}.hero-copy h1{font-size:1.6rem}.hero-security{min-width:0;width:100%}.stage-rail article div{display:none}.trust-grid,.metadata-grid,.destination-grid{grid-template-columns:1fr}.destination-grid .span-2{grid-column:auto}.upload-options{align-items:stretch;flex-direction:column}.upload-options label{min-width:0}.drop-zone{align-items:flex-start;flex-wrap:wrap}.choose-file{margin-left:58px}.import-metrics{grid-template-columns:1fr 1fr}.preview-table-heading,.confirm-card>footer{align-items:flex-start;flex-direction:column}}@media(max-width:480px){.stage-rail{padding:.65rem}.import-metrics{grid-template-columns:1fr}.metadata-title em{display:none}.source-file{max-width:55%}}
</style>
