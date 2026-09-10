<script setup>
import { RouterLink } from 'vue-router';

defineProps({
  label: String,
  value: [String, Number],
  hint: String,
  icon: [Object, Function],
  to: [String, Object],
  tone: { type: String, default: 'brand' },
});
</script>

<template>
  <component
    :is="to ? RouterLink : 'article'"
    :to="to"
    class="admin-kpi"
    :class="{ 'inventory-kpi--warn': tone === 'warn' }"
  >
    <span v-if="icon" class="admin-kpi__icon">
      <component :is="icon" :size="19" aria-hidden="true" />
    </span>
    <span class="admin-kpi__body">
      <span>{{ label }}</span>
      <strong>{{ value }}</strong>
      <small v-if="hint">{{ hint }}</small>
    </span>
  </component>
</template>
