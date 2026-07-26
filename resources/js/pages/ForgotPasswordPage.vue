<script setup>
import { reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();

const error = ref('');
const success = ref('');
const loading = ref(false);
const form = reactive({
  email: '',
});

useHead({
  title: () => `Forgot password | ${theme.brandName}`,
});

async function submit() {
  if (loading.value) return;

  error.value = '';
  success.value = '';
  loading.value = true;

  try {
    const { data } = await api.post('/forgot-password', { email: form.email });
    success.value =
      data.message || 'If that email is registered, we sent a password reset link.';
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to send reset link.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <section class="auth-page">
    <form
      class="form-panel auth-card"
      :class="{ 'is-busy': loading }"
      @submit.prevent="submit"
    >
      <span class="eyebrow">Account</span>
      <h1>Forgot password</h1>
      <p class="auth-lead">
        Enter your account email and we will send a reset link if it matches an account.
      </p>
      <p v-if="error" class="form-error">{{ error }}</p>
      <p v-if="success" class="form-success">{{ success }}</p>
      <FormField
        v-model="form.email"
        label="Email"
        type="email"
        required
        autocomplete="email"
        :disabled="loading"
      />
      <AppButton class="auth-submit" type="submit" :disabled="loading">
        <LoadingSpinner v-if="loading" size="sm" label="Sending…" />
        <template v-else>Send reset link</template>
      </AppButton>
      <p><RouterLink to="/login">Back to login</RouterLink></p>
    </form>
  </section>
</template>
