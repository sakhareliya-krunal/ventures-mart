<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import api from '@/services/api';
import { formatCurrency, unwrapData } from '@/utils/format';

const loading = ref(true);
const orders = ref([]);
const search = ref('');
const status = ref('');

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

    <div v-if="loading" class="admin-muted">Loading…</div>
    <div v-else-if="orders.length" class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>
              <RouterLink :to="`/admin/orders/${order.id}`">{{ order.number }}</RouterLink>
            </td>
            <td>{{ order.created_at ? new Date(order.created_at).toLocaleString() : '—' }}</td>
            <td>
              {{ order.address?.full_name || order.user?.name || '—' }}
              <div class="admin-muted">{{ order.address?.email }}</div>
            </td>
            <td><span class="admin-badge">{{ order.status }}</span></td>
            <td>{{ formatCurrency(order.total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="admin-empty">No orders found.</p>
  </div>
</template>
