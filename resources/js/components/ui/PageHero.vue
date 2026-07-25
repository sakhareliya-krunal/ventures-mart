<script setup>
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
  size: {
    type: String,
    default: 'marketing',
    validator: (value) => ['marketing', 'catalog', 'compact'].includes(value),
  },
});
</script>

<template>
  <section class="page-hero page-section" :class="`page-hero--${size}`">
    <div class="page-hero__inner">
      <div class="page-hero__copy">
        <span v-if="eyebrow" class="eyebrow">{{ eyebrow }}</span>
        <h1>{{ title }}</h1>
        <p v-if="lead" class="page-hero__lead">{{ lead }}</p>
        <p v-if="meta" class="page-hero__meta">{{ meta }}</p>
        <div v-if="$slots.actions" class="page-hero__actions">
          <slot name="actions" />
        </div>
      </div>
      <div v-if="$slots.aside" class="page-hero__aside">
        <slot name="aside" />
      </div>
    </div>
  </section>
</template>
