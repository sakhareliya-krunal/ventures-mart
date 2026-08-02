<script setup>
import { onMounted } from 'vue';
import { ChevronRight } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import { RouterLink } from 'vue-router';
import EmptyState from '@/components/ui/EmptyState.vue';
import Breadcrumb from '@/components/ui/Breadcrumb.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PageHero from '@/components/ui/PageHero.vue';
import { usePostsStore } from '@/stores/posts';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const posts = usePostsStore();

useHead(() =>
  seoHeadFromServer({
    title: `Blog | ${theme.brandName}`,
    description: `Guides and tips on kids toys, school lunches, and stainless steel lunch boxes from ${theme.brandName}.`,
    canonical: '/blog',
  }),
);

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

onMounted(() => posts.fetchList());
</script>

<template>
  <div class="blog-page">
    <PageHero
      eyebrow="Journal"
      title="Blog"
      lead="Practical guides for school lunches, creative play, and shopping with confidence—written for Indian families."
    />

    <section class="page-section blog-index" aria-labelledby="blog-index-title">
      <Breadcrumb
        class="blog-page__crumb"
        :items="[
          { label: 'Home', to: '/' },
          { label: 'Blog' },
        ]"
      />
      <h2 id="blog-index-title" class="sr-only">Latest posts</h2>

      <LoadingSpinner v-if="posts.loading" page label="Loading posts" />

      <EmptyState
        v-else-if="!posts.list.length"
        title="No posts yet"
        description="New guides for toys and lunch boxes will appear here soon."
        action-label="Shop collection"
        action-to="/shop"
      />

      <div v-else class="blog-grid">
        <article v-for="post in posts.list" :key="post.id" class="blog-card">
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
            <p>{{ post.excerpt }}</p>
            <RouterLink class="blog-card__link" :to="`/blog/${post.slug}`">
              Read more
              <ChevronRight :size="16" />
            </RouterLink>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>
