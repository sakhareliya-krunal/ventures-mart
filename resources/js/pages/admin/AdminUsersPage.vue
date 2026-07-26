<script setup>
import { onMounted, ref, watch } from 'vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const loading = ref(true);
const users = ref([]);
const search = ref('');
const error = ref('');

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
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id">
            <td data-label="Name">{{ user.name }}</td>
            <td data-label="Email">{{ user.email }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
