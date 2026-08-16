import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { post } = vi.hoisted(() => ({
  post: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: { post },
}));

function fbqCalls() {
  return Array.from(window.fbq?.queue || [], (entry) => Array.from(entry));
}

async function loadService(pixelId = '1234567890') {
  vi.resetModules();
  window.__APP__ = { metaPixelId: pixelId };
  return import('./metaPixel');
}

describe('metaPixel service', () => {
  beforeEach(() => {
    post.mockReset();
    post.mockResolvedValue({ data: { ok: true } });
    document.head.innerHTML = '';
    document.cookie = '_fbp=; Max-Age=0; path=/';
    document.cookie = '_fbc=; Max-Age=0; path=/';
    delete window.__APP__;
    delete window.fbq;
    delete window._fbq;
    vi.stubGlobal('crypto', { randomUUID: () => 'evt-test-1' });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('initializes the Meta Pixel once with the configured pixel id', async () => {
    const { initMetaPixel } = await loadService('987654321');

    initMetaPixel();
    initMetaPixel();

    const script = document.head.querySelector('script[src="https://connect.facebook.net/en_US/fbevents.js"]');
    expect(script).toBeTruthy();
    expect(document.head.querySelectorAll('script[src="https://connect.facebook.net/en_US/fbevents.js"]')).toHaveLength(1);
    expect(fbqCalls()).toEqual([
      ['set', 'autoConfig', false, '987654321'],
      ['init', '987654321'],
    ]);
  });

  it('tracks browser and CAPI events with a shared event id', async () => {
    document.cookie = '_fbp=fb.1.123';
    document.cookie = '_fbc=fb.1.click';
    const { trackMetaEvent } = await loadService('1234567890');

    const eventId = trackMetaEvent('ViewContent', {
      content_ids: ['42'],
      content_type: 'product',
      currency: 'INR',
      value: 499,
    });

    expect(eventId).toBe('evt-test-1');
    expect(fbqCalls()).toContainEqual([
      'track',
      'ViewContent',
      {
        content_ids: ['42'],
        content_type: 'product',
        currency: 'INR',
        value: 499,
      },
      { eventID: 'evt-test-1' },
    ]);
    expect(post).toHaveBeenCalledWith('/meta/events', expect.objectContaining({
      event_name: 'ViewContent',
      event_id: 'evt-test-1',
      custom_data: expect.objectContaining({ content_ids: ['42'] }),
      fbp: 'fb.1.123',
      fbc: 'fb.1.click',
    }));
  });

  it('does nothing when no pixel id is configured', async () => {
    const { trackMetaEvent } = await loadService('');

    expect(trackMetaEvent('PageView')).toBe('');
    expect(window.fbq).toBeUndefined();
    expect(document.head.querySelector('script[src="https://connect.facebook.net/en_US/fbevents.js"]')).toBeNull();
    expect(post).not.toHaveBeenCalled();
  });
});