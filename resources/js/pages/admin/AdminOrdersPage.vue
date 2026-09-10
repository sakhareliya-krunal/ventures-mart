<script setup>
import AdminDataList from '@/components/admin/AdminDataList.vue';
import AdminListToolbar from '@/components/admin/AdminListToolbar.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminPanel from '@/components/admin/AdminPanel.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { useAdminList } from '@/composables/useAdminList';
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import api from '@/services/api';
import {
  badgeClassToTone,
  orderStatusBadgeClass,
  orderStatusLabel,
  paymentStatusBadgeClass,
  paymentStatusLabel,
} from '@/utils/adminBadges';
import { emailHref } from '@/utils/contactLinks';
import { formatCurrency } from '@/utils/format';

const router = useRouter();
const { rows: orders, loading, refreshing, error: fetchError, search, filters, meta, filtered, load, go, reset } = useAdminList('/admin/orders', {
  filterDefaults: { status: '' },
  perPage: 10,
});
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
</script>

<template>
  <AdminPanel>
    <AdminListToolbar
      v-model="search"
      placeholder="Search order, email, name…"
      label="Search orders"
      :total="meta.total"
      :filtered="filtered"
      @reset="reset"
    >
      <AppSelect
        v-model="filters.status"
        :options="statusOptions"
        placeholder="All statuses"
        aria-label="Filter by status"
      />
    </AdminListToolbar>

    <p v-if="listError" class="form-error">{{ listError }}</p>
    <AdminDataList
      :rows="orders"
      :loading="loading"
      :refreshing="refreshing"
      :error="fetchError"
      :searching="filtered"
      @retry="load"
      @reset="reset"
    >
      <div class="admin-table-wrap">
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
                <div v-if="order.address?.email" class="admin-muted">
                  <a :href="emailHref(order.address.email)">{{ order.address.email }}</a>
                </div>
              </td>
              <td data-label="Status">
                <AdminStatusBadge
                  :label="orderStatusLabel(order.status)"
                  :tone="badgeClassToTone(orderStatusBadgeClass(order.status))"
                />
              </td>
              <td data-label="Payment">
                <div class="admin-payment-cell">
                  <AdminStatusBadge
                    :label="paymentStatusLabel(order.payment_status)"
                    :tone="badgeClassToTone(paymentStatusBadgeClass(order.payment_status))"
                  />
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
                  <AppButton
                    v-if="order.can_delete"
                    type="button"
                    variant="danger"
                    size="sm"
                    @click="requestRemove(order.id)"
                  >
                    Delete
                  </AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </AdminDataList>
    <AdminPagination
      :page="meta.current_page"
      :last-page="meta.last_page"
      :total="meta.total"
      :from="meta.from || 0"
      :to="meta.to || 0"
      @page="go"
    />

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
  </AdminPanel>
</template>
