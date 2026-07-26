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

function formatOrderDate(value) {
  if (!value) return '';
  return new Date(value).toLocaleDateString('en-GB');
}

function formatStatus(status) {
  if (!status) return '';
  return status.charAt(0).toUpperCase() + status.slice(1);
}

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
        <article
          v-for="(order, index) in orders"
          :key="order.id"
          class="order-card"
          :style="{ animationDelay: `${Math.min(index, 8) * 55}ms` }"
        >
          <div class="order-card__meta">
            <h2 class="order-card__number">{{ order.number || order.id }}</h2>
            <p class="order-card__when">
              {{ formatOrderDate(order.created_at) }}
              <span aria-hidden="true"> — </span>
              {{ formatStatus(order.status) }}
            </p>
          </div>
          <ul class="order-card__items">
            <li
              v-for="item in order.items || []"
              :key="`${order.id}-${item.product_id}`"
            >
              {{ item.quantity }} x {{ item.name }}
            </li>
          </ul>
          <strong class="order-card__total">{{ formatCurrency(order.total) }}</strong>
        </article>
      </div>
    </div>
  </section>
</template>
