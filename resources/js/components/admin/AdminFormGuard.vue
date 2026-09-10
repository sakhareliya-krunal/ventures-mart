<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
const root = ref(null);
const dirty = ref(false);
const confirmOpen = ref(false);
let resolveLeave;
let observer;
function accept() { dirty.value = false; confirmOpen.value = false; resolveLeave?.(true); resolveLeave = null; }
function reject() { confirmOpen.value = false; resolveLeave?.(false); resolveLeave = null; }
function beforeUnload(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
onBeforeRouteLeave(() => {
  // Successful submit handlers navigate while the submit button is still busy.
  if (!dirty.value || root.value?.querySelector('form [type="submit"]:disabled')) return true;
  confirmOpen.value = true;
  return new Promise((resolve) => { resolveLeave = resolve; });
});
async function focusError() {
  await nextTick();
  const field = root.value?.querySelector('[aria-invalid="true"], .form-field--error input, .form-field--error textarea');
  field?.focus();
}
onMounted(() => {
  window.addEventListener('beforeunload', beforeUnload);
  observer = new MutationObserver((records) => {
    if (records.some((record) => record.type === 'childList' && [...record.addedNodes].some((node) => node.nodeType === 1 && node.matches?.('.form-error')))) focusError();
  });
  observer.observe(root.value, { childList: true, subtree: true });
});
onBeforeUnmount(() => { observer?.disconnect(); window.removeEventListener('beforeunload', beforeUnload); resolveLeave?.(false); });
</script>
<template><div ref="root" class="admin-page-body" @input="dirty = true" @change="dirty = true" @submit="focusError"><slot /><ConfirmDialog :open="confirmOpen" title="Leave unsaved changes?" message="Your edits have not been saved. Leave this page and discard them?" confirm-label="Discard changes" cancel-label="Keep editing" :close-on-confirm="false" @confirm="accept" @cancel="reject" /></div></template>
