import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useThemeStore = defineStore('theme', () => {
  const brandName = ref('Venture Smart');

  return {
    brandName,
  };
});
