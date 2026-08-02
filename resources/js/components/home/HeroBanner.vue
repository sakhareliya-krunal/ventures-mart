<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { ChevronRight } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { brandAssets } from '@/constants/assets';
import { homeHero } from '@/constants/home';
import { useThemeStore } from '@/stores/theme';

const SLIDE_INTERVAL_MS = 5000;

const theme = useThemeStore();
const heroRef = ref(null);
const videoRef = ref(null);
const useSlideshow = ref(false);
const activeSlide = ref(0);

const slides = computed(() =>
  brandAssets.heroSlidesMobileTablet.map((webp, index) => ({
    webp,
    jpg: brandAssets.heroSlidesMobileTabletJpg[index],
  })),
);

let motionQuery = null;
let compactQuery = null;
let visibilityObserver = null;
let isVisible = true;
let slideTimer = null;

function canPlayVideo() {
  return Boolean(videoRef.value) && !useSlideshow.value && isVisible && !motionQuery?.matches;
}

function syncVideoPlayback() {
  const video = videoRef.value;
  if (!video) {
    return;
  }

  if (!canPlayVideo()) {
    video.pause();
    return;
  }

  video.play().catch(() => {});
}

function applyDesktopVideo({ forceReload = false } = {}) {
  const video = videoRef.value;
  if (!video || useSlideshow.value) {
    return;
  }

  const nextSrc = brandAssets.heroVideo;
  const currentSrc = video.currentSrc || video.getAttribute('src') || '';
  if (!forceReload && currentSrc.endsWith(nextSrc)) {
    syncVideoPlayback();
    return;
  }

  video.setAttribute('src', nextSrc);
  video.load();
}

function clearSlideTimer() {
  if (slideTimer) {
    window.clearInterval(slideTimer);
    slideTimer = null;
  }
}

function canAdvanceSlides() {
  return useSlideshow.value && isVisible && !motionQuery?.matches && slides.value.length > 1;
}

function syncSlideshow() {
  clearSlideTimer();
  if (!canAdvanceSlides()) {
    return;
  }

  slideTimer = window.setInterval(() => {
    activeSlide.value = (activeSlide.value + 1) % slides.value.length;
  }, SLIDE_INTERVAL_MS);
}

function syncMediaMode() {
  const nextSlideshow = Boolean(compactQuery?.matches);
  if (nextSlideshow === useSlideshow.value) {
    if (useSlideshow.value) {
      syncSlideshow();
    } else {
      syncVideoPlayback();
    }
    return;
  }

  useSlideshow.value = nextSlideshow;
  activeSlide.value = 0;
  clearSlideTimer();

  if (useSlideshow.value) {
    videoRef.value?.pause();
    syncSlideshow();
    return;
  }

  applyDesktopVideo({ forceReload: true });
}

function onLoadedData() {
  syncVideoPlayback();
}

onMounted(() => {
  motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  compactQuery = window.matchMedia('(max-width: 1024px)');
  motionQuery.addEventListener('change', syncMediaMode);
  compactQuery.addEventListener('change', syncMediaMode);

  if (heroRef.value && 'IntersectionObserver' in window) {
    visibilityObserver = new IntersectionObserver(
      ([entry]) => {
        isVisible = Boolean(entry?.isIntersecting);
        if (useSlideshow.value) {
          syncSlideshow();
        } else {
          syncVideoPlayback();
        }
      },
      { threshold: 0.35 },
    );
    visibilityObserver.observe(heroRef.value);
  }

  syncMediaMode();
  if (!useSlideshow.value) {
    applyDesktopVideo({ forceReload: true });
  }
});

onUnmounted(() => {
  motionQuery?.removeEventListener('change', syncMediaMode);
  compactQuery?.removeEventListener('change', syncMediaMode);
  visibilityObserver?.disconnect();
  clearSlideTimer();
});
</script>

<template>
  <section ref="heroRef" class="hero" :class="{ 'hero--slideshow': useSlideshow }">
    <div v-if="useSlideshow" class="hero__slides" aria-hidden="true">
      <picture
        v-for="(slide, index) in slides"
        :key="slide.webp"
        class="hero__slide"
        :class="{ 'is-active': index === activeSlide }"
      >
        <source :srcset="slide.webp" type="image/webp" />
        <img
          :src="slide.jpg"
          alt=""
          :fetchpriority="index === 0 ? 'high' : 'low'"
          :loading="index === 0 ? 'eager' : 'lazy'"
          decoding="async"
        />
      </picture>
    </div>
    <video
      v-else
      ref="videoRef"
      class="hero__video"
      :poster="brandAssets.heroPoster"
      autoplay
      muted
      loop
      playsinline
      webkit-playsinline
      preload="metadata"
      aria-hidden="true"
      @loadeddata="onLoadedData"
    />
    <div class="hero__veil" aria-hidden="true" />
    <div class="hero__inner">
      <div class="hero__copy">
        <h1>{{ theme.brandName }}</h1>
        <p class="hero__lead">
          {{ homeHero.lead }}
        </p>
        <p class="hero__assurances">{{ homeHero.assurances }}</p>
        <div class="hero__actions">
          <AppButton to="/category/toys" size="lg">
            Shop toys
            <ChevronRight :size="18" />
          </AppButton>
          <AppButton to="/category/lunch-box" variant="secondary" size="lg" class="hero__cta-secondary">
            Shop lunch boxes
          </AppButton>
        </div>
      </div>
    </div>
  </section>
</template>
