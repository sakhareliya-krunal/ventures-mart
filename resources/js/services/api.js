import axios from 'axios';
import { friendlyApiError, isNetworkOrTimeoutError } from '@/utils/apiError';

const NETWORK_GET_MAX_RETRIES = 20;
const NETWORK_MUTATION_MAX_RETRIES = 3;
const NETWORK_RETRY_DELAY_MS = 1500;

const api = axios.create({
  baseURL: '/api',
  timeout: 20000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

function readCookie(name) {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function readCsrfToken() {
  return (
    readCookie('XSRF-TOKEN') ||
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    null
  );
}

let csrfPromise = null;

export async function ensureCsrfCookie({ force = false } = {}) {
  if (!force && readCsrfToken()) {
    return;
  }

  if (!csrfPromise) {
    csrfPromise = axios
      .get('/sanctum/csrf-cookie', { withCredentials: true })
      .finally(() => {
        csrfPromise = null;
      });
  }

  await csrfPromise;
}

function withUiStore(callback) {
  return import('@/stores/ui')
    .then(({ useUiStore }) => {
      callback(useUiStore());
    })
    .catch(() => {
      // Pinia may not be ready during early boot.
    });
}

function toastError(message) {
  withUiStore((ui) => {
    ui.showToast(message, { type: 'error', durationMs: 4500 });
  });
}

function shouldSkipGlobalToast(config = {}) {
  return Boolean(config.skipErrorToast);
}

function requestPath(config = {}) {
  const raw = String(config.url || '').split('?')[0];
  return raw.replace(/\/+$/, '') || '/';
}

function isQuietUnauthRequest(config = {}) {
  const path = requestPath(config);
  const method = (config.method || 'get').toLowerCase();
  return method === 'get' && (path === '/user' || path === 'user' || path.endsWith('/user'));
}

let handlingUnauthorized = false;

async function handleUnauthorized(error) {
  if (handlingUnauthorized) {
    return;
  }

  handlingUnauthorized = true;

  try {
    const [{ useAuthStore }, { default: router }] = await Promise.all([
      import('@/stores/auth'),
      import('@/router'),
    ]);
    const auth = useAuthStore();

    auth.user = null;

    if (!auth.redirecting) {
      toastError(friendlyApiError(error));
    }

    const route = router.currentRoute.value;
    if (route.name === 'login' || route.path === '/login') {
      return;
    }

    const redirect = route.fullPath || '/';
    auth.beginRedirect();

    await router
      .push({
        name: 'login',
        query: { redirect },
      })
      .catch(() => {
        auth.endRedirect();
      });
  } catch {
    // Pinia/router may not be ready during early boot.
  } finally {
    window.setTimeout(() => {
      handlingUnauthorized = false;
    }, 1500);
  }
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function maxRetriesForMethod(method) {
  return ['get', 'head', 'options'].includes(method)
    ? NETWORK_GET_MAX_RETRIES
    : NETWORK_MUTATION_MAX_RETRIES;
}

api.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase();
  const isMutating = !['get', 'head', 'options'].includes(method);

  if (isMutating) {
    await ensureCsrfCookie();
  }

  const token = readCsrfToken();
  if (token) {
    config.headers['X-XSRF-TOKEN'] = token;
    config.headers['X-CSRF-TOKEN'] = token;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config || {};
    const status = error.response?.status;

    if (status === 419 && !config.__csrfRetried) {
      config.__csrfRetried = true;
      try {
        await ensureCsrfCookie({ force: true });
        const token = readCsrfToken();
        if (token) {
          config.headers = config.headers || {};
          config.headers['X-XSRF-TOKEN'] = token;
          config.headers['X-CSRF-TOKEN'] = token;
        }
        return api.request(config);
      } catch {
        // Fall through to friendly handling.
      }
    }

    if (isNetworkOrTimeoutError(error)) {
      const method = (config.method || 'get').toLowerCase();
      const attempt = config.__networkRetries || 0;
      const maxRetries = maxRetriesForMethod(method);

      if (attempt < maxRetries) {
        config.__networkRetries = attempt + 1;
        await sleep(NETWORK_RETRY_DELAY_MS);
        return api.request(config);
      }

      return Promise.reject(error);
    }

    if (status === 401 && !isQuietUnauthRequest(config) && !shouldSkipGlobalToast(config)) {
      await handleUnauthorized(error);
      return Promise.reject(error);
    }

    if (!shouldSkipGlobalToast(config)) {
      if (status === 419) {
        toastError(friendlyApiError(error));
      } else if (status === 429) {
        toastError(friendlyApiError(error));
      } else if (status === 403) {
        toastError(friendlyApiError(error));
      } else if (status >= 500) {
        toastError(friendlyApiError(error));
      }
    }

    return Promise.reject(error);
  },
);

export default api;
