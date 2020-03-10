<?php
namespace DpdConnect\Shipping\Ui\Component\Shipping;

use Magento\Ui\Component\Control\Action;

class CreateShipmentAction extends Action
{
    public function prepare()
    {
        $config = $this->getConfiguration();
        $context = $this->getContext();

        $config['url'] = $context->getUrl(
            $config['createShipmentAction'],
            ['order_id' => $context->getRequestParam('order_id')]
        );
        $this->setData('config', (array)$config);
        parent::prepare();
    }
}
