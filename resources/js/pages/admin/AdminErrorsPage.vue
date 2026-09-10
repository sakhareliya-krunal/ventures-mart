<script setup>
import AdminDataList from '@/components/admin/AdminDataList.vue';
import AdminDrawer from '@/components/admin/AdminDrawer.vue';
import AdminErrorsDetail from '@/components/admin/AdminErrorsDetail.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminPanel from '@/components/admin/AdminPanel.vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { useAdminList } from '@/composables/useAdminList';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const {
  rows: errors,
  loading,
  refreshing,
  error: fetchError,
  search,
  filters,
  meta,
  filtered,
  load,
  go,
  reset,
} = useAdminList('/admin/errors', {
  filterDefaults: { status: 'unresolved', category: '', level: '' },
  searchParam: 'q',
  searchQueryKey: 'q',
  fixedParams: { sort: 'last_seen_at' },
  skipErrorToast: true,
});

const openCount = computed(() => Number(meta.value.open_count ?? 0));
const isWideViewport = ref(true);
let wideQuery;

const selected = ref(null);
const confirmOpen = ref(false);
const confirmClearOpen = ref(false);
const pendingDeleteUuid = ref(null);
const deleting = ref(false);
const clearing = ref(false);
const updating = ref(false);

const showDesktopDetail = computed(() => Boolean(selected.value && isWideViewport.value));
const showMobileDrawer = computed(() => Boolean(selected.value && !isWideViewport.value));

const layoutClass = computed(() => ({
  'admin-errors-layout': true,
  'admin-errors-layout--split': showDesktopDetail.value,
}));

const statusOptions = [
  { value: 'unresolved', label: 'Unresolved' },
  { value: 'new', label: 'New' },
  { value: 'investigating', label: 'Investigating' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'ignored', label: 'Ignored' },
  { value: 'all', label: 'All' },
];

const categoryOptions = [
  { value: '', label: 'All categories' },
  { value: 'exception', label: 'Exception' },
  { value: 'http', label: 'HTTP' },
  { value: 'job', label: 'Job' },
  { value: 'payment', label: 'Payment' },
  { value: 'api', label: 'API' },
  { value: 'system', label: 'System' },
];

const levelOptions = [
  { value: '', label: 'All levels' },
  { value: 'error', label: 'Error' },
  { value: 'critical', label: 'Critical' },
  { value: 'warning', label: 'Warning' },
];

function syncViewportWidth(event) {
  isWideViewport.value = event.matches;
  if (isWideViewport.value && selected.value) {
    // keep selection when switching to desktop
  }
}

onMounted(() => {
  wideQuery = window.matchMedia('(min-width: 961px)');
  isWideViewport.value = wideQuery.matches;
  wideQuery.addEventListener('change', syncViewportWidth);
});

onBeforeUnmount(() => {
  wideQuery?.removeEventListener('change', syncViewportWidth);
});

async function openError(row) {
  const { data } = await api.get(`/admin/errors/${row.uuid}`);
  selected.value = unwrapData(data) || data.data;
}

function closeDetail() {
  selected.value = null;
}

async function setStatus(nextStatus) {
  if (!selected.value || updating.value) return;
  updating.value = true;
  try {
    const { data } = await api.patch(`/admin/errors/${selected.value.uuid}`, {
      status: nextStatus,
    });
    selected.value = unwrapData(data) || data.data;
    await load();
  } finally {
    updating.value = false;
  }
}

function requestRemove(uuid) {
  pendingDeleteUuid.value = uuid;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteUuid.value || deleting.value) return;
  const uuid = pendingDeleteUuid.value;
  deleting.value = true;
  try {
    await api.delete(`/admin/errors/${uuid}`);
    if (selected.value?.uuid === uuid) {
      selected.value = null;
    }
    pendingDeleteUuid.value = null;
    confirmOpen.value = false;
    await load();
  } finally {
    deleting.value = false;
  }
}

async function clearResolved() {
  if (clearing.value) return;
  clearing.value = true;
  try {
    await api.delete('/admin/errors', { params: { scope: 'resolved' } });
    if (selected.value?.status === 'resolved') {
      selected.value = null;
    }
    confirmClearOpen.value = false;
    await load();
  } finally {
    clearing.value = false;
  }
}

function shortPath(file) {
  if (!file) return '—';
  const parts = String(file).replace(/\\/g, '/').split('/');
  return parts.slice(-2).join('/');
}

function formatWhen(value) {
  return value ? new Date(value).toLocaleString() : '—';
}

const statusTone = {
  new: 'warning',
  investigating: 'brand',
  resolved: 'success',
  ignored: 'neutral',
};
</script>

<template>
  <div :class="layoutClass">
    <AdminPanel class="admin-errors-list">
      <p class="admin-muted admin-errors-summary">{{ openCount }} unresolved · grouped by fingerprint</p>

      <div class="admin-errors-toolbar">
        <AdminSearchField
          v-model="search"
          class="admin-errors-toolbar__search"
          placeholder="Search message, route, id…"
          aria-label="Search errors"
        />
        <div class="admin-errors-toolbar__filters">
          <AppSelect v-model="filters.status" :options="statusOptions" aria-label="Filter by status" />
          <AppSelect
            v-model="filters.category"
            :options="categoryOptions"
            placeholder="All categories"
            aria-label="Filter by category"
          />
          <AppSelect
            v-model="filters.level"
            :options="levelOptions"
            placeholder="All levels"
            aria-label="Filter by level"
          />
        </div>
        <div class="admin-errors-toolbar__actions">
          <AppButton v-if="filtered" type="button" variant="ghost" size="sm" @click="reset">
            Clear filters
          </AppButton>
          <AppButton type="button" variant="ghost" size="sm" @click="confirmClearOpen = true">
            Clear resolved
          </AppButton>
          <span v-if="meta.total != null" class="admin-errors-toolbar__count">
            {{ meta.total.toLocaleString('en-IN') }} results
          </span>
        </div>
      </div>

      <AdminDataList
        :rows="errors"
        :loading="loading"
        :refreshing="refreshing"
        :error="fetchError"
        :searching="filtered"
        @retry="load"
        @reset="reset"
      >
        <div class="admin-table-wrap admin-errors-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Last seen</th>
                <th>Status</th>
                <th>Message</th>
                <th>Count</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in errors" :key="row.uuid">
                <td data-label="Last seen">{{ formatWhen(row.last_seen_at || row.created_at) }}</td>
                <td data-label="Status">
                  <AdminStatusBadge :label="row.status" :tone="statusTone[row.status] || 'neutral'" />
                  <div class="admin-muted">{{ row.category }} · {{ row.level }}</div>
                </td>
                <td data-label="Message">
                  <button type="button" class="linkish" @click="openError(row)">
                    <strong>{{ row.message }}</strong>
                  </button>
                  <div class="admin-muted">
                    {{ row.exception_class || 'Log' }}
                    <template v-if="row.route || row.url">
                      · {{ row.route || row.url }}
                    </template>
                  </div>
                </td>
                <td data-label="Count">
                  <span class="admin-badge">×{{ row.occurrence_count || 1 }}</span>
                </td>
                <td data-label="Actions">
                  <AppButton type="button" variant="danger" size="sm" @click="requestRemove(row.uuid)">
                    Delete
                  </AppButton>
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

    <AdminPanel v-if="showDesktopDetail" class="admin-message-pane admin-errors-detail-pane">
      <h3>Error detail</h3>
      <AdminErrorsDetail
        v-if="selected"
        :selected="selected"
        :updating="updating"
        :status-tone="statusTone"
        :format-when="formatWhen"
        :short-path="shortPath"
        @set-status="setStatus"
        @remove="requestRemove"
      />
    </AdminPanel>

    <AdminDrawer
      :open="showMobileDrawer"
      title="Error detail"
      :busy="updating"
      @update:open="(open) => { if (!open) closeDetail(); }"
      @close="closeDetail"
    >
      <AdminErrorsDetail
        v-if="selected"
        :selected="selected"
        :updating="updating"
        :status-tone="statusTone"
        :format-when="formatWhen"
        :short-path="shortPath"
        @set-status="setStatus"
        @remove="requestRemove"
      />
    </AdminDrawer>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete error?"
      message="This error group will be permanently removed."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />

    <ConfirmDialog
      v-model:open="confirmClearOpen"
      title="Clear resolved errors?"
      message="All resolved error groups will be permanently removed."
      confirm-label="Clear"
      busy-label="Clearing…"
      :busy="clearing"
      :close-on-confirm="false"
      danger
      @confirm="clearResolved"
    />
  </div>
</template>

<style scoped>
.admin-errors-summary {
  margin: 0 0 12px;
}

.linkish {
  background: none;
  border: 0;
  padding: 0;
  text-align: left;
  cursor: pointer;
  color: inherit;
  font: inherit;
  max-width: 100%;
  overflow-wrap: anywhere;
}
</style>
