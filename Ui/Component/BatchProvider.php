<?php

namespace DpdConnect\Shipping\Ui\Component;

use DpdConnect\Shipping\Model\ResourceModel\Batch\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class BatchProvider extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }
}
