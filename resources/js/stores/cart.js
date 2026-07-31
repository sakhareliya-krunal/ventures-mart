import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import { friendlyApiError } from '@/utils/apiError';

const emptyTotals = {
  subtotal: 0,
  shipping: 0,
  cgst: 0,
  sgst: 0,
  igst: 0,
  tax: 0,
  tax_type: 'estimate',
  total: 0,
};

const SYNC_DEBOUNCE_MS = 250;

function applyPayload(target, payload) {
  const lines = unwrapData(payload.items) || [];
  target.items.value = lines;
  target.itemCount.value = payload.item_count ?? lines.length;
  target.quantityCount.value =
    payload.quantity_count ?? lines.reduce((sum, item) => sum + (item.quantity || 0), 0);
  target.totals.value = payload.totals ?? { ...emptyTotals };
}

function calculateLocalTotals(lines) {
  let subtotal = 0;

  for (const line of lines) {
    const price = Number(line.product?.price || 0);
    const qty = Number(line.quantity || 0);
    subtotal += price * qty;
  }

  subtotal = Math.round(subtotal * 100) / 100;
  const shipping = 0;
  const tax = Math.round(subtotal * 0.05 * 100) / 100;
  const cgst = Math.round(subtotal * 0.025 * 100) / 100;
  const sgst = Math.round((tax - cgst) * 100) / 100;

  return {
    subtotal,
    shipping,
    cgst,
    sgst,
    igst: 0,
    tax,
    tax_type: 'estimate',
    total: Math.round((subtotal + shipping + tax) * 100) / 100,
  };
}

function maxQuantityFor(item) {
  const stock = Number(item?.product?.stock);
  const stockCap = Number.isFinite(stock) && stock > 0 ? stock : 99;
  return Math.min(99, stockCap);
}

export const useCartStore = defineStore('cart', () => {
  const items = ref([]);
  const itemCount = ref(0);
  const quantityCount = ref(0);
  const totals = ref({ ...emptyTotals });
  const loading = ref(false);
  const error = ref(null);
  const trayOpen = ref(false);
  const addingIds = ref(new Set());
  const syncingIds = ref(new Set());

  /** @type {Map<number, number>} */
  const pendingQuantities = new Map();
  /** @type {Map<number, ReturnType<typeof setTimeout>>} */
  const syncTimers = new Map();
  /** @type {Map<number, Promise<void>>} */
  const syncChains = new Map();

  const isEmpty = computed(() => items.value.length === 0);
  const lineCount = computed(() => items.value.length);

  function isAdding(productId) {
    return addingIds.value.has(Number(productId));
  }

  function isSyncing(productId) {
    return syncingIds.value.has(Number(productId));
  }

  function markSyncing(id, active) {
    const next = new Set(syncingIds.value);
    if (active) {
      next.add(id);
    } else {
      next.delete(id);
    }
    syncingIds.value = next;
  }

  function recomputeFromItems() {
    const lines = items.value;
    itemCount.value = lines.length;
    quantityCount.value = lines.reduce((sum, item) => sum + (item.quantity || 0), 0);
    totals.value = calculateLocalTotals(lines);
  }

  function openTray() {
    if (!items.value.length) {
      return;
    }
    trayOpen.value = true;
  }

  function closeTray() {
    trayOpen.value = false;
  }

  function clearSyncTimer(id) {
    const timer = syncTimers.get(id);
    if (timer) {
      clearTimeout(timer);
      syncTimers.delete(id);
    }
  }

  async function fetch(options = {}) {
    loading.value = true;
    error.value = null;

    try {
      const params = {};
      if (options.state) {
        params.state = options.state;
      }
      const { data } = await api.get('/cart', { params });
      applyPayload({ items, itemCount, quantityCount, totals }, data);
    } catch (err) {
      error.value = friendlyApiError(err, 'Unable to load cart.');
    } finally {
      loading.value = false;
    }
  }

  async function addItem(productId, quantity = 1) {
    const id = Number(productId);

    if (!id || addingIds.value.has(id)) {
      return;
    }

    addingIds.value = new Set([...addingIds.value, id]);
    error.value = null;

    try {
      const { data } = await api.post('/cart', {
        product_id: id,
        quantity,
      });
      applyPayload({ items, itemCount, quantityCount, totals }, data);
      trayOpen.value = true;
    } catch (err) {
      error.value = friendlyApiError(err, 'Unable to add to cart.');
      import('@/stores/ui').then(({ useUiStore }) => {
        useUiStore().showToast(error.value, { type: 'error' });
      }).catch(() => {});
      throw err;
    } finally {
      const next = new Set(addingIds.value);
      next.delete(id);
      addingIds.value = next;
    }
  }

  async function flushQuantity(productId) {
    const id = Number(productId);
    const quantity = pendingQuantities.get(id);

    if (quantity === undefined) {
      return;
    }

    pendingQuantities.delete(id);
    clearSyncTimer(id);
    markSyncing(id, true);
    error.value = null;

    try {
      if (quantity <= 0) {
        const { data } = await api.delete(`/cart/items/${id}`);
        applyPayload({ items, itemCount, quantityCount, totals }, data);
      } else {
        const { data } = await api.patch(`/cart/items/${id}`, { quantity });
        applyPayload({ items, itemCount, quantityCount, totals }, data);
      }

      if (!items.value.length) {
        closeTray();
      }
    } catch (err) {
      error.value = friendlyApiError(
        err,
        Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Unable to update cart.',
      );
      import('@/stores/ui').then(({ useUiStore }) => {
        useUiStore().showToast(error.value, { type: 'error' });
      }).catch(() => {});
      await fetch();
    } finally {
      markSyncing(id, false);

      // If more changes arrived while this request was in flight, schedule another sync.
      if (pendingQuantities.has(id)) {
        scheduleQuantitySync(id);
      }
    }
  }

  function enqueueFlush(productId) {
    const id = Number(productId);
    const previous = syncChains.get(id) || Promise.resolve();
    const next = previous
      .catch(() => {})
      .then(() => flushQuantity(id))
      .finally(() => {
        if (syncChains.get(id) === next) {
          syncChains.delete(id);
        }
      });
    syncChains.set(id, next);
    return next;
  }

  function scheduleQuantitySync(productId) {
    const id = Number(productId);
    clearSyncTimer(id);
    syncTimers.set(
      id,
      setTimeout(() => {
        syncTimers.delete(id);
        enqueueFlush(id);
      }, SYNC_DEBOUNCE_MS),
    );
  }

  function setPendingQuantity(productId, quantity) {
    const id = Number(productId);
    pendingQuantities.set(id, quantity);
    scheduleQuantitySync(id);
  }

  function bumpQuantity(productId, delta) {
    const id = Number(productId);
    const index = items.value.findIndex((item) => Number(item.product_id) === id);

    if (index === -1) {
      return;
    }

    const current = items.value[index];
    const nextQty = current.quantity + delta;

    if (nextQty <= 0) {
      removeItem(id);
      return;
    }

    const capped = Math.min(nextQty, maxQuantityFor(current));
    if (capped === current.quantity) {
      return;
    }

    const nextItems = items.value.slice();
    nextItems[index] = { ...current, quantity: capped };
    items.value = nextItems;
    recomputeFromItems();
    setPendingQuantity(id, capped);
  }

  function updateQuantity(productId, quantity) {
    const id = Number(productId);
    const index = items.value.findIndex((item) => Number(item.product_id) === id);

    if (index === -1) {
      return;
    }

    if (quantity <= 0) {
      removeItem(id);
      return;
    }

    const current = items.value[index];
    const capped = Math.min(Math.max(1, quantity), maxQuantityFor(current));

    if (capped === current.quantity) {
      return;
    }

    const nextItems = items.value.slice();
    nextItems[index] = { ...current, quantity: capped };
    items.value = nextItems;
    recomputeFromItems();
    setPendingQuantity(id, capped);
  }

  function removeItem(productId) {
    const id = Number(productId);
    clearSyncTimer(id);
    pendingQuantities.set(id, 0);

    items.value = items.value.filter((item) => Number(item.product_id) !== id);
    recomputeFromItems();

    if (!items.value.length) {
      closeTray();
    }

    // Remove should sync promptly (still coalesced via chain if a patch is in flight).
    clearSyncTimer(id);
    enqueueFlush(id);
  }

  async function clear() {
    for (const timer of syncTimers.values()) {
      clearTimeout(timer);
    }
    syncTimers.clear();
    pendingQuantities.clear();

    const { data } = await api.delete('/cart');
    applyPayload({ items, itemCount, quantityCount, totals }, data);
    closeTray();
  }

  return {
    items,
    itemCount,
    quantityCount,
    totals,
    loading,
    error,
    trayOpen,
    addingIds,
    syncingIds,
    isEmpty,
    lineCount,
    isAdding,
    isSyncing,
    openTray,
    closeTray,
    fetch,
    addItem,
    bumpQuantity,
    updateQuantity,
    removeItem,
    clear,
  };
});
