const EMPTY_VALUE = " ";

function clean(value) {
  return String(value ?? "").trim();
}

function display(value) {
  return clean(value) || EMPTY_VALUE;
}

function pad(value) {
  return String(value).padStart(2, "0");
}

function parseDate(value) {
  if (!value) return null;
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;

  const raw = String(value).trim();
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(raw)
    ? `${raw}T00:00:00`
    : raw.replace(" ", "T");
  const date = new Date(normalized);

  return Number.isNaN(date.getTime()) ? null : date;
}

function dateInput(value) {
  const date = parseDate(value);
  if (!date) return "";

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function dateTimeInput(value) {
  const date = parseDate(value);
  if (!date) return "";

  return `${dateInput(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function dateParts(value) {
  const date = parseDate(value);

  return date
    ? {
        year: String(date.getFullYear()),
        month: pad(date.getMonth() + 1),
        day: pad(date.getDate()),
        hour: pad(date.getHours()),
        minute: pad(date.getMinutes()),
      }
    : { year: "", month: "", day: "", hour: "", minute: "" };
}

function splitStudentName(student, snapshot = "") {
  const names = clean(student?.first_name);
  const surnames = clean(student?.last_name).split(/\s+/).filter(Boolean);

  if (names || surnames.length) {
    return {
      names,
      paternalSurname: surnames.shift() || "",
      maternalSurname: surnames.join(" "),
    };
  }

  const fallback = clean(snapshot).split(/\s+/).filter(Boolean);

  return {
    names: fallback.slice(0, Math.max(1, fallback.length - 2)).join(" "),
    paternalSurname: fallback.length > 1 ? fallback[fallback.length - 2] : "",
    maternalSurname: fallback.length > 2 ? fallback[fallback.length - 1] : "",
  };
}

function sexCode(value) {
  const normalized = clean(value).toLowerCase();
  if (["1", "f", "femenino", "female"].includes(normalized)) return "1";
  if (["2", "m", "masculino", "male"].includes(normalized)) return "2";
  return "";
}

function ageAt(birthDate, referenceDate) {
  const birth = parseDate(birthDate);
  const reference = parseDate(referenceDate);
  if (!birth || !reference) return "";

  let age = reference.getFullYear() - birth.getFullYear();
  const monthDifference = reference.getMonth() - birth.getMonth();

  if (monthDifference < 0 || (monthDifference === 0 && reference.getDate() < birth.getDate())) {
    age -= 1;
  }

  return age >= 0 ? String(age) : "";
}

function accidentDayCode(value) {
  const date = parseDate(value);
  if (!date) return "";

  return String(date.getDay() === 0 ? 7 : date.getDay());
}

const FORM_INK = "#4a4a4a";
const FORM_LINE = "#181818";
const TEXT_TOP_CORRECTION = 1.35;

function fittedFontSize(value, width, preferred = 7.3, minimum = 5.2) {
  const length = clean(value).length;
  if (!length) return preferred;

  return Math.max(minimum, Math.min(preferred, (width - 4) / (length * 0.52)));
}

function absoluteText(value, x, sourceTop, width, options = {}) {
  const text = {
    text: Array.isArray(value) ? value : display(value),
    width,
    margin: [0, 0, 0, 0],
    fontSize: options.fontSize ?? 7.15,
    bold: options.bold ?? false,
    alignment: options.alignment ?? "left",
    lineHeight: options.lineHeight ?? 1,
    color: options.color ?? FORM_INK,
  };

  if (options.noWrap) text.noWrap = true;
  if (options.characterSpacing !== undefined) text.characterSpacing = options.characterSpacing;

  return {
    columns: [text],
    absolutePosition: { x, y: sourceTop - TEXT_TOP_CORRECTION },
    columnGap: 0,
  };
}

function centeredText(value, x, sourceTop, width, options = {}) {
  return absoluteText(value, x, sourceTop, width, { ...options, alignment: "center" });
}

function fieldValue(value, x, sourceTop, width, options = {}) {
  return centeredText(value, x, sourceTop, width, {
    ...options,
    fontSize: options.fontSize ?? fittedFontSize(value, width),
  });
}

function line(x1, y1, x2, y2 = y1, lineWidth = 0.72) {
  return { type: "line", x1, y1, x2, y2, lineWidth, lineColor: FORM_LINE };
}

function rectangle(x, y, w, h, lineWidth = 0.72) {
  return { type: "rect", x, y, w, h, lineWidth, lineColor: FORM_LINE };
}

export function createSchoolInsuranceCertificateForm(attention, defaults = {}) {
  const student = attention?.student || {};
  const names = splitStudentName(student, attention?.student_full_name_snapshot);
  const accidentLocation = attention?.accident_location_type;
  const attentionCorrelative = attention?.correlative_number || attention?.id || null;

  return {
    attention_id: attention?.id || null,
    attention_correlative_number: attentionCorrelative,
    certificate_number: attentionCorrelative ? String(attentionCorrelative).padStart(5, "0") : "",
    registration_date: dateInput(attention?.attended_at || new Date()),
    institution_type: String(defaults.institution_type || 3),
    rbd: defaults.rbd || "",
    establishment_name: defaults.establishment_name || "",
    establishment_commune: defaults.commune || "",
    schedule: defaults.schedule || "",
    course: attention?.course_name_snapshot || attention?.course_section?.display_name || "",
    paternal_surname: names.paternalSurname,
    maternal_surname: names.maternalSurname,
    given_names: names.names,
    rut: student.rut || attention?.student_rut_snapshot || "",
    sex: sexCode(student.sex || student.gender),
    birth_date: dateInput(student.birthdate),
    address: student.address || "",
    neighborhood: "",
    residence_commune: defaults.commune || "",
    city: defaults.city || defaults.commune || "",
    commune_code: defaults.commune_code || "",
    occurred_at: dateTimeInput(attention?.occurred_at || attention?.attended_at),
    accident_type: accidentLocation === "trayecto" ? "1" : "2",
    witnesses: "",
    circumstance: attention?.accident_circumstance || attention?.consultation_reason || "",
  };
}

export function schoolInsuranceCertificateFileName(form) {
  const student = [form.given_names, form.paternal_surname, form.maternal_surname]
    .filter(Boolean)
    .join(" ")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^\w-]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .toLowerCase() || "estudiante";

  return `certificado_seguro_escolar_${clean(form.certificate_number) || "sin-numero"}_${student}.pdf`;
}

export function buildSchoolInsuranceCertificateDefinition(form, logoDataUrl = null) {
  const registration = dateParts(form.registration_date);
  const accident = dateParts(form.occurred_at);
  const birth = dateParts(form.birth_date);
  const age = ageAt(form.birth_date, form.occurred_at);
  const shapes = [
    rectangle(452.35, 91.04, 14.01, 22.85),
    rectangle(498.07, 99.15, 16.96, 11.06),
    rectangle(515.77, 99.15, 17.7, 11.06),
    rectangle(534.2, 99.15, 36.13, 11.06),

    line(53.8, 141.18, 116.48),
    line(134.17, 141.18, 330.32),
    line(348.01, 141.18, 410.69),
    line(428.39, 141.18, 490.33),
    line(508.03, 141.18, 570.7),

    line(53.8, 172.89, 114.26),
    line(131.96, 172.89, 191.69),
    line(209.39, 172.89, 269.85),
    line(287.55, 172.89, 348.01),
    rectangle(397.79, 167.72, 22.86, 11.06),
    rectangle(444.98, 167.72, 46.45, 11.06),
    rectangle(531.25, 167.72, 22.86, 11.06),

    line(53.8, 241.46, 214.55),
    line(232.24, 241.46, 303.03),
    line(320.73, 241.46, 392.26),
    line(409.95, 241.46, 481.48),
    line(499.18, 241.46, 570.7),

    rectangle(63.02, 279.81, 22.85, 11.06),
    rectangle(86.61, 279.81, 22.86, 11.06),
    rectangle(168.46, 279.81, 46.46, 11.06),
    rectangle(233.35, 279.81, 22.86, 11.06),
    rectangle(274.64, 279.81, 22.86, 11.06),
    rectangle(315.94, 279.81, 242.6, 11.06),
    rectangle(315.94, 291.6, 242.6, 11.06),
    rectangle(132.33, 305.61, 22.86, 11.06),
    rectangle(267.27, 305.61, 22.86, 11.06),
    rectangle(54.17, 371.24, 382.7, 34.66),
    line(454.93, 397.05, 570.7),

    rectangle(48.27, 441.29, 516.17, 278),
    line(72.23, 467.1, 386.36),
    line(404.05, 467.1, 552.27),
    line(72.23, 496.6, 552.27),
    line(72.23, 526.09, 552.27),
    rectangle(72.6, 575.5, 103.23, 11.06),
    rectangle(194.27, 575.5, 103.23, 11.06),
    rectangle(315.94, 575.5, 102.49, 11.06),
    rectangle(436.87, 575.5, 103.23, 11.06),
    rectangle(166.99, 627.11, 16.96, 11.06),
    rectangle(288.65, 635.22, 16.96, 11.06),
    rectangle(315.94, 609.42, 38.34, 11.06),
    rectangle(355.02, 609.42, 31.71, 11.06),
    rectangle(387.46, 609.42, 30.97, 11.06),
    line(436.5, 692, 540.47),
  ];
  const content = [{ canvas: shapes, absolutePosition: { x: 0, y: 0 } }];

  if (logoDataUrl) {
    content.push({
      image: logoDataUrl,
      absolutePosition: { x: 42, y: 42 },
      width: 36.87,
      height: 35.39,
      fit: [36.87, 35.39],
    });
  }

  content.push(
    centeredText("DECLARACIÓN INDIVIDUAL DE ACCIDENTE ESCOLAR", 150, 51.87, 350, {
      fontSize: 12,
      bold: true,
      characterSpacing: 0.5,
      color: "#333333",
    }),
    absoluteText(`Nº ${display(form.certificate_number)}`, 500, 51.87, 62, {
      fontSize: 10.8,
      bold: true,
      alignment: "right",
      color: "#3d3d3d",
    }),

    absoluteText("A. INDIVIDUALIZACION DEL ESTABLECIMIENTO", 42, 91.49, 215, {
      fontSize: 7.5,
      bold: true,
    }),
    absoluteText("Fiscal o Municipal = 1", 344, 91.49, 96, { alignment: "right" }),
    absoluteText("Particular = 2", 344, 99.6, 96, { alignment: "right" }),
    absoluteText("Particular subvencionado = 3", 340, 107.71, 100, { alignment: "right" }),
    fieldValue(form.institution_type, 452.35, 92.54, 14.01, { fontSize: 8.3 }),
    centeredText("FECHA REGISTRO", 494, 91.49, 77, { fontSize: 7.05, bold: true }),
    fieldValue(registration.day, 498.07, 100.65, 16.96, { fontSize: 8 }),
    fieldValue(registration.month, 515.77, 100.65, 17.7, { fontSize: 8 }),
    fieldValue(registration.year, 534.2, 100.65, 36.13, { fontSize: 8 }),
    centeredText("DÍA", 498.07, 111.4, 16.96, { fontSize: 7, bold: true }),
    centeredText("MES", 515.77, 111.4, 17.7, { fontSize: 7, bold: true }),
    centeredText("AÑO", 534.2, 111.4, 36.13, { fontSize: 7, bold: true }),

    fieldValue(form.rbd, 53.8, 128.36, 62.68),
    fieldValue(form.establishment_name, 134.17, 128.36, 196.15),
    fieldValue(form.establishment_commune, 348.01, 128.36, 62.68),
    fieldValue(form.schedule, 428.39, 128.36, 61.94),
    fieldValue(form.course, 508.03, 128.36, 62.67),
    centeredText("RBD", 53.8, 143.11, 62.68, { fontSize: 7.05, bold: true }),
    centeredText("NOMBRE DEL ESTABLECIMIENTO", 134.17, 143.11, 196.15, { fontSize: 7.05, bold: true }),
    centeredText("COMUNA", 348.01, 143.11, 62.68, { fontSize: 7.05, bold: true }),
    centeredText("HORARIO", 428.39, 143.11, 61.94, { fontSize: 7.05, bold: true }),
    centeredText("CURSO", 508.03, 143.11, 62.67, { fontSize: 7.05, bold: true }),

    fieldValue(form.paternal_surname, 53.8, 160.07, 60.46),
    fieldValue(form.maternal_surname, 131.96, 160.07, 59.73),
    fieldValue(form.given_names, 209.39, 160.07, 60.46),
    fieldValue(form.rut, 287.55, 160.07, 60.46),
    centeredText("APELLIDO\nPATERNO", 53.8, 174.82, 60.46, { fontSize: 7.05, bold: true, lineHeight: 0.96 }),
    centeredText("APELLIDO\nMATERNO", 131.96, 174.82, 59.73, { fontSize: 7.05, bold: true, lineHeight: 0.96 }),
    centeredText("NOMBRES", 209.39, 174.82, 60.46, { fontSize: 7.05, bold: true }),
    centeredText("RUT", 287.55, 174.82, 60.46, { fontSize: 7.05, bold: true }),
    absoluteText("SEXO", 365.66, 160.07, 31, { fontSize: 7.05, bold: true }),
    absoluteText("F     = 1", 365.66, 168.18, 31, { fontSize: 7.05 }),
    absoluteText("M    = 2", 365.66, 176.29, 31, { fontSize: 7.05 }),
    fieldValue(form.sex, 397.79, 169.23, 22.86, { fontSize: 8 }),
    centeredText("AÑO NAC.", 444.98, 160.07, 46.45, { fontSize: 7.05, bold: true }),
    fieldValue(birth.year, 444.98, 169.23, 46.45, { fontSize: 8 }),
    centeredText("EDAD", 531.25, 160.07, 22.86, { fontSize: 7.05, bold: true }),
    fieldValue(age, 531.25, 169.23, 22.86, { fontSize: 8 }),

    absoluteText("B. RESIDENCIA HABITUAL", 42, 211.69, 160, { fontSize: 7.5, bold: true }),
    fieldValue(form.address, 53.8, 228.65, 160.75),
    fieldValue(form.neighborhood, 232.24, 228.65, 70.79),
    fieldValue(form.residence_commune, 320.73, 228.65, 71.53),
    fieldValue(form.city, 409.95, 228.65, 71.53),
    fieldValue(form.commune_code, 499.18, 228.65, 71.52),
    centeredText("DIRECCIÓN", 53.8, 244.13, 160.75, { fontSize: 7.05, bold: true }),
    centeredText("POBLACIÓN/VILLA", 232.24, 244.13, 70.79, { fontSize: 7.05, bold: true }),
    centeredText("COMUNA", 320.73, 244.13, 71.53, { fontSize: 7.05, bold: true }),
    centeredText("CIUDAD", 409.95, 244.13, 71.53, { fontSize: 7.05, bold: true }),
    centeredText("CODIF. COMUNA", 499.18, 244.13, 71.52, { fontSize: 7.05, bold: true }),

    absoluteText([
      { text: "C. INFORME SOBRE EL ACCIDENTE ", fontSize: 7.5, bold: true },
      { text: "(FECHA, HORA Y DIA DE LA SEMANA EN QUE SE ACCIDENTÓ)", fontSize: 7.15 },
    ], 42, 255.19, 350),
    centeredText("HORA", 63.02, 272.15, 22.85, { fontSize: 7.05, bold: true }),
    centeredText("MINUTO", 82.5, 272.15, 31, { fontSize: 7.05, bold: true }),
    centeredText("AÑO", 168.46, 272.15, 46.46, { fontSize: 7.05, bold: true }),
    centeredText("MES", 233.35, 272.15, 22.86, { fontSize: 7.05, bold: true }),
    centeredText("DÍA", 274.64, 272.15, 22.86, { fontSize: 7.05, bold: true }),
    fieldValue(accident.hour, 63.02, 281.31, 22.85, { fontSize: 8 }),
    fieldValue(accident.minute, 86.61, 281.31, 22.86, { fontSize: 8 }),
    fieldValue(accident.year, 168.46, 281.31, 46.46, { fontSize: 8 }),
    fieldValue(accident.month, 233.35, 281.31, 22.86, { fontSize: 8 }),
    fieldValue(accident.day, 274.64, 281.31, 22.86, { fontSize: 8 }),
    absoluteText([
      { text: "TESTIGOS: EN CASO DE TRAYECTO ", bold: true },
      { text: "(NOMBRES, APELLIDOS Y RUN)" },
    ], 315.2, 272.15, 244, { fontSize: 7.05 }),
    absoluteText(form.witnesses, 319, 281.31, 236, { fontSize: 7, lineHeight: 1.05 }),

    absoluteText("DÍA ACCIDENTE", 53.8, 297.96, 100, { fontSize: 7.05, bold: true }),
    absoluteText("LUNES          = 1", 53.8, 306.07, 65, { fontSize: 7.05 }),
    absoluteText("MARTES       = 2", 53.8, 314.18, 65, { fontSize: 7.05 }),
    absoluteText("MIÉRCOLES  = 3", 53.8, 322.29, 65, { fontSize: 7.05 }),
    absoluteText("JUEVES        = 4", 53.8, 330.4, 65, { fontSize: 7.05 }),
    absoluteText("VIERNES      = 5", 53.8, 338.52, 65, { fontSize: 7.05 }),
    absoluteText("SABADO       = 6", 53.8, 346.63, 65, { fontSize: 7.05 }),
    fieldValue(accidentDayCode(form.occurred_at), 132.33, 307.12, 22.86, { fontSize: 8 }),
    absoluteText("TIPO ACCIDENTE", 184.5, 297.96, 100, { fontSize: 7.05, bold: true }),
    absoluteText("EN TRAYECTO     = 1", 184.5, 306.07, 72, { fontSize: 7.05 }),
    absoluteText("EN ESCUELA       = 2", 184.5, 314.18, 72, { fontSize: 7.05 }),
    fieldValue(form.accident_type, 267.27, 307.12, 22.86, { fontSize: 8 }),
    absoluteText([
      { text: "CIRCUNSTANCIA DEL ACCIDENTE", bold: true },
      { text: "(DESCRIBA COMO OCURRIÓ)" },
    ], 53.8, 363.59, 260, { fontSize: 7.05 }),
    absoluteText(form.circumstance, 60, 372.75, 371, { fontSize: 7.6, lineHeight: 1.05 }),
    centeredText("RECTOR O REPRESENTANTE", 454.93, 398.98, 115.77, { fontSize: 7.05, bold: true }),

    absoluteText("D. NATURALEZA Y CONSECUENCIA DEL ACCIDENTE", 42, 427.74, 190, { fontSize: 7.5, bold: true }),
    absoluteText("(SÓLO ESTABLECIMIENTO ASISTENCIAL)", 244.58, 427.74, 150, { fontSize: 7.15 }),
    centeredText("ESTABLECIMIENTO ASISTENCIAL", 72.23, 469.77, 314.13, { fontSize: 7.05, bold: true }),
    centeredText("CÓDIGO DEL SERVICIO DE SALUD", 404.05, 469.77, 148.22, { fontSize: 7.05, bold: true }),
    centeredText("DIAGNÓSTICO MÉDICO", 72.23, 499.26, 480.04, { fontSize: 7.05, bold: true }),
    centeredText("PARTE DEL CUERPO AFECTADA", 72.23, 528.76, 480.04, { fontSize: 7.05, bold: true }),

    absoluteText("HOSPITALIZACIÓN", 72.23, 554.57, 103.23, { fontSize: 7.05, bold: true }),
    absoluteText("(SÍ = 1, NO = 2)", 72.23, 565.94, 103.23, { fontSize: 7.05 }),
    absoluteText("TOTAL DIAS HOSP", 193.72, 554.57, 103.78, { fontSize: 7.05, bold: true }),
    absoluteText("(Número)", 193.72, 565.94, 103.78, { fontSize: 7.05 }),
    absoluteText("INCAPACIDAD", 315.2, 554.57, 103.23, { fontSize: 7.05, bold: true }),
    absoluteText("(SÍ = 1, NO = 2)", 315.2, 565.94, 103.23, { fontSize: 7.05 }),
    absoluteText("TOTAL DIAS INCAPACIDAD", 436.68, 554.57, 103.42, { fontSize: 7.05, bold: true }),
    absoluteText("(Número)", 436.68, 565.94, 103.42, { fontSize: 7.05 }),

    absoluteText("TIPO DE INCAPACIDAD", 72.23, 601.76, 100, { fontSize: 7.05, bold: true }),
    absoluteText("LEVE", 72.23, 621.67, 90, { fontSize: 7.05 }),
    absoluteText("= 1", 151.2, 621.67, 18, { fontSize: 7.05 }),
    absoluteText("TEMPORAL", 72.23, 635.68, 90, { fontSize: 7.05 }),
    absoluteText("= 2", 151.2, 635.68, 18, { fontSize: 7.05 }),
    absoluteText("INVALIDEZ\nPARCIAL", 72.23, 649.69, 90, { fontSize: 7.05, lineHeight: 0.96 }),
    absoluteText("= 3", 151.2, 649.69, 18, { fontSize: 7.05 }),
    absoluteText("INVALIDEZ TOTAL", 72.23, 671.81, 78, { fontSize: 7.05 }),
    absoluteText("= 4", 151.2, 671.81, 18, { fontSize: 7.05 }),
    absoluteText("GRAN INVALIDEZ", 72.23, 685.82, 78, { fontSize: 7.05 }),
    absoluteText("= 5", 151.2, 685.82, 18, { fontSize: 7.05 }),
    absoluteText("MUERTE", 72.23, 699.83, 78, { fontSize: 7.05 }),
    absoluteText("= 6", 151.2, 699.83, 18, { fontSize: 7.05 }),

    absoluteText("CAUSA DE CIERRE DEL\nCASO", 193.72, 601.76, 90, { fontSize: 7.05, bold: true, lineHeight: 0.96 }),
    absoluteText("ALTA MÉDICA", 193.72, 629.78, 78, { fontSize: 7.05 }),
    absoluteText("= 1", 272.69, 629.78, 18, { fontSize: 7.05 }),
    absoluteText("INVALIDEZ", 193.72, 643.79, 78, { fontSize: 7.05 }),
    absoluteText("= 2", 272.69, 643.79, 18, { fontSize: 7.05 }),
    absoluteText("ABANDONO DE\nTRATAMIENTO", 193.72, 657.8, 78, { fontSize: 7.05, lineHeight: 0.96 }),
    absoluteText("= 3", 272.69, 657.8, 18, { fontSize: 7.05 }),
    absoluteText("MUERTE", 193.72, 679.92, 78, { fontSize: 7.05 }),
    absoluteText("= 4", 272.69, 679.92, 18, { fontSize: 7.05 }),

    centeredText("AÑO", 315.94, 601.76, 38.34, { fontSize: 7.05, bold: true }),
    centeredText("MES", 355.02, 601.76, 31.71, { fontSize: 7.05, bold: true }),
    centeredText("DÍA", 387.46, 601.76, 30.97, { fontSize: 7.05, bold: true }),
    centeredText("FIRMA DEL ESTADÍSTICO", 436.5, 693.93, 103.97, { fontSize: 7.05, bold: true })
  );

  return {
    pageSize: "LETTER",
    pageMargins: [0, 0, 0, 0],
    content,
    defaultStyle: {
      fontSize: 7.15,
      color: FORM_INK,
    },
  };
}
