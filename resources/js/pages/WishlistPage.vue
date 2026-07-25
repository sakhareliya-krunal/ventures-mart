<script setup>
import { onMounted } from 'vue';
import { useHead } from '@unhead/vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHero from '@/components/ui/PageHero.vue';
import { useThemeStore } from '@/stores/theme';
import { useWishlistStore } from '@/stores/wishlist';

const theme = useThemeStore();
const wishlist = useWishlistStore();

useHead({
  title: () => `Wishlist | ${theme.brandName}`,
});

onMounted(() => wishlist.fetch());
</script>

<template>
  <EmptyState
    v-if="!wishlist.loading && !wishlist.products.length"
    title="Your wishlist is empty"
    description="Save products while browsing and return to them here."
    action-label="Browse products"
  />
  <section v-else class="wishlist-page">
    <PageHero eyebrow="Wishlist" title="Saved products" size="catalog">
      <template #aside>
        <strong>{{ wishlist.products.length }}</strong>
        items
      </template>
    </PageHero>
    <div class="page-section">
      <ProductGrid :products="wishlist.products" :loading="wishlist.loading" />
    </div>
  </section>
</template>
