<script setup>
import AdminDataList from '@/components/admin/AdminDataList.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import { useAdminList } from '@/composables/useAdminList';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import { apiErrorMessage } from '@/utils/adminPostForm';
import { isNetworkOrTimeoutError } from '@/utils/apiError';

const route = useRoute();
const router = useRouter();
const { rows: posts, loading, refreshing, error: fetchError, search, filters, meta, filtered, load, go, reset } = useAdminList('/admin/posts', {});
const listError = ref('');
const successMessage = ref('');
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const deleting = ref(false);

let successTimer = null;

function flashSuccess(message) {
  successMessage.value = message;
  if (successTimer) clearTimeout(successTimer);
  successTimer = setTimeout(() => {
    successMessage.value = '';
  }, 3500);
}

function consumeNotice() {
  const notice = route.query.notice;
  if (!notice) return;

  if (notice === 'published') flashSuccess('Post published.');
  else if (notice === 'draft') {
    flashSuccess('Post saved as draft (hidden on the public blog until you set Published at).');
  } else if (notice === 'saved') flashSuccess('Post saved.');

  const query = { ...route.query };
  delete query.notice;
  router.replace({ query });
}


function openCreate() {
  router.push({ name: 'admin-post-create' });
}

function openEdit(post) {
  router.push({ name: 'admin-post-edit', params: { id: post.id } });
}

async function requestRemove(id) {
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value || deleting.value) return;
  listError.value = '';
  deleting.value = true;
  try {
    await api.delete(`/admin/posts/${pendingDeleteId.value}`);
    pendingDeleteId.value = null;
    confirmOpen.value = false;
    flashSuccess('Post deleted.');
    await load({ silent: true });
  } catch (err) {
    listError.value = apiErrorMessage(err, 'Unable to delete post.');
    pendingDeleteId.value = null;
  } finally {
    deleting.value = false;
  }
}

onMounted(() => {
  consumeNotice();
});


onBeforeUnmount(() => {
  if (successTimer) clearTimeout(successTimer);
});
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
          <AppButton type="button" @click="openCreate">Add post</AppButton>
        </div>
      </div>
      <p v-if="successMessage" class="form-success">{{ successMessage }}</p>
      <p v-if="listError" class="form-error">{{ listError }}</p>
      <AdminDataList :rows="posts" :loading="loading" :refreshing="refreshing" :error="fetchError" :searching="filtered" @retry="load" @reset="reset"><div class="admin-table-wrap">
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
              <td data-label="Title">
                <strong>{{ post.title }}</strong>
                <div class="admin-muted">{{ post.slug }}</div>
              </td>
              <td data-label="Published">
                {{
                  post.published_at
                    ? new Date(post.published_at).toLocaleString()
                    : 'Draft'
                }}
              </td>
              <td data-label="Actions">
                <div class="admin-actions">
                  <AppButton type="button" variant="secondary" size="sm" @click="openEdit(post)">
                    Edit
                  </AppButton>
                  <AppButton type="button" variant="danger" size="sm" @click="requestRemove(post.id)">
                    Delete
                  </AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!posts.length" class="admin-empty">No posts found.</p>
      </div></AdminDataList>
      <AdminPagination :page="meta.current_page" :last-page="meta.last_page" :total="meta.total" :from="meta.from || 0" :to="meta.to || 0" @page="go" />
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete post?"
      message="This blog post will be permanently removed."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />
  </div>
</template>
