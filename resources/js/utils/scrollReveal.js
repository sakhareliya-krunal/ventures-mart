const REVEAL_SELECTORS = [
  '.promo-band',
  '.service-strip',
  '.product-card',
  '.category-card',
  '.category-pillar',
  '.home-why__item',
  '.home-benefits__card',
  '.about-commitment__stat',
  '.home-trust__quote',
  '.product-detail__gallery',
  '.product-detail__copy',
  '.page-hero',
  '.article-premium__section',
  '.article-premium__close',
  '.article-premium__intro',
  '.static-page',
  '.empty-state',
].join(', ');

const SOFT_SELECTORS =
  '.product-card, .category-card, .category-pillar, .about-commitment__stat, .home-why__item, .home-benefits__card, .home-trust__quote, .article-premium__section';
const VIEWPORT_MARGIN = 100;

let observer = null;
let mutationObserver = null;
let refreshTimer = null;
let mainEl = null;

function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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

function prepareElement(el) {
  if (!(el instanceof HTMLElement)) {
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

  if (prefersReducedMotion()) {
    markRevealed(el);
    return false;
  }

  return true;
}

function ensureObserver() {
  if (observer || prefersReducedMotion() || !('IntersectionObserver' in window)) {
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
      rootMargin: '80px 0px 80px 0px',
    },
  );

  return observer;
}

export function refreshScrollReveal(root = document) {
  const scope = root instanceof Element || root instanceof Document ? root : document;
  const nodes = scope.querySelectorAll(REVEAL_SELECTORS);

  if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
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
  }, 50);
}

export function initScrollReveal(root) {
  mainEl = root instanceof Element ? root : document.querySelector('main');
  refreshScrollReveal(mainEl || document);

  if (mutationObserver || !mainEl || prefersReducedMotion()) {
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
