<script setup>
import { ref, watch } from 'vue';
import { Search } from '@lucide/vue';
import { useRouter } from 'vue-router';
import { useSearchStore } from '@/stores/search';

const props = defineProps({
  className: {
    type: String,
    default: 'header-search',
  },
});

const emit = defineEmits(['submitted']);

const router = useRouter();
const search = useSearchStore();
const term = ref(search.query);

watch(
  () => search.query,
  (value) => {
    term.value = value;
  },
);

function submitSearch() {
  const query = term.value.trim();
  search.setQuery(query);
  router.push(query ? { path: '/search', query: { q: query } } : '/search');
  emit('submitted');
}
</script>

<template>
  <form :class="props.className" @submit.prevent="submitSearch">
    <Search :size="18" />
    <input v-model="term" placeholder="Search lunch boxes..." />
  </form>
</template>
