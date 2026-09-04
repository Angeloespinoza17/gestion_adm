<script setup>
import { computed } from "vue";

const props = defineProps({
    connection: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
});

defineEmits(["connect", "disconnect", "refresh"]);

const configured = computed(() => props.connection?.configured === true);
const connected = computed(() => props.connection?.connected === true);
const reconnectRequired = computed(() => props.connection?.reconnect_required === true || props.connection?.needs_reauthorization === true || props.connection?.status === "expired");
const mode = computed(() => String(props.connection?.mode || "").toLowerCase());
const autofillAvailable = computed(() => props.connection?.autofill_available === true || ["enterprise", "development_trial"].includes(mode.value));
const enterpriseAutofill = computed(() => props.connection?.enterprise_autofill === true || mode.value === "enterprise");
const trialAutofill = computed(() => props.connection?.trial_enabled === true || mode.value === "development_trial");
const ready = computed(() => configured.value && connected.value && !reconnectRequired.value && autofillAvailable.value);
const accountName = computed(() => props.connection?.account?.display_name || props.connection?.account?.name || props.connection?.display_name || "Cuenta Canva conectada");
const teamName = computed(() => props.connection?.account?.team_name || props.connection?.team?.name || props.connection?.team_name || props.connection?.canva_team_id || "Equipo no informado");
const connectionTitle = computed(() => {
    if (ready.value && enterpriseAutofill.value) return `${accountName.value} · Enterprise Autofill`;
    if (ready.value && trialAutofill.value) return `${accountName.value} · Acceso de desarrollo/trial`;
    if (ready.value) return accountName.value;
    if (reconnectRequired.value) return "Vuelve a autorizar tu cuenta de Canva";
    if (connected.value && !autofillAvailable.value) return "Cuenta conectada · Autofill no disponible";
    if (configured.value) return "Conecta tu cuenta de Canva";
    return "Canva aún no está disponible en este entorno";
});
</script>

<template>
    <section class="canva-connection" :class="{ 'is-ready': ready, 'is-warning': configured && !ready, 'is-blocked': !configured }" aria-labelledby="canva-connection-title">
        <div class="canva-symbol" aria-hidden="true"><span>C</span></div>
        <div class="canva-copy">
            <div class="canva-kicker">
                <span>Integración de diseño</span>
                <strong v-if="ready && enterpriseAutofill"><i class="bx bxs-check-circle"></i> Operativa · Enterprise Autofill</strong>
                <strong v-else-if="ready && trialAutofill"><i class="bx bx-test-tube"></i> Conectada · Acceso de desarrollo/trial</strong>
                <strong v-else-if="ready"><i class="bx bxs-check-circle"></i> Autofill operativo</strong>
                <strong v-else-if="reconnectRequired"><i class="bx bx-refresh"></i> Requiere reconexión</strong>
                <strong v-else-if="connected && !autofillAvailable"><i class="bx bx-block"></i> Plan sin Autofill</strong>
                <strong v-else-if="configured"><i class="bx bx-link-alt"></i> Pendiente de conexión</strong>
                <strong v-else><i class="bx bx-cog"></i> No configurada</strong>
            </div>
            <h3 id="canva-connection-title">{{ connectionTitle }}</h3>
            <p v-if="ready && enterpriseAutofill">Canva Enterprise creará el diseño desde una Brand Template aprobada. ChatGPT se encargará del contenido y de la guía docente.</p>
            <p v-else-if="ready && trialAutofill">Autofill está habilitado mediante acceso de desarrollo/trial. Puedes validar el flujo con Brand Templates antes de disponer del acceso Enterprise.</p>
            <p v-else-if="ready">Canva creará el diseño desde una plantilla aprobada. ChatGPT se encargará del contenido y de la guía docente.</p>
            <p v-else-if="connected && !autofillAvailable">La cuenta está conectada, pero no tiene acceso a Brand Templates con Autofill. Se requiere una cuenta habilitada para esta capacidad.</p>
            <p v-else-if="configured">La autorización se realiza directamente en Canva. El sistema nunca muestra ni envía el secreto al navegador.</p>
            <p v-else>Un administrador debe completar las credenciales y la URL de retorno antes de generar nuevas presentaciones con Canva.</p>
            <div v-if="connected" class="canva-account-meta">
                <span><i class="bx bx-user"></i>{{ accountName }}</span>
                <span><i class="bx bx-group"></i>{{ teamName }}</span>
                <span v-if="enterpriseAutofill"><i class="bx bx-layout"></i>Enterprise · Brand Templates + Autofill</span>
                <span v-else-if="trialAutofill"><i class="bx bx-test-tube"></i>Desarrollo/trial · Brand Templates + Autofill</span>
                <span v-else-if="autofillAvailable"><i class="bx bx-layout"></i>Brand Templates + Autofill</span>
                <span v-else><i class="bx bx-block"></i>Autofill no habilitado</span>
            </div>
        </div>
        <div class="canva-actions">
            <button v-if="configured && (!connected || reconnectRequired)" type="button" class="primary-action" :disabled="busy" @click="$emit('connect')">
                <span v-if="busy" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-link-external"></i>
                {{ reconnectRequired ? 'Reconectar Canva' : 'Conectar Canva' }}
            </button>
            <template v-else-if="configured && connected">
                <button type="button" class="secondary-action" :disabled="busy" @click="$emit('refresh')"><i class="bx bx-refresh"></i>Verificar conexión</button>
                <button type="button" class="quiet-action" :disabled="busy" @click="$emit('disconnect')">Cambiar cuenta</button>
            </template>
        </div>
    </section>
</template>

<style scoped>
.canva-connection{position:relative;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:1rem;margin-bottom:1.5rem;padding:1.1rem 1.15rem;border:1px solid #d8e3ea;border-left:5px solid #7b61ff;background:linear-gradient(135deg,#fbfaff 0%,#fff 48%,#f4fbfa 100%);overflow:hidden}.canva-connection:after{content:"";position:absolute;right:-45px;bottom:-55px;width:150px;height:150px;border-radius:50%;background:radial-gradient(circle,rgba(0,196,204,.16),rgba(123,97,255,.04) 58%,transparent 60%);pointer-events:none}.canva-connection.is-ready{border-left-color:#159a86}.canva-connection.is-warning{border-left-color:#d28a25;background:linear-gradient(135deg,#fffaf2,#fff)}.canva-connection.is-blocked{border-left-color:#9aa8b5;background:#f7f9fb}.canva-symbol{position:relative;z-index:1;width:52px;height:52px;display:grid;place-items:center;border-radius:16px;background:linear-gradient(135deg,#00c4cc,#7d2ae8);box-shadow:0 8px 20px rgba(74,62,170,.2);color:#fff;font-family:Georgia,serif;font-style:italic;font-size:1.65rem}.canva-copy{position:relative;z-index:1;min-width:0}.canva-kicker{display:flex;align-items:center;flex-wrap:wrap;gap:.55rem;margin-bottom:.2rem}.canva-kicker>span{color:#6f5ac5;font-size:.64rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.canva-kicker>strong{display:inline-flex;align-items:center;gap:.25rem;padding:.22rem .42rem;border-radius:999px;background:#e8f6f2;color:#24745f;font-size:.65rem}.is-warning .canva-kicker>strong{background:#fff0d5;color:#8c611f}.is-blocked .canva-kicker>strong{background:#e9eef2;color:#61707e}.canva-copy h3{margin:0;font-size:1rem;color:#213a55}.canva-copy p{max-width:820px;margin:.25rem 0 0;color:#65778a;font-size:.77rem;line-height:1.45}.canva-account-meta{display:flex;flex-wrap:wrap;gap:.4rem .85rem;margin-top:.55rem}.canva-account-meta span{display:inline-flex;align-items:center;gap:.3rem;color:#496276;font-size:.69rem;font-weight:700}.canva-account-meta i{color:#168b82;font-size:.9rem}.canva-actions{position:relative;z-index:1;display:flex;align-items:flex-end;flex-direction:column;gap:.4rem}.canva-actions button{display:inline-flex;align-items:center;justify-content:center;gap:.38rem;white-space:nowrap;border-radius:6px;font-size:.75rem;font-weight:800;padding:.58rem .75rem}.primary-action{border:1px solid #6f4bd8;background:#6f4bd8;color:#fff;box-shadow:0 6px 14px rgba(111,75,216,.18)}.secondary-action{border:1px solid #c9d5df;background:#fff;color:#274967}.quiet-action{border:0;background:transparent;color:#7a6075;padding:.25rem .5rem!important}.canva-actions button:focus-visible{outline:3px solid rgba(111,75,216,.22);outline-offset:2px}.canva-actions button:disabled{opacity:.6;cursor:not-allowed}
@media(max-width:767px){.canva-connection{grid-template-columns:auto 1fr;align-items:start}.canva-actions{grid-column:1/-1;width:100%;align-items:stretch}.canva-actions button{width:100%}.canva-account-meta{flex-direction:column;gap:.35rem}}
</style>
