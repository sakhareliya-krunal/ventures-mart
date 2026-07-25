<script setup>
import { onBeforeUnmount, watch } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Confirm',
  },
  message: {
    type: String,
    default: 'Are you sure you want to continue?',
  },
  confirmLabel: {
    type: String,
    default: 'Confirm',
  },
  cancelLabel: {
    type: String,
    default: 'Cancel',
  },
  danger: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:open', 'confirm', 'cancel']);

function close() {
  emit('update:open', false);
  emit('cancel');
}

function confirm() {
  emit('confirm');
  emit('update:open', false);
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    close();
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      window.addEventListener('keydown', onKeydown);
      document.body.style.overflow = 'hidden';
    } else {
      window.removeEventListener('keydown', onKeydown);
      document.body.style.overflow = '';
    }
  },
);

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="confirm-dialog" role="presentation">
      <button class="confirm-dialog__backdrop" type="button" aria-label="Close dialog" @click="close" />
      <div
        class="confirm-dialog__panel"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="'confirm-dialog-title'"
        :aria-describedby="'confirm-dialog-message'"
      >
        <h2 id="confirm-dialog-title">{{ title }}</h2>
        <p id="confirm-dialog-message">{{ message }}</p>
        <div class="confirm-dialog__actions">
          <AppButton type="button" variant="ghost" @click="close">
            {{ cancelLabel }}
          </AppButton>
          <AppButton
            type="button"
            :variant="danger ? 'primary' : 'primary'"
            :class="{ 'button--danger': danger }"
            @click="confirm"
          >
            {{ confirmLabel }}
          </AppButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
