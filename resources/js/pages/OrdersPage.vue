<script setup>
import { onMounted, ref } from 'vue';
import { useHead } from '@unhead/vue';
import { Download } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PageHero from '@/components/ui/PageHero.vue';
import api from '@/services/api';
import { downloadOrderInvoice } from '@/utils/downloadInvoice';
import { formatCurrency, unwrapData } from '@/utils/format';
import { useThemeStore } from '@/stores/theme';
import { useUiStore } from '@/stores/ui';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const ui = useUiStore();
const orders = ref([]);
const loading = ref(true);
const error = ref('');
const downloadingId = ref(null);

useHead(() =>
  seoHeadFromServer({
    title: `Orders | ${theme.brandName}`,
    description: `Your ${theme.brandName} order history.`,
    canonical: '/orders',
    robots: 'noindex,follow',
  }),
);
function formatOrderDate(value) {
  if (!value) return '';
  return new Date(value).toLocaleDateString('en-GB');
}

function formatStatus(status) {
  if (!status) return '';
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function formatPaymentMethod(method) {
  if (method === 'cod') return 'Cash on Delivery';
  if (method === 'razorpay') return 'Pay online';
  return method || '';
}

function itemInitials(name) {
  const parts = String(name || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);
  if (!parts.length) return '?';
  return parts.map((part) => part.charAt(0).toUpperCase()).join('');
}

function usesIgst(order) {
  return Number(order?.igst || 0) > 0;
}

async function downloadInvoice(order) {
  if (!order?.id || downloadingId.value) return;
  downloadingId.value = order.id;
  try {
    await downloadOrderInvoice(order.id);
  } catch (err) {
    ui.showToast(err.response?.data?.message || 'Unable to download invoice.', { type: 'error' });
  } finally {
    downloadingId.value = null;
  }
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
          <div class="order-card__body">
            <div class="order-card__meta">
              <h2 class="order-card__number">{{ order.number || order.id }}</h2>
              <p class="order-card__when">
                {{ formatOrderDate(order.created_at) }}
                <span aria-hidden="true"> — </span>
                {{ formatStatus(order.status) }}
              </p>
              <p class="order-card__payment">
                {{ formatPaymentMethod(order.payment_method) }}
                <span aria-hidden="true"> · </span>
                {{ formatStatus(order.payment_status) }}
              </p>
            </div>

            <ul class="order-card__items">
              <li
                v-for="item in order.items || []"
                :key="`${order.id}-${item.product_id}`"
                class="order-line"
              >
                <img
                  v-if="item.image"
                  class="order-line__thumb"
                  :src="item.image"
                  :alt="item.name || 'Product'"
                />
                <span
                  v-else
                  class="order-line__thumb order-line__thumb--placeholder"
                  aria-hidden="true"
                >
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
              </li>
            </ul>

            <dl class="order-card__totals">
              <div class="order-card__totals-row">
                <dt>Subtotal</dt>
                <dd>{{ formatCurrency(order.subtotal) }}</dd>
              </div>
              <div class="order-card__totals-row">
                <dt>Delivery</dt>
                <dd>
                  {{ Number(order.shipping) > 0 ? formatCurrency(order.shipping) : 'Free' }}
                </dd>
              </div>
              <div v-if="Number(order.cod_fee) > 0" class="order-card__totals-row">
                <dt>COD charge</dt>
                <dd>{{ formatCurrency(order.cod_fee) }}</dd>
              </div>
              <template v-if="usesIgst(order)">
                <div class="order-card__totals-row">
                  <dt>IGST</dt>
                  <dd>{{ formatCurrency(order.igst) }}</dd>
                </div>
              </template>
              <template v-else>
                <div class="order-card__totals-row">
                  <dt>CGST</dt>
                  <dd>{{ formatCurrency(order.cgst) }}</dd>
                </div>
                <div class="order-card__totals-row">
                  <dt>SGST</dt>
                  <dd>{{ formatCurrency(order.sgst) }}</dd>
                </div>
              </template>
              <div class="order-card__totals-row order-card__totals-row--grand">
                <dt>Grand total</dt>
                <dd>{{ formatCurrency(order.total) }}</dd>
              </div>
            </dl>
          </div>

          <div v-if="order.invoice_available" class="order-card__footer">
            <AppButton
              type="button"
              size="sm"
              variant="primary"
              :disabled="downloadingId === order.id"
              @click="downloadInvoice(order)"
            >
              <Download :size="16" aria-hidden="true" />
              {{ downloadingId === order.id ? 'Downloading…' : 'Download invoice' }}
            </AppButton>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>
