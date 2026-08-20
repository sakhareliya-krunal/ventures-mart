<script setup>
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ProductCard from '@/components/product/ProductCard.vue';
import SkeletonCard from '@/components/ui/SkeletonCard.vue';
import { homeCurated } from '@/constants/home';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const products = ref([]);
const loading = ref(false);
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

async function fetchCurated() {
  loading.value = true;

  try {
    const { data } = await api.get('/products/featured');
    products.value = (unwrapData(data) || [])
      .slice()
      .sort((a, b) => {
        const ratingDelta = Number(b.rating || 0) - Number(a.rating || 0);
        if (ratingDelta !== 0) return ratingDelta;
        return Number(b.reviews || 0) - Number(a.reviews || 0);
      })
      .slice(0, 4);
  } catch {
    products.value = [];
  } finally {
    loading.value = false;
    await nextTick();
    observeRail();
    updateScrollButtons();
  }
}

onMounted(() => {
  fetchCurated();
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
  <section
    v-if="loading || products.length"
    class="home-curated page-section"
    aria-labelledby="home-curated-title"
  >
    <div class="home-curated__header">
      <h2 id="home-curated-title">{{ homeCurated.title }}</h2>

      <div v-if="products.length" class="home-curated__controls" aria-label="Curated collection carousel controls">
        <button
          v-show="canScrollLeft"
          type="button"
          class="home-curated__nav"
          aria-label="Scroll curated collection left"
          @click="scrollRail('previous')"
        >
          <ChevronLeft :size="20" aria-hidden="true" />
        </button>
        <button
          v-show="canScrollRight"
          type="button"
          class="home-curated__nav"
          aria-label="Scroll curated collection right"
          @click="scrollRail('next')"
        >
          <ChevronRight :size="20" aria-hidden="true" />
        </button>
      </div>
    </div>

    <div v-if="loading" class="home-curated__rail" aria-label="Loading curated collection">
      <SkeletonCard v-for="index in 4" :key="index" />
    </div>

    <div
      v-else
      ref="railEl"
      class="home-curated__rail"
      @scroll.passive="updateScrollButtons"
    >
      <ProductCard
        v-for="product in products"
        :key="product.id"
        :product="product"
      />
    </div>
  </section>
</template>

<style scoped>
.home-curated {
  overflow: hidden;
}

.home-curated__header {
  align-items: end;
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-bottom: 1.2rem;
  position: relative;
  text-align: center;
}

.home-curated__header h2 {
  font-size: clamp(1.65rem, 3vw, 2.15rem);
  letter-spacing: 0;
  line-height: 1.14;
  margin: 0.35rem 0 0;
}

.home-curated__controls {
  align-items: center;
  display: inline-flex;
  gap: 0.45rem;
  min-height: 2.55rem;
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
}

.home-curated__nav {
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

.home-curated__nav:hover,
.home-curated__nav:focus-visible {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: #fff;
}

.home-curated__nav:focus-visible {
  outline: 3px solid color-mix(in srgb, var(--color-primary) 22%, transparent);
  outline-offset: 2px;
}

.home-curated__nav:active {
  transform: scale(0.96);
}

.home-curated__rail {
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

.home-curated__rail::-webkit-scrollbar {
  display: none;
}

.home-curated__rail > :deep(*) {
  min-width: 0;
  scroll-snap-align: start;
}

@media (max-width: 720px) {
  .home-curated__header {
    align-items: center;
    display: flex;
    gap: 0.85rem;
  }

  .home-curated__controls {
    position: static;
    transform: none;
  }

  .home-curated__rail {
    gap: 0.72rem;
    grid-auto-columns: min(58vw, 13.5rem);
    justify-content: start;
    scroll-snap-type: x mandatory;
  }
}

@media (prefers-reduced-motion: reduce) {
  .home-curated__nav {
    transition: none;
  }
}
</style>
