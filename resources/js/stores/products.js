import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

export const useProductsStore = defineStore('products', () => {
  const list = ref([]);
  const featured = ref([]);
  const sale = ref([]);
  const current = ref(null);
  const related = ref([]);
  const productReviews = ref([]);
  const reviewsLoading = ref(false);
  const reviewSubmitting = ref(false);
  const reviewError = ref(null);
  const loading = ref(false);
  const error = ref(null);

  async function fetchList(params = {}) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/products', { params });
      list.value = unwrapData(data) || [];
      return list.value;
    } catch (err) {
      error.value = err.response?.data?.message || 'Unable to load products.';
      list.value = [];
      return [];
    } finally {
      loading.value = false;
    }
  }

  async function fetchFeatured() {
    const { data } = await api.get('/products/featured');
    featured.value = unwrapData(data) || [];
    return featured.value;
  }

  async function fetchSale() {
    const { data } = await api.get('/products/sale');
    sale.value = unwrapData(data) || [];
    return sale.value;
  }

  async function fetchBySlug(slug) {
    loading.value = true;
    error.value = null;
    productReviews.value = [];

    try {
      const { data } = await api.get(`/products/${slug}`);
      current.value = unwrapData(data);
      related.value = unwrapData(data.related) || [];
      return current.value;
    } catch (err) {
      error.value = err.response?.data?.message || 'Product not found.';
      current.value = null;
      related.value = [];
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function fetchReviews(slug) {
    reviewsLoading.value = true;
    reviewError.value = null;

    try {
      const { data } = await api.get(`/products/${slug}/reviews`);
      productReviews.value = unwrapData(data) || [];
      return productReviews.value;
    } catch (err) {
      reviewError.value = err.response?.data?.message || 'Unable to load reviews.';
      productReviews.value = [];
      return [];
    } finally {
      reviewsLoading.value = false;
    }
  }

  async function submitReview(slug, payload) {
    reviewSubmitting.value = true;
    reviewError.value = null;

    try {
      const { data } = await api.post(`/products/${slug}/reviews`, payload);
      const review = unwrapData(data);
      productReviews.value = [review, ...productReviews.value.filter((item) => item.id !== review.id)];

      if (current.value && data.product) {
        current.value = {
          ...current.value,
          rating: data.product.rating,
          reviews: data.product.reviews,
        };
      }

      return review;
    } catch (err) {
      const message =
        err.response?.data?.message ||
        Object.values(err.response?.data?.errors || {})
          .flat()
          .join(' ') ||
        'Unable to submit review.';
      reviewError.value = message;
      throw err;
    } finally {
      reviewSubmitting.value = false;
    }
  }

  return {
    list,
    featured,
    sale,
    current,
    related,
    productReviews,
    reviewsLoading,
    reviewSubmitting,
    reviewError,
    loading,
    error,
    fetchList,
    fetchFeatured,
    fetchSale,
    fetchBySlug,
    fetchReviews,
    submitReview,
  };
});
