import { onBeforeUnmount, watch } from 'vue';
import { lockScroll, unlockScroll } from '@/utils/scrollLock';

export function useScrollLock(id, source) {
  watch(
    source,
    (locked) => {
      if (locked) {
        lockScroll(id);
      } else {
        unlockScroll(id);
      }
    },
    { immediate: true },
  );

  onBeforeUnmount(() => {
    unlockScroll(id);
  });
}
