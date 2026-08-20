import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import {
  getActiveScrollLockIds,
  isScrollLocked,
  lockScroll,
  resetScrollLock,
  unlockScroll,
} from '@/utils/scrollLock';

function mockScrollPosition(y = 120) {
  let scrollY = y;

  Object.defineProperty(window, 'scrollY', {
    configurable: true,
    get: () => scrollY,
  });

  window.scrollTo = vi.fn((_x, nextY) => {
    if (typeof nextY === 'number') {
      scrollY = nextY;
    }
  });

  return {
    getScrollY: () => scrollY,
  };
}

describe('scrollLock', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    document.body.removeAttribute('style');
    document.documentElement.removeAttribute('style');
    document.documentElement.classList.remove('is-scroll-locked');
    document.body.classList.remove('is-scroll-locked');
    resetScrollLock();
    mockScrollPosition(120);
  });

  afterEach(() => {
    resetScrollLock();
    document.body.innerHTML = '';
    document.body.removeAttribute('style');
    document.documentElement.removeAttribute('style');
    document.documentElement.classList.remove('is-scroll-locked');
    document.body.classList.remove('is-scroll-locked');
  });

  test('applies scroll lock on first lock', () => {
    lockScroll('mobile-drawer');

    expect(isScrollLocked()).toBe(true);
    expect(getActiveScrollLockIds()).toEqual(['mobile-drawer']);
    expect(document.documentElement.classList.contains('is-scroll-locked')).toBe(true);
    expect(document.body.classList.contains('is-scroll-locked')).toBe(true);
    expect(document.body.style.position).toBe('fixed');
    expect(document.body.style.top).toBe('-120px');
  });

  test('keeps page locked until the last overlay closes', () => {
    lockScroll('admin-nav');
    lockScroll('confirm-dialog');

    unlockScroll('admin-nav');

    expect(isScrollLocked()).toBe(true);
    expect(getActiveScrollLockIds()).toEqual(['confirm-dialog']);
    expect(document.documentElement.classList.contains('is-scroll-locked')).toBe(true);

    unlockScroll('confirm-dialog');

    expect(isScrollLocked()).toBe(false);
    expect(getActiveScrollLockIds()).toEqual([]);
    expect(document.documentElement.classList.contains('is-scroll-locked')).toBe(false);
  });

  test('restores scroll position and inline styles after the final unlock', () => {
    document.body.setAttribute('style', 'color: red;');
    document.documentElement.setAttribute('style', 'color: blue;');
    const { getScrollY } = mockScrollPosition(120);

    lockScroll('cart-tray');
    unlockScroll('cart-tray');

    expect(document.body.getAttribute('style')).toBe('color: red;');
    expect(document.documentElement.getAttribute('style')).toBe('color: blue;');
    expect(window.scrollTo).toHaveBeenCalledWith(0, 120);
    expect(getScrollY()).toBe(120);
  });

  test('resetScrollLock clears orphaned state', () => {
    lockScroll('shop-filters');
    lockScroll('cart-tray');

    resetScrollLock();

    expect(isScrollLocked()).toBe(false);
    expect(getActiveScrollLockIds()).toEqual([]);
    expect(document.documentElement.classList.contains('is-scroll-locked')).toBe(false);
  });
});
