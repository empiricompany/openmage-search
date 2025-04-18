<?php
/**
 * Adminhtml controller for MM Search
 */

use Typesense\Client;

class MM_Search_Adminhtml_TestconnectionController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Test connection
     * @todo Implement a proper test connection method
     * @return void
     */
    public function indexAction()
    {
        return $this;
        Mage::log("MM_Search - Controller reached", Zend_Log::DEBUG);
        Mage::log("Request URI: " . $this->getRequest()->getRequestUri(), Zend_Log::DEBUG);
        Mage::log("Request Method: " . $this->getRequest()->getMethod(), Zend_Log::DEBUG);
        
        $result = ['success' => false, 'message' => ''];
        
        if ($this->getRequest()->isPost()) {
            Mage::log("POST request received", Zend_Log::DEBUG);
            try {
                $apiKey = $this->getRequest()->getParam('api_key');
                $host = $this->getRequest()->getParam('host');
                $port = $this->getRequest()->getParam('port');
                $protocol = $this->getRequest()->getParam('protocol');
                
                $client = new Client([
                    'api_key' => $apiKey,
                    'nodes' => [
                        [
                            'host' => $host,
                            'port' => $port,
                            'protocol' => $protocol,
                            'path' => ''
                        ]
                    ],
                    'connection_timeout_seconds' => 5
                ]);
                
                $health = $client->health->retrieve();
                
                if (isset($health['status']) && $health['status'] === 'ok') {
                    $result['success'] = true;
                    $result['message'] = $this->__('Connection successful! Typesense server is healthy.');
                } else {
                    $result['message'] = $this->__('Connection established, but server health check failed.');
                }
            } catch (Exception $e) {
                $result['message'] = $this->__('Connection failed: %s', $e->getMessage());
            }
        } else {
            $result['message'] = $this->__('Invalid request method.');
        }
        
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
    
    /**
     * Check if user has permissions to access this controller
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return true; 
    }
}