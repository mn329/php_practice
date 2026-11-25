<?php

namespace App;

/**
 * 行き先周辺のおすすめスポットを Wikipedia（日本語版）の位置検索 API で探す
 * 半径 10km 以内の記事から、説明文に「神社」「美術館」などを含むものを観光地として選ぶ
 * 結果は 1 日セッションにキャッシュする
 */
class NearbySpots
{
    private const API_URL = 'https://ja.wikipedia.org/w/api.php';
    private const CACHE_SECONDS = 86400;

    // カテゴリ => 説明文に含まれるキーワード
    public const CATEGORIES = [
        'temple'  => ['label' => '⛩️ 寺社',         'pattern' => '神社|寺|神宮|天満宮|院|大社|稲荷|本山|教会'],
        'museum'  => ['label' => '🏛️ 博物館・美術館', 'pattern' => '博物館|美術館|記念館|資料館|水族館|動物園|ミュージアム'],
        'history' => ['label' => '🏯 城・史跡',       'pattern' => '城|史跡|遺跡|御所|離宮|古墳|旧跡|邸宅|山荘|茶室'],
        'nature'  => ['label' => '🌳 自然・公園',     'pattern' => '公園|庭園|山|滝|湖|川|温泉|海岸|景勝|展望|植物園|渓谷'],
        'town'    => ['label' => '🛍️ 街歩き',        'pattern' => '商店街|市場|通り|横丁|テーマパーク|観光|名所|タワー|塔'],
    ];

    // 観光地ではないもの
    private const EXCLUDE = '研究|事業者|本社|テレビ|サービスエリア|学校|大学|駅|警察|庁|局|企業|会社|放送|事件|閉校|あった|病院|道路|路線|廃寺|跡地|政党|団体|人物|選手|銀行|裁判所|事務所|線|インターチェンジ|郵便';

    /**
     * スポット一覧（有名そうな順）
     * 各要素: title, name, description, category, lat, lng, distance(km), thumbnail, url
     */
    public function search(float $lat, float $lng): array
    {
        $cacheKey = 'spots_v2_' . md5("{$lat}|{$lng}");
        $cached = $_SESSION[$cacheKey] ?? null;
        if ($cached && $cached['expires'] > time()) {
            return $cached['data'];
        }

        $json = fetch_json(self::API_URL . '?' . http_build_query([
            'action'        => 'query',
            'format'        => 'json',
            'formatversion' => 2,
            'generator'     => 'geosearch',
            'ggscoord'      => "{$lat}|{$lng}",
            'ggsradius'     => 10000,
            'ggslimit'      => 500,
            'prop'          => 'coordinates|description|info',
            'colimit'       => 500,
        ]));
        if (!isset($json['query']['pages'])) {
            return [];
        }

        $spots = [];
        foreach ($json['query']['pages'] as $page) {
            $description = $page['description'] ?? '';
            $category = self::categorize($description);
            if ($category === null || !isset($page['coordinates'][0])) {
                continue;
            }
            $point = ['lat' => $page['coordinates'][0]['lat'], 'lng' => $page['coordinates'][0]['lon']];
            $spots[] = [
                'title'       => $page['title'],
                // 「黄梅院 (京都市)」→「黄梅院」
                'name'        => preg_replace('/\s*\(.*\)$/u', '', $page['title']),
                'description' => $description,
                'category'    => $category,
                'lat'         => $point['lat'],
                'lng'         => $point['lng'],
                'distance'    => RouteOptimizer::distance(['lat' => $lat, 'lng' => $lng], $point),
                'length'      => $page['length'] ?? 0,
                'thumbnail'   => null,
                'url'         => 'https://ja.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $page['title'])),
            ];
        }

        // 記事が長い = 有名なスポットとみなして上位 30 件
        usort($spots, fn($a, $b) => $b['length'] <=> $a['length']);
        $spots = array_slice($spots, 0, 30);

        $spots = $this->attachThumbnails($spots);

        $_SESSION[$cacheKey] = ['expires' => time() + self::CACHE_SECONDS, 'data' => $spots];
        return $spots;
    }

    /**
     * 説明文からカテゴリを判定（観光地でなければ null）
     */
    private static function categorize(string $description): ?string
    {
        if ($description === '' || preg_match('/' . self::EXCLUDE . '/u', $description)) {
            return null;
        }
        // 「京都市左京区聖護院川原町にある研究機関」のように住所に地名が入るため、
        // 最後の「にある」「の」より後ろ（＝それが何か）だけで判定する
        $kind = preg_replace('/^.*(にある|の)/u', '', $description);
        foreach (self::CATEGORIES as $key => $category) {
            if (preg_match('/' . $category['pattern'] . '/u', $kind)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * サムネイル画像の URL を付ける（1 回の API 呼び出しで最大 50 件）
     */
    private function attachThumbnails(array $spots): array
    {
        if (!$spots) {
            return $spots;
        }
        $json = fetch_json(self::API_URL . '?' . http_build_query([
            'action'        => 'query',
            'format'        => 'json',
            'formatversion' => 2,
            'titles'        => implode('|', array_column($spots, 'title')),
            'prop'          => 'pageimages',
            'piprop'        => 'thumbnail',
            'pithumbsize'   => 320,
            'pilimit'       => 50,
        ]));
        $thumbs = [];
        foreach ($json['query']['pages'] ?? [] as $page) {
            if (isset($page['thumbnail']['source'])) {
                $thumbs[$page['title']] = $page['thumbnail']['source'];
            }
        }
        foreach ($spots as &$spot) {
            $spot['thumbnail'] = $thumbs[$spot['title']] ?? null;
        }
        return $spots;
    }
}
