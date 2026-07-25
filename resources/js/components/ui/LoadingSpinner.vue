<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: {
    type: String,
    default: 'Loading',
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  page: {
    type: Boolean,
    default: false,
  },
});

const rootClass = computed(() => [
  'vm-loader',
  `vm-loader--${props.size}`,
  { 'vm-loader--page': props.page },
]);
</script>

<template>
  <div :class="rootClass" role="status" :aria-label="label">
    <div class="vm-loader__mark" aria-hidden="true">
      <span class="vm-loader__ring vm-loader__ring--outer" />
      <span class="vm-loader__ring vm-loader__ring--inner" />
      <span class="vm-loader__core" />
    </div>
    <span class="vm-loader__label">{{ label }}</span>
  </div>
</template>
