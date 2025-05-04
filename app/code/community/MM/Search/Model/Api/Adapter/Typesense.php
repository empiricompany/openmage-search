<?php

declare(strict_types=1);

use Http\Client\Curl\Client as CurlClient;
use Http\Discovery\Psr17FactoryDiscovery;
use CmsIg\Seal\Adapter\Typesense\TypesenseAdapter;
use Typesense\Client;

class MM_Search_Model_Api_Adapter_Typesense extends MM_Search_Model_Api_Adapter_Abstract implements MM_Search_Model_Api_AdapterInterface
{
    public function create(): CmsIg\Seal\Adapter\AdapterInterface
    {
        $host = $this->getHost();
        $protocol = $this->getProtocol();
        $port = $this->getPort();
        $apiKey = $this->getAdminApiKey();

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
                'client' => new CurlClient(
                    Psr17FactoryDiscovery::findResponseFactory(), 
                    Psr17FactoryDiscovery::findStreamFactory()
                ),
            ]
        );
        
        return new TypesenseAdapter($connectionConfig);
    }
    
    public static function getLabel(): string
    {
        return 'Typesense';
    }

    public static function getType(): string
    {
        return 'typesense';
    }
}