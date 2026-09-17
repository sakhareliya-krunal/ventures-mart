import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import api from '@/services/api';
import { useAuthStore } from './auth';
import { useProductsStore } from './products';

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

describe('store request deduplication', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('does not fetch the resolved guest session again during layout mount', async () => {
    api.get.mockRejectedValueOnce({ response: { status: 401 } });
    const auth = useAuthStore();

    await auth.fetchUser();
    await auth.fetchUser();

    expect(api.get).toHaveBeenCalledTimes(1);
    expect(api.get).toHaveBeenCalledWith('/user');
  });

  it('forces a fresh session lookup after authentication', async () => {
    api.get
      .mockRejectedValueOnce({ response: { status: 401 } })
      .mockResolvedValueOnce({ data: { data: { id: 10, name: 'QA Customer' } } });
    const auth = useAuthStore();

    await auth.fetchUser();
    await auth.fetchUser({ force: true });

    expect(api.get).toHaveBeenCalledTimes(2);
    expect(auth.user.id).toBe(10);
  });

  it('shares an in-flight identical catalog request', async () => {
    let resolveRequest;
    api.get.mockReturnValueOnce(new Promise((resolve) => { resolveRequest = resolve; }));
    const products = useProductsStore();
    const params = { q: 'blocks', sort: 'featured' };

    const first = products.fetchList(params);
    const second = products.fetchList(params);
    resolveRequest({ data: { data: [{ id: 1 }] } });

    await expect(first).resolves.toEqual([{ id: 1 }]);
    await expect(second).resolves.toEqual([{ id: 1 }]);
    expect(api.get).toHaveBeenCalledTimes(1);
  });
});
