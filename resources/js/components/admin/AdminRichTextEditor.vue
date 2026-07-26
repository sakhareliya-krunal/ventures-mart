<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Underline from '@tiptap/extension-underline';
import {
  Bold,
  Heading2,
  Heading3,
  Italic,
  Link2,
  Link2Off,
  List,
  ListOrdered,
  Quote,
  Redo2,
  RemoveFormatting,
  Underline as UnderlineIcon,
  Undo2,
} from '@lucide/vue';
import { normalizeEditorHtml } from '@/utils/html';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Start writing…',
  },
  minHeight: {
    type: [String, Number],
    default: 200,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  ariaLabel: {
    type: String,
    default: 'Rich text editor',
  },
});

const emit = defineEmits(['update:modelValue']);

const contentMinHeight = computed(() =>
  typeof props.minHeight === 'number' ? `${props.minHeight}px` : props.minHeight,
);

const editorTick = ref(0);

const editor = useEditor({
  content: props.modelValue || '',
  editable: !props.disabled,
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3] },
      code: false,
      codeBlock: false,
      horizontalRule: false,
    }),
    Underline,
    Link.configure({
      openOnClick: false,
      HTMLAttributes: {
        rel: 'noopener noreferrer',
        target: '_blank',
      },
    }),
    Placeholder.configure({
      placeholder: props.placeholder,
    }),
  ],
  editorProps: {
    attributes: {
      class: 'admin-rte__content',
      'aria-label': props.ariaLabel,
    },
  },
  onUpdate: ({ editor: instance }) => {
    emit('update:modelValue', normalizeEditorHtml(instance.getHTML()));
  },
  onSelectionUpdate: () => {
    editorTick.value += 1;
  },
  onTransaction: () => {
    editorTick.value += 1;
  },
});

watch(
  () => props.modelValue,
  (value) => {
    if (!editor.value) return;
    const next = normalizeEditorHtml(value);
    const current = normalizeEditorHtml(editor.value.getHTML());
    if (next === current) return;
    editor.value.commands.setContent(next || '', { emitUpdate: false });
  },
);

watch(
  () => props.disabled,
  (disabled) => {
    editor.value?.setEditable(!disabled);
  },
);

onBeforeUnmount(() => {
  editor.value?.destroy();
});

function run(command) {
  if (!editor.value || props.disabled) return;
  command(editor.value.chain().focus()).run();
}

function setLink() {
  if (!editor.value || props.disabled) return;

  const previous = editor.value.getAttributes('link').href || '';
  const url = window.prompt('Enter link URL', previous);
  if (url === null) return;

  const trimmed = url.trim();
  if (!trimmed) {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    return;
  }

  const href =
    /^https?:\/\//i.test(trimmed) || trimmed.startsWith('/') ? trimmed : `https://${trimmed}`;
  editor.value.chain().focus().extendMarkRange('link').setLink({ href }).run();
}

function unsetLink() {
  run((chain) => chain.unsetLink());
}

function clearFormat() {
  run((chain) => chain.clearNodes().unsetAllMarks());
}

const toolbar = computed(() => {
  editorTick.value;
  const instance = editor.value;
  return {
    focused: Boolean(instance?.isFocused),
    bold: Boolean(instance?.isActive('bold')),
    italic: Boolean(instance?.isActive('italic')),
    underline: Boolean(instance?.isActive('underline')),
    h2: Boolean(instance?.isActive('heading', { level: 2 })),
    h3: Boolean(instance?.isActive('heading', { level: 3 })),
    bullet: Boolean(instance?.isActive('bulletList')),
    ordered: Boolean(instance?.isActive('orderedList')),
    quote: Boolean(instance?.isActive('blockquote')),
    link: Boolean(instance?.isActive('link')),
    canUndo: Boolean(instance?.can().undo()),
    canRedo: Boolean(instance?.can().redo()),
  };
});
</script>

<template>
  <div
    class="admin-rte"
    :class="{ 'is-disabled': disabled, 'is-focused': toolbar.focused }"
    :style="{ '--admin-rte-min-height': contentMinHeight }"
  >
    <div class="admin-rte__toolbar" role="toolbar" :aria-label="`${ariaLabel} toolbar`">
      <div class="admin-rte__group">
        <button
          type="button"
          class="admin-rte__btn"
          title="Undo"
          aria-label="Undo"
          :disabled="disabled || !toolbar.canUndo"
          @click="run((chain) => chain.undo())"
        >
          <Undo2 :size="16" />
        </button>
        <button
          type="button"
          class="admin-rte__btn"
          title="Redo"
          aria-label="Redo"
          :disabled="disabled || !toolbar.canRedo"
          @click="run((chain) => chain.redo())"
        >
          <Redo2 :size="16" />
        </button>
      </div>

      <div class="admin-rte__group">
        <button
          type="button"
          class="admin-rte__btn"
          title="Bold"
          aria-label="Bold"
          :class="{ 'is-active': toolbar.bold }"
          :disabled="disabled"
          @click="run((chain) => chain.toggleBold())"
        >
          <Bold :size="16" />
        </button>
        <button
          type="button"
          class="admin-rte__btn"
          title="Italic"
          aria-label="Italic"
          :class="{ 'is-active': toolbar.italic }"
          :disabled="disabled"
          @click="run((chain) => chain.toggleItalic())"
        >
          <Italic :size="16" />
        </button>
        <button
          type="button"
          class="admin-rte__btn"
          title="Underline"
          aria-label="Underline"
          :class="{ 'is-active': toolbar.underline }"
          :disabled="disabled"
          @click="run((chain) => chain.toggleUnderline())"
        >
          <UnderlineIcon :size="16" />
        </button>
      </div>

      <div class="admin-rte__group">
        <button
          type="button"
          class="admin-rte__btn"
          title="Heading 2"
          aria-label="Heading 2"
          :class="{ 'is-active': toolbar.h2 }"
          :disabled="disabled"
          @click="run((chain) => chain.toggleHeading({ level: 2 }))"
        >
          <Heading2 :size="16" />
        </button>
        <button
          type="button"
          class="admin-rte__btn"
          title="Heading 3"
          aria-label="Heading 3"
          :class="{ 'is-active': toolbar.h3 }"
          :disabled="disabled"
          @click="run((chain) => chain.toggleHeading({ level: 3 }))"
        >
          <Heading3 :size="16" />
        </button>
      </div>

      <div class="admin-rte__group">
        <button
          type="button"
          class="admin-rte__btn"
          title="Bullet list"
          aria-label="Bullet list"
          :class="{ 'is-active': toolbar.bullet }"
          :disabled="disabled"
          @click="run((chain) => chain.toggleBulletList())"
        >
          <List :size="16" />
        </button>
        <button
          type="button"
          class="admin-rte__btn"
          title="Ordered list"
          aria-label="Ordered list"
          :class="{ 'is-active': toolbar.ordered }"
          :disabled="disabled"
          @click="run((chain) => chain.toggleOrderedList())"
        >
          <ListOrdered :size="16" />
        </button>
        <button
          type="button"
          class="admin-rte__btn"
          title="Quote"
          aria-label="Quote"
          :class="{ 'is-active': toolbar.quote }"
          :disabled="disabled"
          @click="run((chain) => chain.toggleBlockquote())"
        >
          <Quote :size="16" />
        </button>
      </div>

      <div class="admin-rte__group">
        <button
          type="button"
          class="admin-rte__btn"
          title="Link"
          aria-label="Link"
          :class="{ 'is-active': toolbar.link }"
          :disabled="disabled"
          @click="setLink"
        >
          <Link2 :size="16" />
        </button>
        <button
          type="button"
          class="admin-rte__btn"
          title="Remove link"
          aria-label="Remove link"
          :disabled="disabled || !toolbar.link"
          @click="unsetLink"
        >
          <Link2Off :size="16" />
        </button>
        <button
          type="button"
          class="admin-rte__btn"
          title="Clear formatting"
          aria-label="Clear formatting"
          :disabled="disabled"
          @click="clearFormat"
        >
          <RemoveFormatting :size="16" />
        </button>
      </div>
    </div>

    <EditorContent :editor="editor" class="admin-rte__surface" />
  </div>
</template>
