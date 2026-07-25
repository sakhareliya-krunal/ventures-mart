<script setup>
import { RouterLink } from 'vue-router';

defineProps({
  category: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <RouterLink class="category-card" :to="`/category/${category.slug}`">
    <img :src="category.image" alt="" loading="lazy" />
    <span>{{ category.name }}</span>
    <p>{{ category.description }}</p>
  </RouterLink>
</template>
