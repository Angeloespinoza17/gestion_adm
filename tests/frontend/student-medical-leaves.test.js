// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import Swal from "sweetalert2";
import { beforeEach, describe, expect, it, vi } from "vitest";
import MedicalLeaves from "../../resources/js/views/student-health/medical-leaves.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn() },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state' />" },
}));

const apiResponse = {
  data: [],
  current_page: 1,
  last_page: 1,
  total: 0,
  summary: { total_records: 2, permanent_records: 1, chronic_students: 1, ending_soon: 0 },
  capabilities: { can_create: true, can_edit: true },
};

const mountView = (path = "/infirmary/medical-leaves") => mount(MedicalLeaves, {
  global: {
    mocks: { $route: { path } },
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { template: "<div class='loading-state' />" },
      BAlert: { template: "<div><slot /></div>" },
      BModal: {
        props: ["modelValue"],
        template: "<div v-if='modelValue' class='modal-stub'><slot /></div>",
      },
    },
  },
});

describe("Licencias médicas compartidas", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    Swal.fire.mockClear();
    axios.get.mockResolvedValue({ data: apiResponse });
    Object.defineProperty(URL, "createObjectURL", { configurable: true, value: vi.fn(() => "blob:medical-preview") });
    Object.defineProperty(URL, "revokeObjectURL", { configurable: true, value: vi.fn() });
  });

  it("renders the chronic-student filter and requests only permanent records when activated", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Enfermedad crónica");
    expect(wrapper.text()).toContain("Alumnas con condición crónica");
    await wrapper.find("button.chronic-filter").trigger("click");
    await flushPromises();

    expect(axios.get).toHaveBeenLastCalledWith("/api/student-medical-leaves", {
      params: expect.objectContaining({ permanent: 1 }),
    });
  });

  it("uses a datalist and accepts a permanent condition without an end date", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.find("button.medical-create-button").trigger("click");

    const student = { id: 44, name: "Antonia Soto", rut: "21.111.222-3", course: "1° medio A" };
    const label = `${student.name} · ${student.rut} · ${student.course}`;
    await wrapper.setData({ studentOptions: [student], studentSearch: label });
    await wrapper.find("#medical-leave-student").trigger("change");

    expect(wrapper.find("#medical-leave-student").attributes("list")).toBe("medical-leave-student-options");
    expect(wrapper.find("#medical-leave-student-options").exists()).toBe(true);
    expect(wrapper.vm.form.student_profile_id).toBe(44);
    expect(wrapper.find(".overlap-notice").text()).toContain("Control automático de duplicados");
    expect(wrapper.find(".overlap-notice").text()).toContain("Enfermería o Inspectoría");

    await wrapper.find(".permanent-card input").setValue(true);
    expect(wrapper.vm.form.is_permanent).toBe(true);
    expect(wrapper.vm.form.ends_on).toBe("");
    expect(wrapper.find("#medical-leave-end").attributes()).toHaveProperty("disabled");
    expect(wrapper.find(".overlap-notice").text()).toContain("Condición crónica excluida del bloqueo");
  });

  it("allows attaching a file or opening the rear camera and submits multipart data", async () => {
    axios.post.mockResolvedValue({ data: { message: "Licencia registrada con respaldo privado." } });
    const wrapper = mountView();
    await flushPromises();
    await wrapper.find("button.medical-create-button").trigger("click");

    expect(wrapper.find("#medical-leave-file").attributes("accept")).toContain("application/pdf");
    expect(wrapper.find("#medical-leave-camera").attributes("accept")).toBe("image/*");
    expect(wrapper.find("#medical-leave-camera").attributes("capture")).toBe("environment");
    expect(wrapper.text()).toContain("Respaldo médico privado");
    expect(wrapper.text()).toContain("Solo personal autorizado");

    const photo = new File(["camera-photo"], "licencia-foto.jpg", { type: "image/jpeg" });
    await wrapper.vm.onAttachmentSelected({ target: { files: [photo], value: "" } }, "camera");
    expect(wrapper.vm.attachmentFile).toBe(photo);
    expect(wrapper.vm.attachmentSource).toBe("camera");
    expect(wrapper.find(".attachment-selected").text()).toContain("FOTOGRAFÍA CAPTURADA");

    const student = { id: 44, name: "Antonia Soto", rut: "21.111.222-3", course: "1° medio A" };
    const label = `${student.name} · ${student.rut} · ${student.course}`;
    await wrapper.setData({ studentOptions: [student], studentSearch: label });
    wrapper.vm.form.starts_on = "2026-08-24";
    wrapper.vm.form.ends_on = "2026-08-26";
    wrapper.vm.form.reason = "Reposo indicado.";
    await wrapper.vm.save();

    const payload = axios.post.mock.calls[0][1];
    expect(payload).toBeInstanceOf(FormData);
    expect(payload.get("student_profile_id")).toBe("44");
    expect(payload.get("source_module")).toBe("infirmary");
    expect(payload.get("attachment").name).toBe("licencia-foto.jpg");
  });

  it("opens an integrated live camera like the maintenance work-order flow and captures a photo", async () => {
    const stop = vi.fn();
    const stream = { getTracks: () => [{ stop }] };
    const getUserMedia = vi.fn().mockResolvedValue(stream);
    Object.defineProperty(navigator, "mediaDevices", { configurable: true, value: { getUserMedia } });
    Object.defineProperty(HTMLMediaElement.prototype, "play", { configurable: true, value: vi.fn(() => Promise.resolve()) });
    Object.defineProperty(HTMLCanvasElement.prototype, "getContext", { configurable: true, value: vi.fn(() => ({ drawImage: vi.fn() })) });
    Object.defineProperty(HTMLCanvasElement.prototype, "toBlob", { configurable: true, value: vi.fn((callback) => callback(new Blob(["photo"], { type: "image/jpeg" }))) });

    const wrapper = mountView();
    await flushPromises();
    await wrapper.find("button.medical-create-button").trigger("click");
    await wrapper.find("button.attachment-choice--camera").trigger("click");
    await flushPromises();

    expect(Swal.fire).toHaveBeenCalledWith(expect.objectContaining({ title: "Abrir cámara" }));
    expect(getUserMedia).toHaveBeenCalledWith({
      video: { facingMode: { ideal: "environment" } },
      audio: false,
    });
    expect(wrapper.vm.showCameraModal).toBe(true);
    expect(wrapper.find(".medical-camera-viewport video").exists()).toBe(true);

    await wrapper.vm.capturePhotoFromCamera();
    expect(wrapper.vm.attachmentFile).toBeInstanceOf(File);
    expect(wrapper.vm.attachmentFile.name).toMatch(/^licencia-foto-\d+\.jpg$/);
    expect(wrapper.vm.attachmentSource).toBe("camera");
    expect(stop).toHaveBeenCalledTimes(1);
    expect(wrapper.vm.showCameraModal).toBe(false);
  });

  it("lets Inspectoría edit fields and add or replace a private attachment", async () => {
    const item = {
      id: 81,
      student: { id: 44, name: "Antonia Soto", rut: "21.111.222-3", course: "1° medio A" },
      starts_on: "2026-08-20",
      ends_on: "2026-08-22",
      reason: "Control médico.",
      is_permanent: false,
      status: "ended",
      source_module: "inspectoria",
      registered_by: "Inspectora curso A",
      attachment: { name: "respaldo-original.pdf", mime_type: "application/pdf", size_bytes: 2048, download_url: "/api/student-medical-leaves/81/attachment" },
    };
    axios.get.mockResolvedValue({ data: { ...apiResponse, data: [item], total: 1 } });
    axios.post.mockResolvedValue({ data: { message: "Licencia médica actualizada." } });

    const wrapper = mountView("/inspectoria/licencias-medicas");
    await flushPromises();

    expect(wrapper.find("button.medical-edit-button").exists()).toBe(true);
    await wrapper.find("button.medical-edit-button").trigger("click");

    expect(wrapper.text()).toContain("Corrección trazable");
    expect(wrapper.text()).toContain("RESPALDO ACTUAL");
    expect(wrapper.find("#medical-leave-student").attributes()).toHaveProperty("disabled");

    wrapper.vm.form.ends_on = "2026-08-24";
    wrapper.vm.form.reason = "Control médico con período corregido.";
    const replacement = new File(["corrected"], "respaldo-corregido.pdf", { type: "application/pdf" });
    await wrapper.vm.onAttachmentSelected({ target: { files: [replacement], value: "" } }, "file");
    await wrapper.vm.save();

    const [endpoint, payload] = axios.post.mock.calls[0];
    expect(endpoint).toBe("/api/student-medical-leaves/81");
    expect(payload).toBeInstanceOf(FormData);
    expect(payload.get("_method")).toBe("PUT");
    expect(payload.get("reason")).toBe("Control médico con período corregido.");
    expect(payload.get("ends_on")).toBe("2026-08-24");
    expect(payload.get("source_module")).toBeNull();
    expect(payload.get("attachment").name).toBe("respaldo-corregido.pdf");
  });
});
