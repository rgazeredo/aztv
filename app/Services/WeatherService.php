<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WeatherService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openweathermap.org/data/2.5';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getWeather(string $city): array
    {
        $cacheKey = "weather_{$city}";

        return Cache::remember($cacheKey, 600, function () use ($city) {
            try {
                $response = Http::get("{$this->baseUrl}/weather", [
                    'q' => $city,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                    'lang' => 'pt_br',
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Erro ao obter dados do clima');
                }

                $data = $response->json();

                return [
                    'temperature' => round($data['main']['temp']),
                    'feels_like' => round($data['main']['feels_like']),
                    'description' => ucfirst($data['weather'][0]['description']),
                    'humidity' => $data['main']['humidity'],
                    'pressure' => $data['main']['pressure'],
                    'wind_speed' => round($data['wind']['speed'] * 3.6, 1), // Convert m/s to km/h
                    'visibility' => isset($data['visibility']) ? round($data['visibility'] / 1000, 1) : null,
                    'uv_index' => $this->getUVIndex($data['coord']['lat'], $data['coord']['lon']),
                    'icon' => $this->getWeatherIcon($data['weather'][0]['icon']),
                    'city_name' => $data['name'],
                    'country' => $data['sys']['country'],
                ];
            } catch (\Exception $e) {
                return $this->getFallbackWeather($city);
            }
        });
    }

    public function getForecast(string $city, int $days = 5): array
    {
        $cacheKey = "forecast_{$city}_{$days}";

        return Cache::remember($cacheKey, 1800, function () use ($city, $days) {
            try {
                $response = Http::get("{$this->baseUrl}/forecast", [
                    'q' => $city,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                    'lang' => 'pt_br',
                    'cnt' => $days * 8, // 8 forecasts per day (every 3 hours)
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Erro ao obter previsão do tempo');
                }

                $data = $response->json();
                $forecast = [];

                foreach ($data['list'] as $item) {
                    $date = date('Y-m-d', $item['dt']);

                    if (!isset($forecast[$date])) {
                        $forecast[$date] = [
                            'date' => $date,
                            'temp_min' => $item['main']['temp_min'],
                            'temp_max' => $item['main']['temp_max'],
                            'description' => $item['weather'][0]['description'],
                            'icon' => $this->getWeatherIcon($item['weather'][0]['icon']),
                            'humidity' => $item['main']['humidity'],
                            'wind_speed' => round($item['wind']['speed'] * 3.6, 1),
                        ];
                    } else {
                        $forecast[$date]['temp_min'] = min($forecast[$date]['temp_min'], $item['main']['temp_min']);
                        $forecast[$date]['temp_max'] = max($forecast[$date]['temp_max'], $item['main']['temp_max']);
                    }
                }

                return array_values($forecast);
            } catch (\Exception $e) {
                return $this->getFallbackForecast($city, $days);
            }
        });
    }

    public function testConnection(string $city): bool
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/weather", [
                'q' => $city,
                'appid' => $this->apiKey,
                'units' => 'metric',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getUVIndex(float $lat, float $lon): ?float
    {
        try {
            $response = Http::get("{$this->baseUrl}/uvi", [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['value'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignore UV index errors
        }

        return null;
    }

    private function getWeatherIcon(string $iconCode): string
    {
        $iconMap = [
            '01d' => '☀️', // clear sky day
            '01n' => '🌙', // clear sky night
            '02d' => '⛅', // few clouds day
            '02n' => '☁️', // few clouds night
            '03d' => '☁️', // scattered clouds
            '03n' => '☁️',
            '04d' => '☁️', // broken clouds
            '04n' => '☁️',
            '09d' => '🌧️', // shower rain
            '09n' => '🌧️',
            '10d' => '🌦️', // rain day
            '10n' => '🌧️', // rain night
            '11d' => '⛈️', // thunderstorm
            '11n' => '⛈️',
            '13d' => '❄️', // snow
            '13n' => '❄️',
            '50d' => '🌫️', // mist
            '50n' => '🌫️',
        ];

        return $iconMap[$iconCode] ?? '🌤️';
    }

    private function getFallbackWeather(string $city): array
    {
        return [
            'temperature' => 22,
            'feels_like' => 24,
            'description' => 'Dados indisponíveis',
            'humidity' => 60,
            'pressure' => 1013,
            'wind_speed' => 10,
            'visibility' => 10.0,
            'uv_index' => null,
            'icon' => '🌤️',
            'city_name' => $city,
            'country' => 'BR',
        ];
    }

    private function getFallbackForecast(string $city, int $days): array
    {
        $forecast = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $forecast[] = [
                'date' => $date,
                'temp_min' => 18 + rand(-5, 5),
                'temp_max' => 28 + rand(-5, 5),
                'description' => 'Dados indisponíveis',
                'icon' => '🌤️',
                'humidity' => 60,
                'wind_speed' => 10,
            ];
        }
        return $forecast;
    }
}