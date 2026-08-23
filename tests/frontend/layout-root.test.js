// @vitest-environment jsdom

import { shallowMount } from "@vue/test-utils";
import { createApp } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import App from "../../resources/js/App.vue";
import MainLayout from "../../resources/js/layouts/main.vue";

vi.mock("../../resources/js/layouts/vertical.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));
vi.mock("../../resources/js/layouts/horizontal.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

describe("layout root API", () => {
  beforeEach(() => {
    localStorage.clear();
    document.body.removeAttribute("data-sidebar");
  });

  it("exposes the shared layout methods to child components", () => {
    const wrapper = shallowMount(App, {
      global: {
        mocks: { $route: { path: "/login" } },
        stubs: { RouterView: true },
      },
    });

    expect(typeof wrapper.vm.changeSidebar).toBe("function");
    expect(typeof wrapper.vm.loadRightCollapse).toBe("function");
    expect(wrapper.vm.layout.type).toBe("vertical");

    wrapper.vm.changeSidebar("light");
    expect(document.body.getAttribute("data-topbar")).toBe("dark");
  });

  it("applies a saved sidebar while the main layout is being created", () => {
    localStorage.setItem("layout", JSON.stringify({
      type: "vertical",
      sidebar: "light",
      width: "fluid",
      topbar: "light",
      mode: "light",
      loader: false,
    }));

    const RouterView = {
      components: { MainLayout },
      template: "<MainLayout />",
    };

    const host = document.createElement("div");
    document.body.appendChild(host);
    const app = createApp(App);
    app.config.globalProperties.$route = { path: "/inicio" };
    app.component("RouterView", RouterView);
    app.mount(host);

    expect(document.body.getAttribute("data-topbar")).toBe("dark");
    app.unmount();
    host.remove();
  });
});
