<script setup>
import { onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const route = useRoute();
const router = useRouter();

const error = ref('');
const success = ref('');
const loading = ref(false);
const form = reactive({
  email: '',
  token: '',
  password: '',
  password_confirmation: '',
});

useHead({
  title: () => `Reset password | ${theme.brandName}`,
});

onMounted(() => {
  form.token = String(route.query.token || '');
  form.email = String(route.query.email || '');

  if (!form.token || !form.email) {
    error.value = 'This reset link is incomplete. Request a new password reset email.';
  }
});

async function submit() {
  if (loading.value || !form.token || !form.email) return;

  error.value = '';
  success.value = '';
  loading.value = true;

  try {
    const { data } = await api.post('/reset-password', { ...form });
    success.value = data.message || 'Password reset successfully.';
    await router.replace({
      name: 'login',
      query: { reset: '1' },
    });
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to reset password.';
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
      <h1>Reset password</h1>
      <p class="auth-lead">Choose a new password for {{ form.email || 'your account' }}.</p>
      <p v-if="error" class="form-error">{{ error }}</p>
      <p v-if="success" class="form-success">{{ success }}</p>
      <FormField
        v-model="form.password"
        label="New password"
        type="password"
        required
        autocomplete="new-password"
        :disabled="loading || !form.token"
      />
      <FormField
        v-model="form.password_confirmation"
        label="Confirm password"
        type="password"
        required
        autocomplete="new-password"
        :disabled="loading || !form.token"
      />
      <AppButton
        class="auth-submit"
        type="submit"
        :disabled="loading || !form.token || !form.email"
      >
        <LoadingSpinner v-if="loading" size="sm" label="Saving…" />
        <template v-else>Update password</template>
      </AppButton>
      <p><RouterLink to="/forgot-password">Request a new link</RouterLink></p>
    </form>
  </section>
</template>
