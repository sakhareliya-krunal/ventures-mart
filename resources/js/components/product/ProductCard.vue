<script setup>
import { computed, ref, watch } from 'vue';
import { Heart, ShoppingBag, Star } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import { formatCurrency } from '@/utils/format';
import { stripHtml } from '@/utils/html';
import { useCartStore } from '@/stores/cart';
import { useWishlistStore } from '@/stores/wishlist';

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
});

const cart = useCartStore();
const wishlist = useWishlistStore();

const selectedSlug = ref(props.product.slug);

watch(
  () => props.product.slug,
  (slug) => {
    selectedSlug.value = slug;
  },
);

const display = computed(() => {
  const base = props.product;
  const variant = (base.variants || []).find((item) => item.slug === selectedSlug.value);

  if (!variant) {
    return base;
  }

  return {
    ...base,
    id: variant.id,
    slug: variant.slug,
    name: variant.name,
    image: variant.image || base.image,
    hover_image: variant.hover_image ?? base.hover_image,
    price: variant.price ?? base.price,
    compare_at_price:
      variant.compare_at_price !== undefined ? variant.compare_at_price : base.compare_at_price,
    rating: variant.rating !== undefined ? variant.rating : base.rating,
    reviews: variant.reviews !== undefined ? variant.reviews : base.reviews,
    color_name: variant.color_name ?? base.color_name,
  };
});

const wished = computed(() => wishlist.isWishlisted(display.value.id));
const adding = computed(() => cart.isAdding(display.value.id));
const hasHover = computed(() => Boolean(display.value.hover_image));

function selectVariant(slug) {
  if (slug !== selectedSlug.value) {
    selectedSlug.value = slug;
  }
}

async function addToCart() {
  if (adding.value) return;
  await cart.addItem(display.value.id);
}

async function toggleWish() {
  await wishlist.toggle(display.value.id);
}
</script>

<template>
  <article class="product-card">
    <div class="product-card__media">
      <span v-if="display.badge" class="product-card__badge">{{ display.badge }}</span>
      <RouterLink :to="`/product/${display.slug}`" :aria-label="display.name">
        <img
          class="is-primary"
          :class="{ 'has-hover': hasHover }"
          :src="display.image"
          :alt="display.name"
          loading="lazy"
        />
        <img
          v-if="hasHover"
          class="is-hover"
          :src="display.hover_image"
          :alt="display.name"
          loading="lazy"
        />
      </RouterLink>
      <button
        class="icon-button product-card__wish"
        :class="{ 'is-wished': wished }"
        type="button"
        :aria-label="wished ? 'Remove from wishlist' : 'Add to wishlist'"
        :aria-pressed="wished"
        @click="toggleWish"
      >
        <Heart :size="18" :fill="wished ? 'currentColor' : 'none'" />
      </button>
    </div>
    <div class="product-card__body">
      <div class="product-card__rating">
        <Star :size="15" fill="currentColor" />
        <span>{{ Number(display.rating).toFixed(1) }}</span>
        <span>({{ display.reviews }})</span>
      </div>
      <h3>
        <RouterLink :to="`/product/${display.slug}`">{{ display.name }}</RouterLink>
      </h3>
      <div class="color-swatches color-swatches--compact" @click.stop>
        <template v-if="product.variants?.length > 1">
          <button
            v-for="variant in product.variants"
            :key="variant.slug"
            type="button"
            class="color-swatch"
            :class="{ 'is-active': variant.slug === selectedSlug }"
            :style="{ '--swatch-color': variant.color_hex || '#c5cdd8' }"
            :title="variant.color_name || variant.name"
            :aria-label="variant.color_name || variant.name"
            @click="selectVariant(variant.slug)"
          />
        </template>
      </div>
      <p>{{ stripHtml(display.description, 120) }}</p>
      <div class="product-card__footer">
        <div class="price">
          <strong>{{ formatCurrency(display.price) }}</strong>
          <span v-if="display.compare_at_price">
            {{ formatCurrency(display.compare_at_price) }}
          </span>
        </div>
        <AppButton
          size="sm"
          class="button--busy-sm"
          :disabled="adding"
          :aria-busy="adding"
          aria-label="Add to cart"
          @click="addToCart"
        >
          <span v-if="adding" class="button-spinner" aria-hidden="true" />
          <template v-else>
            <ShoppingBag :size="16" />
            Add
          </template>
        </AppButton>
      </div>
    </div>
  </article>
</template>
