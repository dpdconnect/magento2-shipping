<?php

namespace DpdConnect\Shipping\Services;

use DpdConnect\Shipping\Helper\Data;
use Magento\Framework\DB\LoggerInterface;

class GoogleMaps
{
    public const GOOGLE_MAPS_API_BASE = 'https://maps.googleapis.com/maps/api/';

    /**
     * @var Data
     */
    private $data;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Data $data, LoggerInterface $logger)
    {
        $this->data = $data;
        $this->logger = $logger;
    }

    /**
     * @param string $postcode
     * @param string $countryId
     * @return array|null
     */
    public function getGoogleMapsCenter($postcode, $countryId)
    {
        try {
            $addressToInsert = 'country:' . $countryId . '|postal_code:' . $postcode;
            $result = $this->doGeocodeRequest([
                'components' => $addressToInsert,
                'sensor' => 'false'
            ]);
            $latitude = $result->results[0]->geometry->location->lat;
            $longitude = $result->results[0]->geometry->location->lng;

            return [$latitude, $longitude];

        } catch (\Exception $ex) {
            $this->logger->log('[DpdConnect_Shipping::GoogleMaps::getGoogleMapsCenterByQuery] ' . $ex->getMessage());
            return null;
        }
    }

    /**
     * @param string $query
     * @return array|null
     */
    public function getGoogleMapsCenterByQuery($query)
    {
        try {
            $result = $this->doGeocodeRequest([
                'address' => $query,
                'sensor' => 'false'
            ]);

            $latitude = $result->results[0]->geometry->location->lat;
            $longitude = $result->results[0]->geometry->location->lng;

            return [$latitude, $longitude];

        } catch (\Exception $ex) {
            $this->logger->log('[DpdConnect_Shipping::GoogleMaps::getGoogleMapsCenterByQuery] ' . $ex->getMessage());
            return null;
        }
    }

    /**
     * @param array $fields
     * @return mixed
     */
    protected function doGeocodeRequest($fields)
    {
        $apiKey = $this->data->getGoogleServerApiKey();
        $url = static::GOOGLE_MAPS_API_BASE . 'geocode/json?key=' . $apiKey . '&' . http_build_query($fields);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }
}
