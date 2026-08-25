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
  loading: {
    type: Boolean,
    default: false,
  },
});

const classes = computed(
  () => [
    'button',
    `button--${props.variant}`,
    `button--${props.size}`,
    {
      'button--loading': props.loading,
    },
  ],
);
</script>

<template>
  <RouterLink v-if="to" :class="classes" :to="to">
    <slot />
  </RouterLink>
  <button
    v-else
    :class="classes"
    :type="type"
    :disabled="disabled || loading"
    :aria-busy="loading ? 'true' : undefined"
  >
    <span class="button__content">
      <slot />
    </span>
    <span v-if="loading" class="button-dots" aria-hidden="true">
      <span class="button-dots__dot" />
      <span class="button-dots__dot" />
      <span class="button-dots__dot" />
      <span class="button-dots__dot" />
      <span class="button-dots__dot" />
    </span>
  </button>
</template>
