<script setup>
import { computed, ref, watch } from 'vue';
import { Heart, ShoppingBag, Star } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import { discountPercent, formatCurrency } from '@/utils/format';
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
    stock: variant.stock !== undefined ? variant.stock : base.stock,
    badge: variant.badge !== undefined ? variant.badge : base.badge,
    color_name: variant.color_name ?? base.color_name,
  };
});

const wished = computed(() => wishlist.isWishlisted(display.value.id));
const imageAlt = computed(() => {
  const alt = String(display.value.image_alt || props.product.image_alt || '').trim();
  return alt || display.value.name;
});
const adding = computed(() => cart.isAdding(display.value.id));
const inStock = computed(() => Number(display.value.stock ?? 0) > 0);
const hasHover = computed(() => Boolean(display.value.hover_image));
const hasVariants = computed(() => (props.product.variants || []).length > 1);
const showRating = computed(() => Number(display.value.reviews ?? 0) > 0 || Number(display.value.rating ?? 0) > 0);
const saleOff = computed(() => discountPercent(display.value.price, display.value.compare_at_price));

function selectVariant(slug) {
  if (slug !== selectedSlug.value) {
    selectedSlug.value = slug;
  }
}

async function addToCart() {
  if (adding.value || !inStock.value) return;
  await cart.addItem(display.value.id);
}

async function toggleWish() {
  await wishlist.toggle(display.value.id, {
    variantId: hasVariants.value ? display.value.id : null,
  });
}
</script>

<template>
  <article class="product-card">
    <div class="product-card__media">
      <span v-if="!inStock" class="product-card__badge product-card__badge--oos">Out of Stock</span>
      <span v-else-if="display.badge" class="product-card__badge">{{ display.badge }}</span>
      <RouterLink :to="`/product/${display.slug}`" :aria-label="display.name">
        <img
          class="is-primary"
          :class="{ 'has-hover': hasHover }"
          :src="display.image"
          :alt="imageAlt"
          loading="lazy"
        />
        <img
          v-if="hasHover"
          class="is-hover"
          :src="display.hover_image"
          :alt="imageAlt"
          loading="lazy"
        />
      </RouterLink>
      <button
        class="icon-button product-card__wish"
        :class="{ 'is-wished': wished }"
        type="button"
        :aria-label="wished ? 'Remove from wishlist' : 'Add to wishlist'"
        :aria-pressed="wished"
        @click.stop="toggleWish"
      >
        <Heart :size="18" :fill="wished ? 'currentColor' : 'none'" />
      </button>
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
          <ShoppingBag :size="18" />
        </span>
        <span
          v-if="adding"
          class="button-spinner button-spinner--center"
          aria-hidden="true"
        />
      </button>
    </div>
    <div class="product-card__body">
      <div class="product-card__rating" :class="{ 'is-empty': !showRating }">
        <template v-if="showRating">
          <Star :size="14" fill="currentColor" />
          <span>{{ Number(display.rating).toFixed(1) }}</span>
          <span>({{ display.reviews }})</span>
        </template>
      </div>
      <h3>
        <RouterLink :to="`/product/${display.slug}`">{{ display.name }}</RouterLink>
      </h3>
      <div v-if="hasVariants" class="color-swatches color-swatches--compact" @click.stop>
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
      </div>
      <div class="product-card__footer">
        <div class="price product-card__price">
          <div class="price__row">
            <strong>{{ formatCurrency(display.price) }}</strong>
            <span v-if="saleOff" class="price__off">{{ saleOff }}% OFF</span>
          </div>
          <span v-if="display.compare_at_price" class="price__was">
            {{ formatCurrency(display.compare_at_price) }}
          </span>
        </div>
        <AppButton
          size="sm"
          class="button--busy-sm product-card__add"
          :disabled="adding || !inStock"
          :aria-busy="adding"
          :aria-label="inStock ? 'Add to cart' : 'Out of Stock'"
          @click.stop="addToCart"
        >
          <template v-if="!inStock">Out of Stock</template>
          <template v-else>
            <span class="button__busy-label" :class="{ 'is-loading': adding }">
              <ShoppingBag :size="16" />
              Add
            </span>
            <span
              v-if="adding"
              class="button-spinner button-spinner--center"
              aria-hidden="true"
            />
          </template>
        </AppButton>
      </div>
    </div>
  </article>
</template>
