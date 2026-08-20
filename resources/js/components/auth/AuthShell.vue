<script setup>
import { RouterLink } from 'vue-router';
import { Heart, PackageCheck, ShieldCheck } from '@lucide/vue';
import { brandAssets } from '@/constants/assets';
import { useThemeStore } from '@/stores/theme';

defineProps({
  title: {
    type: String,
    required: true,
  },
  lead: {
    type: String,
    default: '',
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

const theme = useThemeStore();

const benefits = [
  {
    icon: PackageCheck,
    label: 'Track orders',
    helper: 'Follow delivery status from checkout to your door.',
  },
  {
    icon: Heart,
    label: 'Save your wishlist',
    helper: 'Keep favourites handy for your next shop.',
  },
  {
    icon: ShieldCheck,
    label: 'Secure checkout',
    helper: 'Shop with saved details and protected payments.',
  },
];
</script>

<template>
  <section class="auth-page">
    <span class="auth-page__orb auth-page__orb--one" aria-hidden="true" />
    <span class="auth-page__orb auth-page__orb--two" aria-hidden="true" />

    <div class="auth-page__inner">
      <aside class="auth-brand" aria-label="Account benefits">
        <RouterLink class="auth-brand__logo" to="/" :aria-label="`${theme.brandName} home`">
          <img :src="brandAssets.logo" :alt="theme.brandName" />
        </RouterLink>
        <div class="auth-brand__copy">
          <h1 class="auth-brand__title">{{ title }}</h1>
          <p v-if="lead" class="auth-brand__lead">{{ lead }}</p>
        </div>
        <ul class="auth-brand__benefits">
          <li v-for="benefit in benefits" :key="benefit.label" class="auth-brand__benefit">
            <span class="auth-brand__benefit-icon" aria-hidden="true">
              <component :is="benefit.icon" :size="20" :stroke-width="1.75" />
            </span>
            <span class="auth-brand__benefit-copy">
              <strong>{{ benefit.label }}</strong>
              <span>{{ benefit.helper }}</span>
            </span>
          </li>
        </ul>
      </aside>

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
