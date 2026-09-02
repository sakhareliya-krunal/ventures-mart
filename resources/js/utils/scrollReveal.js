const REVEAL_SELECTORS = [
  '.page-section',
  '.page-section--soft',
  '.section-header',
  '.page-title-row',
  '.page-hero',
  '.static-shell__breadcrumb',
  '.static-shell__nav',
  '.static-shell__content',
  '.promo-band',
  '.service-strip',
  '.service-item',
  '.product-card',
  '.product-grid',
  '.category-card',
  '.category-pillar',
  '.category-tabbar',
  '.home-curated',
  '.home-curated__header',
  '.home-best-sellers',
  '.home-best-sellers__header',
  '.home-category-products',
  '.home-category-products__header',
  '.home-video',
  '.home-video__header',
  '.home-video__card',
  '.home-why__intro',
  '.home-why__item',
  '.home-benefits__header',
  '.home-benefits__card',
  '.about-commitment__stat',
  '.about-heritage__media',
  '.about-heritage__copy',
  '.about-commitment__header',
  '.about-gift-cta__inner',
  '.home-trust__intro',
  '.home-trust__quote',
  '.shop-layout',
  '.shop-toolbar',
  '.shop-page__explore',
  '.product-detail__gallery',
  '.product-detail__copy',
  '.product-detail__back',
  '.product-detail__faqs',
  '.product-detail__reviews',
  '.product-detail__links',
  '.product-detail-tabs',
  '.product-detail__full-description',
  '.product-detail__related',
  '.product-review-card',
  '.product-review-form',
  '.summary-panel',
  '.form-panel',
  '.auth-panel',
  '.cart-page',
  '.checkout-page',
  '.checkout-panel',
  '.checkout-layout',
  '.checkout-addresses__option',
  '.checkout-payment__option',
  '.contact-section-intro',
  '.contact-channel',
  '.contact-form-panel',
  '.wishlist-page',
  '.profile-identity__main',
  '.profile-identity__actions',
  '.profile-block',
  '.address-card',
  '.profile-card',
  '.profile-actions',
  '.orders-list',
  '.order-card',
  '.order-confirm-card',
  '.order-confirm-facts',
  '.order-confirm-items',
  '.order-confirm-actions',
  '.order-track-summary',
  '.order-track-hero',
  '.order-track-card',
  '.order-track-timeline__step',
  '.order-line',
  '.blog-card',
  '.blog-index__section-heading',
  '.article-premium__hero-copy',
  '.article-premium__toc',
  '.blog-article__header',
  '.blog-article__cover',
  '.blog-prose',
  '.article-premium__section',
  '.article-premium__close',
  '.article-premium__intro',
  '.static-page',
  '.error-status__card',
  '.empty-state',
].join(', ');

const SOFT_SELECTORS = [
  '.product-card',
  '.category-card',
  '.category-pillar',
  '.service-item',
  '.home-video__card',
  '.about-commitment__stat',
  '.about-heritage__media',
  '.about-heritage__copy',
  '.about-commitment__header',
  '.about-gift-cta__inner',
  '.contact-channel',
  '.contact-form-panel',
  '.home-why__item',
  '.home-benefits__card',
  '.home-trust__quote',
  '.summary-panel',
  '.form-panel',
  '.checkout-addresses__option',
  '.checkout-payment__option',
  '.profile-card',
  '.profile-block',
  '.address-card',
  '.order-card',
  '.order-confirm-card',
  '.order-track-card',
  '.order-line',
  '.blog-card',
  '.article-premium__section',
  '.error-status__card',
].join(', ');

const EXCLUDED_SELECTORS = [
  '[hidden]',
  '[aria-hidden="true"]',
  '.admin-layout',
  '.admin-panel',
  '.admin-modal',
  '.mobile-drawer',
  '.cart-tray',
  '.filters-dialog',
  '.confirm-dialog',
  '.review-dialog',
  '.inventory-history',
  '.skeleton-card',
  '.button-dots',
].join(', ');

const VIEWPORT_MARGIN = 100;
const MAX_STAGGER_INDEX = 5;
const REFRESH_DELAY = 50;
const POST_MUTATION_REFRESH_DELAY = 220;

let observer = null;
let mutationObserver = null;
let refreshTimer = null;
let mainEl = null;

function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function isCoarsePointer() {
  return window.matchMedia('(hover: none), (pointer: coarse)').matches;
}

function shouldRevealImmediately() {
  return prefersReducedMotion() || isCoarsePointer();
}

function markRevealed(el) {
  el.classList.add('is-revealed');
}

function isElementInViewport(el) {
  const rect = el.getBoundingClientRect();
  const viewHeight = window.innerHeight || document.documentElement.clientHeight;
  const viewWidth = window.innerWidth || document.documentElement.clientWidth;

  return (
    rect.bottom >= -VIEWPORT_MARGIN &&
    rect.right >= -VIEWPORT_MARGIN &&
    rect.top <= viewHeight + VIEWPORT_MARGIN &&
    rect.left <= viewWidth + VIEWPORT_MARGIN
  );
}

function isExcluded(el) {
  return el.matches(EXCLUDED_SELECTORS) || Boolean(el.closest(EXCLUDED_SELECTORS));
}

function prepareElement(el) {
  if (!(el instanceof HTMLElement) || isExcluded(el)) {
    return false;
  }

  if (el.dataset.revealObserved === '1') {
    return false;
  }

  el.dataset.revealObserved = '1';
  el.classList.add('reveal');

  if (el.matches(SOFT_SELECTORS)) {
    el.classList.add('reveal--soft');
  }

  if (shouldRevealImmediately()) {
    markRevealed(el);
    return false;
  }

  return true;
}

function assignRevealDelays(nodes) {
  if (shouldRevealImmediately()) {
    return;
  }

  const groups = new Map();

  nodes.forEach((el) => {
    if (!(el instanceof HTMLElement) || !el.parentElement || isExcluded(el)) {
      return;
    }

    const group = groups.get(el.parentElement) || [];
    group.push(el);
    groups.set(el.parentElement, group);
  });

  groups.forEach((group) => {
    group.forEach((el, index) => {
      const delay = Math.min(index, MAX_STAGGER_INDEX) * 55;
      el.style.setProperty('--reveal-delay', `${delay}ms`);
    });
  });
}

function ensureObserver() {
  if (observer || shouldRevealImmediately() || !('IntersectionObserver' in window)) {
    return observer;
  }

  observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        markRevealed(entry.target);
        observer?.unobserve(entry.target);
      });
    },
    {
      threshold: 0.01,
      rootMargin: '90px 0px 110px 0px',
    },
  );

  return observer;
}

export function refreshScrollReveal(root = document) {
  const scope = root instanceof Element || root instanceof Document ? root : document;
  const nodes = scope.querySelectorAll(REVEAL_SELECTORS);
  assignRevealDelays(nodes);

  if (shouldRevealImmediately() || !('IntersectionObserver' in window)) {
    nodes.forEach((el) => {
      prepareElement(el);
      markRevealed(el);
    });
    return;
  }

  const io = ensureObserver();
  nodes.forEach((el) => {
    if (!prepareElement(el)) {
      return;
    }

    if (isElementInViewport(el)) {
      markRevealed(el);
      return;
    }

    io?.observe(el);
  });
}

function scheduleRefresh() {
  window.clearTimeout(refreshTimer);
  refreshTimer = window.setTimeout(() => {
    refreshScrollReveal(mainEl || document);
    refreshTimer = window.setTimeout(() => {
      refreshScrollReveal(mainEl || document);
    }, POST_MUTATION_REFRESH_DELAY);
  }, REFRESH_DELAY);
}

export function initScrollReveal(root) {
  mainEl = root instanceof Element ? root : document.querySelector('main');
  refreshScrollReveal(mainEl || document);

  if (mutationObserver || !mainEl || shouldRevealImmediately()) {
    return;
  }

  mutationObserver = new MutationObserver(() => {
    scheduleRefresh();
  });

  mutationObserver.observe(mainEl, {
    childList: true,
    subtree: true,
  });
}

export function destroyScrollReveal() {
  window.clearTimeout(refreshTimer);
  observer?.disconnect();
  mutationObserver?.disconnect();
  observer = null;
  mutationObserver = null;
  mainEl = null;
}
