// @vitest-environment jsdom

import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import StudentMedicalSummary from "../../resources/js/components/infirmary/student-medical-summary.vue";

const restriction = {
  id: 91,
  restriction_type: "orden_alejamiento",
  restriction_type_label: "Orden de alejamiento",
  reason: "Medida vigente informada por tribunal.",
  source_label: "Trabajo Social",
};

const restrictedContact = {
  type: "primary",
  label: "Apoderado principal",
  name: "María Rojas",
  phone: "+56 9 1111 1111",
  email: "maria@example.test",
  has_active_restriction: true,
  contact_guidance: "No contactar sin validar el protocolo y la medida vigente con Trabajo Social.",
  restrictions: [restriction],
};

const context = {
  full_name: "Josefina Rojas",
  rut: "22.333.444-5",
  course: "7° Básico A",
  age: 13,
  medical_alerts: [],
  permanent_medications: [],
  guardian_restrictions: [restriction],
  emergency_contacts: [restrictedContact],
};

describe("Alertas de restricciones de apoderados en Enfermería", () => {
  it("highlights the restricted guardian in the reusable medical summary", () => {
    const wrapper = mount(StudentMedicalSummary, { props: { context } });

    expect(wrapper.text()).toContain("Restricción de contacto configurada por Trabajo Social");
    expect(wrapper.text()).toContain("No contactar sin validar");
    expect(wrapper.text()).toContain("Orden de alejamiento");
    expect(wrapper.find(".medical-summary__guardian-grid > .is-restricted").exists()).toBe(true);
  });
});
