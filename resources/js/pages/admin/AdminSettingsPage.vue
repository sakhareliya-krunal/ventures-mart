<script setup>
import { reactive, ref } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import api from '@/services/api';

const passwordError = ref('');
const passwordSuccess = ref('');
const savingPassword = ref(false);

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});

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
  <div class="admin-panel">
    <h2>Settings</h2>
    <p class="admin-muted">Update your admin password.</p>
    <p v-if="passwordError" class="form-error">{{ passwordError }}</p>
    <p v-if="passwordSuccess" class="form-success">{{ passwordSuccess }}</p>
    <form class="admin-form" @submit.prevent="savePassword">
      <FormField
        v-model="passwordForm.current_password"
        label="Current password"
        type="password"
        required
        autocomplete="current-password"
      />
      <FormField
        v-model="passwordForm.password"
        label="New password"
        type="password"
        required
        autocomplete="new-password"
      />
      <FormField
        v-model="passwordForm.password_confirmation"
        label="Confirm password"
        type="password"
        required
        autocomplete="new-password"
      />
      <AppButton type="submit" :disabled="savingPassword">
        {{ savingPassword ? 'Updating…' : 'Update password' }}
      </AppButton>
    </form>
  </div>
</template>
