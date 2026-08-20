<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
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

const selectedCategory = computed(
  () => categoryTabs.find((category) => category.slug === selectedSlug.value) || categoryTabs[0],
);

const selectedProducts = computed(() => productsByCategory[selectedSlug.value] || []);
const selectedLoading = computed(() => Boolean(loadingByCategory[selectedSlug.value]));
const selectedError = computed(() => errorByCategory[selectedSlug.value] || '');

async function fetchCategoryProducts(slug) {
  if (productsByCategory[slug] || loadingByCategory[slug]) {
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
  }
}

function selectCategory(slug) {
  selectedSlug.value = slug;
  fetchCategoryProducts(slug);
}

onMounted(() => {
  fetchCategoryProducts(selectedSlug.value);
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

      <div v-else-if="selectedProducts.length" class="home-product-rail">
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
  justify-content: center;
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


.home-category-products__panel {
  min-width: 0;
  overflow: hidden;
}

.home-product-rail {
  display: grid;
  gap: 0.9rem;
  grid-auto-columns: clamp(11.25rem, 21vw, 15.5rem);
  grid-auto-flow: column;
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

@media (max-width: 720px) {
  .home-category-products__header {
    gap: 0.8rem;
  }

  .home-category-products__actions {
    justify-content: center;
    width: 100%;
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
    grid-auto-columns: minmax(0, min(72vw, 13.5rem));
    margin-inline: 0;
    padding-inline: 0;
    scroll-padding-inline: 0;
    scroll-snap-type: x mandatory;
  }
}

@media (prefers-reduced-motion: reduce) {
  .home-category-products__tab {
    transition: none;
  }
}
</style>
