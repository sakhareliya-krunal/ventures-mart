<script setup>
import { homeTrust } from '@/constants/home';
</script>

<template>
  <section class="home-trust" aria-labelledby="home-trust-title">
    <div class="home-trust__inner">
      <div class="home-trust__intro">
        <span class="eyebrow">{{ homeTrust.eyebrow }}</span>
        <h2 id="home-trust-title">{{ homeTrust.title }}</h2>
      </div>
      <div class="home-trust__grid">
        <blockquote
          v-for="item in homeTrust.quotes"
          :key="item.attribution"
          class="home-trust__quote"
        >
          <p>“{{ item.quote }}”</p>
          <footer>{{ item.attribution }}</footer>
        </blockquote>
      </div>
    </div>
  </section>
</template>
