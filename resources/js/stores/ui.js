import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

const SHOW_DELAY_MS = 120;
const TOAST_MS = 3500;
const DEFAULT_NETWORK_LABEL = 'Connecting…';

export const useUiStore = defineStore('ui', () => {
  const navigating = ref(false);
  const splashVisible = ref(true);
  const toast = ref(null);
  const networkWaitCount = ref(0);
  const networkLabel = ref(DEFAULT_NETWORK_LABEL);
  let showTimer = null;
  let toastTimer = null;

  const networkWaiting = computed(() => networkWaitCount.value > 0);

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
   * @param {string} [label]
   */
  function beginNetworkWait(label = DEFAULT_NETWORK_LABEL) {
    networkLabel.value = String(label || DEFAULT_NETWORK_LABEL);
    networkWaitCount.value += 1;
  }

  function endNetworkWait() {
    networkWaitCount.value = Math.max(0, networkWaitCount.value - 1);
    if (networkWaitCount.value === 0) {
      networkLabel.value = DEFAULT_NETWORK_LABEL;
    }
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
    networkWaiting,
    networkLabel,
    startNavigating,
    stopNavigating,
    dismissSplash,
    showToast,
    dismissToast,
    beginNetworkWait,
    endNetworkWait,
  };
});
