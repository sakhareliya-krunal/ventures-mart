<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  title: { type: String, default: '' },
  variant: { type: String, default: '' },
});

const root = ref(null);

const panelClass = computed(() => ({
  'admin-panel': true,
  'admin-panel--form': props.variant === 'form',
  'admin-panel--index': props.variant === 'index',
}));

function scrollIntoView(options) {
  root.value?.scrollIntoView?.(options);
}

defineExpose({ scrollIntoView, $el: root });
</script>

<template>
  <section ref="root" :class="panelClass">
    <header v-if="title || $slots.actions" class="admin-panel__heading">
      <h2 v-if="title">{{ title }}</h2>
      <slot name="actions" />
    </header>
    <slot />
    <footer v-if="$slots.footer" class="admin-panel__footer"><slot name="footer" /></footer>
  </section>
</template>
