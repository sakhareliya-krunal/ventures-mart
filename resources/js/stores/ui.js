import { defineStore } from 'pinia';
import { ref } from 'vue';

const SHOW_DELAY_MS = 120;
const SPLASH_DELAY_MS = 250;
const TOAST_MS = 3500;

export const useUiStore = defineStore('ui', () => {
  const navigating = ref(false);
  const splashVisible = ref(false);
  const toast = ref(null);
  let showTimer = null;
  let splashTimer = null;
  let toastTimer = null;

  function clearShowTimer() {
    if (showTimer != null) {
      clearTimeout(showTimer);
      showTimer = null;
    }
  }

  function clearToastTimer() {
    if (toastTimer != null) {
      clearTimeout(toastTimer);
      toastTimer = null;
    }
  }

  function clearSplashTimer() {
    if (splashTimer != null) {
      clearTimeout(splashTimer);
      splashTimer = null;
    }
  }

  function startNavigating() {
    clearShowTimer();
    showTimer = setTimeout(() => {
      showTimer = null;
      navigating.value = true;
    }, SHOW_DELAY_MS);
  }

  function stopNavigating() {
    clearShowTimer();
    navigating.value = false;
  }

  function dismissSplash() {
    clearSplashTimer();
    splashVisible.value = false;
  }

  function scheduleSplash(delayMs = SPLASH_DELAY_MS) {
    clearSplashTimer();
    splashTimer = setTimeout(() => {
      splashTimer = null;
      splashVisible.value = true;
    }, delayMs);
  }

  function dismissToast() {
    clearToastTimer();
    toast.value = null;
  }

  /**
   * @param {string} message
   * @param {{ type?: 'success'|'error', durationMs?: number }} [options]
   */
  function showToast(message, options = {}) {
    const text = String(message || '').trim();
    if (!text) return;

    clearToastTimer();
    toast.value = {
      id: Date.now(),
      message: text,
      type: options.type === 'error' ? 'error' : 'success',
    };

    toastTimer = setTimeout(() => {
      toast.value = null;
      toastTimer = null;
    }, options.durationMs ?? TOAST_MS);
  }

  return {
    navigating,
    splashVisible,
    toast,
    startNavigating,
    stopNavigating,
    scheduleSplash,
    dismissSplash,
    showToast,
    dismissToast,
  };
});
