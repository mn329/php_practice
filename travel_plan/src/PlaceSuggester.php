<?php

namespace App;

/**
 * 入力中の地名の候補を返す（Photon：OpenStreetMap のデータを使った無料の検索 API）
 * ※ Nominatim は利用規約で入力補完への利用が禁止されているため、補完には Photon を使う
 */
class PlaceSuggester
{
    private const URL = 'https://photon.komoot.io/api/';

    // 候補から除く種類（バス停・駐車場など）
    private const EXCLUDE = ['bus_stop', 'stop', 'parking', 'platform', 'stop_position', 'parking_entrance', 'toilets', 'traffic_signals'];

    /**
     * 行き先（市区町村・都道府県など）の候補
     * 小さな地名（「金沢」という字など）を含めると「金沢市」より先に出てしまうため、
     * まず市区町村・郡・都道府県だけで探し、見つからないときだけ小さな地名も含める
     */
    public function cities(string $query): array
    {
        // layer を複数指定するため、http_build_query ではなく文字列で組み立てる
        $base = self::URL . '?' . http_build_query(['q' => $query, 'limit' => 8])
            . '&layer=city&layer=county&layer=state&layer=district';
        return $this->fetch($base) ?: $this->fetch($base . '&layer=locality');
    }

    /**
     * 観光地・店などの候補（行き先の近くを優先）
     */
    public function spots(string $query, ?float $lat, ?float $lng): array
    {
        $params = ['q' => $query, 'limit' => 10];
        if ($lat !== null && $lng !== null) {
            $params += ['lat' => $lat, 'lon' => $lng, 'zoom' => 14, 'location_bias_scale' => 0.1];
        }
        return $this->fetch(self::URL . '?' . http_build_query($params));
    }

    private function fetch(string $url): array
    {
        $json = fetch_json($url);
        $results = [];
        foreach ($json['features'] ?? [] as $feature) {
            $p = $feature['properties'];
            if (empty($p['name']) || in_array($p['osm_value'] ?? '', self::EXCLUDE, true)) {
                continue;
            }
            // 「京都市 京都府」のように補足情報を付ける
            $sub = implode(' ', array_unique(array_filter([
                $p['city'] ?? $p['county'] ?? '',
                $p['state'] ?? '',
                ($p['countrycode'] ?? 'JP') !== 'JP' ? ($p['country'] ?? '') : '',
            ], fn($v) => $v !== '' && $v !== $p['name'])));

            $key = $p['name'] . '|' . $sub;
            if (isset($results[$key])) {
                continue; // 同じ名前・地域の重複を除く
            }
            $results[$key] = [
                'name' => $p['name'],
                'sub'  => $sub,
                'lat'  => $feature['geometry']['coordinates'][1],
                'lng'  => $feature['geometry']['coordinates'][0],
                'country' => $p['countrycode'] ?? '',
                // 都道府県・州は座標が広い範囲の中心になるので「おおよその位置」として扱う
                'approx' => ($p['type'] ?? '') === 'state' || Geocoder::isPrefecture($p['name']),
            ];
        }
        return array_slice(array_values($results), 0, 6);
    }
}
