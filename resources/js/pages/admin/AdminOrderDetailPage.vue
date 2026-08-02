<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import {
  orderStatusBadgeClass,
  orderStatusLabel,
  paymentStatusBadgeClass,
  paymentStatusLabel,
} from '@/utils/adminBadges';
import { downloadOrderInvoice } from '@/utils/downloadInvoice';
import { formatCurrency, unwrapData } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const saving = ref(false);
const markingPaid = ref(false);
const deleting = ref(false);
const downloadingInvoice = ref(false);
const confirmDeleteOpen = ref(false);
const error = ref('');
const order = ref(null);
const status = ref('Processing');

const statusOptions = [
  { value: 'AwaitingPayment', label: 'Awaiting payment' },
  { value: 'Processing', label: 'Confirmed' },
  { value: 'Packed', label: 'Packed' },
  { value: 'Shipped', label: 'Shipped' },
  { value: 'Delivered', label: 'Delivered' },
  { value: 'Cancelled', label: 'Cancelled' },
];

const courierForm = reactive({
  courier_partner: '',
  awb_number: '',
  tracking_number: '',
  dispatched_at: '',
  expected_delivery_at: '',
});

const savingCourier = ref(false);
const courierSuccess = ref('');

const items = computed(() => order.value?.items || []);

const shipping = computed(() => order.value?.address || {});

const customerName = computed(
  () => order.value?.user?.name || shipping.value.full_name || '—',
);

const customerEmail = computed(
  () => order.value?.user?.email || shipping.value.email || '—',
);

const cityLine = computed(() => {
  const parts = [shipping.value.city, shipping.value.state, shipping.value.postal_code].filter(
    Boolean,
  );
  return parts.length ? parts.join(', ') : '—';
});

const paymentMethodLabel = computed(() => {
  const method = order.value?.payment_method;
  if (method === 'cod') return 'Cash on Delivery';
  if (method === 'razorpay') return 'Razorpay';
  return method || '—';
});

const canMarkPaid = computed(
  () => order.value?.payment_method === 'cod' && order.value?.payment_status === 'pending',
);

async function downloadInvoice() {
  if (!order.value?.id || downloadingInvoice.value) return;
  downloadingInvoice.value = true;
  error.value = '';
  try {
    await downloadOrderInvoice(order.value.id, { admin: true });
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to download invoice.';
  } finally {
    downloadingInvoice.value = false;
  }
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

function toDateInput(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toISOString().slice(0, 10);
}

function fillCourierForm(source) {
  courierForm.courier_partner = source?.courier_partner || '';
  courierForm.awb_number = source?.awb_number || '';
  courierForm.tracking_number = source?.tracking_number || '';
  courierForm.dispatched_at = toDateInput(source?.dispatched_at);
  courierForm.expected_delivery_at = toDateInput(source?.expected_delivery_at);
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/admin/orders/${route.params.id}`);
    order.value = unwrapData(data);
    status.value = order.value?.status || 'Processing';
    fillCourierForm(order.value);
  } catch {
    error.value = 'Order not found.';
  } finally {
    loading.value = false;
  }
});

async function saveStatus() {
  if (!order.value) return;
  saving.value = true;
  error.value = '';
  try {
    const { data } = await api.patch(`/admin/orders/${order.value.id}`, { status: status.value });
    order.value = unwrapData(data);
    fillCourierForm(order.value);
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to update status.';
  } finally {
    saving.value = false;
  }
}

async function saveCourier() {
  if (!order.value || savingCourier.value) return;
  savingCourier.value = true;
  error.value = '';
  courierSuccess.value = '';
  try {
    const { data } = await api.patch(`/admin/orders/${order.value.id}`, {
      courier_partner: courierForm.courier_partner || null,
      awb_number: courierForm.awb_number || null,
      tracking_number: courierForm.tracking_number || null,
      dispatched_at: courierForm.dispatched_at || null,
      expected_delivery_at: courierForm.expected_delivery_at || null,
    });
    order.value = unwrapData(data);
    fillCourierForm(order.value);
    courierSuccess.value = 'Courier details saved.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to save courier details.';
  } finally {
    savingCourier.value = false;
  }
}

async function markPaymentReceived() {
  if (!order.value || !canMarkPaid.value) return;
  markingPaid.value = true;
  error.value = '';
  try {
    const { data } = await api.patch(`/admin/orders/${order.value.id}`, {
      payment_status: 'paid',
    });
    order.value = unwrapData(data);
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to mark payment received.';
  } finally {
    markingPaid.value = false;
  }
}

async function removeOrder() {
  if (!order.value || deleting.value) return;
  deleting.value = true;
  error.value = '';
  try {
    await api.delete(`/admin/orders/${order.value.id}`);
    confirmDeleteOpen.value = false;
    await router.push({ name: 'admin-orders' });
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to delete order.';
    confirmDeleteOpen.value = false;
  } finally {
    deleting.value = false;
  }
}
</script>

<template>
  <div>
    <div class="admin-toolbar">
      <AppButton type="button" variant="ghost" @click="router.push('/admin/orders')">
        ← Back to orders
      </AppButton>
      <AppButton
        v-if="order"
        type="button"
        variant="danger"
        @click="confirmDeleteOpen = true"
      >
        Delete order
      </AppButton>
      <AppButton
        v-if="order?.invoice_available"
        type="button"
        variant="secondary"
        :disabled="downloadingInvoice"
        @click="downloadInvoice"
      >
        {{ downloadingInvoice ? 'Downloading…' : 'Download invoice' }}
      </AppButton>
    </div>

    <LoadingSpinner v-if="loading" page label="Loading order" />
    <div v-else-if="!order" class="admin-panel">{{ error || 'Order not found.' }}</div>
    <div v-else class="admin-panel admin-order-detail">
      <header class="admin-order-detail__header">
        <div>
          <h2>Order {{ order.number }}</h2>
          <p class="admin-muted">
            Placed {{ order.created_at ? new Date(order.created_at).toLocaleString() : '—' }}
          </p>
          <p class="admin-muted">
            Payment method: <strong>{{ paymentMethodLabel }}</strong>
          </p>
        </div>
        <div class="admin-order-detail__badges">
          <span class="admin-badge" :class="paymentStatusBadgeClass(order.payment_status)">
            {{ paymentStatusLabel(order.payment_status) }}
          </span>
          <span class="admin-badge" :class="orderStatusBadgeClass(order.status)">
            {{ orderStatusLabel(order.status) }}
          </span>
          <span v-if="order.paid_at" class="admin-muted">
            Paid {{ new Date(order.paid_at).toLocaleString() }}
          </span>
        </div>
      </header>

      <p v-if="error" class="form-error">{{ error }}</p>
      <p v-if="courierSuccess" class="form-success">{{ courierSuccess }}</p>

      <section class="admin-order-detail__section">
        <h3>Fulfillment</h3>
        <div class="admin-order-detail__actions">
          <label class="admin-order-detail__status-field">
            Status
            <AppSelect
              v-model="status"
              :options="statusOptions"
              placeholder="Select status"
              aria-label="Order status"
            />
          </label>
          <AppButton type="button" :disabled="saving" @click="saveStatus">
            {{ saving ? 'Saving…' : 'Update status' }}
          </AppButton>
          <AppButton
            v-if="canMarkPaid"
            type="button"
            variant="secondary"
            :disabled="markingPaid"
            @click="markPaymentReceived"
          >
            {{ markingPaid ? 'Saving…' : 'Mark payment received' }}
          </AppButton>
        </div>
      </section>

      <section class="admin-order-detail__section">
        <h3>Items</h3>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Line</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="`${item.product_id}-${item.sku}`">
                <td data-label="Item">
                  <div class="admin-order-item">
                    <img
                      v-if="item.image"
                      class="admin-order-item__thumb"
                      :src="item.image"
                      :alt="item.name || 'Product'"
                    />
                    <span
                      v-else
                      class="admin-order-item__thumb admin-order-item__thumb--placeholder"
                      aria-hidden="true"
                    >
                      {{ itemInitials(item.name) }}
                    </span>
                    <div class="admin-order-item__copy">
                      <strong>{{ item.name }}</strong>
                      <div class="admin-muted">{{ item.sku }}</div>
                    </div>
                  </div>
                </td>
                <td data-label="Qty">{{ item.quantity }}</td>
                <td data-label="Unit">{{ formatCurrency(item.unit_price) }}</td>
                <td data-label="Line">{{ formatCurrency(item.line_total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="admin-order-detail__section">
        <h3>Customer &amp; shipping</h3>
        <dl class="admin-order-address-card">
          <div class="admin-order-address-card__row">
            <dt>Name</dt>
            <dd>{{ customerName }}</dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Email</dt>
            <dd>{{ customerEmail }}</dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Phone</dt>
            <dd>{{ shipping.phone || '—' }}</dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Address</dt>
            <dd>{{ shipping.address || '—' }}</dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>City / State / PIN</dt>
            <dd>{{ cityLine }}</dd>
          </div>
        </dl>
      </section>

      <section class="admin-order-detail__section">
        <h3>Courier / tracking</h3>
        <form class="admin-form" @submit.prevent="saveCourier">
          <FormField v-model="courierForm.courier_partner" label="Courier partner" />
          <FormField v-model="courierForm.awb_number" label="AWB number" />
          <FormField v-model="courierForm.tracking_number" label="Tracking number" />
          <div class="admin-form__grid">
            <FormField
              v-model="courierForm.dispatched_at"
              label="Dispatched on"
              type="date"
            />
            <FormField
              v-model="courierForm.expected_delivery_at"
              label="Expected delivery"
              type="date"
            />
          </div>
          <div class="admin-form__actions">
            <AppButton type="submit" :disabled="savingCourier">
              {{ savingCourier ? 'Saving…' : 'Save courier' }}
            </AppButton>
          </div>
        </form>
      </section>

      <section class="admin-order-detail__section">
        <h3>Totals</h3>
        <dl class="admin-order-totals">
          <div class="admin-order-totals__row">
            <dt>Subtotal</dt>
            <dd>{{ formatCurrency(order.subtotal) }}</dd>
          </div>
          <div class="admin-order-totals__row">
            <dt>Delivery</dt>
            <dd>{{ formatCurrency(order.shipping) }}</dd>
          </div>
          <template v-if="Number(order.igst || 0) > 0">
            <div class="admin-order-totals__row">
              <dt>IGST (5%)</dt>
              <dd>{{ formatCurrency(order.igst) }}</dd>
            </div>
          </template>
          <template v-else>
            <div class="admin-order-totals__row">
              <dt>CGST (2.5%)</dt>
              <dd>{{ formatCurrency(order.cgst) }}</dd>
            </div>
            <div class="admin-order-totals__row">
              <dt>SGST (2.5%)</dt>
              <dd>{{ formatCurrency(order.sgst) }}</dd>
            </div>
          </template>
          <div v-if="Number(order.cod_fee || 0) > 0" class="admin-order-totals__row">
            <dt>COD charge</dt>
            <dd>{{ formatCurrency(order.cod_fee) }}</dd>
          </div>
          <div v-if="order.seller_state" class="admin-order-totals__row">
            <dt>Seller state</dt>
            <dd>{{ order.seller_state }}</dd>
          </div>
          <div class="admin-order-totals__row admin-order-totals__row--total">
            <dt>Total</dt>
            <dd>{{ formatCurrency(order.total) }}</dd>
          </div>
        </dl>
      </section>
    </div>

    <ConfirmDialog
      v-model:open="confirmDeleteOpen"
      title="Delete order?"
      message="This order will be permanently removed. Stock will be restored when applicable."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="removeOrder"
    />
  </div>
</template>
