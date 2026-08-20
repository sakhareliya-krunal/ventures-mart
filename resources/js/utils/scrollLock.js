const activeLocks = new Set();

let savedScrollY = 0;
let savedBodyStyle = '';
let savedHtmlStyle = '';

function getScrollbarWidth() {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return 0;
  }

  return Math.max(0, window.innerWidth - document.documentElement.clientWidth);
}

function applyLock() {
  if (typeof document === 'undefined') return;

  const { body, documentElement } = document;

  savedScrollY = window.scrollY;
  savedBodyStyle = body.getAttribute('style') || '';
  savedHtmlStyle = documentElement.getAttribute('style') || '';

  const scrollbarWidth = getScrollbarWidth();

  documentElement.classList.add('is-scroll-locked');
  body.classList.add('is-scroll-locked');

  body.style.position = 'fixed';
  body.style.top = `-${savedScrollY}px`;
  body.style.left = '0';
  body.style.right = '0';
  body.style.width = '100%';
  body.style.overflow = 'hidden';

  if (scrollbarWidth > 0) {
    body.style.paddingRight = `${scrollbarWidth}px`;
    documentElement.style.paddingRight = `${scrollbarWidth}px`;
  }
}

function releaseLock() {
  if (typeof document === 'undefined') return;

  const { body, documentElement } = document;

  documentElement.classList.remove('is-scroll-locked');
  body.classList.remove('is-scroll-locked');

  if (savedBodyStyle) {
    body.setAttribute('style', savedBodyStyle);
  } else {
    body.removeAttribute('style');
  }

  if (savedHtmlStyle) {
    documentElement.setAttribute('style', savedHtmlStyle);
  } else {
    documentElement.removeAttribute('style');
  }

  window.scrollTo(0, savedScrollY);
}

export function lockScroll(id) {
  if (!id || typeof document === 'undefined') return;

  const wasEmpty = activeLocks.size === 0;
  activeLocks.add(id);

  if (wasEmpty) {
    applyLock();
  }
}

export function unlockScroll(id) {
  if (!id || typeof document === 'undefined') return;

  activeLocks.delete(id);

  if (activeLocks.size === 0) {
    releaseLock();
  }
}

export function isScrollLocked() {
  return activeLocks.size > 0;
}

export function getActiveScrollLockIds() {
  return [...activeLocks];
}

export function resetScrollLock() {
  activeLocks.clear();

  if (typeof document === 'undefined') return;

  document.documentElement.classList.remove('is-scroll-locked');
  document.body.classList.remove('is-scroll-locked');

  if (savedBodyStyle) {
    document.body.setAttribute('style', savedBodyStyle);
  } else {
    document.body.removeAttribute('style');
  }

  if (savedHtmlStyle) {
    document.documentElement.setAttribute('style', savedHtmlStyle);
  } else {
    document.documentElement.removeAttribute('style');
  }

  savedScrollY = 0;
  savedBodyStyle = '';
  savedHtmlStyle = '';
}
