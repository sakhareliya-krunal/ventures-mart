<script setup>
import { reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useHead } from '@unhead/vue';
import AuthShell from '@/components/auth/AuthShell.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import api from '@/services/api';
import { useThemeStore } from '@/stores/theme';
import { firstError, normalizeApiErrors, rules, validateFields } from '@/utils/validation';

const theme = useThemeStore();

const error = ref('');
const success = ref('');
const loading = ref(false);
const fieldErrors = ref({});
const form = reactive({
  email: '',
});

useHead({
  title: () => `Forgot password | ${theme.brandName}`,
});

function validateForm() {
  const errors = validateFields(form, {
    email: [rules.required('Email'), rules.email()],
  });
  fieldErrors.value = errors;
  error.value = firstError(errors);
  return Object.keys(errors).length === 0;
}

async function submit() {
  if (loading.value) return;

  error.value = '';
  success.value = '';
  fieldErrors.value = {};

  if (!validateForm()) return;

  loading.value = true;

  try {
    const { data } = await api.post('/forgot-password', { email: form.email });
    success.value =
      data.message || 'If that email is registered, we sent a password reset link.';
  } catch (err) {
    fieldErrors.value = normalizeApiErrors(err.response?.data?.errors);
    error.value =
      firstError(fieldErrors.value) ||
      err.response?.data?.message ||
      'Unable to send reset link.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <AuthShell
    title="Forgot password"
    :busy="loading"
  >
    <form novalidate class="auth-form" @submit.prevent="submit">
      <p v-if="error" class="form-error">{{ error }}</p>
      <p v-if="success" class="form-success">{{ success }}</p>
      <FormField
        v-model="form.email"
        label="Email"
        type="email"
        required
        autocomplete="email"
        :disabled="loading"
        :error="fieldErrors.email"
      />
      <AppButton class="auth-submit" type="submit" :loading="loading">
        Send reset link
      </AppButton>
    </form>

    <template #footer>
      <p><RouterLink to="/login">Back to login</RouterLink></p>
    </template>
  </AuthShell>
</template>