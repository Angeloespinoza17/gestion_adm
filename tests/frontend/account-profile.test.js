// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import Swal from "sweetalert2";
import { beforeEach, describe, expect, it, vi } from "vitest";
import AccountProfile from "../../resources/js/views/account/profile.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { props: ["message"], template: "<div class='loading-state'>{{ message }}</div>" },
}));

const profile = {
  id: 9,
  name: "Ana Soto",
  email: "ana.soto@example.test",
  user_type: "staff",
  profile_photo_url: null,
  profile_photo_source: null,
  cargo: { id: 3, name: "Coordinadora", slug: "coordinadora" },
  roles: [{ id: 5, name: "Equipo directivo", slug: "equipo_directivo" }],
  staff: { id: 17, full_name: "Ana Soto", position: "Coordinadora", profile_photo_url: null },
};

const mountView = () => mount(AccountProfile, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { props: ["message"], template: "<div class='loading-state'>{{ message }}</div>" },
      BAlert: { template: "<div class='alert-stub'><slot /></div>" },
      BButton: {
        props: ["disabled", "type"],
        emits: ["click"],
        template: "<button :type='type || \"button\"' :disabled='disabled' @click='$emit(\"click\")'><slot /></button>",
      },
      BForm: { emits: ["submit"], template: "<form @submit.prevent='$emit(\"submit\")'><slot /></form>" },
      BFormGroup: {
        props: ["label", "labelFor"],
        template: "<div><label :for='labelFor'>{{ label }}</label><slot /></div>",
      },
      BFormInput: {
        props: ["id", "modelValue", "disabled", "type"],
        emits: ["update:modelValue", "input"],
        template: "<input :id='id' :value='modelValue' :disabled='disabled' :type='type || \"text\"' @input='$emit(\"update:modelValue\", $event.target.value); $emit(\"input\", $event)' />",
      },
      BFormInvalidFeedback: { template: "<div><slot /></div>" },
    },
  },
});

describe("Perfil de cuenta", () => {
  beforeEach(() => {
    localStorage.clear();
    axios.get.mockReset();
    axios.post.mockReset();
    axios.put.mockReset();
    Swal.fire.mockClear();
    axios.get.mockResolvedValue({ data: { data: profile } });
    URL.createObjectURL = vi.fn(() => "blob:profile-preview");
    URL.revokeObjectURL = vi.fn();
  });

  it("renders the redesigned identity and security workspace", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.get("#profile-page-title").text()).toBe("Mi perfil");
    expect(wrapper.text()).toContain("Información de perfil");
    expect(wrapper.text()).toContain("Datos institucionales protegidos");
    expect(wrapper.text()).toContain("Cambiar contraseña");
    expect(wrapper.get(".profile-avatar span").text()).toBe("AS");
    expect(wrapper.get("#profile-photo").attributes("accept")).toBe("image/jpeg,image/png,image/webp");
    expect(wrapper.vm.displayUserType).toBe("Funcionario");
    expect(wrapper.vm.canSaveProfile).toBe(false);
  });

  it("shows a recoverable state when the profile request fails", async () => {
    axios.get.mockRejectedValueOnce({ response: { data: { message: "Servicio temporalmente no disponible." } } });
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.find(".profile-load-error").exists()).toBe(true);
    expect(wrapper.text()).toContain("No pudimos cargar tu perfil");
    expect(wrapper.text()).toContain("Servicio temporalmente no disponible.");
    expect(wrapper.text()).toContain("Reintentar");
  });

  it("validates photo type and size before creating a preview", async () => {
    const wrapper = mountView();
    await flushPromises();

    wrapper.vm.selectPhoto(new File(["avatar"], "avatar.gif", { type: "image/gif" }));
    expect(wrapper.vm.profileErrors.photo[0]).toContain("JPG, PNG o WEBP");
    expect(URL.createObjectURL).not.toHaveBeenCalled();

    wrapper.vm.selectPhoto({ name: "avatar.jpg", type: "image/jpeg", size: (5 * 1024 * 1024) + 1 });
    expect(wrapper.vm.profileErrors.photo[0]).toContain("5 MB");

    const validPhoto = new File(["avatar"], "avatar.webp", { type: "image/webp" });
    wrapper.vm.selectPhoto(validPhoto);
    expect(wrapper.vm.selectedPhoto).toBe(validPhoto);
    expect(wrapper.vm.photoPreview).toBe("blob:profile-preview");
    expect(wrapper.vm.canSaveProfile).toBe(true);
  });

  it("enables the password action only for a valid matching replacement", async () => {
    const wrapper = mountView();
    await flushPromises();

    await wrapper.setData({
      passwordForm: {
        current_password: "Actual-2026",
        password: "Nueva-Segura-2026!",
        password_confirmation: "no-coincide",
      },
    });
    expect(wrapper.vm.canSavePassword).toBe(false);
    expect(wrapper.vm.passwordRequirements.find((item) => item.key === "match").valid).toBe(false);

    await wrapper.setData({ passwordForm: { ...wrapper.vm.passwordForm, password_confirmation: "Nueva-Segura-2026!" } });
    expect(wrapper.vm.canSavePassword).toBe(true);
    expect(wrapper.vm.passwordStrength.label).toBe("Sólida");
  });

  it("sends only confirmed profile changes and refreshes the local account", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.setData({ profileForm: { name: "  Ana María Soto  ", remove_photo: false } });
    axios.post.mockResolvedValue({ data: { message: "Perfil actualizado correctamente.", data: { ...profile, name: "Ana María Soto" } } });

    await wrapper.vm.saveProfile();

    expect(Swal.fire).toHaveBeenCalled();
    expect(axios.post).toHaveBeenCalledWith("/api/me/profile", expect.any(FormData), {
      headers: { "Content-Type": "multipart/form-data" },
    });
    const submitted = axios.post.mock.calls[0][1];
    expect(submitted.get("name")).toBe("Ana María Soto");
    expect(wrapper.vm.profile.name).toBe("Ana María Soto");
    expect(JSON.parse(localStorage.getItem("user"))).toMatchObject({
      name: "Ana María Soto",
      user_type: "staff",
      staff_id: 17,
      is_staff: true,
    });
  });
});
