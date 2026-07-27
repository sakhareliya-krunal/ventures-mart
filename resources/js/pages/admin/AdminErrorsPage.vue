<script setup>
import { onMounted, ref, watch } from 'vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const loading = ref(true);
const errors = ref([]);
const selected = ref(null);
const openCount = ref(0);
const search = ref('');
const status = ref('unresolved');
const category = ref('');
const level = ref('');
const confirmOpen = ref(false);
const confirmClearOpen = ref(false);
const pendingDeleteUuid = ref(null);
const deleting = ref(false);
const clearing = ref(false);
const updating = ref(false);

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

async function load({ silent = false } = {}) {
  if (!silent) loading.value = true;
  try {
    const { data } = await api.get('/admin/errors', {
      params: {
        status: status.value,
        category: category.value || undefined,
        level: level.value || undefined,
        q: search.value || undefined,
        sort: 'last_seen_at',
      },
    });
    errors.value = unwrapData(data) || data.data || [];
    openCount.value = data.meta?.open_count ?? 0;
  } finally {
    if (!silent) loading.value = false;
  }
}

async function openError(row) {
  const { data } = await api.get(`/admin/errors/${row.uuid}`);
  selected.value = unwrapData(data) || data.data;
}

async function setStatus(nextStatus) {
  if (!selected.value || updating.value) return;
  updating.value = true;
  try {
    const { data } = await api.patch(`/admin/errors/${selected.value.uuid}`, {
      status: nextStatus,
    });
    selected.value = unwrapData(data) || data.data;
    await load({ silent: true });
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
    await load({ silent: true });
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
    await load({ silent: true });
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

onMounted(load);
watch([search, status, category, level], () => load({ silent: true }));
</script>

<template>
  <div class="admin-detail-grid">
    <div class="admin-panel">
      <div class="admin-toolbar">
        <div>
          <h2>Error logs</h2>
          <p class="admin-muted">{{ openCount }} unresolved · grouped by fingerprint</p>
        </div>
        <div class="admin-toolbar__filters">
          <AdminSearchField
            v-model="search"
            placeholder="Search message, route, id…"
            aria-label="Search errors"
          />
          <AppSelect v-model="status" :options="statusOptions" aria-label="Filter by status" />
          <AppSelect
            v-model="category"
            :options="categoryOptions"
            placeholder="All categories"
            aria-label="Filter by category"
          />
          <AppSelect
            v-model="level"
            :options="levelOptions"
            placeholder="All levels"
            aria-label="Filter by level"
          />
          <AppButton type="button" variant="ghost" size="sm" @click="confirmClearOpen = true">
            Clear resolved
          </AppButton>
        </div>
      </div>

      <LoadingSpinner v-if="loading" page label="Loading errors" />
      <div v-else class="admin-table-wrap">
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
                <span class="admin-badge" :class="{ 'admin-badge--warn': row.status === 'new' }">
                  {{ row.status }}
                </span>
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
                <AppButton type="button" variant="ghost" size="sm" @click="requestRemove(row.uuid)">
                  Delete
                </AppButton>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!errors.length" class="admin-empty">No errors match these filters.</p>
      </div>
    </div>

    <div class="admin-panel admin-message-pane">
      <h3>Detail</h3>
      <template v-if="selected">
        <p>
          <span class="admin-badge">{{ selected.status }}</span>
          <span class="admin-muted">{{ selected.uuid }}</span>
        </p>
        <p><strong>{{ selected.message }}</strong></p>
        <p class="admin-muted">
          {{ selected.category }} · seen {{ selected.occurrence_count || 1 }}× ·
          last {{ formatWhen(selected.last_seen_at) }}
        </p>
        <p v-if="selected.exception_class" class="admin-muted">{{ selected.exception_class }}</p>
        <p v-if="selected.file" class="admin-muted">
          {{ shortPath(selected.file) }}{{ selected.line ? `:${selected.line}` : '' }}
        </p>
        <p v-if="selected.url" class="admin-muted">{{ selected.method }} {{ selected.url }}</p>
        <p v-if="selected.user" class="admin-muted">
          User {{ selected.user.name }} ({{ selected.user.email }})
        </p>
        <p v-if="selected.ip || selected.user_agent" class="admin-muted">
          {{ selected.ip }}
          <template v-if="selected.user_agent"> · {{ selected.user_agent }}</template>
        </p>

        <div class="error-actions">
          <AppButton
            type="button"
            size="sm"
            :disabled="updating || selected.status === 'investigating'"
            @click="setStatus('investigating')"
          >
            Investigating
          </AppButton>
          <AppButton
            type="button"
            size="sm"
            :disabled="updating || selected.status === 'resolved'"
            @click="setStatus('resolved')"
          >
            Resolve
          </AppButton>
          <AppButton
            type="button"
            variant="ghost"
            size="sm"
            :disabled="updating || selected.status === 'ignored'"
            @click="setStatus('ignored')"
          >
            Ignore
          </AppButton>
          <AppButton
            type="button"
            variant="ghost"
            size="sm"
            :disabled="updating || selected.status === 'new'"
            @click="setStatus('new')"
          >
            Reopen
          </AppButton>
          <AppButton type="button" variant="ghost" size="sm" @click="requestRemove(selected.uuid)">
            Delete
          </AppButton>
        </div>

        <h4>Request</h4>
        <pre class="error-pre">{{ JSON.stringify(selected.request || {}, null, 2) }}</pre>

        <h4>Context</h4>
        <pre class="error-pre">{{ JSON.stringify(selected.context || {}, null, 2) }}</pre>

        <h4>Stack trace</h4>
        <pre class="error-pre">{{ selected.trace || 'No stack trace.' }}</pre>
      </template>
      <p v-else class="admin-empty">Select an error to inspect it.</p>
    </div>

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
.linkish {
  background: none;
  border: 0;
  padding: 0;
  text-align: left;
  cursor: pointer;
  color: inherit;
  font: inherit;
}

.error-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 0.75rem 0 1rem;
}

.error-pre {
  margin: 0.5rem 0 1rem;
  padding: 0.75rem;
  max-height: 14rem;
  overflow: auto;
  font-size: 0.75rem;
  line-height: 1.4;
  white-space: pre-wrap;
  word-break: break-word;
  background: color-mix(in srgb, var(--admin-border, #d4d4d8) 35%, transparent);
  border-radius: 0.5rem;
}
</style>
