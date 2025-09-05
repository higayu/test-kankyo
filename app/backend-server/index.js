import express from 'express'
import cors from 'cors'

const app = express()
const PORT = process.env.PORT || 3001

// ミドルウェアの設定
app.use(cors())
app.use(express.json())

// サンプルデータ
const posts = [
  {
    id: 1,
    title: 'Vue 3 バックグラウンド処理の学習',
    body: 'Vue 3における非同期処理、タイマー処理、API通信、状態管理、Web Workerについて学習します。',
    userId: 1
  },
  {
    id: 2,
    title: '非同期処理（async/await）',
    body: 'JavaScriptの非同期処理について理解し、Vue 3でどのように活用するかを学びます。',
    userId: 1
  },
  {
    id: 3,
    title: 'タイマー処理（setTimeout / setInterval）',
    body: '定期的な処理や遅延処理を実装する方法について学習します。',
    userId: 1
  },
  {
    id: 4,
    title: 'API 通信（axios）',
    body: '外部APIとの通信方法とエラーハンドリングについて学びます。',
    userId: 1
  },
  {
    id: 5,
    title: '状態管理（Pinia）',
    body: 'Vue 3の推奨状態管理ライブラリであるPiniaの使用方法を学習します。',
    userId: 1
  },
  {
    id: 6,
    title: 'Web Worker',
    body: 'UIとは別スレッドで重い処理を実行するWeb Workerの活用方法を学びます。',
    userId: 1
  }
]

// ルート
app.get('/', (req, res) => {
  res.json({
    message: 'Vue 3 バックグラウンド処理学習用APIサーバー',
    version: '1.0.0',
    endpoints: {
      posts: '/api/posts',
      health: '/api/health',
      slow: '/api/slow'
    }
  })
})

// ヘルスチェック
app.get('/api/health', (req, res) => {
  res.json({
    status: 'OK',
    timestamp: new Date().toISOString(),
    uptime: process.uptime()
  })
})

// 投稿データを取得
app.get('/api/posts', (req, res) => {
  // クエリパラメータでフィルタリング
  const { limit, userId } = req.query
  let filteredPosts = [...posts]
  
  if (userId) {
    filteredPosts = filteredPosts.filter(post => post.userId === parseInt(userId))
  }
  
  if (limit) {
    filteredPosts = filteredPosts.slice(0, parseInt(limit))
  }
  
  // 遅延をシミュレート（実際のAPI通信の遅延を体験するため）
  setTimeout(() => {
    res.json(filteredPosts)
  }, 500)
})

// 特定の投稿を取得
app.get('/api/posts/:id', (req, res) => {
  const postId = parseInt(req.params.id)
  const post = posts.find(p => p.id === postId)
  
  if (!post) {
    return res.status(404).json({ error: '投稿が見つかりません' })
  }
  
  res.json(post)
})

// 重い処理をシミュレート（Web Workerの学習用）
app.get('/api/slow', (req, res) => {
  const { iterations = 1000000 } = req.query
  
  console.log(`重い計算を開始: ${iterations}回のループ`)
  
  // 重い計算をシミュレート
  let result = 0
  for (let i = 0; i < parseInt(iterations); i++) {
    result += Math.sqrt(i) * Math.sin(i)
  }
  
  res.json({
    result: result.toFixed(2),
    iterations: parseInt(iterations),
    timestamp: new Date().toISOString()
  })
})

// 新しい投稿を作成
app.post('/api/posts', (req, res) => {
  const { title, body, userId } = req.body
  
  if (!title || !body) {
    return res.status(400).json({ error: 'タイトルと本文は必須です' })
  }
  
  const newPost = {
    id: posts.length + 1,
    title,
    body,
    userId: userId || 1
  }
  
  posts.push(newPost)
  
  res.status(201).json(newPost)
})

// エラーハンドリング
app.use((err, req, res, next) => {
  console.error(err.stack)
  res.status(500).json({ error: 'サーバー内部エラーが発生しました' })
})

// 404ハンドリング
app.use((req, res) => {
  res.status(404).json({ error: 'エンドポイントが見つかりません' })
})

// サーバー起動
app.listen(PORT, () => {
  console.log(`🚀 バックエンドサーバーが起動しました: http://localhost:${PORT}`)
  console.log(`📚 学習用APIエンドポイント:`)
  console.log(`   - GET  /api/posts - 投稿データを取得`)
  console.log(`   - GET  /api/health - サーバーの状態確認`)
  console.log(`   - GET  /api/slow - 重い処理のシミュレート`)
  console.log(`   - POST /api/posts - 新しい投稿を作成`)
}) 