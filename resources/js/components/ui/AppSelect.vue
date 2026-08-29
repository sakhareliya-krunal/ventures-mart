<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, ChevronDown } from '@lucide/vue';

let selectId = 0;

const props = defineProps({
  id: {
    type: String,
    default: '',
  },
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
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Select',
  },
  label: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue']);

const generatedId = `app-select-${++selectId}`;
const triggerId = computed(() => props.id || generatedId);
const listId = computed(() => `${triggerId.value}-listbox`);
const errorId = computed(() => `${triggerId.value}-error`);
const accessibleLabel = computed(() => props.ariaLabel || props.label || props.placeholder);
const open = ref(false);
const rootRef = ref(null);
const triggerRef = ref(null);
const listRef = ref(null);
const highlightIndex = ref(-1);

const enabledOptions = computed(() => props.options.filter((option) => !option.disabled));

const selectedOption = computed(
  () => props.options.find((option) => String(option.value) === String(props.modelValue)) || null,
);

const displayLabel = computed(() => selectedOption.value?.label || props.placeholder);

function selectedIndex() {
  return props.options.findIndex((option) => String(option.value) === String(props.modelValue));
}

function firstEnabledIndex() {
  return props.options.findIndex((option) => !option.disabled);
}

function focusTrigger() {
  nextTick(() => triggerRef.value?.focus());
}

function close({ restoreFocus = false } = {}) {
  open.value = false;
  highlightIndex.value = -1;
  if (restoreFocus) {
    focusTrigger();
  }
}

function openMenu() {
  if (props.disabled || !props.options.length) {
    return;
  }

  open.value = true;
  const currentIndex = selectedIndex();
  highlightIndex.value = currentIndex >= 0 ? currentIndex : firstEnabledIndex();
  nextTick(() => listRef.value?.focus());
}

function toggle() {
  if (props.disabled) {
    return;
  }

  if (open.value) {
    close();
    return;
  }

  openMenu();
}

function selectOption(option) {
  if (props.disabled || option.disabled) {
    return;
  }

  emit('update:modelValue', option.value);
  close({ restoreFocus: true });
}

function onDocumentClick(event) {
  if (!rootRef.value?.contains(event.target)) {
    close();
  }
}

function onTriggerKeydown(event) {
  if (props.disabled) {
    return;
  }

  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    toggle();
  } else if (event.key === 'ArrowDown') {
    event.preventDefault();
    openMenu();
  } else if (event.key === 'Escape') {
    close({ restoreFocus: true });
  }
}

function moveHighlight(direction) {
  if (!enabledOptions.value.length) {
    return;
  }

  let nextIndex = highlightIndex.value;
  do {
    nextIndex += direction;
    if (nextIndex < 0) {
      nextIndex = props.options.length - 1;
    } else if (nextIndex >= props.options.length) {
      nextIndex = 0;
    }
  } while (props.options[nextIndex]?.disabled);

  highlightIndex.value = nextIndex;
}

function onListKeydown(event) {
  if (!props.options.length) {
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    close({ restoreFocus: true });
    return;
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    moveHighlight(1);
    return;
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault();
    moveHighlight(-1);
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
      const index = selectedIndex();
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
  <div
    ref="rootRef"
    class="app-select"
    :class="{ 'is-open': open, 'is-disabled': disabled, 'has-error': error }"
  >
    <label v-if="label" class="app-select__label" :for="triggerId">
      {{ label }}<span v-if="required" aria-hidden="true"> *</span>
    </label>
    <button
      :id="triggerId"
      ref="triggerRef"
      type="button"
      class="app-select__trigger"
      :aria-label="label ? undefined : accessibleLabel"
      :aria-controls="listId"
      :aria-describedby="error ? errorId : undefined"
      :aria-expanded="open"
      :aria-invalid="error ? 'true' : undefined"
      :disabled="disabled"
      aria-haspopup="listbox"
      @click="toggle"
      @keydown="onTriggerKeydown"
    >
      <span class="app-select__value">{{ displayLabel }}</span>
      <ChevronDown :size="18" class="app-select__chevron" aria-hidden="true" />
    </button>

    <ul
      v-show="open"
      :id="listId"
      ref="listRef"
      class="app-select__menu"
      role="listbox"
      tabindex="-1"
      :aria-label="accessibleLabel"
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
          'is-disabled': option.disabled,
        }"
        :aria-disabled="option.disabled ? 'true' : undefined"
        :aria-selected="String(option.value) === String(modelValue)"
        @mouseenter="!option.disabled && (highlightIndex = index)"
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
    <small v-if="error" :id="errorId" class="app-select__error">{{ error }}</small>
  </div>
</template>
