<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { formatCurrency, unwrapData } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const orders = ref([]);
const search = ref('');
const status = ref(typeof route.query.status === 'string' ? route.query.status : '');

const statusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'Processing', label: 'Processing' },
  { value: 'Shipped', label: 'Shipped' },
  { value: 'Delivered', label: 'Delivered' },
  { value: 'Cancelled', label: 'Cancelled' },
];

async function load() {
  loading.value = true;
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

    <LoadingSpinner v-if="loading" page label="Loading orders" />
    <div v-else-if="orders.length" class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Status</th>
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
            <td data-label="Status"><span class="admin-badge">{{ order.status }}</span></td>
            <td data-label="Total">{{ formatCurrency(order.total) }}</td>
            <td data-label="Actions">
              <div class="admin-actions">
                <AppButton type="button" variant="secondary" size="sm" @click="openOrder(order)">
                  View
                </AppButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="admin-empty">No orders found.</p>
  </div>
</template>
