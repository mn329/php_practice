<?php

namespace App;

use PDO;

/**
 * items（日程）テーブルの操作
 * 呼び出し側で「プランが自分のものか」を確認してから使う
 */
class ItemRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    /**
     * 日ごとにまとめて返す [day => [item, ...]]（sort_order の順）
     */
    public function groupedByDay(int $planId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM items WHERE plan_id = :plan_id ORDER BY day ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([':plan_id' => $planId]);

        $grouped = [];
        foreach ($stmt->fetchAll() as $item) {
            $grouped[$item['day']][] = $item;
        }
        return $grouped;
    }

    /**
     * 1 日分の予定を順番どおりに返す
     */
    public function listByDay(int $planId, int $day): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM items WHERE plan_id = :plan_id AND day = :day ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':plan_id' => $planId, ':day' => $day]);
        return $stmt->fetchAll();
    }

    public function totalCost(int $planId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(cost), 0) FROM items WHERE plan_id = :plan_id');
        $stmt->execute([':plan_id' => $planId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 予定を追加する
     * 時刻があれば、その日の予定の中で時刻順になる位置に差し込む
     */
    public function create(int $planId, array $data): void
    {
        $items = $this->listByDay($planId, $data['day']);
        $position = count($items);
        if ($data['time'] !== '') {
            foreach ($items as $i => $item) {
                if ($item['time'] !== '' && $item['time'] > $data['time']) {
                    $position = $i;
                    break;
                }
            }
        }

        $this->pdo->beginTransaction();
        // 差し込む位置より後ろの予定を 1 つずつ後ろへずらす
        $ids = array_column($items, 'id');
        array_splice($ids, $position, 0, [0]); // 0 = これから追加する予定の場所
        $this->saveOrder($planId, $data['day'], array_filter($ids));

        $stmt = $this->pdo->prepare(
            'INSERT INTO items (plan_id, day, time, place, memo, cost, sort_order, lat, lng)
             VALUES (:plan_id, :day, :time, :place, :memo, :cost, :sort_order, :lat, :lng)'
        );
        $stmt->execute([
            ':plan_id'    => $planId,
            ':day'        => $data['day'],
            ':time'       => $data['time'],
            ':place'      => $data['place'],
            ':memo'       => $data['memo'],
            ':cost'       => $data['cost'],
            ':sort_order' => $position,
            ':lat'        => $data['lat'],
            ':lng'        => $data['lng'],
        ]);
        $this->pdo->commit();
    }

    /**
     * 並び順を保存する
     * $ids は表示したい順の予定 ID（キーが並び順）。日をまたいで移動した予定は day も更新される
     */
    public function saveOrder(int $planId, int $day, array $ids): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE items SET day = :day, sort_order = :sort_order WHERE id = :id AND plan_id = :plan_id'
        );
        foreach ($ids as $order => $id) {
            $stmt->execute([':day' => $day, ':sort_order' => $order, ':id' => $id, ':plan_id' => $planId]);
        }
    }

    /**
     * 複数の日の並び順をまとめて保存（ドラッグ＆ドロップ用）
     * $days = [day => [id, id, ...], ...]
     */
    public function reorder(int $planId, array $days): void
    {
        $this->pdo->beginTransaction();
        foreach ($days as $day => $ids) {
            $this->saveOrder($planId, (int) $day, array_values($ids));
        }
        $this->pdo->commit();
    }

    public function delete(int $id, int $planId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM items WHERE id = :id AND plan_id = :plan_id');
        $stmt->execute([':id' => $id, ':plan_id' => $planId]);
    }
}
