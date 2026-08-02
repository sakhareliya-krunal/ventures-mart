<script setup>
import { ChevronRight } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { brandAssets } from '@/constants/assets';
import { homePromo } from '@/constants/home';
</script>

<template>
  <section class="promo-band">
    <div class="promo-band__media">
      <img :src="brandAssets.homeBanner" alt="Steel lunch box for school days" />
    </div>
    <div class="promo-band__copy">
      <span class="eyebrow">{{ homePromo.eyebrow }}</span>
      <h2>{{ homePromo.title }}</h2>
      <p>{{ homePromo.text }}</p>
      <AppButton :to="homePromo.href" size="lg">
        {{ homePromo.cta }}
        <ChevronRight :size="18" />
      </AppButton>
    </div>
  </section>
</template>
