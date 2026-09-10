<script setup>
import { computed } from 'vue';

const props = defineProps({
  busy: Boolean,
  dirty: Boolean,
  layout: { type: String, default: 'inline' },
});

const rootClass = computed(() => ({
  'admin-form-actions': true,
  'admin-form-actions--inline': props.layout === 'inline',
  'admin-form-actions--footer': props.layout === 'footer',
}));
</script>
<template>
  <div :class="rootClass" :aria-busy="busy">
    <span v-if="dirty" class="admin-muted">Unsaved changes</span>
    <div><slot /></div>
  </div>
</template>
