<script setup>
import { computed, onMounted, ref } from 'vue';
import InventoryReturnDialog from '@/components/admin/InventoryReturnDialog.vue';
import AppButton from '@/components/ui/AppButton.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { apiErrorMessage } from '@/utils/adminProductForm';

const rows = ref([]);
const meta = ref({});
const loading = ref(true);
const processing = ref(false);
const error = ref('');
const success = ref('');
const dialogOpen = ref(false);

const currentPage = computed(() => Number(meta.value.current_page || 1));
const lastPage = computed(() => Number(meta.value.last_page || 1));

function date(value) {
  if (!value) return '—';
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString('en-IN');
}

function idempotencyKey() {
  return globalThis.crypto?.randomUUID?.() || `return-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

async function load(page = 1) {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/admin/inventory/returns', {
      params: { page, per_page: 25 },
      skipErrorToast: true,
    });
    rows.value = data?.data || [];
    meta.value = data?.meta || {};
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load inventory returns.');
  } finally {
    loading.value = false;
  }
}

async function processReturn(values) {
  processing.value = true;
  error.value = '';
  try {
    await api.post('/admin/inventory/returns', {
      ...values,
      idempotency_key: idempotencyKey(),
    });
    dialogOpen.value = false;
    success.value = 'Return processed and inventory updated.';
    await load(1);
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to process return.');
  } finally {
    processing.value = false;
  }
}

onMounted(() => load());
</script>

<template>
  <section class="admin-panel inventory-operations-panel">
    <div class="admin-toolbar inventory-operations-toolbar">
      <div class="inventory-operations-toolbar__copy">
        <h2>Returns</h2>
        <p class="admin-muted">Receive returned order items and record their disposition.</p>
      </div>
      <AppButton class="inventory-operations-toolbar__action" type="button" @click="dialogOpen = true">
        Process return
      </AppButton>
    </div>

    <p v-if="success" class="form-success" role="status">{{ success }}</p>
    <p v-if="error && !dialogOpen" class="form-error" role="alert">{{ error }}</p>
    <LoadingSpinner v-if="loading" page label="Loading returns" />
    <template v-else>
      <div class="admin-table-wrap inventory-returns-wrap">
        <table class="admin-table inventory-returns-table">
          <thead>
            <tr>
              <th>Processed</th>
              <th>Order / item</th>
              <th>Product</th>
              <th>Quantity</th>
              <th>Disposition</th>
              <th>Status</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in rows" :key="record.uuid" class="inventory-return-row">
              <td data-label="Processed">{{ date(record.processed_at || record.created_at) }}</td>
              <td data-label="Order / item">
                <RouterLink :to="`/admin/orders/${record.order_id}`">Order #{{ record.order_id }}</RouterLink>
                <div class="admin-muted">Item #{{ record.order_item_id }}</div>
              </td>
              <td data-label="Product">#{{ record.product_id }}</td>
              <td data-label="Quantity" class="inventory-number">{{ record.quantity }}</td>
              <td data-label="Disposition">
                <span class="admin-badge admin-badge--info">{{ record.disposition }}</span>
              </td>
              <td data-label="Status">
                <span class="admin-badge admin-badge--ok">{{ record.status }}</span>
              </td>
              <td data-label="Reason">{{ record.reason || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="!rows.length" class="admin-empty">No returns have been processed.</p>
      </div>
      <footer v-if="lastPage > 1" class="inventory-pagination">
        <span>Page {{ currentPage }} of {{ lastPage }}</span>
        <div>
          <AppButton type="button" variant="secondary" size="sm" :disabled="currentPage <= 1" @click="load(currentPage - 1)">
            Previous
          </AppButton>
          <AppButton type="button" variant="secondary" size="sm" :disabled="currentPage >= lastPage" @click="load(currentPage + 1)">
            Next
          </AppButton>
        </div>
      </footer>
    </template>

    <InventoryReturnDialog
      :open="dialogOpen"
      :busy="processing"
      :error="dialogOpen ? error : ''"
      @close="dialogOpen = false"
      @submit="processReturn"
    />
  </section>
</template>
