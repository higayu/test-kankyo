<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-6">
    <div class="max-w-4xl mx-auto">
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
          <router-link :to="{ name: 'leaflet-test' }">
            Leaflet テストへ
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
          東広島基幹相談支援センター
        </h1>
        <p class="text-gray-600">相談記録システム</p>
      </div>

      <!-- メインコンテンツ -->
      <div class="bg-white rounded-2xl shadow-xl p-8">
        <!-- 録音時間表示 -->
        <div class="text-center mb-8">
          <div class="text-6xl font-mono font-bold text-gray-800 mb-2">
            {{ formatTime(recordingTime) }}
          </div>
          <div class="text-sm text-gray-500">
            {{ isRecording ? '録音中...' : '録音待機中' }}
          </div>
        </div>

        <!-- 波形表示エリア -->
        <div class="mb-8">
          <div class="bg-gray-100 rounded-lg p-4 h-32 flex items-center justify-center">
            <canvas
              ref="waveformCanvas"
              class="w-full h-full"
              :class="{ 'opacity-50': !isRecording }"
            ></canvas>
          </div>
        </div>

        <!-- 録音ボタン -->
        <div class="text-center mb-8">
          <button
            @click="toggleRecording"
            :disabled="isProcessing"
            class="w-24 h-24 rounded-full transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-opacity-50"
            :class="[
              isRecording
                ? 'bg-red-500 hover:bg-red-600 focus:ring-red-300 animate-pulse'
                : 'bg-blue-500 hover:bg-blue-600 focus:ring-blue-300',
              isProcessing ? 'opacity-50 cursor-not-allowed' : ''
            ]"
          >
            <MicrophoneIcon v-if="!isRecording" class="w-10 h-10 text-white mx-auto" />
            <StopIcon v-else class="w-10 h-10 text-white mx-auto" />
          </button>
          <div class="mt-4 text-sm text-gray-600">
            {{ isRecording ? 'クリックして録音停止' : 'クリックして録音開始' }}
          </div>
        </div>

        <!-- ファイル名入力 -->
        <div class="mb-6">
          <label for="filename" class="block text-sm font-medium text-gray-700 mb-2">
            ファイル名
          </label>
          <input
            id="filename"
            v-model="filename"
            type="text"
            placeholder="録音ファイル名を入力してください"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>

        <!-- 保存ボタン -->
        <div class="text-center">
          <button
            @click="saveRecording"
            :disabled="!hasRecording || isProcessing"
            class="px-8 py-3 bg-green-500 text-white rounded-lg font-medium transition-all duration-200 hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="isProcessing">処理中...</span>
            <span v-else>保存して文字起こしへ</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { MicrophoneIcon, StopIcon } from '@heroicons/vue/24/solid'
import { ListBulletIcon } from '@heroicons/vue/24/outline'
import { useCounterStore } from '@/stores/counter'
import apiService from '@/services/api-service'

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

interface ApiResponse {
  success: boolean;
  message: string;
  data: {
    file: {
      path: string;
      original_name: string;
      size: number;
      mime_type: string;
    };
    text_data: TextData;
  };
}

const router = useRouter()

// リアクティブな状態
const isRecording = ref(false)
const isProcessing = ref(false)
const hasRecording = ref(false)
const recordingTime = ref(0)
const filename = ref('')
const waveformCanvas = ref<HTMLCanvasElement>()
const counterStore = useCounterStore()
// 録音関連の変数
let mediaRecorder: MediaRecorder | null = null
let audioChunks: Blob[] = []
let recordingInterval: number | null = null
let audioContext: AudioContext | null = null
let analyser: AnalyserNode | null = null
let animationId: number | null = null

// 時間フォーマット関数
const formatTime = (seconds: number): string => {
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const secs = seconds % 60
  return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

// 波形描画関数
const drawWaveform = () => {
  if (!waveformCanvas.value || !analyser) return

  const canvas = waveformCanvas.value
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const bufferLength = analyser.frequencyBinCount
  const dataArray = new Uint8Array(bufferLength)
  
  analyser.getByteFrequencyData(dataArray)

  ctx.clearRect(0, 0, canvas.width, canvas.height)
  
  const barWidth = (canvas.width / bufferLength) * 2.5
  let barHeight
  let x = 0

  for (let i = 0; i < bufferLength; i++) {
    barHeight = (dataArray[i] / 255) * canvas.height * 0.8
    
    ctx.fillStyle = `rgb(59, 130, 246, ${0.3 + (dataArray[i] / 255) * 0.7})`
    ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight)
    
    x += barWidth + 1
  }

  if (isRecording.value) {
    animationId = requestAnimationFrame(drawWaveform)
  }
}

// 録音開始/停止の切り替え
const toggleRecording = async () => {
  if (isRecording.value) {
    stopRecording()
  } else {
    await startRecording()
  }
}

// 録音開始
const startRecording = async () => {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ 
      audio: {
        channelCount: 1,        // モノラル
        sampleRate: 44100,      // サンプルレート
        sampleSize: 16,         // ビット深度
      } 
    })
    
    // AudioContextの設定
    audioContext = new AudioContext({
      sampleRate: 44100,        // サンプルレートを明示的に指定
      latencyHint: 'interactive'
    })
    const source = audioContext.createMediaStreamSource(stream)
    analyser = audioContext.createAnalyser()
    analyser.fftSize = 256
    source.connect(analyser)

    // サポートされているMIMEタイプを確認
    const mimeType = [
      'audio/webm;codecs=opus',  // 優先度1: Opusコーデック付きWebM
      'audio/webm',              // 優先度2: 通常のWebM
      'audio/mp4',               // 優先度3: MP4
      'audio/ogg;codecs=opus'    // 優先度4: Opusコーデック付きOgg
    ].find(type => MediaRecorder.isTypeSupported(type)) || 'audio/webm'

    console.log('使用するMIMEタイプ:', mimeType)

    // MediaRecorderの設定
    mediaRecorder = new MediaRecorder(stream, {
      mimeType: mimeType,
      audioBitsPerSecond: 128000
    })
    audioChunks = []

    mediaRecorder.ondataavailable = (event) => {
      if (event.data.size > 0) {
        audioChunks.push(event.data)
      }
    }

    mediaRecorder.onstop = () => {
      hasRecording.value = true
      console.log('録音停止:', {
        chunks: audioChunks.length,
        totalSize: audioChunks.reduce((acc, chunk) => acc + chunk.size, 0),
        mimeType: mediaRecorder?.mimeType
      })
    }

    // 1秒ごとにデータを取得
    mediaRecorder.start(1000)
    isRecording.value = true
    recordingTime.value = 0

    // 録音時間の更新
    recordingInterval = setInterval(() => {
      recordingTime.value++
    }, 1000)

    // 波形描画開始
    drawWaveform()

    // デフォルトファイル名の設定
    if (!filename.value) {
      const now = new Date()
      filename.value = `録音_${now.getFullYear()}${(now.getMonth() + 1).toString().padStart(2, '0')}${now.getDate().toString().padStart(2, '0')}_${now.getHours().toString().padStart(2, '0')}${now.getMinutes().toString().padStart(2, '0')}`
    }
  } catch (error) {
    console.error('録音開始エラー:', error)
    alert('マイクへのアクセスが拒否されました。ブラウザの設定を確認してください。')
  }
}

// 録音停止
const stopRecording = () => {
  if (mediaRecorder && isRecording.value) {
    mediaRecorder.stop()
    isRecording.value = false

    if (recordingInterval) {
      clearInterval(recordingInterval)
      recordingInterval = null
    }

    if (animationId) {
      cancelAnimationFrame(animationId)
      animationId = null
    }

    if (audioContext) {
      audioContext.close()
      audioContext = null
    }
  }
}

// 録音保存と文字起こし画面への遷移
const saveRecording = async () => {
  if (!hasRecording.value || audioChunks.length === 0) return

  isProcessing.value = true

  try {
    // 録音データをBlobに変換
    const audioBlob = new Blob(audioChunks, { 
      type: mediaRecorder?.mimeType || 'audio/webm'
    })

    console.log('保存する音声データ:', {
      size: audioBlob.size,
      type: audioBlob.type,
      chunks: audioChunks.length
    })

    // ファイル名に拡張子を追加（まだ付いていない場合）
    const extension = mediaRecorder?.mimeType?.includes('webm') ? 'webm' : 'wav'
    const finalFilename = filename.value.endsWith(`.${extension}`) 
      ? filename.value 
      : `${filename.value}.${extension}`

    // APIを使用して音声ファイルをアップロード
    const result = await apiService.uploadAudio(audioBlob, finalFilename) as ApiResponse
    
    if (!result.success) {
      throw new Error(result.message || 'アップロードに失敗しました')
    }

    counterStore.Whisper_response = result.data.text_data;

    // アップロード成功時のデータをセッションストレージに保存
    sessionStorage.setItem('recordingData', JSON.stringify({
      filename: finalFilename,
      duration: recordingTime.value,
      filePath: result.data.file.path,
      timestamp: new Date().toISOString()
    }))

    // 文字起こし画面に遷移
    router.push('/summary')
  } catch (error) {
    console.error('保存エラー:', error)
    alert('録音の保存に失敗しました。')
  } finally {
    isProcessing.value = false
  }
}

// キャンバスのリサイズ
const resizeCanvas = () => {
  if (waveformCanvas.value) {
    const canvas = waveformCanvas.value
    const rect = canvas.getBoundingClientRect()
    canvas.width = rect.width
    canvas.height = rect.height
  }
}

onMounted(() => {
  resizeCanvas()
  window.addEventListener('resize', resizeCanvas)
})

onUnmounted(() => {
  if (recordingInterval) {
    clearInterval(recordingInterval)
  }
  if (animationId) {
    cancelAnimationFrame(animationId)
  }
  if (audioContext) {
    audioContext.close()
  }
  window.removeEventListener('resize', resizeCanvas)
})
</script> 