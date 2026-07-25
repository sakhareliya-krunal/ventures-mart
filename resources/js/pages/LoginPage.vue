<script setup>
import { reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const error = ref('');
const form = reactive({
  email: '',
  password: '',
});

useHead({
  title: () => `Login | ${theme.brandName}`,
});

function defaultPostLoginPath() {
  return auth.isAdmin ? '/admin' : '/profile';
}

watch(
  () => auth.user,
  (user) => {
    if (user) {
      router.replace(String(route.query.redirect || defaultPostLoginPath()));
    }
  },
  { immediate: true },
);

async function submit() {
  error.value = '';

  try {
    await auth.login({ ...form });
    await router.replace(String(route.query.redirect || defaultPostLoginPath()));
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to log in.';
  }
}
</script>

<template>
  <section class="auth-page">
    <form class="form-panel auth-card" @submit.prevent="submit">
      <span class="eyebrow">Account</span>
      <h1>Login</h1>
      <p v-if="error" class="form-error">{{ error }}</p>
      <FormField v-model="form.email" label="Email" type="email" required autocomplete="email" />
      <FormField
        v-model="form.password"
        label="Password"
        type="password"
        required
        autocomplete="current-password"
      />
      <AppButton type="submit" :disabled="auth.loading">
        {{ auth.loading ? 'Signing in…' : 'Login' }}
      </AppButton>
      <p>New here? <RouterLink to="/register">Create an account</RouterLink></p>
    </form>
  </section>
</template>
