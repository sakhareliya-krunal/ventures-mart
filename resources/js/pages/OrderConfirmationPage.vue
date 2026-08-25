<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PageHero from '@/components/ui/PageHero.vue';
import api from '@/services/api';
import { downloadOrderInvoice } from '@/utils/downloadInvoice';
import { formatCurrency, unwrapData } from '@/utils/format';
import { useThemeStore } from '@/stores/theme';
import { useUiStore } from '@/stores/ui';
import { orderMetaParams, trackMetaEvent } from '@/services/metaPixel';

const theme = useThemeStore();
const ui = useUiStore();
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const downloadingInvoice = ref(false);
const error = ref('');
const order = ref(null);

useHead({
  title: () => `Order confirmed | ${theme.brandName}`,
});

const paymentMethodLabel = computed(() => {
  const method = order.value?.payment_method;
  if (method === 'cod') return 'Cash on Delivery';
  if (method === 'razorpay') return 'Pay online';
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

function itemInitials(name) {
  const parts = String(name || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);
  if (!parts.length) return '?';
  return parts.map((part) => part.charAt(0).toUpperCase()).join('');
}

async function downloadInvoice() {
  if (!order.value?.id || downloadingInvoice.value) return;
  downloadingInvoice.value = true;
  try {
    await downloadOrderInvoice(order.value.id);
  } catch (err) {
    ui.showToast(err.response?.data?.message || 'Unable to download invoice.', { type: 'error' });
  } finally {
    downloadingInvoice.value = false;
  }
}

onMounted(async () => {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get(`/orders/${route.params.id}`);
    order.value = unwrapData(data);
    if (!order.value) {
      throw new Error('Order not found.');
    }

    const eventId = order.value.meta_purchase_event_id;
    if (eventId && typeof sessionStorage !== 'undefined') {
      const key = `meta_purchase_${order.value.id}`;
      if (!sessionStorage.getItem(key)) {
        sessionStorage.setItem(key, eventId);
        trackMetaEvent('Purchase', orderMetaParams(order.value), {
          eventId,
          sendCapi: false,
        });
      }
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
        <div class="order-confirm-card__body">
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
            <div v-if="Number(order.cod_fee || 0) > 0">
              <dt>COD charge</dt>
              <dd>{{ formatCurrency(order.cod_fee) }}</dd>
            </div>
            <div class="order-confirm-facts__total">
              <dt>Total</dt>
              <dd>{{ formatCurrency(order.total) }}</dd>
            </div>
          </dl>

          <div v-if="order.items?.length" class="order-confirm-items">
            <h3 class="order-confirm-items__title">Items</h3>
            <ul class="order-confirm-items__list">
              <li
                v-for="item in order.items"
                :key="`${order.id}-${item.product_id}`"
                class="order-line"
              >
                <img
                  v-if="item.image"
                  class="order-line__thumb"
                  :src="item.image"
                  :alt="item.name || 'Product'"
                />
                <span v-else class="order-line__thumb order-line__thumb--placeholder" aria-hidden="true">
                  {{ itemInitials(item.name) }}
                </span>
                <div class="order-line__body">
                  <strong class="order-line__name">{{ item.name }}</strong>
                  <span class="order-line__meta">
                    ×{{ item.quantity }}
                    <template v-if="item.unit_price != null">
                      · {{ formatCurrency(item.unit_price) }}
                    </template>
                  </span>
                </div>
                <strong v-if="item.line_total != null" class="order-line__total">
                  {{ formatCurrency(item.line_total) }}
                </strong>
              </li>
            </ul>
          </div>

          <div class="order-confirm-actions">
            <AppButton
              v-if="order.invoice_available"
              type="button"
              :loading="downloadingInvoice"
              @click="downloadInvoice"
            >
              Download invoice
            </AppButton>
            <AppButton to="/orders" :variant="order.invoice_available ? 'secondary' : 'primary'">
              View all orders
            </AppButton>
            <AppButton to="/shop" variant="secondary">Continue shopping</AppButton>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
