import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '@/services/api';
import PendingUserAction from '@/services/pendingUserAction';
import { unwrapData } from '@/utils/format';
import { friendlyApiError } from '@/utils/apiError';
import { useCartStore } from '@/stores/cart';
import { useUiStore } from '@/stores/ui';
import { useWishlistStore } from '@/stores/wishlist';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const booting = ref(false);
  const loading = ref(false);
  const loggingOut = ref(false);
  const redirecting = ref(false);
  const error = ref(null);
  const pendingReturnUrl = ref(null);

  const isAuthenticated = computed(() => Boolean(user.value));
  const isAdmin = computed(() => Boolean(user.value?.is_admin));

  let sessionPromise = null;

  function beginRedirect() {
    redirecting.value = true;
  }

  function endRedirect() {
    redirecting.value = false;
  }

  function takePendingReturnUrl() {
    const url = pendingReturnUrl.value;
    pendingReturnUrl.value = null;
    return url;
  }

  async function processPendingUserAction() {
    pendingReturnUrl.value = null;
    const action = PendingUserAction.consume();

    if (!action) {
      return;
    }

    pendingReturnUrl.value = action.returnUrl || null;

    if (action.type === 'wishlist.add') {
      const result = await useWishlistStore().completePendingAdd(action);
      if (result.ok) {
        useUiStore().showToast('Product added to your wishlist.', { type: 'success' });
      } else if (useWishlistStore().error) {
        useUiStore().showToast(useWishlistStore().error, { type: 'error' });
      }
      if (result.returnUrl) {
        pendingReturnUrl.value = result.returnUrl;
      }
    }
  }

  async function fetchUser() {
    if (sessionPromise) {
      return sessionPromise;
    }

    sessionPromise = (async () => {
      booting.value = true;
      error.value = null;

      try {
        const { data } = await api.get('/user');
        user.value = unwrapData(data);
      } catch {
        user.value = null;
      } finally {
        booting.value = false;
        sessionPromise = null;
      }
    })();

    return sessionPromise;
  }

  async function login(credentials) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.post('/login', credentials);
      user.value = unwrapData(data.user ?? data);
      await Promise.all([useCartStore().fetch(), useWishlistStore().fetch()]);
      await processPendingUserAction();
      return user.value;
    } catch (err) {
      error.value = friendlyApiError(err, 'Unable to log in.');
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function register(payload) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.post('/register', payload);
      user.value = unwrapData(data.user ?? data);
      await Promise.all([useCartStore().fetch(), useWishlistStore().fetch()]);
      await processPendingUserAction();
      return user.value;
    } catch (err) {
      error.value = friendlyApiError(err, 'Unable to register.');
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function loginWithGoogle({ accessToken, intent }) {
    loading.value = true;
    error.value = null;

    try {
      const response = await api.post('/auth/google', {
        access_token: accessToken,
        intent,
      });
      user.value = unwrapData(response.data.user ?? response.data);
      await Promise.all([useCartStore().fetch(), useWishlistStore().fetch()]);
      await processPendingUserAction();
      return {
        user: user.value,
        created: response.status === 201,
      };
    } catch (err) {
      const payload = err.response?.data || {};
      const googleError = new Error(
        friendlyApiError(
          err,
          payload.message ||
            Object.values(payload.errors || {})[0]?.[0] ||
            'Unable to continue with Google.',
        ),
      );
      googleError.code = payload.code || null;
      googleError.status = err.response?.status || null;
      error.value = googleError.message;
      throw googleError;
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    loggingOut.value = true;
    error.value = null;
    PendingUserAction.clear();
    pendingReturnUrl.value = null;

    try {
      await api.post('/logout');
    } catch {
      // Session may already be gone.
    } finally {
      user.value = null;
      await Promise.all([useCartStore().fetch(), useWishlistStore().fetch()]);
      loggingOut.value = false;
    }
  }

  return {
    user,
    booting,
    loading,
    loggingOut,
    redirecting,
    error,
    pendingReturnUrl,
    isAuthenticated,
    isAdmin,
    beginRedirect,
    endRedirect,
    takePendingReturnUrl,
    fetchUser,
    login,
    register,
    loginWithGoogle,
    logout,
  };
});
