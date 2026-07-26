import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { requireLogin } from '@/utils/authRedirect';
import { unwrapData } from '@/utils/format';

function applyPayload(state, payload) {
  state.count.value = payload.count ?? 0;
  state.productIds.value = payload.product_ids ?? [];

  if (Object.prototype.hasOwnProperty.call(payload, 'products')) {
    state.products.value = unwrapData(payload.products) || [];
  }
}

export const useWishlistStore = defineStore('wishlist', () => {
  const productIds = ref([]);
  const products = ref([]);
  const count = ref(0);
  const loading = ref(false);
  const error = ref(null);
  const pendingIds = ref(new Set());

  function isWishlisted(productId) {
    return productIds.value.includes(Number(productId));
  }

  function isPending(productId) {
    return pendingIds.value.has(Number(productId));
  }

  async function fetch() {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/wishlist');
      applyPayload({ productIds, products, count }, data);
    } catch (err) {
      error.value = err.response?.data?.message || 'Unable to load wishlist.';
    } finally {
      loading.value = false;
    }
  }

  async function toggle(productId) {
    const auth = useAuthStore();

    if (!auth.user) {
      const router = useRouter();
      await requireLogin(router, router.currentRoute.value.fullPath);
      return false;
    }

    const id = Number(productId);

    if (!id || pendingIds.value.has(id)) {
      return isWishlisted(id);
    }

    const snapshot = {
      productIds: [...productIds.value],
      count: count.value,
      products: [...products.value],
    };

    const wasWished = snapshot.productIds.includes(id);

    if (wasWished) {
      productIds.value = snapshot.productIds.filter((item) => item !== id);
      count.value = Math.max(0, snapshot.count - 1);
      products.value = snapshot.products.filter((item) => Number(item.id) !== id);
    } else {
      productIds.value = [...snapshot.productIds, id];
      count.value = snapshot.count + 1;
    }

    pendingIds.value = new Set([...pendingIds.value, id]);

    try {
      const { data } = await api.post('/wishlist/toggle', { product_id: id });
      productIds.value = data.product_ids ?? productIds.value;
      count.value = data.count ?? productIds.value.length;

      if (Object.prototype.hasOwnProperty.call(data, 'products')) {
        products.value = unwrapData(data.products) || [];
      }

      return Boolean(data.wished);
    } catch (err) {
      productIds.value = snapshot.productIds;
      count.value = snapshot.count;
      products.value = snapshot.products;
      error.value = err.response?.data?.message || 'Unable to update wishlist.';
      return wasWished;
    } finally {
      const next = new Set(pendingIds.value);
      next.delete(id);
      pendingIds.value = next;
    }
  }

  const isEmpty = computed(() => count.value === 0);

  return {
    productIds,
    products,
    count,
    loading,
    error,
    pendingIds,
    isEmpty,
    fetch,
    toggle,
    isWishlisted,
    isPending,
  };
});
