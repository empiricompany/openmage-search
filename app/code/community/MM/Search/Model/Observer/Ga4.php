<?php
/**
 * Observer to add item_list_name to GA4 events for search-originated products
 *
 * @category   MM
 * @package    MM_Search
 */
class MM_Search_Model_Observer_Ga4
{
    const COOKIE_NAME = 'mm_search_products';
    
    /**
     * Add item_list_name and item_list_id to GA4 events for products clicked from search
     * 
     * This observer intercepts the googleanalytics_ga4_send_data_before event (native OpenMage)
     * and enriches e-commerce events with item_list_name and item_list_id parameters
     * for products that were clicked from search results.
     * 
     * Data is read from the 'mm_search_products' cookie written by JavaScript.
     * 
     * @param Varien_Event_Observer $observer
     */
    public function addSearchListName(Varien_Event_Observer $observer)
    {
        /** @var Varien_Object $ga4DataTransport */
        $ga4DataTransport = $observer->getEvent()->getGa4DataTransport();
        $events = $ga4DataTransport->getData();
        
        if (!is_array($events) || empty($events)) {
            return;
        }

        $searchProducts = $this->_getSearchProductsFromCookie();
        
        if (empty($searchProducts)) {
            return;
        }

        $eventsToModify = ['add_to_cart', 'remove_from_cart', 'view_cart', 'begin_checkout', 'purchase'];

        foreach ($events as $key => $eventData) {
            list($eventName, $eventParams) = $eventData;

            if (!in_array($eventName, $eventsToModify)) {
                continue;
            }

            if (!isset($eventParams['items']) || !is_array($eventParams['items'])) {
                continue;
            }

            foreach ($eventParams['items'] as $itemKey => $item) {
                $itemSku = $item['item_id'] ?? '';
                
                // Try exact SKU match first
                if ($itemSku && isset($searchProducts[$itemSku])) {
                    $searchQuery = $searchProducts[$itemSku];
                    $eventParams['items'][$itemKey]['item_list_name'] = 'search_results';
                    $eventParams['items'][$itemKey]['item_list_id'] = $searchQuery ?: 'search_results';
                } else {
                    // Fallback: match base SKU (without size suffix)
                    // Use case: user clicks configurable product "J1GJ2470-61" from search
                    // but add_to_cart event contains simple product "J1GJ2470-61-46.5" (with size)
                    $baseSku = preg_replace('/-[\d.]+$/', '', $itemSku);
                    if ($baseSku && $baseSku !== $itemSku && isset($searchProducts[$baseSku])) {
                        $searchQuery = $searchProducts[$baseSku];
                        $eventParams['items'][$itemKey]['item_list_name'] = 'search_results';
                        $eventParams['items'][$itemKey]['item_list_id'] = $searchQuery ?: 'search_results';
                    }
                }
            }

            $events[$key] = [$eventName, $eventParams];
        }

        $ga4DataTransport->setData($events);
    }

    /**
     * Read search products from cookie
     * 
     * @return array Map of SKU => query string
     */
    protected function _getSearchProductsFromCookie()
    {
        try {
            $cookieValue = Mage::app()->getRequest()->getCookie(self::COOKIE_NAME);
            
            if (empty($cookieValue)) {
                return [];
            }
            
            $products = json_decode($cookieValue, true);
            
            if (!is_array($products)) {
                return [];
            }
            
            return $products;
            
        } catch (Exception $e) {
            Mage::logException($e);
            return [];
        }
    }
}
