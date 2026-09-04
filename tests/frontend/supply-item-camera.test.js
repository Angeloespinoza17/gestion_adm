// @vitest-environment jsdom

import { describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";
import { captureCameraPhoto, openRearCamera, stopMediaStream } from "../../resources/js/utils/camera-capture";

const view = readFileSync("resources/js/views/supplies/index.vue", "utf8");
const cameraUtility = readFileSync("resources/js/utils/camera-capture.js", "utf8");

describe("Abastecimiento · foto de referencia del insumo", () => {
  it("ofrece cámara trasera en vivo y selección independiente desde galería", () => {
    expect(view).toContain("Tomar foto");
    expect(view).toContain("Elegir de galería");
    expect(view).toContain('ref="cameraInput"');
    expect(view).toContain('capture="environment"');
    expect(cameraUtility).toContain("navigator.mediaDevices.getUserMedia");
    expect(cameraUtility).toContain('facingMode: { ideal: "environment" }');
    expect(cameraUtility).toContain('canvas.toBlob(resolve, "image/jpeg", 0.9)');
  });

  it("detiene todos los tracks al capturar, cerrar o desmontar el formulario", () => {
    expect(cameraUtility).toContain("getTracks?.().forEach((track) => track.stop())");
    expect(view).toMatch(/beforeUnmount\(\)\s*\{[\s\S]*?this\.stopCamera\(\)/);
    expect(view).toContain("if (!isOpen) this.stopCamera()");
  });

  it("solicita la cámara trasera y libera el dispositivo al cerrar", async () => {
    const stopTrack = vi.fn();
    const stream = { getTracks: () => [{ stop: stopTrack }] };
    const getUserMedia = vi.fn().mockResolvedValue(stream);
    Object.defineProperty(navigator, "mediaDevices", {
      configurable: true,
      value: { getUserMedia },
    });

    const openedStream = await openRearCamera();

    expect(getUserMedia).toHaveBeenCalledWith({
      video: { facingMode: { ideal: "environment" } },
      audio: false,
    });
    expect(openedStream).toBe(stream);

    stopMediaStream(openedStream);
    expect(stopTrack).toHaveBeenCalledOnce();
  });

  it("convierte la captura en la foto JPEG del nuevo insumo", async () => {
    const drawImage = vi.fn();
    const canvas = {
      width: 0,
      height: 0,
      getContext: () => ({ drawImage }),
      toBlob: (callback) => callback(new Blob(["captura"], { type: "image/jpeg" })),
    };
    const video = { videoWidth: 1280, videoHeight: 720 };
    const photo = await captureCameraPhoto(video, canvas, "insumo");

    expect(drawImage).toHaveBeenCalledWith(video, 0, 0, 1280, 720);
    expect(photo).toBeInstanceOf(File);
    expect(photo.name).toMatch(/^insumo-\d+\.jpg$/);
    expect(photo.type).toBe("image/jpeg");
  });
});
