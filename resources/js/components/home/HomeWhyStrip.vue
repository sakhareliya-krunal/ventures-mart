<script setup>
import { ChevronRight, Package, ShieldCheck, Truck } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { homeWhy } from '@/constants/home';

const icons = [Package, ShieldCheck, Truck];

const values = homeWhy.values.map((value, index) => ({
  ...value,
  icon: icons[index] || Package,
  number: String(index + 1).padStart(2, '0'),
}));
</script>

<template>
  <section class="home-why">
    <div class="home-why__inner">
      <div class="home-why__intro">
        <span class="eyebrow">{{ homeWhy.eyebrow }}</span>
        <h2>{{ homeWhy.title }}</h2>
        <p>{{ homeWhy.text }}</p>
      </div>
      <div class="home-why__grid">
        <article v-for="value in values" :key="value.title" class="home-why__item">
          <span class="home-why__number">{{ value.number }}</span>
          <span class="home-why__icon" aria-hidden="true">
            <component :is="value.icon" :size="24" />
          </span>
          <h3>{{ value.title }}</h3>
          <p>{{ value.text }}</p>
        </article>
      </div>
      <div class="home-why__action">
        <AppButton :to="homeWhy.href" size="lg">
          {{ homeWhy.cta }}
          <ChevronRight :size="18" />
        </AppButton>
      </div>
    </div>
  </section>
</template>
