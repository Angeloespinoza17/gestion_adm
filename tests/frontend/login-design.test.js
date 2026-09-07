// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { readFileSync } from "node:fs";
import { beforeEach, describe, expect, it, vi } from "vitest";
import Login from "../../resources/js/views/account/login.vue";

vi.mock("axios", () => ({
  default: {
    defaults: { headers: { common: {} } },
    post: vi.fn(),
  },
}));

const source = readFileSync("resources/js/views/account/login.vue", "utf8");
const appLayoutSource = readFileSync("resources/views/layouts/app.blade.php", "utf8");
const routerPush = vi.fn();

const mountLogin = () => mount(Login, {
  global: {
    mocks: {
      $router: { push: routerPush },
    },
    stubs: {
      RouterLink: {
        props: ["to"],
        template: "<a :href='to'><slot /></a>",
      },
      BAlert: {
        props: ["modelValue"],
        template: "<div v-if='modelValue' class='login-alert'><slot /></div>",
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
        props: ["id", "modelValue", "type", "name", "autocomplete", "inputmode", "placeholder", "required"],
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

describe("Acceso institucional", () => {
  beforeEach(() => {
    localStorage.clear();
    routerPush.mockClear();
    axios.post.mockReset();
    axios.defaults.headers.common = {};
  });

  it("usa la identidad oficial y una jerarquía responsive propia", () => {
    expect(source).toContain('const cnscLogo = "/brand/logo-cnsc.png"');
    expect(source).toContain("Un acceso.<br />Toda tu jornada.");
    expect(source).toContain("Entorno institucional seguro");
    expect(source).toContain("login-mobile-brand");
    expect(source).toContain("@media (max-width: 575.98px)");
    expect(source).not.toContain("profileImg");
  });

  it("reserva el tamaño del escudo antes de cargar el CSS diferido", () => {
    const wrapper = mountLogin();
    const logos = wrapper.findAll("img[data-cnsc-auth-logo]");

    expect(logos).toHaveLength(2);
    logos.forEach((logo) => {
      expect(logo.attributes("width")).toBe("44");
      expect(logo.attributes("height")).toBe("44");
    });
    expect(appLayoutSource).toContain("request()->is('login')");
    expect(appLayoutSource).toContain("resources/js/views/account/login.vue");
    expect(appLayoutSource).toContain("manifestStylesheet");
    expect(appLayoutSource).toContain("build/css/login.min.css");
    expect(appLayoutSource).toContain('rel="stylesheet"');
    expect(appLayoutSource).toContain("filemtime(public_path($loginStylesheet))");
    expect(appLayoutSource).toContain('rel="preload" as="image"');
    expect(appLayoutSource).toContain("img[data-cnsc-auth-logo]");
  });

  it("renderiza campos con semántica de autenticación y recuperación", () => {
    const wrapper = mountLogin();

    expect(wrapper.get("#login-title").text()).toBe("Bienvenido de vuelta");
    expect(wrapper.get("#login-email").attributes("type")).toBe("email");
    expect(wrapper.get("#login-email").attributes("autocomplete")).toBe("email");
    expect(wrapper.get("#login-password").attributes("autocomplete")).toBe("current-password");
    expect(wrapper.get('a[href="/forget-password"]').text()).toContain("Olvidaste tu clave");
  });

  it("permite mostrar y volver a ocultar la clave", async () => {
    const wrapper = mountLogin();
    const toggle = wrapper.get(".login-password-toggle");

    expect(wrapper.get("#login-password").attributes("type")).toBe("password");
    await toggle.trigger("click");
    expect(wrapper.get("#login-password").attributes("type")).toBe("text");
    expect(toggle.attributes("aria-label")).toBe("Ocultar clave");
    await toggle.trigger("click");
    expect(wrapper.get("#login-password").attributes("type")).toBe("password");
  });

  it("presenta el error de autenticación y libera el botón", async () => {
    axios.post.mockRejectedValueOnce({ response: { data: { message: "Credenciales incorrectas." } } });
    const wrapper = mountLogin();

    await wrapper.vm.login();
    await flushPromises();

    expect(wrapper.text()).toContain("Credenciales incorrectas.");
    expect(wrapper.vm.processing).toBe(false);
    expect(wrapper.vm.isAuthError).toBe(true);
  });

  it("conserva el flujo exitoso de sesión hacia Inicio", async () => {
    axios.post.mockResolvedValueOnce({
      data: {
        success: true,
        message: "success",
        data: {
          token: "token-prueba",
          user: {
            id: 7,
            name: "Ana Soto",
            email: "ana@example.test",
            user_type: "staff",
            staff_id: 15,
            is_staff: true,
            profile_photo_url: null,
          },
        },
      },
    });
    const wrapper = mountLogin();

    await wrapper.vm.login();

    expect(localStorage.getItem("token")).toBe("token-prueba");
    expect(JSON.parse(localStorage.getItem("user"))).toMatchObject({
      user_id: 7,
      name: "Ana Soto",
      user_type: "staff",
      staff_id: 15,
      is_staff: true,
    });
    expect(routerPush).toHaveBeenCalledWith("/inicio");
  });
});
