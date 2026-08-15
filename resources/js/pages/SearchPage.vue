<script setup>
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useHead } from '@unhead/vue';
import ShopPage from '@/pages/ShopPage.vue';
import { useSearchStore } from '@/stores/search';
import { useThemeStore } from '@/stores/theme';
import { trackMetaEvent } from '@/services/metaPixel';

const route = useRoute();
const theme = useThemeStore();
const search = useSearchStore();

const query = computed(() => String(route.query.q || ''));

watch(
  query,
  (value) => {
    search.setQuery(value);
    const term = String(value || '').trim();
    if (term) {
      trackMetaEvent('Search', { search_string: term, content_type: 'product' });
    }
  },
  { immediate: true },
);

useHead({
  title: () =>
    query.value
      ? `Search: ${query.value} | ${theme.brandName}`
      : `Search | ${theme.brandName}`,
});
</script>

<template>
  <ShopPage :search-query="query" title="Search" />
</template>
