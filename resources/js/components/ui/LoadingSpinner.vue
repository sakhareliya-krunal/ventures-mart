<script setup>
import { computed } from 'vue';

const LOADER_SRC = '/images/venturesmart-compact-loader.svg';

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
  overlay: {
    type: Boolean,
    default: false,
  },
});

const rootClass = computed(() => [
  'vm-loader',
  `vm-loader--${props.size}`,
  {
    'vm-loader--page': props.page,
    'vm-loader--overlay': props.overlay,
    'vm-loader--icon-only': !props.label,
  },
]);
</script>

<template>
  <div :class="rootClass" role="status" :aria-label="label || 'Loading'">
    <img class="vm-loader__mark" :src="LOADER_SRC" alt="" aria-hidden="true" />
  </div>
</template>
