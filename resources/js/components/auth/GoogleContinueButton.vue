<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';

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

const emit = defineEmits(['credential', 'error']);

const ready = ref(false);
const scriptError = ref('');
const prompting = ref(false);

const clientId = computed(() => {
  const fromApp = typeof window !== 'undefined' ? window.__APP__?.googleClientId : '';
  return String(fromApp || import.meta.env.VITE_GOOGLE_CLIENT_ID || '').trim();
});
const configured = computed(() => Boolean(clientId.value));
const label = computed(() =>
  props.intent === 'register' ? 'Sign up with Google' : 'Continue with Google',
);
const busy = computed(() => props.disabled || !ready.value || prompting.value || !configured.value);

function loadGisScript() {
  if (window.google?.accounts?.id) {
    return Promise.resolve();
  }

  const existing = document.querySelector('script[data-google-gis="true"]');
  if (existing) {
    return new Promise((resolve, reject) => {
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

function handleCredential(response) {
  prompting.value = false;
  if (props.disabled) return;
  const credential = response?.credential;
  if (!credential) {
    emit('error', 'Google did not return a credential. Please try again.');
    return;
  }
  emit('credential', credential);
}

function initializeGis() {
  if (!configured.value || !window.google?.accounts?.id) {
    return;
  }

  window.google.accounts.id.initialize({
    client_id: clientId.value,
    callback: handleCredential,
    ux_mode: 'popup',
    context: props.intent === 'register' ? 'signup' : 'signin',
    auto_select: false,
    cancel_on_tap_outside: true,
  });

  ready.value = true;
}

async function setup() {
  scriptError.value = '';
  ready.value = false;

  if (!configured.value) {
    scriptError.value = 'Google sign-in is not configured yet.';
    return;
  }

  try {
    await loadGisScript();
    initializeGis();
  } catch (err) {
    scriptError.value = err.message || 'Unable to load Google Sign-In.';
    emit('error', scriptError.value);
  }
}

function continueWithGoogle() {
  if (busy.value || !window.google?.accounts?.id) return;

  prompting.value = true;
  initializeGis();

  window.google.accounts.id.prompt((notification) => {
    if (!notification) return;

    if (notification.isNotDisplayed?.() || notification.isSkippedMoment?.() || notification.isDismissedMoment?.()) {
      prompting.value = false;
      const reason =
        notification.getNotDisplayedReason?.() ||
        notification.getSkippedReason?.() ||
        notification.getDismissedReason?.() ||
        '';
      if (reason && reason !== 'tap_outside' && reason !== 'user_cancel') {
        emit('error', 'Unable to open Google Sign-In. Please try again.');
      }
    }
  });
}

onMounted(setup);

watch(
  () => props.intent,
  () => {
    if (ready.value) {
      initializeGis();
    }
  },
);

onBeforeUnmount(() => {
  prompting.value = false;
});
</script>

<template>
  <div class="google-continue" :class="{ 'is-disabled': busy }">
    <p v-if="scriptError" class="google-continue__hint">{{ scriptError }}</p>
    <template v-else>
      <p v-if="!ready" class="google-continue__hint">Loading Google…</p>
      <button
        v-show="ready"
        type="button"
        class="google-continue__btn"
        :disabled="busy"
        :aria-busy="prompting"
        @click="continueWithGoogle"
      >
        <span class="google-continue__icon" aria-hidden="true">
          <svg viewBox="0 0 48 48" width="18" height="18" focusable="false">
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
