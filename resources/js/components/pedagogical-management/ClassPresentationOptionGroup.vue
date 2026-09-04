<script setup>
import { computed } from "vue";

const props = defineProps({
    label: { type: String, required: true },
    optionKey: { type: String, required: true },
    entries: { type: Array, default: () => [] },
    value: { type: [String, Number, Array], default: "" },
    multiple: { type: Boolean, default: false },
    max: { type: Number, default: 1 },
    descriptions: { type: Object, default: () => ({}) },
    hint: { type: String, default: "" },
    variant: { type: String, default: "compact" },
});
defineEmits(["select"]);

const selectedValues = computed(() => (Array.isArray(props.value) ? props.value : [props.value]).map(String));
function isSelected(value) { return selectedValues.value.includes(String(value)); }
</script>

<template>
    <fieldset class="option-group" :class="[`is-${variant}`, { 'allows-multiple': multiple }]">
        <legend>
            <span>{{ label }}</span>
            <small v-if="multiple">Selección múltiple · {{ selectedValues.length }}/{{ max }}</small>
            <small v-else>Una alternativa</small>
        </legend>
        <p v-if="hint" class="option-hint">{{ hint }}</p>
        <div class="option-list">
            <button
                v-for="entry in entries"
                :key="entry[0]"
                type="button"
                :class="{ selected: isSelected(entry[0]) }"
                :aria-pressed="isSelected(entry[0])"
                @click="$emit('select', optionKey, entry[0])"
            >
                <i class="bx" :class="isSelected(entry[0]) ? (multiple ? 'bxs-check-square' : 'bxs-check-circle') : (multiple ? 'bx-square' : 'bx-circle')"></i>
                <span class="option-copy">
                    <strong>{{ entry[1] }}</strong>
                    <small v-if="descriptions[entry[0]]">{{ descriptions[entry[0]] }}</small>
                </span>
            </button>
        </div>
    </fieldset>
</template>

<style scoped>
.option-group{min-width:0}.option-group legend{width:100%;display:flex;align-items:center;justify-content:space-between;gap:1rem}.option-group legend>span{font-size:.85rem;font-weight:850;color:#34495e}.option-group legend>small{font-size:.67rem;text-transform:uppercase;letter-spacing:.06em;color:#1a8d86;font-weight:800}.option-hint{margin:-.25rem 0 .7rem;color:#6b7c8f;font-size:.76rem;line-height:1.4}.option-list{display:flex;flex-wrap:wrap;gap:.5rem}.option-list button{display:flex;align-items:flex-start;gap:.5rem;text-align:left;border:1px solid #d8e2e9;background:#fff;padding:.65rem .75rem;color:#465a6d;border-radius:7px;transition:border-color .15s,background .15s,box-shadow .15s,transform .15s}.option-list button:hover{border-color:#91bab6;transform:translateY(-1px)}.option-list button:focus-visible{outline:3px solid rgba(26,141,134,.22);outline-offset:2px}.option-list button>i{font-size:1.05rem;color:#9caebb;margin-top:.05rem}.option-copy{display:flex;flex-direction:column;gap:.18rem}.option-copy strong{font-size:.8rem;line-height:1.25}.option-copy small{font-size:.69rem;line-height:1.35;color:#718294;font-weight:500}.option-list button.selected{border-color:#1a8d86;background:#eff9f7;color:#176d67;box-shadow:0 5px 14px rgba(26,141,134,.1)}.option-list button.selected>i{color:#1a8d86}.is-cards .option-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))}.is-cards .option-list button{min-height:86px;padding:.82rem}.is-cards .option-copy strong{font-size:.84rem}.is-style .option-list{display:grid;grid-template-columns:repeat(4,minmax(0,1fr))}.is-style .option-list button{position:relative;min-height:104px;padding:.9rem;overflow:hidden}.is-style .option-list button:after{content:"";position:absolute;right:-18px;bottom:-22px;width:54px;height:54px;border-radius:50%;background:#e8f5f3}.is-style .option-list button.selected:after{background:#ccece7}.is-style .option-copy strong{font-size:.88rem;color:#263f58}.is-style .option-copy small{max-width:190px}.allows-multiple legend>small{background:#eaf5f4;padding:.25rem .45rem;border-radius:4px}
@media(max-width:991px){.is-cards .option-list,.is-style .option-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575px){.option-group legend{align-items:flex-start;flex-direction:column;gap:.35rem}.option-list,.is-cards .option-list,.is-style .option-list{display:grid;grid-template-columns:1fr}.option-list button{width:100%}}
</style>
