<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight, Inbox, Mail, RefreshCw, Trash2, X } from '@lucide/vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { useAdminNavigationCountsStore } from '@/stores/adminNavigationCounts';
import { emailHref } from '@/utils/contactLinks';

const navigationCounts = useAdminNavigationCountsStore();
const loading = ref(true);
const refreshing = ref(false);
const messages = ref([]);
const selected = ref(null);
const search = ref('');
const page = ref(1);
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
  from: null,
  to: null,
  unread_count: 0,
});
const listError = ref('');
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const deleting = ref(false);
const markingAll = ref(false);
let searchTimer = null;

const resultsLabel = computed(() => {
  if (!meta.value.total) return 'No messages';
  return `${meta.value.from}–${meta.value.to} of ${meta.value.total}`;
});

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function initialFor(name) {
  return String(name || '?').trim().charAt(0).toUpperCase() || '?';
}

function previewFor(message) {
  return String(message || '').replace(/\s+/g, ' ').trim();
}

async function load({ silent = false } = {}) {
  if (silent) refreshing.value = true;
  else loading.value = true;
  listError.value = '';
  try {
    const { data } = await api.get('/admin/contact-messages', {
      params: {
        page: page.value,
        per_page: meta.value.per_page,
        search: search.value.trim() || undefined,
      },
    });
    messages.value = data.data || [];
    meta.value = data.meta || meta.value;
    navigationCounts.setContactUnread(meta.value.unread_count);

    if (selected.value) {
      const current = messages.value.find((message) => message.id === selected.value.id);
      if (current) selected.value = current;
    }
  } catch (error) {
    listError.value = error.response?.data?.message || 'Unable to load contact messages.';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

async function openMessage(message) {
  selected.value = message;
  if (message.is_read) return;

  try {
    const { data } = await api.patch(`/admin/contact-messages/${message.id}/read`);
    const updated = data.data;
    messages.value = messages.value.map((item) => (item.id === updated.id ? updated : item));
    selected.value = updated;
    meta.value.unread_count = data.unread_count;
    navigationCounts.setContactUnread(data.unread_count);
  } catch (error) {
    listError.value = error.response?.data?.message || 'Unable to mark the message as read.';
  }
}

function closeMessage() {
  selected.value = null;
}

async function markAllRead() {
  if (!meta.value.unread_count || markingAll.value) return;
  markingAll.value = true;
  listError.value = '';
  try {
    const { data } = await api.patch('/admin/contact-messages/read-all');
    const readAt = new Date().toISOString();
    messages.value = messages.value.map((message) => ({
      ...message,
      is_read: true,
      read_at: message.read_at || readAt,
    }));
    if (selected.value) {
      selected.value = messages.value.find((message) => message.id === selected.value.id) || selected.value;
    }
    meta.value.unread_count = data.unread_count;
    navigationCounts.setContactUnread(data.unread_count);
  } catch (error) {
    listError.value = error.response?.data?.message || 'Unable to mark all messages as read.';
  } finally {
    markingAll.value = false;
  }
}

function requestRemove(id) {
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value || deleting.value) return;
  const id = pendingDeleteId.value;
  deleting.value = true;
  try {
    const { data } = await api.delete(`/admin/contact-messages/${id}`);
    if (selected.value?.id === id) {
      selected.value = null;
    }
    navigationCounts.setContactUnread(data.unread_count);
    pendingDeleteId.value = null;
    confirmOpen.value = false;
    if (messages.value.length === 1 && page.value > 1) page.value -= 1;
    await load({ silent: true });
  } catch (error) {
    listError.value = error.response?.data?.message || 'Unable to delete the message.';
  } finally {
    deleting.value = false;
  }
}

function goToPage(nextPage) {
  if (nextPage < 1 || nextPage > meta.value.last_page || nextPage === page.value) return;
  page.value = nextPage;
  load();
}

function onKeydown(event) {
  if (event.key === 'Escape' && selected.value && !confirmOpen.value) {
    closeMessage();
  }
}

watch(search, () => {
  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => {
    page.value = 1;
    load();
  }, 350);
});

onMounted(() => {
  load();
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  window.clearTimeout(searchTimer);
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <section class="contact-inbox">
    <header class="contact-inbox__header">
      <div>
        <p class="contact-inbox__eyebrow">Customer care</p>
        <div class="contact-inbox__title-row">
          <h2>Contact messages</h2>
          <span v-if="meta.unread_count" class="contact-inbox__unread">
            {{ meta.unread_count > 99 ? '99+' : meta.unread_count }} new
          </span>
        </div>
        <p class="admin-muted">Read and respond to customer enquiries from one place.</p>
      </div>
      <div class="contact-inbox__header-actions">
        <AppButton
          type="button"
          variant="secondary"
          size="sm"
          :disabled="!meta.unread_count"
          :loading="markingAll"
          @click="markAllRead"
        >
          Mark all read
        </AppButton>
        <AppButton
          type="button"
          variant="secondary"
          size="sm"
          :loading="refreshing"
          aria-label="Refresh contact messages"
          @click="load({ silent: true })"
        >
          <RefreshCw :size="16" :class="{ 'is-spinning': refreshing }" aria-hidden="true" />
          Refresh
        </AppButton>
      </div>
    </header>

    <div class="contact-inbox__search">
      <AdminSearchField
        v-model="search"
        placeholder="Search name, email, or message…"
        aria-label="Search contact messages"
      />
      <span class="admin-muted">{{ resultsLabel }}</span>
    </div>

    <p v-if="listError" class="form-error contact-inbox__error" role="alert">{{ listError }}</p>

    <div class="contact-inbox__shell">
      <div class="contact-inbox__list-panel" :aria-busy="loading">
        <LoadingSpinner v-if="loading" page label="Loading messages" />

        <div v-else-if="messages.length" class="contact-inbox__list" role="list">
          <article
            v-for="message in messages"
            :key="message.id"
            class="contact-message-card"
            :class="{
              'contact-message-card--unread': !message.is_read,
              'contact-message-card--selected': selected?.id === message.id,
            }"
            role="listitem"
          >
            <button type="button" class="contact-message-card__open" @click="openMessage(message)">
              <span class="contact-message-card__avatar" aria-hidden="true">
                {{ initialFor(message.name) }}
              </span>
              <span class="contact-message-card__content">
                <span class="contact-message-card__topline">
                  <strong>{{ message.name }}</strong>
                  <time :datetime="message.created_at">{{ formatDate(message.created_at) }}</time>
                </span>
                <span class="contact-message-card__email">{{ message.email }}</span>
                <span class="contact-message-card__preview">{{ previewFor(message.message) }}</span>
              </span>
              <span v-if="!message.is_read" class="contact-message-card__dot" aria-label="Unread" />
            </button>
            <button
              type="button"
              class="contact-message-card__delete"
              :aria-label="`Delete message from ${message.name}`"
              @click="requestRemove(message.id)"
            >
              <Trash2 :size="17" aria-hidden="true" />
            </button>
          </article>
        </div>

        <div v-else class="contact-inbox__empty">
          <Inbox :size="38" aria-hidden="true" />
          <h3>{{ search ? 'No matching messages' : 'Your inbox is clear' }}</h3>
          <p>{{ search ? 'Try a different name, email, or keyword.' : 'New customer messages will appear here.' }}</p>
        </div>

        <footer v-if="!loading && meta.last_page > 1" class="contact-inbox__pagination">
          <AppButton
            type="button"
            variant="secondary"
            size="sm"
            :disabled="meta.current_page <= 1"
            aria-label="Previous page"
            @click="goToPage(meta.current_page - 1)"
          >
            <ChevronLeft :size="17" aria-hidden="true" />
            Previous
          </AppButton>
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <AppButton
            type="button"
            variant="secondary"
            size="sm"
            :disabled="meta.current_page >= meta.last_page"
            aria-label="Next page"
            @click="goToPage(meta.current_page + 1)"
          >
            Next
            <ChevronRight :size="17" aria-hidden="true" />
          </AppButton>
        </footer>
      </div>

      <button
        v-if="selected"
        type="button"
        class="contact-inbox__drawer-backdrop"
        aria-label="Close message"
        @click="closeMessage"
      />
      <aside
        class="contact-inbox__detail"
        :class="{ 'contact-inbox__detail--open': selected }"
        :aria-hidden="selected ? 'false' : 'true'"
      >
        <template v-if="selected">
          <header class="contact-inbox__detail-header">
            <div class="contact-message-card__avatar" aria-hidden="true">
              {{ initialFor(selected.name) }}
            </div>
            <div>
              <h3>{{ selected.name }}</h3>
              <time :datetime="selected.created_at">{{ formatDate(selected.created_at) }}</time>
            </div>
            <button type="button" class="contact-inbox__close" aria-label="Close message" @click="closeMessage">
              <X :size="20" aria-hidden="true" />
            </button>
          </header>

          <div class="contact-inbox__detail-actions">
            <a class="button button--primary button--sm" :href="emailHref(selected.email)">
              <Mail :size="16" aria-hidden="true" />
              Reply by email
            </a>
            <AppButton type="button" variant="danger" size="sm" @click="requestRemove(selected.id)">
              <Trash2 :size="16" aria-hidden="true" />
              Delete
            </AppButton>
          </div>

          <dl class="contact-inbox__sender">
            <div>
              <dt>Email</dt>
              <dd><a :href="emailHref(selected.email)">{{ selected.email }}</a></dd>
            </div>
            <div>
              <dt>Received</dt>
              <dd>{{ formatDate(selected.created_at) }}</dd>
            </div>
          </dl>
          <p class="contact-inbox__message">{{ selected.message }}</p>
        </template>

        <div v-else class="contact-inbox__detail-empty">
          <Mail :size="36" aria-hidden="true" />
          <h3>Select a message</h3>
          <p>Choose an enquiry to read the complete message.</p>
        </div>
      </aside>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete message?"
      message="This contact message will be permanently removed."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />
  </section>
</template>
