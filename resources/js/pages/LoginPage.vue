<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import AuthShell from '@/components/auth/AuthShell.vue';
import GoogleContinueButton from '@/components/auth/GoogleContinueButton.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import { useAuthStore } from '@/stores/auth';
import { resolvePostAuthPath } from '@/utils/authRedirect';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';
import { firstError, normalizeApiErrors, rules, validateFields } from '@/utils/validation';

const theme = useThemeStore();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const error = ref('');
const fieldErrors = ref({});
const form = reactive({
  email: '',
  password: '',
});

let leaving = false;

useHead(() =>
  seoHeadFromServer({
    title: `Sign in | ${theme.brandName}`,
    description: `Sign in to your ${theme.brandName} account.`,
    canonical: '/login',
    robots: 'noindex,follow',
  }),
);

const resetNotice = computed(() =>
  route.query.reset === '1' ? 'Password updated. Sign in with your new password.' : '',
);

function defaultPostLoginPath() {
  return auth.isAdmin ? '/admin' : '/';
}

function postLoginPath() {
  return resolvePostAuthPath({
    pending: auth.takePendingReturnUrl(),
    redirect: route.query.redirect,
    fallback: defaultPostLoginPath(),
  });
}

const registerLink = computed(() => ({
  path: '/register',
  query: route.query.redirect ? { redirect: String(route.query.redirect) } : {},
}));

async function leaveAfterAuth() {
  if (leaving || !auth.user) return;
  leaving = true;
  auth.beginRedirect();
  try {
    await router.replace(postLoginPath());
  } catch {
    auth.endRedirect();
    leaving = false;
  }
}

onMounted(() => {
  if (auth.user) {
    leaveAfterAuth();
    return;
  }

  if (auth.booting) {
    const stop = watch(
      () => auth.booting,
      (isBooting) => {
        if (!isBooting) {
          stop();
          if (auth.user) leaveAfterAuth();
        }
      },
    );
  }
});

function validateForm() {
  const errors = validateFields(form, {
    email: [rules.required('Email'), rules.email()],
    password: [rules.required('Password')],
  });
  fieldErrors.value = errors;
  error.value = firstError(errors);
  return Object.keys(errors).length === 0;
}

async function submit() {
  if (auth.loading || auth.redirecting || leaving) return;

  error.value = '';
  fieldErrors.value = {};

  if (!validateForm()) return;

  try {
    await auth.login({ ...form });
    await leaveAfterAuth();
  } catch (err) {
    auth.endRedirect();
    leaving = false;
    fieldErrors.value = normalizeApiErrors(err.response?.data?.errors);
    error.value =
      firstError(fieldErrors.value) ||
      err.response?.data?.message ||
      'Unable to log in.';
  }
}

async function continueWithGoogle(accessToken) {
  if (auth.loading || auth.redirecting || leaving) return;

  error.value = '';
  fieldErrors.value = {};

  try {
    await auth.loginWithGoogle({ accessToken, intent: 'login' });
    await leaveAfterAuth();
  } catch (err) {
    auth.endRedirect();
    leaving = false;
    error.value = err.message || 'Unable to continue with Google.';
  }
}

function onGoogleError(message) {
  if (!message) return;
  error.value = message;
}
</script>

<template>
  <AuthShell
    title="Welcome back"
    :busy="auth.loading || auth.redirecting"
  >
    <form class="auth-form" novalidate @submit.prevent="submit">
      <p v-if="resetNotice" class="form-success">{{ resetNotice }}</p>
      <p v-if="error" class="form-error">{{ error }}</p>
      <FormField
        v-model="form.email"
        label="Email"
        type="email"
        required
        autocomplete="email"
        :disabled="auth.loading || auth.redirecting"
        :error="fieldErrors.email"
      />
      <FormField
        v-model="form.password"
        label="Password"
        type="password"
        required
        autocomplete="current-password"
        :disabled="auth.loading || auth.redirecting"
        :error="fieldErrors.password"
      />
      <p class="auth-forgot">
        <RouterLink to="/forgot-password">Forgot password?</RouterLink>
      </p>
      <AppButton
        class="auth-submit"
        type="submit"
        :loading="auth.loading || auth.redirecting"
      >
        Sign in
      </AppButton>

      <div class="auth-divider" role="separator"><span>or</span></div>
      <GoogleContinueButton
        intent="login"
        :disabled="auth.loading || auth.redirecting"
        @token="continueWithGoogle"
        @error="onGoogleError"
      />
    </form>

    <template #footer>
      <p>New here? <RouterLink :to="registerLink">Create an account</RouterLink></p>
    </template>
  </AuthShell>
</template>