<script setup>
import { computed, onMounted } from 'vue';
import { BookOpen, RefreshCw } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import BlogCard from '@/components/blog/BlogCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Breadcrumb from '@/components/ui/Breadcrumb.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PageHero from '@/components/ui/PageHero.vue';
import { usePostsStore } from '@/stores/posts';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const posts = usePostsStore();
const featuredPost = computed(() => posts.list[0] || null);
const remainingPosts = computed(() => posts.list.slice(1));

useHead(() =>
  seoHeadFromServer({
    title: `Blog | ${theme.brandName}`,
    description: `Guides and tips on kids toys, school lunches, and stainless steel lunch boxes from ${theme.brandName}.`,
    canonical: '/blog',
  }),
);

onMounted(() => posts.fetchList());
</script>

<template>
  <div class="blog-page">
    <PageHero
      eyebrow="The Ventures Journal"
      title="Ideas for happier everyday moments"
      lead="Practical, thoughtful guides for school lunches, creative play, and confident family shopping."
    >
      <template #aside>
        <BookOpen :size="24" aria-hidden="true" />
        <strong>{{ posts.list.length || 'Fresh' }}</strong>
        {{ posts.list.length === 1 ? 'story for your family' : 'stories for your family' }}
      </template>
    </PageHero>

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

      <div v-else-if="posts.error" class="blog-index__error" role="alert">
        <h2>We couldn’t load the journal</h2>
        <p>{{ posts.error }}</p>
        <AppButton type="button" variant="secondary" @click="posts.fetchList()">
          <RefreshCw :size="17" aria-hidden="true" />
          Try again
        </AppButton>
      </div>

      <EmptyState
        v-else-if="!posts.list.length"
        title="No posts yet"
        description="New guides for toys and lunch boxes will appear here soon."
        action-label="Shop collection"
        action-to="/shop"
      />

      <template v-else>
        <BlogCard v-if="featuredPost" :post="featuredPost" featured />
        <div v-if="remainingPosts.length" class="blog-index__section-heading">
          <div>
            <span class="eyebrow">More from the journal</span>
            <h2>Latest stories</h2>
          </div>
          <p>Helpful reading for playtime, mealtime, and everything in between.</p>
        </div>
        <div v-if="remainingPosts.length" class="blog-grid">
          <BlogCard v-for="post in remainingPosts" :key="post.id" :post="post" />
        </div>
      </template>
    </section>
  </div>
</template>
