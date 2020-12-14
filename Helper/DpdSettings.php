<?php

namespace DpdConnect\Shipping\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;

class DpdSettings extends AbstractHelper
{
    const ACCOUNT_USERNAME = 'dpdshipping/account_settings/username';
    const ACCOUNT_PASSWORD = 'dpdshipping/account_settings/password';
    const ACCOUNT_DEPOT = 'dpdshipping/account_settings/depot';
    const ACCOUNT_PRINT_FORMAT = 'dpdshipping/account_settings/print_format';

    const SHIPPING_ORIGIN_NAME1 = 'dpdshipping/shipping_origin/name1';
    const SHIPPING_ORIGIN_STREET = 'dpdshipping/shipping_origin/street';
    const SHIPPING_ORIGIN_HOUSE_NUMBER = 'dpdshipping/shipping_origin/house_number';
    const SHIPPING_ORIGIN_ZIP_CODE = 'dpdshipping/shipping_origin/zip_code';
    const SHIPPING_ORIGIN_CITY = 'dpdshipping/shipping_origin/city';
    const SHIPPING_ORIGIN_COUNTRY = 'dpdshipping/shipping_origin/country';

    const STORE_INFORMATION_NAME = 'dpdshipping/store_information/name';
    const STORE_INFORMATION_STREET = 'dpdshipping/store_information/street';
    const STORE_INFORMATION_HOUSE_NUMBER = 'dpdshipping/store_information/house_number';
    const STORE_INFORMATION_ZIP_CODE = 'dpdshipping/store_information/zip_code';
    const STORE_INFORMATION_CITY = 'dpdshipping/store_information/city';
    const STORE_INFORMATION_COUNTRY = 'dpdshipping/store_information/country';
    const STORE_INFORMATION_PHONE = 'dpdshipping/store_information/phone';
    const STORE_INFORMATION_EMAIL = 'dpdshipping/store_information/email';
    const STORE_INFORMATION_VAT_NUMBER = 'dpdshipping/store_information/vat_number';
    const STORE_INFORMATION_EORI = 'dpdshipping/store_information/eori';
    const STORE_INFORMATION_SPRN = 'dpdshipping/store_information/sprn';
    const STORE_INFORMATION_CUSTOMS_TERMS = 'dpdshipping/store_information/customs_terms';

    const ADVANCED_SEND_CONFIRM_EMAIL = 'dpdshipping/advanced/send_confirm_email';
    const ADVANCED_INCLUDE_RETURN_LABEL = 'dpdshipping/advanced/include_return_label';
    const ADVANCED_PICQER_MODE = 'dpdshipping/advanced/picqer_mode';
    const ADVANCED_SAVE_LABEL_FILE = 'dpdshipping/advanced/save_label_file';
    const ADVANCED_LABEL_PATH = 'dpdshipping/advanced/label_path';
    const ADVANCED_PRINT_PHONE_NUMBER = 'dpdshipping/advanced/print_phone_number';
    const ADVANCED_PRINT_ORDER_ID = 'dpdshipping/advanced/print_order_id';
    const ADVANCED_CUSTOMS_CONTENT_TYPE = 'dpdshipping/advanced/customs_content_type';

    const PRODUCT_ATTRIBUTE_HS_CODE = 'dpdshipping/product_attribute/hs_code';
    const PRODUCT_ATTRIBUTE_LENGTH = 'dpdshipping/product_attribute/product_length';
    const PRODUCT_ATTRIBUTE_WIDTH = 'dpdshipping/product_attribute/product_width';
    const PRODUCT_ATTRIBUTE_HEIGHT = 'dpdshipping/product_attribute/product_height';
    const PRODUCT_ATTRIBUTE_DEPTH = 'dpdshipping/product_attribute/product_depth';

    const API_ASYNC_ENABLED = 'dpdshipping/api/async_enabled';
    const API_ASYNC_THRESHOLD = 'dpdshipping/api/async_threshold';

    const PARCELSHOP_MAPS_CLIENT_KEY = 'carriers/dpdpickup/google_maps_api_client';
    const PARCELSHOP_MAPS_SERVER_KEY = 'carriers/dpdpickup/google_maps_api_server';
    const PARCELSHOP_MAPS_WIDTH = 'carriers/dpdpickup/map_width';
    const PARCELSHOP_MAPS_HEIGHT = 'carriers/dpdpickup/map_height';
    const PARCELSHOP_MAPS_SHOPS = 'carriers/dpdpickup/map_max_shops';

    public function getValue($path, $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        return $this->scopeConfig->getValue($path, $scopeType, $scopeCode);
    }

    public function isSetFlag($path, $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag($path, $scopeType, $scopeCode);
    }

    /**
     * Get carrier title based on Carrier Code
     *
     * @param string $carrierCode
     * @param string $scopeType
     * @param null $scopeCode
     * @return string
     */
    public function getCarrierTitle($carrierCode, $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        return $this->scopeConfig->getValue('carriers/' . $carrierCode . '/title', $scopeType, $scopeCode);
    }
}
