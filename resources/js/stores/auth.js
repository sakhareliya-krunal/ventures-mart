import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import { useCartStore } from '@/stores/cart';
import { useWishlistStore } from '@/stores/wishlist';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const loading = ref(false);
  const error = ref(null);

  const isAuthenticated = computed(() => Boolean(user.value));
  const isAdmin = computed(() => Boolean(user.value?.is_admin));

  async function fetchUser() {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/user');
      user.value = unwrapData(data);
    } catch {
      user.value = null;
    } finally {
      loading.value = false;
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

  async function logout() {
    loading.value = true;
    error.value = null;

    try {
      await api.post('/logout');
    } catch {
      // Session may already be gone.
    } finally {
      user.value = null;
      loading.value = false;
      await Promise.all([useCartStore().fetch(), useWishlistStore().fetch()]);
    }
  }

  return {
    user,
    loading,
    error,
    isAuthenticated,
    isAdmin,
    fetchUser,
    login,
    register,
    logout,
  };
});
