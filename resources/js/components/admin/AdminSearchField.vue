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
      class="admin-search__input"
      :value="modelValue"
      type="search"
      :aria-label="ariaLabel"
      :placeholder="placeholder"
      @input="emit('update:modelValue', $event.target.value)"
    />
  </div>
</template>

<style scoped>
.admin-search input {
  flex: 1;
  min-width: 0;
  min-height: 0;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  outline: none;
  -webkit-appearance: none;
  appearance: none;
}

.admin-search input:focus,
.admin-search input:focus-visible {
  border: 0;
  box-shadow: none;
  outline: none;
}

.admin-search input[type='search']::-webkit-search-decoration,
.admin-search input[type='search']::-webkit-search-cancel-button,
.admin-search input[type='search']::-webkit-search-results-button,
.admin-search input[type='search']::-webkit-search-results-decoration {
  display: none;
}
</style>
