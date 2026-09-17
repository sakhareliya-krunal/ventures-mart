import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import HomeVideoSection from './HomeVideoSection.vue';

describe('HomeVideoSection', () => {
  let intersectionCallback;
  const observe = vi.fn();
  const unobserve = vi.fn();
  const disconnect = vi.fn();

  beforeEach(() => {
    observe.mockClear();
    unobserve.mockClear();
    disconnect.mockClear();
    window.IntersectionObserver = class {
      constructor(callback) {
        intersectionCallback = callback;
        this.observe = observe;
        this.unobserve = unobserve;
        this.disconnect = disconnect;
      }
    };
    vi.spyOn(HTMLMediaElement.prototype, 'load').mockImplementation(() => {});
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    delete window.IntersectionObserver;
  });

  it('defers video sources until the section approaches the viewport', async () => {
    const wrapper = mount(HomeVideoSection);
    const media = wrapper.findAll('video');

    expect(media).toHaveLength(4);
    expect(media.every((video) => video.attributes('src') === undefined)).toBe(true);
    expect(media.every((video) => video.attributes('preload') === 'none')).toBe(true);

    const first = media[0].element;
    intersectionCallback([{ target: first, isIntersecting: true }]);
    await wrapper.vm.$nextTick();

    expect(first.getAttribute('src')).toBe('/videos/home/home-video-4.mp4');
    expect(unobserve).toHaveBeenCalledWith(first);

    wrapper.unmount();
    expect(disconnect).toHaveBeenCalled();
  });
});
