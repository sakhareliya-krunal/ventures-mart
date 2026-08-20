<script setup>
import { computed } from 'vue';
import { ShoppingCart } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import { formatCurrency } from '@/utils/format';
import { useCartStore } from '@/stores/cart';

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
});

const cart = useCartStore();

const imageAlt = computed(() => {
  const alt = String(props.product.image_alt || '').trim();
  return alt || props.product.name;
});
const adding = computed(() => cart.isAdding(props.product.id));
const inStock = computed(() => Number(props.product.stock ?? 0) > 0);
const hasHover = computed(() => Boolean(props.product.hover_image));
const categoryName = computed(() => String(props.product.category_name || '').trim());

async function addToCart() {
  if (adding.value || !inStock.value) return;
  await cart.addItem(props.product.id);
}
</script>

<template>
  <article class="product-card">
    <div class="product-card__media">
      <RouterLink :to="`/product/${product.slug}`" :aria-label="product.name">
        <img
          class="is-primary"
          :class="{ 'has-hover': hasHover }"
          :src="product.image"
          :alt="imageAlt"
          loading="lazy"
        />
        <img
          v-if="hasHover"
          class="is-hover"
          :src="product.hover_image"
          :alt="imageAlt"
          loading="lazy"
        />
      </RouterLink>
    </div>
    <div class="product-card__body">
      <p v-if="categoryName" class="product-card__category">{{ categoryName }}</p>
      <h3>
        <RouterLink :to="`/product/${product.slug}`">{{ product.name }}</RouterLink>
      </h3>
      <div class="product-card__footer">
        <div class="price product-card__price">
          <strong>{{ formatCurrency(product.price) }}</strong>
          <span v-if="product.compare_at_price" class="price__was">
            {{ formatCurrency(product.compare_at_price) }}
          </span>
        </div>
        <button
          type="button"
          class="product-card__cart-icon"
          :class="{ 'is-loading': adding }"
          :disabled="adding || !inStock"
          :aria-busy="adding"
          :aria-label="inStock ? 'Add to cart' : 'Out of Stock'"
          @click.stop="addToCart"
        >
          <span class="product-card__cart-icon-label" :class="{ 'is-loading': adding }">
            <ShoppingCart :size="18" />
          </span>
          <span
            v-if="adding"
            class="button-spinner button-spinner--center"
            aria-hidden="true"
          />
        </button>
      </div>
    </div>
  </article>
</template>
