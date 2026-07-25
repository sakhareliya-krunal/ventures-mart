<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '@/services/api';
import { formatCurrency, unwrapData } from '@/utils/format';

const loading = ref(true);
const stats = ref({
  orders_count: 0,
  products_count: 0,
  users_count: 0,
  contact_messages_count: 0,
  revenue_total: 0,
  recent_orders: [],
});

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/stats');
    stats.value = {
      ...data,
      recent_orders: unwrapData(data.recent_orders) || data.recent_orders || [],
    };
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div>
    <div v-if="loading" class="admin-panel">Loading dashboard…</div>
    <template v-else>
      <div class="admin-stats">
        <div class="admin-stat">
          <span>Orders</span>
          <strong>{{ stats.orders_count }}</strong>
        </div>
        <div class="admin-stat">
          <span>Products</span>
          <strong>{{ stats.products_count }}</strong>
        </div>
        <div class="admin-stat">
          <span>Customers</span>
          <strong>{{ stats.users_count }}</strong>
        </div>
        <div class="admin-stat">
          <span>Messages</span>
          <strong>{{ stats.contact_messages_count }}</strong>
        </div>
        <div class="admin-stat">
          <span>Revenue</span>
          <strong>{{ formatCurrency(stats.revenue_total) }}</strong>
        </div>
      </div>

      <div class="admin-panel">
        <div class="admin-toolbar">
          <h2>Recent orders</h2>
          <RouterLink class="button button--secondary button--sm" to="/admin/orders">
            View all
          </RouterLink>
        </div>
        <div v-if="stats.recent_orders.length" class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in stats.recent_orders" :key="order.id">
                <td>
                  <RouterLink :to="`/admin/orders/${order.id}`">{{ order.number }}</RouterLink>
                </td>
                <td>{{ order.address?.full_name || order.user?.name || '—' }}</td>
                <td><span class="admin-badge">{{ order.status }}</span></td>
                <td>{{ formatCurrency(order.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="admin-empty">No orders yet.</p>
      </div>
    </template>
  </div>
</template>
