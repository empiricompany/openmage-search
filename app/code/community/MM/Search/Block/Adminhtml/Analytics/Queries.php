<?php
/**
 * Popular Queries Container Block
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Block_Adminhtml_Analytics_Queries extends MM_Search_Block_Adminhtml_Analytics_Abstract
{
    /**
     * @var string Redirect parameter for create rules action
     */
    protected $_redirectParam = 'queries';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_blockGroup = 'mm_search';
        $this->_controller = 'adminhtml_analytics_queries';
        $this->_headerText = Mage::helper('mm_search')->__('Popular Search Queries');
        
        parent::__construct();
    }
}