<?php

namespace App;

use RuntimeException;

/**
 * 画像アップロード（拡張子・MIME タイプ・サイズをチェック）
 */
class ImageUploader
{
    public const MAX_SIZE = 2 * 1024 * 1024; // 2MB

    // 許可する MIME タイプ => 保存時の拡張子
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * ファイルが選択されているか
     */
    public static function hasFile(?array $file): bool
    {
        return isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * 保存して、保存したファイル名を返す
     * 問題があれば RuntimeException を投げる
     */
    public static function save(array $file): string
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('ファイルの送信に失敗しました。');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('画像サイズは 2MB 以下にしてください。');
            default:
                throw new RuntimeException('ファイルのアップロードに失敗しました。');
        }

        if ($file['size'] > self::MAX_SIZE) {
            throw new RuntimeException('画像サイズは 2MB 以下にしてください。');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('画像は jpg / png / gif / webp のみアップロードできます。');
        }

        // 拡張子だけでなく中身（MIME タイプ）も確認する
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime]) || @getimagesize($file['tmp_name']) === false) {
            throw new RuntimeException('画像ファイルではありません。');
        }

        // 推測されにくいランダムなファイル名で保存
        $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
            throw new RuntimeException('画像の保存に失敗しました。');
        }

        return $filename;
    }

    public static function delete(?string $filename): void
    {
        if (!$filename) {
            return;
        }
        $path = UPLOAD_DIR . '/' . basename($filename);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
