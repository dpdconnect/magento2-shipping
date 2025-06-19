<?php

declare(strict_types=1);

namespace DpdConnect\Shipping\Controller\Adminhtml\Search\System\Config;

use DpdConnect\Sdk\ClientBuilder;
use DpdConnect\Sdk\Common\HttpClient;
use DpdConnect\Sdk\Exceptions\AuthenticateException;
use DpdConnect\Sdk\Objects\MetaData;
use DpdConnect\Sdk\Objects\ObjectFactory;
use DpdConnect\Shipping\Helper\DpdCache;
use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\AdvancedSearch\Model\Client\ClientResolver;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\StripTags;
use Magento\Framework\Module\ModuleListInterface;
use DpdConnect\Shipping\Helper\DPDClient;
use Magento\Store\Api\StoreManagementInterface;
use Magento\Store\Model\ScopeInterface;
use DpdConnect\Sdk\CacheWrapper;
use Magento\Store\Model\StoreManagement;
use Magento\Store\Model\StoreManagerInterface;

class TestConnection extends Action implements HttpPostActionInterface
{
    /** @var string  */
    public const ADMIN_RESOURCE = 'Magento_Catalog::config_catalog';

    /**
     * @param Context $context
     * @param ClientResolver $clientResolver
     * @param JsonFactory $resultJsonFactory
     * @param StripTags $tagFilter
     * @param DpdSettings $dpdSettings
     * @param ModuleListInterface $moduleList
     * @param ProductMetadataInterface $productMetadata
     * @param Encryptor $crypt
     * @param DpdCache $dpdCache
     */
    public function __construct(
        Context $context,
        private readonly ClientResolver $clientResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly JsonFactory $resultJsonFactory,
        private readonly StripTags $tagFilter,
        private readonly DpdSettings $dpdSettings,
        private readonly ModuleListInterface $moduleList,
        private readonly ProductMetadataInterface $productMetadata,
        private readonly Encryptor $crypt,
        private readonly DpdCache $dpdCache
    ) {
        parent::__construct($context);
    }

    /**
     * Check for connection to server
     *
     * @return Json
     */
    public function execute()
    {
        $options = $this->getRequest()->getParams();

        $result = [
            'success' => false,
            'errorMessage' => '',
        ];

        try {
            if (empty($options['user']) || empty($options['password'])) {
                $result['errorMessage'] = 'Authentication failed';
            } else {
                $result['success'] = $this->authenticate($options);
            }
        } catch (LocalizedException $e) {
            $result['errorMessage'] = $e->getMessage();
        } catch (\Exception $e) {
            $message = __($e->getMessage());
            $result['errorMessage'] = $this->tagFilter->filter($message);
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($result);
    }

    /**
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function authenticate(array $options): bool
    {
        $storeCode = $this->storeManager->getStore()->getCode();
        $url = $this->dpdSettings->getValue(DpdSettings::API_ENDPOINT);

        $user     = $options['user'];
        $password = ($options['password'] === '******' && !is_null($this->dpdSettings->getValue(DpdSettings::ACCOUNT_PASSWORD,ScopeInterface::SCOPE_STORE, $storeCode)))
            ? $this->crypt->decrypt($this->dpdSettings->getValue(DpdSettings::ACCOUNT_PASSWORD,ScopeInterface::SCOPE_STORE, $storeCode))
            : $options['password'];

        $pluginVersion = $this->moduleList->getOne(DPDClient::MODULE_NAME)['setup_version'];

        $clientBuilder = new ClientBuilder($url, ObjectFactory::create(MetaData::class, [
            'webshopType'    => $this->productMetadata->getName() . ' ' . $this->productMetadata->getEdition(),
            'webshopVersion' => $this->productMetadata->getVersion(),
            'pluginVersion'  => $pluginVersion,
        ]));

        try{
            $cacheWrapper = new CacheWrapper();
            $result       = $cacheWrapper->getCachedList($user, false, 'dpd_token');
            $tokenClass   = $clientBuilder->buildAuthenticatedByPassword($user, $password)->getToken();

            if($result) {
                $cacheWrapper->storeCachedList(false, $user, 'dpd_token');
            }

            $tokenString = $tokenClass->getPublicJWTToken($user, $password);
            return $this->isTokenValid($tokenString);
        } catch (AuthenticateException) {
            //...
        }

        return false;
    }

    /**
     * @param string $token
     * @return bool
     * @DOC Mostly taken from \DpdConnect\Sdk\Resources\Token::isTokenValid(), its just private.
     */
    private function isTokenValid(string $token): bool
    {
        $explodedToken = explode('.', $token);

        // Check if token has header, payload and signature
        if (count($explodedToken) != 3) {
            return false;
        }

        list($header, $payload, $signature) = $explodedToken;

        $payload = json_decode(base64_decode($payload), true);

        // Check if token is expired
        // Subtract 5 minutes of token to prevent returning a shortly-expiring token
        if (time() >= (int)$payload['exp'] - (5*60)) {
            return false;
        }

        return true;
    }
}
