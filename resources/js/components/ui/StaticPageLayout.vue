<script setup>
import PageHero from '@/components/ui/PageHero.vue';

defineProps({
  eyebrow: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    required: true,
  },
  lead: {
    type: String,
    default: '',
  },
  meta: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <div class="static-shell">
    <PageHero :eyebrow="eyebrow" :title="title" :lead="lead" :meta="meta" />
    <section class="page-section static-shell__body">
      <slot />
    </section>
  </div>
</template>
