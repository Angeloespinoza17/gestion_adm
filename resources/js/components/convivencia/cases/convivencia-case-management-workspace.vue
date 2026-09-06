<script>
import ConvivenciaOperationalSection from "../operations/convivencia-operational-section.vue";

const recordTypes = Object.freeze([
  {
    key: "casos",
    state: "cases",
    label: "Casos",
    singular: "caso",
    icon: "bx-folder-open",
    description: "Expedientes formales, responsables, clasificación RICE y seguimiento.",
  },
  {
    key: "denuncias",
    state: "complaints",
    label: "Denuncias",
    singular: "denuncia",
    icon: "bx-message-square-error",
    description: "Recepción, admisibilidad y eventual conversión a un caso formal.",
  },
  {
    key: "derivaciones",
    state: "derivations",
    label: "Derivaciones",
    singular: "derivación",
    icon: "bx-transfer-alt",
    description: "Envíos internos o externos, plazos, respuestas y cierre trazable.",
  },
]);

export default {
  name: "ConvivenciaCaseManagementWorkspace",
  components: { ConvivenciaOperationalSection },
  props: {
    selectedType: { type: String, default: "casos" },
    states: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    capabilities: { type: Object, default: () => ({}) },
    canCreateProvider: { type: Function, required: true },
    actionProvider: { type: Function, required: true },
  },
  emits: ["update:selectedType", "create", "refresh", "page", "action"],
  computed: {
    availableTypes() {
      const capabilities = this.capabilities || {};
      return recordTypes.filter(({ key }) => {
        if (key === "casos") return capabilities.can_view_cases === true || capabilities.can_create_cases === true;
        if (key === "denuncias") return capabilities.can_manage_complaints === true || capabilities.can_view_cases === true;
        return capabilities.can_manage_internal_derivations === true
          || capabilities.can_manage_external_derivations === true
          || capabilities.can_view_cases === true;
      });
    },
    activeType() {
      return this.availableTypes.find(({ key }) => key === this.selectedType) || this.availableTypes[0] || recordTypes[0];
    },
    activeState() {
      return this.states[this.activeType.state] || { loading: false, items: [], filters: {}, pagination: null };
    },
  },
  methods: {
    countFor(type) {
      const state = this.states[type.state] || {};
      if (state.loading) return "…";
      if (!state.pagination) return "—";
      return Number(state.pagination.total ?? state.items?.length ?? 0);
    },
    choose(type) {
      if (type.key !== this.activeType.key) this.$emit("update:selectedType", type.key);
    },
  },
};
</script>

<template>
  <section class="case-management-workspace" aria-labelledby="case-management-title">
    <header class="case-management-workspace__header">
      <div class="case-management-workspace__heading">
        <span aria-hidden="true"><i class="bx bx-layer"></i></span>
        <div>
          <small>GESTIÓN UNIFICADA</small>
          <h2 id="case-management-title">Expedientes de convivencia</h2>
          <p>Casos, denuncias y derivaciones se administran desde el mismo espacio y con un único formulario guiado.</p>
        </div>
      </div>
      <div class="case-management-workspace__hint"><i class="bx bx-shield-quarter"></i><span>Permisos y confidencialidad se aplican a cada registro.</span></div>
    </header>

    <div class="case-management-workspace__types" role="tablist" aria-label="Tipo de registro de convivencia">
      <button
        v-for="type in availableTypes"
        :key="type.key"
        type="button"
        role="tab"
        :aria-selected="activeType.key === type.key"
        :class="{ 'is-active': activeType.key === type.key }"
        @click="choose(type)"
      >
        <span class="case-management-workspace__type-icon" aria-hidden="true"><i class="bx" :class="type.icon"></i></span>
        <span class="case-management-workspace__type-copy"><b>{{ type.label }}</b><small>{{ type.description }}</small></span>
        <span class="case-management-workspace__type-count" :aria-label="`${countFor(type)} registros`">{{ countFor(type) }}</span>
      </button>
    </div>

    <ConvivenciaOperationalSection
      :key="activeType.key"
      :section="activeType.key"
      :state="activeState"
      :options="options"
      :can-create="canCreateProvider(activeType.key)"
      :action-provider="actionProvider"
      @create="$emit('create', activeType.key)"
      @refresh="$emit('refresh', activeType.key)"
      @page="$emit('page', activeType.key, $event)"
      @action="(action, item) => $emit('action', activeType.key, action, item)"
    />
  </section>
</template>

<style scoped>
.case-management-workspace{display:flex;flex-direction:column;gap:1rem}.case-management-workspace__header{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;color:#fff;border-radius:18px;background:radial-gradient(circle at 92% 12%,rgba(112,211,183,.28),transparent 30%),linear-gradient(128deg,#263b9d 0%,#5268dc 60%,#2a8c7c 118%);box-shadow:0 14px 30px rgba(38,59,157,.16)}.case-management-workspace__heading{display:flex;min-width:0;align-items:center;gap:.8rem}.case-management-workspace__heading>span{display:grid;width:48px;height:48px;flex:0 0 48px;font-size:1.35rem;place-items:center;border:1px solid rgba(255,255,255,.28);border-radius:14px;background:rgba(255,255,255,.12)}.case-management-workspace__heading small{display:block;margin-bottom:.12rem;color:rgba(255,255,255,.72);font-size:.54rem;font-weight:850;letter-spacing:.12em}.case-management-workspace__heading h2{margin:0;font-size:1rem;font-weight:850}.case-management-workspace__heading p{max-width:680px;margin:.18rem 0 0;color:rgba(255,255,255,.78);font-size:.65rem;line-height:1.45}.case-management-workspace__hint{display:flex;max-width:245px;align-items:center;gap:.45rem;padding:.55rem .7rem;color:rgba(255,255,255,.85);font-size:.57rem;line-height:1.35;border:1px solid rgba(255,255,255,.2);border-radius:11px;background:rgba(255,255,255,.1)}.case-management-workspace__hint i{font-size:1rem}.case-management-workspace__types{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}.case-management-workspace__types>button{position:relative;display:grid;min-width:0;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.65rem;padding:.75rem .8rem;text-align:left;border:1px solid #dfe5ef;border-radius:14px;background:linear-gradient(145deg,#fff,#fafbfe);box-shadow:0 6px 16px rgba(35,48,80,.05);transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease}.case-management-workspace__types>button:hover{transform:translateY(-2px);border-color:#bcc7ef;box-shadow:0 10px 22px rgba(48,65,145,.1)}.case-management-workspace__types>button:focus-visible{outline:3px solid rgba(79,99,217,.2);outline-offset:2px}.case-management-workspace__types>button.is-active{border-color:#8291e5;background:linear-gradient(145deg,#f6f8ff,#eef1ff);box-shadow:0 10px 22px rgba(55,73,168,.13)}.case-management-workspace__types>button.is-active::after{position:absolute;right:12px;bottom:-1px;left:12px;height:3px;content:"";border-radius:99px 99px 0 0;background:linear-gradient(90deg,#4f63d9,#2d9b82)}.case-management-workspace__type-icon{display:grid;width:38px;height:38px;color:#5267d8;font-size:1.05rem;place-items:center;border-radius:11px;background:#eef1ff}.is-active .case-management-workspace__type-icon{color:#fff;background:linear-gradient(135deg,#4f63d9,#2e9a83);box-shadow:0 6px 13px rgba(65,86,194,.2)}.case-management-workspace__type-copy{display:grid;min-width:0;gap:.1rem}.case-management-workspace__type-copy b{color:#30406c;font-size:.7rem}.case-management-workspace__type-copy small{display:-webkit-box;overflow:hidden;color:#7d889a;font-size:.55rem;line-height:1.35;-webkit-box-orient:vertical;-webkit-line-clamp:2}.case-management-workspace__type-count{display:grid;min-width:28px;height:28px;padding:0 .35rem;color:#5062c8;font-size:.62rem;font-weight:850;place-items:center;border-radius:999px;background:#eef1ff}.is-active .case-management-workspace__type-count{color:#23745f;background:#daf2eb}
@media(max-width:991.98px){.case-management-workspace__header{align-items:flex-start;flex-direction:column}.case-management-workspace__hint{max-width:none}.case-management-workspace__types{grid-template-columns:1fr}.case-management-workspace__type-copy small{-webkit-line-clamp:1}}@media(max-width:575.98px){.case-management-workspace__header{padding:.85rem}.case-management-workspace__heading{align-items:flex-start}.case-management-workspace__heading>span{display:none}.case-management-workspace__heading h2{font-size:.92rem}.case-management-workspace__types{display:flex;overflow-x:auto;padding-bottom:.15rem;scroll-snap-type:x proximity}.case-management-workspace__types>button{min-width:235px;scroll-snap-align:start}.case-management-workspace__type-copy small{display:none}}
</style>
