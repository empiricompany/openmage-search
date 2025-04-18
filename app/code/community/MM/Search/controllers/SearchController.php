<?php
/**
 * Search controller
 */
class MM_Search_SearchController extends Mage_Core_Controller_Front_Action
{
    /**
     * Proxy action for Typesense API
     * @return ?Mage_Core_Controller_Response_Http
     */
    public function proxyAction()
    {
        try {
            /**
             * @var MM_Search_Helper_Data $helper
             */
            $helper = Mage::helper('mm_search');
            $host = $helper->getHost();
            $apiKey = $helper->getSearchOnlyApiKey();
            $port = $helper->getPort();
            $protocol = $helper->getProtocol();
            $url = "{$protocol}://{$host}:{$port}/multi_search";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            $requestBody = file_get_contents('php://input');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-typesense-api-key: '.$apiKey
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            
            $this->getResponse()
                ->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->setHeader('Access-Control-Allow-Headers', 'Content-Type')
                ->setHeader('Content-Type', 'application/json');
            
            if ($response === false) {
                Mage::throwException('Failed to connect to Typesense');
            }
            
            $this->getResponse()
                ->setHttpResponseCode($httpCode)
                ->setBody($response);
                
        } catch (Exception $e) {
            Mage::logException($e);

            $this->getResponse()
                ->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->setHeader('Access-Control-Allow-Headers', 'Content-Type')
                ->setHeader('Content-Type', 'application/json')
                ->setHttpResponseCode(500);
                
            return $this->getResponse()->setBody(json_encode([
                'error' => $e->getMessage()
            ]));
        }
    }
}