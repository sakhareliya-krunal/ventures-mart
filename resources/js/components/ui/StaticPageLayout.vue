<script setup>
import { computed } from 'vue';
import Breadcrumb from '@/components/ui/Breadcrumb.vue';
import PageHero from '@/components/ui/PageHero.vue';

const props = defineProps({
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
  size: {
    type: String,
    default: 'marketing',
  },
  sections: {
    type: Array,
    default: () => [],
  },
  wide: {
    type: Boolean,
    default: false,
  },
});

const breadcrumbItems = computed(() => [
  { label: 'Home', to: '/' },
  { label: props.title },
]);
</script>

<template>
  <div class="static-shell">
    <PageHero :eyebrow="eyebrow" :title="title" :lead="lead" :meta="meta" :size="size">
      <template v-if="$slots.actions" #actions><slot name="actions" /></template>
      <template v-if="$slots.aside" #aside><slot name="aside" /></template>
    </PageHero>
    <section
      class="page-section static-shell__body"
      :class="{ 'static-shell__body--wide': wide, 'static-shell__body--with-nav': sections.length }"
    >
      <Breadcrumb class="static-shell__breadcrumb" :items="breadcrumbItems" />
      <div class="static-shell__layout">
        <nav v-if="sections.length" class="static-shell__nav" aria-label="On this page">
          <span>On this page</span>
          <a v-for="section in sections" :key="section.id" :href="`#${section.id}`">
            {{ section.label }}
          </a>
        </nav>
        <div class="static-shell__content">
          <slot />
        </div>
      </div>
    </section>
    <slot name="cta" />
  </div>
</template>
