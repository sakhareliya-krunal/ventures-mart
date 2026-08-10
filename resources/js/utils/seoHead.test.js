import { afterEach, describe, expect, it, vi } from 'vitest';
import { safePublicImageUrl } from './seoHead';

describe('safePublicImageUrl', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('normalizes bare and root-relative public asset paths', () => {
    expect(safePublicImageUrl('storage/products/a/cover.webp')).toBe(
      '/storage/products/a/cover.webp',
    );
    expect(safePublicImageUrl('/storage/products/a/cover.webp')).toBe(
      '/storage/products/a/cover.webp',
    );
    expect(safePublicImageUrl('images/blog/guide.jpg')).toBe('/images/blog/guide.jpg');
  });

  it('converts same-origin absolute URLs to root-relative paths', () => {
    vi.stubGlobal('window', {
      location: {
        origin: 'http://127.0.0.1:8000',
        protocol: 'http:',
      },
    });

    expect(
      safePublicImageUrl('http://127.0.0.1:8000/storage/products/uuid/cover.webp'),
    ).toBe('/storage/products/uuid/cover.webp');
  });

  it('converts absolute public-asset URLs to root-relative paths', () => {
    expect(
      safePublicImageUrl('https://cdn.example.test/storage/products/uuid/cover.webp'),
    ).toBe('/storage/products/uuid/cover.webp');
  });

  it('keeps external https images that are not public asset roots', () => {
    expect(safePublicImageUrl('https://cdn.example.test/covers/story.jpg')).toBe(
      'https://cdn.example.test/covers/story.jpg',
    );
  });

  it('rejects empty and unsafe values', () => {
    expect(safePublicImageUrl('')).toBe('');
    expect(safePublicImageUrl('javascript:alert(1)')).toBe('');
    expect(safePublicImageUrl(null)).toBe('');
  });
});
