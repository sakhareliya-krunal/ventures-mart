<script setup>
import AdminPanel from '@/components/admin/AdminPanel.vue';
import AdminDataList from '@/components/admin/AdminDataList.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import { useAdminList } from '@/composables/useAdminList';
import { onMounted, ref, watch } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadingSpinner from '@/components/admin/AdminLoading.vue';
import api from '@/services/api';
import { emailHref, phoneHref } from '@/utils/contactLinks';
import { unwrapData } from '@/utils/format';

const { rows: addresses, loading, refreshing, error: fetchError, search, filters, meta, filtered, load, go, reset } = useAdminList('/admin/addresses', {});
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const deleting = ref(false);


function requestRemove(id) {
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value || deleting.value) return;
  deleting.value = true;
  try {
    await api.delete(`/admin/addresses/${pendingDeleteId.value}`);
    pendingDeleteId.value = null;
    confirmOpen.value = false;
    await load({ silent: true });
  } finally {
    deleting.value = false;
  }
}

</script>

<template>
  <AdminPanel class="admin-panel">
    <div class="admin-toolbar">
      <h2>Saved addresses</h2>
      <div class="admin-toolbar__filters">
        <AdminSearchField
          v-model="search"
          placeholder="Search addresses…"
          aria-label="Search addresses"
        />
      </div>
    </div>
    <AdminDataList :rows="addresses" :loading="loading" :refreshing="refreshing" :error="fetchError" :searching="filtered" @retry="load" @reset="reset"><div class="admin-table-wrap">
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
            <td data-label="Customer">
              <div class="admin-table__value">
                <strong>{{ address.user?.name || '—' }}</strong>
                <div v-if="address.user?.email" class="admin-muted">
                  <a :href="emailHref(address.user.email)">{{ address.user.email }}</a>
                </div>
              </div>
            </td>
            <td data-label="Address">
              <div class="admin-table__value">
                <strong>{{ address.label }}</strong> · {{ address.full_name }}<br />
                {{ address.address }}, {{ address.city }}
                <template v-if="address.district">, {{ address.district }}</template>,
                {{ address.state }}
                {{ address.postal_code }}
                <div v-if="address.phone" class="admin-muted">
                  <a :href="phoneHref(address.phone)">{{ address.phone }}</a>
                </div>
              </div>
            </td>
            <td data-label="Default">
              <span class="admin-table__value">{{ address.is_default ? 'Yes' : 'No' }}</span>
            </td>
            <td data-label="Actions">
              <div class="admin-actions">
                <AppButton type="button" variant="danger" size="sm" @click="requestRemove(address.id)">
                  Delete
                </AppButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!addresses.length" class="admin-empty">No addresses saved.</p>
    </div></AdminDataList>
      <AdminPagination :page="meta.current_page" :last-page="meta.last_page" :total="meta.total" :from="meta.from || 0" :to="meta.to || 0" @page="go" />

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete address?"
      message="This saved address will be permanently removed."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />
  </AdminPanel>
</template>
