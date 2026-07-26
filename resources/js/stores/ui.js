import { defineStore } from 'pinia';
import { ref } from 'vue';

const SHOW_DELAY_MS = 120;

export const useUiStore = defineStore('ui', () => {
  const navigating = ref(false);
  const splashVisible = ref(true);
  let showTimer = null;

  function clearShowTimer() {
    if (showTimer != null) {
      clearTimeout(showTimer);
      showTimer = null;
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

  return {
    navigating,
    splashVisible,
    startNavigating,
    stopNavigating,
    dismissSplash,
  };
});
