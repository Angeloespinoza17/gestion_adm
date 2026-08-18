const SVG_NAMESPACE = "http://www.w3.org/2000/svg";

const loadImage = (source) =>
    new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            if (
                Number(image.naturalWidth || image.width || 0) < 1 ||
                Number(image.naturalHeight || image.height || 0) < 1
            ) {
                reject(
                    new Error(
                        "La imagen del gráfico no tiene dimensiones válidas. Ajusta la vista y vuelve a exportar."
                    )
                );
                return;
            }
            resolve(image);
        };
        image.onerror = reject;
        image.src = source;
    });

export const serializeSvg = (svg) => {
    if (typeof SVGElement === "undefined" || !(svg instanceof SVGElement))
        return null;
    const clone = svg.cloneNode(true);
    clone.setAttribute("xmlns", SVG_NAMESPACE);
    return new XMLSerializer().serializeToString(clone);
};

export const svgStringToDataUrl = (svg) =>
    `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;

export const svgElementToPngDataUrl = async (
    svg,
    { width = 1400, height = 800, background = "#ffffff" } = {}
) => {
    const source = serializeSvg(svg);
    if (!source) return null;
    const image = await loadImage(svgStringToDataUrl(source));
    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext("2d");
    context.fillStyle = background;
    context.fillRect(0, 0, width, height);
    const viewBox = svg.viewBox?.baseVal;
    const sourceWidth = viewBox?.width || svg.clientWidth || image.naturalWidth;
    const sourceHeight =
        viewBox?.height || svg.clientHeight || image.naturalHeight;
    const imageRect = calculateContainRect(
        sourceWidth,
        sourceHeight,
        0,
        0,
        width,
        height
    );
    context.drawImage(
        image,
        imageRect.x,
        imageRect.y,
        imageRect.width,
        imageRect.height
    );
    return canvas.toDataURL("image/png");
};

const wrapText = (context, value, maximumWidth) => {
    const words = String(value || "")
        .split(/\s+/)
        .filter(Boolean);
    const lines = [];
    let current = "";
    words.forEach((word) => {
        const candidate = current ? `${current} ${word}` : word;
        if (current && context.measureText(candidate).width > maximumWidth) {
            lines.push(current);
            current = word;
        } else {
            current = candidate;
        }
    });
    if (current) lines.push(current);
    return lines;
};

const downloadDataUrl = (dataUrl, filename) => {
    const anchor = document.createElement("a");
    anchor.href = dataUrl;
    anchor.download = filename;
    anchor.rel = "noopener";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
};

export const calculateContainRect = (
    sourceWidth,
    sourceHeight,
    targetX,
    targetY,
    targetWidth,
    targetHeight
) => {
    const safeWidth = Math.max(1, Number(sourceWidth) || 1);
    const safeHeight = Math.max(1, Number(sourceHeight) || 1);
    const scale = Math.min(targetWidth / safeWidth, targetHeight / safeHeight);
    const width = safeWidth * scale;
    const height = safeHeight * scale;
    return {
        x: targetX + (targetWidth - width) / 2,
        y: targetY + (targetHeight - height) / 2,
        width,
        height,
    };
};

export const composeCurriculumVisualizationImage = async ({
    capture,
    title,
    total,
    filters = [],
    hierarchy,
    legend = [],
}) => {
    const imageSource =
        typeof capture === "function" ? await capture() : capture;
    if (!imageSource) {
        throw new Error("La visualización no entregó una imagen exportable.");
    }

    const image = await loadImage(imageSource);
    const width = Math.max(1400, image.naturalWidth || 1400);
    const visualizationHeight = Math.max(
        720,
        Math.min(1100, image.naturalHeight || 720)
    );
    const headerHeight = 280;
    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = headerHeight + visualizationHeight + 52;
    const context = canvas.getContext("2d");

    context.fillStyle = "#f4f7fa";
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = "#ffffff";
    context.fillRect(32, 28, width - 64, canvas.height - 56);
    context.fillStyle = "#17263d";
    context.font = "700 32px system-ui, -apple-system, sans-serif";
    context.fillText(title || "Mapa curricular", 72, 82);
    context.fillStyle = "#245486";
    context.font = "700 19px system-ui, -apple-system, sans-serif";
    context.fillText(
        `${Number(total || 0).toLocaleString("es-CL")} objetivos`,
        72,
        120
    );
    context.fillStyle = "#627187";
    context.font = "16px system-ui, -apple-system, sans-serif";
    const filterText = filters.length
        ? `Filtros: ${filters.join(" · ")}`
        : "Filtros: catálogo completo del contexto seleccionado";
    const lines = wrapText(context, filterText, width - 144).slice(0, 3);
    lines.forEach((line, index) =>
        context.fillText(line, 72, 154 + index * 22)
    );
    context.fillText(`Jerarquía: ${hierarchy}`, 72, 206);
    const visibleLegend = legend.slice(0, 8);
    visibleLegend.forEach((item, index) => {
        const columnWidth = (width - 144) / 4;
        const column = index % 4;
        const row = Math.floor(index / 4);
        const x = 72 + column * columnWidth;
        const y = 237 + row * 23;
        context.fillStyle = item.color || "#245486";
        context.beginPath();
        context.arc(x + 6, y - 5, 6, 0, Math.PI * 2);
        context.fill();
        context.fillStyle = "#33445b";
        context.font = "14px system-ui, -apple-system, sans-serif";
        const suffix = item.count === undefined ? "" : ` · ${item.count}`;
        context.fillText(
            `${String(item.label || "Categoría").slice(0, 22)}${suffix}`,
            x + 18,
            y
        );
    });
    if (legend.length > visibleLegend.length) {
        context.fillStyle = "#627187";
        context.font = "13px system-ui, -apple-system, sans-serif";
        context.fillText(
            `+ ${legend.length - visibleLegend.length} categorías más`,
            width - 255,
            272
        );
    }
    context.textAlign = "right";
    context.fillText(new Date().toLocaleString("es-CL"), width - 72, 120);
    context.textAlign = "left";

    const imageRect = calculateContainRect(
        image.naturalWidth || width,
        image.naturalHeight || visualizationHeight,
        60,
        headerHeight,
        width - 120,
        visualizationHeight
    );
    context.drawImage(
        image,
        imageRect.x,
        imageRect.y,
        imageRect.width,
        imageRect.height
    );
    context.fillStyle = "#627187";
    context.font = "14px system-ui, -apple-system, sans-serif";
    context.fillText(
        "El tamaño representa cantidad de objetivos, no importancia curricular.",
        72,
        canvas.height - 48
    );

    return canvas.toDataURL("image/png", 1);
};

export const exportCurriculumVisualization = async (options) => {
    const dataUrl = await composeCurriculumVisualizationImage(options);
    downloadDataUrl(dataUrl, options?.filename || "mapa-curricular.png");
    return dataUrl;
};

export const buildCurriculumVisualizationPdfDefinition = ({
    image,
    title,
    total,
}) => ({
    pageSize: "A3",
    pageOrientation: "landscape",
    pageMargins: [24, 24, 24, 24],
    info: {
        title: title || "Mapa curricular",
        subject: `${Number(total || 0).toLocaleString(
            "es-CL"
        )} objetivos curriculares`,
        creator: "Libro Digital",
    },
    content: [
        {
            image,
            fit: [1140, 790],
            alignment: "center",
        },
    ],
    defaultStyle: {
        font: "Roboto",
    },
});

export const exportCurriculumVisualizationPdf = async (options) => {
    const image = await composeCurriculumVisualizationImage(options);
    const { getPdfMake } = await import("./pdfmake");
    const pdfMake = await getPdfMake();
    const filename = String(options?.filename || "mapa-curricular.pdf").replace(
        /\.png$/i,
        ".pdf"
    );
    const definition = buildCurriculumVisualizationPdfDefinition({
        image,
        title: options?.title,
        total: options?.total,
    });

    await new Promise((resolve, reject) => {
        try {
            pdfMake.createPdf(definition).download(filename, resolve);
        } catch (error) {
            reject(error);
        }
    });

    return image;
};

export const safeFilenamePart = (value) =>
    String(value || "catalogo")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "")
        .slice(0, 72) || "catalogo";
