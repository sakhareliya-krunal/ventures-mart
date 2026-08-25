<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const loading = ref(true);
const error = ref('');
const success = ref('');
const rows = ref([]);
const busyId = ref(null);
const filters = ref({
  search: '',
  status: '',
});

const statusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'requested', label: 'Requested' },
  { value: 'under_review', label: 'Under review' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'fulfilled', label: 'Fulfilled' },
];

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/admin/replacement-requests', {
      params: {
        search: filters.value.search || undefined,
        status: filters.value.status || undefined,
      },
    });
    rows.value = unwrapData(data) || data.data || [];
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to load replacement requests.';
    rows.value = [];
  } finally {
    loading.value = false;
  }
}

async function approve(row) {
  busyId.value = row.id;
  error.value = '';
  success.value = '';
  try {
    await api.post(`/admin/replacement-requests/${row.id}/approve`);
    success.value = `Replacement approved for ${row.order?.number || 'order'}.`;
    await load();
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to approve replacement.';
  } finally {
    busyId.value = null;
  }
}

async function reject(row) {
  const reason = window.prompt('Rejection reason');
  if (!reason?.trim()) return;
  busyId.value = row.id;
  error.value = '';
  success.value = '';
  try {
    await api.post(`/admin/replacement-requests/${row.id}/reject`, {
      rejection_reason: reason.trim(),
    });
    success.value = `Replacement rejected for ${row.order?.number || 'order'}.`;
    await load();
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to reject replacement.';
  } finally {
    busyId.value = null;
  }
}

watch(filters, () => load(), { deep: true });
onMounted(load);
</script>

<template>
  <div class="admin-panel">
    <div class="admin-toolbar">
      <div>
        <h2>Replacement requests</h2>
        <p class="admin-muted">Review customer replacement requests and fulfill approved cases.</p>
      </div>
    </div>

    <div class="admin-toolbar inventory-toolbar">
      <div class="admin-toolbar__filters inventory-toolbar__filters">
        <AdminSearchField
          v-model="filters.search"
          placeholder="Search order or customer…"
          aria-label="Search replacement requests"
        />
        <AppSelect
          v-model="filters.status"
          :options="statusOptions"
          placeholder="All statuses"
          aria-label="Filter by status"
        />
      </div>
    </div>

    <p v-if="success" class="form-success">{{ success }}</p>
    <p v-if="error" class="form-error">{{ error }}</p>
    <LoadingSpinner v-if="loading" page label="Loading replacements" />
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Requested</th>
            <th>Replacement</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td data-label="Order">
              <RouterLink v-if="row.order?.id" :to="`/admin/orders/${row.order.id}`">
                {{ row.order.number }}
              </RouterLink>
              <div class="admin-muted">{{ row.order?.full_name || row.customer?.name }}</div>
            </td>
            <td data-label="Reason">{{ row.reason }}</td>
            <td data-label="Status">{{ row.status }}</td>
            <td data-label="Requested">
              {{ row.requested_at ? new Date(row.requested_at).toLocaleString() : '—' }}
            </td>
            <td data-label="Replacement">
              <RouterLink
                v-if="row.replacement_order?.id"
                :to="`/admin/orders/${row.replacement_order.id}`"
              >
                {{ row.replacement_order.number }}
              </RouterLink>
              <span v-else>—</span>
            </td>
            <td data-label="Actions">
              <div class="admin-actions">
                <AppButton
                  v-if="['requested', 'under_review'].includes(row.status)"
                  type="button"
                  size="sm"
                  :loading="busyId === row.id"
                  @click="approve(row)"
                >
                  Approve
                </AppButton>
                <AppButton
                  v-if="['requested', 'under_review', 'approved'].includes(row.status)"
                  type="button"
                  size="sm"
                  variant="secondary"
                  :loading="busyId === row.id"
                  @click="reject(row)"
                >
                  Reject
                </AppButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!rows.length" class="admin-empty">No replacement requests yet.</p>
    </div>
  </div>
</template>
