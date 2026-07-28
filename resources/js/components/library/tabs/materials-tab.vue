<script>
import axios from "axios";
import Swal from "sweetalert2";
import LoadingState from "../../ui/loading-state.vue";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import {
  confirmLibraryAction,
  downloadPdfReport,
  formatLibraryDate,
  formatLibraryError,
  showLibrarySuccess,
} from "../module-utils";

const bookTypes = ["libro", "diccionario", "enciclopedia"];
const materialIcons = {
  tablet: "bx-tab",
  notebook: "bx-laptop",
  proyector: "bx-slideshow",
  parlante: "bx-speaker",
  juego_educativo: "bx-shapes",
  material_didactico: "bx-palette",
  kit_pedagogico: "bx-package",
  audiovisual: "bx-movie-play",
  otro: "bx-cube",
};
const materialLabels = {
  tablet: "Tablet",
  notebook: "Notebook",
  proyector: "Proyector",
  parlante: "Parlante",
  juego_educativo: "Juego educativo",
  material_didactico: "Material didáctico",
  kit_pedagogico: "Kit pedagógico",
  audiovisual: "Audiovisual",
  otro: "Otro",
};
const optionLabels = {
  donacion: "Donación",
  reposicion: "Reposición",
  inventario_inicial: "Inventario inicial",
  danado: "Dañado",
  en_reparacion: "En reparación",
  dado_de_baja: "Dado de baja",
};
const dateInputValue = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};
const blank = () => ({
  borrower_type: "teacher",
  student_profile_id: null,
  staff_id: null,
  course_section_id: null,
  user_id: null,
  biblioteca_ejemplar_ids: [],
  borrowed_at: dateInputValue(new Date()),
  due_at: dateInputValue(new Date(Date.now() + 7 * 86400000)),
  signature_name: "",
  signature_rut: "",
  delivery_notes: "",
});
const blankMaterial = () => ({
  material_type: "material_didactico",
  title: "",
  subtitle: "",
  main_author: "",
  publisher: "",
  biblioteca_categoria_id: null,
  description: "",
  internal_code: "",
  barcode: "",
  biblioteca_ubicacion_id: null,
  physical_location: "",
  shelf: "",
  section: "",
  general_status: "disponible",
  observations: "",
  quantity: 1,
  ingress_date: dateInputValue(new Date()),
  origin: "inventario_inicial",
  physical_state: "bueno",
});

export default {
  components: { LoadingState, LibraryHelpButton, LibraryStatusBadge },
  props: { catalogs: { type: Object, required: true } },
  emits: ["refresh-catalogs"],
  data() {
    return {
      loading: false,
      error: null,
      loans: [],
      showLoan: false,
      saving: false,
      form: blank(),
      loanStep: 1,
      loanMaterialSearch: "",
      loanMaterialType: null,
      signerMatchesBorrower: true,
      typeFilter: null,
      showMaterial: false,
      materialSaving: false,
      materialForm: blankMaterial(),
    };
  },
  computed: {
    materials() {
      return (this.catalogs.exemplars || []).filter((item) => !bookTypes.includes(item.material_type));
    },
    availableMaterials() {
      return this.materials.filter((item) => item.availability_status === "disponible" && (!this.typeFilter || item.material_type === this.typeFilter));
    },
    materialTypes() {
      return [...new Set(this.materials.map((item) => item.material_type).filter(Boolean))];
    },
    materialTypeOptions() {
      return (this.catalogs.material_types || [])
        .filter((item) => !bookTypes.includes(item.value))
        .map((item) => ({ value: item.value, text: materialLabels[item.value] || item.label }));
    },
    categoryOptions() {
      return [{ value: null, text: "Sin categoría" }].concat(
        (this.catalogs.categories || []).map((item) => ({
          value: item.id,
          text: `${item.name} · ${item.code}`,
        }))
      );
    },
    locationOptions() {
      return [{ value: null, text: "Sin ubicación asignada" }].concat(
        (this.catalogs.locations || []).map((item) => ({
          value: item.id,
          text: `${item.code} · ${item.name}`,
        }))
      );
    },
    originOptions() {
      return (this.catalogs.ejemplar_origins || []).map((item) => ({
        value: item.value,
        text: optionLabels[item.value] || item.label,
      }));
    },
    stateOptions() {
      return (this.catalogs.ejemplar_states || []).map((item) => ({
        value: item.value,
        text: optionLabels[item.value] || item.label,
      }));
    },
    canSaveMaterial() {
      return Boolean(
        this.materialForm.material_type &&
        this.materialForm.title.trim() &&
        Number(this.materialForm.quantity) > 0
      );
    },
    activeLoans() {
      return this.loans.filter((item) => item.obra && !bookTypes.includes(item.obra.material_type) && ["activo", "renovado", "vencido"].includes(item.status));
    },
    loanBaseMaterials() {
      return this.materials.filter((item) => item.availability_status === "disponible");
    },
    loanAvailableMaterials() {
      const search = this.loanMaterialSearch.trim().toLocaleLowerCase("es");
      return this.loanBaseMaterials.filter((item) => {
        if (this.loanMaterialType && item.material_type !== this.loanMaterialType) return false;
        if (!search) return true;
        return [item.code, item.title, item.label, item.material_type, item.location]
          .filter(Boolean)
          .some((value) => String(value).toLocaleLowerCase("es").includes(search));
      });
    },
    loanMaterialTypeOptions() {
      return [{ value: null, text: "Todos los tipos" }].concat(
        [...new Set(this.materials.map((item) => item.material_type).filter(Boolean))]
          .map((type) => ({ value: type, text: this.materialTypeLabel(type) }))
      );
    },
    selectedLoanMaterials() {
      const ids = this.form.biblioteca_ejemplar_ids || [];
      return this.materials.filter((item) => ids.includes(item.id));
    },
    borrowerOptions() {
      if (this.form.borrower_type === "student") return (this.catalogs.students || []).map((item) => ({ value: item.id, text: `${item.name} · ${item.course || ""}` }));
      if (["staff", "teacher"].includes(this.form.borrower_type)) {
        return (this.catalogs.staff || [])
          .filter((item) => this.form.borrower_type === "teacher" ? this.isTeacher(item) : !this.isTeacher(item))
          .map((item) => ({ value: item.id, text: `${item.full_name} · ${item.rut || "Sin RUT"}` }));
      }
      return (this.catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name }));
    },
    borrowerKey() {
      if (this.form.borrower_type === "student") return "student_profile_id";
      if (["staff", "teacher"].includes(this.form.borrower_type)) return "staff_id";
      return "course_section_id";
    },
    hasValidBorrower() {
      return Boolean(this.form[this.borrowerKey]);
    },
    canSubmitLoan() {
      return Boolean(
        this.hasValidBorrower &&
        this.form.biblioteca_ejemplar_ids.length &&
        this.form.borrowed_at &&
        this.form.due_at &&
        this.form.due_at >= this.form.borrowed_at &&
        this.form.signature_name.trim() &&
        this.form.signature_rut.trim()
      );
    },
  },
  mounted() { this.loadLoans(); },
  methods: {
    formatLibraryDate,
    materialIcon(type) {
      return materialIcons[type] || "bx-cube";
    },
    materialTypeLabel(type) {
      return materialLabels[type]
        || (this.catalogs.material_types || []).find((item) => item.value === type)?.label
        || String(type || "Material").replaceAll("_", " ");
    },
    isTeacher(item) {
      const cargo = `${item?.cargo?.slug || ""} ${item?.cargo?.name || ""}`.toLocaleLowerCase("es");
      return /(docent|profesor|teacher|educador)/.test(cargo);
    },
    async loadLoans() {
      this.loading = true;
      try {
        const response = await axios.get("/api/biblioteca/prestamos", { params: { per_page: 100 } });
        this.loans = response.data.data || [];
      } catch (error) { this.error = formatLibraryError(error); }
      finally { this.loading = false; }
    },
    openCreate() {
      this.form = blank();
      this.loanStep = 1;
      this.loanMaterialSearch = "";
      this.loanMaterialType = null;
      this.signerMatchesBorrower = true;
      this.error = null;
      this.showLoan = true;
    },
    openMaterialCreate() {
      this.materialForm = blankMaterial();
      this.error = null;
      this.showMaterial = true;
    },
    openMaterialFromLoan() {
      this.showLoan = false;
      this.$nextTick(() => this.openMaterialCreate());
    },
    manageMaterialCategories() {
      this.showMaterial = false;
      this.$router.push("/biblioteca/categorias");
    },
    async saveMaterial() {
      const quantity = Number(this.materialForm.quantity || 0);
      const confirmed = await confirmLibraryAction({
        title: "Registrar material",
        text: `Se registrará "${this.materialForm.title}" y se crearán ${quantity} unidad(es) con código institucional individual.`,
        confirmButtonText: "Crear material",
      });
      if (!confirmed.isConfirmed) return;

      this.materialSaving = true;
      this.error = null;
      try {
        await axios.post("/api/biblioteca/materiales", {
          ...this.materialForm,
          subtitle: this.materialForm.subtitle || null,
          main_author: this.materialForm.main_author || null,
          publisher: this.materialForm.publisher || null,
          biblioteca_categoria_id: this.materialForm.biblioteca_categoria_id || null,
          description: this.materialForm.description || null,
          internal_code: this.materialForm.internal_code || null,
          barcode: this.materialForm.barcode || null,
          biblioteca_ubicacion_id: this.materialForm.biblioteca_ubicacion_id || null,
          physical_location: this.materialForm.physical_location || null,
          shelf: this.materialForm.shelf || null,
          section: this.materialForm.section || null,
          observations: this.materialForm.observations || null,
          quantity,
        });
        this.showMaterial = false;
        this.$emit("refresh-catalogs");
        await showLibrarySuccess(`${quantity} unidad(es) de material registradas correctamente.`);
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo registrar el material.");
      } finally {
        this.materialSaving = false;
      }
    },
    toggleMaterial(id) {
      const values = this.form.biblioteca_ejemplar_ids;
      if (values.includes(id)) this.form.biblioteca_ejemplar_ids = values.filter((value) => value !== id);
      else this.form.biblioteca_ejemplar_ids.push(id);
    },
    onBorrowerTypeChange() {
      this.form.student_profile_id = null;
      this.form.staff_id = null;
      this.form.course_section_id = null;
      this.form.user_id = null;
      this.form.signature_name = "";
      this.form.signature_rut = "";
      this.signerMatchesBorrower = this.form.borrower_type !== "course";
    },
    selectedBorrower() {
      const source = this.form.borrower_type === "student"
        ? this.catalogs.students
        : ["staff", "teacher"].includes(this.form.borrower_type) ? this.catalogs.staff : this.catalogs.courses;
      return (source || []).find((item) => Number(item.id) === Number(this.form[this.borrowerKey]));
    },
    syncBorrower() {
      if (!this.signerMatchesBorrower || this.form.borrower_type === "course") return;
      const selected = this.selectedBorrower();
      this.form.signature_name = selected?.name || selected?.full_name || "";
      this.form.signature_rut = selected?.rut || "";
    },
    syncSignerMatch(value) {
      this.signerMatchesBorrower = Boolean(value);
      if (this.signerMatchesBorrower) {
        this.syncBorrower();
        return;
      }
      this.form.signature_name = "";
      this.form.signature_rut = "";
    },
    nextLoanStep() {
      if (this.loanStep === 1 && this.hasValidBorrower) this.loanStep = 2;
      else if (this.loanStep === 2 && this.form.biblioteca_ejemplar_ids.length) this.loanStep = 3;
    },
    previousLoanStep() {
      this.loanStep = Math.max(1, this.loanStep - 1);
    },
    async saveLoan() {
      if (!this.canSubmitLoan) return;
      const result = await confirmLibraryAction({ title: "Confirmar préstamo de materiales", text: `Se entregarán ${this.form.biblioteca_ejemplar_ids.length} material(es) bajo una misma operación.`, confirmButtonText: "Registrar préstamo" });
      if (!result.isConfirmed) return;
      this.saving = true;
      try {
        await axios.post("/api/biblioteca/prestamos", this.form);
        this.showLoan = false;
        await this.loadLoans();
        this.$emit("refresh-catalogs");
        await showLibrarySuccess("Materiales entregados correctamente.");
      } catch (error) { this.error = formatLibraryError(error); }
      finally { this.saving = false; }
    },
    async returnLoan(item) {
      const result = await Swal.fire({ title: "Devolver material", input: "select", inputOptions: { bueno: "Bueno", regular: "Regular", danado: "Dañado", perdido: "Perdido" }, showCancelButton: true, confirmButtonText: "Registrar" });
      if (!result.isConfirmed || !result.value) return;
      try {
        await axios.post(`/api/biblioteca/prestamos/${item.id}/return`, { returned_condition: result.value });
        await this.loadLoans();
        this.$emit("refresh-catalogs");
      } catch (error) { this.error = formatLibraryError(error); }
    },
    exportLoan(item) {
      downloadPdfReport(`prestamo-material-${item.loan_code}`, "Ficha de préstamo de material", item.loan_code, [{
        title: "Entrega y firma",
        headers: ["Campo", "Información"],
        rows: [
          ["Material", `${item.obra?.title || ""} · ${item.ejemplar?.code || ""}`],
          ["Receptor", item.borrower_name_snapshot],
          ["RUT", item.borrower_rut_snapshot || item.signature_rut || ""],
          ["Fecha préstamo", formatLibraryDate(item.borrowed_at)],
          ["Fecha devolución", formatLibraryDate(item.due_at)],
          ["Firma", item.signature_name || "____________________________"],
        ],
      }]);
    },
  },
};
</script>

<template>
  <div class="materials-view">
    <section class="materials-head">
      <div><span>MATERIALES Y RECURSOS</span><h5>Inventario y préstamos de materiales</h5><p>Registra y entrega kits, tecnología o material didáctico a cursos, docentes y estudiantes con trazabilidad completa.</p></div>
      <div class="materials-head__actions">
        <LibraryHelpButton title="Ayuda: materiales" text="Registra materiales con sus unidades físicas y luego préstalos individualmente o como un lote." />
        <button
          v-if="catalogs.capabilities?.manage_materials !== false"
          type="button"
          class="head-button head-button--create"
          data-cnsc-action-ignore
          @click="openMaterialCreate"
        >
          <i class="bx bx-plus-circle"></i>
          <span><small>Inventario</small>Registrar material</span>
        </button>
        <button
          type="button"
          class="head-button head-button--loan"
          data-cnsc-action-ignore
          @click="openCreate"
        >
          <i class="bx bx-transfer"></i>
          <span><small>Circulación</small>Nuevo préstamo</span>
        </button>
      </div>
    </section>
    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <div class="material-summary"><div><strong>{{materials.length}}</strong><span>unidades inventariadas</span></div><div><strong>{{availableMaterials.length}}</strong><span>disponibles</span></div><div><strong>{{activeLoans.length}}</strong><span>préstamos activos</span></div></div>
    <div class="row g-3">
      <div class="col-xl-5">
        <BCard class="border-0 shadow-sm h-100">
          <template #header><div class="d-flex align-items-center justify-content-between"><strong>Inventario disponible</strong><BFormSelect v-model="typeFilter" size="sm" class="w-auto" :options="[{value:null,text:'Todos los tipos'}].concat(materialTypes.map((type)=>({value:type,text:type.replaceAll('_',' ')})))" /></div></template>
          <div class="material-grid">
            <article v-for="item in availableMaterials" :key="item.id"><i class="bx" :class="materialIcon(item.material_type)"></i><div><strong>{{item.label.split(' · ').slice(1).join(' · ')}}</strong><span>{{item.code}} · {{materialTypeLabel(item.material_type)}}</span><small>{{item.location || "Sin ubicación"}}</small></div><span class="available-dot">Disponible</span></article>
            <div v-if="!availableMaterials.length" class="materials-empty">
              <span><i class="bx bx-package"></i></span>
              <strong>No hay materiales disponibles</strong>
              <p>{{ materials.length ? "Prueba seleccionando otro tipo de material." : "Registra el primer material para comenzar a controlar su inventario." }}</p>
              <button v-if="!materials.length && catalogs.capabilities?.manage_materials !== false" type="button" data-cnsc-action-ignore @click="openMaterialCreate">
                <i class="bx bx-plus"></i> Registrar primer material
              </button>
            </div>
          </div>
        </BCard>
      </div>
      <div class="col-xl-7">
        <BCard class="border-0 shadow-sm h-100">
          <template #header><strong>Préstamos activos</strong></template>
          <LoadingState v-if="loading" message="Cargando préstamos..." compact />
          <BTable v-else responsive hover :items="activeLoans" :fields="[{key:'material',label:'Material'},{key:'borrower_name_snapshot',label:'Receptor'},{key:'due_at',label:'Devuelve'},{key:'status',label:'Estado'},{key:'actions',label:''}]">
            <template #cell(material)="{item}"><strong>{{item.obra?.title}}</strong><small class="d-block text-muted">{{item.ejemplar?.code}}</small></template>
            <template #cell(due_at)="{item}">{{formatLibraryDate(item.due_at)}}</template>
            <template #cell(status)="{item}"><LibraryStatusBadge :status="item.status" /></template>
            <template #cell(actions)="{item}"><div class="d-flex gap-1"><BButton size="sm" variant="outline-secondary" @click="exportLoan(item)"><i class="bx bx-file"></i></BButton><BButton size="sm" variant="outline-success" @click="returnLoan(item)">Devolver</BButton></div></template>
          </BTable>
        </BCard>
      </div>
    </div>

    <BModal v-model="showMaterial" size="xl" title="Registrar nuevo material" hide-footer scrollable modal-class="material-create-modal">
      <div class="material-form-head">
        <span><i class="bx" :class="materialIcon(materialForm.material_type)"></i></span>
        <div>
          <small>ALTA DE INVENTARIO</small>
          <h5>{{ materialForm.title || "Nuevo material" }}</h5>
          <p>Cada unidad recibirá un código institucional individual para préstamos, inventario y trazabilidad.</p>
        </div>
        <div class="material-quantity-preview">
          <strong>{{ Number(materialForm.quantity || 0) }}</strong>
          <span>unidad{{ Number(materialForm.quantity || 0) === 1 ? "" : "es" }}</span>
        </div>
      </div>

      <section class="material-form-section">
        <header><span>1</span><div><small>IDENTIFICACIÓN</small><h6>¿Qué material se incorpora?</h6></div></header>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Tipo de material *</label>
            <BFormSelect v-model="materialForm.material_type" :options="materialTypeOptions" />
          </div>
          <div class="col-md-8">
            <label class="form-label">Nombre del material *</label>
            <BFormInput v-model="materialForm.title" placeholder="Ej.: Kit de geometría para aula" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Marca o fabricante</label>
            <BFormInput v-model="materialForm.main_author" placeholder="Opcional" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Modelo o versión</label>
            <BFormInput v-model="materialForm.subtitle" placeholder="Ej.: Modelo 2026" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Proveedor</label>
            <BFormInput v-model="materialForm.publisher" placeholder="Empresa o institución" />
          </div>
          <div class="col-md-4">
            <div class="material-field-label">
              <label class="form-label">Categoría interna</label>
              <button type="button" data-cnsc-action-ignore @click="manageMaterialCategories">
                <i class="bx bx-cog"></i> Gestionar categorías
              </button>
            </div>
            <BFormSelect v-model="materialForm.biblioteca_categoria_id" :options="categoryOptions" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Código interno</label>
            <BFormInput v-model="materialForm.internal_code" placeholder="Automático al guardar" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Código de barras o serie general</label>
            <BFormInput v-model="materialForm.barcode" placeholder="Opcional" />
          </div>
        </div>
      </section>

      <section class="material-form-section">
        <header><span>2</span><div><small>UNIDADES Y RECEPCIÓN</small><h6>Cantidad, procedencia y condición inicial</h6></div></header>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Cantidad inicial *</label>
            <BFormInput v-model="materialForm.quantity" type="number" min="1" max="500" />
            <small class="form-hint">Se generará un código por unidad.</small>
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha de ingreso</label>
            <BFormInput v-model="materialForm.ingress_date" type="date" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Origen</label>
            <BFormSelect v-model="materialForm.origin" :options="originOptions" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Estado físico</label>
            <BFormSelect v-model="materialForm.physical_state" :options="stateOptions" />
          </div>
        </div>
      </section>

      <section class="material-form-section">
        <header><span>3</span><div><small>UBICACIÓN Y DETALLE</small><h6>¿Dónde queda almacenado?</h6></div></header>
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label">Ubicación normalizada</label>
            <BFormSelect v-model="materialForm.biblioteca_ubicacion_id" :options="locationOptions" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Estante</label>
            <BFormInput v-model="materialForm.shelf" placeholder="Ej.: EST-TEC-01" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Sección o repisa</label>
            <BFormInput v-model="materialForm.section" placeholder="Ej.: Repisa 2" />
          </div>
          <div class="col-12">
            <label class="form-label">Descripción del contenido</label>
            <BFormTextarea v-model="materialForm.description" rows="2" placeholder="Componentes incluidos, características o uso recomendado." />
          </div>
          <div class="col-12">
            <label class="form-label">Observaciones de recepción</label>
            <BFormTextarea v-model="materialForm.observations" rows="2" placeholder="Condición, faltantes, garantía u otra información relevante." />
          </div>
        </div>
      </section>

      <div class="material-form-actions">
        <button type="button" class="material-cancel" data-cnsc-action-ignore @click="showMaterial=false">Cancelar</button>
        <button type="button" class="material-save" data-cnsc-action-ignore :disabled="materialSaving || !canSaveMaterial" @click="saveMaterial">
          <span v-if="materialSaving" class="spinner-border spinner-border-sm"></span>
          <i v-else class="bx bx-check-circle"></i>
          {{ materialSaving ? "Registrando..." : `Registrar ${Number(materialForm.quantity || 0)} unidad(es)` }}
        </button>
      </div>
    </BModal>

    <BModal v-model="showLoan" size="xl" title="Préstamo de materiales" hide-footer scrollable modal-class="material-loan-modal">
      <div class="loan-steps">
        <span :class="{ active: loanStep === 1, completed: loanStep > 1 }"><i class="bx" :class="loanStep > 1 ? 'bx-check' : 'bx-user'"></i><b>1. Receptor</b></span>
        <span :class="{ active: loanStep === 2, completed: loanStep > 2 }"><i class="bx" :class="loanStep > 2 ? 'bx-check' : 'bx-package'"></i><b>2. Materiales</b></span>
        <span :class="{ active: loanStep === 3 }"><i class="bx bx-calendar-check"></i><b>3. Fecha y firma</b></span>
      </div>

      <section v-if="loanStep === 1" class="loan-step-panel">
        <header class="loan-step-head">
          <span><i class="bx bx-user-pin"></i></span>
          <div><small>PASO 1 DE 3</small><h5>¿Quién recibirá los materiales?</h5><p>Selecciona el tipo de receptor para cargar únicamente las personas o cursos correspondientes.</p></div>
        </header>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Tipo de receptor *</label>
            <BFormSelect
              v-model="form.borrower_type"
              :options="[{value:'teacher',text:'Docente'},{value:'staff',text:'Funcionario'},{value:'course',text:'Curso completo'},{value:'student',text:'Estudiante individual'}]"
              @change="onBorrowerTypeChange"
            />
          </div>
          <div class="col-md-8">
            <label class="form-label">{{ form.borrower_type === "course" ? "Curso" : "Persona" }} *</label>
            <BFormSelect
              v-model="form[borrowerKey]"
              :options="[{ value: null, text: `Selecciona ${form.borrower_type === 'course' ? 'un curso' : 'una persona'}`, disabled: true }].concat(borrowerOptions)"
              @change="syncBorrower"
            />
            <small v-if="!borrowerOptions.length" class="loan-field-warning">No existen registros compatibles con el tipo seleccionado.</small>
          </div>
        </div>
      </section>

      <section v-else-if="loanStep === 2" class="loan-step-panel">
        <header class="loan-step-head">
          <span><i class="bx bx-package"></i></span>
          <div><small>PASO 2 DE 3</small><h5>Selecciona las unidades a entregar</h5><p>Solo aparecen ejemplares disponibles. Puedes combinar distintos tipos en una misma operación.</p></div>
          <div class="loan-selection-count"><strong>{{ form.biblioteca_ejemplar_ids.length }}</strong><span>seleccionado(s)</span></div>
        </header>

        <div class="loan-material-tools">
          <div class="loan-search">
            <i class="bx bx-search"></i>
            <BFormInput v-model="loanMaterialSearch" placeholder="Buscar por nombre, código, tipo o ubicación" />
          </div>
          <BFormSelect v-model="loanMaterialType" :options="loanMaterialTypeOptions" />
        </div>

        <div v-if="loanAvailableMaterials.length" class="material-picker">
          <button
            v-for="item in loanAvailableMaterials"
            :key="item.id"
            type="button"
            :class="{ selected: form.biblioteca_ejemplar_ids.includes(item.id) }"
            @click="toggleMaterial(item.id)"
          >
            <i class="bx" :class="form.biblioteca_ejemplar_ids.includes(item.id) ? 'bx-check-circle' : materialIcon(item.material_type)"></i>
            <span>
              <strong>{{ item.title || item.label.split(" · ").slice(1).join(" · ") }}</strong>
              <small>{{ item.code }} · {{ materialTypeLabel(item.material_type) }}</small>
              <small>{{ item.location || "Sin ubicación" }}</small>
            </span>
          </button>
        </div>

        <div v-else class="loan-material-empty">
          <span><i class="bx bx-package"></i></span>
          <strong>{{ loanBaseMaterials.length ? "No encontramos coincidencias" : "No hay materiales disponibles" }}</strong>
          <p>{{ loanBaseMaterials.length ? "Cambia la búsqueda o limpia el filtro de tipo." : "Registra al menos una unidad de material antes de generar un préstamo." }}</p>
          <button v-if="loanBaseMaterials.length" type="button" @click="loanMaterialSearch='';loanMaterialType=null">Limpiar filtros</button>
          <button v-else-if="catalogs.capabilities?.manage_materials !== false" type="button" @click="openMaterialFromLoan">
            <i class="bx bx-plus"></i> Registrar material
          </button>
        </div>

        <div v-if="selectedLoanMaterials.length" class="selected-materials">
          <small>SELECCIÓN ACTUAL</small>
          <div>
            <button v-for="item in selectedLoanMaterials" :key="`selected-${item.id}`" type="button" @click="toggleMaterial(item.id)">
              {{ item.title || item.code }} <i class="bx bx-x"></i>
            </button>
          </div>
        </div>
      </section>

      <section v-else class="loan-step-panel">
        <header class="loan-step-head">
          <span><i class="bx bx-calendar-check"></i></span>
          <div><small>PASO 3 DE 3</small><h5>Fecha, responsable y firma</h5><p>Revisa el plazo y registra a la persona responsable de la recepción.</p></div>
          <div class="loan-selection-count"><strong>{{ form.biblioteca_ejemplar_ids.length }}</strong><span>material(es)</span></div>
        </header>

        <div class="loan-final-summary">
          <div><small>RECEPTOR</small><strong>{{ selectedBorrower()?.name || selectedBorrower()?.full_name || selectedBorrower()?.display_name || "Sin receptor" }}</strong></div>
          <div><small>ENTREGA</small><strong>{{ form.biblioteca_ejemplar_ids.length }} unidad(es)</strong></div>
          <div><small>PLAZO</small><strong>{{ form.borrowed_at }} al {{ form.due_at }}</strong></div>
        </div>

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Fecha préstamo *</label>
            <BFormInput v-model="form.borrowed_at" type="date" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha devolución *</label>
            <BFormInput v-model="form.due_at" type="date" :min="form.borrowed_at" />
            <small v-if="form.due_at && form.due_at < form.borrowed_at" class="loan-field-warning">Debe ser igual o posterior al préstamo.</small>
          </div>
          <div class="col-md-6 signer-option">
            <BFormCheckbox
              v-if="form.borrower_type !== 'course'"
              v-model="signerMatchesBorrower"
              @update:modelValue="syncSignerMatch"
            >
              La persona receptora también retira y firma
            </BFormCheckbox>
            <div v-else class="course-signer-note"><i class="bx bx-info-circle"></i> Identifica al docente o funcionario responsable del curso.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nombre del firmante *</label>
            <BFormInput v-model="form.signature_name" :readonly="signerMatchesBorrower && form.borrower_type !== 'course'" placeholder="Nombre completo de quien recibe" />
          </div>
          <div class="col-md-6">
            <label class="form-label">RUT del firmante *</label>
            <BFormInput v-model="form.signature_rut" :readonly="signerMatchesBorrower && form.borrower_type !== 'course'" placeholder="12.345.678-9" />
          </div>
          <div class="col-12">
            <label class="form-label">Detalle de entrega</label>
            <BFormTextarea v-model="form.delivery_notes" rows="3" placeholder="Condición, accesorios incluidos, acuerdos u observaciones." />
          </div>
        </div>
      </section>

      <div class="loan-form-actions">
        <button type="button" class="loan-cancel" @click="showLoan=false">Cancelar</button>
        <div>
          <button v-if="loanStep > 1" type="button" class="loan-back" @click="previousLoanStep"><i class="bx bx-left-arrow-alt"></i> Anterior</button>
          <button v-if="loanStep < 3" type="button" class="loan-next" :disabled="loanStep === 1 ? !hasValidBorrower : !form.biblioteca_ejemplar_ids.length" @click="nextLoanStep">
            Siguiente <i class="bx bx-right-arrow-alt"></i>
          </button>
          <button v-else type="button" class="loan-submit" :disabled="saving || !canSubmitLoan" @click="saveLoan">
            <span v-if="saving" class="spinner-border spinner-border-sm"></span>
            <i v-else class="bx bx-check-circle"></i>
            {{ saving ? "Guardando..." : `Registrar préstamo (${form.biblioteca_ejemplar_ids.length})` }}
          </button>
        </div>
      </div>
    </BModal>
  </div>
</template>

<style scoped>
.materials-view{display:flex;flex-direction:column;gap:1rem}.materials-head{background:linear-gradient(135deg,#143d59,#1d6f8f);color:white;border-radius:18px;padding:1.3rem 1.5rem;display:flex;justify-content:space-between;align-items:center}.materials-head>div:first-child>span{font-size:.66rem;font-weight:800;letter-spacing:.14em;color:#aee9ff}.materials-head h5{color:white;margin:.2rem 0}.materials-head p{color:rgba(255,255,255,.7);margin:0}.material-summary{display:grid;grid-template-columns:repeat(3,1fr);background:#fff;border:1px solid #e5eaf1;border-radius:14px;overflow:hidden}.material-summary div{display:flex;align-items:baseline;justify-content:center;gap:.4rem;padding:.8rem}.material-summary div+div{border-left:1px solid #e8ecf2}.material-summary strong{font-size:1.2rem;color:#225b78}.material-summary span{font-size:.7rem;color:#8792a1}.material-grid{display:grid;gap:.45rem;max-height:560px;overflow:auto}.material-grid article{display:flex;align-items:center;gap:.6rem;padding:.65rem;background:#f7f9fc;border-radius:10px}.material-grid article>i{font-size:1.3rem;color:#2d7c9d}.material-grid article>div{display:flex;flex:1;flex-direction:column}.material-grid article span,.material-grid article small{font-size:.67rem;color:#8793a4}.available-dot{background:#e8f8f1;color:#2d906c!important;padding:.2rem .4rem;border-radius:99px}.loan-steps{display:grid;grid-template-columns:repeat(3,1fr);background:#f2f5fa;border-radius:10px;padding:.3rem;margin-bottom:1rem}.loan-steps span{text-align:center;padding:.45rem;font-size:.72rem;color:#8490a2}.loan-steps span.active{background:white;color:#296f90;border-radius:8px;font-weight:700;box-shadow:0 3px 10px rgba(30,50,75,.08)}.material-picker{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;max-height:300px;overflow:auto}.material-picker button{border:1px solid #dde4ed;background:#fff;border-radius:11px;padding:.65rem;display:flex;gap:.5rem;text-align:left;color:#6d798b}.material-picker button.selected{border-color:#2f8aab;background:#eefaff;color:#1f6a86}.material-picker button>span{display:flex;flex-direction:column}.material-picker small{font-size:.65rem;color:#8994a4}

.materials-head__actions {
  display: flex;
  align-items: center;
  gap: .55rem;
}

.head-button {
  min-height: 48px;
  display: inline-flex;
  align-items: center;
  gap: .55rem;
  padding: .45rem .75rem;
  border-radius: 12px !important;
  font-size: .69rem;
  font-weight: 750;
  text-align: left;
  transition: transform .15s ease, box-shadow .15s ease;
}

.head-button:hover {
  transform: translateY(-1px);
}

.head-button > i {
  font-size: 1.15rem;
}

.head-button > span {
  display: flex;
  flex-direction: column;
  color: inherit;
  font-size: inherit;
  letter-spacing: 0;
}

.head-button small {
  color: inherit;
  font-size: .5rem;
  font-weight: 800;
  letter-spacing: .08em;
  opacity: .65;
  text-transform: uppercase;
}

.head-button--create {
  border: 1px solid rgba(174, 233, 255, .28) !important;
  color: #ecfbff;
  background: rgba(255, 255, 255, .1);
}

.head-button--loan {
  border: 1px solid #fff !important;
  color: #205673;
  background: #fff;
  box-shadow: 0 8px 20px rgba(11, 43, 63, .14);
}

.materials-empty {
  min-height: 265px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  padding: 2rem 1rem;
  text-align: center;
}

.materials-empty > span {
  width: 56px;
  height: 56px;
  display: grid;
  place-items: center;
  margin-bottom: .7rem;
  border-radius: 16px;
  color: #247b99;
  background: #eaf6fa;
  font-size: 1.4rem;
}

.materials-empty strong {
  color: #34475c;
  font-size: .78rem;
}

.materials-empty p {
  max-width: 280px;
  margin: .3rem 0 .8rem;
  color: #8793a4;
  font-size: .66rem;
}

.materials-empty button {
  min-height: 36px;
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .45rem .8rem;
  border: 0 !important;
  border-radius: 9px !important;
  color: #fff;
  background: #287e9c;
  font-size: .65rem;
  font-weight: 750;
}

:deep(.material-create-modal .modal-content) {
  overflow: hidden;
  border: 0;
  border-radius: 18px;
  box-shadow: 0 22px 70px rgba(31, 52, 80, .22);
}

.material-form-head {
  display: grid;
  grid-template-columns: 50px minmax(0, 1fr) auto;
  align-items: center;
  gap: .85rem;
  padding: .9rem 1rem;
  margin-bottom: .8rem;
  border: 1px solid #d9e7ec;
  border-radius: 14px;
  background: linear-gradient(135deg, #f1f8fb, #f3faf7);
}

.material-form-head > span {
  width: 48px;
  height: 48px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  color: #237d9c;
  background: #dff2f8;
  font-size: 1.35rem;
}

.material-form-head small,
.material-form-section header small {
  color: #4c8297;
  font-size: .55rem;
  font-weight: 800;
  letter-spacing: .11em;
}

.material-form-head h5 {
  margin: .12rem 0;
  color: #30445a;
  font-size: .92rem;
}

.material-form-head p {
  margin: 0;
  color: #7c8998;
  font-size: .66rem;
}

.material-quantity-preview {
  min-width: 76px;
  padding: .55rem .7rem;
  border: 1px solid #cee4dc;
  border-radius: 11px;
  text-align: center;
  background: rgba(255,255,255,.75);
}

.material-quantity-preview strong,
.material-quantity-preview span {
  display: block;
}

.material-quantity-preview strong {
  color: #247b65;
  font-size: 1.05rem;
}

.material-quantity-preview span {
  color: #71857e;
  font-size: .56rem;
}

.material-form-section {
  padding: .85rem .95rem .95rem;
  margin-top: .65rem;
  border: 1px solid #e1e8ef;
  border-radius: 14px;
  background: #fff;
}

.material-form-section > header {
  display: flex;
  align-items: center;
  gap: .6rem;
  margin-bottom: .75rem;
}

.material-form-section > header > span {
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  color: #267d9c;
  background: #e5f4f8;
  font-size: .68rem;
  font-weight: 800;
}

.material-form-section header h6 {
  margin: .04rem 0 0;
  color: #35485e;
  font-size: .78rem;
}

.material-form-section .form-label {
  margin-bottom: .32rem;
  color: #4d5c6f;
  font-size: .66rem;
  font-weight: 750;
}

.material-field-label {
  min-height: 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
}

.material-field-label button {
  display: inline-flex;
  align-items: center;
  gap: .2rem;
  padding: 0;
  border: 0 !important;
  color: #287e9c;
  background: transparent;
  font-size: .55rem;
  font-weight: 750;
}

.material-field-label button:hover {
  color: #1c657d;
  text-decoration: underline;
}

.material-form-section :deep(.form-control),
.material-form-section :deep(.form-select),
.material-form-section :deep(.input-group-text) {
  min-height: 42px;
  border-color: #dce4ed;
  border-radius: 10px;
  font-size: .7rem;
  box-shadow: none;
}

.material-form-section :deep(.input-group > .input-group-text) {
  border-radius: 10px 0 0 10px;
  color: #397087;
  background: #f1f7f9;
}

.material-form-section :deep(.input-group > .form-control) {
  border-radius: 0 10px 10px 0;
}

.material-form-section :deep(.form-control:focus),
.material-form-section :deep(.form-select:focus) {
  border-color: #67abc0;
  box-shadow: 0 0 0 3px rgba(43, 133, 161, .1);
}

.form-hint {
  display: block;
  margin-top: .25rem;
  color: #8794a5;
  font-size: .58rem;
}

.material-form-actions {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: flex-end;
  gap: .55rem;
  padding-top: .9rem;
  background: linear-gradient(to bottom, rgba(255,255,255,0), #fff 25%);
}

.material-cancel,
.material-save {
  min-height: 40px;
  padding: .5rem 1rem;
  border-radius: 10px !important;
  font-size: .7rem;
  font-weight: 750;
}

.material-cancel {
  border: 1px solid #dce4ed !important;
  color: #667589;
  background: #fff;
}

.material-save {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  border: 0 !important;
  color: #fff;
  background: linear-gradient(135deg, #247b99, #2b9a83);
  box-shadow: 0 8px 18px rgba(35, 125, 139, .2);
}

.material-save:disabled {
  opacity: .45;
  box-shadow: none;
}

:deep(.material-loan-modal .modal-content) {
  overflow: hidden;
  border: 0;
  border-radius: 20px;
  box-shadow: 0 26px 80px rgba(25, 43, 67, .24);
}

:deep(.material-loan-modal .modal-header) {
  padding: 1rem 1.35rem;
  border-bottom-color: #e8edf3;
}

:deep(.material-loan-modal .modal-body) {
  padding: 1rem 1.25rem 1.15rem;
  background: #fbfcfe;
}

.loan-steps {
  gap: .35rem;
  padding: .35rem;
  margin-bottom: .9rem;
  border: 1px solid #e6ebf2;
  border-radius: 13px;
  background: #f2f5f9;
}

.loan-steps span {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .35rem;
  min-height: 42px;
  padding: .45rem .6rem;
  border-radius: 9px;
  color: #8793a4;
  font-size: .66rem;
  transition: color .16s ease, background .16s ease;
}

.loan-steps span i {
  font-size: .9rem;
}

.loan-steps span.active {
  color: #236f8c;
  background: #fff;
  box-shadow: 0 4px 12px rgba(34, 62, 88, .08);
}

.loan-steps span.completed {
  color: #25846c;
  background: #ebf8f3;
}

.loan-step-panel {
  min-height: 360px;
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 15px;
  background: #fff;
}

.loan-step-panel .form-label {
  margin-bottom: .35rem;
  color: #4d5c6f;
  font-size: .66rem;
  font-weight: 750;
}

.loan-step-panel :deep(.form-control),
.loan-step-panel :deep(.form-select) {
  min-height: 44px;
  border-color: #dce4ed;
  border-radius: 10px;
  font-size: .7rem;
  box-shadow: none;
}

.loan-step-panel :deep(.form-control:focus),
.loan-step-panel :deep(.form-select:focus) {
  border-color: #67abc0;
  box-shadow: 0 0 0 3px rgba(43, 133, 161, .1);
}

.loan-step-head {
  display: flex;
  align-items: center;
  gap: .7rem;
  padding-bottom: .85rem;
  margin-bottom: .9rem;
  border-bottom: 1px solid #edf0f4;
}

.loan-step-head > span {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  color: #247995;
  background: #e8f5f9;
  font-size: 1.1rem;
}

.loan-step-head > div:nth-child(2) {
  min-width: 0;
  flex: 1;
}

.loan-step-head small,
.selected-materials > small {
  color: #4b8398;
  font-size: .54rem;
  font-weight: 800;
  letter-spacing: .11em;
}

.loan-step-head h5 {
  margin: .08rem 0;
  color: #34485e;
  font-size: .84rem;
}

.loan-step-head p {
  margin: 0;
  color: #8390a0;
  font-size: .64rem;
}

.loan-selection-count {
  min-width: 86px;
  padding: .5rem .65rem;
  border: 1px solid #d4e7df;
  border-radius: 10px;
  text-align: center;
  background: #f0faf6;
}

.loan-selection-count strong,
.loan-selection-count span {
  display: block;
}

.loan-selection-count strong {
  color: #258169;
  font-size: 1rem;
}

.loan-selection-count span {
  color: #758b83;
  font-size: .54rem;
}

.loan-material-tools {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 210px;
  gap: .6rem;
  margin-bottom: .7rem;
}

.loan-search {
  position: relative;
}

.loan-search > i {
  position: absolute;
  z-index: 2;
  top: 50%;
  left: .8rem;
  color: #8390a0;
  transform: translateY(-50%);
}

.loan-search :deep(.form-control) {
  padding-left: 2.25rem;
}

.material-picker {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  max-height: 285px;
  padding: .15rem;
}

.material-picker button {
  min-height: 78px;
  align-items: flex-start;
  border-color: #e0e6ee;
  padding: .7rem;
  transition: transform .14s ease, border-color .14s ease, box-shadow .14s ease;
}

.material-picker button:hover {
  transform: translateY(-1px);
  border-color: #90c0d0;
  box-shadow: 0 6px 15px rgba(31, 67, 88, .07);
}

.material-picker button > i {
  margin-top: .05rem;
  color: #3c829c;
  font-size: 1.05rem;
}

.material-picker button.selected {
  border-color: #3a9a83;
  color: #245e51;
  background: #edf9f5;
  box-shadow: 0 0 0 2px rgba(49, 148, 124, .08);
}

.material-picker button.selected > i {
  color: #2c9277;
}

.material-picker button > span {
  min-width: 0;
}

.material-picker strong {
  overflow: hidden;
  color: #3d4c60;
  font-size: .7rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.material-picker small {
  margin-top: .12rem;
}

.loan-material-empty {
  min-height: 235px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  padding: 1.5rem;
  border: 1px dashed #cfdae6;
  border-radius: 13px;
  text-align: center;
  background: #f8fafc;
}

.loan-material-empty > span {
  width: 50px;
  height: 50px;
  display: grid;
  place-items: center;
  margin-bottom: .55rem;
  border-radius: 14px;
  color: #327d97;
  background: #e7f4f8;
  font-size: 1.3rem;
}

.loan-material-empty strong {
  color: #3d4d61;
  font-size: .76rem;
}

.loan-material-empty p {
  max-width: 350px;
  margin: .3rem 0 .7rem;
  color: #8390a0;
  font-size: .64rem;
}

.loan-material-empty button {
  min-height: 36px;
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .42rem .75rem;
  border: 0 !important;
  border-radius: 9px !important;
  color: #fff;
  background: #287f9d;
  font-size: .62rem;
  font-weight: 750;
}

.selected-materials {
  padding-top: .65rem;
  margin-top: .65rem;
  border-top: 1px solid #edf0f4;
}

.selected-materials > div {
  display: flex;
  flex-wrap: wrap;
  gap: .35rem;
  margin-top: .35rem;
}

.selected-materials button {
  display: inline-flex;
  align-items: center;
  gap: .25rem;
  padding: .28rem .5rem;
  border: 1px solid #cbe5dc !important;
  border-radius: 99px !important;
  color: #26735f;
  background: #eff9f5;
  font-size: .57rem;
  font-weight: 700;
}

.loan-final-summary {
  display: grid;
  grid-template-columns: 2fr 1fr 1.4fr;
  margin-bottom: .9rem;
  border: 1px solid #e1e7ef;
  border-radius: 11px;
  overflow: hidden;
  background: #f8fafc;
}

.loan-final-summary > div {
  padding: .6rem .7rem;
}

.loan-final-summary > div + div {
  border-left: 1px solid #e3e8ef;
}

.loan-final-summary small,
.loan-final-summary strong {
  display: block;
}

.loan-final-summary small {
  color: #8995a5;
  font-size: .5rem;
  font-weight: 800;
  letter-spacing: .08em;
}

.loan-final-summary strong {
  overflow: hidden;
  margin-top: .15rem;
  color: #3d4c60;
  font-size: .65rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.signer-option {
  display: flex;
  align-items: flex-end;
  padding-bottom: .6rem;
}

.signer-option :deep(.form-check-label) {
  color: #526276;
  font-size: .65rem;
}

.course-signer-note {
  width: 100%;
  display: flex;
  align-items: center;
  gap: .35rem;
  padding: .55rem .65rem;
  border-radius: 9px;
  color: #557285;
  background: #edf6f9;
  font-size: .62rem;
}

.loan-field-warning {
  display: block;
  margin-top: .25rem;
  color: #c05060;
  font-size: .58rem;
}

.loan-form-actions {
  position: sticky;
  bottom: -1.15rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .7rem;
  padding: 1.1rem 0 .1rem;
  background: linear-gradient(to bottom, rgba(251,252,254,0), #fbfcfe 28%);
}

.loan-form-actions > div {
  display: flex;
  gap: .45rem;
}

.loan-cancel,
.loan-back,
.loan-next,
.loan-submit {
  min-height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .3rem;
  padding: .48rem .85rem;
  border-radius: 10px !important;
  font-size: .66rem;
  font-weight: 750;
}

.loan-cancel,
.loan-back {
  border: 1px solid #dce3ec !important;
  color: #667589;
  background: #fff;
}

.loan-next,
.loan-submit {
  border: 0 !important;
  color: #fff;
  background: linear-gradient(135deg, #287f9d, #2b947d);
  box-shadow: 0 8px 18px rgba(38, 126, 139, .18);
}

.loan-next:disabled,
.loan-submit:disabled {
  cursor: not-allowed;
  opacity: .42;
  box-shadow: none;
}

@media(max-width:800px){
  .materials-head{align-items:flex-start;flex-direction:column;gap:1rem}
  .materials-head__actions{width:100%;flex-wrap:wrap}
  .head-button{flex:1;min-width:140px}
  .material-summary{grid-template-columns:1fr}
  .material-summary div+div{border-left:0;border-top:1px solid #e8ecf2}
  .material-picker{grid-template-columns:1fr 1fr}
  .loan-final-summary{grid-template-columns:1fr}
  .loan-final-summary>div+div{border-left:0;border-top:1px solid #e3e8ef}
}

@media(max-width:576px) {
  .material-form-head {
    grid-template-columns: 42px minmax(0, 1fr);
  }

  .material-form-head > span {
    width: 40px;
    height: 40px;
  }

  .material-quantity-preview {
    grid-column: 1 / -1;
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: .35rem;
  }

  .material-form-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .loan-steps span {
    flex-direction: column;
    gap: .12rem;
  }

  .loan-step-head {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .loan-selection-count {
    width: 100%;
  }

  .loan-material-tools {
    grid-template-columns: 1fr;
  }

  .material-picker {
    grid-template-columns: 1fr;
  }

  .loan-form-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .loan-form-actions > div {
    display: grid;
    grid-template-columns: 1fr;
  }

  .loan-form-actions button {
    width: 100%;
  }
}

@media(max-width:480px){.material-picker{grid-template-columns:1fr}.materials-head__actions{display:grid;grid-template-columns:1fr}.head-button{width:100%}}
</style>
