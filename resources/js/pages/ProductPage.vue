<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import { Heart, MessageSquareQuote, ShoppingBag, Star, X } from '@lucide/vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import AppButton from '@/components/ui/AppButton.vue';
import Breadcrumb from '@/components/ui/Breadcrumb.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { formatCurrency } from '@/utils/format';
import { safeHtml, stripHtml } from '@/utils/html';
import { useCartStore } from '@/stores/cart';
import { useProductsStore } from '@/stores/products';
import { useThemeStore } from '@/stores/theme';
import { useWishlistStore } from '@/stores/wishlist';

const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const products = useProductsStore();
const cart = useCartStore();
const wishlist = useWishlistStore();

const activeImage = ref('');
const activeIndex = ref(0);
const trackEl = ref(null);
const reviewSuccess = ref(false);
const reviewDialogOpen = ref(false);
let reviewSuccessTimer = null;

const reviewForm = reactive({
  author_name: '',
  rating: 5,
  body: '',
});

const slug = computed(() => String(route.params.slug || ''));
const product = computed(() => products.current);
const wished = computed(() =>
  product.value ? wishlist.isWishlisted(product.value.id) : false,
);
const adding = computed(() =>
  product.value ? cart.isAdding(product.value.id) : false,
);
const variants = computed(() => product.value?.variants || []);
const gallery = computed(() => {
  if (!product.value) {
    return [];
  }

  const images = product.value.gallery?.length
    ? product.value.gallery
    : [product.value.image, product.value.hover_image].filter(Boolean);

  return [...new Set(images)];
});

const displayImage = computed(() => activeImage.value || product.value?.image || '');
const hasReviews = computed(() => Number(product.value?.reviews || 0) > 0);
const reviewCountLabel = computed(() => {
  const count = Number(product.value?.reviews || 0);
  return count === 1 ? '1 review' : `${count} reviews`;
});

useHead({
  title: () =>
    product.value
      ? `${product.value.name} | ${theme.brandName}`
      : `Product | ${theme.brandName}`,
});

function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function syncActiveFromIndex(index) {
  const clamped = Math.max(0, Math.min(index, gallery.value.length - 1));
  activeIndex.value = clamped;
  activeImage.value = gallery.value[clamped] || '';
}

function resetGallery(image) {
  const next = image || gallery.value[0] || '';
  activeImage.value = next;
  activeIndex.value = Math.max(0, gallery.value.indexOf(next));
}

async function scrollTrackTo(index, { smooth = true } = {}) {
  await nextTick();
  const track = trackEl.value;
  if (!track || !gallery.value.length) {
    return;
  }

  const slide = track.children[index];
  if (!slide) {
    return;
  }

  const behavior = smooth && !prefersReducedMotion() ? 'smooth' : 'auto';
  slide.scrollIntoView({ inline: 'center', block: 'nearest', behavior });
}

function onTrackScroll() {
  const track = trackEl.value;
  if (!track || !gallery.value.length) {
    return;
  }

  const index = Math.round(track.scrollLeft / Math.max(track.clientWidth, 1));
  if (index !== activeIndex.value) {
    syncActiveFromIndex(index);
  }
}

function goToSlide(index) {
  syncActiveFromIndex(index);
  scrollTrackTo(index);
}

function selectThumb(image) {
  activeImage.value = image;
  activeIndex.value = Math.max(0, gallery.value.indexOf(image));
}

function formatReviewDate(value) {
  if (!value) {
    return '';
  }

  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
}

function clearReviewSuccessTimer() {
  if (reviewSuccessTimer) {
    clearTimeout(reviewSuccessTimer);
    reviewSuccessTimer = null;
  }
}

function resetReviewForm() {
  clearReviewSuccessTimer();
  reviewForm.author_name = '';
  reviewForm.rating = 5;
  reviewForm.body = '';
  reviewSuccess.value = false;
  products.reviewError = null;
}

function lockReviewScroll(locked) {
  document.body.style.overflow = locked ? 'hidden' : '';
}

function onReviewDialogKeydown(event) {
  if (event.key === 'Escape') {
    closeReviewDialog();
  }
}

function openReviewDialog() {
  clearReviewSuccessTimer();
  reviewSuccess.value = false;
  products.reviewError = null;
  reviewDialogOpen.value = true;
}

function closeReviewDialog() {
  reviewDialogOpen.value = false;
  resetReviewForm();
}

async function load() {
  try {
    closeReviewDialog();
    await products.fetchBySlug(slug.value);
    resetGallery(product.value?.image || gallery.value[0] || '');
    await Promise.all([
      scrollTrackTo(activeIndex.value, { smooth: false }),
      products.fetchReviews(slug.value),
    ]);
  } catch {
    await router.replace('/shop');
  }
}

function selectVariant(variantSlug) {
  if (variantSlug !== slug.value) {
    router.push(`/product/${variantSlug}`);
  }
}

async function onSubmitReview() {
  reviewSuccess.value = false;

  try {
    await products.submitReview(slug.value, {
      author_name: reviewForm.author_name.trim(),
      rating: Number(reviewForm.rating),
      body: reviewForm.body.trim(),
    });
    reviewForm.body = '';
    reviewSuccess.value = true;
    clearReviewSuccessTimer();
    reviewSuccessTimer = setTimeout(() => {
      closeReviewDialog();
    }, 1200);
  } catch {
    // Error surfaced via products.reviewError
  }
}

watch(slug, load);
watch(gallery, async () => {
  let index = gallery.value.indexOf(activeImage.value);
  if (index < 0) {
    index = 0;
    activeImage.value = gallery.value[0] || '';
  }
  activeIndex.value = index;
  await scrollTrackTo(index, { smooth: false });
});

watch(reviewDialogOpen, (isOpen) => {
  lockReviewScroll(isOpen);

  if (isOpen) {
    window.addEventListener('keydown', onReviewDialogKeydown);
  } else {
    window.removeEventListener('keydown', onReviewDialogKeydown);
  }
});

onMounted(load);

onBeforeUnmount(() => {
  clearReviewSuccessTimer();
  lockReviewScroll(false);
  window.removeEventListener('keydown', onReviewDialogKeydown);
});
</script>

<template>
  <LoadingSpinner v-if="products.loading" page label="Loading product" />
  <template v-else-if="product">
    <section class="product-detail">
      <Breadcrumb
        class="product-detail__crumb"
        :items="[
          { label: 'Shop', to: '/shop' },
          ...(product.category
            ? [{ label: product.category_name || product.category, to: `/category/${product.category}` }]
            : []),
          { label: product.name },
        ]"
      />

      <div class="product-detail__layout">
        <div class="product-detail__gallery">
          <div
            class="product-detail__swipe"
            aria-roledescription="carousel"
            :aria-label="`${product.name} images`"
          >
            <div
              ref="trackEl"
              class="product-detail__track"
              tabindex="0"
              @scroll.passive="onTrackScroll"
            >
              <div
                v-for="(image, index) in gallery"
                :key="image"
                class="product-detail__slide"
                role="group"
                aria-roledescription="slide"
                :aria-label="`${index + 1} of ${gallery.length}`"
              >
                <img :src="image" :alt="`${product.name} view ${index + 1}`" />
              </div>
            </div>
            <div
              v-if="gallery.length > 1"
              class="product-detail__dots"
              role="tablist"
              aria-label="Gallery images"
            >
              <button
                v-for="(image, index) in gallery"
                :key="`dot-${image}`"
                type="button"
                class="product-detail__dot"
                :class="{ 'is-active': activeIndex === index }"
                role="tab"
                :aria-selected="activeIndex === index"
                :aria-label="`Go to image ${index + 1}`"
                @click="goToSlide(index)"
              />
            </div>
          </div>

          <div class="product-detail__stage">
            <img :src="displayImage" :alt="product.name" />
          </div>
          <div v-if="gallery.length > 1" class="product-detail__thumbs" role="list">
            <button
              v-for="(image, index) in gallery"
              :key="image"
              type="button"
              class="product-detail__thumb"
              :class="{ 'is-active': displayImage === image }"
              :aria-label="`View image ${index + 1}`"
              @click="selectThumb(image)"
            >
              <img :src="image" :alt="`${product.name} view ${index + 1}`" />
            </button>
          </div>
        </div>

        <div class="product-detail__copy">
          <span class="eyebrow">{{ product.sku }}</span>
          <h1>{{ product.name }}</h1>
          <div class="product-detail__rating">
            <template v-if="hasReviews">
              <Star :size="18" fill="currentColor" />
              <strong>{{ Number(product.rating).toFixed(1) }}</strong>
              <a href="#product-reviews">{{ reviewCountLabel }}</a>
            </template>
            <template v-else>
              <Star :size="18" />
              <a href="#product-reviews">No reviews yet</a>
            </template>
          </div>
          <div class="price price--large">
            <strong>{{ formatCurrency(product.price) }}</strong>
            <span v-if="product.compare_at_price">
              {{ formatCurrency(product.compare_at_price) }}
            </span>
          </div>
          <p class="product-detail__lead">{{ stripHtml(product.description, 160) }}</p>

          <div v-if="variants.length > 1" class="color-swatches">
            <span class="color-swatches__label">
              Color{{ product.color_name ? `: ${product.color_name}` : '' }}
            </span>
            <div class="color-swatches__list" role="list">
              <button
                v-for="variant in variants"
                :key="variant.slug"
                type="button"
                class="color-swatch"
                :class="{ 'is-active': variant.slug === product.slug }"
                :style="{ '--swatch-color': variant.color_hex || '#c5cdd8' }"
                :title="variant.color_name || variant.name"
                :aria-label="variant.color_name || variant.name"
                @click="selectVariant(variant.slug)"
              />
            </div>
          </div>

          <ul v-if="product.details?.length" class="check-list">
            <li v-for="detail in product.details" :key="detail">{{ detail }}</li>
          </ul>

          <div class="product-detail__actions">
            <AppButton
              size="lg"
              class="button--busy-lg"
              :disabled="adding"
              :aria-busy="adding"
              aria-label="Add to cart"
              @click="cart.addItem(product.id)"
            >
              <span v-if="adding" class="button-spinner" aria-hidden="true" />
              <template v-else>
                <ShoppingBag :size="18" />
                Add to cart
              </template>
            </AppButton>
            <AppButton
              variant="secondary"
              size="lg"
              class="product-detail__wish"
              :class="{ 'is-wished': wished }"
              :aria-pressed="wished"
              @click="wishlist.toggle(product.id)"
            >
              <Heart :size="18" :fill="wished ? 'currentColor' : 'none'" />
              {{ wished ? 'Saved' : 'Wishlist' }}
            </AppButton>
          </div>
          <p class="stock-note">
            {{ product.stock }} in stock. Ships from Venture Smart fulfillment.
          </p>
        </div>
      </div>
    </section>

    <section class="page-section product-detail__description" aria-labelledby="product-description-title">
      <h2 id="product-description-title">Description</h2>
      <div class="product-detail__prose" v-html="safeHtml(product.description)" />
      <ul v-if="product.details?.length" class="check-list">
        <li v-for="detail in product.details" :key="`desc-${detail}`">{{ detail }}</li>
      </ul>
    </section>

    <section
      id="product-reviews"
      class="page-section product-detail__reviews"
      aria-labelledby="product-reviews-title"
    >
      <div class="product-reviews__header">
        <div>
          <h2 id="product-reviews-title">Customer reviews</h2>
          <p v-if="hasReviews">
            {{ Number(product.rating).toFixed(1) }} average from {{ reviewCountLabel }}
          </p>
          <p v-else>Share honest feedback once you have tried this product.</p>
        </div>
        <AppButton type="button" @click="openReviewDialog">Write a review</AppButton>
      </div>

      <div v-if="products.reviewsLoading" class="product-reviews__loading">Loading reviews…</div>

      <div v-else-if="!products.productReviews.length" class="product-reviews__empty">
        <MessageSquareQuote :size="28" aria-hidden="true" />
        <strong>No reviews yet</strong>
        <p>Be the first to share feedback for this product.</p>
      </div>

      <div v-else class="product-reviews__list">
        <article
          v-for="review in products.productReviews"
          :key="review.id"
          class="product-review-card"
        >
          <div class="product-review-card__top">
            <strong>{{ review.author_name }}</strong>
            <span>{{ formatReviewDate(review.created_at) }}</span>
          </div>
          <div class="product-review-card__stars" :aria-label="`${review.rating} out of 5 stars`">
            <Star
              v-for="star in 5"
              :key="`${review.id}-${star}`"
              :size="14"
              :fill="star <= review.rating ? 'currentColor' : 'none'"
            />
          </div>
          <p>{{ review.body }}</p>
        </article>
      </div>
    </section>

    <section class="page-section product-detail__related">
      <h2>Related products</h2>
      <ProductGrid :products="products.related" />
    </section>

    <Teleport to="body">
      <div v-if="reviewDialogOpen" class="review-dialog">
        <button
          class="review-dialog__backdrop"
          type="button"
          aria-label="Close review dialog"
          @click="closeReviewDialog"
        />
        <div
          class="review-dialog__panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="review-dialog-title"
        >
          <div class="review-dialog__header">
            <h2 id="review-dialog-title">Write a review</h2>
            <button
              class="icon-button"
              type="button"
              aria-label="Close review dialog"
              @click="closeReviewDialog"
            >
              <X :size="22" />
            </button>
          </div>
          <form class="review-dialog__form" @submit.prevent="onSubmitReview">
            <div class="review-dialog__body">
              <FormField
                v-model="reviewForm.author_name"
                label="Your name"
                required
                autocomplete="name"
                placeholder="Full name"
              />
              <fieldset class="product-reviews__rating-field">
                <legend>Rating</legend>
                <div class="product-reviews__rating-picker" role="radiogroup" aria-label="Rating">
                  <button
                    v-for="star in 5"
                    :key="`pick-${star}`"
                    type="button"
                    class="product-reviews__star"
                    :class="{ 'is-active': star <= reviewForm.rating }"
                    :aria-pressed="star === reviewForm.rating"
                    :aria-label="`${star} star${star === 1 ? '' : 's'}`"
                    @click="reviewForm.rating = star"
                  >
                    <Star :size="22" :fill="star <= reviewForm.rating ? 'currentColor' : 'none'" />
                  </button>
                </div>
              </fieldset>
              <FormField
                v-model="reviewForm.body"
                label="Your review"
                type="textarea"
                required
                :rows="4"
                placeholder="What did you like or dislike?"
              />
              <p v-if="products.reviewError" class="product-reviews__error">
                {{ products.reviewError }}
              </p>
              <p v-if="reviewSuccess" class="product-reviews__success">
                Thanks — your review is live.
              </p>
            </div>
            <div class="review-dialog__footer">
              <AppButton type="button" variant="secondary" @click="closeReviewDialog">
                Cancel
              </AppButton>
              <AppButton type="submit" :disabled="products.reviewSubmitting || reviewSuccess">
                {{ products.reviewSubmitting ? 'Submitting…' : 'Submit review' }}
              </AppButton>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </template>
</template>
