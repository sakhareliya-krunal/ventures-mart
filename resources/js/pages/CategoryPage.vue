<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import ShopPage from '@/pages/ShopPage.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCategoriesStore } from '@/stores/categories';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromRecord } from '@/utils/seoHead';

const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const categories = useCategoriesStore();
const ready = ref(false);
const title = ref('Category');
const category = ref(null);

const slug = computed(() => String(route.params.slug || ''));

useHead(() =>
  category.value
    ? seoHeadFromRecord(category.value, {
        title: `${category.value.name} | ${theme.brandName}`,
        description: category.value.description,
        canonical: `/category/${category.value.slug}`,
        image: category.value.image,
      })
    : { title: `${title.value} | ${theme.brandName}` },
);

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

    category.value = match;
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
  <ShopPage v-else :key="slug" :category-slug="slug" :title="title" />
</template>
