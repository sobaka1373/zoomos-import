<?php

namespace entity;

class ApiRequest
{
    public CONST PRICE_LIST_URL = 'https://api.zoomos.by/pricelist';

    public CONST PRODUCT_URL = 'https://api.zoomos.by/item/';

    public CONST OFFSET = '&offset=';

    public CONST LIMIT = '&limit=';

    public CONST KEY = '?key=';

    /**
     * @throws \JsonException
     */
    public static function priceRequest(string $apiKeyValue, int $offsetValue, int $limitValue, string $params = '&competitorInfo=0&deliveryInfo=0'): array
    {
        $ch = curl_init(self::PRICE_LIST_URL . self::KEY . $apiKeyValue . self::OFFSET . $offsetValue . self::LIMIT . $limitValue . $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \JsonException
     */
    public static function productRequest(string $apiKeyValue, int $productId): array
    {
        $ch = curl_init(self::PRODUCT_URL . $productId . self::KEY . $apiKeyValue);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \JsonException
     */
    public static function getAllProductPriceRequest(string $apiKeyValue, string $params = '&competitorInfo=0&deliveryInfo=0'): array
    {
        $ch = curl_init(self::PRICE_LIST_URL . self::KEY . $apiKeyValue . $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, false, 512, JSON_THROW_ON_ERROR);
    }
}