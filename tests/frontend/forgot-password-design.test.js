// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { readFileSync } from "node:fs";
import { beforeEach, describe, expect, it, vi } from "vitest";
import ForgotPassword from "../../resources/js/views/account/forgot-password.vue";

vi.mock("axios", () => ({
  default: {
    post: vi.fn(),
  },
}));

const source = readFileSync("resources/js/views/account/forgot-password.vue", "utf8");

const mountForgotPassword = () => mount(ForgotPassword, {
  global: {
    stubs: {
      RouterLink: {
        props: ["to"],
        template: "<a :href='to'><slot /></a>",
      },
      BAlert: {
        props: ["modelValue"],
        template: "<div v-if='modelValue' class='recovery-alert'><slot /></div>",
      },
      BForm: {
        emits: ["submit"],
        template: "<form @submit.prevent='$emit(\"submit\")'><slot /></form>",
      },
      BFormGroup: {
        props: ["label", "labelFor"],
        template: "<div><label :for='labelFor'>{{ label }}</label><slot /></div>",
      },
      BFormInput: {
        props: ["id", "modelValue", "type", "name", "autocomplete", "inputmode", "placeholder", "required", "disabled"],
        emits: ["update:modelValue", "input"],
        template: `<input
          :id='id'
          :value='modelValue'
          :type='type'
          :name='name'
          :autocomplete='autocomplete'
          :inputmode='inputmode'
          :placeholder='placeholder'
          :required='required'
          :disabled='disabled'
          @input='$emit("update:modelValue", $event.target.value); $emit("input", $event)'
        />`,
      },
      BButton: {
        props: ["type", "disabled"],
        template: "<button :type='type' :disabled='disabled'><slot /></button>",
      },
    },
  },
});

describe("Recuperación de clave institucional", () => {
  beforeEach(() => {
    axios.post.mockReset();
  });

  it("usa la identidad CNSC y una composición responsive", () => {
    expect(source).toContain('const cnscLogo = "/brand/logo-cnsc.png"');
    expect(source).toContain("Volver a tu cuenta es simple.");
    expect(source).toContain("recovery-mobile-brand");
    expect(source).toContain("@media (max-width: 575.98px)");
    expect(source).not.toContain("Skote. Crafted with");
  });

  it("renderiza un formulario accesible con navegación de regreso", () => {
    const wrapper = mountForgotPassword();

    expect(wrapper.get("#recovery-title").text()).toBe("Recupera tu clave");
    expect(wrapper.get("#recovery-email").attributes("type")).toBe("email");
    expect(wrapper.get("#recovery-email").attributes("autocomplete")).toBe("email");
    expect(wrapper.get('a[href="/login"]').text()).toContain("Volver al inicio de sesión");
  });

  it("muestra el estado de éxito sin abandonar la vista", async () => {
    axios.post.mockResolvedValueOnce({
      data: { success: true, message: "success", data: null },
    });
    const wrapper = mountForgotPassword();

    await wrapper.get("#recovery-email").setValue("persona@cnscvaldivia.cl");
    await wrapper.vm.forget();
    await flushPromises();

    expect(axios.post).toHaveBeenCalledWith("/api/forget-password", {
      email: "persona@cnscvaldivia.cl",
    });
    expect(wrapper.text()).toContain("Revisa tu correo");
    expect(wrapper.text()).toContain("persona@cnscvaldivia.cl");
    expect(wrapper.vm.processing).toBe(false);
  });

  it("informa errores de red y vuelve a habilitar el formulario", async () => {
    axios.post.mockRejectedValueOnce({
      response: { data: { message: "No fue posible enviar el correo." } },
    });
    const wrapper = mountForgotPassword();

    await wrapper.get("#recovery-email").setValue("persona@cnscvaldivia.cl");
    await wrapper.vm.forget();
    await flushPromises();

    expect(wrapper.text()).toContain("No fue posible enviar el correo.");
    expect(wrapper.vm.isResetError).toBe(true);
    expect(wrapper.vm.processing).toBe(false);
  });
});
