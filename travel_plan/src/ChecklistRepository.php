<?php

namespace App;

use PDO;

/**
 * checklist（持ち物）テーブルの操作
 */
class ChecklistRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    public function listByPlan(int $planId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checklist WHERE plan_id = :plan_id ORDER BY is_done ASC, id ASC');
        $stmt->execute([':plan_id' => $planId]);
        return $stmt->fetchAll();
    }

    public function add(int $planId, string $label): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO checklist (plan_id, label) VALUES (:plan_id, :label)');
        $stmt->execute([':plan_id' => $planId, ':label' => $label]);
    }

    /**
     * チェックの ON / OFF を切り替える
     */
    public function toggle(int $id, int $planId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE checklist SET is_done = 1 - is_done WHERE id = :id AND plan_id = :plan_id'
        );
        $stmt->execute([':id' => $id, ':plan_id' => $planId]);
    }

    public function delete(int $id, int $planId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM checklist WHERE id = :id AND plan_id = :plan_id');
        $stmt->execute([':id' => $id, ':plan_id' => $planId]);
    }
}
