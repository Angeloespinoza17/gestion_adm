<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import { VueDraggableNext } from "vue-draggable-next";
import Swal from "sweetalert2";

const emptyFilters = () => ({
  search: "",
  owner_user_id: null,
  status: null,
  priority: null,
  stakeholder: "",
  due_date_from: "",
  due_date_to: "",
  created_by_user_id: null,
  created_scope: null,
  overdue: false,
  has_subtasks: false,
});

const emptyTaskForm = () => ({
  title: "",
  priority: "media",
  status: "pendiente",
  stakeholder: "",
  due_date: "",
  owner_user_id: null,
  description: "",
  parent_task_id: null,
  auto_complete_parent_on_subtasks_done: false,
  sort_order: 0,
});

const emptySubtaskForm = () => ({
  title: "",
  priority: "media",
  status: "pendiente",
  stakeholder: "",
  due_date: "",
  description: "",
  auto_complete_parent_on_subtasks_done: false,
  sort_order: 0,
});

export default {
  components: {
    Layout,
    LoadingState,
    Multiselect,
    FullCalendar,
    draggable: VueDraggableNext,
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      success: null,
      viewMode: "table",
      catalogs: {
        priorities: [],
        statuses: [],
        users: [],
        assignable_users: [],
        current_user: {},
        capabilities: {},
      },
      filters: emptyFilters(),
      tasks: [],
      stats: null,
      expanded: {},
      showTaskModal: false,
      selectedTask: null,
      editingId: null,
      form: emptyTaskForm(),
      subtaskForm: emptySubtaskForm(),
      subtaskSaving: false,
      kanbanColumns: {},
      calendarOptions: {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, bootstrap5Plugin],
        locales: [esLocale],
        locale: "es",
        themeSystem: "bootstrap5",
        initialView: "dayGridMonth",
        firstDay: 1,
        height: "auto",
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,timeGridDay",
        },
        buttonText: {
          today: "Hoy",
          month: "Mes",
          week: "Semana",
          day: "Día",
        },
        eventClick: null,
        events: [],
      },
      assigners: [],
      assignerLoading: false,
      assignerSaving: false,
      assignerForm: {
        id: null,
        target_user_id: null,
        assigner_user_id: null,
        active: true,
      },
    };
  },
  computed: {
    canManageAssigners() {
      return Boolean(this.catalogs.capabilities?.can_manage_assigners);
    },
    priorityOptions() {
      return (this.catalogs.priorities || []).map((item) => ({ value: item.value, label: item.label }));
    },
    statusOptions() {
      return (this.catalogs.statuses || []).map((item) => ({ value: item.value, label: item.label }));
    },
    filterStatusOptions() {
      return [{ value: null, label: "Todos" }].concat(this.statusOptions);
    },
    filterPriorityOptions() {
      return [{ value: null, label: "Todas" }].concat(this.priorityOptions);
    },
    userOptions() {
      return [{ value: null, label: "Todos" }].concat((this.catalogs.users || []).map((user) => ({
        value: user.id,
        label: this.userLabel(user),
      })));
    },
    assignableUserOptions() {
      return (this.catalogs.assignable_users || []).map((user) => ({
        value: user.id,
        label: this.userLabel(user),
      }));
    },
    createdScopeOptions() {
      return [
        { value: null, label: "Todas" },
        { value: "mine", label: "Creadas por mí" },
        { value: "third_party", label: "Asignadas por terceros" },
      ];
    },
    statsCards() {
      if (!this.stats) return [];
      return [
        { label: "Total", value: this.stats.total },
        { label: "Pendientes", value: this.stats.pending },
        { label: "En progreso", value: this.stats.in_progress },
        { label: "Completadas", value: this.stats.completed },
        { label: "Vencidas", value: this.stats.overdue, danger: this.stats.overdue > 0 },
        { label: "Próximos 7 días", value: this.stats.due_next_7_days },
        { label: "Terceros", value: this.stats.created_by_third_parties },
        { label: "Promedio cierre", value: this.stats.average_days_to_complete === null ? "-" : `${this.stats.average_days_to_complete} días` },
      ];
    },
    flatTasks() {
      return this.tasks.flatMap((task) => [task].concat(task.subtasks || []));
    },
    undatedTasks() {
      return this.flatTasks.filter((task) => !task.due_date);
    },
  },
  async mounted() {
    if (this.$route.path.includes("/tasks/assigners")) {
      this.viewMode = "assigners";
    }

    await this.loadCatalogs();
    await this.refreshData();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/tasks/catalogs");
      this.catalogs = response.data;
      if (!this.filters.owner_user_id && !this.catalogs.capabilities?.can_manage_backlogs) {
        this.filters.owner_user_id = this.catalogs.current_user?.id || null;
      }
    },
    async refreshData() {
      await Promise.all([this.loadTasks(), this.loadStats()]);
      if (this.canManageAssigners && this.viewMode === "assigners") {
        await this.loadAssigners();
      }
    },
    filterParams(extra = {}) {
      return {
        search: this.filters.search || null,
        owner_user_id: this.filters.owner_user_id,
        status: this.filters.status,
        priority: this.filters.priority,
        stakeholder: this.filters.stakeholder || null,
        due_date_from: this.filters.due_date_from || null,
        due_date_to: this.filters.due_date_to || null,
        created_by_user_id: this.filters.created_by_user_id,
        created_scope: this.filters.created_scope,
        overdue: this.filters.overdue ? 1 : null,
        has_subtasks: this.filters.has_subtasks ? 1 : null,
        ...extra,
      };
    },
    async loadTasks(options = {}) {
      const silent = Boolean(options.silent);
      if (!silent) {
        this.loading = true;
      }
      this.error = null;
      try {
        const response = await axios.get("/api/tasks", {
          params: this.filterParams({ per_page: "all" }),
        });
        this.tasks = response.data.data || [];
        this.rebuildKanban();
        this.rebuildCalendar();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        if (!silent) {
          this.loading = false;
        }
      }
    },
    async loadStats() {
      try {
        const response = await axios.get("/api/tasks/stats", {
          params: this.filterParams(),
        });
        this.stats = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    clearFilters() {
      const owner = this.catalogs.capabilities?.can_manage_backlogs ? null : this.catalogs.current_user?.id || null;
      this.filters = { ...emptyFilters(), owner_user_id: owner };
      this.refreshData();
    },
    setView(view) {
      this.viewMode = view;
      if (view === "assigners" && this.canManageAssigners) {
        this.loadAssigners();
      }
    },
    rebuildCalendar() {
      const events = this.flatTasks
        .filter((task) => task.due_date)
        .map((task) => ({
          id: String(task.id),
          title: task.title,
          start: task.due_date,
          allDay: true,
          backgroundColor: "#e8f8f0",
          borderColor: "#34c38f",
          textColor: "#087f5b",
          classNames: ["task-calendar-event"],
          extendedProps: {
            taskId: task.id,
            priority: task.priority,
            status: task.status,
          },
        }));
      this.calendarOptions = { ...this.calendarOptions, events, eventClick: this.handleCalendarEvent };
    },
    rebuildKanban() {
      const columns = {};
      (this.catalogs.statuses || []).forEach((status) => {
        columns[status.value] = [];
      });
      (this.tasks || []).filter((task) => !task.parent_task_id).forEach((task) => {
        if (!columns[task.status]) {
          columns[task.status] = [];
        }
        columns[task.status].push(task);
      });
      this.kanbanColumns = columns;
    },
    async handleCalendarEvent(info) {
      const taskId = Number(info.event.extendedProps.taskId || info.event.id);
      const task = this.flatTasks.find((item) => item.id === taskId);
      if (task) {
        await this.openTask(task);
      }
    },
    async onKanbanChange(event, status) {
      const changed = event.added || event.moved;
      if (!changed?.element) return;

      const previousTasks = this.cloneTasks(this.tasks);
      const payload = {
        status,
        sort_order: changed.newIndex ?? 0,
      };

      this.patchTaskLocally(changed.element.id, {
        status,
        sort_order: payload.sort_order,
        completed_at: status === "completada" ? changed.element.completed_at || new Date().toISOString() : null,
      });
      this.rebuildCalendar();

      try {
        const response = await axios.put(`/api/tasks/${changed.element.id}/status`, payload);
        if (response.data?.data) {
          this.patchTaskLocally(response.data.data.id, response.data.data);
        }
        this.rebuildCalendar();
        await this.loadStats();
      } catch (error) {
        this.tasks = previousTasks;
        this.rebuildKanban();
        this.rebuildCalendar();
        this.error = this.formatError(error);
        await this.loadStats();
      }
    },
    cloneTasks(tasks) {
      return (tasks || []).map((task) => ({
        ...task,
        subtasks: (task.subtasks || []).map((subtask) => ({ ...subtask })),
      }));
    },
    patchTaskLocally(taskId, patch) {
      const applyPatch = (task) => {
        if (task.id !== taskId) return false;
        Object.assign(task, patch);
        return true;
      };

      (this.tasks || []).some((task) => {
        if (applyPatch(task)) return true;
        return (task.subtasks || []).some((subtask) => applyPatch(subtask));
      });

      Object.values(this.kanbanColumns || {}).some((column) => {
        const task = (column || []).find((item) => item.id === taskId);
        if (!task) return false;
        Object.assign(task, patch);
        return true;
      });

      this.tasks = [...this.tasks];
      this.kanbanColumns = { ...this.kanbanColumns };
    },
    toggleSubtasks(task) {
      this.expanded = { ...this.expanded, [task.id]: !this.expanded[task.id] };
    },
    newTask() {
      this.editingId = null;
      this.selectedTask = null;
      this.form = {
        ...emptyTaskForm(),
        owner_user_id: this.filters.owner_user_id || this.catalogs.current_user?.id || this.assignableUserOptions[0]?.value || null,
      };
      this.subtaskForm = emptySubtaskForm();
      this.showTaskModal = true;
    },
    async openTask(task) {
      this.error = null;
      try {
        const response = await axios.get(`/api/tasks/${task.id}`);
        this.selectedTask = response.data.data;
        this.editingId = this.selectedTask.id;
        this.form = this.taskToForm(this.selectedTask);
        this.subtaskForm = emptySubtaskForm();
        this.showTaskModal = true;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    taskToForm(task) {
      return {
        title: task.title || "",
        priority: task.priority || "media",
        status: task.status || "pendiente",
        stakeholder: task.stakeholder || "",
        due_date: task.due_date || "",
        owner_user_id: task.owner_user_id || null,
        description: task.description || "",
        parent_task_id: task.parent_task_id || null,
        auto_complete_parent_on_subtasks_done: Boolean(task.auto_complete_parent_on_subtasks_done),
        sort_order: task.sort_order || 0,
      };
    },
    validateTaskForm() {
      if (!this.form.title || !this.form.title.trim()) return "El nombre de la tarea es obligatorio.";
      if (!this.form.priority) return "La prioridad es obligatoria.";
      if (!this.form.status) return "El estado es obligatorio.";
      if (!this.form.owner_user_id) return "El funcionario responsable es obligatorio.";
      if (this.form.due_date && Number.isNaN(Date.parse(`${this.form.due_date}T00:00:00`))) {
        return "La fecha de corte debe ser válida.";
      }
      return null;
    },
    async saveTask() {
      const validation = this.validateTaskForm();
      if (validation) {
        this.error = validation;
        return;
      }

      this.saving = true;
      this.error = null;
      try {
        const payload = { ...this.form };
        if (this.editingId) {
          await axios.put(`/api/tasks/${this.editingId}`, payload);
          this.success = "Tarea actualizada correctamente.";
        } else {
          await axios.post("/api/tasks", payload);
          this.success = "Tarea creada correctamente.";
        }
        this.showTaskModal = false;
        await this.refreshData();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async deleteTask() {
      if (!this.selectedTask) return;

      const result = await Swal.fire({
        title: "Eliminar tarea",
        text: this.selectedTask.title,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/tasks/${this.selectedTask.id}`);
        this.success = "Tarea eliminada correctamente.";
        this.showTaskModal = false;
        await this.refreshData();
      } catch (error) {
        const validationErrors = error?.response?.data?.errors || {};
        if (validationErrors.delete_subtasks) {
          const cascade = await Swal.fire({
            title: "La tarea tiene subtareas",
            text: "Confirma si deseas eliminar también todas sus subtareas.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Eliminar tarea y subtareas",
            cancelButtonText: "Cancelar",
          });
          if (cascade.isConfirmed) {
            await axios.delete(`/api/tasks/${this.selectedTask.id}`, { data: { delete_subtasks: true } });
            this.success = "Tarea y subtareas eliminadas correctamente.";
            this.showTaskModal = false;
            await this.refreshData();
          }
          return;
        }
        this.error = this.formatError(error);
      }
    },
    validateSubtaskForm() {
      if (!this.subtaskForm.title || !this.subtaskForm.title.trim()) return "El nombre de la subtarea es obligatorio.";
      if (!this.subtaskForm.priority) return "La prioridad de la subtarea es obligatoria.";
      if (!this.subtaskForm.status) return "El estado de la subtarea es obligatorio.";
      return null;
    },
    async createSubtask() {
      if (!this.selectedTask) return;
      const validation = this.validateSubtaskForm();
      if (validation) {
        this.error = validation;
        return;
      }

      this.subtaskSaving = true;
      try {
        await axios.post(`/api/tasks/${this.selectedTask.id}/subtasks`, {
          ...this.subtaskForm,
          owner_user_id: this.selectedTask.owner_user_id,
          parent_task_id: this.selectedTask.id,
        });
        this.subtaskForm = emptySubtaskForm();
        await this.reloadSelectedTask();
        await this.refreshData();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.subtaskSaving = false;
      }
    },
    async updateSubtask(subtask) {
      try {
        await axios.put(`/api/tasks/${subtask.id}`, this.taskToForm(subtask));
        await this.reloadSelectedTask();
        await this.refreshData();
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async deleteSubtask(subtask) {
      const result = await Swal.fire({
        title: "Eliminar subtarea",
        text: subtask.title,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/tasks/${subtask.id}`);
        await this.reloadSelectedTask();
        await this.refreshData();
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async reloadSelectedTask() {
      if (!this.selectedTask) return;
      const response = await axios.get(`/api/tasks/${this.selectedTask.id}`);
      this.selectedTask = response.data.data;
      this.form = this.taskToForm(this.selectedTask);
    },
    async loadAssigners(page = 1) {
      if (!this.canManageAssigners) return;
      this.assignerLoading = true;
      try {
        const response = await axios.get("/api/tasks/assigners", {
          params: { page, per_page: 50 },
        });
        this.assigners = response.data.data || [];
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.assignerLoading = false;
      }
    },
    editAssigner(assigner) {
      this.assignerForm = {
        id: assigner.id,
        target_user_id: assigner.target_user_id,
        assigner_user_id: assigner.assigner_user_id,
        active: Boolean(assigner.active),
      };
    },
    resetAssignerForm() {
      this.assignerForm = {
        id: null,
        target_user_id: null,
        assigner_user_id: null,
        active: true,
      };
    },
    async saveAssigner() {
      if (!this.assignerForm.target_user_id || !this.assignerForm.assigner_user_id) {
        this.error = "Selecciona receptor y asignador.";
        return;
      }

      this.assignerSaving = true;
      try {
        const payload = { ...this.assignerForm };
        if (payload.id) {
          await axios.put(`/api/tasks/assigners/${payload.id}`, payload);
        } else {
          await axios.post("/api/tasks/assigners", payload);
        }
        this.resetAssignerForm();
        await this.loadAssigners();
        this.success = "Asignador guardado correctamente.";
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.assignerSaving = false;
      }
    },
    async deactivateAssigner(assigner) {
      const result = await Swal.fire({
        title: "Desactivar asignador",
        text: `${this.userLabel(assigner.assigner_user)} dejará de cargar tareas a ${this.userLabel(assigner.target_user)}.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Desactivar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;

      await axios.delete(`/api/tasks/assigners/${assigner.id}`);
      await this.loadAssigners();
    },
    userLabel(user) {
      if (!user) return "-";
      const staffName = user.staff?.full_name;
      const cargo = user.staff?.cargo?.name;
      return `${staffName || user.name}${cargo ? ` · ${cargo}` : ""}`;
    },
    statusLabel(value) {
      return (this.catalogs.statuses || []).find((item) => item.value === value)?.label || value;
    },
    priorityLabel(value) {
      return (this.catalogs.priorities || []).find((item) => item.value === value)?.label || value;
    },
    priorityClass(value) {
      return {
        urgente: "badge-soft-danger",
        alta: "badge-soft-warning",
        media: "badge-soft-info",
        baja: "badge-soft-success",
      }[value] || "badge-soft-secondary";
    },
    statusClass(value) {
      return {
        pendiente: "badge-soft-secondary",
        en_progreso: "badge-soft-primary",
        bloqueada: "badge-soft-danger",
        en_revision: "badge-soft-warning",
        completada: "badge-soft-success",
        cancelada: "badge-soft-dark",
      }[value] || "badge-soft-secondary";
    },
    priorityColor(value) {
      return {
        urgente: "#d63939",
        alta: "#f59f00",
        media: "#339af0",
        baja: "#2f9e44",
      }[value] || "#6c757d";
    },
    statusColor(value) {
      return {
        pendiente: "#6c757d",
        en_progreso: "#3b82f6",
        bloqueada: "#dc3545",
        en_revision: "#f59f00",
        completada: "#2f9e44",
        cancelada: "#495057",
      }[value] || "#6c757d";
    },
    formatDate(value) {
      if (!value) return "-";
      return String(value).slice(0, 10);
    },
    formatDateTime(value) {
      if (!value) return "-";
      return String(value).replace("T", " ").slice(0, 16);
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (errors ? errors[Object.keys(errors)[0]]?.[0] : null) || error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <div>
        <h4 class="mb-1">Backlog de tareas</h4>
        <div class="text-muted">Gestiona tareas personales, subtareas y asignaciones autorizadas.</div>
      </div>
      <BButton variant="primary" @click="newTask">
        <i class="bx bx-plus me-1"></i>Nueva tarea
      </BButton>
    </div>

    <BAlert v-if="error" variant="danger" show dismissible @dismissed="error = null">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show dismissible @dismissed="success = null">{{ success }}</BAlert>

    <section class="task-eisenhower mb-3">
      <div class="row g-3 align-items-center">
        <div class="col-lg-7">
          <h5 class="mb-2">Matriz de Eisenhower</h5>
          <p class="mb-0">
            La Matriz de Eisenhower ayuda a priorizar tareas según dos criterios: urgencia e importancia. Las tareas urgentes e importantes deben abordarse primero; las importantes pero no urgentes deben planificarse; las urgentes pero poco importantes pueden delegarse cuando corresponda; y las que no son urgentes ni importantes deberían evitarse, posponerse o eliminarse. Usa la prioridad y la fecha de corte para ordenar tu backlog con este criterio.
          </p>
        </div>
        <div class="col-lg-5">
          <div class="eisenhower-grid">
            <div><strong>Urgente + Importante</strong><span>Hacer ahora</span></div>
            <div><strong>Importante + No urgente</strong><span>Planificar</span></div>
            <div><strong>Urgente + No importante</strong><span>Delegar</span></div>
            <div><strong>No urgente + No importante</strong><span>Eliminar o postergar</span></div>
          </div>
        </div>
      </div>
    </section>

    <BRow class="g-3 mb-3">
      <BCol v-for="card in statsCards" :key="card.label" sm="6" lg="3">
        <BCard no-body class="task-stat h-100" :class="{ 'task-stat-danger': card.danger }">
          <BCardBody>
            <div class="text-muted small">{{ card.label }}</div>
            <div class="h4 mb-0">{{ card.value }}</div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BCard class="mb-3" no-body>
      <BCardBody>
        <div class="row g-3 align-items-end">
          <div class="col-lg-3">
            <label class="form-label">Buscar</label>
            <BFormInput v-model="filters.search" placeholder="Nombre, descripción o stakeholder" @keyup.enter="refreshData" />
          </div>
          <div class="col-lg-3">
            <label class="form-label">Funcionario</label>
            <Multiselect v-model="filters.owner_user_id" :options="userOptions" :searchable="true" />
          </div>
          <div class="col-lg-2">
            <label class="form-label">Estado</label>
            <Multiselect v-model="filters.status" :options="filterStatusOptions" />
          </div>
          <div class="col-lg-2">
            <label class="form-label">Prioridad</label>
            <Multiselect v-model="filters.priority" :options="filterPriorityOptions" />
          </div>
          <div class="col-lg-2">
            <label class="form-label">Creador</label>
            <Multiselect v-model="filters.created_by_user_id" :options="userOptions" :searchable="true" />
          </div>
          <div class="col-lg-3">
            <label class="form-label">Stakeholder</label>
            <BFormInput v-model="filters.stakeholder" />
          </div>
          <div class="col-lg-2">
            <label class="form-label">Desde</label>
            <BFormInput v-model="filters.due_date_from" type="date" />
          </div>
          <div class="col-lg-2">
            <label class="form-label">Hasta</label>
            <BFormInput v-model="filters.due_date_to" type="date" />
          </div>
          <div class="col-lg-3">
            <label class="form-label">Origen</label>
            <Multiselect v-model="filters.created_scope" :options="createdScopeOptions" />
          </div>
          <div class="col-lg-2">
            <BFormCheckbox v-model="filters.overdue">Vencidas</BFormCheckbox>
            <BFormCheckbox v-model="filters.has_subtasks">Con subtareas</BFormCheckbox>
          </div>
          <div class="col-lg-12 d-flex flex-wrap gap-2">
            <BButton variant="primary" @click="refreshData">Filtrar</BButton>
            <BButton variant="outline-secondary" @click="clearFilters">Limpiar</BButton>
          </div>
        </div>
      </BCardBody>
    </BCard>

    <div class="d-flex flex-wrap gap-2 mb-3">
      <BButton :variant="viewMode === 'table' ? 'primary' : 'outline-secondary'" @click="setView('table')">
        <i class="bx bx-table me-1"></i>Tabla
      </BButton>
      <BButton :variant="viewMode === 'calendar' ? 'primary' : 'outline-secondary'" @click="setView('calendar')">
        <i class="bx bx-calendar me-1"></i>Calendario
      </BButton>
      <BButton :variant="viewMode === 'kanban' ? 'primary' : 'outline-secondary'" @click="setView('kanban')">
        <i class="bx bx-columns me-1"></i>Kanban
      </BButton>
      <BButton v-if="canManageAssigners" :variant="viewMode === 'assigners' ? 'primary' : 'outline-secondary'" @click="setView('assigners')">
        <i class="bx bx-user-check me-1"></i>Asignadores
      </BButton>
    </div>

    <LoadingState v-if="loading" message="Cargando tareas..." compact />

    <BCard v-if="!loading && viewMode === 'table'" no-body>
      <BCardBody>
        <div v-if="!tasks.length" class="text-center text-muted py-4">No hay tareas para los filtros seleccionados.</div>
        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0 task-table">
            <thead>
              <tr>
                <th>Nombre tarea</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Stakeholder</th>
                <th>Fecha de corte</th>
                <th>Avance</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="task in tasks" :key="task.id">
                <tr :class="{ 'table-danger': task.is_overdue, 'table-warning': task.is_due_soon && !task.is_overdue }">
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <BButton v-if="(task.subtasks || []).length" size="sm" variant="light" class="task-toggle" @click.stop="toggleSubtasks(task)">
                        <i :class="expanded[task.id] ? 'bx bx-chevron-down' : 'bx bx-chevron-right'"></i>
                      </BButton>
                      <button class="btn btn-link p-0 text-start task-title" @click="openTask(task)">{{ task.title }}</button>
                    </div>
                    <div class="text-muted small">{{ userLabel(task.owner) }}</div>
                  </td>
                  <td><span class="badge rounded-pill" :class="priorityClass(task.priority)">{{ priorityLabel(task.priority) }}</span></td>
                  <td><span class="badge rounded-pill" :class="statusClass(task.status)">{{ statusLabel(task.status) }}</span></td>
                  <td>{{ task.stakeholder || "-" }}</td>
                  <td>{{ formatDate(task.due_date) }}</td>
                  <td>
                    <span v-if="task.subtasks_progress?.total">{{ task.subtasks_progress.completed }}/{{ task.subtasks_progress.total }}</span>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td class="text-end">
                    <BButton size="sm" variant="outline-primary" @click="openTask(task)">Abrir</BButton>
                  </td>
                </tr>
                <tr v-for="subtask in task.subtasks || []" v-show="expanded[task.id]" :key="`subtask-${subtask.id}`" class="task-subrow">
                  <td>
                    <button class="btn btn-link p-0 text-start task-title" @click="openTask(subtask)">
                      <i class="bx bx-subdirectory-right me-1"></i>{{ subtask.title }}
                    </button>
                  </td>
                  <td><span class="badge rounded-pill" :class="priorityClass(subtask.priority)">{{ priorityLabel(subtask.priority) }}</span></td>
                  <td><span class="badge rounded-pill" :class="statusClass(subtask.status)">{{ statusLabel(subtask.status) }}</span></td>
                  <td>{{ subtask.stakeholder || "-" }}</td>
                  <td>{{ formatDate(subtask.due_date) }}</td>
                  <td class="text-muted">Subtarea</td>
                  <td class="text-end">
                    <BButton size="sm" variant="outline-primary" @click="openTask(subtask)">Abrir</BButton>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </BCardBody>
    </BCard>

    <BCard v-if="!loading && viewMode === 'calendar'" no-body>
      <BCardBody>
        <FullCalendar :options="calendarOptions" />
        <hr />
        <h6>Tareas sin fecha de corte</h6>
        <div v-if="!undatedTasks.length" class="text-muted">No hay tareas sin fecha de corte.</div>
        <div v-else class="d-flex flex-wrap gap-2">
          <button v-for="task in undatedTasks" :key="`undated-${task.id}`" class="btn btn-outline-secondary btn-sm" @click="openTask(task)">
            {{ task.title }}
          </button>
        </div>
      </BCardBody>
    </BCard>

    <div v-if="!loading && viewMode === 'kanban'" class="kanban-board">
      <div v-for="status in catalogs.statuses" :key="status.value" class="kanban-column">
        <div class="kanban-column-header">
          <span>{{ status.label }}</span>
          <span class="badge bg-light text-dark">{{ (kanbanColumns[status.value] || []).length }}</span>
        </div>
        <draggable :list="kanbanColumns[status.value]" group="tasks" item-key="id" class="kanban-list" @change="onKanbanChange($event, status.value)">
          <div v-for="element in kanbanColumns[status.value] || []" :key="element.id" class="kanban-card" @click="openTask(element)">
            <div class="fw-semibold">{{ element.title }}</div>
            <div class="text-muted small">{{ element.stakeholder || "Sin stakeholder" }}</div>
            <div class="d-flex flex-wrap gap-2 mt-2">
              <span class="badge rounded-pill" :class="priorityClass(element.priority)">{{ priorityLabel(element.priority) }}</span>
              <span class="badge rounded-pill" :class="{ 'badge-soft-danger': element.is_overdue, 'badge-soft-warning': element.is_due_soon && !element.is_overdue, 'badge-soft-secondary': !element.due_date }">
                {{ formatDate(element.due_date) }}
              </span>
            </div>
          </div>
        </draggable>
        <div v-if="!(kanbanColumns[status.value] || []).length" class="kanban-empty">Sin tareas</div>
      </div>
    </div>

    <BCard v-if="viewMode === 'assigners' && canManageAssigners" no-body>
      <BCardBody>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-0">Asignadores de tareas</h5>
            <div class="text-muted">Define quién puede cargar tareas a un funcionario específico.</div>
          </div>
          <BButton variant="outline-secondary" @click="resetAssignerForm">Nuevo</BButton>
        </div>

        <div class="row g-3 align-items-end mb-3">
          <div class="col-md-4">
            <label class="form-label">Funcionario receptor</label>
            <Multiselect v-model="assignerForm.target_user_id" :options="userOptions.filter((item) => item.value)" :searchable="true" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Usuario asignador</label>
            <Multiselect v-model="assignerForm.assigner_user_id" :options="userOptions.filter((item) => item.value)" :searchable="true" />
          </div>
          <div class="col-md-2">
            <BFormCheckbox v-model="assignerForm.active">Activo</BFormCheckbox>
          </div>
          <div class="col-md-2">
            <BButton variant="primary" class="w-100" :disabled="assignerSaving" @click="saveAssigner">
              {{ assignerSaving ? "Guardando..." : "Guardar" }}
            </BButton>
          </div>
        </div>

        <LoadingState v-if="assignerLoading" message="Cargando asignadores..." compact />
        <div v-else class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Funcionario receptor</th>
                <th>Asignador autorizado</th>
                <th>Estado</th>
                <th>Creado por</th>
                <th>Fecha creación</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="assigner in assigners" :key="assigner.id">
                <td>{{ userLabel(assigner.target_user) }}</td>
                <td>{{ userLabel(assigner.assigner_user) }}</td>
                <td>
                  <span class="badge rounded-pill" :class="assigner.active ? 'badge-soft-success' : 'badge-soft-secondary'">
                    {{ assigner.active ? "Activo" : "Inactivo" }}
                  </span>
                </td>
                <td>{{ assigner.created_by?.name || "-" }}</td>
                <td>{{ formatDateTime(assigner.created_at) }}</td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <BButton variant="outline-primary" @click="editAssigner(assigner)">Editar</BButton>
                    <BButton variant="outline-danger" :disabled="!assigner.active" @click="deactivateAssigner(assigner)">Desactivar</BButton>
                  </div>
                </td>
              </tr>
              <tr v-if="!assigners.length">
                <td colspan="6" class="text-center text-muted py-4">No hay asignadores configurados.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </BCardBody>
    </BCard>

    <BModal v-model="showTaskModal" size="xl" hide-footer title="Detalle de tarea">
      <div class="row g-3">
        <div class="col-lg-8">
          <label class="form-label">Nombre tarea</label>
          <BFormInput v-model="form.title" />
        </div>
        <div class="col-lg-4">
          <label class="form-label">Funcionario responsable</label>
          <Multiselect v-model="form.owner_user_id" :options="assignableUserOptions" :searchable="true" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Prioridad</label>
          <Multiselect v-model="form.priority" :options="priorityOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <Multiselect v-model="form.status" :options="statusOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Stakeholder</label>
          <BFormInput v-model="form.stakeholder" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha de corte</label>
          <BFormInput v-model="form.due_date" type="date" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción / información complementaria</label>
          <BFormTextarea v-model="form.description" rows="5" />
        </div>
        <div class="col-md-6">
          <BFormCheckbox v-model="form.auto_complete_parent_on_subtasks_done">
            Completar automáticamente cuando todas sus subtareas estén completadas
          </BFormCheckbox>
        </div>
        <div class="col-md-6 text-md-end text-muted">
          <span v-if="selectedTask">Creador: {{ selectedTask.creator?.name || "-" }} · Actualizado: {{ formatDateTime(selectedTask.updated_at) }}</span>
        </div>
      </div>

      <hr />
      <div v-if="selectedTask && !selectedTask.parent_task_id">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Subtareas</h6>
          <span v-if="selectedTask.subtasks_progress?.total" class="text-muted">
            {{ selectedTask.subtasks_progress.completed }}/{{ selectedTask.subtasks_progress.total }} completadas
          </span>
        </div>

        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Fecha de corte</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="subtask in selectedTask.subtasks || []" :key="subtask.id">
                <td><BFormInput v-model="subtask.title" size="sm" /></td>
                <td><Multiselect v-model="subtask.priority" :options="priorityOptions" /></td>
                <td><Multiselect v-model="subtask.status" :options="statusOptions" /></td>
                <td><BFormInput v-model="subtask.due_date" type="date" size="sm" /></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <BButton variant="outline-primary" @click="updateSubtask(subtask)">Guardar</BButton>
                    <BButton variant="outline-danger" @click="deleteSubtask(subtask)">Eliminar</BButton>
                  </div>
                </td>
              </tr>
              <tr v-if="!(selectedTask.subtasks || []).length">
                <td colspan="5" class="text-muted text-center">Sin subtareas.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Nueva subtarea</label>
            <BFormInput v-model="subtaskForm.title" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Prioridad</label>
            <Multiselect v-model="subtaskForm.priority" :options="priorityOptions" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Estado</label>
            <Multiselect v-model="subtaskForm.status" :options="statusOptions" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Fecha</label>
            <BFormInput v-model="subtaskForm.due_date" type="date" />
          </div>
          <div class="col-md-2">
            <BButton variant="outline-primary" class="w-100" :disabled="subtaskSaving" @click="createSubtask">
              {{ subtaskSaving ? "Agregando..." : "Agregar" }}
            </BButton>
          </div>
        </div>
      </div>

      <div v-if="selectedTask" class="mt-4">
        <h6>Historial básico</h6>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Acción</th>
                <th>Usuario</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in selectedTask.activity_logs || []" :key="log.id">
                <td>{{ formatDateTime(log.created_at) }}</td>
                <td>{{ log.action }}</td>
                <td>{{ log.user?.name || "-" }}</td>
              </tr>
              <tr v-if="!(selectedTask.activity_logs || []).length">
                <td colspan="3" class="text-muted text-center">Sin actividad registrada.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
        <BButton v-if="selectedTask" variant="outline-danger" @click="deleteTask">Eliminar</BButton>
        <div class="ms-auto d-flex gap-2">
          <BButton variant="outline-secondary" @click="showTaskModal = false">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="saveTask">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.task-eisenhower {
  background: #f7f9fb;
  border: 1px solid #e7ecf2;
  border-radius: 8px;
  padding: 18px;
}

.eisenhower-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.eisenhower-grid > div {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #4dabf7;
  border-radius: 6px;
  padding: 10px;
  min-height: 76px;
}

.eisenhower-grid strong,
.eisenhower-grid span {
  display: block;
}

.task-stat {
  border-radius: 8px;
  border: 1px solid #e8edf3;
}

.task-stat-danger {
  border-color: #f1aeb5;
}

.task-title {
  color: #243447;
  font-weight: 600;
  text-decoration: none;
}

.task-table {
  --bs-table-hover-bg: #eef4ff;
  --bs-table-hover-color: #243447;
}

.task-table tbody tr {
  color: #243447;
}

.task-table tbody tr:hover > * {
  background-color: var(--bs-table-hover-bg) !important;
  color: var(--bs-table-hover-color) !important;
}

.task-table tbody tr:hover .text-muted,
.task-table tbody tr:hover .task-title {
  color: #243447 !important;
}

.task-table tbody tr.table-warning {
  --bs-table-bg: #fff4df;
  --bs-table-hover-bg: #ffedc2;
  --bs-table-hover-color: #1f2937;
}

.task-table tbody tr.table-danger {
  --bs-table-bg: #fdecef;
  --bs-table-hover-bg: #fbd7dd;
  --bs-table-hover-color: #1f2937;
}

.task-table tbody tr.task-subrow {
  --bs-table-hover-bg: #f5f8fc;
  --bs-table-hover-color: #243447;
}

.task-toggle {
  width: 28px;
  height: 28px;
  padding: 0;
}

.task-subrow td:first-child {
  padding-left: 46px;
}

.kanban-board {
  display: grid;
  grid-template-columns: repeat(6, minmax(240px, 1fr));
  gap: 14px;
  overflow-x: auto;
  padding-bottom: 8px;
}

.kanban-column {
  background: #f8fafc;
  border: 1px solid #e6ebf1;
  border-radius: 8px;
  min-height: 520px;
  display: flex;
  flex-direction: column;
}

.kanban-column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  font-weight: 700;
  border-bottom: 1px solid #e6ebf1;
}

.kanban-list {
  flex: 1;
  padding: 10px;
  min-height: 120px;
}

.kanban-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 10px;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
}

.kanban-empty {
  color: #7a8194;
  font-size: 0.875rem;
  padding: 0 12px 12px;
}

:deep(.fc .task-calendar-event) {
  background-color: #e8f8f0 !important;
  border-color: #34c38f !important;
  color: #087f5b !important;
}

:deep(.fc .task-calendar-event .fc-event-title),
:deep(.fc .task-calendar-event .fc-event-main) {
  color: #087f5b !important;
  font-weight: 700;
}

@media (max-width: 991.98px) {
  .kanban-board {
    grid-template-columns: repeat(6, 260px);
  }

  .eisenhower-grid {
    grid-template-columns: 1fr;
  }
}
</style>
