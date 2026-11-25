<?php

namespace App;

/**
 * 行き先の地名から緯度・経度を調べる
 *   1. 都道府県名は県庁所在地に置き換える（「京都府」の中心は山の中になってしまうため）
 *   2. Open-Meteo Geocoding API（「京都」で見つからなければ「京都市」でも探す）
 *   3. それでも無ければ OpenStreetMap (Nominatim)
 * 結果は 1 日セッションにキャッシュする
 */
class Geocoder
{
    private const URL = 'https://geocoding-api.open-meteo.com/v1/search';
    private const CACHE_SECONDS = 86400;

    private const PREFECTURE_CAPITALS = [
        '北海道' => '札幌市', '青森県' => '青森市', '岩手県' => '盛岡市', '宮城県' => '仙台市',
        '秋田県' => '秋田市', '山形県' => '山形市', '福島県' => '福島市', '茨城県' => '水戸市',
        '栃木県' => '宇都宮市', '群馬県' => '前橋市', '埼玉県' => 'さいたま市', '千葉県' => '千葉市',
        '東京都' => '東京都', '神奈川県' => '横浜市', '新潟県' => '新潟市', '富山県' => '富山市',
        '石川県' => '金沢市', '福井県' => '福井市', '山梨県' => '甲府市', '長野県' => '長野市',
        '岐阜県' => '岐阜市', '静岡県' => '静岡市', '愛知県' => '名古屋市', '三重県' => '津市',
        '滋賀県' => '大津市', '京都府' => '京都市', '大阪府' => '大阪市', '兵庫県' => '神戸市',
        '奈良県' => '奈良市', '和歌山県' => '和歌山市', '鳥取県' => '鳥取市', '島根県' => '松江市',
        '岡山県' => '岡山市', '広島県' => '広島市', '山口県' => '山口市', '徳島県' => '徳島市',
        '香川県' => '高松市', '愛媛県' => '松山市', '高知県' => '高知市', '福岡県' => '福岡市',
        '佐賀県' => '佐賀市', '長崎県' => '長崎市', '熊本県' => '熊本市', '大分県' => '大分市',
        '宮崎県' => '宮崎市', '鹿児島県' => '鹿児島市', '沖縄県' => '那覇市', '沖縄' => '那覇市',
    ];

    /**
     * 見つかれば ['name', 'lat', 'lng', 'country']、見つからなければ null
     */
    public function locate(string $name): ?array
    {
        $cacheKey = 'geo_' . md5($name);
        if (array_key_exists($cacheKey, $_SESSION)) {
            return $_SESSION[$cacheKey];
        }

        $query = self::PREFECTURE_CAPITALS[$name] ?? $name;
        $result = $this->openMeteo($query);
        if ($result === null) {
            $osm = (new PlaceLocator())->search($query);
            if ($osm !== null) {
                $result = ['name' => $query, 'lat' => $osm['lat'], 'lng' => $osm['lng'], 'country' => ''];
            }
        }

        return $_SESSION[$cacheKey] = $result;
    }

    /**
     * 都道府県名か（候補から都道府県を選んだときは、座標ではなく県庁所在地を使うため）
     */
    public static function isPrefecture(string $name): bool
    {
        return isset(self::PREFECTURE_CAPITALS[$name]);
    }

    private function openMeteo(string $name): ?array
    {
        $candidates = [$name];
        if (!preg_match('/[市都]$/u', $name)) {
            $candidates[] = $name . '市';
        }
        foreach ($candidates as $query) {
            $json = fetch_json(self::URL . '?' . http_build_query([
                'name'     => $query,
                'count'    => 1,
                'language' => 'ja',
                'format'   => 'json',
            ]));
            if (!empty($json['results'][0])) {
                $r = $json['results'][0];
                return [
                    'name'    => $r['name'],
                    'lat'     => $r['latitude'],
                    'lng'     => $r['longitude'],
                    'country' => $r['country_code'] ?? '',
                ];
            }
        }
        return null;
    }
}
