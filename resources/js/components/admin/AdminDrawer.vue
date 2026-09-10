<script setup>
import AdminDialog from './AdminDialog.vue';
defineProps({ open: Boolean, busy: Boolean, title: String });
defineEmits(['update:open', 'close']);
</script>
<template><AdminDialog :open="open" :busy="busy" :title="title" drawer @update:open="$emit('update:open', $event)" @close="$emit('close')"><slot /><template v-if="$slots.footer" #footer><slot name="footer" /></template></AdminDialog></template>
