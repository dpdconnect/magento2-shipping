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

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use DpdConnect\Sdk\Objects\ObjectFactory;
use DpdConnect\Sdk\Objects\MetaData;
use DpdConnect\Sdk\ClientBuilder;
use DpdConnect\Sdk\Client;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;

class DPDClient extends AbstractHelper
{
    const MODULE_NAME = 'DpdConnect_Shipping';

    /**
     * @var DpdSettings
     */
    private $dpdSettings;
    /**
     * @var Encryptor
     */
    private $crypt;
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;
    /**
     * @var ModuleListInterface
     */
    private $moduleList;
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * DPDClient constructor.
     * @param Context $context
     * @param DpdSettings $dpdSettings
     * @param Encryptor $crypt
     * @param ProductMetadataInterface $productMetadata
     * @param ModuleListInterface $moduleList
     * @param CacheInterface $cache
     */
    public function __construct(
        Context $context,
        DpdSettings $dpdSettings,
        Encryptor $crypt,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList,
        CacheInterface $cache
    ) {
        $this->dpdSettings = $dpdSettings;
        $this->crypt = $crypt;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
        $this->cache = $cache;
        parent::__construct($context);
    }

    /**
     * @return \DpdConnect\Sdk\Client
     * @throws \Exception
     */
    public function authenticate()
    {
        $url = $this->dpdSettings->getValue(DpdSettings::API_ENDPOINT);
        $pluginVersion = $this->moduleList
            ->getOne(self::MODULE_NAME)['setup_version'];

        $clientBuiler = new ClientBuilder($url, ObjectFactory::create(MetaData::class, [
            'webshopType' => $this->productMetadata->getName() . ' ' . $this->productMetadata->getEdition(),
            'webshopVersion' => $this->productMetadata->getVersion(),
            'pluginVersion' => $pluginVersion,
        ]));

        $client = $clientBuiler->buildAuthenticatedByPassword(
            $this->dpdSettings->getValue(DpdSettings::ACCOUNT_USERNAME),
            $this->crypt->decrypt($this->dpdSettings->getValue(DpdSettings::ACCOUNT_PASSWORD))
        );

        $client->getAuthentication()->setJwtToken(
            $this->cache->load('dpdconnect_jwt_token') ?: null
        );

        // This is where we save the (new) JWT token to the cache
        $client->getAuthentication()->setTokenUpdateCallback(function (string $jwtToken) use ($client) {
            $this->cache->save($jwtToken, 'dpdconnect_jwt_token');
            $client->getAuthentication()->setJwtToken($jwtToken);
        });

        return $client;
    }
}
