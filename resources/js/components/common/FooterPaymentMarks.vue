<script setup>
import { footerPaymentMarks } from '@/constants/footer';
</script>

<template>
  <ul class="footer-bottom-card__payment-list" aria-label="Accepted payment methods">
    <li v-for="mark in footerPaymentMarks" :key="mark.id">
      <span class="footer-bottom-card__payment-chip" :aria-label="mark.label">
        <span class="footer-bottom-card__payment-chip__media">
          <img
            class="footer-bottom-card__payment-icon"
            :src="mark.src"
            alt=""
            loading="lazy"
            decoding="async"
          />
        </span>
      </span>
    </li>
  </ul>
</template>
