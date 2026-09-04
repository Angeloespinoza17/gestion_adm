// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";

const cameraMocks = vi.hoisted(() => ({
  stream: { id: "rear-camera-stream" },
  canUseLiveCamera: vi.fn(),
  captureCameraPhoto: vi.fn(),
  openRearCamera: vi.fn(),
  stopMediaStream: vi.fn(),
}));

vi.mock("../../resources/js/utils/camera-capture", () => cameraMocks);

import InventoryTab from "../../resources/js/components/library/tabs/inventory-tab.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

const catalogs = {
  works: [{ id: 41, title: "El principito" }],
  locations: [],
  users: [],
  ejemplar_origins: [{ value: "inventario_inicial", label: "Inventario inicial" }],
  ejemplar_states: [{ value: "bueno", label: "Bueno" }],
  ejemplar_availability_statuses: [{ value: "disponible", label: "Disponible" }],
  capabilities: { manage_inventory: true },
};

const mountTab = () => mount(InventoryTab, {
  props: { catalogs },
  global: {
    mocks: { $route: { query: {} } },
    stubs: {
      LibraryHelpButton: true,
      LibraryStatusBadge: true,
      LoadingState: true,
      BAlert: { template: "<div><slot /></div>" },
      BFormInput: { props: ["modelValue"], template: "<input :value='modelValue' />" },
      BFormSelect: { props: ["modelValue", "options"], template: "<select :value='modelValue' />" },
      BFormTextarea: { props: ["modelValue"], template: "<textarea :value='modelValue' />" },
      BFormCheckbox: { props: ["modelValue"], template: "<label><input type='checkbox' :checked='modelValue' /><slot /></label>" },
      BPagination: true,
      BModal: { props: ["modelValue"], template: "<div v-if='modelValue' class='modal-stub'><slot /></div>" },
    },
  },
});

describe("Biblioteca · fotografías móviles de inventario", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    axios.put.mockReset();
    axios.get.mockResolvedValue({
      data: {
        items: { data: [], current_page: 1, total: 0, per_page: 15 },
        summary: { active_total: 0, checked_this_year: 0, pending_check: 0, damaged_or_lost: 0 },
      },
    });
    URL.createObjectURL = vi.fn(() => "blob:mobile-camera-preview");
    URL.revokeObjectURL = vi.fn();
    Object.defineProperty(HTMLMediaElement.prototype, "play", {
      configurable: true,
      value: vi.fn(() => Promise.resolve()),
    });
    cameraMocks.canUseLiveCamera.mockReset().mockReturnValue(true);
    cameraMocks.captureCameraPhoto.mockReset();
    cameraMocks.openRearCamera.mockReset().mockResolvedValue(cameraMocks.stream);
    cameraMocks.stopMediaStream.mockReset();
  });

  it("keeps a rear-camera input fallback and previews the captured photo", async () => {
    const wrapper = mountTab();
    await flushPromises();
    wrapper.vm.openCreate();
    await wrapper.vm.$nextTick();

    const cameraInput = wrapper.find('input.inventory-evidence__input[accept="image/*"]');
    expect(cameraInput.attributes("accept")).toBe("image/*");
    expect(cameraInput.attributes("capture")).toBe("environment");

    const photo = new File(["mobile-photo"], "inventario.jpg", { type: "image/jpeg" });
    wrapper.vm.selectPhotos({ target: { files: [photo], value: "selected" } });
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.pendingPhotos).toHaveLength(1);
    expect(wrapper.find(".inventory-evidence__photo.is-pending img").attributes("src")).toBe("blob:mobile-camera-preview");
    expect(wrapper.text()).toContain("Lista para guardar");
  });

  it("opens the device camera live and captures a photo inside the form", async () => {
    const wrapper = mountTab();
    await flushPromises();
    wrapper.vm.openCreate();
    await wrapper.vm.$nextTick();

    await wrapper.find(".inventory-evidence__camera").trigger("click");
    await flushPromises();

    expect(cameraMocks.canUseLiveCamera).toHaveBeenCalledOnce();
    expect(cameraMocks.openRearCamera).toHaveBeenCalledOnce();
    expect(wrapper.vm.cameraActive).toBe(true);
    expect(wrapper.vm.cameraStream).toStrictEqual(cameraMocks.stream);
    expect(wrapper.find(".inventory-camera__viewport").exists()).toBe(true);
    expect(wrapper.text()).toContain("Cámara activa");
    expect(wrapper.text()).toContain("Capturar fotografía");

    const capturedPhoto = new File(["captured-photo"], "ejemplar.jpg", { type: "image/jpeg" });
    cameraMocks.captureCameraPhoto.mockResolvedValue(capturedPhoto);
    await wrapper.find(".inventory-camera__capture").trigger("click");
    await flushPromises();

    expect(cameraMocks.captureCameraPhoto).toHaveBeenCalledOnce();
    expect(wrapper.vm.pendingPhotos).toHaveLength(1);
    expect(wrapper.vm.pendingPhotos[0].file).toBe(capturedPhoto);
    expect(wrapper.vm.cameraActive).toBe(false);
    expect(cameraMocks.stopMediaStream).toHaveBeenCalledOnce();
    expect(cameraMocks.stopMediaStream.mock.calls[0][0]).toStrictEqual(cameraMocks.stream);
  });

  it("saves the copy first and uploads camera files as multipart evidence", async () => {
    const wrapper = mountTab();
    await flushPromises();
    wrapper.vm.openCreate();
    wrapper.vm.form.biblioteca_obra_id = 41;
    const photo = new File(["mobile-photo"], "inventario.jpg", { type: "image/jpeg" });
    wrapper.vm.selectPhotos({ target: { files: [photo], value: "selected" } });
    axios.post.mockImplementation((url) => Promise.resolve({
      data: url === "/api/biblioteca/ejemplares"
        ? { data: { id: 77 } }
        : { data: { id: 77, photo_urls: ["/api/biblioteca/ejemplares/77/photos/photo.jpg"] } },
    }));

    await wrapper.vm.save();

    expect(axios.post.mock.calls[0][0]).toBe("/api/biblioteca/ejemplares");
    expect(axios.post.mock.calls[1][0]).toBe("/api/biblioteca/ejemplares/77/photos");
    expect(axios.post.mock.calls[1][1]).toBeInstanceOf(FormData);
    expect(axios.post.mock.calls[1][1].getAll("photos[]")).toHaveLength(1);
    expect(wrapper.vm.pendingPhotos).toHaveLength(0);
  });
});
