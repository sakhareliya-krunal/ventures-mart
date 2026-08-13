<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import InventoryReturnDialog from '@/components/admin/InventoryReturnDialog.vue';
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
import { emailHref, phoneHref } from '@/utils/contactLinks';
import { downloadOrderInvoice } from '@/utils/downloadInvoice';
import { formatCurrency, unwrapData } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const saving = ref(false);
const markingPaid = ref(false);
const downloadingInvoice = ref(false);
const retryingShiprocket = ref(false);
const syncingShiprocket = ref(false);
const switchDialogOpen = ref(false);
const switchingToManual = ref(false);
const restoreDialogOpen = ref(false);
const restoringToShiprocket = ref(false);
const resendingConfirmation = ref(false);
const error = ref('');
const order = ref(null);
const status = ref('Processing');
const cancellationReason = ref('');
const markingRefunded = ref(false);
const returnDialogOpen = ref(false);
const returnItem = ref(null);
const processingReturn = ref(false);
const returnError = ref('');
const inventorySuccess = ref('');

const statusOptions = [
  { value: 'AwaitingPayment', label: 'Awaiting payment' },
  { value: 'InventoryHold', label: 'Inventory hold' },
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
  const parts = [
    shipping.value.city,
    shipping.value.district,
    shipping.value.state,
    shipping.value.postal_code,
  ].filter(Boolean);
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

const shiprocketManaged = computed(() => order.value?.fulfillment_method === 'shiprocket');
const fulfillmentLabel = computed(() =>
  shiprocketManaged.value ? 'Shiprocket' : 'Manual',
);

function returnableQuantity(item) {
  return Math.max(0, Number(item.shipped_quantity || 0) - Number(item.returned_quantity || 0));
}

function reservationLabel(item) {
  const state = item.inventory_reservation?.state;
  return state ? String(state).replaceAll('_', ' ') : 'Not allocated';
}

function reservationBadge(item) {
  const state = item.inventory_reservation?.state;
  if (['reserved', 'committed', 'consumed'].includes(state)) return 'admin-badge--info';
  if (['released', 'expired', 'cancelled'].includes(state)) return 'admin-badge--warn';
  return '';
}

function openReturn(item) {
  returnItem.value = item;
  returnError.value = '';
  returnDialogOpen.value = true;
}

function idempotencyKey() {
  return globalThis.crypto?.randomUUID?.() || `return-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

async function processReturn(values) {
  processingReturn.value = true;
  returnError.value = '';
  inventorySuccess.value = '';
  try {
    await api.post('/admin/inventory/returns', {
      ...values,
      idempotency_key: idempotencyKey(),
    });
    returnDialogOpen.value = false;
    inventorySuccess.value = 'Return processed and item quantities updated.';
    await loadOrder();
  } catch (err) {
    returnError.value = err.response?.data?.message || 'Unable to process return.';
  } finally {
    processingReturn.value = false;
  }
}

async function retryShiprocket() {
  if (!order.value || retryingShiprocket.value) return;
  retryingShiprocket.value = true;
  error.value = '';
  courierSuccess.value = '';
  try {
    const { data } = await api.post(`/admin/orders/${order.value.id}/shiprocket/retry`);
    courierSuccess.value = data.message || 'Shiprocket fulfillment queued.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to queue Shiprocket fulfillment.';
  } finally {
    retryingShiprocket.value = false;
  }
}

async function syncShiprocket() {
  if (!order.value || syncingShiprocket.value) return;
  syncingShiprocket.value = true;
  error.value = '';
  courierSuccess.value = '';
  try {
    const { data } = await api.post(`/admin/orders/${order.value.id}/shiprocket/sync`);
    courierSuccess.value = data.message || 'Shiprocket tracking sync queued.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to queue Shiprocket tracking sync.';
  } finally {
    syncingShiprocket.value = false;
  }
}

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
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date);
}

function fillCourierForm(source) {
  courierForm.courier_partner = source?.courier_partner || '';
  courierForm.awb_number = source?.awb_number || '';
  courierForm.tracking_number = source?.tracking_number || '';
  courierForm.dispatched_at = toDateInput(source?.dispatched_at);
  courierForm.expected_delivery_at = toDateInput(source?.expected_delivery_at);
}

async function loadOrder() {
  try {
    const { data } = await api.get(`/admin/orders/${route.params.id}`);
    order.value = unwrapData(data);
    status.value = order.value?.status || 'Processing';
    cancellationReason.value = order.value?.cancellation_reason || '';
    fillCourierForm(order.value);
  } catch {
    error.value = 'Order not found.';
  }
}

onMounted(async () => {
  await loadOrder();
  loading.value = false;
});

async function saveStatus() {
  if (!order.value) return;
  saving.value = true;
  error.value = '';
  try {
    const payload = { status: status.value };
    if (status.value === 'Cancelled') {
      payload.cancellation_reason = cancellationReason.value.trim() || 'Admin cancellation';
    }
    const { data } = await api.patch(`/admin/orders/${order.value.id}`, payload);
    order.value = unwrapData(data);
    cancellationReason.value = order.value?.cancellation_reason || '';
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

async function switchToManual() {
  if (!order.value || switchingToManual.value) return;
  switchingToManual.value = true;
  error.value = '';
  courierSuccess.value = '';
  try {
    const { data } = await api.post(`/admin/orders/${order.value.id}/fulfillment/manual`, {
      reason: 'Admin switched fulfillment to manual',
    });
    order.value = unwrapData(data);
    fillCourierForm(order.value);
    switchDialogOpen.value = false;
    courierSuccess.value = 'Order switched to manual fulfillment. Shiprocket IDs were kept for restore.';
  } catch (err) {
    error.value = err.response?.data?.message
      || err.response?.data?.errors?.fulfillment_method?.[0]
      || 'Unable to switch fulfillment method.';
  } finally {
    switchingToManual.value = false;
  }
}

async function restoreToShiprocket() {
  if (!order.value || restoringToShiprocket.value) return;
  restoringToShiprocket.value = true;
  error.value = '';
  courierSuccess.value = '';
  try {
    const { data } = await api.post(`/admin/orders/${order.value.id}/fulfillment/shiprocket`, {
      reason: 'Admin restored fulfillment to Shiprocket',
    });
    order.value = unwrapData(data);
    fillCourierForm(order.value);
    restoreDialogOpen.value = false;
    courierSuccess.value = 'Order restored to Shiprocket. Fulfillment was queued.';
  } catch (err) {
    error.value = err.response?.data?.message
      || err.response?.data?.errors?.fulfillment_method?.[0]
      || 'Unable to restore Shiprocket fulfillment.';
  } finally {
    restoringToShiprocket.value = false;
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

async function markRefunded() {
  if (!order.value?.can_mark_refunded || markingRefunded.value) return;
  markingRefunded.value = true;
  error.value = '';
  try {
    const { data } = await api.patch(`/admin/orders/${order.value.id}`, {
      payment_status: 'refunded',
    });
    order.value = unwrapData(data);
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to mark refunded.';
  } finally {
    markingRefunded.value = false;
  }
}

async function resendConfirmationEmail() {
  if (!order.value?.can_resend_confirmation_email || resendingConfirmation.value) return;
  resendingConfirmation.value = true;
  error.value = '';
  inventorySuccess.value = '';
  try {
    const force = Boolean(order.value.order_confirmation_emailed_at);
    const { data } = await api.post(`/admin/orders/${order.value.id}/emails/confirmation`, {
      force,
    });
    order.value = unwrapData(data);
    inventorySuccess.value = force
      ? 'Confirmation email resent.'
      : 'Confirmation email sent.';
  } catch (err) {
    error.value = err.response?.data?.message
      || err.response?.data?.errors?.email?.[0]
      || 'Unable to send confirmation email.';
  } finally {
    resendingConfirmation.value = false;
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
          <span v-if="order.inventory_status" class="admin-badge admin-badge--info">
            Inventory {{ String(order.inventory_status).replaceAll('_', ' ') }}
          </span>
          <span v-if="order.paid_at" class="admin-muted">
            Paid {{ new Date(order.paid_at).toLocaleString() }}
          </span>
        </div>
      </header>

      <p v-if="error" class="form-error">{{ error }}</p>
      <p v-if="courierSuccess" class="form-success">{{ courierSuccess }}</p>
      <p v-if="inventorySuccess" class="form-success">{{ inventorySuccess }}</p>

      <section
        v-if="order.payment_expires_at || order.cancel_requested_at || order.cancelled_at || order.cancellation_reason"
        class="admin-order-detail__section admin-order-inventory-timeline"
      >
        <h3>Payment &amp; cancellation timing</h3>
        <div class="admin-order-inventory-timeline__items">
          <span v-if="order.payment_expires_at">
            Payment expires <strong>{{ new Date(order.payment_expires_at).toLocaleString() }}</strong>
          </span>
          <span v-if="order.cancel_requested_at">
            Cancellation requested <strong>{{ new Date(order.cancel_requested_at).toLocaleString() }}</strong>
          </span>
          <span v-if="order.cancelled_at">
            Cancelled <strong>{{ new Date(order.cancelled_at).toLocaleString() }}</strong>
          </span>
          <span v-if="order.cancellation_reason">
            Reason <strong>{{ order.cancellation_reason }}</strong>
          </span>
        </div>
      </section>

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
          <label v-if="status === 'Cancelled'" class="admin-order-detail__status-field">
            Cancellation reason
            <input v-model="cancellationReason" maxlength="500" />
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
          <AppButton
            v-if="order.can_mark_refunded"
            type="button"
            variant="secondary"
            :disabled="markingRefunded"
            @click="markRefunded"
          >
            {{ markingRefunded ? 'Saving…' : 'Mark refunded' }}
          </AppButton>
          <AppButton
            v-if="order.can_resend_confirmation_email"
            type="button"
            variant="ghost"
            :disabled="resendingConfirmation"
            @click="resendConfirmationEmail"
          >
            {{
              resendingConfirmation
                ? 'Sending…'
                : order.order_confirmation_emailed_at
                  ? 'Resend confirmation email'
                  : 'Send confirmation email'
            }}
          </AppButton>
        </div>
        <p v-if="order.order_confirmation_emailed_at" class="admin-muted">
          Confirmation emailed {{ formatDateTime(order.order_confirmation_emailed_at) }}
        </p>
      </section>

      <section class="admin-order-detail__section">
        <h3>Items</h3>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Allocation</th>
                <th>Shipped / returned</th>
                <th>Unit</th>
                <th>Line</th>
                <th />
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
                <td data-label="Allocation">
                  <span class="admin-badge" :class="reservationBadge(item)">
                    {{ reservationLabel(item) }}
                  </span>
                  <div v-if="item.inventory_reservation" class="admin-muted">
                    {{ item.inventory_reservation.quantity }} allocated
                  </div>
                </td>
                <td data-label="Shipped / returned">
                  <span class="admin-order-item__quantities">
                    {{ item.shipped_quantity || 0 }} shipped
                    <small>{{ item.returned_quantity || 0 }} returned · {{ item.restocked_quantity || 0 }} restocked</small>
                  </span>
                </td>
                <td data-label="Unit">{{ formatCurrency(item.unit_price) }}</td>
                <td data-label="Line">{{ formatCurrency(item.line_total) }}</td>
                <td data-label="Actions">
                  <div class="admin-actions">
                    <AppButton
                      v-if="returnableQuantity(item) > 0"
                      type="button"
                      variant="secondary"
                      size="sm"
                      @click="openReturn(item)"
                    >
                      Return
                    </AppButton>
                    <AppButton
                      v-if="item.product_id"
                      type="button"
                      variant="ghost"
                      size="sm"
                      :to="`/admin/inventory?search=${encodeURIComponent(item.sku || item.name)}`"
                    >
                      Inventory
                    </AppButton>
                  </div>
                </td>
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
            <dd>
              <a v-if="customerEmail !== '—'" :href="emailHref(customerEmail)">{{ customerEmail }}</a>
              <template v-else>—</template>
            </dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Phone</dt>
            <dd>
              <a v-if="shipping.phone" :href="phoneHref(shipping.phone)">{{ shipping.phone }}</a>
              <template v-else>—</template>
            </dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Address</dt>
            <dd>{{ shipping.address || '—' }}</dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>City / District / State / PIN</dt>
            <dd>{{ cityLine }}</dd>
          </div>
        </dl>
      </section>

      <section v-if="shiprocketManaged" class="admin-order-detail__section">
        <div class="admin-toolbar">
          <div>
            <h3>Fulfillment</h3>
            <span class="admin-badge admin-badge--info">{{ fulfillmentLabel }}</span>
          </div>
          <div class="admin-order-detail__actions">
            <AppButton
              type="button"
              variant="secondary"
              :disabled="retryingShiprocket || order.status === 'Cancelled'"
              @click="retryShiprocket"
            >
              {{ retryingShiprocket ? 'Queuing…' : order.shiprocket ? 'Retry fulfillment' : 'Send to Shiprocket' }}
            </AppButton>
            <AppButton
              v-if="order.shiprocket?.awb_code"
              type="button"
              variant="ghost"
              :disabled="syncingShiprocket"
              @click="syncShiprocket"
            >
              {{ syncingShiprocket ? 'Queuing…' : 'Sync tracking' }}
            </AppButton>
            <AppButton
              v-if="order.can_switch_to_manual"
              type="button"
              variant="ghost"
              :disabled="switchingToManual"
              @click="switchDialogOpen = true"
            >
              Switch to manual
            </AppButton>
          </div>
        </div>
        <dl v-if="order.shiprocket" class="admin-order-address-card">
          <div class="admin-order-address-card__row">
            <dt>Integration Sync Status</dt>
            <dd>
              <span
                class="admin-badge"
                :class="{
                  'admin-badge--danger': order.shiprocket.sync_status === 'failed' || order.shiprocket.sync_status === 'cancel_failed',
                  'admin-badge--success': order.shiprocket.sync_status === 'completed',
                  'admin-badge--info': ['processing', 'pending', 'awaiting_awb'].includes(order.shiprocket.sync_status),
                }"
              >
                {{ order.shiprocket.sync_status || '—' }}
              </span>
            </dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Fulfillment stage</dt>
            <dd>{{ order.shiprocket.stage || '—' }}</dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Shiprocket IDs</dt>
            <dd>
              Order {{ order.shiprocket.shiprocket_order_id || '—' }} · Shipment
              {{ order.shiprocket.shipment_id || '—' }}
            </dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Courier / AWB</dt>
            <dd>
              {{ order.shiprocket.courier_name || '—' }} · {{ order.shiprocket.awb_code || '—' }}
            </dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Pickup Status</dt>
            <dd>
              {{ order.shiprocket.pickup_status || 'Not scheduled' }}
              <template v-if="order.shiprocket.pickup_scheduled_at">
                · {{ formatDateTime(order.shiprocket.pickup_scheduled_at) }}
              </template>
            </dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Shipment Status</dt>
            <dd>
              <template v-if="order.shiprocket.tracking_url">
                <a :href="order.shiprocket.tracking_url" target="_blank" rel="noopener noreferrer">
                  {{ order.shiprocket.shipment_status || 'Track shipment' }}
                </a>
              </template>
              <template v-else>
                {{ order.shiprocket.shipment_status || 'Awaiting first sync' }}
              </template>
            </dd>
          </div>
          <div
            v-if="order.shiprocket.tracking_history?.length"
            class="admin-order-address-card__row"
          >
            <dt>Tracking history</dt>
            <dd>
              <ol class="admin-order-fulfillment-events">
                <li
                  v-for="event in order.shiprocket.tracking_history"
                  :key="event.id"
                >
                  <div>
                    <strong>{{ event.status || 'Update' }}</strong>
                    <span>{{ event.location || event.source }}</span>
                  </div>
                  <time v-if="event.occurred_at" :datetime="event.occurred_at">
                    {{ formatDateTime(event.occurred_at) }}
                  </time>
                </li>
              </ol>
            </dd>
          </div>
          <div v-if="order.shiprocket.last_synced_at" class="admin-order-address-card__row">
            <dt>Last synced</dt>
            <dd>{{ formatDateTime(order.shiprocket.last_synced_at) }}</dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Dispatched</dt>
            <dd>{{ formatDateTime(order.dispatched_at) }}</dd>
          </div>
          <div class="admin-order-address-card__row">
            <dt>Expected delivery</dt>
            <dd>{{ formatDateTime(order.expected_delivery_at) }}</dd>
          </div>
          <div
            v-if="order.shiprocket.last_error && (order.shiprocket.sync_status === 'failed' || order.shiprocket.sync_status === 'cancel_failed')"
            class="admin-order-address-card__row"
          >
            <dt>Last error</dt>
            <dd class="form-error">{{ order.shiprocket.last_error }}</dd>
          </div>
          <div
            v-else-if="order.shiprocket.last_error && order.shiprocket.sync_status === 'awaiting_awb'"
            class="admin-order-address-card__row"
          >
            <dt>Status note</dt>
            <dd class="admin-muted">{{ order.shiprocket.last_error }}</dd>
          </div>
        </dl>
        <p v-else class="admin-muted">
          No Shiprocket shipment has been created yet. Automatic fulfillment runs after order
          confirmation when the integration is enabled.
        </p>
        <p v-if="!order.can_switch_to_manual && order.shiprocket" class="admin-muted">
          Manual switching is unavailable after AWB assignment, pickup scheduling, or courier handoff.
        </p>
      </section>

      <section v-else class="admin-order-detail__section">
        <div class="admin-toolbar">
          <div>
            <h3>Manual courier / tracking</h3>
            <span class="admin-badge">{{ fulfillmentLabel }}</span>
          </div>
          <div class="admin-order-detail__actions">
            <AppButton
              v-if="order.can_restore_to_shiprocket"
              type="button"
              variant="secondary"
              :disabled="restoringToShiprocket"
              @click="restoreDialogOpen = true"
            >
              Restore to Shiprocket
            </AppButton>
          </div>
        </div>
        <p v-if="order.can_restore_to_shiprocket && order.shiprocket" class="admin-muted">
          Prior Shiprocket order {{ order.shiprocket.shiprocket_order_id }} · shipment
          {{ order.shiprocket.shipment_id }} can be resumed without creating a duplicate.
        </p>
        <form class="admin-form" @submit.prevent="saveCourier">
          <FormField
            v-model="courierForm.courier_partner"
            label="Courier partner"
          />
          <FormField
            v-model="courierForm.awb_number"
            label="AWB number"
          />
          <FormField
            v-model="courierForm.tracking_number"
            label="Tracking number"
          />
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

        <details v-if="order.shiprocket" class="admin-order-fulfillment-history">
          <summary>Previous Shiprocket shipment</summary>
          <dl class="admin-order-address-card">
            <div class="admin-order-address-card__row">
              <dt>Remote IDs</dt>
              <dd>
                Order {{ order.shiprocket.shiprocket_order_id || '—' }} · Shipment
                {{ order.shiprocket.shipment_id || '—' }}
              </dd>
            </div>
            <div class="admin-order-address-card__row">
              <dt>Final stage</dt>
              <dd>{{ order.shiprocket.stage || '—' }}</dd>
            </div>
            <div class="admin-order-address-card__row">
              <dt>Integration Sync Status</dt>
              <dd>{{ order.shiprocket.sync_status || '—' }}</dd>
            </div>
            <div class="admin-order-address-card__row">
              <dt>Cancelled</dt>
              <dd>{{ formatDateTime(order.shiprocket.cancelled_at) }}</dd>
            </div>
          </dl>
        </details>
      </section>

      <section v-if="order.fulfillment_events?.length" class="admin-order-detail__section">
        <h3>Fulfillment history</h3>
        <ol class="admin-order-fulfillment-events">
          <li v-for="event in order.fulfillment_events" :key="event.id">
            <div>
              <strong>{{ String(event.event_type || '').replaceAll('_', ' ') }}</strong>
              <span>{{ event.reason || event.provider_status || event.source }}</span>
            </div>
            <time :datetime="event.occurred_at || event.created_at">
              {{ formatDateTime(event.occurred_at || event.created_at) }}
            </time>
          </li>
        </ol>
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

    <InventoryReturnDialog
      :open="returnDialogOpen"
      :item="returnItem"
      :busy="processingReturn"
      :error="returnError"
      @close="returnDialogOpen = false"
      @submit="processReturn"
    />
    <ConfirmDialog
      v-model:open="switchDialogOpen"
      title="Switch to manual fulfillment?"
      message="Shiprocket syncing will stop locally. The remote Shiprocket order is kept so you can restore later. Existing shipment history remains for audit."
      confirm-label="Switch to manual"
      busy-label="Switching…"
      danger
      :busy="switchingToManual"
      :close-on-confirm="false"
      @confirm="switchToManual"
    />
    <ConfirmDialog
      v-model:open="restoreDialogOpen"
      title="Restore Shiprocket fulfillment?"
      message="This order will return to Shiprocket ownership and queue fulfillment using the existing Shiprocket order and shipment IDs."
      confirm-label="Restore to Shiprocket"
      busy-label="Restoring…"
      :busy="restoringToShiprocket"
      :close-on-confirm="false"
      @confirm="restoreToShiprocket"
    />
  </div>
</template>
