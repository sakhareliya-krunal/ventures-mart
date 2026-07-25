<script setup>
import AppButton from '@/components/ui/AppButton.vue';

defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
  actionLabel: {
    type: String,
    default: '',
  },
  actionTo: {
    type: String,
    default: '/shop',
  },
});
</script>

<template>
  <section class="page-section empty-state">
    <h1>{{ title }}</h1>
    <p v-if="description">{{ description }}</p>
    <AppButton v-if="actionLabel" :to="actionTo">{{ actionLabel }}</AppButton>
  </section>
</template>
