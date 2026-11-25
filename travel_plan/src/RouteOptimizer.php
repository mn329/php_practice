<?php

namespace App;

/**
 * 1 日の観光ルートを「移動距離が短くなる順」に並び替える
 *
 *   1. 最近傍法 : 今いる場所から一番近いスポットへ順に進む
 *   2. 2-opt 法 : ルートの一部を逆順にして、距離が縮むなら採用（改善が無くなるまで繰り返す）
 *
 * 最初のスポット（駅・ホテルなど）は出発地点として固定する
 * 距離は緯度経度から求めた直線距離（実際の道のりではない）
 */
class RouteOptimizer
{
    /**
     * 2 地点間の距離（km）をハバーサイン公式で計算
     */
    public static function distance(array $a, array $b): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);
        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;
        return 2 * $earthRadius * asin(sqrt($h));
    }

    /**
     * 順番どおりに回ったときの合計距離（km）。座標の無い予定は飛ばす
     */
    public static function totalDistance(array $items): float
    {
        $points = array_values(array_filter($items, fn($i) => $i['lat'] !== null));
        $total = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $total += self::distance($points[$i - 1], $points[$i]);
        }
        return $total;
    }

    /**
     * 並び替えた予定の配列を返す
     * 座標の無い予定は、順番を保ったまま最後に回す
     */
    public static function optimize(array $items): array
    {
        $withPoint    = array_values(array_filter($items, fn($i) => $i['lat'] !== null));
        $withoutPoint = array_values(array_filter($items, fn($i) => $i['lat'] === null));

        if (count($withPoint) < 3) {
            return array_merge($withPoint, $withoutPoint);
        }

        $route = self::twoOpt(self::nearestNeighbor($withPoint));
        return array_merge($route, $withoutPoint);
    }

    /**
     * 最近傍法
     */
    private static function nearestNeighbor(array $points): array
    {
        $route = [array_shift($points)];
        while ($points) {
            $current = end($route);
            $nearestKey = null;
            $nearestDistance = INF;
            foreach ($points as $key => $point) {
                $d = self::distance($current, $point);
                if ($d < $nearestDistance) {
                    $nearestDistance = $d;
                    $nearestKey = $key;
                }
            }
            $route[] = $points[$nearestKey];
            unset($points[$nearestKey]);
        }
        return $route;
    }

    /**
     * 2-opt 法（出発地点 = 0 番目は動かさない）
     */
    private static function twoOpt(array $route): array
    {
        $n = count($route);
        $improved = true;
        while ($improved) {
            $improved = false;
            for ($i = 1; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $candidate = array_merge(
                        array_slice($route, 0, $i),
                        array_reverse(array_slice($route, $i, $j - $i + 1)),
                        array_slice($route, $j + 1)
                    );
                    if (self::totalDistance($candidate) + 1e-9 < self::totalDistance($route)) {
                        $route = $candidate;
                        $improved = true;
                    }
                }
            }
        }
        return $route;
    }
}
