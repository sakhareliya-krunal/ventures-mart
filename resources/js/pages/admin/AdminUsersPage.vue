<script setup>
import { onMounted, ref, watch } from 'vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { emailHref } from '@/utils/contactLinks';
import { unwrapData } from '@/utils/format';

const loading = ref(true);
const users = ref([]);
const search = ref('');
const error = ref('');
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const deleting = ref(false);

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/admin/users', {
      params: {
        role: 'customer',
        search: search.value || undefined,
      },
    });
    users.value = unwrapData(data) || [];
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to load customers.';
    users.value = [];
  } finally {
    loading.value = false;
  }
}

function requestRemove(id) {
  error.value = '';
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value || deleting.value) return;
  const id = pendingDeleteId.value;
  deleting.value = true;
  try {
    await api.delete(`/admin/users/${id}`);
    pendingDeleteId.value = null;
    confirmOpen.value = false;
    await load();
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to delete customer.';
    pendingDeleteId.value = null;
    confirmOpen.value = false;
  } finally {
    deleting.value = false;
  }
}

onMounted(load);
watch(search, load);
</script>

<template>
  <div class="admin-panel">
    <div class="admin-toolbar">
      <h2>Customers</h2>
      <div class="admin-toolbar__filters">
        <AdminSearchField
          v-model="search"
          placeholder="Search customers…"
          aria-label="Search customers"
        />
      </div>
    </div>
    <p v-if="error" class="form-error">{{ error }}</p>
    <LoadingSpinner v-if="loading" page label="Loading customers" />
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id">
            <td data-label="Name">{{ user.name }}</td>
            <td data-label="Email">
              <a v-if="user.email" :href="emailHref(user.email)">{{ user.email }}</a>
              <template v-else>—</template>
            </td>
            <td data-label="Actions">
              <div class="admin-actions">
                <AppButton type="button" variant="danger" size="sm" @click="requestRemove(user.id)">
                  Delete
                </AppButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete customer?"
      message="This customer will be permanently removed from the database. Their past orders will remain."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />
  </div>
</template>
