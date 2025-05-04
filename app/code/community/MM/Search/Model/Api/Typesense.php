<?php

declare(strict_types=1);

use Http\Client\Curl\Client as CurlClient;
use Http\Discovery\Psr17FactoryDiscovery;
use CmsIg\Seal\Adapter\Typesense\TypesenseAdapter;
use Typesense\Client;

class MM_Search_Model_Api_Typesense extends MM_Search_Model_Api_Abstract implements MM_Search_Model_Api_Contract
{
    public function create(int $storeId): CmsIg\Seal\Adapter\AdapterInterface
    {       
        $host = $this->getHost($storeId);
        $protocol = $this->getProtocol($storeId);
        $port = $this->getPort($storeId);
        $apiKey = $this->getAdminApiKey($storeId);

        $connectionConfig = new Client(
            [
               'api_key' => $apiKey,
                'nodes' => [
                    [
                        'host' => $host,
                        'port' => $port,
                        'protocol' => $protocol
                    ]
                ],
               'client' => new CurlClient(Psr17FactoryDiscovery::findResponseFactory(), Psr17FactoryDiscovery::findStreamFactory()),
            ]
       );
        return new TypesenseAdapter($connectionConfig);
    }
}