<script setup>
import AdminFormActions from '@/components/admin/AdminFormActions.vue';
import AdminPanel from '@/components/admin/AdminPanel.vue';
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/admin/AdminLoading.vue';
import api from '@/services/api';
import { emailHref, normalizeEmail } from '@/utils/contactLinks';
import { unwrapData } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';

const PROFILE_FORM_ID = 'admin-profile-form';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const accountError = ref('');
const accountSuccess = ref('');
const savingAccount = ref(false);
const adminsLoading = ref(true);
const adminsError = ref('');
const admins = ref([]);
const successMessage = ref('');
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const deleting = ref(false);
let successTimer = null;

const account = reactive({
  name: '',
  email: '',
});

function displayEmail(value) {
  return normalizeEmail(value) || '—';
}

function flashSuccess(message) {
  successMessage.value = message;
  if (successTimer) clearTimeout(successTimer);
  successTimer = setTimeout(() => {
    successMessage.value = '';
  }, 3500);
}

function consumeNotice() {
  if (route.query.notice !== 'admin-created') return;

  flashSuccess('Admin created.');
  const query = { ...route.query };
  delete query.notice;
  router.replace({ query });
}

async function loadAdmins() {
  adminsLoading.value = true;
  adminsError.value = '';
  try {
    const { data } = await api.get('/admin/users', {
      params: { role: 'admin', per_page: 100 },
    });
    admins.value = unwrapData(data) || [];
  } catch (err) {
    admins.value = [];
    adminsError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to load admins.';
  } finally {
    adminsLoading.value = false;
  }
}

onMounted(async () => {
  if (!auth.user) {
    await auth.fetchUser();
  }

  account.name = auth.user?.name || '';
  account.email = normalizeEmail(auth.user?.email);
  consumeNotice();
  await loadAdmins();
});

onBeforeUnmount(() => {
  if (successTimer) clearTimeout(successTimer);
});

async function saveAccount() {
  accountError.value = '';
  accountSuccess.value = '';
  savingAccount.value = true;

  try {
    const { data } = await api.patch('/profile', { ...account });
    auth.user = unwrapData(data);
    account.email = normalizeEmail(auth.user?.email);
    accountSuccess.value = 'Profile updated.';
  } catch (err) {
    accountError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to update profile.';
  } finally {
    savingAccount.value = false;
  }
}

function openCreateAdmin() {
  router.push({ name: 'admin-create-admin' });
}

function requestRemoveAdmin(id) {
  adminsError.value = '';
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function removeAdmin() {
  if (!pendingDeleteId.value || deleting.value) return;
  const id = pendingDeleteId.value;
  deleting.value = true;
  try {
    await api.delete(`/admin/users/${id}`);
    pendingDeleteId.value = null;
    confirmOpen.value = false;
    flashSuccess('Administrator removed.');
    await loadAdmins();
  } catch (err) {
    adminsError.value = err.response?.data?.message || 'Unable to delete administrator.';
    pendingDeleteId.value = null;
    confirmOpen.value = false;
  } finally {
    deleting.value = false;
  }
}
</script>

<template>
  <div class="admin-detail-grid admin-account-grid">
    <AdminPanel variant="form" class="admin-panel--form-compact">
      <p class="admin-muted admin-account-intro">Update your name and sign-in email.</p>

      <p v-if="accountError" class="form-error">{{ accountError }}</p>
      <p v-if="accountSuccess" class="form-success">{{ accountSuccess }}</p>

      <form :id="PROFILE_FORM_ID" novalidate class="admin-form" @submit.prevent="saveAccount">
        <div class="admin-form__fields">
          <FormField v-model="account.name" label="Name" required />
          <FormField v-model="account.email" label="Email" type="email" required />
        </div>
      </form>

      <template #footer>
        <AdminFormActions layout="footer" :busy="savingAccount">
          <AppButton type="submit" :form="PROFILE_FORM_ID" :loading="savingAccount">
            Save profile
          </AppButton>
        </AdminFormActions>
      </template>
    </AdminPanel>

    <AdminPanel variant="index">
      <div class="admin-form-intro admin-panel-intro">
        <p class="admin-muted">Administrators who can access this panel.</p>
        <AppButton type="button" @click="openCreateAdmin">Create admin</AppButton>
      </div>

      <p v-if="successMessage" class="form-success">{{ successMessage }}</p>
      <p v-if="adminsError" class="form-error">{{ adminsError }}</p>
      <LoadingSpinner v-if="adminsLoading" page label="Loading admins" />
      <div v-else-if="admins.length" class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in admins" :key="user.id">
              <td data-label="Name">{{ user.name }}</td>
              <td data-label="Email">
                <a v-if="normalizeEmail(user.email)" :href="emailHref(user.email)">
                  {{ displayEmail(user.email) }}
                </a>
                <template v-else>—</template>
              </td>
              <td data-label="Actions">
                <div v-if="user.id !== auth.user?.id" class="admin-actions">
                  <AppButton type="button" variant="danger" size="sm" @click="requestRemoveAdmin(user.id)">
                    Delete
                  </AppButton>
                </div>
                <span v-else class="admin-muted">You</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="admin-empty">No administrators yet.</p>
    </AdminPanel>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete administrator?"
      message="This administrator will be permanently removed and will lose access to the admin panel."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="removeAdmin"
    />
  </div>
</template>
