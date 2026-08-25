<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { brandAssets } from '@/constants/assets';
import { useThemeStore } from '@/stores/theme';
import { useUiStore } from '@/stores/ui';

const FULL_SEQUENCE_MS = 5000;
const REDUCED_SEQUENCE_MS = 900;
const READY_MAX_MS = 6500;
const EXIT_MS = 520;

const router = useRouter();
const theme = useThemeStore();
const ui = useUiStore();

const exiting = ref(false);
const handoff = ref(false);
let exitTimer = null;
let mounted = false;
let previousBodyOverflow = '';
let previousHtmlOverflow = '';

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

function lockScroll() {
  previousBodyOverflow = document.body.style.overflow;
  previousHtmlOverflow = document.documentElement.style.overflow;
  document.body.style.overflow = 'hidden';
  document.documentElement.style.overflow = 'hidden';
}

function unlockScroll() {
  document.body.style.overflow = previousBodyOverflow;
  document.documentElement.style.overflow = previousHtmlOverflow;
}

onMounted(async () => {
  mounted = true;
  ui.showSplash();
  lockScroll();

  const premount = document.getElementById('brand-splash');
  handoff.value = Boolean(premount);
  requestAnimationFrame(() => premount?.remove());

  try {
    const sequenceMs = prefersReducedMotion() ? REDUCED_SEQUENCE_MS : FULL_SEQUENCE_MS;
    const ready = Promise.race([
      router.isReady().catch(() => undefined),
      wait(READY_MAX_MS),
    ]);

    await Promise.race([
      Promise.all([ready, wait(sequenceMs)]),
      wait(READY_MAX_MS),
    ]);
  } catch {
    // Continue dismiss even if router readiness fails.
  }

  if (!mounted) {
    return;
  }

  exiting.value = true;
  await nextTick();

  exitTimer = setTimeout(() => {
    ui.dismissSplash();
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
      v-if="ui.splashVisible"
      class="brand-splash"
      :class="{ 'is-exiting': exiting, 'is-handoff': handoff }"
      role="status"
      aria-live="polite"
      :aria-label="'Loading ' + theme.brandName"
    >
      <div class="brand-splash__stage">
        <div class="brand-splash__scene" aria-hidden="true">
          <span class="brand-splash__ambient brand-splash__ambient--blue" />
          <span class="brand-splash__ambient brand-splash__ambient--red" />
          <span class="brand-splash__motion-streak" />

          <svg class="brand-splash__orbit" viewBox="0 0 160 160" focusable="false">
            <circle class="brand-splash__orbit-path" cx="80" cy="80" r="56" pathLength="360" />
            <circle class="brand-splash__arc brand-splash__arc--blue brand-splash__arc--one" cx="80" cy="80" r="56" pathLength="360" />
            <circle class="brand-splash__arc brand-splash__arc--red" cx="80" cy="80" r="56" pathLength="360" />
            <circle class="brand-splash__arc brand-splash__arc--blue brand-splash__arc--two" cx="80" cy="80" r="56" pathLength="360" />
          </svg>

          <span class="brand-splash__lead brand-splash__lead--one" />
          <span class="brand-splash__lead brand-splash__lead--two" />
          <span class="brand-splash__lead brand-splash__lead--three" />

          <span class="brand-splash__icon brand-splash__icon--top brand-splash__icon--blue">
            <svg viewBox="0 0 32 32" focusable="false">
              <path d="M10 11V8.8C10 6.7 11.7 5 13.8 5h4.4C20.3 5 22 6.7 22 8.8V11" />
              <path d="M7 12.5h18c1.1 0 2 .9 2 2v9.5c0 1.1-.9 2-2 2H7c-1.1 0-2-.9-2-2v-9.5c0-1.1.9-2 2-2Z" />
              <path d="M5 18h22M13 10.8h6M12 18v3h8v-3" />
            </svg>
          </span>

          <span class="brand-splash__icon brand-splash__icon--right brand-splash__icon--blue">
            <svg viewBox="0 0 32 32" focusable="false">
              <path d="M9 8.5c0-1.6 3.1-3 7-3s7 1.4 7 3-3.1 3-7 3-7-1.4-7-3Z" />
              <path d="M9 8.5v5c0 1.6 3.1 3 7 3s7-1.4 7-3v-5" />
              <path d="M9 13.5v5c0 1.6 3.1 3 7 3s7-1.4 7-3v-5" />
              <path d="M9 18.5v5c0 1.6 3.1 3 7 3s7-1.4 7-3v-5" />
            </svg>
          </span>

          <span class="brand-splash__icon brand-splash__icon--left brand-splash__icon--red">
            <svg viewBox="0 0 32 32" focusable="false">
              <path d="M9 14.5h13.2l-1.2 7.2H10.5L9 14.5Z" />
              <path d="M8.8 14.5 7.8 10H5.5M13 14.5l3.2-4.7M20 14.5l-2.8-4.7" />
              <path d="M12 25.2h.1M20.5 25.2h.1" />
              <path d="M9.5 18.2h12.2" />
            </svg>
          </span>

          <div class="brand-splash__logo">
            <span class="brand-splash__logo-ring" />
            <img :src="brandAssets.loaderMark" alt="" width="92" height="92" />
          </div>

          <div class="brand-splash__dots">
            <span class="brand-splash__dot brand-splash__dot--blue" />
            <span class="brand-splash__dot brand-splash__dot--muted" />
            <span class="brand-splash__dot brand-splash__dot--red" />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
