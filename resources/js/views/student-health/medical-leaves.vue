<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const localToday = () => {
  const date = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
  return date.toISOString().slice(0, 10);
};

const emptyForm = () => ({
  student_profile_id: null,
  starts_on: localToday(),
  ends_on: "",
  reason: "",
  is_permanent: false,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: true,
      saving: false,
      error: null,
      items: [],
      filters: { search: "", status: "", permanent: false },
      summary: { total_records: 0, permanent_records: 0, chronic_students: 0, ending_soon: 0 },
      capabilities: { can_create: false },
      pagination: { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 },
      showModal: false,
      form: emptyForm(),
      studentSearch: "",
      studentOptions: [],
      studentSearchLoading: false,
      studentSearchTimer: null,
      studentSearchSequence: 0,
    };
  },
  computed: {
    sourceModule() {
      return this.$route.path.startsWith("/inspectoria") ? "inspectoria" : "infirmary";
    },
    moduleLabel() {
      return this.sourceModule === "inspectoria" ? "Inspectoría" : "Enfermería";
    },
    moduleIcon() {
      return this.sourceModule === "inspectoria" ? "bx-shield-quarter" : "bx-plus-medical";
    },
    sourceBadge() {
      return this.sourceModule === "inspectoria" ? "CONTROL OPERATIVO" : "SALUD ESCOLAR";
    },
    studentDatalistOptions() {
      return this.studentOptions.map((student) => ({
        ...student,
        label: [student.name, student.rut || "Sin RUT", student.course || "Sin curso"].join(" · "),
      }));
    },
    selectedStudent() {
      return this.studentDatalistOptions.find((student) => Number(student.id) === Number(this.form.student_profile_id)) || null;
    },
    activeFilterCount() {
      return Number(Boolean(this.filters.search.trim())) + Number(Boolean(this.filters.status)) + Number(this.filters.permanent);
    },
  },
  watch: {
    "$route.path"() {
      this.closeModal();
      this.load(1);
    },
    "form.is_permanent"(value) {
      if (value) this.form.ends_on = "";
    },
  },
  mounted() {
    this.load(1);
  },
  beforeUnmount() {
    window.clearTimeout(this.studentSearchTimer);
  },
  methods: {
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get("/api/student-medical-leaves", {
          params: {
            page,
            search: this.filters.search.trim() || null,
            status: this.filters.status || null,
            permanent: this.filters.permanent ? 1 : null,
          },
        });
        this.items = data.data || [];
        this.summary = { ...this.summary, ...(data.summary || {}) };
        this.capabilities = { ...this.capabilities, ...(data.capabilities || {}) };
        this.pagination = {
          current_page: data.current_page || 1,
          last_page: data.last_page || 1,
          total: data.total || 0,
          from: data.from || 0,
          to: data.to || 0,
        };
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible cargar las licencias médicas.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = { search: "", status: "", permanent: false };
      this.load(1);
    },
    togglePermanentFilter() {
      this.filters.permanent = !this.filters.permanent;
      this.load(1);
    },
    openCreate() {
      this.form = emptyForm();
      this.studentSearch = "";
      this.studentOptions = [];
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.form = emptyForm();
      this.studentSearch = "";
      this.studentOptions = [];
      this.studentSearchLoading = false;
      window.clearTimeout(this.studentSearchTimer);
    },
    onStudentInput() {
      const exact = this.studentDatalistOptions.find((student) => student.label === this.studentSearch);
      this.form.student_profile_id = exact?.id || null;
      window.clearTimeout(this.studentSearchTimer);
      if (this.studentSearch.trim().length < 2 || exact) return;
      this.studentSearchTimer = window.setTimeout(() => this.searchStudents(), 280);
    },
    syncSelectedStudent() {
      const exact = this.studentDatalistOptions.find((student) => student.label === this.studentSearch);
      this.form.student_profile_id = exact?.id || null;
    },
    async searchStudents() {
      const search = this.studentSearch.trim();
      if (search.length < 2) return;
      const sequence = ++this.studentSearchSequence;
      this.studentSearchLoading = true;
      try {
        const { data } = await axios.get("/api/student-medical-leaves/students", { params: { search } });
        if (sequence !== this.studentSearchSequence || search !== this.studentSearch.trim()) return;
        this.studentOptions = data.data || [];
        this.syncSelectedStudent();
      } catch (error) {
        if (sequence === this.studentSearchSequence) {
          this.error = this.errorMessage(error, "No fue posible buscar alumnas.");
        }
      } finally {
        if (sequence === this.studentSearchSequence) this.studentSearchLoading = false;
      }
    },
    async save() {
      this.syncSelectedStudent();
      if (!this.form.student_profile_id) return this.warn("Selecciona una alumna desde las sugerencias del datalist.");
      if (!this.form.starts_on) return this.warn("Ingresa la fecha de inicio.");
      if (!this.form.is_permanent && !this.form.ends_on) return this.warn("Ingresa la fecha de término o marca la condición como permanente.");
      if (this.form.ends_on && this.form.ends_on < this.form.starts_on) return this.warn("La fecha de término no puede ser anterior al inicio.");
      if (!this.form.reason.trim()) return this.warn("Ingresa el motivo de la licencia o certificado médico.");

      this.saving = true;
      try {
        const wasPermanent = this.form.is_permanent;
        const { data } = await axios.post("/api/student-medical-leaves", {
          ...this.form,
          reason: this.form.reason.trim(),
          ends_on: this.form.ends_on || null,
          source_module: this.sourceModule,
        });
        this.closeModal();
        await this.load(1);
        await Swal.fire({
          icon: "success",
          title: wasPermanent ? "Condición permanente registrada" : "Licencia ingresada",
          text: data.message,
          confirmButtonText: "Entendido",
        });
      } catch (error) {
        await Swal.fire({
          icon: "error",
          title: "No se pudo guardar",
          text: this.errorMessage(error, "Revisa los datos e intenta nuevamente."),
          confirmButtonText: "Entendido",
        });
      } finally {
        this.saving = false;
      }
    },
    statusLabel(status) {
      return { permanent: "Permanente", active: "Vigente", scheduled: "Programada", ended: "Finalizada" }[status] || "Registrada";
    },
    sourceLabel(source) {
      return source === "inspectoria" ? "Inspectoría" : source === "infirmary" ? "Enfermería" : "Registro previo";
    },
    formatDate(value) {
      if (!value) return "Sin término";
      return new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "short", year: "numeric" })
        .format(new Date(`${String(value).slice(0, 10)}T12:00:00`));
    },
    errorMessage(error, fallback) {
      const errors = error?.response?.data?.errors || {};
      const first = Object.values(errors)[0];
      return first?.[0] || error?.response?.data?.message || fallback;
    },
    warn(text) {
      return Swal.fire({ icon: "warning", title: "Revisa el formulario", text, confirmButtonText: "Entendido" });
    },
  },
};
</script>

<template>
  <Layout>
    <main class="medical-leaves-page">
      <header class="medical-hero">
        <div class="medical-hero__icon"><i class="bx" :class="moduleIcon"></i></div>
        <div class="medical-hero__copy">
          <span>{{ sourceBadge }} · REGISTRO COMPARTIDO</span>
          <h2>Licencias y certificados médicos</h2>
          <p>Información centralizada entre Enfermería e Inspectoría para acompañar la asistencia y los cuidados de cada alumna.</p>
        </div>
        <div class="medical-hero__sync"><i class="bx bx-sync"></i><span>Misma información</span><strong>Enfermería + Inspectoría</strong></div>
      </header>

      <section class="medical-summary" aria-label="Resumen de licencias médicas">
        <article><i class="bx bx-file"></i><div><span>Registros totales</span><strong>{{ summary.total_records }}</strong></div></article>
        <article class="is-active"><i class="bx bx-calendar-check"></i><div><span>Próximas a finalizar</span><strong>{{ summary.ending_soon }}</strong><small>En los siguientes 7 días</small></div></article>
        <article class="is-chronic"><i class="bx bx-heart"></i><div><span>Alumnas con condición crónica</span><strong>{{ summary.chronic_students }}</strong><small>{{ summary.permanent_records }} registros permanentes</small></div></article>
      </section>

      <BAlert v-if="error" show dismissible variant="danger" @dismissed="error=null">{{ error }}</BAlert>

      <section class="medical-panel">
        <div class="medical-panel__heading">
          <div><span>TRAZABILIDAD DE SALUD</span><h3>Todos los registros</h3><p>{{ pagination.total }} licencias o certificados disponibles según tu alcance.</p></div>
          <button v-if="capabilities.can_create" type="button" class="btn medical-create-button" @click="openCreate">
            <i class="bx bx-plus"></i><span>Ingresar licencia</span>
          </button>
        </div>

        <div class="medical-filters">
          <label class="medical-search">
            <i class="bx bx-search"></i>
            <input v-model="filters.search" type="search" class="form-control" placeholder="Buscar por alumna, RUT o motivo" @keyup.enter="load(1)">
          </label>
          <select v-model="filters.status" class="form-select" aria-label="Filtrar por estado" @change="load(1)">
            <option value="">Todos los estados</option>
            <option value="active">Vigentes</option>
            <option value="scheduled">Programadas</option>
            <option value="ended">Finalizadas</option>
          </select>
          <button type="button" class="chronic-filter" :class="{ active: filters.permanent }" :aria-pressed="filters.permanent" @click="togglePermanentFilter">
            <i class="bx bx-heart"></i><span>Enfermedad crónica</span><b v-if="filters.permanent">Filtro activo</b>
          </button>
          <button type="button" class="btn btn-outline-primary" @click="load(1)"><i class="bx bx-filter-alt me-1"></i>Filtrar</button>
          <button v-if="activeFilterCount" type="button" class="btn btn-link text-decoration-none" @click="resetFilters">Limpiar</button>
        </div>

        <LoadingState v-if="loading" compact message="Cargando licencias médicas..." />
        <template v-else>
          <div class="table-responsive medical-table-wrap">
            <table class="table medical-table align-middle">
              <thead><tr><th>Alumna</th><th>Vigencia</th><th>Motivo</th><th>Condición</th><th>Origen</th></tr></thead>
              <tbody>
                <tr v-for="item in items" :key="item.id" :class="{ 'is-permanent-row': item.is_permanent }">
                  <td>
                    <div class="student-cell">
                      <span class="student-avatar">{{ (item.student?.name || "A").split(" ").slice(0, 2).map(part => part[0]).join("") }}</span>
                      <div><strong>{{ item.student?.name || "Alumna sin nombre" }}</strong><small>{{ item.student?.rut || "Sin RUT" }} · {{ item.student?.course || "Sin curso" }}</small></div>
                    </div>
                  </td>
                  <td><strong>{{ formatDate(item.starts_on) }}</strong><small>{{ item.is_permanent ? "Vigencia permanente" : `Hasta ${formatDate(item.ends_on)}` }}</small></td>
                  <td><p class="reason-cell">{{ item.reason || "Sin motivo administrativo" }}</p></td>
                  <td><span class="medical-status" :class="item.status"><i class="bx" :class="item.is_permanent ? 'bx-heart' : 'bx-calendar-check'"></i>{{ statusLabel(item.status) }}</span></td>
                  <td><strong>{{ sourceLabel(item.source_module) }}</strong><small>{{ item.registered_by || "Registro histórico" }}</small></td>
                </tr>
              </tbody>
            </table>
            <div v-if="!items.length" class="medical-empty"><i class="bx bx-file-find"></i><strong>No hay registros para estos filtros</strong><span>Prueba otra búsqueda o limpia los filtros activos.</span></div>
          </div>

          <footer v-if="pagination.last_page > 1" class="medical-pagination">
            <span>Mostrando {{ pagination.from }}–{{ pagination.to }} de {{ pagination.total }}</span>
            <div><button class="btn btn-sm btn-outline-primary" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)"><i class="bx bx-chevron-left"></i>Anterior</button><b>{{ pagination.current_page }} / {{ pagination.last_page }}</b><button class="btn btn-sm btn-outline-primary" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Siguiente<i class="bx bx-chevron-right"></i></button></div>
          </footer>
        </template>
      </section>

      <BModal v-model="showModal" title="Ingresar licencia médica" size="lg" hide-footer scrollable @hidden="closeModal">
        <form class="medical-form" @submit.prevent="save">
          <div class="form-intro">
            <i class="bx bx-link-alt"></i><div><strong>Registro único y compartido</strong><span>Lo ingresado aquí aparecerá de inmediato en Enfermería e Inspectoría.</span></div>
          </div>

          <div class="form-field form-field--student">
            <label for="medical-leave-student">Alumna <b>*</b></label>
            <div class="datalist-control">
              <i class="bx bx-search-alt"></i>
              <input id="medical-leave-student" v-model="studentSearch" list="medical-leave-student-options" class="form-control" autocomplete="off" placeholder="Escribe nombre, apellido o RUT" required @input="onStudentInput" @change="syncSelectedStudent">
              <span v-if="studentSearchLoading" class="spinner-border spinner-border-sm" aria-label="Buscando"></span>
              <datalist id="medical-leave-student-options">
                <option v-for="student in studentDatalistOptions" :key="student.id" :value="student.label"></option>
              </datalist>
            </div>
            <small v-if="selectedStudent" class="selected-student"><i class="bx bx-check-circle"></i>{{ selectedStudent.name }} · {{ selectedStudent.course || "Sin curso" }}</small>
            <small v-else>Escribe al menos 2 caracteres y selecciona una sugerencia.</small>
          </div>

          <div class="date-grid">
            <div class="form-field"><label for="medical-leave-start">Fecha de inicio <b>*</b></label><input id="medical-leave-start" v-model="form.starts_on" type="date" class="form-control" required></div>
            <div class="form-field" :class="{ muted: form.is_permanent }"><label for="medical-leave-end">Fecha de término <b v-if="!form.is_permanent">*</b></label><input id="medical-leave-end" v-model="form.ends_on" type="date" class="form-control" :min="form.starts_on" :disabled="form.is_permanent" :required="!form.is_permanent"><small>{{ form.is_permanent ? "No aplica a una condición permanente." : "Último día cubierto por la licencia." }}</small></div>
          </div>

          <div class="overlap-notice" :class="{ 'is-exempt': form.is_permanent }">
            <i class="bx" :class="form.is_permanent ? 'bx-heart' : 'bx-shield-quarter'"></i>
            <div>
              <strong>{{ form.is_permanent ? "Condición crónica excluida del bloqueo" : "Control automático de duplicados" }}</strong>
              <span v-if="form.is_permanent">Podrá coexistir con licencias temporales de la misma alumna.</span>
              <span v-else>Se impedirá guardar un período que se superponga con otro registro temporal de Enfermería o Inspectoría.</span>
            </div>
          </div>

          <label class="permanent-card" :class="{ active: form.is_permanent }">
            <input v-model="form.is_permanent" type="checkbox">
            <span class="permanent-card__icon"><i class="bx bx-heart"></i></span>
            <span><strong>Condición permanente</strong><small>Activa esta opción cuando corresponde a una enfermedad crónica que debe mantenerse visible.</small></span>
            <i class="bx" :class="form.is_permanent ? 'bxs-check-circle' : 'bx-circle'"></i>
          </label>

          <div class="form-field"><label for="medical-leave-reason">Motivo <b>*</b></label><textarea id="medical-leave-reason" v-model="form.reason" class="form-control" rows="4" maxlength="1500" placeholder="Describe brevemente el motivo administrativo o la condición informada" required></textarea><small class="text-end">{{ form.reason.length }} / 1500</small></div>

          <div class="form-actions"><button type="button" class="btn btn-light" :disabled="saving" @click="closeModal">Cancelar</button><button type="submit" class="btn medical-submit" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bx bx-check-shield"></i>{{ saving ? "Guardando..." : "Guardar licencia" }}</button></div>
        </form>
      </BModal>
    </main>
  </Layout>
</template>

<style scoped>
.medical-leaves-page,.medical-form{--ink:#173b57;--blue:#176b87;--teal:#2ca6a4;--green:#188064;--line:#dce8ed;--soft:#f4f8fa}.medical-leaves-page{display:flex;flex-direction:column;gap:1rem;padding-bottom:2rem}.medical-hero{align-items:center;background:linear-gradient(124deg,#153b57 0%,#176b87 58%,#23968f 100%);border-radius:20px;box-shadow:0 18px 38px rgba(23,59,87,.18);color:#fff;display:flex;gap:1rem;overflow:hidden;padding:1.35rem 1.5rem;position:relative}.medical-hero::after{background:rgba(255,255,255,.08);border-radius:50%;content:"";height:260px;position:absolute;right:16%;top:-170px;width:260px}.medical-hero__icon{align-items:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);border-radius:16px;display:flex;flex:0 0 58px;font-size:1.65rem;height:58px;justify-content:center}.medical-hero__copy{flex:1;min-width:0;position:relative;z-index:1}.medical-hero__copy>span{font-size:.62rem;font-weight:850;letter-spacing:.13em;opacity:.75}.medical-hero h2{color:#fff;font-size:1.45rem;margin:.2rem 0}.medical-hero p{font-size:.77rem;margin:0;max-width:730px;opacity:.82}.medical-hero__sync{align-items:flex-start;background:rgba(10,39,55,.22);border:1px solid rgba(255,255,255,.16);border-radius:14px;display:grid;grid-template-columns:30px 1fr;padding:.75rem .9rem;position:relative;z-index:1}.medical-hero__sync>i{font-size:1.25rem;grid-row:1/3}.medical-hero__sync span{font-size:.58rem;font-weight:750;opacity:.7}.medical-hero__sync strong{font-size:.72rem}.medical-summary{display:grid;gap:.75rem;grid-template-columns:repeat(3,minmax(0,1fr))}.medical-summary article{align-items:center;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 8px 22px rgba(31,64,85,.06);display:flex;gap:.8rem;padding:1rem 1.1rem}.medical-summary article>i{align-items:center;background:#e9f3f7;border-radius:13px;color:var(--blue);display:flex;flex:0 0 47px;font-size:1.4rem;height:47px;justify-content:center}.medical-summary article>div{display:grid;grid-template-columns:1fr auto;min-width:0;width:100%}.medical-summary span{color:#718491;font-size:.67rem;font-weight:750}.medical-summary strong{color:var(--ink);font-size:1.55rem;grid-row:1/3;grid-column:2}.medical-summary small{color:#8b9aa3;font-size:.6rem}.medical-summary .is-active>i{background:#edf7f4;color:var(--green)}.medical-summary .is-chronic{background:linear-gradient(135deg,#fff 0%,#fff8f5 100%);border-color:#f0d6cc}.medical-summary .is-chronic>i{background:#fff0ea;color:#bd5b3a}.medical-panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 14px 35px rgba(31,64,85,.07);overflow:hidden}.medical-panel__heading{align-items:center;display:flex;justify-content:space-between;padding:1.25rem 1.35rem}.medical-panel__heading>div>span{color:var(--teal);font-size:.61rem;font-weight:850;letter-spacing:.12em}.medical-panel__heading h3{color:var(--ink);font-size:1.12rem;margin:.15rem 0}.medical-panel__heading p{color:#7b8d98;font-size:.7rem;margin:0}.medical-create-button,.medical-submit{align-items:center;background:linear-gradient(120deg,var(--blue),var(--teal));border:0;color:#fff;display:inline-flex;font-weight:750;gap:.4rem}.medical-create-button{border-radius:11px;box-shadow:0 7px 16px rgba(23,107,135,.2);padding:.62rem .9rem}.medical-create-button:hover,.medical-submit:hover{color:#fff;filter:brightness(1.04)}.medical-filters{align-items:center;background:var(--soft);border-block:1px solid #e3ecef;display:grid;gap:.6rem;grid-template-columns:minmax(260px,1fr) 180px auto auto auto;padding:.9rem 1.35rem}.medical-search{position:relative}.medical-search>i{color:#7b8d98;left:.8rem;position:absolute;top:.68rem}.medical-search input{padding-left:2.25rem}.chronic-filter{align-items:center;background:#fff;border:1px solid #e1d9d4;border-radius:10px;color:#735e56;display:flex;font-size:.7rem;font-weight:750;gap:.35rem;height:38px;padding:0 .7rem}.chronic-filter>i{color:#bd5b3a;font-size:1rem}.chronic-filter b{display:none}.chronic-filter.active{background:#fff0ea;border-color:#dc9a82;color:#9c4225}.chronic-filter.active b{display:inline;font-size:.52rem;margin-left:.3rem;text-transform:uppercase}.medical-table-wrap{border:0}.medical-table{margin:0;min-width:900px}.medical-table thead{background:#fbfcfd}.medical-table th{border-bottom:1px solid var(--line);color:#708490;font-size:.62rem;font-weight:850;letter-spacing:.07em;padding:.75rem 1rem;text-transform:uppercase;white-space:nowrap}.medical-table td{border-color:#edf2f4;color:#526976;font-size:.73rem;padding:.9rem 1rem;vertical-align:middle}.medical-table td>strong,.medical-table td>small{display:block}.medical-table td>strong{color:var(--ink)}.medical-table td>small{color:#83939d;font-size:.62rem;margin-top:.2rem}.medical-table tr.is-permanent-row{background:linear-gradient(90deg,#fffaf7 0%,#fff 40%)}.medical-table tr.is-permanent-row td:first-child{border-left:3px solid #d77b59}.student-cell{align-items:center;display:flex;gap:.65rem;min-width:230px}.student-avatar{align-items:center;background:#e9f3f7;border-radius:11px;color:var(--blue);display:flex;flex:0 0 38px;font-size:.68rem;font-weight:850;height:38px;justify-content:center}.student-cell>div{display:flex;flex-direction:column}.student-cell strong{color:var(--ink);font-size:.76rem}.student-cell small{color:#82939d;font-size:.62rem}.reason-cell{-webkit-box-orient:vertical;-webkit-line-clamp:2;color:#536a77;display:-webkit-box;line-height:1.45;margin:0;max-width:360px;overflow:hidden}.medical-status{align-items:center;background:#edf7f4;border-radius:99px;color:var(--green);display:inline-flex;font-size:.64rem;font-weight:800;gap:.3rem;padding:.35rem .55rem;white-space:nowrap}.medical-status.permanent{background:#fff0ea;color:#a94728}.medical-status.scheduled{background:#eaf3fb;color:#246b95}.medical-status.ended{background:#eef1f3;color:#687a84}.medical-empty{align-items:center;color:#82939d;display:flex;flex-direction:column;gap:.25rem;padding:3.5rem 1rem;text-align:center}.medical-empty>i{font-size:2.2rem;margin-bottom:.3rem}.medical-empty strong{color:var(--ink)}.medical-empty span{font-size:.72rem}.medical-pagination{align-items:center;border-top:1px solid var(--line);display:flex;justify-content:space-between;padding:.85rem 1.2rem}.medical-pagination>span{color:#7c8e98;font-size:.68rem}.medical-pagination>div{align-items:center;display:flex;gap:.55rem}.medical-pagination b{color:var(--ink);font-size:.68rem}.medical-pagination button{align-items:center;display:flex}.form-intro{align-items:center;background:#eef8f7;border:1px solid #d6ece8;border-radius:13px;color:#1c655e;display:flex;gap:.7rem;margin-bottom:1rem;padding:.8rem .9rem}.form-intro>i{font-size:1.5rem}.form-intro>div{display:flex;flex-direction:column}.form-intro strong{font-size:.78rem}.form-intro span{font-size:.66rem}.form-field{display:flex;flex-direction:column;margin-bottom:.9rem}.form-field label{color:var(--ink);font-size:.7rem;font-weight:800;margin-bottom:.35rem}.form-field label b{color:#bd4d35}.form-field>small{color:#82939d;font-size:.62rem;margin-top:.3rem}.form-field.muted{opacity:.62}.datalist-control{position:relative}.datalist-control>i{color:#7b8d98;left:.8rem;position:absolute;top:.7rem}.datalist-control input{padding-left:2.25rem;padding-right:2.3rem}.datalist-control>.spinner-border{position:absolute;right:.75rem;top:.72rem}.selected-student{align-items:center;color:#188064!important;display:flex;gap:.25rem}.date-grid{display:grid;gap:.8rem;grid-template-columns:1fr 1fr}.overlap-notice{align-items:flex-start;background:#f1f7fa;border:1px solid #d8e7ed;border-radius:12px;color:#315e73;display:flex;gap:.65rem;margin:-.15rem 0 .9rem;padding:.7rem .8rem}.overlap-notice>i{color:var(--blue);font-size:1.2rem;margin-top:.05rem}.overlap-notice>div{display:flex;flex-direction:column}.overlap-notice strong{font-size:.69rem}.overlap-notice span{font-size:.62rem;line-height:1.45;margin-top:.08rem}.overlap-notice.is-exempt{background:#fff7f3;border-color:#efdbd2;color:#8a4c35}.overlap-notice.is-exempt>i{color:#bd5b3a}.permanent-card{align-items:center;background:#fffaf7;border:1px solid #eddbd3;border-radius:14px;cursor:pointer;display:flex;gap:.75rem;margin-bottom:1rem;padding:.9rem 1rem}.permanent-card>input{position:absolute;opacity:0}.permanent-card__icon{align-items:center;background:#fff0ea;border-radius:11px;color:#bd5b3a;display:flex;flex:0 0 42px;font-size:1.25rem;height:42px;justify-content:center}.permanent-card>span:nth-child(3){display:flex;flex:1;flex-direction:column}.permanent-card strong{color:var(--ink);font-size:.76rem}.permanent-card small{color:#7f8f98;font-size:.64rem;margin-top:.1rem}.permanent-card>i{color:#b8c2c7;font-size:1.25rem}.permanent-card.active{background:#fff2ec;border-color:#d88668;box-shadow:0 6px 16px rgba(185,84,48,.08)}.permanent-card.active>i{color:#bd5b3a}.form-actions{display:flex;gap:.55rem;justify-content:flex-end;margin-top:1.15rem;padding-top:.9rem;border-top:1px solid var(--line)}.medical-submit{border-radius:9px;padding:.55rem .85rem}
@media(max-width:992px){.medical-hero__sync{display:none}.medical-summary{grid-template-columns:1fr}.medical-filters{grid-template-columns:1fr 1fr}.medical-search{grid-column:1/-1}.medical-filters .btn-link{justify-self:start}.medical-panel__heading{align-items:flex-start;gap:1rem}.chronic-filter{justify-content:center}}
@media(max-width:576px){.medical-hero{align-items:flex-start;padding:1.05rem}.medical-hero__icon{flex-basis:46px;height:46px}.medical-hero h2{font-size:1.15rem}.medical-hero p{font-size:.69rem}.medical-summary article{padding:.85rem}.medical-panel__heading{flex-direction:column}.medical-create-button{justify-content:center;width:100%}.medical-filters{grid-template-columns:1fr;padding:.8rem}.medical-search{grid-column:auto}.medical-pagination{align-items:flex-start;flex-direction:column;gap:.7rem}.date-grid{grid-template-columns:1fr}.permanent-card{align-items:flex-start}.permanent-card>i:last-child{margin-top:.55rem}.form-actions>*{flex:1}}
</style>
