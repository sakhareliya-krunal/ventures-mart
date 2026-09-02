import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import {
  destroyScrollReveal,
  initScrollReveal,
  refreshScrollReveal,
} from './scrollReveal';

let reducedMotion = false;
let coarsePointer = false;
let observerInstances = [];

class MockIntersectionObserver {
  constructor(callback, options) {
    this.callback = callback;
    this.options = options;
    this.observed = [];
    this.unobserve = vi.fn((target) => {
      this.observed = this.observed.filter((item) => item !== target);
    });
    this.disconnect = vi.fn(() => {
      this.observed = [];
    });
    observerInstances.push(this);
  }

  observe(target) {
    this.observed.push(target);
  }

  reveal(target) {
    this.callback([{ target, isIntersecting: true }]);
  }
}

function mockMatchMedia() {
  window.matchMedia = vi.fn((query) => ({
    matches: query === '(prefers-reduced-motion: reduce)'
      ? reducedMotion
      : query === '(hover: none), (pointer: coarse)'
        ? coarsePointer
        : false,
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }));
}

function placeBelowViewport(el) {
  el.getBoundingClientRect = vi.fn(() => ({
    top: window.innerHeight + 400,
    right: 100,
    bottom: window.innerHeight + 520,
    left: 0,
    width: 100,
    height: 120,
  }));
}

beforeEach(() => {
  reducedMotion = false;
  coarsePointer = false;
  observerInstances = [];
  document.body.innerHTML = '';
  mockMatchMedia();
  vi.stubGlobal('IntersectionObserver', MockIntersectionObserver);
  vi.useFakeTimers();
});

afterEach(() => {
  destroyScrollReveal();
  vi.useRealTimers();
  vi.unstubAllGlobals();
  document.body.innerHTML = '';
});

describe('scrollReveal', () => {
  test('prepares and reveals storefront selectors through intersection', () => {
    document.body.innerHTML = `
      <main>
        <section class="contact-section-intro"></section>
        <a class="contact-channel"></a>
        <div class="contact-form-panel"></div>
        <div class="about-heritage__media"></div>
        <div class="static-shell__content"></div>
        <div class="error-status__card"></div>
      </main>
    `;

    const nodes = [...document.querySelectorAll('main > *')];
    nodes.forEach(placeBelowViewport);

    refreshScrollReveal(document.querySelector('main'));

    expect(observerInstances).toHaveLength(1);
    nodes.forEach((node) => {
      expect(node.classList.contains('reveal')).toBe(true);
      expect(node.classList.contains('is-revealed')).toBe(false);
    });
    expect(document.querySelector('.contact-channel').classList.contains('reveal--soft')).toBe(true);
    expect(document.querySelector('.error-status__card').classList.contains('reveal--soft')).toBe(true);

    observerInstances[0].reveal(nodes[0]);

    expect(nodes[0].classList.contains('is-revealed')).toBe(true);
    expect(observerInstances[0].unobserve).toHaveBeenCalledWith(nodes[0]);
  });

  test('skips admin, overlay, and skeleton content', () => {
    document.body.innerHTML = `
      <main>
        <div class="admin-panel"><div class="product-card"></div></div>
        <div class="cart-tray"><div class="summary-panel"></div></div>
        <div class="skeleton-card product-card"></div>
        <div class="product-card"></div>
      </main>
    `;

    const visibleCard = document.querySelector('main > .product-card:not(.skeleton-card)');
    placeBelowViewport(visibleCard);

    refreshScrollReveal(document.querySelector('main'));

    expect(document.querySelector('.admin-panel .product-card').classList.contains('reveal')).toBe(false);
    expect(document.querySelector('.cart-tray .summary-panel').classList.contains('reveal')).toBe(false);
    expect(document.querySelector('.skeleton-card').classList.contains('reveal')).toBe(false);
    expect(visibleCard.classList.contains('reveal')).toBe(true);
  });

  test('reveals immediately when reduced motion is enabled', () => {
    reducedMotion = true;
    mockMatchMedia();
    document.body.innerHTML = '<main><div class="checkout-payment__option"></div></main>';
    const option = document.querySelector('.checkout-payment__option');
    placeBelowViewport(option);

    refreshScrollReveal(document.querySelector('main'));

    expect(option.classList.contains('reveal')).toBe(true);
    expect(option.classList.contains('reveal--soft')).toBe(true);
    expect(option.classList.contains('is-revealed')).toBe(true);
    expect(observerInstances).toHaveLength(0);
  });

  test('reveals touch-device card content immediately without staggered observation', () => {
    coarsePointer = true;
    mockMatchMedia();
    document.body.innerHTML = '<main><article class="product-card"></article></main>';
    const card = document.querySelector('.product-card');
    placeBelowViewport(card);

    refreshScrollReveal(document.querySelector('main'));

    expect(card.classList.contains('reveal')).toBe(true);
    expect(card.classList.contains('reveal--soft')).toBe(true);
    expect(card.classList.contains('is-revealed')).toBe(true);
    expect(card.style.getPropertyValue('--reveal-delay')).toBe('');
    expect(observerInstances).toHaveLength(0);
  });

  test('observes dynamic storefront content inserted after init', async () => {
    document.body.innerHTML = '<main><section class="page-section"></section></main>';
    const main = document.querySelector('main');

    initScrollReveal(main);

    const dynamic = document.createElement('article');
    dynamic.className = 'order-card';
    placeBelowViewport(dynamic);
    main.append(dynamic);

    await Promise.resolve();
    vi.advanceTimersByTime(50);

    expect(dynamic.classList.contains('reveal')).toBe(true);
    expect(dynamic.classList.contains('reveal--soft')).toBe(true);
  });
});