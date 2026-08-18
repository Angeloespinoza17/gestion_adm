<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import { defineAsyncComponent } from "vue";
import { VueDraggableNext } from "vue-draggable-next";
import Swal from "sweetalert2";

const TaskCalendar = defineAsyncComponent(() => import("./components/TaskCalendar.vue"));

const emptyFilters = () => ({
  search: "",
  owner_user_id: null,
  status: null,
  priority: null,
  stakeholder_user_id: null,
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
  stakeholder_user_ids: [],
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
    TaskCalendar,
    draggable: VueDraggableNext,
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      success: null,
      viewMode: "table",
      showAdvancedFilters: false,
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
    isAssignerPage() {
      return this.$route.path.includes("/tasks/assigners");
    },
    canViewReports() {
      return Boolean(this.catalogs.capabilities?.can_view_reports);
    },
    isSuperAdmin() {
      return Boolean(this.catalogs.capabilities?.is_super_admin);
    },
    canEditCurrentTask() {
      return !this.selectedTask || Number(this.selectedTask.owner_user_id) === Number(this.catalogs.current_user?.id);
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
    stakeholderOptions() {
      return (this.catalogs.users || [])
        .filter((user) => Number(user.id) !== Number(this.form.owner_user_id))
        .map((user) => ({ value: user.id, label: this.userLabel(user) }));
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
        { key: "total", label: "Total", value: this.stats.total, icon: "bx-layer", tone: "blue" },
        { key: "pending", label: "Pendientes", value: this.stats.pending, icon: "bx-time-five", tone: "slate", status: "pendiente" },
        { key: "progress", label: "En progreso", value: this.stats.in_progress, icon: "bx-loader-circle", tone: "indigo", status: "en_progreso" },
        { key: "completed", label: "Completadas", value: this.stats.completed, icon: "bx-check-circle", tone: "green", status: "completada" },
      ];
    },
    secondaryStats() {
      if (!this.stats) return [];
      return [
        { key: "overdue", label: "Vencidas", value: this.stats.overdue, icon: "bx-error-circle", danger: this.stats.overdue > 0 },
        { key: "next", label: "Próximos 7 días", value: this.stats.due_next_7_days, icon: "bx-calendar-event" },
        { key: "third", label: "Asignadas por terceros", value: this.stats.created_by_third_parties, icon: "bx-user-pin" },
        { key: "average", label: "Promedio de cierre", value: this.stats.average_days_to_complete === null ? "—" : `${this.stats.average_days_to_complete} días`, icon: "bx-timer" },
      ];
    },
    activeFilterCount() {
      return Object.values(this.filters).filter((value) => value !== null && value !== "" && value !== false).length;
    },
    currentViewLabel() {
      return { table: "Lista de tareas", calendar: "Calendario", kanban: "Tablero Kanban" }[this.viewMode] || "Tareas";
    },
    flatTasks() {
      return this.tasks.flatMap((task) => [task].concat(task.subtasks || []));
    },
    undatedTasks() {
      return this.flatTasks.filter((task) => !task.due_date);
    },
  },
  async mounted() {
    if (this.isAssignerPage) {
      this.viewMode = "assigners";
    }

    await this.loadCatalogs();
    if (this.isAssignerPage) {
      await this.loadAssigners();
    } else {
      await this.refreshData();
    }
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/tasks/catalogs");
      this.catalogs = response.data;
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
        stakeholder_user_id: this.filters.stakeholder_user_id,
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
      this.filters = emptyFilters();
      this.refreshData();
    },
    applyStatFilter(card) {
      this.filters.status = card.status || null;
      this.filters.overdue = card.key === "overdue";
      this.filters.created_scope = card.key === "third" ? "third_party" : null;
      if (card.key === "next") {
        const today = new Date();
        const nextWeek = new Date(today);
        nextWeek.setDate(today.getDate() + 7);
        const formatLocalDate = (date) => [
          date.getFullYear(),
          String(date.getMonth() + 1).padStart(2, "0"),
          String(date.getDate()).padStart(2, "0"),
        ].join("-");
        this.filters.due_date_from = formatLocalDate(today);
        this.filters.due_date_to = formatLocalDate(nextWeek);
      } else {
        this.filters.due_date_from = "";
        this.filters.due_date_to = "";
      }
      this.refreshData();
    },
    isInsightActive(item) {
      if (item.key === "overdue") return this.filters.overdue;
      if (item.key === "third") return this.filters.created_scope === "third_party";
      if (item.key === "next") return Boolean(this.filters.due_date_from && this.filters.due_date_to);
      return false;
    },
    isStatActive(card) {
      const hasInsightFilter = this.filters.overdue
        || this.filters.created_scope === "third_party"
        || Boolean(this.filters.due_date_from && this.filters.due_date_to);
      if (hasInsightFilter) return false;
      return card.key === "total" ? !this.filters.status : this.filters.status === card.status;
    },
    setView(view) {
      if (view === "assigners") {
        this.$router.push("/tasks/assigners");
        return;
      }
      this.viewMode = view;
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
        stakeholder_user_ids: (task.stakeholders || []).map((stakeholder) => stakeholder.id),
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
    stakeholderNames(task) {
      const names = (task?.stakeholders || []).map((user) => user.staff?.full_name || user.name);
      return names.length ? names.join(", ") : "Privada";
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
    <section class="task-hero mb-4">
      <div>
        <span class="task-eyebrow"><i class="bx" :class="isAssignerPage ? 'bx-user-check' : 'bx-lock-alt'"></i> {{ isAssignerPage ? "Gobernanza de tareas" : "Espacio personal" }}</span>
        <h2>{{ isAssignerPage ? "Asignadores autorizados" : "Mi backlog" }}</h2>
        <p>{{ isAssignerPage ? "Define con precisión quién puede cargar tareas al backlog de cada funcionario." : "Organiza tus pendientes y comparte únicamente las tareas que necesiten colaboración." }}</p>
      </div>
      <div class="task-hero-actions">
        <BButton v-if="isAssignerPage" variant="light" @click="$router.push('/tasks/backlog')"><i class="bx bx-arrow-back me-1"></i>Mi backlog</BButton>
        <BButton v-if="isSuperAdmin" variant="light" @click="$router.push('/tasks/all')">
          <i class="bx bx-layer me-1"></i>Todas las tareas
        </BButton>
        <BButton v-if="canViewReports" variant="light" @click="$router.push('/tasks/reports')">
          <i class="bx bx-bar-chart-alt-2 me-1"></i>Reportes
        </BButton>
        <BButton v-if="!isAssignerPage" variant="primary" @click="newTask">
          <i class="bx bx-plus me-1"></i>Nueva tarea
        </BButton>
      </div>
    </section>

    <BAlert v-if="error" variant="danger" show dismissible @dismissed="error = null">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show dismissible @dismissed="success = null">{{ success }}</BAlert>

    <section v-if="!isAssignerPage" class="task-focus mb-3">
      <div class="task-focus-intro">
        <span class="task-section-kicker">Foco de la semana</span>
        <strong>Prioriza con intención</strong>
        <span>Ordena cada tarea según urgencia e impacto.</span>
      </div>
      <div class="task-focus-options">
        <div class="task-focus-item matrix-now"><i class="bx bx-bolt-circle"></i><span><strong>Hacer ahora</strong><small>Urgente e importante</small></span></div>
        <div class="task-focus-item matrix-plan"><i class="bx bx-calendar-check"></i><span><strong>Planificar</strong><small>Importante, no urgente</small></span></div>
        <div class="task-focus-item matrix-delegate"><i class="bx bx-user-plus"></i><span><strong>Delegar</strong><small>Urgente, menor impacto</small></span></div>
        <div class="task-focus-item matrix-later"><i class="bx bx-time-five"></i><span><strong>Postergar</strong><small>Sin urgencia ni impacto</small></span></div>
      </div>
    </section>

    <section v-if="!isAssignerPage" class="task-summary mb-3">
      <button
        v-for="card in statsCards"
        :key="card.key"
        type="button"
        class="task-stat"
        :class="[`task-stat-${card.tone}`, { active: isStatActive(card) }]"
        @click="applyStatFilter(card)"
      >
        <span class="task-stat-icon"><i class="bx" :class="card.icon"></i></span>
        <span class="task-stat-copy"><small>{{ card.label }}</small><strong>{{ card.value }}</strong></span>
        <i class="bx bx-chevron-right task-stat-arrow"></i>
      </button>
    </section>

    <section v-if="!isAssignerPage" class="task-insights mb-3">
      <button
        v-for="item in secondaryStats"
        :key="item.key"
        type="button"
        :disabled="item.key === 'average'"
        :class="{ 'task-insight-danger': item.danger, active: isInsightActive(item) }"
        @click="item.key !== 'average' && applyStatFilter(item)"
      >
        <i class="bx" :class="item.icon"></i>
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
      </button>
    </section>

    <section v-if="!isAssignerPage" class="task-workspace-bar mb-3">
      <div>
        <span class="task-section-kicker mb-1">Tu espacio de trabajo</span>
        <h5 class="mb-0">{{ currentViewLabel }} <span class="task-count">{{ tasks.length }}</span></h5>
      </div>
      <div class="task-view-switch" aria-label="Cambiar vista">
        <button type="button" :class="{ active: viewMode === 'table' }" @click="setView('table')"><i class="bx bx-list-ul"></i><span>Lista</span></button>
        <button type="button" :class="{ active: viewMode === 'calendar' }" @click="setView('calendar')"><i class="bx bx-calendar"></i><span>Calendario</span></button>
        <button type="button" :class="{ active: viewMode === 'kanban' }" @click="setView('kanban')"><i class="bx bx-columns"></i><span>Kanban</span></button>
      </div>
    </section>

    <BCard v-if="!isAssignerPage" class="task-filter-panel mb-3" no-body>
      <BCardBody>
        <div class="task-filter-toolbar">
          <div class="task-search">
            <i class="bx bx-search"></i>
            <BFormInput v-model="filters.search" placeholder="Buscar una tarea..." aria-label="Buscar tarea" @keyup.enter="refreshData" />
          </div>
          <div class="task-filter-select">
            <Multiselect v-model="filters.status" :options="filterStatusOptions" placeholder="Estado" />
          </div>
          <div class="task-filter-select">
            <Multiselect v-model="filters.priority" :options="filterPriorityOptions" placeholder="Prioridad" />
          </div>
          <BButton variant="outline-secondary" class="task-more-filters" @click="showAdvancedFilters = !showAdvancedFilters">
            <i class="bx bx-slider-alt me-1"></i>Más filtros
            <span v-if="activeFilterCount" class="task-filter-count">{{ activeFilterCount }}</span>
            <i class="bx ms-1" :class="showAdvancedFilters ? 'bx-chevron-up' : 'bx-chevron-down'"></i>
          </BButton>
          <BButton variant="primary" @click="refreshData"><i class="bx bx-filter-alt me-1"></i>Aplicar</BButton>
          <BButton v-if="activeFilterCount" variant="link" class="task-clear-filter" @click="clearFilters">Limpiar</BButton>
        </div>

        <div v-show="showAdvancedFilters" class="task-advanced-filters">
          <div class="row g-3 align-items-end">
            <div class="col-md-4 col-xl-3">
              <label class="form-label">Funcionario</label>
              <Multiselect v-model="filters.owner_user_id" :options="userOptions" :searchable="true" />
            </div>
            <div class="col-md-4 col-xl-3">
              <label class="form-label">Creador</label>
              <Multiselect v-model="filters.created_by_user_id" :options="userOptions" :searchable="true" />
            </div>
            <div class="col-md-4 col-xl-3">
              <label class="form-label">Stakeholder</label>
              <Multiselect v-model="filters.stakeholder_user_id" :options="userOptions" :searchable="true" />
            </div>
            <div class="col-md-4 col-xl-3">
              <label class="form-label">Origen</label>
              <Multiselect v-model="filters.created_scope" :options="createdScopeOptions" />
            </div>
            <div class="col-md-4 col-xl-3">
              <label class="form-label">Fecha desde</label>
              <BFormInput v-model="filters.due_date_from" type="date" />
            </div>
            <div class="col-md-4 col-xl-3">
              <label class="form-label">Fecha hasta</label>
              <BFormInput v-model="filters.due_date_to" type="date" />
            </div>
            <div class="col-md-8 col-xl-6 task-check-filters">
              <BFormCheckbox v-model="filters.overdue">Solo vencidas</BFormCheckbox>
              <BFormCheckbox v-model="filters.has_subtasks">Con subtareas</BFormCheckbox>
            </div>
          </div>
        </div>
      </BCardBody>
    </BCard>

    <LoadingState v-if="!isAssignerPage && loading" message="Cargando tareas..." compact />

    <BCard v-if="!isAssignerPage && !loading && viewMode === 'table'" no-body class="task-content-card">
      <BCardBody>
        <div v-if="!tasks.length" class="task-empty">
          <span class="task-empty-icon"><i class="bx" :class="activeFilterCount ? 'bx-search-alt' : 'bx-check-double'"></i></span>
          <h5>{{ activeFilterCount ? "No encontramos tareas" : "Tu backlog está despejado" }}</h5>
          <p>{{ activeFilterCount ? "Prueba ajustando o limpiando los filtros seleccionados." : "No tienes tareas pendientes. Puedes crear una cuando la necesites." }}</p>
          <BButton v-if="activeFilterCount" variant="outline-secondary" @click="clearFilters">Limpiar filtros</BButton>
          <BButton v-else variant="primary" @click="newTask"><i class="bx bx-plus me-1"></i>Crear primera tarea</BButton>
        </div>
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
                  <td><span class="task-privacy"><i class="bx" :class="(task.stakeholders || []).length ? 'bx-group' : 'bx-lock-alt'"></i>{{ stakeholderNames(task) }}</span></td>
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
                  <td>{{ stakeholderNames(subtask) }}</td>
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

    <BCard v-if="!isAssignerPage && !loading && viewMode === 'calendar'" no-body>
      <BCardBody>
        <TaskCalendar :options="calendarOptions" />
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

    <div v-if="!isAssignerPage && !loading && viewMode === 'kanban'" class="kanban-board">
      <div v-for="status in catalogs.statuses" :key="status.value" class="kanban-column">
        <div class="kanban-column-header">
          <span>{{ status.label }}</span>
          <span class="badge bg-light text-dark">{{ (kanbanColumns[status.value] || []).length }}</span>
        </div>
        <draggable :list="kanbanColumns[status.value]" group="tasks" item-key="id" class="kanban-list" @change="onKanbanChange($event, status.value)">
          <div v-for="element in kanbanColumns[status.value] || []" :key="element.id" class="kanban-card" @click="openTask(element)">
            <div class="fw-semibold">{{ element.title }}</div>
            <div class="text-muted small"><i class="bx bx-group me-1"></i>{{ stakeholderNames(element) }}</div>
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

    <BCard v-if="isAssignerPage && canManageAssigners" no-body class="assigner-panel">
      <BCardBody>
        <div class="assigner-note mb-4">
          <i class="bx bx-info-circle"></i>
          <div><strong>Regla de asignación</strong><span>Superadmin puede asignar a cualquier funcionario. Para las demás jefaturas, crea una autorización por cada persona de su equipo.</span></div>
        </div>
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
          <BFormInput v-model="form.title" :disabled="!canEditCurrentTask" />
        </div>
        <div class="col-lg-4">
          <label class="form-label">Funcionario responsable</label>
          <Multiselect v-model="form.owner_user_id" :options="assignableUserOptions" :searchable="true" :disabled="!canEditCurrentTask" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Prioridad</label>
          <Multiselect v-model="form.priority" :options="priorityOptions" :disabled="!canEditCurrentTask" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <Multiselect v-model="form.status" :options="statusOptions" :disabled="!canEditCurrentTask" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Stakeholders</label>
          <Multiselect
            v-model="form.stakeholder_user_ids"
            :options="stakeholderOptions"
            mode="tags"
            :searchable="true"
            :close-on-select="false"
            :disabled="!canEditCurrentTask"
            placeholder="Personas que podrán ver la tarea"
          />
          <small class="text-muted">Solo estas personas, además del responsable, tendrán acceso.</small>
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha de corte</label>
          <BFormInput v-model="form.due_date" type="date" :disabled="!canEditCurrentTask" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción / información complementaria</label>
          <BFormTextarea v-model="form.description" rows="5" :disabled="!canEditCurrentTask" />
        </div>
        <div class="col-md-6">
          <BFormCheckbox v-model="form.auto_complete_parent_on_subtasks_done" :disabled="!canEditCurrentTask">
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
                <td><BFormInput v-model="subtask.title" size="sm" :disabled="!canEditCurrentTask" /></td>
                <td><Multiselect v-model="subtask.priority" :options="priorityOptions" :disabled="!canEditCurrentTask" /></td>
                <td><Multiselect v-model="subtask.status" :options="statusOptions" :disabled="!canEditCurrentTask" /></td>
                <td><BFormInput v-model="subtask.due_date" type="date" size="sm" :disabled="!canEditCurrentTask" /></td>
                <td class="text-end">
                  <div v-if="canEditCurrentTask" class="btn-group btn-group-sm">
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

        <div v-if="canEditCurrentTask" class="row g-2 align-items-end">
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
        <BButton v-if="selectedTask && canEditCurrentTask" variant="outline-danger" @click="deleteTask">Eliminar</BButton>
        <div class="ms-auto d-flex gap-2">
          <BButton variant="outline-secondary" @click="showTaskModal = false">Cancelar</BButton>
          <BButton v-if="canEditCurrentTask" variant="primary" :disabled="saving" @click="saveTask">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.task-hero {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 22px 26px;
  border-radius: 18px;
  color: #fff;
  background: linear-gradient(125deg, #172554 0%, #1e3a8a 52%, #2563eb 100%);
  box-shadow: 0 14px 34px rgba(30, 58, 138, 0.18);
}

.task-hero::after {
  content: "";
  position: absolute;
  width: 260px;
  height: 260px;
  right: -80px;
  top: -130px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.09);
}

.task-hero h2 { margin: 5px 0 4px; color: #fff; font-size: 1.65rem; }
.task-hero p { margin: 0; color: rgba(255, 255, 255, 0.78); max-width: 650px; }
.task-hero-actions { position: relative; z-index: 1; display: flex; gap: 10px; flex-shrink: 0; }
.task-eyebrow { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase; color: #bfdbfe; }

.task-section-kicker { display: block; margin-bottom: 5px; color: #2563eb; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }

.task-focus {
  display: grid;
  grid-template-columns: minmax(220px, 0.8fr) minmax(0, 2.2fr);
  gap: 20px;
  align-items: center;
  padding: 14px 16px;
  border: 1px solid #dbe7f8;
  border-radius: 14px;
  background: linear-gradient(110deg, #f8fbff 0%, #f3f7ff 100%);
}

.task-focus-intro > strong,
.task-focus-intro > span { display: block; }
.task-focus-intro > strong { color: #1e293b; font-size: 0.95rem; }
.task-focus-intro > span:last-child { margin-top: 2px; color: #64748b; font-size: 0.78rem; }

.task-focus-options {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.task-focus-item {
  display: grid;
  grid-template-columns: 26px minmax(0, 1fr);
  align-items: center;
  gap: 7px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-left-width: 3px;
  border-radius: 9px;
  padding: 9px 10px;
  min-width: 0;
}

.task-focus-item i { font-size: 1.15rem; }
.task-focus-item span,
.task-focus-item strong,
.task-focus-item small { display: block; min-width: 0; }
.task-focus-item strong { overflow: hidden; color: #334155; font-size: 0.78rem; text-overflow: ellipsis; white-space: nowrap; }
.task-focus-item small { overflow: hidden; color: #7c8aa0; font-size: 0.67rem; text-overflow: ellipsis; white-space: nowrap; }
.matrix-now { border-left-color: #ef4444 !important; }
.matrix-now i { color: #ef4444; }
.matrix-plan { border-left-color: #2563eb !important; }
.matrix-plan i { color: #2563eb; }
.matrix-delegate { border-left-color: #f59e0b !important; }
.matrix-delegate i { color: #f59e0b; }
.matrix-later { border-left-color: #94a3b8 !important; }
.matrix-later i { color: #64748b; }

.task-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.task-stat {
  display: flex;
  align-items: center;
  gap: 11px;
  width: 100%;
  padding: 13px 14px;
  text-align: left;
  background: #fff;
  border: 1px solid #e8edf3;
  border-radius: 13px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
  transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}

.task-stat:hover { transform: translateY(-1px); border-color: #b8cdf7; box-shadow: 0 8px 22px rgba(30, 64, 175, 0.08); }
.task-stat.active { border-color: #5b82e7; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.12); }
.task-stat-icon { display: inline-grid; width: 39px; height: 39px; flex: 0 0 39px; place-items: center; border-radius: 11px; font-size: 1.25rem; }
.task-stat-blue .task-stat-icon { color: #2563eb; background: #eaf2ff; }
.task-stat-slate .task-stat-icon { color: #526177; background: #f0f3f7; }
.task-stat-indigo .task-stat-icon { color: #4f46e5; background: #eeedff; }
.task-stat-green .task-stat-icon { color: #16815f; background: #e8f8f1; }
.task-stat-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.task-stat-copy small { color: #6b7890; font-size: 0.77rem; }
.task-stat-copy strong { color: #172554; font-size: 1.28rem; line-height: 1.15; }
.task-stat-arrow { color: #a8b2c2; font-size: 1.1rem; }

.task-insights {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  overflow: hidden;
  border: 1px solid #e5eaf1;
  border-radius: 12px;
  background: #fff;
}

.task-insights button {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
  padding: 10px 14px;
  color: #64748b;
  text-align: left;
  background: transparent;
  border: 0;
  border-right: 1px solid #e9edf3;
}
.task-insights button:last-child { border-right: 0; }
.task-insights button:not(:disabled):hover,
.task-insights button.active { background: #f6f9ff; }
.task-insights button:disabled { opacity: 1; cursor: default; }
.task-insights i { color: #718096; font-size: 1rem; }
.task-insights span { overflow: hidden; flex: 1; font-size: 0.76rem; text-overflow: ellipsis; white-space: nowrap; }
.task-insights strong { color: #28364b; font-size: 0.84rem; white-space: nowrap; }
.task-insights .task-insight-danger i,
.task-insights .task-insight-danger strong { color: #dc3545; }

.task-workspace-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-top: 4px;
}
.task-workspace-bar h5 { color: #23324a; }
.task-count { display: inline-grid; min-width: 25px; height: 25px; margin-left: 4px; padding: 0 7px; place-items: center; border-radius: 999px; color: #40516b; background: #edf1f7; font-size: 0.75rem; }
.task-view-switch { display: inline-flex; gap: 4px; padding: 4px; border: 1px solid #dfe5ed; border-radius: 11px; background: #f5f7fa; }
.task-view-switch button { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; color: #68768b; border: 0; border-radius: 8px; background: transparent; font-size: 0.82rem; font-weight: 600; }
.task-view-switch button:hover { color: #244fc0; }
.task-view-switch button.active { color: #1e4db7; background: #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08); }

.task-filter-panel,
.task-content-card { border: 1px solid #e3e8ef; border-radius: 14px; box-shadow: 0 5px 20px rgba(15, 23, 42, 0.04); }
.task-filter-panel :deep(.card-body) { padding: 13px 14px; }
.task-filter-toolbar { display: grid; grid-template-columns: minmax(240px, 1.4fr) minmax(155px, 0.55fr) minmax(155px, 0.55fr) auto auto auto; gap: 9px; align-items: center; }
.task-search { position: relative; }
.task-search > i { position: absolute; z-index: 2; top: 50%; left: 13px; color: #8a96a8; font-size: 1.08rem; transform: translateY(-50%); pointer-events: none; }
.task-search :deep(.form-control) { height: 40px; padding-left: 38px; border-color: #dfe5ed; border-radius: 9px; }
.task-filter-select :deep(.multiselect) { min-height: 40px; border-color: #dfe5ed; border-radius: 9px; }
.task-more-filters { display: inline-flex; align-items: center; white-space: nowrap; }
.task-filter-count { display: inline-grid; min-width: 19px; height: 19px; margin-left: 6px; padding: 0 5px; place-items: center; border-radius: 999px; color: #fff; background: #315dcc; font-size: 0.67rem; }
.task-clear-filter { padding-right: 4px; padding-left: 4px; text-decoration: none; white-space: nowrap; }
.task-advanced-filters { margin-top: 14px; padding-top: 14px; border-top: 1px solid #edf0f4; }
.task-advanced-filters .form-label { margin-bottom: 5px; color: #59677a; font-size: 0.76rem; font-weight: 650; }
.task-check-filters { display: flex; align-items: center; gap: 22px; min-height: 40px; padding-bottom: 8px; }

.task-empty { display: flex; min-height: 255px; align-items: center; justify-content: center; flex-direction: column; padding: 34px 20px; text-align: center; }
.task-empty-icon { display: grid; width: 54px; height: 54px; margin-bottom: 13px; place-items: center; color: #315dcc; border-radius: 16px; background: #edf3ff; font-size: 1.8rem; }
.task-empty h5 { margin-bottom: 5px; color: #26364d; }
.task-empty p { max-width: 430px; margin-bottom: 16px; color: #7a8799; }

.task-title {
  color: #243447;
  font-weight: 600;
  text-decoration: none;
}

.task-privacy { display: inline-flex; align-items: center; gap: 5px; color: #475569; font-size: 0.82rem; }
.assigner-panel { border: 1px solid #dbeafe; border-radius: 15px; box-shadow: 0 8px 26px rgba(15, 23, 42, 0.05); }
.assigner-note { display: flex; gap: 11px; padding: 14px 16px; border: 1px solid #bfdbfe; border-radius: 12px; background: #eff6ff; color: #1e3a8a; }
.assigner-note > i { font-size: 1.25rem; margin-top: 1px; }.assigner-note strong,.assigner-note span { display: block; }.assigner-note span { margin-top: 2px; color: #475569; font-size: .84rem; }

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
  border-radius: 14px;
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
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 10px;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.kanban-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(15, 23, 42, 0.1); }

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
  .task-hero { align-items: flex-start; flex-direction: column; padding: 22px; }
  .task-focus { grid-template-columns: 1fr; gap: 12px; }
  .task-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .task-insights { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .task-insights button:nth-child(2) { border-right: 0; }
  .task-insights button:nth-child(-n + 2) { border-bottom: 1px solid #e9edf3; }
  .task-filter-toolbar { grid-template-columns: minmax(220px, 1fr) minmax(150px, 0.7fr) minmax(150px, 0.7fr); }
  .task-filter-toolbar > .btn { justify-self: start; }
  .kanban-board {
    grid-template-columns: repeat(6, 260px);
  }
}

@media (max-width: 767.98px) {
  .task-hero-actions { width: 100%; flex-wrap: wrap; }
  .task-hero-actions .btn { flex: 1 1 calc(50% - 5px); white-space: nowrap; }
  .task-focus-options { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .task-workspace-bar { align-items: flex-start; flex-direction: column; }
  .task-view-switch { width: 100%; }
  .task-view-switch button { flex: 1; justify-content: center; }
  .task-filter-toolbar { grid-template-columns: 1fr 1fr; }
  .task-search { grid-column: 1 / -1; }
}

@media (max-width: 479.98px) {
  .task-summary,
  .task-insights { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .task-stat { gap: 7px; padding: 10px; }
  .task-stat-icon { width: 34px; height: 34px; flex-basis: 34px; }
  .task-stat-arrow { display: none; }
  .task-insights button { min-height: 55px; align-items: flex-start; flex-wrap: wrap; border-right: 1px solid #e9edf3; border-bottom: 1px solid #e9edf3; }
  .task-insights button:nth-child(2n) { border-right: 0; }
  .task-insights button:nth-child(n + 3) { border-bottom: 0; }
  .task-insights span { flex-basis: calc(100% - 24px); }
  .task-insights strong { margin-left: 23px; }
  .task-filter-toolbar { grid-template-columns: 1fr; }
  .task-filter-toolbar > * { width: 100%; }
  .task-filter-toolbar > .btn { justify-content: center; }
  .task-view-switch button span { display: none; }
}
</style>
