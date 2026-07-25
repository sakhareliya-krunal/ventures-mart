<script setup>
import { RouterLink } from 'vue-router';

defineProps({
  eyebrow: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
  action: {
    type: Object,
    default: null,
  },
});
</script>

<template>
  <div class="section-header">
    <div>
      <span v-if="eyebrow" class="eyebrow">{{ eyebrow }}</span>
      <h2>{{ title }}</h2>
      <p v-if="description">{{ description }}</p>
    </div>
    <RouterLink v-if="action" :to="action.href">{{ action.label }}</RouterLink>
  </div>
</template>
