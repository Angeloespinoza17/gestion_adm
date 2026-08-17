#!/usr/bin/env python3
"""Extrae los Objetivos Fundamentales Terminales de formación artística.

El DS 3/2007 los publica como perfiles de egreso comunes y por mención para
el ciclo 3M-4M, sin códigos impresos. Se crean únicamente ``reference_code``
técnicos para poder auditarlos sin presentarlos como códigos ministeriales.
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

import pdfplumber


SEGMENTS = [
    ("MUS-COMUN", "Artes Musicales", "Común del área", 62, 62, None, None),
    ("MUS-INTERP", "Artes Musicales", "Interpretación Musical", 63, 63, "Mención Interpretación Musical", "Mención Composición Musical"),
    ("MUS-COMP", "Artes Musicales", "Composición Musical", 63, 63, "Mención Composición Musical", None),
    ("MUS-APREC", "Artes Musicales", "Apreciación Musical", 64, 64, "Mención Apreciación Musical", None),
    ("VIS-COMUN", "Artes Visuales", "Común del área", 66, 67, None, "Mención Artes Visuales"),
    ("VIS-ART", "Artes Visuales", "Artes Visuales", 67, 67, "Mención Artes Visuales", "Mención Artes Audiovisuales"),
    ("VIS-AUDIO", "Artes Visuales", "Artes Audiovisuales", 67, 68, "Mención Artes Audiovisuales", "Mención Diseño"),
    ("VIS-DIS", "Artes Visuales", "Diseño", 68, 68, "Mención Diseño", None),
    ("TEA-COMUN", "Artes Escénicas: Teatro", "Común de la sub-área", 70, 71, None, "Mención Interpretación Teatral"),
    ("TEA-INTERP", "Artes Escénicas: Teatro", "Interpretación Teatral", 71, 71, "Mención Interpretación Teatral", None),
    ("TEA-DIS", "Artes Escénicas: Teatro", "Diseño Escénico", 72, 72, "Mención Diseño Escénico", None),
    ("DAN-COMUN", "Artes Escénicas: Danza", "Común de la sub-área", 74, 74, None, None),
    ("DAN-INTERP", "Artes Escénicas: Danza", "Interpretación en Danza de Nivel Intermedio", 75, 75, "Mención Interpretación en Danza de Nivel Intermedio", "Mención Monitoría en Danza"),
    ("DAN-MON", "Artes Escénicas: Danza", "Monitoría en Danza", 75, 76, "Mención Monitoría en Danza", None),
]


def clean(value: str) -> str:
    value = value.replace("\u00ad", "")
    value = re.sub(r"([A-Za-zÁÉÍÓÚÜÑáéíóúüñ])-\s*\n\s*([A-Za-zÁÉÍÓÚÜÑáéíóúüñ])", r"\1\2", value)
    value = re.sub(r"\s+", " ", value)
    value = re.sub(r"\s+\d{2}\s+en la Educación Media\s*$", "", value)
    return value.strip()


def page_body(value: str) -> str:
    """Retira encabezados repetidos sin tocar títulos de mención."""
    noise = re.compile(
        r"^\s*(?:\d{2}|Ministerio de Educación|Objetivos Fundamentales Terminales|"
        r"para la Formación Diferenciada Artística|en la Educación Media|Artes|"
        r"Musicales|Visuales|Artes Escénicas:|Teatro|Danza)\s*$"
    )
    return "\n".join(line for line in value.splitlines() if not noise.match(line))


def slice_segment(text: str, start: str | None, end: str | None) -> str:
    if start:
        position = text.find(start)
        if position < 0:
            raise RuntimeError(f"No se encontró el inicio: {start}")
        text = text[position + len(start) :]
    if end:
        position = text.find(end)
        if position < 0:
            raise RuntimeError(f"No se encontró el término: {end}")
        text = text[:position]
    return text


def objectives(text: str) -> list[tuple[int, str]]:
    text = re.sub(r"(?m)^\s*En el dominio de [^:]+:\s*$", "", text)
    text = re.sub(r"(?m)^\s*(Ministerio de Educación|Objetivos Fundamentales Terminales|para la Formación Diferenciada Artística|en la Educación Media)\s*$", "", text)
    matches = list(re.finditer(r"(?m)^\s*(\d+)\.\s+", text))
    rows: list[tuple[int, str]] = []
    for index, match in enumerate(matches):
        end = matches[index + 1].start() if index + 1 < len(matches) else len(text)
        description = clean(text[match.end() : end])
        description = re.sub(r"\s+(Artes (Musicales|Visuales|Escénicas:)|Teatro|Danza)\s*$", "", description)
        if description:
            rows.append((int(match.group(1)), description))
    return rows


def main() -> int:
    if len(sys.argv) != 3:
        print(f"Uso: {sys.argv[0]} <OF_Terminales_Artistica_DS3.pdf> <salida.json>", file=sys.stderr)
        return 64

    source = Path(sys.argv[1]).resolve()
    output = Path(sys.argv[2]).resolve()
    rows: list[dict[str, object]] = []

    with pdfplumber.open(source) as pdf:
        page_text = {
            number: page_body(pdf.pages[number - 1].extract_text(layout=True) or "")
            for number in range(62, 77)
        }
        for code, area, mention, first, last, start, end in SEGMENTS:
            combined = "\n".join(page_text[number] for number in range(first, last + 1))
            segment = slice_segment(combined, start, end)
            extracted = objectives(segment)
            if not extracted:
                raise RuntimeError(f"No se extrajeron objetivos para {code}")
            for number, description in extracted:
                for grade in ("3M", "4M"):
                    rows.append(
                        {
                            "reference_code": f"REF-ART-{code}-{number:02d}",
                            "official_code": "",
                            "objective_type": "OFT",
                            "subject_code": f"ART_{code.replace('-', '_')}",
                            "subject_name": f"{area} — {mention}",
                            "level_code": "MEDIA",
                            "grade_code": grade,
                            "official_grade_scope": "3M-4M",
                            "technical_projection": True,
                            "curriculum_track": "ARTISTICA",
                            "axis_code": code,
                            "axis_name": mention,
                            "description": description,
                            "source_locator": f"PDF páginas {first}-{last}" if first != last else f"PDF página {first}",
                            "normative_status": "VIGENTE_TEXTO_OFICIAL_CODIGO_TECNICO",
                            "import_supported": False,
                        }
                    )

    payload = {
        "schema": "cnsc-artistica-pdf-extract/v1",
        "notice": "Los códigos REF-* son referencias técnicas; el DS 3/2007 no imprime códigos por OFT.",
        "source_path": str(source),
        "rows": rows,
        "counts": {
            "official_objectives": len(rows) // 2,
            "technical_grade_rows": len(rows),
            "by_segment": {
                code: len({row["reference_code"] for row in rows if row["axis_code"] == code})
                for code, *_ in SEGMENTS
            },
        },
    }
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(payload["counts"], ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
