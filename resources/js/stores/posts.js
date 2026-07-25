import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

export const usePostsStore = defineStore('posts', () => {
  const list = ref([]);
  const current = ref(null);
  const loading = ref(false);
  const error = ref(null);

  async function fetchList() {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/posts');
      list.value = unwrapData(data) || [];
      return list.value;
    } catch (err) {
      error.value = err.response?.data?.message || 'Unable to load posts.';
      list.value = [];
      return [];
    } finally {
      loading.value = false;
    }
  }

  async function fetchBySlug(slug) {
    loading.value = true;
    error.value = null;
    current.value = null;

    try {
      const { data } = await api.get(`/posts/${slug}`);
      current.value = unwrapData(data) || data;
      return current.value;
    } catch (err) {
      error.value = err.response?.data?.message || 'Unable to load this post.';
      current.value = null;
      throw err;
    } finally {
      loading.value = false;
    }
  }

  return {
    list,
    current,
    loading,
    error,
    fetchList,
    fetchBySlug,
  };
});
