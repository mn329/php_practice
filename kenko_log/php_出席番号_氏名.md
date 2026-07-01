# Kenko Log 未完成箇所調査レポート

## 提出者

- 学籍番号:
- 氏名:
- 調査日: 2026/06/24

## 調査した項目一覧

1. No.1: DB 接続情報
2. No.2: トップページのキャッチコピー
3. No.3: フッターのクレジット年表示
4. No.4: 健康記録一覧の心拍数表示
5. No.5: 健康記録の追加（INSERT 先テーブル名）
6. No.6: 健康記録の編集画面（id 取得）
7. No.7: 健康記録の CSV ダウンロード
8. No.8: アクティビティ一覧の種類表示
9. No.9: アクティビティ記録の追加（フォーム method）
10. No.10: 食事記録一覧の種別表示
11. No.11: 睡眠データ削除
12. No.12: ログアウト処理
13. No.13: 共通ナビゲーションの認証状態による出し分け
14. No.14: ログイン失敗時の入力値復元
15. No.16: ユーザー登録の CSRF トークン
16. No.17: ユーザー登録のパスワードハッシュ化
17. No.18: アクティビティグラフ API の URL

---

## No.1 DB 接続情報

#### 症状

- データベースに接続できない
- `health_log` データベースが存在しない

#### 確認したファイル

- `env.php`
- `env.sample.php`
- `admin/create_database.php`
- `lib/Database.php`

#### 原因

- `env.php` の DB 接続情報（ホスト・ユーザー・パスワード・ポート）が空のままだった
- ローカル環境（MAMP）に合わせた設定と、データベースの初期構築が必要だった

#### 修正内容

`env.php` に MAMP 環境向けの接続情報を設定した。

```php
const DB_CONNECTION = 'mysql';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'health_log';
const DB_USER = 'root';
const DB_PASS = 'root';
const DB_PORT = '3306';
const DB_CHARSET = 'utf8mb4';
```

あわせて `databases/schema.sql` と `databases/insert_data.sql` を使い、`health_log` データベースを作成した。

#### 動作確認

- `admin/create_database.php` にアクセスし、DB 作成が成功することを確認
- ログイン画面で「テストユーザーを入力」からログインできることを確認

---

## No.2 トップページのキャッチコピー

#### 症状

トップページ左側にキャッチコピーが表示されない。

#### 確認したファイル

- `index.php`
- `components/top/hero_left.php`

#### 原因

`index.php` で `components/top/hero_left.php` が読み込まれておらず、TODO コメントのまま表示されていた。

#### 修正内容

`index.php` のヒーローセクション左カラムに `hero_left.php` を include した。

```php
<?php include 'components/top/hero_left.php'; ?>
```

#### 動作確認

トップページのヒーローセクション左側に「日々の健康を、もっと楽しく、かんたんに記録。」のキャッチコピーと CTA ボタンが表示された。

---

## No.3 フッターのクレジット年表示

#### 症状

フッターのクレジットが「2020 - 2022」と固定表示され、現在の年が反映されない。

#### 確認したファイル

- `components/footer.php`

#### 原因

年数が HTML にハードコードされており、PHP の日付関数で動的に取得していなかった。

#### 修正内容

`date('Y')` を使い、終了年を現在の年で表示するように変更した。

```php
<span>&copy; 2020 - <?= date('Y') ?> <?= SITE_TITLE ?>. All rights reserved.</span>
```

#### 動作確認

フッターに「2020 - 2026」と表示されることを確認した。

---

## No.4 健康記録一覧の心拍数表示

#### 症状

健康記録の一覧表で心拍数（bpm）列に「TODO: 心拍数を表示」と表示され、実データが出ない。

#### 確認したファイル

- `health/index.php`
- `databases/schema.sql`（`heart_rate` カラムの定義）

#### 原因

一覧テーブルの心拍数セルが TODO コメントのままで、`$row['heart_rate']` を出力していなかった。DB には `heart_rate` カラムが存在する。

#### 修正内容

```php
<td class="px-5 py-4"><?= htmlspecialchars($row['heart_rate']) ?></td>
```

#### 動作確認

健康管理一覧で各記録の心拍数が数値として表示されることを確認した。

---

## No.5 健康記録の追加

#### 症状

健康記録を追加すると SQL エラーが発生する。

#### 確認したファイル

- `health/insert.php`
- `databases/schema.sql`

#### 原因

INSERT 文のテーブル名がプレースホルダ `xxxx` のままで、存在しないテーブルへ書き込もうとしていた。

#### 修正内容

テーブル名を `health_records` に修正した。

```php
$sql = "INSERT INTO health_records (user_id, weight, heart_rate, systolic, diastolic, recorded_at) 
        VALUES (:user_id, :weight, :heart_rate, :systolic, :diastolic, :recorded_at)";
```

#### 動作確認

健康記録の新規追加フォームからデータを送信し、一覧に追加されたことを確認した。

---

## No.6 健康記録の編集画面

#### 症状

編集画面で「該当する記録が見つかりません。」と表示され、フォームが開けない。

#### 確認したファイル

- `health/edit.php`
- `health/index.php`（Edit リンクの `?id=` パラメータ）

#### 原因

`$id` が `null` のまま固定されており、GET パラメータ `id` を取得していなかった。

#### 修正内容

```php
$id = (int) ($_GET['id'] ?? 0);
```

#### 動作確認

一覧の Edit リンクから編集画面を開き、既存データがフォームに表示されることを確認した。

---

## No.7 健康記録の CSV ダウンロード

#### 症状

CSV ダウンロードを実行するとエラーになり、ファイルが取得できない。

#### 確認したファイル

- `api/health/csv/index.php`
- `health/index.php`（CSV ダウンロードリンク）

#### 原因

SQL クエリが空文字列のままで、プリペアドステートメントの実行に失敗していた。

#### 修正内容

ログインユーザーの健康記録を最新 30 件取得する SQL を追加した。

```php
$sql = "SELECT recorded_at, weight, heart_rate, systolic, diastolic
        FROM health_records
        WHERE user_id = :user_id
        ORDER BY recorded_at DESC
        LIMIT 30";
```

#### 動作確認

健康管理画面から CSV ダウンロードを実行し、`health_records_latest.csv` がダウンロードされ、データ行が含まれることを確認した。

---

## No.8 アクティビティ一覧の種類表示

#### 症状

アクティビティ一覧の「種類」列で PHP Warning が発生し、種別が表示されない。

#### 確認したファイル

- `activity/index.php`
- `databases/schema.sql`（`exercise_type` カラム）

#### 原因

`$row['']` と空の配列キーを参照しており、正しいカラム名 `exercise_type` を使っていなかった。

#### 修正内容

```php
<td class="px-5 py-4"><?= htmlspecialchars($row['exercise_type']) ?></td>
```

#### 動作確認

アクティビティ一覧で「ウォーキング」など運動種別が表示され、Warning が出ないことを確認した。

---

## No.9 アクティビティ記録の追加

#### 症状

アクティビティ記録の登録フォームを送信すると「不正なリクエストです。」と表示される。

#### 確認したファイル

- `activity/add.php`
- `activity/insert.php`

#### 原因

フォームの `method` 属性が空で、GET リクエストとして送信されていた。`insert.php` は POST のみ受け付けるため、処理が拒否されていた。

#### 修正内容

```html
<form action="activity/insert.php" method="post" class="space-y-6">
```

#### 動作確認

アクティビティ記録を新規登録し、一覧に反映されることを確認した。

---

## No.10 食事記録一覧の種別表示

#### 症状

食事記録一覧の「種類」列が「TODO: 食事の種別を表示」のままで、実データが表示されない。

#### 確認したファイル

- `meal/index.php`（`mealTypeLabel()` 関数が既に定義済み）

#### 原因

種別表示のセルが TODO のままで、既存の `mealTypeLabel()` 関数を呼び出していなかった。

#### 修正内容

```php
<td class="px-5 py-4"><?= htmlspecialchars(mealTypeLabel($row['meal_type'])) ?></td>
```

#### 動作確認

食事記録一覧で「朝食」「昼食」など日本語ラベルが表示されることを確認した。

---

## No.11 睡眠データ削除

#### 症状

睡眠記録の編集画面から削除すると Fatal error が発生する。

#### 確認したファイル

- `sleep/delete.php`
- `sleep/edit.php`

#### 原因

DELETE 文の `execute()` にバインドパラメータ（`:id`, `:user_id`）が渡されておらず、SQL 実行時にエラーになっていた。

#### 修正内容

```php
$stmt->execute([
    ':id' => $id,
    ':user_id' => (int) $_SESSION['user']['id'],
]);
```

#### 動作確認

睡眠記録を削除し、一覧から消えることを確認した。Fatal error が発生しないことを確認した。

---

## No.12 ログアウト処理

#### 症状

メニューからログアウトしても、セッションが完全に破棄されず、再訪問時に認証状態が残ることがある。

#### 確認したファイル

- `logout/index.php`
- `lib/App.php`（セッション管理）

#### 原因

`$_SESSION['user']` の unset のみで、セッション全体の破棄とクッキーの削除が行われていなかった。

#### 修正内容

セッション変数のクリア、セッションクッキーの削除、`session_destroy()` を実行するように変更した。

#### 動作確認

ログアウト後にダッシュボードへ直接アクセスすると、ログイン画面へリダイレクトされることを確認した。

---

## No.13 共通ナビゲーションの認証状態による出し分け

#### 症状

ログイン前後に関わらず、ログイン済み用メニューと未ログイン用メニューの両方が同時に表示される。

#### 確認したファイル

- `components/nav.php`
- `components/user_nav.php`
- `components/public_nav.php`
- `app.php`（`$auth_user` の定義）

#### 原因

`user_nav.php` と `public_nav.php` が条件分岐なしで常に include されていた。

#### 修正内容

`$auth_user` の有無で include を切り替えた。ロゴリンクも `BASE_URL` を設定した。

```php
<?php if ($auth_user): ?>
    <?php include BASE_DIR . 'components/user_nav.php'; ?>
<?php else: ?>
    <?php include BASE_DIR . 'components/public_nav.php'; ?>
<?php endif; ?>
```

#### 動作確認

- 未ログイン時: ログイン・ユーザー登録ボタンのみ表示
- ログイン後: マイページ・各機能メニュー・ログアウトのみ表示

---

## No.14 ログイン失敗時の入力値復元

#### 症状

ログインに失敗しても、入力したメールアドレスがフォームに復元されない。

#### 確認したファイル

- `login/index.php`
- `login/auth.php`（`$_SESSION['login_old']` への保存処理）

#### 原因

`login/index.php` で `$old` が空の固定値 `['email' => '']` になっており、`$_SESSION['login_old']` を参照していなかった。

#### 修正内容

```php
$old = $_SESSION['login_old'] ?? ['email' => ''];
```

#### 動作確認

誤ったパスワードでログインを試み、メールアドレス欄に前回入力値が残ることを確認した。

---

## No.16 ユーザー登録の CSRF トークン

#### 症状

ユーザー登録を送信すると「不正なリクエストです。」と表示される。

#### 確認したファイル

- `register/input.php`
- `register/store.php`（`validateRegisterInput()` の CSRF 検証）

#### 原因

フォームの hidden フィールド `csrf_token` の value が空文字のままで、サーバー側の `hash_equals` 検証に失敗していた。

#### 修正内容

```php
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
```

#### 動作確認

新規ユーザーを登録し、ログイン画面へ遷移することを確認した。

---

## No.17 ユーザー登録のパスワードハッシュ化

#### 症状

ユーザー登録時に SQL エラーが発生する（`password_hash` カラムが空で INSERT される）。

#### 確認したファイル

- `register/store.php`
- `login/auth.php`（`password_verify` による認証）
- `databases/schema.sql`（`password_hash` カラム）

#### 原因

`createUser()` 内で `password_hash` が空文字のまま DB に保存されていた。ログイン側は `password_verify` を使うため、ハッシュ化が必須。

#### 修正内容

```php
$posts['password_hash'] = password_hash($posts['password'], PASSWORD_DEFAULT);
```

#### 動作確認

登録したユーザーでログインできることを確認した。

---

## No.18 アクティビティグラフ API の URL

#### 症状

アクティビティグラフ画面でデータが取得できず、グラフが表示されない。

#### 確認したファイル

- `js/activity_chart.js`
- `js/health_chart.js`（正しい URL 設定の参考）
- `api/activity/get/index.php`

#### 原因

`fetchActivityData()` 内の API URL が空文字のままで、`fetch('')` が失敗していた。

#### 修正内容

```javascript
const url = 'api/activity/get/';
```

#### 動作確認

アクティビティグラフ画面で消費カロリー・運動時間の折れ線グラフが表示されることを確認した。

---

## AI 利用について

- AI を利用したか: はい
- 利用した内容: 未完成箇所の調査・原因特定・修正コードの作成・レポートの構成整理
- 自分で確認した内容: 各画面での表示確認、DB 接続、ログイン・登録・CRUD 操作、CSV ダウンロード、グラフ表示

## 現状の問題点

- `env.php` の Gemini API キーは未設定のため、健康管理の AI 分析機能は API キー設定後に動作確認が必要
- `register/store.php` でパスワードも `App::sanitize()` の対象になっており、特殊文字を含むパスワードで問題が起きる可能性がある
- `lib/App.php` の `session_regenerate_id(true)` が毎リクエストで実行されており、セッション管理の挙動に注意が必要

## まとめ

`docs/未完成箇所レポート.md` に記載された 17 項目のうち、DB 接続を除く主要な UI・CRUD・認証・API 関連の不具合を調査・修正した。トップページ表示、各記録機能の CRUD、ナビゲーションの認証出し分け、ログイン・登録フロー、アクティビティグラフまで一通り動作することを確認した。Gemini API キーの設定のみ、各自の環境で別途対応が必要である。
