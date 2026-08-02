<script setup>
import { computed, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { ChevronRight } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { usePostsStore } from '@/stores/posts';
import { useThemeStore } from '@/stores/theme';
import { safeHtml, stripHtml } from '@/utils/html';
import { seoHeadFromRecord } from '@/utils/seoHead';
import { splitPostBody } from '@/utils/splitPostBody';

const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const posts = usePostsStore();

const slug = computed(() => String(route.params.slug || ''));
const post = computed(() => posts.current);

const bodyParts = computed(() => splitPostBody(safeHtml(post.value?.body || '')));

const lead = computed(() => {
  if (!post.value) return '';
  if (post.value.excerpt) return post.value.excerpt;
  return stripHtml(bodyParts.value.introHtml, 160);
});

useHead(() =>
  post.value
    ? seoHeadFromRecord(post.value, {
        title: `${post.value.title} | ${theme.brandName}`,
        description: lead.value || `${theme.brandName} journal`,
        canonical: `/blog/${post.value.slug}`,
        image: post.value.cover_image,
      })
    : { title: `Blog | ${theme.brandName}` },
);

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

function sectionTone(index) {
  return index % 2 === 0 ? 'soft' : 'plain';
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
  <div v-else-if="post" class="article-premium">
    <section
      class="article-premium__hero"
      :class="{ 'article-premium__hero--fallback': !post.cover_image }"
      aria-labelledby="blog-hero-title"
    >
      <div v-if="post.cover_image" class="article-premium__hero-media" aria-hidden="true">
        <img :src="post.cover_image" alt="" />
      </div>
      <div class="article-premium__hero-scrim" aria-hidden="true" />
      <div class="article-premium__hero-inner page-section">
        <div class="article-premium__hero-copy">
          <p class="article-premium__brand">{{ theme.brandName }}</p>
          <h1 id="blog-hero-title">{{ post.title }}</h1>
          <p v-if="lead" class="article-premium__lead">{{ lead }}</p>
          <time
            v-if="post.published_at"
            class="article-premium__meta"
            :datetime="post.published_at"
          >
            {{ formatDate(post.published_at) }}
          </time>
          <div class="article-premium__actions">
            <AppButton to="/shop" size="lg">
              Shop collection
              <ChevronRight :size="18" />
            </AppButton>
            <AppButton to="/blog" variant="secondary" size="lg">Back to journal</AppButton>
          </div>
        </div>
      </div>
    </section>

    <section
      v-if="bodyParts.introHtml"
      class="article-premium__intro"
      aria-label="Introduction"
    >
      <div
        class="page-section article-premium__intro-inner article-premium__prose"
        v-html="bodyParts.introHtml"
      />
    </section>

    <section
      v-for="(section, index) in bodyParts.sections"
      :id="section.id"
      :key="section.id"
      class="article-premium__section"
      :class="`article-premium__section--${sectionTone(index)}`"
      :aria-labelledby="`${section.id}-title`"
    >
      <div class="page-section article-premium__section-inner">
        <span class="eyebrow">Journal</span>
        <h2 :id="`${section.id}-title`">{{ section.title }}</h2>
        <div
          v-if="section.html"
          class="article-premium__prose"
          v-html="section.html"
        />
      </div>
    </section>

    <section class="article-premium__close" aria-labelledby="blog-close-title">
      <div class="page-section article-premium__close-inner">
        <span class="eyebrow">Ready when you are</span>
        <h2 id="blog-close-title">Explore the curated collection</h2>
        <p>
          Browse
          <RouterLink to="/category/toys">toys</RouterLink>
          and
          <RouterLink to="/category/lunch-box">lunch boxes</RouterLink>
          behind these guides—or return to the
          <RouterLink to="/blog">journal</RouterLink>.
        </p>
        <div class="article-premium__close-actions">
          <AppButton to="/shop" size="lg">
            Shop collection
            <ChevronRight :size="18" />
          </AppButton>
          <AppButton to="/blog" variant="secondary" size="lg">More articles</AppButton>
        </div>
      </div>
    </section>

    <section
      v-if="post.seo?.faqs?.length"
      class="article-premium__section article-premium__section--plain"
      aria-labelledby="blog-faq-title"
    >
      <div class="page-section article-premium__section-inner">
        <span class="eyebrow">FAQ</span>
        <h2 id="blog-faq-title">Frequently asked questions</h2>
        <article v-for="faq in post.seo.faqs" :key="faq.question" class="article-premium__prose">
          <h3>{{ faq.question }}</h3>
          <p>{{ faq.answer }}</p>
        </article>
      </div>
    </section>
  </div>
</template>
