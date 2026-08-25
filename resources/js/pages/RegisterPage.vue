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
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

let leaving = false;

useHead(() =>
  seoHeadFromServer({
    title: `Register | ${theme.brandName}`,
    description: `Create your ${theme.brandName} account to shop toys and lunch boxes.`,
    canonical: '/register',
    robots: 'noindex,follow',
  }),
);

function defaultPostAuthPath() {
  return auth.isAdmin ? '/admin' : '/';
}

function postAuthPath() {
  return resolvePostAuthPath({
    pending: auth.takePendingReturnUrl(),
    redirect: route.query.redirect,
    fallback: defaultPostAuthPath(),
  });
}

const loginLink = computed(() => ({
  path: '/login',
  query: route.query.redirect ? { redirect: String(route.query.redirect) } : {},
}));

async function leaveAfterAuth({ welcomeBack = false } = {}) {
  if (leaving || !auth.user) return;
  leaving = true;
  auth.beginRedirect();
  try {
    const target = postAuthPath();
    if (welcomeBack) {
      const url = new URL(target, window.location.origin);
      url.searchParams.set('welcome', '1');
      await router.replace(`${url.pathname}${url.search}${url.hash}`);
    } else {
      await router.replace(target);
    }
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
    name: [rules.required('Name')],
    email: [rules.required('Email'), rules.email()],
    password: [rules.required('Password'), rules.minLength('Password', 8)],
    password_confirmation: [
      rules.required('Confirm password'),
      rules.matches('Confirm password', form.password, 'password'),
    ],
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
    await auth.register({ ...form });
    await leaveAfterAuth();
  } catch (err) {
    auth.endRedirect();
    leaving = false;
    fieldErrors.value = normalizeApiErrors(err.response?.data?.errors);
    error.value =
      firstError(fieldErrors.value) ||
      err.response?.data?.message ||
      'Unable to register.';
  }
}

async function continueWithGoogle(accessToken) {
  if (auth.loading || auth.redirecting || leaving) return;

  error.value = '';
  fieldErrors.value = {};

  try {
    const { created } = await auth.loginWithGoogle({ accessToken, intent: 'register' });
    await leaveAfterAuth({ welcomeBack: !created });
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
    title="Create your account"
    :busy="auth.loading || auth.redirecting"
  >
    <form class="auth-form" novalidate @submit.prevent="submit">
      <p v-if="error" class="form-error">{{ error }}</p>
      <FormField
        v-model="form.name"
        label="Name"
        required
        autocomplete="name"
        :disabled="auth.loading || auth.redirecting"
        :error="fieldErrors.name"
      />
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
        autocomplete="new-password"
        :disabled="auth.loading || auth.redirecting"
        :error="fieldErrors.password"
      />
      <FormField
        v-model="form.password_confirmation"
        label="Confirm password"
        type="password"
        required
        autocomplete="new-password"
        :disabled="auth.loading || auth.redirecting"
        :error="fieldErrors.password_confirmation"
      />
      <AppButton
        class="auth-submit"
        type="submit"
        :loading="auth.loading || auth.redirecting"
      >
        Create account
      </AppButton>

      <div class="auth-divider" role="separator"><span>or</span></div>
      <GoogleContinueButton
        intent="register"
        :disabled="auth.loading || auth.redirecting"
        @token="continueWithGoogle"
        @error="onGoogleError"
      />
    </form>

    <template #footer>
      <p>Already registered? <RouterLink :to="loginLink">Sign in</RouterLink></p>
    </template>
  </AuthShell>
</template>