<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { emailHref } from '@/utils/contactLinks';
import { unwrapData } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';

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
let successTimer = null;

const account = reactive({
  name: '',
  email: '',
});

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
  account.email = auth.user?.email || '';
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
</script>

<template>
  <div class="admin-detail-grid">
    <div class="admin-panel">
      <h2>Profile</h2>
      <p v-if="accountError" class="form-error">{{ accountError }}</p>
      <p v-if="accountSuccess" class="form-success">{{ accountSuccess }}</p>
      <form novalidate class="admin-form" @submit.prevent="saveAccount">
        <FormField v-model="account.name" label="Name" required />
        <FormField v-model="account.email" label="Email" type="email" required />
        <AppButton type="submit" :loading="savingAccount">
          Save profile
        </AppButton>
      </form>
    </div>

    <div class="admin-panel">
      <div class="admin-toolbar">
        <div>
          <h2>Admins</h2>
          <p class="admin-muted">Administrators who can access this panel.</p>
        </div>
        <AppButton type="button" @click="openCreateAdmin">Create admin</AppButton>
      </div>

      <p v-if="successMessage" class="form-success">{{ successMessage }}</p>
      <p v-if="adminsError" class="form-error">{{ adminsError }}</p>
      <LoadingSpinner v-if="adminsLoading" page label="Loading admins" />
      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in admins" :key="user.id">
              <td data-label="Name">{{ user.name }}</td>
            <td data-label="Email">
              <a v-if="user.email" :href="emailHref(user.email)">{{ user.email }}</a>
              <template v-else>—</template>
            </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
