<script setup>
import { computed } from 'vue';
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

function go(page) {
  const next = Number(page);
  if (!Number.isInteger(next) || next < 1 || next > props.lastPage || next === props.page) return;
  emit('update:page', next);
  emit('page', next);
}
</script>

<template>
  <nav v-if="total > 0" class="admin-pagination" aria-label="Pagination">
    <p class="admin-pagination__range">
      Showing
      <strong>{{ from }}</strong>
      –
      <strong>{{ to }}</strong>
      of
      <strong>{{ total.toLocaleString('en-IN') }}</strong>
    </p>

    <div v-if="showPager" class="admin-pagination__controls">
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
        <span>Prev</span>
      </AppButton>

      <div class="admin-pagination__pages" role="list">
        <template v-for="(item, index) in pages" :key="`${item}-${index}`">
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
        <span>Next</span>
        <ChevronRight :size="16" />
      </AppButton>
    </div>
  </nav>
</template>
