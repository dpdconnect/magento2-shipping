<?php

namespace DpdConnect\Shipping\Ui\Component;

class ShipmentLabelProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        \DpdConnect\Shipping\Model\ResourceModel\ShipmentLabel\CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    public function getData()
    {
        $data = parent::getData();


        if (isset($data['items'])) {
            foreach ($data['items'] as &$item) {

                // Empty it because magento can't handle blob's in grids, it's not shown anyway
                $item['label'] = '';

                if ($item['is_return']) {
                    $item['is_return'] = __('Yes');
                } else {
                    $item['is_return'] = __('No');
                }
            }
        }
        return $data;
    }
}
