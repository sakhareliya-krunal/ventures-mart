<script setup>
import { ChevronRight } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { brandAssets } from '@/constants/assets';
</script>

<template>
  <section class="promo-band">
    <div class="promo-band__media">
      <img :src="brandAssets.homeBanner" alt="Steel lunch box for school days" />
    </div>
    <div class="promo-band__copy">
      <span class="eyebrow">Season picks</span>
      <h2>Toys for playtime. Lunch boxes for every school day.</h2>
      <p>
        Explore creative toys and durable steel tiffins — curated together for Indian families.
      </p>
      <AppButton to="/shop" size="lg">
        Shop the edit
        <ChevronRight :size="18" />
      </AppButton>
    </div>
  </section>
</template>
