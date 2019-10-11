<?php

namespace DpdConnect\Shipping\Ui\Component;

use DpdConnect\Shipping\Model\ResourceModel\BatchJob\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class BatchJobProvider extends AbstractDataProvider
{
    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->request = $request;
        $this->collection = $collectionFactory->create();
    }
}
