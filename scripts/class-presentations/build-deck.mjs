import fs from "node:fs/promises";
import path from "node:path";
import { createRequire } from "node:module";

const require = createRequire(import.meta.url);
const pptxgen = require("pptxgenjs");

const [, , inputPath, outputPath] = process.argv;
if (!inputPath || !outputPath) {
    throw new Error("Uso: node build-deck.mjs <entrada.json> <salida.pptx>");
}

const payload = JSON.parse(await fs.readFile(inputPath, "utf8"));
const deck = payload.deck;
const configuration = payload.configuration || {};
const snapshot = payload.curricular_snapshot || {};
const pptx = new pptxgen();
const wide = (configuration.aspect_ratio || "wide") === "wide";
const W = wide ? 13.333 : 10;
const H = 7.5;
pptx.layout = wide ? "LAYOUT_WIDE" : "LAYOUT_4X3";
pptx.author = snapshot.author?.name || "Generador de clases";
pptx.company = snapshot.school?.name || "Institución educativa";
pptx.subject = `${snapshot.subject?.name || "Clase"} · ${snapshot.course?.name || "Curso"}`;
pptx.title = deck.metadata?.title || payload.title || "Clase";
pptx.lang = "es-CL";
const fallbackPalettes = {
    institutional: { primary: "173F67", secondary: "1A8D86", accent: "F2A65A", ink: "17243A", muted: "5F6F82", soft: "EAF4F3", bg: "F7FAFC" },
    blue: { primary: "2458A6", secondary: "2F80ED", accent: "56CCF2", ink: "17243A", muted: "5F6F82", soft: "EAF2FC", bg: "F8FAFD" },
    green: { primary: "276749", secondary: "319795", accent: "F6AD55", ink: "18392B", muted: "5F6F82", soft: "E7F5EF", bg: "F8FCFA" },
    violet: { primary: "5B3F8C", secondary: "805AD5", accent: "ED64A6", ink: "2D2440", muted: "6B607A", soft: "F1ECFA", bg: "FBF9FD" },
    warm: { primary: "9C4221", secondary: "DD6B20", accent: "D69E2E", ink: "3C2A22", muted: "745D50", soft: "FFF3E8", bg: "FFFCF8" },
    neutral: { primary: "334155", secondary: "64748B", accent: "0F766E", ink: "1E293B", muted: "64748B", soft: "EEF2F6", bg: "FAFBFC" },
};
const visualStyle = configuration.visual_style || "institutional";
const styleContract = configuration.style_contract || {};
const paletteKey = configuration.palette === "automatic" ? "institutional" : (configuration.palette || "institutional");
const fallback = fallbackPalettes[paletteKey] || fallbackPalettes.institutional;
const tokens = styleContract.colors || {};
const C = {
    primary: tokens.primary || fallback.primary,
    secondary: tokens.secondary || fallback.secondary,
    accent: tokens.accent || fallback.accent,
    accentAlt: tokens.accent_alt || tokens.accentAlt || fallback.secondary,
    ink: tokens.ink || fallback.ink,
    muted: tokens.muted || fallback.muted,
    soft: tokens.soft || fallback.soft,
    bg: tokens.background || tokens.bg || fallback.bg,
};
const isChildren = visualStyle === "children";
const isMinimal = visualStyle === "minimalist";
const isAcademic = visualStyle === "academic";
const isDynamic = ["contemporary", "youth"].includes(visualStyle);
const isInfographic = visualStyle === "infographic";
const FONT_HEAD = styleContract.heading_font || (isChildren ? "Trebuchet MS" : isAcademic ? "Georgia" : "Aptos Display");
const FONT_BODY = styleContract.body_font || (isChildren ? "Trebuchet MS" : "Aptos");
const shape = pptx.ShapeType;
pptx.subject = `${pptx.subject} · Estilo ${styleContract.style_label || visualStyle}`;
pptx.theme = { headFontFace: FONT_HEAD, bodyFontFace: FONT_BODY, lang: "es-CL" };
const masterObjects = [
    { line: { x: 0.66, y: H - 0.38, w: W - 1.32, h: 0, line: { color: isChildren ? C.accent : "D9E2EC", transparency: isChildren ? 28 : 0, width: isChildren ? 1.5 : 0.8 } } },
    { text: { text: snapshot.school?.name || "Institución educativa", options: { x: 0.68, y: H - 0.29, w: W - 2.2, h: 0.16, fontFace: FONT_BODY, fontSize: 8, color: C.muted, margin: 0, breakLine: false } } },
];
if (isChildren) {
    masterObjects.unshift(
        { shape: { type: shape.ellipse, x: W - 0.63, y: 0.24, w: 0.18, h: 0.18, fill: { color: C.accentAlt }, line: { color: C.accentAlt, transparency: 100 }, altText: "Punto decorativo" } },
        { shape: { type: shape.ellipse, x: W - 0.94, y: 0.18, w: 0.12, h: 0.12, fill: { color: C.accent }, line: { color: C.accent, transparency: 100 }, altText: "Punto decorativo" } },
    );
}
pptx.defineSlideMaster({
    title: "CLASS_BASE",
    background: { color: C.bg },
    objects: masterObjects,
    slideNumber: { x: W - 1.08, y: H - 0.31, w: 0.42, h: 0.16, color: C.muted, fontFace: FONT_BODY, fontSize: 8, align: "right", margin: 0 },
});

function addTitle(slide, title, eyebrow = "") {
    const left = isChildren ? 0.82 : 0.68;
    if (isChildren) {
        slide.addShape(shape.ellipse, { x: 0.45, y: 0.4, w: 0.18, h: 0.18, fill: { color: C.accentAlt }, line: { color: C.accentAlt, transparency: 100 }, altText: "Acento decorativo" });
        slide.addShape(shape.arc, { x: W - 1.48, y: 0.42, w: 0.66, h: 0.34, rotate: 8, adjustPoint: 0.3, fill: { color: C.accent, transparency: 12 }, line: { color: C.accent, transparency: 100 }, altText: "Trazo decorativo" });
    }
    if (eyebrow) slide.addText(eyebrow.toLocaleUpperCase("es"), { x: left, y: 0.4, w: W - left - 0.75, h: 0.26, fontFace: FONT_BODY, fontSize: isChildren ? 10.5 : 10, bold: true, color: C.secondary, charSpacing: isChildren ? 0.8 : 1.4, margin: 0.01, breakLine: false, fit: "shrink" });
    slide.addText(title, { x: left, y: eyebrow ? 0.74 : 0.5, w: W - left - 0.7, h: isChildren ? 0.7 : 0.62, fontFace: FONT_HEAD, fontSize: isChildren ? (wide ? 29 : 26) : (wide ? 27 : 24), bold: true, color: C.ink, margin: 0.01, breakLine: false, fit: "shrink" });
    if (isChildren) slide.addShape(shape.roundRect, { x: left, y: eyebrow ? 1.5 : 1.28, w: 1.05, h: 0.09, rectRadius: 0.04, fill: { color: C.accent }, line: { color: C.accent, transparency: 100 }, altText: "Subrayado decorativo" });
    else slide.addShape(shape.line, { x: left, y: eyebrow ? 1.48 : 1.24, w: 0.82, h: 0, line: { color: C.accent, width: 3 } });
}

function addBulletList(slide, bullets, box) {
    const items = (bullets || []).slice(0, 6);
    if (!items.length) return;
    const text = items.map((item) => `•  ${item}`).join("\n");
    slide.addText(text, { ...box, fontFace: FONT_BODY, fontSize: isChildren ? (wide ? 19 : 17) : (wide ? 18 : 16), color: C.ink, breakLine: false, valign: "mid", margin: isChildren ? 0.12 : 0.08, paraSpaceAfterPt: isChildren ? 12 : 10, breakLineOnTextOverflow: false, fit: "shrink" });
}

function addConcepts(slide, concepts, y = 5.82) {
    const list = (concepts || []).slice(0, 5);
    if (!list.length) return;
    const gap = 0.12;
    const itemW = Math.min(2.25, (W - 1.3 - gap * (list.length - 1)) / list.length);
    list.forEach((concept, index) => {
        const x = 0.65 + index * (itemW + gap);
        const colors = [C.secondary, C.accentAlt, C.primary, C.accent, C.secondary];
        slide.addShape(isChildren ? shape.ellipse : shape.roundRect, { x, y, w: itemW, h: isChildren ? 0.56 : 0.5, rectRadius: 0.06, fill: { color: isChildren ? colors[index] : C.soft, transparency: isChildren ? 4 : 0 }, line: { color: isChildren ? colors[index] : C.secondary, transparency: isChildren ? 100 : 50, width: 0.8 }, altText: `Concepto destacado: ${concept}` });
        slide.addText(concept, { x: x + 0.08, y: y + (isChildren ? 0.13 : 0.09), w: itemW - 0.16, h: 0.27, fontFace: FONT_BODY, fontSize: isChildren ? 11.5 : 11, bold: true, color: isChildren ? "FFFFFF" : C.primary, align: "center", margin: 0, breakLine: false, fit: "shrink" });
    });
}

function addVisualPanel(slide, visual, box, concepts = []) {
    const description = visual?.description || "Representación visual editable";
    slide.addShape(shape.roundRect, { ...box, rectRadius: isChildren ? 0.16 : 0.05, fill: { color: C.soft }, line: { color: C.secondary, transparency: isChildren ? 72 : 55, width: isChildren ? 1.5 : 1 }, altText: visual?.alt_text || description });
    const nodes = (concepts || []).filter(Boolean).slice(0, 4);
    while (nodes.length < 4) nodes.push(["observar", "comprender", "dialogar", "acordar"][nodes.length]);
    if (isChildren) {
        const nodeColors = [C.secondary, C.accentAlt, C.primary, C.accent];
        const nodeW = Math.min(1.42, box.w * 0.28);
        const pathY = [box.y + 0.78, box.y + 1.72, box.y + 0.92, box.y + 2.3];
        nodes.forEach((node, index) => {
            const x = box.x + 0.28 + index * ((box.w - 0.56 - nodeW) / 3);
            if (index < nodes.length - 1) {
                const nextX = box.x + 0.28 + (index + 1) * ((box.w - 0.56 - nodeW) / 3);
                slide.addShape(shape.line, { x: x + nodeW, y: pathY[index] + 0.35, w: Math.max(0.08, nextX - x - nodeW), h: pathY[index + 1] - pathY[index], line: { color: C.muted, transparency: 52, width: 2, dash: "dash", endArrowType: "triangle" }, altText: "Ruta de aprendizaje" });
            }
            slide.addShape(index % 2 ? shape.ellipse : shape.roundRect, { x, y: pathY[index], w: nodeW, h: 0.7, rectRadius: 0.14, fill: { color: nodeColors[index] }, line: { color: nodeColors[index], transparency: 100 }, rotate: index % 2 ? 0 : (index % 3 - 1) * 2, altText: `Estación visual: ${node}` });
            slide.addText(node, { x: x + 0.1, y: pathY[index] + 0.2, w: nodeW - 0.2, h: 0.25, fontFace: FONT_BODY, fontSize: 10.5, bold: true, color: "FFFFFF", align: "center", margin: 0, fit: "shrink", breakLine: false });
        });
        slide.addText(description, { x: box.x + 0.32, y: box.y + box.h - 0.56, w: box.w - 0.64, h: 0.3, fontFace: FONT_BODY, fontSize: 10.5, color: C.muted, align: "center", valign: "mid", margin: 0.01, fit: "shrink", breakLine: false });
        return;
    }
    const chipW = Math.min(1.48, box.w * 0.35);
    const chipH = 0.5;
    const centerX = box.x + box.w / 2;
    const centerY = box.y + 1.65;
    const positions = [
        { x: box.x + 0.22, y: box.y + 0.55 },
        { x: box.x + box.w - chipW - 0.22, y: box.y + 0.55 },
        { x: box.x + 0.22, y: box.y + 2.45 },
        { x: box.x + box.w - chipW - 0.22, y: box.y + 2.45 },
    ];
    positions.forEach((position) => {
        slide.addShape(shape.line, { x: Math.min(position.x + chipW / 2, centerX), y: Math.min(position.y + chipH / 2, centerY), w: Math.abs(centerX - position.x - chipW / 2), h: Math.abs(centerY - position.y - chipH / 2), line: { color: C.secondary, transparency: 35, width: 1.3, beginArrowType: "none", endArrowType: "triangle" }, altText: "Conector del mapa conceptual" });
    });
    slide.addShape(shape.ellipse, { x: centerX - 0.58, y: centerY - 0.58, w: 1.16, h: 1.16, fill: { color: C.secondary }, line: { color: C.secondary }, altText: visual?.alt_text || description });
    slide.addText("IDEA\nCENTRAL", { x: centerX - 0.42, y: centerY - 0.28, w: 0.84, h: 0.56, fontFace: FONT_HEAD, fontSize: 11, bold: true, align: "center", valign: "mid", color: "FFFFFF", margin: 0, breakLine: false });
    positions.forEach((position, index) => {
        slide.addShape(shape.roundRect, { x: position.x, y: position.y, w: chipW, h: chipH, rectRadius: 0.04, fill: { color: index % 2 ? "FFFFFF" : C.bg }, line: { color: C.secondary, transparency: 25, width: 0.9 }, altText: `Nodo conceptual: ${nodes[index]}` });
        slide.addText(nodes[index], { x: position.x + 0.08, y: position.y + 0.12, w: chipW - 0.16, h: 0.22, fontFace: FONT_BODY, fontSize: 10, bold: true, color: C.primary, align: "center", margin: 0, fit: "shrink", breakLine: false });
    });
    slide.addText(description, { x: box.x + 0.28, y: box.y + box.h - 0.52, w: box.w - 0.56, h: 0.28, fontFace: FONT_BODY, fontSize: 10, color: C.muted, align: "center", valign: "mid", margin: 0.01, fit: "shrink", breakLine: false });
}

function addNotes(slide, item) {
    if (!configuration.speaker_notes && !(item.sources || []).length) return;
    const notes = [];
    if (configuration.speaker_notes && item.speaker_notes) notes.push(item.speaker_notes);
    if (item.audience_question) notes.push(`Pregunta para el curso: ${item.audience_question}`);
    if ((item.sources || []).length) {
        notes.push("[Sources]\n" + item.sources.map((source) => `- ${source.title}${source.publisher ? ` · ${source.publisher}` : ""}${source.url ? ` · ${source.url}` : ""}`).join("\n"));
    }
    if (notes.length) slide.addNotes(notes.join("\n\n"));
}

function renderCover(item) {
    const slide = pptx.addSlide();
    slide.background = { color: (isChildren || isMinimal) ? C.bg : C.primary };
    if (isChildren) {
        slide.addShape(shape.ellipse, { x: W * 0.68, y: 0, w: W * 0.32, h: H * 0.72, fill: { color: C.secondary }, line: { color: C.secondary, transparency: 100 }, altText: "Forma amable de portada" });
        slide.addShape(shape.ellipse, { x: W * 0.77, y: H * 0.6, w: 2.25, h: 2.25, fill: { color: C.accent }, line: { color: C.accent, transparency: 100 }, altText: "Círculo cálido decorativo" });
        slide.addShape(shape.roundRect, { x: W * 0.69, y: 2.1, w: 2.2, h: 1.2, rectRadius: 0.2, rotate: -5, fill: { color: C.accentAlt }, line: { color: C.accentAlt, transparency: 100 }, altText: "Tarjeta lúdica decorativa" });
    } else if (isMinimal) {
        slide.addShape(shape.line, { x: 0.84, y: 1.16, w: W - 1.68, h: 0, line: { color: C.accent, width: 2 }, altText: "Línea de acento minimalista" });
        slide.addShape(shape.ellipse, { x: W - 2.2, y: H - 1.75, w: 1.05, h: 1.05, fill: { color: C.secondary, transparency: 78 }, line: { color: C.secondary, transparency: 100 }, altText: "Acento minimalista" });
    } else if (isDynamic) {
        slide.addShape(shape.chevron, { x: W * 0.69, y: 0, w: W * 0.31, h: H, fill: { color: C.secondary }, line: { color: C.secondary, transparency: 100 }, altText: "Plano dinámico de portada" });
        slide.addShape(shape.arc, { x: W * 0.76, y: 4.7, w: 2.25, h: 2.25, rotate: -12, adjustPoint: 0.3, fill: { color: C.accentAlt, transparency: 6 }, line: { color: C.accentAlt, transparency: 100 }, altText: "Acento expresivo" });
    } else {
        slide.addShape(shape.rect, { x: W * 0.68, y: 0, w: W * 0.32, h: H, fill: { color: C.secondary }, line: { color: C.secondary }, altText: "Panel decorativo" });
        slide.addShape(shape.arc, { x: W * 0.72, y: 0.05, w: 3.0, h: 3.0, rotate: 12, adjustPoint: 0.22, fill: { color: C.accent, transparency: 10 }, line: { color: C.accent, transparency: 100 }, altText: "Elemento decorativo circular" });
        if (isInfographic) {
            [0, 1, 2].forEach((index) => slide.addShape(shape.ellipse, { x: W * 0.73 + index * 0.72, y: 4.5 + (index % 2) * 0.52, w: 0.46, h: 0.46, fill: { color: [C.accent, C.accentAlt, "FFFFFF"][index], transparency: index === 2 ? 12 : 0 }, line: { color: "FFFFFF", transparency: 72 }, altText: "Nodo infográfico" }));
        }
    }
    const lightCover = isChildren || isMinimal;
    const coverInk = lightCover ? C.ink : "FFFFFF";
    const coverMuted = lightCover ? C.muted : "DCE8F2";
    slide.addText(deck.metadata?.subject || snapshot.subject?.name || "Clase", { x: 0.82, y: 0.72, w: W * 0.56, h: 0.3, fontFace: FONT_BODY, fontSize: isChildren ? 13 : 12, bold: true, color: lightCover ? C.secondary : "BFE3DF", charSpacing: isChildren ? 0.6 : 1.2, margin: 0.01, breakLine: false });
    slide.addText(deck.metadata?.title || item.title, { x: 0.82, y: 1.38, w: W * 0.57, h: 2.18, fontFace: FONT_HEAD, fontSize: isChildren ? (wide ? 34 : 29) : (wide ? 31 : 27), bold: true, color: coverInk, margin: 0.01, breakLine: false, valign: "mid", fit: "shrink" });
    slide.addText(deck.metadata?.subtitle || item.visible_text || "", { x: 0.84, y: 3.86, w: W * 0.53, h: 0.82, fontFace: FONT_BODY, fontSize: isChildren ? 18.5 : 17, color: coverMuted, margin: 0.01, fit: "shrink" });
    slide.addText(`${deck.metadata?.course || snapshot.course?.name || ""}\n${deck.metadata?.unit || snapshot.unit?.title || ""}`, { x: 0.84, y: 5.55, w: W * 0.54, h: 0.82, fontFace: FONT_BODY, fontSize: isChildren ? 14 : 13, bold: true, color: coverInk, margin: 0.01, breakLine: false, fit: "shrink" });
    slide.addText(snapshot.author?.name || "", { x: 0.84, y: 6.65, w: W * 0.54, h: 0.24, fontFace: FONT_BODY, fontSize: 10, color: lightCover ? C.muted : "C9D7E5", margin: 0.01, breakLine: false });
    addNotes(slide, item);
}

function renderObjectives(item) {
    const slide = pptx.addSlide("CLASS_BASE");
    addTitle(slide, item.title, item.pedagogical_function);
    const objectives = snapshot.objectives || [];
    const count = Math.min(3, objectives.length || 1);
    const cardW = (W - 1.3 - 0.22 * (count - 1)) / count;
    (objectives.length ? objectives : [{ code: "OA", description: item.main_idea }]).slice(0, 3).forEach((objective, index) => {
        const x = 0.65 + index * (cardW + 0.22);
        const singleChild = isChildren && count === 1;
        slide.addShape(shape.roundRect, { x, y: singleChild ? 1.9 : 1.82, w: cardW, h: singleChild ? 2.9 : 3.55, rectRadius: isChildren ? 0.18 : 0.04, fill: { color: index === 0 ? C.soft : "FFFFFF" }, line: { color: index === 0 ? C.secondary : "D9E2EC", transparency: isChildren ? 24 : 0, width: isChildren ? 1.5 : 1.1 }, altText: `Objetivo ${objective.code}` });
        if (singleChild) {
            slide.addShape(shape.ellipse, { x: x + cardW - 1.15, y: 2.2, w: 0.48, h: 0.48, fill: { color: C.accent }, line: { color: C.accent, transparency: 100 }, altText: "Acento visual del objetivo" });
            slide.addShape(shape.ellipse, { x: x + cardW - 0.62, y: 2.56, w: 0.24, h: 0.24, fill: { color: C.accentAlt }, line: { color: C.accentAlt, transparency: 100 }, altText: "Acento visual del objetivo" });
        }
        slide.addText(objective.code, { x: x + 0.28, y: 2.12, w: cardW - 0.56, h: 0.34, fontFace: FONT_HEAD, fontSize: isChildren ? 16 : 15, bold: true, color: C.secondary, margin: 0.01, breakLine: false });
        slide.addText(objective.description, { x: x + 0.28, y: 2.72, w: cardW - (singleChild ? 1.5 : 0.56), h: singleChild ? 1.25 : 2.18, fontFace: FONT_BODY, fontSize: singleChild ? 21 : (isChildren ? 17 : 16), color: C.ink, margin: 0.02, valign: "mid", fit: "shrink" });
    });
    addConcepts(slide, item.highlighted_concepts, 5.82);
    addNotes(slide, item);
}

function renderComparison(item) {
    const slide = pptx.addSlide("CLASS_BASE");
    addTitle(slide, item.title, item.pedagogical_function);
    const bullets = item.bullets || [];
    const split = Math.max(1, Math.floor(bullets.length / 2));
    [bullets.slice(0, split), bullets.slice(split)].forEach((list, index) => {
        const x = 0.65 + index * ((W - 1.52) / 2 + 0.22);
        const cardW = (W - 1.52) / 2;
        slide.addShape(shape.roundRect, { x, y: 1.8, w: cardW, h: 4.25, rectRadius: isChildren ? 0.18 : 0.04, fill: { color: index === 0 ? C.soft : "FFFFFF" }, line: { color: index === 0 ? C.secondary : C.accent, transparency: isChildren ? 20 : 0, width: isChildren ? 1.5 : 1.1 }, altText: `Columna comparativa ${index + 1}` });
        slide.addText(index === 0 ? "PERSPECTIVA A" : "PERSPECTIVA B", { x: x + 0.3, y: 2.1, w: cardW - 0.6, h: 0.3, fontFace: FONT_BODY, fontSize: isChildren ? 12 : 11, bold: true, color: index === 0 ? C.secondary : C.primary, charSpacing: isChildren ? 0.4 : 1, margin: 0.01, breakLine: false });
        addBulletList(slide, list, { x: x + 0.28, y: 2.62, w: cardW - 0.56, h: 2.95 });
    });
    addNotes(slide, item);
}

function renderProcess(item) {
    const slide = pptx.addSlide("CLASS_BASE");
    addTitle(slide, item.title, item.pedagogical_function);
    const steps = (item.bullets?.length ? item.bullets : item.highlighted_concepts || []).slice(0, 5);
    const count = Math.max(1, steps.length);
    const stepW = (W - 1.3 - 0.3 * (count - 1)) / count;
    steps.forEach((step, index) => {
        const x = 0.65 + index * (stepW + 0.3);
        if (index < count - 1) slide.addShape(shape.chevron, { x: x + stepW - 0.03, y: 3.25, w: 0.38, h: 0.65, fill: { color: C.accent }, line: { color: C.accent }, altText: "Conector de secuencia" });
        slide.addShape(shape.roundRect, { x, y: 2.25 + (index % 2) * 0.25, w: stepW, h: 2.25, rectRadius: isChildren ? 0.17 : 0.04, fill: { color: index % 2 ? "FFFFFF" : C.soft }, line: { color: C.secondary, transparency: 30, width: isChildren ? 1.5 : 1 }, altText: `Paso ${index + 1}: ${step}` });
        slide.addShape(shape.ellipse, { x: x + stepW / 2 - 0.28, y: 2.0 + (index % 2) * 0.25, w: 0.56, h: 0.56, fill: { color: C.primary }, line: { color: C.primary }, altText: `Número ${index + 1}` });
        slide.addText(String(index + 1), { x: x + stepW / 2 - 0.18, y: 2.15 + (index % 2) * 0.25, w: 0.36, h: 0.22, fontFace: FONT_BODY, fontSize: 13, bold: true, color: "FFFFFF", align: "center", margin: 0, breakLine: false });
        slide.addText(step, { x: x + 0.2, y: 2.87 + (index % 2) * 0.25, w: stepW - 0.4, h: 1.2, fontFace: FONT_BODY, fontSize: isChildren ? 16 : 15, color: C.ink, align: "center", valign: "mid", margin: 0.01, fit: "shrink" });
    });
    addConcepts(slide, item.highlighted_concepts, 5.6);
    addNotes(slide, item);
}

function renderActivity(item, assessment = false) {
    const slide = pptx.addSlide("CLASS_BASE");
    addTitle(slide, item.title, item.pedagogical_function);
    const data = assessment ? item.assessment : item.activity;
    slide.addShape(shape.roundRect, { x: 0.65, y: 1.75, w: W * 0.34, h: 4.55, rectRadius: isChildren ? 0.2 : 0.04, fill: { color: C.primary }, line: { color: C.primary }, altText: assessment ? "Resumen de la evaluación" : "Resumen de la actividad" });
    if (isChildren) slide.addShape(shape.ellipse, { x: W * 0.34, y: 1.98, w: 0.62, h: 0.62, fill: { color: C.accent }, line: { color: C.accent, transparency: 100 }, altText: "Acento lúdico" });
    slide.addText(assessment ? (data?.type || "Evaluación") : (data?.goal || item.main_idea), { x: 0.98, y: 2.12, w: W * 0.34 - 0.66, h: 1.45, fontFace: FONT_HEAD, fontSize: isChildren ? 24 : 22, bold: true, color: "FFFFFF", margin: 0.01, fit: "shrink" });
    slide.addText(assessment ? "Comprobamos lo aprendido" : (data?.modality || "Actividad"), { x: 0.98, y: 4.92, w: W * 0.34 - 0.66, h: 0.62, fontFace: FONT_BODY, fontSize: isChildren ? 14 : 13, color: "DDEAF3", margin: 0.01, fit: "shrink" });
    const instructions = assessment ? data?.instructions : data?.instructions;
    slide.addText(assessment ? "¿Cómo responder?" : "Ruta de trabajo", { x: W * 0.38 + 0.35, y: 1.92, w: W * 0.56, h: 0.38, fontFace: FONT_HEAD, fontSize: isChildren ? 20 : 18, bold: true, color: C.secondary, margin: 0.01, breakLine: false });
    addBulletList(slide, instructions || item.bullets, { x: W * 0.38 + 0.35, y: 2.55, w: W * 0.56, h: 2.65 });
    const footerText = assessment ? (data?.success_criteria || []).join(" · ") : (data?.expected_product ? `Producto esperado: ${data.expected_product}` : item.visible_text);
    slide.addShape(shape.roundRect, { x: W * 0.38 + 0.35, y: 5.38, w: W * 0.56, h: 0.75, rectRadius: isChildren ? 0.16 : 0.04, fill: { color: C.soft }, line: { color: C.soft }, altText: "Criterio de cierre" });
    slide.addText(footerText || "", { x: W * 0.38 + 0.6, y: 5.57, w: W * 0.56 - 0.5, h: 0.35, fontFace: FONT_BODY, fontSize: isChildren ? 13 : 12, bold: true, color: C.primary, align: "center", margin: 0.01, fit: "shrink" });
    addNotes(slide, item);
}

function renderChart(item) {
    const slide = pptx.addSlide("CLASS_BASE");
    addTitle(slide, item.title, item.pedagogical_function);
    const chart = item.chart_data;
    const series = (chart.datasets || []).map((dataset) => ({ name: dataset.name, labels: chart.labels, values: dataset.values }));
    const chartType = chart.type === "line" ? pptx.ChartType.line : chart.type === "pie" ? pptx.ChartType.pie : pptx.ChartType.bar;
    slide.addChart(chartType, series, { x: 0.78, y: 1.72, w: W * 0.58, h: 4.55, catAxisLabelFontFace: FONT_BODY, catAxisLabelFontSize: isChildren ? 12 : 11, valAxisLabelFontFace: FONT_BODY, valAxisLabelFontSize: isChildren ? 11 : 10, showLegend: series.length > 1, legendFontFace: FONT_BODY, legendFontSize: isChildren ? 11 : 10, showTitle: false, showValue: chart.type === "pie", chartColors: [C.secondary, C.accent, C.accentAlt, C.primary], showCatName: chart.type === "pie", altText: item.alt_text || item.visual_resource?.alt_text || "Gráfico editable" });
    slide.addText(item.main_idea, { x: W * 0.62, y: 2.02, w: W * 0.31, h: 1.1, fontFace: FONT_HEAD, fontSize: isChildren ? 23 : 21, bold: true, color: C.primary, margin: 0.01, fit: "shrink" });
    addBulletList(slide, item.bullets, { x: W * 0.62, y: 3.45, w: W * 0.31, h: 2.1 });
    addNotes(slide, item);
}

function renderBibliography(item) {
    const slide = pptx.addSlide("CLASS_BASE");
    addTitle(slide, item.title, item.pedagogical_function);
    const sources = deck.bibliography || item.sources || [];
    const entries = sources.slice(0, 9).map((source, index) => `${index + 1}. ${source.title}${source.publisher ? ` · ${source.publisher}` : ""}${source.url ? `\n${source.url}` : ""}`).join("\n\n");
    slide.addText(entries || "Fuentes curriculares institucionales registradas en el sistema.", { x: 0.78, y: 1.72, w: W - 1.56, h: 4.95, fontFace: FONT_BODY, fontSize: isChildren ? 16 : 15, color: C.ink, margin: 0.08, breakLine: false, fit: "shrink", valign: "top" });
    addNotes(slide, { ...item, sources });
}

function renderClosure(item) {
    const slide = pptx.addSlide();
    slide.background = { color: isChildren ? C.bg : C.primary };
    slide.addShape(shape.ellipse, { x: W - 3.25, y: 0, w: 3.25, h: 3.25, fill: { color: C.secondary, transparency: 5 }, line: { color: C.secondary, transparency: 100 }, altText: "Elemento decorativo de cierre" });
    if (isChildren) slide.addShape(shape.ellipse, { x: W - 4.2, y: H - 2.0, w: 1.45, h: 1.45, fill: { color: C.accentAlt }, line: { color: C.accentAlt, transparency: 100 }, altText: "Acento cálido de cierre" });
    const closureInk = isChildren ? C.ink : "FFFFFF";
    slide.addText(item.title, { x: 0.92, y: 1.18, w: W * 0.64, h: 1.15, fontFace: FONT_HEAD, fontSize: isChildren ? 35 : 33, bold: true, color: closureInk, margin: 0.01, fit: "shrink" });
    slide.addText(item.main_idea || deck.metadata?.central_message, { x: 0.96, y: 2.8, w: W * 0.62, h: 1.3, fontFace: FONT_BODY, fontSize: isChildren ? 23 : 21, color: isChildren ? C.muted : "DDEAF3", margin: 0.01, fit: "shrink" });
    if (item.audience_question) slide.addText(item.audience_question, { x: 0.96, y: 5.2, w: W * 0.64, h: 0.72, fontFace: FONT_BODY, fontSize: isChildren ? 19 : 17, italic: true, color: closureInk, margin: 0.01, fit: "shrink" });
    slide.addText(snapshot.school?.name || "", { x: 0.96, y: 6.7, w: W * 0.64, h: 0.22, fontFace: FONT_BODY, fontSize: 9, color: isChildren ? C.muted : "C9D7E5", margin: 0.01, breakLine: false });
    addNotes(slide, item);
}

function renderGeneric(item, index) {
    const slide = pptx.addSlide("CLASS_BASE");
    addTitle(slide, item.title, item.pedagogical_function);
    const visualRight = index % 2 === 0;
    const textX = visualRight ? 0.68 : W * 0.43;
    const visualX = visualRight ? W * 0.59 : 0.68;
    const textW = visualRight ? W * 0.49 : W * 0.51;
    const visualW = visualRight ? W * 0.34 : W * 0.34;
    slide.addText(item.main_idea, { x: textX, y: 1.78, w: textW, h: 0.86, fontFace: FONT_HEAD, fontSize: isChildren ? 23 : 21, bold: true, color: C.primary, margin: 0.01, fit: "shrink" });
    if (item.visible_text) slide.addText(item.visible_text, { x: textX, y: 2.82, w: textW, h: 0.78, fontFace: FONT_BODY, fontSize: isChildren ? 16.5 : 15, color: C.muted, margin: 0.01, fit: "shrink" });
    addBulletList(slide, item.bullets, { x: textX, y: 3.72, w: textW, h: 1.65 });
    addVisualPanel(slide, item.visual_resource, { x: visualX, y: 1.78, w: visualW, h: 3.95 }, item.highlighted_concepts);
    addConcepts(slide, item.highlighted_concepts, 5.92);
    addNotes(slide, item);
}

deck.slides.forEach((item, index) => {
    if (item.type === "cover") return renderCover(item);
    if (item.type === "objectives") return renderObjectives(item);
    if (item.type === "comparison") return renderComparison(item);
    if (["process", "timeline"].includes(item.type)) return renderProcess(item);
    if (item.type === "activity") return renderActivity(item, false);
    if (item.type === "assessment") return renderActivity(item, true);
    if (item.type === "bibliography") return renderBibliography(item);
    if (["closure", "conclusion"].includes(item.type) && index === deck.slides.length - 1) return renderClosure(item);
    if (item.chart_data?.datasets?.length) return renderChart(item);
    return renderGeneric(item, index);
});

await fs.mkdir(path.dirname(outputPath), { recursive: true });
await pptx.writeFile({ fileName: outputPath, compression: true });
