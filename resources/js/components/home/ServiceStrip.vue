<script setup>
import { brandAssets } from '@/constants/assets';
import { homeServices } from '@/constants/home';

const services = homeServices.map((service) => ({
  ...service,
  image: brandAssets[service.imageKey],
}));
</script>

<template>
  <section class="service-strip" aria-label="Shopping promises">
    <div v-for="service in services" :key="service.title" class="service-item">
      <img :src="service.image" alt="" />
      <div>
        <h3>{{ service.title }}</h3>
        <p>{{ service.text }}</p>
      </div>
    </div>
  </section>
</template>
