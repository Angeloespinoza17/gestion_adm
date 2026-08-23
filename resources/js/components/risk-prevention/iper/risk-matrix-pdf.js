import { getPdfMake } from "../../../utils/pdfmake";

const text = (value) => value === null || value === undefined || value === "" ? "—" : String(value);
const date = (value) => value ? new Intl.DateTimeFormat("es-CL", { dateStyle: "medium" }).format(new Date(String(value).length === 10 ? `${value}T12:00:00` : value)) : "—";
const status = (value) => ({ draft: "Borrador", in_review: "En revisión", observed: "Observada", approved: "Aprobada", superseded: "Reemplazada", archived: "Archivada" }[value] || value);

function risks(version) {
  return (version.processes || []).flatMap((process) => (process.tasks || []).flatMap((task) => (task.risks || []).map((risk) => {
    const assessment = (risk.assessments || []).find((item) => item.phase === "current" && item.active) || {};
    const level = assessment.calculated_level || {};
    const controls = (risk.controls || []).filter((item) => item.control_stage !== "existing");
    return { process: process.name, task: `${task.activity_name} · ${task.task_name}`, hazard: (risk.hazard_factors || []).map((item) => item.hazard_description).join("; "), risk: risk.specific_risk_name, score: assessment.calculated_score, level: level.name || assessment.result_level || "Sin nivel", control: controls.map((item) => item.description).join("; ") || "Sin medida", responsible: controls.map((item) => item.responsible?.name || item.responsible_text).filter(Boolean).join(", ") || "Sin asignar" };
  })));
}

export async function downloadRiskMatrixPdf(version) {
  const pdfMake = await getPdfMake();
  const matrix = version.matrix || {};
  const rows = risks(version);
  const versionStatus = typeof version.status === "string" ? version.status : version.status?.value;
  const levelColor = (level) => level === "Intolerable" ? "#b42318" : level === "Importante" ? "#dc6803" : level === "Moderado" ? "#d97706" : "#2e7d32";
  const definition = {
    pageSize: "A4", pageOrientation: "landscape", pageMargins: [28, 42, 28, 42],
    info: { title: `Matriz IPER ${matrix.code} v${version.version_number}`, subject: "Identificación de peligros y evaluación de riesgos", author: "CNSC Gestión" },
    watermark: versionStatus !== "approved" ? { text: "BORRADOR · NO APROBADO", color: "#b42318", opacity: .055, bold: true } : undefined,
    header: () => ({ margin: [28, 14, 28, 0], columns: [{ text: "PREVENCIÓN DE RIESGOS · MATRIZ IPER/MIPER", color: "#17324d", bold: true, fontSize: 8 }, { text: `${matrix.code} · v${version.version_number} · ${status(versionStatus)}`, alignment: "right", color: "#667085", fontSize: 7 }] }),
    footer: (current, total) => ({ margin: [28, 8, 28, 0], columns: [{ text: `Hash ${version.snapshot_hash ? version.snapshot_hash.slice(0,12) : "sin hash · borrador"} · Emitido ${date(new Date())}`, color: "#667085", fontSize: 6 }, { text: `Página ${current} de ${total}`, alignment: "right", color: "#667085", fontSize: 6 }] }),
    content: [
      { table: { widths: ["*"], body: [[{ stack: [{ text: "MATRIZ DE IDENTIFICACIÓN DE PELIGROS Y EVALUACIÓN DE RIESGOS", bold: true, color: "#fff", fontSize: 15 }, { text: `${text(matrix.name)} · ${text(version.company_name_snapshot)}`, color: "#d7ebee", margin: [0,4,0,0], fontSize: 8 }], fillColor: "#17324d", margin: [12,10,12,10] }]] }, layout: "noBorders", margin: [0,0,0,10] },
      { columns: [[{ text: "Centro de trabajo", style: "label" }, { text: text(version.work_center_name_snapshot), style: "value" }], [{ text: "Metodología", style: "label" }, { text: `${text(version.methodology?.code)} v${text(version.methodology?.version_number)}`, style: "value" }], [{ text: "Fecha de elaboración", style: "label" }, { text: date(version.prepared_on), style: "value" }], [{ text: "Próxima revisión", style: "label" }, { text: date(version.next_review_at), style: "value" }]], columnGap: 10, margin: [0,0,0,10] },
      { text: `Matriz de riesgos · ${rows.length} registros`, style: "section" },
      { table: { headerRows: 1, widths: [70,95,90,100,28,48,"*",70], body: [["Proceso","Actividad / tarea","Peligro","Riesgo específico","VEP","Nivel","Medida preventiva o correctiva","Responsable"].map((item) => ({ text: item, style: "th" })), ...rows.map((row) => [row.process,row.task,row.hazard,row.risk,text(row.score),{ text: row.level, color: levelColor(row.level), bold: true },row.control,row.responsible].map((item) => typeof item === "object" ? { ...item, margin: 3, fontSize: 5.8 } : { text: text(item), margin: 3, fontSize: 5.8 }))] }, layout: { fillColor: (rowIndex) => rowIndex === 0 ? "#17324d" : rowIndex % 2 ? "#f8fafc" : null, hLineColor: () => "#dfe5ec", vLineColor: () => "#dfe5ec", hLineWidth: () => .45, vLineWidth: () => .45 } },
      { text: "Leyenda metodológica", style: "section", pageBreak: rows.length > 22 ? "before" : undefined },
      { columns: [{ text: [{ text: "Probabilidad\n", bold: true }, "1 Baja · 2 Media · 4 Alta"], style: "legend" }, { text: [{ text: "Consecuencia\n", bold: true }, "1 Baja · 2 Media · 4 Alta"], style: "legend" }, { text: [{ text: "Clasificación VEP\n", bold: true }, "1–2 Tolerable · 4 Moderado · 8 Importante · 16 Intolerable"], style: "legend" }, { text: [{ text: "Jerarquía de controles\n", bold: true }, "Eliminación · Sustitución · Ingeniería · Administrativos · EPP"], style: "legend" }], columnGap: 8 },
      { text: "Constancias", style: "section" },
      { table: { widths: ["*","*","*"], body: [[{ text: `Elaboró\n${text(version.prepared_by?.name)}`, style: "signature" }, { text: `Revisó técnicamente\n${text(version.reviewed_by?.name)}`, style: "signature" }, { text: `Aprobó internamente\n${text(version.approved_by?.name)}`, style: "signature" }]] }, layout: { hLineColor: () => "#dfe5ec", vLineColor: () => "#dfe5ec" } },
    ],
    styles: { label: { color: "#667085", fontSize: 6.4, bold: true }, value: { color: "#1d2939", fontSize: 7.4 }, section: { margin: [0,11,0,6], color: "#17324d", fontSize: 9, bold: true }, th: { color: "#fff", bold: true, fontSize: 6, margin: 3 }, legend: { fillColor: "#f5f8fa", color: "#475467", fontSize: 6.5, margin: 7 }, signature: { alignment: "center", margin: [8,15,8,15], color: "#344054", fontSize: 7 } },
    defaultStyle: { fontSize: 7, color: "#344054" },
  };
  const filename = `matriz-iper-${matrix.code || "sin-codigo"}-v${version.version_number}.pdf`.toLowerCase().replace(/[^a-z0-9._-]+/g,"-");
  pdfMake.createPdf(definition).download(filename);
  return definition;
}
