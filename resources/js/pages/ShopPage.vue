<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useHead } from '@unhead/vue';
import FilterSidebar from '@/components/shop/FilterSidebar.vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import PageHero from '@/components/ui/PageHero.vue';
import { useCategoriesStore } from '@/stores/categories';
import { useProductsStore } from '@/stores/products';
import { useThemeStore } from '@/stores/theme';

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

const theme = useThemeStore();
const categories = useCategoriesStore();
const products = useProductsStore();

const query = ref(props.searchQuery ?? '');
const category = ref(props.categorySlug ?? '');
const sort = ref('featured');
const maxPrice = ref(2000);

useHead({
  title: () => `${props.title} | ${theme.brandName}`,
});

const params = computed(() => ({
  q: query.value || undefined,
  category: category.value || undefined,
  max_price: maxPrice.value,
  sort: sort.value,
}));

async function load() {
  await products.fetchList(params.value);
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
    category.value = value ?? '';
  },
);

onMounted(async () => {
  if (!categories.list.length) {
    await categories.fetchAll();
  }
  await load();
});
</script>

<template>
  <section class="shop-page">
    <PageHero eyebrow="Catalog" :title="title" size="catalog">
      <template #aside>
        <strong>{{ products.list.length }}</strong>
        products
      </template>
    </PageHero>

    <div class="page-section shop-layout-wrap">
      <div class="shop-layout">
        <FilterSidebar
          v-model:query="query"
          v-model:category="category"
          v-model:max-price="maxPrice"
          v-model:sort="sort"
          :categories="categories.list"
        />
        <ProductGrid
          :products="products.list"
          :loading="products.loading"
        />
      </div>
    </div>
  </section>
</template>
