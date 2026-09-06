<script>
import CriticalityBadge from "../criticality-badge.vue";
import StatusBadge from "../status-badge.vue";
import ConvivenciaDataTable from "../ui/convivencia-data-table.vue";
import ConvivenciaRowActions from "../ui/convivencia-row-actions.vue";
import ConvivenciaSectionToolbar from "../ui/convivencia-section-toolbar.vue";
import { formatConvivenciaDate, formatConvivenciaDateTime, humanizeConvivenciaStatus } from "../module-utils";

const sectionMeta = {
  planes: { title: "Planes de gestión", description: "Objetivos, acciones y avance anual en un solo listado.", icon: "bx-calendar-check", singular: "plan", create: "Nuevo plan" },
  casos: { title: "Casos de convivencia", description: "Expedientes, criticidad, responsables y estado de seguimiento.", icon: "bx-folder-open", singular: "caso", create: "Abrir caso" },
  denuncias: { title: "Denuncias recibidas", description: "Ingreso, revisión y conversión trazable a expediente.", icon: "bx-message-square-error", singular: "denuncia", create: "Nueva denuncia" },
  derivaciones: { title: "Derivaciones", description: "Gestiones internas y redes externas con prioridad y plazo.", icon: "bx-transfer-alt", singular: "derivación", create: "Nueva derivación" },
  entrevistas: { title: "Entrevistas", description: "Participantes, acuerdos, compromisos y próximos seguimientos.", icon: "bx-conversation", singular: "entrevista", create: "Nueva entrevista" },
  medidas: { title: "Medidas formativas", description: "Asignación, propósito reparador, responsable y vencimiento.", icon: "bx-check-shield", singular: "medida", create: "Nueva medida" },
  bitacora: { title: "Bitácora diaria", description: "Hechos de la jornada y escalamiento a casos o derivaciones.", icon: "bx-book-content", singular: "registro", create: "Nuevo hecho" },
  sociogramas: { title: "Sociogramas", description: "Aplicaciones protegidas y trazabilidad de sus respuestas.", icon: "bx-network-chart", singular: "sociograma", create: "Nuevo sociograma" },
};

export default {
  name: "ConvivenciaOperationalSection",
  components: { CriticalityBadge, StatusBadge, ConvivenciaDataTable, ConvivenciaRowActions, ConvivenciaSectionToolbar },
  props: {
    section: { type: String, required: true },
    state: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    canCreate: { type: Boolean, default: false },
    actionProvider: { type: Function, default: () => [] },
  },
  emits: ["create", "refresh", "action", "page"],
  computed: {
    meta() { return sectionMeta[this.section] || sectionMeta.casos; },
    items() { return this.state.items || []; },
    total() { return Number(this.state.pagination?.total ?? this.items.length); },
  },
  methods: {
    date: formatConvivenciaDate,
    dateTime: formatConvivenciaDateTime,
    status: humanizeConvivenciaStatus,
    rowLabel(item) { return item.folio || item.name || item.title || `${this.meta.singular} ${item.id || ""}`.trim(); },
  },
};
</script>

<template>
  <div class="convivencia-operational-section" :class="`is-${section}`">
    <ConvivenciaSectionToolbar
      :title="meta.title"
      :description="meta.description"
      :icon="meta.icon"
      :primary-label="meta.create"
      :can-create="canCreate"
      :refreshing="state.loading"
      @create="$emit('create')"
      @refresh="$emit('refresh')"
    >
      <template #filters>
        <div v-if="section === 'planes'" class="convivencia-toolbar-filter">
          <BFormSelect v-model="state.filters.academic_year_id" :options="options.academicYearsWithAll" aria-label="Filtrar por año" @change="$emit('refresh')" />
          <BFormSelect v-model="state.filters.status" :options="options.planStatusesWithAll" aria-label="Filtrar por estado" @change="$emit('refresh')" />
        </div>
        <div v-else-if="section === 'casos'" class="convivencia-toolbar-filter is-wide">
          <BFormInput v-model="state.filters.search" type="search" placeholder="Folio, relato o estudiante" aria-label="Buscar casos" @keyup.enter="$emit('refresh')" />
          <BFormSelect v-model="state.filters.course_section_id" :options="options.coursesWithAll" aria-label="Filtrar por curso" @change="$emit('refresh')" />
          <BFormSelect v-model="state.filters.status" :options="options.caseStatusesWithAll" aria-label="Filtrar por estado" @change="$emit('refresh')" />
        </div>
        <div v-else-if="section === 'denuncias'" class="convivencia-toolbar-filter is-wide">
          <BFormInput v-model="state.filters.search" type="search" placeholder="Folio, relato o denunciante" aria-label="Buscar denuncias" @keyup.enter="$emit('refresh')" />
          <BFormSelect v-model="state.filters.course_section_id" :options="options.coursesWithAll" aria-label="Filtrar por curso" @change="$emit('refresh')" />
          <BFormSelect v-model="state.filters.status" :options="options.complaintStatusesWithAll" aria-label="Filtrar por estado" @change="$emit('refresh')" />
        </div>
        <div v-else-if="section === 'derivaciones'" class="convivencia-toolbar-filter is-wide">
          <BFormInput v-model="state.filters.search" type="search" placeholder="Caso, estudiante, motivo o destino" aria-label="Buscar derivaciones" @keyup.enter="$emit('refresh')" />
          <BFormSelect v-model="state.filters.scope" :options="options.derivationScopesWithAll" aria-label="Filtrar por alcance" @change="$emit('refresh')" />
          <BFormSelect v-model="state.filters.status" :options="options.derivationStatusesWithAll" aria-label="Filtrar por estado" @change="$emit('refresh')" />
        </div>
        <div v-else-if="section === 'entrevistas'" class="convivencia-toolbar-filter">
          <BFormSelect v-model="state.filters.course_section_id" :options="options.coursesWithAll" aria-label="Filtrar por curso" @change="$emit('refresh')" />
          <BFormSelect v-model="state.filters.follow_up_status" :options="options.interviewStatusesWithAll" aria-label="Filtrar por seguimiento" @change="$emit('refresh')" />
        </div>
        <div v-else-if="section === 'medidas'" class="convivencia-toolbar-filter">
          <BFormSelect v-model="state.filters.course_section_id" :options="options.coursesWithAll" aria-label="Filtrar por curso" @change="$emit('refresh')" />
          <BFormSelect v-model="state.filters.status" :options="options.measureStatusesWithAll" aria-label="Filtrar por estado" @change="$emit('refresh')" />
        </div>
        <div v-else-if="section === 'bitacora'" class="convivencia-toolbar-filter is-wide">
          <BFormSelect v-model="state.filters.course_section_id" :options="options.coursesWithAll" aria-label="Filtrar por curso" @change="$emit('refresh')" />
          <BFormSelect v-model="state.filters.daily_log_type_item_id" :options="options.dailyLogTypesWithAll" aria-label="Filtrar por tipo" @change="$emit('refresh')" />
          <BFormSelect v-model="state.filters.status" :options="options.dailyLogStatusesWithAll" aria-label="Filtrar por estado" @change="$emit('refresh')" />
        </div>
        <div v-else-if="section === 'sociogramas'" class="convivencia-toolbar-filter">
          <BFormSelect v-model="state.filters.course_section_id" :options="options.coursesWithAll" aria-label="Filtrar por curso" @change="$emit('refresh')" />
          <BFormSelect v-model="state.filters.status" :options="options.sociogramStatusesWithAll" aria-label="Filtrar por estado" @change="$emit('refresh')" />
        </div>
      </template>
    </ConvivenciaSectionToolbar>

    <ConvivenciaDataTable
      :title="meta.title"
      :subtitle="meta.description"
      :icon="meta.icon"
      :count="total"
      :loading="state.loading"
      :empty="items.length === 0"
      :empty-title="`No hay ${meta.title.toLocaleLowerCase('es-CL')}`"
      :empty-text="`Ajusta los filtros o registra un nuevo ${meta.singular}.`"
      min-width="900px"
    >
      <template #empty-action><button v-if="canCreate" type="button" class="btn btn-sm btn-primary mt-2" @click="$emit('create')"><i class="bx bx-plus me-1"></i>{{ meta.create }}</button></template>
      <table class="table align-middle">
        <thead v-if="section === 'planes'"><tr><th>Plan</th><th>Año</th><th>Responsable</th><th>Estado</th><th>Avance</th><th class="text-end">Acciones</th></tr></thead>
        <thead v-else-if="section === 'casos'"><tr><th>Folio</th><th>Estudiante / curso</th><th>Clasificación</th><th>Criticidad</th><th>Estado</th><th>Seguimiento</th><th class="text-end">Acciones</th></tr></thead>
        <thead v-else-if="section === 'denuncias'"><tr><th>Folio</th><th>Situación</th><th>Estudiante</th><th>Denunciante</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <thead v-else-if="section === 'derivaciones'"><tr><th>Fecha</th><th>Ámbito</th><th>Destino</th><th>Prioridad</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <thead v-else-if="section === 'entrevistas'"><tr><th>Fecha</th><th>Tipo</th><th>Motivo</th><th>Participantes</th><th>Seguimiento</th><th class="text-end">Acciones</th></tr></thead>
        <thead v-else-if="section === 'medidas'"><tr><th>Tipo</th><th>Estudiante / curso</th><th>Responsable</th><th>Estado</th><th>Vence</th><th class="text-end">Acciones</th></tr></thead>
        <thead v-else-if="section === 'bitacora'"><tr><th>Fecha</th><th>Tipo</th><th>Estudiante / curso</th><th>Descripción</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <thead v-else><tr><th>Título</th><th>Curso</th><th>Aplicación</th><th>Preguntas / respuestas</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <template v-if="section === 'planes'">
              <td data-label="Plan"><b class="table-primary-text">{{ item.name }}</b><small>{{ item.general_objective || "Sin objetivo registrado" }}</small></td>
              <td data-label="Año">{{ item.academic_year?.name || "-" }}</td><td data-label="Responsable">{{ item.responsible?.name || item.responsible_user?.name || "-" }}</td><td data-label="Estado"><StatusBadge :status="item.status" /></td><td data-label="Avance"><span class="table-progress"><i :style="{ width: `${Number(item.advance_percentage || 0)}%` }"></i><b>{{ Number(item.advance_percentage || 0) }}%</b></span></td>
            </template>
            <template v-else-if="section === 'casos'">
              <td data-label="Folio"><b class="table-primary-text">{{ item.folio }}</b><small>{{ dateTime(item.opened_at) }}</small></td><td data-label="Estudiante / curso"><b>{{ item.student?.registered_name_resolved || item.student?.registered_name || "Identidad protegida" }}</b><small>{{ item.course_section?.display_name || item.courseSection?.display_name || "Sin curso" }}</small></td><td data-label="Clasificación">{{ item.classification_label || "-" }}</td><td data-label="Criticidad"><CriticalityBadge :value="item.criticality_label || item.criticality?.name" /></td><td data-label="Estado"><StatusBadge :status="item.status" /></td><td data-label="Seguimiento">{{ item.follow_up_due_at ? dateTime(item.follow_up_due_at) : "Sin fecha" }}</td>
            </template>
            <template v-else-if="section === 'denuncias'">
              <td data-label="Folio"><b class="table-primary-text">{{ item.folio }}</b><small>{{ dateTime(item.created_at) }}</small><span v-if="item.case_id" class="table-case-link"><i class="bx bx-link-alt"></i>{{ item.case?.folio || "Caso vinculado" }}</span></td><td data-label="Situación">{{ item.situation_type_label || item.situation_type?.name || "-" }}</td><td data-label="Estudiante">{{ item.affected_student?.registered_name_resolved || item.affected_student?.registered_name || "Identidad protegida" }}</td><td data-label="Denunciante">{{ status(item.complainant_type) }}</td><td data-label="Estado"><StatusBadge :status="item.status" /></td>
            </template>
            <template v-else-if="section === 'derivaciones'">
              <td data-label="Fecha">{{ dateTime(item.derived_at) }}<span v-if="item.case_id" class="table-case-link"><i class="bx bx-link-alt"></i>{{ item.case?.folio || "Caso vinculado" }}</span></td><td data-label="Ámbito"><span class="table-scope"><i class="bx" :class="item.scope === 'internal' ? 'bx-building' : 'bx-world'"></i>{{ item.scope === "internal" ? "Interna" : "Externa" }}</span></td><td data-label="Destino">{{ item.destination_label || item.destination_department?.name || item.external_institution?.name || "-" }}</td><td data-label="Prioridad"><CriticalityBadge :value="item.priority_level" /></td><td data-label="Estado"><StatusBadge :status="item.status" /></td>
            </template>
            <template v-else-if="section === 'entrevistas'">
              <td data-label="Fecha">{{ dateTime(item.interview_at) }}</td><td data-label="Tipo">{{ item.interview_type_label || item.type?.name || "-" }}</td><td data-label="Motivo"><span class="table-clamp">{{ item.motive || "-" }}</span></td><td data-label="Participantes">{{ item.participants_count ?? item.participants?.length ?? 0 }}</td><td data-label="Seguimiento"><StatusBadge :status="item.follow_up_status" /></td>
            </template>
            <template v-else-if="section === 'medidas'">
              <td data-label="Tipo"><b class="table-primary-text">{{ item.measure_type_label || item.type?.name || "-" }}</b></td><td data-label="Estudiante / curso"><b>{{ item.student?.registered_name_resolved || item.student?.registered_name || "Sin estudiante" }}</b><small>{{ item.course_section?.display_name || "" }}</small></td><td data-label="Responsable">{{ item.responsible?.name || item.responsible_user?.name || "-" }}</td><td data-label="Estado"><StatusBadge :status="item.status" /></td><td data-label="Vence">{{ dateTime(item.due_at) }}</td>
            </template>
            <template v-else-if="section === 'bitacora'">
              <td data-label="Fecha">{{ dateTime(item.happened_at) }}</td><td data-label="Tipo">{{ item.daily_log_type_label || item.type?.name || "-" }}</td><td data-label="Estudiante / curso"><b>{{ item.student?.registered_name_resolved || item.student?.registered_name || "Registro general" }}</b><small>{{ item.course_section?.display_name || "" }}</small></td><td data-label="Descripción"><span class="table-clamp">{{ item.description }}</span></td><td data-label="Estado"><StatusBadge :status="item.status" /></td>
            </template>
            <template v-else>
              <td data-label="Título"><b class="table-primary-text">{{ item.title }}</b></td><td data-label="Curso">{{ item.courseSection?.display_name || item.course_section?.display_name || "-" }}</td><td data-label="Aplicación">{{ date(item.applied_on) }}</td><td data-label="Preguntas / respuestas">{{ item.questions_count ?? item.questions?.length ?? 0 }} / {{ item.answers_count ?? item.answers?.length ?? 0 }}</td><td data-label="Estado"><StatusBadge :status="item.status" /></td>
            </template>
            <td data-label="Acciones" class="text-end"><ConvivenciaRowActions :actions="actionProvider(section, item)" :item-label="rowLabel(item)" @select="$emit('action', $event, item)" /></td>
          </tr>
        </tbody>
      </table>
      <template v-if="state.pagination?.last_page > 1" #footer>
        <div class="convivencia-operational-section__pagination">
          <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="state.loading || state.pagination.current_page <= 1" @click="$emit('page', state.pagination.current_page - 1)"><i class="bx bx-chevron-left"></i>Anterior</button>
          <span>Página <b>{{ state.pagination.current_page }}</b> de {{ state.pagination.last_page }} · {{ total }} registros</span>
          <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="state.loading || state.pagination.current_page >= state.pagination.last_page" @click="$emit('page', state.pagination.current_page + 1)">Siguiente<i class="bx bx-chevron-right"></i></button>
        </div>
      </template>
    </ConvivenciaDataTable>
  </div>
</template>

<style scoped>
.convivencia-operational-section{display:flex;flex-direction:column;gap:.85rem}.convivencia-toolbar-filter{display:grid;grid-template-columns:repeat(2,minmax(145px,1fr));gap:.4rem}.convivencia-toolbar-filter.is-wide{grid-template-columns:repeat(3,minmax(140px,1fr))}.convivencia-toolbar-filter :deep(.form-control),.convivencia-toolbar-filter :deep(.form-select){min-height:34px;font-size:.66rem;border-color:#dce2ed;border-radius:9px}.table-primary-text{display:block;color:#2f4184;font-size:.7rem}.convivencia-operational-section td small{display:block;margin-top:.12rem;color:#8a94a4;font-size:.58rem}.table-case-link{display:inline-flex;align-items:center;gap:.2rem;margin-top:.28rem;padding:.16rem .38rem;color:#24735f;font-size:.55rem;font-weight:780;border-radius:99px;background:#e4f5ef}.table-progress{display:grid;grid-template-columns:minmax(70px,1fr) auto;align-items:center;gap:.4rem}.table-progress::before{content:"";grid-column:1;grid-row:1;height:6px;border-radius:99px;background:#e7eaf3}.table-progress i{z-index:1;grid-column:1;grid-row:1;height:6px;border-radius:99px;background:linear-gradient(90deg,#4f63d9,#2c9b80)}.table-progress b{font-size:.62rem}.table-scope{display:inline-flex;align-items:center;gap:.3rem}.table-clamp{display:-webkit-box;max-width:300px;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2}.convivencia-operational-section__pagination{display:flex;align-items:center;justify-content:space-between;gap:.6rem;color:#788498;font-size:.64rem}.convivencia-operational-section__pagination .btn{display:inline-flex;align-items:center;gap:.18rem;font-size:.62rem}
@media(min-width:768px){.convivencia-operational-section :deep(th:last-child),.convivencia-operational-section :deep(td:last-child){position:sticky;right:0;z-index:2;border-left:1px solid #e7eaf1;background:#fff;box-shadow:-12px 0 18px -18px rgba(35,48,80,.55)}.convivencia-operational-section :deep(th:last-child){z-index:3;background:#f7f8fc}.convivencia-operational-section :deep(tbody tr:hover td:last-child){background:#fafbff}.convivencia-operational-section.is-casos :deep(th:last-child),.convivencia-operational-section.is-casos :deep(td:last-child){width:254px;min-width:254px}}
@media(max-width:991.98px){.convivencia-toolbar-filter,.convivencia-toolbar-filter.is-wide{width:100%;grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:575.98px){.convivencia-toolbar-filter,.convivencia-toolbar-filter.is-wide{grid-template-columns:1fr}.table-clamp{max-width:none}}
</style>
