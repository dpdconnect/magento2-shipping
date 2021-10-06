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
     * DpdCustomerProductSettings constructor.
     *
     * @param DPDClient $DPDClient
     * @param DpdSettings $dpdSettings
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param array $data
     */
    public function __construct(DPDClient $DPDClient, DpdSettings $dpdSettings,  Context $context, StoreManagerInterface $storeManager, UrlInterface $urlBuilder, array $data = [])
    {
        parent::__construct($context, $data);
        $this->DPDClient = $DPDClient;
        $this->dpdSettings = $dpdSettings;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
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
        return $this->DPDClient->authenticate()->getProduct()->getList();
    }

    /**
     * @return mixed
     */
    public function getCurrentSettings()
    {
        if(0 === count($this->settingsCache)) {
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
            'key' => $this->urlBuilder->getSecretKey('dpd_shipping','tablerate','export'),
            'conditionName' => $condition,
        ]);
    }
}
