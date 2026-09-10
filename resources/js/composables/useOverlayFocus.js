import { nextTick, onBeforeUnmount, watch } from 'vue';

const overlays = [];
export function useOverlayFocus(open, panel, close) {
  const token = {};
  let previous;
  const focusable = () => [...(panel.value?.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]') || [])].filter((el) => !el.closest('[hidden],[inert]') && el.getClientRects().length);
  function keydown(event) {
    if (overlays.at(-1) !== token) return;
    if (event.key === 'Escape') { event.preventDefault(); close(); }
    if (event.key !== 'Tab') return;
    const elements = focusable();
    const first = elements[0] || panel.value;
    const last = elements.at(-1) || first;
    if (!elements.length || (event.shiftKey && (document.activeElement === first || !panel.value?.contains(document.activeElement)))) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && (document.activeElement === last || !panel.value?.contains(document.activeElement))) { event.preventDefault(); first?.focus(); }
  }
  function release() {
    const index = overlays.indexOf(token);
    if (index >= 0) overlays.splice(index, 1);
    document.removeEventListener('keydown', keydown);
    if (previous?.isConnected) previous.focus();
    previous = null;
  }
  watch(open, async (visible) => {
    if (!visible) { release(); return; }
    previous = document.activeElement;
    overlays.push(token);
    document.addEventListener('keydown', keydown);
    await nextTick();
    if (open()) (focusable()[0] || panel.value)?.focus();
  }, { immediate: true });
  onBeforeUnmount(release);
}
