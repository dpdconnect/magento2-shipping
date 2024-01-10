<?php

namespace DpdConnect\Shipping\Helper;

use DpdConnect\Sdk\Resources\CacheInterface;
use Magento\Framework\App\CacheInterface as MagentoCacheInterface;

class DpdCache implements CacheInterface
{
    /**
     * @var MagentoCacheInterface
     */
    private $magentoCache;

    public function __construct(
        MagentoCacheInterface $magentoCache
    ) {
        $this->magentoCache = $magentoCache;
    }

    public function setCache($key, $data, $expire)
    {
        $this->magentoCache->save(serialize($data), $key, [], $expire);
    }

    public function getCache($key)
    {
        $data = unserialize($this->magentoCache->load($key));
        return $data;
    }
}
