<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const LOADER_SRC = '/images/venturesmart-compact-loader.svg';
const EXIT_MS = 220;
const VISIBLE_MAX_MS = 1800;

const props = defineProps({
  active: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    default: 'Loading page',
  },
});

const visible = ref(false);
const completing = ref(false);
let exitTimer = null;
let safetyTimer = null;
let previousBodyOverflow = '';
let previousHtmlOverflow = '';
let scrollLocked = false;

const statusLabel = computed(() => props.label || 'Loading page');

function clearExitTimer() {
  if (exitTimer != null) {
    clearTimeout(exitTimer);
    exitTimer = null;
  }
}

function clearSafetyTimer() {
  if (safetyTimer != null) {
    clearTimeout(safetyTimer);
    safetyTimer = null;
  }
}

function lockScroll() {
  if (scrollLocked || typeof document === 'undefined') {
    return;
  }

  previousBodyOverflow = document.body.style.overflow;
  previousHtmlOverflow = document.documentElement.style.overflow;
  document.body.style.overflow = 'hidden';
  document.documentElement.style.overflow = 'hidden';
  scrollLocked = true;
}

function unlockScroll() {
  if (!scrollLocked || typeof document === 'undefined') {
    return;
  }

  document.body.style.overflow = previousBodyOverflow;
  document.documentElement.style.overflow = previousHtmlOverflow;
  scrollLocked = false;
}

function complete() {
  clearExitTimer();
  clearSafetyTimer();

  if (!visible.value) {
    completing.value = false;
    return;
  }

  completing.value = true;
  exitTimer = setTimeout(() => {
    visible.value = false;
    completing.value = false;
    unlockScroll();
    exitTimer = null;
  }, EXIT_MS);
}

watch(
  () => props.active,
  (active) => {
    clearExitTimer();
    clearSafetyTimer();

    if (active) {
      completing.value = false;
      visible.value = true;
      lockScroll();
      safetyTimer = setTimeout(() => {
        safetyTimer = null;
        complete();
      }, VISIBLE_MAX_MS);
      return;
    }

    complete();
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  clearExitTimer();
  clearSafetyTimer();
  unlockScroll();
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="route-loader"
      :class="{ 'is-completing': completing }"
      role="status"
      aria-live="polite"
      :aria-label="statusLabel"
    >
      <img class="route-loader__frame" :src="LOADER_SRC" alt="" width="160" height="160" />
    </div>
  </Teleport>
</template>
