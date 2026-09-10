import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}));

vi.mock('@/utils/seoHead', () => ({
  syncSeoForPath: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/services/metaPixel', () => ({
  trackMetaEvent: vi.fn(),
}));

describe('admin error route navigation', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.mocked(api.get).mockReset();
    window.matchMedia = vi.fn(() => ({
      matches: false,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }));
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it('keeps direct /admin/error navigation after admin session resolves', async () => {
    vi.mocked(api.get).mockImplementation((url) => {
      if (String(url).includes('/user')) {
        return Promise.resolve({
          data: { data: { id: 1, email: 'admin@test.test', is_admin: true } },
        });
      }
      return Promise.reject(new Error(`Unexpected GET ${url}`));
    });

    const { default: router } = await import('@/router/index.js');
    const auth = useAuthStore();

    expect(auth.user).toBeNull();

    await router.push('/admin/error');
    await router.isReady();
    await flushPromises();

    expect(auth.isAdmin).toBe(true);
    expect(router.currentRoute.value.path).toBe('/admin/error');
    expect(router.currentRoute.value.name).toBe('admin-errors');
  });

  it('still redirects admins from storefront home to the panel after session is ready', async () => {
    vi.mocked(api.get).mockImplementation((url) => {
      if (String(url).includes('/user')) {
        return Promise.resolve({
          data: { data: { id: 1, email: 'admin@test.test', is_admin: true } },
        });
      }
      return Promise.reject(new Error(`Unexpected GET ${url}`));
    });

    const { default: router } = await import('@/router/index.js');
    const auth = useAuthStore();
    auth.user = { id: 1, email: 'admin@test.test', is_admin: true };

    await router.push('/');
    await router.isReady();
    await flushPromises();

    expect(router.currentRoute.value.path).toBe('/admin');
  });
});
