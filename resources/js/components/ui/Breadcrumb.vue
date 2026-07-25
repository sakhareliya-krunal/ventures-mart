<script setup>
import { ChevronRight } from '@lucide/vue';
import { RouterLink } from 'vue-router';

defineProps({
  items: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb__list">
      <li
        v-for="(item, index) in items"
        :key="`${item.label}-${index}`"
        class="breadcrumb__item"
      >
        <RouterLink v-if="item.to" class="breadcrumb__link" :to="item.to">
          {{ item.label }}
        </RouterLink>
        <span v-else class="breadcrumb__current" aria-current="page">
          {{ item.label }}
        </span>
        <ChevronRight
          v-if="index < items.length - 1"
          class="breadcrumb__sep"
          :size="14"
          aria-hidden="true"
        />
      </li>
    </ol>
  </nav>
</template>
