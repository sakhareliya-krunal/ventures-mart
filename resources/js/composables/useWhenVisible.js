import { onMounted, onUnmounted } from 'vue';

export function useWhenVisible(target, callback, options = {}) {
  let observer = null;
  let done = false;

  function run() {
    if (done) return;
    done = true;
    observer?.disconnect();
    observer = null;
    callback();
  }

  onMounted(() => {
    if (!target.value || typeof IntersectionObserver === 'undefined') {
      run();
      return;
    }

    observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        run();
      }
    }, {
      rootMargin: options.rootMargin || '360px 0px',
      threshold: options.threshold ?? 0.01,
    });

    observer.observe(target.value);
  });

  onUnmounted(() => {
    observer?.disconnect();
    observer = null;
  });
}
