import { ref, computed } from 'vue'
import { defineStore } from 'pinia'

// APIレスポンスの型定義
interface TextSegment {
  id: number;
  start: number;
  end: number;
  text: string;
}

interface TextData {
  success: boolean;
  text: string;
  segments: TextSegment[];
}

export const useCounterStore = defineStore('counter', () => {
  const count = ref(0)
  const doubleCount = computed(() => count.value * 2)

  const Whisper_response = ref<TextData | null>(null)

  function increment() {
    count.value++
  }

  return { count, doubleCount, increment, Whisper_response }
})
