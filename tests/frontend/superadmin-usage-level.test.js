// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import UsageLevel from "../../resources/js/views/superadmin/usage-level.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn() },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state' />" },
}));

const staffUser = {
  id: 8,
  name: "Camila Funcionaria",
  email: "camila@cnsc.cl",
  group: "staff",
  group_label: "Funcionario/a",
  profile_label: "Docente",
  active: true,
  login_count: 5,
  usage_count: 13,
  active_days: 6,
  last_activity_at: "2026-08-28 13:00:00",
  last_login_at: "2026-08-28 09:00:00",
};

const listResponse = {
  data: [staffUser],
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 1,
  from: 1,
  to: 1,
  summary: {
    total_users: 1,
    active_accounts: 1,
    users_with_usage: 1,
    users_without_usage: 0,
    adoption_rate: 100,
    login_count: 5,
    usage_count: 13,
    active_days: 6,
    last_activity_at: "2026-08-28 13:00:00",
  },
  groups: {
    staff: { key: "staff", label: "Funcionarios", total_users: 140, active_accounts: 138, users_with_usage: 90, adoption_rate: 64.3, login_count: 510, usage_count: 1400 },
    student: { key: "student", label: "Estudiantes", total_users: 94, active_accounts: 94, users_with_usage: 52, adoption_rate: 55.3, login_count: 230, usage_count: 620 },
  },
  period: { key: "30", label: "Últimos 30 días", from: "2026-08-03", to: "2026-09-01" },
  tracking: {
    started_at: "2026-08-20",
    activity_window_minutes: 10,
    historical_note: "Las métricas se registran desde la instalación de esta función.",
  },
};

const detailResponse = {
  user: { ...staffUser, roles: [{ slug: "docente", name: "Docente" }] },
  totals: {
    login_count: 5,
    usage_count: 13,
    active_days: 2,
    first_activity_at: "2026-08-20 08:00:00",
    last_activity_at: "2026-08-28 13:00:00",
    last_login_at: "2026-08-28 09:00:00",
  },
  timeline: [
    { date: "2026-08-20", logins: 3, usage: 8 },
    { date: "2026-08-28", logins: 2, usage: 5 },
  ],
  period: listResponse.period,
};

const mountView = () => mount(UsageLevel, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { template: "<div class='loading-state' />" },
      BAlert: { props: ["show"], template: "<div v-if='show'><slot /></div>" },
      BPagination: { template: "<nav class='pagination-stub' />" },
    },
  },
});

describe("Nivel de uso de Superadmin", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.get.mockImplementation((url) => {
      if (url.endsWith("/8")) return Promise.resolve({ data: detailResponse });
      return Promise.resolve({ data: listResponse });
    });
  });

  it("separates staff and students and explains the privacy-aware measurement", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Nivel de uso");
    expect(wrapper.text()).toContain("Funcionarios");
    expect(wrapper.text()).toContain("Estudiantes");
    expect(wrapper.text()).toContain("Ingresos exitosos");
    expect(wrapper.text()).toContain("Bloques de actividad");
    expect(wrapper.text()).toContain("sin guardar las páginas ni los contenidos consultados");
    expect(wrapper.findAll(".usage-segment")).toHaveLength(2);
  });

  it("sends the selected group, period and production filters to the paginated endpoint", async () => {
    const wrapper = mountView();
    await flushPromises();

    await wrapper.setData({
      filters: {
        group: "student",
        period: "90",
        search: "Antonia",
        account_status: "active",
        usage_status: "with_usage",
        sort: "logins",
        direction: "desc",
      },
    });
    await wrapper.vm.loadUsers(2);

    expect(axios.get).toHaveBeenLastCalledWith("/api/superadmin/usage-level/users", {
      params: {
        page: 2,
        per_page: 20,
        group: "student",
        period: "90",
        search: "Antonia",
        account_status: "active",
        usage_status: "with_usage",
        sort: "logins",
        direction: "desc",
      },
    });
  });

  it("selects one user and opens a read-only daily activity detail", async () => {
    const wrapper = mountView();
    await flushPromises();

    await wrapper.find(".usage-select-button").trigger("click");
    await flushPromises();

    expect(axios.get).toHaveBeenCalledWith("/api/superadmin/usage-level/users/8", { params: { period: "30" } });
    expect(wrapper.find(".usage-detail").exists()).toBe(true);
    expect(wrapper.find(".usage-detail").text()).toContain("Detalle individual · Sólo lectura");
    expect(wrapper.find(".usage-detail").text()).toContain("Actividad por día registrado");
    expect(wrapper.findAll(".usage-timeline__row")).toHaveLength(2);
    expect(wrapper.vm.formatDate("2026-09-01", false)).toContain("01");
    expect(wrapper.vm.formatDate("2026-09-01", false)).not.toContain("31");
    expect(wrapper.find(".usage-detail").text()).not.toContain("Editar");
  });
});
