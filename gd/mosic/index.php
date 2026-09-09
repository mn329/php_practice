<?php
// ピクセル化する
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image']['tmp_name'];
    $pixelSize = intval($_POST['pixel']) ?: 20;

    if (!file_exists($file)) {
        die('ファイルが見つかりません。');
    }

    // 画像を読み込む
    $upload_file = file_get_contents($file); // ファイルをバイナリとして読み込む
    $src = imagecreatefromstring($upload_file); // JPEG/PNG などを GD の画像リソースに変換
    $width = imagesx($src);   // 画像の幅を取得
    $height = imagesy($src); // 画像の高さを取得

    // モザイクの作り方: 小さくしてから元サイズに戻す
    // 例) 400x400・pixelSize=20 → 20x20 に縮小 → 400x400 に拡大
    //     小さい画像の1ピクセルが、だいたい 20x20 の色ブロックになる

    // 縮小後の画像の幅・高さを計算
    $smallW = intval($width / $pixelSize);
    $smallH = intval($height / $pixelSize);

    // 縮小する（細かい情報を捨てて、粗い色情報だけ残す）
    $small = imagecreatetruecolor($smallW, $smallH); // 指定サイズの空キャンバス（TrueColor画像）を作る
    imagecopyresampled($small, $src, 0, 0, 0, 0, $smallW, $smallH, $width, $height); // なめらかにリサイズしてコピー（縮小に使用）

    // 拡大する（粗い色を元サイズに引き伸ばして、モザイクに見せる）
    $pixelated = imagecreatetruecolor($width, $height); // 指定サイズの空キャンバス（TrueColor画像）を作る
    imagecopyresized($pixelated, $small, 0, 0, 0, 0, $width, $height, $smallW, $smallH); // 粗めにリサイズしてコピー（拡大に使用。ブロック感が出やすい）

    // 画像を出力
    header('Content-Type: image/png');
    imagepng($pixelated);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pixel Art Generator</title>
    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <main class="w-full max-w-lg">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-sky-600 px-6 py-5">
                <h1 class="text-2xl font-bold text-white">Pixel Art Generator</h1>
                <p class="text-sky-100 text-sm mt-1">GD で画像をピクセル風に変換</p>
            </div>

            <div class="p-6 space-y-6">
                <form method="post" enctype="multipart/form-data" class="space-y-6">
                    <!-- 画像選択 -->
                    <div>
                        <label class="block mb-1 font-medium text-slate-700">画像を選択</label>
                        <input type="file" name="image" accept="image/*" required
                            class="w-full rounded-xl border border-slate-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>

                    <!-- ピクセルの粗さ -->
                    <div>
                        <label class="block mb-1 font-medium text-slate-700">ピクセルの粗さ（推奨: 10〜50）</label>
                        <div class="flex items-center gap-3">
                            <input type="range" id="pixelRange" name="pixel" value="20" min="2" max="100"
                                class="flex-1 accent-sky-600">
                            <span class="font-mono text-sm text-slate-800 bg-slate-100 rounded px-2 py-1">
                                <span id="pixelValue">20</span> px
                            </span>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full rounded-xl bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-700 transition">
                        ピクセル化する
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        const range = document.getElementById('pixelRange');
        const valueDisplay = document.getElementById('pixelValue');
        range.addEventListener('input', () => {
            valueDisplay.textContent = range.value;
        });
    </script>
</body>

</html>