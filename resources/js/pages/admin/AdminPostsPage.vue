<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const posts = ref([]);
const search = ref('');
const editingId = ref(null);
const showForm = ref(false);
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);

const form = reactive({
  title: '',
  slug: '',
  excerpt: '',
  body: '',
  cover_image: '',
  published_at: '',
});

function resetForm() {
  editingId.value = null;
  showForm.value = false;
  Object.assign(form, {
    title: '',
    slug: '',
    excerpt: '',
    body: '',
    cover_image: '',
    published_at: '',
  });
}

async function edit(post) {
  const { data } = await api.get(`/admin/posts/${post.id}`);
  const full = unwrapData(data);
  editingId.value = full.id;
  showForm.value = true;
  Object.assign(form, {
    title: full.title || '',
    slug: full.slug || '',
    excerpt: full.excerpt || '',
    body: full.body || '',
    cover_image: full.cover_image || '',
    published_at: full.published_at ? full.published_at.slice(0, 16) : '',
  });
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/admin/posts', {
      params: { search: search.value || undefined },
    });
    posts.value = unwrapData(data) || [];
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  error.value = '';
  const payload = {
    ...form,
    published_at: form.published_at || null,
  };
  try {
    if (editingId.value) {
      await api.put(`/admin/posts/${editingId.value}`, payload);
    } else {
      await api.post('/admin/posts', payload);
    }
    resetForm();
    await load();
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to save post.';
  } finally {
    saving.value = false;
  }
}

async function requestRemove(id) {
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value) return;
  await api.delete(`/admin/posts/${pendingDeleteId.value}`);
  pendingDeleteId.value = null;
  await load();
}

onMounted(load);
watch(search, load);
</script>

<template>
  <div>
    <div class="admin-panel">
      <div class="admin-toolbar">
        <h2>Blog posts</h2>
        <div class="admin-toolbar__filters">
          <AdminSearchField
            v-model="search"
            placeholder="Search posts…"
            aria-label="Search posts"
          />
          <AppButton type="button" @click="showForm = true; editingId = null">Add post</AppButton>
        </div>
      </div>
      <div v-if="loading" class="admin-muted">Loading…</div>
      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Published</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="post in posts" :key="post.id">
              <td>
                <strong>{{ post.title }}</strong>
                <div class="admin-muted">{{ post.slug }}</div>
              </td>
              <td>
                {{
                  post.published_at
                    ? new Date(post.published_at).toLocaleString()
                    : 'Draft'
                }}
              </td>
              <td>
                <div class="admin-actions">
                  <AppButton type="button" variant="secondary" size="sm" @click="edit(post)">
                    Edit
                  </AppButton>
                  <AppButton type="button" variant="ghost" size="sm" @click="requestRemove(post.id)">
                    Delete
                  </AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showForm" class="admin-panel">
      <h3>{{ editingId ? 'Edit post' : 'New post' }}</h3>
      <p v-if="error" class="form-error">{{ error }}</p>
      <form class="admin-form" @submit.prevent="save">
        <div class="admin-form__grid">
          <label>Title <input v-model="form.title" required /></label>
          <label>Slug <input v-model="form.slug" /></label>
          <label>Cover image <input v-model="form.cover_image" /></label>
          <label>Published at <input v-model="form.published_at" type="datetime-local" /></label>
        </div>
        <label>Excerpt <textarea v-model="form.excerpt" rows="2" required /></label>
        <label>Body <textarea v-model="form.body" rows="8" required /></label>
        <div class="admin-actions">
          <AppButton type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</AppButton>
          <AppButton type="button" variant="ghost" @click="resetForm">Cancel</AppButton>
        </div>
      </form>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete post?"
      message="This blog post will be permanently removed."
      confirm-label="Delete"
      danger
      @confirm="remove"
    />
  </div>
</template>
