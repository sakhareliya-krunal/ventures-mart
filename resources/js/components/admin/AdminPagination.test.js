import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AdminPagination from './AdminPagination.vue';

vi.mock('@lucide/vue', () => ({
  ChevronLeft: { template: '<svg aria-hidden="true" />' },
  ChevronRight: { template: '<svg aria-hidden="true" />' },
}));

let frames;
const wrappers = [];
function pager(props = {}) {
  const wrapper = mount(AdminPagination, { props: { page: 5, lastPage: 6, total: 115, from: 81, to: 100, ...props } });
  wrappers.push(wrapper);
  return wrapper;
}
function numbers(wrapper) {
  return wrapper.findAll('.admin-pagination > .admin-pagination__controls .admin-pagination__page').map(button => button.text());
}
async function layout() {
  await nextTick();
  const queued = frames.splice(0);
  for (const callback of queued) await callback();
  await nextTick();
}
beforeEach(() => {
  frames = [];
  vi.stubGlobal('innerWidth', 375);
  vi.spyOn(window, 'requestAnimationFrame').mockImplementation(callback => { frames.push(callback); return frames.length; });
  vi.spyOn(window, 'cancelAnimationFrame').mockImplementation(() => {});
});
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.unstubAllGlobals(); });

describe('responsive admin pagination', () => {
  it('shows three pages around page five without ellipses', () => {
    const wrapper = pager();
    expect(numbers(wrapper)).toEqual(['4', '5', '6']);
    expect(wrapper.find('[aria-current="page"]').text()).toBe('5');
    expect(wrapper.find('.admin-pagination__range').text()).toContain('115');
    expect(wrapper.find('.admin-pagination__measure').attributes('aria-hidden')).toBe('true');
    expect(wrapper.find('.admin-pagination__measure').attributes()).toHaveProperty('inert');
  });
  it('shifts the window at the first and last page and disables boundary navigation', async () => {
    const wrapper = pager({ page: 1 });
    expect(numbers(wrapper)).toEqual(['1', '2', '3']);
    expect(wrapper.find('[aria-label="Previous page"]').attributes()).toHaveProperty('disabled');
    await wrapper.setProps({ page: 6 });
    expect(numbers(wrapper)).toEqual(['4', '5', '6']);
    expect(wrapper.find('[aria-label="Next page"]').attributes()).toHaveProperty('disabled');
  });
  it('emits both existing events for next, previous and direct selection', async () => {
    const wrapper = pager();
    await wrapper.find('[aria-label="Next page"]').trigger('click');
    await wrapper.find('[aria-label="Previous page"]').trigger('click');
    await wrapper.find('[aria-label="Page 4"]').trigger('click');
    await wrapper.find('[aria-label="Page 5"]').trigger('click');
    expect(wrapper.emitted('page')).toEqual([[6], [4], [4]]);
    expect(wrapper.emitted('update:page')).toEqual([[6], [4], [4]]);
  });
  it('hides empty pagination and shows only the summary for a single page', async () => {
    const wrapper = pager({ total: 0 });
    expect(wrapper.find('nav').exists()).toBe(false);
    await wrapper.setProps({ total: 1, page: 1, lastPage: 1 });
    expect(wrapper.find('.admin-pagination__range').exists()).toBe(true);
    expect(wrapper.find('.admin-pagination__controls').exists()).toBe(false);
  });
  it('preserves the desktop page window when it fits', async () => {
    vi.stubGlobal('innerWidth', 1280);
    vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(800);
    vi.spyOn(HTMLElement.prototype, 'scrollWidth', 'get').mockReturnValue(600);
    const wrapper = pager({ page: 50, lastPage: 100 });
    await layout();
    expect(wrapper.classes()).not.toContain('admin-pagination--compact');
    expect(numbers(wrapper)).toEqual(['1', '48', '49', '50', '51', '52', '100']);
  });
  it('falls back to the active page for a narrow container and restores three after resizing', async () => {
    let width = 220;
    vi.stubGlobal('innerWidth', 1280);
    vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockImplementation(() => width);
    vi.spyOn(HTMLElement.prototype, 'scrollWidth', 'get').mockImplementation(function () {
      return this.closest('.admin-pagination__measure') ? 600 : 300;
    });
    const wrapper = pager({ page: 1234, lastPage: 9999 });
    await layout();
    expect(wrapper.classes()).toContain('admin-pagination--compact');
    expect(numbers(wrapper)).toEqual(['1234']);
    width = 400;
    window.dispatchEvent(new Event('resize'));
    await layout();
    expect(numbers(wrapper)).toEqual(['1233', '1234', '1235']);
  });
  it('disconnects the observer and removes the resize listener', () => {
    const disconnect = vi.fn();
    vi.stubGlobal('ResizeObserver', class { observe = vi.fn(); unobserve = vi.fn(); disconnect = disconnect; });
    const remove = vi.spyOn(window, 'removeEventListener');
    const wrapper = pager();
    wrapper.unmount();
    wrappers.pop();
    expect(disconnect).toHaveBeenCalledOnce();
    expect(remove).toHaveBeenCalledWith('resize', expect.any(Function));
  });
});
