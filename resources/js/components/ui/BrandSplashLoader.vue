<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const LOADER_SRC = '/images/venturesmart-compact-loader.svg';
const MIN_VISIBLE_MS = 1400;
const READY_MAX_MS = 5200;
const EXIT_MS = 360;

const router = useRouter();
const visible = ref(true);
const exiting = ref(false);
let mounted = false;
let exitTimer = null;
let previousBodyOverflow = '';
let previousHtmlOverflow = '';

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function lockScroll() {
  if (typeof document === 'undefined') {
    return;
  }

  previousBodyOverflow = document.body.style.overflow;
  previousHtmlOverflow = document.documentElement.style.overflow;
  document.body.style.overflow = 'hidden';
  document.documentElement.style.overflow = 'hidden';
}

function unlockScroll() {
  if (typeof document === 'undefined') {
    return;
  }

  document.body.style.overflow = previousBodyOverflow;
  document.documentElement.style.overflow = previousHtmlOverflow;
}

onMounted(async () => {
  mounted = true;
  lockScroll();

  const premount = document.getElementById('brand-splash');
  requestAnimationFrame(() => premount?.remove());

  try {
    await Promise.race([
      Promise.all([
        router.isReady().catch(() => undefined),
        wait(MIN_VISIBLE_MS),
      ]),
      wait(READY_MAX_MS),
    ]);
  } catch {
    // Keep the app usable even if readiness checks fail.
  }

  if (!mounted) {
    return;
  }

  exiting.value = true;
  await nextTick();

  exitTimer = setTimeout(() => {
    visible.value = false;
    unlockScroll();
  }, EXIT_MS);
});

onUnmounted(() => {
  mounted = false;
  if (exitTimer != null) {
    clearTimeout(exitTimer);
  }
  unlockScroll();
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="brand-splash"
      :class="{ 'is-exiting': exiting }"
      role="status"
      aria-live="polite"
      aria-label="Loading Ventures Mart"
    >
      <img class="brand-splash__loader" :src="LOADER_SRC" alt="" width="160" height="160" />
    </div>
  </Teleport>
</template>
