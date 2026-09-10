<script setup>
import { useId } from 'vue';
defineProps({ label: String, busy: Boolean, error: String, multiple: Boolean, accept: { type: String, default: 'image/*' } });
const emit = defineEmits(['select']);
const id = useId();
function select(event) { emit('select', Array.from(event.target.files || [])); event.target.value = ''; }
</script>
<template><div class="admin-media-field" :aria-busy="busy"><label :for="id">{{ label || 'Upload images' }}</label><input :id="id" type="file" :accept="accept" :multiple="multiple" :disabled="busy" @change="select" /><p v-if="busy" role="status">Uploading…</p><p v-if="error" class="form-error" role="alert">{{ error }}</p><slot /></div></template>
