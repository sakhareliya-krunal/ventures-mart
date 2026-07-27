<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
  intent: {
    type: String,
    required: true,
    validator: (value) => ['login', 'register'].includes(value),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['token', 'error']);

const ready = ref(false);
const scriptError = ref('');
const requesting = ref(false);
/** @type {import('vue').Ref<null | { requestAccessToken: (overrideConfig?: object) => void }>} */
const tokenClient = ref(null);

const clientId = computed(() => {
  const fromApp = typeof window !== 'undefined' ? window.__APP__?.googleClientId : '';
  return String(fromApp || import.meta.env.VITE_GOOGLE_CLIENT_ID || '').trim();
});
const configured = computed(() => Boolean(clientId.value));
const label = computed(() =>
  props.intent === 'register' ? 'Sign up with Google' : 'Continue with Google',
);
const busy = computed(
  () => props.disabled || !ready.value || !configured.value || requesting.value,
);

function loadGisScript() {
  if (window.google?.accounts?.oauth2) {
    return Promise.resolve();
  }

  const existing = document.querySelector('script[data-google-gis="true"]');
  if (existing) {
    return new Promise((resolve, reject) => {
      if (window.google?.accounts?.oauth2) {
        resolve();
        return;
      }
      existing.addEventListener('load', () => resolve(), { once: true });
      existing.addEventListener('error', () => reject(new Error('Unable to load Google Sign-In.')), {
        once: true,
      });
    });
  }

  return new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.defer = true;
    script.dataset.googleGis = 'true';
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Unable to load Google Sign-In.'));
    document.head.appendChild(script);
  });
}

function handleTokenResponse(response) {
  requesting.value = false;

  if (props.disabled) return;

  if (response?.error) {
    if (response.error === 'popup_closed_by_user' || response.error === 'access_denied') {
      return;
    }
    emit('error', response.error_description || 'Google sign-in was cancelled. Please try again.');
    return;
  }

  const accessToken = response?.access_token;
  if (!accessToken) {
    emit('error', 'Google did not return an access token. Please try again.');
    return;
  }

  emit('token', accessToken);
}

function handleTokenError(error) {
  requesting.value = false;

  const type = error?.type || error?.message || '';
  if (type === 'popup_closed' || type === 'popup_closed_by_user') {
    return;
  }

  if (type === 'popup_failed_to_open') {
    emit('error', 'Unable to open Google sign-in popup. Please allow popups and try again.');
    return;
  }

  emit('error', 'Unable to continue with Google. Please try again.');
}

function createTokenClient() {
  if (!configured.value || !window.google?.accounts?.oauth2) {
    return;
  }

  tokenClient.value = window.google.accounts.oauth2.initTokenClient({
    client_id: clientId.value,
    scope: 'openid email profile',
    ux_mode: 'popup',
    callback: handleTokenResponse,
    error_callback: handleTokenError,
  });

  ready.value = true;
}

function startGoogleSignIn() {
  if (busy.value || !tokenClient.value) return;

  requesting.value = true;
  try {
    tokenClient.value.requestAccessToken({ prompt: 'select_account' });
  } catch (err) {
    requesting.value = false;
    emit('error', err?.message || 'Unable to continue with Google. Please try again.');
  }
}

async function setup() {
  scriptError.value = '';
  ready.value = false;
  tokenClient.value = null;

  if (!configured.value) {
    scriptError.value = 'Google sign-in is not configured yet.';
    return;
  }

  try {
    await loadGisScript();
    createTokenClient();
  } catch (err) {
    scriptError.value = err.message || 'Unable to load Google Sign-In.';
    emit('error', scriptError.value);
  }
}

onMounted(setup);

onBeforeUnmount(() => {
  tokenClient.value = null;
});
</script>

<template>
  <div
    class="google-continue"
    :class="{ 'is-disabled': busy, 'is-ready': ready && !scriptError }"
  >
    <p v-if="scriptError" class="google-continue__hint">{{ scriptError }}</p>
    <template v-else>
      <p v-if="!ready" class="google-continue__hint">Loading Google…</p>
      <button
        v-show="ready"
        type="button"
        class="google-continue__shell"
        :disabled="busy"
        :aria-busy="requesting ? 'true' : undefined"
        @click="startGoogleSignIn"
      >
        <span class="google-continue__icon">
          <svg viewBox="0 0 48 48" width="18" height="18" focusable="false" aria-hidden="true">
            <path
              fill="#FFC107"
              d="M43.611 20.083H42V20H24v8h11.303C33.654 32.657 29.208 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"
            />
            <path
              fill="#FF3D00"
              d="M6.306 14.691l6.571 4.819C14.655 16.108 18.961 13 24 13c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"
            />
            <path
              fill="#4CAF50"
              d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.188 0-9.62-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"
            />
            <path
              fill="#1976D2"
              d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"
            />
          </svg>
        </span>
        <span class="google-continue__label">{{ label }}</span>
      </button>
    </template>
  </div>
</template>
