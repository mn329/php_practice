<?php
// 持ち物チェックリストの追加・切り替え・削除（POST → 処理 → plan.php へリダイレクト）
require __DIR__ . '/../src/bootstrap.php';

use App\ChecklistRepository;

$user = require_login();
require_post();
$plan = require_own_plan($user);
$planId = (int) $plan['id'];
$back = "plan.php?id={$planId}";

$repo   = new ChecklistRepository();
$itemId = (int) ($_POST['id'] ?? 0);

switch ($_POST['action'] ?? '') {
    case 'add':
        $label = input($_POST, 'label');
        if ($label === '' || mb_strlen($label) > 30) {
            flash('error', '持ち物は 1〜30 文字で入力してください。');
            break;
        }
        $repo->add($planId, $label);
        break;

    case 'preset':
        foreach (['財布', 'スマートフォン', '充電器', '着替え', '歯ブラシ', '常備薬', '保険証'] as $label) {
            $repo->add($planId, $label);
        }
        flash('success', '定番の持ち物を追加しました。');
        break;

    case 'toggle':
        $repo->toggle($itemId, $planId);
        break;

    case 'delete':
        $repo->delete($itemId, $planId);
        break;
}

redirect($back);
