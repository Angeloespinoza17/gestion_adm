<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    templates: { type: Array, default: () => [] },
    modelValue: { type: [String, Number], default: "" },
    loading: { type: Boolean, default: false },
    continuation: { type: String, default: null },
    requiredSlideCount: { type: [String, Number], default: "" },
    requiredAspectRatio: { type: String, default: "" },
    requiredStyle: { type: String, default: "" },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "select", "search", "load-more", "refresh"]);
const query = ref("");

const visibleTemplates = computed(() => {
    const needle = query.value.trim().toLocaleLowerCase("es");
    if (!needle) return props.templates;
    return props.templates.filter((item) => String(item.title || item.name || "").toLocaleLowerCase("es").includes(needle));
});

function thumbnail(item) {
    return item.thumbnail_url || item.thumbnail?.url || item.preview_url || "";
}

function compatibilityMessages(item) {
    const value = item.compatibility_messages || item.compatibility_reasons || item.reasons || [];
    if (Array.isArray(value)) return value.filter(Boolean);
    return value ? [String(value)] : [];
}

function compatible(item) {
    return item.compatible !== false && item.compatibility?.compatible !== false;
}

function choose(item) {
    if (props.disabled || !compatible(item)) return;
    emit("update:modelValue", String(item.id));
    emit("select", item);
}

function search() {
    emit("search", query.value.trim());
}
</script>

<template>
    <section class="template-picker" aria-labelledby="canva-template-title">
        <div class="template-heading">
            <div>
                <span>Contrato de diseño real</span>
                <h3 id="canva-template-title">Elige una plantilla Canva compatible</h3>
                <p>La plantilla gobierna tipografía, colores, composición y número de páginas. La miniatura permite verificar el estilo antes de generar.</p>
            </div>
            <div class="template-requirements" aria-label="Requisitos de la plantilla">
                <small><i class="bx bx-slideshow"></i>{{ requiredSlideCount || '—' }} páginas</small>
                <small><i class="bx bx-crop"></i>{{ requiredAspectRatio || 'Formato definido' }}</small>
                <small><i class="bx bx-palette"></i>Estilo {{ requiredStyle || 'seleccionado' }}</small>
                <small><i class="bx bx-data"></i>Autofill obligatorio</small>
            </div>
        </div>

        <form class="template-search" @submit.prevent="search">
            <label for="canva-template-search" class="visually-hidden">Buscar plantilla Canva</label>
            <i class="bx bx-search" aria-hidden="true"></i>
            <input id="canva-template-search" v-model="query" type="search" placeholder="Buscar por nombre de plantilla" :disabled="disabled || loading">
            <button type="submit" :disabled="disabled || loading">Buscar</button>
            <button type="button" class="refresh-button" :disabled="disabled || loading" aria-label="Actualizar plantillas" @click="$emit('refresh')"><i class="bx bx-refresh"></i></button>
        </form>

        <div v-if="loading && !templates.length" class="template-loading" role="status"><span class="spinner-border"></span><strong>Consultando plantillas aprobadas…</strong></div>
        <div v-else-if="!visibleTemplates.length" class="template-empty">
            <i class="bx bx-layout"></i>
            <strong>No hay Brand Templates con campos Autofill</strong>
            <span>Crea o publica en Canva una Brand Template que incluya los campos Autofill requeridos y vuelve a consultar.</span>
            <button type="button" :disabled="disabled || loading" @click="$emit('refresh')"><i class="bx bx-refresh"></i>Volver a consultar</button>
        </div>
        <div v-else class="template-grid">
            <button
                v-for="item in visibleTemplates"
                :key="item.id"
                type="button"
                class="template-card"
                :class="{ selected: String(modelValue) === String(item.id), incompatible: !compatible(item) }"
                :disabled="disabled || loading || !compatible(item)"
                :aria-pressed="String(modelValue) === String(item.id)"
                @click="choose(item)"
            >
                <span class="template-preview">
                    <img v-if="thumbnail(item)" :src="thumbnail(item)" :alt="`Vista previa de ${item.title || item.name}`">
                    <span v-else class="preview-placeholder"><i class="bx bx-image-alt"></i>Vista previa no disponible</span>
                    <strong v-if="item.validation_pending" class="selected-mark"><span class="spinner-border spinner-border-sm"></span>Validando</strong>
                    <strong v-else-if="String(modelValue) === String(item.id)" class="selected-mark"><i class="bx bx-check"></i>Seleccionada</strong>
                    <strong v-else-if="!compatible(item)" class="blocked-mark"><i class="bx bx-lock-alt"></i>No compatible</strong>
                </span>
                <span class="template-body">
                    <span class="template-name">{{ item.title || item.name }}</span>
                    <span class="template-meta">
                        <small><i class="bx bx-slideshow"></i>{{ item.page_count || requiredSlideCount || '—' }} páginas</small>
                        <small><i class="bx bx-text"></i>{{ item.field_count ?? item.dataset_fields_count ?? '—' }} campos</small>
                        <small v-if="item.style_label"><i class="bx bx-palette"></i>{{ item.style_label }}</small>
                    </span>
                    <span v-if="compatibilityMessages(item).length" class="compatibility-copy">
                        <small v-for="reason in compatibilityMessages(item)" :key="reason">{{ reason }}</small>
                    </span>
                    <span v-else-if="item.compatible === true" class="compatibility-ok"><i class="bx bxs-check-circle"></i>Lista para esta clase</span>
                    <span v-else class="validation-required"><i class="bx bx-search-alt"></i>Selecciona para validar Autofill</span>
                    <span v-if="String(modelValue) === String(item.id)" class="visual-confirmation">La miniatura es el contrato visual final: verifica que corresponda al estilo {{ requiredStyle || 'solicitado' }}.</span>
                </span>
            </button>
        </div>

        <div v-if="continuation" class="load-more"><button type="button" :disabled="loading" @click="$emit('load-more')"><span v-if="loading" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-plus"></i>Cargar más plantillas</button></div>
    </section>
</template>

<style scoped>
.template-picker{margin:1.55rem 0;padding:1.2rem;border:1px solid #dce5ec;background:linear-gradient(180deg,#fbfcfe,#fff)}.template-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.template-heading>div:first-child>span{display:block;color:#6f4bd8;font-size:.65rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.template-heading h3{margin:.15rem 0;font-size:1.02rem;color:#203c59}.template-heading p{max-width:760px;margin:0;color:#697b8d;font-size:.76rem;line-height:1.45}.template-requirements{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.35rem}.template-requirements small{display:inline-flex;align-items:center;gap:.28rem;white-space:nowrap;padding:.3rem .48rem;background:#f0edf9;border:1px solid #e2daf7;color:#5a478e;border-radius:5px;font-size:.66rem;font-weight:750}.template-requirements i{font-size:.85rem}.template-search{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto;align-items:center;margin-bottom:1rem;border:1px solid #cad7e1;background:#fff;border-radius:7px;overflow:hidden}.template-search>i{margin-left:.75rem;color:#7a8b9c}.template-search input{min-width:0;border:0;outline:0;padding:.68rem .6rem;color:#344b60}.template-search button{align-self:stretch;border:0;border-left:1px solid #d9e2e9;background:#253f60;color:#fff;padding:0 .85rem;font-size:.74rem;font-weight:800}.template-search .refresh-button{background:#f3f6f8;color:#36546e;font-size:1rem}.template-search button:disabled{opacity:.55}.template-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem}.template-card{min-width:0;padding:0;text-align:left;border:1px solid #d7e1e8;background:#fff;border-radius:8px;overflow:hidden;color:#263d54;transition:transform .16s,border-color .16s,box-shadow .16s}.template-card:not(:disabled):hover{transform:translateY(-2px);border-color:#8f7ccf;box-shadow:0 10px 22px rgba(40,56,80,.1)}.template-card:focus-visible{outline:3px solid rgba(111,75,216,.23);outline-offset:2px}.template-card.selected{border:2px solid #6f4bd8;box-shadow:0 10px 24px rgba(111,75,216,.14)}.template-card.incompatible{background:#f8f9fa;filter:saturate(.65)}.template-preview{position:relative;display:block;aspect-ratio:16/9;background:#edf2f5;overflow:hidden}.template-preview img{width:100%;height:100%;display:block;object-fit:cover}.preview-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.35rem;color:#8393a1;font-size:.7rem}.preview-placeholder i{font-size:1.8rem}.selected-mark,.blocked-mark{position:absolute;left:.55rem;top:.55rem;display:inline-flex;align-items:center;gap:.25rem;padding:.27rem .45rem;border-radius:4px;background:#6f4bd8;color:#fff;font-size:.64rem;box-shadow:0 3px 8px rgba(0,0,0,.18)}.blocked-mark{background:#6f7b86}.template-body{display:flex;flex-direction:column;gap:.5rem;padding:.75rem}.template-name{font-size:.84rem;font-weight:850;line-height:1.3;color:#263f58}.template-meta{display:flex;flex-wrap:wrap;gap:.35rem .55rem}.template-meta small{display:inline-flex;align-items:center;gap:.22rem;color:#6d7e8e;font-size:.64rem}.template-meta i{color:#7160aa;font-size:.8rem}.compatibility-ok,.validation-required{display:inline-flex;align-items:center;gap:.25rem;font-size:.67rem;font-weight:800}.compatibility-ok{color:#23755d}.validation-required{color:#6f4bd8}.visual-confirmation{padding:.45rem;border-left:3px solid #6f4bd8;background:#f5f1fd;color:#5d4d86;font-size:.62rem;line-height:1.35}.compatibility-copy{display:flex;flex-direction:column;gap:.2rem;padding:.45rem;background:#f0f2f4;color:#6d4b43}.compatibility-copy small{font-size:.63rem;line-height:1.35}.template-loading,.template-empty{min-height:190px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.45rem;text-align:center;border:1px dashed #c7d4de;background:#f8fafb;color:#66788a;padding:1.4rem}.template-loading .spinner-border{width:1.6rem;height:1.6rem;color:#6f4bd8}.template-empty>i{font-size:2rem;color:#8b79c2}.template-empty strong{color:#354f67}.template-empty span{max-width:560px;font-size:.75rem}.template-empty button,.load-more button{display:inline-flex;align-items:center;gap:.3rem;border:1px solid #c9d5df;background:#fff;color:#294b68;border-radius:5px;padding:.48rem .65rem;font-size:.72rem;font-weight:800}.load-more{display:flex;justify-content:center;margin-top:1rem}
@media(max-width:991px){.template-heading{flex-direction:column}.template-requirements{justify-content:flex-start}.template-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575px){.template-picker{padding:.85rem}.template-grid{grid-template-columns:1fr}.template-search{grid-template-columns:auto minmax(0,1fr) auto}.template-search>button[type="submit"]{display:none}.template-requirements small{white-space:normal}}
</style>
