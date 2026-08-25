<script setup>
import { computed, ref, useId } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  modelValue: {
    type: [String, Number],
    default: '',
  },
  type: {
    type: String,
    default: 'text',
  },
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  rows: {
    type: Number,
    default: 4,
  },
  placeholder: {
    type: String,
    default: '',
  },
  autocomplete: {
    type: String,
    default: undefined,
  },
  error: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);

const fieldId = useId();
const errorId = computed(() => `${fieldId}-error`);
const describedBy = computed(() => (props.error ? errorId.value : undefined));
const revealed = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => {
  if (!isPassword.value) return props.type;
  return revealed.value ? 'text' : 'password';
});

function toggleReveal() {
  revealed.value = !revealed.value;
}
</script>

<template>
  <label :for="fieldId" :class="{ 'has-error': Boolean(error) }">
    {{ label }}
    <textarea
      v-if="type === 'textarea'"
      :id="fieldId"
      :value="modelValue"
      :disabled="disabled"
      :rows="rows"
      :placeholder="placeholder"
      :aria-required="required ? 'true' : undefined"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      :class="{ 'is-invalid': Boolean(error) }"
      @input="emit('update:modelValue', $event.target.value)"
    />
    <span v-else class="form-field__control" :class="{ 'has-toggle': isPassword }">
      <input
        :id="fieldId"
        :type="inputType"
        :value="modelValue"
        :disabled="disabled"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :aria-required="required ? 'true' : undefined"
      :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="describedBy"
        :class="{ 'is-invalid': Boolean(error) }"
        @input="emit('update:modelValue', $event.target.value)"
      />
      <button
        v-if="isPassword"
        type="button"
        class="form-field__toggle"
        :class="{ 'is-revealed': revealed }"
        :aria-label="revealed ? 'Hide password' : 'Show password'"
        :aria-pressed="revealed ? 'true' : 'false'"
        :disabled="disabled"
        @click="toggleReveal"
      >
        <EyeOff v-if="revealed" :size="20" :stroke-width="1.75" aria-hidden="true" />
        <Eye v-else :size="20" :stroke-width="1.75" aria-hidden="true" />
      </button>
    </span>
    <small v-if="error" :id="errorId" class="form-field-error">{{ error }}</small>
  </label>
</template>