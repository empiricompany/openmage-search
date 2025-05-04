<?php

declare(strict_types=1);

use CmsIg\Seal\Adapter\Meilisearch\MeilisearchAdapter;
use Meilisearch\Client;

class MM_Search_Model_Api_Adapter_Meilisearch extends MM_Search_Model_Api_Adapter_Abstract implements MM_Search_Model_Api_AdapterInterface
{
    public function create(): CmsIg\Seal\Adapter\AdapterInterface
    {
        $host = $this->getHost();
        $protocol = $this->getProtocol();
        $port = $this->getPort();
        $apiKey = $this->getAdminApiKey();
        
        $url = sprintf('%s://%s:%d', $protocol, $host, $port);
        $client = new Client($url, $apiKey);
        
        return new MeilisearchAdapter($client);
    }
    
    public static function getLabel(): string
    {
        return 'Meilisearch';
    }

    public static function getType(): string
    {
        return 'meilisearch';
    }
}