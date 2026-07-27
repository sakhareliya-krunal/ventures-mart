<script setup>
import { computed } from 'vue';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();

const visible = computed(() => Boolean(ui.toast?.message));
const typeClass = computed(() =>
  ui.toast?.type === 'error' ? 'app-toast--error' : 'app-toast--success',
);
</script>

<template>
  <Teleport to="body">
    <Transition name="app-toast">
      <div
        v-if="visible"
        class="app-toast"
        :class="typeClass"
        role="status"
        aria-live="polite"
      >
        <p class="app-toast__message">{{ ui.toast.message }}</p>
        <button
          type="button"
          class="app-toast__close"
          aria-label="Dismiss"
          @click="ui.dismissToast()"
        >
          ×
        </button>
      </div>
    </Transition>
  </Teleport>
</template>
