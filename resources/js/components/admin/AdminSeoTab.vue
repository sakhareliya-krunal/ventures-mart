<script setup>
import { computed } from 'vue';
import { Plus, Trash2 } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { blankFaq } from '@/utils/adminSeo';

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  fieldErrors: {
    type: Object,
    default: () => ({}),
  },
  fallbackTitle: {
    type: String,
    default: '',
  },
  fallbackDescription: {
    type: String,
    default: '',
  },
  fallbackUrl: {
    type: String,
    default: '',
  },
});

const score = computed(() => Number(props.form.seo_score || 0));
const checks = computed(() => (Array.isArray(props.form.seo_checks) ? props.form.seo_checks : []));
const previewTitle = computed(() => props.form.seo.title || props.fallbackTitle || 'Ventures Mart');
const previewDescription = computed(() =>
  props.form.seo.meta_description || props.fallbackDescription || 'SEO description fallback will be generated automatically.',
);
const previewUrl = computed(() => props.form.seo.canonical_url || props.fallbackUrl || '/');

function fieldError(name) {
  const value = props.fieldErrors?.[name] || props.fieldErrors?.[`seo.${name}`];
  return Array.isArray(value) ? value[0] : value || '';
}

function addFaq() {
  props.form.faqs.push({ ...blankFaq(), sort_order: props.form.faqs.length });
}

function removeFaq(index) {
  props.form.faqs.splice(index, 1);
}
</script>

<template>
  <section class="admin-product-form__section">
    <div class="admin-toolbar">
      <div>
        <h3>SEO</h3>
        <p class="admin-muted">Leave fields empty to use generated defaults from page content.</p>
      </div>
      <strong class="admin-muted">Score {{ score }}/100</strong>
    </div>

    <div v-if="checks.length" class="admin-seo-checklist">
      <h4>SEO checklist</h4>
      <ul class="admin-seo-checklist__list">
        <li
          v-for="check in checks"
          :key="check.id"
          class="admin-seo-checklist__item"
          :data-pass="check.pass ? '1' : '0'"
        >
          <span class="admin-seo-checklist__status">{{ check.pass ? 'Pass' : 'Fix' }}</span>
          <div>
            <strong>{{ check.label }}</strong>
            <p class="admin-muted">{{ check.hint }}</p>
          </div>
        </li>
      </ul>
    </div>

    <div class="admin-product-form__grid">
      <label class="admin-field">
        <span>SEO title</span>
        <input v-model="form.seo.title" maxlength="255" autocomplete="off" />
        <small v-if="fieldError('title')" class="admin-field__error">{{ fieldError('title') }}</small>
      </label>
      <label class="admin-field">
        <span>Focus keyword</span>
        <input v-model="form.seo.focus_keyword" maxlength="120" autocomplete="off" />
      </label>
      <label class="admin-field admin-field--full">
        <span>Meta description</span>
        <textarea v-model="form.seo.meta_description" rows="3" maxlength="500" />
      </label>
      <label class="admin-field">
        <span>SEO slug</span>
        <input v-model="form.seo.seo_slug" autocomplete="off" />
      </label>
      <label class="admin-field">
        <span>Canonical URL</span>
        <input v-model="form.seo.canonical_url" placeholder="Auto-generated if empty" autocomplete="off" />
      </label>
      <label class="admin-field">
        <span>Meta robots</span>
        <input v-model="form.seo.meta_robots" placeholder="index,follow" autocomplete="off" />
      </label>
      <label class="admin-field">
        <span>Image alt text</span>
        <input v-model="form.seo.image_alt_text" autocomplete="off" />
      </label>
    </div>

    <div class="admin-product-form__grid">
      <label class="admin-field">
        <span>Open Graph title</span>
        <input v-model="form.seo.og_title" autocomplete="off" />
      </label>
      <label class="admin-field">
        <span>Open Graph image</span>
        <input v-model="form.seo.og_image" autocomplete="off" />
      </label>
      <label class="admin-field admin-field--full">
        <span>Open Graph description</span>
        <textarea v-model="form.seo.og_description" rows="2" />
      </label>
      <label class="admin-field">
        <span>Twitter title</span>
        <input v-model="form.seo.twitter_title" autocomplete="off" />
      </label>
      <label class="admin-field">
        <span>Twitter image</span>
        <input v-model="form.seo.twitter_image" autocomplete="off" />
      </label>
      <label class="admin-field admin-field--full">
        <span>Twitter description</span>
        <textarea v-model="form.seo.twitter_description" rows="2" />
      </label>
    </div>

    <div class="admin-product-form__grid">
      <label class="admin-field admin-field--full">
        <span>AI summary</span>
        <textarea v-model="form.seo.ai_summary" rows="3" />
      </label>
      <label class="admin-field admin-field--full">
        <span>AI highlights</span>
        <textarea v-model="form.seo.ai_highlights_text" rows="4" placeholder="One highlight per line" />
      </label>
      <label class="admin-field admin-field--full">
        <span>Custom structured data JSON</span>
        <textarea v-model="form.seo.custom_schema_text" rows="6" spellcheck="false" />
        <small v-if="fieldError('custom_schema')" class="admin-field__error">{{ fieldError('custom_schema') }}</small>
      </label>
    </div>

    <div class="admin-product-form__section">
      <div class="admin-toolbar">
        <h4>FAQs</h4>
        <AppButton type="button" variant="secondary" size="sm" @click="addFaq">
          <Plus :size="16" /> Add FAQ
        </AppButton>
      </div>
      <div v-for="(faq, index) in form.faqs" :key="index" class="admin-form__grid">
        <label>
          Question
          <input v-model="faq.question" />
        </label>
        <label>
          Answer
          <textarea v-model="faq.answer" rows="2" />
        </label>
        <label class="checkbox-row">
          <input v-model="faq.is_visible" type="checkbox" />
          Visible
        </label>
        <AppButton type="button" variant="danger" size="sm" @click="removeFaq(index)">
          <Trash2 :size="16" /> Remove
        </AppButton>
      </div>
    </div>

    <div class="admin-product-form__section">
      <h4>Google preview</h4>
      <p class="admin-muted">{{ previewUrl }}</p>
      <strong>{{ previewTitle }}</strong>
      <p>{{ previewDescription }}</p>
    </div>

    <div v-if="form.suggested_links?.length" class="admin-product-form__section">
      <h4>Internal link suggestions</h4>
      <p v-for="link in form.suggested_links" :key="link.url" class="admin-muted">
        {{ link.label }}: {{ link.url }}
      </p>
    </div>
  </section>
</template>
