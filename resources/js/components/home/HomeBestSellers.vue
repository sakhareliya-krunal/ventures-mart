<script setup>
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ProductCard from '@/components/product/ProductCard.vue';
import SkeletonCard from '@/components/ui/SkeletonCard.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const products = ref([]);
const loading = ref(false);
const error = ref('');
const railEl = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

let resizeObserver = null;

function observeRail() {
  if (!resizeObserver || !railEl.value) {
    return;
  }

  resizeObserver.disconnect();
  resizeObserver.observe(railEl.value);
}

function updateScrollButtons() {
  const rail = railEl.value;

  if (!rail) {
    canScrollLeft.value = false;
    canScrollRight.value = false;
    return;
  }

  const maxScrollLeft = rail.scrollWidth - rail.clientWidth;
  canScrollLeft.value = rail.scrollLeft > 4;
  canScrollRight.value = rail.scrollLeft < maxScrollLeft - 4;
}

function scrollRail(direction) {
  const rail = railEl.value;
  if (!rail) return;

  const distance = rail.clientWidth * 0.86;
  rail.scrollBy({
    left: direction === 'next' ? distance : -distance,
    behavior: 'smooth',
  });
}

async function fetchBestSellers() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/products', {
      params: {
        sort: 'rating',
      },
    });

    products.value = (unwrapData(data) || []).slice(0, 10);
  } catch {
    products.value = [];
    error.value = 'Unable to load best sellers right now.';
  } finally {
    loading.value = false;
    await nextTick();
    observeRail();
    updateScrollButtons();
  }
}

onMounted(() => {
  fetchBestSellers();
  window.addEventListener('resize', updateScrollButtons);

  if ('ResizeObserver' in window) {
    resizeObserver = new ResizeObserver(updateScrollButtons);
    observeRail();
  }
});

watch(railEl, async () => {
  await nextTick();
  observeRail();
  updateScrollButtons();
});

onUnmounted(() => {
  window.removeEventListener('resize', updateScrollButtons);

  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
});
</script>

<template>
  <section class="home-best-sellers page-section" aria-labelledby="home-best-sellers-title">
    <div class="home-best-sellers__header">
      <h2 id="home-best-sellers-title">Best Seller</h2>

      <div v-if="products.length" class="home-best-sellers__controls" aria-label="Best seller carousel controls">
        <button
          v-show="canScrollLeft"
          type="button"
          class="home-best-sellers__nav"
          aria-label="Scroll best sellers left"
          @click="scrollRail('previous')"
        >
          <ChevronLeft :size="20" aria-hidden="true" />
        </button>
        <button
          v-show="canScrollRight"
          type="button"
          class="home-best-sellers__nav"
          aria-label="Scroll best sellers right"
          @click="scrollRail('next')"
        >
          <ChevronRight :size="20" aria-hidden="true" />
        </button>
      </div>
    </div>

    <div v-if="loading" class="home-best-sellers__rail" aria-label="Loading best sellers">
      <SkeletonCard v-for="index in 6" :key="index" />
    </div>

    <p v-else-if="error" class="home-best-sellers__empty">
      {{ error }}
    </p>

    <div
      v-else-if="products.length"
      ref="railEl"
      class="home-best-sellers__rail"
      @scroll.passive="updateScrollButtons"
    >
      <ProductCard
        v-for="product in products"
        :key="product.id"
        :product="product"
      />
    </div>

    <p v-else class="home-best-sellers__empty">
      No best sellers found right now.
    </p>
  </section>
</template>

<style scoped>
.home-best-sellers {
  overflow: hidden;
}

.home-best-sellers__header {
  align-items: end;
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-bottom: 1.2rem;
  position: relative;
  text-align: center;
}

.home-best-sellers__header h2 {
  font-size: clamp(1.65rem, 3vw, 2.15rem);
  letter-spacing: 0;
  line-height: 1.14;
  margin: 0.35rem 0 0;
}

.home-best-sellers__controls {
  align-items: center;
  display: inline-flex;
  gap: 0.45rem;
  min-height: 2.55rem;
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
}

.home-best-sellers__nav {
  align-items: center;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 999px;
  box-shadow: var(--shadow-sm);
  color: var(--color-primary-dark);
  cursor: pointer;
  display: inline-flex;
  height: 2.45rem;
  justify-content: center;
  padding: 0;
  transition: background 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease;
  width: 2.45rem;
}

.home-best-sellers__nav:hover,
.home-best-sellers__nav:focus-visible {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: #fff;
}

.home-best-sellers__nav:focus-visible {
  outline: 3px solid color-mix(in srgb, var(--color-primary) 22%, transparent);
  outline-offset: 2px;
}

.home-best-sellers__nav:active {
  transform: scale(0.96);
}

.home-best-sellers__rail {
  display: grid;
  gap: 0.9rem;
  grid-auto-columns: clamp(11.25rem, 21vw, 15.5rem);
  grid-auto-flow: column;
  justify-content: safe center;
  margin-inline: calc(clamp(1rem, 4vw, 1.25rem) * -1);
  overflow-x: auto;
  overscroll-behavior-x: contain;
  padding: 0.1rem clamp(1rem, 4vw, 1.25rem) 0.6rem;
  scroll-padding-inline: clamp(1rem, 4vw, 1.25rem);
  scroll-snap-type: x proximity;
  scrollbar-width: none;
}

.home-best-sellers__rail::-webkit-scrollbar {
  display: none;
}

.home-best-sellers__rail > :deep(*) {
  min-width: 0;
  scroll-snap-align: start;
}

.home-best-sellers__empty {
  background: var(--color-surface-soft);
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-sm);
  color: rgba(28, 44, 76, 0.72);
  font-weight: 700;
  margin: 0;
  padding: 1.35rem;
  text-align: center;
}

@media (max-width: 1024px) {
  .home-best-sellers__controls {
    display: none;
  }

  .home-best-sellers__rail {
    gap: 0.72rem;
    grid-auto-columns: auto;
    grid-auto-flow: row;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    justify-content: stretch;
    margin-inline: 0;
    overflow-x: visible;
    padding: 0;
    scroll-padding-inline: 0;
    scroll-snap-type: none;
  }

  .home-best-sellers__rail > :deep(*) {
    scroll-snap-align: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .home-best-sellers__nav {
    transition: none;
  }
}
</style>
