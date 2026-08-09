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

  async function establishSessionAfterAuth() {
    await fetchUser();

    if (!user.value) {
      error.value =
        'Your session could not be established. Check that you are using the same host as the API (localhost vs 127.0.0.1), then try again.';
      const err = new Error(error.value);
      err.code = 'session_not_established';
      throw err;
    }

    await Promise.all([useCartStore().fetch(), useWishlistStore().fetch()]);
    await processPendingUserAction();
    return user.value;
  }

  async function login(credentials) {
    loading.value = true;
    error.value = null;

    try {
      await api.post('/login', credentials);
      return await establishSessionAfterAuth();
    } catch (err) {
      if (err?.code !== 'session_not_established') {
        error.value = friendlyApiError(err, 'Unable to log in.');
      }
      user.value = null;
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function register(payload) {
    loading.value = true;
    error.value = null;

    try {
      await api.post('/register', payload);
      return await establishSessionAfterAuth();
    } catch (err) {
      if (err?.code !== 'session_not_established') {
        error.value = friendlyApiError(err, 'Unable to register.');
      }
      user.value = null;
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
      await establishSessionAfterAuth();
      return {
        user: user.value,
        created: response.status === 201,
      };
    } catch (err) {
      user.value = null;
      if (err?.code === 'session_not_established') {
        throw err;
      }
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
