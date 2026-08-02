<script setup>
import { computed, onMounted } from 'vue';
import { useHead } from '@unhead/vue';
import { RouterLink } from 'vue-router';
import CategoryPillars from '@/components/home/CategoryPillars.vue';
import HeroBanner from '@/components/home/HeroBanner.vue';
import HomeTrustQuotes from '@/components/home/HomeTrustQuotes.vue';
import HomeWhyStrip from '@/components/home/HomeWhyStrip.vue';
import OfferBanner from '@/components/home/OfferBanner.vue';
import ServiceStrip from '@/components/home/ServiceStrip.vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import { homeRails } from '@/constants/home';
import { useCategoriesStore } from '@/stores/categories';
import { usePostsStore } from '@/stores/posts';
import { useProductsStore } from '@/stores/products';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const categories = useCategoriesStore();
const products = useProductsStore();
const posts = usePostsStore();

useHead(() =>
  seoHeadFromServer({
    title: `${theme.brandName} | Premium Stainless Steel Lunch Boxes Online in India`,
    description:
      'Buy premium stainless steel lunch boxes for office, school and kids. Leak-proof, BPA-free and durable lunch boxes with fast delivery across India.',
    canonical: '/',
  }),
);

const lunchPicks = computed(() =>
  products.featured.filter((product) => product.category === 'lunch-box').slice(0, 4),
);

const toyPicks = computed(() =>
  products.featured.filter((product) => product.category === 'toys').slice(0, 4),
);

const blogPicks = computed(() => (posts.list || []).slice(0, 3));

function formatDate(value) {
  if (!value) {
    return '';
  }

  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
}

onMounted(async () => {
  await Promise.all([
    categories.list.length ? Promise.resolve() : categories.fetchAll(),
    products.fetchFeatured(),
    products.fetchSale(),
    posts.list.length ? Promise.resolve() : posts.fetchList(),
  ]);
});
</script>

<template>
  <HeroBanner />
  <ServiceStrip />
  <CategoryPillars />

  <section v-if="lunchPicks.length" class="page-section">
    <SectionHeader
      :title="homeRails.lunch.title"
      :description="homeRails.lunch.description"
      :action="homeRails.lunch.action"
    />
    <ProductGrid :products="lunchPicks" />
  </section>

  <OfferBanner />

  <section v-if="toyPicks.length" class="page-section page-section--soft">
    <SectionHeader
      :title="homeRails.toys.title"
      :description="homeRails.toys.description"
      :action="homeRails.toys.action"
    />
    <ProductGrid :products="toyPicks" />
  </section>

  <HomeWhyStrip />
  <HomeTrustQuotes />

  <section v-if="blogPicks.length" class="page-section" :aria-label="homeRails.blog.title">
    <SectionHeader
      :title="homeRails.blog.title"
      :description="homeRails.blog.description"
      :action="homeRails.blog.action"
    />
    <div class="blog-grid">
      <article v-for="post in blogPicks" :key="post.id" class="blog-card">
        <RouterLink :to="`/blog/${post.slug}`" class="blog-card__media">
          <img
            v-if="post.cover_image"
            :src="post.cover_image"
            :alt="post.title"
            loading="lazy"
          />
        </RouterLink>
        <div class="blog-card__copy">
          <time v-if="post.published_at" :datetime="post.published_at">
            {{ formatDate(post.published_at) }}
          </time>
          <h3>
            <RouterLink :to="`/blog/${post.slug}`">{{ post.title }}</RouterLink>
          </h3>
          <p v-if="post.excerpt">{{ post.excerpt }}</p>
        </div>
      </article>
    </div>
  </section>

  <section v-if="products.sale.length" class="page-section page-section--tinted">
    <SectionHeader
      :title="homeRails.sale.title"
      :description="homeRails.sale.description"
      :action="homeRails.sale.action"
    />
    <ProductGrid :products="products.sale" />
  </section>
</template>
