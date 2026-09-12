import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AdminAsyncState from './AdminAsyncState.vue';
import { loadingRegionGeometry } from '@/composables/useAdminLoadingRegion';

const geometry = { top: 350, scrollTop: 0, boundaryTop: 68, boundaryBottom: 800, viewportTop: 0, viewportBottom: 800, bottomPadding: 32 };
afterEach(() => { vi.unstubAllGlobals(); });

describe('admin loading region', () => {
    it('centers below controls with container padding accounted for', () => {
        expect(loadingRegionGeometry(geometry)).toEqual({ height: 418, center: 209 });
    });
    it('keeps its height stable when the content scrolls', () => {
        const scrolled = loadingRegionGeometry({ ...geometry, top: 50, scrollTop: 300 });
        expect(scrolled.height).toBe(418);
        expect(scrolled.center).toBe(218);
    });
    it('centers within a reduced visual viewport', () => {
        expect(loadingRegionGeometry({ ...geometry, viewportBottom: 600 }).center).toBe(125);
    });
    it('provides a compact region when controls fill the screen', () => {
        expect(loadingRegionGeometry({ ...geometry, top: 900 })).toEqual({ height: 112, center: 56 });
    });
    it('uses the dialog boundary rather than full viewport height', () => {
        expect(loadingRegionGeometry({ ...geometry, top: 250, boundaryBottom: 550, bottomPadding: 16 })).toEqual({ height: 284, center: 142 });
    });
    it('renders the shared logo and preserves error, retry, empty, and loaded states', async () => {
        const wrapper = mount(AdminAsyncState, { props: { loading: true, label: 'Loading returns' }, slots: { default: 'Loaded content' } });
        expect(wrapper.find('[role="status"]').attributes('aria-label')).toBe('Loading returns');
        expect(wrapper.find('.admin-loading').attributes('aria-busy')).toBe('true');
        expect(wrapper.find('img').attributes('src')).toBe('/images/venturesmart-compact-loader.svg');
        expect(wrapper.find('.vm-loader--page').exists()).toBe(false);
        await wrapper.setProps({ loading: false, error: 'Network unavailable' });
        expect(wrapper.find('[role="alert"]').text()).toContain('Network unavailable');
        await wrapper.find('button').trigger('click');
        expect(wrapper.emitted('retry')).toHaveLength(1);
        await wrapper.setProps({ error: '', empty: true, searching: true });
        await wrapper.find('button').trigger('click');
        expect(wrapper.emitted('reset')).toHaveLength(1);
        await wrapper.setProps({ empty: false });
        expect(wrapper.text()).toBe('Loaded content');
        wrapper.unmount();
    });
    it('batches resize work and cleans up observers and listeners', () => {
        const callbacks = [];
        vi.spyOn(window, 'requestAnimationFrame').mockImplementation(callback => { callbacks.push(callback); return callbacks.length; });
        const cancel = vi.spyOn(window, 'cancelAnimationFrame').mockImplementation(() => {});
        const disconnect = vi.fn();
        const observe = vi.fn();
        vi.stubGlobal('ResizeObserver', class { observe = observe; disconnect = disconnect; });
        const host = document.createElement('main');
        host.className = 'admin-content';
        document.body.append(host);
        const remove = vi.spyOn(host, 'removeEventListener');
        const wrapper = mount(AdminAsyncState, { attachTo: host, props: { loading: true } });
        window.dispatchEvent(new Event('resize'));
        expect(callbacks).toHaveLength(1);
        expect(observe).toHaveBeenCalled();
        wrapper.unmount();
        expect(cancel).toHaveBeenCalledWith(1);
        expect(disconnect).toHaveBeenCalledOnce();
        expect(remove).toHaveBeenCalledWith('scroll', expect.any(Function));
        host.remove();
    });
});
