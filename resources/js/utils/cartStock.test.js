import { describe, expect, it } from 'vitest';
import { isOutOfStockProduct, maxCartQuantityFor, productStock } from './cartStock';

describe('cartStock', () => {
  it('treats stock 0 as out of stock with max quantity 0', () => {
    const item = { product: { stock: 0 } };
    expect(productStock(item)).toBe(0);
    expect(isOutOfStockProduct(item)).toBe(true);
    expect(maxCartQuantityFor(item)).toBe(0);
  });

  it('caps positive stock at 99', () => {
    expect(maxCartQuantityFor({ product: { stock: 5 } })).toBe(5);
    expect(maxCartQuantityFor({ product: { stock: 120 } })).toBe(99);
    expect(isOutOfStockProduct({ product: { stock: 5 } })).toBe(false);
  });

  it('falls back to 99 when stock is missing', () => {
    expect(maxCartQuantityFor({ product: {} })).toBe(99);
    expect(isOutOfStockProduct({ product: {} })).toBe(false);
    expect(Number.isNaN(productStock({ product: {} }))).toBe(true);
  });
});
