<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { Check, ChevronDown, Search } from '@lucide/vue';

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  options: {
    type: Array,
    default: () => [],
  },
  label: {
    type: String,
    required: true,
  },
  placeholder: {
    type: String,
    default: 'Select',
  },
  searchPlaceholder: {
    type: String,
    default: 'Search…',
  },
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);

const generatedId = useId();
const triggerId = `searchable-select-${generatedId}`;
const listId = `${triggerId}-list`;
const errorId = `${triggerId}-error`;
const rootRef = ref(null);
const searchRef = ref(null);
const open = ref(false);
const query = ref('');
const highlightIndex = ref(-1);

const selectedOption = computed(
  () => props.options.find((option) => String(option.value) === String(props.modelValue)) || null,
);
const filteredOptions = computed(() => {
  const needle = query.value.trim().toLowerCase();
  if (!needle) return props.options;

  return props.options.filter((option) => String(option.label).toLowerCase().includes(needle));
});

function close() {
  open.value = false;
  query.value = '';
  highlightIndex.value = -1;
}

function openMenu() {
  if (props.disabled) return;

  open.value = true;
  query.value = '';
  const selectedIndex = props.options.findIndex(
    (option) => String(option.value) === String(props.modelValue),
  );
  highlightIndex.value = selectedIndex >= 0 ? selectedIndex : 0;
  nextTick(() => searchRef.value?.focus());
}

function toggle() {
  if (open.value) {
    close();
  } else {
    openMenu();
  }
}

function selectOption(option) {
  emit('update:modelValue', option.value);
  close();
}

function moveHighlight(direction) {
  if (!filteredOptions.value.length) return;

  const current = highlightIndex.value;
  highlightIndex.value =
    direction > 0
      ? (current + 1 + filteredOptions.value.length) % filteredOptions.value.length
      : (current - 1 + filteredOptions.value.length) % filteredOptions.value.length;
}

function onSearchKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    moveHighlight(1);
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    moveHighlight(-1);
  } else if (event.key === 'Enter') {
    event.preventDefault();
    const option = filteredOptions.value[highlightIndex.value];
    if (option) selectOption(option);
  } else if (event.key === 'Escape') {
    event.preventDefault();
    close();
  }
}

function onDocumentClick(event) {
  if (!rootRef.value?.contains(event.target)) close();
}

watch(query, () => {
  highlightIndex.value = filteredOptions.value.length ? 0 : -1;
});

watch(
  () => props.disabled,
  (disabled) => {
    if (disabled) close();
  },
);

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
  <div
    ref="rootRef"
    class="searchable-select"
    :class="{ 'is-open': open, 'has-error': Boolean(error), 'is-disabled': disabled }"
  >
    <label class="searchable-select__label" :for="triggerId">
      {{ label }}<template v-if="required"> *</template>
    </label>
    <button
      :id="triggerId"
      type="button"
      class="searchable-select__trigger"
      :disabled="disabled"
      :aria-expanded="open"
      :aria-controls="listId"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? errorId : undefined"
      :aria-required="required ? 'true' : undefined"
      aria-haspopup="listbox"
      @click="toggle"
      @keydown.down.prevent="openMenu"
      @keydown.esc.prevent="close"
    >
      <span :class="{ 'is-placeholder': !selectedOption }">
        {{ selectedOption?.label || placeholder }}
      </span>
      <ChevronDown :size="18" aria-hidden="true" />
    </button>

    <div v-show="open" class="searchable-select__popover">
      <div class="searchable-select__search">
        <Search :size="17" aria-hidden="true" />
        <input
          ref="searchRef"
          v-model="query"
          type="search"
          :placeholder="searchPlaceholder"
          :aria-label="searchPlaceholder"
          autocomplete="off"
          @keydown="onSearchKeydown"
        />
      </div>
      <ul :id="listId" class="searchable-select__menu" role="listbox" :aria-label="label">
        <li
          v-for="(option, index) in filteredOptions"
          :key="`${option.value}-${option.label}`"
          role="option"
          class="searchable-select__option"
          :class="{
            'is-highlighted': index === highlightIndex,
            'is-selected': String(option.value) === String(modelValue),
          }"
          :aria-selected="String(option.value) === String(modelValue)"
          @mouseenter="highlightIndex = index"
          @mousedown.prevent
          @click="selectOption(option)"
        >
          <span>{{ option.label }}</span>
          <Check
            v-if="String(option.value) === String(modelValue)"
            :size="16"
            aria-hidden="true"
          />
        </li>
        <li v-if="!filteredOptions.length" class="searchable-select__empty">
          No matching options
        </li>
      </ul>
    </div>
    <small v-if="error" :id="errorId" class="form-field-error">{{ error }}</small>
  </div>
</template>
