let pdfMakePromise;

export function getPdfMake() {
  if (!pdfMakePromise) {
    pdfMakePromise = Promise.all([
      import("pdfmake/build/pdfmake"),
      import("pdfmake/build/vfs_fonts"),
    ]).then(([pdfMakeModule, fontsModule]) => {
      const pdfMake = pdfMakeModule.default || pdfMakeModule;
      const fonts = fontsModule.default || fontsModule;
      const vfs = fonts?.pdfMake?.vfs || fonts?.vfs || fonts;
      if (vfs) pdfMake.vfs = vfs;
      return pdfMake;
    });
  }

  return pdfMakePromise;
}
