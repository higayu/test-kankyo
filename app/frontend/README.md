# 東広島基幹相談支援センター 相談記録システム

Vue.js + Tailwind CSSで構築された障害者基幹相談支援センター用の録音・文字起こしシステムです。


## 1. 録音画面

### 機能要件
- 録音開始/停止ボタン
- 録音時間の表示
- 録音中の波形表示
- 録音ファイルの保存機能
- 録音ファイル名の入力欄

### UI要素
- ナビゲーションエリア
  - 録音画面（アイコン）
  - 録音一覧
- 録音ボタン（円形の大きなボタン）
- 録音時間表示（HH:MM:SS形式）
- 波形表示エリア
- ファイル名入力フォーム
- 保存ボタン


## 2. 録音一覧

### 機能要件
- 録音ファイルごとの一覧を表示
- 録音時間
- 処理中かどうか表示
- 削除ボタン
- 一覧の右上にファイルから追加ボタン

## 3. 文字起こしサマリ画面

### 機能要件
- 録音ファイルの文字起こし結果の表示
- キーポイントの抽出と表示
- 要約の表示
- 時系列リスト画面への遷移ボタン

### UI要素
- 文字起こしテキスト表示エリア
- キーポイント一覧
- 要約テキスト表示エリア
- 時系列リスト画面への遷移ボタン

## 4. 文字起こし時系列リスト＋再生機能画面

### 機能要件
- 文字起こしの時系列リスト表示
- 音声再生機能
- 再生位置の表示
- 再生速度調整
- 特定の文字起こし部分へのジャンプ機能

### UI要素
- 時系列リスト表示エリア
- 再生コントロール（再生/一時停止、早送り、巻き戻し）
- 再生速度調整スライダー
- 再生位置表示バー
- 時間表示

## 共通仕様

### デザイン要件
- モダンでクリーンなUI
- レスポンシブデザイン対応
- アクセシビリティ対応
- ダークモード対応


### 画面遷移
1. 録音画面 → 文字起こしサマリ画面（録音完了後）
2. 文字起こしサマリ画面 → 時系列リスト画面（遷移ボタンクリック時）
3. 各画面間の戻るボタンによる遷移

## 技術スタック

- **フロントエンド**: Vue.js 3 (Composition API)
- **スタイリング**: Tailwind CSS
- **ルーティング**: Vue Router
- **状態管理**: Pinia
- **アイコン**: Heroicons
- **音声処理**: Web Audio API
- **開発環境**: Vite + TypeScript

## セットアップ

### 前提条件
- Node.js 18以上
- npm または yarn

### インストール

```bash
# 依存関係のインストール
npm install

# 開発サーバーの起動
npm run dev

# ビルド
npm run build

# プレビュー
npm run preview
```

## 使用方法

### 1. 録音の開始
1. ブラウザでアプリケーションにアクセス
2. マイクへのアクセス許可を与える
3. 録音ボタンをクリックして録音開始
4. ファイル名を入力（自動生成も可能）
5. 録音停止後、「保存して文字起こしへ」をクリック

### 2. 文字起こし結果の確認
1. 文字起こし処理の完了を待つ（約3秒のシミュレーション）
2. 文字起こし結果、キーポイント、要約を確認
3. 必要に応じて結果をダウンロード
4. 「時系列リスト・再生画面へ」をクリック

### 3. 音声の再生と確認
1. 音声プレイヤーで録音を再生
2. 時系列リストで特定部分にジャンプ
3. 再生速度を調整して効率的に確認
4. 時系列データをエクスポート

## ブラウザ対応

- Chrome 88+
- Firefox 85+
- Safari 14+
- Edge 88+

※ マイクアクセスにはHTTPS環境が必要です

## 開発

### 開発サーバーの起動
```bash
npm run dev
```

### ビルド
```bash
npm run build
```

### リンティング
```bash
npm run lint
```

### フォーマット
```bash
npm run format
```

## 注意事項

- 現在は文字起こし機能はモックデータを使用しています
- 実際の運用では音声認識API（OpenAI Whisper等）の統合が必要です
- 録音データはブラウザのセッションストレージに一時保存されます
- 本格運用時はサーバーサイドでの永続化が推奨されます

## ライセンス

このプロジェクトは東広島基幹相談支援センター専用に開発されました。

## Recommended IDE Setup

[VSCode](https://code.visualstudio.com/) + [Volar](https://marketplace.visualstudio.com/items?itemName=Vue.volar) (and disable Vetur).

## Type Support for `.vue` Imports in TS

TypeScript cannot handle type information for `.vue` imports by default, so we replace the `tsc` CLI with `vue-tsc` for type checking. In editors, we need [Volar](https://marketplace.visualstudio.com/items?itemName=Vue.volar) to make the TypeScript language service aware of `.vue` types.

## Customize configuration

See [Vite Configuration Reference](https://vite.dev/config/).

## Project Setup

```sh
npm install
```

### Compile and Hot-Reload for Development

```sh
npm run dev
```

### Type-Check, Compile and Minify for Production

```sh
npm run build
```

### Run Unit Tests with [Vitest](https://vitest.dev/)

```sh
npm run test:unit
```

### Lint with [ESLint](https://eslint.org/)

```sh
npm run lint
```
