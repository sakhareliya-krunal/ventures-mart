<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  eyebrow: {
    type: String,
    default: 'Account',
  },
  busy: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <section class="auth-page">
    <span class="auth-page__orb auth-page__orb--one" aria-hidden="true" />
    <span class="auth-page__orb auth-page__orb--two" aria-hidden="true" />

    <div class="auth-page__inner">
      <div class="auth-panel">
        <div class="auth-card form-panel" :class="{ 'is-busy': busy }">
          <header class="auth-card__header">
            <span class="eyebrow">{{ eyebrow }}</span>
            <h2>{{ title }}</h2>
          </header>
          <slot />
          <footer v-if="$slots.footer" class="auth-card__footer">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </div>
  </section>
</template>
