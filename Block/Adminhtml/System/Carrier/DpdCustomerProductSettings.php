<?php

namespace DpdConnect\Shipping\Block\Adminhtml\System\Carrier;

use DpdConnect\Shipping\Helper\DPDClient;
use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class DpdCustomerProductSettings extends Field
{
    protected $_template = 'DpdConnect_Shipping::system/carrier/dpd-customer-product-settings.phtml';

    /**
     * @var DPDClient
     */
    private $DPDClient;

    /**
     * @var DpdSettings
     */
    private $dpdSettings;

    /**
     * @var array
     */
    private $settingsCache = [];

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var \Magento\Directory\Model\ResourceModel\Country\CollectionFactory
     */
    private $countryCollectionFactory;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    private $countryFactory;

    /**
     * DpdCustomerProductSettings constructor.
     *
     * @param DPDClient $DPDClient
     * @param DpdSettings $dpdSettings
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollectionFactory
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param array $data
     */
    public function __construct(DPDClient $DPDClient, DpdSettings $dpdSettings, Context $context, StoreManagerInterface $storeManager, UrlInterface $urlBuilder, \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollectionFactory, \Magento\Directory\Model\CountryFactory $countryFactory, array $data = [])
    {
        parent::__construct($context, $data);
        $this->DPDClient = $DPDClient;
        $this->dpdSettings = $dpdSettings;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->countryFactory = $countryFactory;
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        return $this->toHtml();
    }

    /**
     * @return array
     * @throws \DpdConnect\Sdk\Exceptions\DpdException
     */
    public function getCustomerProducts()
    {
        $products = $this->DPDClient->authenticate()->getProduct()->getList();

        return array_filter($products, function($product) {
            return in_array($product['type'], ['b2b', 'predict']);
        });
    }

    /**
     * @return mixed
     */
    public function getCurrentSettings()
    {
        if (0 === count($this->settingsCache)) {
            $this->settingsCache = $this->dpdSettings->getDpdCarrierCustomerProductSettings();
        }

        return $this->settingsCache;
    }

    /**
     * @param string $code
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|string
     */
    public function getSettingsValue(string $code, string $key, $default = '')
    {
        $settings = $this->getCurrentSettings();
        if (false === isset($settings[$code]) || false === isset($settings[$code][$key])) {
            return $default;
        }

        return $settings[$code][$key];
    }

    /**
     * @return bool
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isWebsiteConfig()
    {
        return 0 !== $this->storeManager->getStore()->getWebsiteId();
    }

    /**
     * @param string $shippingMethod
     * @param string $condition
     *
     * @return string
     */
    public function getExportUrl(string $shippingMethod, string $condition)
    {
        return $this->urlBuilder->getRouteUrl('dpd_shipping/tablerate/export', [
            'website' => $this->storeManager->getStore()->getWebsiteId(),
            'shipping_method' => $shippingMethod,
            'key' => $this->urlBuilder->getSecretKey('dpd_shipping', 'tablerate', 'export'),
            'conditionName' => $condition,
        ]);
    }

    /**
     * @return array
     */
    public function getCountryList()
    {
        $collection = $this->countryCollectionFactory->create()->loadByStore();

        return $collection->getData();
    }

    /**
     * @param string $countryCode
     *
     * @return string
     */
    public function getCountryName(string $countryCode)
    {
        $country = $this->countryFactory->create()->loadByCode($countryCode);

        return $country->getName();
    }
}
