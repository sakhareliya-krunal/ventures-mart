<script setup>
import { nextTick, onBeforeMount, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { brandAssets } from '@/constants/assets';
import { useThemeStore } from '@/stores/theme';
import { useUiStore } from '@/stores/ui';

const MIN_MS = import.meta.env.DEV ? 300 : 1400;
const READY_MAX_MS = 2000;
const EXIT_MS = 400;

const router = useRouter();
const theme = useThemeStore();
const ui = useUiStore();

const exiting = ref(false);
const handoff = ref(false);
let exitTimer = null;

onBeforeMount(() => {
  const premount = document.getElementById('brand-splash');
  handoff.value = Boolean(premount);
  premount?.remove();
});

onMounted(async () => {
  const started = performance.now();

  try {
    await Promise.race([
      router.isReady(),
      new Promise((resolve) => setTimeout(resolve, READY_MAX_MS)),
    ]);
  } catch {
    // Continue dismiss even if router readiness fails.
  }

  const elapsed = performance.now() - started;
  const wait = Math.max(0, MIN_MS - elapsed);
  if (wait > 0) {
    await new Promise((resolve) => setTimeout(resolve, wait));
  }

  exiting.value = true;
  await nextTick();

  exitTimer = setTimeout(() => {
    ui.dismissSplash();
  }, EXIT_MS);
});

onUnmounted(() => {
  if (exitTimer != null) {
    clearTimeout(exitTimer);
  }
});
</script>

<template>
  <Teleport to="body">
    <div
      class="brand-splash"
      :class="{ 'is-exiting': exiting, 'is-handoff': handoff }"
      role="status"
      aria-live="polite"
      :aria-label="`Loading ${theme.brandName}`"
    >
      <span class="brand-splash__orb brand-splash__orb--1" aria-hidden="true" />
      <span class="brand-splash__orb brand-splash__orb--2" aria-hidden="true" />
      <span class="brand-splash__orb brand-splash__orb--3" aria-hidden="true" />
      <div class="brand-splash__inner">
        <div class="brand-splash__mark">
          <span class="brand-splash__ring" aria-hidden="true" />
          <span class="brand-splash__ring brand-splash__ring--inner" aria-hidden="true" />
          <img
            class="brand-splash__logo"
            :src="brandAssets.logo"
            :alt="theme.brandName"
            width="120"
            height="120"
          />
        </div>
        <p class="brand-splash__name">{{ theme.brandName }}</p>
        <p class="brand-splash__tagline">Toys &amp; lunch boxes</p>
        <div class="brand-splash__bar" aria-hidden="true">
          <span class="brand-splash__bar-fill" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
