<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { LIBRO_DIGITAL_API_BASE, libroDigitalApi, waitForLibroDigitalJob } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import {
  confirmAction,
  contextParams,
  errorMessage,
  formatDateTime,
  hasCapability,
  humanize,
  payloadData,
  payloadItems,
  showError,
  showSuccess,
} from "../module-utils";

const props = defineProps({
  context: { type: Object, required: true },
  catalogs: { type: Object, default: () => ({}) },
  capabilities: { type: Object, default: () => ({}) },
  subsection: { type: String, default: "ede" },
  refreshToken: { type: Number, default: 0 },
});
const emit = defineEmits(["subsection-change", "configuration-updated"]);

const tabDefinitions = [
  { key: "ede", label: "Cumplimiento EDE", icon: "bx-shield-quarter" },
  { key: "audit", label: "Auditoría", icon: "bx-fingerprint" },
  { key: "configuration", label: "Configuración", icon: "bx-cog" },
];
const activeTab = ref(props.subsection || "ede");
const loading = ref(false);
const saving = ref(false);
const generating = ref(false);
const validatingId = ref(null);
const verifying = ref(false);
const importingStandard = ref(false);
const showStandardImport = ref(false);
const error = ref(null);
const versions = ref([]);
const mappings = ref([]);
const exportsHistory = ref([]);
const configuration = ref(null);
const auditRows = ref([]);
const auditIntegrity = ref(null);
let controller = null;

const edeFilters = reactive({ normative_profile_id: null, ede_version_id: null, status: "", from: "", to: "" });
const auditFilters = reactive({ entity_type: "", event_type: "", actor_id: null, from: "", to: "", query: "" });
const standardForm = reactive({ version: "", code: "CEDS", authority: "", source_url: "", effective_from: "", source: null, schema: null, mappings: null });
const configForm = reactive({
  timezone: "America/Santiago",
  attendance_autosave_seconds: 10,
  feature_flags: {
    lcd_enabled: true,
    parvularia_enabled: false,
    identity_features_enabled: false,
    ede_exports_enabled: false,
    sige_integration_enabled: false,
    fiscalization_mode_enabled: false,
  },
});

const profiles = computed(() => props.catalogs.regulatory_profiles || props.catalogs.normative_profiles || []);
const activeVersion = computed(() => versions.value.find((item) => item.is_active || item.is_current) || versions.value[0] || null);
const activeExportVersion = computed(() => versions.value.find((item) => Number(item.id) === Number(edeFilters.ede_version_id)) || activeVersion.value);
const configFlags = computed(() => configuration.value?.feature_flags || configuration.value?.features || configForm.feature_flags);
const edeFeatureEnabled = computed(() => {
  const readableFlags = configuration.value?.feature_flags || configuration.value?.features;
  if (!readableFlags) return true;
  return readableFlags.ede_exports_enabled !== false && readableFlags.ede_enabled !== false;
});
const mappingBlockers = computed(() => mappings.value.filter((item) => ["missing", "invalid", "unmapped", "blocked"].includes(String(item.status).toLowerCase())));
const declaredBlockers = computed(() => {
  const values = activeExportVersion.value?.blockers || activeExportVersion.value?.preflight?.blockers || [];
  return Array.isArray(values) ? values : [];
});
const exportBlockers = computed(() => [...declaredBlockers.value, ...mappingBlockers.value]);
const scopeKey = computed(() => JSON.stringify(contextParams(props.context)));
const canExport = computed(() => hasCapability(props.capabilities, "can_export_ede")
  && edeFeatureEnabled.value
  && Boolean(props.context.school_id && props.context.book_id && activeExportVersion.value)
  && !exportBlockers.value.length);
const canValidate = computed(() => hasCapability(props.capabilities, "can_validate_ede"));
const canManageEde = computed(() => Boolean(props.capabilities?.can_manage_ede || props.capabilities?.["*"] || props.capabilities?.is_super_admin));
const canDownload = computed(() => hasCapability(props.capabilities, "can_download_ede"));
const canViewAudit = computed(() => hasCapability(props.capabilities, "can_view_audit"));
const canVerifyAudit = computed(() => hasCapability(props.capabilities, "can_verify_audit"));
const canConfigure = computed(() => hasCapability(props.capabilities, "can_manage_configuration"));
const canValidateRecord = (record) => canValidate.value && ["generated", "validation_failed"].includes(String(record?.status || "").toLowerCase());
const canDownloadRecord = (record) => canDownload.value
  && String(record?.status || "").toLowerCase() === "released"
  && String(record?.validation_status || "").toLowerCase() === "passed"
  && Boolean(record?.download_url);
const tabs = computed(() => tabDefinitions.filter((tab) => {
  if (tab.key === "ede") return canManageEde.value || hasCapability(props.capabilities, "can_export_ede") || canValidate.value || canDownload.value;
  if (tab.key === "audit") return canViewAudit.value;
  return canConfigure.value;
}));

const syncConfiguration = (payload) => {
  const data = payloadData(payload);
  configuration.value = data;
  configForm.timezone = data.timezone || "America/Santiago";
  configForm.attendance_autosave_seconds = Number(data.attendance_autosave_seconds || data.autosave_seconds || 10);
  Object.keys(configForm.feature_flags).forEach((key) => {
    if (data.feature_flags?.[key] !== undefined) configForm.feature_flags[key] = Boolean(data.feature_flags[key]);
    else if (data.features?.[key] !== undefined) configForm.feature_flags[key] = Boolean(data.features[key]);
  });
};

const loadEde = async (signal) => {
  const params = { ...contextParams(props.context), ...edeFilters, per_page: 50 };
  const [versionsPayload, mappingsPayload, exportsPayload, configPayload] = await Promise.all([
    libroDigitalApi.edeVersions(signal),
    libroDigitalApi.edeMappings(params, signal),
    libroDigitalApi.edeExports(params, signal),
    libroDigitalApi.configuration(contextParams(props.context), signal).catch(() => null),
  ]);
  versions.value = payloadItems(versionsPayload);
  mappings.value = payloadItems(mappingsPayload);
  exportsHistory.value = payloadItems(exportsPayload);
  if (configPayload) syncConfiguration(configPayload);
  if (!edeFilters.ede_version_id && activeVersion.value) edeFilters.ede_version_id = activeVersion.value.id;
};

const loadAudit = async (signal) => {
  auditRows.value = payloadItems(await libroDigitalApi.audit({
    ...contextParams(props.context),
    ...auditFilters,
    per_page: 100,
  }, signal));
};

const loadConfiguration = async (signal) => syncConfiguration(
  await libroDigitalApi.configuration(contextParams(props.context), signal),
);

const load = async () => {
  controller?.abort();
  if (!activeTab.value || !tabs.value.some((tab) => tab.key === activeTab.value)) {
    loading.value = false;
    error.value = null;
    return;
  }
  controller = new AbortController();
  loading.value = true;
  error.value = null;
  try {
    if (activeTab.value === "ede") await loadEde(controller.signal);
    else if (activeTab.value === "audit") await loadAudit(controller.signal);
    else await loadConfiguration(controller.signal);
  } catch (requestError) {
    if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
  } finally {
    loading.value = false;
  }
};

const setTab = (key) => {
  if (!tabs.value.some((tab) => tab.key === key)) return;
  activeTab.value = key;
  auditIntegrity.value = null;
  emit("subsection-change", key);
};

watch(() => props.subsection, (value) => { if (value && value !== activeTab.value && tabs.value.some((tab) => tab.key === value)) activeTab.value = value; });
watch(tabs, (availableTabs) => {
  if (!availableTabs.some((tab) => tab.key === activeTab.value)) activeTab.value = availableTabs[0]?.key || "";
}, { immediate: true });
watch([activeTab, scopeKey, () => props.refreshToken], load, { immediate: true });
onBeforeUnmount(() => controller?.abort());

const createExport = async () => {
  if (!canExport.value) return;
  const decision = await confirmAction({
    title: "Generar paquete EDE",
    text: "Se ejecutará una validación técnica con la versión y el perfil normativo seleccionados. No se enviará automáticamente a una autoridad externa.",
    confirmText: "Generar paquete",
    icon: "question",
  });
  if (!decision.isConfirmed) return;
  generating.value = true;
  try {
    let record = payloadData(await libroDigitalApi.createEdeExport({
      ...contextParams(props.context),
      normative_profile_id: edeFilters.normative_profile_id,
      ede_version_id: edeFilters.ede_version_id,
    }));
    if (["pending", "queued", "processing", "generating"].includes(record.status)) {
      record = await waitForLibroDigitalJob(record, (current) => libroDigitalApi.edeExport(current.id));
    }
    await load();
    await showSuccess("Paquete EDE generado", "Revisa su estado de validación antes de descargarlo.");
  } catch (requestError) {
    if (requestError.code === "LCD_JOB_TIMEOUT") await load();
    else await showError(requestError, "No se pudo generar el paquete EDE");
  } finally {
    generating.value = false;
  }
};

const resetStandardForm = () => Object.assign(standardForm, { version: "", code: "CEDS", authority: "", source_url: "", effective_from: "", source: null, schema: null, mappings: null });
const selectStandardFile = (field, event) => { standardForm[field] = event.target.files?.[0] || null; };
const importStandard = async () => {
  if (!canManageEde.value || !props.context.school_id) return;
  if (!standardForm.mappings || !standardForm.mappings.name.toLowerCase().endsWith(".json")) {
    await showError(new Error("El manifiesto declarativo de mapeos debe ser un archivo JSON."), "Archivo de mapeos inválido");
    return;
  }
  importingStandard.value = true;
  try {
    const data = new FormData();
    data.append("school_id", String(props.context.school_id));
    data.append("version", standardForm.version.trim());
    data.append("code", standardForm.code.trim() || "CEDS");
    data.append("authority", standardForm.authority.trim());
    data.append("source_url", standardForm.source_url.trim());
    if (standardForm.effective_from) data.append("effective_from", standardForm.effective_from);
    data.append("source", standardForm.source);
    data.append("schema", standardForm.schema);
    data.append("mappings", standardForm.mappings);
    const imported = payloadData(await libroDigitalApi.importEdeStandard(data));
    showStandardImport.value = false;
    resetStandardForm();
    await load();
    await showSuccess(
      imported.created ? "Estándar EDE importado" : "Estándar EDE ya registrado",
      "Los artefactos quedaron archivados en privado y requieren una activación separada.",
    );
  } catch (requestError) {
    await showError(requestError, "No se pudo importar el estándar EDE");
  } finally {
    importingStandard.value = false;
  }
};

const validateExport = async (record) => {
  if (!canValidate.value) return;
  validatingId.value = record.id;
  try {
    let queued = payloadData(await libroDigitalApi.validateEdeExport(record));
    queued = await waitForLibroDigitalJob(
      queued,
      (current) => libroDigitalApi.edeExport(current.public_id || current.id),
      { pendingStatuses: ["validation_queued", "validating"] },
    );
    await load();
    await showSuccess(
      queued.validation_status === "passed" ? "Validación finalizada" : "Validación procesada",
      "El resultado técnico y su evidencia quedaron asociados al paquete.",
    );
  } catch (requestError) {
    if (requestError.code === "LCD_JOB_TIMEOUT") await load();
    else {
      await showError(requestError, "No se pudo validar el paquete");
      if (requestError.isConflict) await load();
    }
  } finally {
    validatingId.value = null;
  }
};

const downloadExport = async (record) => {
  if (!canDownload.value) return;
  try {
    const fallback = record.filename || `ede_${record.id}.${record.format || "zip"}`;
    await libroDigitalApi.download(record.download_url || `${LIBRO_DIGITAL_API_BASE}/ede/exports/${record.id}/download`, fallback);
  } catch (requestError) {
    await showError(requestError, "No se pudo descargar el paquete");
  }
};

const verifyAudit = async () => {
  if (!canVerifyAudit.value) return;
  verifying.value = true;
  auditIntegrity.value = null;
  try {
    auditIntegrity.value = payloadData(await libroDigitalApi.verifyAudit({
      ...contextParams(props.context),
      ...auditFilters,
    }));
  } catch (requestError) {
    await showError(requestError, "No se pudo verificar la cadena de auditoría");
  } finally {
    verifying.value = false;
  }
};

const saveConfiguration = async () => {
  saving.value = true;
  try {
    const response = await libroDigitalApi.updateConfiguration(configuration.value, {
      school_id: props.context.school_id,
      academic_year_id: props.context.academic_year_id,
      timezone: configForm.timezone,
      attendance_autosave_seconds: Math.min(15, Math.max(5, Number(configForm.attendance_autosave_seconds || 10))),
      feature_flags: { ...configForm.feature_flags },
    });
    syncConfiguration(response);
    emit("configuration-updated");
    await showSuccess("Configuración guardada", "Los cambios quedaron versionados para auditoría.");
  } catch (requestError) {
    await showError(requestError, "No se pudo guardar la configuración");
    if (requestError.isConflict) await load();
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <section class="lcd-compliance" aria-labelledby="lcd-compliance-title" :aria-busy="loading">
    <header class="lcd-section-heading lcd-compliance__hero lcd-surface">
      <div class="lcd-compliance__identity"><div class="lcd-compliance__mark" aria-hidden="true"><i class="bx bx-shield-quarter"></i></div><div><span class="lcd-eyebrow">GOBERNANZA Y CONTROL</span><h2 id="lcd-compliance-title">Cumplimiento, auditoría y configuración</h2><p>Versionado normativo, exportación interoperable, verificación de integridad y activación gradual.</p></div></div>
      <div class="lcd-compliance__scope" aria-label="Alcance de cumplimiento"><span><i class="bx bx-buildings" aria-hidden="true"></i>{{ context.school_id ? 'Establecimiento seleccionado' : 'Establecimiento pendiente' }}</span><span><i class="bx bx-lock-alt" aria-hidden="true"></i>Operación fail-closed</span></div>
    </header>

    <nav class="lcd-compliance__tabs lcd-surface" role="tablist" aria-label="Secciones de cumplimiento">
      <button v-for="tab in tabs" :id="`lcd-compliance-tab-${tab.key}`" :key="tab.key" type="button" role="tab" :class="{ active: activeTab === tab.key }" :aria-selected="activeTab === tab.key" :aria-controls="`lcd-compliance-panel-${tab.key}`" :tabindex="activeTab === tab.key ? 0 : -1" @click="setTab(tab.key)"><span class="lcd-compliance__tab-icon"><i class="bx" :class="tab.icon" aria-hidden="true"></i></span><span><strong>{{ tab.label }}</strong><small>{{ tab.key === 'ede' ? 'Versiones, mapeos y paquetes' : tab.key === 'audit' ? 'Cadena y eventos' : 'Políticas y activación' }}</small></span></button>
    </nav>

    <LibroDigitalStatePanel v-if="!tabs.length" title="Acceso restringido" message="No cuentas con permisos para consultar cumplimiento, auditoría o configuración." />
    <LibroDigitalStatePanel v-else-if="loading" state="loading" title="Cargando controles" message="Consultando configuración, permisos y evidencia auditable." />
    <LibroDigitalStatePanel v-else-if="error" state="error" title="No se pudo cargar esta sección" :message="errorMessage(error)" @retry="load" />

    <template v-else-if="activeTab === 'ede'">
      <div id="lcd-compliance-panel-ede" class="lcd-ede-process lcd-surface" role="tabpanel" aria-labelledby="lcd-compliance-tab-ede">
        <div><span>Flujo controlado</span><strong>Preparación del paquete EDE</strong></div>
        <ol><li :class="{ complete: activeVersion }"><i class="bx bx-file-find" aria-hidden="true"></i><span><small>01</small>Versión importada</span></li><li :class="{ complete: mappings.length && !mappingBlockers.length }"><i class="bx bx-git-compare" aria-hidden="true"></i><span><small>02</small>Mapeo revisado</span></li><li :class="{ complete: !exportBlockers.length && activeVersion }"><i class="bx bx-check-shield" aria-hidden="true"></i><span><small>03</small>Prevalidación local</span></li><li><i class="bx bx-package" aria-hidden="true"></i><span><small>04</small>Generación y validación</span></li></ol>
      </div>
      <div class="lcd-ede-grid">
        <BCard class="border-0 lcd-panel">
          <header><div><span class="lcd-eyebrow">PAQUETE INTEROPERABLE</span><h3>Exportación EDE</h3><p>Configura alcance y versión antes de ejecutar la prevalidación del servidor.</p></div><div class="lcd-ede-actions"><BButton v-if="canManageEde" type="button" size="sm" variant="outline-primary" :disabled="!context.school_id" @click="resetStandardForm(); showStandardImport = true"><i class="bx bx-upload" aria-hidden="true"></i> Importar estándar</BButton><LibroDigitalStatusBadge :status="edeFeatureEnabled ? 'active' : 'pending'" :label="edeFeatureEnabled ? 'Habilitada' : 'Deshabilitada'" /></div></header>
          <form class="row g-3" @submit.prevent>
            <div class="col-md-6"><label class="form-label" for="lcd-ede-version">Versión EDE</label><BFormSelect id="lcd-ede-version" v-model="edeFilters.ede_version_id"><option :value="null">Seleccionar</option><option v-for="version in versions" :key="version.id" :value="version.id">{{ version.name || version.code }} · {{ version.version || version.release }}</option></BFormSelect></div>
            <div class="col-md-6"><label class="form-label" for="lcd-ede-profile">Perfil normativo</label><BFormSelect id="lcd-ede-profile" v-model="edeFilters.normative_profile_id"><option :value="null">Perfil aplicable al libro</option><option v-for="profile in profiles" :key="profile.id" :value="profile.id">{{ profile.name }} · {{ profile.version }}</option></BFormSelect></div>
            <div class="col-md-6"><label class="form-label" for="lcd-ede-from">Filtrar historial desde</label><BFormInput id="lcd-ede-from" v-model="edeFilters.from" type="date" /></div><div class="col-md-6"><label class="form-label" for="lcd-ede-to">Filtrar historial hasta</label><BFormInput id="lcd-ede-to" v-model="edeFilters.to" type="date" /></div>
          </form>
          <small class="d-block mt-2 text-muted">Las fechas filtran mapeos e historial. El paquete EDE se genera siempre con el alcance completo sellado por el servidor.</small>
          <div v-if="exportBlockers.length" class="lcd-blockers" role="alert"><strong><i class="bx bx-error-circle"></i> {{ exportBlockers.length }} bloqueo(s) de prevalidación</strong><ul><li v-for="(blocker, index) in exportBlockers.slice(0, 6)" :key="blocker.id || index">{{ blocker.message || blocker.reason || blocker.field_name || blocker.source_field || humanize(blocker.status) }}</li></ul></div>
          <div v-else class="lcd-ready"><i class="bx bx-check-shield"></i><span><strong>Sin bloqueos conocidos</strong> La validación oficial se ejecuta al generar el paquete.</span></div>
          <footer><small>La generación no implica envío, certificación ni aceptación externa.</small><BButton type="button" variant="primary" :disabled="generating || !canExport" @click="createExport"><span v-if="generating" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-package"></i> Generar paquete</BButton></footer>
        </BCard>

        <BCard class="border-0 lcd-panel" body-class="p-0">
          <header class="px-3 pt-3"><div><span class="lcd-eyebrow">MAPEO DE DATOS</span><h3>Campos requeridos</h3><p>Correspondencia declarativa entre origen local y contrato importado.</p></div><strong class="lcd-count">{{ mappings.length }}</strong></header>
          <div v-if="mappings.length" class="lcd-mappings"><div v-for="mapping in mappings" :key="mapping.id || mapping.target_field"><span><strong>{{ mapping.target_field || mapping.ede_field }}</strong><small>{{ mapping.source_field || mapping.local_field || 'Sin origen configurado' }}</small></span><LibroDigitalStatusBadge :status="mapping.status || (mapping.source_field ? 'completed' : 'missing')" /></div></div>
          <LibroDigitalStatePanel v-else compact title="Sin mapeos publicados" message="La versión seleccionada aún no informa un catálogo de campos." />
        </BCard>
      </div>

      <BCard class="border-0 lcd-table-card" body-class="p-0" aria-labelledby="lcd-ede-history-title">
        <header><div><h3 id="lcd-ede-history-title">Historial EDE</h3><span>Paquetes inmutables y sus validaciones técnicas</span></div><BButton type="button" size="sm" variant="outline-secondary" aria-label="Actualizar historial EDE" @click="load"><i class="bx bx-reset" aria-hidden="true"></i></BButton></header>
        <div v-if="exportsHistory.length" class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th scope="col">Paquete</th><th scope="col">Versión</th><th scope="col">Creado</th><th scope="col">Validación</th><th scope="col">Huella</th><th scope="col" class="text-end">Acciones</th></tr></thead><tbody><tr v-for="record in exportsHistory" :key="record.id"><td><strong>{{ record.filename || `Paquete #${record.id}` }}</strong><small>{{ record.report_identifier || record.uuid }}</small></td><td>{{ record.ede_version?.version || record.ede_version || record.version || '—' }}</td><td>{{ formatDateTime(record.created_at) }}</td><td><LibroDigitalStatusBadge :status="record.validation_status || record.status" /></td><td><code>{{ String(record.sha256 || record.hash || '—').slice(0, 18) }}<template v-if="record.sha256 || record.hash">…</template></code></td><td><div class="lcd-actions"><BButton v-if="canValidateRecord(record)" type="button" size="sm" variant="outline-primary" :disabled="validatingId === record.id" @click="validateExport(record)">Validar</BButton><BButton v-if="canDownloadRecord(record)" type="button" size="sm" variant="outline-success" @click="downloadExport(record)"><i class="bx bx-download"></i> Descargar</BButton></div></td></tr></tbody></table></div>
        <LibroDigitalStatePanel v-else compact title="Sin paquetes EDE" message="Los paquetes generados aparecerán aquí con su versión, hash y resultado de validación." />
      </BCard>
    </template>

    <template v-else-if="activeTab === 'audit'">
      <div id="lcd-compliance-panel-audit" role="tabpanel" aria-labelledby="lcd-compliance-tab-audit" class="lcd-compliance__panel-stack">
      <BCard class="border-0 lcd-panel">
        <header><div><span class="lcd-eyebrow">EVIDENCIA INMUTABLE</span><h3>Explorador de auditoría</h3><p>Acota el alcance antes de verificar la continuidad criptográfica de los eventos.</p></div><BButton v-if="canVerifyAudit" type="button" size="sm" variant="outline-primary" :disabled="verifying" @click="verifyAudit"><span v-if="verifying" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-check-shield" aria-hidden="true"></i> Verificar cadena</BButton></header>
        <form class="lcd-audit-filters" @submit.prevent="load"><div><label for="lcd-audit-query">Buscar</label><BFormInput id="lcd-audit-query" v-model.trim="auditFilters.query" placeholder="Entidad, ID o correlación" /></div><div><label for="lcd-audit-entity">Entidad</label><BFormInput id="lcd-audit-entity" v-model.trim="auditFilters.entity_type" placeholder="session, attendance…" /></div><div><label for="lcd-audit-event">Evento</label><BFormInput id="lcd-audit-event" v-model.trim="auditFilters.event_type" placeholder="created, signed…" /></div><div><label for="lcd-audit-from">Desde</label><BFormInput id="lcd-audit-from" v-model="auditFilters.from" type="date" /></div><div><label for="lcd-audit-to">Hasta</label><BFormInput id="lcd-audit-to" v-model="auditFilters.to" type="date" /></div><BButton type="submit" variant="primary">Aplicar</BButton></form>
        <BAlert v-if="auditIntegrity" show :variant="auditIntegrity.valid || auditIntegrity.status === 'valid' ? 'success' : 'danger'" class="mb-0"><strong>{{ auditIntegrity.valid || auditIntegrity.status === 'valid' ? 'Cadena consistente para el alcance consultado.' : 'La verificación detectó diferencias.' }}</strong> {{ auditIntegrity.message || auditIntegrity.summary }}</BAlert>
      </BCard>
      <BCard class="border-0 lcd-table-card" body-class="p-0"><header><div><h3>Eventos registrados</h3><span>Actor, acción, entidad, momento, IP protegida y correlación</span></div><strong>{{ auditRows.length }}</strong></header><div v-if="auditRows.length" class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th scope="col">Fecha</th><th scope="col">Actor</th><th scope="col">Evento</th><th scope="col">Entidad</th><th scope="col">Cambios</th><th scope="col">Correlación</th></tr></thead><tbody><tr v-for="event in auditRows" :key="event.id"><td>{{ formatDateTime(event.occurred_at || event.created_at) }}</td><td><strong>{{ event.actor?.name || event.actor_name || 'Sistema' }}</strong><small>{{ event.actor_role || event.actor?.role }}</small></td><td><LibroDigitalStatusBadge :status="event.event_type || event.action" /></td><td><strong>{{ humanize(event.entity_type || event.auditable_type) }}</strong><small>#{{ event.entity_id || event.auditable_id }}</small></td><td>{{ event.changed_fields?.join?.(', ') || event.summary || '—' }}</td><td><code>{{ event.correlation_id || event.request_id || '—' }}</code></td></tr></tbody></table></div><LibroDigitalStatePanel v-else compact title="Sin eventos para estos filtros" message="Amplía el periodo o cambia el tipo de entidad consultado." /></BCard>
      </div>
    </template>

    <template v-else>
      <form id="lcd-compliance-panel-configuration" class="lcd-settings" role="tabpanel" aria-labelledby="lcd-compliance-tab-configuration" @submit.prevent="saveConfiguration">
        <BCard class="border-0 lcd-panel">
          <header><div><span>POLÍTICAS OPERATIVAS</span><h3>Configuración del módulo</h3></div><LibroDigitalStatusBadge :status="configForm.feature_flags.lcd_enabled ? 'active' : 'pending'" :label="configForm.feature_flags.lcd_enabled ? 'Módulo activo' : 'Módulo pausado'" /></header>
          <BAlert show variant="info">Los cambios se versionan y auditan. Desactivar una función no elimina ni modifica registros históricos.</BAlert>
          <div class="row g-3"><div class="col-md-6"><label class="form-label" for="lcd-config-timezone">Zona horaria</label><BFormInput id="lcd-config-timezone" v-model.trim="configForm.timezone" required /></div><div class="col-md-6"><label class="form-label" for="lcd-config-autosave">Guardado de asistencia (segundos)</label><BFormInput id="lcd-config-autosave" v-model.number="configForm.attendance_autosave_seconds" type="number" min="5" max="15" required /><small>Entre 5 y 15 segundos; nunca firma automáticamente.</small></div></div>
        </BCard>
        <BCard class="border-0 lcd-panel"><header><div><span>ACTIVACIÓN GRADUAL</span><h3>Feature flags</h3></div></header><div class="lcd-flags"><label v-for="(enabled, key) in configForm.feature_flags" :key="key"><span><strong>{{ humanize(key.replace('_enabled', '')) }}</strong><small>{{ key === 'ede_exports_enabled' ? 'Requiere versión y mapeo sin bloqueos.' : key === 'identity_features_enabled' ? 'Habilita nombre social y controles de visibilidad.' : 'Conserva los datos existentes al desactivarse.' }}</small></span><BFormCheckbox v-model="configForm.feature_flags[key]" switch :disabled="!canConfigure" :aria-label="`Activar ${humanize(key)}`" /></label></div></BCard>
        <footer><span><i class="bx bx-lock-alt"></i> La configuración usa control de versión para evitar sobrescrituras.</span><BButton type="submit" variant="primary" :disabled="saving || !canConfigure"><span v-if="saving" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-save"></i> Guardar configuración</BButton></footer>
      </form>
    </template>

    <BModal v-model="showStandardImport" title="Importar estándar EDE" size="lg" hide-footer lazy no-close-on-backdrop :no-close-on-esc="importingStandard" :hide-header-close="importingStandard">
      <BAlert show variant="warning" class="small"><strong>Importación sin activación:</strong> los tres artefactos se validan, versionan y archivan en privado. Esta operación no activa la versión, no genera paquetes y no envía datos a una autoridad externa.</BAlert>
      <form class="row g-3" @submit.prevent="importStandard">
        <div class="col-md-4"><label class="form-label" for="lcd-standard-version">Versión</label><BFormInput id="lcd-standard-version" v-model.trim="standardForm.version" pattern="[A-Za-z0-9._-]+" maxlength="50" required placeholder="2026.1" /></div>
        <div class="col-md-4"><label class="form-label" for="lcd-standard-code">Código</label><BFormInput id="lcd-standard-code" v-model.trim="standardForm.code" pattern="[A-Za-z0-9._-]+" maxlength="80" required /></div>
        <div class="col-md-4"><label class="form-label" for="lcd-standard-effective">Vigente desde</label><BFormInput id="lcd-standard-effective" v-model="standardForm.effective_from" type="date" /></div>
        <div class="col-md-5"><label class="form-label" for="lcd-standard-authority">Autoridad o fuente responsable</label><BFormInput id="lcd-standard-authority" v-model.trim="standardForm.authority" maxlength="160" required /></div>
        <div class="col-md-7"><label class="form-label" for="lcd-standard-url">URL oficial de origen</label><BFormInput id="lcd-standard-url" v-model.trim="standardForm.source_url" type="url" maxlength="2048" required placeholder="https://…" /></div>
        <div class="col-md-4"><label class="form-label" for="lcd-standard-source">Documento fuente</label><input id="lcd-standard-source" type="file" class="form-control" required @change="selectStandardFile('source', $event)"><small>Máximo 20 MB.</small></div>
        <div class="col-md-4"><label class="form-label" for="lcd-standard-schema">Esquema técnico</label><input id="lcd-standard-schema" type="file" class="form-control" required @change="selectStandardFile('schema', $event)"><small>Máximo 20 MB.</small></div>
        <div class="col-md-4"><label class="form-label" for="lcd-standard-mappings">Mapeos declarativos</label><input id="lcd-standard-mappings" type="file" class="form-control" accept=".json,application/json" required @change="selectStandardFile('mappings', $event)"><small>JSON, máximo 20 MB.</small></div>
        <div class="col-12 d-flex justify-content-end gap-2"><BButton type="button" variant="outline-secondary" :disabled="importingStandard" @click="showStandardImport = false">Cancelar</BButton><BButton type="submit" variant="primary" :disabled="importingStandard || !standardForm.source || !standardForm.schema || !standardForm.mappings"><span v-if="importingStandard" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-upload"></i> Importar para revisión</BButton></div>
      </form>
    </BModal>
  </section>
</template>

<style scoped>
.lcd-compliance{display:grid;gap:1rem;min-width:0;color:var(--lcd-ink,#273244)}
.lcd-compliance :deep(.btn-sm){min-height:36px}.lcd-compliance :deep(.btn:not(.btn-sm)){min-height:40px}.lcd-compliance :deep(.form-control),.lcd-compliance :deep(.form-select){min-height:40px;font-size:.8rem}.lcd-compliance :deep(.form-control-sm),.lcd-compliance :deep(.form-select-sm){min-height:36px}
.lcd-surface{border:1px solid var(--lcd-border,#dfe5ec);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.05))}
.lcd-compliance__hero{display:flex;align-items:center;justify-content:space-between;gap:1.2rem;padding:1rem 1.1rem;border-radius:var(--lcd-radius-lg,14px);background:linear-gradient(135deg,var(--lcd-surface,#fff) 58%,var(--lcd-brand-50,#f1f5fb));position:relative;overflow:hidden}
.lcd-compliance__hero::after{position:absolute;right:-35px;bottom:-76px;width:190px;height:190px;border:30px solid color-mix(in srgb,var(--lcd-brand-500,#405189) 8%,transparent);border-radius:50%;content:"";pointer-events:none}
.lcd-compliance__identity{display:flex;align-items:center;gap:.8rem;position:relative;z-index:1}.lcd-compliance__mark{display:grid;place-items:center;flex:0 0 48px;width:48px;height:48px;border-radius:var(--lcd-radius-md,10px);background:linear-gradient(145deg,var(--lcd-brand-900,#2f3e70),var(--lcd-brand-600,#5268a3));box-shadow:var(--lcd-shadow-md,0 8px 20px rgba(47,62,112,.2));color:#fff;font-size:1.35rem}
.lcd-eyebrow{display:block;color:var(--lcd-brand-700,#405189);font-size:.7rem;font-weight:800;letter-spacing:.08em}.lcd-section-heading h2{margin:.1rem 0 .28rem;color:var(--lcd-ink,#273244);font-size:clamp(1.12rem,2vw,1.42rem);letter-spacing:-.02em}.lcd-section-heading p,.lcd-panel>header p{margin:0;color:var(--lcd-muted,#6d7888);font-size:.8rem}
.lcd-compliance__scope{display:flex;align-items:flex-end;flex-direction:column;gap:.35rem;position:relative;z-index:1}.lcd-compliance__scope span{display:inline-flex;align-items:center;gap:.3rem;padding:.38rem .58rem;border:1px solid color-mix(in srgb,var(--lcd-brand-500,#405189) 14%,var(--lcd-border,#dfe5ec));border-radius:999px;background:color-mix(in srgb,var(--lcd-surface,#fff) 85%,transparent);color:var(--lcd-ink-soft,#4c596b);font-size:.72rem;font-weight:650}.lcd-compliance__scope i{color:var(--lcd-brand-600,#5268a3)}
.lcd-compliance__tabs{display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem;padding:.38rem;border-radius:var(--lcd-radius-lg,12px)}.lcd-compliance__tabs button{display:flex;align-items:center;gap:.55rem;min-width:0;min-height:58px;padding:.5rem .68rem;border:0;border-radius:var(--lcd-radius-md,9px);background:transparent;color:var(--lcd-muted,#687588);text-align:left;transition:var(--lcd-transition,all .16s ease)}.lcd-compliance__tab-icon{display:grid;place-items:center;flex:0 0 34px;width:34px;height:34px;border-radius:8px;background:var(--lcd-surface-muted,#f5f7fa);color:var(--lcd-brand-600,#5268a3);font-size:1rem}.lcd-compliance__tabs button>span:last-child,.lcd-compliance__tabs strong,.lcd-compliance__tabs small{display:block}.lcd-compliance__tabs strong{color:inherit;font-size:.78rem}.lcd-compliance__tabs small{margin-top:.08rem;color:var(--lcd-subtle,#8b96a5);font-size:.7rem;font-weight:500}.lcd-compliance__tabs button:hover{background:var(--lcd-surface-muted,#f5f7fa);color:var(--lcd-brand-700,#405189)}.lcd-compliance__tabs button.active{background:var(--lcd-brand-100,#e8edf8);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--lcd-brand-500,#405189) 15%,transparent);color:var(--lcd-brand-800,#354574)}.lcd-compliance__tabs button.active .lcd-compliance__tab-icon{background:var(--lcd-brand-700,#405189);color:#fff}.lcd-compliance__tabs button:focus-visible{outline:3px solid var(--lcd-focus,rgba(64,81,137,.25));outline-offset:1px}
.lcd-ede-process{display:grid;grid-template-columns:195px minmax(0,1fr);align-items:center;gap:.8rem;padding:.72rem .85rem;border-radius:var(--lcd-radius-md,10px)}.lcd-ede-process>div span,.lcd-ede-process>div strong{display:block}.lcd-ede-process>div span{color:var(--lcd-muted,#758194);font-size:.69rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em}.lcd-ede-process>div strong{margin-top:.12rem;color:var(--lcd-ink,#2d394b);font-size:.78rem}.lcd-ede-process ol{display:grid;grid-template-columns:repeat(4,1fr);gap:.35rem;margin:0;padding:0;list-style:none}.lcd-ede-process li{display:flex;align-items:center;gap:.4rem;min-width:0;padding:.5rem .55rem;border-radius:8px;background:var(--lcd-surface-muted,#f5f7fa);color:var(--lcd-muted,#758194)}.lcd-ede-process li>i{font-size:1rem}.lcd-ede-process li span,.lcd-ede-process li small{display:block}.lcd-ede-process li span{overflow:hidden;font-size:.72rem;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.lcd-ede-process li small{color:var(--lcd-subtle,#8b96a5);font-size:.68rem}.lcd-ede-process li.complete{background:color-mix(in srgb,var(--lcd-success,#0ab39c) 11%,var(--lcd-surface,#fff));color:#087b6c}.lcd-ede-process li.complete small{color:#299180}
.lcd-ede-grid{display:grid;grid-template-columns:minmax(0,1.18fr) minmax(290px,.82fr);gap:1rem}.lcd-ede-actions{display:flex;align-items:center;justify-content:flex-end;gap:.42rem;flex-wrap:wrap}
.lcd-panel,.lcd-table-card{border:1px solid var(--lcd-border,#dfe5ec)!important;border-radius:var(--lcd-radius-lg,12px)!important;background:var(--lcd-surface,#fff)!important;box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.05))!important}.lcd-panel>header,.lcd-table-card>header{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem;margin-bottom:.9rem}.lcd-panel>header h3,.lcd-table-card>header h3{margin:.1rem 0 0;color:var(--lcd-ink,#2d394b);font-size:.94rem}.lcd-panel .form-label{color:var(--lcd-ink-soft,#4c596b);font-size:.74rem;font-weight:750}.lcd-count{display:grid;place-items:center;min-width:36px;height:30px;border-radius:999px;background:var(--lcd-brand-100,#e8edf8);color:var(--lcd-brand-800,#354574);font-size:.78rem}
.lcd-blockers{margin-top:.85rem;padding:.7rem .8rem;border:1px solid color-mix(in srgb,var(--lcd-danger,#f06548) 22%,transparent);border-left:4px solid var(--lcd-danger,#f06548);border-radius:var(--lcd-radius-sm,7px);background:color-mix(in srgb,var(--lcd-danger,#f06548) 8%,var(--lcd-surface,#fff));color:#843c2d;font-size:.76rem}.lcd-blockers strong{display:flex;align-items:center;gap:.35rem}.lcd-blockers ul{margin:.42rem 0 0;padding-left:1.15rem;line-height:1.45}.lcd-ready{display:flex;align-items:center;gap:.6rem;margin-top:.85rem;padding:.7rem .8rem;border:1px solid color-mix(in srgb,var(--lcd-success,#0ab39c) 20%,transparent);border-radius:var(--lcd-radius-sm,7px);background:color-mix(in srgb,var(--lcd-success,#0ab39c) 8%,var(--lcd-surface,#fff));color:#087b6c;font-size:.76rem}.lcd-ready i{font-size:1.25rem}.lcd-ready span,.lcd-ready strong{display:block}
.lcd-panel>footer{display:flex;align-items:center;justify-content:space-between;gap:.8rem;margin-top:.9rem;padding-top:.75rem;border-top:1px solid var(--lcd-border,#e5e9ef)}.lcd-panel>footer small{max-width:55%;color:var(--lcd-muted,#748093);font-size:.72rem}.lcd-panel>footer .btn,.lcd-actions,.lcd-settings>footer .btn{display:flex;align-items:center;gap:.32rem}.lcd-panel .btn{min-height:36px;border-radius:var(--lcd-radius-sm,7px);font-size:.74rem}
.lcd-mappings{max-height:365px;overflow:auto;border-top:1px solid var(--lcd-border,#e5e9ef)}.lcd-mappings>div{display:flex;align-items:center;justify-content:space-between;gap:.65rem;padding:.65rem .9rem;border-bottom:1px solid var(--lcd-border,#edf0f4);transition:var(--lcd-transition,background .15s ease)}.lcd-mappings>div:hover{background:var(--lcd-surface-muted,#f7f8fa)}.lcd-mappings strong,.lcd-mappings small{display:block}.lcd-mappings strong{color:var(--lcd-ink-soft,#344154);font-size:.76rem}.lcd-mappings small{margin-top:.1rem;color:var(--lcd-muted,#7b8797);font-size:.7rem}
.lcd-table-card{overflow:hidden}.lcd-table-card>header{align-items:center;margin:0;padding:.76rem .9rem;border-bottom:1px solid var(--lcd-border,#e5e9ef)}.lcd-table-card header span{color:var(--lcd-muted,#788495);font-size:.72rem}.lcd-table-card table{font-size:.77rem}.lcd-table-card th{padding:.62rem .7rem;background:var(--lcd-surface-muted,#f5f7fa);color:var(--lcd-muted,#647184);font-size:.69rem;letter-spacing:.045em;text-transform:uppercase}.lcd-table-card td{padding:.62rem .7rem;border-color:var(--lcd-border,#edf0f4)}.lcd-table-card td strong,.lcd-table-card td small{display:block}.lcd-table-card td small{margin-top:.12rem;color:var(--lcd-muted,#7d8998);font-size:.7rem}.lcd-table-card code{color:var(--lcd-brand-700,#405189);font-size:.72rem}.lcd-actions{justify-content:flex-end}
.lcd-compliance__panel-stack{display:grid;gap:1rem}.lcd-audit-filters{display:grid;grid-template-columns:1.3fr repeat(4,1fr) auto;gap:.55rem;align-items:end;margin-bottom:.8rem;padding:.72rem;border:1px solid var(--lcd-border,#e5e9ef);border-radius:var(--lcd-radius-md,9px);background:var(--lcd-surface-muted,#f7f8fa)}.lcd-audit-filters label{display:block;margin-bottom:.22rem;color:var(--lcd-ink-soft,#687588);font-size:.69rem;font-weight:750;text-transform:uppercase;letter-spacing:.04em}.lcd-audit-filters :deep(.form-control),.lcd-audit-filters .btn{min-height:38px;font-size:.76rem}.lcd-audit-filters .btn{border-radius:var(--lcd-radius-sm,7px)}
.lcd-settings{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.lcd-settings>footer{grid-column:1/-1;display:flex;align-items:center;justify-content:flex-end;gap:1rem;padding:.72rem .85rem;border:1px solid var(--lcd-border,#dfe5ec);border-radius:var(--lcd-radius-md,9px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.04))}.lcd-settings>footer>span{margin-right:auto;color:var(--lcd-muted,#748093);font-size:.72rem}.lcd-settings>footer>span i{margin-right:.25rem;color:var(--lcd-brand-600,#5268a3)}.lcd-flags{display:grid}.lcd-flags>label{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.68rem .15rem;border-bottom:1px solid var(--lcd-border,#edf0f4);cursor:pointer}.lcd-flags>label:first-child{padding-top:.1rem}.lcd-flags>label:last-child{border-bottom:0}.lcd-flags strong,.lcd-flags small{display:block}.lcd-flags strong{color:var(--lcd-ink-soft,#344154);font-size:.76rem}.lcd-flags small{margin-top:.12rem;color:var(--lcd-muted,#788495);font-size:.7rem}
@media(max-width:1100px){.lcd-ede-grid,.lcd-settings{grid-template-columns:1fr}.lcd-ede-process{grid-template-columns:1fr}.lcd-audit-filters{grid-template-columns:repeat(3,1fr)}.lcd-settings>footer{grid-column:auto}}
@media(max-width:760px){.lcd-compliance__hero{align-items:flex-start;flex-direction:column}.lcd-compliance__scope{align-items:flex-start;flex-direction:row;flex-wrap:wrap}.lcd-compliance__tabs{display:flex;overflow:auto}.lcd-compliance__tabs button{min-width:190px}.lcd-ede-process ol{grid-template-columns:repeat(2,1fr)}.lcd-panel>header{align-items:flex-start;flex-direction:column}.lcd-ede-actions{justify-content:flex-start}.lcd-audit-filters{grid-template-columns:1fr 1fr}.lcd-audit-filters .btn{grid-column:1/-1}.lcd-panel>footer,.lcd-settings>footer{align-items:stretch;flex-direction:column}.lcd-panel>footer small{max-width:none}.lcd-panel>footer .btn,.lcd-settings>footer .btn{justify-content:center}.lcd-settings>footer>span{margin-right:0}}
@media(max-width:520px){.lcd-compliance__hero{padding:.85rem}.lcd-compliance__mark{display:none}.lcd-compliance__scope span:first-child{display:none}.lcd-ede-process ol{grid-template-columns:1fr}.lcd-audit-filters{grid-template-columns:1fr}.lcd-audit-filters .btn{grid-column:auto}.lcd-compliance__tabs button{min-width:165px}.lcd-table-card>header{align-items:flex-start}.lcd-ede-actions{align-items:stretch;flex-direction:column}}
@media(prefers-reduced-motion:reduce){.lcd-compliance__tabs button,.lcd-mappings>div{transition:none}}
</style>
