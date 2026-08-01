<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Search, SlidersHorizontal, X } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';

const PRICE_BUCKET_STEP = 500;

const props = defineProps({
  query: {
    type: String,
    default: '',
  },
  category: {
    type: String,
    default: '',
  },
  minPrice: {
    type: Number,
    default: 0,
  },
  maxPrice: {
    type: Number,
    default: 2000,
  },
  priceFloor: {
    type: Number,
    default: 0,
  },
  priceCeiling: {
    type: Number,
    default: 2000,
  },
  sort: {
    type: String,
    default: 'featured',
  },
  categories: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits([
  'update:query',
  'update:category',
  'update:minPrice',
  'update:maxPrice',
  'update:sort',
]);

const filtersOpen = ref(false);

const categoryChips = computed(() => [
  { value: '', label: 'All' },
  ...props.categories.map((item) => ({
    value: item.slug,
    label: item.name,
  })),
]);

const priceBuckets = computed(() => {
  const ceiling = Math.max(Number(props.priceCeiling) || 0, PRICE_BUCKET_STEP - 1);
  const buckets = [];

  for (let start = 0; start <= ceiling; start += PRICE_BUCKET_STEP) {
    const end = start + PRICE_BUCKET_STEP - 1;
    buckets.push({
      id: `${start}-${end}`,
      min: start,
      max: end,
      label: `${start}–${end}`,
    });
  }

  return buckets;
});

const isFullPriceRange = computed(
  () =>
    Number(props.minPrice) === Number(props.priceFloor) &&
    Number(props.maxPrice) === Number(props.priceCeiling),
);

const activePriceBucketId = computed(() => {
  if (isFullPriceRange.value) return 'any';

  const match = priceBuckets.value.find(
    (bucket) =>
      Number(props.minPrice) === bucket.min && Number(props.maxPrice) === bucket.max,
  );

  return match?.id || null;
});

const sortOptions = [
  { value: 'featured', label: 'Featured' },
  { value: 'newest', label: 'Newest' },
  { value: 'rating', label: 'Top rated' },
  { value: 'price-asc', label: 'Price: low to high' },
  { value: 'price-desc', label: 'Price: high to low' },
];

function selectAnyPrice() {
  emit('update:minPrice', props.priceFloor);
  emit('update:maxPrice', props.priceCeiling);
}

function selectPriceBucket(bucket) {
  emit('update:minPrice', bucket.min);
  emit('update:maxPrice', bucket.max);
}

function openFilters() {
  filtersOpen.value = true;
}

function closeFilters() {
  filtersOpen.value = false;
}

function onKeydown(event) {
  if (event.key === 'Escape' && filtersOpen.value) {
    closeFilters();
  }
}

watch(filtersOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(() => {
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});
</script>

<template>
  <div class="shop-toolbar">
    <div class="shop-toolbar__search">
      <Search :size="18" aria-hidden="true" />
      <input
        :value="query"
        type="search"
        aria-label="Search products"
        placeholder="Search products by name or SKU"
        @input="emit('update:query', $event.target.value)"
      />
    </div>
    <button
      class="shop-toolbar__filters"
      type="button"
      aria-label="Filters"
      @click="openFilters"
    >
      <SlidersHorizontal :size="18" />
      <span class="shop-toolbar__filters-label">Filters</span>
    </button>
  </div>

  <Teleport to="body">
    <div v-if="filtersOpen" class="filters-dialog">
      <button
        class="filters-dialog__backdrop"
        type="button"
        aria-label="Close filters"
        @click="closeFilters"
      />
      <div
        class="filters-dialog__panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="shop-filters-title"
      >
        <div class="filters-dialog__header">
          <h2 id="shop-filters-title">Filters</h2>
          <button
            class="icon-button"
            type="button"
            aria-label="Close filters"
            @click="closeFilters"
          >
            <X :size="22" />
          </button>
        </div>
        <div class="filters-dialog__body">
          <div class="filters-field">
            <span class="filters-field__label">Category</span>
            <div class="filter-chips" role="list">
              <button
                v-for="chip in categoryChips"
                :key="chip.value || 'all'"
                type="button"
                class="filter-chip"
                :class="{ 'is-active': category === chip.value }"
                role="listitem"
                @click="emit('update:category', chip.value)"
              >
                {{ chip.label }}
              </button>
            </div>
          </div>

          <div class="filters-field">
            <span class="filters-field__label">Price</span>
            <div class="filter-chips" role="list" aria-label="Price range">
              <button
                type="button"
                class="filter-chip"
                :class="{ 'is-active': activePriceBucketId === 'any' }"
                role="listitem"
                @click="selectAnyPrice"
              >
                Any
              </button>
              <button
                v-for="bucket in priceBuckets"
                :key="bucket.id"
                type="button"
                class="filter-chip"
                :class="{ 'is-active': activePriceBucketId === bucket.id }"
                role="listitem"
                @click="selectPriceBucket(bucket)"
              >
                {{ bucket.label }}
              </button>
            </div>
          </div>

          <div class="filters-field">
            <span class="filters-field__label">Sort</span>
            <AppSelect
              :model-value="sort"
              :options="sortOptions"
              aria-label="Sort"
              placeholder="Featured"
              @update:model-value="emit('update:sort', $event)"
            />
          </div>
        </div>
        <div class="filters-dialog__footer">
          <AppButton type="button" @click="closeFilters">Done</AppButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
