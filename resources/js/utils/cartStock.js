/**
 * Resolve a product stock number from a cart line or product payload.
 * @param {{ product?: { stock?: number }, stock?: number }|null|undefined} productOrItem
 * @returns {number} Finite stock, or NaN when unknown/missing.
 */
export function productStock(productOrItem) {
  const stock = Number(productOrItem?.product?.stock ?? productOrItem?.stock);
  return Number.isFinite(stock) ? stock : Number.NaN;
}

/**
 * @param {{ product?: { stock?: number }, stock?: number }|null|undefined} productOrItem
 */
export function isOutOfStockProduct(productOrItem) {
  const stock = productStock(productOrItem);
  return Number.isFinite(stock) && stock <= 0;
}

/**
 * Max purchasable quantity for a cart line.
 * OOS (stock <= 0) → 0; known positive stock → min(99, stock); unknown → 99.
 * @param {{ product?: { stock?: number } }|null|undefined} item
 */
export function maxCartQuantityFor(item) {
  const stock = Number(item?.product?.stock);
  if (!Number.isFinite(stock)) {
    return 99;
  }
  if (stock <= 0) {
    return 0;
  }
  return Math.min(99, stock);
}
