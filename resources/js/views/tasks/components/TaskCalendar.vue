<script setup>
import { computed } from "vue";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";

const props = defineProps({ options: { type: Object, default: () => ({}) } });
const calendarOptions = computed(() => ({
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
  buttonText: { today: "Hoy", month: "Mes", week: "Semana", day: "Día" },
  ...props.options,
}));
</script>

<template>
  <FullCalendar :options="calendarOptions" />
</template>
