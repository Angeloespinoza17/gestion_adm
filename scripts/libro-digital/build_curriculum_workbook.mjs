import fs from "node:fs/promises";
import path from "node:path";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const [datasetPath, outputPath, renderDir] = process.argv.slice(2);
if (!datasetPath || !outputPath || !renderDir) {
  throw new Error("Uso: node build_curriculum_workbook.mjs <dataset.json> <salida.xlsx> <renders-dir>");
}

const dataset = JSON.parse(await fs.readFile(datasetPath, "utf8"));
await fs.mkdir(path.dirname(outputPath), { recursive: true });
await fs.mkdir(renderDir, { recursive: true });

const palette = {
  navy: "#0B1F3A",
  blue: "#1D4ED8",
  teal: "#0F766E",
  sky: "#DBEAFE",
  mint: "#D1FAE5",
  amber: "#D97706",
  amberSoft: "#FEF3C7",
  red: "#B91C1C",
  redSoft: "#FEE2E2",
  ink: "#172033",
  muted: "#526078",
  line: "#D7E0EC",
  surface: "#F4F7FB",
  white: "#FFFFFF",
};

const workbook = Workbook.create();
const sheetNames = [
  "Resumen",
  "Cobertura",
  "CoberturaDetalle",
  "Asignaturas",
  "CorpusMaestro",
  "FuentesMaestras",
  "Exclusiones",
  "Catalogo",
  "Objetivos",
  "Fuentes",
  "ObjetivoFuentes",
  "Vinculos",
  "Referencias",
  "Control",
];
const sheets = Object.fromEntries(sheetNames.map((name) => [name, workbook.worksheets.add(name)]));

const colLetter = (index) => {
  let value = index + 1;
  let output = "";
  while (value > 0) {
    value -= 1;
    output = String.fromCharCode(65 + (value % 26)) + output;
    value = Math.floor(value / 26);
  }
  return output;
};

const matrixFrom = (rows, columns) => rows.map((row) => columns.map((column) => {
  const value = row[column.key];
  if (Array.isArray(value)) return value.join("|");
  if (typeof value === "boolean") return value ? "SI" : "NO";
  if (value === null || value === undefined) return "";
  if (column.key === "retrieved_at" && value !== "") return `'${value}`;
  return value;
}));

const widthFor = (key) => {
  if (["description", "reason", "activation_condition", "notes"].includes(key)) return 64;
  if (["source_url", "objective_url", "subject_page_url", "evidence_path"].includes(key)) return 54;
  if (["source_sha256", "sha256"].includes(key)) return 66;
  if (["source_name", "subject_name", "catalog_name", "axis_name", "official_grade_scope"].includes(key)) return 34;
  if (["record_id", "source_key", "coverage_key", "exclusion_key"].includes(key)) return 28;
  if (["official_code", "reference_code", "code", "subject_code", "axis_code", "document_number"].includes(key)) return 24;
  if (["normative_status", "disposition", "status", "source_role", "source_scope"].includes(key)) return 25;
  if (["retrieved_at", "effective_from", "effective_to", "valid_from", "valid_to"].includes(key)) return 18;
  if (["grade_codes", "grade_code", "level_code", "curriculum_track", "objective_type"].includes(key)) return 17;
  return 15;
};

const addTableSheet = ({
  name,
  title,
  subtitle,
  notice,
  columns,
  rows,
  tableName,
  rowHeight = 30,
  tableStyle = "TableStyleMedium2",
}) => {
  const sheet = sheets[name];
  const lastCol = colLetter(columns.length - 1);
  const lastRow = Math.max(6, 5 + rows.length);
  sheet.showGridLines = false;
  sheet.freezePanes.freezeRows(5);
  sheet.getRange(`A1:${lastCol}1`).merge();
  sheet.getRange("A1").values = [[title]];
  sheet.getRange(`A2:${lastCol}2`).merge();
  sheet.getRange("A2").values = [[subtitle]];
  sheet.getRange(`A3:${lastCol}3`).merge();
  sheet.getRange("A3").values = [[notice]];
  sheet.getRange(`A1:${lastCol}1`).format = {
    fill: palette.navy,
    font: { bold: true, color: palette.white, size: 18 },
    verticalAlignment: "center",
  };
  sheet.getRange(`A2:${lastCol}2`).format = {
    fill: palette.sky,
    font: { bold: true, color: palette.navy, size: 11 },
    verticalAlignment: "center",
  };
  sheet.getRange(`A3:${lastCol}3`).format = {
    fill: palette.amberSoft,
    font: { color: "#7C4A03", italic: true, size: 10 },
    wrapText: true,
    verticalAlignment: "center",
  };
  sheet.getRange(`A5:${lastCol}5`).values = [columns.map((column) => column.label)];
  sheet.getRange(`A5:${lastCol}5`).format = {
    fill: palette.blue,
    font: { bold: true, color: palette.white, size: 10 },
    wrapText: true,
    verticalAlignment: "center",
    borders: { preset: "all", style: "thin", color: "#93B4E8" },
  };
  const values = matrixFrom(rows, columns);
  const chunkSize = 1000;
  for (let offset = 0; offset < values.length; offset += chunkSize) {
    const chunk = values.slice(offset, offset + chunkSize);
    sheet.getRangeByIndexes(5 + offset, 0, chunk.length, columns.length).values = chunk;
  }
  if (rows.length > 0) {
    const dataRange = sheet.getRange(`A6:${lastCol}${lastRow}`);
    dataRange.format = {
      font: { color: palette.ink, size: 9 },
      verticalAlignment: "top",
      wrapText: true,
      borders: { preset: "all", style: "thin", color: palette.line },
      rowHeight,
    };
    const table = sheet.tables.add(`A5:${lastCol}${lastRow}`, true, tableName);
    table.style = tableStyle;
    table.showFilterButton = true;
    table.showBandedRows = true;
  }
  columns.forEach((column, index) => {
    sheet.getRangeByIndexes(0, index, lastRow, 1).format.columnWidth = widthFor(column.key);
  });
  sheet.getRange("1:1").format.rowHeight = 34;
  sheet.getRange("2:2").format.rowHeight = 28;
  sheet.getRange("3:3").format.rowHeight = 40;
  sheet.getRange("5:5").format.rowHeight = 34;
  return { sheet, lastRow, lastCol, columns };
};

const C = (key, label = key) => ({ key, label });

const coverage = addTableSheet({
  name: "Cobertura",
  title: "Cobertura extraída por nivel y trayectoria",
  subtitle: "NT1 y NT2 son proyecciones técnicas del Nivel Transición; 3M–4M distingue GENERAL, HC, TP y ARTÍSTICA.",
  notice: "Los conteos incluyen filas maestras. ‘Activables’ excluye expresamente propuestas, OF/OFT y la trayectoria TP bloqueada hasta modelar sector, especialidad y mención.",
  columns: [
    C("coverage_key", "Ámbito"), C("level_code", "Nivel"), C("grade_code", "Grado"), C("curriculum_track", "Trayectoria"),
    C("subject_count", "Asignaturas/ámbitos"), C("master_rows", "Filas maestras"), C("import_included_rows", "Filas importables"),
    C("active_recommended_rows", "Activables"), C("oa", "OA"), C("oat", "OAT"), C("oah", "OAH"), C("oaa", "OAA"),
    C("oag", "OAG"), C("oac", "OAC"), C("of", "OF"), C("oft", "OFT"), C("status", "Estado"),
  ],
  rows: dataset.coverage,
  tableName: "CoberturaCurricular",
});

const assignments = addTableSheet({
  name: "Asignaturas",
  title: "Asignaturas, ámbitos, especialidades y menciones",
  subtitle: "Inventario normalizado de las 328 páginas oficiales nivel–asignatura encontradas en Currículum Nacional.",
  notice: "Un código técnico sirve para interoperar con el ERP; el nombre oficial, trayectoria y URL conservan la identidad del contenido publicado.",
  columns: [
    C("subject_code", "Código técnico"), C("subject_name", "Asignatura / ámbito"), C("curriculum_track", "Trayectoria"),
    C("level_codes", "Niveles"), C("grade_codes", "Grados"), C("objective_rows", "Filas maestras"),
    C("active_recommended_rows", "Activables"), C("dispositions", "Disposiciones"), C("source_url", "Página oficial"), C("status", "Estado"),
  ],
  rows: dataset.subjects,
  tableName: "AsignaturasCurriculum",
});

const coverageDetail = addTableSheet({
  name: "CoberturaDetalle",
  title: "Cobertura detallada por nivel, trayectoria y asignatura",
  subtitle: `Vista de reconciliación de ${dataset.stats.coverage_subject_grade_rows} combinaciones grado–trayectoria–asignatura/ámbito presentes en el corpus proyectado.`,
  notice: "Esta vista acredita filas y evidencias extraídas, no una certificación de completitud ministerial. Las ausencias y estructuras no equivalentes se declaran en Exclusiones.",
  columns: [
    C("coverage_key", "Clave"), C("level_code", "Nivel"), C("grade_code", "Grado"), C("curriculum_track", "Trayectoria"),
    C("subject_code", "Código técnico"), C("subject_name", "Asignatura / ámbito"), C("master_rows", "Filas proyectadas"),
    C("canonical_groups", "Grupos canónicos"), C("import_included_rows", "Incluidas importador"),
    C("active_recommended_rows", "Activables"), C("objective_types", "Tipos"), C("dispositions", "Disposiciones"),
    C("source_count", "Fuentes"), C("source_url", "Página oficial"), C("status", "Estado"),
  ],
  rows: dataset.coverage_detail,
  tableName: "CoberturaDetalleCurricular",
});

const master = addTableSheet({
  name: "CorpusMaestro",
  title: "Corpus maestro proyectado — Currículum Nacional NT1 a 4° medio",
  subtitle: "Cada fila representa un alcance de grado; canonical_group_id reúne proyecciones del mismo objetivo. Conserva OA, OAT, OAH, OAA, OAG, OAC, OF y OFT.",
  notice: "IMPORTABLE_ACTIVABLE no equivale a certificación. MASTER_ONLY y MASTER_REFERENCE_ONLY nunca se cargan como OA; IMPORTABLE_BLOCKED se entrega desactivado.",
  columns: [
    C("record_id", "ID de proyección"), C("canonical_group_id", "Grupo canónico"), C("official_code", "Código oficial"), C("reference_code", "Código/referencia"),
    C("objective_type", "Tipo"), C("subject_code", "Código asignatura"), C("subject_name", "Asignatura / ámbito"),
    C("level_code", "Nivel"), C("grade_codes", "Grados"), C("official_grade_scope", "Alcance oficial"),
    C("technical_projection", "Proyección técnica"), C("curriculum_track", "Trayectoria"), C("axis_code", "Código eje"),
    C("axis_name", "Eje / núcleo"), C("sociolinguistic_context", "Contexto sociolingüístico"), C("description", "Texto oficial"),
    C("normative_status", "Estatus normativo"), C("disposition", "Disposición"), C("import_included", "Incluido importador"),
    C("active_recommended", "Activación sugerida"), C("source_key", "Fuente canónica"), C("source_locator", "Localizador"),
    C("objective_url", "URL del objetivo"), C("subject_page_url", "URL asignatura"), C("source_sha256", "SHA-256 evidencia"),
    C("retrieved_at", "Fecha consulta"),
  ],
  rows: dataset.master_objectives,
  tableName: "CorpusMaestroCurricular",
  rowHeight: 42,
  tableStyle: "TableStyleMedium4",
});

const masterSources = addTableSheet({
  name: "FuentesMaestras",
  title: "Registro maestro de fuentes y evidencias",
  subtitle: "Snapshots HTML y documentos PDF oficiales con hash SHA-256 calculado sobre los bytes archivados.",
  notice: "El hash acredita identidad de los bytes consultados; no implica que MINEDUC certifique este archivo ni garantiza que una URL futura conserve los mismos bytes.",
  columns: [
    C("source_key", "Clave fuente"), C("source_scope", "Alcance"), C("source_name", "Nombre"), C("authority", "Autoridad"),
    C("document_number", "Documento / acto"), C("source_url", "URL oficial"), C("source_sha256", "SHA-256"),
    C("effective_from", "Vigente desde"), C("effective_to", "Vigente hasta"), C("curriculum_track", "Trayectoria"),
    C("subject_code", "Asignatura"), C("objective_type", "Tipo"), C("evidence_filename", "Archivo original"), C("package_filename", "Archivo en ZIP"),
    C("retrieved_at", "Consulta"), C("hash_scope", "Alcance hash"),
  ],
  rows: dataset.master_sources,
  tableName: "FuentesMaestrasCurriculo",
});

const exclusions = addTableSheet({
  name: "Exclusiones",
  title: "Bloqueos, exclusiones y condiciones de activación",
  subtitle: "Decisiones fail-closed para impedir que una estructura no equivalente sea presentada como OA oficial activado.",
  notice: "Resolver una condición requiere evidencia, revisión humana y auditoría; no basta con cambiar SI/NO en este archivo.",
  columns: [
    C("exclusion_key", "Clave"), C("scope", "Alcance"), C("disposition", "Tratamiento"), C("reason_code", "Código motivo"),
    C("reason", "Razón"), C("activation_condition", "Condición para habilitar"), C("official_source_url", "Fuente oficial"),
  ],
  rows: dataset.exclusions,
  tableName: "ExclusionesCurriculum",
  tableStyle: "TableStyleMedium9",
});

const catalogRows = [dataset.catalog];
const catalog = addTableSheet({
  name: "Catalogo",
  title: "Catálogo de importación gobernada",
  subtitle: "Hoja contractual para el importador del Libro Digital.",
  notice: "source_sha256 queda vacío por diseño: la trazabilidad oficial se resuelve en Fuentes + ObjetivoFuentes y los archivos de evidencia, no mediante un hash autorreferente del XLSX.",
  columns: [C("catalog_code"), C("catalog_name"), C("version"), C("authority"), C("source_url"), C("source_sha256"), C("effective_from"), C("effective_to")],
  rows: catalogRows,
  tableName: "CatalogoImportacion",
});

const objectives = addTableSheet({
  name: "Objetivos",
  title: "Objetivos normalizados para el importador",
  subtitle: "Tipos admitidos por el sistema: OA, OAT, OAH, OAA, OAG y OAC. La columna active controla el estado inicial.",
  notice: "Las filas active=NO se preservan para revisión pero no deben activarse automáticamente. OF/OFT y propuestas no aparecen en esta hoja; sí están en CorpusMaestro.",
  columns: [
    C("catalog_code"), C("catalog_version"), C("code"), C("objective_type"), C("subject_code"), C("level_code"), C("grade_code"),
    C("axis_code"), C("unit_code"), C("description"), C("indicators_json"), C("active"), C("source_page"), C("curriculum_track"),
  ],
  rows: dataset.import_objectives,
  tableName: "ObjetivosImportacion",
  rowHeight: 42,
  tableStyle: "TableStyleMedium4",
});

const sources = addTableSheet({
  name: "Fuentes",
  title: "Fuentes vinculadas a objetivos importables",
  subtitle: "Cada source_key debe acompañarse al importar con evidence_files[source_key] y bytes cuyo SHA-256 coincida.",
  notice: "El XLSX no incrusta los archivos oficiales. Use el ZIP de evidencias adjunto, extráigalo y cargue exactamente las fuentes exigidas por el lote.",
  columns: [
    C("source_key"), C("source_scope"), C("source_name"), C("authority"), C("document_number"), C("source_url"), C("source_sha256"),
    C("effective_from"), C("effective_to"), C("curriculum_track"), C("subject_code"), C("objective_type"),
  ],
  rows: dataset.import_sources,
  tableName: "FuentesImportacion",
});

const objectiveSources = addTableSheet({
  name: "ObjetivoFuentes",
  title: "Trazabilidad N:M entre objetivos y fuentes",
  subtitle: "Cada identidad de objetivo tiene exactamente una fuente canonical_text y una base legal complementaria.",
  notice: "canonical_text identifica el artefacto que contiene el texto; legal_basis documenta el acto/base aplicable y no reemplaza la fuente canónica.",
  columns: [
    C("objective_code"), C("objective_type"), C("subject_code"), C("level_code"), C("grade_code"), C("curriculum_track"),
    C("axis_code"), C("source_key"), C("source_role"), C("source_locator"),
  ],
  rows: dataset.objective_sources,
  tableName: "ObjetivoFuentesImportacion",
});

const links = addTableSheet({
  name: "Vinculos",
  title: "Vínculos propuestos para el establecimiento local",
  subtitle: "RBD 6830 · año académico 2026 · oferta local actualmente detectada: NT1, NT2, 1B y 2B.",
  notice: "No se inventan vínculos para cursos no ofrecidos. Antes de validar, deben existir las asignaturas técnicas PM y MAT y deben aplicarse las migraciones curriculares pendientes.",
  columns: [
    C("school_rbd"), C("academic_year"), C("subject_code"), C("catalog_code"), C("catalog_version"), C("level_code"),
    C("grade_code"), C("valid_from"), C("valid_to"), C("active"), C("curriculum_track"),
  ],
  rows: dataset.links,
  tableName: "VinculosImportacion",
});

const references = addTableSheet({
  name: "Referencias",
  title: "Referencias técnicas y normativas",
  subtitle: "Metadatos adicionales leídos por el importador como hoja opcional.",
  notice: "Esta hoja no sustituye Fuentes, ObjetivoFuentes ni los archivos oficiales de evidencia.",
  columns: [C("category"), C("key"), C("value"), C("source_url"), C("sha256"), C("notes")],
  rows: dataset.references,
  tableName: "ReferenciasCurriculum",
});

const controlSheet = sheets.Control;
controlSheet.showGridLines = false;
controlSheet.freezePanes.freezeRows(5);
controlSheet.getRange("A1:D1").merge();
controlSheet.getRange("A1").values = [["Control de integridad del libro curricular"]];
controlSheet.getRange("A2:D2").merge();
controlSheet.getRange("A2").values = [["Fórmulas de control calculadas a partir de las hojas contractuales."]];
controlSheet.getRange("A3:D3").merge();
controlSheet.getRange("A3").values = [["Resultado esperado: todas las verificaciones deben indicar OK antes de iniciar una validación gobernada."]];
controlSheet.getRange("A5:D5").values = [["Control", "Resultado", "Esperado", "Estado"]];
const controlRows = [
  ["Filas importables", `=COUNTA(Objetivos!C6:C${objectives.lastRow})`, dataset.stats.import_rows, '=IF(B6=C6,"OK","REVISAR")'],
  ["Filas activas sugeridas", `=COUNTIF(Objetivos!L6:L${objectives.lastRow},"SI")`, dataset.stats.import_active_rows, '=IF(B7=C7,"OK","REVISAR")'],
  ["Filas corpus maestro", `=COUNTA(CorpusMaestro!A6:A${master.lastRow})`, dataset.stats.master_rows, '=IF(B8=C8,"OK","REVISAR")'],
  ["Fuentes importables", `=COUNTA(Fuentes!A6:A${sources.lastRow})`, dataset.stats.sources_import, '=IF(B9=C9,"OK","REVISAR")'],
  ["Relaciones objetivo–fuente", `=COUNTA(ObjetivoFuentes!A6:A${objectiveSources.lastRow})`, dataset.stats.objective_source_relations, '=IF(B10=C10,"OK","REVISAR")'],
  ["Vínculos locales", `=COUNTA(Vinculos!A6:A${links.lastRow})`, dataset.links.length, '=IF(B11=C11,"OK","REVISAR")'],
  ["Bloqueados en maestro", `=COUNTIF(CorpusMaestro!R6:R${master.lastRow},"IMPORTABLE_BLOCKED")`, dataset.stats.by_disposition.IMPORTABLE_BLOCKED, '=IF(B12=C12,"OK","REVISAR")'],
  ["Fuentes canonical_text", `=COUNTIF(ObjetivoFuentes!I6:I${objectiveSources.lastRow},"canonical_text")`, dataset.stats.import_rows, '=IF(B13=C13,"OK","REVISAR")'],
];
controlSheet.getRange("A6:A13").values = controlRows.map((row) => [row[0]]);
controlSheet.getRange("B6:B13").formulas = controlRows.map((row) => [row[1]]);
controlSheet.getRange("C6:C13").values = controlRows.map((row) => [row[2]]);
controlSheet.getRange("D6:D13").formulas = controlRows.map((row) => [row[3]]);
controlSheet.getRange("A1:D1").format = { fill: palette.navy, font: { bold: true, color: palette.white, size: 18 } };
controlSheet.getRange("A2:D2").format = { fill: palette.sky, font: { bold: true, color: palette.navy, size: 11 } };
controlSheet.getRange("A3:D3").format = { fill: palette.amberSoft, font: { italic: true, color: "#7C4A03", size: 10 }, wrapText: true };
controlSheet.getRange("A5:D5").format = { fill: palette.blue, font: { bold: true, color: palette.white }, borders: { preset: "all", style: "thin", color: palette.line } };
controlSheet.getRange("A6:D13").format = { font: { color: palette.ink, size: 10 }, borders: { preset: "all", style: "thin", color: palette.line }, rowHeight: 28 };
controlSheet.getRange("A:A").format.columnWidth = 34;
controlSheet.getRange("B:C").format.columnWidth = 18;
controlSheet.getRange("D:D").format.columnWidth = 16;
controlSheet.getRange("D6:D13").conditionalFormats.add("containsText", { text: "OK", format: { fill: palette.mint, font: { color: palette.teal, bold: true } } });
controlSheet.getRange("D6:D13").conditionalFormats.add("containsText", { text: "REVISAR", format: { fill: palette.redSoft, font: { color: palette.red, bold: true } } });

const summary = sheets.Resumen;
summary.showGridLines = false;
summary.freezePanes.freezeRows(4);
summary.getRange("A1:H1").merge();
summary.getRange("A1").values = [["Catálogo completo de objetivos — Currículum Nacional"]];
summary.getRange("A2:H2").merge();
summary.getRange("A2").values = [["NT1 · NT2 · 1°–8° básico · 1°–4° medio | GENERAL · HC · TP · ARTÍSTICA"]];
summary.getRange("A3:H3").merge();
summary.getRange("A3").values = [["Extracción asistida desde 328 páginas oficiales y 8 documentos normativos. No es certificación MINEDUC; respete bloqueos y revisión humana."]];
summary.getRange("A1:H1").format = { fill: palette.navy, font: { bold: true, color: palette.white, size: 20 }, verticalAlignment: "center" };
summary.getRange("A2:H2").format = { fill: palette.blue, font: { bold: true, color: palette.white, size: 12 }, verticalAlignment: "center" };
summary.getRange("A3:H3").format = { fill: palette.amberSoft, font: { italic: true, color: "#7C4A03", size: 10 }, wrapText: true, verticalAlignment: "center" };
summary.getRange("A5:B5").merge(); summary.getRange("A5").values = [["CORPUS MAESTRO"]];
summary.getRange("C5:D5").merge(); summary.getRange("C5").values = [["IMPORTABLES"]];
summary.getRange("E5:F5").merge(); summary.getRange("E5").values = [["ACTIVABLES"]];
summary.getRange("G5:H5").merge(); summary.getRange("G5").values = [["FUENTES CON HASH"]];
for (const range of ["A5:B7", "C5:D7", "E5:F7", "G5:H7"]) {
  summary.getRange(range).format = { fill: palette.surface, borders: { preset: "outside", style: "medium", color: palette.line }, verticalAlignment: "center" };
}
for (const range of ["A5:B5", "C5:D5", "E5:F5", "G5:H5"]) {
  summary.getRange(range).format = { fill: palette.sky, font: { bold: true, color: palette.navy, size: 10 }, horizontalAlignment: "center" };
}
summary.getRange("A6:B7").merge(); summary.getRange("A6").formulas = [["=Control!B8"]];
summary.getRange("C6:D7").merge(); summary.getRange("C6").formulas = [["=Control!B6"]];
summary.getRange("E6:F7").merge(); summary.getRange("E6").formulas = [["=Control!B7"]];
summary.getRange("G6:H7").merge(); summary.getRange("G6").formulas = [["=Control!B9"]];
for (const cell of ["A6", "C6", "E6", "G6"]) {
  summary.getRange(cell).format = { font: { bold: true, color: palette.navy, size: 24 }, horizontalAlignment: "center", verticalAlignment: "center" };
  summary.getRange(cell).format.numberFormat = "#,##0";
}
summary.getRange("A9:H9").merge();
summary.getRange("A9").values = [["Cómo utilizar este libro"]];
summary.getRange("A9:H9").format = { fill: palette.teal, font: { bold: true, color: palette.white, size: 12 } };
summary.getRange("A10:H12").values = [
  ["1", "Revise Cobertura, Asignaturas y CorpusMaestro antes de importar.", "", "", "", "", "", ""],
  ["2", "Use únicamente Catalogo, Objetivos, Fuentes, ObjetivoFuentes, Vinculos y Referencias para el importador.", "", "", "", "", "", ""],
  ["3", "Adjunte evidence_files[source_key] desde el ZIP; mantenga active=NO en todas las filas bloqueadas.", "", "", "", "", "", ""],
];
for (let row = 10; row <= 12; row += 1) {
  summary.getRange(`B${row}:H${row}`).merge();
  summary.getRange(`A${row}:H${row}`).format = { fill: row % 2 === 0 ? palette.surface : palette.white, font: { color: palette.ink, size: 10 }, wrapText: true, borders: { preset: "all", style: "thin", color: palette.line }, rowHeight: 30 };
  summary.getRange(`A${row}`).format = { fill: palette.blue, font: { bold: true, color: palette.white, size: 11 }, horizontalAlignment: "center" };
}
summary.getRange("A14:C14").values = [["Nivel", "Filas maestras", "Activables"]];
summary.getRange("A14:C14").format = { fill: palette.blue, font: { bold: true, color: palette.white }, borders: { preset: "all", style: "thin", color: palette.line } };
const gradeOrder = ["NT1", "NT2", "1B", "2B", "3B", "4B", "5B", "6B", "7B", "8B", "1M", "2M", "3M", "4M"];
summary.getRange("A15:A28").values = gradeOrder.map((grade) => [grade]);
summary.getRange("B15:B28").formulas = gradeOrder.map((_, index) => [`=SUMIF(Cobertura!$C$6:$C$${coverage.lastRow},A${15 + index},Cobertura!$F$6:$F$${coverage.lastRow})`]);
summary.getRange("C15:C28").formulas = gradeOrder.map((_, index) => [`=SUMIF(Cobertura!$C$6:$C$${coverage.lastRow},A${15 + index},Cobertura!$H$6:$H$${coverage.lastRow})`]);
summary.getRange("A15:C28").format = { font: { color: palette.ink, size: 9 }, borders: { preset: "all", style: "thin", color: palette.line }, rowHeight: 22 };
const coverageChart = summary.charts.add("bar", summary.getRange("A14:C28"));
coverageChart.title = "Objetivos por nivel: maestro vs. activable";
coverageChart.hasLegend = true;
coverageChart.setPosition("E14", "M30");
summary.getRange("A:A").format.columnWidth = 14;
summary.getRange("B:H").format.columnWidth = 18;
summary.getRange("1:1").format.rowHeight = 38;
summary.getRange("2:2").format.rowHeight = 30;
summary.getRange("3:3").format.rowHeight = 42;

const activeRange = objectives.sheet.getRange(`L6:L${objectives.lastRow}`);
activeRange.conditionalFormats.add("containsText", { text: "SI", format: { fill: palette.mint, font: { color: palette.teal, bold: true } } });
activeRange.conditionalFormats.add("containsText", { text: "NO", format: { fill: palette.amberSoft, font: { color: "#7C4A03", bold: true } } });
master.sheet.getRange(`R6:R${master.lastRow}`).conditionalFormats.add("containsText", { text: "BLOCKED", format: { fill: palette.amberSoft, font: { color: "#7C4A03", bold: true } } });
master.sheet.getRange(`R6:R${master.lastRow}`).conditionalFormats.add("containsText", { text: "MASTER_ONLY", format: { fill: palette.redSoft, font: { color: palette.red, bold: true } } });
coverage.sheet.getRange(`Q6:Q${coverage.lastRow}`).conditionalFormats.add("containsText", { text: "BLOQUEADO", format: { fill: palette.redSoft, font: { color: palette.red, bold: true } } });

const exported = await SpreadsheetFile.exportXlsx(workbook);
await exported.save(outputPath);

const renderRanges = {
  Resumen: "A1:M30",
  Cobertura: `A1:Q${Math.min(30, coverage.lastRow)}`,
  Asignaturas: `A1:J${Math.min(24, assignments.lastRow)}`,
  CoberturaDetalle: "A1:O18",
  CorpusMaestro: "A1:Z14",
  FuentesMaestras: "A1:P16",
  Exclusiones: `A1:G${exclusions.lastRow}`,
  Catalogo: `A1:H${catalog.lastRow}`,
  Objetivos: "A1:N16",
  Fuentes: "A1:L16",
  ObjetivoFuentes: "A1:J16",
  Vinculos: `A1:K${links.lastRow}`,
  Referencias: "A1:F18",
  Control: "A1:D14",
};
for (const name of sheetNames) {
  const preview = await workbook.render({ sheetName: name, range: renderRanges[name], scale: 0.85, format: "png" });
  await fs.writeFile(path.join(renderDir, `${name}.png`), new Uint8Array(await preview.arrayBuffer()));
}

const inspect = await workbook.inspect({
  kind: "workbook,sheet,table,formula",
  maxChars: 12000,
  tableMaxRows: 3,
  tableMaxCols: 5,
  options: { maxResults: 100 },
});
await fs.writeFile(path.join(renderDir, "workbook-inspect.ndjson"), inspect.ndjson ?? String(inspect));

console.log(JSON.stringify({
  outputPath,
  sheets: sheetNames,
  rows: {
    master: dataset.master_objectives.length,
    importable: dataset.import_objectives.length,
    objectiveSources: dataset.objective_sources.length,
    sources: dataset.import_sources.length,
  },
}, null, 2));
