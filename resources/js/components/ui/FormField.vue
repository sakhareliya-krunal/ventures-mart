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
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
  <label>
    {{ label }}
    <textarea
      v-if="type === 'textarea'"
      :value="modelValue"
      :required="required"
      :disabled="disabled"
      :rows="rows"
      :placeholder="placeholder"
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
      @input="emit('update:modelValue', $event.target.value)"
    />
  </label>
</template>
