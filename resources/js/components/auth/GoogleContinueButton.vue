<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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

const hostRef = ref(null);
const ready = ref(false);
const scriptError = ref('');

const clientId = computed(() => {
  const fromApp = typeof window !== 'undefined' ? window.__APP__?.googleClientId : '';
  return String(fromApp || import.meta.env.VITE_GOOGLE_CLIENT_ID || '').trim();
});
const configured = computed(() => Boolean(clientId.value));

let rendered = false;

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
  if (props.disabled) return;
  const credential = response?.credential;
  if (!credential) {
    emit('error', 'Google did not return a credential. Please try again.');
    return;
  }
  emit('credential', credential);
}

function renderButton() {
  if (!configured.value || !hostRef.value || !window.google?.accounts?.id) {
    return;
  }

  hostRef.value.innerHTML = '';
  window.google.accounts.id.initialize({
    client_id: clientId.value,
    callback: handleCredential,
    ux_mode: 'popup',
    context: props.intent === 'register' ? 'signup' : 'signin',
  });

  window.google.accounts.id.renderButton(hostRef.value, {
    type: 'standard',
    theme: 'filled_blue',
    size: 'medium',
    text: props.intent === 'register' ? 'signup_with' : 'continue_with',
    shape: 'pill',
    width: Math.min(Math.max(hostRef.value.clientWidth || 0, 1), 360),
  });

  rendered = true;
  ready.value = true;
}

async function setup() {
  scriptError.value = '';
  ready.value = false;
  rendered = false;

  if (!configured.value) {
    scriptError.value = 'Google sign-in is not configured yet.';
    return;
  }

  try {
    await loadGisScript();
    renderButton();
  } catch (err) {
    scriptError.value = err.message || 'Unable to load Google Sign-In.';
    emit('error', scriptError.value);
  }
}

onMounted(setup);

watch(
  () => [props.intent, props.disabled],
  () => {
    if (hostRef.value) {
      hostRef.value.style.pointerEvents = props.disabled ? 'none' : '';
      hostRef.value.style.opacity = props.disabled ? '0.55' : '';
    }
  },
);

onBeforeUnmount(() => {
  if (hostRef.value) {
    hostRef.value.innerHTML = '';
  }
});
</script>

<template>
  <div class="google-continue" :class="{ 'is-disabled': disabled || !configured }">
    <p v-if="scriptError" class="google-continue__hint">{{ scriptError }}</p>
    <template v-else>
      <p v-if="!ready" class="google-continue__hint">Loading Google…</p>
      <div ref="hostRef" class="google-continue__host" />
    </template>
  </div>
</template>
