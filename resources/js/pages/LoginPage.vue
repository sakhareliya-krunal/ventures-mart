<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import GoogleContinueButton from '@/components/auth/GoogleContinueButton.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const error = ref('');
const errorCode = ref('');
const form = reactive({
  email: '',
  password: '',
});

let leaving = false;

useHead({
  title: () => `Login | ${theme.brandName}`,
});

const resetNotice = computed(() =>
  route.query.reset === '1' ? 'Password updated. Sign in with your new password.' : '',
);

function defaultPostLoginPath() {
  return auth.isAdmin ? '/admin' : '/profile';
}

function postLoginPath() {
  return String(route.query.redirect || defaultPostLoginPath());
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

  // Session restore may still be in flight when landing on /login already signed in.
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

async function submit() {
  if (auth.loading || auth.redirecting || leaving) return;

  error.value = '';
  errorCode.value = '';

  try {
    await auth.login({ ...form });
    await leaveAfterAuth();
  } catch (err) {
    auth.endRedirect();
    leaving = false;
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to log in.';
  }
}

async function continueWithGoogle(credential) {
  if (auth.loading || auth.redirecting || leaving) return;

  error.value = '';
  errorCode.value = '';

  try {
    await auth.loginWithGoogle({ credential, intent: 'login' });
    await leaveAfterAuth();
  } catch (err) {
    auth.endRedirect();
    leaving = false;
    errorCode.value = err.code || '';
    error.value = err.message || 'Unable to continue with Google.';
  }
}

function onGoogleError(message) {
  errorCode.value = '';
  error.value = message;
}
</script>

<template>
  <section class="auth-page">
    <form
      class="form-panel auth-card"
      :class="{ 'is-busy': auth.loading || auth.redirecting }"
      @submit.prevent="submit"
    >
      <span class="eyebrow">Account</span>
      <h1>Login</h1>
      <p v-if="resetNotice" class="form-success">{{ resetNotice }}</p>
      <p v-if="error" class="form-error">{{ error }}</p>
      <div v-if="errorCode === 'account_missing'" class="auth-alert-actions">
        <AppButton :to="registerLink" variant="secondary" size="sm">Create an account</AppButton>
      </div>
      <FormField
        v-model="form.email"
        label="Email"
        type="email"
        required
        autocomplete="email"
        :disabled="auth.loading || auth.redirecting"
      />
      <FormField
        v-model="form.password"
        label="Password"
        type="password"
        required
        autocomplete="current-password"
        :disabled="auth.loading || auth.redirecting"
      />
      <p class="auth-forgot">
        <RouterLink to="/forgot-password">Forgot password?</RouterLink>
      </p>
      <AppButton
        class="auth-submit"
        type="submit"
        :disabled="auth.loading || auth.redirecting"
      >
        <LoadingSpinner
          v-if="auth.loading || auth.redirecting"
          size="sm"
          :label="auth.redirecting && !auth.loading ? 'Redirecting…' : 'Signing in…'"
        />
        <template v-else>Login</template>
      </AppButton>

      <div class="auth-divider" role="separator"><span>or</span></div>
      <GoogleContinueButton
        intent="login"
        :disabled="auth.loading || auth.redirecting"
        @credential="continueWithGoogle"
        @error="onGoogleError"
      />

      <p>New here? <RouterLink :to="registerLink">Create an account</RouterLink></p>
    </form>
  </section>
</template>
