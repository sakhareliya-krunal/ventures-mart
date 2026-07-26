import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useThemeStore = defineStore('theme', () => {
  const brandName = ref('Ventures Mart');

  return {
    brandName,
  };
});
