<script setup>
import { onBeforeUnmount, watch } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import { useScrollLock } from '@/composables/useScrollLock';

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
  busy: {
    type: Boolean,
    default: false,
  },
  busyLabel: {
    type: String,
    default: 'Working…',
  },
  closeOnConfirm: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['update:open', 'confirm', 'cancel']);

function close() {
  if (props.busy) return;
  emit('update:open', false);
  emit('cancel');
}

function confirm() {
  if (props.busy) return;
  emit('confirm');
  if (props.closeOnConfirm) {
    emit('update:open', false);
  }
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    close();
  }
}

useScrollLock('confirm-dialog', () => props.open);

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      window.addEventListener('keydown', onKeydown);
    } else {
      window.removeEventListener('keydown', onKeydown);
    }
  },
);

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="confirm-dialog" role="presentation">
      <button
        class="confirm-dialog__backdrop"
        type="button"
        aria-label="Close dialog"
        :disabled="busy"
        @click="close"
      />
      <div
        class="confirm-dialog__panel"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        aria-describedby="confirm-dialog-message"
        :aria-busy="busy ? 'true' : 'false'"
      >
        <h2 id="confirm-dialog-title">{{ title }}</h2>
        <p id="confirm-dialog-message">{{ message }}</p>
        <div class="confirm-dialog__actions">
          <AppButton type="button" variant="ghost" :disabled="busy" @click="close">
            {{ cancelLabel }}
          </AppButton>
          <AppButton
            type="button"
            class="confirm-dialog__confirm auth-submit"
            :variant="danger ? 'danger' : 'primary'"
            :loading="busy"
            @click="confirm"
          >
            {{ confirmLabel }}
          </AppButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
