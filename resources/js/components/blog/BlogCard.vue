<script setup>
import { ArrowUpRight, CalendarDays } from '@lucide/vue';
import { RouterLink } from 'vue-router';

defineProps({
  post: {
    type: Object,
    required: true,
  },
  featured: {
    type: Boolean,
    default: false,
  },
});

function formatDate(value) {
  if (!value) return '';

  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
}
</script>

<template>
  <article class="blog-card" :class="{ 'blog-card--featured': featured }">
    <RouterLink :to="`/blog/${post.slug}`" class="blog-card__media" :aria-label="`Read ${post.title}`">
      <img
        v-if="post.cover_image"
        :src="post.cover_image"
        :alt="post.seo?.metadata?.image_alt_text || post.title"
        :loading="featured ? 'eager' : 'lazy'"
      />
      <span v-else class="blog-card__media-fallback" aria-hidden="true">VM</span>
      <span v-if="featured" class="blog-card__featured-label">Featured story</span>
    </RouterLink>
    <div class="blog-card__copy">
      <time v-if="post.published_at" :datetime="post.published_at">
        <CalendarDays :size="15" aria-hidden="true" />
        {{ formatDate(post.published_at) }}
      </time>
      <h3>
        <RouterLink :to="`/blog/${post.slug}`">{{ post.title }}</RouterLink>
      </h3>
      <p>{{ post.excerpt }}</p>
      <RouterLink class="blog-card__link" :to="`/blog/${post.slug}`">
        Read article
        <ArrowUpRight :size="16" aria-hidden="true" />
      </RouterLink>
    </div>
  </article>
</template>
