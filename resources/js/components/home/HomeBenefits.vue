<script setup>
import { Package, RefreshCw, ShieldCheck, Truck } from '@lucide/vue';
import { homeBenefits } from '@/constants/home';

const iconMap = {
  Package,
  ShieldCheck,
  Truck,
  RefreshCw,
};

const items = homeBenefits.items.map((item) => ({
  ...item,
  icon: iconMap[item.icon] || Package,
}));
</script>

<template>
  <section class="home-benefits" aria-labelledby="home-benefits-title">
    <div class="home-benefits__inner">
      <header class="home-benefits__header">
        <span class="home-benefits__eyebrow">{{ homeBenefits.eyebrow }}</span>
        <h2 id="home-benefits-title">{{ homeBenefits.title }}</h2>
        <p>{{ homeBenefits.text }}</p>
      </header>
      <div class="home-benefits__grid">
        <article v-for="item in items" :key="item.title" class="home-benefits__card">
          <span class="home-benefits__icon" :class="`is-${item.tone}`" aria-hidden="true">
            <component :is="item.icon" :size="22" />
          </span>
          <h3>{{ item.title }}</h3>
          <p>{{ item.text }}</p>
        </article>
      </div>
    </div>
  </section>
</template>
