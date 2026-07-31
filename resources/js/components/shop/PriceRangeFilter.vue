<script setup>
import { computed } from 'vue';

const props = defineProps({
  minPrice: {
    type: Number,
    required: true,
  },
  maxPrice: {
    type: Number,
    required: true,
  },
  priceFloor: {
    type: Number,
    required: true,
  },
  priceCeiling: {
    type: Number,
    required: true,
  },
  step: {
    type: Number,
    default: 50,
  },
});

const emit = defineEmits(['update:minPrice', 'update:maxPrice']);

const range = computed(() => Math.max(props.priceCeiling - props.priceFloor, 1));

const minPercent = computed(() => {
  return ((props.minPrice - props.priceFloor) / range.value) * 100;
});

const maxPercent = computed(() => {
  return ((props.maxPrice - props.priceFloor) / range.value) * 100;
});

const trackStyle = computed(() => ({
  left: `${minPercent.value}%`,
  right: `${100 - maxPercent.value}%`,
}));

function clamp(value) {
  return Math.min(props.priceCeiling, Math.max(props.priceFloor, value));
}

function snap(value) {
  const stepped = Math.round(value / props.step) * props.step;
  return clamp(stepped);
}

function setMin(raw) {
  const next = snap(Number(raw));
  const capped = Math.min(next, props.maxPrice);
  emit('update:minPrice', capped);
}

function setMax(raw) {
  const next = snap(Number(raw));
  const floored = Math.max(next, props.minPrice);
  emit('update:maxPrice', floored);
}

function onMinInput(event) {
  setMin(event.target.value);
}

function onMaxInput(event) {
  setMax(event.target.value);
}

function onMinBlur(event) {
  const value = Number(event.target.value);
  if (!Number.isFinite(value)) {
    emit('update:minPrice', props.priceFloor);
    return;
  }
  setMin(value);
}

function onMaxBlur(event) {
  const value = Number(event.target.value);
  if (!Number.isFinite(value)) {
    emit('update:maxPrice', props.priceCeiling);
    return;
  }
  setMax(value);
}
</script>

<template>
  <div class="price-range">
    <div class="price-range__label">
      <span>Price</span>
      <strong>₹{{ minPrice }} – ₹{{ maxPrice }}</strong>
    </div>

    <div class="price-range__slider">
      <div class="price-range__track" aria-hidden="true">
        <div class="price-range__fill" :style="trackStyle" />
      </div>
      <input
        class="price-range__thumb price-range__thumb--min"
        type="range"
        :min="priceFloor"
        :max="priceCeiling"
        :step="step"
        :value="minPrice"
        aria-label="Minimum price"
        @input="onMinInput"
      />
      <input
        class="price-range__thumb price-range__thumb--max"
        type="range"
        :min="priceFloor"
        :max="priceCeiling"
        :step="step"
        :value="maxPrice"
        aria-label="Maximum price"
        @input="onMaxInput"
      />
    </div>

    <div class="price-range__inputs">
      <label class="price-range__field">
        <span>Min</span>
        <div class="price-range__currency">
          <span aria-hidden="true">₹</span>
          <input
            type="number"
            :min="priceFloor"
            :max="maxPrice"
            :step="step"
            :value="minPrice"
            @change="onMinBlur"
            @blur="onMinBlur"
          />
        </div>
      </label>
      <label class="price-range__field">
        <span>Max</span>
        <div class="price-range__currency">
          <span aria-hidden="true">₹</span>
          <input
            type="number"
            :min="minPrice"
            :max="priceCeiling"
            :step="step"
            :value="maxPrice"
            @change="onMaxBlur"
            @blur="onMaxBlur"
          />
        </div>
      </label>
    </div>
  </div>
</template>
