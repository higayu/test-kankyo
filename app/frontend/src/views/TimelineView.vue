<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-50 to-indigo-100 p-6">
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
          時系列リスト・再生機能
        </h1>
        <p class="text-gray-600">{{ transcriptionData?.recordingData?.filename || '録音ファイル' }}</p>
      </div>

      <!-- 戻るボタン -->
      <div class="mb-6">
        <button
          @click="goBack"
          class="flex items-center px-4 py-2 text-blue-600 hover:text-blue-800 transition-colors"
        >
          <ArrowLeftIcon class="w-5 h-5 mr-2" />
          サマリ画面に戻る
        </button>
      </div>

      <!-- 音声プレイヤー -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
          <SpeakerWaveIcon class="w-6 h-6 mr-2 text-purple-500" />
          音声再生
        </h2>

        <!-- 再生コントロール -->
        <div class="flex items-center justify-center space-x-4 mb-6">
          <button
            @click="skipBackward"
            class="p-3 bg-gray-100 hover:bg-gray-200 rounded-full transition-colors"
          >
            <BackwardIcon class="w-6 h-6 text-gray-600" />
          </button>

          <button
            @click="togglePlayPause"
            class="p-4 bg-purple-500 hover:bg-purple-600 rounded-full transition-colors"
          >
            <PlayIcon v-if="!isPlaying" class="w-8 h-8 text-white" />
            <PauseIcon v-else class="w-8 h-8 text-white" />
          </button>

          <button
            @click="skipForward"
            class="p-3 bg-gray-100 hover:bg-gray-200 rounded-full transition-colors"
          >
            <ForwardIcon class="w-6 h-6 text-gray-600" />
          </button>
        </div>

        <!-- 再生位置バー -->
        <div class="mb-4">
          <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-500 w-16">{{ formatTime(currentTime) }}</span>
            <div class="flex-1 relative">
              <input
                type="range"
                :min="0"
                :max="duration"
                :value="currentTime"
                @input="seekTo"
                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
              />
              <div
                class="absolute top-0 left-0 h-2 bg-purple-500 rounded-lg pointer-events-none"
                :style="{ width: `${(currentTime / duration) * 100}%` }"
              ></div>
            </div>
            <span class="text-sm text-gray-500 w-16">{{ formatTime(duration) }}</span>
          </div>
        </div>

        <!-- 再生速度調整 -->
        <div class="flex items-center justify-center space-x-4">
          <span class="text-sm text-gray-600">再生速度:</span>
          <button
            v-for="speed in playbackSpeeds"
            :key="speed"
            @click="setPlaybackSpeed(speed)"
            class="px-3 py-1 rounded-full text-sm transition-colors"
            :class="[
              playbackSpeed === speed
                ? 'bg-purple-500 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]"
          >
            {{ speed }}x
          </button>
        </div>

        <!-- 隠れた音声要素 -->
        <audio
          ref="audioElement"
          :src="audioUrl"
          @loadedmetadata="onAudioLoaded"
          @timeupdate="onTimeUpdate"
          @ended="onAudioEnded"
          preload="metadata"
        ></audio>
      </div>

      <!-- 検索機能 -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
          <MagnifyingGlassIcon class="w-5 h-5 mr-2 text-blue-500" />
          検索・フィルタ
        </h3>
        
        <div class="flex items-end gap-4">
          <!-- 検索入力 -->
          <div class="flex-1">
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="文字起こし内容を検索..."
                class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                @input="performSearch"
              />
              <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
            </div>
            <div v-if="searchResults.length > 0" class="mt-2 text-sm text-gray-600">
              {{ searchResults.length }}件の検索結果
            </div>
          </div>
          
          <!-- しおりフィルタトグル -->
          <div class="flex items-center">
            <label class="flex items-center cursor-pointer">
              <input
                type="checkbox"
                v-model="showBookmarkedOnly"
                class="sr-only"
                @change="updateTimelineFilter"
              />
              <div class="relative">
                <div
                  class="w-10 h-6 rounded-full transition-colors duration-200"
                  :class="showBookmarkedOnly ? 'bg-yellow-500' : 'bg-gray-300'"
                ></div>
                <div
                  class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform duration-200"
                  :class="showBookmarkedOnly ? 'transform translate-x-4' : ''"
                ></div>
              </div>
              <span class="ml-2 text-sm text-gray-700 flex items-center whitespace-nowrap">
                <BookmarkIcon class="w-4 h-4 mr-1 text-yellow-500" />
                しおり箇所のみ
              </span>
            </label>
          </div>
        </div>
      </div>

      <!-- 時系列リスト -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
          <ClockIcon class="w-6 h-6 mr-2 text-blue-500" />
          時系列リスト
          <span v-if="searchQuery || showBookmarkedOnly" class="ml-2 text-sm font-normal text-gray-500">
            ({{ getFilterDescription() }}: {{ filteredSegments.length }}件)
          </span>
        </h2>

        <div class="space-y-4 max-h-96 overflow-y-auto">
          <div
            v-for="(segment, index) in filteredSegments"
            :key="segment.originalIndex"
            class="border rounded-lg p-4 transition-all duration-200 cursor-pointer hover:shadow-md"
            :class="[
              currentSegment === segment.originalIndex
                ? 'border-purple-500 bg-purple-50'
                : searchResults.includes(segment.originalIndex)
                ? 'border-blue-300 bg-blue-50'
                : hasBookmarkInSegment(segment)
                ? 'border-yellow-400 bg-yellow-50 shadow-sm'
                : 'border-gray-200 hover:border-gray-300'
            ]"
            @click="jumpToSegment(segment.originalIndex)"
          >
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0 relative">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium"
                  :class="hasBookmarkInSegment(segment) 
                    ? 'bg-yellow-500 text-white' 
                    : 'bg-blue-100 text-blue-600'"
                >
                  {{ segment.originalIndex + 1 }}
                </span>
                <BookmarkIcon 
                  v-if="hasBookmarkInSegment(segment)"
                  class="w-3 h-3 text-yellow-500 absolute -top-1 -right-1 fill-current"
                />
              </div>
              
              <div class="flex-1">
                <div class="flex items-center space-x-2 mb-2">
                  <span class="text-sm font-medium text-purple-600">
                    {{ formatTime(segment.startTime) }} - {{ formatTime(segment.endTime) }}
                  </span>
                  <button
                    @click.stop="jumpToTime(segment.startTime)"
                    class="text-xs bg-purple-100 text-purple-600 px-2 py-1 rounded-full hover:bg-purple-200 transition-colors"
                  >
                    再生
                  </button>
                  <button
                    @click.stop="addBookmark(segment.startTime, segment.text)"
                    class="text-xs px-2 py-1 rounded-full transition-colors flex items-center"
                    :class="hasBookmarkInSegment(segment) 
                      ? 'bg-red-500 text-white hover:bg-red-600' 
                      : 'bg-yellow-100 text-yellow-600 hover:bg-yellow-200'"
                  >
                    <BookmarkIcon class="w-3 h-3 mr-1" :class="hasBookmarkInSegment(segment) ? 'fill-current' : ''" />
                    {{ hasBookmarkInSegment(segment) ? '削除' : 'しおり' }}
                  </button>
                </div>
                
                <p class="text-gray-700 leading-relaxed" v-html="highlightSearchTerm(segment.text)">
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- アクションボタン -->
      <div class="mt-8 text-center space-x-4">
        <button
          @click="exportTimeline"
          class="px-8 py-3 bg-green-500 text-white rounded-lg font-medium transition-all duration-200 hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50"
        >
          時系列データをエクスポート
        </button>
        
        <button
          @click="exportBookmarks"
          class="px-8 py-3 bg-yellow-500 text-white rounded-lg font-medium transition-all duration-200 hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-opacity-50"
        >
          しおりをエクスポート
        </button>
        
        <button
          @click="goToRecording"
          class="px-8 py-3 bg-gray-500 text-white rounded-lg font-medium transition-all duration-200 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50"
        >
          新しい録音を開始
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  SpeakerWaveIcon,
  PlayIcon,
  PauseIcon,
  BackwardIcon,
  ForwardIcon,
  ClockIcon,
  MagnifyingGlassIcon,
  BookmarkIcon,
  XMarkIcon,
  MicrophoneIcon,
  ListBulletIcon
} from '@heroicons/vue/24/outline'

const router = useRouter()

// リアクティブな状態
const transcriptionData = ref<any>(null)
const audioElement = ref<HTMLAudioElement>()
const isPlaying = ref(false)
const currentTime = ref(0)
const duration = ref(0)
const playbackSpeed = ref(1)
const currentSegment = ref(-1)
const audioUrl = ref('')

// 検索・しおり機能の状態
const searchQuery = ref('')
const searchResults = ref<number[]>([])
const bookmarks = ref<Array<{
  id: string
  time: number
  note: string
  timestamp: string
}>>([])

// しおりフィルタ機能の状態
const bookmarkSearchQuery = ref('')
const bookmarkSortBy = ref<'time' | 'created' | 'text'>('time')
const bookmarkSortOrder = ref<'asc' | 'desc'>('asc')
const filteredBookmarks = ref<Array<{
  id: string
  time: number
  note: string
  timestamp: string
}>>([])

// 時系列リストのしおりフィルタ
const showBookmarkedOnly = ref(false)

// 再生速度オプション
const playbackSpeeds = [0.5, 0.75, 1, 1.25, 1.5, 2]

// 時系列セグメント（モックデータ）
const timelineSegments = ref([
  {
    startTime: 0,
    endTime: 15,
    text: '本日は東広島基幹相談支援センターにご相談いただき、ありがとうございます。今回は、知的障害をお持ちの25歳男性の方の就労支援についてのご相談です。'
  },
  {
    startTime: 15,
    endTime: 35,
    text: 'ご本人は軽度の知的障害があり、現在は就労継続支援B型事業所に通所されています。作業内容は清掃業務や軽作業を中心に行っており、真面目に取り組まれています。'
  },
  {
    startTime: 35,
    endTime: 55,
    text: 'ご家族からは、将来的に一般就労を目指したいとのご希望があります。ご本人も働く意欲は高く、コミュニケーション能力も向上してきています。'
  },
  {
    startTime: 55,
    endTime: 75,
    text: '就労移行支援事業所への移行を検討し、職業訓練や企業実習を通じてスキルアップを図ることを提案いたします。また、ジョブコーチ支援の活用も視野に入れています。'
  },
  {
    startTime: 75,
    endTime: 90,
    text: '今後は就労移行支援事業所の見学や体験利用を調整し、ご本人とご家族の意向を確認しながら支援計画を策定していく予定です。'
  }
])

// フィルタリングされたセグメント
const filteredSegments = computed(() => {
  let segments = timelineSegments.value.map((segment, index) => ({
    ...segment,
    originalIndex: index
  }))
  
  // テキスト検索でフィルタ
  if (searchQuery.value) {
    segments = segments.filter(segment => 
      segment.text.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
  }
  
  // しおりフィルタ
  if (showBookmarkedOnly.value) {
    const bookmarkedTimes = bookmarks.value.map(bookmark => bookmark.time)
    segments = segments.filter(segment => {
      return bookmarkedTimes.some(time => 
        time >= segment.startTime && time < segment.endTime
      )
    })
  }
  
  return segments
})

// 時間フォーマット関数
const formatTime = (seconds: number): string => {
  const minutes = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

// 検索実行
const performSearch = () => {
  if (!searchQuery.value.trim()) {
    searchResults.value = []
    return
  }
  
  const query = searchQuery.value.toLowerCase()
  searchResults.value = timelineSegments.value
    .map((segment, index) => ({ segment, index }))
    .filter(({ segment }) => segment.text.toLowerCase().includes(query))
    .map(({ index }) => index)
}

// 検索語句のハイライト
const highlightSearchTerm = (text: string): string => {
  if (!searchQuery.value.trim()) return text
  
  const regex = new RegExp(`(${searchQuery.value})`, 'gi')
  return text.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>')
}

// しおり追加/削除
const addBookmark = (time: number, text: string) => {
  // 既存のしおりをチェック
  const existingBookmarkIndex = bookmarks.value.findIndex(bookmark => 
    bookmark.time >= time - 1 && bookmark.time <= time + 1 // 1秒の誤差を許容
  )
  
  if (existingBookmarkIndex !== -1) {
    // 既存のしおりを削除
    bookmarks.value.splice(existingBookmarkIndex, 1)
  } else {
    // 新しいしおりを追加
    const note = text.substring(0, 50) + (text.length > 50 ? '...' : '')
    const bookmark = {
      id: Date.now().toString(),
      time,
      note,
      timestamp: new Date().toLocaleString('ja-JP')
    }
    
    bookmarks.value.push(bookmark)
    bookmarks.value.sort((a, b) => a.time - b.time)
  }
  
  // ローカルストレージに保存
  localStorage.setItem('bookmarks', JSON.stringify(bookmarks.value))
  
  // フィルタを更新
  filterBookmarks()
}

// しおり削除
const removeBookmark = (id: string) => {
  bookmarks.value = bookmarks.value.filter(bookmark => bookmark.id !== id)
  localStorage.setItem('bookmarks', JSON.stringify(bookmarks.value))
  filterBookmarks()
}

// しおりフィルタ機能
const filterBookmarks = () => {
  let filtered = [...bookmarks.value]
  
  // テキスト検索でフィルタ
  if (bookmarkSearchQuery.value.trim()) {
    const query = bookmarkSearchQuery.value.toLowerCase()
    filtered = filtered.filter(bookmark => 
      bookmark.note.toLowerCase().includes(query)
    )
  }
  
  // ソート
  filtered.sort((a, b) => {
    let comparison = 0
    
    switch (bookmarkSortBy.value) {
      case 'time':
        comparison = a.time - b.time
        break
      case 'created':
        comparison = new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime()
        break
      case 'text':
        comparison = a.note.localeCompare(b.note, 'ja')
        break
    }
    
    return bookmarkSortOrder.value === 'asc' ? comparison : -comparison
  })
  
  filteredBookmarks.value = filtered
}

// ソート順の切り替え
const toggleBookmarkSortOrder = () => {
  bookmarkSortOrder.value = bookmarkSortOrder.value === 'asc' ? 'desc' : 'asc'
  filterBookmarks()
}

// しおりテキストのハイライト
const highlightBookmarkText = (text: string): string => {
  if (!bookmarkSearchQuery.value.trim()) return text
  
  const regex = new RegExp(`(${bookmarkSearchQuery.value})`, 'gi')
  return text.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>')
}

// 時系列リストフィルタの更新
const updateTimelineFilter = () => {
  // フィルタ状態が変更されたときの処理（必要に応じて追加）
}

// フィルタ説明文の取得
const getFilterDescription = (): string => {
  const filters = []
  
  if (searchQuery.value) {
    filters.push('検索結果')
  }
  
  if (showBookmarkedOnly.value) {
    filters.push('しおり箇所')
  }
  
  return filters.join(' + ') || 'フィルタ'
}

// セグメントにしおりがあるかチェック
const hasBookmarkInSegment = (segment: any): boolean => {
  return bookmarks.value.some(bookmark => 
    bookmark.time >= segment.startTime && bookmark.time < segment.endTime
  )
}

// 音声データの読み込み
onMounted(() => {
  const storedData = sessionStorage.getItem('transcriptionData')
  if (storedData) {
    transcriptionData.value = JSON.parse(storedData)
    
    // 録音データから音声URLを取得
    const recordingData = transcriptionData.value.recordingData
    if (recordingData?.audioUrl) {
      audioUrl.value = recordingData.audioUrl
    }
  }
  
  // しおりをローカルストレージから読み込み
  const savedBookmarks = localStorage.getItem('bookmarks')
  if (savedBookmarks) {
    bookmarks.value = JSON.parse(savedBookmarks)
  }
  
  // 初期フィルタを実行
  filterBookmarks()
})

// 音声メタデータ読み込み完了
const onAudioLoaded = () => {
  if (audioElement.value) {
    duration.value = audioElement.value.duration || 90 // モックデータの場合は90秒
  }
}

// 再生時間更新
const onTimeUpdate = () => {
  if (audioElement.value) {
    currentTime.value = audioElement.value.currentTime
    
    // 現在のセグメントを更新
    const segmentIndex = timelineSegments.value.findIndex(
      segment => currentTime.value >= segment.startTime && currentTime.value < segment.endTime
    )
    currentSegment.value = segmentIndex
  }
}

// 再生終了
const onAudioEnded = () => {
  isPlaying.value = false
  currentSegment.value = -1
}

// 再生/一時停止の切り替え
const togglePlayPause = () => {
  if (!audioElement.value) return

  if (isPlaying.value) {
    audioElement.value.pause()
  } else {
    audioElement.value.play()
  }
  isPlaying.value = !isPlaying.value
}

// 早送り
const skipForward = () => {
  if (audioElement.value) {
    audioElement.value.currentTime = Math.min(
      audioElement.value.currentTime + 10,
      duration.value
    )
  }
}

// 巻き戻し
const skipBackward = () => {
  if (audioElement.value) {
    audioElement.value.currentTime = Math.max(
      audioElement.value.currentTime - 10,
      0
    )
  }
}

// 特定時間にシーク
const seekTo = (event: Event) => {
  const target = event.target as HTMLInputElement
  const time = parseFloat(target.value)
  if (audioElement.value) {
    audioElement.value.currentTime = time
  }
}

// 特定時間にジャンプ
const jumpToTime = (time: number) => {
  if (audioElement.value) {
    audioElement.value.currentTime = time
    if (!isPlaying.value) {
      togglePlayPause()
    }
  }
}

// セグメントにジャンプ
const jumpToSegment = (index: number) => {
  const segment = timelineSegments.value[index]
  if (segment) {
    jumpToTime(segment.startTime)
  }
}

// 再生速度設定
const setPlaybackSpeed = (speed: number) => {
  playbackSpeed.value = speed
  if (audioElement.value) {
    audioElement.value.playbackRate = speed
  }
}

// サマリ画面に戻る
const goBack = () => {
  router.push('/summary')
}

// 録音画面に戻る
const goToRecording = () => {
  router.push('/')
}

// 時系列データのエクスポート
const exportTimeline = () => {
  const content = `
東広島基幹相談支援センター - 相談記録（時系列）
ファイル名: ${transcriptionData.value?.recordingData?.filename || '録音ファイル'}
録音日時: ${transcriptionData.value?.recordingData?.timestamp ? new Date(transcriptionData.value.recordingData.timestamp).toLocaleString('ja-JP') : ''}

【時系列リスト】
${timelineSegments.value.map((segment, index) => 
  `${index + 1}. [${formatTime(segment.startTime)} - ${formatTime(segment.endTime)}] ${segment.text}`
).join('\n\n')}

【要約】
${transcriptionData.value?.summary || ''}
  `.trim()

  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${transcriptionData.value?.recordingData?.filename || '時系列データ'}_timeline.txt`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

// しおりのエクスポート
const exportBookmarks = () => {
  if (bookmarks.value.length === 0) {
    alert('エクスポートするしおりがありません。')
    return
  }
  
  const content = `
東広島基幹相談支援センター - しおり一覧
ファイル名: ${transcriptionData.value?.recordingData?.filename || '録音ファイル'}
録音日時: ${transcriptionData.value?.recordingData?.timestamp ? new Date(transcriptionData.value.recordingData.timestamp).toLocaleString('ja-JP') : ''}

【しおり一覧】
${bookmarks.value.map((bookmark, index) => 
  `${index + 1}. [${formatTime(bookmark.time)}] ${bookmark.note}\n   作成日時: ${bookmark.timestamp}`
).join('\n\n')}
  `.trim()

  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${transcriptionData.value?.recordingData?.filename || 'しおり'}_bookmarks.txt`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

onUnmounted(() => {
  if (audioElement.value) {
    audioElement.value.pause()
  }
})
</script>

<style scoped>
.slider::-webkit-slider-thumb {
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #8b5cf6;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.slider::-moz-range-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #8b5cf6;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}
</style> 