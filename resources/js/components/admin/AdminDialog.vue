<script setup>
import { ref, useId } from 'vue';
import { X } from '@lucide/vue';
import { useScrollLock } from '@/composables/useScrollLock';
import { useOverlayFocus } from '@/composables/useOverlayFocus';
const props = defineProps({ open: Boolean, busy: Boolean, title: String, drawer: Boolean });
const emit = defineEmits(['update:open', 'close']);
const panel = ref(null);
const id = useId();
function close() { if (!props.busy) { emit('update:open', false); emit('close'); } }
useScrollLock(`admin-dialog-${id}`, () => props.open);
useOverlayFocus(() => props.open, panel, close);
</script>
<template>
  <Teleport to="body">
    <div v-if="open" class="admin-overlay" :class="{ 'admin-overlay--drawer': drawer }">
      <div class="admin-overlay__backdrop" @click="close" />
      <section ref="panel" class="admin-overlay__panel" role="dialog" aria-modal="true" :aria-labelledby="id" :aria-busy="busy" tabindex="-1">
        <header><h2 :id="id">{{ title }}</h2><button class="admin-icon-button" aria-label="Close dialog" :disabled="busy" @click="close"><X :size="20" /></button></header>
        <div class="admin-overlay__body"><slot /></div>
        <footer v-if="$slots.footer"><slot name="footer" /></footer>
      </section>
    </div>
  </Teleport>
</template>
