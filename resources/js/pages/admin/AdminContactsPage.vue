<script setup>
import { onMounted, ref } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const loading = ref(true);
const messages = ref([]);
const selected = ref(null);
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/admin/contact-messages');
    messages.value = unwrapData(data) || data.data || [];
  } finally {
    loading.value = false;
  }
}

async function openMessage(message) {
  const { data } = await api.get(`/admin/contact-messages/${message.id}`);
  selected.value = unwrapData(data) || data.data;
}

function requestRemove(id) {
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value) return;
  const id = pendingDeleteId.value;
  await api.delete(`/admin/contact-messages/${id}`);
  if (selected.value?.id === id) {
    selected.value = null;
  }
  pendingDeleteId.value = null;
  await load();
}

onMounted(load);
</script>

<template>
  <div class="admin-detail-grid">
    <div class="admin-panel">
      <h2>Contact messages</h2>
      <div v-if="loading" class="admin-muted">Loading…</div>
      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>From</th>
              <th>Date</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="message in messages" :key="message.id">
              <td>
                <button type="button" class="linkish" @click="openMessage(message)">
                  <strong>{{ message.name }}</strong>
                </button>
                <div class="admin-muted">{{ message.email }}</div>
              </td>
              <td>{{ message.created_at ? new Date(message.created_at).toLocaleString() : '—' }}</td>
              <td>
                <AppButton type="button" variant="ghost" size="sm" @click="requestRemove(message.id)">
                  Delete
                </AppButton>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!messages.length" class="admin-empty">No messages.</p>
      </div>
    </div>

    <div class="admin-panel">
      <h3>Message</h3>
      <template v-if="selected">
        <p>
          <strong>{{ selected.name }}</strong><br />
          <a :href="`mailto:${selected.email}`">{{ selected.email }}</a>
        </p>
        <p>{{ selected.message }}</p>
      </template>
      <p v-else class="admin-empty">Select a message to read it.</p>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete message?"
      message="This contact message will be permanently removed."
      confirm-label="Delete"
      danger
      @confirm="remove"
    />
  </div>
</template>

<style scoped>
.linkish {
  background: none;
  border: 0;
  color: inherit;
  cursor: pointer;
  padding: 0;
  text-align: left;
}
</style>
