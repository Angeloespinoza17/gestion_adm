// @vitest-environment jsdom

import { flushPromises, shallowMount } from "@vue/test-utils";
import { nextTick } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";

const apiHarness = vi.hoisted(() => ({
    subjects: vi.fn(),
    bulkSubjectStatus: vi.fn(),
    externalSubjectCatalog: vi.fn(),
    updateExternalSubjectMappings: vi.fn(),
    books: vi.fn(),
    bulkOpenBooks: vi.fn(),
}));

vi.mock("../../resources/js/services/libro-digital/api", () => ({
    libroDigitalApi: {
        subjects: apiHarness.subjects,
        bulkSubjectStatus: apiHarness.bulkSubjectStatus,
        externalSubjectCatalog: apiHarness.externalSubjectCatalog,
        updateExternalSubjectMappings: apiHarness.updateExternalSubjectMappings,
        books: apiHarness.books,
        bulkOpenBooks: apiHarness.bulkOpenBooks,
    },
}));

vi.mock("../../resources/js/components/libro-digital/module-utils", async () => {
    const original = await vi.importActual(
        "../../resources/js/components/libro-digital/module-utils"
    );
    return {
        ...original,
        confirmAction: vi.fn(() => Promise.resolve({ isConfirmed: true })),
        showSuccess: vi.fn(() => Promise.resolve()),
        showError: vi.fn(() => Promise.resolve()),
    };
});

import BooksSection from "../../resources/js/components/libro-digital/sections/BooksSection.vue";
import SubjectsSection from "../../resources/js/components/libro-digital/sections/SubjectsSection.vue";

const commonStubs = {
    BAlert: { template: "<div><slot /></div>" },
    BButton: {
        props: ["disabled"],
        emits: ["click"],
        template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
    },
    BCard: { template: "<div><slot /></div>" },
    BDropdown: { template: "<div><slot /></div>" },
    BDropdownItem: { template: "<button><slot /></button>" },
    BFormCheckbox: {
        props: ["modelValue", "disabled"],
        emits: ["update:modelValue"],
        template:
            '<label><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
    },
    BFormInput: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template:
            '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    },
    BFormSelect: {
        props: ["modelValue"],
        emits: ["update:modelValue"],
        template:
            '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>',
    },
    BFormTextarea: true,
    BModal: { template: "<div><slot /></div>" },
    LibroDigitalStatePanel: true,
    LibroDigitalStatusBadge: true,
    CurriculumImportPanel: true,
};

const context = { school_id: 1, academic_year_id: 20 };
const capabilities = {
    can_manage_subject_catalog: true,
    can_manage_books: true,
};

describe("gestión escalable del catálogo del Libro Digital", () => {
    beforeEach(() => {
        Object.values(apiHarness).forEach((mock) => mock.mockReset());
        apiHarness.subjects.mockResolvedValue({
            data: [
                {
                    id: 1,
                    name: "Lengua y Literatura",
                    technical_name: "GEN_LENGUA_Y_LITERATURA",
                    code: "LEN",
                    area: "Humanidades",
                    type: "common_plan",
                    education_types: ["basica", "media"],
                    aliases: [],
                    active: false,
                    lock_version: 1,
                },
                {
                    id: 2,
                    name: "Pensamiento matemático",
                    technical_name: "PM",
                    code: "PM",
                    area: "Parvularia",
                    type: "official",
                    education_types: ["parvularia"],
                    aliases: [],
                    active: true,
                    lock_version: 1,
                },
            ],
            meta: {
                education_types: [
                    { value: "parvularia", label: "Educación Parvularia" },
                    { value: "basica", label: "Enseñanza Básica" },
                    { value: "media", label: "Enseñanza Media" },
                ],
                subject_types: [
                    { value: "official", label: "Oficial MINEDUC" },
                    { value: "common_plan", label: "Plan común" },
                ],
            },
        });
        apiHarness.bulkSubjectStatus.mockResolvedValue({
            data: [],
            meta: { changed: 1 },
        });
        apiHarness.externalSubjectCatalog.mockResolvedValue({
            data: [
                {
                    mapping_key: "map-1",
                    scope_code: "basica",
                    scope_label: "Enseñanza Básica",
                    education_type: "basica",
                    external_name: "Lenguaje y Comunicación",
                    mapped_subject_id: null,
                    suggested_subject_id: 1,
                    suggested_subject: { id: 1, name: "Lengua y Literatura" },
                    status: "suggested",
                },
            ],
            meta: { total: 1, mapped: 0, suggested: 1, pending: 0, groups: [] },
        });
        apiHarness.updateExternalSubjectMappings.mockResolvedValue({ data: [], meta: {} });
        apiHarness.books.mockResolvedValue({
            data: [
                {
                    id: 10,
                    display_name: "5° Básico A · Lengua y Literatura",
                    status: "draft",
                    lock_version: 1,
                    course: { display_name: "5° Básico A", education_type: "basica" },
                    subject: { name: "Lengua y Literatura" },
                },
                {
                    id: 11,
                    display_name: "1° Medio A · Matemática",
                    status: "open",
                    lock_version: 3,
                    course: { display_name: "1° Medio A", education_type: "media" },
                    subject: { name: "Matemática" },
                },
            ],
            meta: { total: 2 },
        });
        apiHarness.bulkOpenBooks.mockResolvedValue({
            data: [{ id: 10, result: "opened" }],
            meta: { requested: 1, opened: 1, already_open: 0, failed: 0 },
        });
    });

    it("filtra por tipo de enseñanza y activa una selección sin renombrar la identidad técnica", async () => {
        const wrapper = shallowMount(SubjectsSection, {
            props: { context, capabilities },
            global: { stubs: commonStubs, config: { warnHandler: () => {} } },
        });
        await flushPromises();

        expect(wrapper.text()).toContain("Lengua y Literatura");
        expect(wrapper.text()).toContain("GEN_LENGUA_Y_LITERATURA");
        wrapper.vm.educationType = "parvularia";
        await nextTick();
        expect(wrapper.vm.filtered.map((item) => item.id)).toEqual([2]);

        wrapper.vm.educationType = "";
        wrapper.vm.toggleSelected(1);
        await wrapper.vm.runBulkStatus(true);
        expect(apiHarness.bulkSubjectStatus).toHaveBeenCalledWith({
            school_id: 1,
            subject_ids: [1],
            active: true,
        });
        wrapper.unmount();
    });

    it("permite revisar sugerencias del software anterior antes de confirmar el match", async () => {
        const wrapper = shallowMount(SubjectsSection, {
            props: { context, capabilities },
            global: { stubs: commonStubs, config: { warnHandler: () => {} } },
        });
        await flushPromises();
        await wrapper.vm.openExternalCatalog();
        wrapper.vm.applySuggestions();
        await wrapper.vm.saveExternalMappings();

        expect(apiHarness.updateExternalSubjectMappings).toHaveBeenCalledWith({
            school_id: 1,
            source_system: "legacy_gradebook",
            mappings: [
                {
                    scope_code: "basica",
                    external_name: "Lenguaje y Comunicación",
                    schedule_subject_id: 1,
                },
            ],
        });
        wrapper.unmount();
    });

    it("pagina el catálogo externo y aplica sugerencias solo a la página visible", async () => {
        apiHarness.externalSubjectCatalog.mockResolvedValueOnce({
            data: Array.from({ length: 13 }, (_, index) => ({
                mapping_key: `map-${index + 1}`,
                scope_code: "basica",
                scope_label: "Enseñanza Básica",
                education_type: "basica",
                external_name: `Asignatura externa ${index + 1}`,
                mapped_subject_id: null,
                suggested_subject_id: 1,
                suggested_subject: { id: 1, name: "Lengua y Literatura" },
                status: "suggested",
            })),
            meta: { total: 13, mapped: 0, suggested: 13, pending: 0, groups: [] },
        });
        const wrapper = shallowMount(SubjectsSection, {
            props: { context, capabilities },
            global: { stubs: commonStubs, config: { warnHandler: () => {} } },
        });
        await flushPromises();
        await wrapper.vm.openExternalCatalog();

        expect(wrapper.vm.externalPageItems).toHaveLength(12);
        expect(wrapper.vm.externalPageCount).toBe(2);
        wrapper.vm.applySuggestions();
        expect(wrapper.vm.changedMappings).toHaveLength(12);

        wrapper.vm.externalPage = 2;
        await nextTick();
        wrapper.vm.applySuggestions();
        expect(wrapper.vm.changedMappings).toHaveLength(13);
        wrapper.unmount();
    });

    it("selecciona solo libros activables y envía sus versiones al preflight masivo", async () => {
        const wrapper = shallowMount(BooksSection, {
            props: { context, catalogs: {}, capabilities },
            global: { stubs: commonStubs, config: { warnHandler: () => {} } },
        });
        await flushPromises();

        wrapper.vm.toggleAllActivatable();
        await wrapper.vm.runBulkOpen();
        expect(apiHarness.bulkOpenBooks).toHaveBeenCalledWith({
            school_id: 1,
            academic_year_id: 20,
            allow_unassigned_teacher: true,
            books: [{ id: 10, lock_version: 1 }],
        });
        wrapper.unmount();
    });
});
