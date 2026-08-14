<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
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
const inputRef = ref(null);
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
const inputDisplay = computed(() => (open.value ? query.value : selectedOption.value?.label || ''));

function close() {
  open.value = false;
  query.value = '';
  highlightIndex.value = -1;
}

function openMenu() {
  if (props.disabled || open.value) return;

  open.value = true;
  query.value = '';
  const selectedIndex = props.options.findIndex(
    (option) => String(option.value) === String(props.modelValue),
  );
  highlightIndex.value = selectedIndex >= 0 ? selectedIndex : 0;
  nextTick(() => inputRef.value?.focus());
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

function onInput(event) {
  if (props.disabled) return;

  if (!open.value) {
    open.value = true;
  }

  query.value = event.target.value;
}

function onFocus() {
  openMenu();
}

function onKeydown(event) {
  if (props.disabled) return;

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    if (!open.value) {
      openMenu();
    } else {
      moveHighlight(1);
    }
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    if (open.value) moveHighlight(-1);
  } else if (event.key === 'Enter') {
    if (!open.value) return;
    event.preventDefault();
    const option = filteredOptions.value[highlightIndex.value];
    if (option) selectOption(option);
  } else if (event.key === 'Escape') {
    event.preventDefault();
    close();
  }
}

function onPointerDownOutside(event) {
  if (!rootRef.value?.contains(event.target)) close();
}

function onFocusOut(event) {
  if (!rootRef.value?.contains(event.relatedTarget)) close();
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

onMounted(() => document.addEventListener('pointerdown', onPointerDownOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDownOutside));
</script>

<template>
  <div
    ref="rootRef"
    class="searchable-select"
    :class="{ 'is-open': open, 'has-error': Boolean(error), 'is-disabled': disabled }"
    @focusout="onFocusOut"
  >
    <label class="searchable-select__label" :for="triggerId">
      {{ label }}<template v-if="required"> *</template>
    </label>
    <div class="searchable-select__control">
      <input
        :id="triggerId"
        ref="inputRef"
        class="searchable-select__input"
        type="text"
        inputmode="search"
        autocomplete="off"
        role="combobox"
        :disabled="disabled"
        :value="inputDisplay"
        :placeholder="open ? searchPlaceholder : placeholder"
        :aria-expanded="open"
        :aria-controls="listId"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="error ? errorId : undefined"
        :aria-required="required ? 'true' : undefined"
        aria-autocomplete="list"
        aria-haspopup="listbox"
        @input="onInput"
        @focus="onFocus"
        @keydown="onKeydown"
      />
      <ChevronDown :size="18" aria-hidden="true" />
    </div>

    <div v-show="open" class="searchable-select__popover">
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
