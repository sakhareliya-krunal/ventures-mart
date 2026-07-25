<script setup>
import { onMounted, ref, watch } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const loading = ref(true);
const addresses = ref([]);
const search = ref('');
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/admin/addresses', {
      params: { search: search.value || undefined },
    });
    addresses.value = unwrapData(data) || [];
  } finally {
    loading.value = false;
  }
}

function requestRemove(id) {
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value) return;
  await api.delete(`/admin/addresses/${pendingDeleteId.value}`);
  pendingDeleteId.value = null;
  await load();
}

onMounted(load);
watch(search, load);
</script>

<template>
  <div class="admin-panel">
    <div class="admin-toolbar">
      <h2>Saved addresses</h2>
      <AdminSearchField
        v-model="search"
        placeholder="Search addresses…"
        aria-label="Search addresses"
      />
    </div>
    <div v-if="loading" class="admin-muted">Loading…</div>
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Customer</th>
            <th>Address</th>
            <th>Default</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="address in addresses" :key="address.id">
            <td>
              <strong>{{ address.user?.name || '—' }}</strong>
              <div class="admin-muted">{{ address.user?.email }}</div>
            </td>
            <td>
              <strong>{{ address.label }}</strong> · {{ address.full_name }}<br />
              {{ address.address }}, {{ address.city }}, {{ address.state }}
              {{ address.postal_code }}
              <div class="admin-muted">{{ address.phone }}</div>
            </td>
            <td>{{ address.is_default ? 'Yes' : 'No' }}</td>
            <td>
              <AppButton type="button" variant="ghost" size="sm" @click="requestRemove(address.id)">
                Delete
              </AppButton>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!addresses.length" class="admin-empty">No addresses saved.</p>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete address?"
      message="This saved address will be permanently removed."
      confirm-label="Delete"
      danger
      @confirm="remove"
    />
  </div>
</template>
