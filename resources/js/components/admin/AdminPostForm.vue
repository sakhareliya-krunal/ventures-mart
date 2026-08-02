<script setup>
import { computed, ref } from 'vue';
import { ImagePlus, Trash2 } from '@lucide/vue';
import AdminRichTextEditor from '@/components/admin/AdminRichTextEditor.vue';
import AdminSeoTab from '@/components/admin/AdminSeoTab.vue';
import AppButton from '@/components/ui/AppButton.vue';
import api from '@/services/api';

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  fieldErrors: {
    type: Object,
    default: () => ({}),
  },
  error: {
    type: String,
    default: '',
  },
  saving: {
    type: Boolean,
    default: false,
  },
  submitLabel: {
    type: String,
    default: 'Save',
  },
});

defineEmits(['submit', 'cancel']);

const fileInputRef = ref(null);
const uploading = ref(false);
const uploadError = ref('');
const pathDraft = ref('');
const dragOver = ref(false);

const coverImage = computed(() => String(props.form.cover_image || '').trim());
const busy = computed(() => props.saving || uploading.value);

function fieldError(name) {
  const messages = props.fieldErrors?.[name];
  return Array.isArray(messages) ? messages[0] : '';
}

function setCover(url) {
  props.form.cover_image = url || '';
}

async function uploadFiles(fileList) {
  const file = Array.from(fileList || []).find(Boolean);
  if (!file) return;

  uploadError.value = '';
  uploading.value = true;

  const body = new FormData();
  body.append('images[]', file);

  try {
    const { data } = await api.post('/admin/uploads/images', body);
    const url = data.urls?.[0];
    if (url) setCover(url);
    else uploadError.value = 'Unable to upload image.';
  } catch (err) {
    uploadError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to upload image.';
  } finally {
    uploading.value = false;
    if (fileInputRef.value) {
      fileInputRef.value.value = '';
    }
  }
}

function onFileChange(event) {
  uploadFiles(event.target.files);
}

function onDrop(event) {
  dragOver.value = false;
  uploadFiles(event.dataTransfer?.files);
}

function removeCover() {
  setCover('');
}

function addPath() {
  const value = pathDraft.value.trim();
  if (!value) return;
  setCover(value);
  pathDraft.value = '';
}
</script>

<template>
  <div class="admin-panel">
    <p v-if="error" class="form-error">{{ error }}</p>
    <form class="admin-form" novalidate @submit.prevent="$emit('submit')">
      <div class="admin-form__grid">
        <label>
          Title
          <input v-model="form.title" autocomplete="off" />
          <small v-if="fieldError('title')" class="form-error">{{ fieldError('title') }}</small>
        </label>
        <label>
          Slug
          <input v-model="form.slug" placeholder="Auto-generated if empty" autocomplete="off" />
          <small v-if="fieldError('slug')" class="form-error">{{ fieldError('slug') }}</small>
        </label>
        <label>
          Published at
          <input v-model="form.published_at" type="datetime-local" />
          <small class="admin-muted">Leave empty to save as a draft (hidden on the public blog).</small>
          <small v-if="fieldError('published_at')" class="form-error">{{ fieldError('published_at') }}</small>
        </label>
      </div>

      <div class="admin-field admin-field--full">
        <span>Cover image</span>
        <p class="admin-muted admin-product-form__hint">Add one cover image for this post.</p>

        <div
          v-if="!coverImage"
          class="admin-image-dropzone"
          :class="{ 'is-dragover': dragOver, 'is-busy': uploading }"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="onDrop"
        >
          <ImagePlus :size="22" aria-hidden="true" />
          <div>
            <strong>{{ uploading ? 'Uploading…' : 'Add image' }}</strong>
            <p>Drop a file here or browse. JPEG, PNG, or WebP up to 4MB.</p>
          </div>
          <AppButton
            type="button"
            variant="secondary"
            size="sm"
            :disabled="busy"
            @click="fileInputRef?.click()"
          >
            Browse
          </AppButton>
        </div>

        <p v-if="uploadError || fieldError('cover_image')" class="admin-field__error">
          {{ uploadError || fieldError('cover_image') }}
        </p>

        <div v-if="coverImage" class="admin-image-grid">
          <div class="admin-image-card">
            <img :src="coverImage" alt="Cover image preview" />
            <div class="admin-image-card__actions">
              <button
                type="button"
                aria-label="Replace cover image"
                :disabled="busy"
                @click="fileInputRef?.click()"
              >
                <ImagePlus :size="16" />
              </button>
              <button
                type="button"
                aria-label="Remove cover image"
                :disabled="busy"
                @click="removeCover"
              >
                <Trash2 :size="16" />
              </button>
            </div>
          </div>
        </div>

        <input
          ref="fileInputRef"
          class="admin-image-dropzone__input"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          @change="onFileChange"
        />

        <div class="admin-image-path">
          <label class="admin-field admin-field--grow">
            <span>Or add existing path / URL</span>
            <input
              v-model="pathDraft"
              type="text"
              placeholder="/images/…"
              autocomplete="off"
              @keydown.enter.prevent="addPath"
            />
          </label>
          <AppButton type="button" variant="ghost" :disabled="busy || !pathDraft.trim()" @click="addPath">
            {{ coverImage ? 'Replace' : 'Add' }}
          </AppButton>
        </div>
      </div>

      <label>
        Excerpt
        <textarea v-model="form.excerpt" rows="2" maxlength="500" />
        <small v-if="fieldError('excerpt')" class="form-error">{{ fieldError('excerpt') }}</small>
      </label>
      <div class="admin-field admin-field--full">
        <span>Body</span>
        <AdminRichTextEditor
          v-model="form.body"
          :disabled="busy"
          placeholder="Write the full post…"
          :min-height="260"
          aria-label="Post body"
        />
        <small v-if="fieldError('body')" class="form-error">{{ fieldError('body') }}</small>
      </div>
      <AdminSeoTab
        :form="form"
        :field-errors="fieldErrors"
        :fallback-title="form.title ? `${form.title} | Ventures Mart` : 'Blog post | Ventures Mart'"
        :fallback-description="form.excerpt"
        :fallback-url="form.slug ? `/blog/${form.slug}` : '/blog'"
      />
      <div class="admin-actions">
        <AppButton type="button" :disabled="busy" @click="$emit('submit')">
          {{ saving ? 'Saving…' : submitLabel }}
        </AppButton>
        <AppButton type="button" variant="ghost" :disabled="busy" @click="$emit('cancel')">
          Cancel
        </AppButton>
      </div>
    </form>
  </div>
</template>
