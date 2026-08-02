<script setup>
import { computed, ref } from 'vue';
import { ChevronLeft, ChevronRight, ImagePlus, Trash2 } from '@lucide/vue';
import AdminRichTextEditor from '@/components/admin/AdminRichTextEditor.vue';
import AdminSeoTab from '@/components/admin/AdminSeoTab.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import api from '@/services/api';

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  categoryOptions: {
    type: Array,
    default: () => [],
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
  editing: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['submit', 'cancel']);

const fileInputRef = ref(null);
const uploading = ref(false);
const uploadError = ref('');
const pathDraft = ref('');
const dragOver = ref(false);

const images = computed(() => (Array.isArray(props.form.images) ? props.form.images : []));
const busy = computed(() => props.saving || uploading.value);
const submitLabel = computed(() =>
  props.saving ? 'Saving…' : props.editing ? 'Save changes' : 'Create product',
);

function fieldError(name) {
  const value = props.fieldErrors?.[name];
  return Array.isArray(value) ? value[0] : value || '';
}

function setImages(next) {
  props.form.images = next;
}

async function uploadFiles(fileList) {
  const files = Array.from(fileList || []).filter(Boolean);
  if (!files.length) return;

  uploadError.value = '';
  uploading.value = true;

  const body = new FormData();
  files.forEach((file) => body.append('images[]', file));

  try {
    const { data } = await api.post('/admin/uploads/images', body);
    const urls = data.urls || [];
    setImages([...images.value, ...urls]);
  } catch (err) {
    uploadError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to upload images.';
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

function removeImage(index) {
  setImages(images.value.filter((_, i) => i !== index));
}

function moveImage(index, delta) {
  const nextIndex = index + delta;
  if (nextIndex < 0 || nextIndex >= images.value.length) return;
  const next = [...images.value];
  const [item] = next.splice(index, 1);
  next.splice(nextIndex, 0, item);
  setImages(next);
}

function addPath() {
  const value = pathDraft.value.trim();
  if (!value) return;
  if (!images.value.includes(value)) {
    setImages([...images.value, value]);
  }
  pathDraft.value = '';
}
</script>

<template>
  <div class="admin-panel">
    <form class="admin-product-form" novalidate @submit.prevent="emit('submit')">
      <p v-if="error" class="form-error">{{ error }}</p>

      <section class="admin-product-form__section">
        <h3>Basics</h3>
        <div class="admin-product-form__grid">
          <label class="admin-field">
            <span>Name <em>*</em></span>
            <input v-model="form.name" type="text" autocomplete="off" />
            <small v-if="fieldError('name')" class="admin-field__error">{{ fieldError('name') }}</small>
          </label>
          <label class="admin-field">
            <span>Slug</span>
            <input v-model="form.slug" type="text" placeholder="Auto-generated if empty" autocomplete="off" />
            <small v-if="fieldError('slug')" class="admin-field__error">{{ fieldError('slug') }}</small>
          </label>
          <label class="admin-field">
            <span>SKU <em>*</em></span>
            <input v-model="form.sku" type="text" autocomplete="off" />
            <small v-if="fieldError('sku')" class="admin-field__error">{{ fieldError('sku') }}</small>
          </label>
          <label class="admin-field">
            <span>Category <em>*</em></span>
            <AppSelect
              v-model="form.category_id"
              :options="categoryOptions"
              placeholder="Select category"
              aria-label="Product category"
            />
            <small v-if="fieldError('category_id')" class="admin-field__error">
              {{ fieldError('category_id') }}
            </small>
          </label>
        </div>
      </section>

      <section class="admin-product-form__section">
        <h3>Pricing & stock</h3>
        <div class="admin-product-form__grid admin-product-form__grid--3">
          <label class="admin-field">
            <span>Price <em>*</em></span>
            <input v-model.number="form.price" type="number" min="0" step="0.01" />
            <small v-if="fieldError('price')" class="admin-field__error">{{ fieldError('price') }}</small>
          </label>
          <label class="admin-field">
            <span>Compare at</span>
            <input v-model="form.compare_at_price" type="number" min="0" step="0.01" />
            <small v-if="fieldError('compare_at_price')" class="admin-field__error">
              {{ fieldError('compare_at_price') }}
            </small>
          </label>
          <label class="admin-field">
            <span>Stock <em>*</em></span>
            <input v-model.number="form.stock" type="number" min="0" />
            <small v-if="fieldError('stock')" class="admin-field__error">{{ fieldError('stock') }}</small>
          </label>
        </div>
      </section>

      <section class="admin-product-form__section">
        <h3>Media <em class="admin-product-form__required">*</em></h3>
        <p class="admin-muted admin-product-form__hint">
          Upload multiple images. The first image is the product main image.
        </p>

        <div
          class="admin-image-dropzone"
          :class="{ 'is-dragover': dragOver, 'is-busy': uploading }"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="onDrop"
        >
          <ImagePlus :size="22" aria-hidden="true" />
          <div>
            <strong>{{ uploading ? 'Uploading…' : 'Add images' }}</strong>
            <p>Drop files here or browse. JPEG, PNG, or WebP up to 4MB.</p>
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
          <input
            ref="fileInputRef"
            class="admin-image-dropzone__input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            @change="onFileChange"
          />
        </div>

        <p v-if="uploadError || fieldError('image') || fieldError('gallery')" class="admin-field__error">
          {{ uploadError || fieldError('image') || fieldError('gallery') }}
        </p>

        <div v-if="images.length" class="admin-image-grid">
          <div v-for="(src, index) in images" :key="`${src}-${index}`" class="admin-image-card">
            <img :src="src" :alt="`Product image ${index + 1}`" />
            <span v-if="index === 0" class="admin-image-card__badge">Main</span>
            <div class="admin-image-card__actions">
              <button
                type="button"
                aria-label="Move left"
                :disabled="index === 0 || busy"
                @click="moveImage(index, -1)"
              >
                <ChevronLeft :size="16" />
              </button>
              <button
                type="button"
                aria-label="Move right"
                :disabled="index === images.length - 1 || busy"
                @click="moveImage(index, 1)"
              >
                <ChevronRight :size="16" />
              </button>
              <button
                type="button"
                aria-label="Remove image"
                :disabled="busy"
                @click="removeImage(index)"
              >
                <Trash2 :size="16" />
              </button>
            </div>
          </div>
        </div>

        <div class="admin-image-path">
          <label class="admin-field admin-field--grow">
            <span>Or add existing path / URL</span>
            <input
              v-model="pathDraft"
              type="text"
              placeholder="/products/…"
              autocomplete="off"
              @keydown.enter.prevent="addPath"
            />
          </label>
          <AppButton type="button" variant="ghost" :disabled="busy || !pathDraft.trim()" @click="addPath">
            Add
          </AppButton>
        </div>
      </section>

      <section class="admin-product-form__section">
        <h3>Merchandising</h3>
        <div class="admin-product-form__grid">
          <label class="admin-field">
            <span>Badge</span>
            <input v-model="form.badge" type="text" placeholder="e.g. New, Sale" autocomplete="off" />
            <small v-if="fieldError('badge')" class="admin-field__error">{{ fieldError('badge') }}</small>
          </label>
          <div class="admin-field admin-field--full">
            <label class="checkbox-row">
              <input v-model="form.is_active" type="checkbox" />
              Active
            </label>
            <p class="admin-muted admin-product-form__hint">
              Uncheck to hide this product from the shop. It stays available in admin.
            </p>
          </div>
          <div class="admin-field admin-field--full">
            <span>Description</span>
            <AdminRichTextEditor
              v-model="form.description"
              :disabled="busy"
              placeholder="Describe the product…"
              :min-height="180"
              aria-label="Product description"
            />
            <small v-if="fieldError('description')" class="admin-field__error">
              {{ fieldError('description') }}
            </small>
          </div>
        </div>
      </section>

      <AdminSeoTab
        :form="form"
        :field-errors="fieldErrors"
        :fallback-title="form.name ? `${form.name} | ${form.seo?.focus_keyword || 'Online'} | Ventures Mart` : 'Product | Ventures Mart'"
        :fallback-description="form.description"
        :fallback-url="form.slug ? `/product/${form.slug}` : '/product'"
      />

      <div class="admin-actions">
        <AppButton type="button" variant="ghost" :disabled="busy" @click="emit('cancel')">
          Cancel
        </AppButton>
        <AppButton type="submit" :disabled="busy">
          {{ submitLabel }}
        </AppButton>
      </div>
    </form>
  </div>
</template>
