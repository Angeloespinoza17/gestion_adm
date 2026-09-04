import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";
import { classPresentationStatus, isPresentationPending, statusPresentation } from "../../resources/js/services/class-presentations-api";

const view = readFileSync("resources/js/views/pedagogical-management/class-presentations.vue", "utf8");
const api = readFileSync("resources/js/services/class-presentations-api.js", "utf8");
const optionGroup = readFileSync("resources/js/components/pedagogical-management/ClassPresentationOptionGroup.vue", "utf8");
const canvaConnection = readFileSync("resources/js/components/pedagogical-management/CanvaConnectionCard.vue", "utf8");
const canvaTemplates = readFileSync("resources/js/components/pedagogical-management/CanvaTemplatePicker.vue", "utf8");
const pipeline = readFileSync("resources/js/components/pedagogical-management/ClassPresentationPipeline.vue", "utf8");
const deliverables = readFileSync("resources/js/components/pedagogical-management/ClassPresentationDeliverables.vue", "utf8");
const router = readFileSync("resources/js/router/index.js", "utf8");
const sideNav = readFileSync("resources/js/components/side-nav.vue", "utf8");

describe("Gestión pedagógica · Generador de clases", () => {
    it("ofrece el flujo guiado de cuatro pasos sin prompt libre", () => {
        expect(view).toContain("['Currículum', 'Clase', 'Diseño Canva', 'Confirmación']");
        expect(view).toContain("Objetivos de aprendizaje");
        expect(view).toContain("Número exacto de diapositivas");
        expect(view).toContain("Título sugerido");
        expect(view).toContain("currentUnit.value?.public_id || form.unit_id");
        expect(view).toContain("Crear clase con ChatGPT + Canva");
        expect(view).not.toContain("<textarea");
        expect(view).not.toContain('v-model="form.prompt"');
    });

    it("permite combinaciones controladas y explica cada alternativa", () => {
        expect(view).toContain('methodology: ["automatic"]');
        expect(view).toContain('activity: ["group"]');
        expect(view).toContain('assessment: ["exit_ticket"]');
        expect(view).toContain('visual_resources: ["editable"]');
        expect(view).toContain("multiple :max=\"selectionRule('methodology')?.max\"");
        expect(view).toContain("multiple :max=\"selectionRule('visual_resources')?.max\"");
        expect(view).toContain('payload.append(`${key}[]`, item)');
        expect(optionGroup).toContain('type: [String, Number, Array]');
        expect(optionGroup).toContain("Selección múltiple");
        expect(optionGroup).toContain("descriptions[entry[0]]");
    });

    it("presenta el estilo elegido como un contrato visual obligatorio", () => {
        expect(view).toContain("Contrato visual activo");
        expect(view).toContain("Obligatorio");
        expect(view).toContain("selectedStyleProfile.description");
        expect(view).toContain('variant="style"');
        expect(view).toContain("selectedLabels('methodology')");
        expect(view).toContain("selectedLabels('visual_resources')");
    });

    it("mantiene historial, progreso, versiones, previsualización y descargas", () => {
        expect(view).toContain("Mis presentaciones");
        expect(view).toContain("Regenerar como nueva versión");
        expect(view).toContain("La versión actual y sus archivos se conservarán");
        expect(deliverables).toContain("PowerPoint editable");
        expect(deliverables).toContain("Documento PDF");
        expect(deliverables).toContain("Guía docente PDF");
        expect(view).toContain("Previsualización");
        expect(view).toContain("schedulePoll");
        expect(api).toContain("/presentaciones/${id}/estado");
        expect(api).toContain("responseType: \"blob\"");
    });

    it("presenta estados asíncronos en lenguaje claro", () => {
        expect(Object.keys(classPresentationStatus)).toEqual([
            "draft", "queued", "preparing_content", "generating_presentation", "generating_teacher_guide", "generating_guide",
            "sending_to_canva", "creating_canva_design", "exporting_canva", "exporting_presentation", "validating", "ready", "failed", "archived",
        ]);
        expect(statusPresentation("generating_presentation")).toEqual({ label: "Generando presentación", tone: "primary" });
        expect(statusPresentation("failed")).toEqual({ label: "Fallida", tone: "danger" });
        expect(isPresentationPending({ status: "creating_canva_design" })).toBe(true);
        expect(isPresentationPending({ status: "ready", presentation_provider: "canva", canva: { status: "in_progress" } })).toBe(true);
        expect(isPresentationPending({ status: "ready", presentation_provider: "canva", canva: { status: "success" } })).toBe(false);
        expect(isPresentationPending({ status: "draft" })).toBe(false);
    });

    it("protege y expone la ruta solicitada desde Gestión pedagógica", () => {
        expect(router).toContain('path: "/gestion-pedagogica/generador-clases"');
        expect(router).toContain('permission: "class-presentations.view"');
        expect(sideNav).toContain('class_presentation_generator: "/gestion-pedagogica/generador-clases"');
        expect(sideNav).toContain('"/gestion-pedagogica/generador-clases": "bx-slideshow"');
    });

    it("integra Canva mediante OAuth backend sin exponer credenciales al navegador", () => {
        expect(view).toContain("CanvaConnectionCard");
        expect(view).toContain("beginCanvaAuthorization");
        expect(view).toContain('window.open("about:blank", "_blank")');
        expect(view).toContain("authorizationTab.location.replace(parsed.toString())");
        expect(api).toContain("/canva/conexion");
        expect(api).toContain("/canva/autorizacion");
        expect(canvaConnection).toContain("El sistema nunca muestra ni envía el secreto al navegador");
        expect(canvaConnection).not.toContain("client_secret");
    });

    it("exige y valida una Brand Template Autofill antes de encolar", () => {
        expect(view).toContain('presentation_provider: "canva"');
        expect(view).toContain('canva_template_id: ""');
        expect(view).toContain('canva_template_title: ""');
        expect(view).toContain("validateCanvaTemplate");
        expect(view).toContain("canvaGenerationReady");
        expect(api).toContain("/canva/plantillas");
        expect(canvaTemplates).toContain("Autofill obligatorio");
        expect(canvaTemplates).toContain(":disabled=\"disabled || loading || !compatible(item)\"");
        expect(view).toContain("selectedCanvaTemplate.value?.compatible === true");
    });

    it("separa responsabilidades, progreso y entregables de ChatGPT y Canva", () => {
        expect(view).toContain('generate_teacher_guide: true');
        expect(view).toContain("ChatGPT generará un guion separado");
        expect(view).toContain("ClassPresentationPipeline");
        expect(view).toContain("ClassPresentationDeliverables");
        expect(pipeline).toContain("Contenido pedagógico");
        expect(pipeline).toContain("Guía docente PDF");
        expect(pipeline).toContain("Diseño en Canva");
        expect(deliverables).toContain("Editar en Canva");
        expect(deliverables).toContain("Presentación heredada");
        expect(api).toContain("/canva/enlace-edicion");
        expect(api).toContain("/canva/sincronizar");
    });
});
