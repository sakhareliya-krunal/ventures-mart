<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import {
  orderStatusBadgeClass,
  orderStatusLabel,
  paymentStatusBadgeClass,
  paymentStatusLabel,
} from '@/utils/adminBadges';
import { formatCurrency, unwrapData } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const orders = ref([]);
const search = ref('');
const status = ref(typeof route.query.status === 'string' ? route.query.status : '');
const listError = ref('');
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const deleting = ref(false);

const statusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'AwaitingPayment', label: 'Awaiting payment' },
  { value: 'Processing', label: 'Confirmed' },
  { value: 'Packed', label: 'Packed' },
  { value: 'Shipped', label: 'Shipped' },
  { value: 'Delivered', label: 'Delivered' },
  { value: 'Cancelled', label: 'Cancelled' },
];

async function load() {
  loading.value = true;
  listError.value = '';
  try {
    const { data } = await api.get('/admin/orders', {
      params: {
        search: search.value || undefined,
        status: status.value || undefined,
      },
    });
    orders.value = unwrapData(data) || [];
  } finally {
    loading.value = false;
  }
}

function openOrder(order) {
  router.push({ name: 'admin-order-detail', params: { id: order.id } });
}

function requestRemove(id) {
  listError.value = '';
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value || deleting.value) return;
  const id = pendingDeleteId.value;
  deleting.value = true;
  try {
    await api.delete(`/admin/orders/${id}`);
    pendingDeleteId.value = null;
    confirmOpen.value = false;
    await load();
  } catch (err) {
    listError.value = err.response?.data?.message || 'Unable to delete order.';
    pendingDeleteId.value = null;
    confirmOpen.value = false;
  } finally {
    deleting.value = false;
  }
}

onMounted(load);
watch([search, status], load);
</script>

<template>
  <div class="admin-panel">
    <div class="admin-toolbar">
      <h2>All orders</h2>
      <div class="admin-toolbar__filters">
        <AdminSearchField
          v-model="search"
          placeholder="Search order, email, name…"
          aria-label="Search orders"
        />
        <AppSelect
          v-model="status"
          :options="statusOptions"
          placeholder="All statuses"
          aria-label="Filter by status"
        />
      </div>
    </div>

    <p v-if="listError" class="form-error">{{ listError }}</p>
    <LoadingSpinner v-if="loading" page label="Loading orders" />
    <div v-else-if="orders.length" class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Total</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td data-label="Order">
              <RouterLink :to="`/admin/orders/${order.id}`">{{ order.number }}</RouterLink>
            </td>
            <td data-label="Date">
              {{ order.created_at ? new Date(order.created_at).toLocaleString() : '—' }}
            </td>
            <td data-label="Customer">
              {{ order.address?.full_name || order.user?.name || '—' }}
              <div class="admin-muted">{{ order.address?.email }}</div>
            </td>
            <td data-label="Status">
              <div class="admin-status-cell">
                <span class="admin-badge" :class="orderStatusBadgeClass(order.status)">
                  {{ orderStatusLabel(order.status) }}
                </span>
              </div>
            </td>
            <td data-label="Payment">
              <div class="admin-payment-cell">
                <span class="admin-badge" :class="paymentStatusBadgeClass(order.payment_status)">
                  {{ paymentStatusLabel(order.payment_status) }}
                </span>
                <span class="admin-muted">
                  {{ order.payment_method === 'cod' ? 'COD' : (order.payment_method || '—') }}
                </span>
              </div>
            </td>
            <td data-label="Total">{{ formatCurrency(order.total) }}</td>
            <td data-label="Actions">
              <div class="admin-actions">
                <AppButton type="button" variant="secondary" size="sm" @click="openOrder(order)">
                  View
                </AppButton>
                <AppButton type="button" variant="danger" size="sm" @click="requestRemove(order.id)">
                  Delete
                </AppButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="admin-empty">No orders found.</p>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete order?"
      message="This order will be permanently removed. Stock will be restored when applicable."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />
  </div>
</template>
