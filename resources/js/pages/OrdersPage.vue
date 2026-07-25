<script setup>
import { onMounted, ref } from 'vue';
import { useHead } from '@unhead/vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PageHero from '@/components/ui/PageHero.vue';
import api from '@/services/api';
import { formatCurrency, unwrapData } from '@/utils/format';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const orders = ref([]);
const loading = ref(true);
const error = ref('');

useHead({
  title: () => `Orders | ${theme.brandName}`,
});

onMounted(async () => {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/orders');
    orders.value = unwrapData(data) || [];
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to load orders.';
    orders.value = [];
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <LoadingSpinner v-if="loading" page />
  <EmptyState
    v-else-if="!orders.length"
    title="No orders yet"
    description="Completed checkout orders will appear here."
    action-label="Start shopping"
  />
  <section v-else class="orders-page">
    <PageHero
      eyebrow="Account"
      title="Orders"
      lead="Track and review orders placed from your account."
      size="compact"
    />
    <div class="page-section">
      <p v-if="error" class="form-error">{{ error }}</p>
      <div class="orders-list">
        <article v-for="order in orders" :key="order.id" class="order-card">
          <div>
            <h2>{{ order.number || order.id }}</h2>
            <p>
              {{ new Date(order.created_at).toLocaleDateString() }} - {{ order.status }}
            </p>
          </div>
          <ul>
            <li v-for="item in order.items || []" :key="`${order.id}-${item.product_id}`">
              {{ item.quantity }} x {{ item.name }}
            </li>
          </ul>
          <strong>{{ formatCurrency(order.total) }}</strong>
        </article>
      </div>
    </div>
  </section>
</template>
