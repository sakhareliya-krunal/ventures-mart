<script setup>
import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronRight } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import Breadcrumb from '@/components/ui/Breadcrumb.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { usePostsStore } from '@/stores/posts';
import { useThemeStore } from '@/stores/theme';
import { safeHtml } from '@/utils/html';

const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const posts = usePostsStore();

const slug = computed(() => String(route.params.slug || ''));
const post = computed(() => posts.current);

useHead({
  title: () =>
    post.value ? `${post.value.title} | ${theme.brandName}` : `Blog | ${theme.brandName}`,
});

function formatDate(value) {
  if (!value) {
    return '';
  }

  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date(value));
}

async function load() {
  try {
    await posts.fetchBySlug(slug.value);
  } catch {
    await router.replace('/blog');
  }
}

watch(slug, load, { immediate: true });
</script>

<template>
  <LoadingSpinner v-if="posts.loading" page label="Loading post" />
  <div v-else-if="post" class="blog-page blog-page--article">
    <section class="page-section blog-article">
      <Breadcrumb
        class="blog-article__crumb"
        :items="[{ label: 'Blog', to: '/blog' }, { label: post.title }]"
      />

      <header class="blog-article__header">
        <span class="eyebrow">Journal</span>
        <h1>{{ post.title }}</h1>
        <time v-if="post.published_at" :datetime="post.published_at">
          {{ formatDate(post.published_at) }}
        </time>
      </header>

      <div v-if="post.cover_image" class="blog-article__cover">
        <img :src="post.cover_image" :alt="post.title" />
      </div>

      <div class="blog-prose" v-html="safeHtml(post.body)" />

      <div class="blog-article__cta">
        <p>Explore the curated toys and lunch boxes behind these guides.</p>
        <AppButton to="/shop" size="lg">
          Shop collection
          <ChevronRight :size="18" />
        </AppButton>
      </div>
    </section>
  </div>
</template>
