import { createRequire } from "node:module";
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { resolve } from "node:path";
import { createServer } from "vite";

const require = createRequire(import.meta.url);
const pdfMake = require("pdfmake/build/pdfmake");
const fonts = require("pdfmake/build/vfs_fonts");
pdfMake.vfs = fonts?.pdfMake?.vfs || fonts?.vfs || fonts;

const root = process.cwd();
const imageData = (file) => `data:image/png;base64,${readFileSync(resolve(root, file)).toString("base64")}`;
const vite = await createServer({ root, logLevel: "error", server: { middlewareMode: true }, appType: "custom" });

try {
  const { buildSupplyRequestQuotePdfDefinition } = await vite.ssrLoadModule("/resources/js/utils/supply-request-quote-pdf.js");
  const request = {
    id: 1,
    folio: "SOL-ABA-2026-000001",
    title: "Reposición mensual de insumos de aseo",
    destination: "Bodega central y equipo de auxiliares",
    needed_by: "2026-09-15",
    status: "quoted",
    status_label: "Cotización emitida",
    notes: "Solicitud preparada a partir del inventario institucional.",
    review_notes: "Cotizar productos equivalentes de uso institucional. Indicar plazo de entrega y vigencia de la oferta.",
    creator: { name: "Responsable de Abastecimiento" },
    reviewer: { name: "Super Admin" },
    items: [
      { id: 1, item_name_snapshot: "Desinfectante concentrado", description_snapshot: "Bidón de 5 litros, uso institucional", requested_quantity: 8, final_quantity: 12, unit_snapshot: "bidon" },
      { id: 2, item_name_snapshot: "Papel higiénico industrial", description_snapshot: "Rollo de alto metraje", requested_quantity: 36, final_quantity: 48, unit_snapshot: "rollo" },
      { id: 3, item_name_snapshot: "Guantes de nitrilo", description_snapshot: "Caja de 100 unidades, talla M", requested_quantity: 4, final_quantity: 6, unit_snapshot: "caja" },
      { id: 4, item_name_snapshot: "Mopa húmeda reforzada", description_snapshot: "Cabezal lavable con mango metálico", requested_quantity: 6, final_quantity: 6, unit_snapshot: "unidad" },
    ],
  };
  const images = {
    1: imageData("resources/images/product/img-4.png"),
    2: imageData("resources/images/product/img-6.png"),
    3: imageData("resources/images/product/img-7.png"),
  };
  const definition = buildSupplyRequestQuotePdfDefinition(request, images, "2026-09-01T11:45:00-04:00");
  const outputDirectory = resolve(root, "output/pdf");
  const outputPath = resolve(outputDirectory, "solicitud-cotizacion-abastecimiento-demo.pdf");
  mkdirSync(outputDirectory, { recursive: true });

  await new Promise((resolveBuffer, rejectBuffer) => {
    try {
      pdfMake.createPdf(definition).getBuffer((buffer) => {
        writeFileSync(outputPath, buffer);
        resolveBuffer();
      });
    } catch (error) {
      rejectBuffer(error);
    }
  });

  process.stdout.write(`${outputPath}\n`);
} finally {
  await vite.close();
}
