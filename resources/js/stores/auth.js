import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import { useCartStore } from '@/stores/cart';
import { useWishlistStore } from '@/stores/wishlist';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const booting = ref(false);
  const loading = ref(false);
  const loggingOut = ref(false);
  const redirecting = ref(false);
  const error = ref(null);

  const isAuthenticated = computed(() => Boolean(user.value));
  const isAdmin = computed(() => Boolean(user.value?.is_admin));

  function beginRedirect() {
    redirecting.value = true;
  }

  function endRedirect() {
    redirecting.value = false;
  }

  async function fetchUser() {
    booting.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/user');
      user.value = unwrapData(data);
    } catch {
      user.value = null;
    } finally {
      booting.value = false;
    }
  }

  async function login(credentials) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.post('/login', credentials);
      user.value = unwrapData(data.user ?? data);
      await Promise.all([useCartStore().fetch(), useWishlistStore().fetch()]);
      return user.value;
    } catch (err) {
      error.value = err.response?.data?.message || 'Unable to log in.';
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
      return user.value;
    } catch (err) {
      error.value = err.response?.data?.message || 'Unable to register.';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function loginWithGoogle({ credential, intent }) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.post('/auth/google', { credential, intent });
      user.value = unwrapData(data.user ?? data);
      await Promise.all([useCartStore().fetch(), useWishlistStore().fetch()]);
      return user.value;
    } catch (err) {
      const payload = err.response?.data || {};
      const googleError = new Error(
        payload.message ||
          Object.values(payload.errors || {})[0]?.[0] ||
          'Unable to continue with Google.',
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
    isAuthenticated,
    isAdmin,
    beginRedirect,
    endRedirect,
    fetchUser,
    login,
    register,
    loginWithGoogle,
    logout,
  };
});
