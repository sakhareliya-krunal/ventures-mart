import { describe, expect, it } from 'vitest';
import { resolvePostAuthPath } from './authRedirect';

describe('resolvePostAuthPath', () => {
  it('uses the given fallback for empty or home paths', () => {
    expect(resolvePostAuthPath({ pending: '/', redirect: '/', fallback: '/' })).toBe('/');
    expect(resolvePostAuthPath({ pending: '', redirect: '', fallback: '/admin' })).toBe('/admin');
  });

  it('keeps a real return path', () => {
    expect(resolvePostAuthPath({
      pending: null,
      redirect: '/checkout',
      fallback: '/',
    })).toBe('/checkout');
  });
});
