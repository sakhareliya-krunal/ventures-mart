<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { lockScroll, unlockScroll } from '@/utils/scrollLock';

const LOADER_SRC = '/images/venturesmart-loader-fixed-3-new-icons.svg';
const MIN_VISIBLE_MS = 1400;
const READY_MAX_MS = 5200;
const EXIT_MS = 360;
const SCROLL_LOCK_ID = 'brand-splash';

const router = useRouter();
const visible = ref(true);
const exiting = ref(false);
let mounted = false;
let exitTimer = null;
function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

onMounted(async () => {
  mounted = true;
  lockScroll(SCROLL_LOCK_ID);

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
    unlockScroll(SCROLL_LOCK_ID);
  }, EXIT_MS);
});

onUnmounted(() => {
  mounted = false;
  if (exitTimer != null) {
    clearTimeout(exitTimer);
  }
  unlockScroll(SCROLL_LOCK_ID);
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
      <img class="brand-splash__loader" :src="LOADER_SRC" alt="" width="320" height="320" />
    </div>
  </Teleport>
</template>
