import { defineStore } from 'pinia';
import { ref } from 'vue';

const SHOW_DELAY_MS = 120;
const TOAST_MS = 3500;

export const useUiStore = defineStore('ui', () => {
  const navigating = ref(false);
  const splashVisible = ref(true);
  const toast = ref(null);
  let showTimer = null;
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
    splashVisible.value = false;
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
    dismissSplash,
    showToast,
    dismissToast,
  };
});
