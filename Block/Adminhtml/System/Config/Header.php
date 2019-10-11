<?php

namespace DpdConnect\Shipping\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Header extends Field
{
    protected $_template = 'DpdConnect_Shipping::system/config/header.phtml';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        return $this->toHtml();
    }

    /**
     * @return string
     */
    public function getLogo()
    {
        return $this->getViewFileUrl('DpdConnect_Shipping::images/dpd-logo-transparant.png');
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->getViewFileUrl('DpdConnect_Shipping::images/dpd-icon.png');
    }

    /**
     * @return string
     */
    public function getSupportUrl()
    {
        return 'https://klantenservice.dpd.nl/';
    }

    /**
     * @return string
     */
    public function getDocumentationUrl()
    {
        return 'https://github.com/dpdconnect/magento2-shipping';
    }
}
