import { onBeforeUnmount, onMounted } from 'vue';

const MIN_HEIGHT = 112;
const BOUNDARY = '.admin-content, .admin-overlay__body, .inventory-history__body';

export function loadingRegionGeometry({ top, scrollTop, boundaryTop, boundaryBottom, viewportTop, viewportBottom, bottomPadding }) {
    // Use the unscrolled origin so scrolling cannot repeatedly grow the page.
    const height = Math.max(MIN_HEIGHT, boundaryBottom - bottomPadding - top - scrollTop);
    const visibleTop = Math.max(top, boundaryTop, viewportTop);
    const visibleBottom = Math.min(top + height, boundaryBottom - bottomPadding, viewportBottom);
    const center = visibleBottom > visibleTop ? (visibleTop + visibleBottom) / 2 - top : height / 2;
    return { height, center: Math.max(35, Math.min(height - 35, center)) };
}

export function useAdminLoadingRegion(root) {
    let frame = null;
    let observer;
    let boundary;
    let viewport;

    function measure() {
        frame = null;
        const el = root.value;
        if (!el || !boundary) return;
        const rect = el.getBoundingClientRect();
        const bounds = boundary.getBoundingClientRect();
        let bottomPadding = 0;
        for (let parent = el.parentElement; parent; parent = parent.parentElement) {
            const style = getComputedStyle(parent);
            bottomPadding += parseFloat(style.paddingBottom) || 0;
            bottomPadding += parseFloat(style.borderBottomWidth) || 0;
            if (parent === boundary) break;
        }
        const viewportTop = viewport?.offsetTop || 0;
        const geometry = loadingRegionGeometry({
            top: rect.top,
            scrollTop: boundary.scrollTop,
            boundaryTop: bounds.top + boundary.clientTop,
            boundaryBottom: bounds.top + boundary.clientTop + boundary.clientHeight,
            viewportTop,
            viewportBottom: viewportTop + (viewport?.height || window.innerHeight),
            bottomPadding,
        });
        el.style.setProperty('--admin-loading-height', `${geometry.height}px`);
        el.style.setProperty('--admin-loading-center', `${geometry.center}px`);
    }

    function schedule() {
        if (frame === null) frame = window.requestAnimationFrame(measure);
    }

    onMounted(() => {
        boundary = root.value?.closest(BOUNDARY);
        if (!boundary) return;
        viewport = window.visualViewport;
        if (typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(schedule);
            // Controls wrapping above the region can change its available space.
            for (let node = root.value; node && node !== boundary; node = node.parentElement) {
                for (const sibling of node.parentElement.children) observer.observe(sibling);
            }
            observer.observe(boundary);
        }
        boundary.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule);
        window.addEventListener('orientationchange', schedule);
        viewport?.addEventListener('resize', schedule);
        viewport?.addEventListener('scroll', schedule);
        schedule();
    });

    onBeforeUnmount(() => {
        if (frame !== null) window.cancelAnimationFrame(frame);
        observer?.disconnect();
        boundary?.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        window.removeEventListener('orientationchange', schedule);
        viewport?.removeEventListener('resize', schedule);
        viewport?.removeEventListener('scroll', schedule);
    });
}
