<script setup>
defineProps({
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
</script>

<template>
  <label :class="{ 'has-error': Boolean(error) }">
    {{ label }}
    <textarea
      v-if="type === 'textarea'"
      :value="modelValue"
      :required="required"
      :disabled="disabled"
      :rows="rows"
      :placeholder="placeholder"
      :aria-invalid="error ? 'true' : undefined"
      :class="{ 'is-invalid': Boolean(error) }"
      @input="emit('update:modelValue', $event.target.value)"
    />
    <input
      v-else
      :type="type"
      :value="modelValue"
      :required="required"
      :disabled="disabled"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :aria-invalid="error ? 'true' : undefined"
      :class="{ 'is-invalid': Boolean(error) }"
      @input="emit('update:modelValue', $event.target.value)"
    />
    <small v-if="error" class="form-field-error">{{ error }}</small>
  </label>
</template>
