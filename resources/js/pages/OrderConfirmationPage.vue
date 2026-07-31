<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PageHero from '@/components/ui/PageHero.vue';
import api from '@/services/api';
import { formatCurrency, unwrapData } from '@/utils/format';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const error = ref('');
const order = ref(null);

useHead({
  title: () => `Order confirmed | ${theme.brandName}`,
});

const paymentMethodLabel = computed(() => {
  const method = order.value?.payment_method;
  if (method === 'cod') return 'Cash on Delivery';
  if (method === 'razorpay') return 'Razorpay';
  return method || '—';
});

const paymentStatusLabel = computed(() => {
  const status = order.value?.payment_status;
  if (!status) return '—';
  return status.charAt(0).toUpperCase() + status.slice(1);
});

const nextStepCopy = computed(() => {
  if (order.value?.payment_method === 'cod') {
    return 'Your order is confirmed. Please keep cash ready — you’ll pay when it is delivered.';
  }
  if (order.value?.payment_status === 'paid') {
    return 'Payment received. We’re preparing your order for shipment.';
  }
  return 'We’re processing your order.';
});

onMounted(async () => {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get(`/orders/${route.params.id}`);
    order.value = unwrapData(data);
    if (!order.value) {
      throw new Error('Order not found.');
    }
  } catch {
    error.value = 'Unable to load this order.';
    order.value = null;
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <LoadingSpinner v-if="loading" page />
  <section v-else class="order-confirm-page">
    <PageHero
      eyebrow="Checkout"
      title="Order confirmed"
      :lead="error || nextStepCopy"
      size="compact"
    />
    <div class="page-section">
      <div v-if="error" class="form-panel">
        <p class="form-error">{{ error }}</p>
        <AppButton type="button" @click="router.push('/orders')">View orders</AppButton>
      </div>
      <div v-else-if="order" class="order-confirm-card form-panel">
        <dl class="order-confirm-facts">
          <div>
            <dt>Order ID</dt>
            <dd>{{ order.number || order.id }}</dd>
          </div>
          <div>
            <dt>Payment method</dt>
            <dd>{{ paymentMethodLabel }}</dd>
          </div>
          <div>
            <dt>Payment status</dt>
            <dd>{{ paymentStatusLabel }}</dd>
          </div>
          <div>
            <dt>Total</dt>
            <dd>{{ formatCurrency(order.total) }}</dd>
          </div>
        </dl>
        <div class="order-confirm-actions">
          <AppButton to="/orders">View all orders</AppButton>
          <AppButton to="/shop" variant="secondary">Continue shopping</AppButton>
        </div>
      </div>
    </div>
  </section>
</template>
