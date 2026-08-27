<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const localToday = () => {
  const date = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
  return date.toISOString().slice(0, 10);
};

const MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;
const ATTACHMENT_ACCEPT = ".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,application/pdf,image/*";

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
      capabilities: { can_create: false, can_edit: false },
      pagination: { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 },
      showModal: false,
      editingId: null,
      editingItem: null,
      form: emptyForm(),
      studentSearch: "",
      studentOptions: [],
      studentSearchLoading: false,
      studentSearchTimer: null,
      studentSearchSequence: 0,
      attachmentAccept: ATTACHMENT_ACCEPT,
      attachmentFile: null,
      attachmentPreviewUrl: null,
      attachmentSource: null,
      existingAttachment: null,
      downloadingAttachmentId: null,
      showCameraModal: false,
      cameraStream: null,
      cameraError: null,
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
    isEditing() {
      return Boolean(this.editingId);
    },
    canEditLeaves() {
      return this.sourceModule === "inspectoria" && Boolean(this.capabilities.can_edit);
    },
    modalTitle() {
      return this.isEditing ? "Editar licencia médica" : "Ingresar licencia médica";
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
    this.stopCameraStream();
    this.clearAttachment();
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
      this.editingId = null;
      this.editingItem = null;
      this.existingAttachment = null;
      this.form = emptyForm();
      this.studentSearch = "";
      this.studentOptions = [];
      this.showModal = true;
    },
    openEdit(item) {
      const student = item?.student || {};
      this.editingId = item.id;
      this.editingItem = item;
      this.existingAttachment = item.attachment || null;
      this.form = {
        student_profile_id: student.id,
        starts_on: item.starts_on || localToday(),
        ends_on: item.ends_on || "",
        reason: item.reason || "",
        is_permanent: Boolean(item.is_permanent),
      };
      this.studentOptions = [student];
      this.studentSearch = [student.name, student.rut || "Sin RUT", student.course || "Sin curso"].join(" · ");
      this.showModal = true;
    },
    closeModal() {
      this.stopCameraStream();
      this.showCameraModal = false;
      this.showModal = false;
      this.editingId = null;
      this.editingItem = null;
      this.existingAttachment = null;
      this.form = emptyForm();
      this.studentSearch = "";
      this.studentOptions = [];
      this.studentSearchLoading = false;
      window.clearTimeout(this.studentSearchTimer);
      this.clearAttachment();
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
      if (!this.isEditing) this.syncSelectedStudent();
      if (!this.form.student_profile_id) return this.warn("Selecciona una alumna desde las sugerencias del datalist.");
      if (!this.form.starts_on) return this.warn("Ingresa la fecha de inicio.");
      if (!this.form.is_permanent && !this.form.ends_on) return this.warn("Ingresa la fecha de término o marca la condición como permanente.");
      if (this.form.ends_on && this.form.ends_on < this.form.starts_on) return this.warn("La fecha de término no puede ser anterior al inicio.");
      if (!this.form.reason.trim()) return this.warn("Ingresa el motivo de la licencia o certificado médico.");

      this.saving = true;
      try {
        const wasPermanent = this.form.is_permanent;
        const payload = new FormData();
        payload.append("student_profile_id", String(this.form.student_profile_id));
        payload.append("starts_on", this.form.starts_on);
        if (this.form.ends_on) payload.append("ends_on", this.form.ends_on);
        payload.append("reason", this.form.reason.trim());
        payload.append("is_permanent", this.form.is_permanent ? "1" : "0");
        if (!this.isEditing) payload.append("source_module", this.sourceModule);
        if (this.attachmentFile) payload.append("attachment", this.attachmentFile, this.attachmentFile.name);

        const endpoint = this.isEditing
          ? `/api/student-medical-leaves/${this.editingId}`
          : "/api/student-medical-leaves";
        if (this.isEditing) payload.append("_method", "PUT");

        const wasEditing = this.isEditing;
        const { data } = await axios.post(endpoint, payload);
        this.closeModal();
        await this.load(1);
        await Swal.fire({
          icon: "success",
          title: wasEditing
            ? "Licencia actualizada"
            : wasPermanent ? "Condición permanente registrada" : "Licencia ingresada",
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
    async onAttachmentSelected(event, source) {
      const file = event?.target?.files?.[0];
      if (!file) return;

      return this.setAttachment(file, source, event.target);
    },
    async setAttachment(file, source, input = null) {

      const extension = String(file.name || "").split(".").pop().toLowerCase();
      const allowedExtension = ["pdf", "jpg", "jpeg", "png", "webp", "heic", "heif"].includes(extension);
      const allowedMime = file.type === "application/pdf" || String(file.type || "").startsWith("image/");

      if (!allowedExtension && !allowedMime) {
        if (input) input.value = "";
        return this.warn("Adjunta un PDF o una imagen JPG, PNG, WEBP, HEIC o HEIF.");
      }
      if (file.size > MAX_ATTACHMENT_BYTES) {
        if (input) input.value = "";
        return this.warn("El respaldo no puede superar los 15 MB.");
      }

      this.clearAttachment();
      this.attachmentFile = file;
      this.attachmentSource = source;
      if (String(file.type || "").startsWith("image/") && typeof URL.createObjectURL === "function") {
        this.attachmentPreviewUrl = URL.createObjectURL(file);
      }
    },
    async startCameraCapture() {
      this.cameraError = null;
      const confirmation = await Swal.fire({
        icon: "question",
        title: "Abrir cámara",
        text: "Se solicitará permiso para usar la cámara y fotografiar el respaldo médico.",
        showCancelButton: true,
        confirmButtonText: "Abrir cámara",
        cancelButtonText: "Cancelar",
      });
      if (!confirmation.isConfirmed) return;

      const canUseCamera = typeof navigator !== "undefined"
        && navigator.mediaDevices
        && typeof navigator.mediaDevices.getUserMedia === "function";
      if (!canUseCamera) {
        this.$refs.cameraInput?.click?.();
        return;
      }

      this.showCameraModal = true;
      await this.$nextTick();
      await this.openCameraStream();
    },
    async openCameraStream() {
      try {
        this.stopCameraStream();
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: "environment" } },
          audio: false,
        });
        this.cameraStream = stream;
        const video = this.$refs.cameraVideo;
        if (video) {
          video.srcObject = stream;
          const playback = video.play?.();
          if (playback?.catch) await playback.catch(() => {});
        }
      } catch (error) {
        this.cameraStream = null;
        this.cameraError = error?.message || "No se pudo acceder a la cámara. Revisa los permisos del navegador.";
      }
    },
    stopCameraStream() {
      if (this.cameraStream) {
        this.cameraStream.getTracks().forEach((track) => track.stop());
      }
      this.cameraStream = null;
      if (this.$refs.cameraVideo) this.$refs.cameraVideo.srcObject = null;
    },
    async capturePhotoFromCamera() {
      const video = this.$refs.cameraVideo;
      const canvas = this.$refs.cameraCanvas;
      if (!video || !canvas || !this.cameraStream) {
        this.cameraError = "La cámara aún no está lista para capturar.";
        return;
      }

      const width = video.videoWidth || 1280;
      const height = video.videoHeight || 720;
      canvas.width = width;
      canvas.height = height;
      const context = canvas.getContext("2d");
      if (!context) {
        this.cameraError = "No se pudo preparar la captura de la fotografía.";
        return;
      }
      context.drawImage(video, 0, 0, width, height);

      const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.9));
      if (!blob) {
        this.cameraError = "No se pudo generar la fotografía.";
        return;
      }

      const photo = new File([blob], `licencia-foto-${Date.now()}.jpg`, { type: "image/jpeg" });
      await this.setAttachment(photo, "camera");
      this.stopCameraStream();
      this.showCameraModal = false;
    },
    onHideCameraModal() {
      this.stopCameraStream();
    },
    clearAttachment() {
      if (this.attachmentPreviewUrl && typeof URL.revokeObjectURL === "function") {
        URL.revokeObjectURL(this.attachmentPreviewUrl);
      }
      this.attachmentFile = null;
      this.attachmentPreviewUrl = null;
      this.attachmentSource = null;
      if (this.$refs.attachmentInput) this.$refs.attachmentInput.value = "";
      if (this.$refs.cameraInput) this.$refs.cameraInput.value = "";
    },
    formatFileSize(bytes) {
      const size = Number(bytes || 0);
      if (size < 1024 * 1024) return `${Math.max(1, Math.round(size / 1024))} KB`;
      return `${(size / (1024 * 1024)).toFixed(1)} MB`;
    },
    async downloadAttachment(item) {
      if (!item?.attachment?.download_url || this.downloadingAttachmentId) return;
      this.downloadingAttachmentId = item.id;
      try {
        const { data } = await axios.get(item.attachment.download_url, { responseType: "blob" });
        const objectUrl = URL.createObjectURL(data);
        const link = document.createElement("a");
        link.href = objectUrl;
        link.download = String(item.attachment.name || `respaldo-medico-${item.id}`).replace(/[\\/]/g, "_");
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
      } catch (error) {
        await Swal.fire({
          icon: "error",
          title: "No se pudo descargar",
          text: this.errorMessage(error, "El respaldo no está disponible o no pertenece a tu alcance autorizado."),
          confirmButtonText: "Entendido",
        });
      } finally {
        this.downloadingAttachmentId = null;
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
      if (first?.[0]) return first[0];
      if (Number(error?.response?.status || 0) >= 500) return fallback;
      return error?.response?.data?.message || fallback;
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
              <thead><tr><th>Alumna</th><th>Vigencia</th><th>Motivo</th><th>Condición</th><th>Respaldo</th><th>Origen</th><th v-if="canEditLeaves">Acciones</th></tr></thead>
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
                  <td>
                    <button v-if="item.attachment" type="button" class="attachment-download" :disabled="downloadingAttachmentId === item.id" @click="downloadAttachment(item)">
                      <i :class="downloadingAttachmentId === item.id ? 'bx bx-loader-alt bx-spin' : item.attachment.mime_type === 'application/pdf' ? 'bx bxs-file-pdf' : 'bx bx-image'"></i>
                      <span><strong>{{ downloadingAttachmentId === item.id ? "Descargando" : "Abrir respaldo" }}</strong><small>{{ formatFileSize(item.attachment.size_bytes) }}</small></span>
                    </button>
                    <span v-else class="attachment-empty"><i class="bx bx-minus"></i>Sin archivo</span>
                  </td>
                  <td><strong>{{ sourceLabel(item.source_module) }}</strong><small>{{ item.registered_by || "Registro histórico" }}</small></td>
                  <td v-if="canEditLeaves">
                    <button type="button" class="medical-edit-button" :aria-label="`Editar licencia de ${item.student?.name || 'alumna'}`" @click="openEdit(item)">
                      <i class="bx bx-edit-alt"></i><span>Editar</span>
                    </button>
                  </td>
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

      <BModal v-model="showModal" :title="modalTitle" size="lg" hide-footer scrollable @hidden="closeModal">
        <form class="medical-form" @submit.prevent="save">
          <div class="form-intro">
            <i class="bx" :class="isEditing ? 'bx-edit-alt' : 'bx-link-alt'"></i><div><strong>{{ isEditing ? "Corrección trazable" : "Registro único y compartido" }}</strong><span>{{ isEditing ? "Actualiza los campos faltantes o incorpora un respaldo sin crear una licencia duplicada." : "Lo ingresado aquí aparecerá de inmediato en Enfermería e Inspectoría." }}</span></div>
          </div>

          <div class="form-field form-field--student">
            <label for="medical-leave-student">Alumna <b>*</b></label>
            <div class="datalist-control">
              <i class="bx bx-search-alt"></i>
              <input id="medical-leave-student" v-model="studentSearch" list="medical-leave-student-options" class="form-control" autocomplete="off" placeholder="Escribe nombre, apellido o RUT" :disabled="isEditing" required @input="onStudentInput" @change="syncSelectedStudent">
              <span v-if="studentSearchLoading" class="spinner-border spinner-border-sm" aria-label="Buscando"></span>
              <datalist id="medical-leave-student-options">
                <option v-for="student in studentDatalistOptions" :key="student.id" :value="student.label"></option>
              </datalist>
            </div>
            <small v-if="selectedStudent" class="selected-student"><i class="bx bx-check-circle"></i>{{ selectedStudent.name }} · {{ selectedStudent.course || "Sin curso" }}<template v-if="isEditing"> · La alumna no se modifica en esta corrección</template></small>
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

          <section class="attachment-uploader" aria-labelledby="medical-leave-attachment-title">
            <header>
              <span class="attachment-uploader__icon"><i class="bx bx-lock-alt"></i></span>
              <div><strong id="medical-leave-attachment-title">Respaldo médico privado</strong><small>Opcional · PDF o imagen · máximo 15 MB</small></div>
              <span class="attachment-uploader__privacy"><i class="bx bx-shield-quarter"></i>Solo personal autorizado</span>
            </header>

            <input id="medical-leave-camera" ref="cameraInput" type="file" class="visually-hidden" accept="image/*" capture="environment" @change="onAttachmentSelected($event, 'camera')">

            <div v-if="attachmentFile" class="attachment-selected">
              <img v-if="attachmentPreviewUrl" :src="attachmentPreviewUrl" alt="Vista previa del respaldo médico">
              <span v-else class="attachment-selected__file"><i class="bx bxs-file-pdf"></i></span>
              <div><span>{{ attachmentSource === "camera" ? "FOTOGRAFÍA CAPTURADA" : isEditing ? "NUEVO ARCHIVO" : "ARCHIVO ADJUNTO" }}</span><strong>{{ attachmentFile.name }}</strong><small>{{ formatFileSize(attachmentFile.size) }} · {{ existingAttachment ? "Reemplazará el respaldo actual al guardar" : "Se almacenará en el repositorio privado" }}</small></div>
              <button type="button" aria-label="Quitar respaldo seleccionado" @click="clearAttachment"><i class="bx bx-trash"></i></button>
            </div>

            <div v-else-if="isEditing && existingAttachment" class="attachment-existing">
              <div class="attachment-existing__file">
                <i :class="existingAttachment.mime_type === 'application/pdf' ? 'bx bxs-file-pdf' : 'bx bx-image'"></i>
                <span><small>RESPALDO ACTUAL</small><strong>{{ existingAttachment.name || "Archivo médico" }}</strong><b>{{ formatFileSize(existingAttachment.size_bytes) }} · Se conservará si no eliges otro</b></span>
                <button type="button" :disabled="downloadingAttachmentId === editingId" @click="downloadAttachment(editingItem)"><i class="bx bx-download"></i>Descargar</button>
              </div>
              <div class="attachment-uploader__choices attachment-uploader__choices--replace">
                <label for="medical-leave-file" class="attachment-choice">
                  <input id="medical-leave-file" ref="attachmentInput" type="file" class="visually-hidden" :accept="attachmentAccept" @change="onAttachmentSelected($event, 'file')">
                  <span><i class="bx bx-refresh"></i></span><div><strong>Reemplazar archivo</strong><small>Selecciona un PDF o imagen corregida</small></div><i class="bx bx-chevron-right"></i>
                </label>
                <button type="button" class="attachment-choice attachment-choice--camera" @click="startCameraCapture">
                  <span><i class="bx bx-camera"></i></span><div><strong>Reemplazar con foto</strong><small>Captura nuevamente el documento</small></div><i class="bx bx-chevron-right"></i>
                </button>
              </div>
            </div>

            <div v-else class="attachment-uploader__choices">
              <label for="medical-leave-file" class="attachment-choice">
                <input id="medical-leave-file" ref="attachmentInput" type="file" class="visually-hidden" :accept="attachmentAccept" @change="onAttachmentSelected($event, 'file')">
                <span><i class="bx bx-paperclip"></i></span><div><strong>Adjuntar archivo</strong><small>Busca un PDF o imagen en el dispositivo</small></div><i class="bx bx-chevron-right"></i>
              </label>
              <button type="button" class="attachment-choice attachment-choice--camera" @click="startCameraCapture">
                <span><i class="bx bx-camera"></i></span><div><strong>Sacar una foto</strong><small>Abre la cámara trasera del teléfono</small></div><i class="bx bx-chevron-right"></i>
              </button>
            </div>
          </section>

          <div class="form-actions"><button type="button" class="btn btn-light" :disabled="saving" @click="closeModal">Cancelar</button><button type="submit" class="btn medical-submit" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bx bx-check-shield"></i>{{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Guardar licencia" }}</button></div>
        </form>
      </BModal>

      <BModal
        v-model="showCameraModal"
        title="Fotografiar licencia médica"
        size="lg"
        hide-footer
        centered
        teleport-to="body"
        lazy
        no-fade
        @hide="onHideCameraModal"
      >
        <div class="medical-camera-intro"><i class="bx bx-lock-alt"></i><div><strong>Captura privada</strong><span>La imagen se adjuntará al formulario y solo se guardará al confirmar la licencia.</span></div></div>
        <div v-if="cameraError" class="alert alert-warning medical-camera-error">
          <i class="bx bx-error-circle"></i><span>{{ cameraError }}</span>
          <button type="button" class="btn btn-sm btn-outline-primary" @click="$refs.cameraInput?.click()">Usar cámara del dispositivo</button>
        </div>
        <div class="medical-camera-viewport">
          <video ref="cameraVideo" autoplay playsinline muted></video>
          <div class="medical-camera-guide"><span></span><small>Centra el documento dentro del marco</small></div>
        </div>
        <canvas ref="cameraCanvas" class="d-none"></canvas>
        <div class="medical-camera-actions">
          <button type="button" class="btn btn-light" @click="showCameraModal = false">Cancelar</button>
          <button type="button" class="btn medical-camera-capture" :disabled="Boolean(cameraError) || !cameraStream" @click="capturePhotoFromCamera"><i class="bx bx-camera"></i>Capturar foto</button>
        </div>
      </BModal>
    </main>
  </Layout>
</template>

<style scoped>
.medical-leaves-page,.medical-form{--ink:#173b57;--blue:#176b87;--teal:#2ca6a4;--green:#188064;--line:#dce8ed;--soft:#f4f8fa}.medical-leaves-page{display:flex;flex-direction:column;gap:1rem;padding-bottom:2rem}.medical-hero{align-items:center;background:linear-gradient(124deg,#153b57 0%,#176b87 58%,#23968f 100%);border-radius:20px;box-shadow:0 18px 38px rgba(23,59,87,.18);color:#fff;display:flex;gap:1rem;overflow:hidden;padding:1.35rem 1.5rem;position:relative}.medical-hero::after{background:rgba(255,255,255,.08);border-radius:50%;content:"";height:260px;position:absolute;right:16%;top:-170px;width:260px}.medical-hero__icon{align-items:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);border-radius:16px;display:flex;flex:0 0 58px;font-size:1.65rem;height:58px;justify-content:center}.medical-hero__copy{flex:1;min-width:0;position:relative;z-index:1}.medical-hero__copy>span{font-size:.62rem;font-weight:850;letter-spacing:.13em;opacity:.75}.medical-hero h2{color:#fff;font-size:1.45rem;margin:.2rem 0}.medical-hero p{font-size:.77rem;margin:0;max-width:730px;opacity:.82}.medical-hero__sync{align-items:flex-start;background:rgba(10,39,55,.22);border:1px solid rgba(255,255,255,.16);border-radius:14px;display:grid;grid-template-columns:30px 1fr;padding:.75rem .9rem;position:relative;z-index:1}.medical-hero__sync>i{font-size:1.25rem;grid-row:1/3}.medical-hero__sync span{font-size:.58rem;font-weight:750;opacity:.7}.medical-hero__sync strong{font-size:.72rem}.medical-summary{display:grid;gap:.75rem;grid-template-columns:repeat(3,minmax(0,1fr))}.medical-summary article{align-items:center;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 8px 22px rgba(31,64,85,.06);display:flex;gap:.8rem;padding:1rem 1.1rem}.medical-summary article>i{align-items:center;background:#e9f3f7;border-radius:13px;color:var(--blue);display:flex;flex:0 0 47px;font-size:1.4rem;height:47px;justify-content:center}.medical-summary article>div{display:grid;grid-template-columns:1fr auto;min-width:0;width:100%}.medical-summary span{color:#718491;font-size:.67rem;font-weight:750}.medical-summary strong{color:var(--ink);font-size:1.55rem;grid-row:1/3;grid-column:2}.medical-summary small{color:#8b9aa3;font-size:.6rem}.medical-summary .is-active>i{background:#edf7f4;color:var(--green)}.medical-summary .is-chronic{background:linear-gradient(135deg,#fff 0%,#fff8f5 100%);border-color:#f0d6cc}.medical-summary .is-chronic>i{background:#fff0ea;color:#bd5b3a}.medical-panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 14px 35px rgba(31,64,85,.07);overflow:hidden}.medical-panel__heading{align-items:center;display:flex;justify-content:space-between;padding:1.25rem 1.35rem}.medical-panel__heading>div>span{color:var(--teal);font-size:.61rem;font-weight:850;letter-spacing:.12em}.medical-panel__heading h3{color:var(--ink);font-size:1.12rem;margin:.15rem 0}.medical-panel__heading p{color:#7b8d98;font-size:.7rem;margin:0}.medical-create-button,.medical-submit{align-items:center;background:linear-gradient(120deg,var(--blue),var(--teal));border:0;color:#fff;display:inline-flex;font-weight:750;gap:.4rem}.medical-create-button{border-radius:11px;box-shadow:0 7px 16px rgba(23,107,135,.2);padding:.62rem .9rem}.medical-create-button:hover,.medical-submit:hover{color:#fff;filter:brightness(1.04)}.medical-filters{align-items:center;background:var(--soft);border-block:1px solid #e3ecef;display:grid;gap:.6rem;grid-template-columns:minmax(260px,1fr) 180px auto auto auto;padding:.9rem 1.35rem}.medical-search{position:relative}.medical-search>i{color:#7b8d98;left:.8rem;position:absolute;top:.68rem}.medical-search input{padding-left:2.25rem}.chronic-filter{align-items:center;background:#fff;border:1px solid #e1d9d4;border-radius:10px;color:#735e56;display:flex;font-size:.7rem;font-weight:750;gap:.35rem;height:38px;padding:0 .7rem}.chronic-filter>i{color:#bd5b3a;font-size:1rem}.chronic-filter b{display:none}.chronic-filter.active{background:#fff0ea;border-color:#dc9a82;color:#9c4225}.chronic-filter.active b{display:inline;font-size:.52rem;margin-left:.3rem;text-transform:uppercase}.medical-table-wrap{border:0}.medical-table{margin:0;min-width:900px}.medical-table thead{background:#fbfcfd}.medical-table th{border-bottom:1px solid var(--line);color:#708490;font-size:.62rem;font-weight:850;letter-spacing:.07em;padding:.75rem 1rem;text-transform:uppercase;white-space:nowrap}.medical-table td{border-color:#edf2f4;color:#526976;font-size:.73rem;padding:.9rem 1rem;vertical-align:middle}.medical-table td>strong,.medical-table td>small{display:block}.medical-table td>strong{color:var(--ink)}.medical-table td>small{color:#83939d;font-size:.62rem;margin-top:.2rem}.medical-table tr.is-permanent-row{background:linear-gradient(90deg,#fffaf7 0%,#fff 40%)}.medical-table tr.is-permanent-row td:first-child{border-left:3px solid #d77b59}.student-cell{align-items:center;display:flex;gap:.65rem;min-width:230px}.student-avatar{align-items:center;background:#e9f3f7;border-radius:11px;color:var(--blue);display:flex;flex:0 0 38px;font-size:.68rem;font-weight:850;height:38px;justify-content:center}.student-cell>div{display:flex;flex-direction:column}.student-cell strong{color:var(--ink);font-size:.76rem}.student-cell small{color:#82939d;font-size:.62rem}.reason-cell{-webkit-box-orient:vertical;-webkit-line-clamp:2;color:#536a77;display:-webkit-box;line-height:1.45;margin:0;max-width:360px;overflow:hidden}.medical-status{align-items:center;background:#edf7f4;border-radius:99px;color:var(--green);display:inline-flex;font-size:.64rem;font-weight:800;gap:.3rem;padding:.35rem .55rem;white-space:nowrap}.medical-status.permanent{background:#fff0ea;color:#a94728}.medical-status.scheduled{background:#eaf3fb;color:#246b95}.medical-status.ended{background:#eef1f3;color:#687a84}.medical-empty{align-items:center;color:#82939d;display:flex;flex-direction:column;gap:.25rem;padding:3.5rem 1rem;text-align:center}.medical-empty>i{font-size:2.2rem;margin-bottom:.3rem}.medical-empty strong{color:var(--ink)}.medical-empty span{font-size:.72rem}.medical-pagination{align-items:center;border-top:1px solid var(--line);display:flex;justify-content:space-between;padding:.85rem 1.2rem}.medical-pagination>span{color:#7c8e98;font-size:.68rem}.medical-pagination>div{align-items:center;display:flex;gap:.55rem}.medical-pagination b{color:var(--ink);font-size:.68rem}.medical-pagination button{align-items:center;display:flex}.form-intro{align-items:center;background:#eef8f7;border:1px solid #d6ece8;border-radius:13px;color:#1c655e;display:flex;gap:.7rem;margin-bottom:1rem;padding:.8rem .9rem}.form-intro>i{font-size:1.5rem}.form-intro>div{display:flex;flex-direction:column}.form-intro strong{font-size:.78rem}.form-intro span{font-size:.66rem}.form-field{display:flex;flex-direction:column;margin-bottom:.9rem}.form-field label{color:var(--ink);font-size:.7rem;font-weight:800;margin-bottom:.35rem}.form-field label b{color:#bd4d35}.form-field>small{color:#82939d;font-size:.62rem;margin-top:.3rem}.form-field.muted{opacity:.62}.datalist-control{position:relative}.datalist-control>i{color:#7b8d98;left:.8rem;position:absolute;top:.7rem}.datalist-control input{padding-left:2.25rem;padding-right:2.3rem}.datalist-control>.spinner-border{position:absolute;right:.75rem;top:.72rem}.selected-student{align-items:center;color:#188064!important;display:flex;gap:.25rem}.date-grid{display:grid;gap:.8rem;grid-template-columns:1fr 1fr}.overlap-notice{align-items:flex-start;background:#f1f7fa;border:1px solid #d8e7ed;border-radius:12px;color:#315e73;display:flex;gap:.65rem;margin:-.15rem 0 .9rem;padding:.7rem .8rem}.overlap-notice>i{color:var(--blue);font-size:1.2rem;margin-top:.05rem}.overlap-notice>div{display:flex;flex-direction:column}.overlap-notice strong{font-size:.69rem}.overlap-notice span{font-size:.62rem;line-height:1.45;margin-top:.08rem}.overlap-notice.is-exempt{background:#fff7f3;border-color:#efdbd2;color:#8a4c35}.overlap-notice.is-exempt>i{color:#bd5b3a}.permanent-card{align-items:center;background:#fffaf7;border:1px solid #eddbd3;border-radius:14px;cursor:pointer;display:flex;gap:.75rem;margin-bottom:1rem;padding:.9rem 1rem}.permanent-card>input{position:absolute;opacity:0}.permanent-card__icon{align-items:center;background:#fff0ea;border-radius:11px;color:#bd5b3a;display:flex;flex:0 0 42px;font-size:1.25rem;height:42px;justify-content:center}.permanent-card>span:nth-child(3){display:flex;flex:1;flex-direction:column}.permanent-card strong{color:var(--ink);font-size:.76rem}.permanent-card small{color:#7f8f98;font-size:.64rem;margin-top:.1rem}.permanent-card>i{color:#b8c2c7;font-size:1.25rem}.permanent-card.active{background:#fff2ec;border-color:#d88668;box-shadow:0 6px 16px rgba(185,84,48,.08)}.permanent-card.active>i{color:#bd5b3a}.form-actions{display:flex;gap:.55rem;justify-content:flex-end;margin-top:1.15rem;padding-top:.9rem;border-top:1px solid var(--line)}.medical-submit{border-radius:9px;padding:.55rem .85rem}
.medical-table{min-width:1080px}
.attachment-download{align-items:center;background:#f2f8fa;border:1px solid #d8e8ed;border-radius:10px;color:var(--blue);display:inline-flex;gap:.45rem;min-width:124px;padding:.4rem .55rem;text-align:left;transition:.18s ease}.attachment-download:hover{background:#e8f4f6;border-color:#b9d7df;transform:translateY(-1px)}.attachment-download:disabled{cursor:wait;opacity:.7}.attachment-download>i{font-size:1.15rem}.attachment-download>span{display:flex;flex-direction:column}.attachment-download strong{color:var(--ink);font-size:.64rem}.attachment-download small{color:#7f919a;font-size:.56rem}.attachment-empty{align-items:center;color:#9aa8af;display:inline-flex;font-size:.63rem;gap:.25rem;white-space:nowrap}
.attachment-uploader{background:linear-gradient(145deg,#f7fbfc,#f2f8fa);border:1px solid #d8e7ec;border-radius:16px;margin-top:.15rem;overflow:hidden}.attachment-uploader>header{align-items:center;border-bottom:1px solid #dde9ed;display:flex;gap:.65rem;padding:.8rem .9rem}.attachment-uploader__icon{align-items:center;background:#e4f2f4;border-radius:10px;color:var(--blue);display:flex;flex:0 0 38px;font-size:1.05rem;height:38px;justify-content:center}.attachment-uploader>header>div{display:flex;flex:1;flex-direction:column;min-width:0}.attachment-uploader>header strong{color:var(--ink);font-size:.73rem}.attachment-uploader>header small{color:#7d909a;font-size:.6rem;margin-top:.08rem}.attachment-uploader__privacy{align-items:center;background:#e8f5f1;border-radius:99px;color:#28705f;display:inline-flex;font-size:.57rem;font-weight:800;gap:.28rem;padding:.33rem .5rem;white-space:nowrap}
.attachment-uploader__choices{display:grid;gap:.65rem;grid-template-columns:1fr 1fr;padding:.8rem}.attachment-choice{align-items:center;background:#fff;border:1px solid #dce8ec;border-radius:12px;cursor:pointer;display:flex;gap:.65rem;margin:0;padding:.75rem;transition:.18s ease}.attachment-choice:hover{border-color:#9fc8d2;box-shadow:0 7px 18px rgba(23,107,135,.08);transform:translateY(-1px)}.attachment-choice>span{align-items:center;background:#eaf4f7;border-radius:10px;color:var(--blue);display:flex;flex:0 0 40px;font-size:1.15rem;height:40px;justify-content:center}.attachment-choice--camera>span{background:#e8f6f2;color:#188064}.attachment-choice>div{display:flex;flex:1;flex-direction:column;min-width:0}.attachment-choice strong{color:var(--ink);font-size:.7rem}.attachment-choice small{color:#80919a;font-size:.59rem;line-height:1.35;margin-top:.08rem}.attachment-choice>i{color:#a4b2b8;font-size:1.05rem}
.attachment-selected{align-items:center;display:flex;gap:.7rem;padding:.8rem}.attachment-selected>img,.attachment-selected__file{border-radius:11px;flex:0 0 56px;height:56px;object-fit:cover}.attachment-selected__file{align-items:center;background:#fff0ed;color:#bd4933;display:flex;font-size:1.6rem;justify-content:center}.attachment-selected>div{display:flex;flex:1;flex-direction:column;min-width:0}.attachment-selected>div>span{color:var(--teal);font-size:.53rem;font-weight:850;letter-spacing:.08em}.attachment-selected strong{color:var(--ink);font-size:.69rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.attachment-selected small{color:#7e9099;font-size:.58rem;margin-top:.1rem}.attachment-selected>button{align-items:center;background:#fff;border:1px solid #ecd9d5;border-radius:9px;color:#b7513d;display:flex;flex:0 0 36px;height:36px;justify-content:center}
.attachment-choice--camera{font-family:inherit;text-align:left;width:100%}.medical-camera-intro{align-items:center;background:#eef8f7;border:1px solid #d6ece8;border-radius:13px;color:#1c655e;display:flex;gap:.65rem;margin-bottom:.8rem;padding:.7rem .8rem}.medical-camera-intro>i{font-size:1.25rem}.medical-camera-intro>div{display:flex;flex-direction:column}.medical-camera-intro strong{font-size:.73rem}.medical-camera-intro span{font-size:.62rem}.medical-camera-error{align-items:center;display:flex;gap:.55rem}.medical-camera-error>span{flex:1;font-size:.68rem}.medical-camera-viewport{aspect-ratio:16/9;background:#102b3a;border:1px solid #274756;border-radius:15px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.06);overflow:hidden;position:relative}.medical-camera-viewport video{height:100%;object-fit:cover;width:100%}.medical-camera-guide{inset:0;pointer-events:none;position:absolute}.medical-camera-guide>span{border:2px solid rgba(255,255,255,.75);border-radius:12px;inset:8% 10%;position:absolute}.medical-camera-guide>span::before,.medical-camera-guide>span::after{background:var(--teal);content:"";height:3px;left:50%;position:absolute;top:50%;transform:translate(-50%,-50%);width:32px}.medical-camera-guide>span::after{height:32px;width:3px}.medical-camera-guide small{background:rgba(9,31,43,.76);border-radius:99px;bottom:1rem;color:#fff;font-size:.62rem;left:50%;padding:.35rem .65rem;position:absolute;transform:translateX(-50%);white-space:nowrap}.medical-camera-actions{display:flex;gap:.55rem;justify-content:flex-end;margin-top:.85rem}.medical-camera-capture{align-items:center;background:linear-gradient(120deg,var(--blue),var(--teal));border:0;color:#fff;display:inline-flex;font-weight:750;gap:.4rem}.medical-camera-capture:hover{color:#fff}.medical-camera-capture:disabled{color:#fff;opacity:.55}
.medical-edit-button{align-items:center;background:#fff;border:1px solid #cfe1e7;border-radius:9px;color:var(--blue);display:inline-flex;font-size:.65rem;font-weight:800;gap:.3rem;padding:.43rem .58rem;transition:.18s ease;white-space:nowrap}.medical-edit-button:hover{background:#edf7f8;border-color:#9fc7d1;box-shadow:0 6px 14px rgba(23,107,135,.08);transform:translateY(-1px)}.medical-edit-button>i{font-size:.95rem}.attachment-existing{display:flex;flex-direction:column}.attachment-existing__file{align-items:center;background:#fff;border-bottom:1px solid #dde9ed;display:flex;gap:.65rem;padding:.8rem .9rem}.attachment-existing__file>i{align-items:center;background:#fff0ed;border-radius:10px;color:#bd4933;display:flex;flex:0 0 42px;font-size:1.25rem;height:42px;justify-content:center}.attachment-existing__file>span{display:flex;flex:1;flex-direction:column;min-width:0}.attachment-existing__file small{color:var(--teal);font-size:.52rem;font-weight:850;letter-spacing:.08em}.attachment-existing__file strong{color:var(--ink);font-size:.7rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.attachment-existing__file b{color:#7d909a;font-size:.58rem;font-weight:500;margin-top:.08rem}.attachment-existing__file>button{align-items:center;background:#f3f8fa;border:1px solid #d5e5ea;border-radius:9px;color:var(--blue);display:inline-flex;font-size:.62rem;font-weight:750;gap:.3rem;padding:.45rem .55rem}.attachment-uploader__choices--replace{padding-top:.65rem}
@media(max-width:992px){.medical-hero__sync{display:none}.medical-summary{grid-template-columns:1fr}.medical-filters{grid-template-columns:1fr 1fr}.medical-search{grid-column:1/-1}.medical-filters .btn-link{justify-self:start}.medical-panel__heading{align-items:flex-start;gap:1rem}.chronic-filter{justify-content:center}}
@media(max-width:576px){.medical-hero{align-items:flex-start;padding:1.05rem}.medical-hero__icon{flex-basis:46px;height:46px}.medical-hero h2{font-size:1.15rem}.medical-hero p{font-size:.69rem}.medical-summary article{padding:.85rem}.medical-panel__heading{flex-direction:column}.medical-create-button{justify-content:center;width:100%}.medical-filters{grid-template-columns:1fr;padding:.8rem}.medical-search{grid-column:auto}.medical-pagination{align-items:flex-start;flex-direction:column;gap:.7rem}.date-grid{grid-template-columns:1fr}.permanent-card{align-items:flex-start}.permanent-card>i:last-child{margin-top:.55rem}.attachment-uploader>header{align-items:center;display:grid;grid-template-columns:38px minmax(0,1fr)}.attachment-uploader__privacy{grid-column:2;margin-left:0;justify-self:start}.attachment-uploader__choices{grid-template-columns:1fr}.attachment-selected{align-items:flex-start}.attachment-selected>img,.attachment-selected__file{flex-basis:48px;height:48px}.attachment-existing__file{align-items:flex-start;flex-wrap:wrap}.attachment-existing__file>span{flex-basis:calc(100% - 58px)}.attachment-existing__file>button{justify-content:center;margin-left:58px;width:calc(100% - 58px)}.medical-camera-error{align-items:flex-start;flex-wrap:wrap}.medical-camera-error>span{flex-basis:calc(100% - 28px)}.medical-camera-guide small{bottom:.6rem;max-width:88%;overflow:hidden;text-overflow:ellipsis}.medical-camera-actions>*{flex:1}.form-actions>*{flex:1}}
</style>
