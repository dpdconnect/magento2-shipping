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
namespace DpdConnect\Shipping\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use DpdConnect\Sdk\ClientBuilder;
use DpdConnect\Sdk\Client;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\Encryptor;

class DPDClient extends AbstractHelper
{
    /**
     * @var DpdSettings
     */
    private $dpdSettings;
    /**
     * @var Encryptor
     */
    private $crypt;

    /**
     * DPDClient constructor.
     * @param Context $context
     * @param DpdSettings $dpdSettings
     * @param Encryptor $crypt
     */
    public function __construct(
        Context $context,
        DpdSettings $dpdSettings,
        Encryptor $crypt
    ) {
        $this->dpdSettings = $dpdSettings;
        $this->crypt = $crypt;
        parent::__construct($context);
    }

    /**
     * @return \DpdConnect\Sdk\Client
     * @throws \Exception
     */
    public function authenticate()
    {
        // TODO: Add URL to the ClientBuilder
        $url = Client::ENDPOINT;
        $clientBuiler = new ClientBuilder($url);

        return $clientBuiler->buildAuthenticatedByPassword(
            $this->dpdSettings->getValue(DpdSettings::ACCOUNT_USERNAME),
            $this->crypt->decrypt($this->dpdSettings->getValue(DpdSettings::ACCOUNT_PASSWORD))
        );
    }
}
