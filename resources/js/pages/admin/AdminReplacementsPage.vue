<script setup>
import AdminDataList from '@/components/admin/AdminDataList.vue';
import AdminListToolbar from '@/components/admin/AdminListToolbar.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminPanel from '@/components/admin/AdminPanel.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { useAdminList } from '@/composables/useAdminList';
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import api from '@/services/api';

const { rows, loading, refreshing, error: fetchError, search, filters, meta, filtered, load, go, reset } = useAdminList('/admin/replacement-requests', {
  filterDefaults: { status: '' },
});
const actionError = ref('');
const success = ref('');
const busyId = ref(null);

const statusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'requested', label: 'Requested' },
  { value: 'under_review', label: 'Under review' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'fulfilled', label: 'Fulfilled' },
];

const statusTone = {
  requested: 'warning',
  under_review: 'brand',
  approved: 'success',
  rejected: 'danger',
  fulfilled: 'success',
};

async function approve(row) {
  busyId.value = row.id;
  actionError.value = '';
  success.value = '';
  try {
    await api.post(`/admin/replacement-requests/${row.id}/approve`);
    success.value = `Replacement approved for ${row.order?.number || 'order'}.`;
    await load();
  } catch (err) {
    actionError.value = err.response?.data?.message || 'Unable to approve replacement.';
  } finally {
    busyId.value = null;
  }
}

async function reject(row) {
  const reason = window.prompt('Rejection reason');
  if (!reason?.trim()) return;
  busyId.value = row.id;
  actionError.value = '';
  success.value = '';
  try {
    await api.post(`/admin/replacement-requests/${row.id}/reject`, {
      rejection_reason: reason.trim(),
    });
    success.value = `Replacement rejected for ${row.order?.number || 'order'}.`;
    await load();
  } catch (err) {
    actionError.value = err.response?.data?.message || 'Unable to reject replacement.';
  } finally {
    busyId.value = null;
  }
}
</script>

<template>
  <AdminPanel>
    <AdminListToolbar
      v-model="search"
      placeholder="Search order or customer…"
      label="Search replacement requests"
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

    <p v-if="success" class="form-success">{{ success }}</p>
    <p v-if="actionError" class="form-error">{{ actionError }}</p>

    <AdminDataList
      :rows="rows"
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
              <td data-label="Status">
                <AdminStatusBadge :label="row.status" :tone="statusTone[row.status] || 'neutral'" />
              </td>
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
  </AdminPanel>
</template>
