<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import api from '@/services/api';
import { formatCurrency, unwrapData } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const order = ref(null);
const status = ref('Processing');

const statusOptions = [
  { value: 'Processing', label: 'Processing' },
  { value: 'Shipped', label: 'Shipped' },
  { value: 'Delivered', label: 'Delivered' },
  { value: 'Cancelled', label: 'Cancelled' },
];

const items = computed(() => order.value?.items || []);

onMounted(async () => {
  try {
    const { data } = await api.get(`/admin/orders/${route.params.id}`);
    order.value = unwrapData(data);
    status.value = order.value?.status || 'Processing';
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
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to update status.';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div>
    <div class="admin-toolbar">
      <AppButton type="button" variant="ghost" @click="router.push('/admin/orders')">
        ← Back to orders
      </AppButton>
    </div>

    <div v-if="loading" class="admin-panel">Loading…</div>
    <div v-else-if="!order" class="admin-panel">{{ error || 'Order not found.' }}</div>
    <div v-else class="admin-detail-grid">
      <div class="admin-panel">
        <h2>Order {{ order.number }}</h2>
        <p class="admin-muted">
          Placed {{ order.created_at ? new Date(order.created_at).toLocaleString() : '—' }}
        </p>
        <p v-if="error" class="form-error">{{ error }}</p>

        <div class="admin-form" style="margin: 1rem 0">
          <label>
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
        </div>


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
                <td>
                  <strong>{{ item.name }}</strong>
                  <div class="admin-muted">{{ item.sku }}</div>
                </td>
                <td>{{ item.quantity }}</td>
                <td>{{ formatCurrency(item.unit_price) }}</td>
                <td>{{ formatCurrency(item.line_total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div>
        <div class="admin-panel">
          <h3>Customer</h3>
          <p>
            <strong>{{ order.user?.name || order.address?.full_name }}</strong><br />
            {{ order.user?.email || order.address?.email }}
          </p>
        </div>
        <div class="admin-panel">
          <h3>Shipping address</h3>
          <p>
            {{ order.address?.full_name }}<br />
            {{ order.address?.phone }}<br />
            {{ order.address?.address }}<br />
            {{ order.address?.city }}, {{ order.address?.state }} {{ order.address?.postal_code }}
          </p>
        </div>
        <div class="admin-panel">
          <h3>Totals</h3>
          <p>Subtotal: {{ formatCurrency(order.subtotal) }}</p>
          <p>Shipping: {{ formatCurrency(order.shipping) }}</p>
          <p>Tax: {{ formatCurrency(order.tax) }}</p>
          <p><strong>Total: {{ formatCurrency(order.total) }}</strong></p>
        </div>
      </div>
    </div>
  </div>
</template>
