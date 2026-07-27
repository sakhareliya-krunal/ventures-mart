const STORAGE_KEY = 'pending_user_action';
const TTL_MS = 2 * 60 * 60 * 1000; // 2 hours

/** @typedef {'wishlist.add'} PendingActionType */

/**
 * @typedef {object} PendingUserAction
 * @property {PendingActionType} type
 * @property {number} productId
 * @property {number|null} [variantId]
 * @property {string} returnUrl
 * @property {number} createdAt
 * @property {number} expiresAt
 */

const KNOWN_TYPES = new Set(['wishlist.add']);

/**
 * @param {unknown} action
 * @returns {action is PendingUserAction}
 */
export function isValid(action) {
  if (!action || typeof action !== 'object') return false;

  const item = /** @type {Record<string, unknown>} */ (action);
  const productId = Number(item.productId);
  const createdAt = Number(item.createdAt);
  const expiresAt = Number(item.expiresAt);
  const returnUrl = typeof item.returnUrl === 'string' ? item.returnUrl.trim() : '';

  if (!KNOWN_TYPES.has(/** @type {string} */ (item.type))) return false;
  if (!Number.isFinite(productId) || productId <= 0) return false;
  if (!returnUrl || !returnUrl.startsWith('/')) return false;
  if (!Number.isFinite(createdAt) || !Number.isFinite(expiresAt)) return false;
  if (Date.now() > expiresAt) return false;

  if (item.variantId != null && item.variantId !== '') {
    const variantId = Number(item.variantId);
    if (!Number.isFinite(variantId) || variantId <= 0) return false;
  }

  return true;
}

/**
 * @param {Partial<PendingUserAction> & { type: PendingActionType, productId: number|string, returnUrl?: string }} input
 * @returns {PendingUserAction|null}
 */
function normalize(input) {
  const now = Date.now();
  const productId = Number(input.productId);
  const returnUrl = String(input.returnUrl || '/').trim() || '/';
  const variantRaw = input.variantId;
  const variantId =
    variantRaw == null || variantRaw === ''
      ? null
      : Number(variantRaw);

  /** @type {PendingUserAction} */
  const action = {
    type: input.type,
    productId,
    variantId: Number.isFinite(variantId) && variantId > 0 ? variantId : null,
    returnUrl: returnUrl.startsWith('/') ? returnUrl : '/',
    createdAt: now,
    expiresAt: now + TTL_MS,
  };

  return isValid(action) ? action : null;
}

function readRaw() {
  if (typeof sessionStorage === 'undefined') return null;

  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function writeRaw(action) {
  if (typeof sessionStorage === 'undefined') return;

  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(action));
  } catch {
    // Private mode / quota — ignore.
  }
}

export function clear() {
  if (typeof sessionStorage === 'undefined') return;

  try {
    sessionStorage.removeItem(STORAGE_KEY);
  } catch {
    // ignore
  }
}

/**
 * @param {Partial<PendingUserAction> & { type: PendingActionType, productId: number|string, returnUrl?: string }} input
 * @returns {PendingUserAction|null}
 */
export function stash(input) {
  const action = normalize(input);
  if (!action) {
    clear();
    return null;
  }

  writeRaw(action);
  return action;
}

/**
 * @returns {PendingUserAction|null}
 */
export function peek() {
  const action = readRaw();
  if (!isValid(action)) {
    clear();
    return null;
  }
  return /** @type {PendingUserAction} */ (action);
}

/**
 * Read and clear a valid pending action.
 * @returns {PendingUserAction|null}
 */
export function consume() {
  const action = peek();
  clear();
  return action;
}

const PendingUserAction = {
  stash,
  peek,
  consume,
  clear,
  isValid,
};

export default PendingUserAction;
