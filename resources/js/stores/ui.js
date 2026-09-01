import { defineStore } from 'pinia';
import { ref } from 'vue';

const SHOW_DELAY_MS = 120;
const NAVIGATION_MAX_MS = 5000;
const TOAST_MS = 3500;
const ORDER_TOAST_MS = 9000;

export const useUiStore = defineStore('ui', () => {
  const navigating = ref(false);
  const toast = ref(null);
  let showTimer = null;
  let navigationSafetyTimer = null;
  let toastTimer = null;

  function clearShowTimer() {
    if (showTimer != null) {
      clearTimeout(showTimer);
      showTimer = null;
    }
  }

  function clearNavigationSafetyTimer() {
    if (navigationSafetyTimer != null) {
      clearTimeout(navigationSafetyTimer);
      navigationSafetyTimer = null;
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
    clearNavigationSafetyTimer();

    navigationSafetyTimer = setTimeout(() => {
      navigationSafetyTimer = null;
      navigating.value = false;
    }, NAVIGATION_MAX_MS);

    showTimer = setTimeout(() => {
      showTimer = null;
      navigating.value = true;
    }, SHOW_DELAY_MS);
  }

  function stopNavigating() {
    clearShowTimer();
    clearNavigationSafetyTimer();
    navigating.value = false;
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
      title: '',
      type: options.type === 'error' ? 'error' : 'success',
      variant: 'default',
    };

    toastTimer = setTimeout(() => {
      toast.value = null;
      toastTimer = null;
    }, options.durationMs ?? TOAST_MS);
  }

  /**
   * @param {{
   *   title?: string,
   *   message?: string,
   *   orderNumber?: string|number,
   *   orderId?: string|number,
   *   paymentMethod?: string,
   *   paymentStatus?: string,
   *   total?: string|number,
   *   actionHref?: string
   * }} details
   * @param {{ durationMs?: number }} [options]
   */
  function showOrderToast(details = {}, options = {}) {
    const title = String(details.title || 'Order placed').trim();
    const message = String(details.message || '').trim();

    clearToastTimer();
    toast.value = {
      id: Date.now(),
      type: 'success',
      variant: 'order',
      title,
      message,
      orderNumber: details.orderNumber || details.orderId || '',
      paymentMethod: details.paymentMethod || '',
      paymentStatus: details.paymentStatus || '',
      total: details.total ?? null,
      actionHref: details.actionHref || '',
    };

    toastTimer = setTimeout(() => {
      toast.value = null;
      toastTimer = null;
    }, options.durationMs ?? ORDER_TOAST_MS);
  }

  return {
    navigating,
    toast,
    startNavigating,
    stopNavigating,
    showToast,
    showOrderToast,
    dismissToast,
  };
});
