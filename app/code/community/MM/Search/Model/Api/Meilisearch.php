<?php

declare(strict_types=1);

use Meilisearch\Client;
use CmsIg\Seal\Adapter\Meilisearch\MeilisearchAdapter;

class MM_Search_Model_Api_Meilisearch extends MM_Search_Model_Api_Abstract implements MM_Search_Model_Api_Contract
{
    public function create(int $storeId): CmsIg\Seal\Adapter\AdapterInterface
    {
        $host = $this->getHost($storeId);
        $protocol = $this->getProtocol($storeId);
        $port = $this->getPort($storeId);
        $apiKey = $this->getAdminApiKey($storeId);

        $connectionConfig = new Client("$protocol://$host:$port", $apiKey );
        return new MeilisearchAdapter($connectionConfig);
    }
}