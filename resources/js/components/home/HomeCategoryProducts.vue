<script setup>
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import ProductCard from '@/components/product/ProductCard.vue';
import SkeletonCard from '@/components/ui/SkeletonCard.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const categoryTabs = [
  {
    label: 'Lunch Box',
    slug: 'lunch-box',
    title: 'Best Lunch Boxes',
  },
  {
    label: 'Toys',
    slug: 'toys',
    title: 'Best Toys',
  },
];

const selectedSlug = ref(categoryTabs[0].slug);
const productsByCategory = reactive({});
const loadingByCategory = reactive({});
const errorByCategory = reactive({});
const railEl = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

let resizeObserver = null;

const selectedCategory = computed(
  () => categoryTabs.find((category) => category.slug === selectedSlug.value) || categoryTabs[0],
);

const selectedProducts = computed(() => productsByCategory[selectedSlug.value] || []);
const selectedLoading = computed(() => Boolean(loadingByCategory[selectedSlug.value]));
const selectedError = computed(() => errorByCategory[selectedSlug.value] || '');

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

async function syncRailState() {
  await nextTick();
  if (railEl.value) {
    railEl.value.scrollLeft = 0;
  }
  observeRail();
  updateScrollButtons();
}

async function fetchCategoryProducts(slug) {
  if (productsByCategory[slug] || loadingByCategory[slug]) {
    await syncRailState();
    return;
  }

  loadingByCategory[slug] = true;
  errorByCategory[slug] = '';

  try {
    const { data } = await api.get('/products', {
      params: {
        category: slug,
        sort: 'featured',
      },
    });

    productsByCategory[slug] = (unwrapData(data) || []).slice(0, 10);
  } catch {
    productsByCategory[slug] = [];
    errorByCategory[slug] = 'Unable to load products right now.';
  } finally {
    loadingByCategory[slug] = false;
    await syncRailState();
  }
}

function selectCategory(slug) {
  selectedSlug.value = slug;
  fetchCategoryProducts(slug);
}

onMounted(() => {
  fetchCategoryProducts(selectedSlug.value);
  window.addEventListener('resize', updateScrollButtons);

  if ('ResizeObserver' in window) {
    resizeObserver = new ResizeObserver(updateScrollButtons);
    observeRail();
  }
});

watch(railEl, syncRailState);
watch(selectedProducts, syncRailState);

onUnmounted(() => {
  window.removeEventListener('resize', updateScrollButtons);

  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
});
</script>

<template>
  <section class="home-category-products page-section" aria-labelledby="home-category-products-title">
    <div class="home-category-products__header">
      <div>
        <span class="eyebrow">Shop by category</span>
        <h2 id="home-category-products-title">{{ selectedCategory.title }}</h2>
      </div>

      <div class="home-category-products__actions">
        <div class="home-category-products__tabs" role="tablist" aria-label="Product category">
          <button
            v-for="category in categoryTabs"
            :key="category.slug"
            type="button"
            class="home-category-products__tab"
            :class="{ 'is-active': category.slug === selectedSlug }"
            role="tab"
            :aria-selected="category.slug === selectedSlug"
            :aria-controls="`home-category-products-panel-${category.slug}`"
            @click="selectCategory(category.slug)"
          >
            {{ category.label }}
          </button>
        </div>

        <div
          v-if="selectedProducts.length"
          class="home-category-products__controls"
          aria-label="Category products carousel controls"
        >
          <button
            v-show="canScrollLeft"
            type="button"
            class="home-category-products__nav"
            aria-label="Scroll category products left"
            @click="scrollRail('previous')"
          >
            <ChevronLeft :size="20" aria-hidden="true" />
          </button>
          <button
            v-show="canScrollRight"
            type="button"
            class="home-category-products__nav"
            aria-label="Scroll category products right"
            @click="scrollRail('next')"
          >
            <ChevronRight :size="20" aria-hidden="true" />
          </button>
        </div>
      </div>
    </div>

    <div
      :id="`home-category-products-panel-${selectedSlug}`"
      class="home-category-products__panel"
      role="tabpanel"
    >
      <div v-if="selectedLoading" class="home-product-rail" aria-label="Loading products">
        <SkeletonCard v-for="index in 6" :key="index" />
      </div>

      <p v-else-if="selectedError" class="home-category-products__empty">
        {{ selectedError }}
      </p>

      <div
        v-else-if="selectedProducts.length"
        ref="railEl"
        class="home-product-rail"
        @scroll.passive="updateScrollButtons"
      >
        <ProductCard
          v-for="product in selectedProducts"
          :key="product.id"
          :product="product"
        />
      </div>

      <p v-else class="home-category-products__empty">
        No products found in {{ selectedCategory.label }}.
      </p>
    </div>
  </section>
</template>

<style scoped>
.home-category-products {
  overflow: hidden;
}

.home-category-products__header {
  display: grid;
  gap: 0.85rem;
  justify-items: center;
  margin-bottom: 1.2rem;
  text-align: center;
}

.home-category-products__header h2 {
  font-size: clamp(1.65rem, 3vw, 2.15rem);
  letter-spacing: 0;
  line-height: 1.14;
  margin: 0.35rem 0 0.45rem;
}

.home-category-products__actions {
  align-items: center;
  display: flex;
  gap: 1rem;
  justify-content: center;
  min-height: 2.55rem;
  position: relative;
  width: 100%;
}

.home-category-products__tabs {
  align-items: center;
  background: transparent;
  border: 0;
  display: inline-flex;
  gap: clamp(1rem, 3vw, 1.65rem);
  justify-content: center;
  padding: 0;
}

.home-category-products__tab {
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  color: rgba(28, 44, 76, 0.68);
  cursor: pointer;
  font: inherit;
  font-size: 0.95rem;
  font-weight: 800;
  padding: 0.15rem 0.05rem 0.45rem;
  transition: border-color 160ms ease, color 160ms ease;
  white-space: nowrap;
}

.home-category-products__tab:hover,
.home-category-products__tab:focus-visible {
  color: var(--color-primary);
}

.home-category-products__tab:focus-visible {
  outline: 3px solid color-mix(in srgb, var(--color-primary) 22%, transparent);
  outline-offset: 2px;
}

.home-category-products__tab.is-active {
  border-color: var(--color-primary);
  color: var(--color-primary-dark);
}

.home-category-products__controls {
  align-items: center;
  display: inline-flex;
  gap: 0.45rem;
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
}

.home-category-products__nav {
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

.home-category-products__nav:hover,
.home-category-products__nav:focus-visible {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: #fff;
}

.home-category-products__nav:focus-visible {
  outline: 3px solid color-mix(in srgb, var(--color-primary) 22%, transparent);
  outline-offset: 2px;
}

.home-category-products__nav:active {
  transform: scale(0.96);
}

.home-category-products__panel {
  min-width: 0;
  overflow: hidden;
}

.home-product-rail {
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

.home-product-rail::-webkit-scrollbar {
  display: none;
}

.home-product-rail > :deep(*) {
  min-width: 0;
  scroll-snap-align: start;
}

.home-category-products__empty {
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
  .home-category-products__header {
    gap: 0.8rem;
  }

  .home-category-products__actions {
    justify-content: center;
  }

  .home-category-products__controls {
    display: none;
  }

  .home-category-products__tabs {
    flex-wrap: wrap;
    max-width: 100%;
    scrollbar-width: none;
  }

  .home-category-products__tabs::-webkit-scrollbar {
    display: none;
  }

  .home-category-products__tab {
    font-size: 0.9rem;
    padding: 0.1rem 0.04rem 0.38rem;
  }

  .home-product-rail {
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

  .home-product-rail > :deep(*) {
    scroll-snap-align: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .home-category-products__tab,
  .home-category-products__nav {
    transition: none;
  }
}
</style>
