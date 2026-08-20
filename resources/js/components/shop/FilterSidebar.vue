<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Search, SlidersHorizontal, X } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import { formatCurrency } from '@/utils/format';

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

const categoryOptions = computed(() => [
  { value: '', label: 'All Categories' },
  ...props.categories.map((item) => ({
    value: item.slug,
    label: item.name,
  })),
]);

function nicePriceStep(span) {
  if (span <= 1500) {
    return 500;
  }
  if (span <= 6000) {
    return 1000;
  }
  return Math.ceil(span / 3 / 1000) * 1000;
}

const pricePresets = computed(() => {
  const floor = Number(props.priceFloor) || 0;
  const ceiling = Math.max(Number(props.priceCeiling) || 0, floor + 1);
  const span = ceiling - floor;
  const step = nicePriceStep(span);
  const tier1Max = Math.min(floor + step, ceiling);
  const tier2Max = Math.min(floor + step * 2, ceiling);

  return [
    {
      id: 'low',
      min: floor,
      max: tier1Max,
      label: `< ${formatCurrency(tier1Max)}`,
    },
    {
      id: 'mid',
      min: tier1Max,
      max: tier2Max,
      label: `${formatCurrency(tier1Max)} – ${formatCurrency(tier2Max)}`,
    },
    {
      id: 'high',
      min: tier2Max,
      max: ceiling,
      label: `${formatCurrency(tier2Max)}+`,
    },
  ];
});

const isFullPriceRange = computed(
  () =>
    Number(props.minPrice) === Number(props.priceFloor) &&
    Number(props.maxPrice) === Number(props.priceCeiling),
);

const activePricePresetId = computed(() => {
  if (isFullPriceRange.value) {
    return null;
  }

  const match = pricePresets.value.find(
    (preset) =>
      Number(props.minPrice) === preset.min && Number(props.maxPrice) === preset.max,
  );

  return match?.id || null;
});

const sliderMax = computed({
  get: () => Number(props.maxPrice),
  set: (value) => {
    emit('update:minPrice', props.priceFloor);
    emit('update:maxPrice', Number(value));
  },
});

function selectPricePreset(preset) {
  emit('update:minPrice', preset.min);
  emit('update:maxPrice', preset.max);
}

function onSliderInput(event) {
  sliderMax.value = Number(event.target.value);
}

function resetAll() {
  emit('update:query', '');
  emit('update:category', '');
  emit('update:minPrice', props.priceFloor);
  emit('update:maxPrice', props.priceCeiling);
  emit('update:sort', 'featured');
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
  <aside class="filter-matrix filter-matrix--desktop" aria-label="Filters">
    <div class="filter-matrix__card">
      <div class="filter-matrix__header">
        <div class="filter-matrix__title">
          <SlidersHorizontal :size="20" aria-hidden="true" />
          <h2>Filter Matrix</h2>
        </div>
        <button class="filter-matrix__reset" type="button" @click="resetAll">
          Reset all
        </button>
      </div>

      <div class="filter-matrix__section">
        <label class="filter-matrix__label" for="filter-matrix-search-desktop">Search keywords</label>
        <div class="filter-matrix__search">
          <Search :size="18" aria-hidden="true" />
          <input
            id="filter-matrix-search-desktop"
            :value="query"
            type="search"
            placeholder="Type name here…"
            @input="emit('update:query', $event.target.value)"
          />
        </div>
      </div>

      <div class="filter-matrix__section">
        <span class="filter-matrix__label">Category</span>
        <AppSelect
          :model-value="category"
          :options="categoryOptions"
          aria-label="Category"
          placeholder="All Categories"
          @update:model-value="emit('update:category', $event)"
        />
      </div>

      <div class="filter-matrix__section filter-matrix__section--price">
        <span class="filter-matrix__label">Price ceiling</span>
        <div class="filter-matrix__pills" role="list" aria-label="Price presets">
          <button
            v-for="preset in pricePresets"
            :key="preset.id"
            type="button"
            class="filter-matrix__pill"
            :class="{ 'is-active': activePricePresetId === preset.id }"
            role="listitem"
            @click="selectPricePreset(preset)"
          >
            {{ preset.label }}
          </button>
        </div>

        <div class="filter-matrix__slider-wrap">
          <input
            class="filter-matrix__slider"
            type="range"
            :min="priceFloor"
            :max="priceCeiling"
            :value="sliderMax"
            :aria-valuemin="priceFloor"
            :aria-valuemax="priceCeiling"
            :aria-valuenow="sliderMax"
            aria-label="Maximum price"
            @input="onSliderInput"
          />
          <div class="filter-matrix__slider-labels">
            <span>Min</span>
            <span>Max</span>
          </div>
        </div>
      </div>
    </div>
  </aside>

  <div class="shop-toolbar shop-toolbar--mobile">
    <div class="shop-toolbar__search">
      <Search :size="18" aria-hidden="true" />
      <input
        :value="query"
        type="search"
        aria-label="Search products"
        placeholder="Type name here…"
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
        class="filters-dialog__panel filter-matrix__card filter-matrix__card--drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="shop-filters-title"
      >
        <div class="filter-matrix__header">
          <div class="filter-matrix__title">
            <SlidersHorizontal :size="20" aria-hidden="true" />
            <h2 id="shop-filters-title">Filter Matrix</h2>
          </div>
          <div class="filter-matrix__header-actions">
            <button class="filter-matrix__reset" type="button" @click="resetAll">
              Reset all
            </button>
            <button
              class="icon-button filter-matrix__close"
              type="button"
              aria-label="Close filters"
              @click="closeFilters"
            >
              <X :size="22" />
            </button>
          </div>
        </div>

        <div class="filters-dialog__body">
          <div class="filter-matrix__section">
            <label class="filter-matrix__label" for="filter-matrix-search-mobile">Search keywords</label>
            <div class="filter-matrix__search">
              <Search :size="18" aria-hidden="true" />
              <input
                id="filter-matrix-search-mobile"
                :value="query"
                type="search"
                placeholder="Type name here…"
                @input="emit('update:query', $event.target.value)"
              />
            </div>
          </div>

          <div class="filter-matrix__section">
            <span class="filter-matrix__label">Category</span>
            <AppSelect
              :model-value="category"
              :options="categoryOptions"
              aria-label="Category"
              placeholder="All Categories"
              @update:model-value="emit('update:category', $event)"
            />
          </div>

          <div class="filter-matrix__section filter-matrix__section--price">
            <span class="filter-matrix__label">Price ceiling</span>
            <div class="filter-matrix__pills" role="list" aria-label="Price presets">
              <button
                v-for="preset in pricePresets"
                :key="preset.id"
                type="button"
                class="filter-matrix__pill"
                :class="{ 'is-active': activePricePresetId === preset.id }"
                role="listitem"
                @click="selectPricePreset(preset)"
              >
                {{ preset.label }}
              </button>
            </div>

            <div class="filter-matrix__slider-wrap">
              <input
                class="filter-matrix__slider"
                type="range"
                :min="priceFloor"
                :max="priceCeiling"
                :value="sliderMax"
                :aria-valuemin="priceFloor"
                :aria-valuemax="priceCeiling"
                :aria-valuenow="sliderMax"
                aria-label="Maximum price"
                @input="onSliderInput"
              />
              <div class="filter-matrix__slider-labels">
                <span>Min</span>
                <span>Max</span>
              </div>
            </div>
          </div>
        </div>

        <div class="filters-dialog__footer">
          <AppButton type="button" @click="closeFilters">Done</AppButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
