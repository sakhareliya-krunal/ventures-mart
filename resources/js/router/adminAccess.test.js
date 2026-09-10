import { describe, expect, it } from 'vitest';
import { shouldRedirectAdminToPanel } from './adminAccess';

describe('admin storefront access', () => {
  it('allows guests to visit storefront routes', () => {
    expect(shouldRedirectAdminToPanel({ name: 'contact', path: '/contact' }, false)).toBe(false);
    expect(shouldRedirectAdminToPanel({ name: 'home', path: '/' }, false)).toBe(false);
  });

  it('allows admins to open the storefront contact page', () => {
    expect(shouldRedirectAdminToPanel({ name: 'contact', path: '/contact' }, true)).toBe(false);
  });

  it('keeps admin and authentication routes available to admins', () => {
    expect(shouldRedirectAdminToPanel({ name: 'admin-dashboard', path: '/admin' }, true)).toBe(false);
    expect(shouldRedirectAdminToPanel({ name: 'login', path: '/login' }, true)).toBe(false);
  });

  it('redirects admins away from other storefront routes', () => {
    expect(shouldRedirectAdminToPanel({ name: 'home', path: '/' }, true)).toBe(true);
    expect(shouldRedirectAdminToPanel({ name: 'about', path: '/about' }, true)).toBe(true);
  });
});
