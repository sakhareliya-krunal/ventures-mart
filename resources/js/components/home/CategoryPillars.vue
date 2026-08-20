<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { brandAssets } from '@/constants/assets';
import { homePillars } from '@/constants/home';
import { useCategoriesStore } from '@/stores/categories';

const categories = useCategoriesStore();

const fallbackPillars = homePillars.map((pillar) => ({
  name: pillar.title,
  href: pillar.href,
  image: pillar.href.includes('lunch')
    ? brandAssets.pillarLunchBox
    : brandAssets.pillarToys,
}));

const pillars = computed(() => {
  const ordered = [...categories.list].sort((first, second) => {
    if (first.featured !== second.featured) {
      return first.featured ? -1 : 1;
    }

    return 0;
  });

  if (!ordered.length) {
    return fallbackPillars;
  }

  return ordered.map((category) => ({
    ...category,
    href: `/category/${category.slug}`,
    image: category.image || fallbackImageFor(category),
  }));
});

function fallbackImageFor(category) {
  const hint = `${category.slug || ''} ${category.name || ''}`.toLowerCase();

  if (hint.includes('lunch') || hint.includes('tiffin')) {
    return brandAssets.pillarLunchBox;
  }

  if (hint.includes('toy')) {
    return brandAssets.pillarToys;
  }

  return brandAssets.homeBanner || brandAssets.pillarToys;
}

function handleImageError(event, pillar) {
  event.currentTarget.src = fallbackImageFor(pillar);
}

onMounted(() => {
  if (!categories.list.length && !categories.loading) {
    categories.fetchAll();
  }
});
</script>

<template>
  <section v-if="pillars.length" class="category-tabbar" aria-label="Shop categories">
    <div class="category-tabbar__scroller">
      <RouterLink
        v-for="pillar in pillars"
        :key="pillar.href"
        class="category-tabbar__item"
        :to="pillar.href"
        :aria-label="`Shop ${pillar.name}`"
      >
        <span class="category-tabbar__media">
          <img
            :src="pillar.image"
            :alt="pillar.name"
            loading="lazy"
            @error="handleImageError($event, pillar)"
          />
        </span>
        <span class="category-tabbar__label">{{ pillar.name }}</span>
      </RouterLink>
    </div>
  </section>
</template>
