<template>
  <div class="min-h-screen bg-gradient-to-br from-green-50 to-emerald-100 p-6">
    <div class="max-w-6xl mx-auto">
      <!-- ナビゲーション -->
      <nav class="mb-8">
        <div class="flex items-center justify-center space-x-6">
          <router-link
            to="/"
            class="flex items-center px-4 py-2 rounded-lg transition-colors"
            :class="$route.path === '/' ? 'bg-blue-500 text-white' : 'text-blue-600 hover:bg-blue-50'"
          >
            <MicrophoneIcon class="w-5 h-5 mr-2" />
            録音画面
          </router-link>
          <router-link
            to="/list"
            class="flex items-center px-4 py-2 rounded-lg transition-colors"
            :class="$route.path === '/list' ? 'bg-blue-500 text-white' : 'text-blue-600 hover:bg-blue-50'"
          >
            <ListBulletIcon class="w-5 h-5 mr-2" />
            録音一覧
          </router-link>
        </div>
      </nav>

      <!-- ヘッダー -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
          文字起こし結果・サマリ
        </h1>
        <p class="text-gray-600">{{ recordingData?.filename || '録音ファイル' }}</p>
      </div>

      <!-- 戻るボタン -->
      <div class="mb-6">
        <button
          @click="goBack"
          class="flex items-center px-4 py-2 text-blue-600 hover:text-blue-800 transition-colors"
        >
          <ArrowLeftIcon class="w-5 h-5 mr-2" />
          録音画面に戻る
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- 文字起こし結果 -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
            <DocumentTextIcon class="w-6 h-6 mr-2 text-blue-500" />
            文字起こし結果
          </h2>
          
          <div class="bg-gray-50 rounded-lg p-4 max-h-96 overflow-y-auto">
            <div v-if="isProcessing" class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-4"></div>
              <p class="text-gray-600">文字起こし処理中...</p>
            </div>
            
            <div v-else class="space-y-4">
              <p v-for="(paragraph, index) in transcriptionParagraphs" :key="index" class="text-gray-700 leading-relaxed">
                {{ paragraph }}
              </p>
            </div>
          </div>
        </div>

        <!-- キーポイント -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
            <LightBulbIcon class="w-6 h-6 mr-2 text-yellow-500" />
            キーポイント
          </h2>
          
          <div v-if="isProcessing" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-500 mx-auto mb-4"></div>
            <p class="text-gray-600">キーポイント抽出中...</p>
          </div>
          
          <div v-else class="space-y-3">
            <div
              v-for="(point, index) in keyPoints"
              :key="index"
              class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-lg"
            >
              <div class="flex items-start">
                <span class="bg-yellow-400 text-white text-xs font-bold px-2 py-1 rounded-full mr-3 mt-0.5">
                  {{ index + 1 }}
                </span>
                <p class="text-gray-700 flex-1">{{ point }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 要約 -->
      <div class="mt-8 bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
          <ClipboardDocumentListIcon class="w-6 h-6 mr-2 text-green-500" />
          要約
        </h2>
        
        <div v-if="isProcessing" class="text-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-500 mx-auto mb-4"></div>
          <p class="text-gray-600">要約生成中...</p>
        </div>
        
        <div v-else class="bg-green-50 rounded-lg p-6">
          <p class="text-gray-700 leading-relaxed text-lg">
            {{ summary }}
          </p>
        </div>
      </div>

      <!-- アクションボタン -->
      <div class="mt-8 text-center space-x-4">
        <button
          @click="goToTimeline"
          class="px-8 py-3 bg-blue-500 text-white rounded-lg font-medium transition-all duration-200 hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
        >
          時系列リスト・再生画面へ
        </button>
        
        <button
          @click="downloadTranscription"
          class="px-8 py-3 bg-gray-500 text-white rounded-lg font-medium transition-all duration-200 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50"
        >
          文字起こし結果をダウンロード
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useCounterStore } from '@/stores/counter'
import {
  ArrowLeftIcon,
  DocumentTextIcon,
  LightBulbIcon,
  ClipboardDocumentListIcon,
  MicrophoneIcon,
  ListBulletIcon
} from '@heroicons/vue/24/outline'

const router = useRouter()

// リアクティブな状態
const isProcessing = ref(true)
const recordingData = ref<any>(null)
const transcriptionParagraphs = ref<string[]>([])
const keyPoints = ref<string[]>([])
const summary = ref('')
const counterStore = useCounterStore()
// モックデータ（実際の実装では音声認識APIを使用）
const mockTranscription = counterStore.Whisper_response?.text ?? '';

const mockKeyPoints = [
  '25歳男性、軽度知的障害',
  '就労継続支援B型事業所に通所中',
  '清掃業務・軽作業に真面目に取り組み',
  '一般就労への意欲が高い',
  'コミュニケーション能力が向上',
  '就労移行支援事業所への移行を検討'
]

const mockSummary = '軽度知的障害の25歳男性の就労支援相談。現在B型事業所通所中で、一般就労を目指している。就労移行支援事業所への移行とジョブコーチ支援の活用を提案。今後は見学・体験利用を調整し、支援計画を策定予定。'

// 録音データの読み込み
onMounted(() => {
  const storedData = sessionStorage.getItem('recordingData')
  if (storedData) {
    recordingData.value = JSON.parse(storedData)
  }

  // 文字起こし処理のシミュレーション
  setTimeout(() => {
    transcriptionParagraphs.value = mockTranscription.trim().split('\n\n')
    keyPoints.value = mockKeyPoints
    summary.value = mockSummary
    isProcessing.value = false
  }, 3000)
})

// 録音画面に戻る
const goBack = () => {
  router.push('/')
}

// 時系列リスト画面に遷移
const goToTimeline = () => {
  // 文字起こしデータを保存
  const transcriptionData = {
    transcription: transcriptionParagraphs.value.join('\n\n'),
    keyPoints: keyPoints.value,
    summary: summary.value,
    recordingData: recordingData.value
  }
  sessionStorage.setItem('transcriptionData', JSON.stringify(transcriptionData))
  router.push('/timeline')
}

// 文字起こし結果のダウンロード
const downloadTranscription = () => {
  const content = `
東広島基幹相談支援センター - 相談記録
ファイル名: ${recordingData.value?.filename || '録音ファイル'}
録音日時: ${recordingData.value?.timestamp ? new Date(recordingData.value.timestamp).toLocaleString('ja-JP') : ''}

【文字起こし結果】
${transcriptionParagraphs.value.join('\n\n')}

【キーポイント】
${keyPoints.value.map((point, index) => `${index + 1}. ${point}`).join('\n')}

【要約】
${summary.value}
  `.trim()

  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${recordingData.value?.filename || '文字起こし結果'}.txt`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}
</script> 