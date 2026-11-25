<?php

namespace App;

use PDO;

/**
 * plans テーブルの操作
 * すべてのメソッドで user_id を条件に入れ、他人のプランを操作できないようにしている
 */
class PlanRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    /**
     * キーワード検索 + ページング
     */
    public function search(int $userId, string $keyword, int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM plans
             WHERE user_id = :user_id
               AND (title LIKE :kw OR destination LIKE :kw OR memo LIKE :kw)
             ORDER BY start_date ASC, id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':kw', '%' . $keyword . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function count(int $userId, string $keyword): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM plans
             WHERE user_id = :user_id
               AND (title LIKE :kw OR destination LIKE :kw OR memo LIKE :kw)'
        );
        $stmt->execute([':user_id' => $userId, ':kw' => '%' . $keyword . '%']);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO plans (user_id, title, destination, dest_lat, dest_lng, dest_country,
                                start_date, end_date, budget, memo, cover_image)
             VALUES (:user_id, :title, :destination, :dest_lat, :dest_lng, :dest_country,
                     :start_date, :end_date, :budget, :memo, :cover_image)'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':title'       => $data['title'],
            ':destination' => $data['destination'],
            ':dest_lat'    => $data['dest_lat'],
            ':dest_lng'    => $data['dest_lng'],
            ':dest_country' => $data['dest_country'],
            ':start_date'  => $data['start_date'],
            ':end_date'    => $data['end_date'],
            ':budget'      => $data['budget'],
            ':memo'        => $data['memo'],
            ':cover_image' => $data['cover_image'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE plans
             SET title = :title, destination = :destination,
                 dest_lat = :dest_lat, dest_lng = :dest_lng, dest_country = :dest_country, start_date = :start_date,
                 end_date = :end_date, budget = :budget, memo = :memo, cover_image = :cover_image,
                 updated_at = datetime('now', 'localtime')
             WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([
            ':title'       => $data['title'],
            ':destination' => $data['destination'],
            ':dest_lat'    => $data['dest_lat'],
            ':dest_lng'    => $data['dest_lng'],
            ':dest_country' => $data['dest_country'],
            ':start_date'  => $data['start_date'],
            ':end_date'    => $data['end_date'],
            ':budget'      => $data['budget'],
            ':memo'        => $data['memo'],
            ':cover_image' => $data['cover_image'],
            ':id'          => $id,
            ':user_id'     => $userId,
        ]);
    }

    /**
     * 行き先の座標だけを保存（座標が未登録の古いプラン用）
     */
    public function setLocation(int $id, ?float $lat, ?float $lng, string $country): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plans SET dest_lat = :lat, dest_lng = :lng, dest_country = :country WHERE id = :id'
        );
        $stmt->execute([':lat' => $lat, ':lng' => $lng, ':country' => $country, ':id' => $id]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM plans WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }
}
