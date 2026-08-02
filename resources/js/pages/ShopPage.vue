<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import FilterSidebar from '@/components/shop/FilterSidebar.vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import Breadcrumb from '@/components/ui/Breadcrumb.vue';
import PageHero from '@/components/ui/PageHero.vue';
import { useCategoriesStore } from '@/stores/categories';
import { useProductsStore } from '@/stores/products';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const props = defineProps({
  categorySlug: {
    type: String,
    default: '',
  },
  searchQuery: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    default: 'Shop',
  },
});

const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const categories = useCategoriesStore();
const products = useProductsStore();

const query = ref(props.searchQuery ?? '');
const category = ref(props.categorySlug ?? '');
const sort = ref('featured');
const priceFloor = ref(0);
const priceCeiling = ref(2000);
const minPrice = ref(0);
const maxPrice = ref(2000);
const boundsReady = ref(false);
const pageLoading = ref(true);
const syncingFromRoute = ref(false);

const catalogLoading = computed(() => pageLoading.value || products.loading);
const productCountLabel = computed(() =>
  catalogLoading.value ? '—' : String(products.list.length),
);
const breadcrumbItems = computed(() => {
  if (props.categorySlug) {
    return [
      { label: 'Home', to: '/' },
      { label: 'Shop', to: '/shop' },
      { label: props.title || 'Category' },
    ];
  }
  return [
    { label: 'Home', to: '/' },
    { label: 'Shop' },
  ];
});

useHead(() => {
  if (props.categorySlug) {
    return seoHeadFromServer({
      title: `${props.title} Online | ${theme.brandName}`,
      description: `Shop ${props.title} online at ${theme.brandName}.`,
      canonical: `/category/${props.categorySlug}`,
    });
  }

  return seoHeadFromServer({
    title: `Shop Toys & Lunch Boxes Online | ${theme.brandName}`,
    description: `Shop premium stainless steel lunch boxes and kids toys online at ${theme.brandName}. Fast delivery across India.`,
    canonical: '/shop',
  });
});

function applyBounds(bounds) {
  const rawMin = Number(bounds?.min ?? 0);
  const rawMax = Number(bounds?.max ?? 0);

  if (!rawMax) {
    priceFloor.value = 0;
    priceCeiling.value = 2000;
  } else {
    priceFloor.value = Math.floor(rawMin);
    priceCeiling.value = Math.max(Math.ceil(rawMax / 50) * 50, Math.ceil(rawMax));
  }

  minPrice.value = priceFloor.value;
  maxPrice.value = priceCeiling.value;
}

const params = computed(() => {
  const next = {
    q: query.value || undefined,
    category: category.value || undefined,
    sort: sort.value,
  };

  const fullRange =
    Number(minPrice.value) === Number(priceFloor.value) &&
    Number(maxPrice.value) === Number(priceCeiling.value);

  if (!fullRange) {
    next.min_price = minPrice.value;
    next.max_price = maxPrice.value;
  }

  return next;
});

async function load() {
  if (!boundsReady.value) {
    return;
  }
  await products.fetchList(params.value);
}

function syncCategoryToRoute(value) {
  if (syncingFromRoute.value) {
    return;
  }

  const target = value ? `/category/${value}` : '/shop';
  if (route.path !== target) {
    router.push(target);
  }
}

watch(params, load, { deep: true });

watch(
  () => props.searchQuery,
  (value) => {
    query.value = value ?? '';
  },
);

watch(
  () => props.categorySlug,
  (value) => {
    syncingFromRoute.value = true;
    category.value = value ?? '';
    nextTick(() => {
      syncingFromRoute.value = false;
    });
  },
  { immediate: true },
);

watch(category, (value) => {
  syncCategoryToRoute(value);
});

onMounted(async () => {
  pageLoading.value = true;

  if (typeof products.beginListLoad === 'function') {
    products.beginListLoad();
  } else {
    products.loading = true;
    products.error = null;
    products.list = [];
  }

  try {
    if (!categories.list.length) {
      await categories.fetchAll();
    }

    const bounds = await products.fetchPriceBounds();
    applyBounds(bounds);
    boundsReady.value = true;
    await load();
  } finally {
    pageLoading.value = false;
  }
});
</script>

<template>
  <section class="shop-page">
    <PageHero eyebrow="Catalog" :title="title" size="catalog">
      <template #aside>
        <strong>{{ productCountLabel }}</strong>
        products
      </template>
    </PageHero>

    <div class="page-section shop-layout-wrap">
      <Breadcrumb class="shop-page__crumb" :items="breadcrumbItems" />
      <div class="shop-layout">
        <FilterSidebar
          v-model:query="query"
          v-model:category="category"
          v-model:min-price="minPrice"
          v-model:max-price="maxPrice"
          v-model:sort="sort"
          :price-floor="priceFloor"
          :price-ceiling="priceCeiling"
          :categories="categories.list"
        />
        <ProductGrid
          :products="products.list"
          :loading="catalogLoading"
        />
      </div>
    </div>
  </section>
</template>
