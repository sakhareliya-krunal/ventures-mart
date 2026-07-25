<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const loading = ref(true);
const users = ref([]);
const search = ref('');
const error = ref('');
const confirmOpen = ref(false);
const pendingUser = ref(null);

const confirmTitle = computed(() =>
  pendingUser.value?.is_admin ? 'Remove admin access?' : 'Grant admin access?',
);

const confirmMessage = computed(() => {
  if (!pendingUser.value) return '';
  return pendingUser.value.is_admin
    ? `${pendingUser.value.name} will lose access to the admin panel.`
    : `${pendingUser.value.name} will be able to access the admin panel.`;
});

const confirmLabel = computed(() =>
  pendingUser.value?.is_admin ? 'Remove admin' : 'Make admin',
);

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/admin/users', {
      params: { search: search.value || undefined },
    });
    users.value = unwrapData(data) || [];
  } finally {
    loading.value = false;
  }
}

function requestToggleAdmin(user) {
  pendingUser.value = user;
  confirmOpen.value = true;
}

async function toggleAdmin() {
  if (!pendingUser.value) return;
  error.value = '';
  try {
    const { data } = await api.patch(`/admin/users/${pendingUser.value.id}`, {
      is_admin: !pendingUser.value.is_admin,
    });
    const updated = unwrapData(data);
    users.value = users.value.map((item) => (item.id === updated.id ? updated : item));
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to update user.';
  } finally {
    pendingUser.value = null;
  }
}

onMounted(load);
watch(search, load);
</script>

<template>
  <div class="admin-panel">
    <div class="admin-toolbar">
      <h2>Customers</h2>
      <AdminSearchField
        v-model="search"
        placeholder="Search customers…"
        aria-label="Search customers"
      />
    </div>
    <p v-if="error" class="form-error">{{ error }}</p>
    <div v-if="loading" class="admin-muted">Loading…</div>
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id">
            <td>{{ user.name }}</td>
            <td>{{ user.email }}</td>
            <td>
              <span class="admin-badge">{{ user.is_admin ? 'Admin' : 'Customer' }}</span>
            </td>
            <td>
              <AppButton
                type="button"
                variant="secondary"
                size="sm"
                :disabled="user.id === auth.user?.id && user.is_admin"
                @click="requestToggleAdmin(user)"
              >
                {{ user.is_admin ? 'Remove admin' : 'Make admin' }}
              </AppButton>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      :title="confirmTitle"
      :message="confirmMessage"
      :confirm-label="confirmLabel"
      danger
      @confirm="toggleAdmin"
    />
  </div>
</template>
