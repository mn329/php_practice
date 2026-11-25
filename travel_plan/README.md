# ✈️ TabiPlan — 旅行計画アプリ

旅行の **日程・予算・持ち物・現地の天気・地図** をひとつのプランにまとめて管理できる PHP アプリです。
ユーザー登録して、自分だけの旅行プランを作成できます。

## 主な機能

| 機能 | 内容 |
|---|---|
| ユーザー登録・ログイン | パスワードはハッシュ化して保存、セッションでログイン状態を保持 |
| プランの作成・編集・削除 | タイトル・行き先・日程・予算・メモ・表紙画像 |
| プラン一覧 | キーワード検索、ページ切り替え、「出発まであと○日」の表示 |
| 日程（スケジュール） | 何日目・時刻・場所・費用・メモを登録し、日ごとにタイムラインで表示（時刻順に自動で差し込み） |
| 並び替え（手動） | ドラッグ＆ドロップで順番を入れ替え。別の日へも移動できる |
| 並び替え（自動） | 「最短ルート順」ボタンで、移動距離が短くなる順に並び替え（最近傍法 + 2-opt 法） |
| 地図 | 日程の観光地にピンを立て、日ごとに色を分けて回る順に線でつなぐ。1 日の移動距離も表示 |
| 入力候補（サジェスト） | 行き先・スポット名を入力すると候補を表示。選ぶと地図がその場所へ移動し、正確な位置が保存される |
| 行き先の位置 | プラン作成画面の地図で確認・調整（クリック・ピンのドラッグ）。都道府県名は県庁所在地を中心にする |
| 周辺のおすすめスポット | 地図上に寺社・博物館・公園などを表示。ポップアップに写真・行き先や最寄りの予定からの距離を出し、そのまま日程に追加。地図を動かして「この周辺を探す」で別の場所も探せる |
| 持ち物のおすすめ | 天気予報（雨・寒さ・暑さ）、季節、泊数、海外かどうか、日程の多さから持ち物を提案 |
| 予算管理 | 日程の費用を合計し、予算の残りやオーバーを表示 |
| 現地の天気 | 行き先の 16 日間の天気予報を表示し、旅行の日をハイライト |
| 持ち物チェックリスト | 追加・チェック・削除、定番の持ち物をまとめて追加 |

## 動作環境

- PHP 8.1 以上（`pdo_sqlite` / `fileinfo` 拡張。通常は標準で有効）
- Composer
- インターネット接続（天気・地図の取得に使用）

すべて **無料** で、API キーの登録も不要です。

## セットアップ

```bash
cd travel_plan
composer install          # vendor/ を作成（Carbon を導入）
php -S localhost:8000 -t public
```

ブラウザで http://localhost:8000 を開き、「新規登録」からアカウントを作成してください。

MAMP の場合は `htdocs` 配下に置き、`http://localhost:8888/（配置したパス）/travel_plan/` にアクセスします。

### データベースについて

- SQLite を使用しているため、**DB サーバーやパスワードの設定は不要** です。
- 初回アクセス時に `schema.sql` が自動で実行され、`data/travel.sqlite` が作成されます。
- 手動で作る場合は `sqlite3 data/travel.sqlite < schema.sql` を実行します。
- `data/` と `public/uploads/` には書き込み権限が必要です。

## ファイル構成

```
travel_plan/
├── composer.json          # Carbon の導入と App\ 名前空間のオートロード
├── schema.sql             # テーブル作成用 SQL
├── index.php              # MAMP 用（public/ へリダイレクト）
├── data/                  # SQLite データベース（自動作成）
├── public/                # ドキュメントルート
│   ├── index.php          # トップ / プラン一覧（GET: 検索・ページ番号）
│   ├── register.php       # 新規登録（POST）
│   ├── login.php          # ログイン（POST）
│   ├── logout.php         # ログアウト（POST）
│   ├── plan.php           # プラン詳細（GET: ?id=）日程・地図・予算・天気・持ち物
│   ├── plan_form.php      # プランの作成・編集（POST + 画像アップロード）
│   ├── spots.php          # 周辺のおすすめスポットを JSON で返す（GET: ?lat=&lng=）
│   ├── suggest.php        # 入力候補を JSON で返す（GET: ?type=&q=）
│   ├── item_reorder.php   # ドラッグ＆ドロップの並び順を保存（fetch で POST → JSON）
│   ├── item_optimize.php  # 最短ルート順に並び替え（POST）
│   ├── plan_delete.php    # プランの削除（POST）
│   ├── item_save.php      # 日程の追加（POST）
│   ├── item_delete.php    # 日程の削除（POST）
│   ├── checklist.php      # 持ち物の追加・チェック・削除（POST）
│   ├── css/style.css
│   ├── js/suggest.js      # 入力候補のドロップダウン
│   ├── js/plan-map.js     # 詳細画面の地図（日程のピン・おすすめスポット）
│   └── uploads/           # アップロードされた表紙画像
└── src/
    ├── bootstrap.php      # 共通の初期化（オートロード・セッション開始・設定）
    ├── helpers.php        # 共通関数（エスケープ・リダイレクト・CSRF など）
    ├── templates/
    │   ├── header.php     # 共通ヘッダー（require で読み込み）
    │   └── footer.php     # 共通フッター（require で読み込み）
    ├── Database.php       # PDO 接続・テーブル自動作成
    ├── UserRepository.php
    ├── PlanRepository.php
    ├── ItemRepository.php
    ├── ChecklistRepository.php
    ├── ImageUploader.php  # 画像のチェックと保存
    ├── Geocoder.php       # 行き先の地名 → 緯度経度
    ├── Weather.php        # 天気予報 API
    ├── PlaceLocator.php   # 地名 → 緯度経度（地図用）
    ├── PlaceSuggester.php # 入力候補 API
    ├── NearbySpots.php    # 周辺スポット API
    ├── RouteOptimizer.php # 距離計算・最短ルートの並び替え
    └── PackingAdvisor.php # 持ち物のおすすめ
```

## 課題の要件との対応

### 必須

| 要件 | 対応箇所 |
|---|---|
| 01 PHP で動作する | `php -S localhost:8000 -t public` または MAMP |
| 02 2 つ以上の .php ファイル | `public/` 内に 10 ファイル。リンクやフォームで行き来できる |
| 03 GET の利用 | `index.php?q=京都&page=2`（検索・ページ番号）、`plan.php?id=3`、`spots.php?lat=35.01&lng=135.76`、`suggest.php?q=清水` |
| 04 POST の利用 | 登録・ログイン・プラン作成・日程追加・持ち物チェックなど |
| 05 include / require | `src/templates/header.php` と `footer.php` を全ページで `require` |

### 加点

| 要件 | 対応箇所 |
|---|---|
| 06 セッション | `$_SESSION` でログイン状態・フラッシュメッセージ・CSRF トークン・天気のキャッシュを保持 |
| 07 データの永続化 | PDO + SQLite。プリペアドステートメントで users / plans / items / checklist の CRUD |
| 08 関数・クラス | `helpers.php` の関数、`src/` のクラス（Repository・ImageUploader・Weather・RouteOptimizer・PackingAdvisor など） |
| 09 PRG パターン | POST の処理後は必ず `redirect()`（`header("Location: ...")`）。入力エラーもセッションに保存してリダイレクト |
| 10 ファイルアップロード | 表紙画像。拡張子・MIME タイプ・サイズ（2MB）をチェックし、ランダムなファイル名で保存 |
| 11 外部 API | Open-Meteo（天気予報・地名検索）、Nominatim（観光地の緯度経度）、Photon（入力候補）、Wikipedia（周辺スポット）を `file_get_contents` で呼び出し、JSON を表示 |
| 13 Composer | `nesbot/carbon`（日付計算・「出発まであと○日」）、PSR-4 オートロード |
| 14 UI/UX | CSS でカード型のデザイン、スマホ対応、フラッシュメッセージ、地図（Leaflet）、ドラッグ＆ドロップ（SortableJS）、入力候補（キーボード操作・日本語入力に対応） |
| 15 その他 | CSRF 対策、XSS 対策（`htmlspecialchars`）、パスワードのハッシュ化、他人のプランを操作できないように所有者チェック |

※ 12 フレームワークは使用していません（素の PHP で作成）。

## 使用している外部サービス（すべて無料・API キー不要）

- [Open-Meteo](https://open-meteo.com/)：天気予報・地名検索（CC BY 4.0）
- [OpenStreetMap Nominatim](https://nominatim.openstreetmap.org/)：観光地の緯度経度（利用規約に従い、日程の登録時のみ呼び出し。入力補完には使わない）
- [Photon](https://photon.komoot.io/)：入力中の地名候補（OpenStreetMap のデータ）
- [Wikipedia API](https://ja.wikipedia.org/w/api.php)：周辺のおすすめスポット（記事の位置情報・説明・画像。CC BY-SA）
- [SortableJS](https://sortablejs.github.io/Sortable/)：ドラッグ＆ドロップ
- [Leaflet](https://leafletjs.com/) + [OpenStreetMap](https://www.openstreetmap.org/copyright) のタイル：地図表示

## セキュリティ・機密情報

- DB のパスワードなどの機密情報はありません（SQLite のため不要）。
- 提出用 zip には `vendor/`・`data/*.sqlite`・`public/uploads/` の画像を含めません。

## 提出用 zip の作り方

`vendor/`・データベース・アップロード画像を除いて圧縮します（`01_東京太郎` の部分は自分の出席番号と氏名に変更）。

```bash
cd ..   # travel_plan の 1 つ上のフォルダ
zip -r php_01_東京太郎.zip travel_plan \
  -x "travel_plan/vendor/*" "travel_plan/data/*.sqlite*" "travel_plan/public/uploads/*.*" "*.DS_Store"
```

## AI の活用について

<!-- ↓ 実際の使い方に合わせて自分の言葉で書き直してください -->

- テーマ決め（旅行計画アプリ）と機能の洗い出しに Claude Code を使用しました。
- コードの土台（ファイル構成・クラス設計・CSS）は Claude Code に生成してもらいました。
- 天気 API で「京都」のように「市」が無いと見つからない問題を AI と一緒に調べ、「市」を付けて再検索する処理を加えました。
- 並び替え・入力候補・おすすめスポット・持ち物のおすすめも Claude Code で追加しました。周辺スポットは最初に試した Overpass API が遅すぎたため、Wikipedia の API に切り替えました。
- 自分で改良・カスタマイズした点：
  - （例）〇〇の機能を追加した
  - （例）デザインの色や文言を変更した
