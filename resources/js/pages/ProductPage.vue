<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useHead } from '@unhead/vue';
import {
  ChevronLeft,
  MessageSquareQuote,
  Minus,
  Plus,
  RefreshCw,
  ShieldCheck,
  ShoppingBag,
  Star,
  Truck,
} from '@lucide/vue';
import ProductGalleryChrome from '@/components/product/ProductGalleryChrome.vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { productBackLink, productTrustBadges } from '@/constants/product';
import { useCartStore } from '@/stores/cart';
import { useProductsStore } from '@/stores/products';
import { useThemeStore } from '@/stores/theme';
import { useWishlistStore } from '@/stores/wishlist';
import { productMetaParams, trackMetaEvent } from '@/services/metaPixel';
import { maxCartQuantityFor } from '@/utils/cartStock';
import { discountPercent, formatCurrency } from '@/utils/format';
import { safeHtml, stripHtml } from '@/utils/html';
import {
  buildProductTabs,
  productBackTarget,
  productRatingLabel,
  productSizeLabel,
} from '@/utils/productDetail';
import { seoHeadFromRecord } from '@/utils/seoHead';

const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const products = useProductsStore();
const cart = useCartStore();
const wishlist = useWishlistStore();

const trustIconMap = {
  Truck,
  ShieldCheck,
  RefreshCw,
};

const activeImage = ref('');
const activeIndex = ref(0);
const trackEl = ref(null);
const actionsEl = ref(null);
const stickyVisible = ref(false);
const reviewSuccess = ref(false);
const quantity = ref(1);
const activeTabId = ref('');
let stickyObserver = null;

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
const inStock = computed(() => Number(product.value?.stock ?? 0) > 0);
const variants = computed(() => product.value?.variants || []);
const maxQuantity = computed(() =>
  product.value ? maxCartQuantityFor({ product: product.value }) : 0,
);
const atMaxQuantity = computed(() => quantity.value >= maxQuantity.value);

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
const reviewCount = computed(() => Number(product.value?.reviews || 0));
const ratingLabel = computed(() =>
  product.value ? productRatingLabel(product.value) : '',
);
const sizeLabel = computed(() => productSizeLabel(product.value));
const productTabs = computed(() => buildProductTabs(product.value));
const backTarget = computed(() => productBackTarget(product.value));
const trustBadges = computed(() =>
  productTrustBadges.map((badge) => ({
    ...badge,
    icon: trustIconMap[badge.icon] || Truck,
  })),
);
const saleOff = computed(() =>
  product.value ? discountPercent(product.value.price, product.value.compare_at_price) : null,
);
const lowStock = computed(() => Boolean(product.value?.is_low_stock));
const showStockNote = computed(() => !inStock.value || lowStock.value);

async function toggleWish() {
  if (!product.value) return;
  const hasVariants = variants.value.length > 1;
  await wishlist.toggle(product.value.id, {
    variantId: hasVariants ? product.value.id : null,
  });
}

function productImageAlt(index = 0) {
  const alt = String(product.value?.seo?.metadata?.image_alt_text || '').trim();
  if (alt) {
    return index > 0 ? `${alt} — photo ${index + 1}` : alt;
  }
  const keyword = String(product.value?.seo?.metadata?.focus_keyword || '').trim();
  const category = String(product.value?.category_name || product.value?.category || '').trim();
  const hint = keyword || category;
  if (hint) {
    return `${product.value.name} — ${hint} photo ${index + 1}`;
  }
  return `${product.value.name} photo ${index + 1}`;
}

function productImageTitle(index = 0) {
  const name = String(product.value?.name || '').trim();
  if (!name) return '';
  return index > 0 ? `${name} — image ${index + 1}` : name;
}

async function addToCart() {
  if (!product.value || adding.value || !inStock.value) return;
  await cart.addItem(product.value.id, quantity.value);
}

function decreaseQuantity() {
  if (quantity.value <= 1) return;
  quantity.value -= 1;
}

function increaseQuantity() {
  if (!inStock.value || atMaxQuantity.value) return;
  quantity.value += 1;
}

function resetQuantity() {
  quantity.value = 1;
}

function setActiveTab(tabId) {
  activeTabId.value = tabId;
}

function syncActiveTab() {
  const tabs = productTabs.value;
  if (!tabs.length) {
    activeTabId.value = '';
    return;
  }

  if (!tabs.some((tab) => tab.id === activeTabId.value)) {
    activeTabId.value = tabs[0].id;
  }
}

const activeTab = computed(() =>
  productTabs.value.find((tab) => tab.id === activeTabId.value) || productTabs.value[0] || null,
);

useHead(() =>
  product.value
    ? seoHeadFromRecord(product.value, {
        title: `${product.value.name} | ${
          product.value.seo?.metadata?.focus_keyword
          || product.value.category_name
          || 'Online'
        } | ${theme.brandName}`,
        description: stripHtml(product.value.description, 160) || `Shop ${product.value.name} online at ${theme.brandName}.`,
        canonical: `/product/${product.value.slug}`,
        image: product.value.image,
        ogType: 'product',
        siteName: theme.brandName,
      })
    : { title: `Product | ${theme.brandName}` },
);

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

function resetReviewForm() {
  reviewForm.author_name = '';
  reviewForm.rating = 5;
  reviewForm.body = '';
  reviewSuccess.value = false;
  products.reviewError = null;
}

async function load() {
  try {
    resetReviewForm();
    await products.fetchBySlug(slug.value);
    resetGallery(product.value?.image || gallery.value[0] || '');
    resetQuantity();
    syncActiveTab();
    await Promise.all([
      scrollTrackTo(activeIndex.value, { smooth: false }),
      products.fetchReviews(slug.value),
    ]);
    if (product.value?.id) {
      trackMetaEvent('ViewContent', productMetaParams(product.value));
    }
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

watch(
  () => product.value?.id,
  () => {
    resetQuantity();
    syncActiveTab();
  },
);

watch(productTabs, syncActiveTab);

function disconnectStickyObserver() {
  stickyObserver?.disconnect();
  stickyObserver = null;
}

function bindStickyObserver() {
  disconnectStickyObserver();
  stickyVisible.value = false;

  if (!actionsEl.value || typeof IntersectionObserver === 'undefined') {
    return;
  }

  stickyObserver = new IntersectionObserver(
    ([entry]) => {
      stickyVisible.value = !entry.isIntersecting;
    },
    { root: null, threshold: 0, rootMargin: '-8px 0px 0px 0px' },
  );
  stickyObserver.observe(actionsEl.value);
}

watch(
  () => [products.loading, product.value?.id],
  async () => {
    await nextTick();
    bindStickyObserver();
  },
);

onMounted(load);

onBeforeUnmount(() => {
  disconnectStickyObserver();
});
</script>

<template>
  <LoadingSpinner v-if="products.loading" page label="Loading product" />
  <template v-else-if="product">
    <section class="product-detail">
      <RouterLink :to="backTarget.to" class="product-detail__back">
        <ChevronLeft :size="18" aria-hidden="true" />
        <span v-if="backTarget.label">
          {{ productBackLink.categoryPrefix }} {{ backTarget.label }}
        </span>
        <span v-else>{{ productBackLink.shopLabel }}</span>
      </RouterLink>

      <div class="product-detail__layout">
        <div
          class="product-detail__gallery"
          :class="{ 'product-detail__gallery--multi': gallery.length > 1 }"
        >
          <div
            class="product-detail__swipe"
            aria-roledescription="carousel"
            :aria-label="`${product.name} images`"
          >
            <div class="product-detail__swipe-viewport">
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
                  <div class="product-detail__media-frame">
                    <img
                      :src="image"
                      :alt="productImageAlt(index)"
                      :title="productImageTitle(index)"
                      :loading="index === 0 ? 'eager' : 'lazy'"
                      :fetchpriority="index === 0 ? 'high' : undefined"
                    />
                  </div>
                </div>
              </div>
              <ProductGalleryChrome
                :badge="product.badge"
                :wished="wished"
                @toggle-wish="toggleWish"
              />
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
              <img
                :src="image"
                :alt="productImageAlt(index)"
                :title="productImageTitle(index)"
                loading="lazy"
              />
            </button>
          </div>

          <div class="product-detail__stage">
            <div class="product-detail__media-frame">
              <img
                :src="displayImage"
                :alt="productImageAlt(Math.max(0, gallery.indexOf(displayImage)))"
                :title="productImageTitle(Math.max(0, gallery.indexOf(displayImage)))"
                fetchpriority="high"
              />
              <ProductGalleryChrome
                :badge="product.badge"
                :wished="wished"
                @toggle-wish="toggleWish"
              />
            </div>
          </div>
        </div>

        <div class="product-detail__copy">
          <h1>{{ product.name }}</h1>

          <div class="product-detail__rating">
            <Star :size="18" :fill="hasReviews ? 'currentColor' : 'none'" />
            <a href="#product-reviews">{{ ratingLabel }}</a>
          </div>

          <div class="product-detail__price-row price price--large">
            <strong>{{ formatCurrency(product.price) }}</strong>
            <span v-if="product.compare_at_price" class="price__was">
              {{ formatCurrency(product.compare_at_price) }}
            </span>
            <span v-if="saleOff" class="price__off">-{{ saleOff }}% Off</span>
          </div>
          <p class="price__tax">Inclusive of taxes</p>

          <p class="product-detail__lead">{{ stripHtml(product.description, 160) }}</p>

          <p v-if="sizeLabel" class="product-detail__size">
            <strong>Size:</strong> {{ sizeLabel }}
          </p>

          <hr class="product-detail__divider" />

          <div v-if="variants.length > 1" class="product-detail__variants">
            <h2 class="product-detail__variants-label">Available Colors:</h2>
            <div class="product-detail__variant-list" role="list">
              <button
                v-for="variant in variants"
                :key="variant.slug"
                type="button"
                class="product-variant-pill"
                :class="{ 'is-active': variant.slug === product.slug }"
                role="listitem"
                @click="selectVariant(variant.slug)"
              >
                <span
                  class="product-variant-pill__dot"
                  :style="{ background: variant.color_hex || '#c5cdd8' }"
                  aria-hidden="true"
                />
                {{ variant.color_name || variant.name }}
              </button>
            </div>
          </div>

          <div class="product-detail__purchase-row">
            <div
              class="product-detail__qty"
              role="group"
              :aria-label="`Quantity for ${product.name}`"
            >
              <button
                type="button"
                class="product-detail__qty-btn"
                :disabled="quantity <= 1 || !inStock"
                aria-label="Decrease quantity"
                @click="decreaseQuantity"
              >
                <Minus :size="16" />
              </button>
              <span class="product-detail__qty-value">{{ quantity }}</span>
              <button
                type="button"
                class="product-detail__qty-btn"
                :disabled="!inStock || atMaxQuantity"
                aria-label="Increase quantity"
                @click="increaseQuantity"
              >
                <Plus :size="16" />
              </button>
            </div>

            <div ref="actionsEl" class="product-detail__actions">
              <AppButton
                size="lg"
                class="button--busy-lg product-detail__add-btn"
                :disabled="adding || !inStock"
                :aria-busy="adding"
                :aria-label="inStock ? 'Add to cart' : 'Out of Stock'"
                @click="addToCart"
              >
                <template v-if="!inStock">Out of Stock</template>
                <template v-else>
                  <span class="button__busy-label" :class="{ 'is-loading': adding }">
                    <ShoppingBag :size="18" />
                    Add into Bag · {{ formatCurrency(product.price) }}
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

          <ul class="product-detail__trust-pills" aria-label="Purchase confidence">
            <li v-for="badge in trustBadges" :key="badge.label">
              <component :is="badge.icon" :size="18" aria-hidden="true" />
              <span>{{ badge.label }}</span>
            </li>
          </ul>

          <p
            v-if="showStockNote"
            class="stock-note"
            :class="{ 'stock-note--oos': !inStock, 'stock-note--low': lowStock }"
          >
            <template v-if="!inStock">Currently out of stock.</template>
            <template v-else-if="lowStock">Only {{ product.stock }} left — ships from Ventures Mart.</template>
          </p>
        </div>
      </div>

      <div v-if="productTabs.length" class="product-detail-tabs">
        <div class="product-detail-tabs__bar" role="tablist" aria-label="Product information">
          <button
            v-for="tab in productTabs"
            :key="tab.id"
            type="button"
            class="product-detail-tabs__tab"
            :class="{ 'is-active': activeTab?.id === tab.id }"
            role="tab"
            :aria-selected="activeTab?.id === tab.id"
            @click="setActiveTab(tab.id)"
          >
            {{ tab.label }}
          </button>
        </div>
        <div
          v-if="activeTab"
          class="product-detail-tabs__panel"
          role="tabpanel"
          :aria-label="activeTab.label"
        >
          <dl v-if="activeTab.specs.length" class="product-specs product-specs--tab">
            <div
              v-for="row in activeTab.specs"
              :key="`${row.label}-${row.value}`"
              class="product-specs__row"
            >
              <dt>{{ row.label }}</dt>
              <dd>{{ row.value }}</dd>
            </div>
          </dl>
          <ul v-if="activeTab.bullets.length" class="check-list">
            <li v-for="bullet in activeTab.bullets" :key="bullet">{{ bullet }}</li>
          </ul>
        </div>
      </div>

      <details class="product-detail__full-description">
        <summary>Full description</summary>
        <div class="product-detail__prose" v-html="safeHtml(product.description)" />
      </details>
    </section>

    <section
      v-if="product.seo?.faqs?.length"
      class="page-section product-detail__faqs"
      aria-labelledby="product-faq-title"
    >
      <h2 id="product-faq-title">Frequently asked questions</h2>
      <div class="product-faq-list">
        <details
          v-for="faq in product.seo.faqs"
          :key="faq.question"
          class="product-faq"
        >
          <summary>{{ faq.question }}</summary>
          <p>{{ faq.answer }}</p>
        </details>
      </div>
    </section>

    <section
      id="product-reviews"
      class="page-section product-detail__reviews"
      aria-labelledby="product-reviews-title"
    >
      <h2 id="product-reviews-title">Customer Reviews ({{ reviewCount }})</h2>

      <div class="product-reviews__layout">
        <div class="product-reviews__main">
          <div v-if="products.reviewsLoading" class="product-reviews__loading">Loading reviews…</div>

          <div v-else-if="!products.productReviews.length" class="product-reviews__empty">
            <MessageSquareQuote :size="28" aria-hidden="true" />
            <strong>No reviews yet</strong>
            <p>Be the first to review this product and share your thoughts!</p>
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
        </div>

        <aside class="product-reviews__aside">
          <div class="product-review-form">
            <h3>Share Your Experience</h3>
            <form class="product-review-form__body" @submit.prevent="onSubmitReview">
              <fieldset class="product-reviews__rating-field">
                <legend>Overall Rating</legend>
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
                v-model="reviewForm.author_name"
                label="Name"
                required
                autocomplete="name"
                placeholder="Your name"
              />
              <FormField
                v-model="reviewForm.body"
                label="Review Comment"
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
              <AppButton type="submit" :disabled="products.reviewSubmitting || reviewSuccess">
                {{ products.reviewSubmitting ? 'Submitting…' : 'Submit Review' }}
              </AppButton>
            </form>
          </div>
        </aside>
      </div>
    </section>

    <section
      v-if="product.seo?.suggested_links?.length"
      class="page-section product-detail__links"
      aria-labelledby="product-links-title"
    >
      <h2 id="product-links-title">Explore more</h2>
      <ul class="check-list">
        <li v-for="link in product.seo.suggested_links" :key="link.url">
          <RouterLink :to="link.url">{{ link.label }}</RouterLink>
        </li>
      </ul>
    </section>

    <section class="page-section product-detail__related">
      <h2>Related products</h2>
      <ProductGrid :products="products.related" />
    </section>

    <div
      class="product-detail__sticky"
      :class="{ 'is-visible': stickyVisible }"
      :aria-hidden="stickyVisible ? 'false' : 'true'"
    >
      <div class="product-detail__sticky-inner">
        <div class="product-detail__sticky-price">
          <strong>{{ formatCurrency(product.price) }}</strong>
          <span v-if="saleOff" class="price__off">-{{ saleOff }}% Off</span>
        </div>
        <AppButton
          size="sm"
          class="button--busy-sm product-detail__sticky-add"
          :disabled="adding || !inStock"
          :aria-busy="adding"
          :aria-label="inStock ? 'Add to cart' : 'Out of Stock'"
          :tabindex="stickyVisible ? 0 : -1"
          @click="addToCart"
        >
          <template v-if="!inStock">Out of Stock</template>
          <template v-else>
            <span class="button__busy-label" :class="{ 'is-loading': adding }">
              <ShoppingBag :size="16" />
              Add · {{ formatCurrency(product.price) }}
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
  </template>
</template>
