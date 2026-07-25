<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { ChevronRight } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { brandAssets } from '@/constants/assets';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const heroRef = ref(null);
const videoRef = ref(null);
let motionQuery = null;
let mobileQuery = null;
let visibilityObserver = null;
let isVisible = true;

function canPlay() {
  return Boolean(videoRef.value) && isVisible && !motionQuery?.matches;
}

function syncPlayback() {
  const video = videoRef.value;
  if (!video) {
    return;
  }

  if (!canPlay()) {
    video.pause();
    return;
  }

  video.play().catch(() => {});
}

function resolveVideoSrc() {
  return mobileQuery?.matches ? brandAssets.heroVideoMobile : brandAssets.heroVideo;
}

function applyVideoSource({ forceReload = false } = {}) {
  const video = videoRef.value;
  if (!video) {
    return;
  }

  const nextSrc = resolveVideoSrc();
  const currentSrc = video.currentSrc || video.getAttribute('src') || '';
  if (!forceReload && currentSrc.endsWith(nextSrc)) {
    syncPlayback();
    return;
  }

  video.setAttribute('src', nextSrc);
  video.load();
}

function onMobileQueryChange() {
  applyVideoSource({ forceReload: true });
}

function onLoadedData() {
  syncPlayback();
}

onMounted(() => {
  motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  mobileQuery = window.matchMedia('(max-width: 720px)');
  motionQuery.addEventListener('change', syncPlayback);
  mobileQuery.addEventListener('change', onMobileQueryChange);

  if (heroRef.value && 'IntersectionObserver' in window) {
    visibilityObserver = new IntersectionObserver(
      ([entry]) => {
        isVisible = Boolean(entry?.isIntersecting);
        syncPlayback();
      },
      { threshold: 0.35 },
    );
    visibilityObserver.observe(heroRef.value);
  }

  applyVideoSource({ forceReload: true });
});

onUnmounted(() => {
  motionQuery?.removeEventListener('change', syncPlayback);
  mobileQuery?.removeEventListener('change', onMobileQueryChange);
  visibilityObserver?.disconnect();
});
</script>

<template>
  <section ref="heroRef" class="hero">
    <video
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
          Premium toys and lunch boxes for school, play, and everyday family life across India.
        </p>
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
