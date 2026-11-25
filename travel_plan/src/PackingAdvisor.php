<?php

namespace App;

use Carbon\Carbon;

/**
 * 天気予報・季節・日数・日程から、持ち物のおすすめを考える
 */
class PackingAdvisor
{
    /**
     * @param array      $plan        プラン
     * @param array      $weatherDays 天気予報（Weather::forecast() の days。取得できなければ空）
     * @param string     $country     行き先の国コード（JP など。不明なら空）
     * @param array      $itemsByDay  日ごとの予定
     * @param array      $checklist   すでにある持ち物
     * @return array [['label' => 持ち物, 'reason' => 理由], ...]
     */
    public function suggest(array $plan, array $weatherDays, string $country, array $itemsByDay, array $checklist): array
    {
        $suggestions = [];
        $add = function (string $label, string $reason) use (&$suggestions) {
            $suggestions[$label] ??= $reason;
        };

        $start  = Carbon::parse($plan['start_date']);
        $nights = (int) $start->diffInDays(Carbon::parse($plan['end_date']));

        // ---- 旅行中の天気予報 ----
        $tripDays = array_filter(
            $weatherDays,
            fn($d) => $d['date'] >= $plan['start_date'] && $d['date'] <= $plan['end_date']
        );

        if ($tripDays) {
            $maxRain = max(array_column($tripDays, 'rain'));
            $minTemp = min(array_column($tripDays, 'min'));
            $maxTemp = max(array_column($tripDays, 'max'));

            if ($maxRain >= 50) {
                $add('折りたたみ傘', "降水確率 {$maxRain}% の日があります");
            }
            if ($maxRain >= 80) {
                $add('レインコート', '雨が強くなりそうです');
            }
            if ($minTemp <= 5) {
                $add('手袋・マフラー', '最低気温 ' . round($minTemp) . '℃ の予報です');
                $add('カイロ', '最低気温 ' . round($minTemp) . '℃ の予報です');
            } elseif ($minTemp <= 12) {
                $add('上着', '朝晩は ' . round($minTemp) . '℃ まで下がります');
            }
            if ($maxTemp >= 28) {
                $add('日焼け止め', '最高気温 ' . round($maxTemp) . '℃ の予報です');
                $add('帽子', '最高気温 ' . round($maxTemp) . '℃ の予報です');
            }
            if ($maxTemp >= 32) {
                $add('水筒', '熱中症対策に');
            }
        } else {
            // 予報が出ていない時期は季節で判断
            $month = (int) $start->format('n');
            if (in_array($month, [12, 1, 2], true)) {
                $add('上着', '冬の旅行です');
                $add('カイロ', '冬の旅行です');
            } elseif (in_array($month, [7, 8, 9], true)) {
                $add('日焼け止め', '夏の旅行です');
                $add('帽子', '夏の旅行です');
            }
            if (in_array($month, [6, 7], true)) {
                $add('折りたたみ傘', '梅雨の時期です');
            }
        }

        // ---- 日数 ----
        if ($nights >= 1) {
            $add('着替え', "{$nights}泊分");
            $add('歯ブラシ', '宿泊があります');
        }
        if ($nights >= 3) {
            $add('洗濯ネット・洗剤', "{$nights}泊の長めの旅行です");
        }

        // ---- 海外 ----
        if ($country !== '' && $country !== 'JP') {
            $add('パスポート', '海外旅行です');
            $add('変換プラグ', '海外旅行です');
        }

        // ---- 日程 ----
        $maxSpots = max(array_map('count', $itemsByDay ?: [[]]));
        if ($maxSpots >= 4) {
            $add('モバイルバッテリー', "1 日に {$maxSpots} か所まわる日があります");
            $add('歩きやすい靴', "1 日に {$maxSpots} か所まわる日があります");
        }

        // すでにリストにあるものは除く（「着替え（3日分）」があれば「着替え」は出さない）
        $existing = array_column($checklist, 'label');
        $result = [];
        foreach ($suggestions as $label => $reason) {
            $keyword = explode('・', $label)[0];
            foreach ($existing as $e) {
                if (mb_strpos($e, $keyword) !== false) {
                    continue 2;
                }
            }
            $result[] = ['label' => $label, 'reason' => $reason];
        }
        return $result;
    }
}
