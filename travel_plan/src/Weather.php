<?php

namespace App;

/**
 * Open-Meteo（無料・API キー不要）で行き先の天気予報を取得する
 * 緯度・経度 → 最大 16 日先までの天気予報。同じ場所の結果は 30 分間セッションにキャッシュする
 */
class Weather
{
    private const FORECAST_URL  = 'https://api.open-meteo.com/v1/forecast';
    private const CACHE_SECONDS = 1800;

    // WMO 天気コード => [絵文字, 説明]
    private const CODES = [
        0  => ['☀️', '快晴'],
        1  => ['🌤️', '晴れ'],
        2  => ['⛅', '晴れ時々曇り'],
        3  => ['☁️', '曇り'],
        45 => ['🌫️', '霧'],
        48 => ['🌫️', '霧'],
        51 => ['🌦️', '弱い霧雨'],
        53 => ['🌦️', '霧雨'],
        55 => ['🌦️', '強い霧雨'],
        61 => ['🌧️', '小雨'],
        63 => ['🌧️', '雨'],
        65 => ['🌧️', '大雨'],
        66 => ['🌧️', '着氷性の雨'],
        67 => ['🌧️', '着氷性の雨'],
        71 => ['🌨️', '小雪'],
        73 => ['🌨️', '雪'],
        75 => ['❄️', '大雪'],
        77 => ['🌨️', '霧雪'],
        80 => ['🌦️', 'にわか雨'],
        81 => ['🌧️', 'にわか雨'],
        82 => ['⛈️', '激しいにわか雨'],
        85 => ['🌨️', 'にわか雪'],
        86 => ['❄️', '激しいにわか雪'],
        95 => ['⛈️', '雷雨'],
        96 => ['⛈️', '雷雨（ひょう）'],
        99 => ['⛈️', '激しい雷雨'],
    ];

    /**
     * 指定した場所の天気予報を取得
     * 成功: ['days' => [...]]
     * 失敗: ['error' => 'メッセージ']
     */
    public function forecast(float $lat, float $lng): array
    {
        $cacheKey = 'weather_' . md5(round($lat, 3) . '|' . round($lng, 3));
        $cached = $_SESSION[$cacheKey] ?? null;
        if ($cached && $cached['expires'] > time()) {
            return $cached['data'];
        }

        $json = fetch_json(self::FORECAST_URL . '?' . http_build_query([
            'latitude'      => $lat,
            'longitude'     => $lng,
            'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
            'timezone'      => 'auto',
            'forecast_days' => 16,
        ]));
        if (!isset($json['daily']['time'])) {
            return ['error' => '天気予報を取得できませんでした。時間をおいて再度お試しください。'];
        }

        $days = [];
        foreach ($json['daily']['time'] as $i => $date) {
            [$icon, $label] = self::describe((int) $json['daily']['weather_code'][$i]);
            $days[] = [
                'date'  => $date,
                'icon'  => $icon,
                'label' => $label,
                'max'   => $json['daily']['temperature_2m_max'][$i],
                'min'   => $json['daily']['temperature_2m_min'][$i],
                'rain'  => $json['daily']['precipitation_probability_max'][$i],
            ];
        }

        $data = ['days' => $days];
        $_SESSION[$cacheKey] = ['expires' => time() + self::CACHE_SECONDS, 'data' => $data];
        return $data;
    }

    public static function describe(int $code): array
    {
        return self::CODES[$code] ?? ['🌡️', '不明'];
    }
}
