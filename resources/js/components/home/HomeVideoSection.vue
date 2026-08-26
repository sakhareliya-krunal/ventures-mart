<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';

const videos = [
  {
    id: 'home-video-2',
    src: '/videos/home/home-video-2.mp4',
  },
  {
    id: 'home-video-3',
    src: '/videos/home/home-video-3.mp4',
  },
];

const videoEls = ref([]);

function setVideoRef(el, index) {
  if (el) {
    videoEls.value[index] = el;
  }
}

function playVideo(video) {
  if (!video) {
    return;
  }

  video.muted = true;
  video.loop = true;
  video.playsInline = true;

  const promise = video.play();
  if (promise?.catch) {
    promise.catch(() => undefined);
  }
}

function playAll() {
  videoEls.value.forEach(playVideo);
}

function handleVisibilityChange() {
  if (document.visibilityState === 'visible') {
    playAll();
  }
}

onMounted(async () => {
  await nextTick();
  playAll();
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => {
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
  <section class="home-video page-section" aria-labelledby="home-video-title">
    <header class="home-video__header">
      <h2 id="home-video-title">See It In Action</h2>
      <p>Watch quick product moments from the Ventures Mart collection.</p>
    </header>

    <div class="home-video__rail">
      <article v-for="(video, index) in videos" :key="video.id" class="home-video__card">
        <video
          :ref="(el) => setVideoRef(el, index)"
          class="home-video__media"
          :src="video.src"
          autoplay
          muted
          loop
          playsinline
          preload="auto"
          aria-hidden="true"
          @canplay="(event) => playVideo(event.target)"
          @pause="(event) => playVideo(event.target)"
        />
      </article>
    </div>
  </section>
</template>

<style scoped>
.home-video {
  overflow: hidden;
}

.home-video__header {
  margin: 0 auto clamp(1.1rem, 2.2vw, 1.75rem);
  max-width: 42rem;
  text-align: center;
}

.home-video__header h2 {
  font-size: clamp(1.65rem, 3vw, 2.15rem);
  letter-spacing: 0;
  line-height: 1.14;
  margin: 0;
}

.home-video__header p {
  color: var(--color-muted);
  font-size: clamp(0.95rem, 1.4vw, 1.05rem);
  line-height: 1.6;
  margin: 0.55rem auto 0;
  max-width: 32rem;
}

.home-video__rail {
  display: grid;
  gap: clamp(1rem, 2vw, 1.5rem);
  grid-template-columns: repeat(2, clamp(13rem, 18vw, 18rem));
  justify-content: center;
  margin-inline: auto;
  max-width: 42rem;
}

.home-video__card {
  aspect-ratio: 9 / 16;
  background: #f6f8fc;
  border-radius: 0.5rem;
  box-shadow: 0 1rem 2.5rem rgba(8, 22, 66, 0.1);
  min-width: 0;
  overflow: hidden;
  position: relative;
}

.home-video__media {
  display: block;
  height: 100%;
  object-fit: cover;
  width: 100%;
}

@media (max-width: 1024px) {
  .home-video__rail {
    grid-auto-columns: min(44vw, 18rem);
    grid-auto-flow: column;
    grid-template-columns: none;
    margin-inline: calc(clamp(1rem, 4vw, 1.25rem) * -1);
    overflow-x: auto;
    overscroll-behavior-x: contain;
    padding: 0.15rem clamp(1rem, 4vw, 1.25rem) 0.8rem;
    scroll-padding-inline: clamp(1rem, 4vw, 1.25rem);
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
  }

  .home-video__rail::-webkit-scrollbar {
    display: none;
  }

  .home-video__card {
    scroll-snap-align: start;
  }
}

@media (max-width: 640px) {
  .home-video__rail {
    grid-auto-columns: min(68vw, 17rem);
  }
}
</style>
