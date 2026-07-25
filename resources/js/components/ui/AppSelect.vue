<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, ChevronDown } from '@lucide/vue';

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  options: {
    type: Array,
    default: () => [],
  },
  ariaLabel: {
    type: String,
    default: 'Select',
  },
  placeholder: {
    type: String,
    default: 'Select',
  },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const rootRef = ref(null);
const listRef = ref(null);
const highlightIndex = ref(-1);

const selectedOption = computed(
  () => props.options.find((option) => String(option.value) === String(props.modelValue)) || null,
);

const displayLabel = computed(() => selectedOption.value?.label || props.placeholder);

function close() {
  open.value = false;
  highlightIndex.value = -1;
}

function toggle() {
  open.value = !open.value;
  if (open.value) {
    const index = props.options.findIndex(
      (option) => String(option.value) === String(props.modelValue),
    );
    highlightIndex.value = index >= 0 ? index : 0;
    nextTick(() => listRef.value?.focus());
  }
}

function selectOption(option) {
  emit('update:modelValue', option.value);
  close();
}

function onDocumentClick(event) {
  if (!rootRef.value?.contains(event.target)) {
    close();
  }
}

function onTriggerKeydown(event) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    toggle();
  } else if (event.key === 'ArrowDown') {
    event.preventDefault();
    if (!open.value) {
      toggle();
    }
  } else if (event.key === 'Escape') {
    close();
  }
}

function onListKeydown(event) {
  if (!props.options.length) {
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    close();
    return;
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    highlightIndex.value = (highlightIndex.value + 1) % props.options.length;
    return;
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault();
    highlightIndex.value =
      highlightIndex.value <= 0 ? props.options.length - 1 : highlightIndex.value - 1;
    return;
  }

  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    const option = props.options[highlightIndex.value];
    if (option) {
      selectOption(option);
    }
  }
}

watch(
  () => props.modelValue,
  () => {
    if (open.value) {
      const index = props.options.findIndex(
        (option) => String(option.value) === String(props.modelValue),
      );
      if (index >= 0) {
        highlightIndex.value = index;
      }
    }
  },
);

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
  <div ref="rootRef" class="app-select" :class="{ 'is-open': open }">
    <button
      type="button"
      class="app-select__trigger"
      :aria-label="ariaLabel"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="toggle"
      @keydown="onTriggerKeydown"
    >
      <span class="app-select__value">{{ displayLabel }}</span>
      <ChevronDown :size="18" class="app-select__chevron" aria-hidden="true" />
    </button>

    <ul
      v-show="open"
      ref="listRef"
      class="app-select__menu"
      role="listbox"
      tabindex="-1"
      :aria-label="ariaLabel"
      @keydown="onListKeydown"
    >
      <li
        v-for="(option, index) in options"
        :key="`${option.value}-${option.label}`"
        role="option"
        class="app-select__option"
        :class="{
          'is-selected': String(option.value) === String(modelValue),
          'is-highlighted': index === highlightIndex,
        }"
        :aria-selected="String(option.value) === String(modelValue)"
        @mouseenter="highlightIndex = index"
        @click="selectOption(option)"
      >
        <span>{{ option.label }}</span>
        <Check
          v-if="String(option.value) === String(modelValue)"
          :size="16"
          class="app-select__check"
          aria-hidden="true"
        />
      </li>
    </ul>
  </div>
</template>
