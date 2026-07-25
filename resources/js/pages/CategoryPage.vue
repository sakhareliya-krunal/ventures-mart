<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import ShopPage from '@/pages/ShopPage.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCategoriesStore } from '@/stores/categories';
import { useThemeStore } from '@/stores/theme';

const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const categories = useCategoriesStore();
const ready = ref(false);
const title = ref('Category');

const slug = computed(() => String(route.params.slug || ''));

useHead({
  title: () => `${title.value} | ${theme.brandName}`,
});

async function resolveCategory() {
  ready.value = false;

  try {
    if (!categories.list.length) {
      await categories.fetchAll();
    }

    const match = categories.list.find((item) => item.slug === slug.value);
    if (!match) {
      await router.replace('/shop');
      return;
    }

    title.value = match.name;
    ready.value = true;
  } catch {
    await router.replace('/shop');
  }
}

watch(slug, resolveCategory);
onMounted(resolveCategory);
</script>

<template>
  <LoadingSpinner v-if="!ready" page label="Loading category" />
  <ShopPage v-else :category-slug="slug" :title="title" />
</template>
