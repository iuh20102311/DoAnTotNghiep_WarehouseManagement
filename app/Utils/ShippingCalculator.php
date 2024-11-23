<?php

namespace App\Utils;

class ShippingCalculator
{
    private $mapboxToken;
    private $companyAddress;

    public function __construct()
    {
        $this->mapboxToken = $_ENV['MAPBOX_TOKEN'];
        $this->companyAddress = $_ENV['COMPANY_ADDRESS'];
    }

    /**
     * Tính phí ship dựa trên địa chỉ đầy đủ
     */
    public function calculateShippingFee(string $address, string $ward, string $district, string $city): array
    {
        $fullAddress = "$address, $ward, $district, $city";
        try {
            $distance = $this->getDistance($fullAddress);

            if ($distance === null) {
                throw new \Exception('Không thể tính khoảng cách với địa chỉ này');
            }

            $isInnerCity = $distance <= 20; // 20km là ranh giới nội/ngoại thành
            $shippingFee = $isInnerCity
                ? 15000 + ($distance * 5000)   // Nội thành: 15k + 5k/km
                : 30000 + ($distance * 10000);  // Ngoại thành: 30k + 10k/km

            return [
                'distance' => round($distance, 2),
                'isInnerCity' => $isInnerCity,
                'shippingFee' => (int)$shippingFee
            ];

        } catch (\Exception $e) {
            throw new \Exception('Lỗi khi tính phí ship: ' . $e->getMessage());
        }
    }

    /**
     * Lấy khoảng cách từ Mapbox API
     */
    private function getDistance(string $deliveryAddress): ?float
    {
        // Convert địa chỉ sang tọa độ
        $fromCoords = $this->geocodeAddress($this->companyAddress);
        $toCoords = $this->geocodeAddress($deliveryAddress);

        if (!$fromCoords || !$toCoords) {
            return null;
        }

        // Gọi Mapbox Directions API
        $url = sprintf(
            'https://api.mapbox.com/directions/v5/mapbox/driving/%f,%f;%f,%f?access_token=%s',
            $fromCoords['lng'],
            $fromCoords['lat'],
            $toCoords['lng'],
            $toCoords['lat'],
            $this->mapboxToken
        );

        $response = $this->makeRequest($url);

        if (!$response || !isset($response['routes'][0]['distance'])) {
            return null;
        }

        return $response['routes'][0]['distance'] / 1000; // Chuyển từ mét sang km
    }

    private function geocodeAddress(string $address): ?array
    {
        $url = sprintf(
            'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json?access_token=%s&country=vn&limit=1',
            urlencode($address),
            $this->mapboxToken
        );

        $response = $this->makeRequest($url);

        if (!$response || empty($response['features'])) {
            return null;
        }

        $coordinates = $response['features'][0]['center'];
        return [
            'lng' => $coordinates[0],
            'lat' => $coordinates[1]
        ];
    }

    private function makeRequest(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return null;
        }

        return json_decode($response, true);
    }
}