import pdfMake from "pdfmake/build/pdfmake";
import * as pdfFonts from "pdfmake/build/vfs_fonts";

let initialized = false;

export function getPdfMake() {
  if (!initialized) {
    const vfs = pdfFonts?.pdfMake?.vfs || pdfFonts?.default?.pdfMake?.vfs;
    if (vfs) {
      pdfMake.vfs = vfs;
    }
    initialized = true;
  }
  return pdfMake;
}

