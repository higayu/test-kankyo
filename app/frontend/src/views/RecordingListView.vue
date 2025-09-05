<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-50 to-indigo-100 p-6">
    <div class="max-w-4xl mx-auto">
      <!-- ヘッダー -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
          録音一覧
        </h1>
        <p class="text-gray-600">東広島基幹相談支援センター</p>
      </div>

      <!-- アクションバー -->
      <div class="flex justify-between items-center mb-6">
        <div class="flex items-center space-x-4">
          <button
            @click="goToRecording"
            class="flex items-center px-4 py-2 text-blue-600 hover:text-blue-800 transition-colors"
          >
            <MicrophoneIcon class="w-5 h-5 mr-2" />
            新しい録音
          </button>
        </div>
        
        <div class="flex items-center space-x-2">
          <input
            ref="fileInput"
            type="file"
            accept="audio/*"
            @change="handleFileUpload"
            class="hidden"
          />
          <button
            @click="triggerFileUpload"
            class="flex items-center px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors"
          >
            <DocumentPlusIcon class="w-5 h-5 mr-2" />
            ファイルから追加
          </button>
        </div>
      </div>

      <!-- 録音一覧 -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
          <ListBulletIcon class="w-6 h-6 mr-2 text-blue-500" />
          録音ファイル一覧
          <span class="ml-2 text-sm font-normal text-gray-500">
            ({{ recordings.length }}件)
          </span>
        </h2>

        <div v-if="recordings.length === 0" class="text-center py-12">
          <MicrophoneIcon class="w-16 h-16 text-gray-300 mx-auto mb-4" />
          <p class="text-gray-500 text-lg mb-4">録音ファイルがありません</p>
          <button
            @click="goToRecording"
            class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
          >
            最初の録音を開始
          </button>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="recording in recordings"
            :key="recording.id"
            class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all duration-200"
            :class="recording.isProcessing ? 'bg-blue-50 border-blue-200' : 'hover:border-gray-300'"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <div class="flex items-center space-x-3 mb-2">
                  <h3 class="text-lg font-medium text-gray-800">
                    {{ recording.filename }}
                  </h3>
                  
                  <!-- 処理状況バッジ -->
                  <span
                    v-if="recording.isProcessing"
                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                  >
                    <div class="w-2 h-2 bg-blue-500 rounded-full mr-1 animate-pulse"></div>
                    処理中
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"
                  >
                    <CheckCircleIcon class="w-3 h-3 mr-1" />
                    完了
                  </span>
                </div>
                
                <div class="flex items-center space-x-4 text-sm text-gray-600">
                  <div class="flex items-center">
                    <ClockIcon class="w-4 h-4 mr-1" />
                    録音時間: {{ formatDuration(recording.duration) }}
                  </div>
                  <div class="flex items-center">
                    <CalendarIcon class="w-4 h-4 mr-1" />
                    {{ formatDate(recording.createdAt) }}
                  </div>
                </div>
              </div>

              <div class="flex items-center space-x-2">
                <!-- 再生ボタン -->
                <button
                  v-if="!recording.isProcessing"
                  @click="playRecording(recording)"
                  class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors"
                  title="再生"
                >
                  <PlayIcon class="w-5 h-5" />
                </button>

                <!-- サマリ表示ボタン -->
                <button
                  v-if="!recording.isProcessing"
                  @click="viewSummary(recording)"
                  class="p-2 text-green-600 hover:text-green-800 hover:bg-green-50 rounded-lg transition-colors"
                  title="サマリ表示"
                >
                  <DocumentTextIcon class="w-5 h-5" />
                </button>

                <!-- 削除ボタン -->
                <button
                  @click="deleteRecording(recording.id)"
                  class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors"
                  title="削除"
                >
                  <TrashIcon class="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  MicrophoneIcon,
  DocumentPlusIcon,
  ListBulletIcon,
  ClockIcon,
  CalendarIcon,
  PlayIcon,
  DocumentTextIcon,
  TrashIcon,
  CheckCircleIcon
} from '@heroicons/vue/24/outline'

const router = useRouter()

// リアクティブな状態
const recordings = ref<Array<{
  id: string
  filename: string
  duration: number
  createdAt: string
  isProcessing: boolean
  audioUrl?: string
}>>([])

const fileInput = ref<HTMLInputElement>()

// 時間フォーマット関数
const formatDuration = (seconds: number): string => {
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const secs = Math.floor(seconds % 60)
  
  if (hours > 0) {
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
  }
  return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

// 日付フォーマット関数
const formatDate = (dateString: string): string => {
  const date = new Date(dateString)
  return date.toLocaleString('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// 録音画面に遷移
const goToRecording = () => {
  router.push('/')
}

// ファイルアップロードのトリガー
const triggerFileUpload = () => {
  fileInput.value?.click()
}

// ファイルアップロード処理
const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (file) {
    const audioUrl = URL.createObjectURL(file)
    const newRecording = {
      id: Date.now().toString(),
      filename: file.name,
      duration: 0, // 実際の実装では音声ファイルから取得
      createdAt: new Date().toISOString(),
      isProcessing: true,
      audioUrl
    }
    
    recordings.value.unshift(newRecording)
    
    // 模擬的な処理完了（実際の実装では文字起こし処理）
    setTimeout(() => {
      const recording = recordings.value.find(r => r.id === newRecording.id)
      if (recording) {
        recording.isProcessing = false
        recording.duration = 120 // 模擬的な時間
      }
    }, 3000)
    
    // ファイル入力をリセット
    target.value = ''
  }
}

// 録音再生
const playRecording = (recording: any) => {
  // 実際の実装では音声プレイヤーを開く
  console.log('再生:', recording.filename)
}

// サマリ表示
const viewSummary = (recording: any) => {
  // 録音データをセッションストレージに保存
  const transcriptionData = {
    recordingData: {
      filename: recording.filename,
      duration: recording.duration,
      timestamp: recording.createdAt,
      audioUrl: recording.audioUrl
    },
    transcription: `本日は東広島基幹相談支援センターにご相談いただき、ありがとうございます。今回は、知的障害をお持ちの25歳男性の方の就労支援についてのご相談です。

ご本人は軽度の知的障害があり、現在は就労継続支援B型事業所に通所されています。作業内容は清掃業務や軽作業を中心に行っており、真面目に取り組まれています。

ご家族からは、将来的に一般就労を目指したいとのご希望があります。ご本人も働く意欲は高く、コミュニケーション能力も向上してきています。

就労移行支援事業所への移行を検討し、職業訓練や企業実習を通じてスキルアップを図ることを提案いたします。また、ジョブコーチ支援の活用も視野に入れています。

今後は就労移行支援事業所の見学や体験利用を調整し、ご本人とご家族の意向を確認しながら支援計画を策定していく予定です。`,
    keyPoints: [
      '25歳男性、軽度知的障害',
      '現在：就労継続支援B型事業所に通所',
      '作業内容：清掃業務、軽作業',
      '目標：一般就労を目指す',
      '提案：就労移行支援事業所への移行',
      '支援：ジョブコーチ支援の活用',
      '今後：事業所見学・体験利用の調整'
    ],
    summary: '25歳男性（軽度知的障害）の就労支援相談。現在B型事業所で清掃・軽作業に従事。一般就労を目標とし、就労移行支援事業所への移行とジョブコーチ支援を提案。今後は事業所見学・体験利用を通じて支援計画を策定予定。'
  }
  
  sessionStorage.setItem('transcriptionData', JSON.stringify(transcriptionData))
  router.push('/summary')
}

// 録音削除
const deleteRecording = (id: string) => {
  if (confirm('この録音ファイルを削除しますか？')) {
    recordings.value = recordings.value.filter(r => r.id !== id)
    // 実際の実装ではサーバーからも削除
  }
}

// 初期化
onMounted(() => {
  // 模擬データの読み込み
  recordings.value = [
    {
      id: '1',
      filename: '相談記録_2024-01-15_001.wav',
      duration: 180,
      createdAt: '2024-01-15T10:30:00.000Z',
      isProcessing: false
    },
    {
      id: '2',
      filename: '相談記録_2024-01-14_002.wav',
      duration: 240,
      createdAt: '2024-01-14T14:15:00.000Z',
      isProcessing: false
    },
    {
      id: '3',
      filename: '相談記録_2024-01-14_001.wav',
      duration: 0,
      createdAt: '2024-01-14T09:45:00.000Z',
      isProcessing: true
    }
  ]
})
</script> 