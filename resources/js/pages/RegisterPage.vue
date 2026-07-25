<script setup>
import { reactive, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const auth = useAuthStore();
const router = useRouter();

const error = ref('');
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

useHead({
  title: () => `Register | ${theme.brandName}`,
});

watch(
  () => auth.user,
  (user) => {
    if (user) {
      router.replace('/profile');
    }
  },
  { immediate: true },
);

async function submit() {
  error.value = '';

  try {
    await auth.register({ ...form });
    await router.replace('/profile');
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to register.';
  }
}
</script>

<template>
  <section class="auth-page">
    <form class="form-panel auth-card" @submit.prevent="submit">
      <span class="eyebrow">Account</span>
      <h1>Register</h1>
      <p v-if="error" class="form-error">{{ error }}</p>
      <FormField v-model="form.name" label="Name" required autocomplete="name" />
      <FormField v-model="form.email" label="Email" type="email" required autocomplete="email" />
      <FormField
        v-model="form.password"
        label="Password"
        type="password"
        required
        autocomplete="new-password"
      />
      <FormField
        v-model="form.password_confirmation"
        label="Confirm password"
        type="password"
        required
        autocomplete="new-password"
      />
      <AppButton type="submit" :disabled="auth.loading">
        {{ auth.loading ? 'Creating account…' : 'Create account' }}
      </AppButton>
      <p>Already registered? <RouterLink to="/login">Login</RouterLink></p>
    </form>
  </section>
</template>
