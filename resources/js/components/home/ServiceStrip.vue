<script setup>
import { brandAssets } from '@/constants/assets';

const services = [
  {
    title: 'Free Shipping',
    text: 'On orders over ₹999',
    image: brandAssets.serviceShipping,
  },
  {
    title: 'Family Support',
    text: 'Responsive help for every order',
    image: brandAssets.serviceSupport,
  },
  {
    title: 'Easy Returns',
    text: 'Simple returns within 14 days',
    image: brandAssets.serviceReturns,
  },
];
</script>

<template>
  <section class="service-strip">
    <div v-for="service in services" :key="service.title" class="service-item">
      <img :src="service.image" alt="" />
      <div>
        <h3>{{ service.title }}</h3>
        <p>{{ service.text }}</p>
      </div>
    </div>
  </section>
</template>
