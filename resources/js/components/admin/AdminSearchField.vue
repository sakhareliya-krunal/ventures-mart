<script setup>
import { Search } from '@lucide/vue';

defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Search…',
  },
  ariaLabel: {
    type: String,
    default: 'Search',
  },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
  <div class="admin-search">
    <Search :size="18" aria-hidden="true" />
    <input
      :value="modelValue"
      type="search"
      :aria-label="ariaLabel"
      :placeholder="placeholder"
      @input="emit('update:modelValue', $event.target.value)"
    />
  </div>
</template>
