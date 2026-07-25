<script setup>
import { onMounted, reactive, ref } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const accountError = ref('');
const accountSuccess = ref('');
const passwordError = ref('');
const passwordSuccess = ref('');
const savingAccount = ref(false);
const savingPassword = ref(false);

const account = reactive({
  name: '',
  email: '',
});

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});

onMounted(async () => {
  if (!auth.user) {
    await auth.fetchUser();
  }

  account.name = auth.user?.name || '';
  account.email = auth.user?.email || '';
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

async function savePassword() {
  passwordError.value = '';
  passwordSuccess.value = '';
  savingPassword.value = true;

  try {
    const { data } = await api.put('/profile/password', { ...passwordForm });
    passwordSuccess.value = data.message || 'Password updated.';
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
  } catch (err) {
    passwordError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to update password.';
  } finally {
    savingPassword.value = false;
  }
}
</script>

<template>
  <div class="admin-detail-grid">
    <div class="admin-panel">
      <h2>Account details</h2>
      <p v-if="accountError" class="form-error">{{ accountError }}</p>
      <p v-if="accountSuccess" class="form-success">{{ accountSuccess }}</p>
      <form class="admin-form" @submit.prevent="saveAccount">
        <FormField v-model="account.name" label="Name" required />
        <FormField v-model="account.email" label="Email" type="email" required />
        <AppButton type="submit" :disabled="savingAccount">
          {{ savingAccount ? 'Saving…' : 'Save profile' }}
        </AppButton>
      </form>
    </div>

    <div class="admin-panel">
      <h2>Change password</h2>
      <p v-if="passwordError" class="form-error">{{ passwordError }}</p>
      <p v-if="passwordSuccess" class="form-success">{{ passwordSuccess }}</p>
      <form class="admin-form" @submit.prevent="savePassword">
        <FormField
          v-model="passwordForm.current_password"
          label="Current password"
          type="password"
          required
        />
        <FormField v-model="passwordForm.password" label="New password" type="password" required />
        <FormField
          v-model="passwordForm.password_confirmation"
          label="Confirm password"
          type="password"
          required
        />
        <AppButton type="submit" :disabled="savingPassword">
          {{ savingPassword ? 'Updating…' : 'Update password' }}
        </AppButton>
      </form>
    </div>
  </div>
</template>
