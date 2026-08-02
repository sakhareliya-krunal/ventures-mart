import axios from 'axios';
import { friendlyApiError, isNetworkOrTimeoutError } from '@/utils/apiError';

const NETWORK_GET_MAX_RETRIES = 20;
const NETWORK_MUTATION_MAX_RETRIES = 3;
const NETWORK_RETRY_DELAY_MS = 1500;
const SLOW_NETWORK_MS = 600;

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

function beginNetworkWait(label = 'Connecting…') {
  withUiStore((ui) => {
    ui.beginNetworkWait(label);
  });
}

function endNetworkWait() {
  withUiStore((ui) => {
    ui.endNetworkWait();
  });
}

function shouldSkipGlobalToast(config = {}) {
  return Boolean(config.skipErrorToast);
}

function isQuietUnauthRequest(config = {}) {
  const url = String(config.url || '');
  return url.includes('/user') && (config.method || 'get').toLowerCase() === 'get';
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function clearSlowNetworkTimer(config) {
  if (config.__slowNetworkTimer) {
    clearTimeout(config.__slowNetworkTimer);
    config.__slowNetworkTimer = null;
  }
}

function releaseNetworkWait(config) {
  clearSlowNetworkTimer(config);
  if (config.__networkWaitActive) {
    config.__networkWaitActive = false;
    endNetworkWait();
  }
}

function ensureNetworkWait(config, label = 'Connecting…') {
  if (config.__networkWaitActive) {
    return;
  }
  config.__networkWaitActive = true;
  beginNetworkWait(label);
}

function scheduleSlowNetworkWait(config) {
  if (config.__slowNetworkTimer || config.__networkWaitActive) {
    return;
  }

  config.__slowNetworkTimer = setTimeout(() => {
    config.__slowNetworkTimer = null;
    ensureNetworkWait(config, 'Connecting…');
  }, SLOW_NETWORK_MS);
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

  scheduleSlowNetworkWait(config);

  return config;
});

api.interceptors.response.use(
  (response) => {
    releaseNetworkWait(response.config || {});
    return response;
  },
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
        ensureNetworkWait(config, 'Connecting…');
        await sleep(NETWORK_RETRY_DELAY_MS);
        return api.request(config);
      }

      releaseNetworkWait(config);
      return Promise.reject(error);
    }

    releaseNetworkWait(config);

    if (!shouldSkipGlobalToast(config)) {
      if (status === 419) {
        toastError(friendlyApiError(error));
      } else if (status === 429) {
        toastError(friendlyApiError(error));
      } else if (status === 403) {
        toastError(friendlyApiError(error));
      } else if (status === 401 && !isQuietUnauthRequest(config)) {
        toastError(friendlyApiError(error));
      } else if (status >= 500) {
        toastError(friendlyApiError(error));
      }
    }

    return Promise.reject(error);
  },
);

export default api;
