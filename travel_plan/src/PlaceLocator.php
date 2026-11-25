<?php

namespace App;

/**
 * OpenStreetMap の Nominatim（無料・API キー不要）で
 * 観光地や店名から緯度・経度を調べる
 * ※ 利用規約により 1 秒 1 回以下・User-Agent 必須。日程の登録時だけ呼び出す
 */
class PlaceLocator
{
    private const SEARCH_URL = 'https://nominatim.openstreetmap.org/search';

    // 行き先からこれ以上離れた場所は別の同名スポットとみなす（km）
    private const MAX_DISTANCE = 50;

    /**
     * 見つかれば ['lat' => float, 'lng' => float]、見つからなければ null
     * まず「スポット名 + 行き先」で探し、無ければスポット名だけで探す
     * $center（行き先の座標）があれば、そこから遠すぎる結果は使わない
     */
    public function locate(string $place, string $destination, ?array $center = null): ?array
    {
        $isNear = fn(?array $r) => $r !== null
            && ($center === null || RouteOptimizer::distance($center, $r) <= self::MAX_DISTANCE);

        $result = $this->search($place . ' ' . $destination);
        if (!$isNear($result)) {
            usleep(1_100_000); // 1 秒以上空ける
            $result = $this->search($place);
        }
        return $isNear($result) ? $result : null;
    }

    /**
     * キーワード 1 つで検索
     */
    public function search(string $query): ?array
    {
        $json = fetch_json(self::SEARCH_URL . '?' . http_build_query([
            'q'      => $query,
            'format' => 'jsonv2',
            'limit'  => 1,
        ]));
        if (empty($json[0]['lat'])) {
            return null;
        }
        return ['lat' => (float) $json[0]['lat'], 'lng' => (float) $json[0]['lon']];
    }
}
