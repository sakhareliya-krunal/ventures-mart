<script setup>
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();

useHead({
  title: () => `Page not found | ${theme.brandName}`,
});
</script>

<template>
  <section class="page-section empty-state">
    <h1>Page not found</h1>
    <p>The route is not part of the rebuilt Venture Smart storefront.</p>
    <AppButton to="/">Go home</AppButton>
  </section>
</template>
