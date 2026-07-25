import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const emptyTotals = {
  subtotal: 0,
  shipping: 0,
  tax: 0,
  total: 0,
};

function applyPayload(target, payload) {
  const lines = unwrapData(payload.items) || [];
  target.items.value = lines;
  target.itemCount.value = payload.item_count ?? lines.length;
  target.quantityCount.value =
    payload.quantity_count ?? lines.reduce((sum, item) => sum + (item.quantity || 0), 0);
  target.totals.value = payload.totals ?? { ...emptyTotals };
}

export const useCartStore = defineStore('cart', () => {
  const items = ref([]);
  const itemCount = ref(0);
  const quantityCount = ref(0);
  const totals = ref({ ...emptyTotals });
  const loading = ref(false);
  const error = ref(null);
  const trayOpen = ref(false);

  const isEmpty = computed(() => items.value.length === 0);
  const lineCount = computed(() => items.value.length);

  function openTray() {
    if (!items.value.length) {
      return;
    }
    trayOpen.value = true;
  }

  function closeTray() {
    trayOpen.value = false;
  }

  async function fetch() {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/cart');
      applyPayload({ items, itemCount, quantityCount, totals }, data);
    } catch (err) {
      error.value = err.response?.data?.message || 'Unable to load cart.';
    } finally {
      loading.value = false;
    }
  }

  async function addItem(productId, quantity = 1) {
    const { data } = await api.post('/cart', {
      product_id: productId,
      quantity,
    });
    applyPayload({ items, itemCount, quantityCount, totals }, data);
    trayOpen.value = true;
  }

  async function updateQuantity(productId, quantity) {
    const { data } = await api.patch(`/cart/items/${productId}`, { quantity });
    applyPayload({ items, itemCount, quantityCount, totals }, data);
    if (!items.value.length) {
      closeTray();
    }
  }

  async function removeItem(productId) {
    const { data } = await api.delete(`/cart/items/${productId}`);
    applyPayload({ items, itemCount, quantityCount, totals }, data);
    if (!items.value.length) {
      closeTray();
    }
  }

  async function clear() {
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
    isEmpty,
    lineCount,
    openTray,
    closeTray,
    fetch,
    addItem,
    updateQuantity,
    removeItem,
    clear,
  };
});
