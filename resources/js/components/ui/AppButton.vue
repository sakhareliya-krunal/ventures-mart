<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
  },
  size: {
    type: String,
    default: 'md',
  },
  to: {
    type: String,
    default: null,
  },
  type: {
    type: String,
    default: 'button',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const classes = computed(
  () => `button button--${props.variant} button--${props.size}`,
);
</script>

<template>
  <RouterLink v-if="to" :class="classes" :to="to">
    <slot />
  </RouterLink>
  <button v-else :class="classes" :type="type" :disabled="disabled">
    <slot />
  </button>
</template>
