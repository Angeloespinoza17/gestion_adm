<script setup>
import { computed } from "vue";
import {
    aiReportStatistics,
    criterionStatusPresentation,
    groupAiReportCriteria,
    severityPresentation,
} from "../../utils/pedagogical-ai-report";

const props = defineProps({
    report: { type: Object, required: true },
});

const statistics = computed(() => aiReportStatistics(props.report));
const criteriaGroups = computed(() => groupAiReportCriteria(props.report));
</script>

<template>
    <div class="feedback-report" data-pedagogical-ai-report>
        <section class="report-summary">
            <span class="report-kicker">RESUMEN EJECUTIVO</span>
            <p>{{ report.executive_summary }}</p>
        </section>

        <section class="statistics-section" aria-label="Estadística de resumen">
            <div class="statistics-heading">
                <div>
                    <span class="report-kicker">ESTADÍSTICA DE RESUMEN</span>
                    <h4>Panorama de la revisión</h4>
                </div>
                <strong>{{ statistics.criteria_total }} criterios</strong>
            </div>
            <div class="statistics-grid">
                <article class="stat compliance"><strong>{{ statistics.compliance_percentage }}%</strong><span>Nivel de ajuste</span></article>
                <article class="stat success"><strong>{{ statistics.meets }}</strong><span>Se ajustan</span></article>
                <article class="stat warning"><strong>{{ statistics.partially_meets }}</strong><span>Parciales</span></article>
                <article class="stat danger"><strong>{{ statistics.does_not_meet }}</strong><span>No se ajustan</span></article>
                <article class="stat neutral"><strong>{{ statistics.not_evidenced }}</strong><span>No evidenciados</span></article>
                <article class="stat misc"><strong>{{ statistics.miscellaneous_findings }}</strong><span>Misceláneos</span></article>
            </div>
            <div class="coverage-row">
                <div><span>Cobertura de evidencia</span><strong>{{ statistics.evidence_coverage_percentage }}%</strong></div>
                <div class="coverage-track"><span :style="{ width: `${statistics.evidence_coverage_percentage}%` }"></span></div>
            </div>
        </section>

        <section v-if="report.criteria_assessment?.length" class="criteria-review">
            <div class="criteria-heading">
                <div><span>PAUTA INSTITUCIONAL + EPA</span><h4>Evaluación de criterios y consideraciones</h4></div>
                <small>{{ report.criteria_assessment.length }} criterios revisados</small>
            </div>
            <p class="criteria-scope-note"><i class="bx bx-info-circle"></i>“No aplica” identifica criterios ajenos al tipo de instrumento; “No evidenciado” indica que el archivo no permite comprobarlos.</p>
            <div class="criteria-groups">
                <section v-for="group in criteriaGroups" :key="group.dimension" class="criteria-dimension">
                    <header><strong>{{ group.dimension }}</strong><span>{{ group.items.length }} {{ group.items.length === 1 ? "criterio" : "criterios" }}</span></header>
                    <div class="criteria-grid">
                        <article v-for="item in group.items" :key="item.code">
                            <div class="criterion-top"><span class="criterion-code">{{ item.code }}</span><span class="criterion-status" :class="`tone-${criterionStatusPresentation(item.status).tone}`">{{ criterionStatusPresentation(item.status).label }}</span></div>
                            <small v-if="item.applicability" class="criterion-applicability"><i class="bx bx-target-lock"></i>{{ item.applicability }}</small>
                            <strong>{{ item.criterion }}</strong>
                            <p>{{ item.finding }}</p>
                            <small v-if="item.evidence"><b>Evidencia:</b> {{ item.evidence }}<template v-if="item.page"> · pág. {{ item.page }}</template></small>
                            <small v-if="item.recommendation" class="criterion-recommendation"><b>Orientación:</b> {{ item.recommendation }}</small>
                            <div v-if="item.improvement_example" class="improvement-example"><i class="bx bx-bulb"></i><p><b>Ejemplo específico:</b> {{ item.improvement_example }}</p></div>
                        </article>
                    </div>
                </section>
            </div>
        </section>

        <section class="report-columns">
            <div><h4>Fortalezas</h4><ul><li v-for="item in report.strengths" :key="item">{{ item }}</li></ul></div>
            <div><h4>Recomendaciones</h4><ul><li v-for="item in report.recommendations" :key="typeof item === 'string' ? item : item.title">{{ typeof item === "string" ? item : item.recommendation || item.title }}</li></ul></div>
        </section>

        <section v-if="report.observations?.length" class="findings-section">
            <h4>Observaciones detectadas</h4>
            <article v-for="(item, index) in report.observations" :key="`${item.title}-${index}`">
                <span :class="`severity-${item.severity}`">{{ severityPresentation(item.severity) }}</span>
                <div>
                    <strong>{{ item.title }}</strong>
                    <p>{{ item.description }}</p>
                    <small v-if="item.evidence"><b>Evidencia:</b> {{ item.evidence }}<template v-if="item.page"> · pág. {{ item.page }}</template></small>
                    <small><b>Recomendación:</b> {{ item.recommendation }}</small>
                </div>
            </article>
        </section>

        <section class="miscellaneous-section">
            <div class="miscellaneous-heading"><div><span class="report-kicker">CONTROL COMPLEMENTARIO</span><h4>Hallazgos misceláneos</h4></div><strong>{{ statistics.miscellaneous_findings }}</strong></div>
            <p v-if="!report.miscellaneous_findings?.length" class="miscellaneous-empty"><i class="bx bx-check-circle"></i>No se detectaron incoherencias adicionales verificables en puntajes, totales, ponderaciones o estructura interna.</p>
            <article v-for="(item, index) in report.miscellaneous_findings" :key="`${item.title}-${index}`">
                <div class="miscellaneous-title"><span :class="`severity-${item.severity}`">{{ severityPresentation(item.severity) }}</span><strong>{{ item.title }}</strong></div>
                <p>{{ item.finding }}</p>
                <small v-if="item.evidence"><b>Evidencia:</b> {{ item.evidence }}<template v-if="item.page"> · pág. {{ item.page }}</template></small>
                <small><b>Recomendación:</b> {{ item.recommendation }}</small>
                <div class="improvement-example"><i class="bx bx-bulb"></i><p><b>Ejemplo específico:</b> {{ item.improvement_example }}</p></div>
            </article>
        </section>

        <section v-if="report.suggested_teacher_message" class="teacher-message"><strong>Mensaje sugerido para el docente</strong><p>{{ report.suggested_teacher_message }}</p></section>
    </div>
</template>

<style scoped>
.feedback-report{display:grid;gap:1rem;color:#263851}.report-kicker{display:block;color:#18877f;font-size:.65rem;font-weight:850;letter-spacing:.1em}.report-summary{border-radius:14px;padding:1rem;background:#fff}.report-summary p{margin:.45rem 0 0;font-weight:600;line-height:1.55}.statistics-section{border:1px solid #e5e8f3;border-radius:16px;padding:1rem;background:#fff}.statistics-heading,.criteria-heading,.miscellaneous-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem}.statistics-heading h4,.criteria-heading h4,.miscellaneous-heading h4{margin:.2rem 0 0;font-size:.95rem;color:#263851}.statistics-heading>strong{color:#6654b5;font-size:.75rem}.statistics-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.55rem;margin-top:.8rem}.stat{min-width:0;border-radius:12px;padding:.7rem;background:#f3f5f8}.stat strong,.stat span{display:block}.stat strong{font-size:1.25rem}.stat span{margin-top:.12rem;color:#667386;font-size:.65rem;font-weight:750}.stat.compliance{background:#ece9fb;color:#5a48a5}.stat.success{background:#e5f6ee;color:#176a50}.stat.warning{background:#fff4d7;color:#8a5b00}.stat.danger{background:#ffeaec;color:#9b2e36}.stat.neutral{background:#eff1f4;color:#596273}.stat.misc{background:#e6f5f8;color:#176a81}.coverage-row{margin-top:.8rem}.coverage-row>div:first-child{display:flex;justify-content:space-between;color:#667386;font-size:.7rem}.coverage-row strong{color:#18877f}.coverage-track{height:7px;margin-top:.35rem;overflow:hidden;border-radius:99px;background:#edf0f3}.coverage-track span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#18877f,#42a89b)}
.criteria-review{padding:1rem 0;border-top:1px solid #ebe8f7;border-bottom:1px solid #ebe8f7}.criteria-heading span{display:block;color:#18877f;font-size:.65rem;font-weight:800;letter-spacing:.1em}.criteria-heading>small{color:#758193}.criteria-scope-note{display:flex;gap:.35rem;margin:.6rem 0 .8rem;padding:.55rem .65rem;border-radius:10px;background:#f2f5f9;color:#687588;font-size:.7rem}.criteria-scope-note i{color:#6654b5;font-size:.9rem}.criteria-groups{display:grid;gap:.9rem}.criteria-dimension{padding:.8rem;border:1px solid #ebeaf4;border-radius:15px;background:rgba(249,250,255,.72)}.criteria-dimension>header{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.65rem}.criteria-dimension>header strong{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}.criteria-dimension>header span{border-radius:999px;padding:.25rem .5rem;background:#ebe7f7;color:#65569a;font-size:.62rem;font-weight:800}.criteria-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}.criteria-grid article{padding:.8rem;border:1px solid #e7eaf3;border-radius:13px;background:#fff}.criterion-top{display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.45rem}.criterion-code{display:inline-grid;place-items:center;min-width:34px;height:28px;border-radius:8px;background:#e5f5f3;color:#176f6c;font-size:.72rem;font-weight:900}.criterion-status,.findings-section article>span,.miscellaneous-title>span{border-radius:999px;padding:.3rem .52rem;font-size:.63rem;font-weight:800}.tone-success{background:#e4f6ee;color:#176a50}.tone-warning,.severity-important{background:#fff3d4;color:#8a5b00}.tone-danger,.severity-critical{background:#ffeaec;color:#9b2e36}.tone-neutral{background:#eff1f4;color:#596273}.severity-suggestion{background:#e5f4f9;color:#176a81}.criterion-applicability{display:flex;align-items:center;gap:.25rem;margin-bottom:.45rem;color:#6d5ca5;font-weight:700}.criteria-grid article>strong{display:block;font-size:.8rem;line-height:1.35}.criteria-grid article>p{margin:.45rem 0;color:#5d697a;font-size:.78rem;line-height:1.4}.criteria-grid article>small{display:block;color:#778292;font-size:.7rem;line-height:1.4}.criterion-recommendation{margin-top:.35rem;color:#5c4e91!important}.improvement-example{display:flex;gap:.45rem;margin-top:.6rem;padding:.65rem;border-radius:10px;background:#fff8e8;color:#73520a}.improvement-example i{flex:0 0 auto;font-size:1rem}.improvement-example p{margin:0!important;color:inherit!important;font-size:.72rem!important;line-height:1.45!important}
.report-columns{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.report-columns>div{border:1px solid #ece9f8;border-radius:14px;padding:1rem;background:#fff}.report-columns h4,.findings-section>h4{font-size:.8rem;text-transform:uppercase;letter-spacing:.07em;color:#6654b5}.report-columns ul{padding-left:1.1rem;margin-bottom:0}.report-columns li{margin-bottom:.4rem;font-size:.82rem}.findings-section{display:grid;gap:.55rem}.findings-section>h4{margin-bottom:0}.findings-section article{display:flex;align-items:flex-start;gap:.65rem;border:1px solid #ece9f8;border-radius:12px;padding:.75rem;background:#fff}.findings-section article strong,.findings-section article p,.findings-section article small{display:block}.findings-section article p{margin:.2rem 0;color:#536174}.miscellaneous-section{display:grid;gap:.65rem;border:1px solid #dce9ef;border-radius:16px;padding:1rem;background:#f7fbfc}.miscellaneous-heading>strong{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#e6f5f8;color:#176a81}.miscellaneous-empty{display:flex;align-items:center;gap:.45rem;margin:0;padding:.75rem;border-radius:11px;background:#eaf7f1;color:#276e56;font-size:.78rem}.miscellaneous-empty i{font-size:1rem}.miscellaneous-section>article{border:1px solid #dce8ee;border-radius:12px;padding:.8rem;background:#fff}.miscellaneous-title{display:flex;align-items:center;gap:.55rem}.miscellaneous-section>article>p{margin:.45rem 0;color:#536174}.miscellaneous-section>article>small{display:block;margin-top:.3rem;color:#6d7887}.teacher-message{border-radius:12px;padding:.85rem;background:#f2effc}.teacher-message p{margin:.35rem 0 0;white-space:pre-line}
.report-summary p,.criteria-grid article>p,.criteria-grid article>small:not(.criterion-applicability),.improvement-example p,.report-columns li,.findings-section article p,.findings-section article small,.miscellaneous-section>article>p,.miscellaneous-section>article>small,.teacher-message p{text-align:justify;text-justify:inter-word;hyphens:auto}
.feedback-report{--report-navy:#143b58;--report-teal:#137f7a;--report-violet:#6556b8;--report-line:#dce6eb;gap:1.25rem}.report-summary,.statistics-section,.criteria-dimension,.report-columns>div,.findings-section article,.miscellaneous-section,.teacher-message{box-shadow:0 10px 28px rgba(20,59,88,.06)}.report-summary{position:relative;overflow:hidden;padding:1.2rem 1.3rem 1.2rem 1.55rem;border:1px solid #dbe8eb;background:linear-gradient(135deg,#fff 0%,#f4faf9 100%)}.report-summary:before{position:absolute;inset:0 auto 0 0;width:5px;background:linear-gradient(180deg,var(--report-teal),#4ea89f);content:""}.report-summary p{color:#263e53;font-weight:550}.statistics-section{padding:1.2rem;border-color:#dbe5eb;background:linear-gradient(145deg,#fff 0%,#f7f9fc 100%)}.statistics-grid{gap:.65rem;margin-top:1rem}.stat{padding:.85rem;border:1px solid rgba(20,59,88,.06)}.stat strong{font-size:1.35rem}.coverage-track{height:8px}.criteria-review{padding:1.2rem 0}.criteria-groups{gap:1rem}.criteria-dimension{padding:1rem;border-color:#e1e5f1;background:linear-gradient(145deg,#fbfbfe 0%,#f6f5fb 100%)}.criteria-dimension>header{margin-bottom:.8rem;padding-bottom:.7rem;border-bottom:1px solid #e4e1f1}.criteria-grid{gap:.8rem}.criteria-grid article{padding:1rem;border-color:#e0e7ec;box-shadow:0 7px 18px rgba(20,59,88,.045)}.criterion-code{background:var(--report-teal);color:#fff}.improvement-example{margin-top:.75rem;padding:.75rem;border:1px solid #f0ddb3}.report-columns{gap:1.1rem}.report-columns>div{padding:1.1rem;border-color:#e4e2ef}.findings-section{gap:.7rem}.findings-section article{padding:.9rem;border-color:#e1e7ed;border-left:4px solid var(--report-teal)}.miscellaneous-section{padding:1.15rem;border-color:#d8e5e9;background:linear-gradient(145deg,#f8fcfc 0%,#f1f8f8 100%)}.miscellaneous-section>article{padding:.95rem;border-left:4px solid var(--report-navy)}.teacher-message{padding:1rem 1.1rem;border:1px solid #dfdbf1;border-left:5px solid var(--report-violet);background:linear-gradient(135deg,#f5f2fc 0%,#efecf9 100%)}
@media(max-width:900px){.statistics-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:680px){.statistics-heading,.criteria-heading,.miscellaneous-heading{align-items:flex-start;flex-direction:column}.statistics-grid{grid-template-columns:repeat(2,1fr)}.criteria-grid,.report-columns{grid-template-columns:1fr}.findings-section article{flex-direction:column}.report-summary p,.criteria-grid article>p,.criteria-grid article>small:not(.criterion-applicability),.improvement-example p,.report-columns li,.findings-section article p,.findings-section article small,.miscellaneous-section>article>p,.miscellaneous-section>article>small,.teacher-message p{text-align:left;hyphens:none}}
</style>
