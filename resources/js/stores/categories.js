import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

export const useCategoriesStore = defineStore('categories', () => {
  const list = ref([]);
  const current = ref(null);
  const products = ref([]);
  const loading = ref(false);
  const error = ref(null);

  let fetchAllPromise = null;
  let hasFetchedAll = false;

  async function fetchAll(options = {}) {
    if (fetchAllPromise) {
      return fetchAllPromise;
    }

    if (hasFetchedAll && !options.force) {
      return list.value;
    }

    fetchAllPromise = (async () => {
      loading.value = true;
      error.value = null;

      try {
        const { data } = await api.get('/categories');
        list.value = unwrapData(data) || [];
        hasFetchedAll = true;
        return list.value;
      } catch (err) {
        error.value = err.response?.data?.message || 'Unable to load categories.';
        list.value = [];
        return [];
      } finally {
        loading.value = false;
        fetchAllPromise = null;
      }
    })();

    return fetchAllPromise;
  }

  async function fetchBySlug(slug) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get(`/categories/${slug}`);
      current.value = unwrapData(data);
      products.value = unwrapData(data.products) || [];
      return current.value;
    } catch (err) {
      error.value = err.response?.data?.message || 'Category not found.';
      current.value = null;
      products.value = [];
      throw err;
    } finally {
      loading.value = false;
    }
  }

  return {
    list,
    current,
    products,
    loading,
    error,
    fetchAll,
    fetchBySlug,
  };
});
