<?php

namespace App;

use PDO;

/**
 * SQLite への接続を管理するクラス
 * 初回接続時に schema.sql を実行してテーブルを作成する
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        // テーブルが無ければ schema.sql から作成
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetch();
        if (!$exists) {
            $pdo->exec(file_get_contents(SCHEMA_PATH));
        }

        // 古いバージョンで作った DB に、後から追加した列を足す
        self::addColumn($pdo, 'items', 'sort_order', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($pdo, 'plans', 'dest_lat', 'REAL');
        self::addColumn($pdo, 'plans', 'dest_lng', 'REAL');
        self::addColumn($pdo, 'plans', 'dest_country', "TEXT NOT NULL DEFAULT ''");

        return self::$pdo = $pdo;
    }

    private static function addColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $columns = array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
        if (!in_array($column, $columns, true)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}
