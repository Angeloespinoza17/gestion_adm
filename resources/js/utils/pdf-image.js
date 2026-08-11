const PDF_IMAGE_MAX_DIMENSION = 2400;
const PDF_IMAGE_JPEG_QUALITY = 0.88;

const resolveImageUrl = (url) => {
  const value = String(url || "").trim();

  if (!value) return null;
  if (/^https?:\/\//i.test(value) || value.startsWith("/")) return value;

  return `/${value}`;
};

const loadImageElement = (blob) =>
  new Promise((resolve, reject) => {
    if (typeof Image === "undefined" || typeof URL === "undefined") {
      reject(new Error("El navegador no puede decodificar la imagen."));
      return;
    }

    const objectUrl = URL.createObjectURL(blob);
    const image = new Image();

    image.onload = () => {
      resolve({
        source: image,
        width: image.naturalWidth,
        height: image.naturalHeight,
        release: () => URL.revokeObjectURL(objectUrl),
      });
    };
    image.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error("La respuesta no contiene una imagen compatible."));
    };
    image.src = objectUrl;
  });

const decodeImageBlob = async (blob) => {
  if (typeof createImageBitmap === "function") {
    try {
      const bitmap = await createImageBitmap(blob, { imageOrientation: "from-image" });

      return {
        source: bitmap,
        width: bitmap.width,
        height: bitmap.height,
        release: () => bitmap.close(),
      };
    } catch {
      // Algunos navegadores decodifican más formatos mediante Image que con createImageBitmap.
    }
  }

  return loadImageElement(blob);
};

const imageDimensions = (width, height) => {
  const largestDimension = Math.max(width, height);
  const scale = largestDimension > PDF_IMAGE_MAX_DIMENSION ? PDF_IMAGE_MAX_DIMENSION / largestDimension : 1;

  return {
    width: Math.max(1, Math.round(width * scale)),
    height: Math.max(1, Math.round(height * scale)),
  };
};

export const blobToPdfImageDataUrl = async (blob) => {
  if (!(blob instanceof Blob) || !blob.size) {
    throw new Error("La imagen está vacía.");
  }

  if (typeof document === "undefined") {
    throw new Error("El navegador no permite convertir la imagen.");
  }

  const decoded = await decodeImageBlob(blob);

  try {
    if (!decoded.width || !decoded.height) {
      throw new Error("La imagen no tiene dimensiones válidas.");
    }

    const dimensions = imageDimensions(decoded.width, decoded.height);
    const canvas = document.createElement("canvas");
    canvas.width = dimensions.width;
    canvas.height = dimensions.height;

    const context = canvas.getContext("2d");
    if (!context) {
      throw new Error("No se pudo preparar la imagen para el PDF.");
    }

    // JPEG no soporta transparencia; el fondo blanco evita áreas negras en PNG/GIF.
    context.fillStyle = "#ffffff";
    context.fillRect(0, 0, dimensions.width, dimensions.height);
    context.drawImage(decoded.source, 0, 0, dimensions.width, dimensions.height);

    const dataUrl = canvas.toDataURL("image/jpeg", PDF_IMAGE_JPEG_QUALITY);
    canvas.width = 1;
    canvas.height = 1;

    if (!dataUrl.startsWith("data:image/jpeg;base64,")) {
      throw new Error("No se pudo convertir la imagen a JPEG.");
    }

    return dataUrl;
  } finally {
    decoded.release();
  }
};

export const fetchPdfCompatibleImage = async (url) => {
  const absoluteUrl = resolveImageUrl(url);

  if (!absoluteUrl) {
    return { dataUrl: null, status: "missing" };
  }

  try {
    const response = await fetch(absoluteUrl, { credentials: "same-origin" });
    if (!response.ok) {
      throw new Error(`La imagen respondió con estado ${response.status}.`);
    }

    const contentType = String(response.headers.get("content-type") || "")
      .split(";", 1)[0]
      .trim()
      .toLowerCase();

    if (contentType && !contentType.startsWith("image/") && contentType !== "application/octet-stream") {
      throw new Error("La URL de la foto no devolvió una imagen.");
    }

    const dataUrl = await blobToPdfImageDataUrl(await response.blob());

    return { dataUrl, status: "included" };
  } catch {
    return { dataUrl: null, status: "unavailable" };
  }
};
