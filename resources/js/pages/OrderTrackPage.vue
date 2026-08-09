<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import {
  Download,
  ExternalLink,
  Headphones,
  MapPin,
  Package,
  ReceiptText,
  RefreshCw,
  Truck,
} from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { footerContact, footerWhatsApp } from '@/constants/footer';
import api from '@/services/api';
import { emailHref, phoneHref } from '@/utils/contactLinks';
import { downloadTrackedOrderInvoice } from '@/utils/downloadInvoice';
import { formatCurrency, unwrapData } from '@/utils/format';
import { useThemeStore } from '@/stores/theme';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const ui = useUiStore();

const loading = ref(true);
const error = ref('');
const track = ref(null);
const downloadingInvoice = ref(false);

const orderNumber = computed(() => String(route.params.number || ''));

useHead({
  title: () =>
    track.value?.number
      ? `Order ${track.value.number} | ${theme.brandName}`
      : `Track order | ${theme.brandName}`,
});

const timelineSteps = computed(() => {
  const t = track.value?.timeline || {};
  return [
    { key: 'confirmed', label: 'Confirmed', done: !!t.confirmed },
    { key: 'packed', label: 'Packed', done: !!t.packed },
    { key: 'shipped', label: 'Shipped', done: !!t.shipped },
    { key: 'delivered', label: 'Delivered', done: !!t.delivered },
  ];
});

const paymentMethodLabel = computed(() => {
  const method = track.value?.payment_method;
  if (method === 'cod') return 'Cash on Delivery';
  if (method === 'razorpay') return 'Pay online';
  return method || '—';
});

const paymentStatusLabel = computed(() => {
  const status = track.value?.payment_status;
  if (!status) return '—';
  return status.charAt(0).toUpperCase() + status.slice(1);
});

const paymentBadgeClass = computed(() => {
  const status = String(track.value?.payment_status || '').toLowerCase();
  if (status === 'paid') return 'order-track__badge--paid';
  if (status === 'failed') return 'order-track__badge--failed';
  return 'order-track__badge--pending';
});

function formatDate(value) {
  if (!value) return '';
  return new Date(value).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function formatDateTime(value) {
  if (!value) return '';
  return new Date(value).toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
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

async function loadTrack() {
  loading.value = true;
  error.value = '';
  track.value = null;

  if (/^\d+$/.test(orderNumber.value)) {
    await router.replace(`/orders/${orderNumber.value}/confirmed`);
    return;
  }

  try {
    const { data } = await api.get(`/orders/track/${encodeURIComponent(orderNumber.value)}`);
    track.value = unwrapData(data);
  } catch (err) {
    error.value = err.response?.data?.message || 'Order not found.';
  } finally {
    loading.value = false;
  }
}

async function downloadInvoice() {
  if (!track.value?.number || downloadingInvoice.value) return;
  downloadingInvoice.value = true;
  try {
    await downloadTrackedOrderInvoice(track.value.number);
  } catch (err) {
    ui.showToast(err.response?.data?.message || 'Unable to download invoice.', { type: 'error' });
  } finally {
    downloadingInvoice.value = false;
  }
}

onMounted(loadTrack);
</script>

<template>
  <LoadingSpinner v-if="loading" page label="Loading order" />

  <section v-else-if="error" class="order-track-page page-section">
    <div class="order-track-card order-track-card--empty">
      <h1>Order not found</h1>
      <p>{{ error }}</p>
      <div class="order-track__actions">
        <AppButton to="/orders">My orders</AppButton>
        <AppButton to="/contact" variant="secondary">Contact support</AppButton>
      </div>
    </div>
  </section>

  <section v-else-if="track" class="order-track-page">
    <div class="page-section">
      <div class="order-track-hero">
        <p class="order-track-hero__eyebrow">Order tracking</p>
        <h1>{{ track.number }}</h1>
        <p class="order-track-hero__status">
          Status: <strong>{{ track.status_label }}</strong>
        </p>
      </div>

      <div class="order-track-grid">
        <div class="order-track-card">
          <h2>
            <Package :size="18" aria-hidden="true" />
            Order status
          </h2>
          <ol class="order-track-timeline" aria-label="Order progress">
            <li
              v-for="step in timelineSteps"
              :key="step.key"
              class="order-track-timeline__step"
              :class="{ 'is-done': step.done }"
            >
              <span class="order-track-timeline__dot" aria-hidden="true" />
              <span>{{ step.label }}</span>
            </li>
          </ol>
          <p v-if="track.status === 'Cancelled'" class="order-track__note order-track__note--danger">
            This order was cancelled.
          </p>
          <p v-else-if="track.status === 'AwaitingPayment'" class="order-track__note">
            Waiting for payment confirmation.
          </p>
          <p v-else-if="track.status === 'InventoryHold'" class="order-track__note order-track__note--danger">
            Payment was received, but inventory needs review before shipment. Support will contact you if needed.
          </p>
        </div>

        <div class="order-track-card">
          <h2>
            <Truck :size="18" aria-hidden="true" />
            Shipment tracking
          </h2>
          <template v-if="track.courier?.has_details">
            <dl class="order-track-facts">
              <div v-if="track.courier.partner">
                <dt>Partner</dt>
                <dd>{{ track.courier.partner }}</dd>
              </div>
              <div v-if="track.courier.awb_number">
                <dt>Tracking ID (AWB)</dt>
                <dd>{{ track.courier.awb_number }}</dd>
              </div>
              <div v-if="track.shipment?.shipment_status">
                <dt>Shipment status</dt>
                <dd>{{ track.shipment.shipment_status }}</dd>
              </div>
              <div v-if="track.shipment?.pickup_status">
                <dt>Pickup status</dt>
                <dd>{{ track.shipment.pickup_status }}</dd>
              </div>
              <div v-if="track.courier.dispatched_at">
                <dt>Dispatched</dt>
                <dd>{{ formatDate(track.courier.dispatched_at) }}</dd>
              </div>
              <div v-if="track.courier.expected_delivery_at">
                <dt>Estimated delivery</dt>
                <dd>{{ formatDate(track.courier.expected_delivery_at) }}</dd>
              </div>
              <div v-if="track.shipment?.last_synced_at">
                <dt>Last updated</dt>
                <dd>{{ formatDateTime(track.shipment.last_synced_at) }}</dd>
              </div>
            </dl>
            <a
              v-if="track.shipment?.tracking_url"
              class="button button--primary order-track__track-link"
              :href="track.shipment.tracking_url"
              target="_blank"
              rel="noopener noreferrer"
            >
              <ExternalLink :size="16" aria-hidden="true" />
              Track shipment
            </a>
          </template>
          <p v-else class="order-track__note">
            Tracking details will appear here once your order is handed to the courier.
          </p>
        </div>

        <div class="order-track-card">
          <h2>Payment &amp; dates</h2>
          <dl class="order-track-facts">
            <div>
              <dt>Payment</dt>
              <dd>
                {{ paymentMethodLabel }}
                <span class="order-track__badge" :class="paymentBadgeClass">
                  {{ paymentStatusLabel }}
                </span>
              </dd>
            </div>
            <div>
              <dt>Order date</dt>
              <dd>{{ formatDateTime(track.created_at) || '—' }}</dd>
            </div>
            <div v-if="track.paid_at">
              <dt>Paid on</dt>
              <dd>{{ formatDateTime(track.paid_at) }}</dd>
            </div>
            <div v-if="track.invoice_number">
              <dt>Invoice number</dt>
              <dd>{{ track.invoice_number }}</dd>
            </div>
            <div v-if="track.invoice_issued_at">
              <dt>Invoice issued</dt>
              <dd>{{ formatDate(track.invoice_issued_at) }}</dd>
            </div>
            <div>
              <dt>Estimated delivery</dt>
              <dd>
                {{
                  track.expected_delivery_at
                    ? formatDate(track.expected_delivery_at)
                    : 'Pending'
                }}
              </dd>
            </div>
            <div v-if="track.location?.city || track.location?.district || track.location?.state">
              <dt>Ship to</dt>
              <dd>
                {{
                  [track.location.city, track.location.district, track.location.state]
                    .filter(Boolean)
                    .join(', ')
                }}
              </dd>
            </div>
          </dl>
        </div>

        <div class="order-track-card">
          <h2>
            <MapPin :size="18" aria-hidden="true" />
            Delivery details
          </h2>
          <dl class="order-track-facts">
            <div>
              <dt>Customer</dt>
              <dd>{{ track.customer?.full_name || '—' }}</dd>
            </div>
            <div>
              <dt>Email</dt>
              <dd>
                <a v-if="track.customer?.email" :href="emailHref(track.customer.email)">
                  {{ track.customer.email }}
                </a>
                <template v-else>—</template>
              </dd>
            </div>
            <div>
              <dt>Phone</dt>
              <dd>
                <a v-if="track.customer?.phone" :href="phoneHref(track.customer.phone)">
                  {{ track.customer.phone }}
                </a>
                <template v-else>—</template>
              </dd>
            </div>
            <div>
              <dt>Delivery address</dt>
              <dd class="order-track__address">
                {{
                  [
                    track.address?.address,
                    track.address?.city,
                    track.address?.district,
                    track.address?.state,
                    track.address?.postal_code,
                  ]
                    .filter(Boolean)
                    .join(', ')
                }}
              </dd>
            </div>
          </dl>
        </div>

        <div class="order-track-card">
          <h2>
            <ReceiptText :size="18" aria-hidden="true" />
            Ordered products
          </h2>
          <ul class="order-track-items">
            <li v-for="(item, index) in track.items || []" :key="`${item.sku}-${index}`">
              <img
                v-if="item.image"
                :src="item.image"
                :alt="item.name || 'Product'"
              />
              <span v-else class="order-track-items__placeholder" aria-hidden="true">
                {{ itemInitials(item.name) }}
              </span>
              <div>
                <strong>{{ item.name }}</strong>
                <p>×{{ item.quantity }} · {{ formatCurrency(item.unit_price) }}</p>
                <p v-if="item.sku || item.hsn">
                  <span v-if="item.sku">SKU: {{ item.sku }}</span>
                  <span v-if="item.sku && item.hsn"> · </span>
                  <span v-if="item.hsn">HSN: {{ item.hsn }}</span>
                </p>
              </div>
              <strong>{{ formatCurrency(item.line_total) }}</strong>
            </li>
          </ul>
          <dl class="order-track-totals">
            <div>
              <dt>Subtotal</dt>
              <dd>{{ formatCurrency(track.totals.subtotal) }}</dd>
            </div>
            <div>
              <dt>Delivery</dt>
              <dd>
                {{
                  Number(track.totals.shipping) > 0
                    ? formatCurrency(track.totals.shipping)
                    : 'Free'
                }}
              </dd>
            </div>
            <div v-if="Number(track.totals.cod_fee) > 0">
              <dt>COD charge</dt>
              <dd>{{ formatCurrency(track.totals.cod_fee) }}</dd>
            </div>
            <template v-if="Number(track.totals.igst) > 0">
              <div>
                <dt>IGST</dt>
                <dd>{{ formatCurrency(track.totals.igst) }}</dd>
              </div>
            </template>
            <template v-else>
              <div>
                <dt>CGST</dt>
                <dd>{{ formatCurrency(track.totals.cgst) }}</dd>
              </div>
              <div>
                <dt>SGST</dt>
                <dd>{{ formatCurrency(track.totals.sgst) }}</dd>
              </div>
            </template>
            <div class="order-track-totals__grand">
              <dt>Grand total</dt>
              <dd>{{ formatCurrency(track.totals.total) }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="order-track-card order-track-card--actions">
        <div class="order-track__actions">
          <AppButton
            v-if="track.invoice_available"
            type="button"
            :disabled="downloadingInvoice"
            @click="downloadInvoice"
          >
            <Download :size="16" aria-hidden="true" />
            {{ downloadingInvoice ? 'Downloading…' : 'Download invoice' }}
          </AppButton>
          <AppButton
            :to="track.support?.contact_path || '/contact'"
            variant="secondary"
          >
            <Headphones :size="16" aria-hidden="true" />
            Contact support
          </AppButton>
          <AppButton
            v-if="track.return_eligible"
            :to="`${track.support?.returns_path || '/returns'}?order=${encodeURIComponent(track.number)}`"
            variant="secondary"
          >
            <RefreshCw :size="16" aria-hidden="true" />
            Return / replace
          </AppButton>
        </div>
        <p v-if="!track.return_eligible" class="order-track__note">
          Return / replace is available within 7 days after delivery.
        </p>
        <p class="order-track__support">
          Email
          <a :href="emailHref(track.support?.email || footerContact.email)">
            {{ track.support?.email || footerContact.email }}
          </a>
          · Call
          <a :href="phoneHref(track.support?.phone || footerContact.phone)">
            {{ track.support?.phone || footerContact.phone }}
          </a>
          ·
          <a :href="footerWhatsApp.href" target="_blank" rel="noopener noreferrer">
            WhatsApp
          </a>
        </p>
        <p class="order-track__support">
          <RouterLink to="/orders">View all orders</RouterLink>
          (sign in required)
        </p>
      </div>
    </div>
  </section>
</template>
