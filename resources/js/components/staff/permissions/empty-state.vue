<script>
import "./permission-ui.css";

export default {
  props: {
    icon: {
      type: String,
      default: "bx-folder-open",
    },
    title: {
      type: String,
      default: "Sin registros",
    },
    text: {
      type: String,
      default: "",
    },
  },
};
</script>

<template>
  <div class="permission-empty-state">
    <div>
      <div class="permission-empty-state__icon">
        <i :class="`bx ${icon}`"></i>
      </div>
      <div class="permission-empty-state__title">{{ title }}</div>
      <div v-if="text">{{ text }}</div>
    </div>
  </div>
</template>
