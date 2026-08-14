<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import GoogleContinueButton from '@/components/auth/GoogleContinueButton.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useAuthStore } from '@/stores/auth';
import { resolvePostAuthPath } from '@/utils/authRedirect';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const error = ref('');
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

  // Session restore may still be in flight when landing on /register already signed in.
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

  try {
    await auth.register({ ...form });
    await leaveAfterAuth();
  } catch (err) {
    auth.endRedirect();
    leaving = false;
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to register.';
  }
}

async function continueWithGoogle(accessToken) {
  if (auth.loading || auth.redirecting || leaving) return;

  error.value = '';

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
  <section class="auth-page">
    <form
      class="form-panel auth-card"
      :class="{ 'is-busy': auth.loading || auth.redirecting }"
      @submit.prevent="submit"
    >
      <span class="eyebrow">Account</span>
      <h1>Register</h1>
      <p v-if="error" class="form-error">{{ error }}</p>
      <FormField
        v-model="form.name"
        label="Name"
        required
        autocomplete="name"
        :disabled="auth.loading || auth.redirecting"
      />
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
        autocomplete="new-password"
        :disabled="auth.loading || auth.redirecting"
      />
      <FormField
        v-model="form.password_confirmation"
        label="Confirm password"
        type="password"
        required
        autocomplete="new-password"
        :disabled="auth.loading || auth.redirecting"
      />
      <AppButton
        class="auth-submit"
        type="submit"
        :disabled="auth.loading || auth.redirecting"
      >
        <LoadingSpinner
          v-if="auth.loading || auth.redirecting"
          size="sm"
          :label="auth.redirecting && !auth.loading ? 'Redirecting…' : 'Creating account…'"
        />
        <template v-else>Create account</template>
      </AppButton>

      <div class="auth-divider" role="separator"><span>or</span></div>
      <GoogleContinueButton
        intent="register"
        :disabled="auth.loading || auth.redirecting"
        @token="continueWithGoogle"
        @error="onGoogleError"
      />

      <p>Already registered? <RouterLink :to="loginLink">Login</RouterLink></p>
    </form>
  </section>
</template>
