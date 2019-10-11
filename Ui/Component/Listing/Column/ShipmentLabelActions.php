<?php

namespace DpdConnect\Shipping\Ui\Component\Listing\Column;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class ShipmentLabelActions extends Column
{
    protected $urlBuilder;

    protected $storeManager;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $download = $this->urlBuilder->getUrl(
                    'dpd_shipping/label/download',
                    [
                        'entity_id' => $item['entity_id'],
                    ]
                );

                $item[$this->getData('name')] = [
                    'view' => [
                        'href' => $download,
                        'label' => __('Download')
                    ]
                ];
            }
        }

        return $dataSource;
    }
}
