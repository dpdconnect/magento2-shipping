<?php

namespace DpdConnect\Shipping\Model\Carrier;

use DpdConnect\Shipping\Config\Source\Settings\ContentType;
use DpdConnect\Shipping\Helper\DPDClient;
use DpdConnect\Shipping\Helper\DpdSettings;
use DpdConnect\Shipping\Helper\Services\OrderConvertService;
use DpdConnect\Shipping\Helper\Services\ShipmentLabelService;
use DpdConnect\Shipping\Model\ResourceModel\TablerateFactory;
use DpdConnect\Shipping\Services\ShipmentManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Shipment\Request;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Psr\Log\LoggerInterface;

abstract class AbstractCarrier extends \Magento\Shipping\Model\Carrier\AbstractCarrier
{
    /**
     * @var \Magento\Shipping\Model\Tracking\ResultFactory
     */
    public $trackFactory;
    /**
     * @var StatusFactory
     */
    public $_trackStatusFactory;
    /**
     * @var ResultFactory
     */
    public $rateFactory;
    /**
     * @var DpdSettings
     */
    public $dpdSettings;
    /**
     * @var DPDClient
     */
    public $dpdClient;
    /**
     * @var OrderConvertService
     */
    public $orderConvertService;
    /**
     * @var ResultFactory
     */
    public $_rateResultFactory;
    /**
     * @var TablerateFactory
     */
    public $_tablerateFactory;
    /**
     * @var MethodFactory
     */
    public $_rateMethodFactory;
    /**
     * @var Resolver
     */
    public $_localeResolver;
    /**
     * @var TimezoneInterface
     */
    public $_timezoneInterface;
    /**
     * @var ShipmentLabelService
     */
    private $shipmentLabelService;
    /**
     * @var ShipmentManager
     */
    private $shipmentManager;

    /**
     * AbstractCarrier constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param Resolver $localeResolver
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param TablerateFactory $tablerateFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param StatusFactory $trackStatusFactory
     * @param ResultFactory $rateFactory
     * @param DpdSettings $dpdSettings
     * @param DPDClient $dpdClient
     * @param OrderConvertService $orderConvertService
     * @param TimezoneInterface $timezoneInterface
     * @param ShipmentLabelService $shipmentLabelService
     * @param ShipmentManager $shipmentManager
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Resolver $localeResolver,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        TablerateFactory $tablerateFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        StatusFactory $trackStatusFactory,
        ResultFactory $rateFactory,
        DpdSettings $dpdSettings,
        DpdClient $dpdClient,
        OrderConvertService $orderConvertService,
        TimezoneInterface $timezoneInterface,
        ShipmentLabelService $shipmentLabelService,
        ShipmentManager $shipmentManager,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->trackFactory = $trackFactory;
        $this->_trackStatusFactory = $trackStatusFactory;
        $this->rateFactory = $rateFactory;
        $this->dpdSettings = $dpdSettings;
        $this->dpdClient = $dpdClient;
        $this->orderConvertService = $orderConvertService;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_tablerateFactory = $tablerateFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_localeResolver = $localeResolver;
        $this->_timezoneInterface = $timezoneInterface;
        $this->shipmentLabelService = $shipmentLabelService;
        $this->shipmentManager = $shipmentManager;
    }

    /**
     * Check if carrier has shipping label option available
     *
     * @return bool
     */
    public function isShippingLabelsAvailable()
    {
        return true;
    }

    /**
     * Return container types of carrier
     *
     * @param DataObject|null $params
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getContainerTypes(DataObject $params = null)
    {
        return [
            ContentType::CONTENT_TYPE_NON_DOC => __('Non Documents'),
            ContentType::CONTENT_TYPE_DOC => __('Documents')
        ];
    }

    /**
     * Do request to shipment.
     *
     * Implementation must be in overridden method
     *
     * @param Request $request
     * @return DataObject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function requestToShipment($request)
    {
        $shipment = $request->getOrderShipment();
        $packages = $request->getPackages();
        $resultLabels = $this->shipmentLabelService->generateLabelMultiPackage($shipment->getOrder(), false, $shipment, $packages);

        $data = [];
        foreach ($resultLabels as $resultLabel) {
            $label = base64_decode($resultLabel['label']);
            foreach ($resultLabel['parcelNumbers'] as $parcelNumber) {
                $data[] = [
                    'tracking_number' => $parcelNumber,
                    'label_content' => $label,
                ];
                $label = ' ';
            }
        }

        $result = new DataObject();
        $result->setData('info', $data); // Possibly add more data to this if it's supported in all Magento versions
        return $result;
    }
}
