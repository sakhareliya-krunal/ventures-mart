<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { brandAssets } from '@/constants/assets';

const AUTO_ADVANCE_MS = 4000;
const SLIDE_TRANSITION_MS = 700;
const SWIPE_THRESHOLD_PX = 44;

const activeSlide = ref(0);
const previousSlideIndex = ref(null);
const slideDirection = ref('next');
const transitionReady = ref(false);

const slides = computed(() => brandAssets.heroCarouselSlides || []);
const currentSlide = computed(() => slides.value[activeSlide.value] || slides.value[0] || null);

let touchStartX = 0;
let autoAdvanceTimer = null;
let previousSlideTimer = null;
let transitionFrame = null;
let transitionStartFrame = null;

function slideCount() {
  return slides.value.length;
}

function clearPreviousSlideTimer() {
  if (previousSlideTimer) {
    window.clearTimeout(previousSlideTimer);
    previousSlideTimer = null;
  }
}

function clearTransitionFrames() {
  if (transitionFrame) {
    window.cancelAnimationFrame(transitionFrame);
    transitionFrame = null;
  }

  if (transitionStartFrame) {
    window.cancelAnimationFrame(transitionStartFrame);
    transitionStartFrame = null;
  }
}

function clearAutoAdvanceTimer() {
  if (autoAdvanceTimer) {
    window.clearInterval(autoAdvanceTimer);
    autoAdvanceTimer = null;
  }
}

function startAutoAdvanceTimer() {
  clearAutoAdvanceTimer();

  if (slideCount() <= 1) {
    return;
  }

  autoAdvanceTimer = window.setInterval(() => {
    goToSlide(activeSlide.value + 1, 'next', { resetTimer: false });
  }, AUTO_ADVANCE_MS);
}

function resetAutoAdvanceTimer() {
  startAutoAdvanceTimer();
}

function goToSlide(index, direction = 'next', { resetTimer = true } = {}) {
  const count = slideCount();
  if (!count) return;

  const nextIndex = ((index % count) + count) % count;
  if (nextIndex === activeSlide.value) {
    if (resetTimer) {
      resetAutoAdvanceTimer();
    }
    return;
  }

  clearPreviousSlideTimer();
  clearTransitionFrames();
  transitionReady.value = false;
  slideDirection.value = direction;
  previousSlideIndex.value = activeSlide.value;
  activeSlide.value = nextIndex;

  transitionFrame = window.requestAnimationFrame(() => {
    transitionStartFrame = window.requestAnimationFrame(() => {
      transitionReady.value = true;
      transitionFrame = null;
      transitionStartFrame = null;
    });
  });

  previousSlideTimer = window.setTimeout(() => {
    previousSlideIndex.value = null;
    transitionReady.value = false;
    previousSlideTimer = null;
  }, SLIDE_TRANSITION_MS);

  if (resetTimer) {
    resetAutoAdvanceTimer();
  }
}

function nextSlide() {
  goToSlide(activeSlide.value + 1, 'next');
}

function previousSlide() {
  goToSlide(activeSlide.value - 1, 'previous');
}

function onTouchStart(event) {
  touchStartX = event.changedTouches?.[0]?.clientX || 0;
}

function onTouchEnd(event) {
  const endX = event.changedTouches?.[0]?.clientX || 0;
  const delta = endX - touchStartX;
  touchStartX = 0;

  if (Math.abs(delta) < SWIPE_THRESHOLD_PX) {
    return;
  }

  if (delta < 0) {
    nextSlide();
  } else {
    previousSlide();
  }
}

onMounted(() => {
  startAutoAdvanceTimer();
});

onUnmounted(() => {
  clearAutoAdvanceTimer();
  clearPreviousSlideTimer();
  clearTransitionFrames();
});
</script>

<template>
  <section
    class="hero hero--carousel"
    @touchstart.passive="onTouchStart"
    @touchend.passive="onTouchEnd"
  >
    <div class="hero__slides" aria-live="polite">
      <picture
        v-for="(slide, index) in slides"
        :key="slide.largeDesktop || slide.desktop || slide.mobile || slide.webp || slide.jpg"
        class="hero__slide"
        :class="{
          'is-active': index === activeSlide,
          'is-previous': index === previousSlideIndex,
          'is-entering-next': index === activeSlide && slideDirection === 'next' && previousSlideIndex !== null,
          'is-entering-previous': index === activeSlide && slideDirection === 'previous' && previousSlideIndex !== null,
          'is-exiting-next': index === previousSlideIndex && slideDirection === 'next',
          'is-exiting-previous': index === previousSlideIndex && slideDirection === 'previous',
          'is-transitioning': transitionReady && (index === activeSlide || index === previousSlideIndex),
        }"
      >
        <source v-if="slide.largeDesktop" media="(min-width: 1440px)" :srcset="slide.largeDesktop" />
        <source v-if="slide.desktop" media="(min-width: 1025px)" :srcset="slide.desktop" />
        <source v-if="slide.webp" :srcset="slide.webp" type="image/webp" />
        <img
          :src="slide.mobile || slide.desktop || slide.jpg"
          :alt="index === activeSlide ? slide.alt : ''"
          :aria-hidden="index === activeSlide ? undefined : 'true'"
          :fetchpriority="index === 0 ? 'high' : 'low'"
          :loading="index === 0 ? 'eager' : 'lazy'"
          decoding="async"
        />
      </picture>
    </div>
    <div class="hero__veil" aria-hidden="true" />

    <div v-if="slides.length > 1" class="hero__carousel-controls" aria-label="Hero carousel controls">
      <button
        class="hero__nav-button hero__nav-button--previous"
        type="button"
        aria-label="Previous hero slide"
        @click="previousSlide"
      >
        <ChevronLeft :size="20" aria-hidden="true" />
      </button>
      <button
        class="hero__nav-button hero__nav-button--next"
        type="button"
        aria-label="Next hero slide"
        @click="nextSlide"
      >
        <ChevronRight :size="20" aria-hidden="true" />
      </button>
    </div>
    <span class="sr-only">{{ currentSlide?.alt }}</span>
  </section>
</template>
