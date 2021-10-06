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
namespace DpdConnect\Shipping\Block;

use DpdConnect\Sdk\Client;
use DpdConnect\Shipping\Helper\DPDClient;
use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Framework\View\Element\Template\Context;

class GoogleMaps extends \Magento\Framework\View\Element\Template
{
    /**
     * @var DpdSettings
     */
    private $dpdSettings;

    /**
     * GoogleMaps constructor.
     *
     * @param Context     $context
     * @param DpdSettings $dpdSettings
     */
    public function __construct(
        Context $context,
        DpdSettings $dpdSettings
    ) {
        parent::__construct($context);
        $this->dpdSettings = $dpdSettings;
    }

    /**
     * @return string
     */
    public function getJsUrl()
    {
        $apiUrl = $this->dpdSettings->getValue(DpdSettings::API_ENDPOINT);
        if (null === $apiUrl || '' === trim($apiUrl)) {
            $apiUrl = Client::ENDPOINT;
        }

        return sprintf('%s/parcelshop/map/js', $apiUrl);
    }
}
