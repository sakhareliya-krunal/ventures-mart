import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '@/services/api';
import router from '@/router';
import PendingUserAction from '@/services/pendingUserAction';
import { useAuthStore } from '@/stores/auth';
import { requireLogin } from '@/utils/authRedirect';
import { unwrapData } from '@/utils/format';
import { friendlyApiError } from '@/utils/apiError';

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
  let fetchPromise = null;

  function isWishlisted(productId) {
    return productIds.value.includes(Number(productId));
  }

  function isPending(productId) {
    return pendingIds.value.has(Number(productId));
  }

  async function fetch(options = {}) {
    if (fetchPromise && !options.force) {
      return fetchPromise;
    }

    fetchPromise = (async () => {
      loading.value = true;
      error.value = null;

      try {
        const { data } = await api.get('/wishlist');
        applyPayload({ productIds, products, count }, data);
      } catch (err) {
        error.value = friendlyApiError(err, 'Unable to load wishlist.');
      } finally {
        loading.value = false;
        fetchPromise = null;
      }
    })();

    return fetchPromise;
  }

  /**
   * @param {number|string} productId
   * @param {{ variantId?: number|string|null }} [options]
   */
  async function toggle(productId, options = {}) {
    const auth = useAuthStore();
    const id = Number(productId);
    const variantId =
      options.variantId == null || options.variantId === ''
        ? null
        : Number(options.variantId);

    if (!auth.user && auth.booting) {
      await auth.fetchUser();
    }

    if (!auth.user) {
      if (id) {
        const returnUrl = router.currentRoute.value.fullPath || '/';
        PendingUserAction.stash({
          type: 'wishlist.add',
          productId: id,
          variantId: Number.isFinite(variantId) && variantId > 0 ? variantId : null,
          returnUrl,
        });
        await requireLogin(router, returnUrl);
      }
      return false;
    }

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
      error.value = friendlyApiError(err, 'Unable to update wishlist.');
      import('@/stores/ui').then(({ useUiStore }) => {
        useUiStore().showToast(error.value, { type: 'error' });
      }).catch(() => {});
      return wasWished;
    } finally {
      const next = new Set(pendingIds.value);
      next.delete(id);
      pendingIds.value = next;
    }
  }

  /**
   * After login/register: process a pending wishlist.add action (add-only).
   * @param {import('@/services/pendingUserAction').PendingUserAction} action
   * @returns {Promise<{ ok: boolean, added: boolean, returnUrl: string|null }>}
   */
  async function completePendingAdd(action) {
    const auth = useAuthStore();

    if (!auth.user || !action || action.type !== 'wishlist.add') {
      return { ok: false, added: false, returnUrl: null };
    }

    const productId = Number(action.productId);
    if (!productId) {
      return { ok: false, added: false, returnUrl: null };
    }

    const payload = { product_id: productId };
    if (action.variantId) {
      payload.variant_id = Number(action.variantId);
    }

    try {
      const { data } = await api.post('/wishlist/add', payload);
      productIds.value = data.product_ids ?? productIds.value;
      count.value = data.count ?? productIds.value.length;

      return {
        ok: Boolean(data.wished),
        added: Boolean(data.added),
        returnUrl: action.returnUrl || null,
      };
    } catch (err) {
      error.value = friendlyApiError(err, 'Unable to add to wishlist.');
      return { ok: false, added: false, returnUrl: action.returnUrl || null };
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
    completePendingAdd,
    isWishlisted,
    isPending,
  };
});
