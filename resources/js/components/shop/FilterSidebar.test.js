import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, test } from 'vitest';
import FilterSidebar from '@/components/shop/FilterSidebar.vue';
import { isScrollLocked, resetScrollLock } from '@/utils/scrollLock';

describe('FilterSidebar scroll lock', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    resetScrollLock();
  });

  afterEach(() => {
    resetScrollLock();
    document.body.innerHTML = '';
  });

  test('locks scroll when the mobile filter drawer opens and unlocks on close', async () => {
    const wrapper = mount(FilterSidebar, {
      attachTo: document.body,
      props: {
        query: '',
        category: '',
        minPrice: 0,
        maxPrice: 2000,
        priceFloor: 0,
        priceCeiling: 2000,
        sort: 'featured',
        categories: [],
      },
    });

    await wrapper.find('.shop-toolbar__filters').trigger('click');
    await nextTick();
    await flushPromises();

    expect(document.querySelector('.filters-dialog')).not.toBeNull();
    expect(isScrollLocked()).toBe(true);

    await document.querySelector('.filters-dialog__backdrop').click();
    await nextTick();

    expect(document.querySelector('.filters-dialog')).toBeNull();
    expect(isScrollLocked()).toBe(false);

    wrapper.unmount();
  });
});
