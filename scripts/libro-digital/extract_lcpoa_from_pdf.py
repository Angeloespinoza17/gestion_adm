#!/usr/bin/env python3
"""Extrae los OA de LCPOA 1B-6B desde la base oficial DS 97/2021.

El documento publica tres variantes sociolingüísticas en el primer eje y
objetivos comunes en los otros tres ejes. No inventa códigos oficiales: crea
solo ``reference_code`` técnicos y conserva el localizador PDF.
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

import pdfplumber


GRADES = {
    "1B": {"start": 28, "continuation": 29, "shared": [29, 30]},
    "2B": {"start": 31, "continuation": 32, "shared": [32]},
    "3B": {"start": 33, "continuation": 34, "shared": [34, 35]},
    "4B": {"start": 36, "continuation": 37, "shared": [37, 38]},
    "5B": {"start": 39, "continuation": 40, "shared": [40, 41]},
    "6B": {"start": 42, "continuation": 43, "shared": [44]},
}

CONTEXTS = [
    ("SENS", "Sensibilización sobre la lengua"),
    ("RESC", "Rescate y revitalización de la lengua"),
    ("FORT", "Fortalecimiento y desarrollo de la lengua"),
]

AXES = {
    "TERRITORIO, TERRITORIALIDAD, IDENTIDAD Y MEMORIA HISTÓRICA DE LOS PUEBLOS ORIGINARIOS": "TERR",
    "COSMOVISIÓN DE LOS PUEBLOS ORIGINARIOS": "COSM",
    "PATRIMONIO, TECNOLOGÍAS, TÉCNICAS, CIENCIAS Y ARTES ANCESTRALES DE LOS PUEBLOS ORIGINARIOS": "PATR",
}


def clean(value: str) -> str:
    value = value.replace("\u00ad", "")
    value = re.sub(r"\s+", " ", value)
    return value.strip()


def bullets(value: str) -> list[str]:
    return [clean(part) for part in re.split(r"\s*•\s*", value) if clean(part)]


def table_columns(page) -> list[str]:
    tables = page.extract_tables()
    candidates: list[str] = []
    for table in tables:
        for row in table:
            for cell in row:
                text = clean(cell or "")
                if "•" in text:
                    candidates.append(cell or "")
    if len(candidates) < 3:
        raise RuntimeError(f"No se hallaron tres columnas de OA en la página {page.page_number}")
    return candidates[:3]


def continuation_columns(page) -> list[str]:
    tables = page.extract_tables()
    for table in tables:
        if len(table) == 1 and len(table[0]) >= 3:
            return [(cell or "") for cell in table[0][:3]]
    return ["", "", ""]


def shared_axis_segments(text: str) -> list[tuple[str, str]]:
    parts = re.split(r"(?=EJE:\s*)", text)
    result: list[tuple[str, str]] = []
    for part in parts:
        if not part.startswith("EJE:") or "•" not in part:
            continue
        header, body = part.split("•", 1)
        axis = clean(header.removeprefix("EJE:"))
        body = "•" + body
        body = re.split(
            r"Unidad de Currículum y Evaluación, Ministerio de Educación\s+\d+\s+Enero 2021",
            body,
            maxsplit=1,
        )[0]
        if axis in AXES:
            result.append((axis, body))
    return result


def main() -> int:
    if len(sys.argv) != 3:
        print(f"Uso: {sys.argv[0]} <Bases_LCPOA_DS97.pdf> <salida.json>", file=sys.stderr)
        return 64

    source = Path(sys.argv[1]).resolve()
    output = Path(sys.argv[2]).resolve()
    rows: list[dict[str, object]] = []

    with pdfplumber.open(source) as pdf:
        for grade, spec in GRADES.items():
            start_page = pdf.pages[spec["start"] - 1]
            continuation_page = pdf.pages[spec["continuation"] - 1]
            starts = table_columns(start_page)
            continuations = continuation_columns(continuation_page)
            for (context_code, context_name), start, continuation in zip(
                CONTEXTS, starts, continuations, strict=True
            ):
                items = bullets(start + "\n" + continuation)
                for index, description in enumerate(items, start=1):
                    rows.append(
                        {
                            "reference_code": f"REF-LCPOA-{grade}-LENG-{context_code}-{index:02d}",
                            "official_code": "",
                            "objective_type": "OA",
                            "subject_code": "LCPOA",
                            "subject_name": "Lengua y Cultura de los Pueblos Originarios Ancestrales",
                            "level_code": "BASICA",
                            "grade_code": grade,
                            "curriculum_track": "GENERAL",
                            "axis_code": f"LENG_{context_code}",
                            "axis_name": "Lengua, tradición oral, iconografía, prácticas de lectura y escritura de los pueblos originarios",
                            "sociolinguistic_context": context_name,
                            "description": description,
                            "source_locator": f"PDF páginas {spec['start']}-{spec['continuation']}",
                            "normative_status": "VIGENTE_TEXTO_OFICIAL_CODIGO_TECNICO",
                            "import_supported": False,
                        }
                    )

            shared: dict[str, list[tuple[str, int]]] = {axis: [] for axis in AXES}
            for page_number in spec["shared"]:
                page_text = pdf.pages[page_number - 1].extract_text() or ""
                for axis, body in shared_axis_segments(page_text):
                    shared[axis].extend((item, page_number) for item in bullets(body))
            for axis, items in shared.items():
                axis_code = AXES[axis]
                for index, (description, page_number) in enumerate(items, start=1):
                    rows.append(
                        {
                            "reference_code": f"REF-LCPOA-{grade}-{axis_code}-{index:02d}",
                            "official_code": "",
                            "objective_type": "OA",
                            "subject_code": "LCPOA",
                            "subject_name": "Lengua y Cultura de los Pueblos Originarios Ancestrales",
                            "level_code": "BASICA",
                            "grade_code": grade,
                            "curriculum_track": "GENERAL",
                            "axis_code": axis_code,
                            "axis_name": axis.title(),
                            "sociolinguistic_context": "Común a los tres contextos",
                            "description": description,
                            "source_locator": f"PDF página {page_number}",
                            "normative_status": "VIGENTE_TEXTO_OFICIAL_CODIGO_TECNICO",
                            "import_supported": False,
                        }
                    )

    payload = {
        "schema": "cnsc-lcpoa-pdf-extract/v1",
        "notice": "Los códigos REF-* son referencias técnicas; el PDF no imprime códigos oficiales por objetivo.",
        "source_path": str(source),
        "rows": rows,
        "counts": {
            "total": len(rows),
            "by_grade": {
                grade: sum(1 for row in rows if row["grade_code"] == grade)
                for grade in GRADES
            },
        },
    }
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(payload["counts"], ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
