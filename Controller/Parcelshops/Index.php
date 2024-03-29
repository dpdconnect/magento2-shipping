<?php
/**
 * This file is part of the Magento 2 Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2019  DPD Nederland B.V.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace DpdConnect\Shipping\Controller\Parcelshops;

use DpdConnect\Shipping\Helper\DPDClient;
use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\Order;
use DpdConnect\Shipping\Helper\Data;
use DpdConnect\Shipping\Helper\Services\DPDPickupService;
use Magento\Framework\View\Asset\Repository;

class Index extends \Magento\Framework\App\Action\Action
{
    private $data;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    private $assetRepo;
    /**
     * @var DPDClient
     */
    private $dpdClient;
    /**
     * @var DpdSettings
     */
    private $dpdSettings;

    public function __construct(
        Context $context,
        Data $data,
        JsonFactory $resultJsonFactory,
        DPDClient $dpdClient,
        DpdSettings $dpdSettings,
        Repository $assetRepo
    ) {
        parent::__construct($context);

        $this->data = $data;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->assetRepo = $assetRepo;
        $this->dpdClient = $dpdClient;
        $this->dpdSettings = $dpdSettings;
    }

    /**
     * @deprecated This method has been moved to Services\GoogleMaps and will be deleted from this location in a future release
     */
    public function getGoogleMapsCenter($postcode, $countryId)
    {
        try {
            $apiKey = $this->data->getGoogleServerApiKey();
            $addressToInsert = 'country:' . $countryId . '|postal_code:' . $postcode;
            $url = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $apiKey . '&components=' . urlencode($addressToInsert) . '&sensor=false';
            $source = file_get_contents($url);
            $obj = json_decode($source);
            $LATITUDE = $obj->results[0]->geometry->location->lat;
            $LONGITUDE = $obj->results[0]->geometry->location->lng;
        } catch (\Exception $ex) {
            // echo $ex->getMessage();
            return null;
        }
        return [$LATITUDE, $LONGITUDE];
    }

    /**
     * @deprecated This method has been moved to Services\GoogleMaps and will be deleted from this location in a future release
     */
    public function getGoogleMapsCenterByQuery($query)
    {
        try {
            $apiKey = $this->data->getGoogleServerApiKey();
            $url = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $apiKey . '&address=' . urlencode($query) . '&sensor=false';
            $source = file_get_contents($url);
            $obj = json_decode($source);
            $LATITUDE = $obj->results[0]->geometry->location->lat;
            $LONGITUDE = $obj->results[0]->geometry->location->lng;
        } catch (\Exception $ex) {
            return null;
        }
        return [$LATITUDE, $LONGITUDE];
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
        //$this->_view->loadLayout();
        //$this->_view->renderLayout();

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();
        $resultData = array();

        $post = $this->getRequest()->getPostValue();

        if (!isset($post['query']) && (!isset($post['postcode']) || !isset($post['countryId']))) {
            $resultData['success'] = false;
            $resultData['error_message'] = __('No address found');

            return $result->setData($resultData);
        }

        $googleMaps = null;

        if (isset($post['query'])) {
            $googleMaps = $this->getGoogleMapsCenterByQuery($post['query']);
        }

        if (isset($post['postcode']) && isset($post['countryId'])) {
            $googleMaps = $this->getGoogleMapsCenter($post['postcode'], $post['countryId']);
        }

        if ($googleMaps == null) {
            $resultData['success'] = false;
            $resultData['error_message'] = __('No address found');
            return $result->setData($resultData);
        }

        $coordinates = [
            'latitude' => $googleMaps[0],
            'longitude' => $googleMaps[1],
            'countryIso' => $post['countryId'],
            'limit' => $this->dpdSettings->getValue(DpdSettings::PARCELSHOP_MAPS_SHOPS)
        ];

        $parcelShops = $this->dpdClient->authenticate()->getParcelshop()->getList($coordinates);

        $params = array('_secure' => $this->getRequest()->isSecure());

        $resultData['success'] = true;
        $resultData['center_lat'] = $coordinates['longitude'];
        $resultData['center_long'] = $coordinates['latitude'];

        $resultData["gmapsIcon"] = $this->assetRepo->getUrlWithParams('DpdConnect_Shipping::images/icon_parcelshop.png', $params);
        $resultData["gmapsIconShadow"] = $this->assetRepo->getUrlWithParams('DpdConnect_Shipping::images/icon_parcelshop_shadow.png', $params);

        foreach ($parcelShops as $shop) {
            $parcelShop = array();
            $parcelShop['parcelShopId'] = $shop['parcelShopId'];
            $parcelShop['company'] = trim($shop['company']);
            $parcelShop['houseno'] = $shop['street'] . " " . $shop['houseNo'];
            $parcelShop['zipcode'] = $shop['zipCode'];
            $parcelShop['city'] = $shop['city'];
            $parcelShop['country'] = $shop['isoAlpha2'];
            $parcelShop['gmapsCenterlat'] = $shop['latitude'];
            $parcelShop['gmapsCenterlng'] = $shop['longitude'];
            $parcelShop['special'] = false;

            $parcelShop['extra_info'] = json_encode(array_filter(array(
                'Opening hours' => (isset($shop['openingHours']) && $shop['openingHours'] != "" ? json_encode($shop['openingHours']) : ''),
                'Telephone' => (isset($shop['phone']) && $shop['phone'] != "" ? $shop['phone'] : ''),
                'Website' => (isset($shop['homepage']) && $shop['homepage'] != "" ? '<a href="' . 'http://' . $shop['homepage'] . '" target="_blank">' . $shop['homepage'] . '</a>' : ''),
                )));

            $parcelShop['gmapsMarkerContent'] = $this->_getMarkerHtml($shop, false);

            $resultData['parcelshops'][$shop['parcelShopId']] = $parcelShop;
        }

        return $result->setData($resultData);
    }

    /**
     * Gets the opening hours in HTML format.
     *
     * @param $openMorning
     * @param $closeMorning
     * @param $openAfternoon
     * @param $closeAfternoon
     * @param $weekday
     *
     * @return string
     */
    protected function _getOpeningHoursHtml($openMorning, $closeMorning, $openAfternoon, $closeAfternoon, $weekday)
    {
        $openingHoursMorning = $openMorning . ' - ' . $closeMorning;
        $openingHoursAfternoon = $openAfternoon . ' - ' . $closeAfternoon;

        if ($openingHoursMorning === '00:00 - 00:00') {
            $openingHoursMorning = __('Closed');
        }

        if ($openingHoursAfternoon == '00:00 - 00:00') {
            $openingHoursAfternoon = __('Closed');
        }

        $html = '
            <tr>
                <td style="padding: 3px; border: none;"></td>
                <td style="padding: 3px; width: 33%;"><strong>' . __(strtolower($weekday)) . '</strong></td>
                <td style="padding: 3px; width: 33%; text-align: center;">' . $openingHoursMorning . '</td>
                <td style="padding: 3px; width: 33%; text-align: center;">' . $openingHoursAfternoon . '</td>
            </tr>';

        return $html;
    }

    /**
     * Gets html for the marker info bubbles.
     *
     * @param $shop
     * @param $special
     * @return string
     */
    protected function _getMarkerHtml($shop, $special)
    {
        $image = $this->assetRepo->getUrlWithParams('DpdConnect_Shipping::images/dpd_parcelshop_logo.png', array('_secure' => $this->getRequest()->isSecure()));
        $routeIcon = $this->assetRepo->getUrlWithParams('DpdConnect_Shipping::images/icon_route.png', array('_secure' => $this->getRequest()->isSecure()));

        $html = '
            <div class="content">
                <table style="min-width: 380px" cellpadding="3" cellspacing="3">
                    <tbody>
                        <tr>
                            <td style="padding: 3px; padding-right: 15px;" rowspan="2">
                                <img class="parcelshoplogo bubble" style="width: 80px; height: 80px;" src="' . $image . '" alt="DPD Parcelshop logo"/>
                            </td>
                            <td style="padding: 3px; padding-top: 6px; width: 100%;" colspan="3">
                                <strong>' . ($special ? $shop['getParcelshopPudoName']() : $shop['company']) . '</strong><br />
                                ' . ($special ? $shop['getData']('parcelshop_address_1') : $shop['street'] . " " . $shop['houseNo']) . '<br />
                                ' . ($special ? $shop['getParcelshopPostCode']() . ' ' . $shop['getParcelshopTown']() : $shop['zipCode'] . ' ' . $shop['city']) . '
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 3px; padding-top: 6px; width: 100%;" colspan="3">
                                <strong>' . __('Opening hours') . '</strong>
                            </td>
                        </tr>';

        if (!$special && isset($shop['openingHours']) && $shop['openingHours'] != "") {
            foreach ($shop['openingHours'] as $openinghours) {
                $html .= $this->_getOpeningHoursHtml(
                    $openinghours['openMorning'],
                    $openinghours['closeMorning'],
                    $openinghours['openAfternoon'],
                    $openinghours['closeAfternoon'],
                    $openinghours['weekday']
                );
            }
        } else {
            foreach (json_decode($shop['getParcelshopOpeninghours']()) as $openinghours) {
                $html .= $this->_getOpeningHoursHtml(
                    $openinghours['openMorning'],
                    $openinghours['closeMorning'],
                    $openinghours['openAfternoon'],
                    $openinghours['closeAfternoon'],
                    $openinghours['weekday']
                );
            }
        }

        $html .= '
                        <tr>
                            <td style="padding: 3px; border: none;"></td>
                            <td style="padding: 3px; width: 100%;" colspan="3">
                                <a class="parcelshoplink" id="' . ($special ? $shop['getParcelshopDelicomId']() : $shop['parcelShopId']) . '" href="#">' . __('Select Parcelshop') . '</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>';

        return $html;
    }
}
