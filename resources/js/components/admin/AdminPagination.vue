<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';

const props = defineProps({
  page: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
  from: { type: Number, default: 0 },
  to: { type: Number, default: 0 },
});

const emit = defineEmits(['update:page', 'page']);

const showPager = computed(() => props.total > 0 && props.lastPage > 1);

const pages = computed(() => {
  const current = Math.max(1, props.page);
  const last = Math.max(1, props.lastPage);
  if (last <= 7) {
    return Array.from({ length: last }, (_, index) => index + 1);
  }

  const items = new Set([1, last, current, current - 1, current + 1, current - 2, current + 2]);
  const sorted = [...items].filter((value) => value >= 1 && value <= last).sort((a, b) => a - b);
  const result = [];

  for (const value of sorted) {
    const previous = result[result.length - 1];
    if (previous && value - previous > 1) {
      result.push('ellipsis');
    }
    result.push(value);
  }

  return result;
});


const root = ref(null);
const controls = ref(null);
const fullMeasure = ref(null);
const compact = ref(window.innerWidth <= 1024);
const compactCount = ref(3);
let observer;
let frame = null;
let generation = 0;
let disposed = false;

const compactPages = computed(() => {
  const last = Math.max(1, props.lastPage);
  const count = Math.min(compactCount.value, last);
  const start = Math.max(1, Math.min(props.page - Math.floor(count / 2), last - count + 1));
  return Array.from({ length: count }, (_, index) => start + index);
});
const visiblePages = computed(() => compact.value ? compactPages.value : pages.value);

function scheduleLayout() {
  if (disposed) return;
  if (frame !== null) window.cancelAnimationFrame(frame);
  const request = ++generation;
  frame = window.requestAnimationFrame(async () => {
    frame = null;
    if (!root.value?.clientWidth || !fullMeasure.value) return;
    compact.value = window.innerWidth <= 1024 || fullMeasure.value.scrollWidth > root.value.clientWidth;
    compactCount.value = 3;
    await nextTick();
    if (request !== generation) return;
    if (compact.value && controls.value?.scrollWidth > controls.value?.clientWidth) {
      compactCount.value = 1;
    }
  });
}

watch(root, (el, previous) => {
  if (previous) observer?.unobserve(previous);
  if (el) observer?.observe(el);
  scheduleLayout();
}, { flush: 'post' });
watch(() => [props.page, props.lastPage, props.total], scheduleLayout, { flush: 'post' });

onMounted(() => {
  if (typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(scheduleLayout);
    if (root.value) observer.observe(root.value);
  }
  window.addEventListener('resize', scheduleLayout);
  document.fonts?.ready.then(scheduleLayout);
  scheduleLayout();
});
onBeforeUnmount(() => {
  disposed = true;
  generation++;
  if (frame !== null) window.cancelAnimationFrame(frame);
  observer?.disconnect();
  window.removeEventListener('resize', scheduleLayout);
});

function go(page) {
  const next = Number(page);
  if (!Number.isInteger(next) || next < 1 || next > props.lastPage || next === props.page) return;
  emit('update:page', next);
  emit('page', next);
}
</script>

<template>
  <nav v-if="total > 0" ref="root" class="admin-pagination" :class="{ 'admin-pagination--compact': compact }" aria-label="Pagination">
    <p class="admin-pagination__range">
      Showing
      <strong>{{ from }}</strong>
      –
      <strong>{{ to }}</strong>
      of
      <strong>{{ total.toLocaleString('en-IN') }}</strong>
    </p>

    <div v-if="showPager" ref="controls" class="admin-pagination__controls">
      <AppButton
        type="button"
        variant="secondary"
        size="sm"
        class="admin-pagination__nav"
        :disabled="page <= 1"
        aria-label="Previous page"
        @click="go(page - 1)"
      >
        <ChevronLeft :size="16" />
        <span class="admin-pagination__nav-label">Prev</span>
      </AppButton>

      <div class="admin-pagination__pages" role="list">
        <template v-for="(item, index) in visiblePages" :key="`${item}-${index}`">
          <span v-if="item === 'ellipsis'" class="admin-pagination__ellipsis" aria-hidden="true">…</span>
          <button
            v-else
            type="button"
            class="admin-pagination__page"
            :class="{ 'is-active': item === page }"
            :aria-current="item === page ? 'page' : undefined"
            :aria-label="`Page ${item}`"
            @click="go(item)"
          >
            {{ item }}
          </button>
        </template>
      </div>

      <AppButton
        type="button"
        variant="secondary"
        size="sm"
        class="admin-pagination__nav"
        :disabled="page >= lastPage"
        aria-label="Next page"
        @click="go(page + 1)"
      >
        <span class="admin-pagination__nav-label">Next</span>
        <ChevronRight :size="16" />
      </AppButton>
    </div>

    <!-- Measure the full layout without exposing duplicate controls to assistive technology. -->
    <div v-if="showPager" class="admin-pagination__measure" aria-hidden="true" inert>
      <div ref="fullMeasure" class="admin-pagination__controls">
        <AppButton variant="secondary" size="sm" class="admin-pagination__nav" tabindex="-1">
          <ChevronLeft :size="16" /><span>Prev</span>
        </AppButton>
        <div class="admin-pagination__pages">
          <template v-for="(item, index) in pages" :key="index">
            <span v-if="item === 'ellipsis'" class="admin-pagination__ellipsis">…</span>
            <span v-else class="admin-pagination__page">{{ item }}</span>
          </template>
        </div>
        <AppButton variant="secondary" size="sm" class="admin-pagination__nav" tabindex="-1">
          <span>Next</span><ChevronRight :size="16" />
        </AppButton>
      </div>
    </div>
  </nav>
</template>
